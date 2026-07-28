<?php
$pageTitle = 'Lane Allocation — ' . ($event['name'] ?? '');
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken   = $_SESSION['csrf_token'];
$isAdmin     = (($actor['mode'] ?? '') === 'admin');
$trackEvents = $track_events ?? [];
$maxRounds   = (int)($max_rounds ?? 0);
$roundNames  = $round_names ?? ['Preliminary heats', 'Semifinal heats', 'Final'];
$totalApproved = 0;
foreach ($trackEvents as $te) { $totalApproved += (int)$te['approved']; }
$draw    = $draw ?? null;
$hasDraw = !empty($draw);
$typeBadge = function (string $t): string {
    if ($t === 'track') return '<span class="badge bg-primary-subtle text-primary-emphasis">Track</span>';
    if ($t === 'field') return '<span class="badge bg-success-subtle text-success-emphasis">Field</span>';
    return '<span class="text-muted">—</span>';
};
?>

<!-- ── Top Bar ───────────────────────────────────────────────── -->
<div class="sms-card p-3 mb-3">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h5 class="mb-0 fw-bold"><i class="bi bi-flag me-2"></i>Lane Allocation
        <span class="badge bg-info-subtle text-info-emphasis ms-1"><?= e($sport ?? 'Athletics') ?></span>
      </h5>
      <div class="text-muted small mt-1">
        <span class="badge bg-primary-subtle text-primary-emphasis"><i class="bi bi-hash"></i> <?= e($event['event_code'] ?? '') ?></span>
        <span class="ms-1"><?= e($event['name'] ?? '') ?></span>
      </div>
    </div>
  </div>
</div>

<?= flashBag() ?>

