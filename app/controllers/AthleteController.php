<?php
namespace Controllers;

use Core\{Controller, Auth, FileUpload, Mailer, Razorpay};
use Models\{Athlete, Event, EventUnit, EventDocument, EventRegistration, EventRegistrationPayment, Schema, User, Grievance, TeamRegistration, TeamRegistrationPayment};

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
            'pwd_status'           => in_array(($_POST['pwd_status'] ?? ''), ['no','deaf','para'], true)
                                      ? $_POST['pwd_status'] : 'no',
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
        if (!$this->athlete['id_proof_file']  && empty($data['id_proof_file']))  $complete = false;
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
        // The Register/Edit panel needs to know whether this athlete has
        // already started a registration on this event.
        $myRegistration = EventRegistration::findHeader((int)$id, (int)$this->athlete['id']);
        $this->renderWith('app', 'athlete/events/detail', [
            'athlete'         => $this->athlete,
            'event'           => $event,
            'my_registration' => $myRegistration,
            'flash'           => $this->flash(),
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
        $sportItems   = $registration
            ? \Models\RegistrationSportItem::forRegistration((int)$registration['id'])
            : [];

        $this->renderWith('app', 'athlete/events/register', [
            'athlete'       => $this->athlete,
            'event'         => $event,
            'units'         => EventUnit::forEvent((int)$id),
            'documents'     => EventDocument::activeForEvent((int)$id),
            'registration'  => $registration,
            'items'         => $items,
            'payments'      => $payments,
            'sport_items'   => $sportItems,
            'event_items'   => \Models\EventSportItem::forEvent((int)$id),
            'flash'         => $this->flash(),
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
            // Auto-register the typed club into the event's Unit/Club master
            // so it becomes a first-class unit linked to the registration.
            // Reuse an existing unit if the name already exists (case-insensitive).
            $match = null;
            foreach (EventUnit::forEvent((int)$id) as $u) {
                if (strcasecmp(trim((string)$u['name']), $unitNameOther) === 0) { $match = $u; break; }
            }
            if ($match) {
                $unitId = (int)$match['id'];
            } else {
                $unitId = EventUnit::create([
                    'event_id' => (int)$id,
                    'name'     => mb_substr($unitNameOther, 0, 255),
                    'address'  => null,
                ]);
            }
            // Linked to a real unit now — clear the free-text fallback.
            $unitNameOther = '';
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
    /** POST /athlete/events/{id}/register/items/save — add or edit a Sports
     *  Items / Weapons declaration on this registration. */
    public function registerItemSave(string $id): void
    {
        $id = (string)\hid_event_decode($id);
        $this->boot();
        $this->verifyCsrf();

        $event = Event::findById((int)$id);
        if (!$event || $event['status'] !== 'active') $this->abort(404);
        $registration = EventRegistration::findHeader((int)$id, (int)$this->athlete['id']);
        // The Items panel sits ABOVE Save & Continue, so the athlete may
        // declare items before any other Step-1 field is saved. Auto-create
        // a draft registration here so the row to attach items to exists.
        if (!$registration) {
            $newId        = EventRegistration::createDraft((int)$id, (int)$this->athlete['id']);
            $registration = EventRegistration::findHeader((int)$id, (int)$this->athlete['id'])
                            ?? ['id' => $newId];
        }

        $rowId        = (int)($_POST['id']            ?? 0);
        $sportItemId  = (int)($_POST['sport_item_id'] ?? 0);
        $model        = trim($_POST['model']         ?? '');
        $serial       = trim($_POST['serial_number'] ?? '');
        if (!$sportItemId) $this->json(['success' => false, 'message' => 'Pick an item.']);

        // Verify the chosen item is on this event's allow-list.
        $allowed = \Models\EventSportItem::forEvent((int)$id);
        $ok = false;
        foreach ($allowed as $a) if ((int)$a['sport_item_id'] === $sportItemId) { $ok = true; break; }
        if (!$ok) $this->json(['success' => false, 'message' => 'That item is not allowed for this event.']);

        $payload = [
            'registration_id' => (int)$registration['id'],
            'sport_item_id'   => $sportItemId,
            'model'           => $model ?: null,
            'serial_number'   => $serial ?: null,
        ];
        try {
            if ($rowId) {
                $existing = \Models\RegistrationSportItem::find($rowId);
                if (!$existing || (int)$existing['registration_id'] !== (int)$registration['id']) {
                    $this->json(['success' => false, 'message' => 'Row not found.']);
                }
                \Models\RegistrationSportItem::updateRow($rowId, $payload);
            } else {
                \Models\RegistrationSportItem::create($payload);
            }
            $this->json([
                'success' => true,
                'message' => 'Item saved.',
                'list'    => \Models\RegistrationSportItem::forRegistration((int)$registration['id']),
            ]);
        } catch (\Throwable $e) {
            error_log('[athlete/registerItemSave] ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
        }
    }

    /** POST /athlete/events/{id}/register/items/delete — remove a row. */
    public function registerItemDelete(string $id): void
    {
        $id = (string)\hid_event_decode($id);
        $this->boot();
        $this->verifyCsrf();

        $registration = EventRegistration::findHeader((int)$id, (int)$this->athlete['id']);
        if (!$registration) $this->json(['success' => false, 'message' => 'Registration not found.']);
        $rowId = (int)($_POST['id'] ?? 0);
        $existing = \Models\RegistrationSportItem::find($rowId);
        if (!$existing || (int)$existing['registration_id'] !== (int)$registration['id']) {
            $this->json(['success' => false, 'message' => 'Row not found.']);
        }
        \Models\RegistrationSportItem::deleteRow($rowId);
        $this->json([
            'success' => true,
            'message' => 'Item removed.',
            'list'    => \Models\RegistrationSportItem::forRegistration((int)$registration['id']),
        ]);
    }

    /**
     * POST /athlete/events/{id}/register/payments-refresh
     * Athlete-facing "Refresh" button on the Step-5 transactions panel.
     * Best-effort reconciles pending ePayment rows by calling Razorpay
     * directly (mirrors the webhook + cron logic), then returns the
     * up-to-date payments list. Useful when the browser closed mid
     * payment and the athlete wants immediate confirmation.
     */
    public function registerPaymentsRefresh(string $id): void
    {
        $id = (string)\hid_event_decode($id);
        $this->boot();
        $this->verifyCsrf();

        $registration = EventRegistration::findHeader((int)$id, (int)$this->athlete['id']);
        if (!$registration) {
            $this->json(['success' => false, 'message' => 'No registration to refresh.'], 404);
        }

        $pending = EventRegistrationPayment::pendingEpaymentsForRegistration((int)$registration['id']);
        $reconciled = 0;
        foreach ($pending as $row) {
            $orderId = (string)($row['razorpay_order_id'] ?? '');
            if ($orderId === '') continue;
            try {
                $payments = (new \Core\Razorpay())->fetchOrderPayments($orderId);
                $outcome  = \Services\PaymentApprovalService::applyOrderPayments(
                    (int)$row['id'], $payments, 'athlete-refresh'
                );
                EventRegistrationPayment::updateRow((int)$row['id'], [
                    'reconciled_at' => date('Y-m-d H:i:s'),
                ]);
                if ($outcome === 'paid' || $outcome === 'failed') $reconciled++;
            } catch (\Throwable $e) {
                error_log('[athlete/paymentsRefresh] ' . $e->getMessage());
            }
        }

        EventRegistrationPayment::recomputeRegistrationPaymentStatus((int)$registration['id']);
        $this->json([
            'success'    => true,
            'reconciled' => $reconciled,
            'pending'    => count($pending),
            'payments'   => EventRegistrationPayment::forRegistration((int)$registration['id']),
        ]);
    }

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
            // Always store the (untrusted) payment_id for audit.
            EventRegistrationPayment::updateRow((int)$row['id'], [
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature'  => $signature,
            ]);
            \Services\PaymentApprovalService::markFailed(
                (int)$row['id'], 'signature mismatch', 'browser'
            );
            error_log('[athlete/payVerify] HMAC mismatch on order ' . $orderId);
            $this->json(['success' => false, 'message' => 'Payment signature verification failed.'], 400);
        }

        // Status-guarded so a webhook that arrived first is honoured here too.
        \Services\PaymentApprovalService::markPaid(
            (int)$row['id'], $paymentId, $signature, 'browser'
        );

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
        try { Schema::ensureTeamEntry(); } catch (\Throwable $e) {}
        $this->renderWith('app', 'athlete/my-registrations', [
            'athlete'            => $this->athlete,
            'registrations'      => Event::getAthleteRegistrations($this->athlete['id']),
            'team_registrations' => TeamRegistration::forAthlete((int)$this->athlete['id']),
            'flash'              => $this->flash(),
        ]);
    }

    // ── Team Entry ────────────────────────────────────────────────────────────

    /**
     * GET /athlete/team-entry — Step 1 picker (no team yet).
     * Lists events where the athlete has an approved registration and the
     * organiser has enabled team_entry. Athlete picks the event + a team-
     * eligible sport event + types a team name to create a draft team.
     */
    public function teamEntryIndex(): void
    {
        $this->boot();
        try { Schema::ensureTeamEntry(); } catch (\Throwable $e) {}
        if (!$this->athlete['profile_completed']) {
            $this->redirect('/athlete/profile', 'Please complete your profile before creating a team entry.', 'warning');
        }
        $eligible = $this->eligibleEventsForTeamEntry();
        $this->renderWith('app', 'athlete/team-entry/index', [
            'athlete'         => $this->athlete,
            'eligible_events' => $eligible,
            'team_registrations' => TeamRegistration::forAthlete((int)$this->athlete['id']),
            'flash'           => $this->flash(),
        ]);
    }

    /**
     * Events where this athlete is APPROVED, with team_entry_enabled=1.
     * Each row also carries the unit_id the athlete chose on their own
     * registration (used as the team's unit and the validation key when
     * adding members).
     */
    private function eligibleEventsForTeamEntry(): array
    {
        return Event::rowsRaw(
            "SELECT e.id, e.name, e.location, e.event_date_from, e.event_date_to,
                    e.team_entry_window_open,
                    i.name AS institution_name,
                    er.id  AS my_registration_id,
                    er.unit_id AS my_unit_id,
                    eu.name AS my_unit_name,
                    er.competitor_number
               FROM event_registrations er
               JOIN events e        ON e.id = er.event_id
               JOIN institutions i  ON i.id = e.institution_id
          LEFT JOIN event_units eu  ON eu.id = er.unit_id
              WHERE er.athlete_id = ?
                AND er.unit_id IS NOT NULL
                AND e.team_entry_enabled = 1
                AND (e.team_entry_methods IS NULL OR e.team_entry_methods = ''
                     OR FIND_IN_SET('athlete', e.team_entry_methods))
                AND e.status = 'active'
              ORDER BY e.event_date_from DESC",
            [(int)$this->athlete['id']]
        );
    }

    /** Team-eligible sport events (team_entry_fee set) for an event. */
    private function teamEligibleSportEvents(int $eventId): array
    {
        return Event::rowsRaw(
            "SELECT es.id, es.event_code, es.entry_fee, es.team_entry_fee,
                    s.name AS sport_name, se.name AS sport_event_name,
                    sc.name AS sport_event_category,
                    ac.name AS sport_event_age_category,
                    se.gender AS sport_event_gender
               FROM event_sports es
               JOIN sports s             ON s.id = es.sport_id
          LEFT JOIN sport_events se      ON se.id = es.sport_event_id
          LEFT JOIN sport_categories sc  ON sc.id = se.category_id
          LEFT JOIN age_categories ac    ON ac.id = se.age_category_id
              WHERE es.event_id = ?
                AND es.team_entry_fee IS NOT NULL
              ORDER BY s.name, se.name",
            [$eventId]
        );
    }

    /** GET /athlete/team-entry/sport-events?event_id=X — AJAX dropdown loader. */
    public function teamEntrySportEvents(): void
    {
        $this->boot();
        try { Schema::ensureTeamEntry(); } catch (\Throwable $e) {}
        $eventId = (int)($_GET['event_id'] ?? 0);
        $eligible = $this->eligibleEventsForTeamEntry();
        $ok = false;
        foreach ($eligible as $row) if ((int)$row['id'] === $eventId) { $ok = true; break; }
        if (!$ok) $this->json(['success' => false, 'message' => 'Event not eligible.']);
        $this->json([
            'success' => true,
            'sport_events' => $this->teamEligibleSportEvents($eventId),
        ]);
    }

    /**
     * POST /athlete/team-entry/create — Step 1 submission. Creates a fresh
     * draft team (or 422s if a draft for this event+captain already exists).
     */
    public function teamEntryCreate(): void
    {
        $this->boot();
        $this->verifyCsrf();
        try { Schema::ensureTeamEntry(); } catch (\Throwable $e) {}

        $eventId       = (int)($_POST['event_id'] ?? 0);
        $sportEventId  = (int)($_POST['event_sport_id'] ?? 0);
        $teamName      = trim((string)($_POST['team_name'] ?? ''));
        if ($eventId <= 0 || $sportEventId <= 0 || $teamName === '') {
            $this->json(['success' => false, 'message' => 'Event, sport event and team name are required.']);
        }
        if (mb_strlen($teamName) > 255) {
            $this->json(['success' => false, 'message' => 'Team name must be 255 characters or fewer.']);
        }

        // Validate eligibility.
        $eligible = $this->eligibleEventsForTeamEntry();
        $myRow = null;
        foreach ($eligible as $row) if ((int)$row['id'] === $eventId) { $myRow = $row; break; }
        if (!$myRow) {
            $this->json(['success' => false,
                'message' => 'You\'re not eligible to start a team entry for this event.']);
        }
        $eventRow = Event::findById($eventId);
        if (!\eventTeamEntryWindowOpen($eventRow)) {
            $this->json(['success' => false,
                'message' => 'Team entry submissions are closed for this event by the event administrator.']);
        }
        $sportEvents = $this->teamEligibleSportEvents($eventId);
        $sportRow = null;
        foreach ($sportEvents as $se) if ((int)$se['id'] === $sportEventId) { $sportRow = $se; break; }
        if (!$sportRow) {
            $this->json(['success' => false, 'message' => 'Selected sport event is not team-eligible.']);
        }

        // Block a second draft for the same captain on the same event.
        $existing = TeamRegistration::findDraftForCaptain($eventId, (int)$this->athlete['id']);
        if ($existing) {
            $this->json([
                'success'  => false,
                'message'  => 'You already have a draft team for this event — finish it before creating another.',
                'redirect' => '/athlete/team-entry/' . (int)$existing['id'],
            ]);
        }

        $teamId = TeamRegistration::createDraft(
            $eventId, (int)$this->athlete['id'], $teamName,
            $myRow['my_unit_id'] ? (int)$myRow['my_unit_id'] : null
        );
        TeamRegistration::updateRow($teamId, [
            'event_sport_id' => $sportEventId,
            'total_amount'   => (float)$sportRow['team_entry_fee'],
        ]);

        $this->json([
            'success'  => true,
            'message'  => 'Team created. Add up to 3 members.',
            'redirect' => '/athlete/team-entry/' . $teamId,
        ]);
    }

    /** GET /athlete/team-entry/{id} — wizard for an existing team draft. */
    public function teamEntryShow(string $id): void
    {
        $this->boot();
        try { Schema::ensureTeamEntry(); } catch (\Throwable $e) {}
        $team = TeamRegistration::withContext((int)$id);
        if (!$team || (int)$team['athlete_id'] !== (int)$this->athlete['id']) $this->abort(404);
        $event = Event::findById((int)$team['event_id']);
        if (!$event) $this->abort(404);

        $this->renderWith('app', 'athlete/team-entry/wizard', [
            'athlete'   => $this->athlete,
            'event'     => $event,
            'team'      => $team,
            'members'   => TeamRegistration::members((int)$team['id']),
            'payments'  => TeamRegistrationPayment::forTeam((int)$team['id']),
            'pay_totals'=> TeamRegistrationPayment::totals((int)$team['id']),
            'flash'     => $this->flash(),
        ]);
    }

    /** POST /athlete/team-entry/{id}/member-validate — lookup competitor #. */
    public function teamEntryMemberValidate(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $team = TeamRegistration::findById((int)$id);
        if (!$team || (int)$team['athlete_id'] !== (int)$this->athlete['id']) $this->abort(404);
        if (!TeamRegistration::isEditable($team)) {
            $this->json(['success' => false, 'message' => 'Team is locked for review.']);
        }
        $num = (int)($_POST['competitor_number'] ?? 0);
        if ($num <= 0) $this->json(['success' => false, 'message' => 'Enter a valid competitor number.']);
        $result = TeamRegistration::lookupCompetitor(
            (int)$team['event_id'], $num, (int)$team['id'],
            $team['unit_id'] ? (int)$team['unit_id'] : null
        );
        if (!$result['ok']) {
            $this->json(['success' => false, 'message' => $result['error']]);
        }
        $this->json([
            'success' => true,
            'athlete_id'      => $result['athlete_id'],
            'registration_id' => $result['registration_id'],
            'athlete_name'    => $result['athlete_name'],
            'athlete_mobile'  => $result['athlete_mobile'],
            'unit_name'       => $result['unit_name'],
        ]);
    }

    /** POST /athlete/team-entry/{id}/member-add — append a validated member. */
    public function teamEntryMemberAdd(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $team = TeamRegistration::findById((int)$id);
        if (!$team || (int)$team['athlete_id'] !== (int)$this->athlete['id']) $this->abort(404);
        if (!TeamRegistration::isEditable($team)) {
            $this->json(['success' => false, 'message' => 'Team is locked for review.']);
        }
        if (TeamRegistration::memberCount((int)$team['id']) >= 3) {
            $this->json(['success' => false, 'message' => 'A team can have at most 3 members.']);
        }
        $num = (int)($_POST['competitor_number'] ?? 0);
        if ($num <= 0) $this->json(['success' => false, 'message' => 'Enter a valid competitor number.']);

        $result = TeamRegistration::lookupCompetitor(
            (int)$team['event_id'], $num, (int)$team['id'],
            $team['unit_id'] ? (int)$team['unit_id'] : null
        );
        if (!$result['ok']) {
            $this->json(['success' => false, 'message' => $result['error']]);
        }

        TeamRegistration::addMember([
            'team_registration_id' => (int)$team['id'],
            'athlete_id'           => $result['athlete_id'],
            'registration_id'      => $result['registration_id'],
            'competitor_number'    => $num,
            'position'             => TeamRegistration::memberCount((int)$team['id']) + 1,
        ]);

        $this->json([
            'success' => true,
            'message' => 'Member added.',
            'members' => TeamRegistration::members((int)$team['id']),
        ]);
    }

    /** POST /athlete/team-entry/{id}/member-remove */
    public function teamEntryMemberRemove(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $team = TeamRegistration::findById((int)$id);
        if (!$team || (int)$team['athlete_id'] !== (int)$this->athlete['id']) $this->abort(404);
        if (!TeamRegistration::isEditable($team)) {
            $this->json(['success' => false, 'message' => 'Team is locked for review.']);
        }
        $memberId = (int)($_POST['member_id'] ?? 0);
        $members = TeamRegistration::members((int)$team['id']);
        $found = false;
        foreach ($members as $m) if ((int)$m['id'] === $memberId) { $found = true; break; }
        if (!$found) $this->json(['success' => false, 'message' => 'Member not found.']);
        TeamRegistration::removeMember($memberId);
        $this->json([
            'success' => true,
            'message' => 'Member removed.',
            'members' => TeamRegistration::members((int)$team['id']),
        ]);
    }

    /** POST /athlete/team-entry/{id}/payment-mode */
    public function teamEntryPaymentMode(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $team = TeamRegistration::findById((int)$id);
        if (!$team || (int)$team['athlete_id'] !== (int)$this->athlete['id']) $this->abort(404);
        $event = Event::findById((int)$team['event_id']);
        $allowed = $event['payment_modes'] ?? [];
        $mode = $_POST['payment_mode'] ?? '';
        if (!in_array($mode, $allowed, true)) {
            $this->json(['success' => false, 'message' => 'Choose a valid payment mode.']);
        }
        TeamRegistration::updateRow((int)$team['id'], ['payment_mode' => $mode]);
        $this->json(['success' => true, 'message' => 'Payment mode saved.']);
    }

    /** POST /athlete/team-entry/{id}/payment — add one manual transaction. */
    public function teamEntryAddPayment(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $team = TeamRegistration::findById((int)$id);
        if (!$team || (int)$team['athlete_id'] !== (int)$this->athlete['id']) $this->abort(404);
        if (!TeamRegistration::isEditable($team)) {
            $this->json(['success' => false, 'message' => 'Team is locked for review.']);
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
            $proof = (new FileUpload())->upload($_FILES['transaction_proof'], 'team-registrations');
        } catch (\RuntimeException $e) {
            $this->json(['success' => false, 'message' => 'Proof upload failed: ' . $e->getMessage()]);
        }
        TeamRegistrationPayment::create([
            'team_registration_id' => (int)$team['id'],
            'event_id'             => (int)$team['event_id'],
            'transaction_date'     => $txDate,
            'transaction_number'   => $txNum,
            'amount'               => $amount,
            'proof_file'           => $proof,
            'status'               => 'pending',
            'payment_method'       => 'manual',
        ]);
        TeamRegistration::updateRow((int)$team['id'], ['payment_mode' => 'manual']);
        TeamRegistrationPayment::recomputeTeamPaymentStatus((int)$team['id']);
        $this->json([
            'success'  => true,
            'message'  => 'Transaction added.',
            'payments' => TeamRegistrationPayment::forTeam((int)$team['id']),
        ]);
    }

    /** POST /athlete/team-entry/{id}/payment-remove */
    public function teamEntryRemovePayment(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $team = TeamRegistration::findById((int)$id);
        if (!$team || (int)$team['athlete_id'] !== (int)$this->athlete['id']) $this->abort(404);
        $payId = (int)($_POST['payment_id'] ?? 0);
        $p = TeamRegistrationPayment::find($payId);
        if (!$p || (int)$p['team_registration_id'] !== (int)$team['id']) {
            $this->json(['success' => false, 'message' => 'Transaction not found.']);
        }
        if ($p['status'] === 'approved') {
            $this->json(['success' => false, 'message' => 'Approved transactions cannot be removed.']);
        }
        TeamRegistrationPayment::deleteRow($payId);
        TeamRegistrationPayment::recomputeTeamPaymentStatus((int)$team['id']);
        $this->json([
            'success'  => true,
            'message'  => 'Transaction removed.',
            'payments' => TeamRegistrationPayment::forTeam((int)$team['id']),
        ]);
    }

    /** POST /athlete/team-entry/{id}/submit */
    public function teamEntrySubmit(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $team = TeamRegistration::findById((int)$id);
        if (!$team || (int)$team['athlete_id'] !== (int)$this->athlete['id']) $this->abort(404);
        if (!TeamRegistration::isEditable($team)) {
            $this->json(['success' => false, 'message' => 'Team has already been submitted.']);
        }
        $eventRow = Event::findById((int)$team['event_id']);
        if (!\eventTeamEntryWindowOpen($eventRow)) {
            $this->json(['success' => false,
                'message' => 'Team entry submissions are closed for this event by the event administrator.']);
        }
        $count = TeamRegistration::memberCount((int)$team['id']);
        if ($count < 3) {
            $this->json(['success' => false,
                'message' => 'A team must have exactly 3 members before submission. Currently: ' . $count . '.']);
        }
        $event = Event::findById((int)$team['event_id']);
        $allowed = $event['payment_modes'] ?? [];
        $mode = $team['payment_mode'] ?? '';
        if (!in_array($mode, $allowed, true)) {
            $this->json(['success' => false, 'message' => 'Pick a payment mode before submitting.']);
        }
        $payments = TeamRegistrationPayment::forTeam((int)$team['id']);
        if ($mode === 'manual' && !$payments) {
            $this->json(['success' => false,
                'message' => 'Add at least one payment transaction before submitting.']);
        }
        // Total transaction amount must equal team fee.
        $totals = TeamRegistrationPayment::totals((int)$team['id']);
        $required = (float)($team['total_amount'] ?? 0);
        if (round((float)$totals['submitted_amount'], 2) + 0.001 < $required) {
            $this->json(['success' => false,
                'message' => 'Submitted transaction total (₹' . number_format((float)$totals['submitted_amount'], 2)
                           . ') is less than the team fee (₹' . number_format($required, 2) . ').']);
        }
        TeamRegistration::updateRow((int)$team['id'], [
            'status'              => 'pending',
            'admin_review_status' => 'pending',
            'submitted_at'        => date('Y-m-d H:i:s'),
        ]);
        $msg = 'Team entry submitted! The event administrator will review your application and payment.';
        $_SESSION['flash'] = ['type' => 'success', 'message' => $msg];
        $this->json(['success' => true, 'message' => $msg, 'redirect' => '/athlete/my-registrations']);
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
        // Athlete, institution admin AND unit user can all view a card —
        // the per-role checks below limit each to records they own /
        // manage. Redirect unauthenticated requests to the generic
        // login (unit users without a session would be sent here too).
        if (!Auth::check() && !Auth::unitUserCheck()) $this->redirect('/login');
        $reg = EventRegistration::findById((int)$id);
        if (!$reg) $this->abort(404);

        $event       = Event::findById((int)$reg['event_id']);
        if (!$event) $this->abort(404);

        $allowed = false; $athlete = null;
        if (Auth::check() && Auth::is('athlete')) {
            $athlete = Athlete::findByUserId(Auth::id());
            if ($athlete && (int)$reg['athlete_id'] === (int)$athlete['id']) $allowed = true;
        }
        if (Auth::check() && Auth::is('institution_admin')) {
            $inst = \Models\Institution::findByUserId(Auth::id());
            if ($inst && (int)$event['institution_id'] === (int)$inst['id']) {
                $allowed = true;
                $athlete = Athlete::findById((int)$reg['athlete_id']);
            }
        }
        if (!$allowed && Auth::unitUserCheck()) {
            $session = Auth::unitUser();
            $u = \Models\UnitUser::findById((int)$session['id']);
            if ($u && ($u['status'] ?? '') === 'active'
                && (int)$u['event_id'] === (int)$reg['event_id']) {
                $unitIds = \Models\UnitUser::assignmentIds((int)$u['id']);
                if (in_array((int)($reg['unit_id'] ?? 0), $unitIds, true)) {
                    $allowed = true;
                    $athlete = Athlete::findById((int)$reg['athlete_id']);
                }
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
        $ctx         = EventRegistration::competitorCardContext((int)$id);
        $items             = $ctx['items'];
        $catRows           = $ctx['category_rows'];
        $ageCategoryLabel  = $ctx['age_category_label'];

        // Render outside the regular layout so the card is print-friendly.
        $data = [
            'athlete'            => $athlete,
            'event'              => $event,
            'institution'        => $institution,
            'registration'       => $reg,
            'items'              => $items,
            'category_rows'      => $catRows,
            'age_category_label' => $ageCategoryLabel,
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
            'payments'     => EventRegistrationPayment::forRegistration((int)$id),
            'pay_totals'   => EventRegistrationPayment::totals((int)$id),
            'sport_items'  => \Models\RegistrationSportItem::forRegistration((int)$id),
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

        $pwd = strtolower(trim((string)($_POST['pwd_status'] ?? '')));
        if (!in_array($pwd, ['no', 'deaf', 'para'], true)) {
            $this->json(['success' => false,
                'message' => 'Please select a Person with Disability (PwD) status — No, Deaf, or Para.']);
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
            'pwd_status'            => $pwd,
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
        } elseif (empty($this->athlete['id_proof_file'])) {
            $this->json(['success' => false,
                'message' => 'Aadhaar document upload is mandatory.']);
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
        if (empty($a['id_proof_file'])) {
            $missing[] = 'Aadhaar document upload';
        }
        if (empty($a['pwd_status'])) {
            $missing[] = 'Person with Disability (PwD) status';
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
