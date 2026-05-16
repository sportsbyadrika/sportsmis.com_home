<?php $pageTitle = $title; ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/dashboard" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Dashboard
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-three-dots me-2"></i><?= e($title) ?></h5>
  <span class="badge bg-warning-subtle text-warning ms-1">Coming soon</span>
</div>

<div class="sms-empty-state">
  <i class="bi bi-tools"></i>
  <h5><?= e($title) ?></h5>
  <p><?= e($body) ?></p>
</div>
