<?php
namespace Controllers;

use Core\{Controller, Auth, FileUpload, Mailer};
use Models\{Institution, Staff, Event, Athlete, EventRegistration, EventRegistrationPayment, User};

class InstitutionController extends Controller
{
    private array $institution;

    private function boot(): void
    {
        $this->requireAuth('institution_admin');
        $inst = Institution::findByUserId(Auth::id());
        if (!$inst) $this->redirect('/login', 'Institution not found.', 'error');
        $this->institution = $inst;
    }

    public function dashboard(): void
    {
        $this->boot();
        $events = Event::getByInstitution($this->institution['id']);
        $this->renderWith('app', 'dashboard/institution', [
            'institution' => $this->institution,
            'events'      => $events,
            'flash'       => $this->flash(),
        ]);
    }

    public function profileForm(): void
    {
        $this->boot();
        $this->renderWith('app', 'institution/profile', [
            'institution'       => $this->institution,
            'institution_types' => Institution::getTypes(),
            'flash'             => $this->flash(),
            'errors'            => $this->errors(),
        ]);
    }

    public function updateProfile(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $errors = $this->validate([
            'name'       => 'required|max:255',
            'reg_number' => 'required|max:100',
            'address'    => 'required',
        ]);

        $data = [
            'name'       => trim($_POST['name']),
            'type_id'    => (int)($_POST['type_id'] ?? 0) ?: null,
            'reg_number' => trim($_POST['reg_number']),
            'address'    => trim($_POST['address']),
        ];

        if (!empty($_FILES['logo']['name'])) {
            try {
                $uploader = new FileUpload();
                $data['logo'] = $uploader->upload($_FILES['logo'], 'institutions', true);
            } catch (\RuntimeException $e) {
                $errors['logo'][] = $e->getMessage();
            }
        }

        if (!empty($_FILES['reg_document']['name'])) {
            try {
                $uploader = new FileUpload();
                $data['reg_document'] = $uploader->upload($_FILES['reg_document'], 'institutions');
            } catch (\RuntimeException $e) {
                $errors['reg_document'][] = $e->getMessage();
            }
        }

        if ($errors) {
            $_SESSION['errors'] = $errors;
            $this->redirect('/institution/profile');
        }

        $data['profile_completed'] = 1;
        Institution::updateProfile($this->institution['id'], $data);
        $this->redirect('/institution/dashboard', 'Profile updated successfully!');
    }

    // ── AJAX Profile Section Save ────────────────────────────────────────────

