<?php
namespace Services;

use Models\{ScoreEntry, RelayStatusLog, Event};

/**
 * Public scoring API consumed by the score-entry controller AND the future
 * Rank-List / Team-Result / Certificate / Medal-Tally / Unit-Analysis
 * modules. Keeping all aggregation logic here means downstream modules
 * never touch score_entries / score_series directly.
 */
class ScoringService
{
    /**
     * Relays of an event with summary counts (lane count, lanes with at
     * least one score entry, current relay result status, lane-status mix).
     * Drives the Scoring landing page.
     */
    public static function relayList(int $eventId): array
    {
        return Event::rowsRaw(
            "SELECT r.id, r.relay_number, r.order_no, r.relay_date, r.match_time,
                    r.reporting_time, r.result_status,
                    (SELECT COUNT(*) FROM event_relay_lanes WHERE relay_id = r.id) AS lane_count,
                    (SELECT COUNT(*) FROM score_entries se WHERE se.relay_id = r.id) AS lines_count,
                    (SELECT COUNT(*) FROM score_entries se WHERE se.relay_id = r.id
                       AND se.lane_status = 'saved') AS lines_saved
               FROM event_relays r
              WHERE r.event_id = ?
              ORDER BY COALESCE(r.order_no, 999999), r.id",
            [$eventId]
        );
    }

    /** Result Status options + colour classes (single source of truth). */
    public static function statuses(): array
    {
        return [
            'pending'   => ['Pending',   'bg-warning text-dark'],
            'entry'     => ['Entry',     'bg-info-subtle text-info-emphasis'],
            'draft'     => ['Draft',     'bg-secondary'],
            'final'     => ['Final',     'bg-success'],
            'withheld'  => ['Withheld',  'bg-danger'],
        ];
    }

    public static function isLocked(string $relayStatus): bool
    {
        return $relayStatus === 'final';
    }

    /** Change a relay's result_status with audit. */
    public static function setRelayStatus(int $relayId, string $newStatus, string $byName, ?string $notes = null): bool
    {
        $allowed = array_keys(self::statuses());
        if (!in_array($newStatus, $allowed, true)) return false;
        $cur = Event::rowsRaw("SELECT result_status FROM event_relays WHERE id = ?", [$relayId]);
        $from = $cur[0]['result_status'] ?? null;
        if ($from === $newStatus) return true;
        Event::rowsRaw("UPDATE event_relays SET result_status = ? WHERE id = ?", [$newStatus, $relayId]);
        // If the relay flipped to Final, every saved score is locked. We
        // mirror the lane_status to match.
        if ($newStatus === 'final') {
            Event::rowsRaw("UPDATE score_entries SET lane_status = 'final' WHERE relay_id = ?", [$relayId]);
        } elseif ($from === 'final') {
            Event::rowsRaw("UPDATE score_entries SET lane_status = 'saved' WHERE relay_id = ?", [$relayId]);
        }
        RelayStatusLog::log($relayId, $from, $newStatus, $byName, $notes);
        return true;
    }

    /**
     * Compute totals from a posted shot grid. Centralised so the score-
     * entry save endpoint and any future bulk-import / admin-override
     * flows compute identical numbers.
     *
     * $rawSeries = [
     *   [ 'series_no' => 1, 'shots' => [1.0, 9.0, ...], 'inner_tens' => 1, 'penalty' => 0 ],
     *   ...
     * ]
     */
    public static function computeSeries(array $rawSeries): array
    {
        $out = [];
        foreach ($rawSeries as $s) {
            $shots = array_map(static fn($v) => self::normalizeShot($v), $s['shots'] ?? []);
            $sub   = 0.0;
            foreach ($shots as $v) $sub += (float)($v ?? 0);
            $pen   = max(0, (float)($s['penalty'] ?? 0));
            $tot   = round($sub - $pen, 2);
            $out[] = [
                'series_no'    => (int)($s['series_no'] ?? 0),
                'shots'        => $shots,
                'inner_tens'   => (int)($s['inner_tens'] ?? 0),
                'sub_total'    => round($sub, 2),
                'penalty'      => round($pen, 2),
                'series_total' => $tot,
            ];
        }
        return $out;
    }

    /** Translate a raw shot input — supports the "00 → 10" shortcut. */
    public static function normalizeShot($raw)
    {
        if ($raw === null || $raw === '' || $raw === '-') return null;
        $s = trim((string)$raw);
        if ($s === '00') return 10;            // operator shortcut
        if (!is_numeric($s)) return null;
        $v = (float)$s;
        return $v < 0 ? 0 : $v;
    }

    // ── Downstream-module read APIs ──────────────────────────────────────────
    // These are the entry points Rank List / Team Result / Certificates /
    // Medal Tally / Unit Analysis will call. They live here so the future
    // modules never query score_entries directly.

    /** All saved score entries for an event with athlete + unit + category context. */
    public static function eventScores(int $eventId, array $filters = []): array
    {
        $where  = ['se.event_id = ?'];
        $params = [$eventId];
        if (!empty($filters['category_id'])) {
            $where[] = 'se.sport_category_id = ?';
            $params[] = (int)$filters['category_id'];
        }
        if (!empty($filters['unit_id'])) {
            $where[] = 'se.unit_id = ?';
            $params[] = (int)$filters['unit_id'];
        }
        if (!empty($filters['relay_id'])) {
            $where[] = 'se.relay_id = ?';
            $params[] = (int)$filters['relay_id'];
        }
        if (!empty($filters['final_only'])) {
            $where[] = "se.lane_status = 'final'";
        }
        $whereSql = implode(' AND ', $where);
        return Event::rowsRaw(
            "SELECT se.*,
                    a.name AS athlete_name, a.gender, a.date_of_birth,
                    eu.name AS unit_name, eu.address AS unit_address,
                    sc.name AS category_name, sc.abbreviation AS category_abbr,
                    r.relay_number, r.order_no AS relay_order, r.relay_date, r.match_time
               FROM score_entries se
          LEFT JOIN athletes a            ON a.id  = se.athlete_id
          LEFT JOIN event_units eu        ON eu.id = se.unit_id
          LEFT JOIN sport_categories sc   ON sc.id = se.sport_category_id
          LEFT JOIN event_relays r        ON r.id  = se.relay_id
              WHERE {$whereSql}
              ORDER BY se.grand_total DESC, se.inner_ten_count DESC",
            $params
        );
    }

    /** Series rows for a single score entry — fuels series-wise analysis. */
    public static function entrySeries(int $scoreEntryId): array
    {
        return Event::rowsRaw(
            "SELECT * FROM score_series WHERE score_entry_id = ? ORDER BY series_no",
            [$scoreEntryId]
        );
    }

    /**
     * Recalculate trigger hook — after an admin override or bulk edit the
     * caller invokes this with the affected event_id and any cached
     * rank/medal-tally tables will be invalidated. No-op for now (no
     * cache layer yet) but kept as a stable seam.
     */
    public static function recalculate(int $eventId, array $context = []): void
    {
        /* placeholder — invalidate caches when rank/medal tables ship */
    }
}
