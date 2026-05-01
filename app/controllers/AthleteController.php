<?php
namespace Controllers;

use Core\{Controller, Auth, FileUpload};
use Models\{Athlete, Event, EventUnit, EventRegistration, Schema};

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
        $this->renderWith('app', 'dashboard/athlete', [
            'athlete'       => $this->athlete,
            'registrations' => $registrations,
            'flash'         => $this->flash(),
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
        $this->boot();
        $event = Event::findById((int)$id);
        if (!$event || $event['status'] !== 'approved') $this->abort(404);
        $this->renderWith('app', 'athlete/events/detail', [
            'athlete' => $this->athlete,
            'event'   => $event,
            'flash'   => $this->flash(),
        ]);
    }

    public function registerForm(string $id): void
    {
        $this->boot();
        try { Schema::ensureSportHierarchy(); } catch (\Throwable $e) {
            error_log('[athlete/register/ensureSchema] ' . $e->getMessage());
        }
        if (!$this->athlete['profile_completed']) {
            $this->redirect('/athlete/profile', 'Please complete your profile before registering for events.', 'warning');
        }
        $event = Event::findById((int)$id);
        if (!$event || $event['status'] !== 'approved') $this->abort(404);

        $registration = EventRegistration::findHeader((int)$id, (int)$this->athlete['id']);
        $items        = $registration ? EventRegistration::items((int)$registration['id']) : [];

        $this->renderWith('app', 'athlete/events/register', [
            'athlete'      => $this->athlete,
            'event'        => $event,
            'units'        => EventUnit::forEvent((int)$id),
            'registration' => $registration,
            'items'        => $items,
            'flash'        => $this->flash(),
        ]);
    }

    public function registerSave(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        try { Schema::ensureSportHierarchy(); } catch (\Throwable $e) {}

        $event = Event::findById((int)$id);
        if (!$event || $event['status'] !== 'approved') $this->abort(404);

        $unitId = (int)($_POST['unit_id'] ?? 0);
        if (!$unitId) $this->json(['success' => false, 'message' => 'Please select a Unit / Club / Institution.']);
        $unit = EventUnit::find($unitId);
        if (!$unit || (int)$unit['event_id'] !== (int)$id) {
            $this->json(['success' => false, 'message' => 'Invalid unit for this event.']);
        }

        $eventSportIds = array_map('intval', $_POST['event_sport_ids'] ?? []);
        $eventSportIds = array_values(array_filter($eventSportIds));
        if (!$eventSportIds) {
            $this->json(['success' => false, 'message' => 'Pick at least one sport event.']);
        }
        // Make sure each id actually belongs to this event.
        $allowed = array_map('intval', array_column(Event::getSports((int)$id), 'id'));
        foreach ($eventSportIds as $esId) {
            if (!in_array($esId, $allowed, true)) {
                $this->json(['success' => false, 'message' => 'One or more selections are not part of this event.']);
            }
        }

        $registration = EventRegistration::findHeader((int)$id, (int)$this->athlete['id']);
        $regId = $registration['id'] ?? null;
        if (!$regId) {
            $regId = EventRegistration::createDraft((int)$id, (int)$this->athlete['id']);
        }

        // NOC handling.
        $nocReq = $event['noc_required'] ?? 'optional';
        $header = ['unit_id' => $unitId];
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

    public function registerSubmit(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();

        $event = Event::findById((int)$id);
        if (!$event || $event['status'] !== 'approved') $this->abort(404);

        $registration = EventRegistration::findHeader((int)$id, (int)$this->athlete['id']);
        if (!$registration || empty($registration['unit_id'])) {
            $this->json(['success' => false, 'message' => 'Please save the registration details first.']);
        }
        $items = EventRegistration::items((int)$registration['id']);
        if (!$items) {
            $this->json(['success' => false, 'message' => 'No sport events selected.']);
        }

        $allowedModes = $event['payment_modes'] ?? [];
        $mode = $_POST['payment_mode'] ?? '';
        if (!in_array($mode, $allowedModes, true)) {
            $this->json(['success' => false, 'message' => 'Choose a valid payment mode for this event.']);
        }

        $update = ['payment_mode' => $mode];

        if ($mode === 'manual') {
            $txDate = $_POST['transaction_date'] ?? '';
            $txNum  = trim($_POST['transaction_number'] ?? '');
            $amount = (float)($_POST['transaction_amount'] ?? 0);
            if (!$txDate || !$txNum || $amount <= 0) {
                $this->json(['success' => false, 'message' => 'Transaction date, number and amount are required.']);
            }
            if (empty($_FILES['transaction_proof']['name'])) {
                $this->json(['success' => false, 'message' => 'Transaction proof file is mandatory for manual payment.']);
            }
            try {
                $proof = (new FileUpload())->upload($_FILES['transaction_proof'], 'registrations');
            } catch (\RuntimeException $e) {
                $this->json(['success' => false, 'message' => 'Proof upload failed: ' . $e->getMessage()]);
            }
            $update['transaction_date']   = $txDate;
            $update['transaction_number'] = $txNum;
            $update['payment_amount']     = $amount;
            $update['transaction_proof']  = $proof;
            $update['payment_status']     = 'pending';   // institution will verify and mark paid
            $update['status']             = 'pending';
            $message  = 'Registration submitted. The institution will verify your payment shortly.';
            $redirect = '/athlete/my-registrations';
        } else {
            // Online — placeholder summary; real gateway integration comes later.
            $update['payment_status'] = 'pending';
            $update['status']         = 'pending';
            $message  = 'Online payment summary saved. Please complete payment to confirm.';
            $redirect = '/athlete/my-registrations';
        }

        EventRegistration::updateHeader((int)$registration['id'], $update);

        $_SESSION['flash'] = ['type' => 'success', 'message' => $message];
        $this->json(['success' => true, 'message' => $message, 'redirect' => $redirect]);
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

    // ── AJAX Profile Section Save ────────────────────────────────────────────

    public function ajaxSave(): void
    {
        $this->boot();
        $this->verifyCsrf();

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
        if (!$nationality) {
            $this->json(['success' => false, 'message' => 'Nationality is required.']);
        }
        Athlete::updateProfile($this->athlete['id'], [
            'country_id'  => (int)($_POST['country_id'] ?? 1),
            'state_id'    => (int)($_POST['state_id'] ?? 0) ?: null,
            'district_id' => (int)($_POST['district_id'] ?? 0) ?: null,
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
        $required = ['name', 'date_of_birth', 'gender', 'mobile', 'address', 'nationality'];
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

        Athlete::updateProfile($a['id'], ['profile_completed' => 1]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Profile submitted successfully!'];
        $this->json([
            'success'  => true,
            'message'  => 'Profile submitted successfully!',
            'redirect' => '/athlete/dashboard',
        ]);
    }
}
