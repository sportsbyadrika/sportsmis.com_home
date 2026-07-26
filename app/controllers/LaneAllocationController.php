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
            // Optional lane-draw workspace for a chosen round (?round=).
            $draw = $this->buildDrawContext((int)($_GET['round'] ?? 0));
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
                    es.track_event_type, es.track_num_tracks,
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
                'event_code'     => trim((string)($r['event_code'] ?? '')),
                'approved'       => $approved,
                'type'           => $type,
                'num_tracks'     => $tracks,
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
            TrackConfig::setEventType($esid, $type, $tracks);
            $n++;
        }
        $this->redirect('/lane-allocation',
            "Updated {$n} event" . ($n === 1 ? '' : 's') . " as " . ucfirst($type) . '.');
    }

    /** POST /lane-allocation/track/round-add — append a round to one event. */
    public function trackRoundAdd(): void
    {
        $this->boot();
        $this->requireAdmin();
        $this->verifyCsrf();
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}

        $esid  = $this->ownedEventSportId((int)($_POST['event_sport_id'] ?? 0));
        $name  = trim((string)($_POST['round_name'] ?? ''));
        $heats = (int)($_POST['num_heats'] ?? 0);
        if (!in_array($name, TrackConfig::ROUND_NAMES, true)) {
            $this->redirect('/lane-allocation', 'Pick a valid round name.', 'warning');
        }
        if ($heats < 1) {
            $this->redirect('/lane-allocation', 'Number of heats must be at least 1.', 'warning');
        }
        TrackConfig::addRound($esid, $name, $heats);
        $this->redirect('/lane-allocation', 'Round added.');
    }

    /** POST /lane-allocation/track/round-delete — remove a round. */
    public function trackRoundDelete(): void
    {
        $this->boot();
        $this->requireAdmin();
        $this->verifyCsrf();
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}

        $round = TrackConfig::findRound((int)($_POST['round_id'] ?? 0));
        if ($round) {
            $this->ownedEventSportId((int)$round['event_sport_id']);   // 404 if not ours
            TrackConfig::deleteRound((int)$round['id']);
        }
        $this->redirect('/lane-allocation', 'Round removed.');
    }

    /**
     * Build the Heats & Lane Draw workspace context for a round, or null when
     * no / an invalid round is selected. Verifies the round belongs to the
     * booted event. Available-athlete pool: first round = all approved; a later
     * round = qualified athletes from the previous round (pending the Scoring
     * module, so empty for now).
     */
    private function buildDrawContext(int $roundId): ?array
    {
        if ($roundId <= 0) return null;
        try { Schema::ensureTrackConfig(); } catch (\Throwable $e) {}
        $ctx = TrackConfig::roundContext($roundId);
        if (!$ctx || (int)$ctx['event_id'] !== (int)$this->event['id']) return null;

        $esid    = (int)$ctx['event_sport_id'];
        $tracks  = (int)($ctx['track_num_tracks'] ?? 0);
        $isFirst = TrackConfig::isFirstRound($esid, (int)$ctx['round_order']);

        // Assignments grouped by heat.
        $byHeat = [];
        foreach (TrackConfig::assignmentsFor($roundId) as $a) {
            $byHeat[(int)$a['heat_no']][] = $a;
        }

        if ($isFirst) {
            $available = TrackConfig::approvedPool($esid, (int)$this->event['id'], $roundId);
            $poolNote  = '';
        } else {
            // Qualified-from-previous-round comes with the Scoring module.
            $available = [];
            $poolNote  = 'Qualified athletes from the previous round will appear here once results are entered (Scoring).';
        }

        return [
            'round'      => $ctx,
            'num_tracks' => $tracks,
            'is_first'   => $isFirst,
            'by_heat'    => $byHeat,
            'available'  => $available,
            'pool_note'  => $poolNote,
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
        $event   = $this->event;
        $round   = $ctx;
        $heats   = $byHeat;
        require APP_ROOT . '/views/lane-allocation/score-sheet-print.php';
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
