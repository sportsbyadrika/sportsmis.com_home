<?php
namespace Models;

use Core\Model;

/**
 * Lane Allocation — assigns a Unit and an Athlete (registration) to each
 * active relay-lane (event_relay_lanes). Shared by the Event Staff admin
 * screen and the Unit-User self-service screen.
 *
 * A relay-lane is addressed by the (relay_id, lane_id) pair — the natural
 * composite key of event_relay_lanes.
 *
 * Modular by design: Scoring / Result Reports can read the same allocation
 * rows without schema changes.
 */
class LaneAllocation extends Model
{
    /** All active relay-lanes for an event with full allocation context. */
    public static function relayLanes(int $eventId, ?int $unitScope = null): array
    {
        $rows = static::rows(
            "SELECT r.id AS relay_id, r.relay_number, r.relay_date,
                    r.match_time, r.reporting_time,
                    erl.lane_id, erl.category,
                    erl.assigned_unit_id, erl.assigned_registration_id,
                    erl.allocated_by, erl.allocated_at,
                    l.lane_number, l.lane_type, l.default_category,
                    eu.name  AS unit_name, eu.address AS unit_address,
                    a.id     AS athlete_id, a.name AS athlete_name, a.passport_photo,
                    er.competitor_number, er.unit_id AS athlete_unit_id,
                    aeu.name AS athlete_unit_name
               FROM event_relays r
               JOIN event_relay_lanes erl              ON erl.relay_id = r.id
               JOIN event_shooting_range_lanes l       ON l.id = erl.lane_id
          LEFT JOIN event_units eu                     ON eu.id = erl.assigned_unit_id
          LEFT JOIN event_registrations er             ON er.id = erl.assigned_registration_id
          LEFT JOIN athletes a                         ON a.id = er.athlete_id
          LEFT JOIN event_units aeu                    ON aeu.id = er.unit_id
              WHERE r.event_id = ?
              ORDER BY r.relay_number + 0, r.relay_number, l.lane_number",
            [$eventId]
        );
        if ($unitScope) {
            $rows = array_values(array_filter($rows,
                fn($r) => (int)($r['assigned_unit_id'] ?? 0) === $unitScope));
        }
        // Per-lane events-registered label for the allocated athlete.
        foreach ($rows as &$r) {
            $r['events_label'] = '';
            if (!empty($r['assigned_registration_id'])) {
                $r['events_label'] = static::eventsLabelForRegistration((int)$r['assigned_registration_id']);
            }
        }
        unset($r);
        return $rows;
    }

    private static function eventsLabelForRegistration(int $regId): string
    {
        $r = static::rows(
            "SELECT se.name
               FROM event_registration_items eri
               JOIN event_sports  es ON es.id = eri.event_sport_id
               JOIN sport_events  se ON se.id = es.sport_event_id
              WHERE eri.registration_id = ?",
            [$regId]
        );
        return implode(', ', array_filter(array_map(fn($x) => $x['name'] ?? '', $r)));
    }

    /** Locate a single relay-lane row. */
    public static function findLane(int $relayId, int $laneId): ?array
    {
        return static::row(
            "SELECT erl.*, r.event_id
               FROM event_relay_lanes erl
               JOIN event_relays r ON r.id = erl.relay_id
              WHERE erl.relay_id = ? AND erl.lane_id = ?",
            [$relayId, $laneId]
        );
    }

    public static function updateLane(int $relayId, int $laneId, array $data, string $byLabel): void
    {
        $data['allocated_by'] = $byLabel;
        $data['allocated_at'] = date('Y-m-d H:i:s');
        $set = implode(',', array_map(fn($k) => "{$k}=?", array_keys($data)));
        static::query(
            "UPDATE event_relay_lanes SET {$set} WHERE relay_id = ? AND lane_id = ?",
            [...array_values($data), $relayId, $laneId]
        );
    }

    /** Units configured for an event, each with a count of lanes assigned. */
    public static function unitsWithCounts(int $eventId): array
    {
        return static::rows(
            "SELECT eu.id, eu.name, eu.address,
                    (SELECT COUNT(*) FROM event_relay_lanes erl
                       JOIN event_relays r ON r.id = erl.relay_id
                      WHERE r.event_id = ? AND erl.assigned_unit_id = eu.id) AS lane_count
               FROM event_units eu
              WHERE eu.event_id = ?
              ORDER BY eu.name",
            [$eventId, $eventId]
        );
    }

    /**
     * Pending athletes — approved registrations for the event (optionally
     * scoped to a unit and/or a category) that are NOT yet allotted to any
     * relay-lane in this event.
     */
    public static function pendingAthletes(int $eventId, ?int $unitId, ?string $category): array
    {
        $where  = ["er.event_id = ?", "er.admin_review_status = 'approved'"];
        $params = [$eventId];
        if ($unitId)   { $where[] = "er.unit_id = ?"; $params[] = $unitId; }
        if ($category) { $where[] = "sc.name = ?";    $params[] = $category; }
        $whereSql = implode(' AND ', $where);

        return static::rows(
            "SELECT er.id AS registration_id, er.competitor_number, er.unit_id,
                    a.id AS athlete_id, a.name AS athlete_name, a.passport_photo,
                    eu.name AS unit_name,
                    GROUP_CONCAT(DISTINCT se.name ORDER BY se.name SEPARATOR ', ') AS events_label,
                    GROUP_CONCAT(DISTINCT sc.name ORDER BY sc.name SEPARATOR ', ') AS categories
               FROM event_registrations er
               JOIN athletes a                   ON a.id = er.athlete_id
               JOIN event_registration_items eri  ON eri.registration_id = er.id
               JOIN event_sports es               ON es.id = eri.event_sport_id
               JOIN sport_events se               ON se.id = es.sport_event_id
               JOIN sport_categories sc           ON sc.id = se.category_id
          LEFT JOIN event_units eu                ON eu.id = er.unit_id
              WHERE {$whereSql}
                AND er.id NOT IN (
                    SELECT assigned_registration_id FROM event_relay_lanes erl
                      JOIN event_relays r2 ON r2.id = erl.relay_id
                     WHERE r2.event_id = ? AND erl.assigned_registration_id IS NOT NULL
                )
              GROUP BY er.id
              ORDER BY a.name",
            [...$params, $eventId]
        );
    }

