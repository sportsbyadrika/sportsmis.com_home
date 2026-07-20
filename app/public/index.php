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

// Autoloader for project namespaces.
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

// Composer autoload for third-party libraries (mPDF certificate
// generation, etc). Optional — keeps non-Composer dev shells working.
$vendorAutoload = dirname(APP_ROOT) . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

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
$router->get('/privacy',                   'PublicController@privacy');
$router->get('/terms',                     'PublicController@terms');
$router->get('/contact',                   'PublicController@contact');
$router->post('/contact',                  'PublicController@contactSubmit');
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
$router->post('/account/password',         'AuthController@changePassword');

// ── Public LED-wall slideshow (no session required) ────────
$router->get('/led-wall',             'LedWallController@loginForm');
$router->post('/led-wall/login',      'LedWallController@login');
$router->get('/led-wall/{hash}',      'LedWallController@show');

// ── Institution Admin Routes ─────────────────────────────
$router->get('/institution/dashboard',             'InstitutionController@dashboard');
$router->get('/institution/public-events',         'InstitutionController@publicEvents');
$router->get('/institution/participating-events',  'InstitutionController@participatingEvents');
$router->post('/institution/events/{hash}/request-participation', 'InstitutionController@submitParticipationRequest');
$router->post('/institution/events/{hash}/open-as-unit',          'InstitutionController@openAsUnit');
$router->get('/institution/profile',               'InstitutionController@profileForm');
$router->post('/institution/profile',              'InstitutionController@updateProfile');
$router->post('/institution/profile/save',         'InstitutionController@ajaxSave');
$router->post('/institution/profile/submit',       'InstitutionController@submitProfile');
$router->get('/institution/events',                'EventController@institutionIndex');
$router->get('/institution/events/create',         'EventController@createForm');
$router->get('/institution/events/{id}/edit',      'EventController@editForm');
$router->get('/institution/events/{id}/edit/{panel}', 'EventController@editPanel');
$router->get('/institution/events/{hash}/participation-requests',                       'EventController@participationRequests');
$router->post('/institution/events/{hash}/participation-requests/{reqId}/decide',       'EventController@decideParticipationRequest');
$router->post('/institution/events/{id}/save',     'EventController@ajaxSave');
$router->post('/institution/events/{id}/submit',   'EventController@submit');
$router->get('/institution/events/{id}/view',      'EventController@view');
$router->get('/institution/events/sports/{sport_id}/categories', 'EventController@categoriesForSport');
$router->get('/institution/events/sports/{sport_id}/items',      'EventController@itemsForSport');
$router->get('/institution/events/categories/{category_id}/events', 'EventController@eventsForCategory');
$router->get('/institution/events/{id}/reports',                       'EventReportController@index');
$router->get('/institution/events/{id}/reports/registration-stats',     'EventReportController@registrationStats');
$router->get('/institution/events/{id}/reports/registration-stats.csv', 'EventReportController@registrationStatsCsv');
$router->get('/institution/events/{id}/reports/fee-collection',        'EventReportController@feeCollection');
$router->get('/institution/events/{id}/reports/competitor-list',       'EventReportController@competitorList');
$router->get('/institution/events/{id}/reports/unit-competitor-list',       'EventReportController@unitCompetitorList');
$router->get('/institution/events/{id}/reports/unit-competitor-list/print', 'EventReportController@unitCompetitorListPrint');
$router->get('/institution/events/{id}/reports/unit-competitor-list.csv',           'EventReportController@unitCompetitorListCsv');
$router->get('/institution/events/{id}/reports/category-competitor-list',           'EventReportController@categoryCompetitorList');
$router->get('/institution/events/{id}/reports/category-competitor-list/print',     'EventReportController@categoryCompetitorListPrint');
$router->get('/institution/events/{id}/reports/category-competitor-list.csv',       'EventReportController@categoryCompetitorListCsv');
$router->get('/institution/events/{id}/reports/event-competitor-list',              'EventReportController@eventCompetitorList');
$router->get('/institution/events/{id}/reports/event-competitor-list/print',        'EventReportController@eventCompetitorListPrint');
$router->get('/institution/events/{id}/reports/event-competitor-list.csv',          'EventReportController@eventCompetitorListCsv');
$router->get('/institution/events/{id}/reports/participants-count',                 'EventReportController@participantsCount');
$router->get('/institution/events/{id}/reports/participants-count/print',           'EventReportController@participantsCountPrint');
$router->get('/institution/events/{id}/reports/participants-count.csv',             'EventReportController@participantsCountCsv');
$router->get('/institution/events/{id}/reports/qualified-athletes',           'EventReportController@qualifiedAthletes');
$router->get('/institution/events/{id}/reports/qualified-athletes/print',     'EventReportController@qualifiedAthletesPrint');
$router->get('/institution/events/{id}/reports/qualified-athletes.csv',       'EventReportController@qualifiedAthletesCsv');
$router->post('/institution/events/{id}/reports/unit-competitor-list/units/{unitId}/email', 'EventReportController@unitEmailSend');
$router->get('/institution/events/{id}/reports/relay-participants',    'EventReportController@relayParticipants');
$router->get('/institution/events/{id}/reports/unit-others',           'EventReportController@unitOthers');
$router->get('/institution/events/{id}/reports/team-entry-approved',   'EventReportController@teamEntryApproved');
$router->get('/institution/events/{id}/certificates',                 'CertificateController@index');
$router->get('/institution/events/{id}/certificates/diagnostic',      'CertificateController@diagnostic');
$router->get('/institution/events/{id}/certificates/settings',        'CertificateController@settingsForm');
$router->post('/institution/events/{id}/certificates/settings',       'CertificateController@settingsSave');
$router->get('/institution/events/{id}/certificates/preview',         'CertificateController@previewSample');
$router->get('/institution/events/{id}/certificates/register',        'CertificateController@issueRegister');
$router->get('/institution/events/{id}/certificates/register.csv',    'CertificateController@issueRegisterCsv');
$router->post('/institution/events/{id}/certificates/athlete-view-toggle', 'CertificateController@toggleAthleteView');
$router->post('/institution/events/{id}/certificates/units/{unitId}',                'CertificateController@generateForUnit');
$router->post('/institution/events/{id}/certificates/units/{unitId}/generate-chunk', 'CertificateController@generateChunkForUnit');
$router->post('/institution/events/{id}/certificates/units/{unitId}/email',          'CertificateController@emailForUnit');
$router->post('/institution/events/{id}/certificates/units/{unitId}/email-chunk',     'CertificateController@emailChunkForUnit');
$router->post('/institution/events/{id}/certificates/units/{unitId}/regenerate-chunk','CertificateController@regenerateChunkForUnit');
$router->post('/institution/events/{id}/certificates/units/{unitId}/reset',           'CertificateController@resetForUnit');
$router->get('/institution/events/{id}/certificates/units/{unitId}/view',   'CertificateController@viewUnit');
$router->get('/institution/events/{id}/certificates/{certId}/view',   'CertificateController@viewOne');
$router->get('/institution/events/{id}/reports/competitor-cards',      'EventReportController@competitorCards');
$router->get('/institution/events/{id}/reports/competitor-cards.json', 'EventReportController@competitorCardsJson');
$router->post('/institution/events/{id}/reports/competitor-cards/generate', 'EventReportController@competitorCardsGenerate');
$router->post('/institution/events/{id}/reports/competitor-cards/email', 'EventReportController@competitorCardsEmail');
$router->post('/institution/events/{id}/reports/competitor-cards/print', 'EventReportController@competitorCardsPrint');
$router->post('/institution/events/{id}/reports/competitor-cards/settings', 'EventReportController@competitorCardsSettings');
$router->get('/institution/registrations',                       'InstitutionController@registrationsList');
$router->get('/institution/registrations/{id}',                  'InstitutionController@registrationDetail');
$router->get('/institution/registrations/{id}/edit',             'InstitutionController@registrationEditForm');
$router->post('/institution/registrations/{id}/edit/save',       'InstitutionController@registrationEditSave');
$router->post('/institution/registrations/{id}/athlete-profile', 'InstitutionController@updateAthleteProfile');
$router->post('/institution/registrations/{id}/decision',        'InstitutionController@registrationDecision');
$router->post('/institution/registrations/{id}/revoke',          'InstitutionController@revokeRegistrationDecision');
$router->post('/institution/registrations/{id}/resend-card',     'InstitutionController@resendCompetitorCard');
$router->post('/institution/registrations/payments/{id}/decision','InstitutionController@paymentDecision');
$router->get('/institution/events/{id}/athletes-by-unit',        'InstitutionController@athletesByUnit');
$router->get('/institution/events/{id}/unit-payments',           'InstitutionController@unitPaymentsList');
$router->post('/institution/unit-payments/{id}/decision',        'InstitutionController@unitPaymentDecision');
$router->get('/institution/events/{id}/units/{unitId}/receipt.pdf', 'InstitutionController@unitReceiptPdf');
$router->post('/institution/registrations/payments/{id}/status', 'InstitutionController@paymentStatusUpdate');
$router->post('/institution/registrations/{id}/payments/add',    'InstitutionController@addManualPayment');
// Unit / Institution / Club users management (per event)
$router->get('/institution/events/{id}/unit-users',                 'InstitutionController@unitUsersList');
$router->post('/institution/events/{id}/unit-users/save',           'InstitutionController@unitUserSave');
$router->post('/institution/events/{id}/unit-users/delete',         'InstitutionController@unitUserDelete');
$router->post('/institution/events/{id}/unit-users/reset-password', 'InstitutionController@unitUserResetPassword');
$router->post('/institution/events/{id}/unit-messages/send',        'InstitutionController@unitMessageSend');
$router->post('/institution/events/{id}/unit-messages/{msgId}/delete','InstitutionController@unitMessageDelete');