    public function ajaxSave(): void
    {
        $this->boot();
        $this->verifyCsrf();

        // Self-heal: bring older databases up to the current column set.
        try { Institution::ensureSchema(); }
        catch (\Throwable $e) {
            error_log('[institution/ensureSchema] ' . $e->getMessage());
            $this->json(['success' => false,
                'message' => 'Database schema needs an update — please run database/migration_2026_05_01_institution_spoc.sql.']);
        }

        $section = $_POST['section'] ?? '';
        try {
            match ($section) {
                'logo'     => $this->saveLogoSection(),
                'details'  => $this->saveDetailsSection(),
                'contact'  => $this->saveContactSection(),
                'spoc'     => $this->saveSpocSection(),
                'document' => $this->saveDocumentSection(),
                default    => $this->json(['success' => false, 'message' => 'Unknown section.']),
            };
        } catch (\Throwable $e) {
            error_log('[institution/save:' . $section . '] ' . get_class($e) . ': ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->json(['success' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
        }
    }

    private function saveLogoSection(): void
    {
        if (empty($_FILES['logo']) || empty($_FILES['logo']['name'])) {
            error_log('[institution/logo] No file in $_FILES. POST keys: ' . implode(',', array_keys($_POST))
                . '; FILES keys: ' . implode(',', array_keys($_FILES)));
            $this->json(['success' => false, 'message' => 'No logo received by the server.']);
        }
        try {
            $url = (new FileUpload())->upload($_FILES['logo'], 'institutions', true);
            Institution::updateProfile($this->institution['id'], ['logo' => $url]);
            $this->json(['success' => true, 'message' => 'Logo updated!', 'logo_url' => $url]);
        } catch (\RuntimeException $e) {
            error_log('[institution/logo] Upload failed: ' . $e->getMessage()
                . ' | tmp=' . ($_FILES['logo']['tmp_name'] ?? '-')
                . ' | size=' . ($_FILES['logo']['size'] ?? '-')
                . ' | err=' . ($_FILES['logo']['error'] ?? '-'));
            $this->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function saveDetailsSection(): void
    {
        $name      = trim($_POST['name'] ?? '');
        $regNumber = trim($_POST['reg_number'] ?? '');
        $address   = trim($_POST['address'] ?? '');

        if (!$name || !$regNumber || !$address) {
            $this->json(['success' => false, 'message' => 'Name, registration number, and address are required.']);
        }

        Institution::updateProfile($this->institution['id'], [
            'name'       => $name,
            'type_id'    => (int)($_POST['type_id'] ?? 0) ?: null,
            'reg_number' => $regNumber,
            'address'    => $address,
        ]);
        $this->json(['success' => true, 'message' => 'Institution details saved!']);
    }

    private function saveContactSection(): void
    {
        $email   = trim($_POST['email'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $aff     = trim($_POST['affiliated_to'] ?? '');

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'message' => 'Enter a valid institution email address.']);
        }
        if ($website !== '' && !preg_match('~^https?://~i', $website)) {
            $website = 'https://' . $website;
        }

        Institution::updateProfile($this->institution['id'], [
            'email'         => $email ?: null,
            'website'       => $website ?: null,
            'affiliated_to' => $aff ?: null,
        ]);
        $this->json(['success' => true, 'message' => 'Contact info saved!']);
    }

    private function saveSpocSection(): void
    {
        $spocName   = trim($_POST['spoc_name'] ?? '');
        $spocMobile = trim($_POST['spoc_mobile'] ?? '');
        $spocEmail  = trim($_POST['spoc_email'] ?? '');

        if (!$spocName || !$spocMobile) {
            $this->json(['success' => false, 'message' => 'SPOC name and contact number are required.']);
        }
        if (!preg_match('/^[6-9]\d{9}$/', $spocMobile)) {
            $this->json(['success' => false, 'message' => 'Enter a valid 10-digit SPOC contact number.']);
        }
        if ($spocEmail !== '' && !filter_var($spocEmail, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'message' => 'Enter a valid SPOC email address.']);
        }

        Institution::updateProfile($this->institution['id'], [
            'spoc_name'   => $spocName,
            'spoc_mobile' => $spocMobile,
            'spoc_email'  => $spocEmail ?: null,
        ]);
        $this->json(['success' => true, 'message' => 'SPOC details saved!']);
    }

    private function saveDocumentSection(): void
    {
        if (empty($_FILES['reg_document']) || empty($_FILES['reg_document']['name'])) {
            $this->json(['success' => false, 'message' => 'No document received by the server.']);
        }
        try {
            $url = (new FileUpload())->upload($_FILES['reg_document'], 'institutions');
            Institution::updateProfile($this->institution['id'], ['reg_document' => $url]);
            $this->json(['success' => true, 'message' => 'Registration document saved!', 'document_url' => $url]);
        } catch (\RuntimeException $e) {
            error_log('[institution/document] Upload failed: ' . $e->getMessage());
            $this->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function submitProfile(): void
    {
        $this->boot();
        $this->verifyCsrf();

        try { Institution::ensureSchema(); }
        catch (\Throwable $e) { error_log('[institution/ensureSchema:submit] ' . $e->getMessage()); }

        $i = Institution::findByUserId(Auth::id());
        $missing = [];
        $required = ['name', 'reg_number', 'address', 'email', 'spoc_name', 'spoc_mobile'];
        foreach ($required as $f) {
            if (empty($i[$f])) $missing[] = str_replace('_', ' ', $f);
        }
        if (empty($i['logo']))         $missing[] = 'logo';
        if (empty($i['reg_document'])) $missing[] = 'registration document';

        if ($missing) {
            $this->json(['success' => false,
                'message' => 'Please save all required sections first: ' . implode(', ', $missing) . '.']);
        }

        Institution::updateProfile($i['id'], ['profile_completed' => 1]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Profile submitted successfully!'];
        $this->json([
            'success'  => true,
            'message'  => 'Profile submitted successfully!',
            'redirect' => '/institution/dashboard',
        ]);
    }

    // ── Staff Management ─────────────────────────────────────────────────────

    public function staffIndex(): void
    {
        $this->boot();
        $this->renderWith('app', 'institution/staff/index', [
            'institution' => $this->institution,
            'staff_list'  => Staff::getByInstitution($this->institution['id']),
            'flash'       => $this->flash(),
        ]);
    }

    public function staffCreateForm(): void
    {
        $this->boot();
        $this->renderWith('app', 'institution/staff/create', [
            'institution' => $this->institution,
            'roles'       => Staff::getAllRoles(),
            'flash'       => $this->flash(),
            'errors'      => $this->errors(),
        ]);
    }

    public function staffCreate(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $errors = $this->validate([
            'name'   => 'required|max:255',
            'email'  => 'required|email',
            'mobile' => 'required|mobile',
        ]);

        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!$errors && \Models\User::findByEmail($email)) {
            $errors['email'][] = 'Email already registered.';
        }

        if ($errors) {
            $_SESSION['errors'] = $errors;
            $this->redirect('/institution/staff/create');
        }

        $password = \Core\Auth::generatePassword();
        $userId   = \Models\User::create($email, \Core\Auth::hashPassword($password), 'staff');

        $staffId = Staff::create([
            'institution_id' => $this->institution['id'],
            'user_id'        => $userId,
            'name'           => trim($_POST['name']),
            'mobile'         => trim($_POST['mobile']),
        ], array_map('intval', $_POST['roles'] ?? []));

        (new \Core\Mailer())->sendCredentials($email, trim($_POST['name']), $password);

        $this->redirect('/institution/staff', 'Staff member added and login credentials sent.');
    }

    public function staffEditForm(string $id): void
    {
        $this->boot();
        $staff = Staff::findById((int)$id);
        if (!$staff || $staff['institution_id'] != $this->institution['id']) $this->abort(404);

        $this->renderWith('app', 'institution/staff/edit', [
            'institution' => $this->institution,
            'staff'       => $staff,
            'roles'       => Staff::getAllRoles(),
            'assigned'    => Staff::getRoleIds((int)$id),
            'flash'       => $this->flash(),
            'errors'      => $this->errors(),
        ]);
    }

    public function staffUpdate(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $staff = Staff::findById((int)$id);
        if (!$staff || $staff['institution_id'] != $this->institution['id']) $this->abort(404);

        Staff::updateStaff((int)$id, [
            'name'   => trim($_POST['name']),
            'mobile' => trim($_POST['mobile']),
            'status' => $_POST['status'] ?? 'active',
        ], array_map('intval', $_POST['roles'] ?? []));

        $this->redirect('/institution/staff', 'Staff member updated.');
    }

    // ── Athlete Registrations Admin ──────────────────────────────────────────

    public function registrationsList(): void
    {
        $this->boot();
        $q       = trim($_GET['q'] ?? '');
        $status  = $_GET['status'] ?? '';
        $eventId = (int)($_GET['event_id'] ?? 0);

        $where  = ['e.institution_id = ?'];
        $params = [$this->institution['id']];

        if ($q !== '') {
            $where[] = '(a.name LIKE ? OR a.mobile LIKE ? OR e.name LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if (in_array($status, ['pending','approved','rejected','returned'], true)) {
            $where[] = 'er.admin_review_status = ?';
            $params[] = $status;
        } elseif ($status === 'unsubmitted') {
            $where[] = 'er.admin_review_status IS NULL';
        }
        if ($eventId) {
            $where[] = 'er.event_id = ?';
            $params[] = $eventId;
        }

        $sql = "SELECT er.id, er.event_id, er.registered_at, er.submitted_at,
                       er.admin_review_status, er.payment_status, er.total_amount,
                       a.id   AS athlete_id, a.name AS athlete_name, a.mobile,
                       e.name AS event_name,
                       eu.name AS unit_name,
                       (SELECT COUNT(*) FROM event_registration_items WHERE registration_id = er.id) AS items_count,
                       (SELECT COUNT(*) FROM event_registration_payments
                          WHERE registration_id = er.id AND status = 'pending') AS pending_payments
                  FROM event_registrations er
                  JOIN events e        ON e.id  = er.event_id
                  JOIN athletes a      ON a.id  = er.athlete_id
             LEFT JOIN event_units eu  ON eu.id = er.unit_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY er.submitted_at DESC, er.registered_at DESC";

        $rows = \Models\Event::rowsRaw($sql, $params);

        $this->renderWith('app', 'institution/registrations/index', [
            'institution'   => $this->institution,
            'registrations' => $rows,
            'events'        => Event::getByInstitution($this->institution['id']),
            'q'             => $q,
            'status'        => $status,
            'event_id'      => $eventId,
            'flash'         => $this->flash(),
        ]);
    }

    public function registrationDetail(string $id): void
    {
        $this->boot();
        $reg = EventRegistration::withProfile((int)$id);
        if (!$reg || (int)$reg['institution_id'] !== (int)$this->institution['id']) $this->abort(404);

        $athlete = Athlete::findById((int)$reg['athlete_id']);

        $this->renderWith('app', 'institution/registrations/detail', [
            'institution' => $this->institution,
            'registration'=> $reg,
            'athlete'     => $athlete,
            'items'       => EventRegistration::items((int)$id),
            'payments'    => EventRegistrationPayment::forRegistration((int)$id),
            'flash'       => $this->flash(),
        ]);
    }

    public function registrationDecision(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $reg = EventRegistration::withProfile((int)$id);
        if (!$reg || (int)$reg['institution_id'] !== (int)$this->institution['id']) $this->abort(404);

        $action = $_POST['action'] ?? '';
        $notes  = trim($_POST['notes'] ?? '');
        $map = ['approve' => 'approved', 'reject' => 'rejected', 'return' => 'returned'];
        if (!isset($map[$action])) {
            $this->redirect("/institution/registrations/{$id}", 'Invalid action.', 'error');
        }

        EventRegistration::updateHeader((int)$id, [
            'admin_review_status' => $map[$action],
            'admin_review_notes'  => $notes ?: null,
            'admin_reviewed_by'   => Auth::id(),
            'admin_reviewed_at'   => date('Y-m-d H:i:s'),
            'status'              => $action === 'approve' ? 'confirmed' : ($action === 'reject' ? 'cancelled' : 'pending'),
        ]);

        $extra = '';
        if ($action === 'approve') {
            $num = EventRegistration::allocateCompetitorNumber((int)$id);
            if ($num) {
                $extra = ' Competitor #' . $num . ' assigned.';
                if ($this->emailCompetitorCard((int)$id)) {
                    $extra .= ' Card emailed to the athlete.';
                } else {
                    $extra .= ' (Card email could not be sent — use the Resend button.)';
                }
            }
        }
        $this->redirect("/institution/registrations/{$id}", 'Registration ' . $map[$action] . '.' . $extra);
    }

    /** POST /institution/registrations/{id}/resend-card — resend the card email. */
    public function resendCompetitorCard(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $reg = EventRegistration::withProfile((int)$id);
        if (!$reg || (int)$reg['institution_id'] !== (int)$this->institution['id']) $this->abort(404);

        if (($reg['admin_review_status'] ?? '') !== 'approved' || empty($reg['competitor_number'])) {
            $this->redirect("/institution/registrations/{$id}",
                'Card can only be resent for approved registrations with a competitor number.', 'warning');
        }
        $sent = $this->emailCompetitorCard((int)$id);
        $this->redirect("/institution/registrations/{$id}",
            $sent ? 'Card email resent to the athlete.' : 'Could not send the card email — check the mail configuration.',
            $sent ? 'success' : 'error');
    }

    /** Build context + send the competitor-card email. Returns true on success. */
    private function emailCompetitorCard(int $registrationId): bool
    {
        $reg = EventRegistration::findById($registrationId);
        if (!$reg) return false;
        $event = Event::findById((int)$reg['event_id']);
        if (!$event) return false;
        $athlete = Athlete::findById((int)$reg['athlete_id']);
        if (!$athlete) return false;
        $institution = Institution::findById((int)$event['institution_id']);
        $items = EventRegistration::items($registrationId);

        // Athlete email lives on users.email.
        $user = User::findById((int)$athlete['user_id']);
        $email = $user['email'] ?? '';
        if (!$email) return false;

        try {
            return (new Mailer())->sendCompetitorCard($email, $athlete, $event, $institution, $reg, $items);
        } catch (\Throwable $e) {
            error_log('[institution/competitorCard] mail failed: ' . $e->getMessage());
            return false;
        }
    }

    public function paymentDecision(string $paymentId): void
    {
        $this->boot();
        $this->verifyCsrf();

        $payment = EventRegistrationPayment::find((int)$paymentId);
        if (!$payment) $this->abort(404);
        $reg = EventRegistration::withProfile((int)$payment['registration_id']);
        if (!$reg || (int)$reg['institution_id'] !== (int)$this->institution['id']) $this->abort(404);

        $action = $_POST['action'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        $map = ['approve' => 'approved', 'reject' => 'rejected'];
        if (!isset($map[$action])) {
            $this->redirect("/institution/registrations/{$reg['id']}", 'Invalid action.', 'error');
        }

        EventRegistrationPayment::updateRow((int)$paymentId, [
            'status'           => $map[$action],
            'rejection_reason' => $action === 'reject' ? ($reason ?: null) : null,
            'reviewed_by'      => Auth::id(),
            'reviewed_at'      => date('Y-m-d H:i:s'),
        ]);
        EventRegistrationPayment::recomputeRegistrationPaymentStatus((int)$reg['id']);

        $this->redirect("/institution/registrations/{$reg['id']}", 'Transaction ' . $map[$action] . '.');
    }
}
