<?php $pageTitle = 'Settings'; ?>

<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
  <h5 class="mb-0 fw-bold"><i class="bi bi-gear me-2"></i>Settings</h5>
</div>

<div class="row g-4">

  <!-- ─ Masters ─ -->
  <div class="col-lg-4">
    <div class="sms-card p-4 h-100">
      <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge bg-secondary-subtle text-secondary px-3 py-2">
          <i class="bi bi-collection me-1"></i>Masters
        </span>
      </div>
      <p class="text-muted small mb-3">Reference data used across the app — country, state, district, etc.</p>
      <div class="text-muted small fst-italic"><i class="bi bi-three-dots me-1"></i>Coming soon.</div>
    </div>
  </div>

  <!-- ─ Sports Setting ─ -->
  <div class="col-lg-4">
    <div class="sms-card p-4 h-100">
      <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge bg-primary-subtle text-primary px-3 py-2">
          <i class="bi bi-trophy me-1"></i>Sports Setting
        </span>
      </div>
      <p class="text-muted small mb-3">Enable / disable sports, manage age categories, sport categories, and the sport-event catalogue.</p>
      <div class="d-grid gap-2">
        <a href="/admin/settings/sports" class="btn btn-outline-primary text-start">
          <i class="bi bi-sliders me-2"></i>Open Sports Setting
        </a>
      </div>
    </div>
  </div>

  <!-- ─ Sports Items / Weapons ─ -->
  <div class="col-lg-4">
    <div class="sms-card p-4 h-100">
      <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge bg-success-subtle text-success px-3 py-2">
          <i class="bi bi-tools me-1"></i>Sports Items / Weapons
        </span>
      </div>
      <p class="text-muted small mb-3">Per-sport catalogue of items (rifles, bows, gloves…) athletes can declare during event registration.</p>
      <div class="d-grid gap-2">
        <a href="/admin/settings/sport-items" class="btn btn-outline-success text-start">
          <i class="bi bi-list-ul me-2"></i>Manage Items / Weapons
        </a>
      </div>
    </div>
  </div>

  <!-- ─ Login Page ─ -->
  <div class="col-lg-4">
    <div class="sms-card p-4 h-100">
      <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge bg-warning-subtle text-warning px-3 py-2">
          <i class="bi bi-box-arrow-in-right me-1"></i>Login Page
        </span>
      </div>
      <p class="text-muted small mb-3">Show or hide the Athlete <strong>Login</strong> and <strong>Register</strong> buttons on the public login page.</p>
      <div class="d-grid gap-2">
        <a href="/admin/settings/login-page" class="btn btn-outline-warning text-start">
          <i class="bi bi-toggles me-2"></i>Login Page Settings
        </a>
      </div>
    </div>
  </div>
</div>
