<?php
namespace Controllers;

use Core\{Controller, Auth, FileUpload, Mailer};
use Models\{Institution, Staff, Event, Athlete, EventRegistration, EventRegistrationPayment, User, Grievance, TeamRegistration, TeamRegistrationPayment, Schema, EventUnit, UnitUser, EventStaff, UnitPayment};

class InstitutionController extends Controller
{
    private array $institution;

    private function boot(): void
    {
        $this->requireAuth('institution_admin');
        try { Schema::ensureInstitutionEventCreation(); } catch (\Throwable $e) {}
        $inst = Institution::findByUserId(Auth::id());
        if (!$inst) $this->redirect('/login', 'Institution not found.', 'error');
        $this->institution = $inst;
        try { Schema::ensureInstitutionAsUnit(); } catch (\Throwable $e) {}
    }

    public function dashboard(): void
    {
        $this->boot();
        $events = Event::getByInstitution($this->institution['id']);
        // Count approved participations on other institutions' events.
        // event_units.linked_institution_id is set when the join request
        // is approved — same selector the participating-events page uses.
        $partRow = Event::rowsRaw(
            "SELECT COUNT(*) AS c FROM event_units WHERE linked_institution_id = ?",
            [(int)$this->institution['id']]
        );
        $participatingCount = (int)($partRow[0]['c'] ?? 0);

        // Events open for institution participation (same source as the
        // public-events page) with this institution's request status +
        // the event's SPOC contact for the "Submitted" details popup.
        $instId = (int)$this->institution['id'];
        $participationEvents = [];
        try {
            $participationEvents = Event::rowsRaw(
                "SELECT e.*, i.name AS organiser_name,
                        epr.status AS request_status,
                        eu.id AS linked_unit_id
                   FROM events e
              LEFT JOIN institutions i ON i.id = e.institution_id
              LEFT JOIN event_participation_requests epr
                     ON epr.event_id = e.id AND epr.institution_id = ?
              LEFT JOIN event_units eu
                     ON eu.event_id = e.id AND eu.linked_institution_id = ?
                  WHERE e.allow_institution_join_request = 1
                    AND e.status = 'active'
                    AND e.institution_id <> ?
                  ORDER BY e.event_date_from DESC, e.id DESC",
                [$instId, $instId, $instId]
            );
        } catch (\Throwable $e) { /* feature tables absent */ }

        $this->renderWith('app', 'dashboard/institution', [
            'institution' => $this->institution,
            'events'      => $events,
            'participating_count'  => $participatingCount,
            'participation_events' => $participationEvents,
            'flash'       => $this->flash(),
        ]);
    }

    /**
     * GET /institution/public-events
     * Lists every active event whose admin has flipped on
     * allow_institution_join_request, annotated with this institution's
     * current request status (none / pending / approved / rejected) so
     * the UI can show the right call-to-action button per row.
     * Hides the institution's own events — they don't need to "join"
     * what they own.
     */
    public function publicEvents(): void
    {
        $this->boot();
        $instId = (int)$this->institution['id'];
        $rows = Event::rowsRaw(
            "SELECT e.id, e.name, e.event_code, e.location, e.logo,
                    e.event_date_from, e.event_date_to, e.status,
                    i.name AS organiser_name,
                    epr.id AS request_id, epr.status AS request_status,
                    epr.proposed_unit_name, epr.requested_at,
                    epr.reviewer_notes,
                    eu.id AS linked_unit_id, eu.name AS linked_unit_name
               FROM events e
          LEFT JOIN institutions i ON i.id = e.institution_id
          LEFT JOIN event_participation_requests epr
                 ON epr.event_id = e.id AND epr.institution_id = ?
          LEFT JOIN event_units eu
                 ON eu.event_id = e.id AND eu.linked_institution_id = ?
              WHERE e.allow_institution_join_request = 1
                AND e.status = 'active'
                AND e.institution_id <> ?
              ORDER BY e.event_date_from DESC, e.id DESC",
            [$instId, $instId, $instId]
        );
        $this->renderWith('app', 'institution/public-events', [
            'institution' => $this->institution,
            'rows'        => $rows,
            'flash'       => $this->flash(),
        ]);
    }

    /**
     * POST /institution/events/{eventHash}/request-participation
     * Create or refresh this institution's participation request for
     * an event. Re-submitting after a rejection moves it back to
     * pending so the admin can take a second look.
     */
    public function submitParticipationRequest(string $eventHash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $eid = (int)\hid_event_decode($eventHash);
        $event = Event::findById($eid);
        if (!$event || empty($event['allow_institution_join_request'])) {
            $this->redirect('/institution/public-events',
                'That event is not currently accepting institution join requests.', 'warning');
        }
        if ((int)$event['institution_id'] === (int)$this->institution['id']) {
            $this->redirect('/institution/public-events',
                'You own this event — no need to request participation.', 'warning');
        }

        $unitName    = trim((string)($_POST['proposed_unit_name'] ?? ''));
        $unitAddress = trim((string)($_POST['proposed_unit_address'] ?? ''));
        $notes       = trim((string)($_POST['request_notes'] ?? ''));
        if ($unitName === '') {
            $unitName = (string)($this->institution['name'] ?? 'Institution');
        }

        $instId = (int)$this->institution['id'];
        $existing = Event::rowsRaw(
            "SELECT id, status FROM event_participation_requests
              WHERE event_id = ? AND institution_id = ? LIMIT 1",
            [$eid, $instId]
        )[0] ?? null;

        if ($existing) {
            if ($existing['status'] === 'approved') {
                $this->redirect('/institution/public-events',
                    'You already have an approved participation on this event.', 'warning');
            }
            Event::rowsRaw(
                "UPDATE event_participation_requests
                    SET proposed_unit_name = ?, proposed_unit_address = ?,
                        request_notes = ?, status = 'pending',
                        reviewed_at = NULL, reviewed_by_user_id = NULL,
                        reviewer_notes = NULL
                  WHERE id = ?",
                [mb_substr($unitName, 0, 255), $unitAddress ?: null,
                 $notes ?: null, (int)$existing['id']]
            );
        } else {
            Event::rowsRaw(
                "INSERT INTO event_participation_requests
                    (event_id, institution_id, proposed_unit_name,
                     proposed_unit_address, request_notes, status)
                 VALUES (?, ?, ?, ?, ?, 'pending')",
                [$eid, $instId, mb_substr($unitName, 0, 255),
                 $unitAddress ?: null, $notes ?: null]
            );
        }

        $this->redirect('/institution/public-events',
            'Participation request sent. The organiser will review it shortly.');
    }

    /**
     * GET /institution/participating-events
     * Events where this institution has an approved event_unit. Each
     * row has an "Open Unit Console" button that hands the operator
     * into the existing /unit/* UI using their institution session.
     */
    public function participatingEvents(): void
    {
        $this->boot();
        // Allow the unit layout's "Switch back" link (which carries
        // ?leave_unit=1) to drop the proxy session before the list
        // re-renders.
        if (!empty($_GET['leave_unit'])) {
            unset($_SESSION['institution_as_unit'], $_SESSION['unit_active_unit_id']);
        }
        $instId = (int)$this->institution['id'];
        $rows = Event::rowsRaw(
            "SELECT e.id, e.name, e.event_code, e.location, e.logo,
                    e.event_date_from, e.event_date_to, e.status,
                    eu.id AS unit_id, eu.name AS unit_name, eu.address AS unit_address,
                    i.name AS organiser_name
               FROM event_units eu
               JOIN events       e ON e.id = eu.event_id
          LEFT JOIN institutions i ON i.id = e.institution_id
              WHERE eu.linked_institution_id = ?
              ORDER BY e.event_date_from DESC, e.id DESC",
            [$instId]
        );
        $this->renderWith('app', 'institution/participating-events', [
            'institution' => $this->institution,
            'rows'        => $rows,
            'flash'       => $this->flash(),
        ]);
    }

