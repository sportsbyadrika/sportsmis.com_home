<?php
namespace Controllers;

use Core\{Controller, Auth, FileUpload};
use Models\{Schema, Event, EventStaff, TrackConfig, OrderOfEvents};

/**
 * Call Room LED Wall for Event Staff holding the 'order_of_events' privilege.
 *
 *   GET  /event-staff/call-room                control page (pick event/round/heat, upload backgrounds, Display)
 *   POST /event-staff/call-room/background     upload a background image
 *   POST /event-staff/call-room/background/delete
 *   POST /event-staff/call-room/display        set the currently-shown heat (AJAX)
 *   POST /event-staff/call-room/clear          blank the wall content (AJAX)
 *   GET  /event-staff/call-room/wall           full-screen LED-wall display page (opens on the second monitor)
 *   GET  /event-staff/call-room/state.json     current selection + heat athletes (polled by the wall + preview)
 *   GET  /event-staff/call-room/heat.json      athletes for a given round+heat (control-page preview)
 */
class CallRoomController extends Controller
{
    private array $staff;
    private array $event;

    private function boot(): void
    {
        try { Schema::ensureSportHierarchy(); } catch (\Throwable $e) {}
        try { Schema::ensureTrackConfig(); }    catch (\Throwable $e) {}
        try { Schema::ensureCallRoom(); }       catch (\Throwable $e) {}
        if (!Auth::eventStaffCheck()) {
            $this->redirect('/event-staff/login', 'Please sign in to continue.', 'warning');
        }
        $session = Auth::eventStaff();
        $s = EventStaff::findById((int)$session['id']);
        if (!$s || $s['status'] !== 'active') {
            Auth::eventStaffLogout();
            $this->redirect('/event-staff/login', 'Your staff account is not active.', 'error');
        }
        $s['privileges'] = EventStaff::privilegesFor((int)$s['id']);
        if (!in_array('order_of_events', $s['privileges'], true)) $this->abort(403);
        $event = Event::findById((int)$s['event_id']);
        if (!$event) $this->abort(404);
        $event['event_code'] = $event['event_code'] ?? \ensureEventCode((int)$event['id']);
        $this->staff = $s;
        $this->event = $event;
    }

    // ── Control page ─────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->boot();
        $eid = (int)$this->event['id'];

        // Track events with configured rounds, each round with its heat count.
        $rows = OrderOfEvents::listForEvent($eid);
        $ids  = array_map(fn($r) => (int)$r['id'], $rows);
        $rmap = TrackConfig::roundsForMany($ids);
        $events = [];
        foreach ($rows as $r) {
            $esid   = (int)$r['id'];
            $rounds = $rmap[$esid] ?? [];
            if (!$rounds) continue;   // only events that have rounds set up
            $label = trim((string)($r['sport_event_name'] ?? '')) ?: trim((string)($r['event_code'] ?? ''));
            $bits  = array_filter([
                trim((string)($r['sport_event_age_category'] ?? '')),
                trim((string)($r['sport_event_gender'] ?? '')) !== ''
                    ? genderLabel((string)$r['sport_event_gender'], $this->event) : '',
            ]);
            $events[] = [
                'esid'   => $esid,
                'label'  => $label . ($bits ? ' · ' . implode(' · ', $bits) : ''),
                'rounds' => array_map(fn($rd) => [
                    'id'    => (int)$rd['id'],
                    'name'  => (string)$rd['round_name'],
                    'heats' => max(1, (int)$rd['num_heats']),
                ], $rounds),
            ];
        }

        // Age categories present in this event (for the medal-tally filter).
        $ageCats = Event::rowsRaw(
            "SELECT DISTINCT ac.id, ac.name, ac.sort_order
               FROM event_sports es
               JOIN sport_events   se ON se.id = es.sport_event_id
               JOIN age_categories ac ON ac.id = se.age_category_id
              WHERE es.event_id = ?
              ORDER BY (ac.sort_order IS NULL), ac.sort_order, ac.name", [$eid]);

        $this->renderWith('staff', 'staff/call-room/index', [
            'staff'       => $this->staff,
            'event'       => $this->event,
            'events_json' => $events,
            'age_cats'    => $ageCats,
            'nmr_json'    => $this->nmrList($eid),
            'backgrounds' => $this->backgrounds($eid),
            'state'       => $this->stateRow($eid),
            'flash'       => $this->flash(),
        ]);
    }

