<?php
/**
 * Phase-7 reconciliation cron — sweeps "stuck" pending ePayments and asks
 * Razorpay for the truth. CLI-only.
 *
 * Selection criteria:
 *   payment_method = 'epayment'
 *   status         = 'pending'
 *   created_at     between (now - 24h) and (now - 10m)
 *
 * - The 10-minute lower bound gives the browser-side verify and webhook
 *   a fair chance to land first.
 * - The 24-hour upper bound keeps the workload tight; rows older than a
 *   day where Razorpay has no payment record are almost certainly
 *   abandoned attempts.
 *
 * Idempotent: PaymentApprovalService::markPaid / markFailed both UPDATE
 * with status='pending' as a guard, so concurrent runs are safe.
 *
 *   Recommended schedule:  every 15 minutes (cron expression below; the
 *   slash-star is shown with a backslash so this comment block stays open):
 *     [slash-star]/15 * * * *  /usr/bin/php /path/to/sportsmis.com_home/cron/reconcile.php >> /var/log/sportsmis/reconcile.log 2>&1
 *   Replace [slash-star] with the literal characters /15 (omit brackets).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    fwrite(STDERR, "reconcile.php is CLI-only\n");
    exit(1);
}

define('APP_ROOT',    dirname(__DIR__) . '/app');
define('CONFIG_ROOT', APP_ROOT . '/config');

$envFile = APP_ROOT . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, '"\'');
        if ($key !== '') putenv("{$key}={$value}");
    }
}

spl_autoload_register(function (string $class) {
    $map = [
        'Core\\'        => APP_ROOT . '/core/',
        'Controllers\\' => APP_ROOT . '/controllers/',
        'Models\\'      => APP_ROOT . '/models/',
        'Services\\'    => APP_ROOT . '/services/',
    ];
    foreach ($map as $prefix => $base) {
        if (!str_starts_with($class, $prefix)) continue;
        $file = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (file_exists($file)) { require $file; return; }
    }
});

require APP_ROOT . '/core/helpers.php';

date_default_timezone_set('Asia/Kolkata');

try { \Models\Schema::ensureSportHierarchy(); } catch (\Throwable $e) {
    fwrite(STDERR, '[reconcile] schema ensure: ' . $e->getMessage() . "\n");
}

// Find stuck rows directly via a model query so we don't tie ourselves to
// any controller. Both bounds are inclusive of the IST timestamp because
// the column uses CURRENT_TIMESTAMP (server local).
$rows = \Models\EventRegistrationPayment::stuckPendingEpayments(10, 24 * 60);

if (!$rows) {
    fwrite(STDOUT, '[' . date('c') . "] reconcile: no stuck rows\n");
    exit(0);
}

$rzp = new \Core\Razorpay();
$summary = ['paid' => 0, 'failed' => 0, 'still-open' => 0, 'no-payment-attempted' => 0,
            'already-decided' => 0, 'error' => 0];

foreach ($rows as $row) {
    $orderId = (string)($row['razorpay_order_id'] ?? '');
    if ($orderId === '') {
        $summary['error']++;
        fwrite(STDERR, "[reconcile] row #{$row['id']} has no razorpay_order_id, skipping\n");
        continue;
    }
    try {
        $payments = $rzp->fetchOrderPayments($orderId);
        $outcome  = \Services\PaymentApprovalService::applyOrderPayments(
            (int)$row['id'], $payments, 'reconcile'
        );
        $summary[$outcome] = ($summary[$outcome] ?? 0) + 1;
        fwrite(STDOUT, '[' . date('c') . "] row #{$row['id']} order={$orderId} outcome={$outcome}\n");

        // Mark the row reconciled regardless of outcome so the next cron
        // run doesn't re-examine an order with no payments forever.
        \Models\EventRegistrationPayment::updateRow((int)$row['id'], [
            'reconciled_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (\Throwable $e) {
        $summary['error']++;
        fwrite(STDERR, "[reconcile] row #{$row['id']} order={$orderId} ERROR: " . $e->getMessage() . "\n");
    }
}

fwrite(STDOUT, '[' . date('c') . '] reconcile summary: ' . json_encode($summary) . "\n");
