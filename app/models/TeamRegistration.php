<?php
namespace Models;

use Core\Model;

/**
 * Team Entry — athletes register a team (up to 3 members) for one
 * team-eligible sport event under an Event. One row per submitted team.
 */
class TeamRegistration extends Model
{
    public static function findById(int $id): ?array
    {
        return static::row("SELECT * FROM team_registrations WHERE id = ?", [$id]);
    }

    /** The single draft a captain is allowed to have per event. */
    public static function findDraftForCaptain(int $eventId, int $captainAthleteId): ?array
    {
        return static::row(
            "SELECT * FROM team_registrations
              WHERE event_id = ? AND athlete_id = ?
                AND (submitted_at IS NULL OR admin_review_status = 'returned')
              ORDER BY id DESC LIMIT 1",
            [$eventId, $captainAthleteId]
        );
    }

    public static function createDraft(int $eventId, int $athleteId, string $teamName, ?int $unitId): int
    {
        return static::insert('team_registrations', [
            'event_id'   => $eventId,
            'athlete_id' => $athleteId,
            'unit_id'    => $unitId,
            'team_name'  => $teamName,
            'status'     => 'draft',
        ]);
    }

    public static function updateRow(int $id, array $data): void
    {
        if (!$data) return;
        static::query(
            'UPDATE team_registrations SET ' . implode(',', array_map(fn($k) => "{$k}=?", array_keys($data)))
            . ' WHERE id = ?',
            [...array_values($data), $id]
        );
    }

    public static function isEditable(?array $team): bool
    {
        if (!$team) return true;
        $s = $team['admin_review_status'] ?? null;
        return $s === null || $s === '' || $s === 'returned';
    }

    /**
     * Create a team-entry draft from the Unit-user / Event-staff capture
     * screen. created_by_type is 'unit_user' or 'event_staff'.
     */
    public static function createForActor(array $data): int
    {
        return static::insert('team_registrations', array_merge([
            'status' => 'draft',
        ], $data));
    }

    /** Team entries created by a given actor (unit_user / event_staff). */
    public static function forCreator(string $type, int $id, int $eventId): array
    {
        return static::rows(
            "SELECT tr.*,
                    e.name AS event_name,
                    eu.name AS unit_name,
                    es.event_code, es.team_entry_fee,
                    es.team_member_count, es.reserve_count,
                    sp.name AS sport_name, se.name AS sport_event_name,
                    sc.name AS category_name,
                    (SELECT COUNT(*) FROM team_registration_members
                       WHERE team_registration_id = tr.id) AS members_count
               FROM team_registrations tr
               JOIN events e        ON e.id = tr.event_id
          LEFT JOIN event_units eu  ON eu.id = tr.unit_id
          LEFT JOIN event_sports es ON es.id = tr.event_sport_id
          LEFT JOIN sports sp       ON sp.id = es.sport_id
          LEFT JOIN sport_events se ON se.id = es.sport_event_id
          LEFT JOIN sport_categories sc ON sc.id = se.category_id
              WHERE tr.created_by_type = ? AND tr.created_by_id = ? AND tr.event_id = ?
              ORDER BY tr.registered_at DESC",
            [$type, $id, $eventId]
        );
    }

    /** Lookup with athlete + event + unit context for views. */
    public static function withContext(int $id): ?array
    {
        return static::row(
            "SELECT tr.*,
                    e.name AS event_name, e.event_date_from, e.event_date_to,
                    e.location, e.institution_id,
                    i.name AS institution_name,
                    a.name AS captain_name, a.mobile AS captain_mobile,
                    eu.name AS unit_name, eu.address AS unit_address,
                    es.event_code, es.team_entry_fee, es.entry_fee,
                    sp.name AS sport_name, se.name AS sport_event_name,
                    sc.name AS category_name, sc.id AS category_id,
                    COALESCE(
                        a.name,
                        (SELECT name FROM unit_users  uu WHERE uu.id = tr.created_by_id
                            AND tr.created_by_type = 'unit_user'),
                        (SELECT name FROM event_staff esf WHERE esf.id = tr.created_by_id
                            AND tr.created_by_type = 'event_staff')
                    ) AS submitted_by_name
               FROM team_registrations tr
               JOIN events e        ON e.id = tr.event_id
               JOIN institutions i  ON i.id = e.institution_id
          LEFT JOIN athletes a      ON a.id = tr.athlete_id
          LEFT JOIN event_units eu  ON eu.id = tr.unit_id
          LEFT JOIN event_sports es ON es.id = tr.event_sport_id
          LEFT JOIN sports sp       ON sp.id = es.sport_id
          LEFT JOIN sport_events se ON se.id = es.sport_event_id
          LEFT JOIN sport_categories sc ON sc.id = se.category_id
              WHERE tr.id = ?",
            [$id]
        );
    }

