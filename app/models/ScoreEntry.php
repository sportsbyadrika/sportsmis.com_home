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
     * competitor number. There's no DB-level uniqueness on competitor, but
     * each athlete only ever has one active score row in practice; we
     * return the most-recently-updated row to be safe. Also joins the
     * relay + lane labels so the UI can tell the operator where the data
     * is currently sitting.
     */
    public static function findByCompetitor(int $eventId, int $compNo): ?array
    {
        if ($compNo <= 0 || $eventId <= 0) return null;
        return static::row(
            "SELECT se.*,
                    r.relay_number AS src_relay_number,
                    l.lane_number  AS src_lane_number
               FROM score_entries se
          LEFT JOIN event_relays              r ON r.id = se.relay_id
          LEFT JOIN event_shooting_range_lanes l ON l.id = se.lane_id
              WHERE se.event_id = ? AND se.competitor_number = ?
              ORDER BY se.updated_at DESC, se.id DESC
              LIMIT 1",
            [$eventId, $compNo]
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
        $existingForComp = null;
        if ($eventId > 0 && $compNo > 0) {
            $existingForComp = static::row(
                "SELECT * FROM score_entries
                  WHERE event_id = ? AND competitor_number = ?
                  ORDER BY updated_at DESC, id DESC LIMIT 1",
                [$eventId, $compNo]
            );
        }

        // Decide which row (if any) we're updating in place.
        $existing = null;
        if ($existingHere && $existingForComp
            && (int)$existingHere['id'] === (int)$existingForComp['id']) {
            // Same row — straightforward update.
            $existing = $existingHere;
        } elseif ($existingForComp) {
            // The competitor's score row lives at a different (relay, lane).
            // Move it to the destination — but only if the destination lane
            // is empty, otherwise we'd violate uq_relay_lane and silently
            // clobber another athlete's data.
            if ($existingHere) {
                throw new \RuntimeException(
                    'This lane already has a score entry. Clear it before moving '
                    . 'competitor #' . $compNo . '\'s scores here.'
                );
            }
            $existing = $existingForComp;
            // $header already carries the new relay_id + lane_id, which the
            // update below will write — that's the actual move.
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
     * Lane list for a relay with allocation context AND any existing score
     * row. Used by the lane-list page and the relay print report.
     */
    public static function lanesForRelay(int $relayId): array
    {
        return static::rows(
            "SELECT erl.lane_id, erl.category, erl.assigned_unit_id, erl.assigned_registration_id,
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
                    se.athlete_id      AS score_athlete_id,
                    se.competitor_number AS score_competitor_number,
                    (SELECT GROUP_CONCAT(ss.series_total ORDER BY ss.series_no SEPARATOR ',')
                       FROM score_series ss
                      WHERE ss.score_entry_id = se.id) AS series_totals_csv
               FROM event_relay_lanes erl
               JOIN event_shooting_range_lanes l       ON l.id = erl.lane_id
          LEFT JOIN event_units eu                     ON eu.id = erl.assigned_unit_id
          LEFT JOIN event_registrations er             ON er.id = erl.assigned_registration_id
          LEFT JOIN athletes a                         ON a.id = er.athlete_id
          LEFT JOIN score_entries se                   ON se.relay_id = erl.relay_id AND se.lane_id = erl.lane_id
              WHERE erl.relay_id = ?
              ORDER BY l.lane_number",
            [$relayId]
        );
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
