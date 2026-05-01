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
<div class="d-flex align-items-center justify-content-between mb-3" id="activeEvents">
  <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-event me-2"></i>Active Events</h6>
  <span class="badge bg-secondary"><?= count($active_events ?? []) ?></span>
</div>

<?php if (empty($active_events)): ?>
  <div class="sms-card p-4 text-center text-muted small mb-4">
    <i class="bi bi-calendar2-x fs-3 d-block mb-2"></i>
    No active events open for registration right now. Check back later.
  </div>
<?php else: ?>
  <div class="row g-3 mb-4">
    <?php foreach ($active_events as $ev): $myReg = $reg_by_event[(int)$ev['id']] ?? null; ?>
      <div class="col-12 col-md-6 col-xl-4">
        <div class="sms-card h-100 d-flex flex-column">
          <div class="p-3 d-flex align-items-start gap-3 border-bottom">
            <?php if (!empty($ev['logo'])): ?>
              <img src="<?= e($ev['logo']) ?>" alt="" width="56" height="56"
                   class="rounded-3 flex-shrink-0" style="object-fit:cover;border:1px solid #e2e8f0;background:#fff">
            <?php else: ?>
              <div class="sms-event-icon sms-event-icon-lg flex-shrink-0"><i class="bi bi-trophy"></i></div>
            <?php endif; ?>
            <div class="flex-grow-1 min-w-0">
              <div class="fw-semibold text-truncate" title="<?= e($ev['name']) ?>"><?= e($ev['name']) ?></div>
              <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                <?= statusBadge($ev['status']) ?>
                <small class="text-muted text-truncate"><?= e($ev['institution_name']) ?></small>
              </div>
            </div>
          </div>
          <div class="p-3 small flex-grow-1">
            <div class="d-flex align-items-start gap-2 mb-2">
              <i class="bi bi-geo-alt text-primary mt-1"></i>
              <div class="flex-grow-1"><span class="text-muted d-block">Venue</span><strong><?= e($ev['location']) ?></strong></div>
            </div>
            <div class="d-flex align-items-start gap-2 mb-2">
              <i class="bi bi-calendar3 text-success mt-1"></i>
              <div class="flex-grow-1"><span class="text-muted d-block">Event Dates</span><strong><?= formatDate($ev['event_date_from']) ?> – <?= formatDate($ev['event_date_to']) ?></strong></div>
            </div>
            <div class="d-flex align-items-start gap-2 <?= $myReg ? 'mb-2' : '' ?>">
              <i class="bi bi-person-plus text-warning mt-1"></i>
              <div class="flex-grow-1"><span class="text-muted d-block">Registration</span><strong><?= formatDate($ev['reg_date_from']) ?> – <?= formatDate($ev['reg_date_to']) ?></strong></div>
            </div>

            <?php if ($myReg): ?>
              <div class="border-top pt-2 mt-2">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="text-muted">Date of Application</span>
                  <strong><?= formatDate($myReg['registered_at'], 'd M Y') ?></strong>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="text-muted">Application Status</span>
                  <?php
                    $appStatus = $myReg['admin_review_status']
                      ?? ($myReg['status'] === 'pending' ? 'pending' : ($myReg['status'] ?? 'draft'));
                    echo statusBadge($appStatus);
                  ?>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                  <span class="text-muted">Payment Status</span>
                  <?= statusBadge($myReg['payment_status'] ?? 'pending') ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
          <div class="p-3 pt-0 d-flex gap-2 mt-auto">
            <a href="/athlete/events/<?= (int)$ev['id'] ?>" class="btn btn-sm btn-outline-primary flex-fill">
              <i class="bi bi-info-circle me-1"></i>Details
            </a>
            <?php if ($myReg): ?>
              <a href="/athlete/registrations/<?= (int)$myReg['id'] ?>" class="btn btn-sm btn-outline-secondary flex-fill">
                <i class="bi bi-eye me-1"></i>View
              </a>
              <a href="/athlete/events/<?= (int)$ev['id'] ?>/register" class="btn btn-sm btn-primary flex-fill">
                <i class="bi bi-pencil me-1"></i>Edit
              </a>
            <?php else: ?>
              <a href="/athlete/events/<?= (int)$ev['id'] ?>/register" class="btn btn-sm btn-primary flex-fill">
                <i class="bi bi-check-circle me-1"></i>Register
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
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
