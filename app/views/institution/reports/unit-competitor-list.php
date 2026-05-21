<?php $pageTitle = 'Unit-wise Competitor List — ' . $event['name']; ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-buildings me-2"></i>Unit-wise Competitor List</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <button class="btn btn-sm btn-outline-secondary ms-auto" type="button" onclick="window.print()">
    <i class="bi bi-printer me-1"></i>Print
  </button>
</div>

<p class="small text-muted mb-3">
  <i class="bi bi-info-circle me-1"></i>
  One row per (athlete, event category). An athlete registered for multiple
  categories appears on multiple rows; the events column lists every event the
  athlete is registered for in that category (event code + label).
</p>

<?php if (empty($units)): ?>
  <div class="sms-card p-4 text-center text-muted">No approved competitors yet.</div>
<?php else: ?>
  <?php foreach ($units as $u): ?>
    <div class="sms-card p-3 mb-3">
      <div class="d-flex align-items-center gap-3 mb-3 border-bottom pb-2">
        <?php if (!empty($u['unit_logo'])): ?>
          <img src="<?= e($u['unit_logo']) ?>" alt="" width="48" height="48"
               class="rounded flex-shrink-0" style="object-fit:cover;border:1px solid #e2e8f0;background:#fff">
        <?php else: ?>
          <div class="rounded flex-shrink-0 d-flex align-items-center justify-content-center bg-light text-muted"
               style="width:48px;height:48px;border:1px solid #e2e8f0">
            <i class="bi bi-building"></i>
          </div>
        <?php endif; ?>
        <div class="min-w-0">
          <div class="fw-bold"><?= e($u['unit_name']) ?></div>
          <?php if (!empty($u['unit_address'])): ?>
            <div class="small text-muted"><?= e($u['unit_address']) ?></div>
          <?php endif; ?>
        </div>
        <div class="ms-auto small text-muted">
          <?= count($u['rows']) ?> row<?= count($u['rows']) === 1 ? '' : 's' ?>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:50px">Sl. No</th>
              <th style="width:100px">Comp. No.</th>
              <th>Athlete Name</th>
              <th style="width:70px">Age</th>
              <th style="width:90px">Gender</th>
              <th>Event Category</th>
              <th>Events</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($u['rows'] as $i => $r): ?>
              <tr>
                <td class="text-center"><?= $i + 1 ?></td>
                <td class="text-center fw-bold">
                  <?= $r['competitor_number']
                        ? '#' . str_pad((string)(int)$r['competitor_number'], 4, '0', STR_PAD_LEFT)
                        : '—' ?>
                </td>
                <td><?= e($r['athlete_name']) ?></td>
                <td class="text-center"><?= e($r['age']) ?></td>
                <td class="text-center"><?= e($r['gender']) ?></td>
                <td><?= e($r['category_name']) ?></td>
                <td class="small">
                  <?= e(implode(', ', $r['events'])) ?: '<span class="text-muted">—</span>' ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
