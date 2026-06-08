<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'SportsMIS®') ?></title>
  <link rel="icon" href="/assets/img/favicon.ico">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body class="sms-auth-body">

  <div class="sms-auth-wrapper">

    <!-- Left panel (branding) -->
    <div class="sms-auth-brand d-none d-lg-flex flex-column">
      <div class="sms-auth-brand-inner flex-grow-1">
        <a href="https://sportsmis.com" class="d-block mb-4">
          <img src="/assets/img/sba-logo.png" alt="SportsMIS" class="sms-auth-logo">
        </a>
        <h1 class="text-white fw-bold mb-1" style="font-size:2.4rem;letter-spacing:.02em">
          SportsMIS<sup style="font-size:1rem;vertical-align:super">®</sup>
        </h1>
        <h2 class="text-white-75 fw-medium mb-3" style="font-size:1.15rem">
          Sports Event Management Platform
        </h2>
        <p class="text-white-50 mb-5">Register athletes, manage events, and track performance — all in one place.</p>
        <div class="d-flex flex-column gap-3">
          <div class="d-flex align-items-center gap-3 text-white-75">
            <div class="sms-auth-feat-icon"><i class="bi bi-building-check"></i></div>
            <span>Institution & Club Management</span>
          </div>
          <div class="d-flex align-items-center gap-3 text-white-75">
            <div class="sms-auth-feat-icon"><i class="bi bi-person-badge"></i></div>
            <span>Athlete Profiles & Registration</span>
          </div>
          <div class="d-flex align-items-center gap-3 text-white-75">
            <div class="sms-auth-feat-icon"><i class="bi bi-calendar-check"></i></div>
            <span>Event Management & Online Payments</span>
          </div>
        </div>
      </div>
      <div class="text-white-50 small text-center pt-3 mt-3"
           style="border-top:1px solid rgba(255,255,255,.15)">
        &copy; <?= date('Y') ?>
        <a href="https://sportsbya.com" target="_blank" rel="noopener"
           class="text-white-75 text-decoration-none fw-medium">SportsByA Tech (OPC) Private Limited</a>
      </div>
    </div>

    <!-- Right panel (form) -->
    <div class="sms-auth-form-panel">
      <div class="sms-auth-form-inner">

        <!-- Mobile logo -->
        <div class="text-center d-lg-none mb-4">
          <a href="https://sportsmis.com">
            <img src="/assets/img/sba-logo.png" alt="SportsMIS" height="50">
          </a>
        </div>

        <?= flashBag() ?>
        <?php require $content; ?>

      </div>
    </div>

  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
