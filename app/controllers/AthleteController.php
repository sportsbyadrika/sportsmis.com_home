<?php
namespace Controllers;

use Core\{Controller, Auth, FileUpload, Mailer, Razorpay};
use Models\{Athlete, Event, EventUnit, EventDocument, EventRegistration, EventRegistrationPayment, Schema, User, Grievance};

class AthleteController extends Controller
{
    private array $athlete;

    private function boot(): void
    {
        $this->requireAuth('athlete');
        try { Schema::ensureAthleteDobProof(); } catch (\Throwable $e) {
            error_log('[athlete/ensureSchema] ' . $e->getMessage());
        }
        $a = Athlete::findByUserId(Auth::id());
        if (!$a) $this->redirect('/login', 'Athlete profile not found.', 'error');
        $this->athlete = $a;
    }

    public function dashboard(): void
    {
        $this->boot();
        $registrations = Event::getAthleteRegistrations($this->athlete['id']);
        $activeEvents  = !empty($this->athlete['profile_completed'])
            ? Event::getActiveEvents()
            : [];

        // Lookup of "my registration" indexed by event_id so the active-events
        // card can show date / app status / payment status at a glance.
        $regByEvent = [];
        foreach ($registrations as $r) {
            $regByEvent[(int)$r['event_id']] = $r;
        }

        $this->renderWith('app', 'dashboard/athlete', [
            'athlete'        => $this->athlete,
            'registrations'  => $registrations,
            'active_events'  => $activeEvents,
            'reg_by_event'   => $regByEvent,
            'flash'          => $this->flash(),
        ]);
    }

    public function profileForm(): void
    {
        $this->boot();
        $this->renderWith('app', 'athlete/profile', [
            'athlete'         => $this->athlete,
            'sports'          => Athlete::getEventSports(),
            'athlete_sports'  => Athlete::getSports($this->athlete['id']),
            'aadhaar_type'    => Athlete::getAadhaarProofType(),
            'dob_proof_types' => Athlete::getDobProofTypes(),
            'countries'       => Athlete::getCountries(),
            'states'          => Athlete::getStatesByCountry((int)($this->athlete['country_id'] ?? 1)),
            'districts'       => $this->athlete['state_id'] ? Athlete::getDistrictsByState((int)$this->athlete['state_id']) : [],
            'profile_locked'  => Athlete::isProfileLocked((int)$this->athlete['id']),
            'flash'           => $this->flash(),
            'errors'          => $this->errors(),
        ]);
    }

    public function updateProfile(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $dob       = $_POST['date_of_birth'] ?? '';
        $isMinor   = $dob && \ageFromDob($dob) < 18;

        $rules = [
            'name'          => 'required|max:255',
            'date_of_birth' => 'required',
            'mobile'        => 'required|mobile',
            'gender'        => 'required',
            'address'       => 'required',
            'nationality'   => 'required',
        ];
        if ($isMinor) $rules['guardian_name'] = 'required|max:255';

        $errors = $this->validate($rules);

        $data = [
            'name'                 => trim($_POST['name']),
            'date_of_birth'        => $dob,
            'mobile'               => trim($_POST['mobile']),
            'whatsapp_number'      => trim($_POST['whatsapp_number'] ?? ''),
            'gender'               => $_POST['gender'],
            'weight'               => $_POST['weight'] ?: null,
            'height'               => $_POST['height'] ?: null,
            'address'              => trim($_POST['address']),
            'guardian_name'        => trim($_POST['guardian_name'] ?? ''),
            'id_proof_type_id'     => (int)($_POST['id_proof_type_id'] ?? 0) ?: null,
            'id_proof_number'      => trim($_POST['id_proof_number'] ?? ''),
            'communication_address'=> trim($_POST['communication_address'] ?? ''),
            'country_id'           => (int)($_POST['country_id'] ?? 1),
            'state_id'             => (int)($_POST['state_id'] ?? 0) ?: null,
            'district_id'          => (int)($_POST['district_id'] ?? 0) ?: null,
            'nationality'          => trim($_POST['nationality']),
        ];

        if (!empty($_FILES['passport_photo']['name'])) {
            try { $data['passport_photo'] = (new FileUpload())->upload($_FILES['passport_photo'], 'athletes/photos', true); }
            catch (\RuntimeException $e) { $errors['passport_photo'][] = $e->getMessage(); }
        }

        if (!empty($_FILES['id_proof_file']['name'])) {
            try { $data['id_proof_file'] = (new FileUpload())->upload($_FILES['id_proof_file'], 'athletes/idproofs'); }
            catch (\RuntimeException $e) { $errors['id_proof_file'][] = $e->getMessage(); }
        }

        if ($errors) { $_SESSION['errors'] = $errors; $this->redirect('/athlete/profile'); }

        // Check profile completeness
        $required = ['date_of_birth', 'mobile', 'address', 'id_proof_number', 'nationality'];
        $complete = true;
        foreach ($required as $f) { if (empty($data[$f])) { $complete = false; break; } }
        if (!$this->athlete['passport_photo'] && empty($data['passport_photo'])) $complete = false;
        $data['profile_completed'] = $complete ? 1 : 0;

        Athlete::updateProfile($this->athlete['id'], $data);

        // Sync sports
        $sports = [];
        foreach ($_POST['sports'] ?? [] as $sportId => $info) {
            if (!empty($info['selected'])) {
                $sports[(int)$sportId] = [
                    'sport_specific_id' => $info['sport_specific_id'] ?? null,
                    'licenses'          => $info['licenses'] ?? null,
                ];
            }
        }
        Athlete::syncSports($this->athlete['id'], $sports);

        $this->redirect('/athlete/profile', 'Profile updated successfully!');
    }

