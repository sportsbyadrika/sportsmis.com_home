<?php $pageTitle = e($event['name']); ?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="/institution/events" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold">Event Details</h5>
  <?= statusBadge($event['status']) ?>
  <?php if ($event['rejection_reason']): ?>
    <span class="ms-2 text-danger small"><i class="bi bi-exclamation-circle me-1"></i><?= e($event['rejection_reason']) ?></span>
  <?php endif; ?>
</div>

<?php
  $prc = $pr_counts  ?? ['pending'=>0,'approved'=>0,'rejected'=>0];
  $rc  = $reg_counts ?? ['total'=>0,'draft'=>0,'pending'=>0,'approved'=>0,'rejected'=>0,'returned'=>0,'submitted'=>0];
  $uc  = (int)($unit_count ?? 0);
  $eh  = $eventHash ?? hid_event((int)$event['id']);
?>

<div class="row g-4">
  <!-- ===== Event Details panel ===== -->
  <div class="col-lg-6">
    <div class="sms-card p-4 h-100">
      <div class="d-flex align-items-start gap-4 mb-4">
        <?php if ($event['logo']): ?>
          <img src="<?= e($event['logo']) ?>" alt="Logo" width="72" height="72" class="rounded-3 flex-shrink-0" style="object-fit:cover">
        <?php endif; ?>
        <div>
          <h4 class="fw-bold mb-1"><?= e($event['name']) ?></h4>
          <div class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($event['location']) ?></div>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-sm-6"><small class="text-muted">Event Dates</small>
          <div class="fw-medium"><?= formatDate($event['event_date_from']) ?> – <?= formatDate($event['event_date_to']) ?></div></div>
        <div class="col-sm-6"><small class="text-muted">Registration</small>
          <div class="fw-medium"><?= formatDate($event['reg_date_from']) ?> – <?= formatDate($event['reg_date_to']) ?></div></div>
        <div class="col-sm-6"><small class="text-muted">Payment Modes</small>
          <div class="fw-medium"><?= implode(', ', array_map('ucfirst', $event['payment_modes'])) ?></div></div>
        <div class="col-sm-6"><small class="text-muted">Contact</small>
          <div class="fw-medium"><?= e($event['contact_name']) ?> &nbsp;|&nbsp; <?= e($event['contact_mobile']) ?></div></div>
      </div>
    </div>
  </div>

  <!-- ===== Participation Requests + Units & Registrations panel ===== -->
  <div class="col-lg-6">
    <div class="sms-card p-4 h-100">
      <!-- Participation Requests -->
      <h6 class="fw-semibold mb-2"><i class="bi bi-inbox me-1"></i>Participation Requests</h6>
      <div class="row g-2">
        <div class="col-4">
          <a href="/institution/events/<?= e($eh) ?>/participation-requests" class="text-decoration-none">
            <div class="border rounded-3 p-3 h-100 text-center position-relative">
              <div class="text-muted small text-uppercase" style="font-size:.7rem">Pending</div>
              <div class="fs-4 fw-bold text-warning"><?= (int)$prc['pending'] ?></div>
              <div class="small text-primary"><i class="bi bi-arrow-right-circle me-1"></i>Review</div>
            </div>
          </a>
        </div>
        <div class="col-4">
          <div class="border rounded-3 p-3 h-100 text-center">
            <div class="text-muted small text-uppercase" style="font-size:.7rem">Approved</div>
            <div class="fs-4 fw-bold text-success"><?= (int)$prc['approved'] ?></div>
          </div>
        </div>
        <div class="col-4">
          <div class="border rounded-3 p-3 h-100 text-center">
            <div class="text-muted small text-uppercase" style="font-size:.7rem">Rejected</div>
            <div class="fs-4 fw-bold text-danger"><?= (int)$prc['rejected'] ?></div>
          </div>
        </div>
      </div>

      <!-- Units & Registrations -->
      <h6 class="fw-semibold mb-2 mt-4"><i class="bi bi-people me-1"></i>Units &amp; Registrations</h6>
      <div class="row g-2 mb-2">
        <div class="col-6 col-md-3">
          <a href="/institution/registrations?event_id=<?= (int)$event['id'] ?>" class="text-decoration-none">
            <div class="border rounded-3 p-3 h-100 text-center">
              <div class="text-muted small text-uppercase" style="font-size:.7rem">Units</div>
              <div class="fs-4 fw-bold"><?= $uc ?></div>
            </div>
          </a>
        </div>
        <div class="col-6 col-md-3">
          <div class="border rounded-3 p-3 h-100 text-center">
            <div class="text-muted small text-uppercase" style="font-size:.7rem">Submitted</div>
            <div class="fs-4 fw-bold text-info-emphasis"><?= (int)$rc['submitted'] ?></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="border rounded-3 p-3 h-100 text-center">
            <div class="text-muted small text-uppercase" style="font-size:.7rem">Total Regs</div>
            <div class="fs-4 fw-bold"><?= (int)$rc['total'] ?></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="border rounded-3 p-3 h-100 text-center">
            <div class="text-muted small text-uppercase" style="font-size:.7rem">Approved</div>
            <div class="fs-4 fw-bold text-success"><?= (int)$rc['approved'] ?></div>
          </div>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2 small">
        <span class="badge bg-secondary">Draft <?= (int)$rc['draft'] ?></span>
        <span class="badge bg-warning text-dark">Pending <?= (int)$rc['pending'] ?></span>
        <span class="badge bg-success">Approved <?= (int)$rc['approved'] ?></span>
        <span class="badge bg-danger">Rejected <?= (int)$rc['rejected'] ?></span>
        <span class="badge bg-info">Returned <?= (int)$rc['returned'] ?></span>
      </div>
    </div>
  </div>
