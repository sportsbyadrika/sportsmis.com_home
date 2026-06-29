<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'Unit Portal') ?> – SportsMIS®</title>
  <link rel="icon" href="/assets/img/favicon.ico">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/assets/css/app.css?v=<?= @filemtime(PUBLIC_ROOT . '/assets/css/app.css') ?: time() ?>" rel="stylesheet">
</head>
<body class="sms-body">

<?php
  $uu  = \Core\Auth::unitUser() ?? [];
  $ev  = $event ?? null;
  $ecd = $ev['event_code'] ?? '';
?>

<nav class="navbar navbar-expand-lg sms-navbar sticky-top">
  <div class="container-fluid px-4">
    <a class="navbar-brand d-flex align-items-center gap-2" href="/unit/dashboard">
      <img src="/assets/img/sba-logo.png" alt="SportsMIS" height="36">
      <span class="fw-bold">SportsMIS<sup style="font-size:.6em">®</sup> · Unit Portal</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#unitNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="unitNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link <?= activeNav('/unit/dashboard') ?>" href="/unit/dashboard">
            <i class="bi bi-speedometer2 me-1"></i>Dashboard
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= activeNav('/unit/registrations') ?>" href="/unit/registrations">
            <i class="bi bi-clipboard-data me-1"></i>Registrations
          </a>
        </li>
        <?php if (in_array('unit_user', \eventTeamEntryMethods($ev), true)): ?>
        <li class="nav-item">
          <a class="nav-link <?= activeNav('/team-entry') ?>" href="/team-entry">
            <i class="bi bi-people me-1"></i>Team Entry
          </a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
          <a class="nav-link <?= activeNav('/unit/transactions') ?>" href="/unit/transactions">
            <i class="bi bi-cash-stack me-1"></i>Transactions
          </a>
        </li>
        <?php
          // NOC menu visible when the operator has at least one approved
          // athlete on this event. Resolve the operator's unit IDs from
          // either the direct unit_user session OR the institution-as-unit
          // proxy session — otherwise the NOC menu would silently
          // disappear when an institution admin opens the Unit Console.
          $unitNocVisible = false;
          if (!empty($ev['id'])) {
              $opUnitIds = [];
              if (!empty($uu['id'])) {
                  try { $opUnitIds = \Models\UnitUser::assignmentIds((int)$uu['id']); }
                  catch (\Throwable $e) { $opUnitIds = []; }
              } elseif (!empty($_SESSION['institution_as_unit']['unit_id'])) {
                  $opUnitIds = [(int)$_SESSION['institution_as_unit']['unit_id']];
              }
              if ($opUnitIds) {
                  try { $unitNocVisible = \Models\Noc::approvedCount((int)$ev['id'], $opUnitIds) > 0; }
                  catch (\Throwable $e) { $unitNocVisible = false; }
              }
          }
        ?>
        <?php if ($unitNocVisible): ?>
        <li class="nav-item">
          <a class="nav-link <?= activeNav('/unit/noc') ?>" href="/unit/noc">
            <i class="bi bi-file-earmark-check me-1"></i>NOC
          </a>
        </li>
        <?php endif; ?>
        <?php if (!empty($ev['unit_lane_allocation_enabled'])): ?>
        <li class="nav-item">
          <a class="nav-link <?= activeNav('/lane-allocation') ?>" href="/lane-allocation">
            <i class="bi bi-bullseye me-1"></i>Lane Allocation
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
            <div class="sms-avatar"><?= avatarInitials($uu['name'] ?? $uu['email'] ?? 'U') ?></div>
            <span class="d-none d-lg-inline text-truncate" style="max-width:180px">
              <?= e($uu['name'] ?? $uu['email'] ?? '') ?>
            </span>
            <i class="bi bi-chevron-down small"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end sms-dropdown shadow-sm">
            <li>
              <h6 class="dropdown-header">
                <?= e($uu['email'] ?? '') ?>
                <br><small class="text-muted fw-normal">Unit User</small>
              </h6>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#unitChangePassword">
              <i class="bi bi-key me-2"></i>Change Password
            </a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="/unit/logout"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- Mobile/tablet event-code chip -->
<div class="d-lg-none bg-primary-subtle text-primary-emphasis px-3 py-2 small">
  <i class="bi bi-hash me-1"></i><strong>Event Code:</strong> <?= e($ecd) ?>
  <?php if (!empty($ev['name'])): ?>
    <span class="text-muted ms-2">· <?= e($ev['name']) ?></span>
  <?php endif; ?>
</div>

<main class="sms-main">
  <div class="container-fluid px-4 py-4">
    <?php if (!empty($_SESSION['institution_as_unit'])): ?>
      <div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap gap-2 py-2 mb-3">
        <div>
          <i class="bi bi-info-circle me-1"></i>
          You are acting as a Unit on this event using your <strong>institution login</strong>.
          Changes you make here are tied to your institution's account.
        </div>
        <a href="/institution/participating-events?leave_unit=1"
           class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-box-arrow-left me-1"></i>Switch back to Institution Dashboard
        </a>
      </div>
    <?php endif; ?>
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
      &middot; Unit Portal
    </div>
  </div>
</footer>

<div class="modal fade" id="unitChangePassword" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="/unit/password/change">
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