    /**
     * POST /institution/events/{eventHash}/open-as-unit
     * Sets the institution-as-unit session flag and bounces into the
     * existing /unit/dashboard. UnitController::boot() recognises the
     * flag and synthesises the unit-user shape from the institution.
     */
    public function openAsUnit(string $eventHash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $eid = (int)\hid_event_decode($eventHash);
        $instId = (int)$this->institution['id'];
        $eu = Event::rowsRaw(
            "SELECT id FROM event_units
              WHERE event_id = ? AND linked_institution_id = ?
              ORDER BY id LIMIT 1",
            [$eid, $instId]
        )[0] ?? null;
        if (!$eu) {
            $this->redirect('/institution/participating-events',
                'You do not have an approved unit on that event.', 'error');
        }
        $_SESSION['institution_as_unit'] = [
            'institution_id' => $instId,
            'event_id'       => $eid,
            'unit_id'        => (int)$eu['id'],
        ];
        $_SESSION['unit_active_unit_id'] = (int)$eu['id'];
        $this->redirect('/unit/dashboard');
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
        // Registration certificate is optional — institutions may not have
        // a formal registration document (e.g. clubs, schools, informal
        // units). They can still submit without one.

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

        (new \Core\Mailer())->sendStaffCredentials($email, trim($_POST['name']), $password);

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
        $eventId = (int)($_GET['event_id'] ?? 0);
        $instId  = (int)$this->institution['id'];

        // Aggregate application-status counts for the top cards (whole
        // institution, or a single event when one is picked).
        $cWhere = ['e.institution_id = ?']; $cParams = [$instId];
        if ($eventId) { $cWhere[] = 'er.event_id = ?'; $cParams[] = $eventId; }
        $cWhereSql = implode(' AND ', $cWhere);

        $sumApp = \Models\Event::rowsRaw(
            "SELECT COUNT(*) AS total,
                    COUNT(CASE WHEN er.admin_review_status='pending'  THEN 1 END) AS pending,
                    COUNT(CASE WHEN er.admin_review_status='approved' THEN 1 END) AS approved,
                    COUNT(CASE WHEN er.admin_review_status='rejected' THEN 1 END) AS rejected,
                    COUNT(CASE WHEN er.admin_review_status='returned' THEN 1 END) AS returned,
                    COUNT(CASE WHEN er.admin_review_status IS NULL    THEN 1 END) AS draft
               FROM event_registrations er
               JOIN events e ON e.id = er.event_id
              WHERE {$cWhereSql}",
            $cParams
        );
        $appCounts = $sumApp[0] ?? ['total'=>0,'pending'=>0,'approved'=>0,'rejected'=>0,'returned'=>0,'draft'=>0];

        $sumPay = \Models\Event::rowsRaw(
            "SELECT er.payment_mode, er.payment_status, COUNT(*) AS cnt,
                    COALESCE(SUM(er.total_amount), 0) AS amount
               FROM event_registrations er
               JOIN events e ON e.id = er.event_id
              WHERE {$cWhereSql}
              GROUP BY er.payment_mode, er.payment_status",
            $cParams
        );
        $payCounts = [
            'manual' => ['paid'=>0, 'pending'=>0, 'failed'=>0, 'amount_paid'=>0.0],
            'online' => ['paid'=>0, 'pending'=>0, 'failed'=>0, 'amount_paid'=>0.0],
            'unset'  => ['paid'=>0, 'pending'=>0, 'failed'=>0, 'amount_paid'=>0.0],
        ];
        foreach ($sumPay as $row) {
            $mode = in_array($row['payment_mode'], ['manual','online'], true) ? $row['payment_mode'] : 'unset';
            $st   = in_array($row['payment_status'], ['paid','pending','failed'], true) ? $row['payment_status'] : 'pending';
            $payCounts[$mode][$st] += (int)$row['cnt'];
            if ($st === 'paid') $payCounts[$mode]['amount_paid'] += (float)$row['amount'];
        }

        // Unit-wise summary rows (the main table).
        $unitRows = $this->unitRegistrationSummary($eventId, $q);

        $selectedEvent = null;
        if ($eventId) {
            foreach (Event::getByInstitution($instId) as $ev) {
                if ((int)$ev['id'] === $eventId) { $selectedEvent = $ev; break; }
            }
        }

        // Remember the active filter so the detail page's Back button can
        // return the admin to the same filtered view.
        $_SESSION['institution_reg_filter'] = http_build_query(array_filter([
            'q'        => $q,
            'event_id' => $eventId ?: null,
        ], fn($v) => $v !== null && $v !== ''));

        $this->renderWith('app', 'institution/registrations/index', [
            'institution'    => $this->institution,
            'unit_rows'      => $unitRows,
            'events'         => Event::getByInstitution($instId),
            'q'              => $q,
            'event_id'       => $eventId,
            'app_counts'     => $appCounts,
            'pay_counts'     => $payCounts,
            'selected_event' => $selectedEvent,
            'flash'          => $this->flash(),
        ]);
    }

