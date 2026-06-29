<?php
$pageTitle = 'Sign in to SportsMIS';

// Allow deep-linking into a specific panel via ?panel=athlete-login etc.
// Validates against the four known panel keys; anything else falls through
// to "chooser" — i.e. nothing pre-opened.
$allowedPanels  = ['athlete-login', 'athlete-register', 'institution-login', 'institution-register'];
$requestedPanel = (string)($_GET['panel'] ?? '');
$initialPanel   = in_array($requestedPanel, $allowedPanels, true) ? $requestedPanel : '';
?>

<style>
  /* Login-page-only overrides on the auth shell. The form-inner is
     widened from the default 440px (too cramped for the two cards)
     to 650px — 60% of the previous 1080px reading the user asked
     for; the form panel aligns content to the top so the chooser
     sits at the top edge of the panel without flex-centering. */
  .sms-auth-form-panel { align-items:flex-start !important; padding:1.25rem 1.25rem !important; }
  .sms-auth-form-inner { max-width:650px !important; }

  /* Chooser bar — normal in-flow (was sticky), transparent so it
     blends with the auth panel's own surface instead of stamping a
     white block over it. */
  #loginChooserCards {
    margin-bottom:1.5rem;
  }

  /* Modernised card — matches the "Performance Analytics / Event
     Management / Community Support" reference the user shared:
     white surface, hairline slate-200 border, soft drop shadow, big
     vibrant gradient icon. */
  .role-card {
    background:#ffffff;
    border:1px solid #e2e8f0;
    border-radius:16px;
    padding:1.5rem;
    height:100%;
    box-shadow:0 1px 3px rgba(15,23,42,.05), 0 8px 24px rgba(15,23,42,.04);
    transition:box-shadow .18s ease, transform .18s ease, border-color .18s ease;
    display:flex; flex-direction:column; gap:1.25rem;
  }
  .role-card:hover {
    box-shadow:0 6px 14px rgba(15,23,42,.08), 0 16px 32px rgba(15,23,42,.06);
    border-color:#cbd5e1;
    transform:translateY(-2px);
  }
  .role-card .role-icon {
    width:52px; height:52px; border-radius:12px;
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0;
    color:#ffffff;
    box-shadow:0 6px 14px rgba(15,23,42,.12);
  }
  .role-card .role-title { font-weight:700; font-size:1.05rem; letter-spacing:-.01em; color:#0f172a; }
  .role-card .role-sub   { color:#64748b; font-size:.875rem; line-height:1.4; }
  .role-card .role-btn {
    border:1px solid #cbd5e1; background:#fff; color:#0f172a;
    padding:.6rem .9rem; border-radius:10px; font-weight:600;
    display:flex; align-items:center; justify-content:center; gap:.4rem;
    transition:background .15s, border-color .15s, color .15s, box-shadow .15s;
  }
  .role-card .role-btn:hover   { background:#f1f5f9; border-color:#94a3b8; }
  .role-card .role-btn.primary { background:#0f172a; border-color:#0f172a; color:#fff; }
  .role-card .role-btn.primary:hover { background:#1e293b; border-color:#1e293b; }
  .role-card .role-btn.active  {
    background:#1d4ed8; border-color:#1d4ed8; color:#fff;
    box-shadow:0 0 0 3px rgba(29,78,216,.18);
  }
  /* Distinct gradient icon per card — cyan→teal for Athletes
     (performance / activity energy), warm-orange→amber for
     Institutions (welcoming / authority), mirroring the colour
     spread in the user-shared reference. */
  .role-card.role-athlete    .role-icon { background:linear-gradient(135deg, #14b8a6 0%, #06b6d4 100%); }
  .role-card.role-institution .role-icon { background:linear-gradient(135deg, #f59e0b 0%, #f97316 100%); }
</style>

<div class="row g-3 mb-4" id="loginChooserCards">
  <!-- ── Athletes card ── -->
  <div class="col-md-6">
    <div class="role-card role-athlete">
      <div class="d-flex align-items-center gap-3">
        <div class="role-icon"><i class="bi bi-person-arms-up fs-3"></i></div>
        <div class="min-w-0">
          <div class="role-title">Athletes</div>
          <div class="role-sub">Register, compete &amp; track performance</div>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button type="button" class="role-btn primary flex-fill" data-show-panel="athlete-register">
          <i class="bi bi-person-plus"></i><span>Register</span>
        </button>
        <button type="button" class="role-btn flex-fill" data-show-panel="athlete-login">
          <i class="bi bi-box-arrow-in-right"></i><span>Login</span>
        </button>
      </div>
    </div>
  </div>

  <!-- ── Institutions / Clubs card ── -->
  <div class="col-md-6">
    <div class="role-card role-institution">
      <div class="d-flex align-items-center gap-3">
        <div class="role-icon"><i class="bi bi-building fs-3"></i></div>
        <div class="min-w-0">
          <div class="role-title">Schools/Institutions/Clubs</div>
          <div class="role-sub">Manage events, staff &amp; athlete registrations</div>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button type="button" class="role-btn primary flex-fill" data-show-panel="institution-register">
          <i class="bi bi-building-add"></i><span>Register</span>
        </button>
        <button type="button" class="role-btn flex-fill" data-show-panel="institution-login">
          <i class="bi bi-box-arrow-in-right"></i><span>Login</span>
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
          <i class="bi bi-person-arms-up text-warning"></i>
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
  const panels   = document.querySelectorAll('.login-panel');
  const chipBtns = document.querySelectorAll('[data-show-panel]');
  const initial  = <?= json_encode($initialPanel) ?>;

  function showPanel(key) {
    // Cards stay visible at all times; only the form panel below them
    // toggles. The active chooser button is highlighted so the user
    // can see which panel they're currently in.
    panels.forEach(p => p.style.display = p.id === 'panel-' + key ? '' : 'none');
    chipBtns.forEach(b => b.classList.toggle('active', b.dataset.showPanel === key));
    // Reflect the selection in the URL so a refresh keeps the panel open.
    try {
      const u = new URL(location.href);
      u.searchParams.set('panel', key);
      history.replaceState(null, '', u.toString());
    } catch (e) { /* ignore */ }
    // Scroll the form into view (it appears below the cards).
    const panel = document.getElementById('panel-' + key);
    if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function showChooser() {
    panels.forEach(p => p.style.display = 'none');
    chipBtns.forEach(b => b.classList.remove('active'));
    try {
      const u = new URL(location.href);
      u.searchParams.delete('panel');
      history.replaceState(null, '', u.toString());
    } catch (e) { /* ignore */ }
  }

  // Wire chooser buttons.
  chipBtns.forEach(btn => {
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
