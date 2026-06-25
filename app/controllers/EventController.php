<?php
namespace Controllers;

use Core\{Controller, Auth, FileUpload};
use Models\{Institution, Event, Athlete, Schema, SportCategory, SportEvent, EventUnit, EventDocument, SportItem, EventSportItem, ShootingRange, Relay};

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
        try { Schema::ensureUnitRegistration(); } catch (\Throwable $e) {}
        try { Schema::ensureInstitutionAsUnit(); } catch (\Throwable $e) {}
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
        $event = Event::findById(\hid_event_decode($id));
        if (!$event || $event['institution_id'] != $this->institution['id']) $this->abort(404);

        $this->renderWith('app', 'institution/events/edit', [
            'institution'    => $this->institution,
            'event'          => $event,
            'sports'         => Athlete::getEventSports(),
            'units'          => EventUnit::forEvent((int)$id),
            'documents'      => EventDocument::forEvent((int)$id),
            'event_items'    => EventSportItem::forEvent((int)$id),
            'shooting_ranges'=> ShootingRange::forEventTree((int)$id),
            'relays'         => Relay::forEvent((int)$id),
            'event_categories' => $this->distinctSportEventCategories((int)$id),
            'flash'          => $this->flash(),
        ]);
    }

    /** Distinct sport-event category names configured on this event. */
    private function distinctSportEventCategories(int $eventId): array
    {
        $rows = Event::rowsRaw(
            "SELECT DISTINCT sc.name
               FROM event_sports es
               JOIN sport_events     se ON se.id = es.sport_event_id
               JOIN sport_categories sc ON sc.id = se.category_id
              WHERE es.event_id = ?
              ORDER BY sc.name",
            [$eventId]
        );
        return array_values(array_filter(array_map(fn($r) => (string)($r['name'] ?? ''), $rows)));
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
                'medal'          => $this->saveMedalPoints((int)$id),
                'status'         => $this->saveStatus((int)$id),
                'sport_event_add'    => $this->addSportEvent((int)$id),
                'sport_event_update' => $this->updateSportEvent((int)$id),
                'sport_event_remove' => $this->removeSportEvent((int)$id),
                'unit_save'      => $this->saveUnit((int)$id),
                'unit_delete'    => $this->deleteUnit((int)$id),
                'unit_csv'       => $this->importUnitsCsv((int)$id),
                'document_save'  => $this->saveDocument((int)$id),
                'document_delete'=> $this->deleteDocument((int)$id),
                'item_add'       => $this->addEventItem((int)$id),
                'item_remove'    => $this->removeEventItem((int)$id),
                'srange_save'    => $this->saveShootingRange((int)$id),
                'srange_delete'  => $this->deleteShootingRange((int)$id),
                'srdist_save'    => $this->saveShootingRangeDistance((int)$id),
                'srdist_delete'  => $this->deleteShootingRangeDistance((int)$id),
                'srlane_save'    => $this->saveShootingRangeLane((int)$id),
                'srlane_delete'  => $this->deleteShootingRangeLane((int)$id),
                'relay_save'     => $this->saveRelay((int)$id),
                'relay_delete'   => $this->deleteRelay((int)$id),
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

        $bankName    = trim($_POST['bank_name'] ?? '');
        $bankBranch  = trim($_POST['bank_branch'] ?? '');
        $bankAccount = preg_replace('/\s+/', '', $_POST['bank_account_number'] ?? '') ?? '';
        $bankIfsc    = strtoupper(preg_replace('/\s+/', '', $_POST['bank_ifsc'] ?? '') ?? '');

        if (in_array('online', $modes, true)) {
            if ($bankName === '' || $bankBranch === '' || $bankAccount === '' || $bankIfsc === '') {
                $this->json(['success' => false,
                             'message' => 'Bank Name, Branch, Account Number and IFSC are required for Online Payment.']);
            }
            if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $bankIfsc)) {
                $this->json(['success' => false, 'message' => 'IFSC must be 11 chars, e.g. SBIN0001234.']);
            }
            if (!preg_match('/^\d{6,20}$/', $bankAccount)) {
                $this->json(['success' => false, 'message' => 'Account Number must be 6–20 digits.']);
            }
        }
        $data['bank_name']           = $bankName ?: null;
        $data['bank_branch']         = $bankBranch ?: null;
        $data['bank_account_number'] = $bankAccount !== '' ? $bankAccount : null;
        $data['bank_ifsc']           = $bankIfsc !== '' ? $bankIfsc : null;

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
        $teamFeeRaw   = $_POST['team_entry_fee'] ?? '';
        $teamEntryFee = $teamFeeRaw === '' || $teamFeeRaw === null ? null : (float)$teamFeeRaw;
        $mqsRaw       = $_POST['mqs'] ?? '';
        $mqs          = $mqsRaw === '' || $mqsRaw === null ? null : (float)$mqsRaw;
        $eventCode    = trim((string)($_POST['event_code'] ?? ''));
        $force        = !empty($_POST['force']);

        if ($eventCode === '') {
            $this->json(['success' => false, 'message' => 'Enter an Event Code (a short label/identifier).']);
        }
        if (mb_strlen($eventCode) > 50) {
            $this->json(['success' => false, 'message' => 'Event Code must be 50 characters or fewer.']);
        }
        if ($entryFee < 0) {
            $this->json(['success' => false, 'message' => 'Entry fee can\'t be negative.']);
        }
        if ($teamEntryFee !== null && $teamEntryFee < 0) {
            $this->json(['success' => false, 'message' => 'Team entry fee can\'t be negative.']);
        }
        if ($mqs !== null && $mqs < 0) {
            $this->json(['success' => false, 'message' => 'MQS can\'t be negative.']);
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
            'team_entry_fee' => $teamEntryFee,
            'mqs'            => $mqs,
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

    private function updateSportEvent(int $eventId): void
    {
        $rowId       = (int)($_POST['row_id'] ?? 0);
        $code        = trim((string)($_POST['event_code'] ?? ''));
        $entryFee    = (float)($_POST['entry_fee'] ?? 0);
        $teamFeeRaw  = $_POST['team_entry_fee'] ?? '';
        $teamEntryFee = $teamFeeRaw === '' || $teamFeeRaw === null ? null : (float)$teamFeeRaw;
        $mqsRaw      = $_POST['mqs'] ?? '';
        $mqs         = $mqsRaw === '' || $mqsRaw === null ? null : (float)$mqsRaw;

        if (!$rowId) $this->json(['success' => false, 'message' => 'Invalid row id.']);
        if ($code === '') {
            $this->json(['success' => false, 'message' => 'Event Code is required.']);
        }
        if (mb_strlen($code) > 50) {
            $this->json(['success' => false, 'message' => 'Event Code must be 50 characters or fewer.']);
        }
        if ($entryFee < 0) {
            $this->json(['success' => false, 'message' => 'Entry fee can\'t be negative.']);
        }
        if ($teamEntryFee !== null && $teamEntryFee < 0) {
            $this->json(['success' => false, 'message' => 'Team entry fee can\'t be negative.']);
        }
        if ($mqs !== null && $mqs < 0) {
            $this->json(['success' => false, 'message' => 'MQS can\'t be negative.']);
        }

        Event::updateSportRow($eventId, $rowId, [
            'event_code'     => $code,
            'entry_fee'      => $entryFee,
            'team_entry_fee' => $teamEntryFee,
            'mqs'            => $mqs,
        ]);

        $this->json([
            'success' => true,
            'message' => 'Sport event updated.',
            'list'    => Event::getSports($eventId),
        ]);
    }

    private function saveMedalPoints(int $eventId): void
    {
        $cols = [
            'medal_pts_indiv_gold','medal_pts_indiv_silver','medal_pts_indiv_bronze',
            'medal_pts_team_gold','medal_pts_team_silver','medal_pts_team_bronze',
        ];
        $data = [];
        foreach ($cols as $c) {
            $v = (int)($_POST[$c] ?? 0);
            if ($v < 0) $v = 0;
            $data[$c] = $v;
        }
        Event::updatePartial($eventId, $data);
        $this->json(['success' => true, 'message' => 'Medal points saved.']);
    }

    private function saveNocSetting(int $eventId): void
    {
        $val = $_POST['noc_required'] ?? 'optional';
        if (!in_array($val, ['none', 'optional', 'mandatory'], true)) {
            $this->json(['success' => false, 'message' => 'Invalid NOC requirement.']);
        }
        $teamEnabled = !empty($_POST['team_entry_enabled']) ? 1 : 0;
        $methods = $_POST['team_entry_methods'] ?? [];
        if (!is_array($methods)) $methods = [];
        $methods = array_values(array_intersect($methods, ['athlete', 'unit_user', 'event_staff']));
        if ($teamEnabled && !$methods) {
            $this->json(['success' => false,
                'message' => 'Select at least one Team Entry submission method (Athlete, Unit User, or Event Staff).']);
        }
        $allowAthleteReg = !empty($_POST['allow_athlete_registration']) ? 1 : 0;
        $allowUnitReg    = !empty($_POST['allow_unit_registration'])    ? 1 : 0;
        $allowInstReq    = !empty($_POST['allow_institution_join_request']) ? 1 : 0;
        try { Schema::ensureUnitRegistration(); } catch (\Throwable $e) {}
        try { Schema::ensureInstitutionAsUnit(); } catch (\Throwable $e) {}
        Event::updatePartial($eventId, [
            'noc_required'                   => $val,
            'team_entry_enabled'             => $teamEnabled,
            'team_entry_methods'             => $teamEnabled ? implode(',', $methods) : null,
            'allow_athlete_registration'     => $allowAthleteReg,
            'allow_unit_registration'        => $allowUnitReg,
            'allow_institution_join_request' => $allowInstReq,
        ]);
        $this->json(['success' => true, 'message' => 'Registration settings saved.']);
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

    // ── Sports Items / Weapons (per-event allow-list) ────────────────────────

    private function addEventItem(int $eventId): void
    {
        $itemId = (int)($_POST['sport_item_id'] ?? 0);
        $item   = SportItem::find($itemId);
        if (!$item) $this->json(['success' => false, 'message' => 'Item not found.']);
        EventSportItem::add($eventId, $itemId);
        $this->json(['success' => true, 'message' => 'Item added.', 'list' => EventSportItem::forEvent($eventId)]);
    }

    private function removeEventItem(int $eventId): void
    {
        $itemId = (int)($_POST['sport_item_id'] ?? 0);
        EventSportItem::remove($eventId, $itemId);
        $this->json(['success' => true, 'message' => 'Item removed.', 'list' => EventSportItem::forEvent($eventId)]);
    }

    // ── Shooting Ranges (facility → distance → lane) ─────────────────────────

    /** Verify a range belongs to this event before mutating its tree. */
    private function assertRangeOnEvent(int $rangeId, int $eventId): array
    {
        $r = ShootingRange::findRange($rangeId);
        if (!$r || (int)$r['event_id'] !== $eventId) {
            $this->json(['success' => false, 'message' => 'Shooting range not found.'], 404);
        }
        return $r;
    }

    private function assertDistanceOnEvent(int $distId, int $eventId): array
    {
        $d = ShootingRange::findDistance($distId);
        if (!$d) $this->json(['success' => false, 'message' => 'Distance not found.'], 404);
        $this->assertRangeOnEvent((int)$d['shooting_range_id'], $eventId);
        return $d;
    }

    private function saveShootingRange(int $eventId): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $name     = trim((string)($_POST['name'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        if ($name === '') $this->json(['success' => false, 'message' => 'Range name is required.']);

        if ($id) {
            $this->assertRangeOnEvent($id, $eventId);
            ShootingRange::updateRange($id, ['name' => $name, 'location' => $location ?: null]);
        } else {
            $id = ShootingRange::createRange([
                'event_id' => $eventId, 'name' => $name, 'location' => $location ?: null,
            ]);
        }
        $this->json([
            'success' => true,
            'message' => 'Shooting range saved.',
            'id'      => $id,
            'list'    => ShootingRange::forEventTree($eventId),
        ]);
    }

    private function deleteShootingRange(int $eventId): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $this->assertRangeOnEvent($id, $eventId);
        ShootingRange::deleteRange($id);
        $this->json([
            'success' => true,
            'message' => 'Shooting range removed.',
            'list'    => ShootingRange::forEventTree($eventId),
        ]);
    }

    private function saveShootingRangeDistance(int $eventId): void
    {
        $id      = (int)($_POST['id'] ?? 0);
        $rangeId = (int)($_POST['shooting_range_id'] ?? 0);
        $name    = trim((string)($_POST['name'] ?? ''));
        $rawM    = trim((string)($_POST['distance_meters'] ?? ''));
        $meters  = $rawM === '' ? null : (int)$rawM;

        if ($name === '') $this->json(['success' => false, 'message' => 'Shooting range name is required.']);
        if ($meters !== null && $meters < 0) {
            $this->json(['success' => false, 'message' => 'Distance, when set, must be zero or a positive number of metres.']);
        }
        $payload = ['name' => $name, 'distance_meters' => $meters];

        if ($id) {
            $this->assertDistanceOnEvent($id, $eventId);
            try {
                ShootingRange::updateDistance($id, $payload);
            } catch (\Throwable $e) {
                $this->json(['success' => false, 'message' => 'A shooting range with that name already exists for this venue.']);
            }
        } else {
            $this->assertRangeOnEvent($rangeId, $eventId);
            $payload['shooting_range_id'] = $rangeId;
            try {
                $id = ShootingRange::createDistance($payload);
            } catch (\Throwable $e) {
                $this->json(['success' => false, 'message' => 'A shooting range with that name already exists for this venue.']);
            }
        }
        $this->json([
            'success' => true,
            'message' => 'Shooting range saved.',
            'id'      => $id,
            'list'    => ShootingRange::forEventTree($eventId),
        ]);
    }

    private function deleteShootingRangeDistance(int $eventId): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $this->assertDistanceOnEvent($id, $eventId);
        ShootingRange::deleteDistance($id);
        $this->json([
            'success' => true,
            'message' => 'Shooting range removed.',
            'list'    => ShootingRange::forEventTree($eventId),
        ]);
    }

    private function saveShootingRangeLane(int $eventId): void
    {
        $id      = (int)($_POST['id'] ?? 0);
        $distId  = (int)($_POST['distance_id'] ?? 0);
        $number  = (int)($_POST['lane_number'] ?? 0);
        $type    = strtolower(trim((string)($_POST['lane_type'] ?? '')));
        $defCat  = trim((string)($_POST['default_category'] ?? ''));
        if ($number <= 0) $this->json(['success' => false, 'message' => 'Lane number must be a positive integer.']);
        if (!in_array($type, ['manual','mechanical','electronic'], true)) {
            $this->json(['success' => false, 'message' => 'Lane type must be Manual, Mechanical, or Electronic.']);
        }

        if ($id) {
            $existing = ShootingRange::findLane($id);
            if (!$existing) $this->json(['success' => false, 'message' => 'Lane not found.'], 404);
            $this->assertDistanceOnEvent((int)$existing['distance_id'], $eventId);
            try {
                ShootingRange::updateLane($id, [
                    'lane_number'      => $number,
                    'lane_type'        => $type,
                    'default_category' => $defCat ?: null,
                ]);
            } catch (\Throwable $e) {
                $this->json(['success' => false, 'message' => 'Lane number already exists for this distance.']);
            }
        } else {
            $this->assertDistanceOnEvent($distId, $eventId);
            try {
                $id = ShootingRange::createLane([
                    'distance_id'      => $distId,
                    'lane_number'      => $number,
                    'lane_type'        => $type,
                    'default_category' => $defCat ?: null,
                ]);
            } catch (\Throwable $e) {
                $this->json(['success' => false, 'message' => 'Lane number already exists for this distance.']);
            }
        }
        $this->json([
            'success' => true,
            'message' => 'Lane saved.',
            'id'      => $id,
            'list'    => ShootingRange::forEventTree($eventId),
        ]);
    }

    private function deleteShootingRangeLane(int $eventId): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $existing = ShootingRange::findLane($id);
        if (!$existing) $this->json(['success' => false, 'message' => 'Lane not found.'], 404);
        $this->assertDistanceOnEvent((int)$existing['distance_id'], $eventId);
        ShootingRange::deleteLane($id);
        $this->json([
            'success' => true,
            'message' => 'Lane removed.',
            'list'    => ShootingRange::forEventTree($eventId),
        ]);
    }

    // ── Relay schedule ───────────────────────────────────────────────────────

    private function saveRelay(int $eventId): void
    {
        $id           = (int)($_POST['id']                          ?? 0);
        $rangeDistId  = (int)($_POST['shooting_range_distance_id']  ?? 0);
        $relayNo      = trim((string)($_POST['relay_number']         ?? ''));
        $orderNo      = (int)($_POST['order_no']                     ?? 0);
        $relayDate    = trim((string)($_POST['relay_date']           ?? ''));
        $matchTime    = trim((string)($_POST['match_time']           ?? ''));
        $reportingT   = trim((string)($_POST['reporting_time']       ?? ''));

        // Lane assignments arrive as two parallel arrays from the modal:
        //   lane_ids[]   – the lane primary key
        //   categories[] – the dropdown value for that row
        $laneIdsRaw  = $_POST['lane_ids']   ?? [];
        $catsRaw     = $_POST['categories'] ?? [];
        if (!is_array($laneIdsRaw)) $laneIdsRaw = [];
        if (!is_array($catsRaw))    $catsRaw    = [];
        $assignments = [];
        foreach ($laneIdsRaw as $i => $lid) {
            $assignments[] = ['lane_id' => (int)$lid, 'category' => (string)($catsRaw[$i] ?? '')];
        }

        if ($relayNo === '') $this->json(['success' => false, 'message' => 'Relay number is required.']);
        if ($orderNo < 1)    $this->json(['success' => false, 'message' => 'Order number is required and must be a positive integer.']);
        if (!$rangeDistId)   $this->json(['success' => false, 'message' => 'Select a shooting range for the relay.']);
        $this->assertDistanceOnEvent($rangeDistId, $eventId);

        // Order number must be unique within the event.
        $dupe = Event::rowsRaw(
            "SELECT id FROM event_relays WHERE event_id = ? AND order_no = ? AND id <> ? LIMIT 1",
            [$eventId, $orderNo, $id]
        );
        if ($dupe) {
            $this->json(['success' => false,
                'message' => 'Order number ' . $orderNo . ' is already used by another relay in this event.']);
        }

        $payload = [
            'shooting_range_distance_id' => $rangeDistId,
            'relay_number'               => $relayNo,
            'order_no'                   => $orderNo,
            'relay_date'                 => $relayDate ?: null,
            'match_time'                 => $matchTime ?: null,
            'reporting_time'             => $reportingT ?: null,
        ];
        if ($id) {
            $existing = Relay::find($id);
            if (!$existing || (int)$existing['event_id'] !== $eventId) {
                $this->json(['success' => false, 'message' => 'Relay not found.'], 404);
            }
            // Lane-loss guard: if the edit drops lanes that currently carry a
            // unit / athlete allocation, require explicit confirmation.
            $newLaneIds = [];
            foreach ($assignments as $a) {
                $cat = trim((string)($a['category'] ?? ''));
                if ((int)$a['lane_id'] && $cat !== '' && $cat !== 'not_using') {
                    $newLaneIds[] = (int)$a['lane_id'];
                }
            }
            $lost = [];
            foreach (Relay::relayLanes($id) as $cl) {
                if (in_array((int)$cl['lane_id'], $newLaneIds, true)) continue; // kept
                if (!empty($cl['assigned_unit_id']) || !empty($cl['assigned_registration_id'])) {
                    $lost[] = [
                        'lane_number'  => (int)$cl['lane_number'],
                        'unit_name'    => $cl['unit_name'] ?? '',
                        'athlete_name' => $cl['athlete_name'] ?? '',
                    ];
                }
            }
            if ($lost && empty($_POST['confirm_remove'])) {
                $this->json([
                    'success'      => false,
                    'needs_confirm'=> true,
                    'lost_lanes'   => $lost,
                    'message'      => 'Some lanes being removed have existing allocations.',
                ]);
            }
            Relay::updateRow($id, $payload);
        } else {
            $payload['event_id'] = $eventId;
            $id = Relay::create($payload);
        }
        Relay::setLaneAssignments($id, $assignments);
        $this->json([
            'success' => true,
            'message' => 'Relay saved.',
            'id'      => $id,
            'list'    => Relay::forEvent($eventId),
        ]);
    }

    private function deleteRelay(int $eventId): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $existing = Relay::find($id);
        if (!$existing || (int)$existing['event_id'] !== $eventId) {
            $this->json(['success' => false, 'message' => 'Relay not found.'], 404);
        }
        Relay::deleteRow($id);
        $this->json([
            'success' => true,
            'message' => 'Relay removed.',
            'list'    => Relay::forEvent($eventId),
        ]);
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
        $event = Event::findById(\hid_event_decode($id));
        if (!$event || $event['institution_id'] != $this->institution['id']) $this->abort(404);
        $this->renderWith('app', 'institution/events/view', [
            'institution'     => $this->institution,
            'event'           => $event,
            'sportsBreakdown' => Event::sportsBreakdown((int)$id),
        ]);
    }

    // ── Catalog AJAX (for the Sports-in-this-Event picker) ───────────────────

    public function categoriesForSport(string $sportId): void
    {
        $this->requireAuth('institution_admin');
        try { Schema::ensureSportHierarchy(); } catch (\Throwable $e) {}
        $this->json(['success' => true, 'categories' => SportCategory::bySport((int)$sportId)]);
    }

    public function itemsForSport(string $sportId): void
    {
        $this->requireAuth('institution_admin');
        try { Schema::ensureSportHierarchy(); } catch (\Throwable $e) {}
        $this->json(['success' => true, 'items' => SportItem::activeBySport((int)$sportId)]);
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
