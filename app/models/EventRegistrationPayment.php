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

    public static function findByOrderId(string $orderId): ?array
    {
        return static::row(
            "SELECT * FROM event_registration_payments WHERE razorpay_order_id = ?",
            [$orderId]
        );
    }

    /**
     * Phase-7 reliability: pending ePayments older than $minOlderThan
     * minutes and younger than $maxAgeMinutes minutes.
     */
    public static function stuckPendingEpayments(int $minOlderThan = 10, int $maxAgeMinutes = 1440): array
    {
        return static::rows(
            "SELECT p.*, er.event_id AS reg_event_id, er.athlete_id
               FROM event_registration_payments p
               JOIN event_registrations er ON er.id = p.registration_id
              WHERE p.payment_method = 'epayment'
                AND p.status         = 'pending'
                AND p.created_at <= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                AND p.created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
              ORDER BY p.created_at ASC",
            [$minOlderThan, $maxAgeMinutes]
        );
    }

    /** Same selector as above without the lower bound — used by the
     *  Super-Admin Pending ePayments page so admins can see fresh ones too. */
    public static function pendingEpaymentsForAdmin(): array
    {
        return static::rows(
            "SELECT p.*, er.event_id AS reg_event_id, er.athlete_id,
                    a.name AS athlete_name, e.name AS event_name,
                    i.name AS institution_name
               FROM event_registration_payments p
               JOIN event_registrations er ON er.id = p.registration_id
               JOIN athletes      a ON a.id = er.athlete_id
               JOIN events        e ON e.id = er.event_id
               JOIN institutions  i ON i.id = e.institution_id
              WHERE p.payment_method = 'epayment'
                AND p.status         = 'pending'
              ORDER BY p.created_at DESC
              LIMIT 500"
        );
    }

    /** Pending epayment rows on a single registration — used by the
     *  athlete-side "Refresh" button to reconcile via Razorpay. */
    public static function pendingEpaymentsForRegistration(int $regId): array
    {
        return static::rows(
            "SELECT * FROM event_registration_payments
              WHERE registration_id = ?
                AND payment_method  = 'epayment'
                AND status          = 'pending'",
            [$regId]
        );
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
     * Super-Admin epayment summary, grouped by event (= event administrator).
     * @param array{from?:string,to?:string,status?:string,q?:string} $filters
     */
    public static function epaymentSummaryByEvent(array $filters): array
    {
        $where  = ["p.payment_method = 'epayment'"];
        $params = [];
        if (!empty($filters['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['from'])) {
            $where[] = 'p.transaction_date >= ?'; $params[] = $filters['from'];
        }
        if (!empty($filters['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['to'])) {
            $where[] = 'p.transaction_date <= ?'; $params[] = $filters['to'];
        }
        if (!empty($filters['status']) && in_array($filters['status'], ['approved','pending','rejected'], true)) {
            $where[] = 'p.status = ?'; $params[] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(i.name LIKE ? OR e.name LIKE ? OR p.razorpay_payment_id LIKE ? OR p.razorpay_order_id LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $whereSql = implode(' AND ', $where);

        return static::rows("
            SELECT
                e.id                                                       AS event_id,
                e.name                                                     AS event_name,
                i.id                                                       AS institution_id,
                i.name                                                     AS institution_name,
                e.bank_name, e.bank_branch, e.bank_account_number, e.bank_ifsc,
                COUNT(*)                                                   AS txn_count,
                COUNT(CASE WHEN p.status='approved' THEN 1 END)            AS approved_count,
                COUNT(CASE WHEN p.status='pending'  THEN 1 END)            AS pending_count,
                COUNT(CASE WHEN p.status='rejected' THEN 1 END)            AS rejected_count,
                COALESCE(SUM(CASE WHEN p.status='approved' THEN p.amount END), 0) AS approved_amount,
                COALESCE(SUM(CASE WHEN p.status='pending'  THEN p.amount END), 0) AS pending_amount,
                COALESCE(SUM(CASE WHEN p.status='rejected' THEN p.amount END), 0) AS rejected_amount,
                COALESCE(SUM(p.amount), 0)                                 AS total_amount,
                MIN(p.transaction_date)                                    AS first_txn,
                MAX(p.transaction_date)                                    AS last_txn
              FROM event_registration_payments p
              JOIN events       e ON e.id = p.event_id
              JOIN institutions i ON i.id = e.institution_id
             WHERE {$whereSql}
             GROUP BY e.id
             ORDER BY i.name, e.name
        ", $params);
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
