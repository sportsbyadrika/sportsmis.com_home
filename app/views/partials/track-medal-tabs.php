<?php
/**
 * Shared Medal Tally tabs: (1) Unit-wise Points, (2) Event-wise Winners.
 * Included by the Staff portal and the Unit portal.
 * Expects: $unit_tally, $events, $unit_medals.
 * Optional: $showPrint (bool), $printBase (string) — when true, each tab shows
 *           a Print button to $printBase?section=units|events.
 */
$showPrint    = !empty($showPrint);
$printBase    = $printBase ?? '';
$isPublicView = !empty($isPublicView);          // public page: no filter, no completion
$completion   = $completion ?? null;
$lastUpdated  = $last_updated ?? null;
$ageTop       = $age_top ?? [];
$showTopUnits = !empty($show_top_units);          // staff-only: Age-category Top Institutions tab
$ageTopUnits  = $age_top_units ?? [];
$qualList     = $qualified_list ?? [];
$qualMaxRounds = 0;
foreach ($qualList as $qe) { $qualMaxRounds = max($qualMaxRounds, count($qe['rounds'] ?? [])); }
$medalCls     = [1 => 'text-warning', 2 => 'text-secondary', 3 => 'text-danger-emphasis'];
$hasData      = !empty($unit_tally) || !empty($events) || !empty($qualList);
$autoRefresh  = $auto_refresh ?? true;     // public/unit auto-reload; staff uses a manual Refresh button
$onlySection  = $only_section ?? '';       // '' = tabs; else one of units|events|agetop|qual (public card pages)
// Pane class helper: in single-section mode only the chosen pane renders (shown);
// in tabs mode the given default classes apply.
$paneClass = function (string $sec, string $default) use ($onlySection): string {
    return $onlySection === '' ? $default : 'tab-pane fade show active';
};
$canMarkEvent = !empty($can_mark_event);   // staff: show medal-distributed / certificate-issued switches per event
$eventStatus  = $event_status ?? [];       // esid => ['medal'=>bool,'cert'=>bool]
$statusBase   = $status_base ?? '';        // POST target for the status switches
if ($canMarkEvent && empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

// Shared info bar (completion % + last-updated) rendered at the top of each tab.
$renderMtInfo = function () use ($isPublicView, $completion, $lastUpdated) {
  $bits = [];
  if ($completion && (int)$completion['registered'] > 0) {
    $pct = (int)$completion['pct'];
    $tone = $pct >= 100 ? 'success' : ($pct >= 50 ? 'info' : 'warning');
    $bits[] = '<div class="d-inline-flex align-items-center gap-2">'
      . '<span class="small text-muted">Completion</span>'
      . '<div class="progress" style="width:100px;height:9px;border-radius:6px">'
      . '<div class="progress-bar bg-' . $tone . '" role="progressbar" style="width:' . $pct . '%"></div></div>'
      . '<span class="badge bg-' . $tone . '-subtle text-' . $tone . '-emphasis">'
      . $pct . '% · ' . (int)$completion['published'] . '/' . (int)$completion['registered'] . ' events</span></div>';
  }
  if ($lastUpdated) {
    $bits[] = '<span class="small text-muted ms-auto"><i class="bi bi-clock-history me-1"></i>Last updated '
      . e(formatDate($lastUpdated, 'd M Y, h:i A')) . '</span>';
  }
  if ($bits) {
    echo '<div class="d-flex align-items-center gap-3 flex-wrap mb-2">' . implode('', $bits) . '</div>';
  }
};

// Per-unit medal detail for the modal.
$medalData = [];
foreach (($unit_medals ?? []) as $unit => $list) {
  $medalData[$unit] = ['1' => [], '2' => [], '3' => []];
  foreach ($list as $m) {
    $rk = (string)(int)$m['rank'];
    if (isset($medalData[$unit][$rk])) $medalData[$unit][$rk][] = [
      'name'   => (string)$m['name'],
      'event'  => (string)$m['event'],
      'chest'  => (string)($m['chest'] ?? ''),
      'photo'  => (string)($m['photo'] ?? ''),
      'logo'   => (string)($m['logo'] ?? ''),
      'points' => (int)($m['points'] ?? 0),
    ];
  }
}

// Qualified-list roster detail for the modal, keyed "e{eventIdx}r{roundIdx}".
$qualData = [];
foreach ($qualList as $ei => $qe) {
  foreach (($qe['rounds'] ?? []) as $ri => $rd) {
    $qualData['e' . $ei . 'r' . $ri] = [
      'title'   => ($qe['sport_event'] ?? '') . ' · ' . ($rd['round_name'] ?? ''),
      'is_team' => !empty($qe['is_team']),
      'list'    => $rd['list'] ?? [],
    ];
  }
}
?>

<?php if (!$hasData): ?>
  <div class="sms-card p-4 text-muted small text-center">
    <i class="bi bi-info-circle me-1"></i>No published medals yet.
  </div>
<?php else: ?>
  <style>
    /* Alternate-row shading for the medal-tally tables + age-category blocks. */
    .mt-alt > td { background-color: rgba(2, 32, 71, .05); }
    .mt-agealt { background-color: rgba(2, 32, 71, .04); border-radius: .5rem; padding: .5rem .6rem .25rem; }
    @media (prefers-color-scheme: dark) {
      .mt-alt > td { background-color: rgba(255, 255, 255, .06); }
      .mt-agealt { background-color: rgba(255, 255, 255, .05); }
    }
  </style>
  <?php if ($onlySection === ''): ?>
  <ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#mt-units" type="button"><i class="bi bi-buildings me-1"></i>Unit-wise Points</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#mt-events" type="button"><i class="bi bi-trophy me-1"></i>Event-wise Winners</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#mt-agetop" type="button"><i class="bi bi-people me-1"></i>Age-category Top Athletes</button></li>
    <?php if ($showTopUnits): ?>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#mt-ageunits" type="button"><i class="bi bi-buildings-fill me-1"></i>Age-category Top Institutions</button></li>
    <?php endif; ?>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#mt-qual" type="button"><i class="bi bi-check2-square me-1"></i>Qualified List</button></li>
  </ul>
  <?php endif; ?>

  <div class="tab-content">
    <!-- Unit-wise Points -->
    <?php if ($onlySection === '' || $onlySection === 'units'): ?>
    <div class="<?= $paneClass('units', 'tab-pane fade show active') ?>" id="mt-units" role="tabpanel">
      <div class="sms-card p-3">
        <div class="d-flex align-items-center mb-2">
          <h6 class="fw-semibold mb-0"><i class="bi bi-buildings me-1"></i>Unit-wise Points</h6>
          <?php if ($showPrint): ?>
            <a class="btn btn-sm btn-outline-dark ms-auto" target="_blank" rel="noopener" href="<?= e($printBase) ?>?section=units">
              <i class="bi bi-printer me-1"></i>Print
            </a>
          <?php endif; ?>
        </div>
        <?php $renderMtInfo(); ?>
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:48px">Rank</th>
                <th>Unit / Institution</th>
                <th class="text-center" style="width:80px">🥇 Gold</th>
                <th class="text-center" style="width:80px">🥈 Silver</th>
                <th class="text-center" style="width:80px">🥉 Bronze</th>
                <th class="text-end" style="width:90px">Points</th>
              </tr>
            </thead>
            <tbody>
              <?php $i = 0; foreach ($unit_tally as $u): $i++;
                $mCell = function ($u, $key, $rank, $lbl) {
                  $n = (int)$u[$key];
                  if ($n <= 0) return '<td class="text-center text-muted" data-label="' . $lbl . '">0</td>';
                  return '<td class="text-center" data-label="' . $lbl . '"><button type="button" class="btn btn-sm btn-link p-0 fw-bold medal-cell"'
                       . ' data-unit="' . e($u['unit']) . '" data-rank="' . $rank . '">' . $n . '</button></td>';
                };
              ?>
                <tr class="<?= $i % 2 === 0 ? 'mt-alt' : '' ?>">
                  <td class="text-center fw-bold" data-label="Rank"><?= $i ?></td>
                  <td data-label="Unit">
                    <div class="d-flex align-items-center gap-2">
                      <?php if (!empty($u['logo'])): ?>
                        <img src="<?= e($u['logo']) ?>" alt="" style="width:26px;height:26px;object-fit:contain;flex-shrink:0">
                      <?php else: ?>
                        <i class="bi bi-buildings text-muted" style="width:26px;text-align:center"></i>
                      <?php endif; ?>
                      <span><?= e($u['unit']) ?></span>
                    </div>
                  </td>
                  <?= $mCell($u, 'g', 1, 'Gold') ?>
                  <?= $mCell($u, 's', 2, 'Silver') ?>
                  <?= $mCell($u, 'b', 3, 'Bronze') ?>
                  <td class="text-end fw-bold" data-label="Points"><?= (int)$u['points'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Event-wise Winners -->
    <?php if ($onlySection === '' || $onlySection === 'events'): ?>
    <div class="<?= $paneClass('events', 'tab-pane fade') ?>" id="mt-events" role="tabpanel">
      <div class="sms-card p-3">
        <?php
          // Coverage summary — how many events already have published winners.
          $evTotal = count($events); $evDone = 0; $evUnpub = 0;
          foreach ($events as $e) {
            $st = $e['status'] ?? 'published';
            if ($st === 'published') $evDone++;
            elseif ($st === 'unpublished') $evUnpub++;
          }
          $evMissing = $evTotal - $evDone;
          // Day-wise grouping: distinct "final result entered" dates → Day 1, 2, …
          $eventDays = [];
          foreach ($events as $e) { $d = trim((string)($e['result_date'] ?? '')); if ($d !== '') $eventDays[$d] = true; }
          $eventDays = array_keys($eventDays);
          sort($eventDays);
          $dayNo = [];
          foreach ($eventDays as $ix => $d) { $dayNo[$d] = $ix + 1; }
        ?>
        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
          <h6 class="fw-semibold mb-0"><i class="bi bi-trophy me-1"></i>Event-wise Winners</h6>
          <?php if ($evTotal > 0): ?>
            <span class="badge bg-success-subtle text-success-emphasis"><?= $evDone ?>/<?= $evTotal ?> with results</span>
            <?php if ($evMissing > 0): ?>
              <span class="badge bg-secondary-subtle text-secondary-emphasis"><?= $evMissing ?> missing</span>
            <?php endif; ?>
            <?php if ($evUnpub > 0): ?>
              <span class="badge bg-warning-subtle text-warning-emphasis"><?= $evUnpub ?> unpublished</span>
            <?php endif; ?>
          <?php endif; ?>
          <?php if (!$isPublicView): ?>
            <select id="mtEvFilter" class="form-select form-select-sm ms-auto" style="width:auto" onchange="mtEvApplyFilter()">
              <option value="registered" selected>Registered events</option>
              <option value="published">Result only</option>
              <option value="all">All events</option>
            </select>
            <?php if (!empty($eventDays)): ?>
              <select id="mtEvDay" class="form-select form-select-sm" style="width:auto" onchange="mtEvApplyFilter()"
                      title="Filter by the day the final result was entered">
                <option value="">All days</option>
                <?php foreach ($eventDays as $d): ?>
                  <option value="<?= e($d) ?>">Day <?= $dayNo[$d] ?> — <?= e(formatDate($d, 'd M Y')) ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($showPrint): ?>
            <a class="btn btn-sm btn-outline-dark<?= $isPublicView ? ' ms-auto' : '' ?>" target="_blank" rel="noopener" href="<?= e($printBase) ?>?section=events">
              <i class="bi bi-printer me-1"></i>Print
            </a>
          <?php endif; ?>
        </div>
        <?php $renderMtInfo(); ?>
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:48px">Sl.</th>
                <th>Sport Event</th>
                <th>First</th>
                <th>Second</th>
                <th>Third</th>
                <?php if ($canMarkEvent): ?>
                  <th class="text-center" style="width:104px" title="Medals distributed for this event">Medals Given</th>
                  <th class="text-center" style="width:110px" title="Certificates issued for this event">Certs Issued</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php
                // Group events by the day their result was updated: dated groups
                // in chronological order, an undated ("results awaited") group last.
                $evGroups = [];
                foreach ($events as $ev) { $d = trim((string)($ev['result_date'] ?? '')); $evGroups[$d][] = $ev; }
                $evGroupDates = array_values(array_filter(array_keys($evGroups), fn($d) => $d !== ''));
                sort($evGroupDates);
                if (isset($evGroups[''])) $evGroupDates[] = '';
                $evColspan = $canMarkEvent ? 7 : 5;
                $sl = 0;
                foreach ($evGroupDates as $gd): $grpRows = $evGroups[$gd];
              ?>
                <tr class="mt-ev-group" data-groupday="<?= e($gd) ?>">
                  <td colspan="<?= $evColspan ?>" class="fw-semibold" style="background:#e9eef5">
                    <?php if ($gd !== ''): ?>
                      <i class="bi bi-calendar3 me-1"></i>Day <?= $dayNo[$gd] ?? '' ?> &mdash; <?= e(formatDate($gd, 'd M Y')) ?>
                      <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1"><?= count($grpRows) ?> event<?= count($grpRows) === 1 ? '' : 's' ?></span>
                    <?php else: ?>
                      <i class="bi bi-hourglass-split me-1"></i>Results awaited
                      <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1"><?= count($grpRows) ?></span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php foreach ($grpRows as $ev): $sl++; $st = $ev['status'] ?? 'published'; $pc = (int)($ev['participants'] ?? 0); ?>
                <tr class="mt-ev-row <?= $st === 'published' ? '' : 'table-light' ?> <?= $sl % 2 === 0 ? 'mt-alt' : '' ?>" data-evstatus="<?= e($st) ?>" data-participants="<?= $pc ?>" data-resultday="<?= e((string)($ev['result_date'] ?? '')) ?>">
                  <td class="text-center mt-ev-sl" data-label="#"><?= $sl ?></td>
                  <td class="fw-medium" data-label="Event"><?= e($ev['sport_event']) ?>
                    <span class="text-muted fw-normal">(<?= $pc ?>)</span>
                    <?php $ty = (string)($ev['type'] ?? ''); if ($ty === 'Team'): ?>
                      <span class="badge bg-primary-subtle text-primary-emphasis ms-1">Team</span>
                    <?php elseif ($ty === 'Individual'): ?>
                      <span class="badge bg-info-subtle text-info-emphasis ms-1">Individual</span>
                    <?php endif; ?>
                    <?php if ($st === 'unpublished'): ?>
                      <span class="badge bg-warning-subtle text-warning-emphasis ms-1" title="Results entered but not published">Not published</span>
                    <?php elseif ($st === 'pending'): ?>
                      <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1" title="No result recorded yet">No result yet</span>
                    <?php endif; ?>
                  </td>
                  <?php $rkLbl = [1 => 'First', 2 => 'Second', 3 => 'Third'];
                        for ($rk = 1; $rk <= 3; $rk++): $list = $ev['places'][$rk] ?? []; if (!is_array($list)) $list = $list ? [$list] : []; ?>
                    <td class="small" data-label="<?= $rkLbl[$rk] ?>">
                      <?php if (empty($list)): ?>
                        <span class="text-muted">—</span>
                      <?php elseif (count($list) === 1): $p = $list[0];
                        // Team places show the institution logo (no individual photo),
                        // the Unit Relay Code in brackets and the members (BIB + name).
                        $isTeamPlace = !empty($p['team_id']);
                        $img = $isTeamPlace ? (string)($p['unit_logo'] ?? '') : (string)($p['photo'] ?? '');
                      ?>
                        <div class="d-flex align-items-start gap-2">
                          <?php if ($img !== ''): ?>
                            <img src="<?= e($img) ?>" alt="" style="width:30px;height:34px;object-fit:<?= $isTeamPlace ? 'contain' : 'cover' ?>;border-radius:.3rem;flex-shrink:0">
                          <?php else: ?>
                            <span class="d-inline-flex align-items-center justify-content-center bg-body-secondary rounded" style="width:30px;height:34px;flex-shrink:0"><i class="bi <?= $isTeamPlace ? 'bi-buildings' : 'bi-person' ?> text-muted"></i></span>
                          <?php endif; ?>
                          <div>
                            <div><i class="bi bi-award-fill <?= $medalCls[$rk] ?>"></i>
                              <?php if ($p['chest'] !== ''): ?><code><?= e($p['chest']) ?></code> <?php endif; ?>
                              <span class="fw-medium"><?= e($p['name']) ?></span>
                              <?php if ($isTeamPlace && !empty($p['relay_code'])): ?><span class="text-muted">(<?= e($p['relay_code']) ?>)</span><?php endif; ?>
                            </div>
                            <?php if ($p['unit'] !== ''): ?><div class="text-muted"><?= e($p['unit']) ?></div><?php endif; ?>
                            <?php if ($isTeamPlace && !empty($p['sub'])): ?><div class="text-muted"><i class="bi bi-people me-1"></i><?= e($p['sub']) ?></div><?php endif; ?>
                          </div>
                        </div>
                      <?php else: /* tie — multiple winners, comma-separated */ ?>
                        <div><i class="bi bi-award-fill <?= $medalCls[$rk] ?>"></i>
                          <span class="badge bg-light text-dark border ms-1"><?= count($list) ?> tied</span>
                        </div>
                        <div class="mt-1">
                          <?php foreach ($list as $ix => $p): ?><?= $ix ? ', ' : '' ?><?php if ($p['chest'] !== ''): ?><code><?= e($p['chest']) ?></code> <?php endif; ?><span class="fw-medium"><?= e($p['name']) ?></span><?php if (!empty($p['team_id']) && !empty($p['relay_code'])): ?> <span class="text-muted">(<?= e($p['relay_code']) ?>)</span><?php endif; ?><?php if ($p['unit'] !== ''): ?> <span class="text-muted">[<?= e($p['unit']) ?>]</span><?php endif; ?><?php if (!empty($p['team_id']) && !empty($p['sub'])): ?> <span class="text-muted small">— <?= e($p['sub']) ?></span><?php endif; ?><?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </td>
                  <?php endfor; ?>
                  <?php if ($canMarkEvent):
                    $esid = (int)($ev['esid'] ?? 0);
                    $es = $eventStatus[$esid] ?? ['medal' => false, 'cert' => false];
                  ?>
                    <td class="text-center" data-label="Medals Given">
                      <div class="form-check form-switch d-inline-flex m-0 ps-0" style="min-height:auto">
                        <input class="form-check-input m-0 event-status-switch" type="checkbox" role="switch"
                               style="cursor:pointer" data-esid="<?= $esid ?>" data-field="medal" <?= $es['medal'] ? 'checked' : '' ?>>
                      </div>
                    </td>
                    <td class="text-center" data-label="Certs Issued">
                      <div class="form-check form-switch d-inline-flex m-0 ps-0" style="min-height:auto">
                        <input class="form-check-input m-0 event-status-switch" type="checkbox" role="switch"
                               style="cursor:pointer" data-esid="<?= $esid ?>" data-field="cert" <?= $es['cert'] ? 'checked' : '' ?>>
                      </div>
                    </td>
                  <?php endif; ?>
                </tr>
                <?php endforeach; /* rows in group */ ?>
              <?php endforeach; /* date groups */ ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Age-category Top Athletes -->
    <?php if ($onlySection === '' || $onlySection === 'agetop'): ?>
    <div class="<?= $paneClass('agetop', 'tab-pane fade') ?>" id="mt-agetop" role="tabpanel">
      <div class="sms-card p-3">
        <div class="d-flex align-items-center mb-2">
          <h6 class="fw-semibold mb-0"><i class="bi bi-people me-1"></i>Age-category Top Athletes</h6>
          <?php if ($showPrint): ?>
            <a class="btn btn-sm btn-outline-dark ms-auto" target="_blank" rel="noopener" href="<?= e($printBase) ?>?section=agetop">
              <i class="bi bi-printer me-1"></i>Print
            </a>
          <?php endif; ?>
        </div>
        <?php $renderMtInfo(); ?>
        <?php if (empty($ageTop)): ?>
          <p class="text-muted small mb-0">No published individual medals yet.</p>
        <?php else:
          $atCls = [0 => 'warning', 1 => 'secondary', 2 => 'danger'];
          $atLbl = [0 => '1st', 1 => '2nd', 2 => '3rd'];
          $genBadge = ['Male' => 'primary', 'Female' => 'danger', 'Mixed' => 'success', 'Other' => 'secondary'];
          $agi = 0;
          foreach ($ageTop as $ag): $agi++; ?>
          <div class="mb-3 <?= $agi % 2 === 0 ? 'mt-agealt' : '' ?>">
            <div class="fw-semibold border-bottom pb-1 mb-2"><i class="bi bi-tag me-1"></i><?= e($ag['age']) ?></div>
            <?php foreach (($ag['genders'] ?? []) as $gp): ?>
              <div class="mb-2">
                <div class="small fw-semibold mb-1 d-flex align-items-center flex-wrap gap-2">
                  <span class="badge bg-<?= $genBadge[$gp['gender']] ?? 'secondary' ?>-subtle text-<?= $genBadge[$gp['gender']] ?? 'secondary' ?>-emphasis"><i class="bi bi-gender-ambiguous me-1"></i><?= e($gp['gender']) ?></span>
                  <?php $gc = $gp['completion'] ?? null; if ($gc && (int)$gc['registered'] > 0):
                    $gpct = (int)$gc['pct']; $gtone = $gpct >= 100 ? 'success' : ($gpct >= 50 ? 'info' : 'warning'); ?>
                    <span class="d-inline-flex align-items-center gap-1" title="Events completed in this age &amp; gender">
                      <span class="progress" style="width:70px;height:8px;border-radius:6px">
                        <span class="progress-bar bg-<?= $gtone ?>" role="progressbar" style="width:<?= $gpct ?>%;display:block;height:100%"></span>
                      </span>
                      <span class="badge bg-<?= $gtone ?>-subtle text-<?= $gtone ?>-emphasis fw-normal"><?= $gpct ?>% &middot; <?= (int)$gc['published'] ?>/<?= (int)$gc['registered'] ?> events</span>
                    </span>
                  <?php endif; ?>
                </div>
                <div class="row g-2">
                  <?php foreach ($gp['athletes'] as $i => $at): $pos = (int)($at['pos'] ?? ($i + 1)); $pi = $pos - 1; ?>
                    <div class="col-md-4">
                      <div class="border rounded p-2 h-100 d-flex align-items-center gap-2">
                        <span class="badge bg-<?= $atCls[$pi] ?? 'light' ?>-subtle text-<?= $atCls[$pi] ?? 'muted' ?>-emphasis" style="min-width:34px"><?= $atLbl[$pi] ?? ($pos) ?></span>
                        <?php if (!empty($at['photo'])): ?>
                          <img src="<?= e($at['photo']) ?>" alt="" style="width:38px;height:44px;object-fit:cover;border-radius:.3rem;flex-shrink:0">
                        <?php else: ?>
                          <span class="d-inline-flex align-items-center justify-content-center bg-body-secondary rounded" style="width:38px;height:44px;flex-shrink:0"><i class="bi bi-person text-muted"></i></span>
                        <?php endif; ?>
                        <div class="flex-grow-1" style="min-width:0;overflow:hidden">
                          <div class="fw-medium text-truncate" title="<?= e($at['name']) ?>">
                            <?php if ($at['chest'] !== ''): ?><code><?= e($at['chest']) ?></code> <?php endif; ?><?= e($at['name']) ?>
                          </div>
                          <?php if ($at['unit'] !== ''): ?><div class="small text-muted text-truncate" title="<?= e($at['unit']) ?>"><?= e($at['unit']) ?></div><?php endif; ?>
                          <div class="small mt-1">
                            <span class="badge bg-dark-subtle text-dark-emphasis"><?= (int)$at['points'] ?> pts</span>
                            <span class="text-muted ms-1">🥇<?= (int)$at['gold'] ?> 🥈<?= (int)$at['silver'] ?> 🥉<?= (int)$at['bronze'] ?></span>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Age-category Top Institutions (staff tab / unit tab / public section) -->
    <?php if ($showTopUnits && ($onlySection === '' || $onlySection === 'ageunits')): ?>
    <div class="<?= $paneClass('ageunits', 'tab-pane fade') ?>" id="mt-ageunits" role="tabpanel">
      <div class="sms-card p-3">
        <div class="d-flex align-items-center mb-2">
          <h6 class="fw-semibold mb-0"><i class="bi bi-buildings-fill me-1"></i>Age-category Top Institutions</h6>
          <?php if ($showPrint): ?>
            <a class="btn btn-sm btn-outline-dark ms-auto" target="_blank" rel="noopener" href="<?= e($printBase) ?>?section=ageunits">
              <i class="bi bi-printer me-1"></i>Print
            </a>
          <?php endif; ?>
        </div>
        <?php $renderMtInfo(); ?>
        <p class="text-muted small mb-2"><i class="bi bi-info-circle me-1"></i>Institutions ranked by medal points earned within each age category (individual &amp; team).</p>
        <?php if (empty($ageTopUnits)): ?>
          <p class="text-muted small mb-0">No published medals yet.</p>
        <?php else:
          $auTone = [0 => 'warning', 1 => 'secondary', 2 => 'danger'];
          $agi = 0;
          foreach ($ageTopUnits as $ag): $agi++; ?>
          <div class="mb-3 <?= $agi % 2 === 0 ? 'mt-agealt' : '' ?>">
            <div class="fw-semibold border-bottom pb-1 mb-2"><i class="bi bi-tag me-1"></i><?= e($ag['age']) ?></div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="text-center" style="width:60px">Rank</th>
                    <th>Institution</th>
                    <th class="text-center" style="width:56px">🥇</th>
                    <th class="text-center" style="width:56px">🥈</th>
                    <th class="text-center" style="width:56px">🥉</th>
                    <th class="text-center" style="width:80px">Points</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($ag['units'] as $i => $u): ?>
                    <tr>
                      <td class="text-center"><span class="badge bg-<?= $auTone[$i] ?? 'light' ?>-subtle text-<?= $auTone[$i] ?? 'muted' ?>-emphasis" style="min-width:30px"><?= $i + 1 ?></span></td>
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          <?php if (!empty($u['logo'])): ?>
                            <img src="<?= e($u['logo']) ?>" alt="" style="width:26px;height:26px;object-fit:contain;flex-shrink:0">
                          <?php endif; ?>
                          <span class="fw-medium"><?= e($u['unit']) ?></span>
                        </div>
                      </td>
                      <td class="text-center"><?= (int)$u['g'] ?></td>
                      <td class="text-center"><?= (int)$u['s'] ?></td>
                      <td class="text-center"><?= (int)$u['b'] ?></td>
                      <td class="text-center"><span class="badge bg-dark-subtle text-dark-emphasis"><?= (int)$u['points'] ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Qualified List (published only) -->
    <?php if ($onlySection === '' || $onlySection === 'qual'): ?>
    <div class="<?= $paneClass('qual', 'tab-pane fade') ?>" id="mt-qual" role="tabpanel">
      <div class="sms-card p-3">
        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
          <h6 class="fw-semibold mb-0"><i class="bi bi-check2-square me-1"></i>Qualified List
            <span class="text-muted fw-normal small">(published only)</span></h6>
        </div>
        <?php $renderMtInfo(); ?>
        <?php if (empty($qualList)): ?>
          <p class="text-muted small mb-0">No qualified athletes published yet.</p>
        <?php else: ?>
          <p class="small text-muted mb-2">Click a round's count to see the qualified <?= 'athletes / teams' ?>.</p>
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width:48px">Sl.</th>
                  <th>Sport Event</th>
                  <?php for ($c = 1; $c <= $qualMaxRounds; $c++): ?>
                    <th class="text-center" style="width:150px">Round <?= $c ?></th>
                  <?php endfor; ?>
                </tr>
              </thead>
              <tbody>
                <?php
                  // Group by the day the qualified result was updated (chronological),
                  // undated ("results awaited") last. Keep each event's original
                  // index $ei so the roster popup keys (data-key="e{ei}r{c}") stay valid.
                  $qualGroups = [];
                  foreach ($qualList as $ei => $qe) { $d = trim((string)($qe['result_date'] ?? '')); $qualGroups[$d][] = ['ei' => $ei, 'qe' => $qe]; }
                  $qualGroupDates = array_values(array_filter(array_keys($qualGroups), fn($d) => $d !== ''));
                  sort($qualGroupDates);
                  $qualDayNo = [];
                  foreach ($qualGroupDates as $ix => $d) { $qualDayNo[$d] = $ix + 1; }
                  if (isset($qualGroups[''])) $qualGroupDates[] = '';
                  $qColspan = 2 + $qualMaxRounds;
                  $qsl = 0;
                  foreach ($qualGroupDates as $gd): $qGrp = $qualGroups[$gd];
                ?>
                  <tr class="table-light">
                    <td colspan="<?= $qColspan ?>" class="fw-semibold" style="background:#e9eef5">
                      <?php if ($gd !== ''): ?>
                        <i class="bi bi-calendar3 me-1"></i>Day <?= $qualDayNo[$gd] ?? '' ?> &mdash; <?= e(formatDate($gd, 'd M Y')) ?>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1"><?= count($qGrp) ?> event<?= count($qGrp) === 1 ? '' : 's' ?></span>
                      <?php else: ?>
                        <i class="bi bi-hourglass-split me-1"></i>Results awaited
                        <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1"><?= count($qGrp) ?></span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php foreach ($qGrp as $qItem): $ei = $qItem['ei']; $qe = $qItem['qe']; $qsl++;
                    $qsub = [];
                    if (($qe['category'] ?? '') !== '') $qsub[] = e($qe['category']);
                    if (($qe['age_name'] ?? '') !== '') $qsub[] = e($qe['age_name']);
                    if (($qe['gender'] ?? '') !== '')   $qsub[] = e($qe['gender']);
                  ?>
                  <tr class="<?= $qsl % 2 === 0 ? 'mt-alt' : '' ?>">
                    <td class="text-center"><?= $qsl ?></td>
                    <td class="fw-medium"><?= e($qe['sport_event']) ?>
                      <?php if (!empty($qe['is_team'])): ?>
                        <span class="badge bg-primary-subtle text-primary-emphasis ms-1">Team</span>
                      <?php else: ?>
                        <span class="badge bg-info-subtle text-info-emphasis ms-1">Individual</span>
                      <?php endif; ?>
                      <?php if ($qsub): ?><div class="small text-muted fw-normal"><?= implode(' · ', $qsub) ?></div><?php endif; ?>
                    </td>
                    <?php for ($c = 0; $c < $qualMaxRounds; $c++): $rd = $qe['rounds'][$c] ?? null; ?>
                      <td class="text-center small">
                        <?php if (!$rd): ?>
                          <span class="text-muted">—</span>
                        <?php else: $cnt = (int)$rd['count']; ?>
                          <div><span class="badge bg-secondary-subtle text-secondary-emphasis"><?= e($rd['round_name']) ?></span></div>
                          <div class="mt-1">
                            <?php if ($cnt > 0): ?>
                              <button type="button" class="btn btn-sm btn-link p-0 fw-bold qual-cell" data-key="e<?= $ei ?>r<?= $c ?>">
                                <i class="bi bi-people me-1"></i><?= $cnt ?> qualified
                              </button>
                            <?php else: ?>
                              <span class="text-muted">0</span>
                            <?php endif; ?>
                          </div>
                        <?php endif; ?>
                      </td>
                    <?php endfor; ?>
                  </tr>
                  <?php endforeach; /* events in group */ ?>
                <?php endforeach; /* date groups */ ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Qualified roster modal -->
  <div class="modal fade" id="qualModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title fw-semibold" id="qualModalTitle">Qualified</h6>
          <span class="badge bg-success-subtle text-success-emphasis ms-2" id="qualModalCount"></span>
          <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
              <thead class="table-light" id="qualModalHead"></thead>
              <tbody id="qualModalBody"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Medal detail modal -->
  <div class="modal fade" id="medalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title fw-semibold" id="medalModalTitle">Medals</h6>
          <span class="badge bg-primary-subtle text-primary-emphasis ms-2" id="medalModalTotal"></span>
          <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width:44px">Sl.</th>
                  <th style="width:46px">Photo</th>
                  <th style="width:70px">Chest</th>
                  <th>Participant / Team</th>
                  <th>Name of Event</th>
                  <th style="width:80px">Position</th>
                  <th style="width:70px" class="text-end">Point</th>
                </tr>
              </thead>
              <tbody id="medalModalBody"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    var MEDAL_DATA = <?= json_encode($medalData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    var MEDAL_POS  = { '1': 'First', '2': 'Second', '3': 'Third' };
    var MEDAL_LBL  = { '1': 'Gold', '2': 'Silver', '3': 'Bronze' };
    function medalEsc(s){ return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
    // Event-wise Winners filter: Registered (participants>0) / Result only / All.
    function mtEvApplyFilter() {
      var sel = document.getElementById('mtEvFilter');
      var mode = sel ? sel.value : 'all';
      var daySel = document.getElementById('mtEvDay');
      var day = daySel ? daySel.value : '';
      var n = 0;
      document.querySelectorAll('.mt-ev-row').forEach(function (tr) {
        var ok;
        if (mode === 'published')      ok = tr.dataset.evstatus === 'published';
        else if (mode === 'registered') ok = (parseInt(tr.dataset.participants, 10) || 0) > 0;
        else                            ok = true;
        if (ok && day) ok = tr.dataset.resultday === day;   // day-wise: final result entered on this date
        tr.classList.toggle('d-none', !ok);
        if (ok) {
          n++;
          var c = tr.querySelector('.mt-ev-sl'); if (c) c.textContent = n;
          tr.classList.toggle('mt-alt', n % 2 === 0);   // zebra over visible rows
        } else {
          tr.classList.remove('mt-alt');
        }
      });
      // Hide a date-group header when all of its rows are filtered out.
      document.querySelectorAll('.mt-ev-group').forEach(function (h) {
        var gd = h.dataset.groupday || '', any = false;
        document.querySelectorAll('.mt-ev-row').forEach(function (tr) {
          if ((tr.dataset.resultday || '') === gd && !tr.classList.contains('d-none')) any = true;
        });
        h.classList.toggle('d-none', !any);
      });
    }
    // Apply the default filter (Registered events) on load.
    document.addEventListener('DOMContentLoaded', function () { if (document.getElementById('mtEvFilter')) mtEvApplyFilter(); });
    <?php if ($autoRefresh): ?>
    // Auto-refresh the medal tally every 60 seconds so results stay live.
    setTimeout(function () { location.reload(); }, 60000);
    <?php endif; ?>
    document.addEventListener('DOMContentLoaded', function () {
      var modalEl = document.getElementById('medalModal');
      var modal = modalEl ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
      document.querySelectorAll('.medal-cell').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var unit = btn.dataset.unit, rk = btn.dataset.rank;
          var list = (MEDAL_DATA[unit] && MEDAL_DATA[unit][rk]) ? MEDAL_DATA[unit][rk] : [];
          var total = list.reduce(function (s, m) { return s + (parseInt(m.points, 10) || 0); }, 0);
          document.getElementById('medalModalTitle').textContent = MEDAL_LBL[rk] + ' — ' + unit + ' (' + list.length + ')';
          document.getElementById('medalModalTotal').textContent = 'Total ' + total + ' pt' + (total === 1 ? '' : 's');
          document.getElementById('medalModalBody').innerHTML = list.length
            ? list.map(function (m, i) {
                // Teams carry an institution logo (no individual photo) — show that instead.
                var photo = m.logo
                  ? '<img src="' + medalEsc(m.logo) + '" alt="" style="width:28px;height:32px;object-fit:contain;border-radius:.25rem">'
                  : (m.photo
                    ? '<img src="' + medalEsc(m.photo) + '" alt="" style="width:28px;height:32px;object-fit:cover;border-radius:.25rem">'
                    : '<span class="text-muted"><i class="bi bi-person"></i></span>');
                var chest = m.chest ? '<code>' + medalEsc(m.chest) + '</code>' : '';
                return '<tr><td class="text-center">' + (i + 1) + '</td>'
                     + '<td>' + photo + '</td>'
                     + '<td class="text-center">' + chest + '</td>'
                     + '<td>' + medalEsc(m.name) + '</td>'
                     + '<td>' + medalEsc(m.event) + '</td>'
                     + '<td>' + MEDAL_POS[rk] + '</td>'
                     + '<td class="text-end fw-bold">' + (parseInt(m.points, 10) || 0) + '</td></tr>';
              }).join('')
            : '<tr><td colspan="7" class="text-center text-muted py-3">No entries.</td></tr>';
          if (modal) modal.show();
        });
      });
    });

    // Qualified List — roster popup per event-round.
    var QUAL_DATA = <?= json_encode($qualData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    document.addEventListener('DOMContentLoaded', function () {
      var qEl = document.getElementById('qualModal');
      var qModal = qEl ? bootstrap.Modal.getOrCreateInstance(qEl) : null;
      document.querySelectorAll('.qual-cell').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var d = QUAL_DATA[btn.dataset.key]; if (!d) return;
          var list = d.list || [];
          document.getElementById('qualModalTitle').textContent = d.title;
          document.getElementById('qualModalCount').textContent = list.length + ' qualified';
          var head = document.getElementById('qualModalHead');
          var body = document.getElementById('qualModalBody');
          if (d.is_team) {
            head.innerHTML = '<tr><th style="width:44px">Sl.</th><th style="width:96px">Team Code</th>'
                           + '<th>Members (Chest · Name)</th><th>Institution</th></tr>';
            body.innerHTML = list.length ? list.map(function (m, i) {
              return '<tr><td class="text-center">' + (i + 1) + '</td>'
                   + '<td class="text-center"><code>' + medalEsc(m.code || '—') + '</code></td>'
                   + '<td>' + medalEsc(m.members || '') + '</td>'
                   + '<td>' + medalEsc(m.unit || '') + '</td></tr>';
            }).join('') : '<tr><td colspan="4" class="text-center text-muted py-3">No entries.</td></tr>';
          } else {
            head.innerHTML = '<tr><th style="width:44px">Sl.</th><th style="width:90px">Chest No</th>'
                           + '<th>Name</th><th>Institution</th></tr>';
            body.innerHTML = list.length ? list.map(function (m, i) {
              return '<tr><td class="text-center">' + (i + 1) + '</td>'
                   + '<td class="text-center">' + (m.chest ? '<code>' + medalEsc(m.chest) + '</code>' : '') + '</td>'
                   + '<td>' + medalEsc(m.name || '') + '</td>'
                   + '<td>' + medalEsc(m.unit || '') + '</td></tr>';
            }).join('') : '<tr><td colspan="4" class="text-center text-muted py-3">No entries.</td></tr>';
          }
          if (qModal) qModal.show();
        });
      });
    });

    <?php if ($canMarkEvent): ?>
    // Event-wise switches — persist medal-distributed / certificate-issued flags.
    var EV_STATUS_BASE  = '<?= e($statusBase) ?>';
    var EV_STATUS_TOKEN = '<?= e($_SESSION['csrf_token'] ?? '') ?>';
    document.addEventListener('DOMContentLoaded', function () {
      document.querySelectorAll('.event-status-switch').forEach(function (sw) {
        sw.addEventListener('change', function () {
          var fd = new FormData();
          fd.append('_token', EV_STATUS_TOKEN);
          fd.append('esid',  sw.dataset.esid);
          fd.append('field', sw.dataset.field);
          fd.append('value', sw.checked ? 1 : 0);
          sw.disabled = true;
          fetch(EV_STATUS_BASE, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (!d || !d.success) { sw.checked = !sw.checked; alert((d && d.message) || 'Could not save.'); } })
            .catch(function () { sw.checked = !sw.checked; alert('Network error while saving.'); })
            .finally(function () { sw.disabled = false; });
        });
      });
    });
    <?php endif; ?>
  </script>
<?php endif; ?>
