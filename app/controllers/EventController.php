<?php
namespace Controllers;

use Core\{Controller, Auth, FileUpload};
use Models\{Institution, Event, Athlete, Schema, SportCategory, SportEvent};

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
        try { Schema::ensureSportHierarchy(); } catch (\Throwable $e) {
            error_log('[event/ensureSchema] ' . $e->getMessage());
        }
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

    /** GET /institution/events/create — create a blank draft and redirect to the edit form. */
    public function createForm(): void
    {
        $this->boot();
        $id = Event::create([
            'institution_id'      => $this->institution['id'],
            'name'                => 'Untitled Event',
            'location'            => '',
            'reg_date_from'       => date('Y-m-d'),
            'reg_date_to'         => date('Y-m-d'),
            'event_date_from'     => date('Y-m-d'),
            'event_date_to'       => date('Y-m-d'),
            'contact_name'        => '',
            'contact_mobile'      => '',
            'contact_email'       => '',
            'status'              => 'draft',
        ], [], []);
        $this->redirect("/institution/events/{$id}/edit");
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
        ]);
    }

    // ── AJAX Panel Saves ─────────────────────────────────────────────────────

    public function ajaxSave(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $event = Event::findById((int)$id);
        if (!$event || $event['institution_id'] != $this->institution['id']) $this->abort(404);

        $section = $_POST['section'] ?? '';
        try {
            match ($section) {
                'details'        => $this->saveDetails((int)$id),
                'logo'           => $this->saveLogo((int)$id),
                'location'       => $this->saveLocation((int)$id),
                'payment'        => $this->savePayment((int)$id),
                'contact'        => $this->saveContact((int)$id),
                'sport_event_add'    => $this->addSportEvent((int)$id),
                'sport_event_remove' => $this->removeSportEvent((int)$id),
                default          => $this->json(['success' => false, 'message' => 'Unknown section.']),
            };
        } catch (\Throwable $e) {
            error_log('[event/save:' . $section . '] ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
        }
    }

    private function saveDetails(int $id): void
    {
        $name      = trim($_POST['name'] ?? '');
        $location  = trim($_POST['location'] ?? '');
        $regFrom   = $_POST['reg_date_from']   ?? '';
        $regTo     = $_POST['reg_date_to']     ?? '';
        $evFrom    = $_POST['event_date_from'] ?? '';
        $evTo      = $_POST['event_date_to']   ?? '';

        if ($name === '' || $location === '' || !$regFrom || !$regTo || !$evFrom || !$evTo) {
            $this->json(['success' => false, 'message' => 'Name, location, and all dates are required.']);
        }
        Event::updatePartial($id, [
            'name'            => $name,
            'location'        => $location,
            'reg_date_from'   => $regFrom,
            'reg_date_to'     => $regTo,
            'event_date_from' => $evFrom,
            'event_date_to'   => $evTo,
        ]);
        $this->json(['success' => true, 'message' => 'Event details saved!']);
    }

    private function saveLogo(int $id): void
    {
        if (empty($_FILES['logo']) || empty($_FILES['logo']['name'])) {
            $this->json(['success' => false, 'message' => 'No logo received.']);
        }
        $url = (new FileUpload())->upload($_FILES['logo'], 'events', true);
        Event::updatePartial($id, ['logo' => $url]);
        $this->json(['success' => true, 'message' => 'Logo updated!', 'logo_url' => $url]);
    }

    private function saveLocation(int $id): void
    {
        Event::updatePartial($id, [
            'latitude'  => $_POST['latitude']  !== '' ? $_POST['latitude']  : null,
            'longitude' => $_POST['longitude'] !== '' ? $_POST['longitude'] : null,
        ]);
        $this->json(['success' => true, 'message' => 'Location saved!']);
    }

    private function savePayment(int $id): void
    {
        $modes = $_POST['payment_modes'] ?? [];
        if (!is_array($modes)) $modes = [];
        $modes = array_values(array_intersect($modes, ['manual', 'online']));
        if (!$modes) {
            $this->json(['success' => false, 'message' => 'Select at least one payment mode.']);
        }
        $data = ['bank_details' => trim($_POST['bank_details'] ?? '')];
        if (!empty($_FILES['bank_qr_code']['name'])) {
            $data['bank_qr_code'] = (new FileUpload())->upload($_FILES['bank_qr_code'], 'events', true);
        }
        Event::updatePartial($id, $data);
        Event::syncPaymentModesPublic($id, $modes);
        $this->json(['success' => true, 'message' => 'Payment settings saved!',
                     'qr_url' => $data['bank_qr_code'] ?? null]);
    }

    private function saveContact(int $id): void
    {
        $name   = trim($_POST['contact_name'] ?? '');
        $mobile = trim($_POST['contact_mobile'] ?? '');
        $email  = strtolower(trim($_POST['contact_email'] ?? ''));
        if ($name === '' || $mobile === '' || $email === '') {
            $this->json(['success' => false, 'message' => 'Name, mobile, and email are required.']);
        }
        if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
            $this->json(['success' => false, 'message' => 'Enter a valid 10-digit mobile number.']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'message' => 'Enter a valid email address.']);
        }
        Event::updatePartial($id, [
            'contact_name'        => $name,
            'contact_designation' => trim($_POST['contact_designation'] ?? ''),
            'contact_mobile'      => $mobile,
            'contact_email'       => $email,
        ]);
        $this->json(['success' => true, 'message' => 'Contact info saved!']);
    }

    private function addSportEvent(int $eventId): void
    {
        $sportEventId = (int)($_POST['sport_event_id'] ?? 0);
        $entryFee     = (float)($_POST['entry_fee'] ?? 0);

        $se = SportEvent::find($sportEventId);
        if (!$se) $this->json(['success' => false, 'message' => 'Choose a valid sport event.']);

        Event::addSportEvent($eventId, [
            'sport_id'       => (int)$se['sport_id'],
            'sport_event_id' => (int)$se['id'],
            'category'       => $se['name'],
            'entry_fee'      => $entryFee,
        ]);

        $this->json(['success' => true, 'message' => 'Sport event added.', 'list' => Event::getSports($eventId)]);
    }

    private function removeSportEvent(int $eventId): void
    {
        $rowId = (int)($_POST['row_id'] ?? 0);
        if ($rowId) Event::removeSportRow($eventId, $rowId);
        $this->json(['success' => true, 'message' => 'Removed.', 'list' => Event::getSports($eventId)]);
    }

    /** POST /institution/events/{id}/submit — flip from draft to pending_approval. */
    public function submit(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();

        $event = Event::findById((int)$id);
        if (!$event || $event['institution_id'] != $this->institution['id']) $this->abort(404);

        $required = ['name', 'location', 'reg_date_from', 'reg_date_to', 'event_date_from', 'event_date_to',
                     'contact_name', 'contact_mobile', 'contact_email'];
        $missing = [];
        foreach ($required as $f) {
            if (empty($event[$f])) $missing[] = str_replace('_', ' ', $f);
        }
        $modes = Event::getPaymentModes((int)$id);
        if (!$modes)                         $missing[] = 'payment mode';
        if (empty($event['sports']))         $missing[] = 'at least one sport event';

        if ($missing) {
            $this->json(['success' => false,
                'message' => 'Please save all required sections first: ' . implode(', ', $missing) . '.']);
        }

        Event::updatePartial((int)$id, ['status' => 'pending_approval']);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Event submitted for approval!'];
        $this->json(['success' => true, 'message' => 'Event submitted for approval!',
                     'redirect' => '/institution/events']);
    }

    public function view(string $id): void
    {
        $this->boot();
        $event = Event::findById((int)$id);
        if (!$event || $event['institution_id'] != $this->institution['id']) $this->abort(404);
        $this->renderWith('app', 'institution/events/view', ['institution' => $this->institution, 'event' => $event]);
    }

    // ── Catalog AJAX (for the Sports-in-this-Event picker) ───────────────────

    public function categoriesForSport(string $sportId): void
    {
        $this->requireAuth('institution_admin');
        try { Schema::ensureSportHierarchy(); } catch (\Throwable $e) {}
        $this->json(['success' => true, 'categories' => SportCategory::bySport((int)$sportId)]);
    }

    public function eventsForCategory(string $categoryId): void
    {
        $this->requireAuth('institution_admin');
        try { Schema::ensureSportHierarchy(); } catch (\Throwable $e) {}
        $this->json(['success' => true, 'sport_events' => SportEvent::byCategory((int)$categoryId)]);
    }
}
