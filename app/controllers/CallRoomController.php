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

        $ctx = $round > 0 ? TrackConfig::roundContext($round) : null;
        if (!$ctx || (int)$ctx['event_id'] !== $eid) {
            $this->json(['success' => false, 'message' => 'Pick a valid event and round.']);
        }
        if ($heat < 1 || $heat > (int)$ctx['num_heats']) {
            $this->json(['success' => false, 'message' => 'Pick a valid heat.']);
        }
        if ($bgId > 0) {
            $bg = Event::rowsRaw("SELECT id FROM call_room_backgrounds WHERE id = ? AND event_id = ?", [$bgId, $eid]);
            if (!$bg) $bgId = 0;
        }
        Event::rowsRaw(
            "INSERT INTO call_room_state
                    (event_id, event_sport_id, round_id, heat_no, background_id,
                     head_top_px, head_font_px, table_top_px, margin_left_px, margin_right_px, margin_bottom_px, is_live)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?,1)
             ON DUPLICATE KEY UPDATE event_sport_id=VALUES(event_sport_id), round_id=VALUES(round_id),
                                     heat_no=VALUES(heat_no), background_id=VALUES(background_id),
                                     head_top_px=VALUES(head_top_px), head_font_px=VALUES(head_font_px),
                                     table_top_px=VALUES(table_top_px), margin_left_px=VALUES(margin_left_px),
                                     margin_right_px=VALUES(margin_right_px), margin_bottom_px=VALUES(margin_bottom_px), is_live=1",
            [$eid, (int)$ctx['event_sport_id'], $round, $heat, $bgId ?: null,
             $topPx, $fontPx, $tblTop, $mLeft, $mRight, $mBottom]
        );
        $this->json(['success' => true, 'message' => 'Displayed on the LED wall.']);
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
        $out = [
            'live'       => !empty($st['is_live']),
            'background' => $bg,
            'head_top'      => (int)($st['head_top_px'] ?? 0),
            'head_font'     => (int)($st['head_font_px'] ?? 0),
            'table_top'     => (int)($st['table_top_px'] ?? 0),
            'margin_left'   => (int)($st['margin_left_px'] ?? 0),
            'margin_right'  => (int)($st['margin_right_px'] ?? 0),
            'margin_bottom' => (int)($st['margin_bottom_px'] ?? 0),
            'updated_at' => (string)($st['updated_at'] ?? ''),
        ];
        if (!empty($st['is_live']) && !empty($st['round_id']) && !empty($st['heat_no'])) {
            $out['round_id'] = (int)$st['round_id'];
            $out = array_merge($out, $this->heatPayload((int)$st['round_id'], (int)$st['heat_no'], $eid));
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
