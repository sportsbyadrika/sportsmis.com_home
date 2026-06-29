<?php
$pageTitle = 'Sign in to SportsMIS';

// Allow deep-linking into a specific panel via ?panel=athlete-login etc.
// Validates against the four known panel keys; anything else falls through
// to "chooser" — i.e. nothing pre-opened.
$allowedPanels  = ['athlete-login', 'athlete-register', 'institution-login', 'institution-register'];
$requestedPanel = (string)($_GET['panel'] ?? '');
$initialPanel   = in_array($requestedPanel, $allowedPanels, true) ? $requestedPanel : '';
?>

<div class="row g-3 mb-3" id="loginChooserCards">
  <!-- ── Athletes card ── -->
  <div class="col-md-6">
    <div class="border rounded-3 p-3 shadow-sm h-100" style="background:#f8fafc">
      <div class="d-flex align-items-center gap-3 mb-3">
        <div style="width:44px;height:44px;border-radius:.6rem;background:#0b1f3a;display:flex;align-items:center;justify-content:center">
          <i class="bi bi-person-running text-warning fs-4"></i>
        </div>
        <div class="min-w-0">
          <div class="fw-bold" style="font-size:1.05rem;color:#0b1f3a">Athletes</div>
          <div class="text-muted small">Register, compete &amp; track performance</div>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-warning fw-semibold flex-fill" data-show-panel="athlete-register">
          <i class="bi bi-person-plus me-1"></i>Register
        </button>
        <button type="button" class="btn btn-outline-secondary fw-semibold flex-fill" data-show-panel="athlete-login">
          <i class="bi bi-box-arrow-in-right me-1"></i>Login
        </button>
      </div>
    </div>
  </div>

  <!-- ── Institutions / Clubs card ── -->
  <div class="col-md-6">
    <div class="border rounded-3 p-3 shadow-sm h-100" style="background:#ecfeff">
      <div class="d-flex align-items-center gap-3 mb-3">
        <div style="width:44px;height:44px;border-radius:.6rem;background:#0e7490;display:flex;align-items:center;justify-content:center">
          <i class="bi bi-building text-white fs-4"></i>
        </div>
        <div class="min-w-0">
          <div class="fw-bold" style="font-size:1.05rem;color:#0e7490">Institutions &amp; Clubs</div>
          <div class="text-muted small">Manage events, staff &amp; athlete registrations</div>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-info fw-semibold flex-fill text-white" data-show-panel="institution-register">
          <i class="bi bi-building-add me-1"></i>Register
        </button>
        <button type="button" class="btn btn-outline-secondary fw-semibold flex-fill" data-show-panel="institution-login">
          <i class="bi bi-box-arrow-in-right me-1"></i>Login
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── Form panels (hidden by default; one shown at a time) ── -->

<!-- Athlete Login -->
<div class="login-panel" id="panel-athlete-login" style="display:none">
  <div class="border rounded-3 overflow-hidden shadow-sm">
    <div class="p-3 px-4 d-flex justify-content-between align-items-center" style="background:#fef3c7;border-bottom:1px solid #fde68a">
      <div class="d-flex align-items-center gap-2">
        <div style="width:36px;height:36px;border-radius:.5rem;background:#0b1f3a;display:flex;align-items:center;justify-content:center">
          <i class="bi bi-person-running text-warning"></i>
        </div>
        <div>
          <div class="fw-bold" style="font-size:1rem;line-height:1.2;color:#0b1f3a">Athlete Login</div>
          <div class="text-muted" style="font-size:.8rem">Sign in to your athlete account</div>
        </div>
      </div>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-close-panel><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="p-4 bg-white">
      <a href="/auth/google?tab=athlete" class="btn btn-outline-danger w-100 py-2 fw-medium mb-3">
        <i class="bi bi-google me-2"></i>Continue with Google
      </a>
      <div class="d-flex align-items-center mb-3">
        <hr class="flex-grow-1 m-0"><span class="px-3 text-muted small">or sign in with email</span><hr class="flex-grow-1 m-0">
      </div>
      <form method="POST" action="/login" novalidate>
        <?= csrf() ?>
        <input type="hidden" name="role_hint" value="athlete">
        <div class="mb-3">
          <label class="form-label fw-medium">Email Address</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" name="email" value="<?= e(old('email')) ?>"
                   class="form-control <?= hasError('email') ?>"
                   placeholder="you@example.com" required>
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
            <input type="password" name="password" id="pwdAthlete" class="form-control" placeholder="••••••••" required>
            <button type="button" class="btn btn-outline-secondary toggle-pwd" tabindex="-1" data-target="pwdAthlete">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
          <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Athlete Register -->
