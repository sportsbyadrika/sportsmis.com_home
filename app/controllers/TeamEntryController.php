<?php
namespace Controllers;

use Core\{Controller, Auth, FileUpload};
use Models\{Schema, Event, EventUnit, UnitUser, EventStaff, TeamRegistration, TeamRegistrationPayment};

/**
 * Team Entry capture screen — shared by Unit/Club/Institution users and by
 * Event Staff who hold the 'team_entry' privilege.
 *
 * The acting user is resolved in boot() into a uniform $actor structure so
 * the rest of the controller is portal-agnostic:
 *   - type            'unit_user' | 'event_staff'
 *   - layout          'unit'      | 'staff'
 *   - units           the units the actor may pick from
 *   - payment_required true for unit users, false for staff
 */
class TeamEntryController extends Controller
{
    private array $actor;
    private array $event;

    private function boot(): void
    {
        try { Schema::ensureEventStaff(); } catch (\Throwable $e) {}

        // Event staff take precedence if both sessions somehow exist.
        if (Auth::eventStaffCheck()) {
            $session = Auth::eventStaff();
            $staff   = EventStaff::findById((int)$session['id']);
            if (!$staff || $staff['status'] !== 'active') {
                Auth::eventStaffLogout();
                $this->redirect('/staff/login', 'Your staff account is not active.', 'error');
            }
            $privileges = EventStaff::privilegesFor((int)$staff['id']);
            if (!in_array('team_entry', $privileges, true)) {
                $this->abort(403);
            }
            $event = $this->resolveEvent((int)$staff['event_id']);
            $this->actor = [
                'type'             => 'event_staff',
                'id'               => (int)$staff['id'],
                'event_id'         => (int)$staff['event_id'],
                'name'             => $staff['name'],
                'layout'           => 'staff',
                'payment_required' => false,
                'units'            => EventUnit::forEvent((int)$staff['event_id']),
            ];
            $this->event = $event;
            return;
        }

        if (Auth::unitUserCheck()) {
            $session = Auth::unitUser();
            $u = UnitUser::findById((int)$session['id']);
            if (!$u || $u['status'] !== 'active') {
                Auth::unitUserLogout();
                $this->redirect('/unit/login', 'Your unit user account is not active.', 'error');
            }
            $event = $this->resolveEvent((int)$u['event_id']);
            $this->actor = [
                'type'             => 'unit_user',
                'id'               => (int)$u['id'],
                'event_id'         => (int)$u['event_id'],
                'name'             => $u['name'],
                'layout'           => 'unit',
                'payment_required' => true,
                'units'            => UnitUser::assignmentsFor((int)$u['id']),
            ];
            $this->event = $event;
            return;
        }

        $this->redirect('/unit/login', 'Please sign in to continue.', 'warning');
    }

    private function resolveEvent(int $eventId): array
    {
        $event = Event::findById($eventId);
        if (!$event) $this->abort(404);
        $event['event_code'] = $event['event_code'] ?? \ensureEventCode($eventId);
        return $event;
    }

    /** Verify a unit id is one the actor is allowed to pick. */
    private function actorAllowsUnit(int $unitId): bool
    {
        foreach ($this->actor['units'] as $u) {
            if ((int)$u['id'] === $unitId) return true;
        }
        return false;
    }

