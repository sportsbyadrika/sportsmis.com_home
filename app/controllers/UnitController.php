<?php
namespace Controllers;

use Core\{Controller, Auth, FileUpload};
use Models\{UnitUser, Event, EventUnit, EventRegistration, EventRegistrationPayment, EventDocument, Athlete, Schema, Noc};

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
            'event_sports' => Event::getSports((int)$this->event['id']),
            'can_edit'     => !empty($this->event['allow_unit_registration'])
                              && EventRegistration::isEditable($reg),
            'flash'        => $this->flash(),
        ]);
    }

    /**
     * POST /unit/athletes/{id}/items — Unit user picks the sport-events
     * this athlete will compete in. Replaces the registration's items in
     * one shot via syncItems(), then refreshes the total amount.
     */
    public function saveAthleteItems(string $regId): void
    {
        $this->boot();
        $this->verifyCsrf();
        $reg = $this->loadEditableRegistration($regId);

        $picked = $_POST['event_sport_ids'] ?? [];
        if (!is_array($picked)) $picked = [];
        $picked = array_values(array_unique(array_map('intval', $picked)));

        $total = EventRegistration::syncItems((int)$reg['id'], $picked);
        EventRegistration::updateHeader((int)$reg['id'], ['total_amount' => $total]);

        $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
            $picked ? 'Sport events saved.' : 'All sport events removed.');
    }

    /**
     * POST /unit/athletes/{id}/submit — flip the draft / returned
     * registration to admin-review state. After submit the Unit User
     * can no longer edit; the event admin owns it.
     */
    public function submitAthleteRegistration(string $regId): void
    {
        $this->boot();
        $this->verifyCsrf();
        $reg = $this->loadEditableRegistration($regId);

        if (!EventRegistration::items((int)$reg['id'])) {
            $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
                'Pick at least one sport event before submitting.', 'warning');
        }

        EventRegistration::updateHeader((int)$reg['id'], [
            'status'              => 'pending',
            'admin_review_status' => 'pending',
            'submitted_at'        => date('Y-m-d H:i:s'),
        ]);
        $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
            'Registration submitted to the event administrator for review.');
    }

    /**
     * Shared guard for the two write actions above: the registration
     * must belong to one of the Unit User's assigned units, the event
     * must currently allow Unit-driven registration, and the lock
     * state machine must still permit edits.
     */
    private function loadEditableRegistration(string $regId): array
    {
        $rid = \hid_reg_decode($regId);
        $reg = EventRegistration::withProfile((int)$rid);
        if (!$reg || (int)$reg['event_id'] !== (int)$this->event['id']) $this->abort(404);
        $allowed = UnitUser::assignmentIds((int)$this->unitUser['id']);
        if (empty($reg['unit_id']) || !in_array((int)$reg['unit_id'], $allowed, true)) {
            $this->abort(403);
        }
        if (empty($this->event['allow_unit_registration'])) {
            $this->redirect('/unit/dashboard',
                'Unit-driven registration is not enabled for this event.', 'error');
        }
        if (!EventRegistration::isEditable($reg)) {
            $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
                'This registration is locked. Contact the event administrator for changes.', 'warning');
        }
        return $reg;
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
     * GET /unit/athletes/new — form used by the Unit User to create a
     * brand-new managed athlete and start a registration draft for them.
     * Only available when the event admin has flipped on
     * events.allow_unit_registration.
     */
    public function addAthleteForm(): void
    {
        $this->boot();
        try { Schema::ensureUnitRegistration(); } catch (\Throwable $e) {}
        if (empty($this->event['allow_unit_registration'])) {
            $this->redirect('/unit/dashboard',
                'Unit-driven registration is not enabled for this event.', 'warning');
        }
        $units = UnitUser::assignmentsFor((int)$this->unitUser['id']);
        if (!$units) {
            $this->redirect('/unit/dashboard',
                'No Unit / Club is assigned to your account yet.', 'warning');
        }
        $this->renderWith('unit', 'unit/athletes-new', [
            'unit_user' => $this->unitUser,
            'event'     => $this->event,
            'units'     => $units,
            'active_unit_id' => (int)($_SESSION['unit_active_unit_id'] ?? ($units[0]['id'] ?? 0)),
            'flash'     => $this->flash(),
            'old'       => $_SESSION['old']    ?? [],
            'errors'    => $_SESSION['errors'] ?? [],
        ]);
        unset($_SESSION['old'], $_SESSION['errors']);
    }

    /**
     * POST /unit/athletes — create the managed athlete, dedupe by Aadhaar
     * / mobile / email, create a draft event_registration tied to the
     * picked unit, and redirect into the existing read-only view so the
     * Unit User can confirm and edit further from the dashboard.
     */
    public function storeAthlete(): void
    {
        $this->boot();
        $this->verifyCsrf();
        try { Schema::ensureUnitRegistration(); } catch (\Throwable $e) {}
        if (empty($this->event['allow_unit_registration'])) {
            $this->redirect('/unit/dashboard',
                'Unit-driven registration is not enabled for this event.', 'error');
        }

        $assigned = UnitUser::assignmentIds((int)$this->unitUser['id']);
        $unitId   = (int)($_POST['unit_id'] ?? 0);
        if (!in_array($unitId, $assigned, true)) {
            $this->redirect('/unit/athletes/new',
                'Pick one of the Units assigned to your account.', 'error');
        }

        $name    = trim((string)($_POST['name']          ?? ''));
        $gender  = strtolower(trim((string)($_POST['gender'] ?? '')));
        $dob     = trim((string)($_POST['date_of_birth'] ?? ''));
        $mobile  = trim((string)($_POST['mobile']        ?? ''));
        $email   = strtolower(trim((string)($_POST['email'] ?? '')));
        $aadhaar = preg_replace('/\s+/', '', (string)($_POST['id_proof_number'] ?? ''));
        $address = trim((string)($_POST['address']       ?? ''));

        $errors = [];
        if ($name === '')                                  $errors['name']         = 'Full name is required.';
        if (!in_array($gender, ['male', 'female', 'other'], true)) $errors['gender'] = 'Pick the athlete\'s gender.';
        if ($dob === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) $errors['date_of_birth'] = 'Enter a valid date of birth.';
        if ($mobile !== '' && !preg_match('/^[6-9]\d{9}$/', $mobile)) $errors['mobile'] = 'Enter a valid 10-digit mobile number.';
        if ($email   !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email or leave blank.';
        if ($aadhaar !== '' && !preg_match('/^\d{12}$/', $aadhaar)) $errors['id_proof_number'] = 'Aadhaar must be 12 digits or leave blank.';

        if ($errors) {
            $_SESSION['old']    = $_POST;
            $_SESSION['errors'] = $errors;
            $this->redirect('/unit/athletes/new', 'Fix the highlighted fields.', 'error');
        }

        // Dedupe — Aadhaar first, then mobile, then email. If a match
        // exists, we don't silently link; surface it to the unit user
        // so the event admin can decide.
        $existing = Athlete::findExistingForUnitDedupe(
            $aadhaar !== '' ? $aadhaar : null,
            $mobile  !== '' ? $mobile  : null,
            $email   !== '' ? $email   : null,
        );
        if ($existing) {
            $_SESSION['old'] = $_POST;
            $msg = 'An athlete with this '
                 . ($aadhaar !== '' ? 'Aadhaar' : ($mobile !== '' ? 'mobile' : 'email'))
                 . ' already exists in the system (' . htmlspecialchars((string)$existing['name'], ENT_QUOTES) . ').'
                 . ' Please contact the event administrator to link them to your Unit.';
            $this->redirect('/unit/athletes/new', $msg, 'warning');
        }

        // Optional photo / Aadhaar file uploads — mirror athlete-side
        // profile handling so the resulting record is consistent.
        $passportPhoto = null;
        $idProofFile   = null;
        if (!empty($_FILES['passport_photo']['name'])) {
            try { $passportPhoto = (new FileUpload())->upload($_FILES['passport_photo'], 'athletes/photos', true); }
            catch (\RuntimeException $e) {
                $_SESSION['old'] = $_POST;
                $this->redirect('/unit/athletes/new', 'Photo upload failed: ' . $e->getMessage(), 'error');
            }
        }
        if (!empty($_FILES['id_proof_file']['name'])) {
            try { $idProofFile = (new FileUpload())->upload($_FILES['id_proof_file'], 'athletes/idproofs'); }
            catch (\RuntimeException $e) {
                $_SESSION['old'] = $_POST;
                $this->redirect('/unit/athletes/new', 'Aadhaar proof upload failed: ' . $e->getMessage(), 'error');
            }
        }

        // Stub user row only when an email was supplied. The password is
        // a random secret the athlete will reset via "Forgot password"
        // when they claim the account.
        $userId = null;
        if ($email !== '') {
            $existingUser = \Models\User::findByEmail($email);
            if ($existingUser) {
                $_SESSION['old'] = $_POST;
                $this->redirect('/unit/athletes/new',
                    'A user with that email already exists. Leave the email blank to create a managed athlete, or contact the event administrator.', 'error');
            }
            $userId = \Models\User::create($email, password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT), 'athlete');
        }

        $athleteId = Athlete::createManaged([
            'name'             => mb_substr($name, 0, 255),
            'gender'           => $gender,
            'date_of_birth'    => $dob,
            'mobile'           => $mobile ?: null,
            'address'          => $address ?: null,
            'id_proof_number'  => $aadhaar ?: null,
            'id_proof_file'    => $idProofFile,
            'passport_photo'   => $passportPhoto,
            'profile_completed' => 1,
        ], $userId, $unitId);

        // Create the draft event_registration pinned to this unit.
        $regId = EventRegistration::createDraft((int)$this->event['id'], $athleteId);
        EventRegistration::updateHeader($regId, ['unit_id' => $unitId]);

        $this->redirect('/unit/athletes/' . \hid_reg($regId),
            'Athlete created. Open the registration to add sport-events and submit.');
    }

    // ── NOC management ───────────────────────────────────────────────────────

    /** Resolve the active unit from ?unit_id / session / first assigned. */
    private function pickActiveUnit(array $units): ?array
    {
        if (!$units) return null;
        $requested = (int)($_GET['unit_id'] ?? ($_SESSION['unit_active_unit_id'] ?? 0));
        foreach ($units as $u) {
            if ((int)$u['id'] === $requested) return $u;
        }
        return $units[0];
    }

    /** GET /unit/noc — NOC management screen. */
    public function nocIndex(): void
    {
        $this->boot();
        $units  = UnitUser::assignmentsFor((int)$this->unitUser['id']);
        $active = $this->pickActiveUnit($units);
        if ($active) $_SESSION['unit_active_unit_id'] = (int)$active['id'];
        $athletes = $active
            ? Noc::athletesForUnit((int)$this->event['id'], (int)$active['id'])
            : [];
        $this->renderWith('unit', 'unit/noc', [
            'unit_user'   => $this->unitUser,
            'event'       => $this->event,
            'units'       => $units,
            'active_unit' => $active,
            'athletes'    => $athletes,
            'flash'       => $this->flash(),
        ]);
    }

    /** POST /unit/noc/set — AJAX update of one athlete's NOC status. */
    public function nocSet(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $regId  = (int)($_POST['registration_id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if (!in_array($status, Noc::STATUSES, true)) {
            $this->json(['success' => false, 'message' => 'Invalid NOC status.']);
        }
        $reg = EventRegistration::findById($regId);
        $allowed = UnitUser::assignmentIds((int)$this->unitUser['id']);
        if (!$reg
            || (int)$reg['event_id'] !== (int)$this->event['id']
            || !in_array((int)($reg['unit_id'] ?? 0), $allowed, true)
            || ($reg['admin_review_status'] ?? '') !== 'approved') {
            $this->json(['success' => false, 'message' => 'Athlete not found in your unit.']);
        }
        Noc::setStatus($regId, $status, (string)$this->unitUser['name']);
        $this->json([
            'success' => true,
            'message' => 'NOC status updated.',
            'status'  => $status,
            'at'      => date('d M Y H:i'),
            'by'      => $this->unitUser['name'],
        ]);
    }

    /** GET /unit/noc/print — print-ready NOC report (honours filters). */
    public function nocPrint(): void
    {
        $this->boot();
        $units  = UnitUser::assignmentsFor((int)$this->unitUser['id']);
        $active = $this->pickActiveUnit($units);
        $athletes = $active
            ? Noc::athletesForUnit((int)$this->event['id'], (int)$active['id'])
            : [];
        $fStatus = (string)($_GET['status'] ?? '');
        $fName   = trim((string)($_GET['name'] ?? ''));
        if (in_array($fStatus, Noc::STATUSES, true)) {
            $athletes = array_filter($athletes, fn($a) => $a['noc_status'] === $fStatus);
        }
        if ($fName !== '') {
            $athletes = array_filter($athletes, fn($a) => stripos((string)$a['athlete_name'], $fName) !== false);
        }
        $this->renderWith('print', 'unit/noc-print', [
            'event'         => $this->event,
            'active_unit'   => $active,
            'athletes'      => array_values($athletes),
            'filter_status' => $fStatus,
            'filter_name'   => $fName,
        ]);
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
