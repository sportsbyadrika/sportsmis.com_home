<?php $pageTitle = 'Qualified Athletes — ' . $event['name']; ?>

<?= flashBag() ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-patch-check me-2"></i>Qualified Athletes</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <div class="ms-auto d-flex gap-2 flex-wrap">
    <a class="btn btn-sm btn-outline-success"
       href="/institution/events/<?= e($eventHash) ?>/reports/qualified-athletes.csv">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i>Download CSV
    </a>
    <a class="btn btn-sm btn-outline-secondary"
       href="/institution/events/<?= e($eventHash) ?>/reports/qualified-athletes/print"
       target="_blank" rel="noopener">
      <i class="bi bi-printer me-1"></i>Print
    </a>
  </div>
</div>

<p class="small text-muted mb-3">
  <i class="bi bi-info-circle me-1"></i>
  Approved athletes whose total score met or exceeded the Minimum Qualifying Score (MQS)
  in at least one sport-event. The inner table lists every event each athlete qualified in.
</p>

<?php if (empty($athletes)): ?>
  <div class="sms-card p-4 text-muted small text-center">
    No athletes have qualified yet — either no MQS is configured on any sport-event, or no
    recorded score reaches the MQS.
  </div>
<?php else: ?>
  <div class="sms-card p-3 mb-3">
    <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-2">
      <strong>Qualified Athletes</strong>
      <span class="badge bg-secondary-subtle text-secondary-emphasis ms-auto">
        <?= count($athletes) ?> athlete<?= count($athletes) === 1 ? '' : 's' ?>
      </span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th rowspan="2" style="width:50px">Sl. No</th>
            <th rowspan="2" style="width:90px">Comp. No.</th>
            <th rowspan="2">Name</th>
            <th rowspan="2" style="width:80px">Gender</th>
            <th rowspan="2" style="width:55px">Age</th>
            <th rowspan="2">Age Category</th>
            <th rowspan="2">Unit</th>
            <th colspan="5" class="text-center">Qualified Events</th>
          </tr>
          <tr>
            <th style="width:36px">#</th>
            <th>Category</th>
            <th>Event</th>
            <th class="text-end" style="width:70px">MQS</th>
            <th class="text-end" style="width:90px">Total Score</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($athletes as $i => $a): $span = count($a['qualified']); ?>
            <?php foreach ($a['qualified'] as $j => $q): ?>
              <tr>
                <?php if ($j === 0): ?>
                  <td rowspan="<?= $span ?>" class="text-center"><?= $i + 1 ?></td>
                  <td rowspan="<?= $span ?>" class="text-center fw-bold"><?= $a['competitor_number'] !== '' ? '#' . e($a['competitor_number']) : '<span class="text-muted">—</span>' ?></td>
                  <td rowspan="<?= $span ?>"><?= e($a['athlete_name']) ?></td>
                  <td rowspan="<?= $span ?>" class="text-center"><?= e($a['gender']) ?: '<span class="text-muted">—</span>' ?></td>
                  <td rowspan="<?= $span ?>" class="text-center"><?= $a['age'] === '' ? '<span class="text-muted">—</span>' : e($a['age']) ?></td>
                  <td rowspan="<?= $span ?>" class="small"><?= e($a['age_category']) ?: '<span class="text-muted">—</span>' ?></td>
                  <td rowspan="<?= $span ?>" class="small text-muted"><?= e($a['unit_name']) ?: '<span class="text-muted">—</span>' ?></td>
                <?php endif; ?>
                <td class="text-center"><?= $j + 1 ?></td>
                <td class="small"><?= e($q['category_name']) ?></td>
                <td class="small"><?= e($q['event_label']) ?></td>
                <td class="text-end"><?= e($q['mqs']) ?></td>
                <td class="text-end fw-semibold"><?= e($q['total_score']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
