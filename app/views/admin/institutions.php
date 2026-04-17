<?php $pageTitle = 'Institutions'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h5 class="mb-0 fw-bold"><i class="bi bi-building me-2"></i>Institutions</h5>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4">
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'pending' ? 'active' : '' ?>" href="?tab=pending">
      Pending Registrations
      <?php if (count($pending_registrations)): ?>
        <span class="badge bg-danger ms-1"><?= count($pending_registrations) ?></span>
      <?php endif; ?>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'all' ? 'active' : '' ?>" href="?tab=all">All Institutions</a>
  </li>
</ul>

<?php if ($tab === 'pending'): ?>
  <?php if (empty($pending_registrations)): ?>
    <div class="sms-empty-state">
      <i class="bi bi-check-circle text-success"></i>
      <h5>All Clear!</h5>
      <p>No pending institution registrations.</p>
    </div>
  <?php else: ?>
  <div class="sms-card">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr><th>Institution</th><th>SPOC</th><th>Mobile</th><th>Email</th><th>Submitted</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php foreach ($pending_registrations as $r): ?>
          <tr>
            <td class="fw-medium"><?= e($r['institution_name']) ?></td>
            <td><?= e($r['spoc_name']) ?></td>
            <td class="text-muted"><?= e($r['spoc_mobile']) ?></td>
            <td class="text-muted"><?= e($r['email']) ?></td>
            <td class="text-muted small"><?= formatDate($r['created_at']) ?></td>
            <td class="d-flex gap-1">
              <a href="/admin/institutions/<?= $r['id'] ?>" class="btn btn-sm btn-primary">Review</a>
              <form method="POST" action="/admin/institutions/<?= $r['id'] ?>/reject"
                    onsubmit="return confirm('Reject this registration?')">
                <?= csrf() ?>
                <button class="btn btn-sm btn-outline-danger">Reject</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

<?php else: ?>
  <div class="sms-card">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr><th>Institution</th><th>Type</th><th>Email</th><th>Valid Till</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($institutions as $inst): ?>
          <tr>
            <td>
              <div class="fw-medium"><?= e($inst['name']) ?></div>
              <small class="text-muted"><?= e($inst['address'] ?? '') ?></small>
            </td>
            <td class="text-muted"><?= e($inst['type_name'] ?? '—') ?></td>
            <td class="text-muted"><?= e($inst['email']) ?></td>
            <td class="text-muted small"><?= formatDate($inst['validity_to']) ?></td>
            <td><?= statusBadge($inst['status']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