<!-- ── Tabs ──────────────────────────────────────────────────── -->
<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $hasDraw ? '' : 'active' ?>" data-bs-toggle="tab" data-bs-target="#tab-events" type="button" role="tab">
      <i class="bi bi-list-ol me-1"></i>Events
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $hasDraw ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-heats" type="button" role="tab">
      <i class="bi bi-diagram-3 me-1"></i>Heats &amp; Lane Draw
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button id="results-tab-btn" class="nav-link <?= $hasDraw ? '' : 'disabled' ?>"
            <?= $hasDraw ? 'data-bs-toggle="tab" data-bs-target="#tab-results"' : 'tabindex="-1" aria-disabled="true" title="Select a round first"' ?>
            type="button" role="tab">
      <i class="bi bi-trophy me-1"></i>Results
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade <?= $hasDraw ? '' : 'show active' ?>" id="tab-events" role="tabpanel">
    <div class="sms-card p-3">
      <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-2 flex-wrap">
        <strong><i class="bi bi-list-ol me-1"></i>Events with Approved Athletes</strong>
        <span class="badge bg-secondary-subtle text-secondary-emphasis">
          <?= count($trackEvents) ?> event<?= count($trackEvents) === 1 ? '' : 's' ?> · <?= $totalApproved ?> approved
        </span>
        <a href="/lane-allocation/track/rounds-report" target="_blank" rel="noopener"
           class="btn btn-sm btn-outline-dark ms-auto">
          <i class="bi bi-table me-1"></i>Participants, Rounds &amp; Heats
        </a>
        <?php if ($isAdmin): ?>
          <button type="button" id="updTypeBtn" class="btn btn-sm btn-primary" disabled
                  onclick="openEventTypeModal()">
            <i class="bi bi-tag me-1"></i>Update Event Type <span id="selCount" class="badge bg-light text-dark ms-1">0</span>
          </button>
        <?php endif; ?>
      </div>

      <?php if (empty($trackEvents)): ?>
        <p class="text-muted small text-center py-3 mb-0">No sport events have approved athletes yet.</p>
      <?php else:
        // Distinct sport categories & age categories for the filter dropdowns.
        $catOpts = []; $ageOpts = [];
        foreach ($trackEvents as $te) {
          $c = trim((string)($te['category'] ?? ''));       if ($c !== '') $catOpts[$c] = true;
          $g = trim((string)($te['age_category'] ?? ''));   if ($g !== '') $ageOpts[$g] = true;
        }
        $catOpts = array_keys($catOpts); sort($catOpts, SORT_NATURAL | SORT_FLAG_CASE);
        $ageOpts = array_keys($ageOpts); sort($ageOpts, SORT_NATURAL | SORT_FLAG_CASE);
      ?>
      <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
        <span class="small text-muted"><i class="bi bi-funnel me-1"></i>Filter:</span>
        <select id="teFilterCat" class="form-select form-select-sm" style="width:auto" onchange="teApplyFilter()">
          <option value="">All Sport Categories</option>
          <?php foreach ($catOpts as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?>
        </select>
        <select id="teFilterAge" class="form-select form-select-sm" style="width:auto" onchange="teApplyFilter()">
          <option value="">All Age Categories</option>
          <?php foreach ($ageOpts as $g): ?><option value="<?= e($g) ?>"><?= e($g) ?></option><?php endforeach; ?>
        </select>
        <select id="teFilterType" class="form-select form-select-sm" style="width:auto" onchange="teApplyFilter()">
          <option value="">All Types</option>
          <option value="track">Track</option>
          <option value="field">Field</option>
          <option value="__none">Not set</option>
        </select>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="teClearFilter()">
          <i class="bi bi-x-lg me-1"></i>Clear
        </button>
        <span class="small text-muted ms-auto" id="teFilterCount"></span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" id="teTable">
          <thead class="table-light">
            <tr>
              <?php if ($isAdmin): ?>
                <th style="width:34px"><input type="checkbox" class="form-check-input" id="selAll" onchange="toggleAll(this)"></th>
              <?php endif; ?>
              <th style="width:56px">Sl. No</th>
              <th>Name of Sport Event</th>
              <th class="text-end" style="width:110px">Approved</th>
              <th class="text-center" style="width:80px">Type</th>
              <th class="text-center" style="width:110px">Primary Rounds</th>
              <?php for ($c = 1; $c <= $maxRounds; $c++): ?>
                <th class="text-center" style="width:120px">Round <?= $c ?></th>
              <?php endfor; ?>
              <?php if ($isAdmin): ?><th class="text-center" style="width:70px">Action</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($trackEvents as $i => $te):
              $esid   = (int)$te['event_sport_id'];
              $rounds = $te['rounds'];
            ?>
              <tr class="te-row" id="teRow-<?= $esid ?>" data-cat="<?= e($te['category'] ?? '') ?>" data-age="<?= e($te['age_category'] ?? '') ?>" data-type="<?= e((string)($te['type'] ?? '')) ?>">
                <?php if ($isAdmin): ?>
                  <td><input type="checkbox" class="form-check-input row-check" value="<?= $esid ?>" onchange="updSel()"></td>
                <?php endif; ?>
                <td class="text-center te-sl"><?= $i + 1 ?></td>
                <td>
                  <div class="fw-medium"><?= e($te['sport_event']) ?></div>
                  <?php
                    $subBits = [];
                    if (($te['category'] ?? '') !== '')     $subBits[] = e($te['category']);
                    if (($te['age_category'] ?? '') !== '')  $subBits[] = e($te['age_category']);
                  ?>
                  <?php if ($subBits || $te['event_code'] !== ''): ?>
                    <div class="small text-muted">
                      <?= implode(' · ', $subBits) ?><?php if ($subBits && $te['event_code'] !== ''): ?> · <?php endif; ?>
                      <?php if ($te['event_code'] !== ''): ?><code><?= e($te['event_code']) ?></code><?php endif; ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="text-end fw-bold"><?= (int)$te['approved'] ?></td>
                <td class="text-center">
                  <?= $typeBadge((string)$te['type']) ?>
                  <?php if ($te['type'] === 'track' && (int)$te['num_tracks'] > 0): ?>
                    <div class="small text-muted"><?= (int)$te['num_tracks'] ?> tracks<?php if ((int)($te['num_laps'] ?? 0) > 0): ?> · <?= (int)$te['num_laps'] ?> laps<?php endif; ?></div>
                  <?php endif; ?>
                  <?php
                    $ruMap = ['time' => 'Time', 'height' => 'Metre Height', 'length' => 'Metre Length'];
                    $ru = (string)($te['result_unit'] ?? 'time');
                    if ($te['type'] !== '' && isset($ruMap[$ru])):
                  ?>
                    <div class="small text-muted"><i class="bi bi-rulers"></i> <?= $ruMap[$ru] ?></div>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <?= $te['primary_rounds'] !== null ? '<span class="fw-semibold">' . (int)$te['primary_rounds'] . '</span>' : '<span class="text-muted">—</span>' ?>
                </td>
                <?php for ($c = 0; $c < $maxRounds; $c++):
                  $rd = $rounds[$c] ?? null; ?>
                  <td class="text-center te-rnd">
                    <?php if ($rd): ?>
                      <a href="/lane-allocation?round=<?= (int)$rd['id'] ?>" class="text-decoration-none"
                         title="Open Heats &amp; Lane Draw">
                        <span class="badge bg-primary-subtle text-primary-emphasis border"><?= e($rd['round_name']) ?></span>
                        <div class="small text-muted"><?= (int)$rd['num_heats'] ?> heat<?= (int)$rd['num_heats'] === 1 ? '' : 's' ?></div>
                      </a>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                <?php endfor; ?>
                <?php if ($isAdmin): ?>
                  <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal" data-bs-target="#manageModal-<?= $esid ?>" title="Manage rounds">
                      <i class="bi bi-diagram-3"></i>
                    </button>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <script>
        // Client-side filtering of the Events table by sport / age category
        // and event type. Rows hidden by the filter are also de-selected so that
        // "select all" only ever acts on the rows currently shown.
        function teApplyFilter() {
          var cat = (document.getElementById('teFilterCat') || {}).value || '';
          var age = (document.getElementById('teFilterAge') || {}).value || '';
          var typ = (document.getElementById('teFilterType') || {}).value || '';
          var shown = 0, sl = 0;
          document.querySelectorAll('#teTable tbody tr.te-row').forEach(function (tr) {
            var rowType = tr.dataset.type || '';
            var typeOk = (typ === '') ||
                         (typ === '__none' ? rowType === '' : rowType === typ);
            var ok = (cat === '' || tr.dataset.cat === cat) &&
                     (age === '' || tr.dataset.age === age) && typeOk;
            tr.classList.toggle('d-none', !ok);
            if (ok) { shown++; var c = tr.querySelector('.te-sl'); if (c) c.textContent = ++sl; }
            else { var chk = tr.querySelector('.row-check'); if (chk) chk.checked = false; }
          });
          var total = document.querySelectorAll('#teTable tbody tr.te-row').length;
          var lbl = document.getElementById('teFilterCount');
          if (lbl) lbl.textContent = (cat || age || typ) ? ('Showing ' + shown + ' of ' + total) : '';
          var all = document.getElementById('selAll'); if (all) all.checked = false;
          if (window.updSel) window.updSel();
        }
        function teClearFilter() {
          var a = document.getElementById('teFilterCat'); if (a) a.value = '';
          var b = document.getElementById('teFilterAge'); if (b) b.value = '';
          var d = document.getElementById('teFilterType'); if (d) d.value = '';
          teApplyFilter();
        }
      </script>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tab 2: Heats & Lane Draw -->
  <div class="tab-pane fade <?= $hasDraw ? 'show active' : '' ?>" id="tab-heats" role="tabpanel">
    <?php if (!$hasDraw): ?>
      <div class="sms-card p-4 text-muted small text-center">
        <i class="bi bi-info-circle me-1"></i>Select a round label from the <strong>Events</strong> tab to open its Heats &amp; Lane Draw workspace.
      </div>
    <?php else:
      $rd        = $draw['round'];
      $isField   = !empty($draw['is_field']);
      $numHeats  = $isField ? 1 : (int)$rd['num_heats'];
      $numTracks = (int)$draw['num_tracks'];   // field: number of order positions
      $chest     = fn($n) => $n ? '#' . (string)(int)$n : '—';
      $evName    = trim((string)($rd['sport_event_name'] ?? '')) ?: trim((string)($rd['event_code'] ?? ''));
      $posLabel  = $isField ? 'Order No' : 'Track';
    ?>
      <!-- Heading -->
      <div class="sms-card p-3 mb-3">
        <div class="d-flex flex-wrap align-items-center gap-3">
          <div>
            <div class="fw-bold"><i class="bi bi-flag me-1"></i><?= e($rd['event_name']) ?></div>
            <div class="small text-muted"><?= e($evName) ?><?php if (!empty($rd['category_name'])): ?> · <?= e($rd['category_name']) ?><?php endif; ?></div>
          </div>
          <div class="ms-auto d-flex flex-wrap align-items-center gap-2">
            <span class="badge bg-secondary-subtle text-secondary-emphasis">Total Athletes: <?= (int)$rd['approved'] ?></span>
            <span class="badge bg-primary-subtle text-primary-emphasis"><?= e($rd['round_name']) ?></span>
            <?php if ($isField): ?>
              <span class="badge bg-success-subtle text-success-emphasis">Field event</span>
              <span class="badge bg-warning-subtle text-warning-emphasis"><?= $numTracks ?> order position<?= $numTracks === 1 ? '' : 's' ?></span>
            <?php else: ?>
              <span class="badge bg-info-subtle text-info-emphasis"><?= $numHeats ?> heat<?= $numHeats === 1 ? '' : 's' ?></span>
              <span class="badge bg-warning-subtle text-warning-emphasis"><?= $numTracks ?> track<?= $numTracks === 1 ? '' : 's' ?></span>
              <?php $numLaps = (int)($rd['track_num_laps'] ?? 0); if ($numLaps > 0): ?>
                <span class="badge bg-success-subtle text-success-emphasis">No. of Laps: <?= $numLaps ?></span>
              <?php endif; ?>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
              <form method="POST" action="/lane-allocation/track/auto-allocate" class="m-0 ms-1"
                    onsubmit="return confirm('<?= $isField
                        ? 'Auto-arrange the pending participants into order numbers so the same institution is not in consecutive orders? Existing placements are kept.'
                        : 'Auto-allocate the pending participants into heats and tracks? Institutions are spread across heats. Existing assignments are kept.' ?>');">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="round_id" value="<?= (int)$rd['round_id'] ?>">
                <input type="hidden" name="pool" value="<?= e($draw['pool_type'] ?? '') ?>">
                <button type="submit" class="btn btn-sm btn-primary" <?= $numTracks < 1 ? 'disabled title="No participants to allocate"' : '' ?>>
                  <i class="bi bi-magic me-1"></i><?= $isField ? 'Auto Order Allocation' : 'Auto Lane Allocation' ?>
                </button>
              </form>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-success ms-1"
                    onclick="document.getElementById('results-tab-btn').click()">
              <i class="bi bi-trophy me-1"></i>Results
            </button>
          </div>
        </div>
      </div>

      <?php if ($numTracks < 1): ?>
        <?php if ($isField): ?>
          <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>No participants are available for this round yet — order numbers appear once there are approved (or qualified) athletes.</div>
        <?php else: ?>
          <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>Set the number of tracks for this event (Events tab → Update Event Type) before drawing lanes.</div>
        <?php endif; ?>
      <?php endif; ?>

      <div class="row g-3" id="drawArea"
           data-round="<?= (int)$rd['round_id'] ?>" data-tracks="<?= $numTracks ?>" data-csrf="<?= e($csrfToken) ?>">
        <!-- Left: heats -->
        <div class="col-lg-7">
          <div class="sms-card p-3 h-100">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
              <strong class="me-1"><?= $isField ? 'Order Numbers' : 'Heats' ?></strong>
              <?php if (!$isField): ?>
              <div class="btn-group btn-group-sm flex-wrap" role="group" id="heatBtns">
                <?php for ($h = 1; $h <= $numHeats; $h++):
                  $cnt = count($draw['by_heat'][$h] ?? []); ?>
                  <button type="button" class="btn btn-outline-primary heat-btn <?= $h === 1 ? 'active' : '' ?>"
                          data-heat="<?= $h ?>">
                    Heat <?= $h ?> <span class="badge bg-primary ms-1 heat-count" data-heat="<?= $h ?>"><?= $cnt ?></span>
                  </button>
                <?php endfor; ?>
              </div>
              <?php endif; ?>
              <div class="ms-auto d-flex flex-wrap gap-2">
                <?php if ($isAdmin && !$isField): ?>
                  <form method="POST" action="/lane-allocation/track/heat-add" class="m-0">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="round_id" value="<?= (int)$rd['round_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-success" title="Add one more heat">
                      <i class="bi bi-plus-square me-1"></i>Add Heat
                    </button>
                  </form>
                  <form method="POST" action="/lane-allocation/track/heat-delete" class="m-0"
                        onsubmit="return confirm('Remove the last heat? This is only allowed when no athletes are assigned to it.');">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="round_id" value="<?= (int)$rd['round_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete the last heat if empty">
                      <i class="bi bi-dash-square me-1"></i>Delete Last Heat
                    </button>
                  </form>
                <?php endif; ?>
                <div class="btn-group btn-group-sm" role="group">
                  <a href="/lane-allocation/track/participants-list?round=<?= (int)$rd['round_id'] ?>&orientation=portrait"
                     target="_blank" rel="noopener" class="btn btn-outline-primary">
                    <i class="bi bi-list-ul me-1"></i>Print Participants List
                  </a>
                  <button type="button" class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split"
                          data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="visually-hidden">Orientation</span>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" target="_blank" rel="noopener"
                           href="/lane-allocation/track/participants-list?round=<?= (int)$rd['round_id'] ?>&orientation=portrait">
                      <i class="bi bi-file-earmark me-1"></i>Portrait (default)</a></li>
                    <li><a class="dropdown-item" target="_blank" rel="noopener"
                           href="/lane-allocation/track/participants-list?round=<?= (int)$rd['round_id'] ?>&orientation=landscape">
                      <i class="bi bi-file-earmark-break me-1" style="transform:rotate(90deg);display:inline-block"></i>Landscape</a></li>
                  </ul>
                </div>
                <a href="/lane-allocation/track/heat-compact?round=<?= (int)$rd['round_id'] ?>"
                   target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark"
                   title="Compact heat sheet — 8 heats per page (Chest No &amp; Name)">
                  <i class="bi bi-grid-3x3-gap me-1"></i>Print Heat wise (Compact)
                </a>
                <div class="btn-group btn-group-sm" role="group">
                  <a href="/lane-allocation/track/score-sheet?round=<?= (int)$rd['round_id'] ?>&orientation=portrait"
                     target="_blank" rel="noopener" class="btn btn-outline-dark">
                    <i class="bi bi-printer me-1"></i>Print Heat wise Participants
                  </a>
                  <button type="button" class="btn btn-outline-dark dropdown-toggle dropdown-toggle-split"
                          data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="visually-hidden">Orientation</span>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" target="_blank" rel="noopener"
                           href="/lane-allocation/track/score-sheet?round=<?= (int)$rd['round_id'] ?>&orientation=portrait">
                      <i class="bi bi-file-earmark me-1"></i>Portrait (default)</a></li>
                    <li><a class="dropdown-item" target="_blank" rel="noopener"
                           href="/lane-allocation/track/score-sheet?round=<?= (int)$rd['round_id'] ?>&orientation=landscape">
                      <i class="bi bi-file-earmark-break me-1" style="transform:rotate(90deg);display:inline-block"></i>Landscape</a></li>
                  </ul>
                </div>
              </div>
            </div>

            <?php
              $trackRows = max($numTracks, 1);
              for ($h = 1; $h <= $numHeats; $h++):
                $byTrack = [];
                foreach (($draw['by_heat'][$h] ?? []) as $a) { $byTrack[(int)$a['track_no']] = $a; }
            ?>
              <div class="heat-panel <?= $h === 1 ? '' : 'd-none' ?>" data-heat="<?= $h ?>">
                <div class="table-responsive border rounded">
                  <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th style="width:70px"><?= e($posLabel) ?></th>
                        <th style="width:80px">Chest</th>
                        <th>Athlete</th>
                        <th>Institution</th>
                        <?php if ($isAdmin): ?><th style="width:40px"></th><?php endif; ?>
                      </tr>
                    </thead>
                    <tbody class="heat-body" data-heat="<?= $h ?>">
                      <?php for ($t = 1; $t <= $trackRows; $t++): $a = $byTrack[$t] ?? null; ?>
                        <tr class="track-row" data-heat="<?= $h ?>" data-track="<?= $t ?>"
                            <?php if ($a): ?>data-reg="<?= (int)$a['registration_id'] ?>"
                            data-chest="<?= e($chest($a['competitor_number'])) ?>"
                            data-name="<?= e($a['athlete_name']) ?>"
                            data-unit="<?= e($a['unit_name'] ?? '') ?>"
                            data-dob="<?= e($a['date_of_birth'] ?? '') ?>"<?php endif; ?>>
                          <td class="text-center fw-bold"><?= $t ?></td>
                          <?php if ($a): ?>
                            <td><code><?= e($chest($a['competitor_number'])) ?></code></td>
                            <td><?= e($a['athlete_name']) ?></td>
                            <td class="small text-muted"><?= e($a['unit_name'] ?? '—') ?></td>
                            <?php if ($isAdmin): ?>
                              <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 rm-athlete" title="Remove"><i class="bi bi-x-lg"></i></button>
                              </td>
                            <?php endif; ?>
                          <?php else: ?>
                            <td colspan="3" class="text-muted small track-empty"><em>— drop athlete here —</em></td>
                            <?php if ($isAdmin): ?><td></td><?php endif; ?>
                          <?php endif; ?>
                        </tr>
                      <?php endfor; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            <?php endfor; ?>
          </div>
        </div>

        <!-- Right: athlete pool -->
        <div class="col-lg-5">
          <div class="sms-card p-3 h-100">
            <?php
              $poolType     = $draw['pool_type'] ?? 'approved';
              $poolHasPrev  = !empty($draw['has_prev']);
              $poolPrevName = (string)($draw['prev_round_name'] ?? '');
              $poolRoundId  = (int)$rd['round_id'];
            ?>
            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
              <strong>Participants</strong>
              <select class="form-select form-select-sm" style="width:auto"
                      onchange="location.href='/lane-allocation?round=<?= $poolRoundId ?>&pool=' + this.value">
                <option value="approved" <?= $poolType === 'approved' ? 'selected' : '' ?>>Approved Athletes</option>
                <?php if ($poolHasPrev): ?>
                  <option value="qualified" <?= $poolType === 'qualified' ? 'selected' : '' ?>>Qualified — <?= e($poolPrevName) ?></option>
                <?php endif; ?>
              </select>
              <span class="badge bg-secondary-subtle text-secondary-emphasis ms-auto" id="poolCount"><?= count($draw['available']) ?></span>
            </div>
            <?php if ($draw['pool_note'] !== ''): ?>
              <div class="alert alert-info py-2 small"><i class="bi bi-info-circle me-1"></i><?= e($draw['pool_note']) ?></div>
            <?php endif; ?>
            <?php if ($isAdmin): ?><div class="small text-muted mb-2"><i class="bi bi-hand-index me-1"></i>Drag an athlete onto an empty track — or click an athlete, then click a track.</div><?php endif; ?>
            <div id="poolList" class="d-flex flex-column gap-1" style="max-height:60vh;overflow:auto">
              <?php foreach ($draw['available'] as $a): ?>
                <div class="border rounded px-2 py-1 pool-item d-flex align-items-center gap-2 <?= $isAdmin ? '' : '' ?>"
                     <?= $isAdmin ? 'draggable="true"' : '' ?>
                     data-reg="<?= (int)$a['registration_id'] ?>"
                     data-chest="<?= e($chest($a['competitor_number'])) ?>"
                     data-name="<?= e($a['athlete_name']) ?>"
                     data-unit="<?= e($a['unit_name'] ?? '') ?>"
                     data-dob="<?= e($a['date_of_birth'] ?? '') ?>">
                  <code class="small"><?= e($chest($a['competitor_number'])) ?></code>
                  <span class="small fw-medium text-truncate"><?= e($a['athlete_name']) ?></span>
                  <span class="small text-muted text-truncate ms-auto"><?= e($a['unit_name'] ?? '') ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Tab 3: Results -->
  <div class="tab-pane fade" id="tab-results" role="tabpanel">
    <?php if (!$hasDraw): ?>
      <div class="sms-card p-4 text-muted small text-center">
        <i class="bi bi-info-circle me-1"></i>Select a round label from the <strong>Events</strong> tab, then open <strong>Results</strong> to enter timings.
      </div>
    <?php else:
      $rRd       = $draw['round'];
      $rHeats    = (int)$rRd['num_heats'];
      $rEvName   = trim((string)($rRd['sport_event_name'] ?? '')) ?: trim((string)($rRd['event_code'] ?? ''));
    ?>
      <div class="sms-card p-3">
        <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3 flex-wrap">
          <strong><i class="bi bi-trophy me-1"></i>Enter Results</strong>
          <span class="small text-muted"><?= e($rEvName) ?> · <?= e($rRd['round_name']) ?></span>
          <a href="/lane-allocation/track/results-report?round=<?= (int)$rRd['round_id'] ?>"
             target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark ms-auto">
            <i class="bi bi-printer me-1"></i>Print Results
          </a>
          <span class="badge bg-info-subtle text-info-emphasis"><?= $rHeats ?> heat<?= $rHeats === 1 ? '' : 's' ?></span>
        </div>

        <!-- Heat strip -->
        <div class="btn-group btn-group-sm flex-wrap mb-3" role="group" id="resHeatBtns">
          <?php for ($h = 1; $h <= $rHeats; $h++):
            $cnt = count($draw['by_heat'][$h] ?? []); ?>
            <button type="button" class="btn btn-outline-primary res-heat-btn <?= $h === 1 ? 'active' : '' ?>" data-heat="<?= $h ?>">
              Heat <?= $h ?> <span class="badge bg-primary ms-1"><?= $cnt ?></span>
            </button>
          <?php endfor; ?>
        </div>

        <?php for ($h = 1; $h <= $rHeats; $h++):
          $hAthletes = $draw['by_heat'][$h] ?? [];
          usort($hAthletes, fn($a, $b) => (int)$a['track_no'] <=> (int)$b['track_no']);
        ?>
          <div class="res-heat-panel <?= $h === 1 ? '' : 'd-none' ?>" data-heat="<?= $h ?>">
            <form class="res-form" data-heat="<?= $h ?>">
              <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
              <input type="hidden" name="round_id" value="<?= (int)$rRd['round_id'] ?>">
              <div class="table-responsive border rounded">
                <table class="table table-sm align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th style="width:60px">Track</th>
                      <th style="width:90px">Chest</th>
                      <th>Name of Athlete</th>
                      <th>Institution</th>
                      <th style="width:130px">Time</th>
                      <th style="width:90px">Rank</th>
                      <th style="width:90px" class="text-center">Qualified</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($hAthletes)): ?>
                      <tr><td colspan="7" class="text-muted text-center py-3">No athletes assigned to this heat.</td></tr>
                    <?php else: foreach ($hAthletes as $a): $rid = (int)$a['registration_id']; ?>
                      <tr>
                        <td class="text-center fw-bold"><?= (int)$a['track_no'] ?></td>
                        <td><code><?= e($chest($a['competitor_number'])) ?></code></td>
                        <td><?= e($a['athlete_name']) ?></td>
                        <td class="small text-muted"><?= e($a['unit_name'] ?? '—') ?></td>
                        <?php if ($isAdmin): ?>
                          <td><input type="text" class="form-control form-control-sm" name="time[<?= $rid ?>]"
                                     value="<?= e($a['result_time'] ?? '') ?>" placeholder="mm:ss.SSS"></td>
                          <td><input type="number" min="1" step="1" class="form-control form-control-sm" name="rank[<?= $rid ?>]"
                                     value="<?= (int)($a['result_rank'] ?? 0) > 0 ? (int)$a['result_rank'] : '' ?>"></td>
                          <td class="text-center"><input type="checkbox" class="form-check-input" name="qualified[<?= $rid ?>]"
                                     value="1" <?= !empty($a['is_qualified']) ? 'checked' : '' ?>></td>
                        <?php else: ?>
                          <td class="small"><?= e($a['result_time'] ?? '') ?: '—' ?></td>
                          <td class="small"><?= (int)($a['result_rank'] ?? 0) > 0 ? (int)$a['result_rank'] : '—' ?></td>
                          <td class="text-center"><?= !empty($a['is_qualified']) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '—' ?></td>
                        <?php endif; ?>
                      </tr>
                    <?php endforeach; endif; ?>
                  </tbody>
                </table>
              </div>
              <?php if ($isAdmin && !empty($hAthletes)):
                // Heat is "published" when every row carries the published flag.
                $heatPublished = !array_filter($hAthletes, fn($a) => empty($a['is_published']));
              ?>
                <div class="d-flex align-items-center mt-2">
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="resPub<?= $h ?>" name="published" value="1" <?= $heatPublished ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="resPub<?= $h ?>">Publish results</label>
                  </div>
                  <button type="submit" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-save me-1"></i>Update Heat <?= $h ?></button>
                </div>
              <?php endif; ?>
            </form>
          </div>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($hasDraw && $isAdmin): ?>
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="drawToast" class="toast align-items-center text-bg-dark border-0" role="alert">
    <div class="d-flex"><div class="toast-body" id="drawToastMsg"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>
  </div>