</div>

<!-- ===== Action cards (5 in a row) ===== -->
<h6 class="fw-semibold mb-3 mt-4">Actions</h6>
<div class="row g-3 row-cols-2 row-cols-md-3 row-cols-xl-5">
  <?php if (!in_array($event['status'], ['approved', 'completed', 'cancelled'])): ?>
    <div class="col">
      <a href="/institution/events/<?= $event['id'] ?>/edit" class="text-decoration-none">
        <div class="sms-card p-4 h-100 text-center sms-hover-lift">
          <div class="display-6 text-primary mb-2"><i class="bi bi-sliders"></i></div>
          <h6 class="fw-bold mb-0">Manage Event</h6>
        </div>
      </a>
    </div>
  <?php endif; ?>
  <div class="col">
    <a href="/institution/registrations?event_id=<?= (int)$event['id'] ?>" class="text-decoration-none">
      <div class="sms-card p-4 h-100 text-center sms-hover-lift">
        <div class="display-6 text-info mb-2"><i class="bi bi-people"></i></div>
        <h6 class="fw-bold mb-0">Athlete Registrations</h6>
      </div>
    </a>
  </div>
  <div class="col">
    <a href="/institution/events/<?= e($eh) ?>/unit-users" class="text-decoration-none">
      <div class="sms-card p-4 h-100 text-center sms-hover-lift">
        <div class="display-6 text-info mb-2"><i class="bi bi-person-gear"></i></div>
        <h6 class="fw-bold mb-0">Unit Users</h6>
      </div>
    </a>
  </div>
  <div class="col">
    <a href="/institution/events/<?= e($eh) ?>/staff-users" class="text-decoration-none">
      <div class="sms-card p-4 h-100 text-center sms-hover-lift">
        <div class="display-6 text-info mb-2"><i class="bi bi-person-vcard"></i></div>
        <h6 class="fw-bold mb-0">Event Staff</h6>
      </div>
    </a>
  </div>
  <div class="col">
    <a href="/institution/events/<?= e($eh) ?>/team-registrations" class="text-decoration-none">
      <div class="sms-card p-4 h-100 text-center sms-hover-lift">
        <div class="display-6 text-info mb-2"><i class="bi bi-people-fill"></i></div>
        <h6 class="fw-bold mb-0">Team Entries</h6>
      </div>
    </a>
  </div>
  <div class="col">
    <a href="/institution/events/<?= e($eh) ?>/reports" class="text-decoration-none">
      <div class="sms-card p-4 h-100 text-center sms-hover-lift">
        <div class="display-6 text-success mb-2"><i class="bi bi-bar-chart"></i></div>
        <h6 class="fw-bold mb-0">Reports</h6>
      </div>
    </a>
  </div>
  <div class="col">
    <a href="/institution/events/<?= e($eh) ?>/grievances" class="text-decoration-none">
      <div class="sms-card p-4 h-100 text-center sms-hover-lift position-relative">
        <?php $gOpen = (int)($event['grievance_open'] ?? 0); $gTot = (int)($event['grievance_total'] ?? 0); if ($gTot > 0): ?>
          <span class="badge rounded-pill <?= $gOpen > 0 ? 'bg-danger' : 'bg-secondary' ?> position-absolute top-0 end-0 m-2">
            <?= $gOpen > 0 ? $gOpen . ' open' : $gTot ?>
          </span>
        <?php endif; ?>
        <div class="display-6 text-warning mb-2"><i class="bi bi-chat-square-dots"></i></div>
        <h6 class="fw-bold mb-0">Grievances</h6>
      </div>
    </a>
  </div>
</div>
