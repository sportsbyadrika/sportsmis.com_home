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

        $this->renderWith('staff', 'staff/search-view', [
            'staff'          => $this->staff,
            'event'          => $this->event,
            'reg'            => $reg,
            'athlete'        => $athlete,
            'items'          => $items,
            'team_entries'   => $teamEntries,
            'results'        => $results,
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
            // ── Step 1: every approved (athlete, sport-event) pair on
            //    this event under the chosen category. An athlete
            //    registered for several sport-events in the same
            //    category appears once per sport-event here.
            $rows = Event::rowsRaw(
                "SELECT es.id                AS event_sport_id,
                        es.event_code,
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
            'flash'      => $this->flash(),
        ]);
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
        $this->renderWith('staff', 'staff/result-reports/index', [
            'staff' => $this->staff,
            'event' => $this->event,
            'flash' => $this->flash(),
        ]);
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
