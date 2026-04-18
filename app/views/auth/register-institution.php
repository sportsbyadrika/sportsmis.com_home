<?php $pageTitle = 'Register Institution'; ?>

<div class="border rounded-3 overflow-hidden shadow-sm">

  <!-- Panel header -->
  <div class="p-3 px-4" style="background:#f8fafc;border-bottom:1px solid #e2e8f0">
    <div class="d-flex align-items-center gap-2">
      <div style="width:36px;height:36px;border-radius:.5rem;background:#0b1f3a;display:flex;align-items:center;justify-content:center">
        <i class="bi bi-building text-warning"></i>
      </div>
      <div>
        <div class="fw-bold" style="font-size:1rem;line-height:1.2">Register Your Institution</div>
        <div class="text-muted" style="font-size:.8rem">Sports academies, clubs, schools &amp; federations</div>
      </div>
    </div>
  </div>

  <!-- Panel body -->
  <div class="p-4 bg-white">

    <form method="POST" action="/register/institution" novalidate>
      <?= csrf() ?>

      <div class="mb-3">
        <label class="form-label fw-medium">Institution / Club Name <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-building"></i></span>
          <input type="text" name="institution_name" value="<?= e(old('institution_name')) ?>"
                 class="form-control <?= hasError('institution_name') ?>"
                 placeholder="e.g. Kerala State Sports Council" required autofocus>
        </div>
        <?= fieldError('institution_name') ?>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-sm-7">
          <label class="form-label fw-medium">SPOC Name <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" name="spoc_name" value="<?= e(old('spoc_name')) ?>"
                   class="form-control <?= hasError('spoc_name') ?>"
                   placeholder="Contact person name" required>
          </div>
          <?= fieldError('spoc_name') ?>
        </div>
        <div class="col-sm-5">
          <label class="form-label fw-medium">Mobile <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-phone"></i></span>
            <input type="tel" name="spoc_mobile" value="<?= e(old('spoc_mobile')) ?>"
                   class="form-control <?= hasError('spoc_mobile') ?>"
                   placeholder="10-digit" maxlength="10" required>
          </div>
          <?= fieldError('spoc_mobile') ?>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-medium">
          Email Address <span class="text-danger">*</span>
          <small class="text-muted fw-normal">(used as login username)</small>
        </label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input type="email" name="email" value="<?= e(old('email')) ?>"
                 class="form-control <?= hasError('email') ?>"
                 placeholder="admin@yourinstitution.com" required>
        </div>
        <?= fieldError('email') ?>
      </div>

      <div class="mb-4">
        <label class="form-label fw-medium">Address <span class="text-danger">*</span></label>
        <textarea name="address" rows="3"
                  class="form-control <?= hasError('address') ?>"
                  placeholder="Full address of the institution" required><?= e(old('address')) ?></textarea>
        <?= fieldError('address') ?>
      </div>

      <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
        <i class="bi bi-send me-2"></i>Submit Registration
      </button>
    </form>

    <p class="text-center text-muted small mt-3 mb-0">
      Already have an account? <a href="/login" class="fw-medium">Sign in</a>
    </p>

  </div><!-- /.panel body -->
</div><!-- /.border.rounded-3 -->
