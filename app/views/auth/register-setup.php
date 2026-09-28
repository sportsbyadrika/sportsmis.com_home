<?php $pageTitle = 'Finish setting up your account'; ?>

<div class="sms-signin mx-auto" style="max-width:420px">
  <div class="text-center mb-4">
    <h3 class="fw-bold mb-1">Finish setting up your account</h3>
    <p class="text-muted small mb-0">Email confirmed — add your details and a password.</p>
  </div>

  <div class="border rounded-3 shadow-sm bg-white p-4">
    <div class="alert alert-success d-flex align-items-center gap-2 mb-4 py-2">
      <i class="bi bi-check-circle-fill"></i>
      <span><strong>Email confirmed:</strong> <?= e($email) ?></span>
    </div>

    <form method="POST" action="/register/setup" novalidate>
      <?= csrf() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">

      <div class="mb-3">
        <label class="form-label fw-medium">Full Name <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-person"></i></span>
          <input type="text" name="name" value="<?= e(old('name')) ?>"
                 class="form-control <?= hasError('name') ?>" placeholder="Your full name" autofocus required>
        </div>
        <?= fieldError('name') ?>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-sm-6">
          <label class="form-label fw-medium">Mobile <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-phone"></i></span>
            <input type="tel" name="mobile" value="<?= e(old('mobile')) ?>"
                   class="form-control <?= hasError('mobile') ?>" placeholder="10-digit" maxlength="10" required>
          </div>
          <?= fieldError('mobile') ?>
        </div>
        <div class="col-sm-6">
          <label class="form-label fw-medium">Gender <span class="text-danger">*</span></label>
          <select name="gender" class="form-select <?= hasError('gender') ?>" required>
            <option value="">Select</option>
            <option value="male"   <?= old('gender') === 'male'   ? 'selected' : '' ?>>Male</option>
            <option value="female" <?= old('gender') === 'female' ? 'selected' : '' ?>>Female</option>
            <option value="other"  <?= old('gender') === 'other'  ? 'selected' : '' ?>>Other</option>
          </select>
          <?= fieldError('gender') ?>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-sm-6">
          <label class="form-label fw-medium">Password <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" name="password" class="form-control <?= hasError('password') ?>"
                   minlength="8" placeholder="Min. 8 characters" required>
          </div>
          <?= fieldError('password') ?>
        </div>
        <div class="col-sm-6">
          <label class="form-label fw-medium">Confirm Password <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
            <input type="password" name="password_confirmation" class="form-control" minlength="8" required>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
        <i class="bi bi-check-circle me-2"></i>Create account &amp; sign in
      </button>
    </form>
  </div>
</div>
