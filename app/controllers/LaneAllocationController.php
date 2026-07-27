<?php
namespace Controllers;

use Core\{Controller, Auth};
use Models\{Schema, Event, EventUnit, UnitUser, EventStaff, LaneAllocation, TrackConfig};

/**
 * Lane Allocation — shared by Event Staff (admin, "lane_allocation" privilege)
 * and Unit Users (self-service, only when the per-event toggle is enabled).
 *
 * boot() resolves a uniform $actor:
 *   - mode      'admin' | 'unit'
 *   - layout    'staff' | 'unit'
 *   - unit_ids  null for admin (all units) | [ids] for a unit user
 *
 * Structured so Scoring / Result Reports can reuse LaneAllocation data
 * without a rewrite.
 */
class LaneAllocationController extends Controller
{
    private array $actor;
    private array $event;

    private function boot(): void
    {
        try { Schema::ensureLaneAllocation(); } catch (\Throwable $e) {}

        if (Auth::eventStaffCheck()) {
            $session = Auth::eventStaff();
            $staff   = EventStaff::findById((int)$session['id']);
            if (!$staff || $staff['status'] !== 'active') {
                Auth::eventStaffLogout();
                $this->redirect('/event-staff/login', 'Your staff account is not active.', 'error');
            }
            if (!in_array('lane_allocation', EventStaff::privilegesFor((int)$staff['id']), true)) {
                $this->abort(403);
            }
            $this->event = $this->resolveEvent((int)$staff['event_id']);
            $this->actor = [
                'mode'     => 'admin',
                'layout'   => 'staff',
                'name'     => $staff['name'],
                'unit_ids' => null,
            ];
            return;
        }

        if (Auth::unitUserCheck()) {
            $session = Auth::unitUser();
            $u = UnitUser::findById((int)$session['id']);
            if (!$u || $u['status'] !== 'active') {
                Auth::unitUserLogout();
                $this->redirect('/unit/login', 'Your unit account is not active.', 'error');
            }
            $this->event = $this->resolveEvent((int)$u['event_id']);
            // The whole module is gated by the per-event toggle.
            if (empty($this->event['unit_lane_allocation_enabled'])) {
                $this->abort(403);
            }
            $this->actor = [
                'mode'     => 'unit',
                'layout'   => 'unit',
                'name'     => $u['name'],
                'unit_ids' => UnitUser::assignmentIds((int)$u['id']),
            ];
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

    // ── Page ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->boot();

        // Athletics / Skating (quad & inline races) use a different, tab-based
        // workspace instead of the shooting lane grid. The workspace is chosen
        // by the event's configured sport (Manage Event → Sport in this event).
        if ($this->isTrackSport()) {
            try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}
            $trackEvents = $this->trackEventsWithCounts((int)$this->event['id']);
            $maxRounds = 0;
            foreach ($trackEvents as $te) { $maxRounds = max($maxRounds, count($te['rounds'])); }
            // Optional lane-draw workspace for a chosen round (?round=), with an
            // optional participants-pool override (?pool=approved|qualified).
            $draw = $this->buildDrawContext((int)($_GET['round'] ?? 0), (string)($_GET['pool'] ?? ''));
            $this->renderWith($this->actor['layout'], 'lane-allocation/track-index', [
                'actor'     => $this->actor,
                'event'     => $this->event,
                'sport'     => Event::sport($this->event),
                'staff'     => Auth::eventStaff(),
                'unit_user' => Auth::unitUser(),
                'track_events' => $trackEvents,
                'max_rounds'   => $maxRounds,
                'round_names'  => TrackConfig::ROUND_NAMES,
                'draw'         => $draw,
                'flash'     => $this->flash(),
            ]);
            return;
        }

        $this->renderWith($this->actor['layout'], 'lane-allocation/index', [
            'actor'   => $this->actor,
            'event'   => $this->event,
            // staff layout needs $staff for the navbar; unit layout needs $unit_user.
            'staff'     => Auth::eventStaff(),
            'unit_user' => Auth::unitUser(),
            'flash'   => $this->flash(),
        ]);
    }

    /** True when the event's sport is a track/race sport (Athletics / Skating). */
    private function isTrackSport(): bool
    {
        $sport = Event::sport($this->event);
        return stripos($sport, 'athlet') !== false || stripos($sport, 'skat') !== false;
    }

