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
          <?php $i = 0; foreach ($unit_tally as $u): $i++; ?>
            <tr>
              <td class="text-center fw-bold"><?= $i ?></td>
              <td><?= e($u['unit']) ?></td>
              <td class="text-center"><?= (int)$u['g'] ?></td>
              <td class="text-center"><?= (int)$u['s'] ?></td>
              <td class="text-center"><?= (int)$u['b'] ?></td>
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
              <td>
                <div class="fw-medium"><?= e($ev['sport_event']) ?><?php if ($ev['gender'] !== ''): ?> — <?= e(ucfirst($ev['gender'])) ?><?php endif; ?></div>
                <div class="small text-muted"><?= e($ev['category']) ?><?php if ($ev['age_name'] !== ''): ?> · <?= e($ev['age_name']) ?><?php endif; ?></div>
              </td>
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
<?php endif; ?>