    public function browseEvents(): void
    {
        $this->boot();
        $events = Event::getActiveEvents();
        $this->renderWith('app', 'athlete/events/index', [
            'athlete' => $this->athlete,
            'events'  => $events,
            'flash'   => $this->flash(),
        ]);
    }

    public function eventDetail(string $id): void
    {
        $id = (string)\hid_event_decode($id);
        $this->boot();
        $event = Event::findById((int)$id);
        if (!$event || $event['status'] !== 'active') $this->abort(404);
        $this->renderWith('app', 'athlete/events/detail', [
            'athlete' => $this->athlete,
            'event'   => $event,
            'flash'   => $this->flash(),
        ]);
    }

    public function registerForm(string $id): void
    {
        $id = (string)\hid_event_decode($id);
        $this->boot();
        try { Schema::ensureSportHierarchy(); } catch (\Throwable $e) {
            error_log('[athlete/register/ensureSchema] ' . $e->getMessage());
        }
        if (!$this->athlete['profile_completed']) {
            $this->redirect('/athlete/profile', 'Please complete your profile before registering for events.', 'warning');
        }
        $event = Event::findById((int)$id);
        if (!$event || $event['status'] !== 'active') $this->abort(404);

        $registration = EventRegistration::findHeader((int)$id, (int)$this->athlete['id']);
        $items        = $registration ? EventRegistration::items((int)$registration['id']) : [];
        $payments     = $registration ? EventRegistrationPayment::forRegistration((int)$registration['id']) : [];

        $this->renderWith('app', 'athlete/events/register', [
            'athlete'      => $this->athlete,
            'event'        => $event,
            'units'        => EventUnit::forEvent((int)$id),
            'documents'    => EventDocument::activeForEvent((int)$id),
            'registration' => $registration,
            'items'        => $items,
            'payments'     => $payments,
            'flash'        => $this->flash(),
        ]);
    }

    public function registerSave(string $id): void
    {
        $id = (string)\hid_event_decode($id);
        $this->boot();
        $this->verifyCsrf();
        try { Schema::ensureSportHierarchy(); } catch (\Throwable $e) {}

        $event = Event::findById((int)$id);
        if (!$event || $event['status'] !== 'active') $this->abort(404);

        // Unit picker — supports a literal "OTHER" sentinel that switches
        // the dropdown into a free-text input.
        $rawUnit       = (string)($_POST['unit_id'] ?? '');
        $isOther       = ($rawUnit === 'OTHER');
        $unitId        = $isOther ? 0 : (int)$rawUnit;
        $unitNameOther = $isOther ? trim((string)($_POST['unit_name_other'] ?? '')) : '';
        $unitRegNo     = trim((string)($_POST['unit_reg_no'] ?? ''));

        if ($isOther) {
            if ($unitNameOther === '') {
                $this->json(['success' => false, 'message' => 'Enter the Unit / Club / Institution name.']);
            }
        } else {
            if (!$unitId) $this->json(['success' => false, 'message' => 'Please select a Unit / Club / Institution.']);
            $unit = EventUnit::find($unitId);
            if (!$unit || (int)$unit['event_id'] !== (int)$id) {
                $this->json(['success' => false, 'message' => 'Invalid unit for this event.']);
            }
        }

        $eventSportIds = array_map('intval', $_POST['event_sport_ids'] ?? []);
        $eventSportIds = array_values(array_filter($eventSportIds));
        if (!$eventSportIds) {
            $this->json(['success' => false, 'message' => 'Pick at least one sport event.']);
        }
        // Validate each pick against this event AND the athlete's gender.
        // (Age-category eligibility is intentionally disabled for now.)
        $allRows  = Event::getSports((int)$id);
        $byId     = [];
        foreach ($allRows as $r) $byId[(int)$r['id']] = $r;
        // Normalise gender values: profile stores 'male'/'female',
        // catalog displays 'Men'/'Women' (same underlying enum) — but
        // some legacy data may carry 'men'/'women' literally.
        $normGender = static function (?string $g): string {
            $g = strtolower(trim((string)$g));
            return match ($g) { 'men' => 'male', 'women' => 'female', default => $g };
        };
        $athleteGender  = $normGender($this->athlete['gender'] ?? '');
        $canGenderCheck = ($athleteGender === 'male' || $athleteGender === 'female');
        foreach ($eventSportIds as $esId) {
            if (!isset($byId[$esId])) {
                $this->json(['success' => false, 'message' => 'One or more selections are not part of this event.']);
            }
            $row = $byId[$esId];
            $rowGender = $normGender($row['sport_event_gender'] ?? '');
            if ($canGenderCheck && $rowGender && $rowGender !== 'mixed' && $rowGender !== $athleteGender) {
                $this->json(['success' => false,
                    'message' => 'You can only register for events matching your gender (or Mixed).']);
            }
        }

        $registration = EventRegistration::findHeader((int)$id, (int)$this->athlete['id']);
        if (!EventRegistration::isEditable($registration)) {
            $this->json(['success' => false,
                'message' => 'This registration has already been submitted and is awaiting review. '
                           . 'Wait for the event administrator to return it before making changes.']);
        }
        $regId = $registration['id'] ?? null;
        if (!$regId) {
            $regId = EventRegistration::createDraft((int)$id, (int)$this->athlete['id']);
        }

        // NOC handling.
        $nocReq = $event['noc_required'] ?? 'optional';
        $header = [
            'unit_id'         => $unitId ?: null,
            'unit_name_other' => $unitNameOther ?: null,
            'unit_reg_no'     => $unitRegNo ?: null,
        ];
        if (!empty($_FILES['noc_letter']['name'])) {
            try {
                $header['noc_letter'] = (new FileUpload())->upload($_FILES['noc_letter'], 'registrations');
            } catch (\RuntimeException $e) {
                $this->json(['success' => false, 'message' => 'NOC upload failed: ' . $e->getMessage()]);
            }
        } elseif ($nocReq === 'mandatory' && empty($registration['noc_letter'])) {
            $this->json(['success' => false, 'message' => 'NOC letter is mandatory for this event. Please upload it.']);
        }

        // Sync line items, get total.
        $total = EventRegistration::syncItems((int)$regId, $eventSportIds);
        $header['total_amount'] = $total;
        EventRegistration::updateHeader((int)$regId, $header);

        $this->json([
            'success'      => true,
            'message'      => 'Saved. Now choose payment mode.',
            'registration' => array_merge(EventRegistration::findHeader((int)$id, (int)$this->athlete['id']) ?? [], [
                'items_count' => count($eventSportIds),
            ]),
            'total'        => (float)$total,
        ]);
    }