</div>
<script>
// Deferred to DOMContentLoaded so Bootstrap's bundle (loaded at the end of the
// layout body) is available — otherwise new bootstrap.Toast() would throw here
// and abort all the drag/drop wiring below.
document.addEventListener('DOMContentLoaded', function () {
  const area  = document.getElementById('drawArea');
  if (!area) return;
  const round = area.dataset.round;
  const csrf  = area.dataset.csrf;
  const toastEl = document.getElementById('drawToast');
  let toast = null;
  function say(m) {
    if (toastEl && window.bootstrap) {
      if (!toast) toast = new bootstrap.Toast(toastEl, { delay: 2500 });
      document.getElementById('drawToastMsg').textContent = m; toast.show();
    } else { alert(m); }
  }

  async function post(url, data) {
    const fd = new FormData();
    fd.append('_token', csrf);
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    const r = await fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    return r.json();
  }

  // Heat button switching.
  document.querySelectorAll('.heat-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.heat-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const h = btn.dataset.heat;
      document.querySelectorAll('.heat-panel').forEach(p => p.classList.toggle('d-none', p.dataset.heat !== h));
    });
  });

  function setCount(h) {
    const n = document.querySelectorAll('.heat-body[data-heat="' + h + '"] tr[data-reg]').length;
    const badge = document.querySelector('.heat-count[data-heat="' + h + '"]');
    if (badge) badge.textContent = n;
  }
  function poolCount() {
    document.getElementById('poolCount').textContent = document.querySelectorAll('#poolList .pool-item').length;
  }

  // Fill a track row with an athlete (row element stays; only contents change).
  function fillRow(row, d) {
    row.dataset.reg = d.reg; row.dataset.chest = d.chest; row.dataset.name = d.name;
    row.dataset.unit = d.unit || ''; row.dataset.dob = d.dob || '';
    row.innerHTML =
      '<td class="text-center fw-bold">' + row.dataset.track + '</td>' +
      '<td><code>' + d.chest + '</code></td>' +
      '<td>' + d.name + '</td>' +
      '<td class="small text-muted">' + (d.unit || '—') + '</td>' +
      '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 rm-athlete" title="Remove"><i class="bi bi-x-lg"></i></button></td>';
  }
  // Clear a track row back to an empty drop zone.
  function clearRow(row) {
    ['reg', 'chest', 'name', 'unit', 'dob'].forEach(k => delete row.dataset[k]);
    row.innerHTML =
      '<td class="text-center fw-bold">' + row.dataset.track + '</td>' +
      '<td colspan="3" class="text-muted small track-empty"><em>— drop athlete here —</em></td>' +
      '<td></td>';
  }
  function makePoolItem(d) {
    const el = document.createElement('div');
    el.className = 'border rounded px-2 py-1 pool-item d-flex align-items-center gap-2';
    el.setAttribute('draggable', 'true');
    Object.entries(d).forEach(([k, v]) => el.dataset[k] = v);
    el.innerHTML = '<code class="small">' + d.chest + '</code>' +
      '<span class="small fw-medium text-truncate">' + d.name + '</span>' +
      '<span class="small text-muted text-truncate ms-auto">' + (d.unit || '') + '</span>';
    wireDrag(el);
    return el;
  }

  async function assign(row, el) {
    if (row.dataset.reg) { say('That track is occupied.'); return; }
    const res = await post('/lane-allocation/track/heat-assign', {
      round_id: round, registration_id: el.dataset.reg,
      heat_no: row.dataset.heat, track_no: row.dataset.track
    });
    if (!res.success) { say(res.message || 'Could not assign.'); return; }
    fillRow(row, { reg: el.dataset.reg, chest: el.dataset.chest, name: el.dataset.name, unit: el.dataset.unit, dob: el.dataset.dob });
    el.remove();
    setCount(row.dataset.heat); poolCount();
  }

  async function remove(row) {
    const res = await post('/lane-allocation/track/heat-unassign', { round_id: round, registration_id: row.dataset.reg });
    if (!res.success) { say(res.message || 'Could not remove.'); return; }
    const d = { reg: row.dataset.reg, chest: row.dataset.chest, name: row.dataset.name, unit: row.dataset.unit, dob: row.dataset.dob };
    const h = row.dataset.heat;
    clearRow(row);
    document.getElementById('poolList').appendChild(makePoolItem(d));
    setCount(h); poolCount();
  }

  // Remove buttons (delegated).
  document.getElementById('tab-heats').addEventListener('click', e => {
    const btn = e.target.closest('.rm-athlete');
    if (btn) remove(btn.closest('tr'));
  });

  // Drag & drop — pool items are draggable; each empty track row is a drop zone.
  let dragEl = null;
  let pickedEl = null;   // click-to-place selection
  function pick(el) {
    if (pickedEl) pickedEl.classList.remove('border-primary', 'border-2');
    if (pickedEl === el) { pickedEl = null; return; }   // toggle off
    pickedEl = el;
    el.classList.add('border-primary', 'border-2');
  }
  function wireDrag(el) {
    el.addEventListener('dragstart', e => {
      dragEl = el; el.classList.add('opacity-50');
      // Setting drag data is REQUIRED for the drop to fire in most browsers.
      try {
        e.dataTransfer.setData('text/plain', el.dataset.reg || '');
        e.dataTransfer.effectAllowed = 'move';
      } catch (err) { /* ignore */ }
    });
    el.addEventListener('dragend', () => { el.classList.remove('opacity-50'); dragEl = null; });
    // Click-to-place fallback (trackpads / touch).
    el.addEventListener('click', () => pick(el));
  }
  document.querySelectorAll('#poolList .pool-item').forEach(wireDrag);

  document.querySelectorAll('.track-row').forEach(row => {
    row.addEventListener('dragover', e => {
      if (row.dataset.reg) return;                 // occupied — no drop
      e.preventDefault();
      if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
      row.classList.add('table-primary');
    });
    row.addEventListener('dragleave', () => row.classList.remove('table-primary'));
    row.addEventListener('drop', e => {
      e.preventDefault(); row.classList.remove('table-primary');
      if (dragEl && !row.dataset.reg) assign(row, dragEl);
    });
    // Click an empty track after picking an athlete.
    row.addEventListener('click', () => {
      if (row.dataset.reg) return;
      if (pickedEl) { const el = pickedEl; pickedEl = null; el.classList.remove('border-primary', 'border-2'); assign(row, el); }
    });
  });
});
</script>
<?php endif; ?>

