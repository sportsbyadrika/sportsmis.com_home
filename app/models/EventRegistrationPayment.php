<?php
namespace Models;

use Core\Model;

class EventRegistrationPayment extends Model
{
    public static function forRegistration(int $regId): array
    {
        return static::rows(
            "SELECT * FROM event_registration_payments WHERE registration_id = ? ORDER BY transaction_date DESC, id DESC",
            [$regId]
        );
    }

    public static function find(int $id): ?array
    {
        return static::row("SELECT * FROM event_registration_payments WHERE id = ?", [$id]);
    }

    public static function create(array $data): int
    {
        return static::insert('event_registration_payments', $data);
    }

    public static function updateRow(int $id, array $data): void
    {
        static::update('event_registration_payments', $data, ['id' => $id]);
    }

    public static function deleteRow(int $id): void
    {
        static::query("DELETE FROM event_registration_payments WHERE id = ?", [$id]);
    }

    public static function totals(int $regId): array
    {
        $r = static::row(
            "SELECT
                COUNT(*)                              AS total,
                COUNT(CASE WHEN status='approved' THEN 1 END) AS approved,
                COUNT(CASE WHEN status='rejected' THEN 1 END) AS rejected,
                COUNT(CASE WHEN status='pending'  THEN 1 END) AS pending,
                COALESCE(SUM(CASE WHEN status='approved' THEN amount END), 0) AS approved_amount,
                COALESCE(SUM(amount), 0) AS submitted_amount
               FROM event_registration_payments
              WHERE registration_id = ?",
            [$regId]
        );
        return $r ?: ['total'=>0,'approved'=>0,'rejected'=>0,'pending'=>0,'approved_amount'=>0,'submitted_amount'=>0];
    }

    /**
     * Recompute the registration's payment_status from its payment records.
     * - any approved >= total_amount → 'paid'
     * - any rejected & no approved → 'failed'
     * - otherwise → 'pending'
     */
    public static function recomputeRegistrationPaymentStatus(int $regId): string
    {
        $reg = EventRegistration::findById($regId);
        $totals = self::totals($regId);
        $required = (float)($reg['total_amount'] ?? 0);

        if ($totals['approved'] > 0 && $required > 0 && (float)$totals['approved_amount'] + 0.001 >= $required) {
            $status = 'paid';
        } elseif ($totals['rejected'] > 0 && $totals['approved'] === 0 && $totals['pending'] === 0) {
            $status = 'failed';
        } else {
            $status = 'pending';
        }
        EventRegistration::updateHeader($regId, ['payment_status' => $status]);
        return $status;
    }
}
