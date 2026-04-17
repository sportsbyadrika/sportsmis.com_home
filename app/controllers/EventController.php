<?php
namespace Controllers;

use Core\{Controller, Auth, FileUpload};
use Models\{Institution, Event, Athlete};

class EventController extends Controller
{
    private array $institution;

    private function boot(): void
    {
        $this->requireAuth('institution_admin');
        $inst = Institution::findByUserId(Auth::id());
        if (!$inst) $this->redirect('/login', 'Institution not found.', 'error');
        if (!$inst['profile_completed']) {
            $this->redirect('/institution/profile', 'Please complete your institution profile first.', 'warning');
        }
        $this->institution = $inst;
    }

    public function institutionIndex(): void
    {
        $this->boot();
        $this->renderWith('app', 'institution/events/index', [
            'institution' => $this->institution,
            'events'      => Event::getByInstitution($this->institution['id']),
            'flash'       => $this->flash(),
        ]);
    }

    public function createForm(): void
    {
        $this->boot();
        $this->renderWith('app', 'institution/events/create', [
            'institution' => $this->institution,
            'sports'      => Athlete::getAllSports(),
            'flash'       => $this->flash(),
            'errors'      => $this->errors(),
        ]);
    }

    public function create(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $errors = $this->validate([
            'name'            => 'required|max:255',
            'location'        => 'required',
            'reg_date_from'   => 'required',
            'reg_date_to'     => 'required',
            'event_date_from' => 'required',
            'event_date_to'   => 'required',
            'contact_name'    => 'required|max:255',
            'contact_mobile'  => 'required|mobile',
            'contact_email'   => 'required|email',
        ]);

        $paymentModes = $_POST['payment_modes'] ?? [];
        if (empty($paymentModes)) $errors['payment_modes'][] = 'Select at least one payment mode.';

        $data = [
            'institution_id'     => $this->institution['id'],
            'name'               => trim($_POST['name']),
            'location'           => trim($_POST['location']),
            'reg_date_from'      => $_POST['reg_date_from'],
            'reg_date_to'        => $_POST['reg_date_to'],
            'event_date_from'    => $_POST['event_date_from'],
            'event_date_to'      => $_POST['event_date_to'],
            'latitude'           => $_POST['latitude']  ?: null,
            'longitude'          => $_POST['longitude'] ?: null,
            'bank_details'       => trim($_POST['bank_details'] ?? ''),
            'contact_name'       => trim($_POST['contact_name']),
            'contact_designation'=> trim($_POST['contact_designation'] ?? ''),
            'contact_mobile'     => trim($_POST['contact_mobile']),
            'contact_email'      => strtolower(trim($_POST['contact_email'])),
            'status'             => 'pending_approval',
        ];

        if (!empty($_FILES['logo']['name'])) {
            try {
                $data['logo'] = (new FileUpload())->upload($_FILES['logo'], 'events', true);
            } catch (\RuntimeException $e) {
                $errors['logo'][] = $e->getMessage();
            }
        }

        if (!empty($_FILES['bank_qr_code']['name'])) {
            try {
                $data['bank_qr_code'] = (new FileUpload())->upload($_FILES['bank_qr_code'], 'events', true);
            } catch (\RuntimeException $e) {
                $errors['bank_qr_code'][] = $e->getMessage();
            }
        }

        if ($errors) {
            $_SESSION['errors'] = $errors;
            $this->redirect('/institution/events/create');
        }

        // Parse sports: sports[sport_id][entry_fee] and sports[sport_id][category]
        $sports = [];
        foreach ($_POST['sports'] ?? [] as $sportId => $info) {
            if (!empty($info['selected'])) {
                $sports[(int)$sportId] = [
                    'category'  => $info['category'] ?? null,
                    'entry_fee' => (float)($info['entry_fee'] ?? 0),
                ];
            }
        }

        Event::create($data, $paymentModes, $sports);
        $this->redirect('/institution/events', 'Event submitted for approval!');
    }

    public function editForm(string $id): void
    {
        $this->boot();
        $event = Event::findById((int)$id);
        if (!$event || $event['institution_id'] != $this->institution['id']) $this->abort(404);
        if (in_array($event['status'], ['approved', 'completed'])) {
            $this->redirect('/institution/events', 'Approved events cannot be edited.', 'warning');
        }

        $this->renderWith('app', 'institution/events/edit', [
            'institution' => $this->institution,
            'event'       => $event,
            'sports'      => Athlete::getAllSports(),
            'flash'       => $this->flash(),
            'errors'      => $this->errors(),
        ]);
    }

    public function update(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $event = Event::findById((int)$id);
        if (!$event || $event['institution_id'] != $this->institution['id']) $this->abort(404);

        $errors = $this->validate([
            'name'           => 'required|max:255',
            'location'       => 'required',
            'contact_name'   => 'required|max:255',
            'contact_mobile' => 'required|mobile',
            'contact_email'  => 'required|email',
        ]);

        $data = [
            'name'               => trim($_POST['name']),
            'location'           => trim($_POST['location']),
            'reg_date_from'      => $_POST['reg_date_from'],
            'reg_date_to'        => $_POST['reg_date_to'],
            'event_date_from'    => $_POST['event_date_from'],
            'event_date_to'      => $_POST['event_date_to'],
            'latitude'           => $_POST['latitude']  ?: null,
            'longitude'          => $_POST['longitude'] ?: null,
            'bank_details'       => trim($_POST['bank_details'] ?? ''),
            'contact_name'       => trim($_POST['contact_name']),
            'contact_designation'=> trim($_POST['contact_designation'] ?? ''),
            'contact_mobile'     => trim($_POST['contact_mobile']),
            'contact_email'      => strtolower(trim($_POST['contact_email'])),
            'status'             => 'pending_approval',
        ];

        if (!empty($_FILES['logo']['name'])) {
            try { $data['logo'] = (new FileUpload())->upload($_FILES['logo'], 'events', true); }
            catch (\RuntimeException $e) { $errors['logo'][] = $e->getMessage(); }
        }

        if ($errors) { $_SESSION['errors'] = $errors; $this->redirect("/institution/events/{$id}/edit"); }

        $sports = [];
        foreach ($_POST['sports'] ?? [] as $sportId => $info) {
            if (!empty($info['selected'])) {
                $sports[(int)$sportId] = ['category' => $info['category'] ?? null, 'entry_fee' => (float)($info['entry_fee'] ?? 0)];
            }
        }
        Event::update((int)$id, $data, $_POST['payment_modes'] ?? [], $sports);
        $this->redirect('/institution/events', 'Event updated and resubmitted for approval!');
    }

    public function view(string $id): void
    {
        $this->boot();
        $event = Event::findById((int)$id);
        if (!$event || $event['institution_id'] != $this->institution['id']) $this->abort(404);
        $this->renderWith('app', 'institution/events/view', ['institution' => $this->institution, 'event' => $event]);
    }
}
