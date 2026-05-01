<?php
$pageTitle = 'Athlete Dashboard';
$profileComplete = (bool)($athlete['profile_completed'] ?? false);
?>

<?php if (!$profileComplete): ?>
<div class="alert alert-warning d-flex align-items-center gap-3 mb-4" role="alert">
  <i class="bi bi-person-exclamation fs-4 flex-shrink-0"></i>
  <div>
    <strong>Profile Incomplete.</strong> Complete your profile to register for events.
    <a href="/athlete/profile" class="alert-link ms-2">Complete Now →</a>
  </div>
</div>
<?php endif; ?>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-4">
  <div class="d-flex align-items-center gap-3">
    <?php if ($athlete['passport_photo']): ?>
      <img src="<?= e($athlete['passport_photo']) ?>" alt="Photo"
           class="rounded-circle" width="56" height="56" style="object-fit:cover">
    <?php else: ?>
      <div class="sms-avatar sms-avatar-lg"><?= avatarInitials($athlete['name']) ?></div>
    <?php endif; ?>
    <div>
      <h4 class="mb-0 fw-bold"><?= e($athlete['name']) ?></h4>
      <small class="text-muted"><?= ucfirst($athlete['gender'] ?? '') ?>
        <?php if ($athlete['date_of_birth']): ?>
          &nbsp;·&nbsp; <?= ageFromDob($athlete['date_of_birth']) ?> yrs
        <?php endif; ?>
      </small>
    </div>
  </div>
  <?php if ($profileComplete): ?>
    <a href="#activeEvents" class="btn btn-primary"><i class="bi bi-search me-2"></i>Browse Active Events</a>
  <?php else: ?>
    <a href="/athlete/profile" class="btn btn-warning"><i class="bi bi-pencil me-2"></i>Complete Profile</a>
  <?php endif; ?>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="sms-stat-card">
      <div class="sms-stat-icon bg-primary-subtle text-primary"><i class="bi bi-list-check"></i></div>
      <div class="sms-stat-body">
        <div class="sms-stat-value"><?= count($registrations) ?></div>
        <div class="sms-stat-label">Events Registered</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="sms-stat-card">
      <div class="sms-stat-icon bg-success-subtle text-success"><i class="bi bi-check2-circle"></i></div>
      <div class="sms-stat-body">
        <div class="sms-stat-value"><?= count(array_filter($registrations, fn($r) => $r['status'] === 'confirmed')) ?></div>
        <div class="sms-stat-label">Confirmed</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="sms-stat-card">
      <div class="sms-stat-icon bg-warning-subtle text-warning"><i class="bi bi-credit-card"></i></div>
      <div class="sms-stat-body">
        <div class="sms-stat-value"><?= count(array_filter($registrations, fn($r) => $r['payment_status'] === 'pending')) ?></div>
        <div class="sms-stat-label">Payments Pending</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="sms-stat-card">
      <div class="sms-stat-icon bg-info-subtle text-info"><i class="bi bi-person-check"></i></div>
      <div class="sms-stat-body">
        <div class="sms-stat-value"><?= $profileComplete ? '100%' : 'Incomplete' ?></div>
        <div class="sms-stat-label">Profile Status</div>
      </div>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <a href="/athlete/profile" class="sms-action-card text-decoration-none">
      <div class="sms-action-icon text-primary"><i class="bi bi-person-badge"></i></div>
      <div>
        <div class="fw-semibold">My Profile</div>
        <small class="text-muted">Update photo, sports &amp; ID proof</small>
      </div>
      <i class="bi bi-chevron-right ms-auto text-muted"></i>
    </a>
  </div>
  <div class="col-md-6">
    <a href="/athlete/my-registrations" class="sms-action-card text-decoration-none">
      <div class="sms-action-icon text-info"><i class="bi bi-list-check"></i></div>
      <div>
        <div class="fw-semibold">My Registrations</div>
        <small class="text-muted">Track event &amp; payment status</small>
      </div>
      <i class="bi bi-chevron-right ms-auto text-muted"></i>
    </a>
  </div>
</div>

<!-- Active Events (after profile submission) -->
<?php if ($profileComplete): ?>
<div class="sms-card mb-4" id="activeEvents">
  <div class="sms-card-header">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-event me-2"></i>Active Events</h6>
    <span class="badge bg-secondary"><?= count($active_events ?? []) ?></span>
  </div>
  <?php if (empty($active_events)): ?>
    <div class="p-4 text-center text-muted small">
      <i class="bi bi-calendar2-x fs-3 d-block mb-2"></i>
      No active events open for registration right now. Check back later.
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>Event</th>
            <th>Institution</th>
            <th>Venue</th>
            <th>Event Dates</th>
            <th>Registration</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($active_events as $ev): ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php if (!empty($ev['logo'])): ?>
                    <img src="<?= e($ev['logo']) ?>" alt="" width="32" height="32" class="rounded" style="object-fit:cover">
                  <?php else: ?>
                    <div class="sms-event-icon"><i class="bi bi-trophy"></i></div>
                  <?php endif; ?>
                  <div class="fw-medium"><?= e($ev['name']) ?></div>
                </div>
              </td>
              <td class="text-muted small"><?= e($ev['institution_name']) ?></td>
              <td class="text-muted small"><?= e($ev['location']) ?></td>
              <td class="text-muted small"><?= formatDate($ev['event_date_from']) ?> – <?= formatDate($ev['event_date_to']) ?></td>
              <td class="text-muted small"><?= formatDate($ev['reg_date_from']) ?> – <?= formatDate($ev['reg_date_to']) ?></td>
              <td class="text-end">
                <a href="/athlete/events/<?= (int)$ev['id'] ?>" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-info-circle me-1"></i>Details</a>
                <a href="/athlete/events/<?= (int)$ev['id'] ?>/register" class="btn btn-sm btn-primary"><i class="bi bi-check-circle me-1"></i>Register</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Recent Registrations -->
<?php if ($registrations): ?>
<div class="sms-card">
  <div class="sms-card-header">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-list-check me-2"></i>Recent Registrations</h6>
    <a href="/athlete/my-registrations" class="btn btn-sm btn-outline-primary">View All</a>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th>Event</th><th>Sport</th><th>Dates</th><th>Payment</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach (array_slice($registrations, 0, 5) as $reg): ?>
        <tr>
          <td>
            <div class="fw-medium"><?= e($reg['event_name']) ?></div>
            <small class="text-muted"><?= e($reg['institution_name']) ?></small>
          </td>
          <td class="text-muted"><?= e($reg['sport_name']) ?></td>
          <td class="text-muted small">
            <?= formatDate($reg['event_date_from']) ?> – <?= formatDate($reg['event_date_to']) ?>
          </td>
          <td><?= statusBadge($reg['payment_status']) ?></td>
          <td><?= statusBadge($reg['status']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php elseif ($profileComplete): ?>
<div class="sms-empty-state">
  <i class="bi bi-calendar-plus"></i>
  <h5>No Registrations Yet</h5>
  <p>Pick an active event from the list above to get started.</p>
</div>
<?php endif; ?>
