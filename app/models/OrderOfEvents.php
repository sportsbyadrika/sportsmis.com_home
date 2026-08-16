<?php
namespace Models;

use Core\Model;

/**
 * Order of Events (competition programme) for an event.
 *
 * The programme lives on the event_sports rows themselves — each configured
 * sport-event carries a scheduled slot (order_date + order_time), a serial
 * number within the programme (order_sl_no) and a call-room status
 * (order_call_status). Managed by Event Staff holding the 'order_of_events'
 * privilege; also feeds the printable date-wise programme.
 */
class OrderOfEvents extends Model
{
    /**
     * Call-room lifecycle statuses, in running order. Keys are stored;
     * values are the human labels shown in the UI and the PDF.
     */
    public const STATUSES = [
        'scheduled'        => 'Scheduled',
        'call_open'        => 'Call Open',
        'last_call'        => 'Last Call',
        'call_closed'      => 'Call Closed',
        'under_inspection' => 'Under Inspection',
        'assembled'        => 'Assembled',
        'in_progress'      => 'In Progress',
        'finished'         => 'Finished',
        'result_published' => 'Result Published',
        'medal_ceremony'   => 'Medal Ceremony',
    ];

    public static function statusLabel(?string $key): string
    {
        $key = (string)$key;
        return self::STATUSES[$key] ?? ($key !== '' ? ucfirst(str_replace('_', ' ', $key)) : 'Scheduled');
    }

    public static function isValidStatus(string $key): bool
    {
        return isset(self::STATUSES[$key]);
    }

    /**
     * Every sport-event in the programme for an event, hydrated with sport /
     * category / age / gender labels and the schedule fields. Sorted so
     * scheduled items lead (by date, then time, then serial number) and
     * unscheduled items trail alphabetically.
     *
     * @param string|null $dateFilter  'YYYY-MM-DD' to restrict to one day,
     *                                  'unscheduled' for rows with no date,
     *                                  null/'' for everything.
     */
    public static function listForEvent(int $eventId, ?string $dateFilter = null): array
    {
        $sql = "SELECT es.id, es.sport_event_id,
                       es.order_sl_no, es.order_date, es.order_time,
                       COALESCE(es.order_call_status, 'scheduled') AS order_call_status,
                       s.name  AS sport_name,
                       se.name AS sport_event_name,
                       sc.name AS sport_event_category,
                       ac.name AS sport_event_age_category,
                       se.gender AS sport_event_gender,
                       es.event_code
                  FROM event_sports es
                  JOIN sports s              ON s.id  = es.sport_id
             LEFT JOIN sport_events     se   ON se.id = es.sport_event_id
             LEFT JOIN sport_categories sc   ON sc.id = se.category_id
             LEFT JOIN age_categories   ac   ON ac.id = se.age_category_id
                 WHERE es.event_id = ?";
        $params = [$eventId];

        if ($dateFilter === 'unscheduled') {
            $sql .= " AND es.order_date IS NULL";
        } elseif ($dateFilter !== null && $dateFilter !== '') {
            $sql .= " AND es.order_date = ?";
            $params[] = $dateFilter;
        }

        $sql .= " ORDER BY (es.order_date IS NULL), es.order_date,
                           (es.order_time IS NULL), es.order_time,
                           (es.order_sl_no IS NULL), es.order_sl_no,
                           s.name, sc.name, ac.name";
        return static::rows($sql, $params);
    }

    /**
     * Number of distinct athletes registered for each sport-event on this
     * event (rejected registrations excluded). Keyed by event_sports.id so
     * it lines up with the rows from listForEvent().
     *
     * @return array<int,int>  event_sports.id => athlete count
     */
    public static function athleteCounts(int $eventId): array
    {
        $rows = static::rows(
            "SELECT eri.event_sport_id AS es, COUNT(DISTINCT er.athlete_id) AS c
               FROM event_registration_items eri
               JOIN event_registrations er ON er.id = eri.registration_id
              WHERE er.event_id = ?
                AND COALESCE(er.admin_review_status, '') <> 'rejected'
              GROUP BY eri.event_sport_id",
            [$eventId]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r['es']] = (int)$r['c'];
        }
        return $out;
    }

    /** Distinct scheduled dates on an event's programme, ascending. */
    public static function distinctDates(int $eventId): array
    {
        $rows = static::rows(
            "SELECT DISTINCT order_date
               FROM event_sports
              WHERE event_id = ? AND order_date IS NOT NULL
              ORDER BY order_date",
            [$eventId]
        );
        return array_map(fn($r) => (string)$r['order_date'], $rows);
    }

    /** True when the event has at least one row with no scheduled date. */
    public static function hasUnscheduled(int $eventId): bool
    {
        $r = static::row(
            "SELECT COUNT(*) AS c FROM event_sports
              WHERE event_id = ? AND order_date IS NULL",
            [$eventId]
        );
        return (int)($r['c'] ?? 0) > 0;
    }

    /**
     * Update the schedule fields (serial number, date, time) for one
     * programme row, scoped to the event so a staff member can only touch
     * their own event's rows.
     */
    public static function updateSchedule(int $eventId, int $rowId, array $data): void
    {
        if (!$data) return;
        $cols = [];
        $vals = [];
        foreach (['order_sl_no', 'order_date', 'order_time'] as $c) {
            if (array_key_exists($c, $data)) {
                $cols[] = "{$c} = ?";
                $vals[] = $data[$c];
            }
        }
        if (!$cols) return;
        $vals[] = $eventId;
        $vals[] = $rowId;
        static::query(
            "UPDATE event_sports SET " . implode(', ', $cols) . " WHERE event_id = ? AND id = ?",
            $vals
        );
    }

    /** Update just the call-room status for one programme row. */
    public static function updateStatus(int $eventId, int $rowId, string $status): void
    {
        static::query(
            "UPDATE event_sports SET order_call_status = ? WHERE event_id = ? AND id = ?",
            [$status, $eventId, $rowId]
        );
    }
}
