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
</div>

<?= flashBag() ?>

<?php
  $showPrint = true;
  $printBase = '/event-staff/result-reports/track-medal/print';
  require APP_ROOT . '/views/partials/track-medal-tabs.php';
?>