<?php if ($hasDraw): ?>
<?php if ($isAdmin): ?>
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="resToast" class="toast align-items-center text-bg-success border-0" role="alert">
    <div class="d-flex"><div class="toast-body" id="resToastMsg"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>
  </div>
</div>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Results tab — heat strip switching.
  document.querySelectorAll('.res-heat-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.res-heat-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const h = btn.dataset.heat;
      document.querySelectorAll('.res-heat-panel').forEach(p => p.classList.toggle('d-none', p.dataset.heat !== h));
    });
  });

  // Results tab — save one heat's results via AJAX.
  const toastEl = document.getElementById('resToast');
  let toast = null;
  function rsay(m, ok) {
    const body = document.getElementById('resToastMsg');
    if (toastEl && window.bootstrap) {
      if (!toast) toast = new bootstrap.Toast(toastEl, { delay: 2500 });
      if (body) body.textContent = m;
      toastEl.classList.toggle('text-bg-success', ok !== false);
      toastEl.classList.toggle('text-bg-danger', ok === false);
      toast.show();
    } else { alert(m); }
  }
  document.querySelectorAll('.res-form').forEach(form => {
    form.addEventListener('submit', async e => {
      e.preventDefault();
      const btn = form.querySelector('button[type="submit"]');
      if (btn) btn.disabled = true;
      try {
        const res = await fetch('/lane-allocation/track/heat-results', {
          method: 'POST', body: new FormData(form),
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        rsay(data.message || (data.success ? 'Saved.' : 'Could not save.'), data.success);
      } catch (err) {
        rsay('Network error while saving.', false);
      } finally {
        if (btn) btn.disabled = false;
      }
    });
  });
});
</script>
<?php endif; ?>

