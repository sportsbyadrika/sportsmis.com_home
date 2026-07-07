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

  <?php $authEvents = $active_events ?? []; ?>
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
    <div class="sms-auth-form-panel<?= !empty($authEvents) ? ' with-events' : '' ?>">
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

      <?php if (!empty($authEvents)): ?>
      <!-- ── Active Events band — full width of the right panel (left-panel edge → window right) ── -->
      <div class="sms-auth-events-band">
    <div class="aeb-head">
      <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-calendar2-event me-2"></i>Active Events</h6>
      <div class="d-flex gap-2">
        <button type="button" class="aeb-nav-btn" id="aebPrev" aria-label="Scroll back"><i class="bi bi-chevron-left"></i></button>
        <button type="button" class="aeb-nav-btn" id="aebNext" aria-label="Scroll forward"><i class="bi bi-chevron-right"></i></button>
      </div>
    </div>
    <div class="aeb-scroller" id="aebScroller">
      <?php foreach ($authEvents as $ev):
        $canAthlete = !empty($ev['allow_athlete_registration']);
        $canInst    = !empty($ev['allow_institution_join_request']);
        $from = !empty($ev['event_date_from']) ? formatDate($ev['event_date_from'], 'd M Y') : '';
        $to   = !empty($ev['event_date_to'])   ? formatDate($ev['event_date_to'],   'd M Y') : '';
      ?>
        <div class="aeb-card">
          <div class="d-flex align-items-center gap-2">
            <?php if (!empty($ev['logo'])): ?>
              <img src="<?= e($ev['logo']) ?>" alt="" class="aeb-logo">
            <?php else: ?>
              <span class="aeb-logo"><i class="bi bi-calendar-event"></i></span>
            <?php endif; ?>
            <div class="aeb-name" title="<?= e($ev['name']) ?>"><?= e($ev['name']) ?></div>
          </div>
          <div class="aeb-meta">
            <?php if (!empty($ev['location'])): ?>
              <div><i class="bi bi-geo-alt me-1"></i><?= e($ev['location']) ?></div>
            <?php endif; ?>
            <?php if ($from || $to): ?>
              <div><i class="bi bi-calendar3 me-1"></i><?= e($from) ?><?= ($from && $to && $from !== $to) ? ' – ' . e($to) : '' ?></div>
            <?php endif; ?>
          </div>
          <div class="d-flex flex-wrap gap-1">
            <?php if ($canAthlete): ?>
              <span class="badge bg-info-subtle text-info-emphasis" style="font-size:.65rem">
                <i class="bi bi-person-arms-up me-1"></i>Athlete
              </span>
            <?php endif; ?>
            <?php if ($canInst): ?>
              <span class="badge bg-warning-subtle text-warning-emphasis" style="font-size:.65rem">
                <i class="bi bi-building me-1"></i>Institution
              </span>
            <?php endif; ?>
            <?php if (!$canAthlete && !$canInst): ?>
              <span class="badge bg-secondary-subtle text-secondary" style="font-size:.65rem">Registration closed</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <script>
  (function () {
    var s = document.getElementById('aebScroller');
    if (!s) return;
    var prev = document.getElementById('aebPrev');
    var next = document.getElementById('aebNext');
    var step = function () { return Math.max(s.clientWidth * 0.9, 240); };
    if (prev) prev.addEventListener('click', function () { s.scrollBy({ left: -step(), behavior: 'smooth' }); });
    if (next) next.addEventListener('click', function () { s.scrollBy({ left:  step(), behavior: 'smooth' }); });
    var sync = function () {
      var overflow = s.scrollWidth > s.clientWidth + 4;
      [prev, next].forEach(function (b) { if (b) b.style.display = overflow ? '' : 'none'; });
    };
    sync();
    window.addEventListener('resize', sync);
  })();
      </script>
      <?php endif; ?>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