// Event Staff users management (per event)
$router->get('/institution/events/{id}/staff-users',                 'InstitutionController@staffUsersList');
$router->post('/institution/events/{id}/staff-users/save',           'InstitutionController@staffUserSave');
$router->post('/institution/events/{id}/staff-users/delete',         'InstitutionController@staffUserDelete');
$router->post('/institution/events/{id}/staff-users/reset-password', 'InstitutionController@staffUserResetPassword');

$router->get('/institution/events/{id}/team-registrations',  'InstitutionController@teamRegistrationsList');
$router->post('/institution/events/{id}/team-registrations/toggle-window', 'InstitutionController@teamEntryToggleWindow');
$router->get('/institution/team-registrations/{id}',           'InstitutionController@teamRegistrationDetail');
$router->post('/institution/team-registrations/{id}/decision', 'InstitutionController@teamRegistrationDecision');
$router->post('/institution/team-registrations/{id}/delete',   'InstitutionController@teamRegistrationDelete');
$router->post('/institution/team-registrations/payments/{id}/decision', 'InstitutionController@teamPaymentDecision');
$router->get('/institution/events/{id}/grievances',          'InstitutionController@eventGrievances');
$router->get('/institution/grievances/{id}',                 'InstitutionController@grievanceShow');
$router->post('/institution/grievances/{id}/reply',          'InstitutionController@grievanceReply');
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
$router->post('/athlete/events/{id}/register/save',           'AthleteController@registerSave');
$router->post('/athlete/events/{id}/register/items/save',     'AthleteController@registerItemSave');
$router->post('/athlete/events/{id}/register/items/delete',   'AthleteController@registerItemDelete');
$router->post('/athlete/events/{id}/register/payments-refresh','AthleteController@registerPaymentsRefresh');
$router->post('/athlete/events/{id}/register/payment-mode',   'AthleteController@registerSetPaymentMode');
$router->post('/athlete/events/{id}/register/payment',        'AthleteController@registerAddPayment');
$router->post('/athlete/events/{id}/register/payment-remove', 'AthleteController@registerRemovePayment');
$router->post('/athlete/events/{id}/register/submit',         'AthleteController@registerSubmit');
$router->post('/athlete/events/{id}/pay/create-order',        'AthleteController@payCreateOrder');
$router->post('/athlete/events/{id}/pay/verify',              'AthleteController@payVerify');
$router->get('/athlete/grievances',                           'AthleteController@grievanceIndex');
$router->get('/athlete/grievances/{id}',                      'AthleteController@grievanceShow');
$router->post('/athlete/grievances/{id}/reply',               'AthleteController@grievanceReply');
$router->get('/athlete/events/{id}/grievances',               'AthleteController@eventGrievances');
$router->post('/athlete/events/{id}/grievances',              'AthleteController@grievanceCreate');
$router->get('/athlete/my-registrations',          'AthleteController@myRegistrations');
$router->get('/athlete/registrations/{id}',        'AthleteController@viewRegistration');
$router->get('/athlete/registrations/{id}/card',        'AthleteController@competitorCard');
$router->get('/athlete/registrations/{id}/certificate', 'CertificateController@viewForAthlete');
$router->get('/athlete/my-results',                     'AthleteController@myResults');
$router->get('/athlete/my-results/{id}/details',        'AthleteController@myResultDetails');

