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
  <a href="/athlete/events" class="btn btn-primary <?= !$profileComplete ? 'disabled' : '' ?>">
    <i class="bi bi-search me-2"></i>Find Events
  </a>
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
  <div class="col-md-4">
    <a href="/athlete/profile" class="sms-action-card text-decoration-none">
      <div class="sms-action-icon text-primary"><i class="bi bi-person-badge"></i></div>
      <div>
        <div class="fw-semibold">My Profile</div>
        <small class="text-muted">Update photo, sports &amp; ID proof</small>
      </div>
      <i class="bi bi-chevron-right ms-auto text-muted"></i>
    </a>
  </div>
  <div class="col-md-4">
    <a href="/athlete/events" class="sms-action-card text-decoration-none <?= !$profileComplete ? 'opacity-50 pe-none' : '' ?>">
      <div class="sms-action-icon text-success"><i class="bi bi-search"></i></div>
      <div>
        <div class="fw-semibold">Find Events</div>
        <small class="text-muted">Browse &amp; register for active events</small>
      </div>
      <i class="bi bi-chevron-right ms-auto text-muted"></i>
    </a>
  </div>
  <div class="col-md-4">
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
<?php else: ?>
<div class="sms-empty-state">
  <i class="bi bi-calendar-plus"></i>
  <h5>No Registrations Yet</h5>
  <p>Find and register for upcoming sports events.</p>
  <a href="/athlete/events" class="btn btn-primary <?= !$profileComplete ? 'disabled' : '' ?>">
    <i class="bi bi-search me-2"></i>Browse Events
  </a>
</div>
<?php endif; ?>
