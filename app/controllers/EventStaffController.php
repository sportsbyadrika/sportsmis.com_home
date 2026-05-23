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

    /**
     * GET /event-staff/result-reports/event-rank-list — pick an event
     * category from the dropdown, then surface ranked entries grouped
     * by Sport-Event (one section per event_sport_id). Rank is by
     * Total Score desc, with progressively deeper series-total tie-
     * breaks (last series first, then preceding), and finally by the
     * highest count of shots scoring >= 10.
     */
    public function eventRankList(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');

        $eid   = (int)$this->event['id'];
        $catId = (int)($_GET['category_id'] ?? 0);

        // Available categories for the filter (categories actually
        // present on the event via event_sports).
        $categories = Event::rowsRaw(
            "SELECT DISTINCT sc.id, sc.name, sc.abbreviation
               FROM event_sports es
               JOIN sport_events     se ON se.id = es.sport_event_id
               JOIN sport_categories sc ON sc.id = se.category_id
              WHERE es.event_id = ?
              ORDER BY sc.name",
            [$eid]
        );

        $groups    = [];
        $maxSeries = 0;
        if ($catId > 0) {
            $entries = Event::rowsRaw(
                "SELECT se.id              AS score_entry_id,
                        se.event_sport_id,
                        se.sport_category_id,
                        se.series_count,
                        se.competitor_number,
                        se.grand_total,
                        se.total_penalty,
                        se.inner_ten_count,
                        se.remarks         AS score_remarks,
                        se.notes           AS score_notes,
                        se.lane_status,
                        a.name             AS athlete_name,
                        a.passport_photo,
                        eu.name            AS unit_name,
                        sc.name            AS category_name,
                        sc.abbreviation    AS category_abbr,
                        es.event_code,
                        sev.name           AS sport_event_name,
                        (SELECT GROUP_CONCAT(ss.series_total ORDER BY ss.series_no SEPARATOR ',')
                           FROM score_series ss WHERE ss.score_entry_id = se.id) AS series_totals_csv
                   FROM score_entries se
              LEFT JOIN athletes a            ON a.id  = se.athlete_id
              LEFT JOIN event_units eu        ON eu.id = se.unit_id
              LEFT JOIN sport_categories sc   ON sc.id = se.sport_category_id
              LEFT JOIN event_sports es       ON es.id = se.event_sport_id
              LEFT JOIN sport_events sev      ON sev.id = es.sport_event_id
                  WHERE se.event_id = ?
                    AND se.sport_category_id = ?
                    AND se.lane_status IN ('saved', 'final')",
                [$eid, $catId]
            );

            // No. of 10s — count shots >= 10 across all of an entry's
            // series. Computed in PHP, batched for the page's entries.
            $entryIds = array_map(fn($r) => (int)$r['score_entry_id'], $entries);
            $tensByEntry = [];
            if ($entryIds) {
                $in = implode(',', array_fill(0, count($entryIds), '?'));
                $shotsRows = Event::rowsRaw(
                    "SELECT score_entry_id, shots_json
                       FROM score_series
                      WHERE score_entry_id IN ({$in})",
                    $entryIds
                );
                foreach ($shotsRows as $sr) {
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

            // Hydrate per-row: parsed series array + 10s count + driver
            // for the pivot column count.
            foreach ($entries as &$e) {
                $e['tens_count'] = $tensByEntry[(int)$e['score_entry_id']] ?? 0;
                $arr = [];
                if (!empty($e['series_totals_csv'])) {
                    $arr = array_map('trim', explode(',', (string)$e['series_totals_csv']));
                }
                $e['series_array'] = $arr;
                if (count($arr) > $maxSeries) $maxSeries = count($arr);
                $sc = (int)($e['series_count'] ?? 0);
                if ($sc > $maxSeries) $maxSeries = $sc;
            }
            unset($e);
            if ($maxSeries < 1) $maxSeries = 4;

            // Group by event_sport_id (Sport-Event under the chosen
            // category).
            foreach ($entries as $e) {
                $key = (int)$e['event_sport_id'];
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'event_code'    => $e['event_code'],
                        'sport_event'   => $e['sport_event_name'],
                        'category'      => $e['category_name'],
                        'category_abbr' => $e['category_abbr'],
                        'entries'       => [],
                    ];
                }
                $groups[$key]['entries'][] = $e;
            }

            // Sort each group by the user-specified rank rule.
            foreach ($groups as &$g) {
                usort($g['entries'], function ($a, $b) use ($maxSeries) {
                    // Higher Total Score wins.
                    $aT = (float)($a['grand_total'] ?? 0);
                    $bT = (float)($b['grand_total'] ?? 0);
                    if ($aT != $bT) return $bT <=> $aT;
                    // Tie-break: last series total, then preceding,
                    // down to the first.
                    for ($i = $maxSeries - 1; $i >= 0; $i--) {
                        $av = (float)($a['series_array'][$i] ?? 0);
                        $bv = (float)($b['series_array'][$i] ?? 0);
                        if ($av != $bv) return $bv <=> $av;
                    }
                    // Final tie-break: more shots scoring >= 10.
                    $aTens = (int)($a['tens_count'] ?? 0);
                    $bTens = (int)($b['tens_count'] ?? 0);
                    if ($aTens != $bTens) return $bTens <=> $aTens;
                    return 0;
                });
                $rank = 0;
                foreach ($g['entries'] as $i => $_) {
                    $g['entries'][$i]['rank'] = ++$rank;
                }
            }
            unset($g);
            // Stable ordering of groups by event code.
            uasort($groups, fn($a, $b) =>
                strcmp((string)($a['event_code'] ?? ''), (string)($b['event_code'] ?? '')));
        }

        $this->renderWith('staff', 'staff/result-reports/event-rank-list', [
            'staff'      => $this->staff,
            'event'      => $this->event,
            'categories' => $categories,
            'category_id'=> $catId,
            'groups'     => $groups,
            'max_series' => $maxSeries ?: 4,
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
        $maxSeries  = 0;
        if ($selectedId > 0) {
            foreach ($relays as $r) {
                if ((int)$r['id'] === $selectedId) { $relay = $r; break; }
            }
            if ($relay) {
                $lanes = \Models\ScoreEntry::lanesForRelay($selectedId);
                // Result reports are about competitors, so hide lanes
                // that don't yet carry a competitor number (either on
                // the allocation row or the score entry).
                $lanes = array_values(array_filter($lanes, function ($l) {
                    return !empty($l['competitor_number'])
                        || !empty($l['score_competitor_number']);
                }));

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
                    // Max series count across the relay's lanes drives
                    // the per-series pivot columns in the view.
                    $sc = (int)($l['series_count'] ?? 0);
                    if ($sc > $maxSeries) $maxSeries = $sc;
                    if (!empty($l['series_totals_csv'])) {
                        $parts = explode(',', (string)$l['series_totals_csv']);
                        if (count($parts) > $maxSeries) $maxSeries = count($parts);
                    }
                }
                unset($l);
                // Default to 4 series when the relay has no scored
                // entries yet — keeps the table readable on a blank
                // relay rather than collapsing the Score band.
                if ($maxSeries < 1) $maxSeries = 4;
            }
        }

        $this->renderWith('staff', 'staff/result-reports/relay-result', [
            'staff'      => $this->staff,
            'event'      => $this->event,
            'relays'     => $relays,
            'relay'      => $relay,
            'lanes'      => $lanes,
            'max_series' => $maxSeries,
            'selected'   => $selectedId,
            'flash'      => $this->flash(),
        ]);
    }
}
