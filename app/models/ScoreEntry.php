<?php
namespace Models;

use Core\Model;

/**
 * Score entry header + per-series rows.
 *
 * One score_entries row per (relay, lane). The series rows hold the shot
 * grid (as JSON), inner-tens, sub-total, penalty and series-total. All
 * totals are stored so ranking/analysis services don't have to recompute.
 */
class ScoreEntry extends Model
{
    public const REMARKS = ['', 'dns', 'dnf', 'disqualified', 'other'];

    public static function findById(int $id): ?array
    {
        return static::row("SELECT * FROM score_entries WHERE id = ?", [$id]);
    }

    public static function findByRelayLane(int $relayId, int $laneId): ?array
    {
        return static::row(
            "SELECT * FROM score_entries WHERE relay_id = ? AND lane_id = ?",
            [$relayId, $laneId]
        );
    }

    /**
     * Locate an existing score-entry for an athlete on this event by their
     * competitor number — optionally constrained to a sport category so
     * that an athlete registered for multiple categories has independent
     * score rows per category. Returns the most-recently-updated match,
     * joined with the source relay + lane labels for the UI.
     */
    public static function findByCompetitor(int $eventId, int $compNo, ?int $sportCategoryId = null): ?array
    {
        if ($compNo <= 0 || $eventId <= 0) return null;
        $where  = "se.event_id = ? AND se.competitor_number = ?";
        $params = [$eventId, $compNo];
        if ($sportCategoryId !== null && $sportCategoryId > 0) {
            $where .= " AND se.sport_category_id = ?";
            $params[] = $sportCategoryId;
        }
        return static::row(
            "SELECT se.*,
                    r.relay_number AS src_relay_number,
                    l.lane_number  AS src_lane_number
               FROM score_entries se
          LEFT JOIN event_relays              r ON r.id = se.relay_id
          LEFT JOIN event_shooting_range_lanes l ON l.id = se.lane_id
              WHERE {$where}
              ORDER BY se.updated_at DESC, se.id DESC
              LIMIT 1",
            $params
        );
    }

    public static function series(int $entryId): array
    {
        return static::rows(
            "SELECT * FROM score_series WHERE score_entry_id = ? ORDER BY series_no",
            [$entryId]
        );
    }

    /**
     * Persist a score entry header + its series rows in one shot.
     *  $header  — score_entries column => value
     *  $series  — [ ['series_no'=>1, 'shots'=>[...], 'inner_tens'=>..,
     *               'sub_total'=>.., 'penalty'=>.., 'series_total'=>..], ... ]
     * Returns the score entry id.
     */
    public static function save(array $header, array $series, string $byName): int
    {
        $relayId = (int)($header['relay_id'] ?? 0);
        $laneId  = (int)($header['lane_id']  ?? 0);
        $compNo  = (int)($header['competitor_number'] ?? 0);
        $eventId = (int)($header['event_id'] ?? 0);

        $existingHere = ($relayId && $laneId)
            ? self::findByRelayLane($relayId, $laneId)
            : null;

        // Find any prior score row for this competitor in this category
        // anywhere on the event. Use findByCompetitor() so we also get the
        // source relay/lane labels for a clear error message if blocked.
        $catId = (int)($header['sport_category_id'] ?? 0) ?: null;
        $existingForComp = ($eventId > 0 && $compNo > 0)
            ? self::findByCompetitor($eventId, $compNo, $catId)
            : null;

        // Decide which row (if any) we're updating in place.
        //   - If the competitor already has a score row on THIS lane → straight update.
        //   - If the competitor already has a score row on a DIFFERENT lane → BLOCK.
        //     The Score Entry "athlete swap" feature is supposed to relocate
        //     the lane allocation, not silently move an existing score
        //     between lanes. The caller (ScoringController::save) clears
        //     the displaced athlete from the lane via LaneAllocation before
        //     calling us, so $existingHere (if any) is a stale row for the
        //     prior athlete and is overwritten in place — there is at most
        //     one score row per (relay, lane) by the UNIQUE constraint.
        $existing = null;
        if ($existingForComp) {
            if ((int)$existingForComp['relay_id'] === $relayId
                && (int)$existingForComp['lane_id']  === $laneId) {
                $existing = $existingForComp;
            } else {
                $srcR = $existingForComp['src_relay_number'] ?? ('#' . (int)$existingForComp['relay_id']);
                $srcL = $existingForComp['src_lane_number']  ?? ('#' . (int)$existingForComp['lane_id']);
                throw new \RuntimeException(
                    'Cannot save: competitor #' . $compNo
                    . ' already has a score recorded on Relay ' . $srcR
                    . ', Lane ' . $srcL . ' for this category. '
                    . 'Delete that entry before recording the score here.'
                );
            }
        } else {
            $existing = $existingHere;
        }

        // Aggregate totals so the header carries denormalised values for
        // ranking/analysis without re-aggregating the series rows.
        $grand = 0.0; $totalPenalty = 0.0; $innerTotal = 0;
        foreach ($series as $s) {
            $grand        += (float)($s['series_total'] ?? 0);
            $totalPenalty += (float)($s['penalty'] ?? 0);
            $innerTotal   += (int)($s['inner_tens'] ?? 0);
        }
        $header['grand_total']     = round($grand, 2);
        $header['total_penalty']   = round($totalPenalty, 2);
        $header['inner_ten_count'] = $innerTotal;
        $header['lane_status']     = $header['lane_status'] ?? 'saved';
        $header['updated_by_name'] = $byName;

        if ($existing) {
            $id = (int)$existing['id'];
            static::update('score_entries', $header, ['id' => $id]);
        } else {
            $header['created_by_name'] = $byName;
            $id = static::insert('score_entries', $header);
        }

        // Replace series rows transactionally enough for our scale.
        static::query("DELETE FROM score_series WHERE score_entry_id = ?", [$id]);
        foreach ($series as $s) {
            static::insert('score_series', [
                'score_entry_id' => $id,
                'series_no'      => (int)$s['series_no'],
                'shots_json'     => json_encode(array_values($s['shots'] ?? [])),
                'inner_tens'     => (int)($s['inner_tens'] ?? 0),
                'sub_total'      => round((float)($s['sub_total'] ?? 0), 2),
                'penalty'        => round((float)($s['penalty'] ?? 0), 2),
                'series_total'   => round((float)($s['series_total'] ?? 0), 2),
            ]);
        }
        return $id;
    }