    /**
     * Build the unit-wise summary rows for the registrations page. One row
     * per event_unit (scoped to the institution, optionally a single event
     * and a unit-name search), carrying the SPOC, per-status athlete counts,
     * total demand (individual + team) and total submitted transaction
     * amount (individual payments + bulk pool + team payments). A synthetic
     * "Direct / self-registered" row aggregates any registrations without a
     * unit so nothing is hidden on self-registration events.
     */
    private function unitRegistrationSummary(int $eventId, string $q): array
    {
        $instId = (int)$this->institution['id'];

        // Base unit list.
        $where = ['e.institution_id = ?']; $params = [$instId];
        if ($eventId)   { $where[] = 'eu.event_id = ?'; $params[] = $eventId; }
        if ($q !== '')  { $where[] = 'eu.name LIKE ?';  $params[] = '%' . $q . '%'; }
        $whereSql = implode(' AND ', $where);

        $units = \Models\Event::rowsRaw(
            "SELECT eu.id AS unit_id, eu.name AS unit_name, eu.event_id, e.name AS event_name
               FROM event_units eu JOIN events e ON e.id = eu.event_id
              WHERE {$whereSql}
              ORDER BY e.name, eu.name",
            $params
        );

        $unitIds = array_map(fn($u) => (int)$u['unit_id'], $units);

        $counts = []; $teamDemand = []; $txn = []; $spoc = [];
        if ($unitIds) {
            $ph = implode(',', array_fill(0, count($unitIds), '?'));
            $ids = array_map('intval', $unitIds);

            foreach (\Models\Event::rowsRaw(
                "SELECT unit_id,
                        COUNT(*) AS total,
                        SUM(admin_review_status IS NULL)        AS draft,
                        SUM(admin_review_status = 'pending')    AS submitted,
                        SUM(admin_review_status = 'approved')   AS approved,
                        SUM(admin_review_status = 'rejected')   AS rejected,
                        SUM(admin_review_status = 'returned')   AS returned,
                        COALESCE(SUM(CASE WHEN COALESCE(admin_review_status,'') <> 'rejected'
                                          THEN total_amount ELSE 0 END), 0) AS demand
                   FROM event_registrations
                  WHERE unit_id IN ($ph)
                  GROUP BY unit_id", $ids) as $r) {
                $counts[(int)$r['unit_id']] = $r;
            }

            try {
                foreach (\Models\Event::rowsRaw(
                    "SELECT unit_id, COALESCE(SUM(total_amount),0) AS d
                       FROM team_registrations
                      WHERE unit_id IN ($ph) AND COALESCE(admin_review_status,'') <> 'rejected'
                      GROUP BY unit_id", $ids) as $r) {
                    $teamDemand[(int)$r['unit_id']] = (float)$r['d'];
                }
            } catch (\Throwable $e) { /* team tables absent */ }

            $txn = array_fill_keys($unitIds, 0.0);
            foreach (\Models\Event::rowsRaw(
                "SELECT er.unit_id, COALESCE(SUM(p.amount),0) AS amt
                   FROM event_registration_payments p
                   JOIN event_registrations er ON er.id = p.registration_id
                  WHERE er.unit_id IN ($ph)
                    AND COALESCE(p.payment_method,'manual') <> 'demand' AND p.status <> 'rejected'
                  GROUP BY er.unit_id", $ids) as $r) {
                $txn[(int)$r['unit_id']] += (float)$r['amt'];
            }
            try {
                foreach (\Models\Event::rowsRaw(
                    "SELECT unit_id, COALESCE(SUM(amount),0) AS amt FROM event_unit_payments
                      WHERE unit_id IN ($ph) AND status IN ('submitted','approved')
                      GROUP BY unit_id", $ids) as $r) {
                    $txn[(int)$r['unit_id']] += (float)$r['amt'];
                }
            } catch (\Throwable $e) { /* unit payments absent */ }
            try {
                foreach (\Models\Event::rowsRaw(
                    "SELECT tr.unit_id, COALESCE(SUM(pp.amount),0) AS amt
                       FROM team_registration_payments pp
                       JOIN team_registrations tr ON tr.id = pp.team_registration_id
                      WHERE tr.unit_id IN ($ph) AND pp.status <> 'rejected'
                      GROUP BY tr.unit_id", $ids) as $r) {
                    $txn[(int)$r['unit_id']] += (float)$r['amt'];
                }
            } catch (\Throwable $e) { /* team payments absent */ }

            try {
                foreach (\Models\Event::rowsRaw(
                    "SELECT uuu.event_unit_id AS unit_id, uu.name, uu.email, uu.mobile
                       FROM unit_user_units uuu JOIN unit_users uu ON uu.id = uuu.unit_user_id
                      WHERE uuu.event_unit_id IN ($ph)
                      ORDER BY uu.id", $ids) as $r) {
                    $uid = (int)$r['unit_id'];
                    if (!isset($spoc[$uid])) $spoc[$uid] = $r;   // first assigned = SPOC
                }
            } catch (\Throwable $e) { /* no unit users */ }
        }

        $rows = [];
        foreach ($units as $u) {
            $uid = (int)$u['unit_id'];
            $c   = $counts[$uid] ?? [];
            $rows[] = [
                'unit_id'    => $uid,
                'unit_name'  => (string)$u['unit_name'],
                'event_id'   => (int)$u['event_id'],
                'event_name' => (string)$u['event_name'],
                'spoc'       => $spoc[$uid] ?? null,
                'total'      => (int)($c['total']     ?? 0),
                'draft'      => (int)($c['draft']     ?? 0),
                'submitted'  => (int)($c['submitted'] ?? 0),
                'approved'   => (int)($c['approved']  ?? 0),
                'rejected'   => (int)($c['rejected']  ?? 0),
                'returned'   => (int)($c['returned']  ?? 0),
                'demand'     => round((float)($c['demand'] ?? 0) + (float)($teamDemand[$uid] ?? 0), 2),
                'txn'        => round((float)($txn[$uid] ?? 0), 2),
            ];
        }

        // Direct / self-registered athletes (no unit) — one aggregate row so
        // self-registration events aren't blanked out. Only when a single
        // event is in focus (its View more needs a concrete event id) and no
        // unit-name search is active (it can't match "no unit").
        if ($q === '' && $eventId) {
            $nWhere = ['e.institution_id = ?', 'er.unit_id IS NULL', 'er.event_id = ?'];
            $nParams = [$instId, $eventId];
            $nWhereSql = implode(' AND ', $nWhere);
            $n = \Models\Event::rowsRaw(
                "SELECT COUNT(*) AS total,
                        SUM(er.admin_review_status IS NULL)      AS draft,
                        SUM(er.admin_review_status = 'pending')  AS submitted,
                        SUM(er.admin_review_status = 'approved') AS approved,
                        SUM(er.admin_review_status = 'rejected') AS rejected,
                        SUM(er.admin_review_status = 'returned') AS returned,
                        COALESCE(SUM(CASE WHEN COALESCE(er.admin_review_status,'') <> 'rejected'
                                          THEN er.total_amount ELSE 0 END),0) AS demand
                   FROM event_registrations er JOIN events e ON e.id = er.event_id
                  WHERE {$nWhereSql}", $nParams);
            $nc = $n[0] ?? [];
            if ((int)($nc['total'] ?? 0) > 0) {
                $nTxn = \Models\Event::rowsRaw(
                    "SELECT COALESCE(SUM(p.amount),0) AS amt
                       FROM event_registration_payments p
                       JOIN event_registrations er ON er.id = p.registration_id
                       JOIN events e ON e.id = er.event_id
                      WHERE {$nWhereSql}
                        AND COALESCE(p.payment_method,'manual') <> 'demand' AND p.status <> 'rejected'",
                    $nParams);
                $rows[] = [
                    'unit_id'    => 0,
                    'unit_name'  => '— Direct / self-registered —',
                    'event_id'   => $eventId,
                    'event_name' => '',
                    'spoc'       => null,
                    'total'      => (int)($nc['total']     ?? 0),
                    'draft'      => (int)($nc['draft']     ?? 0),
                    'submitted'  => (int)($nc['submitted'] ?? 0),
                    'approved'   => (int)($nc['approved']  ?? 0),
                    'rejected'   => (int)($nc['rejected']  ?? 0),
                    'returned'   => (int)($nc['returned']  ?? 0),
                    'demand'     => round((float)($nc['demand'] ?? 0), 2),
                    'txn'        => round((float)($nTxn[0]['amt'] ?? 0), 2),
                ];
            }
        }

        return $rows;
    }

    public function registrationDetail(string $id): void
    {
        $this->boot();
        $reg = EventRegistration::withProfile((int)$id);
        if (!$reg || (int)$reg['institution_id'] !== (int)$this->institution['id']) $this->abort(404);

        $athlete = Athlete::findById((int)$reg['athlete_id']);

        // Carry the saved list filter so the Back button restores it.
        $listQs = (string)($_SESSION['institution_reg_filter'] ?? '');

        // Carry the parent event so the view can render gender labels
        // through genderLabel() and honour the event's gender_label_set.
        $event = Event::findById((int)$reg['event_id']);
        $this->renderWith('app', 'institution/registrations/detail', [
            'institution' => $this->institution,
            'registration'=> $reg,
            'event'       => $event,
            'athlete'     => $athlete,
            'items'       => EventRegistration::items((int)$id),
            'payments'    => EventRegistrationPayment::forRegistration((int)$id),
            'sport_items' => \Models\RegistrationSportItem::forRegistration((int)$id),
            'list_qs'     => $listQs,
            'flash'       => $this->flash(),
        ]);
    }

    public function registrationDecision(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $reg = EventRegistration::withProfile((int)$id);
        if (!$reg || (int)$reg['institution_id'] !== (int)$this->institution['id']) $this->abort(404);

        // Optional return target — used by the "Athletes by Unit" grouped page
        // so a decision returns the admin there instead of the detail screen.
        // Only same-site institution paths are honoured.
        $back = (string)($_POST['back'] ?? '');
        $back = (preg_match('#^/institution/[^\s?]*(\?[^\s]*)?$#', $back)) ? $back : "/institution/registrations/{$id}";

        $action = $_POST['action'] ?? '';
        $notes  = trim($_POST['notes'] ?? '');
        $map = ['approve' => 'approved', 'reject' => 'rejected', 'return' => 'returned'];
        if (!isset($map[$action])) {
            $this->redirect($back, 'Invalid action.', 'error');
        }

        // A decision may only be taken on a registration that has been
        // submitted by the unit/athlete. Drafts (never submitted) are
        // view-only; approved / rejected are terminal. 'returned' stays
        // open so the admin can decide again.
        $rs = $reg['admin_review_status'] ?? null;
        if (!in_array($rs, ['pending', 'returned'], true)) {
            $msg = ($rs === null || $rs === '')
                ? 'This registration has not been submitted by the unit/athlete yet — you can review it once submitted.'
                : 'This registration is already ' . $rs . ' and cannot be changed here.';
            $this->redirect($back, $msg, 'warning');
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
            // Approval only updates the application status + notifies the
            // athlete. Competitor numbers and cards are issued later in bulk
            // from the Competitor Card report.
            if ($this->emailRegistrationApproved((int)$id)) {
                $extra = ' Approval email sent to the athlete.';
            } else {
                $extra = ' (Approval email could not be sent — check mail config.)';
            }
        }
        $this->redirect($back, 'Registration ' . $map[$action] . '.' . $extra);
    }

    /** Build context + send the registration-approved notification. */
    private function emailRegistrationApproved(int $registrationId): bool
    {
        $reg = EventRegistration::findById($registrationId);
        if (!$reg) return false;
        $event = Event::findById((int)$reg['event_id']);
        if (!$event) return false;
        $athlete = Athlete::findById((int)$reg['athlete_id']);
        if (!$athlete) return false;
        $user = User::findById((int)$athlete['user_id']);
        $email = $user['email'] ?? '';
        if (!$email) return false;

        try {
            return (new Mailer())->sendRegistrationApproved($email, $athlete, $event);
        } catch (\Throwable $e) {
            error_log('[institution/regApprovedMail] ' . $e->getMessage());
            return false;
        }
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
        // withProfile() so the joined unit_name + unit_address come along —
        // the Mailer's card template needs them for the Unit row.
        $reg = EventRegistration::withProfile($registrationId);
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

    /**
     * GET /institution/registrations/{id}/edit — edit screen for the event
     * admin. Mirrors the athlete's own register flow (unit, sport-events,
     * items / weapons, transactions) but with admin overrides.
     */
    public function registrationEditForm(string $id): void
    {
        $this->boot();
        $reg = EventRegistration::withProfile((int)$id);
        if (!$reg || (int)$reg['institution_id'] !== (int)$this->institution['id']) $this->abort(404);
        $event = Event::findById((int)$reg['event_id']);
        if (!$event) $this->abort(404);

        $this->renderWith('app', 'institution/registrations/edit', [
            'institution'   => $this->institution,
            'registration'  => $reg,
            'event'         => $event,
            'units'         => \Models\EventUnit::forEvent((int)$event['id']),
            'items'         => EventRegistration::items((int)$id),
            'event_sports'  => Event::getSports((int)$event['id']),
            'sport_items'   => \Models\RegistrationSportItem::forRegistration((int)$id),
            'event_items'   => \Models\EventSportItem::forEvent((int)$event['id']),
            'payments'      => EventRegistrationPayment::forRegistration((int)$id),
            'pay_totals'    => EventRegistrationPayment::totals((int)$id),
            'flash'         => $this->flash(),
        ]);
    }

    /**
     * POST /institution/registrations/{id}/athlete-profile — event admin
     * edits the athlete's basic profile (name, DOB, mobile, photo) directly
     * from the registration view, even when the profile is otherwise locked.
     */
    public function updateAthleteProfile(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $reg = EventRegistration::withProfile((int)$id);
        if (!$reg || (int)$reg['institution_id'] !== (int)$this->institution['id']) $this->abort(404);
        $athleteId = (int)$reg['athlete_id'];
        $back = "/institution/registrations/{$reg['id']}";

        $name      = trim((string)($_POST['name'] ?? ''));
        $dob       = trim((string)($_POST['date_of_birth'] ?? ''));
        $mobile    = trim((string)($_POST['mobile'] ?? ''));
        $aadhaar   = preg_replace('/\s+/', '', (string)($_POST['id_proof_number'] ?? ''));

        if ($name === '' || $dob === '' || $mobile === '') {
            $this->redirect($back, 'Name, date of birth and mobile are required.', 'error');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) || !strtotime($dob)) {
            $this->redirect($back, 'Enter a valid date of birth.', 'error');
        }
        if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
            $this->redirect($back, 'Enter a valid 10-digit mobile number.', 'error');
        }
        // Aadhaar is optional, but if supplied it must be exactly 12 digits.
        if ($aadhaar !== '' && !preg_match('/^\d{12}$/', $aadhaar)) {
            $this->redirect($back, 'Aadhaar number must be 12 digits.', 'error');
        }

        $data = [
            'name'            => mb_substr($name, 0, 255),
            'date_of_birth'   => $dob,
            'mobile'          => $mobile,
            'id_proof_number' => $aadhaar !== '' ? $aadhaar : null,
        ];
        if (!empty($_FILES['passport_photo']['name'])) {
            try {
                $data['passport_photo'] = (new FileUpload())->upload($_FILES['passport_photo'], 'athletes/photos', true);
            } catch (\RuntimeException $e) {
                $this->redirect($back, 'Photo upload failed: ' . $e->getMessage(), 'error');
            }
        }
        if (!empty($_FILES['id_proof_file']['name'])) {
            try {
                $data['id_proof_file'] = (new FileUpload())->upload($_FILES['id_proof_file'], 'athletes/idproofs');
            } catch (\RuntimeException $e) {
                $this->redirect($back, 'Aadhaar proof upload failed: ' . $e->getMessage(), 'error');
            }
        }
        Athlete::updateProfile($athleteId, $data);
        $this->redirect($back, 'Athlete profile updated.');
    }

    /**
     * POST /institution/registrations/{id}/edit/save — AJAX section save
     * for the event-admin edit page (header / items / sport items).
     */
    public function registrationEditSave(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $reg = EventRegistration::withProfile((int)$id);
        if (!$reg || (int)$reg['institution_id'] !== (int)$this->institution['id']) $this->abort(404);
        $regId   = (int)$reg['id'];
        $eventId = (int)$reg['event_id'];

        $section = $_POST['section'] ?? '';
        try {
            match ($section) {
                'header'           => $this->editRegistrationHeader($regId, $eventId, $reg),
                'sport_event_add'  => $this->editRegistrationAddSportEvent($regId, $eventId),
                'sport_event_remove'=> $this->editRegistrationRemoveSportEvent($regId, $eventId),
                'item_save'        => $this->editRegistrationItemSave($regId, $eventId),
                'item_delete'      => $this->editRegistrationItemDelete($regId),
                default            => $this->json(['success' => false, 'message' => 'Unknown section.']),
            };
        } catch (\Throwable $e) {
            error_log('[institution/registration/edit:' . $section . '] ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
        }
    }

    private function editRegistrationHeader(int $regId, int $eventId, array $reg): void
    {
        $rawUnit = (string)($_POST['unit_id'] ?? '');
        $isOther = ($rawUnit === 'OTHER');
        $unitId  = $isOther ? 0 : (int)$rawUnit;
        $unitNameOther = $isOther ? trim((string)($_POST['unit_name_other'] ?? '')) : '';
        $unitRegNo     = trim((string)($_POST['unit_reg_no'] ?? ''));

        if ($isOther) {
            if ($unitNameOther === '') {
                $this->json(['success' => false, 'message' => 'Enter the Unit / Club / Institution name.']);
            }
        } else {
            if (!$unitId) $this->json(['success' => false, 'message' => 'Pick a Unit / Club / Institution.']);
            $unit = \Models\EventUnit::find($unitId);
            if (!$unit || (int)$unit['event_id'] !== $eventId) {
                $this->json(['success' => false, 'message' => 'Invalid unit for this event.']);
            }
        }
        EventRegistration::updateHeader($regId, [
            'unit_id'         => $unitId ?: null,
            'unit_name_other' => $unitNameOther ?: null,
            'unit_reg_no'     => $unitRegNo ?: null,
        ]);
        $this->json(['success' => true, 'message' => 'Registration details saved.']);
    }

    private function editRegistrationAddSportEvent(int $regId, int $eventId): void
    {
        $eventSportId = (int)($_POST['event_sport_id'] ?? 0);
        if ($eventSportId <= 0) {
            $this->json(['success' => false, 'message' => 'Pick a sport event.']);
        }
        $allRows = Event::getSports($eventId);
        $byId    = [];
        foreach ($allRows as $r) $byId[(int)$r['id']] = $r;
        if (!isset($byId[$eventSportId])) {
            $this->json(['success' => false, 'message' => 'Sport event not part of this event.']);
        }
        // Re-sync to keep one row per event_sport_id: pull current ids,
        // add the new one if missing, then recompute the total.
        $current = array_map(fn($r) => (int)$r['event_sport_id'],
            EventRegistration::items($regId));
        if (!in_array($eventSportId, $current, true)) $current[] = $eventSportId;
        $total = EventRegistration::syncItems($regId, $current);
        EventRegistration::updateHeader($regId, ['total_amount' => $total]);
        $this->json([
            'success' => true, 'message' => 'Sport event added.',
            'items'   => EventRegistration::items($regId),
            'total'   => $total,
        ]);
    }

    private function editRegistrationRemoveSportEvent(int $regId, int $eventId): void
    {
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId <= 0) $this->json(['success' => false, 'message' => 'Invalid item.']);
        // Confirm the item belongs to this registration before deleting.
        $row = Event::rowsRaw(
            "SELECT id FROM event_registration_items WHERE id = ? AND registration_id = ?",
            [$itemId, $regId]
        );
        if (!$row) $this->json(['success' => false, 'message' => 'Item not on this registration.']);
        Event::rowsRaw("DELETE FROM event_registration_items WHERE id = ?", [$itemId]);
        // Recompute total from remaining items.
        $remaining = EventRegistration::items($regId);
        $total = 0.0;
        foreach ($remaining as $r) $total += (float)$r['fee'];
        EventRegistration::updateHeader($regId, ['total_amount' => $total]);
        $this->json([
            'success' => true, 'message' => 'Sport event removed.',
            'items'   => $remaining,
            'total'   => $total,
        ]);
    }

    private function editRegistrationItemSave(int $regId, int $eventId): void
    {
        $rowId       = (int)($_POST['id']            ?? 0);
        $sportItemId = (int)($_POST['sport_item_id'] ?? 0);
        $model       = trim($_POST['model']         ?? '');
        $serial      = trim($_POST['serial_number'] ?? '');
        if (!$sportItemId) $this->json(['success' => false, 'message' => 'Pick an item.']);

        $allowed = \Models\EventSportItem::forEvent($eventId);
        $ok = false;
        foreach ($allowed as $a) if ((int)$a['sport_item_id'] === $sportItemId) { $ok = true; break; }
        if (!$ok) $this->json(['success' => false, 'message' => 'That item is not allowed for this event.']);

        $payload = [
            'registration_id' => $regId,
            'sport_item_id'   => $sportItemId,
            'model'           => $model ?: null,
            'serial_number'   => $serial ?: null,
        ];
        if ($rowId) {
            $existing = \Models\RegistrationSportItem::find($rowId);
            if (!$existing || (int)$existing['registration_id'] !== $regId) {
                $this->json(['success' => false, 'message' => 'Row not found.']);
            }
            \Models\RegistrationSportItem::updateRow($rowId, $payload);
        } else {
            \Models\RegistrationSportItem::create($payload);
        }
        $this->json([
            'success' => true, 'message' => 'Item saved.',
            'list'    => \Models\RegistrationSportItem::forRegistration($regId),
        ]);
    }

    private function editRegistrationItemDelete(int $regId): void
    {
        $rowId = (int)($_POST['id'] ?? 0);
        $existing = \Models\RegistrationSportItem::find($rowId);
        if (!$existing || (int)$existing['registration_id'] !== $regId) {
            $this->json(['success' => false, 'message' => 'Row not found.']);
        }
        \Models\RegistrationSportItem::deleteRow($rowId);
        $this->json([
            'success' => true, 'message' => 'Item removed.',
            'list'    => \Models\RegistrationSportItem::forRegistration($regId),
        ]);
    }

    /**
     * POST /institution/registrations/payments/{id}/status — admin override
     * to flip a transaction between pending / approved / rejected. Unlike
     * paymentDecision (one-way, only pending→approved/rejected), this can
     * also reset an already-approved or rejected row.
     */
    public function paymentStatusUpdate(string $paymentId): void
    {
        $this->boot();
        $this->verifyCsrf();
        $payment = EventRegistrationPayment::find((int)$paymentId);
        if (!$payment) $this->abort(404);
        $reg = EventRegistration::withProfile((int)$payment['registration_id']);
        if (!$reg || (int)$reg['institution_id'] !== (int)$this->institution['id']) $this->abort(404);

        $status = $_POST['status'] ?? '';
        $reason = trim((string)($_POST['reason'] ?? ''));
        if (!in_array($status, ['pending','approved','rejected'], true)) {
            $this->redirect("/institution/registrations/{$reg['id']}/edit", 'Invalid status.', 'error');
        }
        EventRegistrationPayment::updateRow((int)$paymentId, [
            'status'           => $status,
            'rejection_reason' => $status === 'rejected' ? ($reason ?: 'Rejected by event admin') : null,
            'reviewed_by'      => Auth::id(),
            'reviewed_at'      => $status === 'pending' ? null : date('Y-m-d H:i:s'),
        ]);
        EventRegistrationPayment::recomputeRegistrationPaymentStatus((int)$reg['id']);
        $this->redirect("/institution/registrations/{$reg['id']}/edit",
            'Transaction status updated to ' . ucfirst($status) . '.');
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

        // Transactions can only be reviewed once the registration has been
        // submitted — draft registrations are view-only.
        if (($reg['admin_review_status'] ?? null) === null || ($reg['admin_review_status'] ?? '') === '') {
            $this->redirect("/institution/registrations/{$reg['id']}",
                'This registration has not been submitted yet — transactions can be reviewed after submission.', 'warning');
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

    /**
     * POST /institution/registrations/{id}/payments/add
     * Event-admin counterpart to AthleteController::registerAddPayment —
     * lets the institution log a manual payment row directly on an athlete's
     * registration (same fields, same proof upload, same shape) and
     * optionally approve/reject it in the same request.
     */
    public function addManualPayment(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();

        $reg = EventRegistration::withProfile((int)$id);
        if (!$reg || (int)$reg['institution_id'] !== (int)$this->institution['id']) $this->abort(404);

        $txDate = $_POST['transaction_date']   ?? '';
        $txNum  = trim($_POST['transaction_number'] ?? '');
        $amount = (float)($_POST['transaction_amount'] ?? 0);
        $decision = $_POST['decision'] ?? 'pending'; // pending | approve | reject
        $reason   = trim($_POST['rejection_reason'] ?? '');

        if (!$txDate || !$txNum || $amount <= 0) {
            $this->redirect("/institution/registrations/{$reg['id']}", 'Transaction date, number and amount are required.', 'error');
        }
        $proofUrl = null;
        if (!empty($_FILES['transaction_proof']['name'])) {
            try {
                $proofUrl = (new FileUpload())->upload($_FILES['transaction_proof'], 'registrations');
            } catch (\RuntimeException $e) {
                $this->redirect("/institution/registrations/{$reg['id']}",
                    'Proof upload failed: ' . $e->getMessage(), 'error');
            }
        }

        $status = 'pending';
        $extra  = [];
        if ($decision === 'approve') {
            $status = 'approved';
            $extra  = ['reviewed_by' => Auth::id(), 'reviewed_at' => date('Y-m-d H:i:s')];
        } elseif ($decision === 'reject') {
            $status = 'rejected';
            $extra  = [
                'rejection_reason' => $reason ?: 'Rejected by event admin',
                'reviewed_by'      => Auth::id(),
                'reviewed_at'      => date('Y-m-d H:i:s'),
            ];
        }

        $payId = EventRegistrationPayment::create(array_merge([
            'registration_id'    => (int)$reg['id'],
            'event_id'           => (int)$reg['event_id'],
            'transaction_date'   => $txDate,
            'transaction_number' => $txNum,
            'amount'             => $amount,
            'proof_file'         => $proofUrl,
            'status'             => $status,
            'payment_method'     => 'manual',
        ], $extra));

        // Mirror the athlete-side flow: header stays / flips to manual mode,
        // and the registration's payment_status reflects the new total.
        if (empty($reg['payment_mode'])) {
            EventRegistration::updateHeader((int)$reg['id'], ['payment_mode' => 'manual']);
        }
        EventRegistrationPayment::recomputeRegistrationPaymentStatus((int)$reg['id']);

        $msg = match ($status) {
            'approved' => 'Manual transaction added and approved.',
            'rejected' => 'Manual transaction added and rejected.',
            default    => 'Manual transaction added (pending review).',
        };
        $this->redirect("/institution/registrations/{$reg['id']}", $msg);
    }

    // ── Unit / Institution / Club Users (per-event) ──────────────────────────

    /** GET /institution/events/{id}/unit-users — management screen. */
    public function unitUsersList(string $eventHash): void
    {
        $this->boot();
        try { Schema::ensureUnitUsers(); } catch (\Throwable $e) {}
        $eventId = \hid_event_decode($eventHash);
        $event = Event::findById((int)$eventId);
        if (!$event || (int)$event['institution_id'] !== (int)$this->institution['id']) $this->abort(404);

        // Make sure the event has a short event code to display.
        $eventCode = \ensureEventCode((int)$eventId);
        $event['event_code'] = $eventCode;

        $this->renderWith('app', 'institution/events/unit-users', [
            'institution' => $this->institution,
            'event'       => $event,
            'eventHash'   => $eventHash,
            'units'       => EventUnit::forEvent((int)$eventId),
            'unit_users'  => UnitUser::forEvent((int)$eventId),
            'flash'       => $this->flash(),
        ]);
    }

    /** POST /institution/events/{id}/unit-users/save — AJAX create or update. */
    public function unitUserSave(string $eventHash): void
    {
        $this->boot();
        $this->verifyCsrf();
        try { Schema::ensureUnitUsers(); } catch (\Throwable $e) {}
        $eventId = \hid_event_decode($eventHash);
        $event = Event::findById((int)$eventId);
        if (!$event || (int)$event['institution_id'] !== (int)$this->institution['id']) $this->abort(404);

        $id     = (int)($_POST['id'] ?? 0);
        $name   = trim((string)($_POST['name'] ?? ''));
        $email  = strtolower(trim((string)($_POST['email'] ?? '')));
        $mobile = trim((string)($_POST['mobile'] ?? ''));
        $status = $_POST['status'] ?? 'active';
        if (!in_array($status, ['active','inactive'], true)) $status = 'active';
        $assignments = $_POST['unit_ids'] ?? [];
        if (!is_array($assignments)) $assignments = [];
        $assignments = array_map('intval', $assignments);

        if ($name === '') $this->json(['success' => false, 'message' => 'Name is required.']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'message' => 'Enter a valid email address.']);
        }
        if ($mobile !== '' && !preg_match('/^[6-9]\d{9}$/', $mobile)) {
            $this->json(['success' => false, 'message' => 'Mobile must be a 10-digit number starting with 6-9.']);
        }
        // Verify each assigned unit belongs to this event.
        $eventUnitIds = array_map(fn($u) => (int)$u['id'], EventUnit::forEvent((int)$eventId));
        foreach ($assignments as $aId) {
            if (!in_array($aId, $eventUnitIds, true)) {
                $this->json(['success' => false, 'message' => 'One of the picked units does not belong to this event.']);
            }
        }

        // Duplicate-email guard scoped to this event.
        $existing = UnitUser::findByEventEmail((int)$eventId, $email);
        if ($existing && (int)$existing['id'] !== $id) {
            $this->json(['success' => false,
                'message' => 'A unit user with this email is already registered for this event.']);
        }

        $tempPassword = null;
        if ($id) {
            $row = UnitUser::findById($id);
            if (!$row || (int)$row['event_id'] !== (int)$eventId) {
                $this->json(['success' => false, 'message' => 'Unit user not found for this event.']);
            }
            UnitUser::updateRow($id, [
                'name'   => $name,
                'email'  => $email,
                'mobile' => $mobile ?: null,
                'status' => $status,
            ]);
        } else {
            $tempPassword = Auth::generatePassword(10);
            $id = UnitUser::create([
                'event_id' => (int)$eventId,
                'name'     => $name,
                'email'    => $email,
                'mobile'   => $mobile ?: null,
                'password' => Auth::hashPassword($tempPassword),
                'status'   => $status,
            ]);
            // Best-effort send credentials so the unit user can log in.
            try {
                $code = \ensureEventCode((int)$eventId);
                (new \Core\Mailer())->sendUnitUserCredentials($email, $name, $code, $event['name'], $tempPassword);
            } catch (\Throwable $e) {
                error_log('[unitUserSave/mail] ' . $e->getMessage());
            }
        }

        UnitUser::setAssignments($id, $assignments);

        $this->json([
            'success'       => true,
            'message'       => $tempPassword
                ? 'Unit user created. Login credentials emailed (initial password also returned).'
                : 'Unit user updated.',
            'id'            => $id,
            'temp_password' => $tempPassword,
            'list'          => UnitUser::forEvent((int)$eventId),
        ]);
    }

    /** POST /institution/events/{id}/unit-users/delete */
    public function unitUserDelete(string $eventHash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $eventId = \hid_event_decode($eventHash);
        $event = Event::findById((int)$eventId);
        if (!$event || (int)$event['institution_id'] !== (int)$this->institution['id']) $this->abort(404);
        $id = (int)($_POST['id'] ?? 0);
        $row = UnitUser::findById($id);
        if (!$row || (int)$row['event_id'] !== (int)$eventId) {
            $this->json(['success' => false, 'message' => 'Unit user not found.']);
        }
        UnitUser::deleteRow($id);
        $this->json([
            'success' => true, 'message' => 'Unit user removed.',
            'list'    => UnitUser::forEvent((int)$eventId),
        ]);
    }

    /** POST /institution/events/{id}/unit-users/reset-password */
    public function unitUserResetPassword(string $eventHash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $eventId = \hid_event_decode($eventHash);
        $event = Event::findById((int)$eventId);
        if (!$event || (int)$event['institution_id'] !== (int)$this->institution['id']) $this->abort(404);
        $id = (int)($_POST['id'] ?? 0);
        $row = UnitUser::findById($id);
        if (!$row || (int)$row['event_id'] !== (int)$eventId) {
            $this->json(['success' => false, 'message' => 'Unit user not found.']);
        }
        $pwd = Auth::generatePassword(10);
        UnitUser::updatePassword($id, Auth::hashPassword($pwd));
        try {
            $code = \ensureEventCode((int)$eventId);
            (new \Core\Mailer())->sendUnitUserCredentials($row['email'], $row['name'], $code, $event['name'], $pwd);
        } catch (\Throwable $e) {
            error_log('[unitUserResetPassword/mail] ' . $e->getMessage());
        }
        $this->json([
            'success'       => true,
            'message'       => 'Password reset. New credentials emailed.',
            'temp_password' => $pwd,
        ]);
    }

    // ── Event Staff Users (per-event) ────────────────────────────────────────

    /** GET /institution/events/{id}/staff-users — management screen. */
    public function staffUsersList(string $eventHash): void
    {
        $this->boot();
        try { Schema::ensureEventStaff(); } catch (\Throwable $e) {}
        $eventId = \hid_event_decode($eventHash);
        $event = Event::findById((int)$eventId);
        if (!$event || (int)$event['institution_id'] !== (int)$this->institution['id']) $this->abort(404);

        $event['event_code'] = \ensureEventCode((int)$eventId);

        $this->renderWith('app', 'institution/events/staff-users', [
            'institution' => $this->institution,
            'event'       => $event,
            'eventHash'   => $eventHash,
            'staff'       => EventStaff::forEvent((int)$eventId),
            'privileges'  => EventStaff::PRIVILEGES,
            'flash'       => $this->flash(),
        ]);
    }

    /** POST /institution/events/{id}/staff-users/save — AJAX create or update. */
    public function staffUserSave(string $eventHash): void
    {
        $this->boot();
        $this->verifyCsrf();
        try { Schema::ensureEventStaff(); } catch (\Throwable $e) {}
        $eventId = \hid_event_decode($eventHash);
        $event = Event::findById((int)$eventId);
        if (!$event || (int)$event['institution_id'] !== (int)$this->institution['id']) $this->abort(404);

        $id     = (int)($_POST['id'] ?? 0);
        $name   = trim((string)($_POST['name'] ?? ''));
        $email  = strtolower(trim((string)($_POST['email'] ?? '')));
        $mobile = trim((string)($_POST['mobile'] ?? ''));
        $status = $_POST['status'] ?? 'active';
        if (!in_array($status, ['active','inactive'], true)) $status = 'active';
        $privileges = $_POST['privileges'] ?? [];
        if (!is_array($privileges)) $privileges = [];
        $privileges = array_values(array_filter($privileges, fn($p) => isset(EventStaff::PRIVILEGES[$p])));

        if ($name === '') $this->json(['success' => false, 'message' => 'Name is required.']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'message' => 'Enter a valid email address.']);
        }
        if ($mobile !== '' && !preg_match('/^[6-9]\d{9}$/', $mobile)) {
            $this->json(['success' => false, 'message' => 'Mobile must be a 10-digit number starting with 6-9.']);
        }

        // Duplicate-email guard scoped to this event.
        $existing = EventStaff::findByEventEmail((int)$eventId, $email);
        if ($existing && (int)$existing['id'] !== $id) {
            $this->json(['success' => false,
                'message' => 'A staff user with this email is already registered for this event.']);
        }

        $tempPassword = null;
        if ($id) {
            $row = EventStaff::findById($id);
            if (!$row || (int)$row['event_id'] !== (int)$eventId) {
                $this->json(['success' => false, 'message' => 'Staff user not found for this event.']);
            }
            EventStaff::updateRow($id, [
                'name'   => $name,
                'email'  => $email,
                'mobile' => $mobile ?: null,
                'status' => $status,
            ]);
        } else {
            $tempPassword = Auth::generatePassword(10);
            $id = EventStaff::create([
                'event_id' => (int)$eventId,
                'name'     => $name,
                'email'    => $email,
                'mobile'   => $mobile ?: null,
                'password' => Auth::hashPassword($tempPassword),
                'status'   => $status,
            ]);
            try {
                $code = \ensureEventCode((int)$eventId);
                (new \Core\Mailer())->sendEventStaffCredentials($email, $name, $code, $event['name'], $tempPassword);
            } catch (\Throwable $e) {
                error_log('[staffUserSave/mail] ' . $e->getMessage());
            }
        }

        EventStaff::setPrivileges($id, $privileges);

        $this->json([
            'success'       => true,
            'message'       => $tempPassword
                ? 'Staff user created. Login credentials emailed (initial password also returned).'
                : 'Staff user updated.',
            'id'            => $id,
            'temp_password' => $tempPassword,
        ]);
    }

    /** POST /institution/events/{id}/staff-users/delete */
    public function staffUserDelete(string $eventHash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $eventId = \hid_event_decode($eventHash);
        $event = Event::findById((int)$eventId);
        if (!$event || (int)$event['institution_id'] !== (int)$this->institution['id']) $this->abort(404);
        $id = (int)($_POST['id'] ?? 0);
        $row = EventStaff::findById($id);
        if (!$row || (int)$row['event_id'] !== (int)$eventId) {
            $this->json(['success' => false, 'message' => 'Staff user not found.']);
        }
        EventStaff::deleteRow($id);
        $this->json(['success' => true, 'message' => 'Staff user removed.']);
    }

    /** POST /institution/events/{id}/staff-users/reset-password */
    public function staffUserResetPassword(string $eventHash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $eventId = \hid_event_decode($eventHash);
        $event = Event::findById((int)$eventId);
        if (!$event || (int)$event['institution_id'] !== (int)$this->institution['id']) $this->abort(404);
        $id = (int)($_POST['id'] ?? 0);
        $row = EventStaff::findById($id);
        if (!$row || (int)$row['event_id'] !== (int)$eventId) {
            $this->json(['success' => false, 'message' => 'Staff user not found.']);
        }
        $pwd = Auth::generatePassword(10);
        EventStaff::updatePassword($id, Auth::hashPassword($pwd));
        try {
            $code = \ensureEventCode((int)$eventId);
            (new \Core\Mailer())->sendEventStaffCredentials($row['email'], $row['name'], $code, $event['name'], $pwd);
        } catch (\Throwable $e) {
            error_log('[staffUserResetPassword/mail] ' . $e->getMessage());
        }
        $this->json([
            'success'       => true,
            'message'       => 'Password reset. New credentials emailed.',
            'temp_password' => $pwd,
        ]);
    }

    // ── Team Registrations (per-event approval pages) ────────────────────────

    /** GET /institution/events/{id}/team-registrations — list teams for one event. */
    public function teamRegistrationsList(string $eventHash): void
    {
        $this->boot();
        try { Schema::ensureTeamEntry(); } catch (\Throwable $e) {}
        $eventId = \hid_event_decode($eventHash);
        $event = Event::findById((int)$eventId);
        if (!$event || (int)$event['institution_id'] !== (int)$this->institution['id']) $this->abort(404);

        $eventSportFilter = (int)($_GET['event_sport_id'] ?? 0);
        $unitFilter       = (int)($_GET['unit_id']       ?? 0);
        $statusFilter     = (string)($_GET['status']     ?? '');

        $teams = TeamRegistration::forEvent((int)$eventId);
        $teams = array_values(array_filter($teams, function ($t) use ($eventSportFilter, $unitFilter, $statusFilter) {
            if ($eventSportFilter > 0 && (int)($t['event_sport_id'] ?? 0) !== $eventSportFilter) return false;
            if ($unitFilter       > 0 && (int)($t['unit_id']       ?? 0) !== $unitFilter)       return false;
            if ($statusFilter !== '') {
                $s = (string)($t['admin_review_status'] ?? '');
                if ($statusFilter === 'draft') {
                    if ($s !== '' || !empty($t['submitted_at'])) return false;
                } elseif ($s !== $statusFilter) {
                    return false;
                }
            }
            return true;
        }));

        // Members for each team — front-end displays Athlete 1/2/3 inline.
        $byTeam = [];
        foreach ($teams as $t) {
            $byTeam[(int)$t['id']] = TeamRegistration::members((int)$t['id']);
        }

        // Filter dropdown options — team-eligible sport events and event units.
        $sportEvents = Event::rowsRaw(
            "SELECT es.id, es.event_code, se.name AS sport_event_name, sp.name AS sport_name
               FROM event_sports es
          LEFT JOIN sport_events se ON se.id = es.sport_event_id
          LEFT JOIN sports        sp ON sp.id = es.sport_id
              WHERE es.event_id = ? AND es.team_entry_fee IS NOT NULL
              ORDER BY es.event_code, se.name",
            [(int)$eventId]
        );
        $units = Event::rowsRaw(
            "SELECT id, name FROM event_units WHERE event_id = ? ORDER BY name",
            [(int)$eventId]
        );

        $this->renderWith('app', 'institution/team-registrations/index', [
            'institution'       => $this->institution,
            'event'             => $event,
            'teams'             => $teams,
            'members_by_team'   => $byTeam,
            'sport_events'      => $sportEvents,
            'units'             => $units,
            'event_sport_filter'=> $eventSportFilter,
            'unit_filter'       => $unitFilter,
            'status_filter'     => $statusFilter,
            'flash'             => $this->flash(),
        ]);
    }

    /**
     * POST /institution/events/{id}/team-registrations/toggle-window —
     * open / close the team-entry submission window. Event Staff can
     * still submit when closed; unit users and athletes are blocked.
     */
    public function teamEntryToggleWindow(string $eventHash): void
    {
        $this->boot();
        $this->verifyCsrf();
        try { Schema::ensureTeamEntry(); } catch (\Throwable $e) {}
        $eventId = \hid_event_decode($eventHash);
        $event = Event::findById((int)$eventId);
        if (!$event || (int)$event['institution_id'] !== (int)$this->institution['id']) $this->abort(404);

        $open = !empty($_POST['open']) ? 1 : 0;
        Event::updatePartial((int)$eventId, ['team_entry_window_open' => $open]);
        $msg = $open
            ? 'Team entry submissions are now OPEN for unit users and athletes.'
            : 'Team entry submissions are now CLOSED for unit users and athletes. Event Staff can still submit.';
        $this->redirect("/institution/events/{$eventHash}/team-registrations", $msg);
    }

    /** GET /institution/team-registrations/{id} — detail / approval page. */
    public function teamRegistrationDetail(string $id): void
    {
        $this->boot();
        try { Schema::ensureTeamEntry(); } catch (\Throwable $e) {}
        $team = TeamRegistration::withContext((int)$id);
        if (!$team) $this->abort(404);
        $event = Event::findById((int)$team['event_id']);
        if (!$event || (int)$event['institution_id'] !== (int)$this->institution['id']) $this->abort(404);

        $this->renderWith('app', 'institution/team-registrations/detail', [
            'institution' => $this->institution,
            'event'       => $event,
            'team'        => $team,
            'members'     => TeamRegistration::members((int)$team['id']),
            'payments'    => TeamRegistrationPayment::forTeam((int)$team['id']),
            'pay_totals'  => TeamRegistrationPayment::totals((int)$team['id']),
            'flash'       => $this->flash(),
        ]);
    }

    /** POST /institution/team-registrations/{id}/decision — approve/reject/return. */
    public function teamRegistrationDecision(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $team = TeamRegistration::withContext((int)$id);
        if (!$team) $this->abort(404);
        $event = Event::findById((int)$team['event_id']);
        if (!$event || (int)$event['institution_id'] !== (int)$this->institution['id']) $this->abort(404);

        $action = $_POST['action'] ?? '';
        $notes  = trim($_POST['notes'] ?? '');
        $map = ['approve' => 'approved', 'reject' => 'rejected', 'return' => 'returned'];
        if (!isset($map[$action])) {
            $this->redirect("/institution/team-registrations/{$id}", 'Invalid action.', 'error');
        }
        TeamRegistration::updateRow((int)$id, [
            'admin_review_status' => $map[$action],
            'admin_review_notes'  => $notes ?: null,
            'admin_reviewed_by'   => Auth::id(),
            'admin_reviewed_at'   => date('Y-m-d H:i:s'),
            'status'              => $action === 'approve' ? 'confirmed' : ($action === 'reject' ? 'cancelled' : 'pending'),
        ]);
        $this->redirect("/institution/team-registrations/{$id}", 'Team registration ' . $map[$action] . '.');
    }

    /**
     * POST /institution/team-registrations/{id}/delete — permanently
     * delete a team entry (and its members + payment rows via FK
     * cascades). Available to the event-admin from the list/detail
     * pages at any review status, including approved or rejected.
     */
    public function teamRegistrationDelete(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        try { Schema::ensureTeamEntry(); } catch (\Throwable $e) {}
        $team = TeamRegistration::withContext((int)$id);
        if (!$team) $this->abort(404);
        $event = Event::findById((int)$team['event_id']);
        if (!$event || (int)$event['institution_id'] !== (int)$this->institution['id']) $this->abort(404);

        Event::rowsRaw("DELETE FROM team_registrations WHERE id = ?", [(int)$id]);
        $eventHash = \hid_event((int)$event['id']);
        $this->redirect(
            "/institution/events/{$eventHash}/team-registrations",
            'Team entry deleted.'
        );
    }

    /** POST /institution/team-registrations/payments/{id}/decision */
    public function teamPaymentDecision(string $paymentId): void
    {
        $this->boot();
        $this->verifyCsrf();
        $payment = TeamRegistrationPayment::find((int)$paymentId);
        if (!$payment) $this->abort(404);
        $team = TeamRegistration::withContext((int)$payment['team_registration_id']);
        if (!$team) $this->abort(404);
        $event = Event::findById((int)$team['event_id']);
        if (!$event || (int)$event['institution_id'] !== (int)$this->institution['id']) $this->abort(404);

        $action = $_POST['action'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        $map = ['approve' => 'approved', 'reject' => 'rejected'];
        if (!isset($map[$action])) {
            $this->redirect("/institution/team-registrations/{$team['id']}", 'Invalid action.', 'error');
        }
        TeamRegistrationPayment::updateRow((int)$paymentId, [
            'status'           => $map[$action],
            'rejection_reason' => $action === 'reject' ? ($reason ?: null) : null,
            'reviewed_by'      => Auth::id(),
            'reviewed_at'      => date('Y-m-d H:i:s'),
        ]);
        TeamRegistrationPayment::recomputeTeamPaymentStatus((int)$team['id']);
        $this->redirect("/institution/team-registrations/{$team['id']}", 'Transaction ' . $map[$action] . '.');
    }

    // ── Athletes by Unit (grouped review) ────────────────────────────────────

    /**
     * GET /institution/events/{id}/athletes-by-unit — grouped review screen.
     * Athletes are grouped under their unit; by default only SUBMITTED
     * registrations are shown (admin_review_status set) with a toggle to also
     * reveal drafts. Each unit group carries its fund-transfer summary (bulk
     * mode) so the admin can reconcile collection vs demand in the same place.
     * Approving an athlete whose payment isn't settled is allowed but flagged
     * with a soft warning (decision is independent of payment approval).
     */
    public function athletesByUnit(string $eventHash): void
    {
        $this->boot();
        try { Schema::ensureUnitPayments(); } catch (\Throwable $e) {}
        $eventId = \hid_event_decode($eventHash);
        $event   = Event::findById((int)$eventId);
        if (!$event || (int)$event['institution_id'] !== (int)$this->institution['id']) $this->abort(404);
        $eid  = (int)$event['id'];
        $bulk = (($event['unit_payment_mode'] ?? 'individual') === 'bulk');

        // Optional focus on a single unit (from the "View more" action on the
        // Registrations page). null = all units; 0 = direct / self-registered.
        $focusUnit = isset($_GET['unit_id']) && $_GET['unit_id'] !== ''
            ? (int)$_GET['unit_id'] : null;

        // Default view = submitted only; ?show=all also reveals drafts.
        $show      = ($_GET['show'] ?? 'submitted') === 'all' ? 'all' : 'submitted';
        $draftCond = $show === 'all' ? '' : "AND er.admin_review_status IS NOT NULL";

        // Registrations for this event, richest columns for the review row.
        $regs = Event::rowsRaw(
            "SELECT er.id, er.unit_id, er.admin_review_status, er.payment_status,
                    er.submitted_at, er.total_amount,
                    a.name AS athlete_name, a.gender, a.date_of_birth, a.mobile,
                    eu.name AS unit_name,
                    (SELECT COUNT(*) FROM event_registration_items eri
                       WHERE eri.registration_id = er.id) AS items_count,
                    (SELECT COALESCE(SUM(p.amount),0) FROM event_registration_payments p
                       WHERE p.registration_id = er.id
                         AND COALESCE(p.payment_method,'manual') <> 'demand'
                         AND p.status <> 'rejected') AS claimed_amount,
                    (SELECT COALESCE(SUM(p.amount),0) FROM event_registration_payments p
                       WHERE p.registration_id = er.id
                         AND COALESCE(p.payment_method,'manual') <> 'demand'
                         AND p.status = 'approved') AS approved_amount
               FROM event_registrations er
               JOIN athletes   a  ON a.id  = er.athlete_id
          LEFT JOIN event_units eu ON eu.id = er.unit_id
              WHERE er.event_id = ? {$draftCond}
              ORDER BY eu.name, a.name",
            [$eid]
        );

        // Per-unit demand (individual item fees + team totals) — mirrors the
        // unit-payments page so the two views reconcile identically.
        $demandByUnit = [];
        foreach (Event::rowsRaw(
            "SELECT er.unit_id, COALESCE(SUM(eri.fee),0) AS d
               FROM event_registration_items eri
               JOIN event_registrations er ON er.id = eri.registration_id
              WHERE er.event_id = ? AND er.unit_id IS NOT NULL
                AND COALESCE(er.admin_review_status,'') <> 'rejected'
              GROUP BY er.unit_id", [$eid]) as $r) {
            $demandByUnit[(int)$r['unit_id']]['individual'] = (float)$r['d'];
        }
        try {
            foreach (Event::rowsRaw(
                "SELECT tr.unit_id, COALESCE(SUM(tr.total_amount),0) AS d
                   FROM team_registrations tr
                  WHERE tr.event_id = ? AND tr.unit_id IS NOT NULL
                    AND COALESCE(tr.admin_review_status,'') <> 'rejected'
                  GROUP BY tr.unit_id", [$eid]) as $r) {
                $demandByUnit[(int)$r['unit_id']]['team'] = (float)$r['d'];
            }
        } catch (\Throwable $e) { /* team tables absent */ }

        // Per-unit collection buckets + fund-transfer rows (bulk mode only).
        $collByUnit = []; $poolByUnit = [];
        if ($bulk) {
            foreach (Event::rowsRaw(
                "SELECT unit_id,
                        COALESCE(SUM(CASE WHEN status='submitted' THEN amount END),0) AS submitted,
                        COALESCE(SUM(CASE WHEN status='approved'  THEN amount END),0) AS approved
                   FROM event_unit_payments
                  WHERE event_id = ?
                  GROUP BY unit_id", [$eid]) as $r) {
                $collByUnit[(int)$r['unit_id']] = [
                    'submitted' => (float)$r['submitted'],
                    'approved'  => (float)$r['approved'],
                ];
            }
            foreach (UnitPayment::forEventAdmin($eid) as $t) {
                $poolByUnit[(int)$t['unit_id']][] = $t;
            }
        }

        // Team entries per unit (all statuses except we surface the status),
        // shown in each group's Team Entry table.
        $teamsByUnit = [];
        try {
            foreach (TeamRegistration::forEvent($eid) as $t) {
                $teamsByUnit[(int)($t['unit_id'] ?? 0)][] = $t;
            }
        } catch (\Throwable $e) { /* team tables absent */ }

        // Assemble groups keyed by unit id (0 = no unit / self-registered).
        $groups = [];
        $ensure = function (int $uid, string $name) use (&$groups, $demandByUnit, $collByUnit, $poolByUnit, $teamsByUnit, $bulk) {
            if (isset($groups[$uid])) return;
            $dInd = (float)($demandByUnit[$uid]['individual'] ?? 0);
            $dTm  = (float)($demandByUnit[$uid]['team']       ?? 0);
            $sub  = (float)($collByUnit[$uid]['submitted']    ?? 0);
            $app  = (float)($collByUnit[$uid]['approved']     ?? 0);
            $groups[$uid] = [
                'unit_id'           => $uid,
                'unit_name'         => $name !== '' ? $name : ($uid === 0 ? 'No unit (self-registered)' : 'Unit #' . $uid),
                'demand_individual' => $dInd,
                'demand_team'       => $dTm,
                'demand_total'      => round($dInd + $dTm, 2),
                'submitted'         => $sub,
                'approved'          => $app,
                'committed'         => round($sub + $app, 2),
                'pool'              => $bulk ? ($poolByUnit[$uid] ?? []) : [],
                'teams'             => $teamsByUnit[$uid] ?? [],
                'rows'              => [],
                'count_submitted'   => 0,
                'count_draft'       => 0,
            ];
        };
        foreach ($regs as $r) {
            $uid = (int)($r['unit_id'] ?? 0);
            $ensure($uid, (string)($r['unit_name'] ?? ''));
            $rs      = (string)($r['admin_review_status'] ?? '');
            $isDraft = ($rs === '');
            // Per-athlete payment settled flag for the soft warning.
            $demand   = (float)($r['total_amount'] ?? 0);
            $approved = (float)($r['approved_amount'] ?? 0);
            $paymentOk = $bulk
                ? (($collByUnit[$uid]['approved'] ?? 0) + 0.005 >= (float)($groups[$uid]['demand_total'] ?? 0) && ($groups[$uid]['demand_total'] ?? 0) > 0)
                : ($demand <= 0 || $approved + 0.005 >= $demand);
            $r['is_draft']   = $isDraft;
            $r['payment_ok'] = $paymentOk;
            $groups[$uid]['rows'][] = $r;
            if ($isDraft) $groups[$uid]['count_draft']++; else $groups[$uid]['count_submitted']++;
        }
        // Ensure groups exist for units that have ONLY team entries (no
        // individual registrations) so their Team Entry table still shows.
        foreach (array_keys($teamsByUnit) as $uid) {
            $nm = '';
            foreach (($teamsByUnit[$uid] ?? []) as $t) { $nm = (string)($t['unit_name'] ?? ''); break; }
            $ensure((int)$uid, $nm);
        }

        // Focus on a single unit when requested (View more).
        $focusUnitName = '';
        if ($focusUnit !== null) {
            if (!isset($groups[$focusUnit])) {
                // Build an empty group so the page isn't blank (unit may exist
                // with no registrations / teams yet).
                $nm = '';
                if ($focusUnit > 0) {
                    $u = EventUnit::find($focusUnit);
                    if ($u && (int)$u['event_id'] === $eid) $nm = (string)$u['name'];
                }
                $ensure($focusUnit, $nm);
            }
            $groups = isset($groups[$focusUnit]) ? [$focusUnit => $groups[$focusUnit]] : [];
            $focusUnitName = $groups[$focusUnit]['unit_name'] ?? '';
        }
        uasort($groups, fn($a, $b) => strcasecmp((string)$a['unit_name'], (string)$b['unit_name']));

        // Also expose draft counts even when hidden, so the toggle can show a
        // badge. A cheap COUNT ignoring the current draft filter.
        $draftTotal = 0;
        try {
            $dc = Event::rowsRaw(
                "SELECT COUNT(*) AS c FROM event_registrations
                  WHERE event_id = ? AND admin_review_status IS NULL", [$eid]);
            $draftTotal = (int)($dc[0]['c'] ?? 0);
        } catch (\Throwable $e) {}

        $this->renderWith('app', 'institution/registrations/by-unit', [
            'institution'     => $this->institution,
            'event'           => $event,
            'eventHash'       => \hid_event($eid),
            'bulk'            => $bulk,
            'groups'          => $groups,
            'show'            => $show,
            'draft_total'     => $draftTotal,
            'focus_unit_id'   => $focusUnit,
            'focus_unit_name' => $focusUnitName,
            'flash'           => $this->flash(),
        ]);
    }

    // ── Unit bulk payment transactions (dedicated sub-page) ──────────────────

    /**
     * GET /institution/events/{id}/unit-payments — dedicated page listing
     * the unit-level bulk payment transactions submitted by each unit, with
     * proof + approve / reject (with reason). Grouped by unit and shown
     * against that unit's demand (individual + team) so the admin can
     * reconcile collection vs demand at a glance.
     */
    public function unitPaymentsList(string $eventHash): void
    {
        $this->boot();
        try { Schema::ensureUnitPayments(); } catch (\Throwable $e) {}
        $eventId = \hid_event_decode($eventHash);
        $event   = Event::findById((int)$eventId);
        if (!$event || (int)$event['institution_id'] !== (int)$this->institution['id']) $this->abort(404);
        $eid = (int)$event['id'];

        // Transactions the admin should see (submitted / approved / rejected).
        $txns = UnitPayment::forEventAdmin($eid);

        // Per-unit demand — individual (item fees) + team (team totals).
        $demandByUnit = [];
        foreach (Event::rowsRaw(
            "SELECT er.unit_id, COALESCE(SUM(eri.fee), 0) AS d
               FROM event_registration_items eri
               JOIN event_registrations er ON er.id = eri.registration_id
              WHERE er.event_id = ? AND er.unit_id IS NOT NULL
                AND COALESCE(er.admin_review_status, '') <> 'rejected'
              GROUP BY er.unit_id", [$eid]) as $r) {
            $demandByUnit[(int)$r['unit_id']]['individual'] = (float)$r['d'];
        }
        try {
            foreach (Event::rowsRaw(
                "SELECT tr.unit_id, COALESCE(SUM(tr.total_amount), 0) AS d
                   FROM team_registrations tr
                  WHERE tr.event_id = ? AND tr.unit_id IS NOT NULL
                    AND COALESCE(tr.admin_review_status, '') <> 'rejected'
                  GROUP BY tr.unit_id", [$eid]) as $r) {
                $demandByUnit[(int)$r['unit_id']]['team'] = (float)$r['d'];
            }
        } catch (\Throwable $e) { /* team tables absent */ }

        // Per-unit collection buckets.
        $collByUnit = [];
        foreach (Event::rowsRaw(
            "SELECT unit_id,
                    COALESCE(SUM(CASE WHEN status='submitted' THEN amount END), 0) AS submitted,
                    COALESCE(SUM(CASE WHEN status='approved'  THEN amount END), 0) AS approved
               FROM event_unit_payments
              WHERE event_id = ?
              GROUP BY unit_id", [$eid]) as $r) {
            $collByUnit[(int)$r['unit_id']] = [
                'submitted' => (float)$r['submitted'],
                'approved'  => (float)$r['approved'],
            ];
        }

        // Unit names for every unit that has demand or a transaction.
        $unitIds = array_values(array_unique(array_merge(
            array_keys($demandByUnit),
            array_keys($collByUnit),
            array_map(fn($t) => (int)$t['unit_id'], $txns)
        )));
        $names = [];
        if ($unitIds) {
            $ph = implode(',', array_fill(0, count($unitIds), '?'));
            foreach (Event::rowsRaw(
                "SELECT id, name FROM event_units WHERE id IN ($ph)",
                array_map('intval', $unitIds)) as $u) {
                $names[(int)$u['id']] = (string)$u['name'];
            }
        }

        // Assemble grouped structure, ordered by unit name.
        $groups = [];
        foreach ($unitIds as $uid) {
            $dInd = (float)($demandByUnit[$uid]['individual'] ?? 0);
            $dTm  = (float)($demandByUnit[$uid]['team']       ?? 0);
            $sub  = (float)($collByUnit[$uid]['submitted']    ?? 0);
            $app  = (float)($collByUnit[$uid]['approved']     ?? 0);
            $groups[$uid] = [
                'unit_id'           => $uid,
                'unit_name'         => $names[$uid] ?? ('Unit #' . $uid),
                'demand_individual' => $dInd,
                'demand_team'       => $dTm,
                'demand_total'      => round($dInd + $dTm, 2),
                'submitted'         => $sub,
                'approved'          => $app,
                'committed'         => round($sub + $app, 2),
                'rows'              => [],
            ];
        }
        foreach ($txns as $t) {
            $uid = (int)$t['unit_id'];
            if (!isset($groups[$uid])) {
                $groups[$uid] = [
                    'unit_id' => $uid, 'unit_name' => $t['unit_name'] ?? ('Unit #' . $uid),
                    'demand_individual' => 0.0, 'demand_team' => 0.0, 'demand_total' => 0.0,
                    'submitted' => 0.0, 'approved' => 0.0, 'committed' => 0.0, 'rows' => [],
                ];
            }
            $groups[$uid]['rows'][] = $t;
        }
        uasort($groups, fn($a, $b) => strcasecmp($a['unit_name'], $b['unit_name']));

        $this->renderWith('app', 'institution/unit-payments/index', [
            'institution' => $this->institution,
            'event'       => $event,
            'eventHash'   => \hid_event($eid),
            'groups'      => $groups,
            'flash'       => $this->flash(),
        ]);
    }

    /**
     * POST /institution/unit-payments/{id}/decision — approve / reject a
     * submitted unit bulk transaction. Reject stores the reason and soft-
     * deletes the row from the unit's active list (they enter a fresh one).
     */
    public function unitPaymentDecision(string $paymentId): void
    {
        $this->boot();
        $this->verifyCsrf();
        try { Schema::ensureUnitPayments(); } catch (\Throwable $e) {}

        $pay = UnitPayment::find((int)$paymentId);
        if (!$pay) $this->abort(404);
        $event = Event::findById((int)$pay['event_id']);
        if (!$event || (int)$event['institution_id'] !== (int)$this->institution['id']) $this->abort(404);
        $eventHash = \hid_event((int)$event['id']);
        $back = "/institution/events/{$eventHash}/unit-payments";
        // Honour a same-site institution return target (e.g. the grouped
        // "Athletes by Unit" page posts back its own URL).
        $reqBack = (string)($_POST['back'] ?? '');
        if (preg_match('#^/institution/[^\s?]*(\?[^\s]*)?$#', $reqBack)) $back = $reqBack;

        $action = $_POST['action'] ?? '';
        $reason = trim((string)($_POST['reason'] ?? ''));
        $map = ['approve' => 'approved', 'reject' => 'rejected'];
        if (!isset($map[$action])) {
            $this->redirect($back, 'Invalid action.', 'error');
        }
        if ($action === 'reject' && $reason === '') {
            $this->redirect($back, 'A reason is required to reject a transaction.', 'warning');
        }
        UnitPayment::updateRow((int)$paymentId, [
            'status'           => $map[$action],
            'reject_reason'    => $action === 'reject' ? $reason : null,
            'reviewed_by'      => Auth::id(),
            'reviewed_by_name' => (string)((Auth::user() ?? [])['name'] ?? ''),
            'reviewed_at'      => date('Y-m-d H:i:s'),
        ]);
        $this->redirect($back, 'Transaction ' . $map[$action] . '.');
    }

    /**
     * GET /institution/events/{id}/units/{unitId}/receipt.pdf — consolidated
     * payment receipt (Dompdf) for one unit's approved bulk transactions.
     * Issued by the event organiser.
     */
    public function unitReceiptPdf(string $eventHash, string $unitId): void
    {
        $this->boot();
        try { Schema::ensureUnitPayments(); } catch (\Throwable $e) {}
        try { Schema::ensureUnitReceipts(); } catch (\Throwable $e) {}
        $eventId = \hid_event_decode($eventHash);
        $event   = Event::findById((int)$eventId);
        if (!$event || (int)$event['institution_id'] !== (int)$this->institution['id']) $this->abort(404);
        $eu = EventUnit::find((int)$unitId);
        if (!$eu || (int)$eu['event_id'] !== (int)$event['id']) $this->abort(404);

        if (!\Core\UnitReceiptPdf::hasApproved((int)$event['id'], (int)$eu['id'])) {
            $this->redirect('/institution/events/' . \hid_event((int)$event['id']) . '/unit-payments',
                'No approved transactions to receipt for this unit yet.', 'warning');
        }
        \Core\UnitReceiptPdf::stream($event, $this->institution, $eu);
    }

    // ── Grievances (per-event, replies + status changes) ─────────────────────

    private function resolveEventForGrievance(string $eventHash): array
    {
        $eventId = \hid_event_decode($eventHash);
        $event   = Event::findById((int)$eventId);
        if (!$event || (int)$event['institution_id'] !== (int)$this->institution['id']) {
            $this->abort(404);
        }
        return $event;
    }

    /** GET /institution/events/{id}/grievances — list every grievance filed
     *  against this event, regardless of athlete. */
    public function eventGrievances(string $eventHash): void
    {
        $this->boot();
        $event = $this->resolveEventForGrievance($eventHash);
        $status = $_GET['status'] ?? '';
        $rows = Grievance::forEvent((int)$event['id'], $status);
        $this->renderWith('app', 'institution/grievances/index', [
            'institution' => $this->institution,
            'event'       => $event,
            'eventHash'   => $eventHash,
            'grievances'  => $rows,
            'status'      => $status,
        ]);
    }

    public function grievanceShow(string $id): void
    {
        $this->boot();
        $g = Grievance::withContext((int)$id);
        if (!$g) $this->abort(404);
        // Authorise: this institution must own the event.
        $event = Event::findById((int)$g['event_id']);
        if (!$event || (int)$event['institution_id'] !== (int)$this->institution['id']) $this->abort(404);

        $this->renderWith('app', 'institution/grievances/show', [
            'institution' => $this->institution,
            'event'       => $event,
            'eventHash'   => \hid_event((int)$event['id']),
            'grievance'   => $g,
            'replies'     => Grievance::replies((int)$id),
        ]);
    }

    public function grievanceReply(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $g = Grievance::find((int)$id);
        if (!$g) $this->abort(404);
        $event = Event::findById((int)$g['event_id']);
        if (!$event || (int)$event['institution_id'] !== (int)$this->institution['id']) $this->abort(404);

        $message = trim((string)($_POST['message'] ?? ''));
        $newStatus = $_POST['status'] ?? '';
        if ($message === '' && !in_array($newStatus, ['open','in_progress','resolved','closed'], true)) {
            $this->redirect("/institution/grievances/{$id}", 'Type a reply or pick a status to update.', 'error');
        }

        if ($message !== '') {
            Grievance::addReply([
                'grievance_id'   => (int)$id,
                'author_user_id' => Auth::id(),
                'author_role'    => 'institution_admin',
                'message'        => $message,
            ]);
            Grievance::bumpUpdated((int)$id);
        }
        if (in_array($newStatus, ['open','in_progress','resolved','closed'], true)) {
            Grievance::setStatus((int)$id, $newStatus);
        }
        $this->redirect("/institution/grievances/{$id}", 'Grievance updated.', 'success');
    }
}
