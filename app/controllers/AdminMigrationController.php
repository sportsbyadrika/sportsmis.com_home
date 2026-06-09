<?php
namespace Controllers;

use Core\{Controller, Auth};
use Models\{Event, Institution};

/**
 * Super-admin: copy / migrate event-level master data from one event
 * to another. Multi-step wizard:
 *
 *   1. /admin/event-migrate            — pick source & destination events + sets
 *   2. /admin/event-migrate/items      — pick individual items per selected set
 *   3. /admin/event-migrate/preview    — dry-run + per-set destination-blank check
 *   4. /admin/event-migrate/run        — commit (single transaction)
 *
 * State is held in $_SESSION['event_migrate'] so each step can refer
 * back to the prior selections. Per-set partial copy semantics: any
 * set whose destination table is non-blank is skipped with a clear
 * message; remaining sets still copy.
 */
class AdminMigrationController extends Controller
{
    /** Supported data sets, in commit order. Relays MUST run after
     *  ranges so the distance / lane id remapping is ready. */
    private const SETS = ['sports', 'units', 'items', 'ranges', 'relays'];

    private const SET_LABELS = [
        'sports' => 'Sports in this Event',
        'units'  => 'Units / Clubs / Institutions',
        'items'  => 'Sports Items / Weapons (allowed for athletes)',
        'ranges' => 'Shooting Range Venues, Distances + Lanes',
        'relays' => 'Relays + Lane Allocations',
    ];

    private const SET_TABLES = [
        'sports' => 'event_sports',
        'units'  => 'event_units',
        'items'  => 'event_sport_items',
        'ranges' => 'event_shooting_ranges',
        'relays' => 'event_relays',
    ];

    private function boot(): void
    {
        $this->requireAuth('super_admin');
    }

    /** GET /admin/event-migrate */
    public function index(): void
    {
        $this->boot();
        // Reset wizard state on a fresh visit (no ?keep=1) so a back-to-
        // start link always lands clean.
        if (empty($_GET['keep'])) unset($_SESSION['event_migrate']);
        $this->renderWith('app', 'admin/migrate/index', [
            'institutions' => Institution::getAll('active'),
            'sets'         => self::SETS,
            'set_labels'   => self::SET_LABELS,
            'state'        => $_SESSION['event_migrate'] ?? null,
            'flash'        => $this->flash(),
        ]);
    }

    /** GET /admin/event-migrate/events-for-institution?id= */
    public function eventsForInstitution(): void
    {
        $this->boot();
        $instId = (int)($_GET['id'] ?? 0);
        if (!$instId) $this->json(['success' => true, 'events' => []]);
        $events = Event::rowsRaw(
            "SELECT id, name, event_code, event_date_from
               FROM events
              WHERE institution_id = ?
              ORDER BY event_date_from DESC, id DESC",
            [$instId]
        );
        $this->json(['success' => true, 'events' => $events]);
    }

    /** POST /admin/event-migrate — Step 1 → Step 2 */
    public function saveStep1(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $srcId = (int)($_POST['source_event_id'] ?? 0);
        $dstId = (int)($_POST['dest_event_id']   ?? 0);
        $sets  = (array)($_POST['sets']          ?? []);
        $sets  = array_values(array_intersect(self::SETS, $sets));

        if (!$srcId || !$dstId) {
            $this->redirect('/admin/event-migrate?keep=1',
                'Pick both a source and a destination event.', 'warning');
        }
        if ($srcId === $dstId) {
            $this->redirect('/admin/event-migrate?keep=1',
                'Source and destination must be different events.', 'warning');
        }
        if (!$sets) {
            $this->redirect('/admin/event-migrate?keep=1',
                'Pick at least one data set to copy.', 'warning');
        }
        // Force dependency: copying relays requires copying ranges too,
        // since event_relays carry distance_id / event_relay_lanes carry
        // lane_id that must point at copied destination rows.
        if (in_array('relays', $sets, true) && !in_array('ranges', $sets, true)) {
            $sets[] = 'ranges';
            $sets   = array_values(array_intersect(self::SETS, $sets));
        }

        $_SESSION['event_migrate'] = [
            'source_event_id' => $srcId,
            'dest_event_id'   => $dstId,
            'sets'            => $sets,
            'items'           => [],
        ];
        $this->redirect('/admin/event-migrate/items');
    }