<?php if ($isAdmin): ?>
<!-- ── Update Event Type modal ── -->
<div class="modal fade" id="eventTypeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="/lane-allocation/track/event-type">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <div id="etIds"></div>
        <div class="modal-header">
          <h6 class="modal-title fw-semibold"><i class="bi bi-tag me-2"></i>Update Event Type</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted">Applying to <strong id="etCount">0</strong> selected event(s).</p>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="track_event_type" id="etTrack" value="track"
                   onchange="document.getElementById('etTracksWrap').style.display='block'">
            <label class="form-check-label" for="etTrack">Track <small class="text-muted">(lane races — needs number of tracks)</small></label>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="track_event_type" id="etField" value="field"
                   onchange="document.getElementById('etTracksWrap').style.display='none'">
            <label class="form-check-label" for="etField">Field <small class="text-muted">(single round — order numbers)</small></label>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-medium">Result Unit</label>
            <select name="track_result_unit" class="form-select form-select-sm">
              <option value="time">Time</option>
              <option value="height">Meter Height</option>
              <option value="length">Meter Length</option>
            </select>
            <div class="form-text small">How the result is recorded — Time for races, Metre Height / Length for field events.</div>
          </div>
          <div id="etTracksWrap" style="display:none">
            <div class="row g-2">
              <div class="col-6">
                <label class="form-label small fw-medium">Number of Tracks / Lanes</label>
                <input type="number" name="track_num_tracks" min="1" step="1" class="form-control form-control-sm" value="8">
              </div>
              <div class="col-6">
                <label class="form-label small fw-medium">Number of Laps</label>
                <input type="number" name="track_num_laps" min="0" step="1" class="form-control form-control-sm" value="0"
                       placeholder="0 = n/a">
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Per-event Manage Rounds modals ── -->
<?php foreach ($trackEvents as $te):
  $esid    = (int)$te['event_sport_id'];
  $rounds  = $te['rounds'];
  $prelim  = $te['primary_rounds'];
