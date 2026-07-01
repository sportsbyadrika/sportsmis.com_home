<?php
namespace Controllers;

use Core\{Controller, Auth};
use Models\{Schema, Event, EventStaff, Relay, ScoreEntry, RelayStatusLog, ShootingRange, LaneAllocation};
use Services\ScoringService;

/**
 * Scoring portal for Event Staff with the 'scoring' privilege.
 *
 * Routes (all under /event-staff/scoring/...):
 *   GET  /             relay list (landing)
 *   GET  /relays/{id}  lane list for a relay
 *   GET  /relays/{id}/lanes/{laneId}      score entry page (edit / view)
 *   GET  /relays/{id}/lanes/{laneId}/sheet  per-lane print sheet
 *   GET  /relays/{id}/print                 relay print report (pivot)
 *   GET  /lookup-competitor                AJAX competitor lookup
 *   POST /save                             AJAX save scores
 *   POST /relay-status                     AJAX change relay result status
 *
 * Future Rank-List / Team-Result / Certificate / Medal-Tally / Unit-Analysis
 * modules consume Services\ScoringService directly — they don't poke
 * score_entries / score_series themselves.
 */
class ScoringController extends Controller
{
    private array $staff;
    private array $event;

    private function boot(): void
    {
        try { Schema::ensureScoring(); } catch (\Throwable $e) {}
        if (!Auth::eventStaffCheck()) {
            $this->redirect('/event-staff/login', 'Please sign in to continue.', 'warning');
        }
        $session = Auth::eventStaff();
        $s = EventStaff::findById((int)$session['id']);
        if (!$s || $s['status'] !== 'active') {
            Auth::eventStaffLogout();
            $this->redirect('/event-staff/login', 'Your staff account is not active.', 'error');
        }
        if (!in_array('scoring', EventStaff::privilegesFor((int)$s['id']), true)) {
            $this->abort(403);
        }
        $event = Event::findById((int)$s['event_id']);
        if (!$event) $this->abort(404);
        $event['event_code'] = $event['event_code'] ?? \ensureEventCode((int)$event['id']);
        $s['privileges'] = EventStaff::privilegesFor((int)$s['id']);
        $this->staff = $s;
        $this->event = $event;
    }

    // ── Landing: relay list ──────────────────────────────────────────────────

    public function relays(): void
    {
        $this->boot();
        $this->renderWith('staff', 'scoring/relays', [
            'staff'    => $this->staff,
            'event'    => $this->event,
            'relays'   => ScoringService::relayList((int)$this->event['id']),
            'statuses' => ScoringService::statuses(),
            'flash'    => $this->flash(),
        ]);
    }

    // ── Entered results (participant-wise, already-entered scores) ────────────

    /**
     * GET /event-staff/scoring/results — a participant-wise list of every
     * result already entered on the event, optionally filtered to one event
     * category. Useful to review what's been captured without opening each
     * relay/lane.
     */
    public function enteredResults(): void
    {
        $this->boot();
        $catId = (int)($_GET['category_id'] ?? 0);
        $this->renderWith('staff', 'scoring/entered-results', [
            'staff'             => $this->staff,
            'event'             => $this->event,
            'categories'        => ScoreEntry::categoriesWithResults((int)$this->event['id']),
            'selected_category' => $catId,
            'results'           => ScoreEntry::enteredResultsForEvent((int)$this->event['id'], $catId ?: null),
            // Note: do NOT consume the flash here — the staff layout renders
            // flashBag() itself, so the bulk-update redirect message survives.
        ]);
    }

    /**
     * POST /event-staff/scoring/results/update — bulk-extrapolate the series
     * of the selected score entries with the chosen mode (30→60 / 20→30 /
     * 20→40). Entries on a locked (Final) relay are skipped.
     */
    public function bulkUpdateResults(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $mode = (string)($_POST['mode'] ?? '');
        $back = '/event-staff/scoring/results';
        $catId = (int)($_POST['category_id'] ?? 0);
        if ($catId > 0) $back .= '?category_id=' . $catId;

        if (!in_array($mode, ['30to60', '20to30', '20to40', '40to60'], true)) {
            $this->redirect($back, 'Pick a valid "Update with…" option.', 'warning');
        }
        $ids = $_POST['entry_ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            $this->redirect($back, 'Select at least one result row to update.', 'warning');
        }

        $eventId = (int)$this->event['id'];
        $relayLock = [];   // relay_id => bool locked (cache)
        $updated = 0; $skipped = 0;
        foreach ($ids as $eid) {
            $entry = ScoreEntry::findById($eid);
            if (!$entry || (int)$entry['event_id'] !== $eventId) { $skipped++; continue; }
            $rid = (int)$entry['relay_id'];
            if (!array_key_exists($rid, $relayLock)) {
                $relay = $rid ? Relay::find($rid) : null;
                $relayLock[$rid] = $relay ? ScoringService::isLocked((string)($relay['result_status'] ?? '')) : false;
            }
            if ($relayLock[$rid]) { $skipped++; continue; }   // Final relay — locked
            $res = ScoreEntry::applyExtrapolation($eid, $mode, (string)($this->staff['name'] ?? 'staff'));
            if ($res === 'ok') $updated++; else $skipped++;
        }
        $label = ['30to60' => '30 → 60', '20to30' => '20 → 30', '20to40' => '20 → 40', '40to60' => '40 → 60'][$mode];
        $msg = sprintf('Updated %d result%s with "%s"%s.',
            $updated, $updated === 1 ? '' : 's', $label,
            $skipped > 0 ? ", {$skipped} skipped (locked or too few series)" : '');
        $this->redirect($back, $msg, $updated > 0 ? 'success' : 'warning');
    }

