<?php
/**
 * cleanup_spam_signups.php — safely remove bot self-registration rows.
 *
 * Targets the triple created by the public athlete self-registration flow
 * (users + athletes + athlete_registrations) for accounts that:
 *   • role = 'athlete'
 *   • athlete.created_by_role = 'self'   (unit-managed athletes are safe)
 *   • were created inside a time window you specify
 *   • have NO downstream activity (no event registrations, team memberships,
 *     payments or scores) — i.e. the account was never actually used.
 *
 * It is DRY-RUN by default: it only reports and writes CSV backups. Nothing
 * is deleted unless you pass BOTH --execute and --yes, and a time window.
 *
 * USAGE (run from the server shell, not the browser):
 *   # 1. See the sign-up burst so you can pick the window:
 *   php app/scripts/cleanup_spam_signups.php --report
 *
 *   # 2. Dry run for a window (counts + CSV backup, deletes nothing):
 *   php app/scripts/cleanup_spam_signups.php --from="2024-06-01 00:00:00" --to="2024-06-03 23:59:59"
 *
 *   # 3. Actually delete (irreversible — back up your DB first):
 *   php app/scripts/cleanup_spam_signups.php --from="..." --to="..." --execute --yes
 *
 * Options:
 *   --report            Print per-day / per-hour athlete sign-up counts and exit.
 *   --from=, --to=      created_at window on the users table (REQUIRED to delete).
 *   --execute           Perform deletion (default is dry-run).
 *   --yes               Required confirmation alongside --execute.
 *   --batch=N           Rows per batch (default 1000).
 *   --max=N             Safety ceiling; refuse if more than N rows match
 *                       (default 200000) unless --force is given.
 *   --force             Override the safety ceiling.
 *   --include-google    Also purge auth_provider='google' rows (default: keep).
 */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "This script runs from the command line only.\n"); exit(1); }

define('APP_ROOT',    dirname(__DIR__));
define('CONFIG_ROOT', APP_ROOT . '/config');

// ── Load .env (same loader as public/index.php) ──────────────────────────
$envFile = APP_ROOT . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if ($k !== '') putenv($k . '=' . trim($v, "\"'"));
    }
}

// ── Args ─────────────────────────────────────────────────────────────────
$opt = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z0-9_-]+)(?:=(.*))?$/i', $a, $m)) {
        $opt[$m[1]] = $m[2] ?? true;
    }
}
$report        = isset($opt['report']);
$execute       = isset($opt['execute']);
$confirmed     = isset($opt['yes']);
$force         = isset($opt['force']);
$includeGoogle = isset($opt['include-google']);
$from          = isset($opt['from']) ? (string)$opt['from'] : '';
$to            = isset($opt['to'])   ? (string)$opt['to']   : '';
$batch         = max(100, (int)($opt['batch'] ?? 1000));
$maxCeiling    = max(1,   (int)($opt['max']   ?? 200000));

