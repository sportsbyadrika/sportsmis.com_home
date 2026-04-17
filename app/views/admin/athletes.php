<?php $pageTitle = 'Athletes'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i>Athletes</h5>
</div>

<ul class="nav nav-tabs mb-4">
  <li class="nav-item">
    <a class="nav-link active" href="#">
      Pending Registrations
      <?php if (count($pending_registrations)): ?>
        <span class="badge bg-danger ms-1"><?= count($pending_registrations) ?></span>
      <?php endif; ?>
    </a>
  </li>
</ul>

<?php if (empty($pending_registrations)): ?>
  <div class="sms-empty-state">
    <i class="bi bi-check-circle text-success"></i>
    <h5>All Clear!</h5>
    <p>No pending athlete registrations.</p>
  </div>
<?php else: ?>
<div class="sms-card mb-4">
  <div class="sms-card-header">
    <h6 class="mb-0 fw-semibold">Pending Athlete Verifications</h6>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th>Name</th><th>Gender</th><th>Mobile</th><th>Email</th><th>Provider</th><th>Submitted</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($pending_registrations as $r): ?>
        <tr>
          <td class="fw-medium"><?= e($r['name']) ?></td>
          <td class="text-muted"><?= ucfirst($r['gender']) ?></td>
          <td class="text-muted"><?= e($r['mobile']) ?></td>
          <td class="text-muted"><?= e($r['email']) ?></td>
          <td>
            <?php if ($r['auth_provider'] === 'google'): ?>
              <span class="badge bg-info-subtle text-info border border-info-subtle">Google</span>
            <?php else: ?>
              <span class="badge bg-secondary-subtle text-secondary">Email</span>
            <?php endif; ?>
          </td>
          <td class="text-muted small"><?= formatDate($r['created_at']) ?></td>
          <td class="d-flex gap-1">
            <form method="POST" action="/admin/athletes/<?= $r['id'] ?>/verify">
              <?= csrf() ?>
              <button class="btn btn-sm btn-success" onclick="return confirm('Verify and send credentials?')">
                <i class="bi bi-check-circle me-1"></i>Verify
              </button>
            </form>
            <form method="POST" action="/admin/athletes/<?= $r['id'] ?>/reject"
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

<!-- All Athletes -->
<div class="sms-card">
  <div class="sms-card-header">
    <h6 class="mb-0 fw-semibold">Registered Athletes</h6>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th>Name</th><th>Gender</th><th>Profile</th><th>Email</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($athletes as $a): ?>
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
          <td class="text-muted"><?= ucfirst($a['gender']) ?></td>
          <td><?= $a['profile_completed'] ? '<span class="badge bg-success">Complete</span>' : '<span class="badge bg-warning text-dark">Incomplete</span>' ?></td>
          <td class="text-muted"><?= e($a['email']) ?></td>
          <td><?= statusBadge($a['user_status']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
