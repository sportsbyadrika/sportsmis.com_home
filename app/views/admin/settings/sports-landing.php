<?php $pageTitle = 'Sports Setting'; ?>

<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
  <a href="/admin/settings" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Settings
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-trophy me-2"></i>Sports Setting</h5>
</div>

<div class="row g-4">

  <!-- ─ Age Categories ─ -->
  <div class="col-lg-6">
    <div class="sms-card p-4 h-100">
      <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge bg-primary-subtle text-primary-emphasis px-3 py-2">
          <i class="bi bi-calendar-event me-1"></i>Age Categories
        </span>
      </div>
      <p class="text-muted small mb-3">
        Maintain the age-bracket master list (Sub Youth, Youth, Junior, Senior, …)
        — Min/Max Age, Min/Max Birth Year, and the "Also Eligible In" upgrades
        that let a younger bracket compete in older brackets.
      </p>
      <div class="d-grid gap-2">
        <a href="/admin/settings/sports/age-categories" class="btn btn-outline-primary text-start">
          <i class="bi bi-sliders me-2"></i>Manage Age Categories
        </a>
      </div>
    </div>
  </div>

  <!-- ─ Sports → Categories → Events ─ -->
  <div class="col-lg-6">
    <div class="sms-card p-4 h-100">
      <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge bg-success-subtle text-success-emphasis px-3 py-2">
          <i class="bi bi-diagram-3 me-1"></i>Sports &rarr; Categories &rarr; Events
        </span>
      </div>
      <p class="text-muted small mb-3">
        Toggle each sport's visibility, manage per-sport categories
        (e.g. 10m Air Pistol) and the sport-event rows under each category
        (age category, gender, weight, height, para).
      </p>
      <div class="d-grid gap-2">
        <a href="/admin/settings/sports/catalog" class="btn btn-outline-success text-start">
          <i class="bi bi-list-nested me-2"></i>Open Catalog
        </a>
      </div>
    </div>
  </div>
</div>