    // ── Backgrounds ──────────────────────────────────────────────────────────

    public function backgroundUpload(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $eid = (int)$this->event['id'];
        if (empty($_FILES['background']) || ($_FILES['background']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->redirect('/event-staff/call-room', 'Choose an image to upload.', 'warning');
        }
        try {
            $url = (new FileUpload())->upload($_FILES['background'], 'call-room', true);
        } catch (\Throwable $e) {
            $this->redirect('/event-staff/call-room', 'Upload failed: ' . $e->getMessage(), 'error');
        }
        $label = mb_substr(trim((string)($_POST['label'] ?? '')), 0, 120);
        Event::rowsRaw(
            "INSERT INTO call_room_backgrounds (event_id, label, image_path) VALUES (?,?,?)",
            [$eid, $label ?: null, $url]
        );
        $this->redirect('/event-staff/call-room', 'Background uploaded.');
    }

    public function backgroundDelete(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $eid = (int)$this->event['id'];
        $id  = (int)($_POST['id'] ?? 0);
        Event::rowsRaw("DELETE FROM call_room_backgrounds WHERE id = ? AND event_id = ?", [$id, $eid]);
        // Drop it from the live selection if it was in use.
        Event::rowsRaw("UPDATE call_room_state SET background_id = NULL WHERE event_id = ? AND background_id = ?", [$eid, $id]);
        $this->redirect('/event-staff/call-room', 'Background removed.');
    }

    // ── Set / clear what the wall shows ──────────────────────────────────────

    public function display(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $eid   = (int)$this->event['id'];
        $round = (int)($_POST['round_id'] ?? 0);
        $heat  = (int)($_POST['heat_no'] ?? 0);
        $bgId  = (int)($_POST['background_id'] ?? 0);
        $mode  = (string)($_POST['mode'] ?? 'heat');
        if (!in_array($mode, ['heat', 'results', 'medal', 'nmr'], true)) $mode = 'heat';
        // Medal-tally age-category filter (comma-separated age_category ids).
        $ageIds = array_values(array_filter(array_map('intval', (array)($_POST['medal_age_ids'] ?? [])), fn($x) => $x > 0));
        $ageIdsCsv = implode(',', $ageIds);
        // Heading layout: top offset (px) before the title, and title font size
        // (px). Clamped to sane bounds; 0 font = page default.
        $clamp   = fn($k, $max = 2000) => max(0, min($max, (int)($_POST[$k] ?? 0)));
        $topPx   = $clamp('head_top_px');
        $fontPx  = (int)($_POST['head_font_px'] ?? 0);
        $fontPx  = $fontPx > 0 ? max(12, min(400, $fontPx)) : 0;
        $tblTop  = $clamp('table_top_px');
        $mLeft   = $clamp('margin_left_px');
        $mRight  = $clamp('margin_right_px');
        $mBottom = $clamp('margin_bottom_px');

        // Medal Tally is event-wide; NMR shows one record for a chosen
        // event-sport — neither needs a round / heat.
        $esid = null;
        if ($mode === 'medal') {
            $round = 0; $heat = 0;
        } elseif ($mode === 'nmr') {
            $round = 0; $heat = 0;
            $esid = (int)($_POST['nmr_esid'] ?? 0);
            // Must be a genuine NMR for this event.
            $ok = false;
            foreach ($this->nmrList($eid) as $n) { if ((int)$n['esid'] === $esid) { $ok = true; break; } }
            if (!$ok) $this->json(['success' => false, 'message' => 'Pick a valid New Meet Record.']);
        } else {
            $ctx = $round > 0 ? TrackConfig::roundContext($round) : null;
            if (!$ctx || (int)$ctx['event_id'] !== $eid) {
                $this->json(['success' => false, 'message' => 'Pick a valid event and round.']);
            }
            if ($heat < 1 || $heat > (int)$ctx['num_heats']) {
                $this->json(['success' => false, 'message' => 'Pick a valid heat.']);
            }
            $esid = (int)$ctx['event_sport_id'];
        }
        if ($bgId > 0) {
            $bg = Event::rowsRaw("SELECT id FROM call_room_backgrounds WHERE id = ? AND event_id = ?", [$bgId, $eid]);
            if (!$bg) $bgId = 0;
        }
        Event::rowsRaw(
            "INSERT INTO call_room_state
                    (event_id, event_sport_id, round_id, heat_no, background_id,
                     head_top_px, head_font_px, table_top_px, margin_left_px, margin_right_px, margin_bottom_px, `mode`, medal_age_ids, is_live)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1)
             ON DUPLICATE KEY UPDATE event_sport_id=VALUES(event_sport_id), round_id=VALUES(round_id),
                                     heat_no=VALUES(heat_no), background_id=VALUES(background_id),
                                     head_top_px=VALUES(head_top_px), head_font_px=VALUES(head_font_px),
                                     table_top_px=VALUES(table_top_px), margin_left_px=VALUES(margin_left_px),
                                     margin_right_px=VALUES(margin_right_px), margin_bottom_px=VALUES(margin_bottom_px),
                                     `mode`=VALUES(`mode`), medal_age_ids=VALUES(medal_age_ids), is_live=1",
            [$eid, $esid, $round ?: null, $heat ?: null, $bgId ?: null,
             $topPx, $fontPx, $tblTop, $mLeft, $mRight, $mBottom, $mode, $ageIdsCsv]
        );
        $msg = ['results' => 'Results displayed on the LED wall.',
                'medal'   => 'Medal tally displayed on the LED wall.',
                'nmr'     => 'New Meet Record displayed on the LED wall.'][$mode] ?? 'Displayed on the LED wall.';
        $this->json(['success' => true, 'message' => $msg]);
    }