?>
<div class="modal fade" id="manageModal-<?= $esid ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-semibold"><i class="bi bi-diagram-3 me-2"></i>Manage Rounds — <?= e($te['sport_event']) ?></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2 mb-3">
          <div class="col-4">
            <div class="border rounded p-2 text-center">
              <div class="small text-muted">Total Athletes</div>
              <div class="fs-5 fw-bold"><?= (int)$te['approved'] ?></div>
            </div>
          </div>
          <div class="col-4">
            <div class="border rounded p-2 text-center">
              <div class="small text-muted">Tracks</div>
              <div class="fs-5 fw-bold"><?= $te['type'] === 'track' && (int)$te['num_tracks'] > 0 ? (int)$te['num_tracks'] : '—' ?></div>
            </div>
          </div>
          <div class="col-4">
            <div class="border rounded p-2 text-center">
              <div class="small text-muted">Prelim Heats</div>
              <div class="fs-5 fw-bold"><?= $prelim !== null ? (int)$prelim : '—' ?></div>
            </div>
          </div>
        </div>

        <?php if ($te['type'] === ''): ?>
          <div class="alert alert-warning py-2 small mb-3">
            <i class="bi bi-exclamation-triangle me-1"></i>Set this event's type (Track / Field) first to compute preliminary heats.
          </div>
        <?php endif; ?>

        <div class="fw-semibold small mb-1">Rounds</div>
        <div class="rnd-list" id="rndList-<?= $esid ?>" data-esid="<?= $esid ?>">
          <?php if (empty($rounds)): ?>
            <p class="text-muted small mb-3">No rounds added yet.</p>
          <?php else: ?>
            <ol class="ps-3 mb-3">
              <?php foreach ($rounds as $rd): ?>
                <li class="mb-1 d-flex align-items-center gap-2">
                  <span><?= e($rd['round_name']) ?> · <strong><?= (int)$rd['num_heats'] ?></strong> heat<?= (int)$rd['num_heats'] === 1 ? '' : 's' ?></span>
                  <form method="POST" action="/lane-allocation/track/round-delete" class="rnd-del-form ms-auto"
                        data-esid="<?= $esid ?>">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="round_id" value="<?= (int)$rd['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger py-0 px-1" title="Remove"><i class="bi bi-x-lg"></i></button>
                  </form>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
        </div>

        <form method="POST" action="/lane-allocation/track/round-add" class="rnd-add-form border-top pt-3" data-esid="<?= $esid ?>">
          <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="event_sport_id" value="<?= $esid ?>">
          <div class="fw-semibold small mb-2">Add Round</div>
          <div class="row g-2 align-items-end">
            <div class="col-7">
              <label class="form-label small mb-1">Round name</label>
              <select name="round_name" class="form-select form-select-sm" required>
                <?php foreach ($roundNames as $rn): ?>
                  <option value="<?= e($rn) ?>"><?= e($rn) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-3">
              <label class="form-label small mb-1">Heats</label>
              <input type="number" name="num_heats" min="1" step="1" class="form-control form-control-sm"
                     value="<?= $prelim !== null ? (int)$prelim : 1 ?>" required>
            </div>
            <div class="col-2 d-grid">
              <button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i></button>
            </div>
          </div>
          <div class="rnd-msg small mt-2"></div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script>
