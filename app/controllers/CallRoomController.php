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

        $this->renderWith('staff', 'staff/call-room/index', [
            'staff'       => $this->staff,
            'event'       => $this->event,
            'events_json' => $events,
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
        if (!in_array($mode, ['heat', 'results', 'medal'], true)) $mode = 'heat';
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

        // Medal Tally shows the whole event's unit standings — no round / heat.
        $esid = null;
        if ($mode !== 'medal') {
            $ctx = $round > 0 ? TrackConfig::roundContext($round) : null;
            if (!$ctx || (int)$ctx['event_id'] !== $eid) {
                $this->json(['success' => false, 'message' => 'Pick a valid event and round.']);
            }
            if ($heat < 1 || $heat > (int)$ctx['num_heats']) {
                $this->json(['success' => false, 'message' => 'Pick a valid heat.']);
            }
            $esid = (int)$ctx['event_sport_id'];
        } else {
            $round = 0; $heat = 0;
        }
        if ($bgId > 0) {
            $bg = Event::rowsRaw("SELECT id FROM call_room_backgrounds WHERE id = ? AND event_id = ?", [$bgId, $eid]);
            if (!$bg) $bgId = 0;
        }
        Event::rowsRaw(
            "INSERT INTO call_room_state
                    (event_id, event_sport_id, round_id, heat_no, background_id,
                     head_top_px, head_font_px, table_top_px, margin_left_px, margin_right_px, margin_bottom_px, mode, is_live)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1)
             ON DUPLICATE KEY UPDATE event_sport_id=VALUES(event_sport_id), round_id=VALUES(round_id),
                                     heat_no=VALUES(heat_no), background_id=VALUES(background_id),
                                     head_top_px=VALUES(head_top_px), head_font_px=VALUES(head_font_px),
                                     table_top_px=VALUES(table_top_px), margin_left_px=VALUES(margin_left_px),
                                     margin_right_px=VALUES(margin_right_px), margin_bottom_px=VALUES(margin_bottom_px),
                                     mode=VALUES(mode), is_live=1",
            [$eid, $esid, $round ?: null, $heat ?: null, $bgId ?: null,
             $topPx, $fontPx, $tblTop, $mLeft, $mRight, $mBottom, $mode]
        );
        $msg = ['results' => 'Results displayed on the LED wall.',
                'medal'   => 'Medal tally displayed on the LED wall.'][$mode] ?? 'Displayed on the LED wall.';
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

    // ── LED-wall display page (second monitor) ───────────────────────────────

    public function wall(): void
    {
        $this->boot();
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
        if (!in_array($mode, ['heat', 'results', 'medal'], true)) $mode = 'heat';
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
        ];
        if (!empty($st['is_live'])) {
            if ($mode === 'medal') {
                $out = array_merge($out, $this->medalPayload($eid));
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
        $this->json($this->medalPayload((int)$this->event['id']));
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** Heading + athlete cards for one heat, scoped to this event. */
    private function heatPayload(int $roundId, int $heatNo, int $eid): array
    {
        $ctx = $roundId > 0 ? TrackConfig::roundContext($roundId) : null;
        if (!$ctx || (int)$ctx['event_id'] !== $eid) {
            return ['ok' => false, 'athletes' => []];
        }
        $athletes = [];
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
        usort($athletes, fn($x, $y) => ($x['lane'] ?: 999) <=> ($y['lane'] ?: 999));
        $evName = trim((string)($ctx['sport_event_name'] ?? '')) ?: (string)($ctx['event_code'] ?? '');
        return [
            'ok'        => true,
            'event'     => $evName,
            'round'     => (string)($ctx['round_name'] ?? ''),
            'heat'      => $heatNo,
            'num_heats' => (int)($ctx['num_heats'] ?? 1),
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
    private function medalPayload(int $eid): array
    {
        try {
            $data = \Services\TrackMedal::build($this->event, 0, 0, true, false);
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
