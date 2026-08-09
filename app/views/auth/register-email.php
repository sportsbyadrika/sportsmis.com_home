<?php $pageTitle = 'Create your account'; ?>

<div class="border rounded-3 overflow-hidden shadow-sm">
  <div class="p-3 px-4" style="background:#f8fafc;border-bottom:1px solid #e2e8f0">
    <div class="d-flex align-items-center gap-2">
      <div style="width:36px;height:36px;border-radius:.5rem;background:#0b1f3a;display:flex;align-items:center;justify-content:center">
        <i class="bi bi-person-plus text-warning"></i>
      </div>
      <div>
        <div class="fw-bold" style="font-size:1rem;line-height:1.2">Create your SportsMIS account</div>
        <div class="text-muted" style="font-size:.8rem">One account — participate, organise, or run a unit</div>
      </div>
    </div>
  </div>

  <div class="p-4 bg-white">
    <a href="/auth/google?tab=athlete" class="btn btn-outline-danger w-100 py-2 fw-medium mb-3">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48" class="me-2" style="vertical-align:-.2em">
        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
      </svg>
      Continue with Google
    </a>

    <div class="d-flex align-items-center mb-3">
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

    <p class="text-center text-muted small mt-3 mb-0">
      Already have an account? <a href="/login" class="fw-medium">Sign in</a>
    </p>
  </div>
</div>