<div class="login-panel" id="panel-athlete-register" style="display:none">
  <div class="border rounded-3 overflow-hidden shadow-sm">
    <div class="p-3 px-4 d-flex justify-content-between align-items-center" style="background:#fef3c7;border-bottom:1px solid #fde68a">
      <div class="d-flex align-items-center gap-2">
        <div style="width:36px;height:36px;border-radius:.5rem;background:#0b1f3a;display:flex;align-items:center;justify-content:center">
          <i class="bi bi-person-plus text-warning"></i>
        </div>
        <div>
          <div class="fw-bold" style="font-size:1rem;line-height:1.2;color:#0b1f3a">Register as Athlete</div>
          <div class="text-muted" style="font-size:.8rem">Create your athlete account</div>
        </div>
      </div>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-close-panel><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="p-4 bg-white">
      <a href="/auth/google?tab=athlete" class="btn btn-outline-danger w-100 py-2 fw-medium mb-3">
        <i class="bi bi-google me-2"></i>Continue with Google
      </a>
      <div class="d-flex align-items-center mb-3">
        <hr class="flex-grow-1 m-0"><span class="px-3 text-muted small">or register with email</span><hr class="flex-grow-1 m-0">
      </div>
      <form method="POST" action="/register/athlete" novalidate>
        <?= csrf() ?>
        <div class="mb-3">
          <label class="form-label fw-medium">Full Name <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" name="name" value="<?= e(old('name')) ?>"
                   class="form-control <?= hasError('name') ?>" placeholder="Your full name" required>
          </div>
          <?= fieldError('name') ?>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-sm-6">
            <label class="form-label fw-medium">Mobile <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-phone"></i></span>
              <input type="tel" name="mobile" value="<?= e(old('mobile')) ?>"
                     class="form-control <?= hasError('mobile') ?>" placeholder="10-digit" maxlength="10" required>
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
          <label class="form-label fw-medium">
            Email Address <span class="text-danger">*</span>
            <small class="text-muted fw-normal">(used as login username)</small>
          </label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" name="email" value="<?= e(old('email')) ?>"
                   class="form-control <?= hasError('email') ?>" placeholder="you@example.com" required>
          </div>
          <?= fieldError('email') ?>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
          <i class="bi bi-send me-2"></i>Submit Registration
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Institution Login -->
<div class="login-panel" id="panel-institution-login" style="display:none">
  <div class="border rounded-3 overflow-hidden shadow-sm">
    <div class="p-3 px-4 d-flex justify-content-between align-items-center" style="background:#ecfeff;border-bottom:1px solid #a5f3fc">
      <div class="d-flex align-items-center gap-2">
        <div style="width:36px;height:36px;border-radius:.5rem;background:#0e7490;display:flex;align-items:center;justify-content:center">
          <i class="bi bi-building text-white"></i>
        </div>
        <div>
          <div class="fw-bold" style="font-size:1rem;line-height:1.2;color:#0e7490">Institution / Club Login</div>
          <div class="text-muted" style="font-size:.8rem">Sign in to manage your institution account</div>
        </div>
      </div>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-close-panel><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="p-4 bg-white">
      <a href="/auth/google?tab=institution" class="btn btn-outline-danger w-100 py-2 fw-medium mb-3">
        <i class="bi bi-google me-2"></i>Continue with Google
      </a>
      <div class="d-flex align-items-center mb-3">
        <hr class="flex-grow-1 m-0"><span class="px-3 text-muted small">or sign in with email</span><hr class="flex-grow-1 m-0">
      </div>
      <form method="POST" action="/login" novalidate>
        <?= csrf() ?>
        <input type="hidden" name="role_hint" value="institution">
        <div class="mb-3">
          <label class="form-label fw-medium">Email Address</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" name="email" value="<?= e(old('email')) ?>"
                   class="form-control <?= hasError('email') ?>"
                   placeholder="admin@yourinstitution.com" required>
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
            <input type="password" name="password" id="pwdInst" class="form-control" placeholder="••••••••" required>
            <button type="button" class="btn btn-outline-secondary toggle-pwd" tabindex="-1" data-target="pwdInst">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
          <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Institution Register -->
