<?php $pageTitle = 'Reports'; ?>

<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
  <h5 class="mb-0 fw-bold"><i class="bi bi-bar-chart me-2"></i>Reports</h5>
</div>

<div class="row g-4">

  <!-- ─ ePayment based reports ─ -->
  <div class="col-lg-4">
    <div class="sms-card p-4 h-100">
      <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge bg-success-subtle text-success px-3 py-2">
          <i class="bi bi-credit-card-2-front me-1"></i>ePayment Based
        </span>
      </div>
      <p class="text-muted small mb-3">
        Razorpay / online-gateway transactions across every event, with the
        receiving event-administrator&apos;s bank account on the same row.
      </p>
      <div class="d-grid gap-2">
        <a href="/admin/reports/epayments" class="btn btn-outline-success text-start">
          <i class="bi bi-receipt me-2"></i>Event-Admin&nbsp;wise ePayment Summary
          <small class="d-block text-muted ms-4">Counts &amp; amounts grouped by event, with bank details</small>
        </a>
        <a href="/admin/reports/epayments/pending" class="btn btn-outline-warning text-start">
          <i class="bi bi-hourglass-split me-2"></i>Pending ePayment Transactions
          <small class="d-block text-muted ms-4">Per-row Re-check with Razorpay (cron-equivalent)</small>
        </a>
      </div>
    </div>
  </div>

  <!-- ─ Registration based reports ─ -->
  <div class="col-lg-4">
    <div class="sms-card p-4 h-100">
      <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge bg-primary-subtle text-primary px-3 py-2">
          <i class="bi bi-people me-1"></i>Registration Based
        </span>
      </div>
      <p class="text-muted small mb-3">Cross-event registration roll-ups for athletes and institutions.</p>
      <div class="text-muted small fst-italic"><i class="bi bi-three-dots me-1"></i>Coming soon.</div>
    </div>
  </div>

  <!-- ─ Audit / Operations ─ -->
  <div class="col-lg-4">
    <div class="sms-card p-4 h-100">
      <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge bg-warning-subtle text-warning px-3 py-2">
          <i class="bi bi-shield-check me-1"></i>Audit &amp; Ops
        </span>
      </div>
      <p class="text-muted small mb-3">Login history, deletions, approval actions.</p>
      <div class="text-muted small fst-italic"><i class="bi bi-three-dots me-1"></i>Coming soon.</div>
    </div>
  </div>
</div>