    /**
     * Delete a score entry. The score_series rows are wiped via the
     * existing FK ON DELETE CASCADE, but we clean them explicitly first
     * in case the FK isn't present on legacy installs.
     */
    public static function delete(int $id): void
    {
        if ($id <= 0) return;
        static::query("DELETE FROM score_series  WHERE score_entry_id = ?", [$id]);
        static::query("DELETE FROM score_entries WHERE id = ?",             [$id]);
    }

    /**
     * Lane list for a relay with allocation context AND any existing score
     * row. Used by the lane-list page and the relay print report.
     */
    public static function lanesForRelay(int $relayId): array
    {
        return static::rows(
            "SELECT erl.lane_id, erl.category, erl.assigned_unit_id, erl.assigned_registration_id,
                    sc.abbreviation    AS category_abbr,
                    l.lane_number, l.lane_type, l.default_category,
                    eu.name  AS unit_name,
                    a.id     AS athlete_id, a.name AS athlete_name, a.passport_photo,
                    er.competitor_number, er.unit_id AS athlete_unit_id,
                    se.id              AS score_entry_id,
                    se.lane_status     AS score_status,
                    se.grand_total     AS score_total,
                    se.total_penalty   AS score_penalty,
                    se.inner_ten_count AS score_inner_tens,
                    se.remarks         AS score_remarks,
                    se.notes           AS score_notes,
                    se.athlete_id      AS score_athlete_id,
                    se.competitor_number AS score_competitor_number,
                    se.series_count    AS series_count,
                    (SELECT GROUP_CONCAT(ss.sub_total ORDER BY ss.series_no SEPARATOR ',')
                       FROM score_series ss
                      WHERE ss.score_entry_id = se.id) AS series_subs_csv
               FROM event_relay_lanes erl
               JOIN event_shooting_range_lanes l       ON l.id = erl.lane_id
          LEFT JOIN sport_categories sc                ON sc.name = erl.category
          LEFT JOIN event_units eu                     ON eu.id = erl.assigned_unit_id
          LEFT JOIN event_registrations er             ON er.id = erl.assigned_registration_id
          LEFT JOIN athletes a                         ON a.id = er.athlete_id
          LEFT JOIN score_entries se                   ON se.relay_id = erl.relay_id AND se.lane_id = erl.lane_id
              WHERE erl.relay_id = ?
              ORDER BY l.lane_number",
            [$relayId]
        );
    }

    /** Distinct categories that have at least one entered score on the event. */
    public static function categoriesWithResults(int $eventId): array
    {
        return static::rows(
            "SELECT DISTINCT sc.id, sc.name, sc.abbreviation
               FROM score_entries se
               JOIN sport_categories sc ON sc.id = se.sport_category_id
              WHERE se.event_id = ? AND se.lane_status IN ('saved','final')
              ORDER BY sc.name",
            [$eventId]
        );
    }

