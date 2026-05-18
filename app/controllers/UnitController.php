<?php
namespace Controllers;

use Core\{Controller, Auth, FileUpload};
use Models\{UnitUser, Event, EventUnit, EventRegistration, EventRegistrationPayment, EventDocument, Athlete, Schema};

/**
 * Separate login portal + dashboard for Unit / Institution / Club users.
 * Auth lives in $_SESSION['unit_user'] (independent of $_SESSION['user']
 * so a unit user can coexist with another role on the same browser).
 *
 * Forward-looking placeholders (Team Entry, Lane Allocation) are kept as
 * clearly-marked stubs so future prompts can drop into them.
 */
class UnitController extends Controller
{
    private array $unitUser;
    private array $event;

    private function boot(): void
    {
        try { Schema::ensureUnitUsers(); } catch (\Throwable $e) {}
        if (!Auth::unitUserCheck()) {
            $this->redirect('/unit/login', 'Please sign in to continue.', 'warning');
        }
        $session = Auth::unitUser();
        $u = UnitUser::findById((int)$session['id']);
        if (!$u || $u['status'] !== 'active') {
            Auth::unitUserLogout();
            $this->redirect('/unit/login', 'Your unit user account is not active.', 'error');
        }
        $event = Event::findById((int)$u['event_id']);
        if (!$event) {
            Auth::unitUserLogout();
            $this->redirect('/unit/login', 'Event no longer exists.', 'error');
        }
        $event['event_code'] = $event['event_code'] ?? \ensureEventCode((int)$event['id']);
        $this->unitUser = $u;
        $this->event    = $event;
    }

    // ── Auth ─────────────────────────────────────────────────────────────────

    public function loginForm(): void
    {
        if (Auth::unitUserCheck()) $this->redirect('/unit/dashboard');
        $this->renderWith('auth', 'unit/login', [
            'flash' => $this->flash(),
        ]);
    }

    public function login(): void
    {
        $this->verifyCsrf();
        $code     = trim((string)($_POST['event_code'] ?? ''));
        $email    = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');

        $user = UnitUser::attempt($code, $email, $password);
        if (!$user) {
            $this->redirect('/unit/login', 'Invalid Event Code, email or password.', 'error');
        }
        Auth::unitUserLogin($user);
        $this->redirect('/unit/dashboard');
    }

    public function logout(): void
    {
        Auth::unitUserLogout();
        $this->redirect('/unit/login', 'Signed out.');
    }

    public function changePassword(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $current = (string)($_POST['current_password']      ?? '');
        $new     = (string)($_POST['password']              ?? '');
        $confirm = (string)($_POST['password_confirmation'] ?? '');
        $back    = '/unit/dashboard';
        if ($current === '' || $new === '' || $confirm === '') {
            $this->redirect($back, 'All three password fields are required.', 'error');
        }
        if (strlen($new) < 8) {
            $this->redirect($back, 'New password must be at least 8 characters.', 'error');
        }
        if ($new !== $confirm) {
            $this->redirect($back, 'New password and confirmation do not match.', 'error');
        }
        if (!password_verify($current, $this->unitUser['password'])) {
            $this->redirect($back, 'Current password is incorrect.', 'error');
        }
        UnitUser::updatePassword((int)$this->unitUser['id'], Auth::hashPassword($new));
        $this->redirect($back, 'Password updated successfully.');
    }

    // ── Dashboard ────────────────────────────────────────────────────────────

    public function dashboard(): void
    {
        $this->boot();
        $units = UnitUser::assignmentsFor((int)$this->unitUser['id']);
        if (!$units) {
            $this->renderWith('unit', 'unit/dashboard', [
                'unit_user'     => $this->unitUser,
                'event'         => $this->event,
                'units'         => [],
                'active_unit'   => null,
                'stats'         => ['total' => 0, 'approved' => 0],
                'registrations' => [],
                'flash'         => $this->flash(),
            ]);
            return;
        }
        // Pick the active unit from session, ?unit_id=, or first by default.
        $requested = (int)($_GET['unit_id'] ?? ($_SESSION['unit_active_unit_id'] ?? 0));
        $active = null;
        foreach ($units as $u) if ((int)$u['id'] === $requested) { $active = $u; break; }
        if (!$active) $active = $units[0];
        $_SESSION['unit_active_unit_id'] = (int)$active['id'];

        $stats         = $this->statsForUnit((int)$active['id']);
        $registrations = $this->registrationsForUnit((int)$active['id']);

        $this->renderWith('unit', 'unit/dashboard', [
            'unit_user'     => $this->unitUser,
            'event'         => $this->event,
            'units'         => $units,
            'active_unit'   => $active,
            'stats'         => $stats,
            'registrations' => $registrations,
            'flash'         => $this->flash(),
        ]);
    }

