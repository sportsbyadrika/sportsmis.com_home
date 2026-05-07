<?php
namespace Controllers;

use Core\{Controller, Razorpay};
use Models\{EventRegistrationPayment, WebhookLog};
use Services\PaymentApprovalService;

/**
 * Public, server-to-server endpoints. No login, no CSRF — Razorpay calls
 * these directly. Authenticity is verified per request via HMAC of the
 * raw request body against RAZORPAY_WEBHOOK_SECRET.
 *
 * Always returns 200 once the signature has verified, even on no-op
 * duplicates, so Razorpay does NOT retry indefinitely. Returns 400 only
 * when the signature does not verify (bad secret or replay).
 */
class WebhookController extends Controller
{
    /**
     * POST /webhook/razorpay
     *
     * Razorpay events handled:
     *   payment.captured  → mark the matching epayment row paid
     *   payment.failed    → mark the matching epayment row failed
     *   order.paid        → equivalent of payment.captured for this flow
     *
     * Every callback (including signature failures) is recorded in
     * webhook_log for audit / replay.
     */
    public function razorpay(): void
    {
        $rawBody   = (string)file_get_contents('php://input');
        $signature = (string)($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');

        // Parse first so we can attempt to log even on signature failure.
        $body  = json_decode($rawBody, true);
        $event = is_array($body) ? (string)($body['event'] ?? '') : '';
        $rzpEvId = is_array($body) ? (string)($body['id']    ?? '') : '';
        $orderId = is_array($body) ? (string)($body['payload']['payment']['entity']['order_id']
                                       ?? $body['payload']['order']['entity']['id']
                                       ?? '') : '';
        $payId   = is_array($body) ? (string)($body['payload']['payment']['entity']['id'] ?? '') : '';

        $rzp = new Razorpay();
        $sigOk = $rzp->verifyWebhookSignature($rawBody, $signature);

        if (!$sigOk) {
            WebhookLog::record([
                'event_type'          => $event ?: 'unknown',
                'rzp_event_id'        => $rzpEvId ?: null,
                'razorpay_order_id'   => $orderId ?: null,
                'razorpay_payment_id' => $payId   ?: null,
                'signature_ok'        => 0,
                'raw_payload'         => $rawBody,
                'outcome'             => 'signature-mismatch',
                'http_status'         => 400,
            ]);
            error_log('[webhook/razorpay] HMAC mismatch (orderId=' . $orderId . ', event=' . $event . ')');
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => 'signature mismatch']);
            return;
        }

        // Dedupe on Razorpay's event id — replays land harmlessly on the
        // UNIQUE index, but we also short-circuit so the log row is honest.
        if ($rzpEvId !== '' && WebhookLog::existsByEventId($rzpEvId)) {
            self::respond200('replay-duplicate-event-id');
            return;
        }

        $outcome = 'ignored';
        try {
            $row = $orderId !== '' ? EventRegistrationPayment::findByOrderId($orderId) : null;
            if (!$row) {
                $outcome = 'no-matching-order';
            } else {
                switch ($event) {
                    case 'payment.captured':
                    case 'order.paid':
                        $outcome = PaymentApprovalService::markPaid(
                            (int)$row['id'], $payId, '', 'webhook'
                        ) ? 'paid' : 'already-decided';
                        break;
                    case 'payment.failed':
                        $reason = (string)($body['payload']['payment']['entity']['error_description']
                                ?? $body['payload']['payment']['entity']['error_code'] ?? '');
                        $outcome = PaymentApprovalService::markFailed(
                            (int)$row['id'], $reason, 'webhook'
                        ) ? 'failed' : 'already-decided';
                        break;
                    default:
                        $outcome = 'unhandled-event';
                }
            }
        } catch (\Throwable $e) {
            error_log('[webhook/razorpay] processing error: ' . $e->getMessage());
            $outcome = 'error: ' . $e->getMessage();
        }

        WebhookLog::record([
            'event_type'          => $event ?: 'unknown',
            'rzp_event_id'        => $rzpEvId ?: null,
            'razorpay_order_id'   => $orderId ?: null,
            'razorpay_payment_id' => $payId   ?: null,
            'signature_ok'        => 1,
            'raw_payload'         => $rawBody,
            'outcome'             => $outcome,
            'http_status'         => 200,
        ]);
        self::respond200($outcome);
    }

    private static function respond200(string $outcome): void
    {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'outcome' => $outcome]);
    }
}
