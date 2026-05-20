<?php
$pageTitle = 'Scoring — Lanes';
$st = $relay['result_status'] ?: 'pending';
[$stLabel, $stCls] = $statuses[$st] ?? ['—','bg-secondary'];
$catAbbr = function ($name) {
  return $name ? $name : '';
};
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/scoring" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-list-ol me-2"></i>Relay <?= e($relay['relay_number']) ?></h5>
  <span class="badge <?= e($stCls) ?>"><?= e($stLabel) ?></span>
  <a href="/event-staff/scoring/relays/<?= (int)$relay['id'] ?>/print" target="_blank"
     class="btn btn-sm btn-outline-success ms-auto">
    <i class="bi bi-printer me-1"></i>Print Report
  </a>
</div>

<!-- Panel 1 — Relay Details -->
<div class="sms-card p-3 mb-3">
  <h6 class="fw-semibold border-bottom pb-2 mb-2">Relay Details</h6>
  <div class="row g-2 small">
    <div class="col-md-3"><span class="text-muted">Order No.</span><br><strong><?= (int)($relay['order_no'] ?? 0) ?></strong></div>
    <div class="col-md-3"><span class="text-muted">Relay No.</span><br><strong><?= e($relay['relay_number']) ?></strong></div>
    <div class="col-md-3"><span class="text-muted">Date / Time</span><br>
      <?= !empty($relay['relay_date']) ? e(formatDate($relay['relay_date'], 'd M Y')) : '—' ?>
      <?php if (!empty($relay['match_time'])): ?> · <?= e(substr($relay['match_time'],0,5)) ?><?php endif; ?>
    </div>
    <div class="col-md-3"><span class="text-muted">Shooting Range</span><br>
      <?= e(($relay['venue_name'] ?? '') . ' → ' . ($relay['range_name'] ?? '')) ?>
    </div>
    <div class="col-md-3"><span class="text-muted">Event</span><br>
      <?= e($event['name']) ?> · <code><?= e($event['event_code'] ?? '') ?></code>
    </div>
    <div class="col-md-3"><span class="text-muted">Status</span><br>
      <span class="badge <?= e($stCls) ?>"><?= e($stLabel) ?></span>
    </div>
  </div>
</div>

<!-- Panel 2 — Lanes -->
<div class="sms-card p-3 mb-3">
  <h6 class="fw-semibold border-bottom pb-2 mb-2">Lanes (<?= count($lanes) ?>)</h6>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Lane</th><th>Type</th><th>Category</th>
          <th>Assigned Unit</th><th>Allocated Athlete</th>
          <th>Score Status</th><th class="text-end">Grand Total</th>
          <th class="text-end"></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($lanes)): ?>
          <tr><td colspan="8" class="text-muted text-center py-3">No lanes on this relay.</td></tr>
        <?php else: foreach ($lanes as $l):
          $scoreSt = $l['score_status'] ?? null;
          $scoreLabel = [
            'final'       => ['Final',       'bg-success'],
            'saved'       => ['Saved',       'bg-info-subtle text-info-emphasis'],
            'in_progress' => ['In Progress', 'bg-warning text-dark'],
          ][$scoreSt] ?? ['Not Started', 'bg-secondary'];
        ?>
          <tr>
            <td>Lane <strong><?= (int)$l['lane_number'] ?></strong></td>
            <td class="small"><?= e(ucfirst((string)$l['lane_type'])) ?></td>
            <td class="small"><?= e($l['category'] ?: ($l['default_category'] ?: '—')) ?></td>
            <td class="small"><?= e($l['unit_name'] ?: '—') ?></td>
            <td class="small">
              <?php if (!empty($l['athlete_name'])): ?>
                <code>#<?= (int)$l['competitor_number'] ?></code> <?= e($l['athlete_name']) ?>
              <?php elseif (!empty($l['score_competitor_number'])): ?>
                <span class="text-muted">via score: </span>
                <code>#<?= (int)$l['score_competitor_number'] ?></code>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td><span class="badge <?= e($scoreLabel[1]) ?>"><?= e($scoreLabel[0]) ?></span></td>
            <td class="text-end fw-bold">
              <?= $l['score_total'] !== null ? number_format((float)$l['score_total'], 2) : '<span class="text-muted">—</span>' ?>
            </td>
            <td class="text-end text-nowrap">
              <a href="/event-staff/scoring/relays/<?= (int)$relay['id'] ?>/lanes/<?= (int)$l['lane_id'] ?>"
                 class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
              <a href="/event-staff/scoring/relays/<?= (int)$relay['id'] ?>/lanes/<?= (int)$l['lane_id'] ?>?view=1"
                 class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye me-1"></i>View</a>
              <a href="/event-staff/scoring/relays/<?= (int)$relay['id'] ?>/lanes/<?= (int)$l['lane_id'] ?>/sheet"
                 target="_blank" class="btn btn-sm btn-outline-success" title="Print Score Sheet">
                <i class="bi bi-printer"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
