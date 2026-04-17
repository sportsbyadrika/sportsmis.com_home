<?php $pageTitle = 'Admin Dashboard'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h4 class="mb-0 fw-bold">Super Admin Dashboard</h4>
    <small class="text-muted">SportsMIS Control Centre</small>
  </div>
  <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">Super Admin</span>
</div>

<!-- Pending Alerts -->
<?php if (count($pending_institutions) || count($pending_athletes) || count($pending_events)): ?>
<div class="alert alert-warning d-flex align-items-start gap-3 mb-4" role="alert">
  <i class="bi bi-exclamation-triangle-fill fs-5 mt-1 flex-shrink-0"></i>
  <div>
    <strong>Action Required:</strong>
    <?php if (count($pending_institutions)): ?>
      <span class="ms-2"><?= count($pending_institutions) ?> institution(s) awaiting verification</span> &nbsp;·&nbsp;
    <?php endif; ?>
    <?php if (count($pending_athletes)): ?>
      <span><?= count($pending_athletes) ?> athlete(s) awaiting verification</span> &nbsp;·&nbsp;
    <?php endif; ?>
    <?php if (count($pending_events)): ?>
      <span><?= count($pending_events) ?> event(s) awaiting approval</span>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Stats Row -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="sms-stat-card">
      <div class="sms-stat-icon bg-warning-subtle text-warning"><i class="bi bi-building-exclamation"></i></div>
      <div class="sms-stat-body">
        <div class="sms-stat-value"><?= count($pending_institutions) ?></div>
        <div class="sms-stat-label">Pending Institutions</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="sms-stat-card">
      <div class="sms-stat-icon bg-warning-subtle text-warning"><i class="bi bi-person-exclamation"></i></div>
      <div class="sms-stat-body">
        <div class="sms-stat-value"><?= count($pending_athletes) ?></div>
        <div class="sms-stat-label">Pending Athletes</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="sms-stat-card">
      <div class="sms-stat-icon bg-warning-subtle text-warning"><i class="bi bi-calendar-x"></i></div>
      <div class="sms-stat-body">
        <div class="sms-stat-value"><?= count($pending_events) ?></div>
        <div class="sms-stat-label">Events for Approval</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="sms-stat-card">
      <div class="sms-stat-icon bg-primary-subtle text-primary"><i class="bi bi-activity"></i></div>
      <div class="sms-stat-body">
        <div class="sms-stat-value">Live</div>
        <div class="sms-stat-label">System Status</div>
      </div>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <a href="/admin/institutions" class="sms-action-card text-decoration-none">
      <div class="sms-action-icon text-primary position-relative">
        <i class="bi bi-building"></i>
        <?php if (count($pending_institutions)): ?>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:10px"><?= count($pending_institutions) ?></span>
        <?php endif; ?>
      </div>
      <div>
        <div class="fw-semibold">Institutions</div>
        <small class="text-muted">Verify &amp; manage institutions</small>
      </div>
      <i class="bi bi-chevron-right ms-auto text-muted"></i>
    </a>
  </div>
  <div class="col-md-4">
    <a href="/admin/athletes" class="sms-action-card text-decoration-none">
      <div class="sms-action-icon text-success position-relative">
        <i class="bi bi-people"></i>
        <?php if (count($pending_athletes)): ?>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:10px"><?= count($pending_athletes) ?></span>
        <?php endif; ?>
      </div>
      <div>
        <div class="fw-semibold">Athletes</div>
        <small class="text-muted">Verify &amp; manage athletes</small>
      </div>
      <i class="bi bi-chevron-right ms-auto text-muted"></i>
    </a>
  </div>
  <div class="col-md-4">
    <a href="/admin/events" class="sms-action-card text-decoration-none">
      <div class="sms-action-icon text-warning position-relative">
        <i class="bi bi-calendar-event"></i>
        <?php if (count($pending_events)): ?>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:10px"><?= count($pending_events) ?></span>
        <?php endif; ?>
      </div>
      <div>
        <div class="fw-semibold">Events</div>
        <small class="text-muted">Approve &amp; manage events</small>
      </div>
      <i class="bi bi-chevron-right ms-auto text-muted"></i>
    </a>
  </div>
</div>

<!-- Pending Institutions Table -->
<?php if ($pending_institutions): ?>
<div class="sms-card mb-4">
  <div class="sms-card-header">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-building-exclamation me-2 text-warning"></i>Pending Institution Verifications</h6>
    <a href="/admin/institutions" class="btn btn-sm btn-outline-warning">Manage All</a>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th>Institution</th><th>SPOC</th><th>Email</th><th>Registered</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($pending_institutions as $reg): ?>
        <tr>
          <td class="fw-medium"><?= e($reg['institution_name']) ?></td>
          <td><?= e($reg['spoc_name']) ?> <small class="text-muted d-block"><?= e($reg['spoc_mobile']) ?></small></td>
          <td class="text-muted"><?= e($reg['email']) ?></td>
          <td class="text-muted small"><?= formatDate($reg['created_at']) ?></td>
          <td>
            <a href="/admin/institutions/<?= $reg['id'] ?>" class="btn btn-sm btn-primary">Review</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Pending Athletes Table -->
<?php if ($pending_athletes): ?>
<div class="sms-card">
  <div class="sms-card-header">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-person-exclamation me-2 text-warning"></i>Pending Athlete Verifications</h6>
    <a href="/admin/athletes" class="btn btn-sm btn-outline-warning">Manage All</a>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th>Name</th><th>Mobile</th><th>Email</th><th>Gender</th><th>Registered</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($pending_athletes as $reg): ?>
        <tr>
          <td class="fw-medium"><?= e($reg['name']) ?></td>
          <td class="text-muted"><?= e($reg['mobile']) ?></td>
          <td class="text-muted"><?= e($reg['email']) ?></td>
          <td class="text-muted"><?= ucfirst($reg['gender']) ?></td>
          <td class="text-muted small"><?= formatDate($reg['created_at']) ?></td>
          <td>
            <a href="/admin/athletes/<?= $reg['id'] ?>" class="btn btn-sm btn-primary">Review</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
