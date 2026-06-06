<?php $pageTitle = 'Event Category-wise Competitor List — ' . $event['name']; ?>

<?= flashBag() ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-collection me-2"></i>Event Category-wise Competitor List</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
</div>

<p class="small text-muted mb-3">
  <i class="bi bi-info-circle me-1"></i>
  Pick an Event Category to list every approved athlete registered for any
  sport-event under that category, grouped and sorted by Unit Code.
</p>

<!-- ── Category picker ─────────────────────────────────────────── -->
<form method="GET" class="sms-card p-3 mb-3" action="/institution/events/<?= e($eventHash) ?>/reports/category-competitor-list">
  <div class="row g-2 align-items-end">
    <div class="col-md-6">
      <label class="form-label small mb-1">Event Category <span class="text-danger">*</span></label>
      <select name="category" class="form-select form-select-sm" required>
        <option value="">— Select a category —</option>
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
      <?php if ($selected_category !== ''): ?>
        <a class="btn btn-sm btn-outline-success"
           href="/institution/events/<?= e($eventHash) ?>/reports/category-competitor-list.csv?category=<?= urlencode($selected_category) ?>">
          <i class="bi bi-file-earmark-spreadsheet me-1"></i>Download CSV
        </a>
        <a class="btn btn-sm btn-outline-secondary"
           href="/institution/events/<?= e($eventHash) ?>/reports/category-competitor-list/print?category=<?= urlencode($selected_category) ?>"
           target="_blank" rel="noopener">
          <i class="bi bi-printer me-1"></i>Print
        </a>
      <?php endif; ?>
    </div>
  </div>
</form>

<?php if ($selected_category === ''): ?>
  <div class="sms-card p-4 text-muted small">
    Select an Event Category above to load the list.
  </div>
<?php elseif (empty($athletes)): ?>
  <div class="sms-card p-4 text-muted small text-center">
    No approved athletes are registered for <strong><?= e($selected_category) ?></strong>.
  </div>
<?php else: ?>
  <div class="sms-card p-3 mb-3">
    <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-2">
      <strong><?= e($selected_category) ?></strong>
      <span class="badge bg-secondary-subtle text-secondary-emphasis ms-auto">
        <?= count($athletes) ?> athlete<?= count($athletes) === 1 ? '' : 's' ?>
      </span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:50px">Sl. No</th>
            <th>Unit Code</th>
            <th>Unit Name</th>
            <th>Name of Candidate</th>
            <th style="width:60px">Age</th>
            <th style="width:80px">Gender</th>
            <th>Events</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($athletes as $i => $a): ?>
            <tr>
              <td class="text-center"><?= $i + 1 ?></td>
              <td><?= e($a['unit_code']) ?: '<span class="text-muted">—</span>' ?></td>
              <td class="small text-muted"><?= e($a['unit_name_field']) ?: '<span class="text-muted">—</span>' ?></td>
              <td><?= e($a['athlete_name']) ?></td>
              <td class="text-center"><?= $a['age'] === '' ? '<span class="text-muted">—</span>' : e($a['age']) ?></td>
              <td class="text-center"><?= e($a['gender']) ?: '<span class="text-muted">—</span>' ?></td>
              <td class="small">
                <?php if (empty($a['events'])): ?>
                  <span class="text-muted">—</span>
                <?php else: ?>
                  <ol class="ps-3 mb-0" style="font-size:.85rem">
                    <?php foreach ($a['events'] as $ev): ?>
                      <li><?= e($ev) ?></li>
                    <?php endforeach; ?>
                  </ol>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
