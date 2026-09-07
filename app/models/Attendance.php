<?php
namespace Models;

use Core\Model;

/**
 * Per-day, per-athlete attendance for a competition date. Only 'absent' rows
 * matter for filtering — an athlete with no row (or 'present') is attending.
 */
class Attendance extends Model
{
    /** athlete_ids marked absent for an event on a given date. */
    public static function absentIds(int $eventId, string $date): array
    {
        if ($date === '') return [];
        return array_map(fn($r) => (int)$r['athlete_id'], static::rows(
            "SELECT athlete_id FROM event_attendance
              WHERE event_id = ? AND att_date = ? AND status = 'absent'",
            [$eventId, $date]
        ));
    }

    /** [athlete_id => status] for one unit + date (to prefill the toggles). */
    public static function statusMap(int $eventId, int $unitId, string $date): array
    {
        if ($date === '') return [];
        $out = [];
        foreach (static::rows(
            "SELECT athlete_id, status FROM event_attendance
              WHERE event_id = ? AND att_date = ? AND (unit_id = ? OR unit_id IS NULL)",
            [$eventId, $date, $unitId]) as $r) {
            $out[(int)$r['athlete_id']] = (string)$r['status'];
        }
        return $out;
    }

    /** Upsert one athlete's status for a date. */
    public static function save(int $eventId, ?int $unitId, int $athleteId, string $date, string $status): void
    {
        $status = $status === 'absent' ? 'absent' : 'present';
        static::query(
            "INSERT INTO event_attendance (event_id, unit_id, athlete_id, att_date, status)
                  VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), unit_id = VALUES(unit_id)",
            [$eventId, $unitId, $athleteId, $date, $status]
        );
    }
}
