<?php
namespace Controllers;

use Core\{Controller, Auth};
use Models\{Schema, Event, EventUnit, UnitUser, EventStaff, LaneAllocation};

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
        $this->renderWith($this->actor['layout'], 'lane-allocation/index', [
            'actor'   => $this->actor,
            'event'   => $this->event,
            // staff layout needs $staff for the navbar; unit layout needs $unit_user.
            'staff'     => Auth::eventStaff(),
            'unit_user' => Auth::unitUser(),
            'flash'   => $this->flash(),
        ]);
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
            'pending'      => $pending,
            'pivot'        => $this->actor['mode'] === 'admin' ? LaneAllocation::pivot($eventId) : null,
            'relay_numbers'=> LaneAllocation::relayNumbers($eventId),
            'category_abbr'=> LaneAllocation::categoryAbbr($eventId),
            'last_modified'=> LaneAllocation::lastModified($eventId),
            'unit_access'  => (int)($this->event['unit_lane_allocation_enabled'] ?? 0),
        ]);
    }

    /** POST /lane-allocation/assign — set/clear a lane's unit or athlete. */
    public function assign(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $relayId = (int)($_POST['relay_id'] ?? 0);
        $laneId  = (int)($_POST['lane_id'] ?? 0);
        $field   = $_POST['field'] ?? '';          // 'unit' | 'athlete'
        $value   = (int)($_POST['value'] ?? 0);    // id, or 0 to clear

        $lane = LaneAllocation::findLane($relayId, $laneId);
        if (!$lane || (int)$lane['event_id'] !== (int)$this->event['id']) {
            $this->json(['success' => false, 'message' => 'Lane not found for this event.']);
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