    // ── Lane list for a relay ────────────────────────────────────────────────

    public function lanes(string $relayId): void
    {
        $this->boot();
        $relay = $this->loadRelay((int)$relayId);
        $lanes = ScoreEntry::lanesForRelay((int)$relay['id']);
        $this->renderWith('staff', 'scoring/lanes', [
            'staff'    => $this->staff,
            'event'    => $this->event,
            'relay'    => $relay,
            'lanes'    => $lanes,
            'statuses' => ScoringService::statuses(),
            'flash'    => $this->flash(),
        ]);
    }

    // ── Score entry page (edit / view) ───────────────────────────────────────

    public function entry(string $relayId, string $laneId): void
    {
        $this->boot();
        $relay = $this->loadRelay((int)$relayId);
        $lane  = $this->loadRelayLane((int)$relay['id'], (int)$laneId);
        $entry = ScoreEntry::findByRelayLane((int)$relay['id'], (int)$laneId);
        $series = $entry ? ScoreEntry::series((int)$entry['id']) : [];

        // If lane allocation already attached an athlete, pre-resolve their details.
        $prefill = null;
        $allottedCompNo = $lane['competitor_number'] ?? null;
        if ($entry && !empty($entry['competitor_number'])) {
            $prefill = ScoreEntry::lookupCompetitor((int)$this->event['id'], (int)$entry['competitor_number']);
        } elseif ($allottedCompNo) {
            $prefill = ScoreEntry::lookupCompetitor((int)$this->event['id'], (int)$allottedCompNo);
        }

        // Effective category config (master default + per-event override).
        $cfg = null;
        $catId = $entry['sport_category_id']
            ?? ($prefill['categories'][0]['id'] ?? null);
        if ($catId) {
            $cfg = ScoreEntry::resolveCategoryConfig((int)$this->event['id'], (int)$catId);
        }

        // All effective category configs for the available categories so the
        // dropdown can switch the grid client-side without a round-trip.
        $allConfigs = [];
        $availableCats = $prefill['categories'] ?? [];
        foreach ($availableCats as $c) {
            $allConfigs[$c['id']] = ScoreEntry::resolveCategoryConfig((int)$this->event['id'], (int)$c['id']);
        }

        $locked = ScoringService::isLocked((string)$relay['result_status']);

        // Pre-compute the "next lane" URL so the entry view's "Next without
        // Save" button can jump ahead without a server round-trip. Mirrors
        // the logic the save handler uses to pick the next lane in order.
        $nextLaneUrl = null;
        $lanesList = ScoreEntry::lanesForRelay((int)$relay['id']);
        $found = false;
        foreach ($lanesList as $l) {
            if ($found) {
                $nextLaneUrl = "/event-staff/scoring/relays/{$relay['id']}/lanes/{$l['lane_id']}";
                break;
            }
            if ((int)$l['lane_id'] === (int)$laneId) $found = true;
        }

        $this->renderWith('staff', 'scoring/entry', [
            'staff'         => $this->staff,
            'event'         => $this->event,
            'relay'         => $relay,
            'lane'          => $lane,
            'entry'         => $entry,
            'series'        => $series,
            'prefill'       => $prefill,
            'config'        => $cfg,
            'all_configs'   => $allConfigs,
            'locked'        => $locked,
            'view_only'     => $locked || isset($_GET['view']),
            'statuses'      => ScoringService::statuses(),
            'next_lane_url' => $nextLaneUrl,
            'flash'         => $this->flash(),
        ]);
    }

    // ── AJAX: competitor lookup ──────────────────────────────────────────────