// Team Entry (athlete)
$router->get('/athlete/team-entry',                          'AthleteController@teamEntryIndex');
$router->get('/athlete/team-entry/sport-events',             'AthleteController@teamEntrySportEvents');
$router->post('/athlete/team-entry/create',                  'AthleteController@teamEntryCreate');
$router->get('/athlete/team-entry/{id}',                     'AthleteController@teamEntryShow');
$router->post('/athlete/team-entry/{id}/member-validate',    'AthleteController@teamEntryMemberValidate');
$router->post('/athlete/team-entry/{id}/member-add',         'AthleteController@teamEntryMemberAdd');
$router->post('/athlete/team-entry/{id}/member-remove',      'AthleteController@teamEntryMemberRemove');
$router->post('/athlete/team-entry/{id}/payment-mode',       'AthleteController@teamEntryPaymentMode');
$router->post('/athlete/team-entry/{id}/payment',            'AthleteController@teamEntryAddPayment');
$router->post('/athlete/team-entry/{id}/payment-remove',     'AthleteController@teamEntryRemovePayment');
$router->post('/athlete/team-entry/{id}/submit',             'AthleteController@teamEntrySubmit');
$router->post('/athlete/profile/save',             'AthleteController@ajaxSave');
$router->post('/athlete/profile/submit',           'AthleteController@submitProfile');