    /**
     * Sport events of this event that have at least one approved athlete, with
     * the approved-athlete count. Powers the first tab of the track workspace.
     * Returns ['event_sport_id','sport_event','category','event_code','approved'].
     */
    private function trackEventsWithCounts(int $eventId): array
    {
        $rows = Event::rowsRaw(
            "SELECT es.id AS event_sport_id, es.event_code,
                    es.track_event_type, es.track_num_tracks, es.track_num_laps,
                    sev.name AS sport_event_name, sev.gender AS event_gender,
                    sc.name AS category_name, sc.abbreviation AS category_abbr,
                    ac.name AS age_category_name, ac.sort_order AS age_sort,
                    COUNT(DISTINCT er.athlete_id) AS approved
               FROM event_sports es
          LEFT JOIN sport_events     sev ON sev.id = es.sport_event_id
          LEFT JOIN sport_categories sc  ON sc.id  = sev.category_id
          LEFT JOIN age_categories   ac  ON ac.id  = sev.age_category_id
          LEFT JOIN event_registration_items eri ON eri.event_sport_id = es.id
          LEFT JOIN event_registrations er ON er.id = eri.registration_id
                                           AND er.admin_review_status = 'approved'
              WHERE es.event_id = ?
              GROUP BY es.id, es.event_code, es.track_event_type, es.track_num_tracks,
                       sev.name, sev.gender, sc.name, sc.abbreviation, ac.name, ac.sort_order
             HAVING approved > 0
              ORDER BY (sc.abbreviation IS NULL OR sc.abbreviation = ''), sc.abbreviation, sc.name,
                       (ac.sort_order IS NULL), ac.sort_order, ac.name,
                       es.event_code, sev.gender",
            [$eventId]
        );
        $ids   = array_map(fn($r) => (int)$r['event_sport_id'], $rows);
        $rmap  = TrackConfig::roundsForMany($ids);
        $out = [];
        foreach ($rows as $r) {
            $esid     = (int)$r['event_sport_id'];
            $approved = (int)$r['approved'];
            $type     = (string)($r['track_event_type'] ?? '');
            $tracks   = (int)($r['track_num_tracks'] ?? 0);
            // Primary rounds (heats): field = 1; track = ceil(athletes / tracks).
            $primary = null;
            if ($type === 'field') {
                $primary = 1;
            } elseif ($type === 'track' && $tracks > 0) {
                $primary = (int)ceil($approved / $tracks);
            }
            $out[] = [
                'event_sport_id' => $esid,
                'sport_event'    => trim((string)($r['sport_event_name'] ?? '')) ?: trim((string)($r['event_code'] ?? '')),
                'category'       => trim((string)($r['category_name'] ?? '')),
                'age_category'   => trim((string)($r['age_category_name'] ?? '')),
                'event_code'     => trim((string)($r['event_code'] ?? '')),
                'approved'       => $approved,
                'type'           => $type,
                'num_tracks'     => $tracks,
                'num_laps'       => (int)($r['track_num_laps'] ?? 0),
                'primary_rounds' => $primary,
                'rounds'         => $rmap[$esid] ?? [],
            ];
        }
        return $out;
    }

    /** Guard: config edits are event-staff (admin) only. */
    private function requireAdmin(): void
    {
        if (($this->actor['mode'] ?? '') !== 'admin') $this->abort(403);
    }

    /** Resolve an event_sport id that belongs to the booted event, or 404. */
    private function ownedEventSportId(int $esid): int
    {
        $r = Event::rowsRaw(
            "SELECT id FROM event_sports WHERE id = ? AND event_id = ?",
            [$esid, (int)$this->event['id']]
        );
        if (!$r) $this->abort(404);
        return $esid;
    }

    /**
     * POST /lane-allocation/track/event-type
     * Bulk-set the event type (track|field) — and track count for track — on
     * the selected sport events.
     */
    public function trackEventType(): void
    {
        $this->boot();
        $this->requireAdmin();
        $this->verifyCsrf();
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}