    /**
     * Participant-wise entered results across the event — every saved / final
     * score row with the competitor, category, event and totals. Optional
     * category filter. Used by the staff "Entered Results" quick view.
     */
    public static function enteredResultsForEvent(int $eventId, ?int $categoryId = null): array
    {
        $sql = "SELECT se.id               AS score_entry_id,
                       se.competitor_number,
                       se.grand_total, se.total_penalty, se.inner_ten_count,
                       se.remarks, se.lane_status, se.series_count,
                       se.relay_id, se.lane_id,
                       a.name              AS athlete_name,
                       sc.id               AS category_id,
                       sc.name             AS category_name,
                       sc.abbreviation     AS category_abbr,
                       sev.name            AS sport_event_name,
                       es.event_code       AS event_code,
                       eu.name             AS unit_name,
                       r.relay_number      AS relay_number,
                       l.lane_number       AS lane_number,
                       (SELECT GROUP_CONCAT(ss.sub_total ORDER BY ss.series_no SEPARATOR ',')
                          FROM score_series ss WHERE ss.score_entry_id = se.id) AS series_subs_csv
                  FROM score_entries se
             LEFT JOIN athletes a                    ON a.id  = se.athlete_id
             LEFT JOIN sport_categories sc           ON sc.id = se.sport_category_id
             LEFT JOIN event_sports es               ON es.id = se.event_sport_id
             LEFT JOIN sport_events sev              ON sev.id = es.sport_event_id
             LEFT JOIN event_units eu                ON eu.id = se.unit_id
             LEFT JOIN event_relays r                ON r.id  = se.relay_id
             LEFT JOIN event_shooting_range_lanes l  ON l.id  = se.lane_id
                 WHERE se.event_id = ? AND se.lane_status IN ('saved','final')";
        $params = [$eventId];
        if ($categoryId) {
            $sql     .= " AND se.sport_category_id = ?";
            $params[] = $categoryId;
        }
        $sql .= " ORDER BY sc.name, CAST(se.competitor_number AS UNSIGNED), se.competitor_number";
        return static::rows($sql, $params);
    }

    /** Look up an approved registration by event + competitor number. */
    public static function lookupCompetitor(int $eventId, int $compNo): ?array
    {
        $r = static::row(
            "SELECT er.id AS registration_id, er.athlete_id, er.competitor_number, er.unit_id,
                    a.name AS athlete_name, a.gender, a.date_of_birth, a.passport_photo,
                    eu.id  AS unit_id_only, eu.name AS unit_name
               FROM event_registrations er
               JOIN athletes a   ON a.id = er.athlete_id
          LEFT JOIN event_units eu ON eu.id = er.unit_id
              WHERE er.event_id = ? AND er.competitor_number = ?
                AND er.admin_review_status = 'approved'",
            [$eventId, $compNo]
        );
        if (!$r) return null;
        $cats = static::rows(
            "SELECT DISTINCT sc.id, sc.name, sc.abbreviation
               FROM event_registration_items eri
               JOIN event_sports     es ON es.id = eri.event_sport_id
               JOIN sport_events     se ON se.id = es.sport_event_id
               JOIN sport_categories sc ON sc.id = se.category_id
              WHERE eri.registration_id = ?",
            [(int)$r['registration_id']]
        );
        $r['categories'] = $cats;
        return $r;
    }

    /** Resolve the effective Series / Shots / Score-Type config for a category on an event. */
    public static function resolveCategoryConfig(int $eventId, int $categoryId): array
    {
        $cat = static::row(
            "SELECT id, name, abbreviation, inner_ten,
                    default_series_count, default_shots_per_series, default_score_type
               FROM sport_categories WHERE id = ?",
            [$categoryId]
        );
        // Pick the per-event override if any event_sports row for this event
        // points at the category (via sport_events) and carries its own values.
        $ov = static::row(
            "SELECT es.series_count, es.shots_per_series, es.score_type
               FROM event_sports es
               JOIN sport_events  sev ON sev.id = es.sport_event_id
              WHERE es.event_id = ? AND sev.category_id = ?
                AND (es.series_count IS NOT NULL
                  OR es.shots_per_series IS NOT NULL
                  OR es.score_type IS NOT NULL)
              ORDER BY es.id LIMIT 1",
            [$eventId, $categoryId]
        );
        return [
            'category_id'      => $categoryId,
            'category_name'    => $cat['name'] ?? null,
            'abbreviation'     => $cat['abbreviation'] ?? null,
            'inner_ten'        => (bool)($cat['inner_ten'] ?? 0),
            'series_count'     => (int)($ov['series_count']     ?? $cat['default_series_count']     ?? 6),
            'shots_per_series' => (int)($ov['shots_per_series'] ?? $cat['default_shots_per_series'] ?? 10),
            'score_type'       => (string)($ov['score_type']    ?? $cat['default_score_type']       ?? 'integer'),
        ];
    }
}