// ── Super Admin Routes ───────────────────────────────────
$router->get('/admin/dashboard',                   'AdminController@dashboard');
$router->get('/admin/institutions',                'AdminController@institutions');
$router->get('/admin/institutions/{id}',           'AdminController@institutionDetail');
$router->post('/admin/institutions/{id}/verify',   'AdminController@verifyInstitution');
$router->post('/admin/institutions/{id}/approve',  'AdminController@approveInstitution');
$router->post('/admin/institutions/{id}/reject',   'AdminController@rejectInstitution');
$router->post('/admin/institutions/{id}/toggle-event-creation', 'AdminController@toggleEventCreation');
$router->post('/admin/institutions/{id}/reset-password',        'AdminController@resetInstitutionPassword');
$router->post('/admin/institutions/{id}/login-as',              'AdminController@loginAsInstitution');
$router->get('/admin/stop-impersonating',                       'AdminController@stopImpersonating');
$router->get('/admin/impersonation-log',                        'AdminController@impersonationLog');
$router->get('/admin/athletes',                    'AdminController@athletes');
$router->get('/admin/athletes/{id}/view',          'AdminController@athleteProfile');
$router->get('/admin/athletes/{id}',               'AdminController@athleteDetail');
$router->post('/admin/athletes/{id}/verify',       'AdminController@verifyAthlete');
$router->post('/admin/athletes/{id}/reject',       'AdminController@rejectAthlete');
$router->get('/admin/events',                      'AdminController@events');
$router->post('/admin/events/push-spoc',           'AdminController@pushSpocToUnits');
$router->post('/admin/events/{id}/approve',        'AdminController@approveEvent');
$router->post('/admin/events/{id}/reject',         'AdminController@rejectEvent');
$router->post('/admin/events/{id}/status',         'AdminController@setEventStatus');
$router->post('/admin/events/{id}/delete',         'AdminController@deleteEvent');
$router->get('/admin/registrations',               'AdminController@registrations');
$router->post('/admin/registrations/{id}/delete',  'AdminController@deleteRegistration');
$router->post('/admin/athletes/{id}/delete',       'AdminController@deleteAthlete');

