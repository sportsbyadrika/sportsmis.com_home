<?php $pageTitle = 'Login'; ?>

<h3 class="fw-bold mb-1">Welcome back</h3>
<p class="text-muted mb-4">Sign in to your SportsMIS account</p>

<form method="POST" action="/login" novalidate>
  <?= csrf() ?>

  <div class="mb-3">
    <label class="form-label fw-medium">Email Address</label>
    <div class="input-group">
      <span class="input-group-text"><i class="bi bi-envelope"></i></span>
      <input type="email" name="email" value="<?= e(old('email')) ?>"
             class="form-control <?= hasError('email') ?>"
             placeholder="you@example.com" required autofocus>
    </div>
    <?= fieldError('email') ?>
  </div>

  <div class="mb-4">
    <label class="form-label fw-medium d-flex justify-content-between">
      Password
      <a href="/password/forgot" class="text-decoration-none small">Forgot password?</a>
    </label>
    <div class="input-group">
      <span class="input-group-text"><i class="bi bi-lock"></i></span>
      <input type="password" name="password" id="password"
             class="form-control <?= hasError('password') ?>"
             placeholder="••••••••" required>
      <button type="button" class="btn btn-outline-secondary" id="togglePassword" tabindex="-1">
        <i class="bi bi-eye" id="eyeIcon"></i>
      </button>
    </div>
    <?= fieldError('password') ?>
  </div>

  <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
  </button>
</form>

<hr class="my-4">

<div class="text-center text-muted small mb-3">New to SportsMIS? Choose your registration type:</div>
<div class="d-flex gap-2">
  <a href="/register/institution" class="btn btn-outline-secondary flex-fill">
    <i class="bi bi-building me-1"></i>Institution / Club
  </a>
  <a href="/register/athlete" class="btn btn-outline-secondary flex-fill">
    <i class="bi bi-person-running me-1"></i>Athlete
  </a>
</div>

<script>
document.getElementById('togglePassword')?.addEventListener('click', function() {
  const pwd = document.getElementById('password');
  const icon = document.getElementById('eyeIcon');
  if (pwd.type === 'password') { pwd.type = 'text'; icon.className = 'bi bi-eye-slash'; }
  else { pwd.type = 'password'; icon.className = 'bi bi-eye'; }
});
</script>
