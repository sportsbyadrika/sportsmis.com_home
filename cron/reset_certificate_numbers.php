<?php
/**
 * One-time reset of certificate numbers for an event.
 *
 * After repeated Generate / Reset cycles during testing, the
 * cert_no_next counter on an event drifts upward even though no
 * "live" certificates remain at those numbers. This script wipes the
 * event_certificates rows for the chosen event AND resets
 * events.cert_no_next to 1, so the next Generate restarts numbering
 * from 0001.
 *
 * Usage:
 *   php cron/reset_certificate_numbers.php
 *      # lists every event with its current cert_no_next + issued count
 *
 *   php cron/reset_certificate_numbers.php <event_id>
 *      # prompts for confirmation, then wipes and resets the event
 *
 *   php cron/reset_certificate_numbers.php <event_id> --yes
 *      # skip the confirmation prompt
 *
 *   php cron/reset_certificate_numbers.php <event_id> --yes --regenerate
 *      # also re-issue certificates for every approved registration
 *      # on the event, allocating fresh sequences starting from 1
 *
 * CLI-only. Safe to run multiple times — every step is idempotent.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    fwrite(STDERR, "reset_certificate_numbers.php is CLI-only\n");
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

try { \Models\Schema::ensureCertificates(); } catch (\Throwable $e) {
    fwrite(STDERR, '[reset] schema ensure: ' . $e->getMessage() . "\n");
}

// ── Parse args ──────────────────────────────────────────────────────
$args = array_slice($argv, 1);
$flags = [
    'yes'        => in_array('--yes',        $args, true) || in_array('-y', $args, true),
    'regenerate' => in_array('--regenerate', $args, true) || in_array('-r', $args, true),
];
$eventId = 0;
foreach ($args as $a) {
    if ($a[0] === '-') continue;
    if (ctype_digit($a)) { $eventId = (int)$a; break; }
}

// ── No event id → list ──────────────────────────────────────────────
if ($eventId <= 0) {
    $events = \Models\Event::rowsRaw(
        "SELECT e.id, e.name, e.event_code, e.cert_no_prefix, e.cert_no_suffix,
                e.cert_no_next,
                (SELECT COUNT(*) FROM event_certificates ec WHERE ec.event_id = e.id) AS issued
           FROM events e
          ORDER BY e.id DESC", []
    );
    fwrite(STDOUT, "Events with cert config:\n\n");
    fwrite(STDOUT, sprintf("%-6s %-40s %-12s %-12s %-10s %-7s\n",
        'ID', 'Name', 'Prefix', 'Suffix', 'Next #', 'Issued'));
    fwrite(STDOUT, str_repeat('-', 90) . "\n");
    foreach ($events as $e) {
        fwrite(STDOUT, sprintf("%-6d %-40s %-12s %-12s %-10d %-7d\n",
            (int)$e['id'],
            mb_substr((string)$e['name'], 0, 40),
            (string)($e['cert_no_prefix'] ?? ($e['event_code'] ?? '')),
            (string)($e['cert_no_suffix'] ?? ''),
            (int)($e['cert_no_next'] ?? 1),
            (int)$e['issued']
        ));
    }
    fwrite(STDOUT, "\nUsage:  php cron/reset_certificate_numbers.php <event_id> [--yes] [--regenerate]\n");
    exit(0);
}

// ── Verify the event exists ─────────────────────────────────────────
$event = \Models\Event::findById($eventId);
if (!$event) {
    fwrite(STDERR, "[reset] event id {$eventId} not found\n");
    exit(1);
}

$issued = (int)(\Models\Event::rowsRaw(
    "SELECT COUNT(*) AS c FROM event_certificates WHERE event_id = ?",
    [$eventId])[0]['c'] ?? 0);

fwrite(STDOUT, "Event : #{$eventId}  {$event['name']}\n");
fwrite(STDOUT, "Prefix: " . ($event['cert_no_prefix'] ?? ($event['event_code'] ?? '')) . "\n");
fwrite(STDOUT, "Suffix: " . ($event['cert_no_suffix'] ?? '') . "\n");
fwrite(STDOUT, "Current next sequence: " . (int)($event['cert_no_next'] ?? 1) . "\n");
fwrite(STDOUT, "Existing certificates: {$issued}\n\n");

fwrite(STDOUT, "About to:\n");
fwrite(STDOUT, "  1. DELETE all {$issued} certificate row(s) for this event\n");
fwrite(STDOUT, "  2. RESET events.cert_no_next to 1\n");
if ($flags['regenerate']) {
    fwrite(STDOUT, "  3. RE-ISSUE certificates for every approved registration, sequence starting at 1\n");
}
fwrite(STDOUT, "\nThis cannot be undone.\n");

if (!$flags['yes']) {
    fwrite(STDOUT, "Type 'yes' to continue: ");
    $reply = trim((string)fgets(STDIN));
    if (strtolower($reply) !== 'yes') {
        fwrite(STDOUT, "Aborted.\n");
        exit(0);
    }
}

// ── 1. Delete existing certs ────────────────────────────────────────
\Models\Event::rowsRaw("DELETE FROM event_certificates WHERE event_id = ?", [$eventId]);
fwrite(STDOUT, "[reset] deleted {$issued} certificate row(s)\n");

// ── 2. Reset sequence counter ───────────────────────────────────────
\Models\Event::updatePartial($eventId, ['cert_no_next' => 1]);
fwrite(STDOUT, "[reset] events.cert_no_next set to 1\n");

if (!$flags['regenerate']) {
    fwrite(STDOUT, "\nDone. Next Generate from the UI will issue certificates starting at 1.\n");
    exit(0);
}

// ── 3. Re-issue certs for every approved registration ──────────────
$regs = \Models\Event::rowsRaw(
    "SELECT er.id, er.unit_id
       FROM event_registrations er
      WHERE er.event_id = ?
        AND er.admin_review_status = 'approved'
      ORDER BY er.unit_id, er.competitor_number, er.id",
    [$eventId]
);

if (!$regs) {
    fwrite(STDOUT, "[reset] no approved registrations to re-issue\n");
    exit(0);
}

// Inline allocator — composeCertNo mirrors CertificateController.
$compose = function (array $ev, int $seq): string {
    $prefix = trim((string)($ev['cert_no_prefix'] ?? ($ev['event_code'] ?? '')));
    $suffix = trim((string)($ev['cert_no_suffix'] ?? ''));
    $seqStr = str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    $parts = array_values(array_filter([$prefix, $seqStr, $suffix],
        fn($p) => $p !== '' && $p !== null));
    return implode('/', $parts);
};

$count = 0;
foreach ($regs as $r) {
    // Re-read event for latest cert_no_next on every iteration so
    // sequence is monotonic.
    $ev   = \Models\Event::findById($eventId);
    $next = (int)($ev['cert_no_next'] ?? 1);
    $no   = $compose($ev, $next);

    \Models\Event::rowsRaw(
        "INSERT INTO event_certificates
            (event_id, registration_id, certificate_no, cert_no_sequence, generated_by_name)
         VALUES (?, ?, ?, ?, ?)",
        [$eventId, (int)$r['id'], $no, $next, 'reset_certificate_numbers.php']
    );
    \Models\Event::updatePartial($eventId, ['cert_no_next' => $next + 1]);
    $count++;
}

fwrite(STDOUT, "[reset] re-issued {$count} certificate(s) starting at 1\n");
fwrite(STDOUT, "Done.\n");
