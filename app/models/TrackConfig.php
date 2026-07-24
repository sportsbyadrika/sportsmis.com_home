<?php
namespace Models;

use Core\Model;

/**
 * Track / field configuration for the Athletics-Skating lane-allocation
 * workspace: per-event-sport event type (track|field) + number of tracks,
 * and the ordered list of rounds (Preliminary heats / Semifinal heats /
 * Final) with their heat counts.
 */
class TrackConfig extends Model
{
    public const ROUND_NAMES = ['Preliminary heats', 'Semifinal heats', 'Final'];

    /** Set the event type (and track count) for one event_sport row. */
    public static function setEventType(int $eventSportId, string $type, ?int $numTracks): void
    {
        $type = in_array($type, ['track', 'field'], true) ? $type : 'field';
        static::update('event_sports', [
            'track_event_type' => $type,
            'track_num_tracks' => $type === 'track' ? max(1, (int)$numTracks) : null,
        ], ['id' => $eventSportId]);
    }

    /** Rounds for a single event_sport, ordered. */
    public static function roundsFor(int $eventSportId): array
    {
        return static::rows(
            "SELECT * FROM event_sport_rounds WHERE event_sport_id = ? ORDER BY round_order, id",
            [$eventSportId]
        );
    }

    /** Rounds for many event_sport ids, grouped by event_sport_id. */
    public static function roundsForMany(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = static::rows(
            "SELECT * FROM event_sport_rounds WHERE event_sport_id IN ($ph) ORDER BY round_order, id",
            $ids
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r['event_sport_id']][] = $r;
        }
        return $out;
    }

    /** Append a round; the order is the next slot for that event_sport. */
    public static function addRound(int $eventSportId, string $name, int $numHeats): int
    {
        if (!in_array($name, self::ROUND_NAMES, true)) $name = self::ROUND_NAMES[0];
        $mx = static::row(
            "SELECT COALESCE(MAX(round_order), 0) AS mx FROM event_sport_rounds WHERE event_sport_id = ?",
            [$eventSportId]
        );
        $order = (int)($mx['mx'] ?? 0) + 1;
        return static::insert('event_sport_rounds', [
            'event_sport_id' => $eventSportId,
            'round_order'    => $order,
            'round_name'     => $name,
            'num_heats'      => max(1, $numHeats),
        ]);
    }

    public static function findRound(int $id): ?array
    {
        return static::row("SELECT * FROM event_sport_rounds WHERE id = ?", [$id]);
    }

    public static function deleteRound(int $id): void
    {
        static::query("DELETE FROM event_sport_rounds WHERE id = ?", [$id]);
    }

    // ── Round context + lane draw ────────────────────────────────────────────

    /**
     * One round joined with its event-sport context: track count, sport-event
     * name, event id + logo, approved-athlete count. Null if not found.
     */
    public static function roundContext(int $roundId): ?array
    {
        return static::row(
            "SELECT r.id AS round_id, r.round_order, r.round_name, r.num_heats,
                    es.id AS event_sport_id, es.event_id, es.event_code,
                    es.track_num_tracks, es.track_event_type,
                    sev.name AS sport_event_name, sc.name AS category_name,
                    e.name AS event_name, e.logo AS event_logo, e.event_date_from,
                    (SELECT COUNT(DISTINCT er.athlete_id)
                       FROM event_registration_items eri
                       JOIN event_registrations er ON er.id = eri.registration_id
                      WHERE eri.event_sport_id = es.id
                        AND er.admin_review_status = 'approved') AS approved
               FROM event_sport_rounds r
               JOIN event_sports es       ON es.id = r.event_sport_id
               JOIN events e              ON e.id  = es.event_id
          LEFT JOIN sport_events     sev  ON sev.id = es.sport_event_id
          LEFT JOIN sport_categories sc   ON sc.id  = sev.category_id
              WHERE r.id = ?",
            [$roundId]
        );
    }

    /** Is this the first (lowest-order) round for its event-sport? */
    public static function isFirstRound(int $eventSportId, int $roundOrder): bool
    {
        $r = static::row(
            "SELECT COUNT(*) AS c FROM event_sport_rounds
              WHERE event_sport_id = ? AND round_order < ?",
            [$eventSportId, $roundOrder]
        );
        return (int)($r['c'] ?? 0) === 0;
    }

    /** Lane assignments for a round, with athlete details, ordered heat, track. */
    public static function assignmentsFor(int $roundId): array
    {
        return static::rows(
            "SELECT tha.id, tha.heat_no, tha.track_no, tha.registration_id,
                    er.competitor_number, a.name AS athlete_name, a.date_of_birth,
                    eu.name AS unit_name
               FROM track_heat_assignments tha
               JOIN event_registrations er ON er.id = tha.registration_id
               JOIN athletes a             ON a.id = er.athlete_id
          LEFT JOIN event_units eu         ON eu.id = er.unit_id
              WHERE tha.round_id = ?
              ORDER BY tha.heat_no, tha.track_no",
            [$roundId]
        );
    }

    /**
     * Approved registrations for an event-sport (the pool for the first round),
     * excluding any already assigned in $roundId. Ordered by name.
     */
    public static function approvedPool(int $eventSportId, int $eventId, int $roundId): array
    {
        return static::rows(
            "SELECT er.id AS registration_id, er.competitor_number,
                    a.name AS athlete_name, a.date_of_birth, eu.name AS unit_name
               FROM event_registrations er
               JOIN event_registration_items eri ON eri.registration_id = er.id AND eri.event_sport_id = ?
               JOIN athletes a             ON a.id = er.athlete_id
          LEFT JOIN event_units eu         ON eu.id = er.unit_id
              WHERE er.event_id = ? AND er.admin_review_status = 'approved'
                AND er.id NOT IN (SELECT registration_id FROM track_heat_assignments WHERE round_id = ?)
              GROUP BY er.id, er.competitor_number, a.name, a.date_of_birth, eu.name
              ORDER BY a.name",
            [$eventSportId, $eventId, $roundId]
        );
    }

    /**
     * Assign a registration to the next free track in a heat. Returns the
     * track number, or 0 when the heat is full / the registration is already
     * placed / inputs are invalid.
     */
    public static function assignLane(int $roundId, int $registrationId, int $heatNo, int $numTracks): int
    {
        if ($registrationId <= 0 || $heatNo <= 0 || $numTracks <= 0) return 0;
        // Already placed anywhere in this round? (unique round+reg)
        $exists = static::row(
            "SELECT id FROM track_heat_assignments WHERE round_id = ? AND registration_id = ?",
            [$roundId, $registrationId]
        );
        if ($exists) return 0;
        // Tracks already used in this heat.
        $used = static::rows(
            "SELECT track_no FROM track_heat_assignments WHERE round_id = ? AND heat_no = ?",
            [$roundId, $heatNo]
        );
        $taken = array_map(fn($u) => (int)$u['track_no'], $used);
        for ($t = 1; $t <= $numTracks; $t++) {
            if (in_array($t, $taken, true)) continue;
            try {
                static::insert('track_heat_assignments', [
                    'round_id'        => $roundId,
                    'heat_no'         => $heatNo,
                    'track_no'        => $t,
                    'registration_id' => $registrationId,
                ]);
                return $t;
            } catch (\Throwable $e) {
                // Race on the unique index — try the next free track.
            }
        }
        return 0;
    }

    public static function unassignLane(int $roundId, int $registrationId): void
    {
        static::query(
            "DELETE FROM track_heat_assignments WHERE round_id = ? AND registration_id = ?",
            [$roundId, $registrationId]
        );
    }
}