(function () {
  function checked() { return Array.from(document.querySelectorAll('.row-check:checked')); }
  window.updSel = function () {
    const n = checked().length;
    const btn = document.getElementById('updTypeBtn');
    document.getElementById('selCount').textContent = n;
    btn.disabled = n === 0;
  };
  window.toggleAll = function (cb) {
    // Only toggle rows that are currently visible under the active filter, so
    // "select all" never reaches records the filter has hidden.
    document.querySelectorAll('#teTable tbody tr.te-row').forEach(tr => {
      const c = tr.querySelector('.row-check');
      if (!c) return;
      c.checked = tr.classList.contains('d-none') ? false : cb.checked;
    });
    updSel();
  };
  window.openEventTypeModal = function () {
    const ids = checked().map(c => c.value);
    if (!ids.length) return;
    const box = document.getElementById('etIds');
    box.innerHTML = ids.map(v => '<input type="hidden" name="event_sport_ids[]" value="' + v + '">').join('');
    document.getElementById('etCount').textContent = ids.length;
    new bootstrap.Modal(document.getElementById('eventTypeModal')).show();
  };

  // ── AJAX round manager (Manage Rounds modal) ──────────────────────────────
  const RND_CSRF = '<?= e($csrfToken) ?>';
  function rndEsc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g,
      c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }
  function rndBadge(rd) {
    return '<a href="/lane-allocation?round=' + rd.id + '" class="text-decoration-none" title="Open Heats & Lane Draw">'
      + '<span class="badge bg-primary-subtle text-primary-emphasis border">' + rndEsc(rd.round_name) + '</span>'
      + '<div class="small text-muted">' + rd.num_heats + ' heat' + (rd.num_heats === 1 ? '' : 's') + '</div></a>';
  }
  function rndRenderList(esid, rounds) {
    const box = document.getElementById('rndList-' + esid);
    if (!box) return;
    if (!rounds.length) { box.innerHTML = '<p class="text-muted small mb-3">No rounds added yet.</p>'; return; }
    box.innerHTML = '<ol class="ps-3 mb-3">' + rounds.map(function (rd) {
      return '<li class="mb-1 d-flex align-items-center gap-2">'
        + '<span>' + rndEsc(rd.round_name) + ' · <strong>' + rd.num_heats + '</strong> heat' + (rd.num_heats === 1 ? '' : 's') + '</span>'
        + '<form method="POST" action="/lane-allocation/track/round-delete" class="rnd-del-form ms-auto" data-esid="' + esid + '">'
        + '<input type="hidden" name="_token" value="' + RND_CSRF + '">'
        + '<input type="hidden" name="round_id" value="' + rd.id + '">'
        + '<button class="btn btn-sm btn-outline-danger py-0 px-1" title="Remove"><i class="bi bi-x-lg"></i></button>'
        + '</form></li>';
    }).join('') + '</ol>';
  }
  // Update the event's row in the table. Returns false when a new round column
  // is needed (more rounds than displayed columns) so the caller can reload.
  function rndRenderRow(esid, rounds) {
    const row = document.getElementById('teRow-' + esid);
    if (!row) return true;
    const cells = row.querySelectorAll('.te-rnd');
    if (rounds.length > cells.length) return false;
    cells.forEach(function (td, i) {
      td.innerHTML = rounds[i] ? rndBadge(rounds[i]) : '<span class="text-muted">—</span>';
    });
    return true;
  }
  async function rndSubmit(form) {
    const msg = form.querySelector('.rnd-msg');
    function show(t, ok) {
      if (msg) { msg.className = 'rnd-msg small mt-2 ' + (ok ? 'text-success' : 'text-danger'); msg.textContent = t;
                 if (ok) setTimeout(() => { msg.textContent = ''; }, 2500); }
      else if (!ok) alert(t);
    }
    try {
      const res = await fetch(form.action, {
        method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const data = await res.json();
      if (!data.success) { show(data.message || 'Could not save.', false); return; }
      rndRenderList(data.esid, data.rounds || []);
      if (!rndRenderRow(data.esid, data.rounds || [])) { location.reload(); return; }
      show(data.message || 'Saved.', true);
    } catch (e) { show('Network error while saving.', false); }
  }
  document.addEventListener('submit', function (e) {
    const form = e.target.closest('.rnd-add-form, .rnd-del-form');
    if (!form) return;
    e.preventDefault();
    if (form.classList.contains('rnd-del-form') && !confirm('Remove this round?')) return;
    rndSubmit(form);
  });
})();
</script>
<?php endif; ?>
