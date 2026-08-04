<?php
/**
 * Athletics / Skating — Medal Tally (staff screen). Tabbed: Unit-wise Points +
 * Event-wise Winners, each with its own Print. Published results only.
 * Expects: $event, $unit_tally, $events, $unit_medals.
 */
$pageTitle = 'Medal Tally — ' . $event['name'];
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/result-reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-award me-2"></i>Medal Tally</h5>
  <span class="badge bg-info-subtle text-info-emphasis">Athletics / Skating</span>
  <button type="button" class="btn btn-sm btn-primary ms-auto" onclick="location.reload()">
    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
  </button>
</div>

<?= flashBag() ?>

<?php
  $showPrint = true;
  $printBase = '/event-staff/result-reports/track-medal/print';
  $auto_refresh = false;   // manual Refresh button instead of a 60s auto-reload
  $show_top_units = true;  // staff-only extra tab: Age-category Top Institutions
  require APP_ROOT . '/views/partials/track-medal-tabs.php';
?>
