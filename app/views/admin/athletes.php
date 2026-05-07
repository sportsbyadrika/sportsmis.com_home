<?php
$pageTitle = 'Athletes';
$f = $filters;
$pivotTotals = ['male'=>0,'female'=>0,'other'=>0,'total'=>0];
foreach ($state_pivot as $row) {
  $pivotTotals['male']   += (int)$row['male'];
  $pivotTotals['female'] += (int)$row['female'];
  $pivotTotals['other']  += (int)$row['other'];
  $pivotTotals['total']  += (int)$row['total'];
}
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i>Athletes</h5>
  <span class="text-muted small"><?= count($athletes) ?> shown<?= count($athletes) === 1000 ? ' (capped at 1000 — narrow filters for older rows)' : '' ?></span>
</div>

<!-- ─ State × Gender pivot ─ -->
<div class="sms-card p-3 mb-4">
  <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-geo me-2"></i>Athletes by State × Gender</h6>
  <?php if (empty($state_pivot)): ?>
    <p class="text-muted small mb-0">No athletes registered yet.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle mb-0">
        <thead class="table-light text-center">
          <tr><th class="text-start">State</th><th>Men</th><th>Women</th><th>Other</th><th>Total</th></tr>
        </thead>
        <tbody class="text-center">
          <?php foreach ($state_pivot as $row): ?>
            <tr>
              <td class="text-start"><?= e($row['state_name']) ?></td>
              <td><?= (int)$row['male']   > 0 ? (int)$row['male']   : '<span class="text-muted">·</span>' ?></td>
              <td><?= (int)$row['female'] > 0 ? (int)$row['female'] : '<span class="text-muted">·</span>' ?></td>
              <td><?= (int)$row['other']  > 0 ? (int)$row['other']  : '<span class="text-muted">·</span>' ?></td>
              <td class="fw-bold"><?= (int)$row['total'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light text-center">
          <tr>
            <th class="text-end">Grand Total</th>
            <th><?= $pivotTotals['male'] ?></th>
            <th><?= $pivotTotals['female'] ?></th>
            <th><?= $pivotTotals['other'] ?></th>
            <th><?= $pivotTotals['total'] ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ─ Search filters ─ -->
<form method="GET" action="/admin/athletes" class="sms-card p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small mb-1">Name</label>
      <input type="search" name="q" value="<?= e($f['q']) ?>" class="form-control form-control-sm" placeholder="Athlete name…">
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1">Email</label>
      <input type="search" name="email" value="<?= e($f['email']) ?>" class="form-control form-control-sm" placeholder="email substring…">
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1">Mobile</label>
      <input type="search" name="mobile" value="<?= e($f['mobile']) ?>" class="form-control form-control-sm" placeholder="10-digit…">
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1">WhatsApp</label>
      <input type="search" name="whatsapp" value="<?= e($f['whatsapp']) ?>" class="form-control form-control-sm" placeholder="10-digit…">
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1">Address</label>
      <input type="search" name="address" value="<?= e($f['address']) ?>" class="form-control form-control-sm" placeholder="city / locality…">
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1">Profile Status</label>
      <select name="profile" class="form-select form-select-sm">
        <option value="">All</option>
        <option value="complete"   <?= $f['profile']==='complete'   ? 'selected' : '' ?>>Complete</option>
        <option value="incomplete" <?= $f['profile']==='incomplete' ? 'selected' : '' ?>>Incomplete</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1">Account Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">All</option>
        <option value="active"    <?= $f['status']==='active'    ? 'selected' : '' ?>>Active</option>
        <option value="pending"   <?= $f['status']==='pending'   ? 'selected' : '' ?>>Pending</option>
        <option value="blocked"   <?= $f['status']==='blocked'   ? 'selected' : '' ?>>Blocked</option>
        <option value="suspended" <?= $f['status']==='suspended' ? 'selected' : '' ?>>Suspended</option>
      </select>
    </div>
    <div class="col-md-6 d-flex gap-2">
      <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Apply Filters</button>
      <a href="/admin/athletes" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Reset</a>
    </div>
  </div>
</form>

<!-- ─ Registered athletes table ─ -->
<div class="sms-card">
  <div class="sms-card-header">
    <h6 class="mb-0 fw-semibold">Registered Athletes</h6>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Name</th>
          <th>Gender</th>
          <th>Mobile</th>
          <th>State</th>
          <th>District</th>
          <th>Email</th>
          <th>Profile</th>
          <th>Status</th>
          <th>Created</th>
          <th>Submitted</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($athletes)): ?>
          <tr><td colspan="11" class="text-muted text-center py-4">No athletes match the filters.</td></tr>
        <?php else: foreach ($athletes as $a): ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <?php if ($a['passport_photo']): ?>
                <img src="<?= e($a['passport_photo']) ?>" class="rounded-circle" width="32" height="32" style="object-fit:cover">
              <?php else: ?>
                <div class="sms-avatar sms-avatar-sm"><?= avatarInitials($a['name']) ?></div>
              <?php endif; ?>
              <span class="fw-medium"><?= e($a['name']) ?></span>
            </div>
          </td>
          <td class="text-muted"><?= ucfirst($a['gender'] ?? '') ?></td>
          <td class="text-muted small"><?= e($a['mobile'] ?? '—') ?></td>
          <td class="text-muted small"><?= e($a['state_name']    ?? '—') ?></td>
          <td class="text-muted small"><?= e($a['district_name'] ?? '—') ?></td>
          <td class="text-muted small"><?= e($a['email']) ?></td>
          <td><?= $a['profile_completed'] ? '<span class="badge bg-success">Complete</span>' : '<span class="badge bg-warning text-dark">Incomplete</span>' ?></td>
          <td><?= statusBadge($a['user_status']) ?></td>
          <td class="text-muted small"><?= !empty($a['created_at']) ? formatDate($a['created_at'], 'd M Y') : '—' ?></td>
          <td class="text-muted small"><?= !empty($a['submitted_at']) ? formatDate($a['submitted_at'], 'd M Y') : '—' ?></td>
          <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger"
                    data-bs-toggle="modal" data-bs-target="#smsDeleteModal"
                    data-action="/admin/athletes/<?= (int)$a['id'] ?>/delete"
                    data-kind="athlete"
                    data-name="<?= e($a['name']) ?>"
                    data-warning="Removes the athlete profile, login account, every event registration, payment transactions, and uploaded files (photo, ID proof, DOB proof, NOC letters, transaction proofs)."
                    title="Delete athlete profile (cascade)">
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