    /** GET /admin/event-migrate/items */
    public function items(): void
    {
        $this->boot();
        $state = $_SESSION['event_migrate'] ?? null;
        if (!$state) $this->redirect('/admin/event-migrate');
        $srcId = (int)$state['source_event_id'];

        $data = [
            'sports' => in_array('sports', $state['sets'], true) ? $this->fetchSports($srcId) : [],
            'units'  => in_array('units',  $state['sets'], true) ? $this->fetchUnits($srcId)  : [],
            'items'  => in_array('items',  $state['sets'], true) ? $this->fetchItems($srcId)  : [],
            'ranges' => in_array('ranges', $state['sets'], true) ? $this->fetchRanges($srcId) : [],
            'relays' => in_array('relays', $state['sets'], true) ? $this->fetchRelays($srcId) : [],
        ];

        $this->renderWith('app', 'admin/migrate/items', [
            'state'      => $state,
            'set_labels' => self::SET_LABELS,
            'data'       => $data,
            'src_event'  => Event::findById($srcId),
            'dst_event'  => Event::findById((int)$state['dest_event_id']),
            'flash'      => $this->flash(),
        ]);
    }

    /** POST /admin/event-migrate/items — Step 2 → Step 3 */
    public function saveStep2(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $state = $_SESSION['event_migrate'] ?? null;
        if (!$state) $this->redirect('/admin/event-migrate');

        $state['items'] = [
            'sports' => array_values(array_unique(array_map('intval', (array)($_POST['sports'] ?? [])))),
            'units'  => array_values(array_unique(array_map('intval', (array)($_POST['units']  ?? [])))),
            'items'  => array_values(array_unique(array_map('intval', (array)($_POST['items']  ?? [])))),
            'ranges' => array_values(array_unique(array_map('intval', (array)($_POST['ranges'] ?? [])))),
            'relays' => array_values(array_unique(array_map('intval', (array)($_POST['relays'] ?? [])))),
        ];
        $_SESSION['event_migrate'] = $state;
        $this->redirect('/admin/event-migrate/preview');
    }

    /** GET /admin/event-migrate/preview */
    public function preview(): void
    {
        $this->boot();
        $state = $_SESSION['event_migrate'] ?? null;
        if (!$state) $this->redirect('/admin/event-migrate');

        $dstId  = (int)$state['dest_event_id'];
        $checks = [];
        foreach ($state['sets'] as $set) {
            $count = (int)(Event::rowsRaw(
                "SELECT COUNT(*) AS c FROM " . self::SET_TABLES[$set] . " WHERE event_id = ?",
                [$dstId]
            )[0]['c'] ?? 0);
            $picked = count($state['items'][$set] ?? []);
            $checks[$set] = [
                'label'        => self::SET_LABELS[$set],
                'picked'       => $picked,
                'dest_count'   => $count,
                'will_copy'    => $count === 0 && $picked > 0,
                'skip_reason'  => $count > 0
                    ? "Destination already has {$count} row(s) in " . self::SET_TABLES[$set]
                    : ($picked === 0 ? 'No items selected' : ''),
            ];
        }

        $this->renderWith('app', 'admin/migrate/preview', [
            'state'      => $state,
            'set_labels' => self::SET_LABELS,
            'checks'     => $checks,
            'src_event'  => Event::findById((int)$state['source_event_id']),
            'dst_event'  => Event::findById($dstId),
            'flash'      => $this->flash(),
        ]);
    }