    /** All teams a captain has created. */
    public static function forAthlete(int $athleteId): array
    {
        return static::rows(
            "SELECT tr.*,
                    e.name AS event_name, e.event_date_from, e.event_date_to, e.location,
                    i.name AS institution_name,
                    eu.name AS unit_name,
                    es.event_code, es.team_entry_fee,
                    sp.name AS sport_name, se.name AS sport_event_name,
                    (SELECT COUNT(*) FROM team_registration_members
                       WHERE team_registration_id = tr.id) AS members_count
               FROM team_registrations tr
               JOIN events e        ON e.id = tr.event_id
               JOIN institutions i  ON i.id = e.institution_id
          LEFT JOIN event_units eu  ON eu.id = tr.unit_id
          LEFT JOIN event_sports es ON es.id = tr.event_sport_id
          LEFT JOIN sports sp       ON sp.id = es.sport_id
          LEFT JOIN sport_events se ON se.id = es.sport_event_id
              WHERE tr.athlete_id = ?
              ORDER BY tr.registered_at DESC",
            [$athleteId]
        );
    }

    /** All teams for a single event (institution admin / report view). */
    public static function forEvent(int $eventId, bool $approvedOnly = false): array
    {
        $where = "tr.event_id = ?";
        if ($approvedOnly) $where .= " AND tr.admin_review_status = 'approved'";
        return static::rows(
            "SELECT tr.*,
                    eu.name AS unit_name, eu.address AS unit_address,
                    es.event_code, es.team_entry_fee,
                    es.team_member_count, es.reserve_count,
                    sp.name AS sport_name, se.name AS sport_event_name,
                    sc.name AS category_name,
                    a.name AS captain_name,
                    COALESCE(
                        a.name,
                        (SELECT name FROM unit_users  uu WHERE uu.id = tr.created_by_id
                            AND tr.created_by_type = 'unit_user'),
                        (SELECT name FROM event_staff esf WHERE esf.id = tr.created_by_id
                            AND tr.created_by_type = 'event_staff')
                    ) AS submitted_by_name,
                    (SELECT COUNT(*) FROM team_registration_members
                       WHERE team_registration_id = tr.id) AS members_count
               FROM team_registrations tr
          LEFT JOIN athletes a      ON a.id = tr.athlete_id
          LEFT JOIN event_units eu  ON eu.id = tr.unit_id
          LEFT JOIN event_sports es ON es.id = tr.event_sport_id
          LEFT JOIN sports sp       ON sp.id = es.sport_id
          LEFT JOIN sport_events se ON se.id = es.sport_event_id
          LEFT JOIN sport_categories sc ON sc.id = se.category_id
              WHERE {$where}
              ORDER BY tr.submitted_at DESC, tr.registered_at DESC",
            [$eventId]
        );
    }

