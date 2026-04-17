<?php $pageTitle = 'Edit Staff Member'; ?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="/institution/staff" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold">Edit Staff Member</h5>
</div>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="sms-card p-4">
      <form method="POST" action="/institution/staff/<?= $staff['id'] ?>/edit" novalidate>
        <?= csrf() ?>

        <div class="mb-3">
          <label class="form-label fw-medium">Full Name</label>
          <input type="text" name="name" value="<?= e(old('name', $staff['name'])) ?>"
                 class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-medium">Email</label>
          <input type="text" class="form-control" value="<?= e($staff['email']) ?>" disabled>
          <small class="text-muted">Email cannot be changed.</small>
        </div>
        <div class="mb-3">
          <label class="form-label fw-medium">Mobile</label>
          <input type="tel" name="mobile" value="<?= e(old('mobile', $staff['mobile'])) ?>"
                 class="form-control" maxlength="10">
        </div>
        <div class="mb-3">
          <label class="form-label fw-medium">Status</label>
          <select name="status" class="form-select">
            <option value="active"   <?= ($staff['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= ($staff['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>

        <div class="mb-4">
          <label class="form-label fw-medium">Roles</label>
          <div class="row g-2 mt-1">
            <?php foreach ($roles as $role): ?>
            <div class="col-md-6">
              <div class="form-check border rounded-3 px-3 py-2">
                <input class="form-check-input" type="checkbox"
                       name="roles[]" value="<?= $role['id'] ?>"
                       id="role_<?= $role['id'] ?>"
                       <?= in_array($role['id'], $assigned) ? 'checked' : '' ?>>
                <label class="form-check-label fw-medium" for="role_<?= $role['id'] ?>">
                  <?= e($role['name']) ?>
                </label>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="d-flex gap-2 justify-content-end">
          <a href="/institution/staff" class="btn btn-light">Cancel</a>
          <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-check-circle me-2"></i>Update Staff
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