    /** POST /athlete/events/{id}/register/payment-mode — pick the mode (no submission yet). */
    public function registerSetPaymentMode(string $id): void
    {
        $id = (string)\hid_event_decode($id);
        $this->boot();
        $this->verifyCsrf();

        $event = Event::findById((int)$id);
        if (!$event || $event['status'] !== 'active') $this->abort(404);

        $registration = EventRegistration::findHeader((int)$id, (int)$this->athlete['id']);
        if (!$registration) $this->json(['success' => false, 'message' => 'Save Step 1 first.']);

        $mode = $_POST['payment_mode'] ?? '';
        $allowedModes = $event['payment_modes'] ?? [];
        if (!in_array($mode, $allowedModes, true)) {
            $this->json(['success' => false, 'message' => 'Choose a valid payment mode.']);
        }
        EventRegistration::updateHeader((int)$registration['id'], ['payment_mode' => $mode]);
        $this->json(['success' => true, 'message' => 'Payment mode saved.']);
    }

    /** POST /athlete/events/{id}/register/payment — add one manual-payment record. */
    public function registerAddPayment(string $id): void
    {
        $id = (string)\hid_event_decode($id);
        $this->boot();
        $this->verifyCsrf();

        $event = Event::findById((int)$id);
        if (!$event || $event['status'] !== 'active') $this->abort(404);

        $registration = EventRegistration::findHeader((int)$id, (int)$this->athlete['id']);
        if (!$registration) $this->json(['success' => false, 'message' => 'Save Step 1 first.']);
        if (in_array(($registration['admin_review_status'] ?? null), ['approved'], true)) {
            $this->json(['success' => false, 'message' => 'Registration is already approved — payments are locked.']);
        }

        $txDate = $_POST['transaction_date'] ?? '';
        $txNum  = trim($_POST['transaction_number'] ?? '');
        $amount = (float)($_POST['transaction_amount'] ?? 0);
        if (!$txDate || !$txNum || $amount <= 0) {
            $this->json(['success' => false, 'message' => 'Transaction date, number and amount are required.']);
        }
        if (empty($_FILES['transaction_proof']['name'])) {
            $this->json(['success' => false, 'message' => 'Transaction proof file is mandatory.']);
        }
        try {
            $proof = (new FileUpload())->upload($_FILES['transaction_proof'], 'registrations');
        } catch (\RuntimeException $e) {
            $this->json(['success' => false, 'message' => 'Proof upload failed: ' . $e->getMessage()]);
        }

        EventRegistrationPayment::create([
            'registration_id'    => (int)$registration['id'],
            'event_id'           => (int)$registration['event_id'],
            'transaction_date'   => $txDate,
            'transaction_number' => $txNum,
            'amount'             => $amount,
            'proof_file'         => $proof,
            'status'             => 'pending',
        ]);

        // Make sure the header reflects "manual" mode and update payment_status.
        EventRegistration::updateHeader((int)$registration['id'], ['payment_mode' => 'manual']);
        EventRegistrationPayment::recomputeRegistrationPaymentStatus((int)$registration['id']);

        $this->json([
            'success'  => true,
            'message'  => 'Transaction added.',
            'payments' => EventRegistrationPayment::forRegistration((int)$registration['id']),
        ]);
    }

