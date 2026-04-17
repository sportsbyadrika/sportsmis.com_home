<?php $pageTitle = 'Add Staff Member'; ?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="/institution/staff" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold">Add Staff Member</h5>
</div>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="sms-card p-4">
      <form method="POST" action="/institution/staff/create" novalidate>
        <?= csrf() ?>

        <div class="mb-3">
          <label class="form-label fw-medium">Full Name <span class="text-danger">*</span></label>
          <input type="text" name="name" value="<?= e(old('name')) ?>"
                 class="form-control <?= hasError('name') ?>" required>
          <?= fieldError('name') ?>
        </div>

        <div class="mb-3">
          <label class="form-label fw-medium">Email Address <span class="text-danger">*</span>
            <small class="text-muted fw-normal">(login username)</small>
          </label>
          <input type="email" name="email" value="<?= e(old('email')) ?>"
                 class="form-control <?= hasError('email') ?>" required>
          <?= fieldError('email') ?>
        </div>

        <div class="mb-4">
          <label class="form-label fw-medium">Mobile <span class="text-danger">*</span></label>
          <input type="tel" name="mobile" value="<?= e(old('mobile')) ?>"
                 class="form-control <?= hasError('mobile') ?>"
                 placeholder="10-digit mobile number" maxlength="10" required>
          <?= fieldError('mobile') ?>
        </div>

        <div class="mb-4">
          <label class="form-label fw-medium">Assign Roles <span class="text-danger">*</span></label>
          <?= fieldError('roles') ?>
          <div class="row g-2 mt-1">
            <?php foreach ($roles as $role): ?>
            <div class="col-md-6">
              <div class="form-check border rounded-3 px-3 py-2">
                <input class="form-check-input" type="checkbox"
                       name="roles[]" value="<?= $role['id'] ?>"
                       id="role_<?= $role['id'] ?>">
                <label class="form-check-label" for="role_<?= $role['id'] ?>">
                  <span class="fw-medium"><?= e($role['name']) ?></span>
                  <?php if ($role['description']): ?>
                    <small class="text-muted d-block"><?= e($role['description']) ?></small>
                  <?php endif; ?>
                </label>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="alert alert-info d-flex gap-2 align-items-start small">
          <i class="bi bi-info-circle mt-1 flex-shrink-0"></i>
          <span>A temporary password will be generated and sent to the staff member's email.</span>
        </div>

        <div class="d-flex gap-2 justify-content-end">
          <a href="/institution/staff" class="btn btn-light">Cancel</a>
          <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-person-plus me-2"></i>Add Staff Member
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
