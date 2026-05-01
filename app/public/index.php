<?php
declare(strict_types=1);

define('APP_ROOT',    dirname(__DIR__));
define('CONFIG_ROOT', APP_ROOT . '/config');
define('PUBLIC_ROOT', __DIR__);

// ── Load .env file if present ─────────────────────────────────────────────
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

// ── Global exception handler (prevents blank 500 pages) ──────────────────
set_exception_handler(function (Throwable $e) {
    $isDebug = (getenv('APP_ENV') === 'local');
    error_log('[SportsMIS] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if ($isDebug) {
        echo '<pre style="font:14px monospace;padding:20px;background:#1e1e1e;color:#f8f8f2">';
        echo '<b style="color:#ff6b6b">' . htmlspecialchars(get_class($e)) . '</b>: ';
        echo htmlspecialchars($e->getMessage()) . "\n\n";
        echo htmlspecialchars($e->getTraceAsString());
        echo '</pre>';
    } else {
        require APP_ROOT . '/views/errors/500.php';
    }
    exit;
});

// Autoloader
spl_autoload_register(function (string $class) {
    $map = [
        'Core\\'        => APP_ROOT . '/core/',
        'Controllers\\' => APP_ROOT . '/controllers/',
        'Models\\'      => APP_ROOT . '/models/',
    ];
    foreach ($map as $prefix => $base) {
        if (!str_starts_with($class, $prefix)) continue;
        $file = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (file_exists($file)) { require $file; return; }
    }
});

// Helpers
require APP_ROOT . '/core/helpers.php';

// Bootstrap
$appConfig = require CONFIG_ROOT . '/app.php';
date_default_timezone_set($appConfig['timezone']);
error_reporting($appConfig['debug'] ? E_ALL : 0);
ini_set('display_errors', $appConfig['debug'] ? '1' : '0');