    /** POST /athlete/events/{id}/register/payment-remove — remove a pending payment row. */
    public function registerRemovePayment(string $id): void
    {
        $id = (string)\hid_event_decode($id);
        $this->boot();
        $this->verifyCsrf();

        $registration = EventRegistration::findHeader((int)$id, (int)$this->athlete['id']);
        if (!$registration) $this->abort(404);
        $paymentId = (int)($_POST['payment_id'] ?? 0);
        $payment   = EventRegistrationPayment::find($paymentId);
        if (!$payment || (int)$payment['registration_id'] !== (int)$registration['id']) {
            $this->json(['success' => false, 'message' => 'Transaction not found.']);
        }
        if ($payment['status'] === 'approved') {
            $this->json(['success' => false, 'message' => 'Approved transactions cannot be removed.']);
        }
        EventRegistrationPayment::deleteRow($paymentId);
        EventRegistrationPayment::recomputeRegistrationPaymentStatus((int)$registration['id']);
        $this->json([
            'success'  => true,
            'message'  => 'Transaction removed.',
            'payments' => EventRegistrationPayment::forRegistration((int)$registration['id']),
        ]);
    }

    /** POST /athlete/events/{id}/register/submit — Final Submit; handed over to event admin. */
    public function registerSubmit(string $id): void
    {
        $id = (string)\hid_event_decode($id);
        $this->boot();
        $this->verifyCsrf();

        $event = Event::findById((int)$id);
        if (!$event || $event['status'] !== 'active') $this->abort(404);

        $registration = EventRegistration::findHeader((int)$id, (int)$this->athlete['id']);
        if (!$registration || empty($registration['unit_id'])) {
            $this->json(['success' => false, 'message' => 'Please save Step 1 first.']);
        }
        $items = EventRegistration::items((int)$registration['id']);
        if (!$items) {
            $this->json(['success' => false, 'message' => 'No sport events selected. Add at least one in Step 1.']);
        }

        $allowedModes = $event['payment_modes'] ?? [];
        if (!$allowedModes) {
            $this->json(['success' => false,
                'message' => 'This event has no payment modes configured. Please contact the organiser.']);
        }

        // Resolve the payment mode. Prefer what's on the registration; if it
        // wasn't saved (e.g. the radio onchange didn't fire on slow networks)
        // fall back to a POSTed value, then infer from existing transactions.
        $mode = $registration['payment_mode'] ?? '';
        if (!$mode) $mode = $_POST['payment_mode'] ?? '';
        $payments = EventRegistrationPayment::forRegistration((int)$registration['id']);
        if (!$mode && $payments)                          $mode = 'manual';
        if (!$mode && count($allowedModes) === 1)         $mode = $allowedModes[0];
        if (!in_array($mode, $allowedModes, true)) {
            $this->json(['success' => false,
                'message' => 'Pick a payment mode in Step 2 before submitting.']);
        }
        // Persist the resolved mode so subsequent loads stay consistent.
        if (($registration['payment_mode'] ?? '') !== $mode) {
            EventRegistration::updateHeader((int)$registration['id'], ['payment_mode' => $mode]);
        }

        if ($mode === 'manual' && !$payments) {
            $this->json(['success' => false,
                'message' => 'Add at least one payment transaction before submitting.']);
        }

        try {
            EventRegistration::updateHeader((int)$registration['id'], [
                'status'              => 'pending',
                'admin_review_status' => 'pending',
                'submitted_at'        => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('[athlete/registerSubmit] update failed: ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Could not submit: ' . $e->getMessage()]);
        }

        $msg = 'Registration submitted! The event administrator will review your application and payment.';
        $_SESSION['flash'] = ['type' => 'success', 'message' => $msg];
        $this->json(['success' => true, 'message' => $msg, 'redirect' => '/athlete/my-registrations']);
    }

    // ── Razorpay (ePayment) ──────────────────────────────────────────────────

    /**
     * POST /athlete/events/{id}/pay/create-order
     * Creates a Razorpay Order for the registration's outstanding fee, inserts
     * a 'pending' epayment row in event_registration_payments, and returns the
     * keys checkout.js needs to open the modal.
     *
     * The amount is ALWAYS read from the server (event_registrations.total_amount
     * minus already-approved payments) — the client-supplied amount is ignored.
     */
    public function payCreateOrder(string $id): void
    {
        $id = (string)\hid_event_decode($id);
        $this->boot();
        $this->verifyCsrf();

        $event = Event::findById((int)$id);
        if (!$event || $event['status'] !== 'active') $this->abort(404);

        $registration = EventRegistration::findHeader((int)$id, (int)$this->athlete['id']);
        if (!$registration || empty($registration['unit_id'])) {
            $this->json(['success' => false, 'message' => 'Save Step 1 first.'], 400);
        }
        $items = EventRegistration::items((int)$registration['id']);
        if (!$items) {
            $this->json(['success' => false, 'message' => 'No sport events selected.'], 400);
        }
        if (!in_array('online', $event['payment_modes'] ?? [], true)) {
            $this->json(['success' => false, 'message' => 'Online payment is not enabled for this event.'], 400);
        }

        // Server-authoritative amount: required total minus what's already
        // been approved. Manual + ePayment payments share the same totals
        // because they live in the same transactions table.
        $totals       = EventRegistrationPayment::totals((int)$registration['id']);
        $required     = (float)($registration['total_amount'] ?? 0);
        $alreadyPaid  = (float)($totals['approved_amount'] ?? 0);
        $outstanding  = round($required - $alreadyPaid, 2);
        if ($outstanding <= 0) {
            $this->json(['success' => false, 'message' => 'No outstanding amount — registration is already paid.'], 400);
        }
        $amountPaise = (int) round($outstanding * 100);

        try {
            $rzp = new Razorpay();
            $order = $rzp->createOrder($amountPaise,
                'reg-' . (int)$registration['id'] . '-' . time(),
                'INR',
                [
                    'registration_id' => (string)(int)$registration['id'],
                    'event_id'        => (string)(int)$id,
                    'athlete_id'      => (string)(int)$this->athlete['id'],
                ]
            );
        } catch (\RuntimeException $e) {
            error_log('[athlete/payCreateOrder] ' . $e->getMessage());
            $code = $e->getCode() === 401 ? 401 : 500;
            $this->json(['success' => false, 'message' => $e->getMessage()], $code);
        }

        // Persist a pending epayment transaction. The unique index on
        // razorpay_order_id makes this idempotent — a retry from the
        // browser won't create duplicates.
        try {
            EventRegistrationPayment::create([
                'registration_id'    => (int)$registration['id'],
                'event_id'           => (int)$registration['event_id'],
                'transaction_date'   => date('Y-m-d'),
                'transaction_number' => $order['id'],
                'amount'             => $outstanding,
                'proof_file'         => null,
                'status'             => 'pending',
                'payment_method'     => 'epayment',
                'razorpay_order_id'  => $order['id'],
            ]);
        } catch (\Throwable $e) {
            error_log('[athlete/payCreateOrder] insert failed: ' . $e->getMessage());
        }
        EventRegistration::updateHeader((int)$registration['id'], ['payment_mode' => 'online']);

        $user = User::findById((int)$this->athlete['user_id']);
        $this->json([
            'success'  => true,
            'order_id' => $order['id'],
            'amount'   => $amountPaise,
            'currency' => $order['currency'],
            'key_id'   => $rzp->keyId(),
            'prefill'  => [
                'name'    => $this->athlete['name']   ?? '',
                'contact' => $this->athlete['mobile'] ?? '',
                'email'   => $user['email']           ?? '',
            ],
        ]);
    }

    /**
     * POST /athlete/events/{id}/pay/verify
     * Receives the three Razorpay round-trip values, verifies the HMAC,
     * and on match auto-approves the matching event_registration_payments
     * row (status='approved'). On mismatch the row is rejected.
     */
    public function payVerify(string $id): void
    {
        $id = (string)\hid_event_decode($id);
        $this->boot();
        $this->verifyCsrf();

        $orderId   = (string)($_POST['razorpay_order_id']   ?? '');
        $paymentId = (string)($_POST['razorpay_payment_id'] ?? '');
        $signature = (string)($_POST['razorpay_signature']  ?? '');
        if ($orderId === '' || $paymentId === '' || $signature === '') {
            $this->json(['success' => false, 'message' => 'Missing payment fields.'], 400);
        }

        // Locate the pending epayment row we created at order time. Joining
        // back to the registration also lets us verify athlete ownership,
        // closing off cross-account replay attacks.
        $row = EventRegistrationPayment::findByOrderId($orderId);
        if (!$row) $this->abort(404);
        $registration = EventRegistration::findById((int)$row['registration_id']);
        if (!$registration || (int)$registration['athlete_id'] !== (int)$this->athlete['id']) {
            $this->abort(403);
        }

        $rzp = new Razorpay();
        $ok  = $rzp->verifySignature($orderId, $paymentId, $signature);

        if (!$ok) {
            EventRegistrationPayment::updateRow((int)$row['id'], [
                'status'              => 'rejected',
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature'  => $signature,
                'rejection_reason'    => 'AUTO: signature mismatch',
                'reviewed_at'         => date('Y-m-d H:i:s'),
            ]);
            EventRegistrationPayment::recomputeRegistrationPaymentStatus((int)$registration['id']);
            error_log('[athlete/payVerify] HMAC mismatch on order ' . $orderId);
            $this->json(['success' => false, 'message' => 'Payment signature verification failed.'], 400);
        }

        // Auto-approve. reviewed_by stays NULL (FK to users — no user did
        // this); rejection_reason carries the audit string.
        EventRegistrationPayment::updateRow((int)$row['id'], [
            'status'              => 'approved',
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature'  => $signature,
            'rejection_reason'    => 'AUTO: ePayment HMAC verified',
            'reviewed_at'         => date('Y-m-d H:i:s'),
        ]);
        EventRegistrationPayment::recomputeRegistrationPaymentStatus((int)$registration['id']);

        $this->json([
            'success'    => true,
            'message'    => 'Payment verified.',
            'payment_id' => $paymentId,
            'status'     => 'approved',
        ]);
    }

    public function myRegistrations(): void
    {
        $this->boot();
        $this->renderWith('app', 'athlete/my-registrations', [
            'athlete'       => $this->athlete,
            'registrations' => Event::getAthleteRegistrations($this->athlete['id']),
            'flash'         => $this->flash(),
        ]);
    }

    /**
     * Print-friendly Competitor Card. Available once the event admin has
     * approved the registration and a competitor_number has been allocated.
     * Accessible to the owning athlete OR to the institution admin who runs
     * the event (so they can print a stack of cards from the review screen).
     */
    public function competitorCard(string $id): void
    {
        $id = (string)\hid_reg_decode($id);
        if (!Auth::check()) $this->redirect('/login');
        $reg = EventRegistration::findById((int)$id);
        if (!$reg) $this->abort(404);

        $event       = Event::findById((int)$reg['event_id']);
        if (!$event) $this->abort(404);

        $allowed = false; $athlete = null;
        if (Auth::is('athlete')) {
            $athlete = Athlete::findByUserId(Auth::id());
            if ($athlete && (int)$reg['athlete_id'] === (int)$athlete['id']) $allowed = true;
        }
        if (Auth::is('institution_admin')) {
            $inst = \Models\Institution::findByUserId(Auth::id());
            if ($inst && (int)$event['institution_id'] === (int)$inst['id']) {
                $allowed = true;
                $athlete = Athlete::findById((int)$reg['athlete_id']);
            }
        }
        if (!$allowed) $this->abort(403);

        if (($reg['admin_review_status'] ?? '') !== 'approved' || empty($reg['competitor_number'])) {
            $this->redirect(Auth::is('institution_admin')
                ? "/institution/registrations/{$id}"
                : '/athlete/my-registrations',
                'Competitor card is available only after the registration is approved and a competitor number is allocated.',
                'warning');
        }

        $institution = \Models\Institution::findById((int)$event['institution_id']);
        $items       = EventRegistration::items((int)$id);

        // Render outside the regular layout so the card is print-friendly.
        $data = [
            'athlete'      => $athlete,
            'event'        => $event,
            'institution'  => $institution,
            'registration' => $reg,
            'items'        => $items,
        ];
        extract($data);
        require APP_ROOT . '/views/athlete/events/competitor-card.php';
    }

    public function viewRegistration(string $id): void
    {
        $id = (string)\hid_reg_decode($id);
        $this->boot();
        $reg = EventRegistration::findById((int)$id);
        if (!$reg || (int)$reg['athlete_id'] !== (int)$this->athlete['id']) $this->abort(404);

        $event = Event::findById((int)$reg['event_id']);
        $unit  = !empty($reg['unit_id']) ? EventUnit::find((int)$reg['unit_id']) : null;

        $this->renderWith('app', 'athlete/registration-view', [
            'athlete'      => $this->athlete,
            'event'        => $event,
            'registration' => $reg,
            'unit'         => $unit,
            'items'        => EventRegistration::items((int)$id),
            'documents'    => EventDocument::activeForEvent((int)$event['id']),
            'flash'        => $this->flash(),
        ]);
    }

    // ── AJAX Profile Section Save ────────────────────────────────────────────

    public function ajaxSave(): void
    {
        $this->boot();
        $this->verifyCsrf();

        if (Athlete::isProfileLocked((int)$this->athlete['id'])) {
            $this->json(['success' => false,
                'message' => 'Your profile is locked because an event registration has been approved. '
                           . 'Contact the event administrator if changes are required.']);
        }

        $section = $_POST['section'] ?? '';
        match ($section) {
            'photo'    => $this->savePhotoSection(),
            'personal' => $this->savePersonalSection(),
            'location' => $this->saveLocationSection(),
            'idproof'  => $this->saveIdProofSection(),
            'dobproof' => $this->saveDobProofSection(),
            'sports'   => $this->saveSportsSection(),
            default    => $this->json(['success' => false, 'message' => 'Unknown section.']),
        };
    }

    private function savePhotoSection(): void
    {
        if (empty($_FILES['passport_photo']) || empty($_FILES['passport_photo']['name'])) {
            $maxPost = ini_get('post_max_size');
            $hint = empty($_POST) && empty($_FILES)
                ? " The request body was empty — your photo may be larger than the server's post_max_size ({$maxPost})."
                : '';
            error_log('[athlete/photo] No file in $_FILES. POST keys: ' . implode(',', array_keys($_POST))
                . '; FILES keys: ' . implode(',', array_keys($_FILES)));
            $this->json(['success' => false, 'message' => 'No photo received by the server.' . $hint]);
        }
        try {
            $url = (new FileUpload())->upload($_FILES['passport_photo'], 'athletes/photos', true);
            Athlete::updateProfile($this->athlete['id'], ['passport_photo' => $url]);
            $this->json(['success' => true, 'message' => 'Photo updated!', 'photo_url' => $url]);
        } catch (\RuntimeException $e) {
            error_log('[athlete/photo] Upload failed: ' . $e->getMessage()
                . ' | tmp=' . ($_FILES['passport_photo']['tmp_name'] ?? '-')
                . ' | size=' . ($_FILES['passport_photo']['size'] ?? '-')
                . ' | err=' . ($_FILES['passport_photo']['error'] ?? '-'));
            $this->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function savePersonalSection(): void
    {
        $name    = trim($_POST['name'] ?? '');
        $dob     = $_POST['date_of_birth'] ?? '';
        $gender  = $_POST['gender'] ?? '';
        $mobile  = trim($_POST['mobile'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (!$name || !$dob || !$gender || !$mobile || !$address) {
            $this->json(['success' => false, 'message' => 'Name, date of birth, gender, mobile, and address are required.']);
        }
        if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
            $this->json(['success' => false, 'message' => 'Enter a valid 10-digit mobile number.']);
        }

        $age = \ageFromDob($dob);
        $guardian = trim($_POST['guardian_name'] ?? '');
        if ($age < 18 && !$guardian) {
            $this->json(['success' => false, 'message' => 'Guardian name is required for athletes under 18.']);
        }

        Athlete::updateProfile($this->athlete['id'], [
            'name'                  => $name,
            'date_of_birth'         => $dob,
            'gender'                => $gender,
            'mobile'                => $mobile,
            'whatsapp_number'       => trim($_POST['whatsapp_number'] ?? ''),
            'weight'                => $_POST['weight'] ?: null,
            'height'                => $_POST['height'] ?: null,
            'guardian_name'         => $guardian,
            'address'               => $address,
            'communication_address' => trim($_POST['communication_address'] ?? ''),
        ]);
        $this->json(['success' => true, 'message' => 'Personal information saved!']);
    }

    private function saveLocationSection(): void
    {
        $nationality = trim($_POST['nationality'] ?? '');
        $countryId   = (int)($_POST['country_id'] ?? 0);
        $stateId     = (int)($_POST['state_id'] ?? 0);
        $districtId  = (int)($_POST['district_id'] ?? 0);

        if (!$nationality) {
            $this->json(['success' => false, 'message' => 'Nationality is required.']);
        }
        if (!$countryId)  $this->json(['success' => false, 'message' => 'Country is required.']);
        if (!$stateId)    $this->json(['success' => false, 'message' => 'State is required.']);
        if (!$districtId) $this->json(['success' => false, 'message' => 'District is required.']);

        Athlete::updateProfile($this->athlete['id'], [
            'country_id'  => $countryId,
            'state_id'    => $stateId,
            'district_id' => $districtId,
            'nationality' => $nationality,
        ]);
        $this->json(['success' => true, 'message' => 'Location saved!']);
    }

    private function saveIdProofSection(): void
    {
        // First proof slot is locked to Aadhaar Card.
        $aadhaar = Athlete::getAadhaarProofType();
        if (!$aadhaar) {
            $this->json(['success' => false, 'message' => 'Aadhaar proof type missing in master data.']);
        }

        $number = trim($_POST['id_proof_number'] ?? '');
        if ($number === '') {
            $this->json(['success' => false, 'message' => 'Aadhaar number is required.']);
        }
        // Aadhaar is a 12-digit number; allow optional spaces.
        if (!preg_match('/^\d{4}\s?\d{4}\s?\d{4}$/', $number)) {
            $this->json(['success' => false, 'message' => 'Enter a valid 12-digit Aadhaar number.']);
        }

        $data = [
            'id_proof_type_id' => (int)$aadhaar['id'],
            'id_proof_number'  => preg_replace('/\s+/', '', $number),
        ];
        if (!empty($_FILES['id_proof_file']['name'])) {
            try {
                $data['id_proof_file'] = (new FileUpload())->upload($_FILES['id_proof_file'], 'athletes/idproofs');
            } catch (\RuntimeException $e) {
                $this->json(['success' => false, 'message' => $e->getMessage()]);
            }
        }
        Athlete::updateProfile($this->athlete['id'], $data);
        $this->json(['success' => true, 'message' => 'Aadhaar proof saved!']);
    }

    private function saveDobProofSection(): void
    {
        $allowed = array_column(Athlete::getDobProofTypes(), 'id');
        $allowed = array_map('intval', $allowed);
        $typeId  = (int)($_POST['dob_proof_type_id'] ?? 0);
        $number  = trim($_POST['dob_proof_number'] ?? '');

        // The whole section is optional — if the athlete leaves it blank, clear it.
        $hasAny = $typeId || $number !== '' || !empty($_FILES['dob_proof_file']['name']);
        if (!$hasAny) {
            Athlete::updateProfile($this->athlete['id'], [
                'dob_proof_type_id' => null,
                'dob_proof_number'  => null,
            ]);
            $this->json(['success' => true, 'message' => 'DOB proof cleared.']);
        }

        if (!$typeId || !in_array($typeId, $allowed, true)) {
            $this->json(['success' => false,
                'message' => 'Choose Driving Licence, Birth Certificate, School Certificate or Passport.']);
        }
        if ($number === '') {
            $this->json(['success' => false, 'message' => 'DOB proof number is required.']);
        }

        $data = [
            'dob_proof_type_id' => $typeId,
            'dob_proof_number'  => $number,
        ];
        if (!empty($_FILES['dob_proof_file']['name'])) {
            try {
                $data['dob_proof_file'] = (new FileUpload())->upload($_FILES['dob_proof_file'], 'athletes/idproofs');
            } catch (\RuntimeException $e) {
                $this->json(['success' => false, 'message' => $e->getMessage()]);
            }
        }
        Athlete::updateProfile($this->athlete['id'], $data);
        $this->json(['success' => true, 'message' => 'Date-of-Birth proof saved!']);
    }

    private function saveSportsSection(): void
    {
        $sports = [];
        foreach ($_POST['sports'] ?? [] as $sportId => $info) {
            if (!empty($info['selected'])) {
                $sports[(int)$sportId] = [
                    'sport_specific_id' => $info['sport_specific_id'] ?? null,
                    'licenses'          => $info['licenses'] ?? null,
                ];
            }
        }
        Athlete::syncSports($this->athlete['id'], $sports);
        $this->json(['success' => true, 'message' => 'Sports preferences saved!']);
    }

    // ── Submit Profile ────────────────────────────────────────────────────────

    public function submitProfile(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $a = Athlete::findByUserId(Auth::id());
        $required = ['name', 'date_of_birth', 'gender', 'mobile', 'address',
                     'country_id', 'state_id', 'district_id', 'nationality'];
        $missing = [];
        foreach ($required as $f) {
            if (empty($a[$f])) $missing[] = str_replace('_', ' ', $f);
        }
        if (empty($a['passport_photo'])) $missing[] = 'passport photo';

        // Aadhaar (the first ID proof slot) is mandatory.
        $aadhaar = Athlete::getAadhaarProofType();
        if (empty($a['id_proof_number'])
            || ($aadhaar && (int)($a['id_proof_type_id'] ?? 0) !== (int)$aadhaar['id'])) {
            $missing[] = 'Aadhaar number';
        }

        if ($missing) {
            $this->json(['success' => false,
                'message' => 'Please save all required sections first: ' . implode(', ', $missing) . '.']);
        }

        $wasComplete = !empty($a['profile_completed']);
        Athlete::updateProfile($a['id'], ['profile_completed' => 1]);

        // First-time profile completion: nudge the athlete via email so the
        // next steps (Find Events, Register) are obvious. Don't re-send if
        // the profile was already complete and they hit Submit again.
        if (!$wasComplete) {
            try {
                $user = User::findById((int)$a['user_id']);
                if (!empty($user['email'])) {
                    (new Mailer())->sendProfileCompleted($user['email'], $a['name']);
                }
            } catch (\Throwable $e) {
                error_log('[athlete/submitProfile] mail failed: ' . $e->getMessage());
            }
        }

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Profile submitted successfully!'];
        $this->json([
            'success'  => true,
            'message'  => 'Profile submitted successfully!',
            'redirect' => '/athlete/dashboard',
        ]);
    }

    // ── Grievances (per-event Q&A with the event admin) ──────────────────────

    /** GET /athlete/grievances — list all grievances the athlete has filed. */
    public function grievanceIndex(): void
    {
        $this->boot();
        $this->renderWith('app', 'athlete/grievances/index', [
            'athlete'    => $this->athlete,
            'grievances' => Grievance::forAthlete((int)$this->athlete['id']),
        ]);
    }

    /** GET /athlete/events/{id}/grievances — submit + view the athlete's own
     *  grievances scoped to one event. */
    public function eventGrievances(string $id): void
    {
        $id = (string)\hid_event_decode($id);
        $this->boot();
        $event = Event::findById((int)$id);
        if (!$event) $this->abort(404);
        $rows = Grievance::forEvent((int)$id);
        // Only show this athlete's own grievances on the athlete side.
        $own  = array_values(array_filter($rows, fn($r) => (int)$r['athlete_id'] === (int)$this->athlete['id']));
        $this->renderWith('app', 'athlete/grievances/event', [
            'athlete'    => $this->athlete,
            'event'      => $event,
            'grievances' => $own,
        ]);
    }

    /** POST /athlete/events/{id}/grievances — file a new grievance. */
    public function grievanceCreate(string $id): void
    {
        $id = (string)\hid_event_decode($id);
        $this->boot();
        $this->verifyCsrf();

        $event = Event::findById((int)$id);
        if (!$event) $this->abort(404);

        $subject = trim((string)($_POST['subject'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));
        if ($subject === '' || $message === '') {
            $this->redirect("/athlete/events/" . \hid_event((int)$id) . "/grievances",
                'Subject and message are required.', 'error');
        }

        $gid = Grievance::create([
            'event_id'   => (int)$id,
            'athlete_id' => (int)$this->athlete['id'],
            'subject'    => mb_substr($subject, 0, 255),
            'message'    => $message,
            'status'     => 'open',
        ]);

        $this->redirect("/athlete/grievances/" . $gid,
            'Grievance submitted. The event administrator will reply here.', 'success');
    }

    /** GET /athlete/grievances/{id} — thread view (athlete side). */
    public function grievanceShow(string $id): void
    {
        $this->boot();
        $g = Grievance::withContext((int)$id);
        if (!$g || (int)$g['athlete_id'] !== (int)$this->athlete['id']) $this->abort(404);
        $this->renderWith('app', 'athlete/grievances/show', [
            'athlete'   => $this->athlete,
            'grievance' => $g,
            'replies'   => Grievance::replies((int)$id),
        ]);
    }

    /** POST /athlete/grievances/{id}/reply — athlete posts a follow-up. */
    public function grievanceReply(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $g = Grievance::find((int)$id);
        if (!$g || (int)$g['athlete_id'] !== (int)$this->athlete['id']) $this->abort(404);

        $message = trim((string)($_POST['message'] ?? ''));
        if ($message === '') {
            $this->redirect("/athlete/grievances/{$id}", 'Reply message is required.', 'error');
        }

        Grievance::addReply([
            'grievance_id'   => (int)$id,
            'author_user_id' => Auth::id(),
            'author_role'    => 'athlete',
            'message'        => $message,
        ]);
        // If the admin had marked it resolved, an athlete follow-up reopens it.
        if (in_array($g['status'], ['resolved','closed'], true)) {
            Grievance::setStatus((int)$id, 'open');
        } else {
            Grievance::bumpUpdated((int)$id);
        }
        $this->redirect("/athlete/grievances/{$id}", 'Reply added.', 'success');
    }
}
