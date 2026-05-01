<?php $pageTitle = 'Athlete Registrations'; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-check me-2"></i>Athlete Registrations</h5>
</div>

<form method="GET" action="/institution/registrations" class="sms-card p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label small mb-1">Search</label>
      <input type="search" name="q" value="<?= e($q) ?>" class="form-control form-control-sm"
             placeholder="Athlete name, mobile, event…">
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1">Event</label>
      <select name="event_id" class="form-select form-select-sm">
        <option value="0">All events</option>
        <?php foreach ($events as $ev): ?>
          <option value="<?= (int)$ev['id'] ?>" <?= (int)$event_id === (int)$ev['id'] ? 'selected' : '' ?>>
            <?= e($ev['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1">Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">All</option>
        <option value="pending"     <?= $status==='pending'     ? 'selected' : '' ?>>Pending review</option>
        <option value="approved"    <?= $status==='approved'    ? 'selected' : '' ?>>Approved</option>
        <option value="rejected"    <?= $status==='rejected'    ? 'selected' : '' ?>>Rejected</option>
        <option value="returned"    <?= $status==='returned'    ? 'selected' : '' ?>>Returned</option>
        <option value="unsubmitted" <?= $status==='unsubmitted' ? 'selected' : '' ?>>Drafts (not submitted)</option>
      </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
      <a href="/institution/registrations" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
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
          <th>Unit</th>
          <th class="text-end">Items</th>
          <th class="text-end">Total</th>
          <th>Submitted</th>
          <th>Application</th>
          <th>Payment</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($registrations)): ?>
          <tr><td colspan="9" class="text-muted text-center py-4">No registrations match the filters.</td></tr>
        <?php else: foreach ($registrations as $reg): ?>
          <tr>
            <td>
              <div class="fw-medium"><?= e($reg['athlete_name']) ?></div>
              <small class="text-muted"><?= e($reg['mobile'] ?? '') ?></small>
            </td>
            <td class="text-muted small"><?= e($reg['event_name']) ?></td>
            <td class="text-muted small"><?= e($reg['unit_name'] ?? '—') ?></td>
            <td class="text-end"><?= (int)$reg['items_count'] ?></td>
            <td class="text-end fw-medium"><?= !empty($reg['total_amount']) ? '₹' . number_format((float)$reg['total_amount'], 2) : '—' ?></td>
            <td class="text-muted small"><?= $reg['submitted_at'] ? formatDate($reg['submitted_at'], 'd M Y') : '<em>not submitted</em>' ?></td>
            <td>
              <?php if (!empty($reg['admin_review_status'])): ?>
                <?= statusBadge($reg['admin_review_status']) ?>
              <?php else: ?>
                <span class="badge bg-secondary">Draft</span>
              <?php endif; ?>
            </td>
            <td>
              <?= statusBadge($reg['payment_status'] ?? 'pending') ?>
              <?php if ((int)$reg['pending_payments'] > 0): ?>
                <small class="d-block text-warning"><?= (int)$reg['pending_payments'] ?> txn pending</small>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <a href="/institution/registrations/<?= (int)$reg['id'] ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye"></i><span class="d-none d-lg-inline ms-1">View</span>
              </a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
