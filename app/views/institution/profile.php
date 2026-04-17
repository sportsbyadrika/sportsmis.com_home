<?php $pageTitle = 'Institution Profile'; ?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="/institution/dashboard" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-bold">Institution Profile</h5>
</div>

<form method="POST" action="/institution/profile" enctype="multipart/form-data" novalidate>
  <?= csrf() ?>

  <div class="row g-4">

    <!-- Left: Logo -->
    <div class="col-lg-3">
      <div class="sms-card text-center p-4">
        <div class="mb-3">
          <?php if ($institution['logo']): ?>
            <img src="<?= e($institution['logo']) ?>" alt="Logo"
                 class="rounded-3 mb-3" width="140" height="140" style="object-fit:contain;border:1px solid #e2e8f0">
          <?php else: ?>
            <div class="sms-avatar sms-avatar-xl mx-auto mb-3"><?= avatarInitials($institution['name'] ?? 'I') ?></div>
          <?php endif; ?>
        </div>
        <label class="form-label fw-medium">Institution Logo</label>
        <input type="file" name="logo" class="form-control form-control-sm <?= hasError('logo') ?>"
               accept="image/jpeg,image/png,image/webp">
        <?= fieldError('logo') ?>
        <small class="text-muted d-block mt-1">JPG/PNG/WebP · Max 2 MB</small>
      </div>
    </div>

    <!-- Right: Details -->
    <div class="col-lg-9">
      <div class="sms-card p-4">
        <h6 class="fw-semibold mb-3 border-bottom pb-2">Institution Details</h6>

        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label fw-medium">Institution Name <span class="text-danger">*</span></label>
            <input type="text" name="name" value="<?= e(old('name', $institution['name'] ?? '')) ?>"
                   class="form-control <?= hasError('name') ?>" required>
            <?= fieldError('name') ?>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-medium">Institution Type</label>
            <select name="type_id" class="form-select">
              <option value="">-- Select Type --</option>
              <?php foreach ($institution_types as $type): ?>
                <option value="<?= $type['id'] ?>"
                  <?= (old('type_id', $institution['type_id'] ?? '') == $type['id']) ? 'selected' : '' ?>>
                  <?= e($type['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-medium">Registration Number <span class="text-danger">*</span></label>
            <input type="text" name="reg_number" value="<?= e(old('reg_number', $institution['reg_number'] ?? '')) ?>"
                   class="form-control <?= hasError('reg_number') ?>"
                   placeholder="e.g. REG/2024/001" required>
            <?= fieldError('reg_number') ?>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-medium">Registration Certificate</label>
            <input type="file" name="reg_document" class="form-control <?= hasError('reg_document') ?>"
                   accept="image/jpeg,image/png,application/pdf">
            <?= fieldError('reg_document') ?>
            <?php if ($institution['reg_document']): ?>
              <small class="text-success"><i class="bi bi-check-circle me-1"></i>Document uploaded
                <a href="<?= e($institution['reg_document']) ?>" target="_blank" class="ms-1">View</a>
              </small>
            <?php endif; ?>
          </div>

          <div class="col-12">
            <label class="form-label fw-medium">Address <span class="text-danger">*</span></label>
            <textarea name="address" rows="3"
                      class="form-control <?= hasError('address') ?>"
                      required><?= e(old('address', $institution['address'] ?? '')) ?></textarea>
            <?= fieldError('address') ?>
          </div>
        </div>

        <div class="d-flex gap-2 mt-4 justify-content-end">
          <a href="/institution/dashboard" class="btn btn-light">Cancel</a>
          <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-check-circle me-2"></i>Save Profile
          </button>
        </div>
      </div>

      <!-- Validity Info -->
      <?php if ($institution['validity_from']): ?>
      <div class="sms-card p-3 mt-3 border-start border-4 border-success">
        <div class="d-flex align-items-center gap-3">
          <i class="bi bi-shield-check fs-4 text-success"></i>
          <div>
            <div class="fw-semibold">Institution Approved</div>
            <small class="text-muted">
              Valid from <strong><?= formatDate($institution['validity_from']) ?></strong>
              to <strong><?= formatDate($institution['validity_to']) ?></strong>
            </small>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>

  </div>
</form>
