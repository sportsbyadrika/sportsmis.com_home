<?php
namespace Controllers;

use Core\{Controller, Auth};
use Models\{EventStaff, Event, Schema, TeamRegistration};

/**
 * Separate login portal + dashboard for Event Staff users.
 * Auth lives in $_SESSION['event_staff']. The dashboard menu is gated by
 * the privileges assigned by the event administrator.
 *
 * Lane Allocation / Scoring / Result Reports are intentionally modular
 * stubs — later prompts replace the placeholder bodies.
 */
class EventStaffController extends Controller
{
    private array $staff;
    private array $event;

    private function boot(): void
    {
        try { Schema::ensureEventStaff(); } catch (\Throwable $e) {}
        if (!Auth::eventStaffCheck()) {
            $this->redirect('/event-staff/login', 'Please sign in to continue.', 'warning');
        }
        $session = Auth::eventStaff();
        $s = EventStaff::findById((int)$session['id']);
        if (!$s || $s['status'] !== 'active') {
            Auth::eventStaffLogout();
            $this->redirect('/event-staff/login', 'Your staff account is not active.', 'error');
        }
        $event = Event::findById((int)$s['event_id']);
        if (!$event) {
            Auth::eventStaffLogout();
            $this->redirect('/event-staff/login', 'Event no longer exists.', 'error');
        }
        $event['event_code'] = $event['event_code'] ?? \ensureEventCode((int)$event['id']);
        $s['privileges'] = EventStaff::privilegesFor((int)$s['id']);
        $this->staff = $s;
        $this->event = $event;
    }

    private function requirePrivilege(string $privilege): void
    {
        if (!in_array($privilege, $this->staff['privileges'] ?? [], true)) {
            $this->abort(403);
        }
    }

    // ── Auth ─────────────────────────────────────────────────────────────────

    public function loginForm(): void
    {
        if (Auth::eventStaffCheck()) $this->redirect('/event-staff/dashboard');
        $this->renderWith('auth', 'staff/login', ['flash' => $this->flash()]);
    }

    public function login(): void
    {
        $this->verifyCsrf();
        $code     = trim((string)($_POST['event_code'] ?? ''));
        $email    = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');

        $staff = EventStaff::attempt($code, $email, $password);
        if (!$staff) {
            $this->redirect('/event-staff/login', 'Invalid Event Code, email or password.', 'error');
        }
        Auth::eventStaffLogin($staff, EventStaff::privilegesFor((int)$staff['id']));
        $this->redirect('/event-staff/dashboard');
    }

    public function logout(): void
    {
        Auth::eventStaffLogout();
        $this->redirect('/event-staff/login', 'Signed out.');
    }

    public function changePassword(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $current = (string)($_POST['current_password']      ?? '');
        $new     = (string)($_POST['password']              ?? '');
        $confirm = (string)($_POST['password_confirmation'] ?? '');
        $back    = '/event-staff/dashboard';
        if ($current === '' || $new === '' || $confirm === '') {
            $this->redirect($back, 'All three password fields are required.', 'error');
        }
        if (strlen($new) < 8) {
            $this->redirect($back, 'New password must be at least 8 characters.', 'error');
        }
        if ($new !== $confirm) {
            $this->redirect($back, 'New password and confirmation do not match.', 'error');
        }
        if (!password_verify($current, $this->staff['password'])) {
            $this->redirect($back, 'Current password is incorrect.', 'error');
        }
        EventStaff::updatePassword((int)$this->staff['id'], Auth::hashPassword($new));
        $this->redirect($back, 'Password updated successfully.');
    }

    // ── Dashboard ────────────────────────────────────────────────────────────

    public function dashboard(): void
    {
        $this->boot();
        $teamCount = 0;
        if (in_array('team_entry', $this->staff['privileges'], true)) {
            $rows = TeamRegistration::forCreator('event_staff', (int)$this->staff['id'], (int)$this->event['id']);
            $teamCount = count($rows);
        }
        $this->renderWith('staff', 'staff/dashboard', [
            'staff'      => $this->staff,
            'event'      => $this->event,
            'team_count' => $teamCount,
            'flash'      => $this->flash(),
        ]);
    }

    // ── Modular placeholders (later prompts replace the bodies) ──────────────

    public function laneAllocation(): void
    {
        // Lane Allocation is now a shared module served from /lane-allocation
        // (LaneAllocationController). Kept so existing links still resolve.
        $this->boot();
        $this->requirePrivilege('lane_allocation');
        $this->redirect('/lane-allocation');
    }

    public function scoring(): void
    {
        $this->boot();
        $this->requirePrivilege('scoring');
        $this->renderWith('staff', 'staff/placeholder', [
            'staff' => $this->staff,
            'event' => $this->event,
            'title' => 'Scoring',
            'body'  => 'Score entry and management for staff will be enabled here in a follow-up release.',
        ]);
    }

    public function resultReports(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        $this->renderWith('staff', 'staff/placeholder', [
            'staff' => $this->staff,
            'event' => $this->event,
            'title' => 'Result Reports',
            'body'  => 'Generation and display of event results will be enabled here in a follow-up release.',
        ]);
    }
}