// Admin Reports
$router->get('/admin/event-migrate',                      'AdminMigrationController@index');
$router->get('/admin/event-migrate/events-for-institution','AdminMigrationController@eventsForInstitution');
$router->post('/admin/event-migrate',                     'AdminMigrationController@saveStep1');
$router->get('/admin/event-migrate/items',                'AdminMigrationController@items');
$router->post('/admin/event-migrate/items',               'AdminMigrationController@saveStep2');
$router->get('/admin/event-migrate/preview',              'AdminMigrationController@preview');
$router->post('/admin/event-migrate/run',                 'AdminMigrationController@run');
$router->get('/admin/reports',                     'AdminReportsController@index');
$router->get('/admin/reports/epayments',           'AdminReportsController@epayments');
$router->get('/admin/reports/epayments/pending',   'AdminReportsController@pendingEpayments');
$router->post('/admin/reports/epayments/recheck',  'AdminReportsController@recheckEpayment');

// ── Unit / Institution / Club Portal ─────────────────────────
$router->get('/unit/login',                 'UnitController@loginForm');
$router->post('/unit/login',                'UnitController@login');
$router->get('/unit/logout',                'UnitController@logout');
$router->post('/unit/password/change',      'UnitController@changePassword');
$router->get('/unit/dashboard',             'UnitController@dashboard');
$router->get('/unit/sport-events/{id}/participants', 'UnitController@sportEventParticipants');
$router->get('/unit/registrations',         'UnitController@registrationsList');
$router->post('/unit/registrations/bulk-pay','UnitController@bulkPayRegistrations');
$router->post('/unit/registrations/bulk-submit','UnitController@bulkSubmitRegistrations');
$router->get('/unit/transactions',          'UnitController@transactionsList');
$router->post('/unit/submit-all',           'UnitController@submitAllForUnit');
$router->get('/unit/submittable-counts',    'UnitController@submittableCountsJson');
$router->get('/unit/receipt/{unitId}',      'UnitController@receiptPdf');
$router->get('/unit/participants-report/{unitId}', 'UnitController@participantsReport');
$router->post('/unit/transactions/payments/add',          'UnitController@addUnitPayment');
$router->post('/unit/transactions/payments/{id}/delete',  'UnitController@deleteUnitPayment');
$router->post('/unit/transactions/payments/{id}/submit',  'UnitController@submitUnitPayment');
$router->get('/unit/athletes/new',          'UnitController@addAthleteForm');
$router->post('/unit/athletes',             'UnitController@storeAthlete');
$router->get('/unit/athletes/{id}',         'UnitController@athleteShow');
$router->post('/unit/athletes/{id}/profile',       'UnitController@saveAthleteProfile');
$router->post('/unit/athletes/{id}/items',         'UnitController@saveAthleteItems');
$router->post('/unit/athletes/{id}/items/add',     'UnitController@addAthleteItem');
$router->post('/unit/athletes/{id}/items/remove',  'UnitController@removeAthleteItem');
$router->post('/unit/athletes/{id}/payments',      'UnitController@addAthletePayment');
$router->post('/unit/athletes/{id}/payments/remove','UnitController@removeAthletePayment');
$router->post('/unit/athletes/{id}/submit',        'UnitController@submitAthleteRegistration');
$router->post('/unit/athletes/{id}/delete',        'UnitController@deleteAthleteRegistration');
$router->get('/unit/noc',                   'UnitController@nocIndex');
$router->post('/unit/noc/set',              'UnitController@nocSet');
$router->get('/unit/noc/print',             'UnitController@nocPrint');
$router->post('/unit/unit-logo',            'UnitController@uploadUnitLogo');
$router->get('/unit/team-entry',            'UnitController@teamEntryIndex');

