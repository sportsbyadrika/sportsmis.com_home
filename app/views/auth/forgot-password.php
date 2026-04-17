<?php $pageTitle = 'Forgot Password'; ?>

<h3 class="fw-bold mb-1">Reset Password</h3>
<p class="text-muted mb-4">Enter your email and we'll send a reset link.</p>

<form method="POST" action="/password/forgot" novalidate>
  <?= csrf() ?>
  <div class="mb-4">
    <label class="form-label fw-medium">Email Address</label>
    <div class="input-group">
      <span class="input-group-text"><i class="bi bi-envelope"></i></span>
      <input type="email" name="email" class="form-control" placeholder="you@example.com" required autofocus>
    </div>
  </div>
  <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
    <i class="bi bi-send me-2"></i>Send Reset Link
  </button>
</form>
<p class="text-center text-muted small mt-4">
  <a href="/login" class="fw-medium"><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
</p>
