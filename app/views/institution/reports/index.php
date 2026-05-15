<?php $pageTitle = 'Reports — ' . $event['name']; ?>

<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
  <a href="/institution/events/<?= (int)$event['id'] ?>/view" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Back to Event
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-bar-chart me-2"></i>Reports — <?= e($event['name']) ?></h5>
</div>

<div class="row g-4">
  <!-- ─ Pre-Event ─ -->
  <div class="col-lg-4">
    <div class="sms-card p-4 h-100">
      <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge bg-primary-subtle text-primary px-3 py-2"><i class="bi bi-1-circle me-1"></i>Pre-Event</span>
      </div>
      <p class="text-muted small mb-3">Reports useful before the event day — registrations, fees collected, athlete lists.</p>
      <div class="d-grid gap-2">
        <a href="/institution/events/<?= e($eventHash) ?>/reports/registration-stats"
           class="btn btn-outline-primary text-start">
          <i class="bi bi-pie-chart me-2"></i>Registration Statistics
          <small class="d-block text-muted ms-4">Sport-category counts &amp; sport-event counts (gender pivot)</small>
        </a>
        <a href="/institution/events/<?= e($eventHash) ?>/reports/fee-collection"
           class="btn btn-outline-primary text-start">
          <i class="bi bi-cash-coin me-2"></i>Fee Collection
          <small class="d-block text-muted ms-4">Per-transaction list with date / status filters &amp; grand total</small>
        </a>
        <a href="/institution/events/<?= e($eventHash) ?>/reports/competitor-list"
           class="btn btn-outline-primary text-start" target="_blank" rel="noopener">
          <i class="bi bi-list-ol me-2"></i>Competitor List <small class="text-muted">(printable)</small>
          <small class="d-block text-muted ms-4">Sport-Event wise athlete list — A4, opens in new tab</small>
        </a>
        <a href="/institution/events/<?= e($eventHash) ?>/reports/unit-others"
           class="btn btn-outline-primary text-start">
          <i class="bi bi-buildings me-2"></i>Unit = Other Registrations
          <small class="d-block text-muted ms-4">Registrations where the athlete picked "Other" and typed a unit name</small>
        </a>
      </div>
    </div>
  </div>

  <!-- ─ Event Day ─ -->
  <div class="col-lg-4">
    <div class="sms-card p-4 h-100">
      <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge bg-warning-subtle text-warning px-3 py-2"><i class="bi bi-2-circle me-1"></i>Event Day</span>
      </div>
      <p class="text-muted small mb-3">Reports for the event day itself — competitor lists, attendance.</p>
      <div class="text-muted small fst-italic"><i class="bi bi-three-dots me-1"></i>Coming soon.</div>
    </div>
  </div>

  <!-- ─ Post-Event ─ -->
  <div class="col-lg-4">
    <div class="sms-card p-4 h-100">
      <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge bg-success-subtle text-success px-3 py-2"><i class="bi bi-3-circle me-1"></i>Post-Event</span>
      </div>
      <p class="text-muted small mb-3">Reports after the event — results, certificates, settlements.</p>
      <div class="text-muted small fst-italic"><i class="bi bi-three-dots me-1"></i>Coming soon.</div>
    </div>
  </div>
</div>
