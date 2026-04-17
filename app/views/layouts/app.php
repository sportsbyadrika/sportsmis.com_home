<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'SportsMIS') ?> – SportsMIS</title>
  <link rel="icon" href="/assets/img/favicon.ico">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body class="sms-body">

<!-- ═══════════════════════════════════════════════════════ NAVBAR -->
<nav class="navbar navbar-expand-lg sms-navbar sticky-top">
  <div class="container-fluid px-4">

    <!-- Brand -->
    <a class="navbar-brand d-flex align-items-center gap-2" href="/">
      <img src="/assets/img/sba-logo.png" alt="SportsMIS" height="36">
      <span class="fw-bold">SportsMIS</span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMain">

      <!-- Primary Nav -->
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php if (\Core\Auth::is('institution_admin')): ?>
          <li class="nav-item">
            <a class="nav-link <?= activeNav('/institution/dashboard') ?>" href="/institution/dashboard">
              <i class="bi bi-grid me-1"></i>Dashboard
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= activeNav('/institution/profile') ?>" href="/institution/profile">
              <i class="bi bi-building me-1"></i>Profile
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= activeNav('/institution/events') ?>" href="/institution/events">
              <i class="bi bi-calendar-event me-1"></i>Events
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= activeNav('/institution/staff') ?>" href="/institution/staff">
              <i class="bi bi-people me-1"></i>Staff
            </a>
          </li>

        <?php elseif (\Core\Auth::is('athlete')): ?>
          <li class="nav-item">
            <a class="nav-link <?= activeNav('/athlete/dashboard') ?>" href="/athlete/dashboard">
              <i class="bi bi-grid me-1"></i>Dashboard
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= activeNav('/athlete/profile') ?>" href="/athlete/profile">
              <i class="bi bi-person me-1"></i>My Profile
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= activeNav('/athlete/events') ?>" href="/athlete/events">
              <i class="bi bi-search me-1"></i>Find Events
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= activeNav('/athlete/my-registrations') ?>" href="/athlete/my-registrations">
              <i class="bi bi-list-check me-1"></i>My Registrations
            </a>
          </li>

        <?php elseif (\Core\Auth::is('super_admin')): ?>
          <li class="nav-item">
            <a class="nav-link <?= activeNav('/admin/dashboard') ?>" href="/admin/dashboard">
              <i class="bi bi-speedometer2 me-1"></i>Dashboard
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= activeNav('/admin/institutions') ?>" href="/admin/institutions">
              <i class="bi bi-building me-1"></i>Institutions
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= activeNav('/admin/athletes') ?>" href="/admin/athletes">
              <i class="bi bi-people me-1"></i>Athletes
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= activeNav('/admin/events') ?>" href="/admin/events">
              <i class="bi bi-calendar-event me-1"></i>Events
            </a>
          </li>
        <?php endif; ?>
      </ul>

      <!-- Right: Avatar dropdown -->
      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item dropdown">
          <a class="nav-link d-flex align-items-center gap-2 sms-avatar-trigger" href="#"
             role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="sms-avatar">
              <?= avatarInitials(\Core\Auth::user()['email'] ?? 'U') ?>
            </div>
            <span class="d-none d-lg-inline text-truncate" style="max-width:140px">
              <?= e(\Core\Auth::user()['email'] ?? '') ?>
            </span>
            <i class="bi bi-chevron-down small"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end sms-dropdown shadow-sm">
            <li>
              <h6 class="dropdown-header">
                <?= e(\Core\Auth::user()['email'] ?? '') ?>
                <br><small class="text-muted fw-normal"><?= ucfirst(str_replace('_', ' ', \Core\Auth::role() ?? '')) ?></small>
              </h6>
            </li>
            <li><hr class="dropdown-divider"></li>
            <?php if (\Core\Auth::is('institution_admin')): ?>
              <li><a class="dropdown-item" href="/institution/profile"><i class="bi bi-building me-2"></i>Institution Profile</a></li>
            <?php elseif (\Core\Auth::is('athlete')): ?>
              <li><a class="dropdown-item" href="/athlete/profile"><i class="bi bi-person me-2"></i>My Profile</a></li>
            <?php endif; ?>
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalChangePassword">
              <i class="bi bi-key me-2"></i>Change Password
            </a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="/logout"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
          </ul>
        </li>
      </ul>

    </div>
  </div>
</nav>

<!-- ═══════════════════════════════════════════════════════ MAIN -->
<main class="sms-main">
  <div class="container-fluid px-4 py-4">
    <?= flashBag() ?>
    <?php require $content; ?>
  </div>
</main>

<!-- ═══════════════════════════════════════════════════════ FOOTER -->
<footer class="sms-footer mt-auto">
  <div class="container-fluid px-4">
    <div class="row align-items-center">
      <div class="col-md-6 text-center text-md-start">
        <span class="text-muted small">&copy; <?= date('Y') ?> Sportsbya Tech Pvt. Ltd. All rights reserved.</span>
      </div>
      <div class="col-md-6 text-center text-md-end mt-2 mt-md-0">
        <a href="https://sportsmis.com/privacy" class="text-muted small me-3">Privacy Policy</a>
        <a href="https://sportsmis.com/terms"   class="text-muted small me-3">Terms of Use</a>
        <a href="https://sportsmis.com/contact" class="text-muted small">Contact</a>
      </div>
    </div>
  </div>
</footer>

<!-- Change Password Modal -->
<div class="modal fade" id="modalChangePassword" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="/account/password">
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