    /** POST /admin/event-migrate/run — commit */
    public function run(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $state = $_SESSION['event_migrate'] ?? null;
        if (!$state) $this->redirect('/admin/event-migrate');

        $srcId = (int)$state['source_event_id'];
        $dstId = (int)$state['dest_event_id'];
        $sets  = $state['sets'];
        $items = $state['items'];

        $report = [
            'copied'  => [],
            'skipped' => [],
            'error'   => null,
            'src_event' => Event::findById($srcId),
            'dst_event' => Event::findById($dstId),
        ];

        try {
            Event::rowsRaw('START TRANSACTION', []);

            $distIdMap = []; $laneIdMap = [];

            // 1. Sports
            if (in_array('sports', $sets, true) && $items['sports']) {
                if (!$this->destBlank($dstId, 'event_sports')) {
                    $report['skipped']['sports'] = 'event_sports already has rows';
                } else {
                    $n = $this->copySports($srcId, $dstId, $items['sports']);
                    $report['copied']['sports'] = $n;
                }
            }

            // 2. Units
            if (in_array('units', $sets, true) && $items['units']) {
                if (!$this->destBlank($dstId, 'event_units')) {
                    $report['skipped']['units'] = 'event_units already has rows';
                } else {
                    $n = $this->copyUnits($srcId, $dstId, $items['units']);
                    $report['copied']['units'] = $n;
                }
            }

            // 3. Sports Items
            if (in_array('items', $sets, true) && $items['items']) {
                if (!$this->destBlank($dstId, 'event_sport_items')) {
                    $report['skipped']['items'] = 'event_sport_items already has rows';
                } else {
                    $n = $this->copyItems($srcId, $dstId, $items['items']);
                    $report['copied']['items'] = $n;
                }
            }

            // 4. Shooting Ranges (must run before Relays)
            if (in_array('ranges', $sets, true) && $items['ranges']) {
                if (!$this->destBlank($dstId, 'event_shooting_ranges')) {
                    $report['skipped']['ranges'] = 'event_shooting_ranges already has rows';
                } else {
                    [$n, $distIdMap, $laneIdMap] = $this->copyRanges($srcId, $dstId, $items['ranges']);
                    $report['copied']['ranges'] = $n;
                }
            }

            // 5. Relays — needs distIdMap / laneIdMap from step 4
            if (in_array('relays', $sets, true) && $items['relays']) {
                if (!$this->destBlank($dstId, 'event_relays')) {
                    $report['skipped']['relays'] = 'event_relays already has rows';
                } elseif (!isset($report['copied']['ranges'])) {
                    $report['skipped']['relays'] = 'Required Shooting Ranges step did not run — relay distance/lane ids can\'t be remapped';
                } else {
                    $n = $this->copyRelays($srcId, $dstId, $items['relays'], $distIdMap, $laneIdMap);
                    $report['copied']['relays'] = $n;
                }
            }

            Event::rowsRaw('COMMIT', []);
        } catch (\Throwable $e) {
            try { Event::rowsRaw('ROLLBACK', []); } catch (\Throwable $_e) {}
            $report['error'] = $e->getMessage();
            $report['copied'] = []; // rolled back
        }

        unset($_SESSION['event_migrate']);
        $this->renderWith('app', 'admin/migrate/result', [
            'report'     => $report,
            'set_labels' => self::SET_LABELS,
            'flash'      => $this->flash(),
        ]);
    }

    // ── Fetchers (Step 2 lists) ────────────────────────────────────────

    private function fetchSports(int $eid): array
    {
        return Event::rowsRaw(
            "SELECT es.id, es.event_code, es.entry_fee, es.team_entry_fee,
                    s.name  AS sport_name,
                    sev.name AS sport_event_name, sev.gender,
                    sc.name AS category_name
               FROM event_sports es
               JOIN sports        s   ON s.id  = es.sport_id
          LEFT JOIN sport_events     sev ON sev.id = es.sport_event_id
          LEFT JOIN sport_categories sc  ON sc.id  = sev.category_id
              WHERE es.event_id = ?
              ORDER BY sc.name, sev.name, es.event_code",
            [$eid]
        );
    }

    private function fetchUnits(int $eid): array
    {
        return Event::rowsRaw(
            "SELECT id, name, address FROM event_units
              WHERE event_id = ? ORDER BY name",
            [$eid]
        );
    }

