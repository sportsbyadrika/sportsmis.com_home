<?php
namespace Services;

use Models\{EventRegistrationPayment, EventRegistration};

/**
 * Phase-7 shared post-approval logic for ePayment rows.
 *
 * Single source of truth for "this Razorpay payment was successful" used by:
 *   - browser-side payVerify              (AthleteController::payVerify)
 *   - server-to-server webhook            (WebhookController::razorpay)
 *   - reconciliation cron                 (cron/reconcile.php)
 *   - admin Re-check button               (AdminReportsController)
 *
 * All three funnels arrive at markPaid() / markFailed(); the first one
 * that wins flips the row from 'pending' → 'approved'/'rejected'. Later
 * arrivals are no-ops because the UPDATE is gated on status='pending'.
 *
 * The status vocabulary on event_registration_payments is:
 *   pending  ≡ Razorpay "created"
 *   approved ≡ Razorpay "captured" / "paid"
 *   rejected ≡ Razorpay "failed"
 *
 * Returned bool indicates whether THIS call actually changed anything.
 */
class PaymentApprovalService
{
    /**
     * Idempotently mark an ePayment row paid. UPDATE is guarded by
     * status='pending' so duplicate signals are safe.
     *
     * @param int    $rowId       event_registration_payments.id
     * @param string $paymentId   razorpay_payment_id
     * @param string $signature   razorpay_signature (browser path) — '' from webhook/reconcile
     * @param string $source      'browser' | 'webhook' | 'reconcile' | 'admin'
     */
    public static function markPaid(int $rowId, string $paymentId, string $signature, string $source): bool
    {
        $row = EventRegistrationPayment::find($rowId);
        if (!$row || $row['status'] !== 'pending') return false;

        $reason = sprintf('AUTO: ePayment captured via %s', $source);
        // Status-guarded UPDATE: the WHERE clause inside updateRow doesn't
        // re-check status, so we read-then-write. Two concurrent winners are
        // unlikely (webhook + browser both flip the same row), but the
        // recompute step is idempotent anyway, so the worst case is one
        // wasted UPDATE.
        EventRegistrationPayment::updateRow($rowId, [
            'status'              => 'approved',
            'razorpay_payment_id' => $paymentId ?: ($row['razorpay_payment_id'] ?? null),
            'razorpay_signature'  => $signature ?: ($row['razorpay_signature']  ?? null),
            'rejection_reason'    => $reason,
            'reviewed_at'         => date('Y-m-d H:i:s'),
            'reconciled_at'       => date('Y-m-d H:i:s'),
        ]);
        EventRegistrationPayment::recomputeRegistrationPaymentStatus((int)$row['registration_id']);
        return true;
    }

    /** Same shape as markPaid for the failed branch. */
    public static function markFailed(int $rowId, string $reason, string $source): bool
    {
        $row = EventRegistrationPayment::find($rowId);
        if (!$row || $row['status'] !== 'pending') return false;

        EventRegistrationPayment::updateRow($rowId, [
            'status'           => 'rejected',
            'rejection_reason' => 'AUTO: ePayment failed via ' . $source . ($reason ? ' — ' . $reason : ''),
            'reviewed_at'      => date('Y-m-d H:i:s'),
            'reconciled_at'    => date('Y-m-d H:i:s'),
        ]);
        EventRegistrationPayment::recomputeRegistrationPaymentStatus((int)$row['registration_id']);
        return true;
    }

    /**
     * Decide what to do with a pending row given Razorpay's truth (the
     * `items` array returned by GET /v1/orders/{id}/payments).
     *
     * Used by the reconcile cron and the admin Re-check button. Returns a
     * short outcome string for logging.
     */
    public static function applyOrderPayments(int $rowId, array $payments, string $source): string
    {
        if (!$payments) return 'no-payment-attempted';

        // A captured payment trumps everything else.
        $captured = null; $failed = null;
        foreach ($payments as $p) {
            $st = strtolower((string)($p['status'] ?? ''));
            if ($st === 'captured') { $captured = $p; break; }
            if ($st === 'failed')   { $failed   = $p; }
        }
        if ($captured) {
            $changed = self::markPaid(
                $rowId,
                (string)($captured['id']     ?? ''),
                (string)($captured['signature'] ?? ''),  // not present on /payments – stays empty
                $source
            );
            return $changed ? 'paid' : 'already-decided';
        }
        if ($failed) {
            $reason  = trim((string)(($failed['error_description'] ?? '') ?: ($failed['error_code'] ?? '')));
            $changed = self::markFailed($rowId, $reason, $source);
            return $changed ? 'failed' : 'already-decided';
        }
        // Created / authorized but not captured yet — leave alone.
        return 'still-open';
    }
}