// ── Event Staff Portal ───────────────────────────────────────
$router->get('/event-staff/login',            'EventStaffController@loginForm');
$router->post('/event-staff/login',           'EventStaffController@login');
$router->get('/event-staff/logout',           'EventStaffController@logout');
$router->post('/event-staff/password/change', 'EventStaffController@changePassword');
$router->get('/event-staff/dashboard',        'EventStaffController@dashboard');
$router->get('/event-staff/search',           'EventStaffController@search');
$router->get('/event-staff/search/{id}',      'EventStaffController@searchView');
$router->get('/event-staff/lane-allocation',  'EventStaffController@laneAllocation');
$router->get('/event-staff/scoring',                                'ScoringController@relays');
$router->get('/event-staff/scoring/import',                         'ScoringController@importForm');
$router->post('/event-staff/scoring/import',                        'ScoringController@importProcess');
$router->get('/event-staff/scoring/lookup-competitor',              'ScoringController@lookupCompetitor');
$router->get('/event-staff/scoring/results',                        'ScoringController@enteredResults');
$router->post('/event-staff/scoring/results/update',                'ScoringController@bulkUpdateResults');
$router->post('/event-staff/scoring/save',                          'ScoringController@save');
$router->post('/event-staff/scoring/relay-status',                  'ScoringController@relayStatus');
$router->get('/event-staff/scoring/relays/{id}',                    'ScoringController@lanes');
$router->get('/event-staff/scoring/relays/{id}/print',              'ScoringController@relayReport');
$router->get('/event-staff/scoring/relays/{id}/lanes/{laneId}',     'ScoringController@entry');
$router->get('/event-staff/scoring/relays/{id}/lanes/{laneId}/sheet','ScoringController@laneSheet');
$router->post('/event-staff/scoring/relays/{id}/lanes/{laneId}/delete','ScoringController@deleteLaneEntry');
$router->get('/event-staff/result-reports',                   'EventStaffController@resultReports');
$router->get('/event-staff/result-reports/consolidated',      'EventStaffController@consolidatedReport');
$router->post('/event-staff/result-reports/led-wall-settings','EventStaffController@ledWallSettings');
$router->get('/event-staff/result-reports/relay-result',      'EventStaffController@relayResult');
$router->get('/event-staff/result-reports/event-rank-list',   'EventStaffController@eventRankList');
$router->get('/event-staff/result-reports/team-rank-list',    'EventStaffController@teamRankList');
$router->get('/event-staff/result-reports/medal',             'EventStaffController@medalReport');
$router->get('/event-staff/result-reports/category-top-units','EventStaffController@categoryTopUnits');
$router->get('/event-staff/result-reports/category-event-top3',       'EventStaffController@categoryEventTopThree');
$router->get('/event-staff/result-reports/category-event-top3/print', 'EventStaffController@categoryEventTopThreePrint');
$router->get('/event-staff/result-reports/category-event-top3/live',  'EventStaffController@categoryEventTopThreeLive');

// ── Lane Allocation (shared: Event Staff + Unit users) ───────
$router->get('/lane-allocation',                    'LaneAllocationController@index');
$router->get('/lane-allocation/data',               'LaneAllocationController@data');
$router->post('/lane-allocation/assign',            'LaneAllocationController@assign');
$router->post('/lane-allocation/toggle-unit-access','LaneAllocationController@toggleUnitAccess');

// ── Team Entry (shared: Unit users + Event Staff) ────────────
$router->get('/team-entry',                   'TeamEntryController@index');
$router->get('/team-entry/new',               'TeamEntryController@form');
$router->get('/team-entry/category-events',   'TeamEntryController@categoryEvents');
$router->get('/team-entry/members',           'TeamEntryController@memberOptions');
$router->post('/team-entry/save',             'TeamEntryController@save');
$router->post('/team-entry/bulk-pay',         'TeamEntryController@bulkPay');
$router->post('/team-entry/bulk-submit',      'TeamEntryController@bulkSubmit');
$router->get('/team-entry/{id}',              'TeamEntryController@form');
$router->post('/team-entry/{id}/delete',      'TeamEntryController@delete');

// Public webhook endpoint (server-to-server only, HMAC-verified)
$router->post('/webhook/razorpay',                 'WebhookController@razorpay');

// Admin Settings (sport hierarchy, age categories)
$router->get('/admin/settings',                              'AdminSettingsController@index');
$router->get('/admin/settings/sport-items',                  'AdminSettingsController@sportItemsForm');
$router->post('/admin/settings/sport-items/save',            'AdminSettingsController@sportItemSave');
$router->post('/admin/settings/sport-items/delete',          'AdminSettingsController@sportItemDelete');
$router->get('/admin/settings/sports',                       'AdminSettingsController@sportsForm');
$router->get('/admin/settings/sports/age-categories',        'AdminSettingsController@ageCategoriesForm');
$router->get('/admin/settings/sports/catalog',                            'AdminSettingsController@sportCatalogForm');
$router->get('/admin/settings/sports/{id}/categories',                    'AdminSettingsController@sportCategoriesForm');
$router->get('/admin/settings/sport-categories/{id}/sport-events',        'AdminSettingsController@sportEventsForm');
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
