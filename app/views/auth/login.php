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

<div class="text-center my-4">
  <a href="/auth/google" class="btn btn-outline-danger w-100 py-2 fw-medium">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48" class="me-2" style="vertical-align:-.2em">
      <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
      <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
      <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
      <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
    </svg>
    Continue with Google
  </a>
</div>

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
