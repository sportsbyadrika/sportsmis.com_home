<?php
namespace Models;

use Core\Model;

/**
 * Per-event, per-athlete attendance. Attendance is tracked for each event a
 * given athlete is registered for (an event_sport), because an athlete may be
 * registered for two events yet only turn up for one. Only 'absent' rows
 * matter for filtering — an athlete with no row (or 'present') is attending
 * that event.
 */
class Attendance extends Model
{
    /**
     * athlete_ids marked absent for a single event (event_sport).
     * @return int[] athlete_ids
     */
    public static function absentIdsForSport(int $eventSportId): array
    {
        if ($eventSportId <= 0) return [];
        return array_map(fn($r) => (int)$r['athlete_id'], static::rows(
            "SELECT athlete_id FROM event_attendance
              WHERE event_sport_id = ? AND status = 'absent'",
            [$eventSportId]
        ));
    }

    /**
     * [event_sport_id => [athlete_id => status]] for a whole event, so a page
     * can look up any athlete/event pairing without a query per row.
     */
    public static function statusMapForEvent(int $eventId): array
    {
        $out = [];
        foreach (static::rows(
            "SELECT event_sport_id, athlete_id, status FROM event_attendance
              WHERE event_id = ?",
            [$eventId]) as $r) {
            $out[(int)$r['event_sport_id']][(int)$r['athlete_id']] = (string)$r['status'];
        }
        return $out;
    }

    /** [athlete_id => status] for one event_sport (to prefill the toggles). */
    public static function statusMapForSport(int $eventSportId): array
    {
        if ($eventSportId <= 0) return [];
        $out = [];
        foreach (static::rows(
            "SELECT athlete_id, status FROM event_attendance
              WHERE event_sport_id = ?",
            [$eventSportId]) as $r) {
            $out[(int)$r['athlete_id']] = (string)$r['status'];
        }
        return $out;
    }

    /** Upsert one athlete's status for a single event (event_sport). */
    public static function save(int $eventId, int $eventSportId, ?int $unitId, int $athleteId, ?string $date, string $status): void
    {
        if ($eventSportId <= 0) return;
        $status = $status === 'absent' ? 'absent' : 'present';
        static::query(
            "INSERT INTO event_attendance (event_id, event_sport_id, unit_id, athlete_id, att_date, status)
                  VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), unit_id = VALUES(unit_id), att_date = VALUES(att_date)",
            [$eventId, $eventSportId, $unitId, $athleteId, ($date !== '' ? $date : null), $status]
        );
    }
}
