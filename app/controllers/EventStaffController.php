<?php
namespace Controllers;

use Core\{Controller, Auth};
use Models\{EventStaff, Event, Schema, TeamRegistration, TrackConfig, TrackCertificate};

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
        // If they arrived from a main account (athlete/institution), that session
        // is still alive — send them home rather than to the staff login.
        if (Auth::check()) {
            $this->redirect(Auth::homeUrl(), 'Left the staff console.');
        }
        $this->redirect('/event-staff/login', 'Signed out.');
    }

    /**
     * POST /event-staff/enter — bridge from a signed-in MAIN account (athlete
     * or institution) into the Event Staff console WITHOUT the separate
     * event-code login.
     *
     * Authorisation model: the main account is already password-authenticated,
     * and an event administrator granted staff rights to that exact email
     * address. We re-derive the staff row, its event and its privileges from
     * the database (never trusting anything but the staff id from the client)
     * and require the staff email to equal the signed-in account's email. No
     * password or event code is involved — proven email ownership plus the
     * admin's grant is the link. The normal event_staff session is then
     * established, so every downstream check (boot(), requirePrivilege) is
     * unchanged. The main session is left intact for a one-click return.
     */
    public function enterFromAccount(): void
    {
        try { Schema::ensureEventStaff(); } catch (\Throwable $e) {}
        $this->verifyCsrf();
        if (!Auth::check()) {
            $this->redirect('/login', 'Please sign in to continue.', 'warning');
        }
        $accountEmail = strtolower(trim((string)(Auth::user()['email'] ?? '')));
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $staff   = $staffId > 0 ? EventStaff::findById($staffId) : null;

        // Must exist, be active, and belong to THIS account's email.
        if (!$staff
            || ($staff['status'] ?? '') !== 'active'
            || $accountEmail === ''
            || strtolower((string)($staff['email'] ?? '')) !== $accountEmail) {
            $this->redirect(Auth::homeUrl(),
                'That event staff access is not available for your account.', 'error');
        }
        if (!Event::findById((int)$staff['event_id'])) {
            $this->redirect(Auth::homeUrl(), 'That event no longer exists.', 'error');
        }

        Auth::eventStaffLogin($staff, EventStaff::privilegesFor((int)$staff['id']));
        // Remember we arrived from a main account so the staff area can offer a
        // one-click "back to my dashboard" (the main session stays alive).
        $_SESSION['event_staff']['via_account']  = true;
        $_SESSION['event_staff']['account_home'] = Auth::homeUrl();
        $this->redirect('/event-staff/dashboard');
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

        $eventId   = (int)$this->event['id'];
        $athleteId = (int)$reg['athlete_id'];

        // ── Team entries this athlete is a member of for THIS event ──
        $teamEntries = [];
        try {
            $teamEntries = \Models\Event::rowsRaw(
                "SELECT tr.id, tr.team_name, tr.admin_review_status,
                        es.event_code, sev.name AS sport_event_name,
                        sc.name AS category_name
                   FROM team_registration_members trm
                   JOIN team_registrations tr ON tr.id = trm.team_registration_id
              LEFT JOIN event_sports     es  ON es.id  = tr.event_sport_id
              LEFT JOIN sport_events     sev ON sev.id = es.sport_event_id
              LEFT JOIN sport_categories sc  ON sc.id  = sev.category_id
                  WHERE trm.athlete_id = ? AND tr.event_id = ?
                  ORDER BY tr.id DESC",
                [$athleteId, $eventId]
            );
            foreach ($teamEntries as &$te) {
                $te['members'] = \Models\Event::rowsRaw(
                    "SELECT trm.athlete_id, a.name AS athlete_name,
                            er.competitor_number
                       FROM team_registration_members trm
                       JOIN athletes a ON a.id = trm.athlete_id
                  LEFT JOIN event_registrations er ON er.id = trm.registration_id
                      WHERE trm.team_registration_id = ?
                      ORDER BY a.name",
                    [(int)$te['id']]
                );
            }
            unset($te);
        } catch (\Throwable $e) { /* team tables absent */ }

        // ── Results: one row per registered event-sport, scored via the
        //    matching category. score_entries store the score keyed by
        //    sport_category, so multiple event-sports under the same
        //    category share one score row.
        $resultRows = \Models\Event::rowsRaw(
            "SELECT eri.event_sport_id, es.event_code, sev.name AS sport_event_name,
                    sev.category_id, sc.name AS category_name,
                    se.id AS score_entry_id, se.grand_total, se.total_penalty,
                    se.remarks, se.score_type,
                    r.relay_number, r.relay_date, r.match_time
               FROM event_registration_items eri
               JOIN event_sports     es  ON es.id  = eri.event_sport_id
          LEFT JOIN sport_events     sev ON sev.id = es.sport_event_id
          LEFT JOIN sport_categories sc  ON sc.id  = sev.category_id
          LEFT JOIN score_entries    se  ON se.event_id = ?
                                       AND se.athlete_id = ?
                                       AND se.sport_category_id = sev.category_id
                                       AND se.lane_status IN ('saved','final')
          LEFT JOIN event_relays     r   ON r.id = se.relay_id
              WHERE eri.registration_id = ?
              ORDER BY sc.name, sev.name, es.event_code",
            [$eventId, $athleteId, $regId]
        );

        $results = [];
        foreach ($resultRows as $row) {
            $r = [
                'event_code'       => (string)($row['event_code']       ?? ''),
                'sport_event_name' => (string)($row['sport_event_name'] ?? ''),
                'relay_number'     => (string)($row['relay_number']     ?? ''),
                'relay_date'       => (string)($row['relay_date']       ?? ''),
                'match_time'       => (string)($row['match_time']       ?? ''),
                'series'           => [],
                'penalty'          => null,
                'tens_count'       => null,
                'final_score'      => null,
                'remarks'          => (string)($row['remarks']          ?? ''),
            ];
            $eId = (int)($row['score_entry_id'] ?? 0);
            if ($eId > 0) {
                $seriesRows = \Models\Event::rowsRaw(
                    "SELECT series_no, sub_total, inner_tens
                       FROM score_series WHERE score_entry_id = ? ORDER BY series_no",
                    [$eId]
                );
                $r['series']      = $seriesRows;
                $r['penalty']     = $row['total_penalty'] !== null ? (float)$row['total_penalty'] : null;
                $r['final_score'] = $row['grand_total']   !== null ? (float)$row['grand_total']   : null;
                // No. of 10x — series_sum mode keeps the count in
                // score_series.inner_tens; shot mode counts shots >= 10
                // in shots_json (relay-result already uses this rule).
                if (($row['score_type'] ?? '') === 'series_sum') {
                    $tot = 0;
                    foreach ($seriesRows as $sr) $tot += (int)($sr['inner_tens'] ?? 0);
                    $r['tens_count'] = $tot;
                } else {
                    $shotsAll = \Models\Event::rowsRaw(
                        "SELECT shots_json FROM score_series WHERE score_entry_id = ?",
                        [$eId]
                    );
                    $tot = 0;
                    foreach ($shotsAll as $sr) {
                        $shots = json_decode((string)($sr['shots_json'] ?? '[]'), true);
                        if (!is_array($shots)) continue;
                        foreach ($shots as $v) {
                            if ($v !== null && $v !== '' && (float)$v >= 10.0) $tot++;
                        }
                    }
                    $r['tens_count'] = $tot;
                }
            }
            $results[] = $r;
        }

        // ── Round-wise results (Athletics / Skating) ──────────────────────────
        // For each track/field event the athlete is registered in, the per-round
        // placement (heat / order, result value, rank, qualified) for this
        // athlete's registration. Drives the per-event modal in the view.
        $trackResults = [];
        try {
            Schema::ensureTrackConfig();
            $teRows = \Models\Event::rowsRaw(
                "SELECT es.id AS esid, es.event_code, es.track_event_type,
                        es.track_result_unit, es.track_num_laps,
                        sev.name AS sport_event_name, sc.name AS category_name,
                        ac.name AS age_name, sev.gender
                   FROM event_registration_items eri
                   JOIN event_sports     es  ON es.id  = eri.event_sport_id
              LEFT JOIN sport_events     sev ON sev.id = es.sport_event_id
              LEFT JOIN sport_categories sc  ON sc.id  = sev.category_id
              LEFT JOIN age_categories   ac  ON ac.id  = sev.age_category_id
                  WHERE eri.registration_id = ? AND es.track_event_type IS NOT NULL
                  ORDER BY sc.name, sev.name, es.event_code",
                [$regId]
            );
            if ($teRows) {
                $esids   = array_map(fn($r) => (int)$r['esid'], $teRows);
                $roundMap = TrackConfig::roundsForMany($esids);
                $assignByRound = [];
                foreach (\Models\Event::rowsRaw(
                    "SELECT round_id, heat_no, track_no, result_time, result_rank, is_qualified, is_published
                       FROM track_heat_assignments WHERE registration_id = ?", [$regId]) as $ar) {
                    $assignByRound[(int)$ar['round_id']] = $ar;
                }
                foreach ($teRows as $te) {
                    $esid    = (int)$te['esid'];
                    $isField = (($te['track_event_type'] ?? '') === 'field');
                    $rr = []; $hasResult = false; $placed = false;
                    foreach (($roundMap[$esid] ?? []) as $rd) {
                        $a = $assignByRound[(int)$rd['id']] ?? null;
                        if ($a) {
                            $placed = true;
                            if (($a['result_time'] ?? '') !== '' || $a['result_rank'] !== null) $hasResult = true;
                        }
                        $rr[] = [
                            'round_name'   => (string)$rd['round_name'],
                            'assign'       => $a,
                            'participants' => (int)($rd['assigned_count'] ?? 0),
                            'results'      => (int)($rd['result_count'] ?? 0),
                        ];
                    }
                    $trackResults[] = [
                        'esid'        => $esid,
                        'event'       => trim((string)($te['sport_event_name'] ?? '')) ?: (string)($te['event_code'] ?? ''),
                        'category'    => (string)($te['category_name'] ?? ''),
                        'age'         => (string)($te['age_name'] ?? ''),
                        'gender'      => (string)($te['gender'] ?? ''),
                        'is_field'    => $isField,
                        'result_unit' => (string)($te['track_result_unit'] ?? 'time'),
                        'num_laps'    => (int)($te['track_num_laps'] ?? 0),
                        'rounds'      => $rr,
                        'placed'      => $placed,
                        'has_result'  => $hasResult,
                    ];
                }
            }
        } catch (\Throwable $e) { $trackResults = []; }

        $this->renderWith('staff', 'staff/search-view', [
            'staff'          => $this->staff,
            'event'          => $this->event,
            'reg'            => $reg,
            'athlete'        => $athlete,
            'items'          => $items,
            'team_entries'   => $teamEntries,
            'results'        => $results,
            'track_results'  => $trackResults,
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

        $eid     = (int)$this->event['id'];
        $catId   = (int)($_GET['category_id'] ?? 0);
        $showMqs = !empty($_GET['show_mqs']);

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
            // ── Step 1: every approved (athlete, sport-event) pair on
            //    this event under the chosen category. An athlete
            //    registered for several sport-events in the same
            //    category appears once per sport-event here.
            $rows = Event::rowsRaw(
                "SELECT es.id                AS event_sport_id,
                        es.event_code,
                        es.mqs                AS mqs,
                        sev.name              AS sport_event_name,
                        es.series_count       AS es_series_count,
                        sc.id                 AS category_id,
                        sc.name               AS category_name,
                        sc.abbreviation       AS category_abbr,
                        er.id                 AS registration_id,
                        er.athlete_id,
                        er.competitor_number  AS reg_competitor_number,
                        a.name                AS athlete_name,
                        a.passport_photo,
                        eu.name               AS unit_name
                   FROM event_sports es
                   JOIN sport_events sev      ON sev.id = es.sport_event_id
                   JOIN sport_categories sc   ON sc.id = sev.category_id
                   JOIN event_registration_items eri ON eri.event_sport_id = es.id
                   JOIN event_registrations er  ON er.id = eri.registration_id
                                              AND er.admin_review_status = 'approved'
                   JOIN athletes a            ON a.id = er.athlete_id
              LEFT JOIN event_units eu        ON eu.id = er.unit_id
                  WHERE es.event_id = ?
                    AND sc.id      = ?
                  ORDER BY es.event_code, a.name",
                [$eid, $catId]
            );

            // ── Step 2: pull every score entry on this event + category
            //    once, keyed by athlete_id. The same row gets attached
            //    to whichever event_sport buckets the athlete is
            //    registered in.
            $scoreRows = Event::rowsRaw(
                "SELECT se.id              AS score_entry_id,
                        se.athlete_id,
                        se.competitor_number AS score_competitor_number,
                        se.series_count,
                        se.grand_total,
                        se.total_penalty,
                        se.inner_ten_count,
                        se.remarks         AS score_remarks,
                        se.notes           AS score_notes,
                        (SELECT GROUP_CONCAT(ss.sub_total ORDER BY ss.series_no SEPARATOR ',')
                           FROM score_series ss WHERE ss.score_entry_id = se.id) AS series_subs_csv
                   FROM score_entries se
                  WHERE se.event_id = ?
                    AND se.sport_category_id = ?
                    AND se.lane_status IN ('saved', 'final')",
                [$eid, $catId]
            );

            $scoreByAthlete = [];
            $entryIds = [];
            foreach ($scoreRows as $s) {
                $aId = (int)$s['athlete_id'];
                if ($aId <= 0) continue;
                // If somehow two scores exist for an athlete on the same
                // event+category, keep the higher one.
                if (isset($scoreByAthlete[$aId])
                    && (float)$scoreByAthlete[$aId]['grand_total'] >= (float)$s['grand_total']) {
                    continue;
                }
                $scoreByAthlete[$aId] = $s;
                $entryIds[$aId]       = (int)$s['score_entry_id'];
            }

            // No. of 10s — shots >= 10 across the entry's series. For
            // entries saved in series_sum mode, shots_json carries only
            // the sub-total, so fall back to score_series.inner_tens
            // (where the operator typed the per-series count).
            $tensByEntry = [];
            $uniqueEntryIds = array_values(array_unique($entryIds));
            if ($uniqueEntryIds) {
                $in = implode(',', array_fill(0, count($uniqueEntryIds), '?'));
                $shotsRows = Event::rowsRaw(
                    "SELECT ss.score_entry_id, ss.shots_json, ss.inner_tens, se.score_type
                       FROM score_series ss
                       JOIN score_entries se ON se.id = ss.score_entry_id
                      WHERE ss.score_entry_id IN ({$in})",
                    $uniqueEntryIds
                );
                foreach ($shotsRows as $sr) {
                    $eId = (int)$sr['score_entry_id'];
                    if (($sr['score_type'] ?? '') === 'series_sum') {
                        $tensByEntry[$eId] = ($tensByEntry[$eId] ?? 0) + (int)($sr['inner_tens'] ?? 0);
                        continue;
                    }
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

            // ── Step 3: build per-event-sport buckets, attaching the
            //    matching score to each registration row.
            foreach ($rows as $r) {
                $aId = (int)$r['athlete_id'];
                $key = (int)$r['event_sport_id'];
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'event_code'    => $r['event_code'],
                        'sport_event'   => $r['sport_event_name'],
                        'category'      => $r['category_name'],
                        'category_abbr' => $r['category_abbr'],
                        'mqs'           => ($r['mqs'] !== null && $r['mqs'] !== '')
                                            ? (float)$r['mqs'] : null,
                        'entries'       => [],
                    ];
                }
                $score = $scoreByAthlete[$aId] ?? null;
                $seriesArr = [];
                if ($score && !empty($score['series_subs_csv'])) {
                    $seriesArr = array_map('trim', explode(',', (string)$score['series_subs_csv']));
                }
                $scCount = (int)($r['es_series_count'] ?? 0);
                if ($score) {
                    $scCount = max($scCount, (int)($score['series_count'] ?? 0), count($seriesArr));
                }
                if ($scCount > $maxSeries) $maxSeries = $scCount;

                $sub = 0.0;
                foreach ($seriesArr as $sv) { if ($sv !== '') $sub += (float)$sv; }
                $groups[$key]['entries'][] = [
                    'competitor_number' => $r['reg_competitor_number']
                                            ?: ($score['score_competitor_number'] ?? null),
                    'athlete_name'      => $r['athlete_name'],
                    'unit_name'         => $r['unit_name'],
                    'has_score'         => $score !== null,
                    'grand_total'       => $score['grand_total']      ?? null,
                    'sub_total'         => $score ? $sub : null,
                    'total_penalty'     => $score['total_penalty']    ?? null,
                    'series_array'      => $seriesArr,
                    'tens_count'        => $score ? ($tensByEntry[(int)$score['score_entry_id']] ?? 0) : 0,
                    'score_remarks'     => $score['score_remarks']   ?? '',
                    'score_notes'       => $score['score_notes']     ?? '',
                ];
            }
            if ($maxSeries < 1) $maxSeries = 4;

            // ── Step 4: sort each group by the rank rule and assign
            //    ranks. Only score-bearing entries whose remarks are NOT
            //    DNS/DNF/Disqualified get a rank number — those flagged
            //    competitors and the un-scored ones sit at the bottom
            //    with rank = null.
            $unranked = ['dns', 'dnf', 'disqualified'];
            foreach ($groups as &$g) {
                usort($g['entries'], function ($a, $b) use ($maxSeries, $unranked) {
                    $aRankable = ($a['has_score'] ?? false)
                        && !in_array((string)($a['score_remarks'] ?? ''), $unranked, true);
                    $bRankable = ($b['has_score'] ?? false)
                        && !in_array((string)($b['score_remarks'] ?? ''), $unranked, true);
                    if ($aRankable !== $bRankable) return $bRankable <=> $aRankable;
                    // Higher Total Score wins.
                    $aT = (float)($a['grand_total'] ?? 0);
                    $bT = (float)($b['grand_total'] ?? 0);
                    if ($aT != $bT) return $bT <=> $aT;
                    // Tie-break: last series total back to the first.
                    for ($i = $maxSeries - 1; $i >= 0; $i--) {
                        $av = (float)($a['series_array'][$i] ?? 0);
                        $bv = (float)($b['series_array'][$i] ?? 0);
                        if ($av != $bv) return $bv <=> $av;
                    }
                    // Final tie-break: more shots scoring >= 10.
                    $aTens = (int)($a['tens_count'] ?? 0);
                    $bTens = (int)($b['tens_count'] ?? 0);
                    if ($aTens != $bTens) return $bTens <=> $aTens;
                    return strcmp((string)$a['athlete_name'], (string)$b['athlete_name']);
                });
                $rank = 0;
                foreach ($g['entries'] as $i => $_) {
                    $hasScore = !empty($g['entries'][$i]['has_score']);
                    $remarks  = (string)($g['entries'][$i]['score_remarks'] ?? '');
                    if ($hasScore && !in_array($remarks, $unranked, true)) {
                        $g['entries'][$i]['rank'] = ++$rank;
                    } else {
                        $g['entries'][$i]['rank'] = null;
                    }
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
            'show_mqs'   => $showMqs,
            'flash'      => $this->flash(),
        ]);
    }

    /**
     * GET /event-staff/result-reports/track-rank-list — Athletics / Skating
     * event-wise rank list. Filters: event category + age category. For each
     * matching sport-event, a table of participants with their round-wise
     * (Time / Rank / Qualified) results across every configured round.
     */
    public function trackRankList(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}

        $eid   = (int)$this->event['id'];
        $catId = (int)($_GET['category_id'] ?? 0);
        $ageId = (int)($_GET['age_category_id'] ?? 0);

        $data = $this->buildTrackRankList($eid, $catId, $ageId);
        $this->renderWith('staff', 'staff/result-reports/track-rank-list', [
            'staff'           => $this->staff,
            'event'           => $this->event,
            'categories'      => $data['categories'],
            'age_categories'  => $data['age_categories'],
            'category_id'     => $catId,
            'age_category_id' => $ageId,
            'groups'          => $data['groups'],
            'flash'           => $this->flash(),
        ]);
    }

    /** GET /event-staff/result-reports/track-rank-list/print — printable (landscape). */
    public function trackRankListPrint(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}

        $eid   = (int)$this->event['id'];
        $catId = (int)($_GET['category_id'] ?? 0);
        $ageId = (int)($_GET['age_category_id'] ?? 0);
        $data  = $this->buildTrackRankList($eid, $catId, $ageId);
        $event  = $this->event;
        $groups = $data['groups'];
        require APP_ROOT . '/views/staff/result-reports/track-rank-list-print.php';
    }

    /**
     * Build the track rank-list: filter option lists plus, when a category is
     * chosen, one group per sport-event with its ordered rounds and the
     * participants' round-wise results.
     */
    private function buildTrackRankList(int $eid, int $catId, int $ageId): array
    {
        $categories = Event::rowsRaw(
            "SELECT DISTINCT sc.id, sc.name, sc.abbreviation
               FROM event_sports es
               JOIN sport_events     se ON se.id = es.sport_event_id
               JOIN sport_categories sc ON sc.id = se.category_id
              WHERE es.event_id = ?
              ORDER BY sc.name",
            [$eid]
        );
        $ageCategories = Event::rowsRaw(
            "SELECT DISTINCT ac.id, ac.name, ac.sort_order
               FROM event_sports es
               JOIN sport_events   se ON se.id = es.sport_event_id
               JOIN age_categories ac ON ac.id = se.age_category_id
              WHERE es.event_id = ?
              ORDER BY (ac.sort_order IS NULL), ac.sort_order, ac.name",
            [$eid]
        );

        $groups = [];
        // Filter on EITHER an event category OR an age category (or both).
        if ($catId > 0 || $ageId > 0) {
            $params = [$eid];
            $where  = '';
            if ($catId > 0) { $where .= ' AND sc.id = ?';                $params[] = $catId; }
            if ($ageId > 0) { $where .= ' AND sev.age_category_id = ?';  $params[] = $ageId; }
            $events = Event::rowsRaw(
                "SELECT es.id AS esid, es.event_code,
                        sev.name AS sport_event_name, sev.gender AS gender,
                        sc.name AS category_name, sc.abbreviation AS category_abbr,
                        ac.name AS age_name, ac.sort_order AS age_sort
                   FROM event_sports es
                   JOIN sport_events     sev ON sev.id = es.sport_event_id
                   JOIN sport_categories sc  ON sc.id  = sev.category_id
              LEFT JOIN age_categories   ac  ON ac.id  = sev.age_category_id
                  WHERE es.event_id = ?{$where}
                  ORDER BY (ac.sort_order IS NULL), ac.sort_order, ac.name, es.event_code, sev.gender",
                $params
            );

            foreach ($events as $ev) {
                $esid   = (int)$ev['esid'];
                $rounds = TrackConfig::roundsFor($esid);   // ordered by round_order
                if (empty($rounds)) continue;              // no rounds → nothing to rank
                $roundIds = array_map(fn($r) => (int)$r['id'], $rounds);
                $in = implode(',', array_fill(0, count($roundIds), '?'));

                $arows = Event::rowsRaw(
                    "SELECT tha.round_id, tha.registration_id,
                            tha.result_time, tha.result_rank, tha.is_qualified,
                            er.competitor_number, a.name AS athlete_name, eu.name AS unit_name
                       FROM track_heat_assignments tha
                       JOIN event_registrations er ON er.id = tha.registration_id
                       JOIN athletes a             ON a.id = er.athlete_id
                  LEFT JOIN event_units eu         ON eu.id = er.unit_id
                      WHERE tha.round_id IN ({$in})",
                    $roundIds
                );

                // round_id -> its order index (0-based) for sorting.
                $orderOf = [];
                foreach ($rounds as $i => $r) { $orderOf[(int)$r['id']] = $i; }

                $athletes = [];
                foreach ($arows as $r) {
                    $rid = (int)$r['registration_id'];
                    if (!isset($athletes[$rid])) {
                        $athletes[$rid] = [
                            'competitor_number' => (int)($r['competitor_number'] ?? 0),
                            'athlete_name'      => (string)($r['athlete_name'] ?? ''),
                            'unit_name'         => (string)($r['unit_name'] ?? ''),
                            'results'           => [],   // round_id => [time,rank,qualified]
                            'top_round'         => -1,
                            'top_rank'          => PHP_INT_MAX,
                        ];
                    }
                    $ridRound = (int)$r['round_id'];
                    $rank = (int)($r['result_rank'] ?? 0);
                    $athletes[$rid]['results'][$ridRound] = [
                        'time'      => (string)($r['result_time'] ?? ''),
                        'rank'      => $rank,
                        'qualified' => !empty($r['is_qualified']),
                    ];
                    // Track the latest round (highest order) with a rank for sorting.
                    $ord = $orderOf[$ridRound] ?? -1;
                    if ($rank > 0 && $ord >= $athletes[$rid]['top_round']) {
                        if ($ord > $athletes[$rid]['top_round'] || $rank < $athletes[$rid]['top_rank']) {
                            $athletes[$rid]['top_round'] = $ord;
                            $athletes[$rid]['top_rank']  = $rank;
                        }
                    }
                }

                // Finalists (deepest round reached, best rank) first; unranked last by name.
                $list = array_values($athletes);
                usort($list, function ($x, $y) {
                    if ($x['top_round'] !== $y['top_round']) return $y['top_round'] <=> $x['top_round'];
                    if ($x['top_rank']  !== $y['top_rank'])  return $x['top_rank']  <=> $y['top_rank'];
                    return strcasecmp($x['athlete_name'], $y['athlete_name']);
                });

                $groups[] = [
                    'event_code'    => (string)($ev['event_code'] ?? ''),
                    'sport_event'   => trim((string)($ev['sport_event_name'] ?? '')) ?: (string)($ev['event_code'] ?? ''),
                    'category'      => (string)($ev['category_name'] ?? ''),
                    'category_abbr' => (string)($ev['category_abbr'] ?? ''),
                    'age_name'      => (string)($ev['age_name'] ?? ''),
                    'gender'        => (string)($ev['gender'] ?? ''),
                    'rounds'        => array_map(fn($r) => [
                        'id' => (int)$r['id'], 'name' => (string)$r['round_name'],
                    ], $rounds),
                    'athletes'      => $list,
                ];
            }
        }

        return ['categories' => $categories, 'age_categories' => $ageCategories, 'groups' => $groups];
    }

    /**
     * GET /event-staff/result-reports/team-results — enter Athletics / Skating
     * team-entry results (time, position, qualified) per team event.
     */
    public function trackTeamResults(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        try { Schema::ensureTeamEntry(); }   catch (\Throwable $e) {}
        try { Schema::ensureTrackConfig(); }  catch (\Throwable $e) {}

        $eid = (int)$this->event['id'];
        $events = Event::rowsRaw(
            "SELECT es.id AS esid, es.event_code, sev.name AS sport_event_name, sev.gender AS gender,
                    sc.name AS category_name, sc.abbreviation AS category_abbr,
                    ac.name AS age_name, ac.sort_order AS age_sort
               FROM event_sports es
               JOIN sport_events     sev ON sev.id = es.sport_event_id
               JOIN sport_categories sc  ON sc.id  = sev.category_id
          LEFT JOIN age_categories   ac  ON ac.id  = sev.age_category_id
              WHERE es.event_id = ?
                AND EXISTS (SELECT 1 FROM team_registrations tr
                             WHERE tr.event_sport_id = es.id AND tr.admin_review_status = 'approved')
              ORDER BY (ac.sort_order IS NULL), ac.sort_order, ac.name, es.event_code, sev.gender",
            [$eid]
        );
        $groups = [];
        foreach ($events as $ev) {
            $teams = Event::rowsRaw(
                "SELECT tr.id, tr.team_name, tr.result_time, tr.result_rank, tr.is_qualified, tr.is_published,
                        eu.name AS unit_name,
                        (SELECT GROUP_CONCAT(am.name ORDER BY m.position, m.id SEPARATOR ', ')
                           FROM team_registration_members m
                           JOIN athletes am ON am.id = m.athlete_id
                          WHERE m.team_registration_id = tr.id) AS members
                   FROM team_registrations tr
              LEFT JOIN event_units eu ON eu.id = tr.unit_id
                  WHERE tr.event_id = ? AND tr.event_sport_id = ? AND tr.admin_review_status = 'approved'
                  ORDER BY (tr.result_rank IS NULL OR tr.result_rank = 0), tr.result_rank, tr.team_name",
                [$eid, (int)$ev['esid']]
            );
            $ev['teams'] = $teams;
            $groups[] = $ev;
        }

        $this->renderWith('staff', 'staff/result-reports/track-team-results', [
            'staff'  => $this->staff,
            'event'  => $this->event,
            'groups' => $groups,
            'flash'  => $this->flash(),
        ]);
    }

    /** POST /event-staff/result-reports/team-results/save — save one team event's results. */
    public function trackTeamResultsSave(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        $this->verifyCsrf();
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}

        $eid   = (int)$this->event['id'];
        $times = (array)($_POST['time']      ?? []);
        $ranks = (array)($_POST['rank']      ?? []);
        $quals = (array)($_POST['qualified'] ?? []);
        $published = !empty($_POST['published']);   // one publish switch per event form
        $saved = 0;
        foreach ($times as $tid => $t) {
            $tid  = (int)$tid;
            if ($tid <= 0) continue;
            $rank = (int)($ranks[$tid] ?? 0);
            $qual = !empty($quals[$tid]);
            TeamRegistration::saveResult($tid, $eid, (string)$t, $rank, $qual, $published);
            $saved++;
        }
        $this->redirect('/event-staff/result-reports/team-results',
            'Saved ' . $saved . ' team result' . ($saved === 1 ? '' : 's')
            . ($published ? ' · published' : '') . '.');
    }

    // ── Athletics / Skating certificates (overlay onto pre-printed paper) ──────

    /** Variable fields printed on each certificate: key => [label, defX, defY, defSize]. */
    /** The four certificate date formats offered in the layout config. */
    private const CERT_DATE_FORMATS = ['d M Y', 'd F Y', 'd-m-Y', 'd/m/Y'];

    private function trackCertFieldDefs(string $type): array
    {
        if ($type === 'appreciation') {
            return [
                'name'        => ['Name of Athlete',              150, 70, 16],
                'school'      => ['Name of School / Institution', 150, 92, 14],
                'event'       => ['Name of Event',                150, 114, 14],
                'event_label' => ['Event Label',                  150, 126, 14],
                'date'        => ['Date',                          55, 150, 12],
                'cert_no'     => ['Certificate Number',           235, 30, 11],
                'const1'      => ['Constant Value 1',             150, 170, 12],
                'const2'      => ['Constant Value 2',             150, 182, 12],
                'const3'      => ['Constant Value 3',             150, 194, 12],
            ];
        }
        return [ // merit
            'name'        => ['Name of Athlete',            150, 66, 16],
            'school'      => ['Name of Institution',        150, 88, 14],
            'prize'       => ['Prize (First/Second/Third)',  90, 110, 14],
            'event'       => ['Name of Event',              170, 110, 14],
            'event_label' => ['Event Label',                170, 124, 14],
            'date'        => ['Date',                         55, 150, 12],
            'cert_no'     => ['Certificate Number',         235, 30, 11],
            'const1'      => ['Constant Value 1',           150, 170, 12],
            'const2'      => ['Constant Value 2',           150, 182, 12],
            'const3'      => ['Constant Value 3',           150, 194, 12],
        ];
    }

    /** Decode the stored cert config, filling defaults for any missing piece. */
    private function trackCertConfig(string $type): array
    {
        $col = $type === 'appreciation' ? 'track_cert_appr_config' : 'track_cert_merit_config';
        $cfg = json_decode((string)($this->event[$col] ?? ''), true);
        if (!is_array($cfg)) $cfg = [];
        $cfg += ['orientation' => 'landscape', 'const1_text' => '', 'const2_text' => '', 'const3_text' => '',
                 'cert_date' => '', 'date_format' => 'd M Y', 'font' => 'Georgia',
                 'cert_prefix' => '', 'cert_seq_start' => 1, 'cert_suffix' => '', 'fields' => []];
        $cfg['orientation'] = $cfg['orientation'] === 'portrait' ? 'portrait' : 'landscape';
        // Only the four offered date formats are honoured.
        if (!in_array($cfg['date_format'], self::CERT_DATE_FORMATS, true)) $cfg['date_format'] = 'd M Y';
        $cfg['font'] = trim((string)$cfg['font']) !== '' ? (string)$cfg['font'] : 'Georgia';
        $saved = is_array($cfg['fields']) ? $cfg['fields'] : [];
        $fields = [];
        foreach ($this->trackCertFieldDefs($type) as $k => $d) {
            $f = is_array($saved[$k] ?? null) ? $saved[$k] : [];
            $fields[$k] = [
                'x'       => isset($f['x'])    ? (float)$f['x']    : (float)$d[1],
                'y'       => isset($f['y'])    ? (float)$f['y']    : (float)$d[2],
                'size'    => isset($f['size']) ? (float)$f['size'] : (float)$d[3],
                'enabled' => array_key_exists('enabled', $f) ? (bool)$f['enabled'] : true,
                'bold'    => !empty($f['bold']),
                'italic'  => !empty($f['italic']),
            ];
        }
        $cfg['fields'] = $fields;
        return $cfg;
    }

    /** GET config page for a certificate type. */
    private function trackCertConfigPage(string $type): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}
        $eid = (int)$this->event['id'];
        $categories = Event::rowsRaw(
            "SELECT DISTINCT sc.id, sc.name, sc.abbreviation
               FROM event_sports es JOIN sport_events se ON se.id = es.sport_event_id
               JOIN sport_categories sc ON sc.id = se.category_id
              WHERE es.event_id = ? ORDER BY sc.name", [$eid]);
        $ageCategories = Event::rowsRaw(
            "SELECT DISTINCT ac.id, ac.name, ac.sort_order
               FROM event_sports es JOIN sport_events se ON se.id = es.sport_event_id
               JOIN age_categories ac ON ac.id = se.age_category_id
              WHERE es.event_id = ? ORDER BY (ac.sort_order IS NULL), ac.sort_order, ac.name", [$eid]);
        // Every sport-event, tagged with its category / age for client-side filtering.
        $events = Event::rowsRaw(
            "SELECT es.id AS esid, es.event_code, sev.name AS sport_event_name, sev.gender,
                    sc.id AS category_id, sc.name AS category_name,
                    ac.id AS age_id, ac.name AS age_name, ac.sort_order AS age_sort
               FROM event_sports es
               JOIN sport_events     sev ON sev.id = es.sport_event_id
               JOIN sport_categories sc  ON sc.id  = sev.category_id
          LEFT JOIN age_categories   ac  ON ac.id  = sev.age_category_id
              WHERE es.event_id = ?
              ORDER BY (ac.sort_order IS NULL), ac.sort_order, ac.name, sc.name, es.event_code, sev.gender", [$eid]);
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}
        // Units with approved participants (for the "Generate by Unit" option).
        $units = Event::rowsRaw(
            "SELECT eu.id, eu.name, COUNT(DISTINCT er.athlete_id) AS cnt
               FROM event_units eu
               JOIN event_registrations er ON er.unit_id = eu.id
                                          AND er.event_id = ? AND er.admin_review_status = 'approved'
              GROUP BY eu.id, eu.name
              ORDER BY eu.name", [$eid]);
        $this->renderWith('staff', 'staff/result-reports/track-certificate', [
            'staff'          => $this->staff,
            'event'          => $this->event,
            'cert_type'      => $type,
            'defs'           => $this->trackCertFieldDefs($type),
            'config'         => $this->trackCertConfig($type),
            'categories'     => $categories,
            'age_categories' => $ageCategories,
            'events'         => $events,
            'units'          => $units,
            'final_counts'   => $this->trackFinalResultCounts($eid),
            'issued'         => TrackCertificate::forEvent($eid, $type),
            'flash'          => $this->flash(),
        ]);
    }

    public function trackMeritCert(): void { $this->trackCertConfigPage('merit'); }
    public function trackApprCert(): void  { $this->trackCertConfigPage('appreciation'); }

    /** POST — save a certificate type's layout config. */
    public function trackCertSave(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        $this->verifyCsrf();
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}
        $type = ($_POST['cert_type'] ?? '') === 'appreciation' ? 'appreciation' : 'merit';
        $defs = $this->trackCertFieldDefs($type);
        $fields = [];
        foreach ($defs as $k => $d) {
            $fields[$k] = [
                'x'       => (float)($_POST["x_$k"]    ?? $d[1]),
                'y'       => (float)($_POST["y_$k"]    ?? $d[2]),
                'size'    => (float)($_POST["size_$k"] ?? $d[3]),
                'enabled' => !empty($_POST["en_$k"]),
                'bold'    => !empty($_POST["bold_$k"]),
                'italic'  => !empty($_POST["italic_$k"]),
            ];
        }
        $dateFmt = (string)($_POST['date_format'] ?? 'd M Y');
        if (!in_array($dateFmt, self::CERT_DATE_FORMATS, true)) $dateFmt = 'd M Y';
        $font = trim((string)($_POST['font'] ?? ''));
        $cfg = [
            'orientation'    => ($_POST['orientation'] ?? '') === 'portrait' ? 'portrait' : 'landscape',
            'const1_text'    => trim((string)($_POST['const1_text'] ?? '')),
            'const2_text'    => trim((string)($_POST['const2_text'] ?? '')),
            'const3_text'    => trim((string)($_POST['const3_text'] ?? '')),
            'cert_date'      => trim((string)($_POST['cert_date'] ?? '')),
            'date_format'    => $dateFmt,
            'font'           => $font !== '' ? $font : 'Georgia',
            'cert_prefix'    => trim((string)($_POST['cert_prefix'] ?? '')),
            'cert_seq_start' => max(1, (int)($_POST['cert_seq_start'] ?? 1)),
            'cert_suffix'    => trim((string)($_POST['cert_suffix'] ?? '')),
            'fields'         => $fields,
        ];
        $col = $type === 'appreciation' ? 'track_cert_appr_config' : 'track_cert_merit_config';
        Event::updatePartial((int)$this->event['id'], [$col => json_encode($cfg)]);
        $url = $type === 'appreciation'
             ? '/event-staff/result-reports/appreciation-certificate'
             : '/event-staff/result-reports/merit-certificate';
        $this->redirect($url, 'Certificate layout saved.');
    }

    /** GET — generate the merit certificates (winners) as an overlay print sheet. */
    public function trackMeritCertPrint(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}
        $eid   = (int)$this->event['id'];
        $catId = (int)($_GET['category_id'] ?? 0);
        $ageId = (int)($_GET['age_category_id'] ?? 0);
        $pick  = array_filter(array_map('intval', (array)($_GET['esid'] ?? [])));
        $cfg   = $this->trackCertConfig('merit');
        // Certificates are generated from entered results — published or not.
        $tally = $this->buildTrackMedalTally($eid, $catId, $ageId, false);
        $placeName = [1 => 'First', 2 => 'Second', 3 => 'Third'];
        $certs = []; $issuedIds = [];
        foreach ($tally['events'] as $ev) {
            if ($pick && !in_array((int)$ev['esid'], $pick, true)) continue;
            for ($rk = 1; $rk <= 3; $rk++) {
                $list = $ev['places'][$rk] ?? [];
                if (!is_array($list)) $list = $list ? [$list] : [];
                $evLabel = trim((string)($ev['event_label'] ?? '')) !== '' ? $ev['event_label'] : $ev['sport_event'];
                // A tie at this rank issues a certificate for each winner. A team
                // winner issues one certificate PER MEMBER (individual).
                foreach ($list as $p) {
                    if (!$p) continue;
                    $teamId = (int)($p['team_id'] ?? 0);
                    // Build the recipient list for this place.
                    $recipients = [];
                    if ($teamId > 0) {
                        foreach ($this->teamMembersList($teamId) as $mem) {
                            $recipients[] = [
                                'key'   => 'm|es' . (int)$ev['esid'] . '|p' . $rk . '|tm' . $teamId . '|ath' . (int)$mem['athlete_id'],
                                'rtype' => 'individual',
                                'ref'   => (int)$mem['athlete_id'],
                                'name'  => (string)$mem['athlete_name'],
                            ];
                        }
                        if (!$recipients) {   // team with no members recorded → one team cert
                            $recipients[] = ['key' => 'm|es' . (int)$ev['esid'] . '|p' . $rk . '|t' . $teamId,
                                             'rtype' => 'team', 'ref' => $teamId, 'name' => (string)$p['name']];
                        }
                    } else {
                        $recipients[] = ['key' => 'm|es' . (int)$ev['esid'] . '|p' . $rk . '|i' . (int)($p['reg_id'] ?? 0),
                                         'rtype' => 'individual', 'ref' => (int)($p['reg_id'] ?? 0), 'name' => (string)$p['name']];
                    }
                    foreach ($recipients as $rcp) {
                        $rec = $this->issueTrackCert($eid, 'merit', $rcp['key'], [
                            'event_sport_id' => (int)$ev['esid'],
                            'recipient_type' => $rcp['rtype'],
                            'ref_id'         => $rcp['ref'],
                            'recipient_name' => $rcp['name'],
                            'school'         => $p['unit'],
                            'event_name'     => $ev['sport_event'],
                            'prize'          => $placeName[$rk],
                        ], $cfg);
                        $issuedIds[] = (int)$rec['id'];
                        $certs[] = [
                            'name'        => $rcp['name'],
                            'school'      => $p['unit'],
                            'prize'       => $placeName[$rk],
                            'event'       => $ev['sport_event'],
                            'event_label' => $evLabel,
                            'cert_no'     => (string)($rec['cert_number'] ?? ''),
                        ];
                    }
                }
            }
        }
        $event = $this->event; $config = $cfg; $type = 'merit';
        $issued_ids = $issuedIds; $csrf = $this->csrfToken();
        require APP_ROOT . '/views/staff/result-reports/track-certificate-print.php';
    }

    /**
     * Find-or-create a certificate record with a stable number. Numbers are
     * only assigned for Merit; Appreciation records track generated/printed
     * without a printed number.
     */
    private function issueTrackCert(int $eid, string $type, string $key, array $data, array $cfg): array
    {
        $ex = TrackCertificate::find($eid, $type, $key);
        if ($ex) return $ex;
        // Both templates now carry a sequential certificate number, each with its
        // own prefix / start / suffix (separate sequences per type).
        $max = TrackCertificate::maxSeq($eid, $type);
        $seq = $max > 0 ? $max + 1 : max(1, (int)($cfg['cert_seq_start'] ?? 1));
        $num = (string)($cfg['cert_prefix'] ?? '') . $seq . (string)($cfg['cert_suffix'] ?? '');
        $id = TrackCertificate::create(array_merge([
            'event_id'     => $eid,
            'cert_type'    => $type,
            'cert_key'     => $key,
            'seq'          => $seq,
            'cert_number'  => $num,
            'is_generated' => 1,
            'is_printed'   => 0,
            'generated_at' => date('Y-m-d H:i:s'),
        ], $data));
        return TrackCertificate::find($eid, $type, $key) ?? ['id' => $id, 'cert_number' => $num];
    }

    /**
     * GET — generate the appreciation certificates. One certificate per athlete
     * per event they entered (mirrors Merit), showing that event's catalog
     * label. Medalists are excluded per event — they receive Merit certificates
     * for the events they placed in but still get Appreciation for the others.
     */
    public function trackApprCertPrint(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}
        $eid   = (int)$this->event['id'];
        $catId = (int)($_GET['category_id'] ?? 0);
        $ageId = (int)($_GET['age_category_id'] ?? 0);
        $pick  = array_values(array_filter(array_map('intval', (array)($_GET['esid'] ?? []))));
        $cfg   = $this->trackCertConfig('appreciation');

        $params = [$eid];
        $where  = '';
        if ($pick) {
            $where .= ' AND eri.event_sport_id IN (' . implode(',', array_fill(0, count($pick), '?')) . ')';
            $params = array_merge($params, $pick);
        }
        if ($catId > 0) { $where .= ' AND sc.id = ?';               $params[] = $catId; }
        if ($ageId > 0) { $where .= ' AND sev.age_category_id = ?';  $params[] = $ageId; }
        // Optional: generate for a single unit/institution.
        $unitId = (int)($_GET['unit_id'] ?? 0);
        if ($unitId > 0) { $where .= ' AND er.unit_id = ?'; $params[] = $unitId; }
        // One row per athlete per event-sport, with that event's catalog label.
        $rows = Event::rowsRaw(
            "SELECT a.id AS athlete_id, a.name AS athlete_name, eu.name AS unit_name,
                    es.id AS esid, sev.name AS sport_event_name, sev.event_label AS event_label
               FROM event_registrations er
               JOIN athletes a ON a.id = er.athlete_id
               JOIN event_registration_items eri ON eri.registration_id = er.id
               JOIN event_sports es  ON es.id = eri.event_sport_id
               JOIN sport_events sev ON sev.id = es.sport_event_id
               JOIN sport_categories sc ON sc.id = sev.category_id
          LEFT JOIN event_units eu ON eu.id = er.unit_id
              WHERE er.event_id = ? AND er.admin_review_status = 'approved'{$where}
              GROUP BY a.id, a.name, eu.name, es.id, sev.name, sev.event_label
              ORDER BY sev.name, a.name",
            $params
        );
        // Medalist (athlete, event) pairs — excluded from Appreciation per event.
        $medalPairs = $this->trackMedalistPairs($eid);
        // Event-sports that have approved teams are handled ONLY by the team path
        // below (so relay members in "both" mode aren't double-processed).
        $teamEsids = [];
        foreach (Event::rowsRaw(
            "SELECT DISTINCT event_sport_id FROM team_registrations
              WHERE event_id = ? AND admin_review_status = 'approved'", [$eid]) as $r) {
            $teamEsids[(int)$r['event_sport_id']] = true;
        }
        $certs = []; $issuedIds = [];
        foreach ($rows as $r) {
            $aid  = (int)$r['athlete_id'];
            $esid = (int)$r['esid'];
            if (isset($teamEsids[$esid])) continue;                 // team event → team path
            if (isset($medalPairs[$esid . ':' . $aid])) continue;   // medalist here → Merit instead
            $sportEvent = trim((string)($r['sport_event_name'] ?? ''));
            $label      = trim((string)($r['event_label'] ?? '')) !== '' ? (string)$r['event_label'] : $sportEvent;
            $rec = $this->issueTrackCert($eid, 'appreciation', 'a|es' . $esid . '|ath' . $aid, [
                'event_sport_id' => $esid,
                'recipient_type' => 'individual',
                'ref_id'         => $aid,
                'recipient_name' => (string)$r['athlete_name'],
                'school'         => (string)($r['unit_name'] ?? ''),
                'event_name'     => $sportEvent,
                'prize'          => null,
            ], $cfg);
            $issuedIds[] = (int)$rec['id'];
            $certs[] = [
                'name'        => (string)$r['athlete_name'],
                'school'      => (string)($r['unit_name'] ?? ''),
                'event'       => $sportEvent,
                'event_label' => $label,
                'cert_no'     => (string)($rec['cert_number'] ?? ''),
            ];
        }

        // Team-event members also receive participation certificates (one per
        // member per team-event), excluding members of medal-winning teams.
        $tparams = [$eid]; $twhere = '';
        if ($pick)      { $twhere .= ' AND es.id IN (' . implode(',', array_fill(0, count($pick), '?')) . ')'; $tparams = array_merge($tparams, $pick); }
        if ($catId > 0) { $twhere .= ' AND sc.id = ?';              $tparams[] = $catId; }
        if ($ageId > 0) { $twhere .= ' AND sev.age_category_id = ?'; $tparams[] = $ageId; }
        if ($unitId > 0){ $twhere .= ' AND tr.unit_id = ?';          $tparams[] = $unitId; }
        $medalTeams = $this->trackMedalistTeamIds($eid);
        $teamRows = Event::rowsRaw(
            "SELECT a.id AS athlete_id, a.name AS athlete_name, eu.name AS unit_name,
                    es.id AS esid, sev.name AS sport_event_name, sev.event_label AS event_label, tr.id AS team_id
               FROM team_registrations tr
               JOIN team_registration_members trm ON trm.team_registration_id = tr.id
               JOIN athletes a ON a.id = trm.athlete_id
               JOIN event_sports es  ON es.id = tr.event_sport_id
               JOIN sport_events sev ON sev.id = es.sport_event_id
               JOIN sport_categories sc ON sc.id = sev.category_id
          LEFT JOIN event_units eu ON eu.id = tr.unit_id
              WHERE tr.event_id = ? AND tr.admin_review_status = 'approved'{$twhere}
              ORDER BY sev.name, a.name",
            $tparams
        );
        foreach ($teamRows as $r) {
            if (isset($medalTeams[(int)$r['team_id']])) continue;   // winning team's members → Merit
            $aid  = (int)$r['athlete_id'];
            $esid = (int)$r['esid'];
            $sportEvent = trim((string)($r['sport_event_name'] ?? ''));
            $label      = trim((string)($r['event_label'] ?? '')) !== '' ? (string)$r['event_label'] : $sportEvent;
            $rec = $this->issueTrackCert($eid, 'appreciation', 'a|es' . $esid . '|ath' . $aid, [
                'event_sport_id' => $esid,
                'recipient_type' => 'individual',
                'ref_id'         => $aid,
                'recipient_name' => (string)$r['athlete_name'],
                'school'         => (string)($r['unit_name'] ?? ''),
                'event_name'     => $sportEvent,
                'prize'          => null,
            ], $cfg);
            $issuedIds[] = (int)$rec['id'];
            $certs[] = [
                'name'        => (string)$r['athlete_name'],
                'school'      => (string)($r['unit_name'] ?? ''),
                'event'       => $sportEvent,
                'event_label' => $label,
                'cert_no'     => (string)($rec['cert_number'] ?? ''),
            ];
        }
        $event = $this->event; $config = $cfg; $type = 'appreciation';
        $issued_ids = $issuedIds; $csrf = $this->csrfToken();
        require APP_ROOT . '/views/staff/result-reports/track-certificate-print.php';
    }

    /** POST — mark a set of issued certificates as printed. */
    public function trackCertMarkPrinted(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        $this->verifyCsrf();
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        $n = TrackCertificate::markPrinted((int)$this->event['id'], $ids);
        $this->json(['success' => true, 'marked' => $n]);
    }

    /** POST — delete all issued certificates for this event (optionally one type). */
    public function trackCertDeleteAll(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        $this->verifyCsrf();
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}
        $type = in_array(($_POST['cert_type'] ?? ''), ['merit', 'appreciation'], true) ? $_POST['cert_type'] : '';
        $n = TrackCertificate::deleteAllForEvent((int)$this->event['id'], $type);
        $url = $type === 'appreciation'
             ? '/event-staff/result-reports/appreciation-certificate'
             : '/event-staff/result-reports/merit-certificate';
        $this->redirect($url, 'Deleted ' . $n . ' issued certificate' . ($n === 1 ? '' : 's') . '. Numbering will restart from the first.');
    }

    /**
     * GET /event-staff/result-reports/track-medal — Athletics / Skating medal
     * tally: unit-wise points + event-wise 1st/2nd/3rd (individual and team).
     */
    public function trackMedalTally(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}
        try { Schema::ensureTeamEntry(); }   catch (\Throwable $e) {}
        // allEvents = true so every event shows, revealing which still lack results.
        $eid  = (int)$this->event['id'];
        $data = $this->buildTrackMedalTally($eid, 0, 0, true, true);
        try { Schema::ensureResultStatus(); } catch (\Throwable $e) {}
        $statusMap = [];
        try {
            foreach (Event::rowsRaw(
                "SELECT event_sport_id, medal_distributed, certificate_issued
                   FROM event_result_status WHERE event_id = ?", [$eid]) as $r) {
                $statusMap[(int)$r['event_sport_id']] = [
                    'medal' => (int)$r['medal_distributed'] === 1,
                    'cert'  => (int)$r['certificate_issued'] === 1,
                ];
            }
        } catch (\Throwable $e) { $statusMap = []; }
        $this->renderWith('staff', 'staff/result-reports/track-medal', [
            'staff'        => $this->staff,
            'event'        => $this->event,
            'unit_tally'   => $data['unit_tally'],
            'events'       => $data['events'],
            'unit_medals'  => $data['unit_medals'],
            'completion'   => $data['completion'] ?? null,
            'last_updated' => $data['last_updated'] ?? null,
            'age_top'      => $data['age_top'] ?? [],
            'qualified_list' => $data['qualified_list'] ?? [],
            'can_mark_event' => true,
            'event_status'   => $statusMap,
            'status_base'    => '/event-staff/result-reports/result-status',
            'flash'        => $this->flash(),
        ]);
    }

    /**
     * POST /event-staff/result-reports/result-status — toggle an event's
     * medal-distributed / certificate-issued flag from the Event-wise Winners.
     */
    public function resultStatusToggle(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        $this->verifyCsrf();
        try { Schema::ensureResultStatus(); } catch (\Throwable $e) {}
        $eid   = (int)$this->event['id'];
        $esid  = (int)($_POST['esid'] ?? 0);
        $field = (string)($_POST['field'] ?? '');
        $value = !empty($_POST['value']) ? 1 : 0;
        if ($esid <= 0 || !in_array($field, ['medal', 'cert'], true)) {
            $this->json(['success' => false, 'message' => 'Invalid request.']);
        }
        // Confirm the event-sport belongs to this event.
        $owned = Event::rowsRaw(
            "SELECT id FROM event_sports WHERE id = ? AND event_id = ?", [$esid, $eid]);
        if (!$owned) { $this->json(['success' => false, 'message' => 'Event not found.']); }
        $col   = $field === 'medal' ? 'medal_distributed' : 'certificate_issued';
        $other = $field === 'medal' ? 'certificate_issued' : 'medal_distributed';
        $exists = Event::rowsRaw(
            "SELECT id FROM event_result_status WHERE event_id = ? AND event_sport_id = ?", [$eid, $esid]);
        if ($exists) {
            Event::rowsRaw(
                "UPDATE event_result_status SET {$col} = ?, updated_at = NOW()
                  WHERE event_id = ? AND event_sport_id = ?", [$value, $eid, $esid]);
        } else {
            Event::rowsRaw(
                "INSERT INTO event_result_status (event_id, event_sport_id, {$col}, {$other}, updated_at)
                 VALUES (?, ?, ?, 0, NOW())", [$eid, $esid, $value]);
        }
        $this->json(['success' => true, 'esid' => $esid, 'field' => $field, 'value' => $value]);
    }

    /** GET /event-staff/result-reports/track-medal/print — printable (portrait). */
    public function trackMedalTallyPrint(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}
        try { Schema::ensureTeamEntry(); }   catch (\Throwable $e) {}
        $data = $this->buildTrackMedalTally((int)$this->event['id']);
        $event      = $this->event;
        $unit_tally = $data['unit_tally'];
        $events     = $data['events'];
        $age_top    = $data['age_top'] ?? [];
        $section    = in_array(($_GET['section'] ?? ''), ['units', 'events', 'agetop'], true) ? $_GET['section'] : 'all';
        require APP_ROOT . '/views/staff/result-reports/track-medal-print.php';
    }

    /**
     * Compute the medal tally. Individual winners come from each track event's
     * final (last) round rank 1/2/3; team winners from team_registrations
     * result_rank 1/2/3. Points use the event's configured medal-point values.
     */
    private function buildTrackMedalTally(int $eid, int $catId = 0, int $ageId = 0, bool $publishedOnly = true, bool $allEvents = false): array
    {
        return \Services\TrackMedal::build($this->event, $catId, $ageId, $publishedOnly, $allEvents);
    }

    /**
     * Per event-sport, the result counts in its FINAL (last) round: how many
     * results are entered, how many of those are published, and how many are
     * published medal places (rank 1/2/3). Individual events read
     * track_heat_assignments of the final round; team events read
     * team_registrations. Keyed by event_sport_id. Certificates and the medal
     * tally only count published medal rows, so 'published'/'medalists' explain
     * why an event may yield no certificates.
     */
    private function trackFinalResultCounts(int $eid): array
    {
        $out = [];
        // Individual — final round per event-sport.
        $esRows = Event::rowsRaw("SELECT id AS esid FROM event_sports WHERE event_id = ?", [$eid]);
        $ids    = array_map(fn($r) => (int)$r['esid'], $esRows);
        $finalRoundOf = [];
        foreach (TrackConfig::roundsForMany($ids) as $esid => $rounds) {
            if ($rounds) { $last = end($rounds); $finalRoundOf[(int)$esid] = (int)$last['id']; }
        }
        if ($finalRoundOf) {
            $rids = array_values($finalRoundOf);
            $in   = implode(',', array_fill(0, count($rids), '?'));
            $rows = Event::rowsRaw(
                "SELECT round_id,
                        SUM(result_rank IS NOT NULL OR (result_time IS NOT NULL AND result_time <> '')) AS entered,
                        SUM((result_rank IS NOT NULL OR (result_time IS NOT NULL AND result_time <> '')) AND is_published = 1) AS published,
                        SUM(result_rank IN (1,2,3)) AS medalists,
                        SUM(result_rank IN (1,2,3) AND is_published = 1) AS medalists_pub
                   FROM track_heat_assignments WHERE round_id IN ({$in}) GROUP BY round_id",
                $rids
            );
            $byRound = [];
            foreach ($rows as $r) { $byRound[(int)$r['round_id']] = $r; }
            foreach ($finalRoundOf as $esid => $rid) {
                $r = $byRound[$rid] ?? [];
                $out[$esid] = [
                    'entered'       => (int)($r['entered'] ?? 0),
                    'published'     => (int)($r['published'] ?? 0),
                    'medalists'     => (int)($r['medalists'] ?? 0),
                    'medalists_pub' => (int)($r['medalists_pub'] ?? 0),
                    'is_team'       => false,
                ];
            }
        }
        // Team events — results held on team_registrations.
        foreach (Event::rowsRaw(
            "SELECT event_sport_id AS esid,
                    SUM(result_rank IS NOT NULL OR (result_time IS NOT NULL AND result_time <> '')) AS entered,
                    SUM((result_rank IS NOT NULL OR (result_time IS NOT NULL AND result_time <> '')) AND is_published = 1) AS published,
                    SUM(result_rank IN (1,2,3)) AS medalists,
                    SUM(result_rank IN (1,2,3) AND is_published = 1) AS medalists_pub
               FROM team_registrations
              WHERE event_id = ? AND admin_review_status = 'approved'
              GROUP BY event_sport_id", [$eid]) as $r) {
            $esid = (int)$r['esid'];
            $entered = (int)($r['entered'] ?? 0);
            // Only surface team counts when the event actually has team results.
            if ($entered > 0 || !isset($out[$esid])) {
                $out[$esid] = [
                    'entered'       => $entered,
                    'published'     => (int)($r['published'] ?? 0),
                    'medalists'     => (int)($r['medalists'] ?? 0),
                    'medalists_pub' => (int)($r['medalists_pub'] ?? 0),
                    'is_team'       => true,
                ];
            }
        }
        // Approved participant count per event-sport — appreciation certificates
        // go to participants OTHER THAN medalists, so the page shows this minus
        // the medal places.
        foreach (Event::rowsRaw(
            "SELECT eri.event_sport_id AS esid, COUNT(DISTINCT er.athlete_id) AS participants
               FROM event_registration_items eri
               JOIN event_registrations er ON er.id = eri.registration_id
              WHERE er.event_id = ? AND er.admin_review_status = 'approved'
              GROUP BY eri.event_sport_id", [$eid]) as $r) {
            $esid = (int)$r['esid'];
            if (!isset($out[$esid])) {
                $out[$esid] = ['entered' => 0, 'published' => 0, 'medalists' => 0, 'medalists_pub' => 0, 'is_team' => false];
            }
            $out[$esid]['participants'] = (int)$r['participants'];
        }
        return $out;
    }

    /** team_registration ids that placed (rank 1/2/3) — final-round assignment or team_registrations. */
    private function trackMedalistTeamIds(int $eid): array
    {
        $set = [];
        $esRows = Event::rowsRaw("SELECT id AS esid FROM event_sports WHERE event_id = ?", [$eid]);
        $ids    = array_map(fn($r) => (int)$r['esid'], $esRows);
        $finalRoundOf = [];
        foreach (TrackConfig::roundsForMany($ids) as $esid => $rounds) {
            if ($rounds) { $last = end($rounds); $finalRoundOf[(int)$esid] = (int)$last['id']; }
        }
        if ($finalRoundOf) {
            $rids = array_values($finalRoundOf);
            $in   = implode(',', array_fill(0, count($rids), '?'));
            foreach (Event::rowsRaw(
                "SELECT DISTINCT team_registration_id FROM track_heat_assignments
                  WHERE round_id IN ({$in}) AND team_registration_id IS NOT NULL AND result_rank IN (1,2,3)",
                $rids) as $r) {
                $set[(int)$r['team_registration_id']] = true;
            }
        }
        foreach (Event::rowsRaw(
            "SELECT id FROM team_registrations
              WHERE event_id = ? AND admin_review_status = 'approved' AND result_rank IN (1,2,3)", [$eid]) as $r) {
            $set[(int)$r['id']] = true;
        }
        return $set;
    }

    /** Members (athlete id, name, BIB) of a team, in playing order. */
    private function teamMembersList(int $teamId): array
    {
        if ($teamId <= 0) return [];
        return Event::rowsRaw(
            "SELECT trm.athlete_id, a.name AS athlete_name, trm.competitor_number
               FROM team_registration_members trm JOIN athletes a ON a.id = trm.athlete_id
              WHERE trm.team_registration_id = ? ORDER BY trm.position, trm.id",
            [$teamId]
        );
    }

    /**
     * (athlete, event-sport) pairs that hold a medal place (rank 1/2/3) in that
     * event's final round, as a lookup set keyed "esid:athleteId". Used to
     * exclude medalists from Appreciation per event — a medalist in one event
     * still gets Appreciation for the other events they entered.
     */
    private function trackMedalistPairs(int $eid): array
    {
        $esRows = Event::rowsRaw("SELECT id AS esid FROM event_sports WHERE event_id = ?", [$eid]);
        $ids    = array_map(fn($r) => (int)$r['esid'], $esRows);
        $finalRoundOf = [];
        foreach (TrackConfig::roundsForMany($ids) as $esid => $rounds) {
            if ($rounds) { $last = end($rounds); $finalRoundOf[(int)$esid] = (int)$last['id']; }
        }
        if (!$finalRoundOf) return [];
        $roundToEsid = array_flip($finalRoundOf);
        $rids = array_values($finalRoundOf);
        $in   = implode(',', array_fill(0, count($rids), '?'));
        $rows = Event::rowsRaw(
            "SELECT tha.round_id, er.athlete_id
               FROM track_heat_assignments tha
               JOIN event_registrations er ON er.id = tha.registration_id
              WHERE tha.round_id IN ({$in}) AND tha.result_rank IN (1,2,3)",
            $rids
        );
        $set = [];
        foreach ($rows as $r) {
            $esid = $roundToEsid[(int)$r['round_id']] ?? 0;
            if ($esid) $set[$esid . ':' . (int)$r['athlete_id']] = true;
        }
        return $set;
    }

    /** @deprecated retained for reference — logic moved to Services\TrackMedal. */
    private function buildTrackMedalTallyLegacy(int $eid, int $catId = 0, int $ageId = 0): array
    {
        $ev = $this->event;
        $ptsIndiv = [1 => (int)($ev['medal_pts_indiv_gold'] ?? 5),
                     2 => (int)($ev['medal_pts_indiv_silver'] ?? 3),
                     3 => (int)($ev['medal_pts_indiv_bronze'] ?? 2)];
        $ptsTeam  = [1 => (int)($ev['medal_pts_team_gold'] ?? 5),
                     2 => (int)($ev['medal_pts_team_silver'] ?? 3),
                     3 => (int)($ev['medal_pts_team_bronze'] ?? 2)];

        $eventsRaw = Event::rowsRaw(
            "SELECT es.id AS esid, es.event_code, sev.name AS sport_event_name, sev.gender AS gender,
                    sc.name AS category_name, sc.abbreviation AS category_abbr,
                    ac.name AS age_name, ac.sort_order AS age_sort
               FROM event_sports es
               JOIN sport_events     sev ON sev.id = es.sport_event_id
               JOIN sport_categories sc  ON sc.id  = sev.category_id
          LEFT JOIN age_categories   ac  ON ac.id  = sev.age_category_id
              WHERE es.event_id = ?" . ($catId > 0 ? " AND sc.id = ?" : '') . ($ageId > 0 ? " AND sev.age_category_id = ?" : '') . "
              ORDER BY (ac.sort_order IS NULL), ac.sort_order, ac.name, es.event_code, sev.gender",
            array_merge([$eid], $catId > 0 ? [$catId] : [], $ageId > 0 ? [$ageId] : [])
        );

        // Individual: final round per event-sport.
        $allIds  = array_map(fn($r) => (int)$r['esid'], $eventsRaw);
        $roundMap = TrackConfig::roundsForMany($allIds);
        $finalRoundOf = [];               // esid => final round id
        foreach ($eventsRaw as $r) {
            $rounds = $roundMap[(int)$r['esid']] ?? [];
            if ($rounds) { $last = end($rounds); $finalRoundOf[(int)$r['esid']] = (int)$last['id']; }
        }
        $indivWinners = [];               // esid => [rank => winner]
        if ($finalRoundOf) {
            $ids = array_values($finalRoundOf);
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $rows = Event::rowsRaw(
                "SELECT tha.round_id, tha.result_rank, tha.registration_id, er.athlete_id,
                        er.competitor_number, a.name AS athlete_name, eu.name AS unit_name
                   FROM track_heat_assignments tha
                   JOIN event_registrations er ON er.id = tha.registration_id
                   JOIN athletes a             ON a.id = er.athlete_id
              LEFT JOIN event_units eu         ON eu.id = er.unit_id
                  WHERE tha.round_id IN ({$in}) AND tha.result_rank IN (1,2,3)
                    AND tha.is_published = 1",
                $ids
            );
            $esidOfRound = array_flip($finalRoundOf);   // round_id => esid
            foreach ($rows as $r) {
                $esid = $esidOfRound[(int)$r['round_id']] ?? 0;
                $rk   = (int)$r['result_rank'];
                if ($esid && $rk >= 1 && $rk <= 3 && !isset($indivWinners[$esid][$rk])) {
                    $indivWinners[$esid][$rk] = [
                        'chest'  => (int)($r['competitor_number'] ?? 0),
                        'name'   => (string)($r['athlete_name'] ?? ''),
                        'unit'   => (string)($r['unit_name'] ?? ''),
                        'reg_id' => (int)($r['registration_id'] ?? 0),
                    ];
                }
            }
        }

        // Team winners.
        $teamWinners = [];                // esid => [rank => winner]
        foreach (Event::rowsRaw(
            "SELECT tr.id AS team_id, tr.event_sport_id AS esid, tr.result_rank, tr.team_name,
                    eu.name AS unit_name,
                    (SELECT GROUP_CONCAT(am.name ORDER BY m.position, m.id SEPARATOR ', ')
                       FROM team_registration_members m JOIN athletes am ON am.id = m.athlete_id
                      WHERE m.team_registration_id = tr.id) AS members
               FROM team_registrations tr
          LEFT JOIN event_units eu ON eu.id = tr.unit_id
              WHERE tr.event_id = ? AND tr.admin_review_status = 'approved'
                AND tr.result_rank IN (1,2,3) AND tr.is_published = 1",
            [$eid]) as $r) {
            $esid = (int)$r['esid']; $rk = (int)$r['result_rank'];
            if (!isset($teamWinners[$esid][$rk])) {
                $teamWinners[$esid][$rk] = [
                    'team'    => (string)($r['team_name'] ?? ''),
                    'unit'    => (string)($r['unit_name'] ?? ''),
                    'members' => (string)($r['members'] ?? ''),
                    'team_id' => (int)($r['team_id'] ?? 0),
                ];
            }
        }

        // Assemble event-wise output + accumulate unit points.
        $units      = [];   // name => ['g','s','b','points']
        $unitMedals = [];   // name => [ ['rank'=>, 'name'=>, 'event'=>], ... ]
        $bump = function (&$units, $unit, $rank, $pts) {
            $unit = trim((string)$unit); if ($unit === '') $unit = '—';
            if (!isset($units[$unit])) $units[$unit] = ['g'=>0,'s'=>0,'b'=>0,'points'=>0];
            $units[$unit][[1=>'g',2=>'s',3=>'b'][$rank]]++;
            $units[$unit]['points'] += (int)($pts[$rank] ?? 0);
        };
        $addMedal = function (&$unitMedals, $unit, $rank, $name, $event) {
            $unit = trim((string)$unit); if ($unit === '') $unit = '—';
            $unitMedals[$unit][] = ['rank' => $rank, 'name' => (string)$name, 'event' => (string)$event];
        };
        $events = [];
        foreach ($eventsRaw as $r) {
            $esid = (int)$r['esid'];
            $isTeam = isset($teamWinners[$esid]) && !isset($indivWinners[$esid]);
            $win = $isTeam ? ($teamWinners[$esid] ?? []) : ($indivWinners[$esid] ?? []);
            if (empty($win)) continue;   // no medals recorded for this event
            $places = [];
            for ($rk = 1; $rk <= 3; $rk++) {
                if (!isset($win[$rk])) { $places[$rk] = null; continue; }
                $w = $win[$rk];
                $evLabel = trim((string)($r['sport_event_name'] ?? '')) ?: (string)($r['event_code'] ?? '');
                if ($isTeam) {
                    $places[$rk] = ['chest' => '', 'name' => $w['team'], 'unit' => $w['unit'], 'sub' => $w['members'],
                                    'team_id' => (int)($w['team_id'] ?? 0), 'reg_id' => 0];
                    $bump($units, $w['unit'], $rk, $ptsTeam);
                    $addMedal($unitMedals, $w['unit'], $rk, $w['team'], $evLabel);
                } else {
                    $places[$rk] = ['chest' => $w['chest'] > 0 ? (string)$w['chest'] : '', 'name' => $w['name'], 'unit' => $w['unit'], 'sub' => '',
                                    'team_id' => 0, 'reg_id' => (int)($w['reg_id'] ?? 0)];
                    $bump($units, $w['unit'], $rk, $ptsIndiv);
                    $addMedal($unitMedals, $w['unit'], $rk, $w['name'], $evLabel);
                }
            }
            $events[] = [
                'esid'        => $esid,
                'sport_event' => trim((string)($r['sport_event_name'] ?? '')) ?: (string)($r['event_code'] ?? ''),
                'category'    => (string)($r['category_name'] ?? ''),
                'age_name'    => (string)($r['age_name'] ?? ''),
                'gender'      => (string)($r['gender'] ?? ''),
                'type'        => $isTeam ? 'Team' : 'Individual',
                'places'      => $places,
            ];
        }

        // Unit tally sorted by points desc, then gold/silver/bronze.
        $tally = [];
        foreach ($units as $name => $u) { $tally[] = ['unit' => $name] + $u; }
        usort($tally, function ($a, $b) {
            return ($b['points'] <=> $a['points']) ?: ($b['g'] <=> $a['g'])
                ?: ($b['s'] <=> $a['s']) ?: strcasecmp($a['unit'], $b['unit']);
        });

        return ['unit_tally' => $tally, 'events' => $events, 'unit_medals' => $unitMedals];
    }

    /**
     * GET /event-staff/result-reports/team-rank-list — pick an event
     * category, list every approved team registration in that
     * category, group by the team's sport-event and rank teams by
     * the sum of their three members' Total Scores in the category.
     */
    public function teamRankList(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        try { Schema::ensureTeamEntry(); } catch (\Throwable $e) {}

        $eid   = (int)$this->event['id'];
        $catId = (int)($_GET['category_id'] ?? 0);

        $categories = Event::rowsRaw(
            "SELECT DISTINCT sc.id, sc.name, sc.abbreviation
               FROM event_sports es
               JOIN sport_events     se ON se.id = es.sport_event_id
               JOIN sport_categories sc ON sc.id = se.category_id
              WHERE es.event_id = ?
              ORDER BY sc.name",
            [$eid]
        );

        $groups = [];
        if ($catId > 0) {
            // Step 1: approved team registrations on this event whose
            // sport-event falls under the chosen category.
            $teams = Event::rowsRaw(
                "SELECT tr.id              AS team_id,
                        tr.team_name,
                        tr.event_sport_id,
                        eu.id              AS unit_id,
                        eu.name            AS unit_name,
                        eu.address         AS unit_address,
                        eu.relay_code      AS unit_relay_code,
                        es.event_code,
                        sev.name           AS sport_event_name,
                        sc.id              AS category_id,
                        sc.name            AS category_name,
                        sc.abbreviation    AS category_abbr
                   FROM team_registrations tr
              LEFT JOIN event_units eu       ON eu.id = tr.unit_id
              LEFT JOIN event_sports es      ON es.id = tr.event_sport_id
              LEFT JOIN sport_events sev     ON sev.id = es.sport_event_id
              LEFT JOIN sport_categories sc  ON sc.id = sev.category_id
                  WHERE tr.event_id = ?
                    AND tr.admin_review_status = 'approved'
                    AND sc.id = ?
                  ORDER BY es.event_code, tr.team_name",
                [$eid, $catId]
            );

            // Step 2: pull members for the matched teams.
            $teamIds = array_map(fn($t) => (int)$t['team_id'], $teams);
            $membersByTeam = [];
            if ($teamIds) {
                $in = implode(',', array_fill(0, count($teamIds), '?'));
                $memberRows = Event::rowsRaw(
                    "SELECT trm.team_registration_id, trm.athlete_id, trm.position,
                            COALESCE(er.competitor_number, trm.competitor_number) AS competitor_number,
                            a.name AS athlete_name
                       FROM team_registration_members trm
                  LEFT JOIN athletes a            ON a.id = trm.athlete_id
                  LEFT JOIN event_registrations er ON er.id = trm.registration_id
                      WHERE trm.team_registration_id IN ({$in})
                      ORDER BY trm.team_registration_id, trm.position, trm.id",
                    $teamIds
                );
                foreach ($memberRows as $m) {
                    $membersByTeam[(int)$m['team_registration_id']][] = $m;
                }
            }

            // Step 3: every member's score on this event + category.
            $athleteIds = [];
            foreach ($membersByTeam as $ms) {
                foreach ($ms as $m) $athleteIds[] = (int)$m['athlete_id'];
            }
            $athleteIds = array_values(array_unique(array_filter($athleteIds)));
            $scoreByAthlete = [];
            if ($athleteIds) {
                $in = implode(',', array_fill(0, count($athleteIds), '?'));
                $scoreRows = Event::rowsRaw(
                    "SELECT se.athlete_id, se.grand_total, se.remarks
                       FROM score_entries se
                      WHERE se.event_id = ?
                        AND se.sport_category_id = ?
                        AND se.athlete_id IN ({$in})
                        AND se.lane_status IN ('saved', 'final')",
                    array_merge([$eid, $catId], $athleteIds)
                );
                $unranked = ['dns', 'dnf', 'disqualified'];
                foreach ($scoreRows as $s) {
                    $aId = (int)$s['athlete_id'];
                    // Skip DNS/DNF/DQ entries — they don't contribute
                    // to the team total.
                    if (in_array((string)($s['remarks'] ?? ''), $unranked, true)) continue;
                    $total = (float)$s['grand_total'];
                    if (!isset($scoreByAthlete[$aId])
                        || $scoreByAthlete[$aId] < $total) {
                        $scoreByAthlete[$aId] = $total;
                    }
                }
            }

            // Step 4: assemble per-event-sport buckets.
            foreach ($teams as $t) {
                $key = (int)$t['event_sport_id'];
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'event_code'    => $t['event_code'],
                        'sport_event'   => $t['sport_event_name'],
                        'category'      => $t['category_name'],
                        'category_abbr' => $t['category_abbr'],
                        'teams'         => [],
                    ];
                }
                $members = $membersByTeam[(int)$t['team_id']] ?? [];
                $memberRows  = [];
                $teamTotal   = 0.0;
                $scoredCount = 0;
                foreach ($members as $m) {
                    $aId   = (int)$m['athlete_id'];
                    $score = $scoreByAthlete[$aId] ?? null;
                    if ($score !== null) {
                        $teamTotal += (float)$score;
                        $scoredCount++;
                    }
                    $memberRows[] = [
                        'competitor_number' => (int)($m['competitor_number'] ?? 0),
                        'athlete_name'      => (string)($m['athlete_name'] ?? ''),
                        'score'             => $score,
                    ];
                }
                $groups[$key]['teams'][] = [
                    'unit_name'       => (string)($t['unit_name'] ?? '—'),
                    'unit_relay_code' => (string)($t['unit_relay_code'] ?? ''),
                    'team_name'       => (string)($t['team_name'] ?? ''),
                    'members'         => $memberRows,
                    'team_total'      => $teamTotal,
                    'all_scored'      => count($members) > 0 && $scoredCount === count($members),
                ];
            }

            // Step 5: rank teams in each group. Only teams whose every
            // member has a (non-DNS/DNF/DQ) score get a rank number.
            foreach ($groups as &$g) {
                usort($g['teams'], function ($a, $b) {
                    if ($a['all_scored'] !== $b['all_scored']) {
                        return $b['all_scored'] <=> $a['all_scored'];
                    }
                    $aT = (float)$a['team_total'];
                    $bT = (float)$b['team_total'];
                    if ($aT != $bT) return $bT <=> $aT;
                    return strcmp((string)$a['team_name'], (string)$b['team_name']);
                });
                $rank = 0;
                foreach ($g['teams'] as $i => $_) {
                    if (!empty($g['teams'][$i]['all_scored'])) {
                        $g['teams'][$i]['rank'] = ++$rank;
                    } else {
                        $g['teams'][$i]['rank'] = null;
                    }
                }
            }
            unset($g);
            uasort($groups, fn($a, $b) =>
                strcmp((string)($a['event_code'] ?? ''), (string)($b['event_code'] ?? '')));
        }

        $this->renderWith('staff', 'staff/result-reports/team-rank-list', [
            'staff'      => $this->staff,
            'event'      => $this->event,
            'categories' => $categories,
            'category_id'=> $catId,
            'groups'     => $groups,
            'flash'      => $this->flash(),
        ]);
    }

    /**
     * GET /event-staff/result-reports/medal — aggregate medal report.
     * Three panels:
     *  (a) Unit-wise points + rank — Gold/Silver/Bronze for individual
     *      and team awards, summed per unit.
     *  (b) Per-category sport-event medalists (Gold/Silver/Bronze).
     *  (c) Per-category top-5 highest scorers.
     */
    public function medalReport(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        try { Schema::ensureScoring(); } catch (\Throwable $e) {}
        try { Schema::ensureTeamEntry(); } catch (\Throwable $e) {}

        $eid = (int)$this->event['id'];
        $points = [
            'indiv' => [
                1 => (int)($this->event['medal_pts_indiv_gold']   ?? 5),
                2 => (int)($this->event['medal_pts_indiv_silver'] ?? 3),
                3 => (int)($this->event['medal_pts_indiv_bronze'] ?? 2),
            ],
            'team' => [
                1 => (int)($this->event['medal_pts_team_gold']    ?? 5),
                2 => (int)($this->event['medal_pts_team_silver']  ?? 3),
                3 => (int)($this->event['medal_pts_team_bronze']  ?? 2),
            ],
        ];

        // 1. Every approved (athlete, event-sport) registration with its
        //    category and unit context.
        $regRows = Event::rowsRaw(
            "SELECT es.id              AS event_sport_id,
                    es.event_code,
                    sev.name            AS sport_event_name,
                    sc.id               AS category_id,
                    sc.name             AS category_name,
                    sc.abbreviation     AS category_abbr,
                    er.athlete_id,
                    er.competitor_number AS reg_competitor_number,
                    a.name              AS athlete_name,
                    eu.id               AS unit_id,
                    eu.name             AS unit_name,
                    eu.address          AS unit_address
               FROM event_sports es
               JOIN sport_events sev      ON sev.id = es.sport_event_id
               JOIN sport_categories sc   ON sc.id = sev.category_id
               JOIN event_registration_items eri ON eri.event_sport_id = es.id
               JOIN event_registrations er ON er.id = eri.registration_id
                                          AND er.admin_review_status = 'approved'
               JOIN athletes a            ON a.id = er.athlete_id
          LEFT JOIN event_units eu        ON eu.id = er.unit_id
              WHERE es.event_id = ?",
            [$eid]
        );

        // 2. All scores keyed by (athlete_id, sport_category_id). DNS /
        //    DNF / Disqualified are skipped so they can't win a medal.
        $scoreRows = Event::rowsRaw(
            "SELECT se.id AS score_entry_id,
                    se.athlete_id, se.sport_category_id, se.competitor_number,
                    se.grand_total, se.total_penalty, se.inner_ten_count,
                    se.remarks AS score_remarks, se.series_count,
                    (SELECT GROUP_CONCAT(ss.sub_total ORDER BY ss.series_no SEPARATOR ',')
                       FROM score_series ss WHERE ss.score_entry_id = se.id) AS series_subs_csv
               FROM score_entries se
              WHERE se.event_id = ?
                AND se.lane_status IN ('saved','final')",
            [$eid]
        );
        $unranked = ['dns','dnf','disqualified'];
        $scoreByKey = [];
        foreach ($scoreRows as $s) {
            if (in_array((string)($s['score_remarks'] ?? ''), $unranked, true)) continue;
            $k = (int)$s['athlete_id'] . '|' . (int)$s['sport_category_id'];
            if (!isset($scoreByKey[$k])
                || (float)$scoreByKey[$k]['grand_total'] < (float)$s['grand_total']) {
                $scoreByKey[$k] = $s;
            }
        }

        // 10s count per score_entry (shots >= 10). series_sum entries
        // fall back to score_series.inner_tens since shots_json on
        // those rows carries only the sub-total.
        $tensByEntry = [];
        $entryIds = [];
        foreach ($scoreByKey as $s) $entryIds[(int)$s['score_entry_id']] = true;
        if ($entryIds) {
            $ids = array_keys($entryIds);
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $sr  = Event::rowsRaw(
                "SELECT ss.score_entry_id, ss.shots_json, ss.inner_tens, se.score_type
                   FROM score_series ss
                   JOIN score_entries se ON se.id = ss.score_entry_id
                  WHERE ss.score_entry_id IN ({$in})",
                $ids
            );
            foreach ($sr as $r) {
                $eId = (int)$r['score_entry_id'];
                if (($r['score_type'] ?? '') === 'series_sum') {
                    $tensByEntry[$eId] = ($tensByEntry[$eId] ?? 0) + (int)($r['inner_tens'] ?? 0);
                    continue;
                }
                $shots = json_decode((string)($r['shots_json'] ?? '[]'), true);
                if (!is_array($shots)) continue;
                foreach ($shots as $v) {
                    if ($v !== null && $v !== '' && (float)$v >= 10.0) {
                        $tensByEntry[$eId] = ($tensByEntry[$eId] ?? 0) + 1;
                    }
                }
            }
        }

        // 3. Per-event-sport individual ranking — top 3 are the medalists.
        $perES = [];
        foreach ($regRows as $r) {
            $key = (int)$r['event_sport_id'];
            if (!isset($perES[$key])) {
                $perES[$key] = [
                    'event_sport_id' => $key,
                    'event_code'     => (string)($r['event_code'] ?? ''),
                    'sport_event'    => (string)($r['sport_event_name'] ?? ''),
                    'category_id'    => (int)$r['category_id'],
                    'category_name'  => (string)($r['category_name'] ?? ''),
                    'category_abbr'  => (string)($r['category_abbr'] ?? ''),
                    'entries'        => [],
                ];
            }
            $score = $scoreByKey[(int)$r['athlete_id'] . '|' . (int)$r['category_id']] ?? null;
            if (!$score) continue;
            $seriesArr = !empty($score['series_subs_csv'])
                ? array_map('trim', explode(',', (string)$score['series_subs_csv'])) : [];
            $perES[$key]['entries'][] = [
                'athlete_id'        => (int)$r['athlete_id'],
                'athlete_name'      => (string)$r['athlete_name'],
                'competitor_number' => (int)$r['reg_competitor_number'] ?: (int)$score['competitor_number'],
                'unit_id'           => (int)($r['unit_id'] ?? 0),
                'unit_name'         => (string)($r['unit_name'] ?? ''),
                'unit_address'      => (string)($r['unit_address'] ?? ''),
                'grand_total'       => (float)$score['grand_total'],
                'series_array'      => $seriesArr,
                'tens_count'        => $tensByEntry[(int)$score['score_entry_id']] ?? 0,
            ];
        }
        $rankSorter = function (array $a, array $b): int {
            $aT = (float)$a['grand_total']; $bT = (float)$b['grand_total'];
            if ($aT != $bT) return $bT <=> $aT;
            $n = max(count($a['series_array'] ?? []), count($b['series_array'] ?? []));
            for ($i = $n - 1; $i >= 0; $i--) {
                $av = (float)($a['series_array'][$i] ?? 0);
                $bv = (float)($b['series_array'][$i] ?? 0);
                if ($av != $bv) return $bv <=> $av;
            }
            return (int)$b['tens_count'] <=> (int)$a['tens_count'];
        };
        // Dedupe athletes within an event-sport (a multi-row registration
        // shouldn't duplicate the same athlete), then sort.
        foreach ($perES as &$g) {
            $seen = [];
            $g['entries'] = array_values(array_filter($g['entries'], function ($e) use (&$seen) {
                if (isset($seen[$e['athlete_id']])) return false;
                $seen[$e['athlete_id']] = true; return true;
            }));
            usort($g['entries'], $rankSorter);
        }
        unset($g);

        // 4. Per-event-sport TEAM ranking.
        $teams = Event::rowsRaw(
            "SELECT tr.id          AS team_id, tr.team_name, tr.event_sport_id,
                    eu.id          AS unit_id, eu.name AS unit_name, eu.address AS unit_address,
                    es.event_code, sev.name AS sport_event_name,
                    sc.id          AS category_id, sc.name AS category_name, sc.abbreviation AS category_abbr
               FROM team_registrations tr
          LEFT JOIN event_units eu       ON eu.id = tr.unit_id
          LEFT JOIN event_sports es      ON es.id = tr.event_sport_id
          LEFT JOIN sport_events sev     ON sev.id = es.sport_event_id
          LEFT JOIN sport_categories sc  ON sc.id = sev.category_id
              WHERE tr.event_id = ?
                AND tr.admin_review_status = 'approved'",
            [$eid]
        );
        $teamIds = array_map(fn($t) => (int)$t['team_id'], $teams);
        $membersByTeam = [];
        if ($teamIds) {
            $in = implode(',', array_fill(0, count($teamIds), '?'));
            $mr = Event::rowsRaw(
                "SELECT trm.team_registration_id, trm.athlete_id, trm.position
                   FROM team_registration_members trm
                  WHERE trm.team_registration_id IN ({$in})", $teamIds);
            foreach ($mr as $m) {
                $membersByTeam[(int)$m['team_registration_id']][] = (int)$m['athlete_id'];
            }
        }
        $perESTeam = [];
        foreach ($teams as $t) {
            $key = (int)$t['event_sport_id'];
            if (!isset($perESTeam[$key])) {
                $perESTeam[$key] = [
                    'event_sport_id' => $key,
                    'event_code'     => (string)($t['event_code'] ?? ''),
                    'sport_event'    => (string)($t['sport_event_name'] ?? ''),
                    'category_id'    => (int)$t['category_id'],
                    'teams'          => [],
                ];
            }
            $members   = $membersByTeam[(int)$t['team_id']] ?? [];
            $total     = 0.0;
            $allScored = !empty($members);
            foreach ($members as $aId) {
                $sc = $scoreByKey[$aId . '|' . (int)$t['category_id']] ?? null;
                if (!$sc) { $allScored = false; continue; }
                $total += (float)$sc['grand_total'];
            }
            $perESTeam[$key]['teams'][] = [
                'team_id'      => (int)$t['team_id'],
                'team_name'    => (string)$t['team_name'],
                'unit_id'      => (int)($t['unit_id'] ?? 0),
                'unit_name'    => (string)($t['unit_name'] ?? ''),
                'unit_address' => (string)($t['unit_address'] ?? ''),
                'team_total'   => $total,
                'all_scored'   => $allScored,
            ];
        }
        foreach ($perESTeam as &$g) {
            usort($g['teams'], function ($a, $b) {
                if ($a['all_scored'] !== $b['all_scored']) return $b['all_scored'] <=> $a['all_scored'];
                return $b['team_total'] <=> $a['team_total'];
            });
        }
        unset($g);

        // 5. Panel (a): Aggregate medal points per unit.
        $unitPts = [];
        $ensureUnit = function (&$bag, $unitId, $name, $address) {
            if (!isset($bag[$unitId])) {
                $bag[$unitId] = [
                    'unit_id' => $unitId, 'name' => $name, 'address' => $address,
                    'indiv' => 0, 'team' => 0,
                ];
            }
        };
        foreach ($perES as $g) {
            $top = array_slice($g['entries'], 0, 3);
            foreach ($top as $i => $e) {
                $rk = $i + 1;
                $uid = (int)$e['unit_id'];
                if (!$uid) continue;
                $ensureUnit($unitPts, $uid, $e['unit_name'], $e['unit_address']);
                $unitPts[$uid]['indiv'] += $points['indiv'][$rk] ?? 0;
            }
        }
        foreach ($perESTeam as $g) {
            $rk = 0;
            foreach ($g['teams'] as $t) {
                if (!$t['all_scored']) continue;
                $rk++;
                if ($rk > 3) break;
                $uid = (int)$t['unit_id'];
                if (!$uid) continue;
                $ensureUnit($unitPts, $uid, $t['unit_name'], $t['unit_address']);
                $unitPts[$uid]['team'] += $points['team'][$rk] ?? 0;
            }
        }
        foreach ($unitPts as &$u) { $u['grand'] = $u['indiv'] + $u['team']; }
        unset($u);
        $unitRanked = array_values($unitPts);
        usort($unitRanked, function ($a, $b) {
            if ($a['grand'] !== $b['grand']) return $b['grand'] - $a['grand'];
            if ($a['indiv'] !== $b['indiv']) return $b['indiv'] - $a['indiv'];
            return strcmp((string)$a['name'], (string)$b['name']);
        });
        foreach ($unitRanked as $i => $_) { $unitRanked[$i]['rank'] = $i + 1; }

        // 6. Panel (b): per-category event-sport medalists (Individual).
        $byCatEvents = [];
        foreach ($perES as $g) {
            $cid = (int)$g['category_id'];
            if (!isset($byCatEvents[$cid])) {
                $byCatEvents[$cid] = [
                    'category_id'   => $cid,
                    'category_name' => $g['category_name'],
                    'category_abbr' => $g['category_abbr'],
                    'events'        => [],
                ];
            }
            $top = array_slice($g['entries'], 0, 3);
            $byCatEvents[$cid]['events'][] = [
                'event_code'  => $g['event_code'],
                'sport_event' => $g['sport_event'],
                'gold'   => $top[0] ?? null,
                'silver' => $top[1] ?? null,
                'bronze' => $top[2] ?? null,
            ];
        }
        foreach ($byCatEvents as &$c) {
            usort($c['events'], fn($a, $b) =>
                strcmp((string)($a['event_code'] ?? ''), (string)($b['event_code'] ?? '')));
        }
        unset($c);
        ksort($byCatEvents);

        // 7. Panel (c): per-category top-5 scorers.
        $regByKey = [];
        foreach ($regRows as $r) {
            $k = (int)$r['athlete_id'] . '|' . (int)$r['category_id'];
            if (!isset($regByKey[$k])) $regByKey[$k] = $r;
        }
        $byCatTop = [];
        foreach ($scoreByKey as $k => $s) {
            [$aIdStr, $cIdStr] = explode('|', $k);
            $aId = (int)$aIdStr; $cId = (int)$cIdStr;
            if (!isset($byCatTop[$cId])) {
                $byCatTop[$cId] = ['category_id' => $cId, 'entries' => []];
            }
            $reg = $regByKey[$k] ?? null;
            $byCatTop[$cId]['entries'][] = [
                'athlete_id'        => $aId,
                'athlete_name'      => (string)($reg['athlete_name'] ?? ''),
                'competitor_number' => (int)($reg['reg_competitor_number'] ?? 0) ?: (int)($s['competitor_number'] ?? 0),
                'unit_name'         => (string)($reg['unit_name'] ?? ''),
                'unit_address'      => (string)($reg['unit_address'] ?? ''),
                'category_name'     => (string)($reg['category_name'] ?? ''),
                'category_abbr'     => (string)($reg['category_abbr'] ?? ''),
                'grand_total'       => (float)$s['grand_total'],
                'series_array'      => !empty($s['series_subs_csv'])
                                        ? array_map('trim', explode(',', (string)$s['series_subs_csv'])) : [],
                'tens_count'        => $tensByEntry[(int)$s['score_entry_id']] ?? 0,
            ];
        }
        foreach ($byCatTop as &$c) {
            usort($c['entries'], $rankSorter);
            $c['entries'] = array_slice($c['entries'], 0, 5);
            $first = $c['entries'][0] ?? [];
            $c['category_name'] = $first['category_name'] ?? '';
            $c['category_abbr'] = $first['category_abbr'] ?? '';
        }
        unset($c);
        // Sort categories alphabetically.
        uasort($byCatTop, fn($a, $b) =>
            strcmp((string)($a['category_name'] ?? ''), (string)($b['category_name'] ?? '')));

        $this->renderWith('staff', 'staff/result-reports/medal', [
            'staff'              => $this->staff,
            'event'              => $this->event,
            'points'             => $points,
            'unit_ranked'        => $unitRanked,
            'by_category_events' => $byCatEvents,
            'by_category_top'    => $byCatTop,
            'flash'              => $this->flash(),
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
        try { Schema::ensureLedWall(); } catch (\Throwable $e) {}
        // Fresh fetch so we see the new led_wall_* columns even if boot()
        // loaded the event before the migration ran.
        $eventRow = Event::rowsRaw(
            "SELECT id, event_code, led_wall_enabled, led_wall_password, led_wall_interval, led_wall_unit_scroll
               FROM events WHERE id = ? LIMIT 1",
            [(int)$this->event['id']]
        )[0] ?? [];
        $this->renderWith('staff', 'staff/result-reports/index', [
            'staff' => $this->staff,
            'event' => $this->event,
            'led_wall' => [
                'enabled'     => !empty($eventRow['led_wall_enabled']),
                'password'    => (string)($eventRow['led_wall_password'] ?? ''),
                'interval'    => (int)($eventRow['led_wall_interval'] ?? 8),
                'unit_scroll' => (int)($eventRow['led_wall_unit_scroll'] ?? 20),
            ],
            'flash' => $this->flash(),
        ]);
    }

    /**
     * GET /event-staff/result-reports/consolidated —
     * One-page summary of the event: participant counts by gender,
     * per-category participation, total sport-events, total teams,
     * medals (Individual + Team), and athletes who hit each
     * sport-event's MQS (distinct count).
     */
    public function consolidatedReport(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        try { Schema::ensureTeamEntry(); } catch (\Throwable $e) {}
        try { Schema::ensureUnitRegistration(); } catch (\Throwable $e) {}
        $eid = (int)$this->event['id'];

        // 1. Total participants by gender (distinct athletes with an
        //    approved registration on this event).
        $partRows = Event::rowsRaw(
            "SELECT a.gender, COUNT(DISTINCT a.id) AS cnt
               FROM event_registrations er
               JOIN athletes a ON a.id = er.athlete_id
              WHERE er.event_id = ?
                AND er.admin_review_status = 'approved'
              GROUP BY a.gender",
            [$eid]
        );
        $participants = $this->bucketByGender($partRows);

        // 2. Participants per sport_category (distinct athletes who
        //    have at least one approved registration item under each
        //    category).
        $catRows = Event::rowsRaw(
            "SELECT sc.id AS category_id, sc.name AS category_name,
                    sc.abbreviation AS category_abbr,
                    a.gender, COUNT(DISTINCT a.id) AS cnt
               FROM event_registrations er
               JOIN event_registration_items eri ON eri.registration_id = er.id
               JOIN event_sports es              ON es.id = eri.event_sport_id
               JOIN sport_events sev             ON sev.id = es.sport_event_id
               JOIN sport_categories sc          ON sc.id = sev.category_id
               JOIN athletes a                   ON a.id = er.athlete_id
              WHERE er.event_id = ?
                AND er.admin_review_status = 'approved'
              GROUP BY sc.id, sc.name, sc.abbreviation, a.gender
              ORDER BY sc.name",
            [$eid]
        );
        $byCategory = [];
        foreach ($catRows as $r) {
            $cid = (int)$r['category_id'];
            if (!isset($byCategory[$cid])) {
                $byCategory[$cid] = [
                    'name' => (string)$r['category_name'],
                    'abbr' => (string)($r['category_abbr'] ?? ''),
                    'male' => 0, 'female' => 0, 'other' => 0, 'total' => 0,
                    'q_male' => 0, 'q_female' => 0, 'q_other' => 0, 'q_total' => 0,
                    'nq_male' => 0,'nq_female' => 0,'nq_other' => 0,'nq_total' => 0,
                ];
            }
            $g = $this->normGender((string)($r['gender'] ?? ''));
            $byCategory[$cid][$g] = (int)$r['cnt'];
            $byCategory[$cid]['total'] += (int)$r['cnt'];
        }

        // 2b. Per-category qualified-by-MQS distinct counts.
        $catQualRows = Event::rowsRaw(
            "SELECT sc.id AS category_id, a.gender, COUNT(DISTINCT a.id) AS cnt
               FROM event_registrations er
               JOIN event_registration_items eri ON eri.registration_id = er.id
               JOIN event_sports es              ON es.id = eri.event_sport_id
               JOIN sport_events sev             ON sev.id = es.sport_event_id
               JOIN sport_categories sc          ON sc.id = sev.category_id
               JOIN athletes a                   ON a.id = er.athlete_id
               JOIN score_entries sce            ON sce.event_id = er.event_id
                                                AND sce.athlete_id = er.athlete_id
                                                AND sce.sport_category_id = sev.category_id
                                                AND sce.lane_status IN ('saved','final')
              WHERE er.event_id = ?
                AND er.admin_review_status = 'approved'
                AND es.mqs IS NOT NULL AND es.mqs > 0
                AND sce.grand_total IS NOT NULL
                AND sce.grand_total >= es.mqs
                AND (sce.remarks IS NULL OR sce.remarks NOT IN ('dns','dnf','disqualified'))
              GROUP BY sc.id, a.gender",
            [$eid]
        );
        foreach ($catQualRows as $r) {
            $cid = (int)$r['category_id'];
            if (!isset($byCategory[$cid])) continue;
            $g = $this->normGender((string)($r['gender'] ?? ''));
            $byCategory[$cid]['q_' . $g] = (int)$r['cnt'];
            $byCategory[$cid]['q_total'] += (int)$r['cnt'];
        }
        foreach ($byCategory as $cid => $_) {
            foreach (['male', 'female', 'other', 'total'] as $k) {
                $byCategory[$cid]['nq_' . $k] = max(
                    0,
                    (int)$byCategory[$cid][$k] - (int)$byCategory[$cid]['q_' . $k]
                );
            }
        }

        // 3. Total sport-events configured for this event.
        $totalEvents = (int)(Event::rowsRaw(
            "SELECT COUNT(*) AS c FROM event_sports WHERE event_id = ?",
            [$eid]
        )[0]['c'] ?? 0);

        // 4. Total approved teams.
        $totalTeams = 0;
        try {
            $totalTeams = (int)(Event::rowsRaw(
                "SELECT COUNT(*) AS c FROM team_registrations
                  WHERE event_id = ? AND admin_review_status = 'approved'",
                [$eid]
            )[0]['c'] ?? 0);
        } catch (\Throwable $e) { /* team tables absent */ }

        // 5. Medals (Gold / Silver / Bronze).
        $indivMedals = $this->medalCountsIndividual($eid);
        $teamMedals  = $this->medalCountsTeam($eid);

        // 6. Distinct athletes who hit MQS in any registered sport-event.
        $qualifiedRows = Event::rowsRaw(
            "SELECT a.gender, COUNT(DISTINCT a.id) AS cnt
               FROM event_registrations er
               JOIN event_registration_items eri ON eri.registration_id = er.id
               JOIN event_sports es              ON es.id = eri.event_sport_id
               JOIN sport_events sev             ON sev.id = es.sport_event_id
               JOIN athletes a                   ON a.id = er.athlete_id
               JOIN score_entries sc             ON sc.event_id = er.event_id
                                                AND sc.athlete_id = er.athlete_id
                                                AND sc.sport_category_id = sev.category_id
                                                AND sc.lane_status IN ('saved','final')
              WHERE er.event_id = ?
                AND er.admin_review_status = 'approved'
                AND es.mqs IS NOT NULL
                AND es.mqs > 0
                AND sc.grand_total IS NOT NULL
                AND sc.grand_total >= es.mqs
                AND (sc.remarks IS NULL OR sc.remarks NOT IN ('dns','dnf','disqualified'))
              GROUP BY a.gender",
            [$eid]
        );
        $qualified = $this->bucketByGender($qualifiedRows);

        $this->renderWith('staff', 'staff/result-reports/consolidated', [
            'staff'            => $this->staff,
            'event'            => $this->event,
            'participants'     => $participants,
            'by_category'      => array_values($byCategory),
            'total_events'     => $totalEvents,
            'total_teams'      => $totalTeams,
            'indiv_medals'     => $indivMedals,
            'team_medals'      => $teamMedals,
            'qualified'        => $qualified,
            'flash'            => $this->flash(),
        ]);
    }

    /** Coerce legacy / variant gender strings into the canonical set. */
    private function normGender(?string $g): string
    {
        $g = strtolower(trim((string)$g));
        return match ($g) {
            'men'   => 'male',
            'women' => 'female',
            ''      => 'other',
            default => in_array($g, ['male', 'female'], true) ? $g : 'other',
        };
    }

    /** Reduce a {gender, cnt} row set to a flat male/female/other/total array. */
    private function bucketByGender(array $rows): array
    {
        $out = ['male' => 0, 'female' => 0, 'other' => 0, 'total' => 0];
        foreach ($rows as $r) {
            $g = $this->normGender((string)($r['gender'] ?? ''));
            $out[$g] = (int)$r['cnt'];
            $out['total'] += (int)$r['cnt'];
        }
        return $out;
    }

    /**
     * Compute Gold / Silver / Bronze counts across every event_sport on
     * this event. Top three athletes by grand_total in each event_sport
     * (with their score in that sport-event's category) take the
     * medals; DNS / DNF / DQ are excluded.
     */
    private function medalCountsIndividual(int $eid): array
    {
        $rows = Event::rowsRaw(
            "SELECT es.id AS event_sport_id,
                    sev.category_id,
                    er.athlete_id,
                    sc.grand_total, sc.remarks
               FROM event_sports es
               JOIN sport_events sev             ON sev.id = es.sport_event_id
               JOIN event_registration_items eri ON eri.event_sport_id = es.id
               JOIN event_registrations er       ON er.id = eri.registration_id
                                                AND er.admin_review_status = 'approved'
          LEFT JOIN score_entries sc             ON sc.event_id = er.event_id
                                                AND sc.athlete_id = er.athlete_id
                                                AND sc.sport_category_id = sev.category_id
                                                AND sc.lane_status IN ('saved','final')
              WHERE es.event_id = ?",
            [$eid]
        );
        $unranked = ['dns', 'dnf', 'disqualified'];
        $buckets = []; // event_sport_id => [athlete_id => best score]
        foreach ($rows as $r) {
            if ($r['grand_total'] === null) continue;
            if (in_array((string)($r['remarks'] ?? ''), $unranked, true)) continue;
            $esId = (int)$r['event_sport_id'];
            $aId  = (int)$r['athlete_id'];
            $score = (float)$r['grand_total'];
            if (isset($buckets[$esId][$aId]) && $buckets[$esId][$aId] >= $score) continue;
            $buckets[$esId][$aId] = $score;
        }
        return $this->countTop3($buckets);
    }

    /**
     * Compute Gold / Silver / Bronze counts for teams. Each team's
     * total = sum of its members' best grand_total in the team's
     * sport-event category. Teams with any missing / DNS / DNF / DQ
     * member are not ranked.
     */
    private function medalCountsTeam(int $eid): array
    {
        $teams = [];
        try {
            $teams = Event::rowsRaw(
                "SELECT tr.id AS team_id, tr.event_sport_id, sev.category_id
                   FROM team_registrations tr
                   JOIN event_sports es ON es.id = tr.event_sport_id
                   JOIN sport_events sev ON sev.id = es.sport_event_id
                  WHERE tr.event_id = ?
                    AND tr.admin_review_status = 'approved'",
                [$eid]
            );
        } catch (\Throwable $e) { return ['gold' => 0, 'silver' => 0, 'bronze' => 0]; }
        if (!$teams) return ['gold' => 0, 'silver' => 0, 'bronze' => 0];

        $teamIds = array_map(fn($t) => (int)$t['team_id'], $teams);
        $membersByTeam = [];
        $athleteIds = [];
        try {
            $in = implode(',', array_fill(0, count($teamIds), '?'));
            $mRows = Event::rowsRaw(
                "SELECT team_registration_id, athlete_id
                   FROM team_registration_members
                  WHERE team_registration_id IN ({$in})",
                $teamIds
            );
            foreach ($mRows as $m) {
                $membersByTeam[(int)$m['team_registration_id']][] = (int)$m['athlete_id'];
                $athleteIds[] = (int)$m['athlete_id'];
            }
        } catch (\Throwable $e) { return ['gold' => 0, 'silver' => 0, 'bronze' => 0]; }
        $athleteIds = array_values(array_unique($athleteIds));
        if (!$athleteIds) return ['gold' => 0, 'silver' => 0, 'bronze' => 0];

        $in2 = implode(',', array_fill(0, count($athleteIds), '?'));
        $sRows = Event::rowsRaw(
            "SELECT athlete_id, sport_category_id, grand_total, remarks
               FROM score_entries
              WHERE event_id = ?
                AND lane_status IN ('saved','final')
                AND athlete_id IN ({$in2})",
            array_merge([$eid], $athleteIds)
        );
        $unranked = ['dns', 'dnf', 'disqualified'];
        $bestByAthCat = [];
        foreach ($sRows as $s) {
            if (in_array((string)($s['remarks'] ?? ''), $unranked, true)) continue;
            $key = (int)$s['athlete_id'] . '|' . (int)$s['sport_category_id'];
            if (isset($bestByAthCat[$key]) && $bestByAthCat[$key] >= (float)$s['grand_total']) continue;
            $bestByAthCat[$key] = (float)$s['grand_total'];
        }

        $buckets = []; // event_sport_id => [team_id => team total]
        foreach ($teams as $t) {
            $tid  = (int)$t['team_id'];
            $cat  = (int)$t['category_id'];
            $esId = (int)$t['event_sport_id'];
            $members = $membersByTeam[$tid] ?? [];
            if (!$members) continue;
            $sum = 0.0; $scored = 0;
            foreach ($members as $aid) {
                if (isset($bestByAthCat[$aid . '|' . $cat])) {
                    $sum += $bestByAthCat[$aid . '|' . $cat];
                    $scored++;
                }
            }
            // All members must have a rankable score for the team to rank.
            if ($scored < count($members)) continue;
            $buckets[$esId][$tid] = $sum;
        }
        return $this->countTop3($buckets);
    }

    /**
     * Given event-sport buckets of {entity => score}, sort each bucket
     * descending and tally the first three as Gold / Silver / Bronze.
     */
    private function countTop3(array $buckets): array
    {
        $counts = ['gold' => 0, 'silver' => 0, 'bronze' => 0];
        foreach ($buckets as $b) {
            arsort($b);
            $i = 0;
            foreach ($b as $_) {
                $i++;
                if ($i === 1) $counts['gold']++;
                elseif ($i === 2) $counts['silver']++;
                elseif ($i === 3) $counts['bronze']++;
                if ($i >= 3) break;
            }
        }
        return $counts;
    }

    /**
     * POST /event-staff/result-reports/led-wall-settings —
     * Toggle the public LED-wall slideshow for this event and set / change
     * the numeric password the operator at the TV will enter. Password is
     * a 4–10 digit PIN only required when the feature is enabled.
     */
    public function ledWallSettings(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        $this->verifyCsrf();
        try { Schema::ensureLedWall(); } catch (\Throwable $e) {}
        $enabled = !empty($_POST['enabled']) ? 1 : 0;
        $pwd     = trim((string)($_POST['password'] ?? ''));
        if ($enabled && !preg_match('/^\d{4,10}$/', $pwd)) {
            $this->redirect('/event-staff/result-reports',
                'LED Wall password must be a 4–10 digit number.', 'error');
        }
        // Slide change interval (seconds) — clamped to a sane range.
        $interval = (int)($_POST['interval'] ?? 8);
        $interval = max(3, min(60, $interval));
        // Unit-wise points scroll: seconds for one full top→bottom pass
        // (higher = slower). Clamped to a sane range.
        $unitScroll = (int)($_POST['unit_scroll'] ?? 20);
        $unitScroll = max(5, min(120, $unitScroll));
        Event::rowsRaw(
            "UPDATE events SET led_wall_enabled = ?, led_wall_password = ?, led_wall_interval = ?, led_wall_unit_scroll = ? WHERE id = ?",
            [$enabled, $pwd !== '' ? $pwd : null, $interval, $unitScroll, (int)$this->event['id']]
        );
        $this->redirect('/event-staff/result-reports',
            $enabled ? 'LED Wall enabled — share the URL and PIN with the operator.'
                     : 'LED Wall disabled.');
    }

    /**
     * GET /event-staff/result-reports/category-top-units —
     * Per Event Category, top 5 units ranked by medal points
     * (Individual + Team). Points come from the event's configured
     * Gold/Silver/Bronze values.
     */
    public function categoryTopUnits(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        try { Schema::ensureScoring(); } catch (\Throwable $e) {}
        try { Schema::ensureTeamEntry(); } catch (\Throwable $e) {}

        $eid    = (int)$this->event['id'];
        $points = [
            'indiv' => [
                1 => (int)($this->event['medal_pts_indiv_gold']   ?? 5),
                2 => (int)($this->event['medal_pts_indiv_silver'] ?? 3),
                3 => (int)($this->event['medal_pts_indiv_bronze'] ?? 2),
            ],
            'team' => [
                1 => (int)($this->event['medal_pts_team_gold']    ?? 5),
                2 => (int)($this->event['medal_pts_team_silver']  ?? 3),
                3 => (int)($this->event['medal_pts_team_bronze']  ?? 2),
            ],
        ];

        // ── Same data pull as the Medal report — every approved
        //    (athlete, event-sport) and every approved score row. We
        //    only need the slice that contributes to medal points, so
        //    we lean on the existing query shapes.
        $regRows = Event::rowsRaw(
            "SELECT es.id              AS event_sport_id,
                    sc.id              AS category_id,
                    sc.name            AS category_name,
                    sc.abbreviation    AS category_abbr,
                    er.athlete_id,
                    eu.id              AS unit_id,
                    eu.name            AS unit_name,
                    eu.address         AS unit_address
               FROM event_sports es
               JOIN sport_events sev      ON sev.id = es.sport_event_id
               JOIN sport_categories sc   ON sc.id = sev.category_id
               JOIN event_registration_items eri ON eri.event_sport_id = es.id
               JOIN event_registrations er ON er.id = eri.registration_id
                                          AND er.admin_review_status = 'approved'
          LEFT JOIN event_units eu        ON eu.id = er.unit_id
              WHERE es.event_id = ?",
            [$eid]
        );

        $scoreRows = Event::rowsRaw(
            "SELECT se.athlete_id, se.sport_category_id,
                    se.grand_total, se.remarks AS score_remarks
               FROM score_entries se
              WHERE se.event_id = ?
                AND se.lane_status IN ('saved','final')",
            [$eid]
        );
        $unranked   = ['dns','dnf','disqualified'];
        $scoreByKey = [];
        foreach ($scoreRows as $s) {
            if (in_array((string)($s['score_remarks'] ?? ''), $unranked, true)) continue;
            $k = (int)$s['athlete_id'] . '|' . (int)$s['sport_category_id'];
            if (!isset($scoreByKey[$k])
                || (float)$scoreByKey[$k]['grand_total'] < (float)$s['grand_total']) {
                $scoreByKey[$k] = $s;
            }
        }

        // ── Individual: rank per event-sport, top 3 → medal points → unit.
        $perES = [];
        $catMeta = []; // cat_id => [name, abbr]
        foreach ($regRows as $r) {
            $cid = (int)$r['category_id'];
            $catMeta[$cid] = [
                'name' => (string)$r['category_name'],
                'abbr' => (string)$r['category_abbr'],
            ];
            $key = (int)$r['event_sport_id'];
            if (!isset($perES[$key])) {
                $perES[$key] = ['category_id' => $cid, 'entries' => []];
            }
            $score = $scoreByKey[(int)$r['athlete_id'] . '|' . $cid] ?? null;
            if (!$score) continue;
            $perES[$key]['entries'][] = [
                'athlete_id'   => (int)$r['athlete_id'],
                'unit_id'      => (int)($r['unit_id'] ?? 0),
                'unit_name'    => (string)($r['unit_name'] ?? ''),
                'unit_address' => (string)($r['unit_address'] ?? ''),
                'grand_total'  => (float)$score['grand_total'],
            ];
        }
        // unit-per-category bag: cat_id => unit_id => bucket
        $bag = [];
        $ensure = function (int $cid, int $uid, string $name, string $addr) use (&$bag) {
            if (!isset($bag[$cid][$uid])) {
                $bag[$cid][$uid] = [
                    'unit_id' => $uid, 'name' => $name, 'address' => $addr,
                    'indiv_g' => 0, 'indiv_s' => 0, 'indiv_b' => 0,
                    'team_g'  => 0, 'team_s'  => 0, 'team_b'  => 0,
                    'points'  => 0,
                ];
            }
        };
        foreach ($perES as $g) {
            $cid = (int)$g['category_id'];
            // Dedupe athletes within event-sport, sort by grand_total desc.
            $seen = []; $list = [];
            foreach ($g['entries'] as $e) {
                if (isset($seen[$e['athlete_id']])) continue;
                $seen[$e['athlete_id']] = true; $list[] = $e;
            }
            usort($list, fn($a, $b) => (float)$b['grand_total'] <=> (float)$a['grand_total']);
            foreach (array_slice($list, 0, 3) as $i => $e) {
                $uid = (int)$e['unit_id']; if (!$uid) continue;
                $rk = $i + 1;
                $ensure($cid, $uid, $e['unit_name'], $e['unit_address']);
                $key = $rk === 1 ? 'indiv_g' : ($rk === 2 ? 'indiv_s' : 'indiv_b');
                $bag[$cid][$uid][$key]++;
                $bag[$cid][$uid]['points'] += $points['indiv'][$rk] ?? 0;
            }
        }

        // ── Team: rank per event-sport, top 3 → medal points → team's unit.
        $teams = Event::rowsRaw(
            "SELECT tr.id          AS team_id, tr.event_sport_id,
                    eu.id          AS unit_id, eu.name AS unit_name, eu.address AS unit_address,
                    sc.id          AS category_id, sc.name AS category_name, sc.abbreviation AS category_abbr
               FROM team_registrations tr
          LEFT JOIN event_units eu       ON eu.id = tr.unit_id
          LEFT JOIN event_sports es      ON es.id = tr.event_sport_id
          LEFT JOIN sport_events sev     ON sev.id = es.sport_event_id
          LEFT JOIN sport_categories sc  ON sc.id = sev.category_id
              WHERE tr.event_id = ?
                AND tr.admin_review_status = 'approved'",
            [$eid]
        );
        $teamIds = array_map(fn($t) => (int)$t['team_id'], $teams);
        $membersByTeam = [];
        if ($teamIds) {
            $in = implode(',', array_fill(0, count($teamIds), '?'));
            $mr = Event::rowsRaw(
                "SELECT trm.team_registration_id, trm.athlete_id
                   FROM team_registration_members trm
                  WHERE trm.team_registration_id IN ({$in})", $teamIds
            );
            foreach ($mr as $m) {
                $membersByTeam[(int)$m['team_registration_id']][] = (int)$m['athlete_id'];
            }
        }
        $perESTeam = [];
        foreach ($teams as $t) {
            $cid = (int)$t['category_id'];
            $catMeta[$cid] = $catMeta[$cid] ?? [
                'name' => (string)$t['category_name'],
                'abbr' => (string)$t['category_abbr'],
            ];
            $tot = 0.0; $any = false;
            foreach ($membersByTeam[(int)$t['team_id']] ?? [] as $aid) {
                $s = $scoreByKey[$aid . '|' . $cid] ?? null;
                if (!$s) continue;
                $tot += (float)$s['grand_total']; $any = true;
            }
            $perESTeam[(int)$t['event_sport_id']]['cat'] = $cid;
            $perESTeam[(int)$t['event_sport_id']]['teams'][] = [
                'unit_id'      => (int)($t['unit_id'] ?? 0),
                'unit_name'    => (string)($t['unit_name'] ?? ''),
                'unit_address' => (string)($t['unit_address'] ?? ''),
                'team_total'   => $tot,
                'any'          => $any,
            ];
        }
        foreach ($perESTeam as $key => $g) {
            $cid = (int)$g['cat'];
            $list = $g['teams'];
            // Teams without any scored member can't medal.
            $list = array_values(array_filter($list, fn($t) => !empty($t['any'])));
            usort($list, fn($a, $b) => (float)$b['team_total'] <=> (float)$a['team_total']);
            foreach (array_slice($list, 0, 3) as $i => $t) {
                $uid = (int)$t['unit_id']; if (!$uid) continue;
                $rk = $i + 1;
                $ensure($cid, $uid, $t['unit_name'], $t['unit_address']);
                $key2 = $rk === 1 ? 'team_g' : ($rk === 2 ? 'team_s' : 'team_b');
                $bag[$cid][$uid][$key2]++;
                $bag[$cid][$uid]['points'] += $points['team'][$rk] ?? 0;
            }
        }

        // ── Reduce: per-category top-5 units ranked by points (then total medals).
        $perCategory = [];
        foreach ($bag as $cid => $units) {
            $list = array_values($units);
            usort($list, function ($a, $b) {
                if ($a['points'] !== $b['points']) return $b['points'] <=> $a['points'];
                $am = $a['indiv_g'] + $a['indiv_s'] + $a['indiv_b'] + $a['team_g'] + $a['team_s'] + $a['team_b'];
                $bm = $b['indiv_g'] + $b['indiv_s'] + $b['indiv_b'] + $b['team_g'] + $b['team_s'] + $b['team_b'];
                if ($am !== $bm) return $bm <=> $am;
                return strcmp((string)$a['name'], (string)$b['name']);
            });
            $top5 = array_slice($list, 0, 5);
            foreach ($top5 as $i => &$u) $u['rank'] = $i + 1;
            unset($u);
            $perCategory[$cid] = [
                'category_id'   => $cid,
                'category_name' => $catMeta[$cid]['name'] ?? '',
                'category_abbr' => $catMeta[$cid]['abbr'] ?? '',
                'units'         => $top5,
            ];
        }
        uasort($perCategory, fn($a, $b) =>
            strcmp((string)($a['category_name'] ?? ''), (string)($b['category_name'] ?? '')));

        $this->renderWith('staff', 'staff/result-reports/category-top-units', [
            'staff'        => $this->staff,
            'event'        => $this->event,
            'points'       => $points,
            'per_category' => $perCategory,
            'flash'        => $this->flash(),
        ]);
    }

    /**
     * GET /event-staff/result-reports/category-event-top3 —
     * Pick an Event Category from a dropdown; the report then lists
     * every sport-event in that category with its top-3 athletes
     * (Gold / Silver / Bronze). Each sport-event prints on its own
     * page via @page page-break-after.
     */
    public function categoryEventTopThree(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        try { Schema::ensureScoring(); } catch (\Throwable $e) {}

        $eid       = (int)$this->event['id'];
        $selected  = (int)($_GET['category_id'] ?? 0);
        $payload   = $this->buildCategoryEventTop3($eid, $selected);

        $this->renderWith('staff', 'staff/result-reports/category-event-top3', [
            'staff'              => $this->staff,
            'event'              => $this->event,
            'categories'         => $payload['categories'],
            'selected_category'  => $selected,
            'sport_events'       => $payload['sport_events'],
            'flash'              => $this->flash(),
        ]);
    }

    /**
     * GET /event-staff/result-reports/category-event-top3/print —
     * Print-only variant: clean white page through the `print`
     * layout, no app chrome. Each sport-event on its own A4 sheet.
     */
    public function categoryEventTopThreePrint(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        try { Schema::ensureScoring(); } catch (\Throwable $e) {}

        $eid      = (int)$this->event['id'];
        $selected = (int)($_GET['category_id'] ?? 0);
        if ($selected <= 0) {
            $this->redirect('/event-staff/result-reports/category-event-top3',
                'Pick an Event Category to print.', 'warning');
        }
        $payload = $this->buildCategoryEventTop3($eid, $selected);

        $catName = '';
        foreach ($payload['categories'] as $c) {
            if ((int)$c['id'] === $selected) { $catName = (string)$c['name']; break; }
        }

        $this->renderWith('print', 'staff/result-reports/category-event-top3-print', [
            'event'         => $this->event,
            'category_name' => $catName,
            'sport_events'  => $payload['sport_events'],
        ]);
    }

    /**
     * GET /event-staff/result-reports/category-event-top3/live —
     * Slide-show of medal-podium screens for live streaming. One
     * slide per sport-event with a green-screen background ready
     * for chroma keying.
     */
    public function categoryEventTopThreeLive(): void
    {
        $this->boot();
        $this->requirePrivilege('result_reports');
        try { Schema::ensureScoring(); } catch (\Throwable $e) {}

        $eid      = (int)$this->event['id'];
        $selected = (int)($_GET['category_id'] ?? 0);
        if ($selected <= 0) {
            $this->redirect('/event-staff/result-reports/category-event-top3',
                'Pick an Event Category to open Live Screen.', 'warning');
        }
        $payload = $this->buildCategoryEventTop3($eid, $selected);

        $catName = '';
        foreach ($payload['categories'] as $c) {
            if ((int)$c['id'] === $selected) { $catName = (string)$c['name']; break; }
        }

        // No layout chrome — the live view is a full-page green screen.
        $data = [
            'event'             => $this->event,
            'category_name'     => $catName,
            'categories'        => $payload['categories'],
            'selected_category' => $selected,
            'sport_events'      => $payload['sport_events'],
        ];
        extract($data);
        require APP_ROOT . '/views/staff/result-reports/category-event-top3-live.php';
    }

    /** Shared data builder used by the on-screen + print views. */
    private function buildCategoryEventTop3(int $eid, int $selected): array
    {
        // Categories configured on this event for the dropdown.
        $categories = Event::rowsRaw(
            "SELECT DISTINCT sc.id, sc.name, sc.abbreviation
               FROM event_sports es
               JOIN sport_events     sev ON sev.id = es.sport_event_id
               JOIN sport_categories sc  ON sc.id  = sev.category_id
              WHERE es.event_id = ?
              ORDER BY sc.name",
            [$eid]
        );
        if ($selected <= 0) {
            return ['categories' => $categories, 'sport_events' => []];
        }
            // Approved (athlete, event-sport) registrations under the
            // chosen category — mirrors the Medal report's join chain.
            $regRows = Event::rowsRaw(
                "SELECT es.id              AS event_sport_id,
                        es.event_code,
                        sev.name            AS sport_event_name,
                        sev.gender,
                        ac.name             AS age_category_name,
                        sc.id               AS category_id,
                        sc.name             AS category_name,
                        sc.abbreviation     AS category_abbr,
                        er.athlete_id,
                        er.competitor_number AS reg_competitor_number,
                        a.name              AS athlete_name,
                        a.passport_photo    AS athlete_photo,
                        eu.name             AS unit_name,
                        eu.address          AS unit_address
                   FROM event_sports es
                   JOIN sport_events sev      ON sev.id = es.sport_event_id
                   JOIN sport_categories sc   ON sc.id = sev.category_id
              LEFT JOIN age_categories ac     ON ac.id = sev.age_category_id
                   JOIN event_registration_items eri ON eri.event_sport_id = es.id
                   JOIN event_registrations er ON er.id = eri.registration_id
                                              AND er.admin_review_status = 'approved'
                   JOIN athletes a            ON a.id = er.athlete_id
              LEFT JOIN event_units eu        ON eu.id = er.unit_id
                  WHERE es.event_id = ? AND sc.id = ?",
                [$eid, $selected]
            );

            $scoreRows = Event::rowsRaw(
                "SELECT se.id AS score_entry_id,
                        se.athlete_id, se.sport_category_id, se.competitor_number,
                        se.grand_total, se.remarks AS score_remarks,
                        (SELECT GROUP_CONCAT(ss.sub_total ORDER BY ss.series_no SEPARATOR ',')
                           FROM score_series ss WHERE ss.score_entry_id = se.id) AS series_subs_csv
                   FROM score_entries se
                  WHERE se.event_id = ? AND se.sport_category_id = ?
                    AND se.lane_status IN ('saved','final')",
                [$eid, $selected]
            );
            $unranked   = ['dns','dnf','disqualified'];
            $scoreByAthlete = [];
            foreach ($scoreRows as $s) {
                if (in_array((string)($s['score_remarks'] ?? ''), $unranked, true)) continue;
                $aid = (int)$s['athlete_id'];
                if (!isset($scoreByAthlete[$aid])
                    || (float)$scoreByAthlete[$aid]['grand_total'] < (float)$s['grand_total']) {
                    $scoreByAthlete[$aid] = $s;
                }
            }

            // No. of 10x per score entry — series_sum sums score_series.inner_tens;
            // shot-mode counts shots >= 10 in shots_json. Used for tie-break.
            $tensByEntry = [];
            $entryIds = [];
            foreach ($scoreByAthlete as $s) $entryIds[(int)$s['score_entry_id']] = true;
            if ($entryIds) {
                $ids = array_keys($entryIds);
                $in  = implode(',', array_fill(0, count($ids), '?'));
                $sr  = Event::rowsRaw(
                    "SELECT ss.score_entry_id, ss.shots_json, ss.inner_tens, se.score_type
                       FROM score_series ss
                       JOIN score_entries se ON se.id = ss.score_entry_id
                      WHERE ss.score_entry_id IN ({$in})",
                    $ids
                );
                foreach ($sr as $r) {
                    $eIdK = (int)$r['score_entry_id'];
                    if (($r['score_type'] ?? '') === 'series_sum') {
                        $tensByEntry[$eIdK] = ($tensByEntry[$eIdK] ?? 0) + (int)($r['inner_tens'] ?? 0);
                        continue;
                    }
                    $shots = json_decode((string)($r['shots_json'] ?? '[]'), true);
                    if (!is_array($shots)) continue;
                    foreach ($shots as $v) {
                        if ($v !== null && $v !== '' && (float)$v >= 10.0) {
                            $tensByEntry[$eIdK] = ($tensByEntry[$eIdK] ?? 0) + 1;
                        }
                    }
                }
            }

            // Bucket entries per event-sport.
            $perES = [];
            foreach ($regRows as $r) {
                $key = (int)$r['event_sport_id'];
                if (!isset($perES[$key])) {
                    $perES[$key] = [
                        'event_sport_id' => $key,
                        'event_code'     => (string)($r['event_code'] ?? ''),
                        'sport_event'    => (string)($r['sport_event_name'] ?? ''),
                        'age_category'   => (string)($r['age_category_name'] ?? ''),
                        'gender'         => (string)($r['gender'] ?? ''),
                        'entries'        => [],
                    ];
                }
                $score = $scoreByAthlete[(int)$r['athlete_id']] ?? null;
                if (!$score) continue;
                $seriesArr = !empty($score['series_subs_csv'])
                    ? array_map('trim', explode(',', (string)$score['series_subs_csv'])) : [];
                $perES[$key]['entries'][] = [
                    'athlete_id'        => (int)$r['athlete_id'],
                    'athlete_name'      => (string)$r['athlete_name'],
                    'athlete_photo'     => (string)($r['athlete_photo'] ?? ''),
                    'competitor_number' => (int)$r['reg_competitor_number'] ?: (int)$score['competitor_number'],
                    'unit_name'         => (string)($r['unit_name'] ?? ''),
                    'unit_address'      => (string)($r['unit_address'] ?? ''),
                    'grand_total'       => (float)$score['grand_total'],
                    'series_array'      => $seriesArr,
                    'tens_count'        => $tensByEntry[(int)$score['score_entry_id']] ?? 0,
                ];
            }
            // Sort: grand_total desc, then last-series-desc tiebreak, then 10x desc.
            $sorter = function (array $a, array $b): int {
                $aT = (float)$a['grand_total']; $bT = (float)$b['grand_total'];
                if ($aT != $bT) return $bT <=> $aT;
                $n = max(count($a['series_array'] ?? []), count($b['series_array'] ?? []));
                for ($i = $n - 1; $i >= 0; $i--) {
                    $av = (float)($a['series_array'][$i] ?? 0);
                    $bv = (float)($b['series_array'][$i] ?? 0);
                    if ($av != $bv) return $bv <=> $av;
                }
                return (int)$b['tens_count'] <=> (int)$a['tens_count'];
            };
            foreach ($perES as &$g) {
                // Dedupe per athlete.
                $seen = [];
                $g['entries'] = array_values(array_filter($g['entries'], function ($e) use (&$seen) {
                    if (isset($seen[$e['athlete_id']])) return false;
                    $seen[$e['athlete_id']] = true; return true;
                }));
                usort($g['entries'], $sorter);
                $g['top3'] = array_slice($g['entries'], 0, 3);
            }
            unset($g);
            // Stable sort sport-events by event_code then sport_event name.
            uasort($perES, fn($a, $b) => strcmp(
                (string)$a['event_code'] . '|' . (string)$a['sport_event'],
                (string)$b['event_code'] . '|' . (string)$b['sport_event']
            ));
            $sportEvents = array_values($perES);

        return ['categories' => $categories, 'sport_events' => $sportEvents];
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
                // series_sum entries fall back to score_series.inner_tens
                // since their shots_json carries only the sub-total.
                $entryIds = array_values(array_filter(array_map(
                    fn($l) => (int)($l['score_entry_id'] ?? 0), $lanes
                )));
                $tensByEntry = [];
                if ($entryIds) {
                    $in = implode(',', array_fill(0, count($entryIds), '?'));
                    $seriesRows = Event::rowsRaw(
                        "SELECT ss.score_entry_id, ss.shots_json, ss.inner_tens, se.score_type
                           FROM score_series ss
                           JOIN score_entries se ON se.id = ss.score_entry_id
                          WHERE ss.score_entry_id IN ({$in})",
                        $entryIds
                    );
                    foreach ($seriesRows as $sr) {
                        $eId = (int)$sr['score_entry_id'];
                        if (($sr['score_type'] ?? '') === 'series_sum') {
                            $tensByEntry[$eId] = ($tensByEntry[$eId] ?? 0) + (int)($sr['inner_tens'] ?? 0);
                            continue;
                        }
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
                    if (!empty($l['series_subs_csv'])) {
                        $parts = explode(',', (string)$l['series_subs_csv']);
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
