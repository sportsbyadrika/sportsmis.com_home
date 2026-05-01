<?php
namespace Controllers;

use Core\{Controller, Auth, FileUpload};
use Models\{Institution, Event, Athlete, Schema, SportCategory, SportEvent, EventUnit, EventDocument};

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

        $this->renderWith('app', 'institution/events/edit', [
            'institution' => $this->institution,
            'event'       => $event,
            'sports'      => Athlete::getEventSports(),
            'units'       => EventUnit::forEvent((int)$id),
            'documents'   => EventDocument::forEvent((int)$id),
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
                'noc'            => $this->saveNocSetting((int)$id),
                'status'         => $this->saveStatus((int)$id),
                'sport_event_add'    => $this->addSportEvent((int)$id),
                'sport_event_remove' => $this->removeSportEvent((int)$id),
                'unit_save'      => $this->saveUnit((int)$id),
                'unit_delete'    => $this->deleteUnit((int)$id),
                'unit_csv'       => $this->importUnitsCsv((int)$id),
                'document_save'  => $this->saveDocument((int)$id),
                'document_delete'=> $this->deleteDocument((int)$id),
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
        $eventCode    = trim((string)($_POST['event_code'] ?? ''));
        $force        = !empty($_POST['force']);

        if ($eventCode === '') {
            $this->json(['success' => false, 'message' => 'Enter an Event Code (a short label/identifier).']);
        }
        if (mb_strlen($eventCode) > 50) {
            $this->json(['success' => false, 'message' => 'Event Code must be 50 characters or fewer.']);
        }

        $se = SportEvent::find($sportEventId);
        if (!$se) $this->json(['success' => false, 'message' => 'Choose a valid sport event.']);

        if (!$force && Event::hasSportEvent($eventId, $sportEventId)) {
            $this->json([
                'success'   => false,
                'duplicate' => true,
                'message'   => 'This sport event is already added to this event. '
                             . 'Remove it first or use Update Fee to change the entry fee/code.',
            ]);
        }

        Event::addSportEvent($eventId, [
            'sport_id'       => (int)$se['sport_id'],
            'sport_event_id' => (int)$se['id'],
            'event_code'     => $eventCode,
            'category'       => $se['name'],
            'entry_fee'      => $entryFee,
        ]);

        $this->json([
            'success' => true,
            'message' => $force ? 'Entry fee/code updated.' : 'Sport event added.',
            'list'    => Event::getSports($eventId),
        ]);
    }

    private function removeSportEvent(int $eventId): void
    {
        $rowId = (int)($_POST['row_id'] ?? 0);
        if ($rowId) Event::removeSportRow($eventId, $rowId);
        $this->json(['success' => true, 'message' => 'Removed.', 'list' => Event::getSports($eventId)]);
    }

    private function saveNocSetting(int $eventId): void
    {
        $val = $_POST['noc_required'] ?? 'optional';
        if (!in_array($val, ['none', 'optional', 'mandatory'], true)) {
            $this->json(['success' => false, 'message' => 'Invalid NOC requirement.']);
        }
        Event::updatePartial($eventId, ['noc_required' => $val]);
        $this->json(['success' => true, 'message' => 'NOC requirement saved.']);
    }

    private function saveStatus(int $eventId): void
    {
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['draft', 'active', 'completed', 'suspended'], true)) {
            $this->json(['success' => false, 'message' => 'Invalid status.']);
        }
        Event::setStatus($eventId, $status);
        $this->json(['success' => true, 'message' => 'Status updated to ' . ucfirst($status) . '.']);
    }

    private function saveUnit(int $eventId): void
    {
        $unitId  = (int)($_POST['unit_id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        if ($name === '') $this->json(['success' => false, 'message' => 'Unit name is required.']);

        if ($unitId) {
            $u = EventUnit::find($unitId);
            if (!$u || (int)$u['event_id'] !== $eventId) $this->json(['success' => false, 'message' => 'Unit not found.']);
            EventUnit::updateRow($unitId, ['name' => $name, 'address' => $address ?: null]);
        } else {
            $unitId = EventUnit::create(['event_id' => $eventId, 'name' => $name, 'address' => $address ?: null]);
        }
        $this->json(['success' => true, 'message' => 'Unit saved.', 'id' => $unitId,
                     'list' => EventUnit::forEvent($eventId)]);
    }

    private function deleteUnit(int $eventId): void
    {
        $unitId = (int)($_POST['unit_id'] ?? 0);
        $u = EventUnit::find($unitId);
        if (!$u || (int)$u['event_id'] !== $eventId) $this->json(['success' => false, 'message' => 'Unit not found.']);
        EventUnit::deleteRow($unitId);
        $this->json(['success' => true, 'message' => 'Unit removed.', 'list' => EventUnit::forEvent($eventId)]);
    }

    private function importUnitsCsv(int $eventId): void
    {
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'message' => 'No CSV file uploaded.']);
        }
        $tmp  = $_FILES['file']['tmp_name'];
        $size = (int)$_FILES['file']['size'];
        if ($size > 1 * 1024 * 1024) {
            $this->json(['success' => false, 'message' => 'CSV is larger than 1 MB.']);
        }

        $fh = @fopen($tmp, 'r');
        if (!$fh) $this->json(['success' => false, 'message' => 'Could not read the uploaded file.']);

        $created = 0; $skipped = 0; $errors = [];
        $lineNo  = 0;
        $existing = array_column(EventUnit::forEvent($eventId), 'name');
        $existing = array_map('strtolower', $existing);
        // Read first row, treat as header if it contains "name".
        $first = fgetcsv($fh);
        if ($first === false) { fclose($fh); $this->json(['success' => false, 'message' => 'CSV is empty.']); }
        $headerHasName = in_array('name', array_map('strtolower', array_map('trim', $first)), true);
        if (!$headerHasName) {
            // Treat the first row as data.
            rewind($fh);
        }

        while (($row = fgetcsv($fh)) !== false) {
            $lineNo++;
            $name    = isset($row[0]) ? trim($row[0]) : '';
            $address = isset($row[1]) ? trim($row[1]) : '';
            if ($name === '') { $skipped++; continue; }
            if (in_array(strtolower($name), $existing, true)) { $skipped++; continue; }
            try {
                EventUnit::create([
                    'event_id' => $eventId,
                    'name'     => mb_substr($name, 0, 255),
                    'address'  => $address !== '' ? mb_substr($address, 0, 65535) : null,
                ]);
                $existing[] = strtolower($name);
                $created++;
            } catch (\Throwable $e) {
                $errors[] = "Line {$lineNo}: " . $e->getMessage();
            }
        }
        fclose($fh);

        $msgParts = ["Imported {$created} unit" . ($created === 1 ? '' : 's')];
        if ($skipped) $msgParts[] = "{$skipped} skipped (blank or duplicate)";
        if ($errors)  $msgParts[] = count($errors) . ' error(s): ' . implode('; ', array_slice($errors, 0, 3));

        $this->json([
            'success' => $created > 0 || empty($errors),
            'message' => implode(' · ', $msgParts) . '.',
            'list'    => EventUnit::forEvent($eventId),
        ]);
    }

    private function saveDocument(int $eventId): void
    {
        $docId   = (int)($_POST['doc_id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $purpose = trim($_POST['purpose'] ?? '');
        $status  = $_POST['status'] ?? 'active';
        if (!in_array($status, ['active', 'inactive'], true)) $status = 'active';
        if ($name === '') $this->json(['success' => false, 'message' => 'Document name is required.']);

        $data = [
            'name'    => $name,
            'purpose' => $purpose ?: null,
            'status'  => $status,
        ];

        if (!empty($_FILES['file']['name'])) {
            try {
                $data['file'] = (new FileUpload())->upload($_FILES['file'], 'events/documents');
            } catch (\RuntimeException $e) {
                $this->json(['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()]);
            }
        }

        if ($docId) {
            $existing = EventDocument::find($docId);
            if (!$existing || (int)$existing['event_id'] !== $eventId) {
                $this->json(['success' => false, 'message' => 'Document not found.']);
            }
            EventDocument::updateRow($docId, $data);
        } else {
            $data['event_id'] = $eventId;
            $docId = EventDocument::create($data);
        }
        $this->json([
            'success' => true,
            'message' => 'Document saved.',
            'id'      => $docId,
            'list'    => EventDocument::forEvent($eventId),
        ]);
    }

    private function deleteDocument(int $eventId): void
    {
        $docId = (int)($_POST['doc_id'] ?? 0);
        $d = EventDocument::find($docId);
        if (!$d || (int)$d['event_id'] !== $eventId) $this->json(['success' => false, 'message' => 'Document not found.']);
        EventDocument::deleteRow($docId);
        $this->json(['success' => true, 'message' => 'Document removed.', 'list' => EventDocument::forEvent($eventId)]);
    }

    /**
     * Deprecated: previously flipped a draft to pending_approval. The
     * institution now controls the event status directly via the
     * Status dropdown — this endpoint is kept so any in-flight client
     * doesn't 404, but it explains the new flow.
     */
    public function submit(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $this->json(['success' => false,
            'message' => 'Submit-for-approval is deprecated. Set the event Status to "Active" from the edit page when you are ready to publish.']);
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
        $gender = $_GET['gender'] ?? '';
        $list = SportEvent::byCategory((int)$categoryId);
        if (in_array($gender, ['male', 'female', 'mixed'], true)) {
            $list = array_values(array_filter($list, fn($r) => $r['gender'] === $gender));
        }
        $this->json(['success' => true, 'sport_events' => $list]);
    }
}
