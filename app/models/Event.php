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
             WHERE e.status = 'approved'
               AND e.reg_date_from <= CURDATE()
               AND e.reg_date_to >= CURDATE()
             ORDER BY e.event_date_from ASC"
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
        return static::rows(
            'SELECT er.*, e.name AS event_name, e.event_date_from, e.event_date_to,
                    e.location, i.name AS institution_name, s.name AS sport_name
             FROM event_registrations er
             JOIN events e ON e.id = er.event_id
             JOIN institutions i ON i.id = e.institution_id
             JOIN sports s ON s.id = er.sport_id
             WHERE er.athlete_id = ? ORDER BY er.registered_at DESC',
            [$athleteId]
        );
    }
}