    /**
     * Approved participants eligible to be team members: athletes with an
     * approved registration on the event, in the given unit, who registered
     * for the chosen sport-event line. Used by the capture-screen member
     * dropdowns.
     */
    public static function memberCandidates(int $eventId, int $unitId, int $eventSportId): array
    {
        // Look up the event_sport's team_entry_mode so the eligibility
        // gate matches the row's configuration:
        //   'both'           — athlete must already be registered for
        //                       this specific sport-event individually
        //                       (Team is on top of Individual).
        //   'team_only'      — no individual registration exists for
        //                       this row by definition; ANY athlete from
        //                       the unit with a non-rejected event_
        //                       registration is eligible.
        //   'individual_only'— shouldn't reach here (excluded from the
        //                       category/event dropdowns), but guard
        //                       anyway and treat like 'both'.
        $modeRow = static::row(
            "SELECT COALESCE(team_entry_mode, 'both') AS mode
               FROM event_sports WHERE id = ?",
            [$eventSportId]
        );
        $mode = (string)($modeRow['mode'] ?? 'both');
        $requireItem = $mode !== 'team_only';

        // Also relax the approval gate from 'approved' to
        // 'approved'+'pending' so the Unit User can compose team
        // entries while individual registrations are still being
        // reviewed — rejected athletes stay excluded.
        $sql = "SELECT er.id        AS registration_id,
                       er.athlete_id,
                       er.competitor_number,
                       a.name        AS athlete_name,
                       a.gender      AS gender
                  FROM event_registrations er
                  JOIN athletes a ON a.id = er.athlete_id";
        $params = [$eventId, $unitId];
        if ($requireItem) {
            $sql .= "
                  JOIN event_registration_items eri ON eri.registration_id = er.id
                                                  AND eri.event_sport_id = ?";
            // event_sport_id param goes first because of the order of
            // placeholders in the resulting SQL. Match by re-building.
            $params = [$eventSportId, $eventId, $unitId];
        }
        $sql .= "
                 WHERE er.event_id = ? AND er.unit_id = ?
                   AND COALESCE(er.admin_review_status,'') <> 'rejected'
                 GROUP BY er.id
                 ORDER BY a.name";
        return static::rows($sql, $params);
    }

    /** Replace the full member list for a team (used by the capture screen). */
    public static function setMembers(int $teamId, array $members): void
    {
        static::query("DELETE FROM team_registration_members WHERE team_registration_id = ?", [$teamId]);
        $pos = 0;
        foreach ($members as $m) {
            $pos++;
            static::insert('team_registration_members', [
                'team_registration_id' => $teamId,
                'athlete_id'           => (int)$m['athlete_id'],
                'registration_id'      => (int)($m['registration_id'] ?? 0) ?: null,
                'competitor_number'    => (int)($m['competitor_number'] ?? 0),
                'position'             => $pos,
            ]);
        }
    }

    /** All teams across an institution (event admin landing). */
    public static function forInstitution(int $institutionId): array
    {
        return static::rows(
            "SELECT tr.*,
                    e.name AS event_name,
                    eu.name AS unit_name,
                    es.event_code, es.team_entry_fee,
                    sp.name AS sport_name, se.name AS sport_event_name,
                    a.name AS captain_name,
                    (SELECT COUNT(*) FROM team_registration_members
                       WHERE team_registration_id = tr.id) AS members_count
               FROM team_registrations tr
               JOIN events e      ON e.id = tr.event_id
               JOIN athletes a    ON a.id = tr.athlete_id
          LEFT JOIN event_units eu  ON eu.id = tr.unit_id
          LEFT JOIN event_sports es ON es.id = tr.event_sport_id
          LEFT JOIN sports sp       ON sp.id = es.sport_id
          LEFT JOIN sport_events se ON se.id = es.sport_event_id
              WHERE e.institution_id = ?
              ORDER BY tr.submitted_at DESC, tr.registered_at DESC",
            [$institutionId]
        );
    }

    /**
     * Team-eligible categories for an event: distinct sport_categories of
     * event_sports rows that carry a team_entry_fee.
     */
    /**
     * Distinct sport_categories with at least one team-eligible row on
     * the event. A row is team-eligible when its team_entry_mode is
     * 'both' or 'team_only'. COALESCE so legacy rows that pre-date the
     * column (no value set) still surface — the schema default is
     * 'both', but the COALESCE protects sub-second-old rows that the
     * ALTER hasn't backfilled yet on slower MySQL configurations.
     */
    public static function teamCategories(int $eventId): array
    {
        return static::rows(
            "SELECT DISTINCT sc.id, sc.name, sc.abbreviation
               FROM event_sports es
               JOIN sport_events     se ON se.id = es.sport_event_id
               JOIN sport_categories sc ON sc.id = se.category_id
              WHERE es.event_id = ?
                AND COALESCE(es.team_entry_mode, 'both') IN ('both','team_only')
              ORDER BY sc.name",
            [$eventId]
        );
    }