    public function clear(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $eid = (int)$this->event['id'];
        Event::rowsRaw("UPDATE call_room_state SET is_live = 0 WHERE event_id = ?", [$eid]);
        $this->json(['success' => true, 'message' => 'Wall cleared.']);
    }

    /** POST /event-staff/call-room/flowers — trigger the flower-shower on the wall. */
    public function flowers(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $eid = (int)$this->event['id'];
        // Ensure a state row exists, then stamp the trigger time.
        Event::rowsRaw(
            "INSERT INTO call_room_state (event_id, flowers_at) VALUES (?, NOW())
             ON DUPLICATE KEY UPDATE flowers_at = NOW()", [$eid]);
        $this->json(['success' => true, 'message' => 'Flowers! 🌸']);
    }

    // ── LED-wall display page (second monitor) ───────────────────────────────

    public function wall(): void
    {
        $this->boot();
        // The wall is a long-lived tab on a second monitor; never let the
        // browser serve a stale copy that predates a new display mode.
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        $event = $this->event;
        require APP_ROOT . '/views/staff/call-room/wall.php';
    }

    // ── JSON: current live selection + its athletes (polled by the wall) ─────

    public function stateJson(): void
    {
        $this->boot();
        $eid = (int)$this->event['id'];
        $st  = $this->stateRow($eid);
        $bg  = '';
        if (!empty($st['background_id'])) {
            $r = Event::rowsRaw("SELECT image_path FROM call_room_backgrounds WHERE id = ? AND event_id = ?",
                [(int)$st['background_id'], $eid]);
            $bg = $r[0]['image_path'] ?? '';
        }
        $mode = (string)($st['mode'] ?? 'heat');
        if (!in_array($mode, ['heat', 'results', 'medal', 'nmr'], true)) $mode = 'heat';
        $out = [
            'live'       => !empty($st['is_live']),
            'mode'       => $mode,
            'background' => $bg,
            'head_top'      => (int)($st['head_top_px'] ?? 0),
            'head_font'     => (int)($st['head_font_px'] ?? 0),
            'table_top'     => (int)($st['table_top_px'] ?? 0),
            'margin_left'   => (int)($st['margin_left_px'] ?? 0),
            'margin_right'  => (int)($st['margin_right_px'] ?? 0),
            'margin_bottom' => (int)($st['margin_bottom_px'] ?? 0),
            'updated_at' => (string)($st['updated_at'] ?? ''),
            'flowers_at' => (string)($st['flowers_at'] ?? ''),
        ];
        if (!empty($st['is_live'])) {
            if ($mode === 'medal') {
                $ageIds = array_values(array_filter(array_map('intval',
                    explode(',', (string)($st['medal_age_ids'] ?? ''))), fn($x) => $x > 0));
                $out = array_merge($out, $this->medalPayload($eid, $ageIds));
            } elseif ($mode === 'nmr') {
                $out = array_merge($out, $this->nmrPayload($eid, (int)($st['event_sport_id'] ?? 0)));
            } elseif (!empty($st['round_id']) && !empty($st['heat_no'])) {
                $out['round_id'] = (int)$st['round_id'];
                $out = array_merge($out, $mode === 'results'
                    ? $this->resultsPayload((int)$st['round_id'], (int)$st['heat_no'], $eid)
                    : $this->heatPayload((int)$st['round_id'], (int)$st['heat_no'], $eid));
            }
        }
        $this->json($out);
    }

    public function heatJson(): void
    {
        $this->boot();
        $eid   = (int)$this->event['id'];
        $round = (int)($_GET['round_id'] ?? 0);
        $heat  = (int)($_GET['heat_no'] ?? 0);
        $this->json($this->heatPayload($round, $heat, $eid));
    }

    public function resultsJson(): void
    {
        $this->boot();
        $eid   = (int)$this->event['id'];
        $round = (int)($_GET['round_id'] ?? 0);
        $heat  = (int)($_GET['heat_no'] ?? 0);
        $this->json($this->resultsPayload($round, $heat, $eid));
    }

    public function medalJson(): void
    {
        $this->boot();
        $ageIds = array_values(array_filter(array_map('intval', (array)($_GET['age_ids'] ?? [])), fn($x) => $x > 0));
        $this->json($this->medalPayload((int)$this->event['id'], $ageIds));
    }

    public function nmrJson(): void
    {
        $this->boot();
        $this->json(['ok' => true, 'records' => $this->nmrList((int)$this->event['id'])]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** Heading + athlete cards for one heat, scoped to this event. */
    private function heatPayload(int $roundId, int $heatNo, int $eid): array
    {
        $ctx = $roundId > 0 ? TrackConfig::roundContext($roundId) : null;
        if (!$ctx || (int)$ctx['event_id'] !== $eid) {
            return ['ok' => false, 'athletes' => []];
        }
        $esid   = (int)$ctx['event_sport_id'];
        $isTeam = TrackConfig::approvedTeamCount($esid) > 0;
        $athletes = [];
        if ($isTeam) {
            // Team / relay heat: each lane is a team — show the relay letter and
            // the members' BIB numbers.
            foreach (TrackConfig::teamAssignmentsFor($roundId) as $a) {
                if ((int)$a['heat_no'] !== $heatNo) continue;
                $bibs = [];
                foreach (TrackConfig::teamMembers((int)$a['team_registration_id']) as $m) {
                    $c = (int)($m['chest'] ?? 0);
                    if ($c > 0) $bibs[] = $c;
                }
                $athletes[] = [
                    'lane'    => (int)$a['track_no'],
                    'is_team' => true,
                    'relay'   => (string)($a['relay_code'] ?? ''),
                    'bibs'    => implode(', ', $bibs),
                    'name'    => (string)($a['team_name'] ?? ''),
                    'unit'    => (string)($a['unit_name'] ?? ''),
                ];
            }
        } else {
            foreach (TrackConfig::assignmentsFor($roundId) as $a) {
                if ((int)$a['heat_no'] !== $heatNo) continue;
                $athletes[] = [
                    'lane'  => (int)$a['track_no'],
                    'bib'   => (int)($a['competitor_number'] ?? 0),
                    'name'  => (string)($a['athlete_name'] ?? ''),
                    'unit'  => (string)($a['unit_name'] ?? ''),
                    'photo' => (string)($a['photo'] ?? ''),
                ];
            }
        }
        usort($athletes, fn($x, $y) => ($x['lane'] ?: 999) <=> ($y['lane'] ?: 999));
        $evName = trim((string)($ctx['sport_event_name'] ?? '')) ?: (string)($ctx['event_code'] ?? '');
        return [
            'ok'        => true,
            'event'     => $evName,
            'round'     => (string)($ctx['round_name'] ?? ''),
            'heat'      => $heatNo,
            'num_heats' => (int)($ctx['num_heats'] ?? 1),
            'is_team'   => $isTeam,
            'athletes'  => $athletes,
        ];
    }

    /**
     * Top-6 finishers (by rank) of one round + heat for the results display.
     * A Final round carries is_final so the wall can drop the "Heat" label.
     */
    private function resultsPayload(int $roundId, int $heatNo, int $eid): array
    {
        $ctx = $roundId > 0 ? TrackConfig::roundContext($roundId) : null;
        if (!$ctx || (int)$ctx['event_id'] !== $eid) {
            return ['ok' => false, 'athletes' => []];
        }
        $ranked = [];
        foreach (TrackConfig::assignmentsFor($roundId) as $a) {
            if ((int)$a['heat_no'] !== $heatNo) continue;
            $rank = (int)($a['result_rank'] ?? 0);
            if ($rank < 1) continue;   // only finishers with a place
            $ranked[] = [
                'rank'  => $rank,
                'time'  => trim((string)($a['result_time'] ?? '')),
                'lane'  => (int)$a['track_no'],
                'bib'   => (int)($a['competitor_number'] ?? 0),
                'name'  => (string)($a['athlete_name'] ?? ''),
                'unit'  => (string)($a['unit_name'] ?? ''),
                'photo' => (string)($a['photo'] ?? ''),
            ];
        }
        // Order by rank and keep the first six places.
        usort($ranked, fn($x, $y) => $x['rank'] <=> $y['rank']);
        $ranked = array_slice($ranked, 0, 6);
        $evName  = trim((string)($ctx['sport_event_name'] ?? '')) ?: (string)($ctx['event_code'] ?? '');
        $isFinal = trim((string)($ctx['round_name'] ?? '')) === 'Final';
        return [
            'ok'        => true,
            'event'     => $evName,
            'round'     => (string)($ctx['round_name'] ?? ''),
            'heat'      => $heatNo,
            'num_heats' => (int)($ctx['num_heats'] ?? 1),
            'is_final'  => $isFinal,
            'athletes'  => $ranked,
        ];
    }

    /**
     * Unit-wise medal tally for the whole event (published results only),
     * ranked by points then medal counts. Positions are dense-ranked so units
     * on equal points share the same rank. Feeds the medal-tally wall table.
     */
    private function medalPayload(int $eid, array $ageIds = []): array
    {
        try {
            $data = \Services\TrackMedal::build($this->event, 0, 0, true, false, $ageIds);
        } catch (\Throwable $e) {
            return ['ok' => false, 'units' => []];
        }
        $maxPos = (int)($data['max_position'] ?? 3);
        $units = [];
        $pos = 0; $rankNo = 0; $prevKey = null;
        foreach (($data['unit_tally'] ?? []) as $u) {
            $rankNo++;
            // Dense rank: units with the same points & medal profile share a place.
            $key = implode('-', array_map(fn($p) => (int)($u[$p] ?? 0), range(1, $maxPos))) . '|' . (int)($u['points'] ?? 0);
            if ($key !== $prevKey) { $pos = $rankNo; $prevKey = $key; }
            $row = [
                'pos'    => $pos,
                'unit'   => (string)($u['unit'] ?? ''),
                'logo'   => (string)($u['logo'] ?? ''),
                'g'      => (int)($u['g'] ?? 0),
                's'      => (int)($u['s'] ?? 0),
                'b'      => (int)($u['b'] ?? 0),
                'points' => (int)($u['points'] ?? 0),
            ];
            // Extra places 4-6 only when the event configured points for them.
            for ($p = 4; $p <= $maxPos; $p++) $row['p' . $p] = (int)($u[$p] ?? 0);
            $units[] = $row;
        }
        return [
            'ok'           => true,
            'event'        => (string)($this->event['name'] ?? ''),
            'max_position' => $maxPos,
            'units'        => $units,
        ];
    }

    /**
     * New Meet Records for the event: for every event-sport that has a standing
     * meet record, the single best entered performance that beats it (lower
     * time / greater height/length). One entry per event-sport.
     */
    private function nmrList(int $eid): array
    {
        try { Schema::ensureMeetRecords(); } catch (\Throwable $e) { return []; }
        $records = \Models\MeetRecord::mapForEvent($eid);
        if (!$records) return [];
        // Labels + result unit per event-sport that has a record.
        $meta = [];
        foreach (Event::rowsRaw(
            "SELECT es.id AS esid, es.track_result_unit AS unit,
                    sev.name AS sport_event_name, sev.event_label AS event_label, sev.gender AS gender,
                    sc.name AS category_name, ac.name AS age_name
               FROM event_sports es
               JOIN sport_events     sev ON sev.id = es.sport_event_id
          LEFT JOIN sport_categories sc  ON sc.id  = sev.category_id
          LEFT JOIN age_categories   ac  ON ac.id  = sev.age_category_id
              WHERE es.event_id = ?", [$eid]) as $r) {
            $meta[(int)$r['esid']] = $r;
        }
        $out = [];
        foreach ($records as $esid => $rec) {
            $esid = (int)$esid;
            if (!isset($meta[$esid])) continue;
            $unit = (string)($meta[$esid]['unit'] ?? 'time');
            $best = null; $bestNum = null;
            $consider = function (?string $time, string $name, string $unitName, int $bib, string $photo = '')
                use (&$best, &$bestNum, $unit) {
                $num = \Models\MeetRecord::toNumber((string)$time, $unit);
                if ($num === null) return;
                $better = $bestNum === null || ($unit === 'time' ? $num < $bestNum : $num > $bestNum);
                if ($better) { $bestNum = $num; $best = ['time' => (string)$time, 'name' => $name, 'unit' => $unitName, 'bib' => $bib, 'photo' => $photo]; }
            };
            // Individual performances across this event-sport's rounds.
            foreach (Event::rowsRaw(
                "SELECT tha.result_time, er.competitor_number, a.name AS athlete_name, a.passport_photo AS photo, eu.name AS unit_name
                   FROM track_heat_assignments tha
                   JOIN event_sport_rounds r  ON r.id = tha.round_id AND r.event_sport_id = ?
                   JOIN event_registrations er ON er.id = tha.registration_id
                   JOIN athletes a            ON a.id = er.athlete_id
              LEFT JOIN event_units eu        ON eu.id = er.unit_id
                  WHERE tha.result_time IS NOT NULL AND tha.result_time <> ''", [$esid]) as $g) {
                $consider($g['result_time'], (string)($g['athlete_name'] ?? ''), (string)($g['unit_name'] ?? ''), (int)($g['competitor_number'] ?? 0), (string)($g['photo'] ?? ''));
            }
            // Team performances (relay), if any.
            try {
                foreach (Event::rowsRaw(
                    "SELECT tr.result_time, tr.team_name, eu.name AS unit_name
                       FROM team_registrations tr
                  LEFT JOIN event_units eu ON eu.id = tr.unit_id
                      WHERE tr.event_sport_id = ? AND tr.result_time IS NOT NULL AND tr.result_time <> ''", [$esid]) as $g) {
                    $consider($g['result_time'], (string)($g['team_name'] ?? ''), (string)($g['unit_name'] ?? ''), 0, '');
                }
            } catch (\Throwable $e) { /* team tables may be absent */ }

            if (!$best) continue;
            if (!\Models\MeetRecord::isNMR($best['time'], $rec, $unit)) continue;
            $label = trim((string)($meta[$esid]['sport_event_name'] ?? '')) ?: ('Event #' . $esid);
            $sub = implode(' · ', array_filter([
                trim((string)($meta[$esid]['category_name'] ?? '')),
                trim((string)($meta[$esid]['age_name'] ?? '')),
                trim((string)($meta[$esid]['gender'] ?? '')) !== '' ? genderLabel((string)$meta[$esid]['gender'], $this->event) : '',
            ]));
            $oldMeta = trim(implode(', ', array_filter([
                trim((string)($rec['athlete_name'] ?? '')),
                trim((string)($rec['meet_name'] ?? '')),
                trim((string)($rec['record_year'] ?? '')),
            ])));
            $out[] = [
                'esid'     => $esid,
                'event'    => $label,
                'sub'      => $sub,
                'unit_type'=> $unit,
                'athlete'  => (string)$best['name'],
                'unit'     => (string)$best['unit'],
                'photo'    => (string)($best['photo'] ?? ''),
                'bib'      => (int)$best['bib'],
                'old'      => (string)($rec['record_value'] ?? ''),
                'old_meta' => $oldMeta,
                'new'      => (string)$best['time'],
            ];
        }
        // Alphabetical by event for a stable list.
        usort($out, fn($a, $b) => strcasecmp($a['event'], $b['event']));
        return $out;
    }

    /** The single NMR for one event-sport (for the wall), or ok=false. */
    private function nmrPayload(int $eid, int $esid): array
    {
        foreach ($this->nmrList($eid) as $n) {
            if ((int)$n['esid'] === $esid) return array_merge(['ok' => true], $n);
        }
        return ['ok' => false];
    }

    private function backgrounds(int $eid): array
    {
        return Event::rowsRaw(
            "SELECT id, label, image_path FROM call_room_backgrounds WHERE event_id = ? ORDER BY id DESC",
            [$eid]
        );
    }

    private function stateRow(int $eid): array
    {
        $r = Event::rowsRaw("SELECT * FROM call_room_state WHERE event_id = ?", [$eid]);
        return $r[0] ?? [];
    }
}
