<?php
/**
 * Athletics / Skating — Medal Tally (screen).
 * (a) Unit-wise points, (b) Event-wise 1st / 2nd / 3rd (individual + team).
 * Expects: $event, $unit_tally, $events.
 */
$pageTitle = 'Medal Tally — ' . $event['name'];
$place = [1 => 'First', 2 => 'Second', 3 => 'Third'];
$medalCls = [1 => 'text-warning', 2 => 'text-secondary', 3 => 'text-danger-emphasis'];
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/result-reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-award me-2"></i>Medal Tally</h5>
  <span class="badge bg-info-subtle text-info-emphasis">Athletics / Skating</span>
  <?php if (!empty($unit_tally) || !empty($events)): ?>
    <a class="btn btn-sm btn-outline-dark ms-auto" target="_blank" rel="noopener"
       href="/event-staff/result-reports/track-medal/print">
      <i class="bi bi-printer me-1"></i>Print
    </a>
  <?php endif; ?>
</div>

<?= flashBag() ?>

<?php if (empty($unit_tally) && empty($events)): ?>
  <div class="sms-card p-4 text-muted small text-center">
    <i class="bi bi-info-circle me-1"></i>No medals recorded yet. Enter ranks in the Results tab (individual) and Team Results, then return here.
  </div>
<?php else: ?>
  <!-- (a) Unit-wise points -->
  <div class="sms-card p-3 mb-3">
    <h6 class="fw-semibold border-bottom pb-2 mb-2"><i class="bi bi-buildings me-1"></i>Unit-wise Points</h6>
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
              <td><?= e($u['unit']) ?></td>
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

  <!-- (b) Event-wise Top 3 -->
  <div class="sms-card p-3">
    <h6 class="fw-semibold border-bottom pb-2 mb-2"><i class="bi bi-trophy me-1"></i>Event-wise Winners</h6>
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
          <?php $sl = 0; foreach ($events as $ev): $sl++; ?>
            <tr>
              <td class="text-center"><?= $sl ?></td>
              <td class="fw-medium"><?= e($ev['sport_event']) ?></td>
              <td class="text-center small"><?= e($ev['type']) ?></td>
              <?php for ($rk = 1; $rk <= 3; $rk++): $p = $ev['places'][$rk] ?? null; ?>
                <td class="small">
                  <?php if ($p): ?>
                    <div><i class="bi bi-award-fill <?= $medalCls[$rk] ?>"></i>
                      <?php if ($p['chest'] !== ''): ?><code><?= e($p['chest']) ?></code> <?php endif; ?>
                      <span class="fw-medium"><?= e($p['name']) ?></span>
                    </div>
                    <?php if ($p['unit'] !== ''): ?><div class="text-muted"><?= e($p['unit']) ?></div><?php endif; ?>
                  <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
              <?php endfor; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php
    // Per-unit medal detail for the modal: unit => {1:[{name,event}], 2:[], 3:[]}.
    $medalData = [];
    foreach (($unit_medals ?? []) as $unit => $list) {
      $medalData[$unit] = ['1' => [], '2' => [], '3' => []];
      foreach ($list as $m) {
        $rk = (string)(int)$m['rank'];
        if (isset($medalData[$unit][$rk])) {
          $medalData[$unit][$rk][] = ['name' => (string)$m['name'], 'event' => (string)$m['event']];
        }
      }
    }
  ?>
  <div class="modal fade" id="medalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title fw-semibold" id="medalModalTitle">Medals</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr><th style="width:48px">Sl.</th><th>Participant / Team</th><th>Name of Event</th><th style="width:90px">Position</th></tr>
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
    document.addEventListener('DOMContentLoaded', function () {
      var modalEl = document.getElementById('medalModal');
      var modal = modalEl ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
      document.querySelectorAll('.medal-cell').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var unit = btn.dataset.unit, rk = btn.dataset.rank;
          var list = (MEDAL_DATA[unit] && MEDAL_DATA[unit][rk]) ? MEDAL_DATA[unit][rk] : [];
          document.getElementById('medalModalTitle').textContent = MEDAL_LBL[rk] + ' — ' + unit + ' (' + list.length + ')';
          document.getElementById('medalModalBody').innerHTML = list.length
            ? list.map(function (m, i) {
                return '<tr><td class="text-center">' + (i + 1) + '</td><td>' + medalEsc(m.name)
                     + '</td><td>' + medalEsc(m.event) + '</td><td>' + MEDAL_POS[rk] + '</td></tr>';
              }).join('')
            : '<tr><td colspan="4" class="text-center text-muted py-3">No entries.</td></tr>';
          if (modal) modal.show();
        });
      });
    });
  </script>
<?php endif; ?>
