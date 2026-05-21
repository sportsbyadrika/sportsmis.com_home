<?php
namespace Controllers;

use Core\{Controller, Auth};
use Models\{Schema, Event, EventStaff, Relay, ScoreEntry, RelayStatusLog, ShootingRange};
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

        $this->renderWith('staff', 'scoring/entry', [
            'staff'        => $this->staff,
            'event'        => $this->event,
            'relay'        => $relay,
            'lane'         => $lane,
            'entry'        => $entry,
            'series'       => $series,
            'prefill'      => $prefill,
            'config'       => $cfg,
            'all_configs'  => $allConfigs,
            'locked'       => $locked,
            'view_only'    => $locked || isset($_GET['view']),
            'statuses'     => ScoringService::statuses(),
            'flash'        => $this->flash(),
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
        $this->loadRelayLane((int)$relay['id'], $laneId);

        $compNo = (int)($_POST['competitor_number'] ?? 0);
        $reg = $compNo ? ScoreEntry::lookupCompetitor((int)$this->event['id'], $compNo) : null;

        $catId = (int)($_POST['sport_category_id'] ?? 0) ?: null;
        $cfg   = $catId ? ScoreEntry::resolveCategoryConfig((int)$this->event['id'], $catId) : null;

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
        if (!in_array($scoreType, ['integer','decimal_1','decimal_2','any'], true)) $scoreType = 'integer';

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
        $id = ScoreEntry::save($header, $series, (string)$this->staff['name']);

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