<div class="login-panel" id="panel-institution-register" style="display:none">
  <div class="border rounded-3 overflow-hidden shadow-sm">
    <div class="p-3 px-4 d-flex justify-content-between align-items-center" style="background:#ecfeff;border-bottom:1px solid #a5f3fc">
      <div class="d-flex align-items-center gap-2">
        <div style="width:36px;height:36px;border-radius:.5rem;background:#0e7490;display:flex;align-items:center;justify-content:center">
          <i class="bi bi-building-add text-white"></i>
        </div>
        <div>
          <div class="fw-bold" style="font-size:1rem;line-height:1.2;color:#0e7490">Register Your Institution</div>
          <div class="text-muted" style="font-size:.8rem">Sports academies, clubs, schools &amp; federations</div>
        </div>
      </div>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-close-panel><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="p-4 bg-white">
      <form method="POST" action="/register/institution" novalidate>
        <?= csrf() ?>
        <div class="mb-3">
          <label class="form-label fw-medium">Institution / Club Name <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-building"></i></span>
            <input type="text" name="institution_name" value="<?= e(old('institution_name')) ?>"
                   class="form-control <?= hasError('institution_name') ?>"
                   placeholder="e.g. Kerala State Sports Council" required>
          </div>
          <?= fieldError('institution_name') ?>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-sm-7">
            <label class="form-label fw-medium">SPOC Name <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-person"></i></span>
              <input type="text" name="spoc_name" value="<?= e(old('spoc_name')) ?>"
                     class="form-control <?= hasError('spoc_name') ?>" placeholder="Contact person name" required>
            </div>
            <?= fieldError('spoc_name') ?>
          </div>
          <div class="col-sm-5">
            <label class="form-label fw-medium">Mobile <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-phone"></i></span>
              <input type="tel" name="spoc_mobile" value="<?= e(old('spoc_mobile')) ?>"
                     class="form-control <?= hasError('spoc_mobile') ?>" placeholder="10-digit" maxlength="10" required>
            </div>
            <?= fieldError('spoc_mobile') ?>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-medium">
            Email Address <span class="text-danger">*</span>
            <small class="text-muted fw-normal">(used as login username)</small>
          </label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" name="email" value="<?= e(old('email')) ?>"
                   class="form-control <?= hasError('email') ?>" placeholder="admin@yourinstitution.com" required>
          </div>
          <?= fieldError('email') ?>
        </div>
        <div class="mb-4">
          <label class="form-label fw-medium">Address <span class="text-danger">*</span></label>
          <textarea name="address" rows="3" class="form-control <?= hasError('address') ?>"
                    placeholder="Full address of the institution" required><?= e(old('address')) ?></textarea>
          <?= fieldError('address') ?>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
          <i class="bi bi-send me-2"></i>Submit Registration
        </button>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  const cards   = document.getElementById('loginChooserCards');
  const panels  = document.querySelectorAll('.login-panel');
  const initial = <?= json_encode($initialPanel) ?>;

  function showPanel(key) {
    panels.forEach(p => p.style.display = p.id === 'panel-' + key ? '' : 'none');
    // Hide the chooser cards while a panel is open so the page stays focused.
    if (cards) cards.style.display = 'none';
    // Reflect the selection in the URL so a refresh keeps the same panel.
    try {
      const u = new URL(location.href);
      u.searchParams.set('panel', key);
      history.replaceState(null, '', u.toString());
    } catch (e) { /* ignore */ }
  }

  function showChooser() {
    panels.forEach(p => p.style.display = 'none');
    if (cards) cards.style.display = '';
    try {
      const u = new URL(location.href);
      u.searchParams.delete('panel');
      history.replaceState(null, '', u.toString());
    } catch (e) { /* ignore */ }
  }

  // Wire chooser buttons.
  document.querySelectorAll('[data-show-panel]').forEach(btn => {
    btn.addEventListener('click', () => showPanel(btn.dataset.showPanel));
  });

  // Wire each panel's × Close button.
  document.querySelectorAll('[data-close-panel]').forEach(btn => {
    btn.addEventListener('click', showChooser);
  });

  // Open the requested panel (deep-link / form-error re-render).
  if (initial) showPanel(initial);

  // Existing password show/hide buttons.
  document.querySelectorAll('.toggle-pwd').forEach(btn => {
    btn.addEventListener('click', function () {
      const input = document.getElementById(this.dataset.target);
      const icon  = this.querySelector('i');
      if (input.type === 'password') { input.type = 'text';     icon.className = 'bi bi-eye-slash'; }
      else                           { input.type = 'password'; icon.className = 'bi bi-eye'; }
    });
  });
})();
</script>
