<?php
namespace Core;

/**
 * Tiny Razorpay HTTP client. No SDK / Composer — the project doesn't use
 * Composer anywhere else, so we stay consistent and use raw cURL.
 *
 * Only two server-side calls are needed for the Checkout-roundtrip flow
 * we use on the athlete event-registration page:
 *
 *   1. createOrder()           – server-only, hits /v1/orders.
 *   2. verifySignature()       – pure-PHP HMAC-SHA256 + hash_equals.
 *
 * The KEY_SECRET is never sent to the browser. Only KEY_ID is exposed
 * via the create-order JSON response so checkout.js can identify the
 * merchant.
 */
class Razorpay
{
    private string $keyId;
    private string $keySecret;

    public function __construct(?string $keyId = null, ?string $keySecret = null)
    {
        if ($keyId === null || $keySecret === null) {
            $cfg = require CONFIG_ROOT . '/app.php';
            $rzp = $cfg['razorpay'] ?? [];
            $keyId     = $keyId     ?? (string)($rzp['key_id']     ?? '');
            $keySecret = $keySecret ?? (string)($rzp['key_secret'] ?? '');
        }
        if ($keyId === '' || $keySecret === '') {
            throw new \RuntimeException('Razorpay credentials are not configured (RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET).');
        }
        $this->keyId     = $keyId;
        $this->keySecret = $keySecret;
    }

    public function keyId(): string { return $this->keyId; }

    /**
     * Create a Razorpay Order and return the parsed JSON response.
     * Throws \RuntimeException with a concise message on any failure.
     *
     * @param int    $amountPaise   amount in the smallest currency unit
     * @param string $receipt       merchant-side reference (≤ 40 chars)
     * @param string $currency      defaults to INR
     * @param array  $notes         arbitrary string=>string metadata
     */
    public function createOrder(int $amountPaise, string $receipt, string $currency = 'INR', array $notes = []): array
    {
        if ($amountPaise < 100) {
            throw new \RuntimeException('Amount must be at least 100 paise (₹1).');
        }
        $payload = [
            'amount'          => $amountPaise,
            'currency'        => $currency,
            'receipt'         => substr($receipt, 0, 40),
            'payment_capture' => 1,
        ];
        if ($notes) $payload['notes'] = $notes;

        if (!function_exists('curl_init')) {
            throw new \RuntimeException('PHP curl extension is required for Razorpay integration.');
        }
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => $this->keyId . ':' . $this->keySecret,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Razorpay request failed: ' . $err);
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string)$body, true);
        if ($code === 401 || $code === 403) {
            throw new \RuntimeException('Razorpay auth failed (check RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET).', 401);
        }
        if ($code < 200 || $code >= 300) {
            $msg = $data['error']['description'] ?? ('HTTP ' . $code);
            throw new \RuntimeException('Razorpay order creation failed: ' . $msg, 500);
        }
        if (!is_array($data) || empty($data['id'])) {
            throw new \RuntimeException('Razorpay returned an unexpected response.');
        }
        return $data;
    }

    /**
     * Razorpay's documented HMAC-SHA256 signature check for Checkout
     * round-trips:
     *
     *   expected = hash_hmac('sha256', order_id . '|' . payment_id, key_secret)
     *   verify   = hash_equals(expected, signature)
     */
    public function verifySignature(string $orderId, string $paymentId, string $signature): bool
    {
        if ($orderId === '' || $paymentId === '' || $signature === '') return false;
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $this->keySecret);
        return hash_equals($expected, $signature);
    }
}