    private function fetchItems(int $eid): array
    {
        return Event::rowsRaw(
            "SELECT esi.id, si.name AS item_name, s.name AS sport_name
               FROM event_sport_items esi
               JOIN sport_items si ON si.id = esi.sport_item_id
          LEFT JOIN sports     s  ON s.id  = si.sport_id
              WHERE esi.event_id = ?
              ORDER BY s.name, si.name",
            [$eid]
        );
    }

    private function fetchRanges(int $eid): array
    {
        $ranges = Event::rowsRaw(
            "SELECT id, name, location FROM event_shooting_ranges
              WHERE event_id = ? ORDER BY name",
            [$eid]
        );
        foreach ($ranges as &$r) {
            $r['distances'] = Event::rowsRaw(
                "SELECT id, name, distance_meters
                   FROM event_shooting_range_distances
                  WHERE shooting_range_id = ? ORDER BY name",
                [(int)$r['id']]
            );
            foreach ($r['distances'] as &$d) {
                $d['lanes'] = Event::rowsRaw(
                    "SELECT id, lane_number, lane_type, default_category
                       FROM event_shooting_range_lanes
                      WHERE distance_id = ? ORDER BY lane_number",
                    [(int)$d['id']]
                );
            }
            unset($d);
        }
        unset($r);
        return $ranges;
    }

    private function fetchRelays(int $eid): array
    {
        return Event::rowsRaw(
            "SELECT er.id, er.relay_number, er.order_no, er.relay_date,
                    er.match_time, er.reporting_time, er.result_status,
                    erd.name AS distance_name, esr.name AS range_name
               FROM event_relays er
          LEFT JOIN event_shooting_range_distances erd ON erd.id = er.shooting_range_distance_id
          LEFT JOIN event_shooting_ranges          esr ON esr.id = erd.shooting_range_id
              WHERE er.event_id = ?
              ORDER BY COALESCE(er.order_no, 999999), er.id",
            [$eid]
        );
    }

    // ── Validation helpers ─────────────────────────────────────────────

    private function destBlank(int $dstId, string $table): bool
    {
        $c = (int)(Event::rowsRaw(
            "SELECT COUNT(*) AS c FROM {$table} WHERE event_id = ?", [$dstId]
        )[0]['c'] ?? 0);
        return $c === 0;
    }

    // ── Copiers ────────────────────────────────────────────────────────

    /** event_sports rows. ID maps not needed (FKs out are master tables). */
    private function copySports(int $srcId, int $dstId, array $ids): int
    {
        if (!$ids) return 0;
        $in = implode(',', array_map('intval', $ids));
        $rows = Event::rowsRaw(
            "SELECT * FROM event_sports
              WHERE event_id = ? AND id IN ({$in})", [$srcId]
        );
        $n = 0;
        foreach ($rows as $r) {
            unset($r['id']);
            $r['event_id'] = $dstId;
            Event::insertRow('event_sports', $r);
            $n++;
        }
        return $n;
    }

    private function copyUnits(int $srcId, int $dstId, array $ids): int
    {
        if (!$ids) return 0;
        $in = implode(',', array_map('intval', $ids));
        $rows = Event::rowsRaw(
            "SELECT * FROM event_units
              WHERE event_id = ? AND id IN ({$in})", [$srcId]
        );
        $n = 0;
        foreach ($rows as $r) {
            unset($r['id']);
            $r['event_id'] = $dstId;
            Event::insertRow('event_units', $r);
            $n++;
        }
        return $n;
    }

    private function copyItems(int $srcId, int $dstId, array $ids): int
    {
        if (!$ids) return 0;
        $in = implode(',', array_map('intval', $ids));
        $rows = Event::rowsRaw(
            "SELECT * FROM event_sport_items
              WHERE event_id = ? AND id IN ({$in})", [$srcId]
        );
        $n = 0;
        foreach ($rows as $r) {
            unset($r['id']);
            $r['event_id'] = $dstId;
            Event::insertRow('event_sport_items', $r);
            $n++;
        }
        return $n;
    }

