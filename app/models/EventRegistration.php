<?php
namespace Models;

use Core\Model;

/**
 * Header + items access for the new athlete registration flow.
 * The legacy single-row Event::registerAthlete() path is kept untouched so
 * existing data still works; new code goes through these helpers.
 */
class EventRegistration extends Model
{
    public static function findHeader(int $eventId, int $athleteId): ?array
    {
        return static::row(
            "SELECT * FROM event_registrations WHERE event_id = ? AND athlete_id = ?",
            [$eventId, $athleteId]
        );
    }

    public static function findById(int $id): ?array
    {
        return static::row("SELECT * FROM event_registrations WHERE id = ?", [$id]);
    }

    public static function createDraft(int $eventId, int $athleteId): int
    {
        return static::insert('event_registrations', [
            'event_id'       => $eventId,
            'athlete_id'     => $athleteId,
            'status'         => 'pending',
            'payment_status' => 'pending',
        ]);
    }

    public static function updateHeader(int $id, array $data): void
    {
        if (!$data) return;
        static::query(
            'UPDATE event_registrations SET ' . implode(',', array_map(fn($k) => "{$k}=?", array_keys($data)))
            . ' WHERE id = ?',
            [...array_values($data), $id]
        );
    }

    public static function items(int $registrationId): array
    {
        return static::rows(
            "SELECT eri.*, es.event_code, es.category, es.entry_fee AS event_fee,
                    sp.name AS sport_name, se.name AS sport_event_name
               FROM event_registration_items eri
               JOIN event_sports es ON es.id = eri.event_sport_id
          LEFT JOIN sports sp        ON sp.id = es.sport_id
          LEFT JOIN sport_events se  ON se.id = es.sport_event_id
              WHERE eri.registration_id = ?
              ORDER BY eri.id",
            [$registrationId]
        );
    }

    public static function syncItems(int $registrationId, array $eventSportIds): float
    {
        static::query("DELETE FROM event_registration_items WHERE registration_id = ?", [$registrationId]);
        if (!$eventSportIds) return 0.0;

        // Pull fees in one go to compute the running total.
        $placeholders = implode(',', array_fill(0, count($eventSportIds), '?'));
        $rows = static::rows(
            "SELECT id, entry_fee FROM event_sports WHERE id IN ({$placeholders})",
            array_map('intval', $eventSportIds)
        );
        $feeById = array_column($rows, 'entry_fee', 'id');

        $total = 0.0;
        foreach ($eventSportIds as $esId) {
            $fee = (float)($feeById[(int)$esId] ?? 0);
            static::insert('event_registration_items', [
                'registration_id' => $registrationId,
                'event_sport_id'  => (int)$esId,
                'fee'             => $fee,
            ]);
            $total += $fee;
        }
        return $total;
    }
}
