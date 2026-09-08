<?php
namespace Models;

use Core\Model;

/**
 * Existing meet records per sport-event (one per event_sport). Stores the
 * standing record value plus the meet, year and athlete it belongs to.
 */
class MeetRecord extends Model
{
    /** All records for an event, keyed by event_sport_id (for quick lookup). */
    public static function mapForEvent(int $eventId): array
    {
        $out = [];
        foreach (static::rows(
            "SELECT event_sport_id, record_value, meet_name, record_year, athlete_name
               FROM event_meet_records WHERE event_id = ?", [$eventId]) as $r) {
            $out[(int)$r['event_sport_id']] = $r;
        }
        return $out;
    }

    /** Records for the management list, joined with sport-event labels. */
    public static function listForEvent(int $eventId): array
    {
        return static::rows(
            "SELECT mr.id, mr.event_sport_id, mr.record_value, mr.meet_name, mr.record_year, mr.athlete_name,
                    se.name AS sport_event_name, se.gender AS gender,
                    sc.name AS category_name, ac.name AS age_name
               FROM event_meet_records mr
               JOIN event_sports es      ON es.id = mr.event_sport_id
          LEFT JOIN sport_events se       ON se.id = es.sport_event_id
          LEFT JOIN sport_categories sc   ON sc.id = se.category_id
          LEFT JOIN age_categories ac     ON ac.id = se.age_category_id
              WHERE mr.event_id = ?
              ORDER BY sc.name, ac.name, se.name",
            [$eventId]
        );
    }

    /** Upsert the record for one event_sport (unique per event_sport_id). */
    public static function save(int $eventId, int $esId, string $value, ?string $meet, ?string $year, ?string $athlete): void
    {
        static::query(
            "INSERT INTO event_meet_records (event_id, event_sport_id, record_value, meet_name, record_year, athlete_name)
                  VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE record_value=VALUES(record_value), meet_name=VALUES(meet_name),
                                     record_year=VALUES(record_year), athlete_name=VALUES(athlete_name)",
            [$eventId, $esId, $value, $meet, $year, $athlete]
        );
    }

    public static function deleteRow(int $id, int $eventId): void
    {
        static::query("DELETE FROM event_meet_records WHERE id = ? AND event_id = ?", [$id, $eventId]);
    }

    /**
     * Parse a performance value to a comparable number: seconds for a time
     * (mm:ss.SS / h:mm:ss / ss.SS), else the numeric metres. Returns null when
     * it can't be parsed.
     */
    public static function toNumber(string $value, string $unit): ?float
    {
        $value = trim($value);
        if ($value === '') return null;
        if ($unit === 'time') {
            $num = 0.0;
            foreach (explode(':', $value) as $p) {
                $p = trim($p);
                if ($p === '' || !is_numeric($p)) return null;
                $num = $num * 60 + (float)$p;
            }
            return $num;
        }
        $v = preg_replace('/[^0-9.]/', '', $value);
        return ($v === '' || !is_numeric($v)) ? null : (float)$v;
    }

    /**
     * Is $value a New Meet Record against $record? Time: lower is better;
     * height / length: greater is better. False when either can't be parsed
     * or there is no standing record.
     */
    public static function isNMR(string $value, ?array $record, string $unit): bool
    {
        if (!$record) return false;
        $v = self::toNumber($value, $unit);
        $r = self::toNumber((string)($record['record_value'] ?? ''), $unit);
        if ($v === null || $r === null) return false;
        return $unit === 'time' ? ($v < $r) : ($v > $r);
    }
}