// ── DB ───────────────────────────────────────────────────────────────────
$cfg = require CONFIG_ROOT . '/database.php';
$dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}";
try {
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options']);
} catch (PDOException $e) {
    fwrite(STDERR, "DB connection failed: {$e->getMessage()}\n");
    exit(1);
}
$tableExists = function (string $t) use ($pdo): bool {
    $s = $pdo->prepare("SELECT 1 FROM information_schema.tables
                         WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $s->execute([$t]);
    return (bool)$s->fetchColumn();
};

echo "── SportsMIS spam sign-up cleanup ──\n";
echo "DB: {$cfg['database']}@{$cfg['host']}   mode: "
   . ($report ? 'REPORT' : ($execute ? 'EXECUTE' : 'DRY-RUN')) . "\n\n";

// ── Report mode ──────────────────────────────────────────────────────────
if ($report) {
    echo "Athlete sign-ups per day (last 60 days):\n";
    foreach ($pdo->query(
        "SELECT DATE(created_at) d, COUNT(*) c FROM users
          WHERE role='athlete' AND created_at >= (NOW() - INTERVAL 60 DAY)
          GROUP BY DATE(created_at) ORDER BY d DESC") as $r) {
        printf("  %s  %8d  %s\n", $r['d'], $r['c'], str_repeat('#', min(60, (int)($r['c'] / 50) + 1)));
    }
    echo "\nBusiest single hours (top 15):\n";
    foreach ($pdo->query(
        "SELECT DATE_FORMAT(created_at,'%Y-%m-%d %H:00') h, COUNT(*) c FROM users
          WHERE role='athlete' GROUP BY h ORDER BY c DESC LIMIT 15") as $r) {
        printf("  %s  %8d\n", $r['h'], $r['c']);
    }
    echo "\nPick the burst window and re-run with --from / --to (dry-run first).\n";
    exit(0);
}

// ── Build the candidate set ──────────────────────────────────────────────
$hasEventRegs = $tableExists('event_registrations');
$hasTeamMems  = $tableExists('team_registration_members');
$hasEventPays = $tableExists('event_registration_payments');
$hasScores    = $tableExists('score_entries');

$where  = ["u.role = 'athlete'", "COALESCE(a.created_by_role,'self') = 'self'"];
$params = [];
if ($from !== '' && $to !== '') {
    $where[] = "u.created_at BETWEEN ? AND ?";
    $params[] = $from; $params[] = $to;
}
if (!$includeGoogle) {
    // Keep Google-verified sign-ups. auth_provider lives on the registration.
    $where[] = "COALESCE(ar.auth_provider,'email') <> 'google'";
}
// Never touch accounts that actually did something.
if ($hasEventRegs) $where[] = "NOT EXISTS (SELECT 1 FROM event_registrations er WHERE er.athlete_id = a.id)";
if ($hasTeamMems)  $where[] = "NOT EXISTS (SELECT 1 FROM team_registration_members trm WHERE trm.athlete_id = a.id)";
if ($hasEventPays) $where[] = "NOT EXISTS (SELECT 1 FROM event_registration_payments p
                                            JOIN event_registrations er2 ON er2.id = p.registration_id
                                           WHERE er2.athlete_id = a.id)";
if ($hasScores)    $where[] = "NOT EXISTS (SELECT 1 FROM score_entries se WHERE se.athlete_id = a.id)";

$whereSql = implode("\n      AND ", $where);
$baseFrom =
    "FROM users u
       JOIN athletes a               ON a.user_id = u.id
  LEFT JOIN athlete_registrations ar ON ar.user_id = u.id
      WHERE {$whereSql}";

// Count + span.
$cnt = $pdo->prepare("SELECT COUNT(*) c, MIN(u.created_at) mn, MAX(u.created_at) mx {$baseFrom}");
$cnt->execute($params);
$agg   = $cnt->fetch();
$total = (int)($agg['c'] ?? 0);

echo "Candidate junk accounts: {$total}\n";
echo "created_at span: " . ($agg['mn'] ?? '—') . "  →  " . ($agg['mx'] ?? '—') . "\n";
echo "Filters: role=athlete, created_by_role=self"
   . ($from !== '' ? ", window {$from}..{$to}" : ", NO WINDOW")
   . ($includeGoogle ? ", incl. google" : ", excl. google")
   . ", no event/team/payment/score activity.\n\n";

if ($total === 0) { echo "Nothing matches. Done.\n"; exit(0); }

// Sample.
$sample = $pdo->prepare("SELECT u.id, u.email, u.created_at {$baseFrom} ORDER BY u.created_at LIMIT 10");
$sample->execute($params);
echo "Sample (first 10):\n";
foreach ($sample as $r) printf("  #%-8d %-40s %s\n", $r['id'], $r['email'], $r['created_at']);
echo "\n";

// ── Guardrails ───────────────────────────────────────────────────────────
if (!$execute) {
    echo "DRY-RUN — nothing deleted. Re-run with --execute --yes (and a --from/--to window) to remove these.\n";
    // Still write a backup of the IDs so you have a record.
}
if ($execute) {
    if ($from === '' || $to === '') {
        fwrite(STDERR, "REFUSING to delete without a --from/--to window. Aborting.\n"); exit(2);
    }
    if (!$confirmed) {
        fwrite(STDERR, "REFUSING to delete without --yes. Aborting.\n"); exit(2);
    }
    if ($total > $maxCeiling && !$force) {
        fwrite(STDERR, "REFUSING: {$total} rows exceed the safety ceiling ({$maxCeiling}). "
                     . "Re-check the window, or pass --force if this is intended.\n"); exit(2);
    }
}

// ── Backup directory ─────────────────────────────────────────────────────
$stamp   = date('Ymd-His');
$bkpRoot = APP_ROOT . '/storage/spam-cleanup-' . $stamp;
if (!is_dir($bkpRoot) && !@mkdir($bkpRoot, 0775, true) && !is_dir($bkpRoot)) {
    $bkpRoot = sys_get_temp_dir() . '/spam-cleanup-' . $stamp;
    @mkdir($bkpRoot, 0775, true);
}
echo "Backups: {$bkpRoot}\n\n";

$dumpCsv = function (string $file, PDOStatement $stmt): int {
    $fh = fopen($file, 'w'); $n = 0; $header = false;
    while ($row = $stmt->fetch()) {
        if (!$header) { fputcsv($fh, array_keys($row)); $header = true; }
        fputcsv($fh, $row); $n++;
    }
    fclose($fh);
    return $n;
};

// Collect the target user_ids (ordered, for stable batching).
$idStmt = $pdo->prepare("SELECT u.id {$baseFrom} ORDER BY u.id");
$idStmt->execute($params);
$userIds = array_map('intval', $idStmt->fetchAll(PDO::FETCH_COLUMN));

// Always back up the full targeted rows before touching anything.
$mkIn = fn(array $ids) => implode(',', array_map('intval', $ids));
if ($userIds) {
    $in = $mkIn($userIds);
    $dumpCsv("{$bkpRoot}/users.csv",
        $pdo->query("SELECT * FROM users WHERE id IN ({$in})"));
    $dumpCsv("{$bkpRoot}/athletes.csv",
        $pdo->query("SELECT * FROM athletes WHERE user_id IN ({$in})"));
    $dumpCsv("{$bkpRoot}/athlete_registrations.csv",
        $pdo->query("SELECT * FROM athlete_registrations WHERE user_id IN ({$in})"));
    echo "Backed up " . count($userIds) . " accounts (users/athletes/athlete_registrations).\n";
}

if (!$execute) {
    echo "\nDRY-RUN complete. Review the CSVs above, then re-run with --execute --yes.\n";
    exit(0);
}

// ── Delete in FK-safe batches: registrations → athletes → users ──────────
$deleted = ['athlete_registrations' => 0, 'athletes' => 0, 'users' => 0];
$chunks  = array_chunk($userIds, $batch);
$i = 0;
foreach ($chunks as $chunk) {
    $i++;
    $in = $mkIn($chunk);
    $pdo->beginTransaction();
    try {
        // Re-assert the no-activity guard at delete time (defence in depth):
        // drop any id that gained activity since the scan.
        if ($hasEventRegs) {
            $safe = $pdo->query("SELECT u.id FROM users u JOIN athletes a ON a.user_id=u.id
                                  WHERE u.id IN ({$in})
                                    AND NOT EXISTS (SELECT 1 FROM event_registrations er WHERE er.athlete_id=a.id)")
                        ->fetchAll(PDO::FETCH_COLUMN);
            $chunk = array_map('intval', $safe);
            $in = $mkIn($chunk);
        }
        if (!$chunk) { $pdo->commit(); continue; }

        $deleted['athlete_registrations'] += $pdo->exec("DELETE FROM athlete_registrations WHERE user_id IN ({$in})");
        $deleted['athletes']              += $pdo->exec("DELETE FROM athletes WHERE user_id IN ({$in})");
        $deleted['users']                 += $pdo->exec("DELETE FROM users WHERE id IN ({$in})");
        $pdo->commit();
        printf("  batch %d/%d — removed %d accounts\n", $i, count($chunks), count($chunk));
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "  batch {$i} FAILED, rolled back: {$e->getMessage()}\n");
    }
}

echo "\nDone. Deleted: "
   . "{$deleted['users']} users, {$deleted['athletes']} athletes, "
   . "{$deleted['athlete_registrations']} registrations.\n";
echo "Backups kept at {$bkpRoot}\n";
