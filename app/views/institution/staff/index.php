<?php $pageTitle = 'Staff Management'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i>Staff Management</h5>
  <a href="/institution/staff/create" class="btn btn-primary">
    <i class="bi bi-person-plus me-2"></i>Add Staff
  </a>
</div>

<?php if (empty($staff_list)): ?>
<div class="sms-empty-state">
  <i class="bi bi-people"></i>
  <h5>No Staff Added</h5>
  <p>Add staff members to assign them event management roles.</p>
  <a href="/institution/staff/create" class="btn btn-primary">Add Staff Member</a>
</div>
<?php else: ?>
<div class="sms-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Mobile</th>
          <th>Roles</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($staff_list as $s): ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="sms-avatar sms-avatar-sm"><?= avatarInitials($s['name']) ?></div>
              <span class="fw-medium"><?= e($s['name']) ?></span>
            </div>
          </td>
          <td class="text-muted"><?= e($s['email']) ?></td>
          <td class="text-muted"><?= e($s['mobile']) ?></td>
          <td>
            <?php if ($s['roles']): ?>
              <?php foreach (explode(', ', $s['roles']) as $role): ?>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1"><?= e($role) ?></span>
              <?php endforeach; ?>
            <?php else: ?>
              <span class="text-muted small">No roles assigned</span>
            <?php endif; ?>
          </td>
          <td><?= statusBadge($s['status']) ?></td>
          <td>
            <a href="/institution/staff/<?= $s['id'] ?>/edit" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-pencil"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
