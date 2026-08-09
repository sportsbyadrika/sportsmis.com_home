<?php
declare(strict_types=1);

/**
 * SportsMIS mobile/JSON API — front controller.
 *
 * This entry point deliberately boots the SAME domain layer as the web app
 * (app/core, app/models, app/services). Business logic must NOT be duplicated
 * here: API endpoints call the same Models/Services the web controllers use,
 * so results, medals and scoring can never drift between web and mobile.
 *
 * Docroot for the api.sportsmis.com subdomain should point at THIS directory
 * (repo /api/public). See MONOREPO.md.
 */

// Reuse the web app's code as the shared domain layer.
define('APP_ROOT',    dirname(__DIR__, 2) . '/app');
define('CONFIG_ROOT', APP_ROOT . '/config');
define('REPO_ROOT',   dirname(__DIR__, 2));

// ── .env (same file the web app reads) ────────────────────────────────────
$envFile = REPO_ROOT . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, '"\'');
        if ($key !== '') putenv("{$key}={$value}");
    }
}

header('Content-Type: application/json; charset=UTF-8');

// JSON error envelope — never leak stack traces to API clients.
set_exception_handler(function (Throwable $e) {
    error_log('[SportsMIS API] ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'internal_error']);
    exit;
});

// Same project autoloader as app/public/index.php (shared domain layer).
spl_autoload_register(function (string $class) {
    $map = [
        'Core\\'     => APP_ROOT . '/core/',
        'Models\\'   => APP_ROOT . '/models/',
        'Services\\' => APP_ROOT . '/services/',
        'Api\\'      => dirname(__DIR__) . '/src/',
    ];
    foreach ($map as $prefix => $base) {
        if (!str_starts_with($class, $prefix)) continue;
        $file = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) { require $file; return; }
    }
});

$vendorAutoload = REPO_ROOT . '/vendor/autoload.php';
if (is_file($vendorAutoload)) require_once $vendorAutoload;

$appConfig = require CONFIG_ROOT . '/app.php';
date_default_timezone_set($appConfig['timezone']);

// ── Minimal versioned routing (scaffold) ──────────────────────────────────
// The mobile app targets /api/v1/*. This is intentionally tiny for now — the
// full endpoint set lands with the mobile-app phase. Health/version prove the
// shared domain layer is wired.
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path   = '/' . trim($path, '/');

switch ($path) {
    case '/':
    case '/api':
    case '/api/v1':
    case '/api/v1/health':
        echo json_encode(['ok' => true, 'service' => 'sportsmis-api', 'version' => 'v1']);
        break;

    case '/api/v1/version':
        // Demonstrates loading the shared config; extend with build metadata.
        echo json_encode([
            'ok'       => true,
            'api'      => 'v1',
            'timezone' => $appConfig['timezone'] ?? null,
        ]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'not_found', 'path' => $path]);
}
