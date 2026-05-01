<?php
namespace Models;

use Core\Model;

class Event extends Model
{
    public static function create(array $data, array $paymentModes, array $sports): int
    {
        $id = static::insert('events', $data);
        static::syncPaymentModes($id, $paymentModes);
        static::syncSports($id, $sports);
        return $id;
    }

    public static function findById(int $id): ?array
    {
        $event = static::row(
            'SELECT e.*, i.name AS institution_name FROM events e
             JOIN institutions i ON i.id = e.institution_id
             WHERE e.id = ?',
            [$id]
        );
        if ($event) {
            $event['payment_modes'] = static::getPaymentModes($id);
            $event['sports']        = static::getSports($id);
        }
        return $event;
    }

    public static function getByInstitution(int $institutionId): array
    {
        return static::rows(
            'SELECT * FROM events WHERE institution_id = ? ORDER BY created_at DESC',
            [$institutionId]
        );
    }

    public static function getActiveEvents(): array
    {
        return static::rows(
            "SELECT e.*, i.name AS institution_name, i.logo AS institution_logo
             FROM events e
             JOIN institutions i ON i.id = e.institution_id
             WHERE e.status = 'active'
               AND e.reg_date_from <= CURDATE()
               AND e.reg_date_to >= CURDATE()
             ORDER BY e.event_date_from ASC"
        );
    }

    public static function setStatus(int $id, string $status, ?int $adminId = null): void
    {
        $allowed = ['draft', 'active', 'completed', 'suspended'];
        if (!in_array($status, $allowed, true)) return;
        $data = ['status' => $status];
        if ($adminId && $status === 'active') {
            $data['approved_by'] = $adminId;
            $data['approved_at'] = date('Y-m-d H:i:s');
        }
        static::query(
            'UPDATE events SET ' . implode(',', array_map(fn($k) => "{$k}=?", array_keys($data)))
            . ' WHERE id = ?',
            [...array_values($data), $id]
        );
    }

    public static function getAllForAdmin(): array
    {
        return static::rows(
            'SELECT e.*, i.name AS institution_name FROM events e
             JOIN institutions i ON i.id = e.institution_id
             ORDER BY e.created_at DESC'
        );
    }

    public static function updateEvent(int $id, array $data, array $paymentModes, array $sports): void
    {
        static::query(
            'UPDATE events SET ' . implode(',', array_map(fn($k) => "{$k}=?", array_keys($data)))
            . ' WHERE id = ?',
            [...array_values($data), $id]
        );
        static::syncPaymentModes($id, $paymentModes);
        static::syncSports($id, $sports);
    }

    public static function updateStatus(int $id, string $status, ?int $adminId = null, ?string $reason = null): void
    {
        $data = ['status' => $status];
        if ($adminId) { $data['approved_by'] = $adminId; $data['approved_at'] = date('Y-m-d H:i:s'); }
        if ($reason)  { $data['rejection_reason'] = $reason; }
        static::query(
            'UPDATE events SET ' . implode(',', array_map(fn($k) => "{$k}=?", array_keys($data)))
            . ' WHERE id = ?',
            [...array_values($data), $id]
        );
    }

    private static function syncPaymentModes(int $eventId, array $modes): void
    {
        static::query('DELETE FROM event_payment_modes WHERE event_id = ?', [$eventId]);
        foreach ($modes as $mode) {
            static::insert('event_payment_modes', ['event_id' => $eventId, 'mode' => $mode]);
        }
    }

    public static function syncPaymentModesPublic(int $eventId, array $modes): void
    {
        self::syncPaymentModes($eventId, $modes);
    }

    private static function syncSports(int $eventId, array $sports): void
    {
        static::query('DELETE FROM event_sports WHERE event_id = ?', [$eventId]);
        foreach ($sports as $sportId => $info) {
            static::insert('event_sports', [
                'event_id'  => $eventId,
                'sport_id'  => (int)$sportId,
                'category'  => $info['category'] ?? null,
                'entry_fee' => (float)($info['entry_fee'] ?? 0),
            ]);
        }
    }

    public static function hasSportEvent(int $eventId, int $sportEventId): bool
    {
        $r = static::row(
            'SELECT id FROM event_sports WHERE event_id = ? AND sport_event_id = ?',
            [$eventId, $sportEventId]
        );
        return (bool)$r;
    }

