<?php $pageTitle = 'Register as Athlete'; ?>

<h3 class="fw-bold mb-1">Athlete Registration</h3>
<p class="text-muted mb-4">Create your SportsMIS athlete account</p>

<form method="POST" action="/register/athlete" novalidate>
  <?= csrf() ?>

  <div class="mb-3">
    <label class="form-label fw-medium">Full Name <span class="text-danger">*</span></label>
    <div class="input-group">
      <span class="input-group-text"><i class="bi bi-person"></i></span>
      <input type="text" name="name" value="<?= e(old('name')) ?>"
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
    <label class="form-label fw-medium">Email Address <span class="text-danger">*</span>
      <small class="text-muted fw-normal">(used as login username)</small>
    </label>
    <div class="input-group">
      <span class="input-group-text"><i class="bi bi-envelope"></i></span>
      <input type="email" name="email" value="<?= e(old('email')) ?>"
             class="form-control <?= hasError('email') ?>"
             placeholder="you@example.com" required>
    </div>
    <?= fieldError('email') ?>
  </div>

  <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mb-3">
    <i class="bi bi-send me-2"></i>Submit Registration
  </button>
</form>

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

<p class="text-center text-muted small mt-4">
  Already have an account? <a href="/login" class="fw-medium">Sign in</a>
</p>
