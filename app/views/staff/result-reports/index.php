<?php $pageTitle = 'Result Reports — ' . $event['name']; ?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold"><i class="bi bi-trophy me-2"></i>Result Reports</h5>
    <div class="text-muted small mt-1">
      Event: <strong><?= e($event['name']) ?></strong>
      · Code: <code><?= e($event['event_code'] ?? '') ?></code>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-6 col-lg-3">
    <a href="/event-staff/result-reports/relay-result" class="text-decoration-none">
      <div class="sms-card p-4 h-100 text-center sms-hover-lift">
        <div class="display-6 text-primary mb-2"><i class="bi bi-bullseye"></i></div>
        <h6 class="fw-bold mb-1">Relay Result</h6>
        <p class="small text-muted mb-0">Lane-wise results for a chosen relay — series scores, penalty, inner tens, grand total.</p>
      </div>
    </a>
  </div>
  <div class="col-md-6 col-lg-3">
    <a href="/event-staff/result-reports/event-rank-list" class="text-decoration-none">
      <div class="sms-card p-4 h-100 text-center sms-hover-lift">
        <div class="display-6 text-primary mb-2"><i class="bi bi-list-ol"></i></div>
        <h6 class="fw-bold mb-1">Event — Rank List</h6>
        <p class="small text-muted mb-0">Ranked results per sport-event, grouped under a chosen event category.</p>
      </div>
    </a>
  </div>
  <div class="col-md-6 col-lg-3">
    <a href="/event-staff/result-reports/team-rank-list" class="text-decoration-none">
      <div class="sms-card p-4 h-100 text-center sms-hover-lift">
        <div class="display-6 text-primary mb-2"><i class="bi bi-people"></i></div>
        <h6 class="fw-bold mb-1">Team — Rank List</h6>
        <p class="small text-muted mb-0">Team standings grouped by sport-event under a chosen category — sum of the three members' Total Scores.</p>
      </div>
    </a>
  </div>
  <div class="col-md-6 col-lg-3">
    <a href="/event-staff/result-reports/medal" class="text-decoration-none">
      <div class="sms-card p-4 h-100 text-center sms-hover-lift">
        <div class="display-6 text-primary mb-2"><i class="bi bi-award"></i></div>
        <h6 class="fw-bold mb-1">Medal</h6>
        <p class="small text-muted mb-0">Unit points + per-category medalists + per-category top scorers.</p>
      </div>
    </a>
  </div>
  <div class="col-md-6 col-lg-3">
    <a href="/event-staff/result-reports/category-top-units" class="text-decoration-none">
      <div class="sms-card p-4 h-100 text-center sms-hover-lift">
        <div class="display-6 text-primary mb-2"><i class="bi bi-buildings"></i></div>
        <h6 class="fw-bold mb-1">Category — Top 5 Units</h6>
        <p class="small text-muted mb-0">Per event category, the top 5 units / clubs ranked by medal points (individual + team).</p>
      </div>
    </a>
  </div>
</div>