        $ids  = (array)($_POST['event_sport_ids'] ?? []);
        $ids  = array_values(array_unique(array_map('intval', $ids)));
        $type = (string)($_POST['track_event_type'] ?? '');
        if (!in_array($type, ['track', 'field'], true)) {
            $this->redirect('/lane-allocation', 'Pick Track or Field.', 'warning');
        }
        $tracks = (int)($_POST['track_num_tracks'] ?? 0);
        $laps   = (int)($_POST['track_num_laps'] ?? 0);
        if ($type === 'track' && $tracks < 1) {
            $this->redirect('/lane-allocation', 'Enter the number of tracks (at least 1).', 'warning');
        }
        if (!$ids) {
            $this->redirect('/lane-allocation', 'Select at least one event.', 'warning');
        }
        $n = 0;
        foreach ($ids as $esid) {
            $r = Event::rowsRaw("SELECT id FROM event_sports WHERE id = ? AND event_id = ?",
                [$esid, (int)$this->event['id']]);
            if (!$r) continue;
            TrackConfig::setEventType($esid, $type, $tracks, $laps);
            $n++;
        }
        $this->redirect('/lane-allocation',
            "Updated {$n} event" . ($n === 1 ? '' : 's') . " as " . ucfirst($type) . '.');
    }

    /** True when the current request expects a JSON (AJAX) response. */
    private function wantsJson(): bool
    {
        return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    /** Rounds of one event-sport, shaped for the AJAX round manager. */
    private function roundsPayload(int $esid): array
    {
        $out = [];
        foreach (TrackConfig::roundsFor($esid) as $r) {
            $out[] = [
                'id'         => (int)$r['id'],
                'round_name' => (string)$r['round_name'],
                'num_heats'  => (int)$r['num_heats'],
            ];
        }
        return $out;
    }

    /** POST /lane-allocation/track/round-add — append a round to one event. */
    public function trackRoundAdd(): void
    {
        $this->boot();
        $this->requireAdmin();
        $this->verifyCsrf();
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}
        $ajax = $this->wantsJson();

        $esid  = (int)($_POST['event_sport_id'] ?? 0);
        $owned = Event::rowsRaw("SELECT id FROM event_sports WHERE id = ? AND event_id = ?",
            [$esid, (int)$this->event['id']]);
        if (!$owned) {
            if ($ajax) $this->json(['success' => false, 'message' => 'Event not found.']);
            $this->abort(404);
        }
        $name  = trim((string)($_POST['round_name'] ?? ''));
        $heats = (int)($_POST['num_heats'] ?? 0);
        if (!in_array($name, TrackConfig::ROUND_NAMES, true)) {
            if ($ajax) $this->json(['success' => false, 'message' => 'Pick a valid round name.']);
            $this->redirect('/lane-allocation', 'Pick a valid round name.', 'warning');
        }
        if ($heats < 1) {
            if ($ajax) $this->json(['success' => false, 'message' => 'Number of heats must be at least 1.']);
            $this->redirect('/lane-allocation', 'Number of heats must be at least 1.', 'warning');
        }
        TrackConfig::addRound($esid, $name, $heats);
        if ($ajax) {
            $this->json(['success' => true, 'message' => 'Round added.',
                'esid' => $esid, 'rounds' => $this->roundsPayload($esid)]);
        }
        $this->redirect('/lane-allocation', 'Round added.');
    }

    /** POST /lane-allocation/track/round-delete — remove a round. */
    public function trackRoundDelete(): void
    {
        $this->boot();
        $this->requireAdmin();
        $this->verifyCsrf();
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}
        $ajax = $this->wantsJson();

        $esid  = 0;
        $round = TrackConfig::findRound((int)($_POST['round_id'] ?? 0));
        if ($round) {
            $esid = (int)$round['event_sport_id'];
            $owned = Event::rowsRaw("SELECT id FROM event_sports WHERE id = ? AND event_id = ?",
                [$esid, (int)$this->event['id']]);
            if (!$owned) {
                if ($ajax) $this->json(['success' => false, 'message' => 'Round not found.']);
                $this->abort(404);
            }
            TrackConfig::deleteRound((int)$round['id']);
        }
        if ($ajax) {
            $this->json(['success' => true, 'message' => 'Round removed.',
                'esid' => $esid, 'rounds' => $esid ? $this->roundsPayload($esid) : []]);
        }
        $this->redirect('/lane-allocation', 'Round removed.');
    }

    /** POST /lane-allocation/track/heat-add — append one heat to a round. */
    public function trackHeatAdd(): void
    {
        $this->boot();
        $this->requireAdmin();
        $this->verifyCsrf();
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}

        $roundId = (int)($_POST['round_id'] ?? 0);
        $round   = TrackConfig::findRound($roundId);
        if (!$round) {
            $this->redirect('/lane-allocation', 'Round not found.', 'warning');
        }
        $this->ownedEventSportId((int)$round['event_sport_id']);   // 404 if not ours
        TrackConfig::addHeat($roundId);
        $this->redirect('/lane-allocation?round=' . $roundId, 'Heat added.');
    }

    /**
     * POST /lane-allocation/track/heat-delete — drop the last heat of a round,
     * only when no athletes are assigned to it.
     */
    public function trackHeatDelete(): void
    {
        $this->boot();
        $this->requireAdmin();
        $this->verifyCsrf();
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}

        $roundId = (int)($_POST['round_id'] ?? 0);
        $round   = TrackConfig::findRound($roundId);
        if (!$round) {
            $this->redirect('/lane-allocation', 'Round not found.', 'warning');
        }
        $this->ownedEventSportId((int)$round['event_sport_id']);   // 404 if not ours
        $status = TrackConfig::deleteLastEmptyHeat($roundId);
        $back   = '/lane-allocation?round=' . $roundId;
        switch ($status) {
            case 'ok':
                $this->redirect($back, 'Last heat removed.');
                break;
            case 'has_athletes':
                $this->redirect($back, 'The last heat still has athletes assigned. Remove them first.', 'warning');
                break;
            case 'min':
                $this->redirect($back, 'A round must keep at least one heat.', 'warning');
                break;
            default:
                $this->redirect($back, 'Round not found.', 'warning');
        }
    }

    /**
     * Build the Heats & Lane Draw workspace context for a round, or null when
     * no / an invalid round is selected. Verifies the round belongs to the
     * booted event. Available-athlete pool: the first round draws from the
     * approved athletes; a later round draws from the athletes marked Qualified
     * in the immediately previous round. The caller (a dropdown in the pool
     * panel) can override the source via $poolType ('approved' | 'qualified').
     */
    private function buildDrawContext(int $roundId, string $poolType = ''): ?array
    {
        if ($roundId <= 0) return null;
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}
        $ctx = TrackConfig::roundContext($roundId);
        if (!$ctx || (int)$ctx['event_id'] !== (int)$this->event['id']) return null;

        $esid   = (int)$ctx['event_sport_id'];
        $tracks = (int)($ctx['track_num_tracks'] ?? 0);

        // The round immediately preceding this one (for its event-sport).
        $prevRound = null;
        foreach (TrackConfig::roundsFor($esid) as $r) {
            if ((int)$r['id'] === $roundId) break;
            $prevRound = $r;
        }
        $hasPrev = $prevRound !== null;
        $isFirst = !$hasPrev;

        // Assignments grouped by heat.
        $byHeat = [];
        foreach (TrackConfig::assignmentsFor($roundId) as $a) {
            $byHeat[(int)$a['heat_no']][] = $a;
        }

        // Effective pool source: honour the dropdown, else default by position
        // (first / only round -> approved; later rounds -> qualified-from-prev).
        $effective = in_array($poolType, ['approved', 'qualified'], true)
                        ? $poolType
                        : ($hasPrev ? 'qualified' : 'approved');
        if ($effective === 'qualified' && !$hasPrev) $effective = 'approved';

        if ($effective === 'qualified') {
            $available = TrackConfig::qualifiedPool($roundId, (int)$prevRound['id']);
            $poolNote  = $available ? '' :
                'No athletes are marked Qualified in ' . (string)$prevRound['round_name']
                . ' yet — enter results in the Results tab and tick "Qualified".';
        } else {
            $available = TrackConfig::approvedPool($esid, (int)$this->event['id'], $roundId);
            $poolNote  = '';
        }

        return [
            'round'           => $ctx,
            'num_tracks'      => $tracks,
            'is_first'        => $isFirst,
            'by_heat'         => $byHeat,
            'available'       => $available,
            'pool_note'       => $poolNote,
            'pool_type'       => $effective,
            'has_prev'        => $hasPrev,
            'prev_round_name' => $hasPrev ? (string)$prevRound['round_name'] : '',
        ];
    }

    /** POST /lane-allocation/track/heat-assign — place an athlete in a heat. */
    public function heatAssign(): void
    {
        $this->boot();
        $this->requireAdmin();
        $this->verifyCsrf();
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}

        $roundId = (int)($_POST['round_id'] ?? 0);
        $regId   = (int)($_POST['registration_id'] ?? 0);
        $heatNo  = (int)($_POST['heat_no'] ?? 0);
        $trackNo = (int)($_POST['track_no'] ?? 0);
        $ctx = TrackConfig::roundContext($roundId);
        if (!$ctx || (int)$ctx['event_id'] !== (int)$this->event['id']) {
            $this->json(['success' => false, 'message' => 'Invalid round.']);
        }
        if ($heatNo < 1 || $heatNo > (int)$ctx['num_heats']) {
            $this->json(['success' => false, 'message' => 'Invalid heat.']);
        }
        $tracks = (int)($ctx['track_num_tracks'] ?? 0);
        if ($tracks < 1) {
            $this->json(['success' => false, 'message' => 'Set the number of tracks for this event first.']);
        }
        if ($trackNo < 1 || $trackNo > $tracks) {
            $this->json(['success' => false, 'message' => 'Invalid track.']);
        }
        $track = TrackConfig::assignLane($roundId, $regId, $heatNo, $trackNo, $tracks);
        if ($track < 1) {
            $this->json(['success' => false, 'message' => 'That track is already taken or the athlete is already placed.']);
        }
        $this->json(['success' => true, 'track_no' => $track, 'heat_no' => $heatNo]);
    }

    /** POST /lane-allocation/track/heat-unassign — remove an athlete from a heat. */
    public function heatUnassign(): void
    {
        $this->boot();
        $this->requireAdmin();
        $this->verifyCsrf();
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}

        $roundId = (int)($_POST['round_id'] ?? 0);
        $regId   = (int)($_POST['registration_id'] ?? 0);
        $ctx = TrackConfig::roundContext($roundId);
        if (!$ctx || (int)$ctx['event_id'] !== (int)$this->event['id']) {
            $this->json(['success' => false, 'message' => 'Invalid round.']);
        }
        TrackConfig::unassignLane($roundId, $regId);
        $this->json(['success' => true]);
    }

    /**
     * POST /lane-allocation/track/heat-results — save the recorded results
     * (time, rank, qualified flag) for one heat of a round. Payload carries
     * parallel maps keyed by registration_id: time[rid], rank[rid],
     * qualified[rid] (checkbox — present only when ticked).
     */
    public function heatResults(): void
    {
        $this->boot();
        $this->requireAdmin();
        $this->verifyCsrf();
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}

        $roundId = (int)($_POST['round_id'] ?? 0);
        $ctx = TrackConfig::roundContext($roundId);
        if (!$ctx || (int)$ctx['event_id'] !== (int)$this->event['id']) {
            $this->json(['success' => false, 'message' => 'Invalid round.']);
        }
        // Registrations actually assigned in this round (safety scope).
        $valid = [];
        foreach (TrackConfig::assignmentsFor($roundId) as $a) {
            $valid[(int)$a['registration_id']] = true;
        }
        $times = (array)($_POST['time']      ?? []);
        $ranks = (array)($_POST['rank']      ?? []);
        $quals = (array)($_POST['qualified'] ?? []);
        // One publish switch per heat form — applies to every row in the heat.
        $published = !empty($_POST['published']);
        $saved = 0;
        foreach ($times as $rid => $t) {
            $rid = (int)$rid;
            if (!isset($valid[$rid])) continue;
            $rank = (int)($ranks[$rid] ?? 0);
            $qual = !empty($quals[$rid]);
            TrackConfig::saveResult($roundId, $rid, (string)$t, $rank, $qual, $published);
            $saved++;
        }
        $this->json(['success' => true, 'saved' => $saved, 'published' => $published,
            'message' => 'Saved ' . $saved . ' result' . ($saved === 1 ? '' : 's')
                       . ($published ? ' · published' : '') . '.']);
    }

    /** Normalised institution key for the auto-allocator. */
    private function instKey($name): string
    {
        return strtolower(trim((string)$name));
    }

    /**
     * POST /lane-allocation/track/auto-allocate — spread the pending pool across
     * the round's heats so no institution appears twice in the same heat.
     * Largest institutions are placed first, each athlete going to the least
     * loaded heat that has a free track and doesn't already hold that
     * institution (the no-same-institution rule is relaxed only when an
     * institution has more athletes than there are heats). Existing assignments
     * are preserved — only empty tracks are filled.
     */
    public function autoAllocate(): void
    {
        $this->boot();
        $this->requireAdmin();
        $this->verifyCsrf();
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}

        $roundId  = (int)($_POST['round_id'] ?? 0);
        $poolType = (string)($_POST['pool'] ?? '');
        $draw = $this->buildDrawContext($roundId, $poolType);
        $back = '/lane-allocation?round=' . $roundId . ($poolType !== '' ? '&pool=' . urlencode($poolType) : '');
        if (!$draw) {
            $this->redirect('/lane-allocation', 'Pick a round first.', 'warning');
        }
        $tracks   = (int)$draw['num_tracks'];
        $numHeats = (int)$draw['round']['num_heats'];
        if ($tracks < 1) {
            $this->redirect($back, 'Set the number of tracks first (Events → Update Event Type).', 'warning');
        }
        $available = $draw['available'] ?? [];
        if (empty($available)) {
            $this->redirect($back, 'No pending participants to allocate.', 'warning');
        }

        // Current heat state: which tracks are taken and which institutions are
        // already present (so we respect manual assignments).
        $occupied = []; $insts = [];
        for ($h = 1; $h <= $numHeats; $h++) { $occupied[$h] = []; $insts[$h] = []; }
        foreach (($draw['by_heat'] ?? []) as $h => $arr) {
            $h = (int)$h;
            if ($h < 1 || $h > $numHeats) continue;
            foreach ($arr as $a) {
                $occupied[$h][(int)$a['track_no']] = true;
                $k = $this->instKey($a['unit_name'] ?? '');
                if ($k !== '') $insts[$h][$k] = true;
            }
        }

        // Group the pending pool by institution, largest groups first.
        $groups = [];
        foreach ($available as $a) {
            $groups[$this->instKey($a['unit_name'] ?? '')][] = $a;
        }
        $ordered = [];
        foreach ($groups as $k => $items) { $ordered[] = ['key' => $k, 'items' => $items, 'n' => count($items)]; }
        usort($ordered, fn($x, $y) => ($y['n'] <=> $x['n']) ?: strcmp($x['key'], $y['key']));

        $load = fn($h) => count($occupied[$h]);
        $assigned = 0; $failed = 0;
        foreach ($ordered as $g) {
            foreach ($g['items'] as $a) {
                $regId = (int)$a['registration_id'];
                $k     = $g['key'];

                // Prefer a heat with a free track that doesn't hold this institution.
                $best = null;
                for ($h = 1; $h <= $numHeats; $h++) {
                    if ($load($h) >= $tracks) continue;
                    if ($k !== '' && isset($insts[$h][$k])) continue;
                    if ($best === null || $load($h) < $load($best)) $best = $h;
                }
                // Relax the institution rule only if unavoidable.
                if ($best === null) {
                    for ($h = 1; $h <= $numHeats; $h++) {
                        if ($load($h) >= $tracks) continue;
                        if ($best === null || $load($h) < $load($best)) $best = $h;
                    }
                }
                if ($best === null) { $failed++; continue; }  // every heat full

                $track = 0;
                for ($t = 1; $t <= $tracks; $t++) { if (empty($occupied[$best][$t])) { $track = $t; break; } }
                if ($track === 0) { $failed++; continue; }

                if (TrackConfig::assignLane($roundId, $regId, $best, $track, $tracks) < 1) { $failed++; continue; }
                $occupied[$best][$track] = true;
                if ($k !== '') $insts[$best][$k] = true;
                $assigned++;
            }
        }

        $msg = 'Auto-allocated ' . $assigned . ' athlete' . ($assigned === 1 ? '' : 's') . '.';
        if ($failed) $msg .= ' ' . $failed . ' could not be placed (heats full).';
        $this->redirect($back, $msg, $assigned ? 'success' : 'warning');
    }

    /**
     * GET /lane-allocation/track/rounds-report — Participants, Rounds & Heats
     * summary (landscape): per sport-event, the submitted / approved counts,
     * primary rounds (ceil(approved / tracks)), number of laps, and for each
     * round (Preliminary / Semifinal / Final) the heats and heats×laps, with a
     * per-event Total and a grand-total row.
     */
    public function roundsReport(): void
    {
        $this->boot();
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}
        $report = $this->buildRoundsReport((int)$this->event['id']);
        $event  = $this->event;
        $rows   = $report['rows'];
        $totals = $report['totals'];
        require APP_ROOT . '/views/lane-allocation/rounds-report-print.php';
    }

    /**
     * Build the rows + grand totals for the Participants, Rounds & Heats report.
     * One row per sport-event with at least one approved athlete.
     */
    private function buildRoundsReport(int $eventId): array
    {
        $raw = Event::rowsRaw(
            "SELECT es.id AS esid, es.event_code,
                    es.track_num_tracks, es.track_num_laps, es.track_event_type,
                    sev.name AS sport_event_name, sev.gender AS event_gender,
                    sc.name AS category_name, sc.abbreviation AS category_abbr,
                    ac.name AS age_name, ac.sort_order AS age_sort,
                    COUNT(DISTINCT CASE WHEN er.admin_review_status IN ('pending','approved')
                                        THEN er.athlete_id END) AS submitted,
                    COUNT(DISTINCT CASE WHEN er.admin_review_status = 'approved'
                                        THEN er.athlete_id END) AS approved
               FROM event_sports es
          LEFT JOIN sport_events     sev ON sev.id = es.sport_event_id
          LEFT JOIN sport_categories sc  ON sc.id  = sev.category_id
          LEFT JOIN age_categories   ac  ON ac.id  = sev.age_category_id
          LEFT JOIN event_registration_items eri ON eri.event_sport_id = es.id
          LEFT JOIN event_registrations er ON er.id = eri.registration_id
              WHERE es.event_id = ?
              GROUP BY es.id, es.event_code, es.track_num_tracks, es.track_num_laps, es.track_event_type,
                       sev.name, sev.gender, sc.name, sc.abbreviation, ac.name, ac.sort_order
             HAVING approved > 0
              ORDER BY (sc.abbreviation IS NULL OR sc.abbreviation = ''), sc.abbreviation, sc.name,
                       (ac.sort_order IS NULL), ac.sort_order, ac.name, es.event_code, sev.gender",
            [$eventId]
        );
        $ids  = array_map(fn($r) => (int)$r['esid'], $raw);
        $rmap = TrackConfig::roundsForMany($ids);

        $rows = [];
        $tot  = ['submitted'=>0,'approved'=>0,'prelim_h'=>0,'prelim_l'=>0,'quarter_h'=>0,'quarter_l'=>0,
                 'semi_h'=>0,'semi_l'=>0,'final_h'=>0,'final_l'=>0,'total_h'=>0,'total_l'=>0];
        foreach ($raw as $r) {
            $esid      = (int)$r['esid'];
            $laps      = (int)($r['track_num_laps'] ?? 0);
            $tracks    = (int)($r['track_num_tracks'] ?? 0);
            $approved  = (int)$r['approved'];
            $submitted = (int)$r['submitted'];
            $primary   = $tracks > 0 ? (int)ceil($approved / $tracks) : null;

            // Sum heats per round name (in case a round name repeats).
            $byName = ['Preliminary heats'=>0, 'Quarterfinal heats'=>0, 'Semifinal heats'=>0, 'Final'=>0];
            foreach ($rmap[$esid] ?? [] as $rd) {
                $nm = (string)$rd['round_name'];
                if (isset($byName[$nm])) $byName[$nm] += (int)$rd['num_heats'];
            }
            $ph = $byName['Preliminary heats']; $qh = $byName['Quarterfinal heats'];
            $sh = $byName['Semifinal heats'];   $fh = $byName['Final'];
            $pl = $ph * $laps; $ql = $qh * $laps; $sl = $sh * $laps; $fl = $fh * $laps;
            $th = $ph + $qh + $sh + $fh; $tl = $pl + $ql + $sl + $fl;

            $rows[] = [
                'category'     => trim((string)($r['category_abbr'] ?? '')) ?: trim((string)($r['category_name'] ?? '')),
                'sport_event'  => trim((string)($r['sport_event_name'] ?? '')) ?: trim((string)($r['event_code'] ?? '')),
                'age_name'     => trim((string)($r['age_name'] ?? '')),
                'submitted'    => $submitted,
                'approved'     => $approved,
                'primary'      => $primary,
                'laps'         => $laps,
                'prelim_h'=>$ph,  'prelim_l'=>$pl,  'quarter_h'=>$qh, 'quarter_l'=>$ql,
                'semi_h'=>$sh,    'semi_l'=>$sl,    'final_h'=>$fh,   'final_l'=>$fl,
                'total_h'=>$th,   'total_l'=>$tl,
            ];
            $tot['submitted'] += $submitted; $tot['approved'] += $approved;
            $tot['prelim_h']  += $ph; $tot['prelim_l']  += $pl;
            $tot['quarter_h'] += $qh; $tot['quarter_l'] += $ql;
            $tot['semi_h']    += $sh; $tot['semi_l']    += $sl;
            $tot['final_h']   += $fh; $tot['final_l']   += $fl;
            $tot['total_h']   += $th; $tot['total_l']   += $tl;
        }
        return ['rows' => $rows, 'totals' => $tot];
    }

    /**
     * GET /lane-allocation/track/score-sheet?round=… — printable score sheet
     * (landscape) with one block per heat and blank time / rank / remarks
     * columns for the field referee.
     */
    public function scoreSheet(): void
    {
        $this->boot();
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}
        $roundId = (int)($_GET['round'] ?? 0);
        $ctx = TrackConfig::roundContext($roundId);
        if (!$ctx || (int)$ctx['event_id'] !== (int)$this->event['id']) {
            $this->redirect('/lane-allocation', 'Pick a round to print.', 'warning');
        }
        $byHeat = [];
        foreach (TrackConfig::assignmentsFor($roundId) as $a) {
            $byHeat[(int)$a['heat_no']][] = $a;
        }
        $event       = $this->event;
        $round       = $ctx;
        $heats       = $byHeat;
        $orientation = (($_GET['orientation'] ?? '') === 'landscape') ? 'landscape' : 'portrait';
        require APP_ROOT . '/views/lane-allocation/score-sheet-print.php';
    }

    /**
     * GET /lane-allocation/track/participants-list?round=… — flat printable
     * participants list (portrait) for one round: event heading + logo, sport
     * event, number of laps, round label, then Sl.No / Chest / Athlete / DOB /
     * School for every approved participant.
     */
    public function participantsList(): void
    {
        $this->boot();
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}
        $roundId = (int)($_GET['round'] ?? 0);
        $ctx = TrackConfig::roundContext($roundId);
        if (!$ctx || (int)$ctx['event_id'] !== (int)$this->event['id']) {
            $this->redirect('/lane-allocation', 'Pick a round to print.', 'warning');
        }
        $event        = $this->event;
        $round        = $ctx;
        $participants = TrackConfig::approvedParticipants((int)$ctx['event_sport_id'], (int)$this->event['id']);
        $orientation  = (($_GET['orientation'] ?? '') === 'landscape') ? 'landscape' : 'portrait';
        require APP_ROOT . '/views/lane-allocation/participants-list-print.php';
    }

    /**
     * GET /lane-allocation/track/results-report?round=… — printable results
     * report (landscape) covering every heat of a round: rank, chest, athlete,
     * institution, time and qualified flag.
     */
    public function resultsReport(): void
    {
        $this->boot();
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}
        $roundId = (int)($_GET['round'] ?? 0);
        $ctx = TrackConfig::roundContext($roundId);
        if (!$ctx || (int)$ctx['event_id'] !== (int)$this->event['id']) {
            $this->redirect('/lane-allocation', 'Pick a round to print.', 'warning');
        }
        $byHeat = [];
        foreach (TrackConfig::assignmentsFor($roundId) as $a) {
            $byHeat[(int)$a['heat_no']][] = $a;
        }
        $event = $this->event;
        $round = $ctx;
        $heats = $byHeat;
        require APP_ROOT . '/views/lane-allocation/results-report-print.php';
    }

    /**
     * GET /lane-allocation/track/heat-compact?round=… — compact heat-wise sheet
     * (portrait): eight small heat tables per page laid out two-per-row × four
     * rows. Each table shows just Chest Number (no #) and athlete name under a
     * small heat heading.
     */
    public function heatCompact(): void
    {
        $this->boot();
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}
        $roundId = (int)($_GET['round'] ?? 0);
        $ctx = TrackConfig::roundContext($roundId);
        if (!$ctx || (int)$ctx['event_id'] !== (int)$this->event['id']) {
            $this->redirect('/lane-allocation', 'Pick a round to print.', 'warning');
        }
        $byHeat = [];
        foreach (TrackConfig::assignmentsFor($roundId) as $a) {
            $byHeat[(int)$a['heat_no']][] = $a;
        }
        $event = $this->event;
        $round = $ctx;
        $heats = $byHeat;
        require APP_ROOT . '/views/lane-allocation/heat-compact-print.php';
    }

    /** GET /lane-allocation/data — JSON snapshot powering the workspace. */
    public function data(): void
    {
        $this->boot();
        $eventId   = (int)$this->event['id'];
        $unitScope = null;

        $relayLanes = LaneAllocation::relayLanes($eventId);
        if ($this->actor['mode'] === 'unit') {
            // Unit users see only lanes assigned to one of their units.
            $ids = $this->actor['unit_ids'];
            $relayLanes = array_values(array_filter($relayLanes,
                fn($r) => in_array((int)($r['assigned_unit_id'] ?? 0), $ids, true)));
        }

        // Pending athletes — unit users restricted to their own units.
        if ($this->actor['mode'] === 'unit') {
            $pending = [];
            foreach ($this->actor['unit_ids'] as $uid) {
                foreach (LaneAllocation::pendingAthletes($eventId, $uid, null) as $p) {
                    $pending[] = $p;
                }
            }
        } else {
            $pending = LaneAllocation::pendingAthletes($eventId, null, null);
        }

        $this->json([
            'success'      => true,
            'mode'         => $this->actor['mode'],
            'unit_ids'     => $this->actor['unit_ids'],
            'relay_lanes'  => array_values($relayLanes),
            'units'        => LaneAllocation::unitsWithCounts($eventId),
            // Categories are admin-only — unit users can't change the
            // category on a lane.
            'categories'   => $this->actor['mode'] === 'admin'
                                ? LaneAllocation::categoriesForEvent($eventId) : [],
            'pending'      => $pending,
            'pivot'        => $this->actor['mode'] === 'admin' ? LaneAllocation::pivot($eventId) : null,
            'relay_numbers'=> LaneAllocation::relayNumbers($eventId),
            'venues'       => LaneAllocation::venuesForEvent($eventId),
            'ranges'       => LaneAllocation::rangesForEvent($eventId),
            'relay_meta'   => LaneAllocation::relayMeta($eventId),
            'category_abbr'=> LaneAllocation::categoryAbbr($eventId),
            'last_modified'=> LaneAllocation::lastModified($eventId),
            'unit_access'  => (int)($this->event['unit_lane_allocation_enabled'] ?? 0),
        ]);
    }

    /** POST /lane-allocation/assign — set/clear a lane's unit, athlete or category. */
    public function assign(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $relayId = (int)($_POST['relay_id'] ?? 0);
        $laneId  = (int)($_POST['lane_id'] ?? 0);
        $field   = $_POST['field'] ?? '';          // 'unit' | 'athlete' | 'category'
        // value is an ID for unit/athlete; a category NAME (string) for category.
        $rawValue = (string)($_POST['value'] ?? '');
        $value    = (int)$rawValue;

        $lane = LaneAllocation::findLane($relayId, $laneId);
        if (!$lane || (int)$lane['event_id'] !== (int)$this->event['id']) {
            $this->json(['success' => false, 'message' => 'Lane not found for this event.']);
        }

        if ($field === 'category') {
            if ($this->actor['mode'] !== 'admin') {
                $this->json(['success' => false, 'message' => 'Only the Lane Allocation Admin can change the event category.']);
            }
            $newCat = trim($rawValue);
            if ($newCat !== '') {
                // Category must be one configured on this event.
                $known = Event::rowsRaw(
                    "SELECT 1 FROM event_sports es
                       JOIN sport_events     se ON se.id = es.sport_event_id
                       JOIN sport_categories sc ON sc.id = se.category_id
                      WHERE es.event_id = ? AND sc.name = ? LIMIT 1",
                    [(int)$this->event['id'], $newCat]
                );
                if (!$known) {
                    $this->json(['success' => false,
                        'message' => "Category \"{$newCat}\" is not configured on this event."]);
                }
                // If a lane already has an athlete, the new category must be
                // registered for that athlete — otherwise refuse so we don't
                // silently invalidate the allocation.
                if (!empty($lane['assigned_registration_id'])) {
                    $catOk = Event::rowsRaw(
                        "SELECT 1 FROM event_registration_items eri
                           JOIN event_sports     es ON es.id = eri.event_sport_id
                           JOIN sport_events     se ON se.id = es.sport_event_id
                           JOIN sport_categories sc ON sc.id = se.category_id
                          WHERE eri.registration_id = ? AND sc.name = ? LIMIT 1",
                        [(int)$lane['assigned_registration_id'], $newCat]
                    );
                    if (!$catOk) {
                        $this->json(['success' => false,
                            'message' => 'Cannot change category: the athlete allocated to this lane is not registered for ' . $newCat . '. Remove the athlete first.']);
                    }
                }
            }
            LaneAllocation::updateLane($relayId, $laneId,
                ['category' => $newCat !== '' ? $newCat : null], $this->actor['name']);
            $this->json(['success' => true]);
        }

        if ($field === 'unit') {
            if ($this->actor['mode'] !== 'admin') {
                $this->json(['success' => false, 'message' => 'Only the Lane Allocation Admin can assign units.']);
            }
            if ($value) {
                $unit = EventUnit::find($value);
                if (!$unit || (int)$unit['event_id'] !== (int)$this->event['id']) {
                    $this->json(['success' => false, 'message' => 'Invalid unit for this event.']);
                }
            }
            // Changing the unit clears any athlete that no longer belongs.
            $data = ['assigned_unit_id' => $value ?: null];
            if (!$value) $data['assigned_registration_id'] = null;
            LaneAllocation::updateLane($relayId, $laneId, $data, $this->actor['name']);
            $this->json(['success' => true]);
        }

        if ($field === 'athlete') {
            // Unit users may only touch lanes belonging to their own units.
            if ($this->actor['mode'] === 'unit'
                && !in_array((int)($lane['assigned_unit_id'] ?? 0), $this->actor['unit_ids'], true)) {
                $this->json(['success' => false, 'message' => 'This lane is not allocated to your unit.']);
            }
            if ($value) {
                $reg = Event::rowsRaw(
                    "SELECT id, unit_id FROM event_registrations
                      WHERE id = ? AND event_id = ? AND admin_review_status = 'approved'",
                    [$value, (int)$this->event['id']]
                );
                $reg = $reg[0] ?? null;
                if (!$reg) {
                    $this->json(['success' => false, 'message' => 'Athlete is not an approved participant of this event.']);
                }
                // Athlete's unit should match the lane's assigned unit.
                if (!empty($lane['assigned_unit_id'])
                    && (int)$reg['unit_id'] !== (int)$lane['assigned_unit_id']) {
                    $this->json(['success' => false,
                        'message' => 'Cannot allocate: the athlete belongs to a different unit than this lane.']);
                }
                if ($this->actor['mode'] === 'unit'
                    && !in_array((int)$reg['unit_id'], $this->actor['unit_ids'], true)) {
                    $this->json(['success' => false, 'message' => 'You can only allot athletes from your own unit.']);
                }
                // Category match — the lane must have a category and the
                // athlete must be registered for it.
                $laneCat = trim((string)($lane['category'] ?? ''));
                if ($laneCat === '') {
                    $this->json(['success' => false,
                        'message' => 'This lane has no Event Category configured — cannot allot an athlete.']);
                }
                $catOk = Event::rowsRaw(
                    "SELECT 1 FROM event_registration_items eri
                       JOIN event_sports     es ON es.id = eri.event_sport_id
                       JOIN sport_events     se ON se.id = es.sport_event_id
                       JOIN sport_categories sc ON sc.id = se.category_id
                      WHERE eri.registration_id = ? AND sc.name = ? LIMIT 1",
                    [$value, $laneCat]
                );
                if (!$catOk) {
                    $this->json(['success' => false,
                        'message' => "Cannot allocate: the athlete is not registered for this lane's "
                                   . "Event Category (" . $laneCat . ")."]);
                }
                // One lane per athlete *per category* — an athlete registered
                // for multiple categories should hold one lane in each. Only
                // clear other lanes of the same category as the target lane.
                Event::rowsRaw(
                    "UPDATE event_relay_lanes erl
                       JOIN event_relays r ON r.id = erl.relay_id
                        SET erl.assigned_registration_id = NULL
                      WHERE r.event_id = ?
                        AND erl.assigned_registration_id = ?
                        AND erl.category = ?",
                    [(int)$this->event['id'], $value, $laneCat]
                );
            }
            LaneAllocation::updateLane($relayId, $laneId,
                ['assigned_registration_id' => $value ?: null], $this->actor['name']);
            $this->json(['success' => true]);
        }

        $this->json(['success' => false, 'message' => 'Unknown field.']);
    }

    /** POST /lane-allocation/toggle-unit-access — admin only. */
    public function toggleUnitAccess(): void
    {
        $this->boot();
        $this->verifyCsrf();
        if ($this->actor['mode'] !== 'admin') $this->abort(403);
        $enabled = !empty($_POST['enabled']) ? 1 : 0;
        Event::updatePartial((int)$this->event['id'], ['unit_lane_allocation_enabled' => $enabled]);
        $this->json(['success' => true, 'enabled' => $enabled]);
    }
}
