<?php
/**
 * Unit portal — Medal Tally (published results only). Tabbed: Unit-wise Points
 * and Event-wise Winners. Read-only (no print / no edit).
 * Expects: $event, $unit_tally, $events, $unit_medals.
 */
$pageTitle = 'Medal Tally — ' . ($event['name'] ?? '');
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <h5 class="mb-0 fw-bold"><i class="bi bi-award me-2"></i>Medal Tally</h5>
  <span class="text-muted small"><?= e($event['name'] ?? '') ?></span>
  <button type="button" class="btn btn-sm btn-primary ms-auto" onclick="location.reload()">
    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
  </button>
</div>

<?= flashBag() ?>

<?php
  $showPrint = false;
  $auto_refresh = false;   // manual Refresh button instead of a 60s auto-reload
  require APP_ROOT . '/views/partials/track-medal-tabs.php';
?>
