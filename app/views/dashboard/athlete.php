<?php
$pageTitle   = 'Dashboard';
$active_menu = 'dashboard';
$unitCount   = count($unit_access_cards ?? []);
$staffCount  = count($event_staff_cards ?? []);
?>

<div class="row g-3">

  <!-- ── LEFT RAIL: unified profile + menu ── -->
  <div class="col-12 col-lg-4 col-xl-3">
    <?php require APP_ROOT . '/views/partials/athlete-rail.php'; ?>
  </div>

  <!-- ── MAIN: events + access ── -->
  <div class="col-12 col-lg-8 col-xl-9">

    <!-- Events open for participation (registration-type aware). This single
         list already reflects each event's join/registration status, so there
         is no separate "my participations" list to duplicate it. -->
    <?php require APP_ROOT . '/views/partials/eligible-events.php'; ?>

    <!-- Unit / Club access -->
    <?php if ($unitCount): require APP_ROOT . '/views/partials/unit-access-cards.php'; endif; ?>

    <!-- Event staff access -->
    <?php if ($staffCount): require APP_ROOT . '/views/partials/event-staff-cards.php'; endif; ?>

  </div><!-- /main -->
</div><!-- /row -->
