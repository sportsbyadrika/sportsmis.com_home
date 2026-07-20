<?php
$pageTitle = 'Event-wise Competitor List — ' . $event['name'];
$compLbl   = $comp_label ?? 'Comp. No.';
$fmtDob = function ($d) {
    $d = trim((string)$d);
    return ($d !== '' && ($ts = strtotime($d))) ? date('d M Y', $ts) : '';
};
$totalAthletes = 0;
foreach ($groups as $g) { $totalAthletes += count($g['athletes']); }
?>

<?= flashBag() ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-list-columns-reverse me-2"></i>Event-wise Competitor List</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
</div>

<p class="small text-muted mb-3">
  <i class="bi bi-info-circle me-1"></i>
  Pick an Event Category to list every approved athlete, grouped under each
  <strong>sport event</strong>.
</p>

<!-- ── Category picker ─────────────────────────────────────────── -->
<form method="GET" class="sms-card p-3 mb-3" action="/institution/events/<?= e($eventHash) ?>/reports/event-competitor-list">
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
           href="/institution/events/<?= e($eventHash) ?>/reports/event-competitor-list.csv?category=<?= urlencode($selected_category) ?>">
          <i class="bi bi-file-earmark-spreadsheet me-1"></i>Download CSV
        </a>
        <a class="btn btn-sm btn-outline-secondary"
           href="/institution/events/<?= e($eventHash) ?>/reports/event-competitor-list/print?category=<?= urlencode($selected_category) ?>"
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
<?php elseif (empty($groups)): ?>
  <div class="sms-card p-4 text-muted small text-center">
    No approved athletes are registered for <strong><?= e($selected_category) ?></strong>.
  </div>
<?php else: ?>
  <div class="d-flex align-items-center gap-2 mb-2">
    <strong><?= e($selected_category) ?></strong>
    <span class="badge bg-secondary-subtle text-secondary-emphasis">
      <?= count($groups) ?> event<?= count($groups) === 1 ? '' : 's' ?> · <?= $totalAthletes ?> entr<?= $totalAthletes === 1 ? 'y' : 'ies' ?>
    </span>
  </div>
  <?php foreach ($groups as $g): ?>
    <div class="sms-card p-3 mb-3">
      <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-2">
        <i class="bi bi-bullseye"></i>
        <strong><?= e($g['event_label']) ?></strong>
        <span class="badge bg-primary-subtle text-primary-emphasis ms-auto">
          <?= count($g['athletes']) ?> athlete<?= count($g['athletes']) === 1 ? '' : 's' ?>
        </span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:44px">#</th>
              <th style="width:90px">Unit Code</th>
              <th>Unit Name</th>
              <th style="width:90px"><?= e($compLbl) ?></th>
              <th>Name of Candidate</th>
              <th style="width:56px">Age</th>
              <th style="width:76px">Gender</th>
              <th style="width:90px">DOB</th>
              <th>Age Category</th>
              <th style="width:60px">Photo</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($g['athletes'] as $i => $a): ?>
              <tr>
                <td class="text-center"><?= $i + 1 ?></td>
                <td class="text-center"><?= e($a['unit_code']) ?: '<span class="text-muted">—</span>' ?></td>
                <td><?= e($a['unit_name_field']) ?: '<span class="text-muted">—</span>' ?></td>
                <td class="text-center fw-bold"><?= $a['competitor_no'] !== '' ? '#' . e($a['competitor_no']) : '<span class="text-muted">—</span>' ?></td>
                <td><?= e($a['athlete_name']) ?></td>
                <td class="text-center"><?= $a['age'] === '' ? '<span class="text-muted">—</span>' : e($a['age']) ?></td>
                <td class="text-center"><?= e($a['gender']) ?: '<span class="text-muted">—</span>' ?></td>
                <td class="text-center small"><?= e($fmtDob($a['dob'])) ?: '<span class="text-muted">—</span>' ?></td>
                <td class="small"><?= e($a['age_category']) ?: '<span class="text-muted">—</span>' ?></td>
                <td class="text-center">
                  <?php if (!empty($a['photo'])): ?>
                    <img src="<?= e($a['photo']) ?>" alt=""
                         style="width:36px;height:44px;object-fit:cover;border:1px solid #ccc;border-radius:3px">
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
