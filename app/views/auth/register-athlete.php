<?php
$pageTitle    = 'Register as Athlete';
$viaGoogle    = !empty($google_data);
$prefillName  = old('name', $google_data['name'] ?? '');
$prefillEmail = $viaGoogle ? $google_data['email'] : old('email', '');
?>

<div class="border rounded-3 overflow-hidden shadow-sm">

  <!-- Panel header -->
  <div class="p-3 px-4" style="background:#f8fafc;border-bottom:1px solid #e2e8f0">
    <div class="d-flex align-items-center gap-2">
      <div style="width:36px;height:36px;border-radius:.5rem;background:#0b1f3a;display:flex;align-items:center;justify-content:center">
        <i class="bi bi-person-running text-warning"></i>
      </div>
      <div>
        <div class="fw-bold" style="font-size:1rem;line-height:1.2">Register as Athlete</div>
        <div class="text-muted" style="font-size:.8rem">Create your SportsMIS athlete account</div>
      </div>
    </div>
  </div>

  <!-- Panel body -->
  <div class="p-4 bg-white">

    <?php if ($viaGoogle): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 mb-4 py-2">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48" class="flex-shrink-0">
        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
      </svg>
      <span>
        <strong>Google account verified.</strong>
        Your email is confirmed — just fill in the remaining details below.
      </span>
    </div>
    <?php else: ?>
    <a href="/auth/google?tab=athlete" class="btn btn-outline-danger w-100 py-2 fw-medium mb-3">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48" class="me-2" style="vertical-align:-.2em">
        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
      </svg>
      Continue with Google
    </a>

    <div class="d-flex align-items-center mb-3">
      <hr class="flex-grow-1 m-0">
      <span class="px-3 text-muted small">or register with email</span>
      <hr class="flex-grow-1 m-0">
    </div>
    <?php endif; ?>

    <form method="POST" action="/register/athlete" novalidate>
      <?= csrf() ?>

      <div class="mb-3">
        <label class="form-label fw-medium">Full Name <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-person"></i></span>
          <input type="text" name="name" value="<?= e($prefillName) ?>"
                 class="form-control <?= hasError('name') ?>"
                 placeholder="Your full name" required <?= $viaGoogle ? '' : 'autofocus' ?>>
        </div>
        <?= fieldError('name') ?>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-sm-6">
          <label class="form-label fw-medium">Mobile <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-phone"></i></span>
            <input type="tel" name="mobile" value="<?= e(old('mobile')) ?>"
                   class="form-control <?= hasError('mobile') ?>"
                   placeholder="10-digit" maxlength="10" required
                   <?= $viaGoogle ? 'autofocus' : '' ?>>
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

      <div class="mb-4">
        <label class="form-label fw-medium">
          Email Address <span class="text-danger">*</span>
          <small class="text-muted fw-normal">(used as login username)</small>
        </label>
        <div class="input-group">
          <span class="input-group-text">
            <i class="bi <?= $viaGoogle ? 'bi-google text-danger' : 'bi-envelope' ?>"></i>
          </span>
          <input type="email" name="email" value="<?= e($prefillEmail) ?>"
                 class="form-control <?= hasError('email') ?>"
                 placeholder="you@example.com"
                 <?= $viaGoogle ? 'readonly' : '' ?> required>
          <?php if ($viaGoogle): ?>
            <span class="input-group-text text-success"><i class="bi bi-check-circle-fill"></i></span>
          <?php endif; ?>
        </div>
        <?= fieldError('email') ?>
        <?php if ($viaGoogle): ?>
          <small class="text-muted">Verified via Google — cannot be changed here.</small>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
        <?php if ($viaGoogle): ?>
          <i class="bi bi-check-circle me-2"></i>Complete Registration
        <?php else: ?>
          <i class="bi bi-send me-2"></i>Submit Registration
        <?php endif; ?>
      </button>
    </form>

    <p class="text-center text-muted small mt-3 mb-0">
      Already have an account? <a href="/login" class="fw-medium">Sign in</a>
    </p>

  </div><!-- /.panel body -->
</div><!-- /.border.rounded-3 -->