    /**
     * Pivot: Unit × Event Category, each cell carrying
     *   registered / unit-assigned / athlete-allotted counts.
     * Returns ['categories'=>[...], 'rows'=>[unit => [cat => [...]]], ...].
     */
    public static function pivot(int $eventId): array
    {
        // Registered: distinct athletes per (unit, category).
        $reg = static::rows(
            "SELECT eu.name AS unit_name, sc.name AS category_name,
                    COUNT(DISTINCT er.athlete_id) AS c
               FROM event_registrations er
               JOIN event_registration_items eri ON eri.registration_id = er.id
               JOIN event_sports es              ON es.id = eri.event_sport_id
               JOIN sport_events se              ON se.id = es.sport_event_id
               JOIN sport_categories sc          ON sc.id = se.category_id
               JOIN event_units eu               ON eu.id = er.unit_id
              WHERE er.event_id = ? AND er.admin_review_status = 'approved'
              GROUP BY eu.name, sc.name",
            [$eventId]
        );
        // Unit-assigned lanes per (unit, category).
        $assigned = static::rows(
            "SELECT eu.name AS unit_name, erl.category AS category_name, COUNT(*) AS c
               FROM event_relay_lanes erl
               JOIN event_relays r  ON r.id = erl.relay_id
               JOIN event_units eu  ON eu.id = erl.assigned_unit_id
              WHERE r.event_id = ? AND erl.assigned_unit_id IS NOT NULL
              GROUP BY eu.name, erl.category",
            [$eventId]
        );
        // Allotted athletes per (athlete's unit, lane category).
        $allotted = static::rows(
            "SELECT eu.name AS unit_name, erl.category AS category_name,
                    COUNT(DISTINCT er.athlete_id) AS c
               FROM event_relay_lanes erl
               JOIN event_relays r            ON r.id = erl.relay_id
               JOIN event_registrations er    ON er.id = erl.assigned_registration_id
               JOIN event_units eu            ON eu.id = er.unit_id
              WHERE r.event_id = ?
              GROUP BY eu.name, erl.category",
            [$eventId]
        );

        $cats = [];
        $cell = []; // unit => cat => [reg,assigned,allotted]
        $ensure = function (&$cell, $unit, $cat) {
            if (!isset($cell[$unit][$cat])) {
                $cell[$unit][$cat] = ['reg' => 0, 'assigned' => 0, 'allotted' => 0];
            }
        };
        foreach ($reg as $x) {
            $u = $x['unit_name'] ?: '—'; $c = $x['category_name'] ?: '—';
            $cats[$c] = true; $ensure($cell, $u, $c);
            $cell[$u][$c]['reg'] = (int)$x['c'];
        }
        foreach ($assigned as $x) {
            $u = $x['unit_name'] ?: '—'; $c = $x['category_name'] ?: '—';
            $cats[$c] = true; $ensure($cell, $u, $c);
            $cell[$u][$c]['assigned'] = (int)$x['c'];
        }
        foreach ($allotted as $x) {
            $u = $x['unit_name'] ?: '—'; $c = $x['category_name'] ?: '—';
            $cats[$c] = true; $ensure($cell, $u, $c);
            $cell[$u][$c]['allotted'] = (int)$x['c'];
        }
        $categories = array_keys($cats);
        sort($categories);
        ksort($cell);
        return ['categories' => $categories, 'rows' => $cell];
    }

    /** Most recent allocation change for an event (timestamp + user). */
    public static function lastModified(int $eventId): ?array
    {
        return static::row(
            "SELECT erl.allocated_by, erl.allocated_at
               FROM event_relay_lanes erl
               JOIN event_relays r ON r.id = erl.relay_id
              WHERE r.event_id = ? AND erl.allocated_at IS NOT NULL
              ORDER BY erl.allocated_at DESC LIMIT 1",
            [$eventId]
        );
    }

    /** Distinct relay numbers for an event (filter dropdowns). */
    public static function relayNumbers(int $eventId): array
    {
        $r = static::rows(
            "SELECT DISTINCT relay_number FROM event_relays WHERE event_id = ?
              ORDER BY relay_number + 0, relay_number",
            [$eventId]
        );
        return array_map(fn($x) => $x['relay_number'], $r);
    }

    /**
     * Map of category name => abbreviation for the categories configured on
     * an event (used to badge the Relay × Lane pivot cells).
     */
    public static function categoryAbbr(int $eventId): array
    {
        $rows = static::rows(
            "SELECT DISTINCT sc.name, sc.abbreviation
               FROM event_sports es
               JOIN sport_events se      ON se.id = es.sport_event_id
               JOIN sport_categories sc  ON sc.id = se.category_id
              WHERE es.event_id = ?",
            [$eventId]
        );
        $map = [];
        foreach ($rows as $r) {
            if (!empty($r['name']) && !empty($r['abbreviation'])) {
                $map[$r['name']] = $r['abbreviation'];
            }
        }
        return $map;
    }
}