    /** GET /unit/athletes/{regId} — read-only registration detail. */
    public function athleteShow(string $regId): void
    {
        $this->boot();
        $rid = \hid_reg_decode($regId);
        $reg = EventRegistration::withProfile((int)$rid);
        if (!$reg || (int)$reg['event_id'] !== (int)$this->event['id']) $this->abort(404);

        // Authorise: the registration's unit_id must be among the unit_user's
        // assigned units. Free-text "Other" units are intentionally NOT
        // surfaced to unit users because they aren't tied to any specific
        // unit account.
        $allowedUnitIds = UnitUser::assignmentIds((int)$this->unitUser['id']);
        if (empty($reg['unit_id']) || !in_array((int)$reg['unit_id'], $allowedUnitIds, true)) {
            $this->abort(403);
        }
        $athlete = Athlete::findById((int)$reg['athlete_id']);

        $this->renderWith('unit', 'unit/athlete', [
            'unit_user'    => $this->unitUser,
            'event'        => $this->event,
            'registration' => $reg,
            'athlete'      => $athlete,
            'items'        => EventRegistration::items((int)$rid),
            'sport_items'  => \Models\RegistrationSportItem::forRegistration((int)$rid),
            'payments'     => EventRegistrationPayment::forRegistration((int)$rid),
            'pay_totals'   => EventRegistrationPayment::totals((int)$rid),
            'documents'    => EventDocument::activeForEvent((int)$this->event['id']),
            'flash'        => $this->flash(),
        ]);
    }

    /**
     * Legacy entry point — Team Entry is now a shared module served from
     * /team-entry (TeamEntryController). Kept so old bookmarks still work.
     */
    public function teamEntryIndex(): void
    {
        $this->redirect('/team-entry');
    }

    /**
     * POST /unit/unit-logo — upload a (square-cropped) logo for one of the
     * unit user's assigned units, from the dashboard Unit Details panel.
     */
    public function uploadUnitLogo(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $unitId  = (int)($_POST['unit_id'] ?? 0);
        $allowed = UnitUser::assignmentIds((int)$this->unitUser['id']);
        if (!$unitId || !in_array($unitId, $allowed, true)) {
            $this->json(['success' => false, 'message' => 'You are not permitted to manage this unit.']);
        }
        $unit = EventUnit::find($unitId);
        if (!$unit || (int)$unit['event_id'] !== (int)$this->event['id']) {
            $this->json(['success' => false, 'message' => 'Unit not found for this event.']);
        }
        if (empty($_FILES['logo']) || empty($_FILES['logo']['name'])) {
            $this->json(['success' => false, 'message' => 'No logo image received.']);
        }
        try {
            $url = (new FileUpload())->upload($_FILES['logo'], 'units', true);
        } catch (\RuntimeException $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()]);
        }
        EventUnit::updateRow($unitId, ['logo' => $url]);
        $this->json(['success' => true, 'message' => 'Unit logo updated.', 'logo_url' => $url]);
    }

    // ── Internal helpers ─────────────────────────────────────────────────────

    private function statsForUnit(int $unitId): array
    {
        $r = Event::rowsRaw(
            "SELECT
                COUNT(*) AS total,
                COUNT(CASE WHEN admin_review_status = 'approved' THEN 1 END) AS approved
               FROM event_registrations
              WHERE event_id = ? AND unit_id = ?",
            [(int)$this->event['id'], $unitId]
        );
        return [
            'total'    => (int)($r[0]['total']    ?? 0),
            'approved' => (int)($r[0]['approved'] ?? 0),
        ];
    }

    private function registrationsForUnit(int $unitId): array
    {
        return Event::rowsRaw(
            "SELECT er.id, er.admin_review_status, er.payment_status,
                    er.submitted_at, er.registered_at, er.competitor_number,
                    er.card_issued_at, er.total_amount,
                    a.name AS athlete_name, a.mobile AS athlete_mobile,
                    a.gender, a.date_of_birth, a.passport_photo,
                    (SELECT COUNT(*) FROM event_registration_items
                       WHERE registration_id = er.id) AS items_count
               FROM event_registrations er
               JOIN athletes a ON a.id = er.athlete_id
              WHERE er.event_id = ? AND er.unit_id = ?
              ORDER BY a.name",
            [(int)$this->event['id'], $unitId]
        );
    }
}