    // ── List ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->boot();
        $teams = TeamRegistration::forCreator($this->actor['type'], $this->actor['id'], (int)$this->event['id']);
        $this->renderWith($this->actor['layout'], 'team-entry/index', [
            'actor' => $this->actor,
            'event' => $this->event,
            'teams' => $teams,
            'flash' => $this->flash(),
        ]);
    }

    // ── Capture form (new / edit) ────────────────────────────────────────────

    public function form(string $id = '0'): void
    {
        $this->boot();
        $teamId = (int)$id;
        $team = null; $members = []; $payment = null;
        if ($teamId) {
            $team = TeamRegistration::withContext($teamId);
            if (!$team || !$this->ownsTeam($team)) $this->abort(404);
            $members = TeamRegistration::members($teamId);
            $pays    = TeamRegistrationPayment::forTeam($teamId);
            $payment = $pays[0] ?? null;
        }
        $this->renderWith($this->actor['layout'], 'team-entry/form', [
            'actor'      => $this->actor,
            'event'      => $this->event,
            'team'       => $team,
            'members'    => $members,
            'payment'    => $payment,
            'categories' => TeamRegistration::teamCategories((int)$this->event['id']),
            'read_only'  => $team && !TeamRegistration::isEditable($team),
            'flash'      => $this->flash(),
        ]);
    }

    private function ownsTeam(array $team): bool
    {
        return ($team['created_by_type'] ?? '') === $this->actor['type']
            && (int)($team['created_by_id'] ?? 0) === $this->actor['id']
            && (int)$team['event_id'] === (int)$this->event['id'];
    }

    // ── AJAX dropdown feeds ──────────────────────────────────────────────────

    /** GET /team-entry/category-events?category_id= */
    public function categoryEvents(): void
    {
        $this->boot();
        $categoryId = (int)($_GET['category_id'] ?? 0);
        $this->json([
            'success' => true,
            'events'  => TeamRegistration::teamEventsForCategory((int)$this->event['id'], $categoryId),
        ]);
    }

    /** GET /team-entry/members?unit_id=&event_sport_id= */
    public function memberOptions(): void
    {
        $this->boot();
        $unitId       = (int)($_GET['unit_id'] ?? 0);
        $eventSportId = (int)($_GET['event_sport_id'] ?? 0);
        if (!$this->actorAllowsUnit($unitId)) {
            $this->json(['success' => false, 'message' => 'Unit not permitted.']);
        }
        $this->json([
            'success'    => true,
            'candidates' => TeamRegistration::memberCandidates((int)$this->event['id'], $unitId, $eventSportId),
        ]);
    }

    // ── Save (draft / submit) ────────────────────────────────────────────────

    public function save(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $teamId   = (int)($_POST['id'] ?? 0);
        $action   = $_POST['action'] ?? 'draft';            // draft | submit
        $isSubmit = $action === 'submit';

        $team = null;
        if ($teamId) {
            $team = TeamRegistration::withContext($teamId);
            if (!$team || !$this->ownsTeam($team)) {
                $this->json(['success' => false, 'message' => 'Team entry not found.']);
            }
            if (!TeamRegistration::isEditable($team)) {
                $this->json(['success' => false, 'message' => 'This team entry is locked and cannot be edited.']);
            }
        }

        $teamName     = trim((string)($_POST['team_name'] ?? ''));
        $unitId       = (int)($_POST['unit_id'] ?? 0);
        $eventSportId = (int)($_POST['event_sport_id'] ?? 0);
        $memberRegIds = array_map('intval', array_filter([
            $_POST['member_1'] ?? 0,
            $_POST['member_2'] ?? 0,
            $_POST['member_3'] ?? 0,
        ]));

        // Team name is mandatory for everyone, even on draft.
        if ($teamName === '') {
            $this->json(['success' => false, 'message' => 'Team Name is required.']);
        }
        if ($unitId && !$this->actorAllowsUnit($unitId)) {
            $this->json(['success' => false, 'message' => 'You are not permitted to use that unit.']);
        }

        // event_sport / fee resolution.
        $teamFee = null; $esRow = null;
        if ($eventSportId) {
            $esRow = Event::rowsRaw(
                "SELECT id, team_entry_fee FROM event_sports WHERE id = ? AND event_id = ?",
                [$eventSportId, (int)$this->event['id']]
            );
            $esRow = $esRow[0] ?? null;
            if (!$esRow || $esRow['team_entry_fee'] === null) {
                $this->json(['success' => false, 'message' => 'Selected event is not team-eligible.']);
            }
            $teamFee = (float)$esRow['team_entry_fee'];
        }

        // Resolve members → (athlete_id, competitor_number) from registrations.
        $resolved = [];
        if ($memberRegIds) {
            if (count($memberRegIds) !== count(array_unique($memberRegIds))) {
                $this->json(['success' => false, 'message' => 'The same athlete cannot be in more than one member slot.']);
            }
            if (!$unitId || !$eventSportId) {
                $this->json(['success' => false, 'message' => 'Pick a unit and event before choosing members.']);
            }
            $candidates = TeamRegistration::memberCandidates((int)$this->event['id'], $unitId, $eventSportId);
            $byReg = [];
            foreach ($candidates as $c) $byReg[(int)$c['registration_id']] = $c;
            foreach ($memberRegIds as $rid) {
                if (!isset($byReg[$rid])) {
                    $this->json(['success' => false, 'message' => 'A selected member is not a valid approved participant for this unit/event.']);
                }
                $resolved[] = [
                    'athlete_id'        => (int)$byReg[$rid]['athlete_id'],
                    'registration_id'  => $rid,
                    'competitor_number'=> (int)$byReg[$rid]['competitor_number'],
                ];
            }
        }

        // Submit-time validation: everything required.
        if ($isSubmit) {
            if (!$unitId)       $this->json(['success' => false, 'message' => 'Select a Unit / Club / Institution.']);
            if (!$eventSportId) $this->json(['success' => false, 'message' => 'Select an Event Category and Event.']);
            if (count($resolved) !== 3) {
                $this->json(['success' => false, 'message' => 'Pick all three team members before submitting.']);
            }
        }

        // ── Persist header ──
        $header = [
            'event_id'        => (int)$this->event['id'],
            'unit_id'         => $unitId ?: null,
            'event_sport_id'  => $eventSportId ?: null,
            'team_name'       => $teamName,
            'total_amount'    => $teamFee,
        ];
        if ($teamId) {
            TeamRegistration::updateRow($teamId, $header);
        } else {
            $teamId = TeamRegistration::createForActor(array_merge($header, [
                'created_by_type' => $this->actor['type'],
                'created_by_id'   => $this->actor['id'],
            ]));
        }

        // ── Members ──
        if ($resolved) {
            TeamRegistration::setMembers($teamId, $resolved);
        } elseif (!$isSubmit && $teamId) {
            // draft with no members yet — leave existing as-is unless cleared
            if (isset($_POST['clear_members'])) TeamRegistration::setMembers($teamId, []);
        }

        // ── Payment proof ──
        $existingPayments = TeamRegistrationPayment::forTeam($teamId);
        $hasProof = !empty($existingPayments);
        if (!empty($_FILES['payment_proof']['name'])) {
            try {
                $proof = (new FileUpload())->upload($_FILES['payment_proof'], 'team-registrations');
            } catch (\RuntimeException $e) {
                $this->json(['success' => false, 'message' => 'Proof upload failed: ' . $e->getMessage()]);
            }
            // Replace any prior pending proof rows for a clean single record.
            foreach ($existingPayments as $p) {
                if (($p['status'] ?? '') !== 'approved') TeamRegistrationPayment::deleteRow((int)$p['id']);
            }
            TeamRegistrationPayment::create([
                'team_registration_id' => $teamId,
                'event_id'             => (int)$this->event['id'],
                'transaction_date'     => ($_POST['transaction_date'] ?? '') ?: date('Y-m-d'),
                'transaction_number'   => trim((string)($_POST['transaction_number'] ?? '')) ?: 'N/A',
                'amount'               => $teamFee ?? 0,
                'proof_file'           => $proof,
                'status'               => 'pending',
                'payment_method'       => 'manual',
            ]);
            $hasProof = true;
        }

        // Unit users must attach payment proof to submit.
        if ($isSubmit && $this->actor['payment_required'] && !$hasProof) {
            $this->json(['success' => false,
                'message' => 'Payment proof upload is mandatory before you can submit this team entry.']);
        }

        if ($isSubmit) {
            TeamRegistration::updateRow($teamId, [
                'status'              => 'pending',
                'admin_review_status' => 'pending',
                'submitted_at'        => date('Y-m-d H:i:s'),
                'payment_mode'        => 'manual',
            ]);
            TeamRegistrationPayment::recomputeTeamPaymentStatus($teamId);
            $_SESSION['flash'] = ['type' => 'success',
                'message' => 'Team entry submitted for the event administrator\'s review.'];
            $this->json(['success' => true, 'redirect' => '/team-entry']);
        }

        TeamRegistrationPayment::recomputeTeamPaymentStatus($teamId);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Team entry saved as draft.'];
        $this->json(['success' => true, 'redirect' => '/team-entry/' . $teamId]);
    }

    /** POST /team-entry/{id}/delete — drop a draft (never a submitted entry). */
    public function delete(string $id): void
    {
        $this->boot();
        $this->verifyCsrf();
        $team = TeamRegistration::withContext((int)$id);
        if (!$team || !$this->ownsTeam($team)) $this->abort(404);
        if (!empty($team['submitted_at'])) {
            $this->json(['success' => false, 'message' => 'Submitted entries cannot be deleted.']);
        }
        Event::rowsRaw("DELETE FROM team_registrations WHERE id = ?", [(int)$id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Draft team entry deleted.'];
        $this->json(['success' => true, 'redirect' => '/team-entry']);
    }
}