// Session
$sessionCfg = $appConfig['session'];
session_name($sessionCfg['name']);
session_set_cookie_params([
    'lifetime' => $sessionCfg['lifetime'],
    'path'     => '/',
    'secure'   => !$appConfig['debug'],
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Router
$router = new Core\Router();

// ── Auth Routes ──────────────────────────────────────────
$router->get('/',                          'AuthController@loginForm');
$router->get('/login',                     'AuthController@loginForm');
$router->post('/login',                    'AuthController@login');
$router->get('/institution/login',         'AuthController@institutionLoginForm');
$router->get('/logout',                    'AuthController@logout');
$router->get('/register/institution',      'AuthController@registerInstitutionForm');
$router->post('/register/institution',     'AuthController@registerInstitution');
$router->get('/register/athlete',          'AuthController@registerAthleteForm');
$router->post('/register/athlete',         'AuthController@registerAthlete');
$router->get('/register/pending',          'AuthController@pendingVerification');
$router->get('/auth/google',               'AuthController@googleRedirect');
$router->get('/auth/google/callback',      'AuthController@googleCallback');
$router->get('/password/forgot',           'AuthController@forgotForm');
$router->post('/password/forgot',          'AuthController@forgotPassword');
$router->get('/password/reset/{token}',    'AuthController@resetForm');
$router->post('/password/reset',           'AuthController@resetPassword');

// ── Institution Admin Routes ─────────────────────────────
$router->get('/institution/dashboard',             'InstitutionController@dashboard');
$router->get('/institution/profile',               'InstitutionController@profileForm');
$router->post('/institution/profile',              'InstitutionController@updateProfile');
$router->post('/institution/profile/save',         'InstitutionController@ajaxSave');
$router->post('/institution/profile/submit',       'InstitutionController@submitProfile');
$router->get('/institution/events',                'EventController@institutionIndex');
$router->get('/institution/events/create',         'EventController@createForm');
$router->get('/institution/events/{id}/edit',      'EventController@editForm');
$router->post('/institution/events/{id}/save',     'EventController@ajaxSave');
$router->post('/institution/events/{id}/submit',   'EventController@submit');
$router->get('/institution/events/{id}/view',      'EventController@view');
$router->get('/institution/events/sports/{sport_id}/categories', 'EventController@categoriesForSport');
$router->get('/institution/events/categories/{category_id}/events', 'EventController@eventsForCategory');
$router->get('/institution/staff',                 'InstitutionController@staffIndex');
$router->get('/institution/staff/create',          'InstitutionController@staffCreateForm');
$router->post('/institution/staff/create',         'InstitutionController@staffCreate');
$router->get('/institution/staff/{id}/edit',       'InstitutionController@staffEditForm');
$router->post('/institution/staff/{id}/edit',      'InstitutionController@staffUpdate');

// ── Athlete Routes ───────────────────────────────────────
$router->get('/athlete/dashboard',                 'AthleteController@dashboard');
$router->get('/athlete/profile',                   'AthleteController@profileForm');
$router->post('/athlete/profile',                  'AthleteController@updateProfile');
$router->get('/athlete/events',                    'AthleteController@browseEvents');
$router->get('/athlete/events/{id}',               'AthleteController@eventDetail');
$router->get('/athlete/events/{id}/register',      'AthleteController@registerForm');
$router->post('/athlete/events/{id}/register/save',   'AthleteController@registerSave');
$router->post('/athlete/events/{id}/register/submit', 'AthleteController@registerSubmit');
$router->get('/athlete/my-registrations',          'AthleteController@myRegistrations');
$router->get('/athlete/registrations/{id}',        'AthleteController@viewRegistration');
$router->post('/athlete/profile/save',             'AthleteController@ajaxSave');
$router->post('/athlete/profile/submit',           'AthleteController@submitProfile');

// ── Super Admin Routes ───────────────────────────────────
$router->get('/admin/dashboard',                   'AdminController@dashboard');
$router->get('/admin/institutions',                'AdminController@institutions');
$router->get('/admin/institutions/{id}',           'AdminController@institutionDetail');
$router->post('/admin/institutions/{id}/verify',   'AdminController@verifyInstitution');
$router->post('/admin/institutions/{id}/approve',  'AdminController@approveInstitution');
$router->post('/admin/institutions/{id}/reject',   'AdminController@rejectInstitution');
$router->get('/admin/athletes',                    'AdminController@athletes');
$router->get('/admin/athletes/{id}',               'AdminController@athleteDetail');
$router->post('/admin/athletes/{id}/verify',       'AdminController@verifyAthlete');
$router->post('/admin/athletes/{id}/reject',       'AdminController@rejectAthlete');
$router->get('/admin/events',                      'AdminController@events');
$router->post('/admin/events/{id}/approve',        'AdminController@approveEvent');
$router->post('/admin/events/{id}/reject',         'AdminController@rejectEvent');
$router->post('/admin/events/{id}/status',         'AdminController@setEventStatus');

// Admin Settings (sport hierarchy, age categories)
$router->get('/admin/settings/sports',                       'AdminSettingsController@sportsForm');
$router->post('/admin/settings/sports/toggle',               'AdminSettingsController@toggleSport');
$router->post('/admin/settings/age-categories/save',         'AdminSettingsController@ageCategorySave');
$router->post('/admin/settings/age-categories/delete',       'AdminSettingsController@ageCategoryDelete');
$router->post('/admin/settings/sport-categories/save',       'AdminSettingsController@categorySave');
$router->post('/admin/settings/sport-categories/delete',     'AdminSettingsController@categoryDelete');
$router->get('/admin/settings/sport-categories/{id}/events', 'AdminSettingsController@categorySportEvents');
$router->post('/admin/settings/sport-events/save',           'AdminSettingsController@sportEventSave');
$router->post('/admin/settings/sport-events/delete',         'AdminSettingsController@sportEventDelete');

// ── API (JSON) ───────────────────────────────────────────
$router->get('/api/states/{country_id}',           'ApiController@states');
$router->get('/api/districts/{state_id}',          'ApiController@districts');

// Dispatch
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
