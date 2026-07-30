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
$medalCls     = [1 => 'text-warning', 2 => 'text-secondary', 3 => 'text-danger-emphasis'];
$hasData      = !empty($unit_tally) || !empty($events);

// Shared info bar (completion % + last-updated) rendered at the top of each tab.
$renderMtInfo = function () use ($isPublicView, $completion, $lastUpdated) {
  $bits = [];
  if (!$isPublicView && $completion && (int)$completion['registered'] > 0) {
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
      'points' => (int)($m['points'] ?? 0),
    ];
  }
}
?>

<?php if (!$hasData): ?>
  <div class="sms-card p-4 text-muted small text-center">
    <i class="bi bi-info-circle me-1"></i>No published medals yet.
  </div>
<?php else: ?>
  <ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#mt-units" type="button"><i class="bi bi-buildings me-1"></i>Unit-wise Points</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#mt-events" type="button"><i class="bi bi-trophy me-1"></i>Event-wise Winners</button></li>
  </ul>

  <div class="tab-content">
    <!-- Unit-wise Points -->
    <div class="tab-pane fade show active" id="mt-units" role="tabpanel">
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
                $mCell = function ($u, $key, $rank) {
                  $n = (int)$u[$key];
                  if ($n <= 0) return '<td class="text-center text-muted">0</td>';
                  return '<td class="text-center"><button type="button" class="btn btn-sm btn-link p-0 fw-bold medal-cell"'
                       . ' data-unit="' . e($u['unit']) . '" data-rank="' . $rank . '">' . $n . '</button></td>';
                };
              ?>
                <tr>
                  <td class="text-center fw-bold"><?= $i ?></td>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <?php if (!empty($u['logo'])): ?>
                        <img src="<?= e($u['logo']) ?>" alt="" style="width:26px;height:26px;object-fit:contain;flex-shrink:0">
                      <?php else: ?>
                        <i class="bi bi-buildings text-muted" style="width:26px;text-align:center"></i>
                      <?php endif; ?>
                      <span><?= e($u['unit']) ?></span>
                    </div>
                  </td>
                  <?= $mCell($u, 'g', 1) ?>
                  <?= $mCell($u, 's', 2) ?>
                  <?= $mCell($u, 'b', 3) ?>
                  <td class="text-end fw-bold"><?= (int)$u['points'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Event-wise Winners -->
    <div class="tab-pane fade" id="mt-events" role="tabpanel">
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
                <th style="width:70px" class="text-center">Type</th>
                <th>First</th>
                <th>Second</th>
                <th>Third</th>
              </tr>
            </thead>
            <tbody>
              <?php $sl = 0; foreach ($events as $ev): $sl++; $st = $ev['status'] ?? 'published'; $pc = (int)($ev['participants'] ?? 0); ?>
                <tr class="mt-ev-row <?= $st === 'published' ? '' : 'table-light' ?>" data-evstatus="<?= e($st) ?>" data-participants="<?= $pc ?>">
                  <td class="text-center mt-ev-sl"><?= $sl ?></td>
                  <td class="fw-medium"><?= e($ev['sport_event']) ?>
                    <span class="text-muted fw-normal">(<?= $pc ?>)</span>
                    <?php if ($st === 'unpublished'): ?>
                      <span class="badge bg-warning-subtle text-warning-emphasis ms-1" title="Results entered but not published">Not published</span>
                    <?php elseif ($st === 'pending'): ?>
                      <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1" title="No result recorded yet">No result yet</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center small"><?= e($ev['type']) ?></td>
                  <?php for ($rk = 1; $rk <= 3; $rk++): $list = $ev['places'][$rk] ?? []; if (!is_array($list)) $list = $list ? [$list] : []; ?>
                    <td class="small">
                      <?php if (empty($list)): ?>
                        <span class="text-muted">—</span>
                      <?php elseif (count($list) === 1): $p = $list[0]; ?>
                        <div class="d-flex align-items-start gap-2">
                          <?php if (!empty($p['photo'])): ?>
                            <img src="<?= e($p['photo']) ?>" alt="" style="width:30px;height:34px;object-fit:cover;border-radius:.3rem;flex-shrink:0">
                          <?php else: ?>
                            <span class="d-inline-flex align-items-center justify-content-center bg-body-secondary rounded" style="width:30px;height:34px;flex-shrink:0"><i class="bi bi-person text-muted"></i></span>
                          <?php endif; ?>
                          <div>
                            <div><i class="bi bi-award-fill <?= $medalCls[$rk] ?>"></i>
                              <?php if ($p['chest'] !== ''): ?><code><?= e($p['chest']) ?></code> <?php endif; ?>
                              <span class="fw-medium"><?= e($p['name']) ?></span>
                            </div>
                            <?php if ($p['unit'] !== ''): ?><div class="text-muted"><?= e($p['unit']) ?></div><?php endif; ?>
                          </div>
                        </div>
                      <?php else: /* tie — multiple winners, comma-separated */ ?>
                        <div><i class="bi bi-award-fill <?= $medalCls[$rk] ?>"></i>
                          <span class="badge bg-light text-dark border ms-1"><?= count($list) ?> tied</span>
                        </div>
                        <div class="mt-1">
                          <?php foreach ($list as $ix => $p): ?><?= $ix ? ', ' : '' ?><?php if ($p['chest'] !== ''): ?><code><?= e($p['chest']) ?></code> <?php endif; ?><span class="fw-medium"><?= e($p['name']) ?></span><?php if ($p['unit'] !== ''): ?> <span class="text-muted">(<?= e($p['unit']) ?>)</span><?php endif; ?><?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </td>
                  <?php endfor; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
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
      var n = 0;
      document.querySelectorAll('.mt-ev-row').forEach(function (tr) {
        var ok;
        if (mode === 'published')      ok = tr.dataset.evstatus === 'published';
        else if (mode === 'registered') ok = (parseInt(tr.dataset.participants, 10) || 0) > 0;
        else                            ok = true;
        tr.classList.toggle('d-none', !ok);
        if (ok) { var c = tr.querySelector('.mt-ev-sl'); if (c) c.textContent = ++n; }
      });
    }
    // Apply the default filter (Registered events) on load.
    document.addEventListener('DOMContentLoaded', function () { if (document.getElementById('mtEvFilter')) mtEvApplyFilter(); });
    // Auto-refresh the medal tally every 60 seconds so results stay live.
    setTimeout(function () { location.reload(); }, 60000);
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
                var photo = m.photo
                  ? '<img src="' + medalEsc(m.photo) + '" alt="" style="width:28px;height:32px;object-fit:cover;border-radius:.25rem">'
                  : '<span class="text-muted"><i class="bi bi-person"></i></span>';
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
  </script>
<?php endif; ?>