    /** Append one sport-event entry to an event without disturbing the others. */
    public static function addSportEvent(int $eventId, array $row): void
    {
        // De-dupe on (event, sport_event_id) so re-adding the same catalog entry
        // updates the entry fee instead of inserting a duplicate.
        if (!empty($row['sport_event_id'])) {
            static::query(
                'DELETE FROM event_sports WHERE event_id = ? AND sport_event_id = ?',
                [$eventId, (int)$row['sport_event_id']]
            );
        }
        static::insert('event_sports', [
            'event_id'       => $eventId,
            'sport_id'       => (int)$row['sport_id'],
            'sport_event_id' => $row['sport_event_id'] ?? null,
            'event_code'     => $row['event_code'] ?? null,
            'category'       => $row['category'] ?? null,
            'entry_fee'      => (float)($row['entry_fee'] ?? 0),
        ]);
    }

    public static function removeSportRow(int $eventId, int $rowId): void
    {
        static::query('DELETE FROM event_sports WHERE event_id = ? AND id = ?', [$eventId, $rowId]);
    }

    public static function updatePartial(int $eventId, array $data): void
    {
        if (!$data) return;
        static::query(
            'UPDATE events SET ' . implode(',', array_map(fn($k) => "{$k}=?", array_keys($data)))
            . ' WHERE id = ?',
            [...array_values($data), $eventId]
        );
    }

    public static function getPaymentModes(int $eventId): array
    {
        return array_column(
            static::rows('SELECT mode FROM event_payment_modes WHERE event_id = ?', [$eventId]),
            'mode'
        );
    }

    public static function getSports(int $eventId): array
    {
        return static::rows(
            "SELECT es.*, s.name AS sport_name,
                    se.name AS sport_event_name,
                    sc.name AS sport_event_category,
                    ac.name AS sport_event_age_category,
                    se.gender AS sport_event_gender
               FROM event_sports es
               JOIN sports s             ON s.id  = es.sport_id
          LEFT JOIN sport_events     se ON se.id = es.sport_event_id
          LEFT JOIN sport_categories sc ON sc.id = se.category_id
          LEFT JOIN age_categories   ac ON ac.id = se.age_category_id
              WHERE es.event_id = ?
              ORDER BY es.id",
            [$eventId]
        );
    }

    // ── Registrations ────────────────────────────────────────────────────────

    public static function registerAthlete(array $data): int
    {
        return static::insert('event_registrations', $data);
    }

    public static function isAthleteRegistered(int $eventId, int $athleteId, int $sportId): bool
    {
        $r = static::row(
            'SELECT id FROM event_registrations WHERE event_id=? AND athlete_id=? AND sport_id=?',
            [$eventId, $athleteId, $sportId]
        );
        return (bool)$r;
    }

    public static function getAthleteRegistrations(int $athleteId): array
    {
        // The legacy flow stored a single sport on event_registrations.sport_id;
        // the new flow stores many lines on event_registration_items. LEFT JOIN
        // both sources and aggregate so either one shows up.
        $rows = static::rows(
            "SELECT er.*,
                    e.name AS event_name,
                    e.event_date_from, e.event_date_to,
                    e.location,
                    i.name AS institution_name,
                    legacy_sport.name AS legacy_sport_name,
                    eu.name AS unit_name,
                    -- aggregate the new line items
                    GROUP_CONCAT(DISTINCT s_item.name ORDER BY s_item.name SEPARATOR ', ') AS item_sports,
                    GROUP_CONCAT(DISTINCT
                        CONCAT_WS(' ',
                            COALESCE(NULLIF(es.event_code, ''), ''),
                            COALESCE(NULLIF(se.name, ''), es.category)
                        )
                        SEPARATOR ' | '
                    ) AS item_events,
                    COUNT(DISTINCT eri.id) AS items_count,
                    COALESCE(SUM(eri.fee), 0) AS items_fee_total
               FROM event_registrations er
               JOIN events e        ON e.id = er.event_id
               JOIN institutions i  ON i.id = e.institution_id
          LEFT JOIN sports legacy_sport ON legacy_sport.id = er.sport_id
          LEFT JOIN event_units eu ON eu.id = er.unit_id
          LEFT JOIN event_registration_items eri ON eri.registration_id = er.id
          LEFT JOIN event_sports es      ON es.id = eri.event_sport_id
          LEFT JOIN sports s_item        ON s_item.id = es.sport_id
          LEFT JOIN sport_events se      ON se.id = es.sport_event_id
              WHERE er.athlete_id = ?
              GROUP BY er.id
              ORDER BY er.registered_at DESC",
            [$athleteId]
        );

        // Backfill sport_name/total for legacy single-sport rows so views
        // can use one consistent set of keys.
        foreach ($rows as &$r) {
            $r['sport_name']  = $r['item_sports'] ?: ($r['legacy_sport_name'] ?? '');
            $r['event_label'] = $r['item_events'] ?: '';
            if (!isset($r['total_amount']) || $r['total_amount'] === null) {
                $r['total_amount'] = $r['items_fee_total'];
            }
        }
        return $rows;
    }
}
