<?php
$pageTitle = 'Institution Dashboard';
$incomplete = !($institution['profile_completed'] ?? false);
$pendingEvents = array_filter($events, fn($e) => $e['status'] === 'pending_approval');
$approvedEvents = array_filter($events, fn($e) => $e['status'] === 'approved');
?>

<?php if ($incomplete): ?>
<div class="alert alert-warning d-flex align-items-center gap-3 mb-4" role="alert">
  <i class="bi bi-exclamation-triangle-fill fs-4 flex-shrink-0"></i>
  <div>
    <strong>Profile Incomplete.</strong> Please complete your institution profile to unlock all features.
    <a href="/institution/profile" class="alert-link ms-2">Complete Profile →</a>
  </div>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-4">
  <div class="d-flex align-items-center gap-3">
    <?php if ($institution['logo']): ?>
      <img src="<?= e($institution['logo']) ?>" alt="Logo" class="rounded-circle" width="56" height="56" style="object-fit:cover">
    <?php else: ?>
      <div class="sms-avatar sms-avatar-lg"><?= avatarInitials($institution['name']) ?></div>
    <?php endif; ?>
    <div>
      <h4 class="mb-0 fw-bold"><?= e($institution['name']) ?></h4>
      <small class="text-muted"><?= e($institution['type_name'] ?? 'Institution') ?></small>
    </div>
  </div>
  <a href="/institution/events/create" class="btn btn-primary <?= $incomplete ? 'disabled' : '' ?>">
    <i class="bi bi-plus-circle me-2"></i>New Event
  </a>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="sms-stat-card">
      <div class="sms-stat-icon bg-primary-subtle text-primary"><i class="bi bi-calendar-event"></i></div>
      <div class="sms-stat-body">
        <div class="sms-stat-value"><?= count($events) ?></div>
        <div class="sms-stat-label">Total Events</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="sms-stat-card">
      <div class="sms-stat-icon bg-success-subtle text-success"><i class="bi bi-check-circle"></i></div>
      <div class="sms-stat-body">
        <div class="sms-stat-value"><?= count($approvedEvents) ?></div>
        <div class="sms-stat-label">Approved Events</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="sms-stat-card">
      <div class="sms-stat-icon bg-warning-subtle text-warning"><i class="bi bi-hourglass-split"></i></div>
      <div class="sms-stat-body">
        <div class="sms-stat-value"><?= count($pendingEvents) ?></div>
        <div class="sms-stat-label">Pending Approval</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="sms-stat-card">
      <div class="sms-stat-icon bg-info-subtle text-info">
        <?= $institution['validity_to'] ? '<i class="bi bi-shield-check"></i>' : '<i class="bi bi-shield-exclamation"></i>' ?>
      </div>
      <div class="sms-stat-body">
        <div class="sms-stat-value" style="font-size:1rem">
          <?= $institution['validity_to'] ? formatDate($institution['validity_to']) : 'Pending' ?>
        </div>
        <div class="sms-stat-label">Validity Till</div>
      </div>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <a href="/institution/profile" class="sms-action-card text-decoration-none">
      <div class="sms-action-icon text-primary"><i class="bi bi-building"></i></div>
      <div>
        <div class="fw-semibold">Institution Profile</div>
        <small class="text-muted">Update logo, type &amp; registration</small>
      </div>
      <i class="bi bi-chevron-right ms-auto text-muted"></i>
    </a>
  </div>
  <div class="col-md-4">
    <a href="/institution/events" class="sms-action-card text-decoration-none">
      <div class="sms-action-icon text-success"><i class="bi bi-calendar-event"></i></div>
      <div>
        <div class="fw-semibold">Manage Events</div>
        <small class="text-muted">Create and manage your events</small>
      </div>
      <i class="bi bi-chevron-right ms-auto text-muted"></i>
    </a>
  </div>
  <div class="col-md-4">
    <a href="/institution/staff" class="sms-action-card text-decoration-none">
      <div class="sms-action-icon text-info"><i class="bi bi-people"></i></div>
      <div>
        <div class="fw-semibold">Staff Management</div>
        <small class="text-muted">Add and manage staff roles</small>
      </div>
      <i class="bi bi-chevron-right ms-auto text-muted"></i>
    </a>
  </div>
</div>

<!-- Recent Events Table -->
<?php if ($events): ?>
<div class="sms-card">
  <div class="sms-card-header">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-event me-2"></i>Recent Events</h6>
    <a href="/institution/events" class="btn btn-sm btn-outline-primary">View All</a>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Event Name</th>
          <th>Dates</th>
          <th>Location</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (array_slice($events, 0, 5) as $event): ?>
        <tr>
          <td class="fw-medium"><?= e($event['name']) ?></td>
          <td class="text-muted small">
            <?= formatDate($event['event_date_from']) ?> – <?= formatDate($event['event_date_to']) ?>
          </td>
          <td class="text-muted small"><?= e($event['location']) ?></td>
          <td><?= statusBadge($event['status']) ?></td>
          <td>
            <a href="/institution/events/<?= $event['id'] ?>/view" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-eye"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="sms-empty-state">
  <i class="bi bi-calendar-plus"></i>
  <h5>No Events Yet</h5>
  <p>Create your first event to get started.</p>
  <a href="/institution/events/create" class="btn btn-primary <?= $incomplete ? 'disabled' : '' ?>">
    <i class="bi bi-plus-circle me-2"></i>Create Event
  </a>
</div>
<?php endif; ?>
