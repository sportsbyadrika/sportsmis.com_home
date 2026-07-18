<?php $pageTitle = e($event['name']); ?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="/institution/events" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold">Event Details</h5>
  <?= statusBadge($event['status']) ?>
  <?php if ($event['rejection_reason']): ?>
    <span class="ms-2 text-danger small"><i class="bi bi-exclamation-circle me-1"></i><?= e($event['rejection_reason']) ?></span>
  <?php endif; ?>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="sms-card p-4">
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

      <?php
        $prc = $pr_counts  ?? ['pending'=>0,'approved'=>0,'rejected'=>0];
        $rc  = $reg_counts ?? ['total'=>0,'draft'=>0,'pending'=>0,'approved'=>0,'rejected'=>0,'returned'=>0,'submitted'=>0];
        $uc  = (int)($unit_count ?? 0);
        $eh  = $eventHash ?? hid_event((int)$event['id']);
      ?>

      <!-- Participation Requests -->
      <div class="mt-4">
        <h6 class="fw-semibold mb-2"><i class="bi bi-inbox me-1"></i>Participation Requests</h6>
        <div class="row g-2">
          <div class="col-4">
            <a href="/institution/events/<?= e($eh) ?>/participation-requests"
               class="text-decoration-none">
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
      </div>

      <!-- Units & Registrations -->
      <div class="mt-4">
        <h6 class="fw-semibold mb-2"><i class="bi bi-people me-1"></i>Units &amp; Registrations</h6>
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

  <div class="col-lg-4">
    <?php $eventHash = hid_event((int)$event['id']); ?>
    <div class="sms-card p-4">
      <h6 class="fw-semibold mb-3">Actions</h6>
      <div class="d-grid gap-2">
        <?php if (!in_array($event['status'], ['approved', 'completed', 'cancelled'])): ?>
          <a href="/institution/events/<?= $event['id'] ?>/edit" class="btn btn-outline-primary">
            <i class="bi bi-sliders me-2"></i>Manage Event
          </a>
        <?php endif; ?>
        <a href="/institution/registrations?event_id=<?= (int)$event['id'] ?>" class="btn btn-outline-info">
          <i class="bi bi-people me-2"></i>Athlete Registrations
        </a>
        <a href="/institution/events/<?= e($eventHash) ?>/unit-users" class="btn btn-outline-info">
          <i class="bi bi-person-gear me-2"></i>Unit Users
        </a>
        <a href="/institution/events/<?= e($eventHash) ?>/staff-users" class="btn btn-outline-info">
          <i class="bi bi-person-vcard me-2"></i>Event Staff
        </a>
        <a href="/institution/events/<?= e($eventHash) ?>/team-registrations" class="btn btn-outline-info">
          <i class="bi bi-people-fill me-2"></i>Team Entries
        </a>
        <a href="/institution/events/<?= e($eventHash) ?>/reports" class="btn btn-outline-success">
          <i class="bi bi-bar-chart me-2"></i>Reports
        </a>
        <a href="/institution/events/<?= e($eventHash) ?>/grievances" class="btn btn-outline-warning d-flex align-items-center justify-content-between">
          <span><i class="bi bi-chat-square-dots me-2"></i>Grievances</span>
          <?php $gOpen = (int)($event['grievance_open'] ?? 0); $gTot = (int)($event['grievance_total'] ?? 0); if ($gTot > 0): ?>
            <span class="badge rounded-pill <?= $gOpen > 0 ? 'bg-danger' : 'bg-secondary' ?>">
              <?= $gOpen > 0 ? $gOpen . ' open' : $gTot ?>
            </span>
          <?php endif; ?>
        </a>
      </div>
    </div>
  </div>
</div>
