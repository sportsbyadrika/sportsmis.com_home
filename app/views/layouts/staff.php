<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'Staff Portal') ?> – SportsMIS®</title>
  <link rel="icon" href="/assets/img/favicon.ico">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/assets/css/app.css?v=<?= @filemtime(PUBLIC_ROOT . '/assets/css/app.css') ?: time() ?>" rel="stylesheet">
</head>
<body class="sms-body">

<?php
  $st  = \Core\Auth::eventStaff() ?? [];
  $ev  = $event ?? null;
  $ecd = $ev['event_code'] ?? '';
  $priv = $st['privileges'] ?? [];
?>

<nav class="navbar navbar-expand-lg sms-navbar sticky-top">
  <div class="container-fluid px-4">
    <a class="navbar-brand d-flex align-items-center gap-2" href="/event-staff/dashboard">
      <img src="/assets/img/sba-logo.png" alt="SportsMIS" height="36">
      <span class="fw-bold">SportsMIS<sup style="font-size:.6em">®</sup> · Staff Portal</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#staffNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="staffNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link <?= activeNav('/event-staff/dashboard') ?>" href="/event-staff/dashboard">
            <i class="bi bi-speedometer2 me-1"></i>Dashboard
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= activeNav('/event-staff/search') ?>" href="/event-staff/search">
            <i class="bi bi-search me-1"></i>Search
          </a>
        </li>
        <?php if (in_array('team_entry', $priv, true)
                  && in_array('event_staff', \eventTeamEntryMethods($ev), true)): ?>
          <li class="nav-item">
            <a class="nav-link <?= activeNav('/team-entry') ?>" href="/team-entry">
              <i class="bi bi-people me-1"></i>Team Entry
            </a>
          </li>
        <?php endif; ?>
        <?php if (in_array('lane_allocation', $priv, true)): ?>
          <li class="nav-item">
            <a class="nav-link <?= activeNav('/lane-allocation') ?>" href="/lane-allocation">
              <i class="bi bi-bullseye me-1"></i>Lane Allocation
            </a>
          </li>
        <?php endif; ?>
        <?php if (in_array('scoring', $priv, true)): ?>
          <li class="nav-item">
            <a class="nav-link <?= activeNav('/event-staff/scoring') ?>" href="/event-staff/scoring">
              <i class="bi bi-pencil-square me-1"></i>Scoring
            </a>
          </li>
        <?php endif; ?>
        <?php if (in_array('result_reports', $priv, true)): ?>
          <li class="nav-item">
            <a class="nav-link <?= activeNav('/event-staff/result-reports') ?>" href="/event-staff/result-reports">
              <i class="bi bi-trophy me-1"></i>Result Reports
            </a>
          </li>
        <?php endif; ?>
      </ul>

      <div class="d-none d-lg-flex align-items-center me-3 px-3 py-1 rounded-3 bg-primary-subtle text-primary-emphasis">
        <i class="bi bi-hash me-1"></i>
        <span class="small me-1">Event Code:</span>
        <strong><?= e($ecd) ?></strong>
      </div>

      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item dropdown">
          <a class="nav-link d-flex align-items-center gap-2 sms-avatar-trigger" href="#"
             role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="sms-avatar"><?= avatarInitials($st['name'] ?? $st['email'] ?? 'S') ?></div>
            <span class="d-none d-lg-inline text-truncate" style="max-width:180px">
              <?= e($st['name'] ?? $st['email'] ?? '') ?>
            </span>
            <i class="bi bi-chevron-down small"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end sms-dropdown shadow-sm">
            <li>
              <h6 class="dropdown-header">
                <?= e($st['email'] ?? '') ?>
                <br><small class="text-muted fw-normal">Event Staff</small>
              </h6>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#staffChangePassword">
              <i class="bi bi-key me-2"></i>Change Password
            </a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="/event-staff/logout"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="d-lg-none bg-primary-subtle text-primary-emphasis px-3 py-2 small">
  <i class="bi bi-hash me-1"></i><strong>Event Code:</strong> <?= e($ecd) ?>
  <?php if (!empty($ev['name'])): ?>
    <span class="text-muted ms-2">· <?= e($ev['name']) ?></span>
  <?php endif; ?>
</div>

<main class="sms-main">
  <div class="container-fluid px-4 py-4">
    <?= flashBag() ?>
    <?php require $content; ?>
  </div>
</main>

<footer class="sms-footer mt-auto">
  <div class="container-fluid px-4">
    <div class="small text-muted py-3">
      &copy; <?= date('Y') ?>
      <a href="https://sportsbya.com" target="_blank" rel="noopener" class="text-decoration-none">SportsByA Tech (OPC) Private Limited</a>
      &middot; Powered by <strong>SportsMIS<sup style="font-size:.7em">&reg;</sup></strong>
      &middot; Staff Portal
    </div>
  </div>
</footer>

<div class="modal fade" id="staffChangePassword" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="/event-staff/password/change">
        <?= csrf() ?>
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-key me-2"></i>Change Password</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="password" class="form-control" minlength="8" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="password_confirmation" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