    public function lookupCompetitor(): void
    {
        $this->boot();
        $compNo = (int)($_GET['competitor_number'] ?? 0);
        if ($compNo <= 0) $this->json(['success' => false, 'message' => 'Enter a competitor number.']);
        $row = ScoreEntry::lookupCompetitor((int)$this->event['id'], $compNo);
        if (!$row) $this->json(['success' => false, 'message' => 'No approved athlete with that competitor number.']);
        // Resolve effective config per category for the client.
        $configs = [];
        foreach ($row['categories'] as $c) {
            $configs[$c['id']] = ScoreEntry::resolveCategoryConfig((int)$this->event['id'], (int)$c['id']);
        }
        $row['configs'] = $configs;
        $row['age_categories'] = \Models\Athlete::baseAgeCategories($row['date_of_birth'] ?? null);

        // Existing-score lookup is scoped to the lane's category so an
        // athlete registered for multiple categories (e.g. Air Pistol
        // and Air Rifle) keeps an independent score row per category.
        // Without this scope, saving the second category would move the
        // first category's score onto the new lane.
        $scopeCatId = (int)($_GET['sport_category_id'] ?? 0);
        if ($scopeCatId === 0) {
            $relayId = (int)($_GET['relay_id'] ?? 0);
            $laneId  = (int)($_GET['lane_id']  ?? 0);
            if ($relayId > 0 && $laneId > 0) {
                $laneRow = \Models\Event::rowsRaw(
                    "SELECT sc.id
                       FROM event_relay_lanes erl
                  LEFT JOIN sport_categories sc ON sc.name = erl.category
                      WHERE erl.relay_id = ? AND erl.lane_id = ?",
                    [$relayId, $laneId]
                )[0] ?? null;
                $scopeCatId = (int)($laneRow['id'] ?? 0);
            }
        }
        $existing = ScoreEntry::findByCompetitor(
            (int)$this->event['id'], $compNo, $scopeCatId ?: null
        );
        if ($existing) {
            $row['existing_score'] = [
                'id'                 => (int)$existing['id'],
                'relay_id'           => (int)$existing['relay_id'],
                'lane_id'            => (int)$existing['lane_id'],
                'src_relay_number'   => $existing['src_relay_number'] ?? null,
                'src_lane_number'    => $existing['src_lane_number']  ?? null,
                'sport_category_id'  => $existing['sport_category_id'] ? (int)$existing['sport_category_id'] : null,
                'target_from'        => $existing['target_from'] ? (int)$existing['target_from'] : null,
                'target_to'          => $existing['target_to']   ? (int)$existing['target_to']   : null,
                'series_count'       => (int)($existing['series_count']     ?? 6),
                'shots_per_series'   => (int)($existing['shots_per_series'] ?? 10),
                'score_type'         => (string)($existing['score_type']    ?? 'integer'),
                'remarks'            => (string)($existing['remarks']       ?? ''),
                'notes'              => (string)($existing['notes']         ?? ''),
                'series'             => array_map(fn($s) => [
                    'series_no'    => (int)$s['series_no'],
                    'shots'        => json_decode($s['shots_json'] ?? '[]', true) ?: [],
                    'inner_tens'   => (int)($s['inner_tens'] ?? 0),
                    'penalty'      => (float)($s['penalty'] ?? 0),
                    'sub_total'    => (float)($s['sub_total'] ?? 0),
                    'series_total' => (float)($s['series_total'] ?? 0),
                ], ScoreEntry::series((int)$existing['id'])),
            ];
        }
        $this->json(['success' => true, 'data' => $row]);
    }

    // ── Save (AJAX) ──────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $relayId = (int)($_POST['relay_id'] ?? 0);
        $laneId  = (int)($_POST['lane_id']  ?? 0);
        $relay   = $this->loadRelay($relayId);
        if (ScoringService::isLocked((string)$relay['result_status'])) {
            $this->json(['success' => false, 'message' => 'Relay is Final — scores are locked.']);
        }
        $lane = $this->loadRelayLane((int)$relay['id'], $laneId);

        $compNo = (int)($_POST['competitor_number'] ?? 0);
        $reg = $compNo ? ScoreEntry::lookupCompetitor((int)$this->event['id'], $compNo) : null;

        $catId = (int)($_POST['sport_category_id'] ?? 0) ?: null;
        $cfg   = $catId ? ScoreEntry::resolveCategoryConfig((int)$this->event['id'], $catId) : null;

