<?php $pageTitle = 'Set New Password'; ?>

<h3 class="fw-bold mb-1">Set New Password</h3>
<p class="text-muted mb-4">Choose a strong password (min. 8 characters).</p>

<form method="POST" action="/password/reset" novalidate>
  <?= csrf() ?>
  <input type="hidden" name="token" value="<?= e($token) ?>">

  <div class="mb-3">
    <label class="form-label fw-medium">New Password</label>
    <input type="password" name="password" class="form-control" minlength="8" required>
  </div>
  <div class="mb-4">
    <label class="form-label fw-medium">Confirm Password</label>
    <input type="password" name="password_confirmation" class="form-control" required>
  </div>
  <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
    <i class="bi bi-check-circle me-2"></i>Update Password
  </button>
</form>
