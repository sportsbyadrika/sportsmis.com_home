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
    public const ROUND_NAMES = ['Preliminary heats', 'Quarterfinal heats', 'Semifinal heats', 'Final'];

    public const RESULT_UNITS = ['time' => 'Time', 'height' => 'Meter Height', 'length' => 'Meter Length'];

    /** Set the event type (track count + laps + result unit) for one event_sport row. */
    public static function setEventType(int $eventSportId, string $type, ?int $numTracks, ?int $numLaps = null, string $resultUnit = 'time'): void
    {
        $type = in_array($type, ['track', 'field'], true) ? $type : 'field';
        $resultUnit = isset(self::RESULT_UNITS[$resultUnit]) ? $resultUnit : 'time';
        static::update('event_sports', [
            'track_event_type'  => $type,
            'track_num_tracks'  => $type === 'track' ? max(1, (int)$numTracks) : null,
            'track_num_laps'    => $type === 'track' && (int)$numLaps > 0 ? (int)$numLaps : null,
            'track_result_unit' => $resultUnit,
        ], ['id' => $eventSportId]);
    }

    /**
     * SELECT column list for a round row plus three live counts:
     *  - assigned_count  : participants placed in the round (heat assignments)
     *  - result_count    : assignments that have a recorded result (time or rank)
     *  - published_count : assignments whose result is published (public-visible)
     */
    private const ROUND_COUNTS_SELECT =
        "r.*,
         (SELECT COUNT(*) FROM track_heat_assignments tha
           WHERE tha.round_id = r.id) AS assigned_count,
         (SELECT COUNT(*) FROM track_heat_assignments tha
           WHERE tha.round_id = r.id
             AND ((tha.result_time IS NOT NULL AND tha.result_time <> '')
                  OR tha.result_rank IS NOT NULL)) AS result_count,
         (SELECT COUNT(*) FROM track_heat_assignments tha
           WHERE tha.round_id = r.id AND tha.is_published = 1) AS published_count";

    /** Rounds for a single event_sport, ordered, with participant/result counts. */
    public static function roundsFor(int $eventSportId): array
    {
        return static::rows(
            "SELECT " . self::ROUND_COUNTS_SELECT . "
               FROM event_sport_rounds r
              WHERE r.event_sport_id = ? ORDER BY r.round_order, r.id",
            [$eventSportId]
        );
    }

    /** Rounds for many event_sport ids, grouped by event_sport_id, with counts. */
    public static function roundsForMany(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = static::rows(
            "SELECT " . self::ROUND_COUNTS_SELECT . "
               FROM event_sport_rounds r
              WHERE r.event_sport_id IN ($ph) ORDER BY r.round_order, r.id",
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

    /** Append one heat to a round. Returns the new heat count. */
    public static function addHeat(int $roundId): int
    {
        $r = static::findRound($roundId);
        if (!$r) return 0;
        $n = (int)$r['num_heats'] + 1;
        static::update('event_sport_rounds', ['num_heats' => $n], ['id' => $roundId]);
        return $n;
    }

    /**
     * Remove the last heat of a round when it holds no athletes. Returns a
     * short status: 'ok', 'has_athletes', 'min' (can't drop below one heat),
     * or 'not_found'.
     */
    public static function deleteLastEmptyHeat(int $roundId): string
    {
        $r = static::findRound($roundId);
        if (!$r) return 'not_found';
        $heats = (int)$r['num_heats'];
        if ($heats <= 1) return 'min';
        $used = static::row(
            "SELECT COUNT(*) AS c FROM track_heat_assignments WHERE round_id = ? AND heat_no = ?",
            [$roundId, $heats]
        );
        if ((int)($used['c'] ?? 0) > 0) return 'has_athletes';
        static::update('event_sport_rounds', ['num_heats' => $heats - 1], ['id' => $roundId]);
        return 'ok';
    }

    /**
     * All approved registrations for an event-sport — the flat participants
     * list, ordered by chest number then name.
     */
    public static function approvedParticipants(int $eventSportId, int $eventId): array
    {
        return static::rows(
            "SELECT er.id AS registration_id, er.competitor_number,
                    a.name AS athlete_name, a.date_of_birth, eu.name AS unit_name
               FROM event_registrations er
               JOIN event_registration_items eri ON eri.registration_id = er.id AND eri.event_sport_id = ?
               JOIN athletes a             ON a.id = er.athlete_id
          LEFT JOIN event_units eu         ON eu.id = er.unit_id
              WHERE er.event_id = ? AND er.admin_review_status = 'approved'
              GROUP BY er.id, er.competitor_number, a.name, a.date_of_birth, eu.name
              ORDER BY (er.competitor_number IS NULL), er.competitor_number, a.name",
            [$eventSportId, $eventId]
        );
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
                    es.track_num_tracks, es.track_event_type, es.track_num_laps,
                    sev.name AS sport_event_name, sc.name AS category_name,
                    e.name AS event_name, e.logo AS event_logo, e.event_date_from,
                    (SELECT COUNT(DISTINCT er.athlete_id)
                       FROM event_registration_items eri
                       JOIN event_registrations er ON er.id = eri.registration_id
                       JOIN athletes a2            ON a2.id = er.athlete_id
                      WHERE eri.event_sport_id = es.id
                        AND er.event_id = es.event_id
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
                    tha.result_time, tha.result_rank, tha.is_qualified, tha.is_published,
                    er.competitor_number, a.name AS athlete_name, a.date_of_birth,
                    a.passport_photo AS photo, eu.name AS unit_name
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
              ORDER BY (eu.name IS NULL OR eu.name = ''), eu.name, a.name",
            [$eventSportId, $eventId, $roundId]
        );
    }

    /**
     * Athletes who were marked Qualified in a previous round — the pool for the
     * next round. Excludes anyone already assigned in the current round.
     * Ordered by institution then name (mirrors approvedPool).
     */
    public static function qualifiedPool(int $currentRoundId, int $prevRoundId): array
    {
        return static::rows(
            "SELECT er.id AS registration_id, er.competitor_number,
                    a.name AS athlete_name, a.date_of_birth, eu.name AS unit_name,
                    tha.result_rank AS prev_rank
               FROM track_heat_assignments tha
               JOIN event_registrations er ON er.id = tha.registration_id
               JOIN athletes a             ON a.id = er.athlete_id
          LEFT JOIN event_units eu         ON eu.id = er.unit_id
              WHERE tha.round_id = ? AND tha.is_qualified = 1
                AND tha.registration_id NOT IN
                    (SELECT registration_id FROM track_heat_assignments WHERE round_id = ?)
              ORDER BY (eu.name IS NULL OR eu.name = ''), eu.name, a.name",
            [$prevRoundId, $currentRoundId]
        );
    }

    /**
     * Place a registration in a specific (heat, track). Returns the track
     * number on success, or 0 when the track is taken, the registration is
     * already placed elsewhere in the round, or inputs are invalid.
     */
    public static function assignLane(int $roundId, int $registrationId, int $heatNo, int $trackNo, int $numTracks): int
    {
        if ($registrationId <= 0 || $heatNo <= 0 || $trackNo <= 0 || $trackNo > max(1, $numTracks)) return 0;
        // Already placed anywhere in this round? (unique round+reg)
        $exists = static::row(
            "SELECT id FROM track_heat_assignments WHERE round_id = ? AND registration_id = ?",
            [$roundId, $registrationId]
        );
        if ($exists) return 0;
        // Track already occupied in this heat?
        $taken = static::row(
            "SELECT id FROM track_heat_assignments WHERE round_id = ? AND heat_no = ? AND track_no = ?",
            [$roundId, $heatNo, $trackNo]
        );
        if ($taken) return 0;
        try {
            static::insert('track_heat_assignments', [
                'round_id'        => $roundId,
                'heat_no'         => $heatNo,
                'track_no'        => $trackNo,
                'registration_id' => $registrationId,
            ]);
            return $trackNo;
        } catch (\Throwable $e) {
            return 0;   // race on the unique index
        }
    }

    /**
     * Clear the recorded results (time, rank, qualified, published) for every
     * assignment in one heat of a round. Returns the number of rows cleared.
     */
    public static function clearHeatResults(int $roundId, int $heatNo): int
    {
        static::query(
            "UPDATE track_heat_assignments
                SET result_time = NULL, result_rank = NULL, is_qualified = 0, is_published = 0
              WHERE round_id = ? AND heat_no = ?",
            [$roundId, $heatNo]
        );
        $r = static::row(
            "SELECT COUNT(*) AS c FROM track_heat_assignments WHERE round_id = ? AND heat_no = ?",
            [$roundId, $heatNo]
        );
        return (int)($r['c'] ?? 0);
    }

    public static function unassignLane(int $roundId, int $registrationId): void
    {
        static::query(
            "DELETE FROM track_heat_assignments WHERE round_id = ? AND registration_id = ?",
            [$roundId, $registrationId]
        );
    }

    // ── Team events (relays / team entries) ──────────────────────────────────

    /** SQL fragment: members of a team as "BIB Name, BIB Name" (playing order). */
    private const TEAM_MEMBERS_SQL =
        "(SELECT GROUP_CONCAT(CONCAT(m.competitor_number, ' ', am.name) ORDER BY m.position, m.id SEPARATOR ', ')
            FROM team_registration_members m JOIN athletes am ON am.id = m.athlete_id
           WHERE m.team_registration_id = %s)";

    /** True when the event-sport has any approved team registration. */
    public static function isTeamEventSport(int $eventSportId): bool
    {
        $r = static::row(
            "SELECT COUNT(*) AS c FROM team_registrations
              WHERE event_sport_id = ? AND admin_review_status = 'approved'",
            [$eventSportId]
        );
        return (int)($r['c'] ?? 0) > 0;
    }

    /** Approved team count for an event-sport. */
    public static function approvedTeamCount(int $eventSportId): int
    {
        $r = static::row(
            "SELECT COUNT(*) AS c FROM team_registrations
              WHERE event_sport_id = ? AND admin_review_status = 'approved'",
            [$eventSportId]
        );
        return (int)($r['c'] ?? 0);
    }

    /** Team lane assignments for a round: team name, relay code, members, result. */
    public static function teamAssignmentsFor(int $roundId): array
    {
        $mem = sprintf(self::TEAM_MEMBERS_SQL, 'tr.id');
        return static::rows(
            "SELECT tha.id, tha.heat_no, tha.track_no, tha.team_registration_id,
                    tha.result_time, tha.result_rank, tha.is_qualified, tha.is_published,
                    tr.team_name, eu.name AS unit_name, eu.relay_code,
                    {$mem} AS members
               FROM track_heat_assignments tha
               JOIN team_registrations tr ON tr.id = tha.team_registration_id
          LEFT JOIN event_units eu        ON eu.id = tr.unit_id
              WHERE tha.round_id = ?
              ORDER BY tha.heat_no, tha.track_no",
            [$roundId]
        );
    }

    /** Members of a team (playing order) with chest number, name and photo. */
    public static function teamMembers(int $teamRegistrationId): array
    {
        return static::rows(
            "SELECT m.competitor_number AS chest, am.name AS athlete_name,
                    am.passport_photo AS photo
               FROM team_registration_members m
               JOIN athletes am ON am.id = m.athlete_id
              WHERE m.team_registration_id = ?
              ORDER BY m.position, m.id",
            [$teamRegistrationId]
        );
    }

    /** All approved teams for an event-sport (flat list — for the participants report). */
    public static function approvedTeams(int $eventSportId, int $eventId): array
    {
        $mem = sprintf(self::TEAM_MEMBERS_SQL, 'tr.id');
        return static::rows(
            "SELECT tr.id AS team_registration_id, tr.team_name,
                    eu.name AS unit_name, eu.relay_code, {$mem} AS members
               FROM team_registrations tr
          LEFT JOIN event_units eu ON eu.id = tr.unit_id
              WHERE tr.event_id = ? AND tr.event_sport_id = ? AND tr.admin_review_status = 'approved'
              ORDER BY (eu.name IS NULL OR eu.name = ''), eu.name, tr.team_name",
            [$eventId, $eventSportId]
        );
    }

    /** Approved teams for an event-sport, excluding any already drawn in $roundId. */
    public static function approvedTeamPool(int $eventSportId, int $eventId, int $roundId): array
    {
        $mem = sprintf(self::TEAM_MEMBERS_SQL, 'tr.id');
        return static::rows(
            "SELECT tr.id AS team_registration_id, tr.team_name,
                    eu.name AS unit_name, eu.relay_code, {$mem} AS members
               FROM team_registrations tr
          LEFT JOIN event_units eu ON eu.id = tr.unit_id
              WHERE tr.event_id = ? AND tr.event_sport_id = ? AND tr.admin_review_status = 'approved'
                AND tr.id NOT IN (SELECT team_registration_id FROM track_heat_assignments
                                   WHERE round_id = ? AND team_registration_id IS NOT NULL)
              ORDER BY (eu.name IS NULL OR eu.name = ''), eu.name, tr.team_name",
            [$eventId, $eventSportId, $roundId]
        );
    }

    /** Teams marked Qualified in a previous round — pool for the next round. */
    public static function qualifiedTeamPool(int $currentRoundId, int $prevRoundId): array
    {
        $mem = sprintf(self::TEAM_MEMBERS_SQL, 'tr.id');
        return static::rows(
            "SELECT tr.id AS team_registration_id, tr.team_name,
                    eu.name AS unit_name, eu.relay_code, {$mem} AS members,
                    tha.result_rank AS prev_rank
               FROM track_heat_assignments tha
               JOIN team_registrations tr ON tr.id = tha.team_registration_id
          LEFT JOIN event_units eu        ON eu.id = tr.unit_id
              WHERE tha.round_id = ? AND tha.is_qualified = 1
                AND tha.team_registration_id NOT IN
                    (SELECT team_registration_id FROM track_heat_assignments
                      WHERE round_id = ? AND team_registration_id IS NOT NULL)
              ORDER BY (eu.name IS NULL OR eu.name = ''), eu.name, tr.team_name",
            [$prevRoundId, $currentRoundId]
        );
    }

    /** Place a team in a specific (heat, track). Returns the track no or 0. */
    public static function assignTeamLane(int $roundId, int $teamId, int $heatNo, int $trackNo, int $numTracks): int
    {
        if ($teamId <= 0 || $heatNo <= 0 || $trackNo <= 0 || $trackNo > max(1, $numTracks)) return 0;
        if (static::row("SELECT id FROM track_heat_assignments WHERE round_id = ? AND team_registration_id = ?",
                [$roundId, $teamId])) return 0;
        if (static::row("SELECT id FROM track_heat_assignments WHERE round_id = ? AND heat_no = ? AND track_no = ?",
                [$roundId, $heatNo, $trackNo])) return 0;
        try {
            static::insert('track_heat_assignments', [
                'round_id'             => $roundId,
                'heat_no'              => $heatNo,
                'track_no'             => $trackNo,
                'team_registration_id' => $teamId,
            ]);
            return $trackNo;
        } catch (\Throwable $e) { return 0; }
    }

    public static function unassignTeam(int $roundId, int $teamId): void
    {
        static::query(
            "DELETE FROM track_heat_assignments WHERE round_id = ? AND team_registration_id = ?",
            [$roundId, $teamId]
        );
    }

    /** Save a team's recorded result on its lane assignment. */
    public static function saveTeamResult(int $roundId, int $teamId, ?string $time, ?int $rank, bool $qualified, bool $published = false): void
    {
        $time = $time !== null ? trim($time) : '';
        static::query(
            "UPDATE track_heat_assignments
                SET result_time = ?, result_rank = ?, is_qualified = ?, is_published = ?, updated_at = NOW()
              WHERE round_id = ? AND team_registration_id = ?",
            [$time !== '' ? $time : null, ($rank && $rank > 0) ? $rank : null, $qualified ? 1 : 0, $published ? 1 : 0,
             $roundId, $teamId]
        );
    }

    /**
     * Save the recorded result (time, rank, qualified flag) for one lane
     * assignment within a round. Rank of 0/blank clears to NULL.
     */
    public static function saveResult(int $roundId, int $registrationId, ?string $time, ?int $rank, bool $qualified, bool $published = false): void
    {
        $time = $time !== null ? trim($time) : '';
        static::query(
            "UPDATE track_heat_assignments
                SET result_time = ?, result_rank = ?, is_qualified = ?, is_published = ?, updated_at = NOW()
              WHERE round_id = ? AND registration_id = ?",
            [$time !== '' ? $time : null, ($rank && $rank > 0) ? $rank : null, $qualified ? 1 : 0, $published ? 1 : 0,
             $roundId, $registrationId]
        );
    }
}
