<?php
$pageTitle = 'Sign in to SportsMIS';

// One account for everyone now. Registration can still be hidden by a
// super-admin (reuses the existing setting so prior intent is preserved).
$registerVisible = \Models\AppSetting::getBool('login.athlete_register_visible', true);

// Reveal the email/password form straight away if the user just failed a
// sign-in (so they land back on the fields they submitted).
$revealEmail = trim((string)old('email')) !== '' || !empty($errors ?? []);
?>

<noscript>
  <style>
    /* Without JS the progressive-disclosure toggle can't work — always show the
       email form and hide the toggle button. */
    #emailBlock { display:block !important; }
    #emailToggle { display:none !important; }
  </style>
</noscript>

<div class="sms-signin mx-auto" style="max-width:420px">
  <div class="text-center mb-4">
    <h3 class="fw-bold mb-1">Sign in to SportsMIS</h3>
    <p class="text-muted small mb-0">One account — participate, organise events, or run a unit.</p>
  </div>

  <div class="border rounded-3 shadow-sm bg-white p-4">
    <!-- Primary: Google SSO -->
    <a href="/auth/google?tab=any" class="btn btn-outline-danger w-100 py-2 fw-medium">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48" class="me-2" style="vertical-align:-.2em">
        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
      </svg>
      Continue with Google
    </a>

    <!-- Secondary: reveal the credential form (progressive disclosure) -->
    <button type="button" id="emailToggle" class="btn btn-outline-secondary w-100 py-2 fw-medium mt-2"
            <?= $revealEmail ? 'style="display:none"' : '' ?>>
      <i class="bi bi-envelope me-2"></i>Continue with email
    </button>

    <div id="emailBlock" <?= $revealEmail ? '' : 'style="display:none"' ?>>
      <div class="d-flex align-items-center my-3">
        <hr class="flex-grow-1 m-0"><span class="px-3 text-muted small">sign in with email</span><hr class="flex-grow-1 m-0">
      </div>
      <form method="POST" action="/login" novalidate>
        <?= csrf() ?>
        <input type="hidden" name="role_hint" value="any">
        <div class="mb-3">
          <label class="form-label fw-medium">Email Address</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" name="email" value="<?= e(old('email')) ?>"
                   class="form-control <?= hasError('email') ?>" placeholder="you@example.com" required>
          </div>
          <?= fieldError('email') ?>
        </div>
        <div class="mb-3">
          <label class="form-label fw-medium d-flex justify-content-between">
            Password
            <a href="/password/forgot" class="text-decoration-none small">Forgot password?</a>
          </label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" name="password" id="pwdMain" class="form-control" placeholder="••••••••" required>
            <button type="button" class="btn btn-outline-secondary toggle-pwd" tabindex="-1" data-target="pwdMain">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>
        <?php if (!empty($login_captcha)): ?><?= captcha_widget() ?><?php endif; ?>
        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
          <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>
      </form>
    </div>

    <?php if ($registerVisible): ?>
    <div class="text-center mt-4 pt-3 border-top">
      <span class="text-muted small">New to SportsMIS?</span>
      <a href="/register" class="fw-semibold small text-decoration-none ms-1">Create an account</a>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  var toggle = document.getElementById('emailToggle');
  var block  = document.getElementById('emailBlock');
  if (toggle && block) {
    toggle.addEventListener('click', function () {
      block.style.display = '';
      toggle.style.display = 'none';
      var email = block.querySelector('input[name="email"]');
      if (email) email.focus();
    });
  }
  document.querySelectorAll('.toggle-pwd').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(this.dataset.target);
      var icon  = this.querySelector('i');
      if (input.type === 'password') { input.type = 'text';     icon.className = 'bi bi-eye-slash'; }
      else                           { input.type = 'password'; icon.className = 'bi bi-eye'; }
    });
  });
})();
</script>
