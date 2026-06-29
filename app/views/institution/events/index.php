<?php $pageTitle = 'My Events'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-event me-2"></i>Events</h5>
  <a href="/institution/events/create" class="btn btn-primary">
    <i class="bi bi-plus-circle me-2"></i>Create Event
  </a>
</div>

<?php if (empty($events)): ?>
<div class="sms-empty-state">
  <i class="bi bi-calendar-plus"></i>
  <h5>No Events Yet</h5>
  <p>Create your first event to start managing athletes and competitions.</p>
  <a href="/institution/events/create" class="btn btn-primary">Create Event</a>
</div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($events as $event): ?>
  <div class="col-md-6 col-xl-4">
    <div class="sms-event-card">
      <!-- Status Bar -->
      <div class="sms-event-status-bar status-<?= $event['status'] ?>"></div>

      <div class="sms-event-card-body">
        <div class="d-flex align-items-start gap-3 mb-3">
          <?php if ($event['logo']): ?>
            <img src="<?= e($event['logo']) ?>" alt="Logo" width="48" height="48"
                 class="rounded-3" style="object-fit:cover;flex-shrink:0">
          <?php else: ?>
            <div class="sms-event-icon"><i class="bi bi-trophy"></i></div>
          <?php endif; ?>
          <div class="flex-grow-1 min-w-0">
            <h6 class="fw-bold mb-1 text-truncate"><?= e($event['name']) ?></h6>
            <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($event['location']) ?></small>
          </div>
          <?= statusBadge($event['status']) ?>
        </div>

        <div class="row g-2 text-muted small mb-3">
          <div class="col-6">
            <i class="bi bi-calendar3 me-1 text-primary"></i>
            <strong>Event:</strong><br>
            <?= formatDate($event['event_date_from']) ?> – <?= formatDate($event['event_date_to']) ?>
          </div>
          <div class="col-6">
            <i class="bi bi-person-plus me-1 text-success"></i>
            <strong>Registration:</strong><br>
            <?= formatDate($event['reg_date_from']) ?> – <?= formatDate($event['reg_date_to']) ?>
          </div>
        </div>

        <!-- Registration counters -->
        <?php
          $applicants = (int)($event['applicants_count'] ?? 0);
          $registered = (int)($event['registered_count'] ?? 0);
          $pending    = (int)($event['pending_count']    ?? 0);
        ?>
        <a href="/institution/registrations?event_id=<?= (int)$event['id'] ?>"
           class="d-block text-decoration-none mb-3">
          <div class="row g-2 text-center small">
            <div class="col-4">
              <div class="border rounded-3 p-2 bg-light-subtle">
                <div class="fw-bold text-primary fs-5"><?= $applicants ?></div>
                <div class="text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.04em">Applicants</div>
              </div>
            </div>
            <div class="col-4">
              <div class="border rounded-3 p-2 bg-light-subtle">
                <div class="fw-bold text-success fs-5"><?= $registered ?></div>
                <div class="text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.04em">Registered</div>
              </div>
            </div>
            <div class="col-4">
              <div class="border rounded-3 p-2 bg-light-subtle">
                <div class="fw-bold text-warning fs-5"><?= $pending ?></div>
                <div class="text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.04em">Pending</div>
              </div>
            </div>
          </div>
        </a>

        <?php $eventHash = hid_event((int)$event['id']); ?>
        <div class="d-flex gap-2 mb-2">
          <a href="/institution/events/<?= $event['id'] ?>/view" class="btn btn-sm btn-outline-secondary flex-fill">
            <i class="bi bi-eye me-1"></i>View
          </a>
          <a href="/institution/events/<?= $event['id'] ?>/edit" class="btn btn-sm btn-outline-primary flex-fill">
            <i class="bi bi-sliders me-1"></i>Manage Event
          </a>
        </div>
        <div class="d-flex gap-2">
          <a href="/institution/events/<?= e($eventHash) ?>/reports" class="btn btn-sm btn-outline-success flex-fill">
            <i class="bi bi-bar-chart me-1"></i>Reports
          </a>
          <a href="/institution/events/<?= e($eventHash) ?>/grievances" class="btn btn-sm btn-outline-warning flex-fill position-relative">
            <i class="bi bi-chat-square-dots me-1"></i>Grievances
            <?php $gOpen = (int)($event['grievance_open'] ?? 0); $gTot = (int)($event['grievance_total'] ?? 0); if ($gTot > 0): ?>
              <span class="badge rounded-pill <?= $gOpen > 0 ? 'bg-danger' : 'bg-secondary' ?> ms-1">
                <?= $gOpen > 0 ? $gOpen . ' open' : $gTot ?>
              </span>
            <?php endif; ?>
          </a>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