        // ── Athlete swap detection ─────────────────────────────────────────
        // If the competitor + category being entered differ from what Lane
        // Allocation currently has on this lane, we treat it as a "swap":
        // the physical sheet says athlete X actually shot in this lane, even
        // though athlete Y was allotted. We re-allocate event_relay_lanes
        // BEFORE saving the score so the lane state, the score row, and the
        // override_history audit all agree.
        $isSwap    = false;
        $swapAudit = null;
        if ($reg && $catId) {
            $newRegId  = (int)$reg['registration_id'];
            $newUnitId = (int)($reg['unit_id'] ?? 0);
            $catName   = (string)($cfg['category_name'] ?? '');
            $laneRegId = (int)($lane['assigned_registration_id'] ?? 0);
            $laneCat   = (string)($lane['category'] ?? '');

            if ($laneRegId !== $newRegId || $laneCat !== $catName) {
                // Block if the new athlete already has a score for this
                // category on a different lane — we don't silently move
                // scores between lanes.
                $other = ScoreEntry::findByCompetitor(
                    (int)$this->event['id'], $compNo, $catId
                );
                if ($other
                    && ((int)$other['relay_id'] !== (int)$relay['id']
                     || (int)$other['lane_id']  !== $laneId)) {
                    $srcR = $other['src_relay_number'] ?? ('#' . (int)$other['relay_id']);
                    $srcL = $other['src_lane_number']  ?? ('#' . (int)$other['lane_id']);
                    $this->json(['success' => false,
                        'message' => 'Cannot save: competitor #' . $compNo
                            . ' already has a score recorded on Relay '
                            . $srcR . ', Lane ' . $srcL
                            . ' for this category. Delete that entry first.']);
                }
                $isSwap = true;
                $swapAudit = [
                    'from' => [
                        'reg'      => $laneRegId ?: null,
                        'unit'     => $lane['assigned_unit_id'] !== null ? (int)$lane['assigned_unit_id'] : null,
                        'category' => $laneCat ?: null,
                    ],
                    'to' => [
                        'reg'      => $newRegId,
                        'unit'     => $newUnitId ?: null,
                        'category' => $catName ?: null,
                    ],
                ];
                try {
                    LaneAllocation::performSwap(
                        (int)$this->event['id'], (int)$relay['id'], $laneId,
                        $newRegId, $newUnitId, $catName,
                        (string)$this->staff['name']
                    );
                } catch (\Throwable $e) {
                    $this->json(['success' => false,
                        'message' => 'Could not re-allocate the lane: ' . $e->getMessage()]);
                }
            }
        }

        // Build series payload from posted shots/penalty arrays.
        $seriesCount  = max(1, (int)($_POST['series_count']     ?? $cfg['series_count']     ?? 6));
        $shotsCount   = max(1, (int)($_POST['shots_per_series'] ?? $cfg['shots_per_series'] ?? 10));
        $shotsArr     = $_POST['shots']      ?? [];   // shots[seriesNo][shotNo] = value
        $penaltyArr   = $_POST['penalty']    ?? [];   // penalty[seriesNo]
        $innerTensArr = $_POST['inner_tens'] ?? [];   // inner_tens[seriesNo]
        $raw = [];
        for ($s = 1; $s <= $seriesCount; $s++) {
            $row = ['series_no' => $s, 'shots' => [], 'penalty' => (float)($penaltyArr[$s] ?? 0),
                    'inner_tens' => (int)($innerTensArr[$s] ?? 0)];
            for ($k = 1; $k <= $shotsCount; $k++) {
                $row['shots'][] = $shotsArr[$s][$k] ?? null;
            }
            $raw[] = $row;
        }
        $series = ScoringService::computeSeries($raw);

        $remarks = (string)($_POST['remarks'] ?? '');
        if (!in_array($remarks, ScoreEntry::REMARKS, true)) $remarks = '';
        $scoreType = (string)($_POST['score_type'] ?? ($cfg['score_type'] ?? 'integer'));
        if (!in_array($scoreType, ['integer','decimal_1','decimal_2','any','series_sum'], true)) $scoreType = 'integer';

        $header = [
            'event_id'          => (int)$this->event['id'],
            'relay_id'          => (int)$relay['id'],
            'lane_id'           => $laneId,
            'sport_category_id' => $catId,
            'athlete_id'        => $reg['athlete_id']      ?? null,
            'registration_id'   => $reg['registration_id'] ?? null,
            'competitor_number' => $compNo ?: null,
            'unit_id'           => $reg['unit_id']          ?? null,
            'target_from'       => (int)($_POST['target_from'] ?? 0) ?: null,
            'target_to'         => (int)($_POST['target_to']   ?? 0) ?: null,
            'series_count'      => $seriesCount,
            'shots_per_series'  => $shotsCount,
            'score_type'        => $scoreType,
            'remarks'           => $remarks ?: null,
            'notes'             => trim((string)($_POST['notes'] ?? '')) ?: null,
            'lane_status'       => 'saved',
        ];

