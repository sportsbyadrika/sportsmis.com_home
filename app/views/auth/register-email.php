<?php $pageTitle = 'Create your account'; ?>

<div class="sms-signin mx-auto" style="max-width:420px">
  <div class="text-center mb-4">
    <h3 class="fw-bold mb-1">Create your SportsMIS account</h3>
    <p class="text-muted small mb-0">One account — participate, organise, or run a unit.</p>
  </div>

  <div class="border rounded-3 shadow-sm bg-white p-4">
    <a href="/auth/google?tab=athlete" class="btn btn-outline-danger w-100 py-2 fw-medium">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48" class="me-2" style="vertical-align:-.2em">
        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
      </svg>
      Continue with Google
    </a>

    <div class="d-flex align-items-center my-3">
      <hr class="flex-grow-1 m-0">
      <span class="px-3 text-muted small">or sign up with email</span>
      <hr class="flex-grow-1 m-0">
    </div>

    <form method="POST" action="/register/start" novalidate>
      <?= csrf() ?>
      <?= antibot_fields() ?>
      <div class="mb-3">
        <label class="form-label fw-medium">Email Address <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input type="email" name="email" value="<?= e(old('email')) ?>"
                 class="form-control <?= hasError('email') ?>"
                 placeholder="you@example.com" autofocus required>
        </div>
        <?= fieldError('email') ?>
        <small class="text-muted">We'll send a confirmation link. You set your profile &amp; password after confirming.</small>
      </div>

      <?= captcha_widget() ?>
      <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
        <i class="bi bi-send me-2"></i>Send confirmation link
      </button>
    </form>

    <div class="text-center mt-4 pt-3 border-top">
      <span class="text-muted small">Already have an account?</span>
      <a href="/login" class="fw-semibold small text-decoration-none ms-1">Sign in</a>
    </div>
  </div>
</div>
