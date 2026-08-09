<?php $pageTitle = 'Check your email'; ?>

<div class="text-center py-3">
  <div class="mb-3">
    <span style="width:64px;height:64px;border-radius:50%;background:#e0f2fe;display:inline-flex;align-items:center;justify-content:center">
      <i class="bi bi-envelope-check text-primary" style="font-size:2rem"></i>
    </span>
  </div>
  <h3 class="fw-bold mb-2">Check your email</h3>
  <p class="text-muted mb-1">
    If <strong><?= e($email ?: 'that address') ?></strong> can be registered, we've sent a confirmation
    link to it.
  </p>
  <p class="text-muted mb-4">
    Open the link to set your profile and password. The link expires in 24 hours.
  </p>
  <div class="alert alert-light border small text-start">
    <i class="bi bi-info-circle me-1"></i>
    Didn't get it? Check your spam folder, or
    <a href="/register" class="fw-medium">try again</a>.
    Already registered? <a href="/login" class="fw-medium">Sign in</a>.
  </div>
</div>
