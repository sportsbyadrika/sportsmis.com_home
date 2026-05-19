<?php
namespace Models;

use Core\Model;

/**
 * NOC (No-Objection Certificate) status that a Unit/Club/Institution user
 * sets against each of their approved athletes for an event.
 *
 * Stored on event_registrations (noc_status / noc_status_at / noc_status_by)
 * so it is naturally event-specific and isolated per event.
 */
class Noc extends Model
{
    public const STATUSES = ['pending', 'accepted', 'rejected'];

    /**
     * Approved athletes of a unit (event-scoped) with NOC + context, for the
     * unit-user NOC management screen and its print report.
     */
    public static function athletesForUnit(int $eventId, int $unitId): array
    {
        return static::rows(
            "SELECT er.id AS registration_id, er.competitor_number,
                    er.noc_status, er.noc_status_at, er.noc_status_by,
                    a.id AS athlete_id, a.name AS athlete_name, a.gender,
                    a.mobile, a.passport_photo,
                    eu.name AS unit_name, eu.address AS unit_address,
                    GROUP_CONCAT(DISTINCT
                        CONCAT_WS(' ',
                            COALESCE(NULLIF(es.event_code, ''), ''),
                            COALESCE(NULLIF(se.name, ''), es.category)
                        ) ORDER BY se.name SEPARATOR ', ') AS events_label
               FROM event_registrations er
               JOIN athletes a              ON a.id = er.athlete_id
               JOIN event_units eu          ON eu.id = er.unit_id
          LEFT JOIN event_registration_items eri ON eri.registration_id = er.id
          LEFT JOIN event_sports es        ON es.id = eri.event_sport_id
          LEFT JOIN sport_events se        ON se.id = es.sport_event_id
              WHERE er.event_id = ? AND er.unit_id = ?
                AND er.admin_review_status = 'approved'
              GROUP BY er.id
              ORDER BY a.name",
            [$eventId, $unitId]
        );
    }

    /** Count of approved athletes across a set of unit ids. */
    public static function approvedCount(int $eventId, array $unitIds): int
    {
        $unitIds = array_values(array_filter(array_map('intval', $unitIds)));
        if (!$unitIds) return 0;
        $in = implode(',', array_fill(0, count($unitIds), '?'));
        $r = static::row(
            "SELECT COUNT(*) AS c FROM event_registrations
              WHERE event_id = ? AND admin_review_status = 'approved'
                AND unit_id IN ({$in})",
            [$eventId, ...$unitIds]
        );
        return (int)($r['c'] ?? 0);
    }

    /** Does the given unit user have at least one approved athlete? */
    public static function unitUserHasApproved(int $unitUserId, int $eventId): bool
    {
        $units = UnitUser::assignmentIds($unitUserId);
        return $units && self::approvedCount($eventId, $units) > 0;
    }

    /** Persist a NOC status decision (logs timestamp + username). */
    public static function setStatus(int $registrationId, string $status, string $byLabel): void
    {
        if (!in_array($status, self::STATUSES, true)) return;
        static::update('event_registrations', [
            'noc_status'    => $status,
            'noc_status_at' => date('Y-m-d H:i:s'),
            'noc_status_by' => $byLabel,
        ], ['id' => $registrationId]);
    }
}