    /** Team-eligible sport-events under a category. */
    public static function teamEventsForCategory(int $eventId, int $categoryId): array
    {
        return static::rows(
            "SELECT es.id, es.event_code, es.team_entry_fee,
                    es.team_entry_mode, es.team_member_count, es.reserve_count,
                    s.name  AS sport_name,
                    se.name AS sport_event_name,
                    se.gender
               FROM event_sports es
               JOIN sports        s  ON s.id  = es.sport_id
               JOIN sport_events  se ON se.id = es.sport_event_id
              WHERE es.event_id = ? AND se.category_id = ?
                AND COALESCE(es.team_entry_mode, 'both') IN ('both','team_only')
              ORDER BY se.name",
            [$eventId, $categoryId]
        );
    }

    // ── Members ──────────────────────────────────────────────────────────────

    public static function members(int $teamId): array
    {
        // The competitor_number captured into team_registration_members at
        // member-add time can go stale: if the number is generated *later*
        // (via the Competitor Cards report) only event_registrations
        // gets updated, not the cached member row. Prefer the live value
        // from event_registrations.competitor_number, falling back to the
        // cached number for legacy rows where the registration link is
        // missing.
        return static::rows(
            "SELECT m.id, m.team_registration_id, m.athlete_id, m.registration_id,
                    m.position, m.created_at,
                    COALESCE(er.competitor_number, m.competitor_number) AS competitor_number,
                    a.name AS athlete_name, a.mobile AS athlete_mobile, a.gender,
                    eu.name AS unit_name
               FROM team_registration_members m
               JOIN athletes a              ON a.id = m.athlete_id
          LEFT JOIN event_registrations er  ON er.id = m.registration_id
          LEFT JOIN event_units eu          ON eu.id = er.unit_id
              WHERE m.team_registration_id = ?
              ORDER BY m.position, m.id",
            [$teamId]
        );
    }

    public static function addMember(array $data): int
    {
        return static::insert('team_registration_members', $data);
    }

    public static function removeMember(int $memberId): void
    {
        static::query("DELETE FROM team_registration_members WHERE id = ?", [$memberId]);
    }

    public static function memberCount(int $teamId): int
    {
        $r = static::row(
            "SELECT COUNT(*) AS c FROM team_registration_members WHERE team_registration_id = ?",
            [$teamId]
        );
        return (int)($r['c'] ?? 0);
    }

    /**
     * Look up an athlete by competitor number on an event. Validates that the
     * athlete is approved on the event, sits in the same unit as the team,
     * and isn't already a member of this team.
     *
     * Returns ['ok'=>true,'athlete'=>..., 'registration_id'=>...] or
     * ['ok'=>false,'error'=>'…'].
     */
    public static function lookupCompetitor(int $eventId, int $competitorNumber, int $teamId, ?int $expectedUnitId): array
    {
        $r = static::row(
            "SELECT er.id AS registration_id, er.athlete_id, er.unit_id,
                    er.admin_review_status,
                    a.name AS athlete_name, a.mobile AS athlete_mobile, a.gender,
                    eu.name AS unit_name
               FROM event_registrations er
               JOIN athletes a          ON a.id = er.athlete_id
          LEFT JOIN event_units eu      ON eu.id = er.unit_id
              WHERE er.event_id = ? AND er.competitor_number = ?",
            [$eventId, $competitorNumber]
        );
        if (!$r) {
            return ['ok' => false, 'error' => 'No athlete found with this competitor number for this event.'];
        }
        if (($r['admin_review_status'] ?? '') !== 'approved') {
            return ['ok' => false, 'error' => 'That athlete\'s registration is not approved yet.'];
        }
        if ($expectedUnitId && (int)$r['unit_id'] !== (int)$expectedUnitId) {
            return ['ok' => false, 'error' => 'Athlete belongs to a different Unit / Club / Institution from the team.'];
        }
        // Already a member?
        $dup = static::row(
            "SELECT id FROM team_registration_members
              WHERE team_registration_id = ? AND athlete_id = ?",
            [$teamId, (int)$r['athlete_id']]
        );
        if ($dup) {
            return ['ok' => false, 'error' => 'This athlete is already in the team.'];
        }
        return [
            'ok'              => true,
            'athlete_id'      => (int)$r['athlete_id'],
            'registration_id' => (int)$r['registration_id'],
            'athlete_name'    => $r['athlete_name'],
            'athlete_mobile'  => $r['athlete_mobile'],
            'gender'          => $r['gender'],
            'unit_name'       => $r['unit_name'],
        ];
    }
}
