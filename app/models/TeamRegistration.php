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

    /** Lookup with athlete + event + unit context for views. */
    public static function withContext(int $id): ?array
    {
        return static::row(
            "SELECT tr.*,
                    e.name AS event_name, e.event_date_from, e.event_date_to,
                    e.location, e.institution_id, e.payment_modes,
                    i.name AS institution_name,
                    a.name AS captain_name, a.mobile AS captain_mobile,
                    eu.name AS unit_name, eu.address AS unit_address,
                    es.event_code, es.team_entry_fee, es.entry_fee,
                    sp.name AS sport_name, se.name AS sport_event_name
               FROM team_registrations tr
               JOIN events e        ON e.id = tr.event_id
               JOIN institutions i  ON i.id = e.institution_id
               JOIN athletes a      ON a.id = tr.athlete_id
          LEFT JOIN event_units eu  ON eu.id = tr.unit_id
          LEFT JOIN event_sports es ON es.id = tr.event_sport_id
          LEFT JOIN sports sp       ON sp.id = es.sport_id
          LEFT JOIN sport_events se ON se.id = es.sport_event_id
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
    public static function forEvent(int $eventId): array
    {
        return static::rows(
            "SELECT tr.*,
                    eu.name AS unit_name,
                    es.event_code, es.team_entry_fee,
                    sp.name AS sport_name, se.name AS sport_event_name,
                    a.name AS captain_name,
                    (SELECT COUNT(*) FROM team_registration_members
                       WHERE team_registration_id = tr.id) AS members_count
               FROM team_registrations tr
               JOIN athletes a     ON a.id = tr.athlete_id
          LEFT JOIN event_units eu  ON eu.id = tr.unit_id
          LEFT JOIN event_sports es ON es.id = tr.event_sport_id
          LEFT JOIN sports sp       ON sp.id = es.sport_id
          LEFT JOIN sport_events se ON se.id = es.sport_event_id
              WHERE tr.event_id = ?
              ORDER BY tr.submitted_at DESC, tr.registered_at DESC",
            [$eventId]
        );
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

    // ── Members ──────────────────────────────────────────────────────────────

    public static function members(int $teamId): array
    {
        return static::rows(
            "SELECT m.*,
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
