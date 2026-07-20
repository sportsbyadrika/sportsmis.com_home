<?php
$pageTitle = 'Event-wise Participants Count — ' . $event['name'];
$totSub = 0; $totApp = 0;
foreach ($rows as $r) { $totSub += (int)$r['submitted']; $totApp += (int)$r['approved']; }
?>

<?= flashBag() ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-bar-chart-line me-2"></i>Event-wise Participants Count</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
</div>

<p class="small text-muted mb-3">
  <i class="bi bi-info-circle me-1"></i>
  Participants in each sport event — <strong>Submitted</strong> (submitted for
  review, i.e. pending + approved) and <strong>Approved</strong>. Drafts are
  excluded. Optionally filter to a single Event Category — the default lists all.
</p>

<!-- ── Category picker ─────────────────────────────────────────── -->
<form method="GET" class="sms-card p-3 mb-3" action="/institution/events/<?= e($eventHash) ?>/reports/participants-count">
  <div class="row g-2 align-items-end">
    <div class="col-md-6">
      <label class="form-label small mb-1">Event Category</label>
      <select name="category" class="form-select form-select-sm">
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= e($c['name']) ?>"
                  <?= $selected_category === $c['name'] ? 'selected' : '' ?>>
            <?= e($c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6 d-flex gap-2 flex-wrap">
      <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Show</button>
      <a class="btn btn-sm btn-outline-success"
         href="/institution/events/<?= e($eventHash) ?>/reports/participants-count.csv?category=<?= urlencode($selected_category) ?>">
        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Download CSV
      </a>
      <a class="btn btn-sm btn-outline-secondary"
         href="/institution/events/<?= e($eventHash) ?>/reports/participants-count/print?category=<?= urlencode($selected_category) ?>"
         target="_blank" rel="noopener">
        <i class="bi bi-printer me-1"></i>Print
      </a>
    </div>
  </div>
</form>

<?php if (empty($rows)): ?>
  <div class="sms-card p-4 text-muted small text-center">
    No approved participants
    <?= $selected_category !== '' ? 'for <strong>' . e($selected_category) . '</strong>' : 'yet' ?>.
  </div>
<?php else: ?>
  <div class="sms-card p-3 mb-3">
    <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-2">
      <strong><?= $selected_category !== '' ? e($selected_category) : 'All categories' ?></strong>
      <span class="badge bg-secondary-subtle text-secondary-emphasis ms-auto">
        <?= count($rows) ?> sport event<?= count($rows) === 1 ? '' : 's' ?> · <?= $totSub ?> submitted · <?= $totApp ?> approved
      </span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:60px">Sl. No</th>
            <th>Event Category</th>
            <th>Sport Event</th>
            <th class="text-end" style="width:120px">Submitted</th>
            <th class="text-end" style="width:120px">Approved</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $i => $r): ?>
            <tr>
              <td class="text-center"><?= $i + 1 ?></td>
              <td><?= e($r['category_name']) ?: '<span class="text-muted">—</span>' ?></td>
              <td><?= e($r['sport_event']) ?></td>
              <td class="text-end fw-bold text-warning-emphasis"><?= (int)$r['submitted'] ?></td>
              <td class="text-end fw-bold text-success"><?= (int)$r['approved'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="table-light">
            <th colspan="3" class="text-end">Total</th>
            <th class="text-end"><?= $totSub ?></th>
            <th class="text-end"><?= $totApp ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
<?php endif; ?>
