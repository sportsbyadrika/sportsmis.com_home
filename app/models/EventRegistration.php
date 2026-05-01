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

    /**
     * The athlete may edit Step 1 (unit / NOC / sport events) only while the
     * registration is still a draft (no admin_review_status) or has been
     * explicitly returned by the event admin for changes.
     */
    /**
     * Allocate the next competitor number for an event (starting from 1001),
     * persist it on the registration, and return the assigned number. Idempotent
     * — if the registration already has one, it's returned unchanged.
     */
    public static function allocateCompetitorNumber(int $registrationId): int
    {
        $reg = self::findById($registrationId);
        if (!$reg) return 0;
        if (!empty($reg['competitor_number'])) return (int)$reg['competitor_number'];

        $eventId = (int)$reg['event_id'];
        $r = static::row(
            "SELECT MAX(competitor_number) AS mx FROM event_registrations WHERE event_id = ?",
            [$eventId]
        );
        $next = max(1001, (int)($r['mx'] ?? 0) + 1);
        // Race-safe-ish: try, on duplicate (concurrent allocations) bump and retry once.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                static::query(
                    "UPDATE event_registrations SET competitor_number = ? WHERE id = ? AND competitor_number IS NULL",
                    [$next, $registrationId]
                );
                return $next;
            } catch (\Throwable $e) {
                $next++;
            }
        }
        return 0;
    }

    public static function isEditable(?array $reg): bool
    {
        if (!$reg) return true;
        $s = $reg['admin_review_status'] ?? null;
        return $s === null || $s === '' || $s === 'returned';
    }

    public static function withProfile(int $id): ?array
    {
        return static::row(
            "SELECT er.*, e.name AS event_name, e.event_date_from, e.event_date_to,
                    e.location, e.institution_id,
                    i.name AS institution_name,
                    a.name AS athlete_name, a.mobile AS athlete_mobile, a.gender, a.date_of_birth,
                    a.passport_photo, a.id_proof_number, a.dob_proof_number,
                    u.email AS athlete_email,
                    eu.name AS unit_name, eu.address AS unit_address
               FROM event_registrations er
               JOIN events e        ON e.id = er.event_id
               JOIN institutions i  ON i.id = e.institution_id
               JOIN athletes a      ON a.id = er.athlete_id
          LEFT JOIN users u         ON u.id = a.user_id
          LEFT JOIN event_units eu  ON eu.id = er.unit_id
              WHERE er.id = ?",
            [$id]
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
