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
            // Reflect every team entry on the event (staff portal sees them all).
            $teamCount = count(TeamRegistration::forEvent((int)$this->event['id']));
        }
        $this->renderWith('staff', 'staff/dashboard', [
            'staff'      => $this->staff,
            'event'      => $this->event,
            'team_count' => $teamCount,
            'flash'      => $this->flash(),
        ]);
    }

    // ── Athlete Search ───────────────────────────────────────────────────────

    /**
     * GET /event-staff/search — look up a competitor on this event by
     * competitor number (typed or QR-scanned), athlete name, unit, or
     * mobile number. Available to every signed-in staff member.
     */
    public function search(): void
    {
        $this->boot();

        $by     = (string)($_GET['by']      ?? '');   // competitor | name | unit | mobile
        $q      = trim((string)($_GET['q']  ?? ''));
        $unitId = (int)($_GET['unit_id']    ?? 0);
        $eid    = (int)$this->event['id'];

        $results  = [];
        $searched = false;
        $notice   = '';

        if (in_array($by, ['competitor', 'name', 'unit', 'mobile'], true)) {
            $searched = true;
            $where  = ['er.event_id = ?'];
            $params = [$eid];

            if ($by === 'competitor') {
                // A QR scan typically yields the Competitor-Card URL
                // (…/athlete/registrations/{hash}/card). Resolve that to
                // the registration id; otherwise treat q as a number.
                if ($q !== '' && preg_match('#/athlete/registrations/([A-Za-z0-9]+)/card#', $q, $m)) {
                    $regId = \hid_reg_decode($m[1]);
                    if ($regId > 0) {
                        $where[]  = 'er.id = ?';
                        $params[] = $regId;
                    } else {
                        $where[] = '1 = 0';
                        $notice  = 'The scanned QR code could not be matched to a registration.';
                    }
                } elseif ($q !== '') {
                    $where[]  = 'er.competitor_number = ?';
                    $params[] = (int)preg_replace('/\D+/', '', $q);
                } else {
                    $where[] = '1 = 0';
                }
            } elseif ($by === 'name') {
                if ($q !== '') { $where[] = 'a.name LIKE ?'; $params[] = '%' . $q . '%'; }
                else           { $where[] = '1 = 0'; }
            } elseif ($by === 'unit') {
                if ($unitId > 0) { $where[] = 'er.unit_id = ?'; $params[] = $unitId; }
                else             { $where[] = '1 = 0'; }
            } elseif ($by === 'mobile') {
                if ($q !== '') { $where[] = 'a.mobile LIKE ?'; $params[] = '%' . $q . '%'; }
                else           { $where[] = '1 = 0'; }
            }

            $results = Event::rowsRaw(
                "SELECT er.id AS registration_id, er.competitor_number,
                        er.admin_review_status,
                        a.name AS athlete_name, a.passport_photo, a.mobile,
                        eu.name AS unit_name, eu.address AS unit_address,
                        er.unit_name_other
                   FROM event_registrations er
                   JOIN athletes a       ON a.id  = er.athlete_id
              LEFT JOIN event_units eu   ON eu.id = er.unit_id
                  WHERE " . implode(' AND ', $where) . "
                  ORDER BY a.name
                  LIMIT 200",
                $params
            );
        }

        $units = \Models\EventUnit::forEvent($eid);

        $this->renderWith('staff', 'staff/search', [
            'staff'     => $this->staff,
            'event'     => $this->event,
            'by'        => $by,
            'q'         => $q,
            'unit_id'   => $unitId,
            'units'     => $units,
            'results'   => $results,
            'searched'  => $searched,
            'notice'    => $notice,
            'flash'     => $this->flash(),
        ]);
    }

    /**
     * GET /event-staff/search/{regHash} — full athlete + registration
     * detail for one search result.
     */
    public function searchView(string $regHash): void
    {
        $this->boot();
        $regId = \hid_reg_decode($regHash);
        if ($regId <= 0) $this->abort(404);

        $reg = \Models\EventRegistration::withProfile($regId);
        if (!$reg || (int)$reg['event_id'] !== (int)$this->event['id']) {
            $this->abort(404);
        }

        $athlete = \Models\Athlete::findById((int)$reg['athlete_id']);
        $items   = \Models\EventRegistration::items($regId);

        $age = null;
        if (!empty($reg['date_of_birth'])) {
            try {
                $dob = new \DateTimeImmutable((string)$reg['date_of_birth']);
                $age = (int)$dob->diff(new \DateTimeImmutable('today'))->y;
            } catch (\Throwable $e) { $age = null; }
        }
        $ageCategories = \Models\Athlete::baseAgeCategories($reg['date_of_birth'] ?? null);

        $this->renderWith('staff', 'staff/search-view', [
            'staff'          => $this->staff,
            'event'          => $this->event,
            'reg'            => $reg,
            'athlete'        => $athlete,
            'items'          => $items,
            'age'            => $age,
            'age_categories' => $ageCategories,
            'flash'          => $this->flash(),
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
        $this->renderWith('staff', 'staff/result-reports/index', [
            'staff' => $this->staff,
            'event' => $this->event,
            'flash' => $this->flash(),
        ]);
    }

    /**
     * GET /event-staff/result-reports/relay-result — pick a relay from
     * the dropdown, then surface the per-lane results in lane-number
     * order: lane, photo, comp no, athlete, unit, event category,
     * per-series scores, total penalty, inner tens, grand total.
     */
    public function relayResult(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');

        $eid = (int)$this->event['id'];
        $relays = \Models\Relay::forEvent($eid);

        $selectedId = (int)($_GET['relay_id'] ?? 0);
        $relay      = null;
        $lanes      = [];
        if ($selectedId > 0) {
            foreach ($relays as $r) {
                if ((int)$r['id'] === $selectedId) { $relay = $r; break; }
            }
            if ($relay) {
                $lanes = \Models\ScoreEntry::lanesForRelay($selectedId);

                // No. of 10s — total shots across all series whose value
                // is 10 or higher. Computed in PHP so the rule stays
                // simple and works regardless of MySQL version.
                $entryIds = array_values(array_filter(array_map(
                    fn($l) => (int)($l['score_entry_id'] ?? 0), $lanes
                )));
                $tensByEntry = [];
                if ($entryIds) {
                    $in = implode(',', array_fill(0, count($entryIds), '?'));
                    $seriesRows = Event::rowsRaw(
                        "SELECT score_entry_id, shots_json
                           FROM score_series
                          WHERE score_entry_id IN ({$in})",
                        $entryIds
                    );
                    foreach ($seriesRows as $sr) {
                        $eId = (int)$sr['score_entry_id'];
                        $shots = json_decode((string)($sr['shots_json'] ?? '[]'), true);
                        if (!is_array($shots)) continue;
                        foreach ($shots as $v) {
                            if ($v === null || $v === '') continue;
                            if ((float)$v >= 10.0) {
                                $tensByEntry[$eId] = ($tensByEntry[$eId] ?? 0) + 1;
                            }
                        }
                    }
                }
                foreach ($lanes as &$l) {
                    $l['tens_count'] = $tensByEntry[(int)($l['score_entry_id'] ?? 0)] ?? 0;
                }
                unset($l);
            }
        }

        $this->renderWith('staff', 'staff/result-reports/relay-result', [
            'staff'    => $this->staff,
            'event'    => $this->event,
            'relays'   => $relays,
            'relay'    => $relay,
            'lanes'    => $lanes,
            'selected' => $selectedId,
            'flash'    => $this->flash(),
        ]);
    }
}
