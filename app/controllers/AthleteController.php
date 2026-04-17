<?php
namespace Controllers;

use Core\{Controller, Auth, FileUpload};
use Models\{Athlete, Event};

class AthleteController extends Controller
{
    private array $athlete;

    private function boot(): void
    {
        $this->requireAuth('athlete');
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
            'athlete'        => $this->athlete,
            'sports'         => Athlete::getAllSports(),
            'athlete_sports' => Athlete::getSports($this->athlete['id']),
            'id_proofs'      => Athlete::getAllIdProofTypes(),
            'countries'      => Athlete::getCountries(),
            'states'         => Athlete::getStatesByCountry((int)($this->athlete['country_id'] ?? 1)),
            'districts'      => $this->athlete['state_id'] ? Athlete::getDistrictsByState((int)$this->athlete['state_id']) : [],
            'flash'          => $this->flash(),
            'errors'         => $this->errors(),
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

    public function registerForEvent(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();

        if (!$this->athlete['profile_completed']) {
            $this->redirect('/athlete/profile', 'Please complete your profile before registering for events.', 'warning');
        }

        $event = Event::findById((int)$id);
        if (!$event || $event['status'] !== 'approved') $this->abort(404);

        $sportId     = (int)($_POST['sport_id'] ?? 0);
        $paymentMode = $_POST['payment_mode'] ?? '';

        if (Event::isAthleteRegistered((int)$id, $this->athlete['id'], $sportId)) {
            $this->redirect("/athlete/events/{$id}", 'You are already registered for this sport in this event.', 'warning');
        }

        Event::registerAthlete([
            'event_id'   => (int)$id,
            'athlete_id' => $this->athlete['id'],
            'sport_id'   => $sportId,
            'payment_mode'  => $paymentMode,
            'payment_status'=> 'pending',
        ]);

        $this->redirect('/athlete/my-registrations', 'Successfully registered for the event!');
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
}
