<?php
$pageTitle = 'Result Reports — ' . $event['name'];
$ledOn   = !empty($led_wall['enabled']);
$ledPwd  = (string)($led_wall['password'] ?? '');
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold"><i class="bi bi-trophy me-2"></i>Result Reports</h5>
    <div class="text-muted small mt-1">
      Event: <strong><?= e($event['name']) ?></strong>
      · Code: <code><?= e($event['event_code'] ?? '') ?></code>
    </div>
  </div>
</div>

<?= flashBag() ?>

<!-- LED-Wall slideshow controls — public URL + PIN for the TV operator -->
<div class="sms-card p-3 mb-3 border-start border-4 <?= $ledOn ? 'border-success' : 'border-secondary' ?>">
  <form method="POST" action="/event-staff/result-reports/led-wall-settings"
        class="row g-2 align-items-end">
    <?= csrf() ?>
    <div class="col-12 col-md-auto">
      <h6 class="fw-semibold mb-0">
        <i class="bi bi-broadcast me-2"></i>LED Wall Slideshow
        <?php if ($ledOn): ?>
          <span class="badge bg-success-subtle text-success-emphasis ms-1">Enabled</span>
        <?php else: ?>
          <span class="badge bg-secondary-subtle text-secondary ms-1">Disabled</span>
        <?php endif; ?>
      </h6>
      <small class="text-muted">Public auto-rotating result deck for a TV / projector.</small>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small mb-1">Status</label>
      <div class="form-check form-switch mt-1">
        <input class="form-check-input" type="checkbox" role="switch"
               name="enabled" value="1" id="ledEnabled" <?= $ledOn ? 'checked' : '' ?>>
        <label class="form-check-label small" for="ledEnabled">Enable</label>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small mb-1">PIN <span class="text-muted">(4–10 digits)</span></label>
      <input type="text" name="password" class="form-control form-control-sm"
             inputmode="numeric" pattern="\d{4,10}" maxlength="10"
             value="<?= e($ledPwd) ?>" placeholder="e.g. 1234">
    </div>
    <div class="col-12 col-md-auto">
      <button class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Save</button>
    </div>
    <?php if ($ledOn && $ledPwd !== ''): ?>
      <div class="col-12">
        <div class="alert alert-info py-2 mb-0 small d-flex flex-wrap gap-2 align-items-center">
          <i class="bi bi-info-circle"></i>
          <span>
            Share with the operator:
            <strong>URL</strong>
            <a href="/led-wall" target="_blank" class="link-primary">
              <code><?= e(($_SERVER['HTTP_HOST'] ? '/led-wall' : '/led-wall')) ?></code>
            </a>
            &middot; <strong>Event Code</strong> <code><?= e($event['event_code'] ?? '') ?></code>
            &middot; <strong>PIN</strong> <code><?= e($ledPwd) ?></code>
          </span>
          <a href="/led-wall" target="_blank" class="btn btn-sm btn-outline-success ms-auto">
            <i class="bi bi-box-arrow-up-right me-1"></i>Open Sign-In Page
          </a>
        </div>
      </div>
    <?php endif; ?>
  </form>
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
  <div class="col-md-6 col-lg-3">
    <a href="/event-staff/result-reports/category-event-top3" class="text-decoration-none">
      <div class="sms-card p-4 h-100 text-center sms-hover-lift">
        <div class="display-6 text-primary mb-2"><i class="bi bi-podium"></i></div>
        <h6 class="fw-bold mb-1">Category — Event Top 3</h6>
        <p class="small text-muted mb-0">Pick an event category — printable Gold / Silver / Bronze for every sport-event in it, one event per page.</p>
      </div>
    </a>
  </div>
  <div class="col-md-6 col-lg-3">
    <a href="/event-staff/result-reports/consolidated" class="text-decoration-none">
      <div class="sms-card p-4 h-100 text-center sms-hover-lift">
        <div class="display-6 text-primary mb-2"><i class="bi bi-bar-chart-line"></i></div>
        <h6 class="fw-bold mb-1">Consolidated Report</h6>
        <p class="small text-muted mb-0">One-page summary &mdash; participants, per-category breakdown, totals, MQS-qualified count, and Individual + Team medal tally.</p>
      </div>
    </a>
  </div>
</div>
