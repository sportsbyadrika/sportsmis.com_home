<?php $pageTitle = 'All Registrations'; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-check me-2"></i>All Registrations</h5>
  <span class="text-muted small"><?= count($rows) ?> shown<?= count($rows) === 500 ? ' (capped at 500 — narrow the filters for older rows)' : '' ?></span>
</div>

<form method="GET" action="/admin/registrations" class="sms-card p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label small mb-1">Search</label>
      <input type="search" name="q" value="<?= e($q) ?>" class="form-control form-control-sm"
             placeholder="Athlete, event or institution name…">
    </div>
    <div class="col-md-4">
      <label class="form-label small mb-1">Event</label>
      <select name="event_id" class="form-select form-select-sm">
        <option value="0">All events</option>
        <?php foreach ($events_list as $ev): ?>
          <option value="<?= (int)$ev['id'] ?>" <?= (int)$event_id === (int)$ev['id'] ? 'selected' : '' ?>>
            <?= e($ev['name']) ?> <small>— <?= e($ev['institution_name']) ?></small>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label small mb-1">Club / Institution</label>
      <select name="institution_id" class="form-select form-select-sm">
        <option value="0">All institutions</option>
        <?php foreach ($institutions as $i): ?>
          <option value="<?= (int)$i['id'] ?>" <?= (int)$institution_id === (int)$i['id'] ? 'selected' : '' ?>>
            <?= e($i['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1">Created From</label>
      <input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm">
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1">Created To</label>
      <input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm">
    </div>
    <div class="col-md-6 d-flex gap-2">
      <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Apply Filters</button>
      <a href="/admin/registrations" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Reset</a>
    </div>
  </div>
</form>

<div class="sms-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Athlete</th>
          <th>Event</th>
          <th>Institution</th>
          <th class="text-end">Total</th>
          <th>Application</th>
          <th>Payment</th>
          <th>Created</th>
          <th>Submitted</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="9" class="text-muted text-center py-4">No registrations match the filters.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['athlete_name']) ?></td>
            <td class="small text-muted"><?= e($r['event_name']) ?></td>
            <td class="small text-muted"><?= e($r['institution_name']) ?></td>
            <td class="text-end"><?= !empty($r['total_amount']) ? '₹' . number_format((float)$r['total_amount'], 2) : '—' ?></td>
            <td><?= appStatusBadge($r['admin_review_status'] ?? null, $r['submitted_at'] ?? null) ?></td>
            <td><?= statusBadge($r['payment_status'] ?? 'pending') ?></td>
            <td class="text-muted small">
              <?= !empty($r['registered_at']) ? formatDate($r['registered_at'], 'd M Y') : '—' ?>
            </td>
            <td class="text-muted small">
              <?= !empty($r['submitted_at']) ? formatDate($r['submitted_at'], 'd M Y') : '<em>not submitted</em>' ?>
            </td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-outline-danger"
                      data-bs-toggle="modal" data-bs-target="#smsDeleteModal"
                      data-action="/admin/registrations/<?= (int)$r['id'] ?>/delete"
                      data-kind="registration"
                      data-name="#<?= (int)$r['id'] ?> — <?= e($r['athlete_name']) ?> · <?= e($r['event_name']) ?>"
                      data-warning="Removes its line items, payment transactions and uploaded proof files."
                      title="Delete registration">
                <i class="bi bi-trash"></i>
              </button>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/_delete-modal.php'; ?>
