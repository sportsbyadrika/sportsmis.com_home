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

      <?php if (!empty($sportsBreakdown)): ?>
      <div class="mt-4">
        <h6 class="fw-semibold mb-2"><i class="bi bi-trophy me-1"></i>Sports &amp; Registrations</h6>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Sport</th>
                <th>Category</th>
                <th class="text-end">Sport Events</th>
                <th class="text-end">Registrations</th>
                <th class="text-end">Approved</th>
              </tr>
            </thead>
            <tbody>
              <?php
                $totSE = 0; $totReg = 0; $totApp = 0;
                $lastSport = null;
                foreach ($sportsBreakdown as $row):
                  $totSE  += (int)$row['sport_event_count'];
                  $totReg += (int)$row['registration_count'];
                  $totApp += (int)$row['approved_count'];
                  $sportLabel = ($lastSport === $row['sport_id']) ? '' : e($row['sport_name']);
                  $lastSport = $row['sport_id'];
              ?>
                <tr>
                  <td class="fw-medium"><?= $sportLabel ?></td>
                  <td><?= e($row['category_name']) ?></td>
                  <td class="text-end"><?= (int)$row['sport_event_count'] ?></td>
                  <td class="text-end"><?= (int)$row['registration_count'] ?></td>
                  <td class="text-end text-success fw-medium"><?= (int)$row['approved_count'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
              <tr>
                <th colspan="2" class="text-end">Total</th>
                <th class="text-end"><?= $totSE ?></th>
                <th class="text-end"><?= $totReg ?></th>
                <th class="text-end text-success"><?= $totApp ?></th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
      <?php elseif ($event['sports']): ?>
        <div class="mt-4 text-muted small fst-italic">
          <i class="bi bi-info-circle me-1"></i>Sports configured but breakdown is unavailable.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-4">
    <?php $eventHash = hid_event((int)$event['id']); ?>
    <div class="sms-card p-4">
      <h6 class="fw-semibold mb-3">Actions</h6>
      <div class="d-grid gap-2">
        <?php if (!in_array($event['status'], ['approved', 'completed', 'cancelled'])): ?>
          <a href="/institution/events/<?= $event['id'] ?>/edit" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-2"></i>Edit Event
          </a>
        <?php endif; ?>
        <a href="/institution/registrations?event_id=<?= (int)$event['id'] ?>" class="btn btn-outline-info">
          <i class="bi bi-people me-2"></i>Athlete Registrations
        </a>
        <a href="/institution/events/<?= e($eventHash) ?>/unit-users" class="btn btn-outline-info">
          <i class="bi bi-person-gear me-2"></i>Unit Users
        </a>
        <?php if (!empty($event['team_entry_enabled'])): ?>
        <a href="/institution/events/<?= e($eventHash) ?>/team-registrations" class="btn btn-outline-info">
          <i class="bi bi-people-fill me-2"></i>Team Registrations
        </a>
        <?php endif; ?>
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