        if ($isSwap) {
            // Append the swap to override_history so the trail survives
            // later edits / re-saves of this lane.
            $hist  = [];
            $prior = ScoreEntry::findByRelayLane((int)$relay['id'], $laneId);
            if ($prior && !empty($prior['override_history'])) {
                $decoded = json_decode((string)$prior['override_history'], true);
                if (is_array($decoded)) $hist = $decoded;
            }
            $hist[] = [
                'ts'     => date('c'),
                'actor'  => (string)$this->staff['name'],
                'action' => 'lane_swap',
            ] + $swapAudit;
            $header['override_history'] = json_encode(
                $hist, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        try {
            $id = ScoreEntry::save($header, $series, (string)$this->staff['name']);
        } catch (\RuntimeException $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()]);
        }

        // Where to go next?
        $next = (string)($_POST['next'] ?? '');
        $redirect = "/event-staff/scoring/relays/{$relay['id']}/lanes/{$laneId}";
        if ($next === 'lanes') {
            $redirect = "/event-staff/scoring/relays/{$relay['id']}";
        } elseif ($next === 'next_lane') {
            $lanes = ScoreEntry::lanesForRelay((int)$relay['id']);
            $found = false;
            foreach ($lanes as $l) {
                if ($found) { $redirect = "/event-staff/scoring/relays/{$relay['id']}/lanes/{$l['lane_id']}"; break; }
                if ((int)$l['lane_id'] === $laneId) $found = true;
            }
        }
        ScoringService::recalculate((int)$this->event['id'], ['relay_id' => $relayId]);
        $this->json(['success' => true, 'message' => 'Scores saved.', 'id' => $id, 'redirect' => $redirect]);
    }

    /**
     * POST /event-staff/scoring/relays/{relayId}/lanes/{laneId}/delete
     * Wipe the score entry (and its series rows) for one lane on a relay,
     * recalculate ranks, and return the operator to the lane list.
     */
    public function deleteLaneEntry(string $relayId, string $laneId): void
    {
        $this->boot();
        $this->verifyCsrf();
        $relay = $this->loadRelay((int)$relayId);
        $lane  = $this->loadRelayLane((int)$relay['id'], (int)$laneId);
        $entry = ScoreEntry::findByRelayLane((int)$relay['id'], (int)$laneId);
        $back  = "/event-staff/scoring/relays/{$relay['id']}";
        if (!$entry) {
            $this->redirect($back, 'No score entry to delete on Lane ' . (int)$lane['lane_number'] . '.', 'warning');
        }
        ScoreEntry::delete((int)$entry['id']);
        try {
            ScoringService::recalculate((int)$this->event['id'], ['relay_id' => $relay['id']]);
        } catch (\Throwable $e) {
            error_log('[scoring/delete/recalc] ' . $e->getMessage());
        }
        $this->redirect($back, 'Score entry on Lane ' . (int)$lane['lane_number'] . ' deleted.');
    }

    // ── Relay status change (AJAX) ───────────────────────────────────────────

    /**
     * GET /event-staff/scoring/import — upload form for the bulk-score
     * CSV. Page also doubles as the results page once a file is posted.
     */
    public function importForm(): void
    {
        $this->boot();
        $this->renderWith('staff', 'scoring/import', [
            'staff'   => $this->staff,
            'event'   => $this->event,
            'results' => null,
            'flash'   => $this->flash(),
        ]);
    }

    /**
     * POST /event-staff/scoring/import — single-pass importer. Every
     * row of the uploaded CSV is validated; rows that pass save into
     * score_entries / score_series, rows that fail are listed with a
     * reason. No partial writes — any row that fails validation is
     * skipped entirely.
     */
    public function importProcess(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $eid = (int)$this->event['id'];

        if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
            $this->redirect('/event-staff/scoring/import', 'Pick a CSV file first.', 'error');
        }
        $fh = fopen($_FILES['csv']['tmp_name'], 'r');
        if (!$fh) {
            $this->redirect('/event-staff/scoring/import', 'Could not read the uploaded file.', 'error');
        }

        // Header row — be tolerant of trailing whitespace and case.
        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            $this->redirect('/event-staff/scoring/import', 'The CSV appears to be empty.', 'error');
        }
        $hMap = [];
        foreach ($header as $i => $h) {
            $hMap[strtoupper(trim((string)$h))] = $i;
        }
        $col = function (string $name) use ($hMap) {
            return $hMap[strtoupper($name)] ?? null;
        };
        $iRelay   = $col('RELAY');
        $iLane    = $col('LANE');
        $iComp    = $col('COMP. NO');
        $iName    = $col('NAME OF ATHLETE');
        $iUnit    = $col('UNIT');
        $iCat     = $col('CATEGORY');
        $iPen     = $col('PENALITY') ?? $col('PENALTY');
        $iSub     = $col('SUB-TOTAL') ?? $col('SUB TOTAL');
        $iTotal   = $col('TOTAL');
        $iRem     = $col('REMARKS');
        if ($iRelay === null || $iLane === null || $iComp === null) {
            fclose($fh);
            $this->redirect('/event-staff/scoring/import',
                'CSV header missing required columns (RELAY, LANE, COMP. NO).', 'error');
        }

        // Pre-load every relay-lane on this event for quick lookup.
        $laneMap = []; // "relay_label|lane_number" => [erl row + category abbr + assigned comp number]
        $relayLanes = Event::rowsRaw(
            "SELECT r.id AS relay_id, r.relay_number,
                    erl.lane_id, l.lane_number,
                    erl.category, sc.abbreviation AS category_abbr,
                    erl.assigned_registration_id,
                    er.competitor_number AS lane_comp_no,
                    a.name AS athlete_name
               FROM event_relays r
               JOIN event_relay_lanes erl              ON erl.relay_id = r.id
               JOIN event_shooting_range_lanes l       ON l.id = erl.lane_id
          LEFT JOIN sport_categories sc                ON sc.name = erl.category
          LEFT JOIN event_registrations er             ON er.id = erl.assigned_registration_id
          LEFT JOIN athletes a                         ON a.id = er.athlete_id
              WHERE r.event_id = ?",
            [$eid]
        );
        foreach ($relayLanes as $r) {
            $key = strtoupper(trim((string)$r['relay_number'])) . '|' . (int)$r['lane_number'];
            $laneMap[$key] = $r;
        }

        // Resolve category config (series count, shots per series, score type).
        $catCache = [];
        $resolveCat = function (string $catName) use ($eid, &$catCache) {
            if (isset($catCache[$catName])) return $catCache[$catName];
            $cat = Event::rowsRaw(
                "SELECT id, name, abbreviation, default_series_count, default_shots_per_series, default_score_type
                   FROM sport_categories WHERE name = ?", [$catName]
            )[0] ?? null;
            if (!$cat) return $catCache[$catName] = null;
            return $catCache[$catName] = ScoreEntry::resolveCategoryConfig($eid, (int)$cat['id']) + ['id' => (int)$cat['id']];
        };

        $results = [
            'success'  => [],
            'failed'   => [],
            'total'    => 0,
        ];

        while (($row = fgetcsv($fh)) !== false) {
            // Skip rows that don't look like data (no relay label).
            $rRelay = strtoupper(trim((string)($row[$iRelay] ?? '')));
            $rLane  = trim((string)($row[$iLane]  ?? ''));
            if ($rRelay === '' || $rLane === '') continue;
            $results['total']++;

            $rComp  = (int)preg_replace('/\D+/', '', (string)($row[$iComp] ?? ''));
            $rUnit  = trim((string)($row[$iUnit] ?? ''));
            $rCatAb = strtoupper(trim((string)($row[$iCat]  ?? '')));
            // Name might be "ABHINANTHU L KRISHNAN\n16 yrs | Male | …"
            $rName  = trim(strtok((string)($row[$iName] ?? ''), "\n"));
            $rPen   = ($iPen !== null) ? trim((string)($row[$iPen] ?? '')) : '';
            $rRem   = ($iRem !== null) ? strtolower(trim((string)($row[$iRem] ?? ''))) : '';

            $label = "Relay {$rRelay}, Lane {$rLane}, Comp #{$rComp}";

            // ── 1. Lane must exist on this event.
            $key = $rRelay . '|' . (int)$rLane;
            $lane = $laneMap[$key] ?? null;
            if (!$lane) {
                $results['failed'][] = ['row' => $label, 'reason' => "Lane not configured on {$rRelay}"];
                continue;
            }

            // ── 2. Lane must already be allotted to an athlete.
            if (!$lane['assigned_registration_id']) {
                $results['failed'][] = ['row' => $label, 'reason' => 'No competitor allocated to this lane'];
                continue;
            }

            // ── 3. Competitor number must match.
            if ((int)$lane['lane_comp_no'] !== $rComp) {
                $results['failed'][] = ['row' => $label,
                    'reason' => 'Competitor mismatch — lane is allotted to #' . str_pad((string)(int)$lane['lane_comp_no'], 4, '0', STR_PAD_LEFT)];
                continue;
            }

            // ── 4. Category abbreviation must match.
            $laneAbb = strtoupper(trim((string)($lane['category_abbr'] ?? '')));
            if ($rCatAb !== '' && $laneAbb !== '' && $laneAbb !== $rCatAb) {
                $results['failed'][] = ['row' => $label,
                    'reason' => "Category mismatch — lane is configured for {$laneAbb}"];
                continue;
            }

            // ── 5. Existing score? Don't overwrite.
            $existing = ScoreEntry::findByRelayLane((int)$lane['relay_id'], (int)$lane['lane_id']);
            if ($existing
                && (in_array($existing['lane_status'] ?? '', ['saved', 'final'], true)
                    || (float)($existing['grand_total'] ?? 0) > 0
                    || $existing['remarks'])) {
                $results['failed'][] = ['row' => $label,
                    'reason' => 'Score data already exists for this lane — not overwritten'];
                continue;
            }

            // ── 6. Resolve config (series count / shots per series).
            $cfg = $resolveCat((string)$lane['category']);
            if (!$cfg) {
                $results['failed'][] = ['row' => $label, 'reason' => "Unknown category configuration"];
                continue;
            }
            $seriesCount = (int)$cfg['series_count'];
            $shotsPer    = (int)$cfg['shots_per_series'];
            $expected    = $seriesCount * $shotsPer;

            // ── 7. Pull SHOT1..SHOT{expected} from the row.
            $shots = [];
            for ($k = 1; $k <= $expected; $k++) {
                $ci = $col('SHOT' . $k);
                if ($ci === null) {
                    $results['failed'][] = ['row' => $label,
                        'reason' => "Missing column SHOT{$k} in CSV"];
                    continue 2;
                }
                $v = $row[$ci] ?? '';
                $v = $v === '' ? null : (float)$v;
                $shots[] = $v;
            }

            // ── 8. Build series payload + run ScoringService for totals.
            $raw = [];
            $shotIdx = 0;
            $pen = ($rPen === '' ? 0.0 : (float)$rPen);
            for ($s = 1; $s <= $seriesCount; $s++) {
                $sShots = array_slice($shots, $shotIdx, $shotsPer);
                $shotIdx += $shotsPer;
                $raw[] = [
                    'series_no'  => $s,
                    'shots'      => $sShots,
                    // Put the entire CSV penalty on series 1; other series 0.
                    'penalty'    => $s === 1 ? $pen : 0,
                    'inner_tens' => 0,
                ];
            }
            $series  = ScoringService::computeSeries($raw);
            $sub     = 0.0;
            foreach ($series as $sg) $sub += (float)$sg['sub_total'];
            $totFromCsv = ($row[$iTotal] ?? '') === '' ? null : (float)$row[$iTotal];
            $subFromCsv = ($iSub !== null && ($row[$iSub] ?? '') !== '') ? (float)$row[$iSub] : null;
            $expectedTotal = round($sub - $pen, 2);

            // Optional sanity check — warn the operator if CSV totals
            // disagree with the computed value.
            if ($subFromCsv !== null && abs($subFromCsv - $sub) > 0.01) {
                $results['failed'][] = ['row' => $label,
                    'reason' => "Sub-Total mismatch (CSV " . $fmt = number_format($subFromCsv, 2) . " vs computed " . number_format($sub, 2) . ")"];
                continue;
            }
            if ($totFromCsv !== null && abs($totFromCsv - $expectedTotal) > 0.01) {
                $results['failed'][] = ['row' => $label,
                    'reason' => "Total mismatch (CSV " . number_format($totFromCsv, 2) . " vs computed " . number_format($expectedTotal, 2) . ")"];
                continue;
            }

            // ── 9. Save.
            $remarks = '';
            if (in_array($rRem, ['dns','dnf','disqualified','other'], true)) $remarks = $rRem;

            // Pull athlete + unit context off the allotted registration.
            // The registration's own id IS the registration_id we need
            // — no separate column.
            $reg = Event::rowsRaw(
                "SELECT id, athlete_id, unit_id
                   FROM event_registrations WHERE id = ?",
                [(int)$lane['assigned_registration_id']]
            )[0] ?? null;

            $header = [
                'event_id'          => $eid,
                'relay_id'          => (int)$lane['relay_id'],
                'lane_id'           => (int)$lane['lane_id'],
                'sport_category_id' => (int)($cfg['id'] ?? 0) ?: null,
                'athlete_id'        => (int)($reg['athlete_id'] ?? 0) ?: null,
                'registration_id'   => (int)($lane['assigned_registration_id'] ?? 0) ?: null,
                'competitor_number' => $rComp,
                'unit_id'           => (int)($reg['unit_id'] ?? 0) ?: null,
                'series_count'      => $seriesCount,
                'shots_per_series'  => $shotsPer,
                'score_type'        => (string)($cfg['score_type'] ?? 'integer'),
                'remarks'           => $remarks ?: null,
                'notes'             => null,
                'lane_status'       => 'saved',
            ];
            try {
                ScoreEntry::save($header, $series, (string)$this->staff['name'] . ' (CSV import)');
                $results['success'][] = [
                    'row' => $label,
                    'name' => $rName,
                    'unit' => $rUnit,
                    'total' => $expectedTotal,
                ];
            } catch (\Throwable $e) {
                $results['failed'][] = ['row' => $label, 'reason' => 'Save error: ' . $e->getMessage()];
            }
        }
        fclose($fh);

        $this->renderWith('staff', 'scoring/import', [
            'staff'   => $this->staff,
            'event'   => $this->event,
            'results' => $results,
            'flash'   => $this->flash(),
        ]);
    }

    // ── Relay status change (AJAX) ───────────────────────────────────────────

    public function relayStatus(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $relayId = (int)($_POST['relay_id'] ?? 0);
        $status  = (string)($_POST['status'] ?? '');
        $relay   = $this->loadRelay($relayId);
        $notes   = trim((string)($_POST['notes'] ?? '')) ?: null;
        $ok = ScoringService::setRelayStatus((int)$relay['id'], $status, (string)$this->staff['name'], $notes);
        if (!$ok) $this->json(['success' => false, 'message' => 'Invalid status.']);
        $this->json(['success' => true, 'message' => 'Relay status updated to ' . ucfirst($status) . '.']);
    }

    // ── Print: per-lane score sheet (mirrors the paper form) ────────────────

    public function laneSheet(string $relayId, string $laneId): void
    {
        $this->boot();
        $relay = $this->loadRelay((int)$relayId);
        $lane  = $this->loadRelayLane((int)$relay['id'], (int)$laneId);
        $entry = ScoreEntry::findByRelayLane((int)$relay['id'], (int)$laneId);
        $series = $entry ? ScoreEntry::series((int)$entry['id']) : [];
        $cfg = null;
        if ($entry && !empty($entry['sport_category_id'])) {
            $cfg = ScoreEntry::resolveCategoryConfig((int)$this->event['id'], (int)$entry['sport_category_id']);
        }
        $this->renderWith('print', 'scoring/sheet-print', [
            'event'  => $this->event,
            'relay'  => $relay,
            'lane'   => $lane,
            'entry'  => $entry,
            'series' => $series,
            'config' => $cfg,
        ]);
    }

    // ── Print: relay-wide report (pivot of series totals) ───────────────────

    public function relayReport(string $relayId): void
    {
        $this->boot();
        $relay = $this->loadRelay((int)$relayId);
        $lanes = ScoreEntry::lanesForRelay((int)$relay['id']);
        // Hydrate per-lane series for the pivot columns.
        $maxSeries = 0;
        foreach ($lanes as &$l) {
            $l['series_rows'] = [];
            if (!empty($l['score_entry_id'])) {
                $sr = ScoreEntry::series((int)$l['score_entry_id']);
                $l['series_rows'] = $sr;
                $maxSeries = max($maxSeries, count($sr));
            }
        }
        unset($l);
        $this->renderWith('print', 'scoring/report-print', [
            'event'      => $this->event,
            'relay'      => $relay,
            'lanes'      => $lanes,
            'max_series' => $maxSeries,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function loadRelay(int $relayId): array
    {
        $r = Relay::find($relayId);
        if (!$r || (int)$r['event_id'] !== (int)$this->event['id']) $this->abort(404);
        // Hydrate range labels for display.
        $tree = Event::rowsRaw(
            "SELECT r.*, d.name AS range_name, d.distance_meters,
                    sr.name AS venue_name, sr.location AS venue_location
               FROM event_relays r
               JOIN event_shooting_range_distances d  ON d.id  = r.shooting_range_distance_id
               JOIN event_shooting_ranges          sr ON sr.id = d.shooting_range_id
              WHERE r.id = ?", [$relayId]
        );
        return $tree[0] ?? $r;
    }

    private function loadRelayLane(int $relayId, int $laneId): array
    {
        $row = Event::rowsRaw(
            "SELECT erl.relay_id, erl.lane_id, erl.category, erl.assigned_unit_id, erl.assigned_registration_id,
                    l.lane_number, l.lane_type, l.default_category,
                    eu.name AS unit_name,
                    a.id    AS athlete_id, a.name AS athlete_name, a.passport_photo,
                    er.competitor_number, er.unit_id AS athlete_unit_id
               FROM event_relay_lanes erl
               JOIN event_shooting_range_lanes l ON l.id = erl.lane_id
          LEFT JOIN event_units eu               ON eu.id = erl.assigned_unit_id
          LEFT JOIN event_registrations er       ON er.id = erl.assigned_registration_id
          LEFT JOIN athletes a                   ON a.id = er.athlete_id
              WHERE erl.relay_id = ? AND erl.lane_id = ? LIMIT 1",
            [$relayId, $laneId]
        );
        if (!$row) $this->abort(404);
        return $row[0];
    }
}