    /**
     * Copy the three-level range tree. Returns [count_of_ranges, distMap, laneMap]
     * where the maps go from source ids → destination ids (used to remap
     * relay.shooting_range_distance_id and event_relay_lanes.lane_id).
     * Ranges not in $rangeIds are skipped entirely.
     */
    private function copyRanges(int $srcId, int $dstId, array $rangeIds): array
    {
        $distMap = []; $laneMap = []; $n = 0;
        if (!$rangeIds) return [0, $distMap, $laneMap];
        $in = implode(',', array_map('intval', $rangeIds));
        $ranges = Event::rowsRaw(
            "SELECT * FROM event_shooting_ranges
              WHERE event_id = ? AND id IN ({$in})", [$srcId]
        );
        foreach ($ranges as $r) {
            $srcRangeId = (int)$r['id'];
            unset($r['id']);
            $r['event_id'] = $dstId;
            $dstRangeId = (int)Event::insertRow('event_shooting_ranges', $r);
            $n++;

            // Distances under this range
            $dists = Event::rowsRaw(
                "SELECT * FROM event_shooting_range_distances
                  WHERE shooting_range_id = ?", [$srcRangeId]
            );
            foreach ($dists as $d) {
                $srcDistId = (int)$d['id'];
                unset($d['id']);
                $d['shooting_range_id'] = $dstRangeId;
                $dstDistId = (int)Event::insertRow('event_shooting_range_distances', $d);
                $distMap[$srcDistId] = $dstDistId;

                // Lanes under this distance
                $lanes = Event::rowsRaw(
                    "SELECT * FROM event_shooting_range_lanes
                      WHERE distance_id = ?", [$srcDistId]
                );
                foreach ($lanes as $l) {
                    $srcLaneId = (int)$l['id'];
                    unset($l['id']);
                    $l['distance_id'] = $dstDistId;
                    $dstLaneId = (int)Event::insertRow('event_shooting_range_lanes', $l);
                    $laneMap[$srcLaneId] = $dstLaneId;
                }
            }
        }
        return [$n, $distMap, $laneMap];
    }

    /**
     * Copy event_relays + event_relay_lanes. The relay's
     * shooting_range_distance_id is remapped via $distIdMap; each
     * event_relay_lanes row's lane_id via $laneIdMap. Assignment
     * columns (assigned_unit_id, assigned_registration_id,
     * allocated_by, allocated_at) are cleared — they belong to the
     * source event's allocation, not the new event.
     */
    private function copyRelays(int $srcId, int $dstId, array $relayIds,
        array $distIdMap, array $laneIdMap): int
    {
        if (!$relayIds) return 0;
        $in = implode(',', array_map('intval', $relayIds));
        $relays = Event::rowsRaw(
            "SELECT * FROM event_relays
              WHERE event_id = ? AND id IN ({$in})", [$srcId]
        );
        $n = 0;
        foreach ($relays as $r) {
            $srcRelayId = (int)$r['id'];
            $srcDistId  = (int)($r['shooting_range_distance_id'] ?? 0);
            if (!isset($distIdMap[$srcDistId])) {
                // Relay points at a distance we didn't copy — skip rather
                // than create a FK violation.
                continue;
            }
            unset($r['id']);
            $r['event_id']                   = $dstId;
            $r['shooting_range_distance_id'] = $distIdMap[$srcDistId];
            $r['result_status']              = 'pending';
            $dstRelayId = (int)Event::insertRow('event_relays', $r);

            // Junction rows
            $lanes = Event::rowsRaw(
                "SELECT * FROM event_relay_lanes WHERE relay_id = ?", [$srcRelayId]
            );
            foreach ($lanes as $jl) {
                $srcLaneId = (int)$jl['lane_id'];
                if (!isset($laneIdMap[$srcLaneId])) continue;
                Event::insertRow('event_relay_lanes', [
                    'relay_id'                 => $dstRelayId,
                    'lane_id'                  => $laneIdMap[$srcLaneId],
                    'category'                 => $jl['category'] ?? null,
                    'assigned_unit_id'         => null,
                    'assigned_registration_id' => null,
                    'allocated_by'             => null,
                    'allocated_at'             => null,
                ]);
            }
            $n++;
        }
        return $n;
    }
}
