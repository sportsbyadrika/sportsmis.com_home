<?php
$pageTitle   = 'Register as Athlete';
$viaGoogle   = !empty($google_data);
$prefillName = old('name', $google_data['name'] ?? '');
$prefillEmail= $viaGoogle ? $google_data['email'] : old('email', '');
?>

<h3 class="fw-bold mb-1">Athlete Registration</h3>
<p class="text-muted mb-4">Create your SportsMIS athlete account</p>

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
<?php endif; ?>

<form method="POST" action="/register/athlete" novalidate>
  <?= csrf() ?>

  <div class="mb-3">
    <label class="form-label fw-medium">Full Name <span class="text-danger">*</span></label>
    <div class="input-group">
      <span class="input-group-text"><i class="bi bi-person"></i></span>
      <input type="text" name="name" value="<?= e($prefillName) ?>"
             class="form-control <?= hasError('name') ?>"
             placeholder="Your full name" required>
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
               placeholder="10-digit" maxlength="10" required>
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

  <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mb-3">
    <?php if ($viaGoogle): ?>
      <i class="bi bi-check-circle me-2"></i>Complete Registration
    <?php else: ?>
      <i class="bi bi-send me-2"></i>Submit Registration
    <?php endif; ?>
  </button>
</form>

<?php if (!$viaGoogle): ?>
<div class="text-center">
  <div class="sms-divider-text">or sign up with</div>
  <a href="/auth/google" class="btn btn-outline-secondary w-100 mt-2">
    <svg width="18" height="18" viewBox="0 0 18 18" class="me-2" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.874 2.684-6.615z" fill="#4285F4"/>
      <path d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z" fill="#34A853"/>
      <path d="M3.964 10.706A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.706V4.962H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.038l3.007-2.332z" fill="#FBBC05"/>
      <path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.962L3.964 7.294C4.672 5.163 6.656 3.58 9 3.58z" fill="#EA4335"/>
    </svg>
    Continue with Google
  </a>
</div>
<?php endif; ?>

<p class="text-center text-muted small mt-4">
  Already have an account? <a href="/login" class="fw-medium">Sign in</a>
</p>
