<?php
$pageTitle  = 'Login';
$activeTab  = old('role_hint', 'athlete');
$googleIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48" class="me-2" style="vertical-align:-.2em"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>';
?>

<h3 class="fw-bold mb-1">Welcome back</h3>
<p class="text-muted mb-4">Sign in to your SportsMIS account</p>

<!-- Role tabs -->
<ul class="nav nav-pills nav-fill mb-4 p-1 rounded-3" style="background:#f1f5f9" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $activeTab !== 'institution' ? 'active' : '' ?> fw-medium rounded-2"
            id="athlete-tab" data-bs-toggle="pill" data-bs-target="#paneAthlete" type="button" role="tab">
      <i class="bi bi-person-running me-1"></i>Athlete
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $activeTab === 'institution' ? 'active' : '' ?> fw-medium rounded-2"
            id="institution-tab" data-bs-toggle="pill" data-bs-target="#paneInstitution" type="button" role="tab">
      <i class="bi bi-building me-1"></i>Institution / Club
    </button>
  </li>
</ul>

<div class="tab-content">

  <!-- ── Athlete Tab ─────────────────────────────────────── -->
  <div class="tab-pane fade <?= $activeTab !== 'institution' ? 'show active' : '' ?>"
       id="paneAthlete" role="tabpanel">

    <a href="/auth/google" class="btn btn-outline-danger w-100 py-2 fw-medium">
      <?= $googleIcon ?>Continue with Google
    </a>

    <div class="d-flex align-items-center my-3">
      <hr class="flex-grow-1 m-0">
      <span class="px-3 text-muted small">or sign in with email</span>
      <hr class="flex-grow-1 m-0">
    </div>

    <form method="POST" action="/login" novalidate>
      <?= csrf() ?>
      <input type="hidden" name="role_hint" value="athlete">

      <div class="mb-3">
        <label class="form-label fw-medium">Email Address</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input type="email" name="email" value="<?= $activeTab !== 'institution' ? e(old('email')) : '' ?>"
                 class="form-control <?= $activeTab !== 'institution' ? hasError('email') : '' ?>"
                 placeholder="you@example.com" required autofocus>
        </div>
        <?= $activeTab !== 'institution' ? fieldError('email') : '' ?>
      </div>

      <div class="mb-4">
        <label class="form-label fw-medium d-flex justify-content-between">
          Password
          <a href="/password/forgot" class="text-decoration-none small">Forgot password?</a>
        </label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" name="password" id="pwdAthlete"
                 class="form-control" placeholder="••••••••" required>
          <button type="button" class="btn btn-outline-secondary toggle-pwd" tabindex="-1"
                  data-target="pwdAthlete">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
      </button>
    </form>

    <p class="text-center text-muted small mt-3 mb-0">
      No account? <a href="/register/athlete" class="fw-medium">Register as Athlete</a>
    </p>
  </div>

  <!-- ── Institution / Club Tab ─────────────────────────── -->
  <div class="tab-pane fade <?= $activeTab === 'institution' ? 'show active' : '' ?>"
       id="paneInstitution" role="tabpanel">

    <form method="POST" action="/login" novalidate>
      <?= csrf() ?>
      <input type="hidden" name="role_hint" value="institution">

      <div class="mb-3">
        <label class="form-label fw-medium">Email Address</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input type="email" name="email" value="<?= $activeTab === 'institution' ? e(old('email')) : '' ?>"
                 class="form-control <?= $activeTab === 'institution' ? hasError('email') : '' ?>"
                 placeholder="you@example.com" required>
        </div>
        <?= $activeTab === 'institution' ? fieldError('email') : '' ?>
      </div>

      <div class="mb-4">
        <label class="form-label fw-medium d-flex justify-content-between">
          Password
          <a href="/password/forgot" class="text-decoration-none small">Forgot password?</a>
        </label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" name="password" id="pwdInst"
                 class="form-control" placeholder="••••••••" required>
          <button type="button" class="btn btn-outline-secondary toggle-pwd" tabindex="-1"
                  data-target="pwdInst">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
      </button>
    </form>

    <p class="text-center text-muted small mt-3 mb-0">
      No account? <a href="/register/institution" class="fw-medium">Register your Institution</a>
    </p>
  </div>

</div>

<script>
document.querySelectorAll('.toggle-pwd').forEach(btn => {
  btn.addEventListener('click', function() {
    const input = document.getElementById(this.dataset.target);
    const icon  = this.querySelector('i');
    if (input.type === 'password') { input.type = 'text';     icon.className = 'bi bi-eye-slash'; }
    else                           { input.type = 'password'; icon.className = 'bi bi-eye'; }
  });
});
</script>
