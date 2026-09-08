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
  <button class="btn btn-sm btn-outline-secondary ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#ageCfg">
    <i class="bi bi-funnel me-1"></i>Age Category Filter
  </button>
  <button type="button" class="btn btn-sm btn-primary" onclick="location.reload()">
    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
  </button>
</div>

<?= flashBag() ?>

<?php
  $selAgeIds = array_map('intval', $sel_age_ids ?? []);
?>
<div class="collapse mb-3 <?= !empty($selAgeIds) ? 'show' : '' ?>" id="ageCfg">
  <div class="sms-card p-3">
    <form method="POST" action="/event-staff/result-reports/track-medal/age-config">
      <?= csrf() ?>
      <div class="small text-muted mb-2">
        <i class="bi bi-info-circle me-1"></i>Choose which <strong>age categories</strong> the medal tally and result
        reports count. This applies to <strong>all result reports</strong> — this page, the printable report, the
        public results and the unit-user login. Leave every box unticked to count <strong>all</strong> age categories.
      </div>
      <?php if (empty($age_cats)): ?>
        <div class="small text-muted">No age categories found for this event.</div>
      <?php else: ?>
        <div class="d-flex flex-wrap gap-3 mb-2">
          <?php foreach (($age_cats ?? []) as $ac): ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="age_ids[]" value="<?= (int)$ac['id'] ?>"
                     id="ageCfg<?= (int)$ac['id'] ?>" <?= in_array((int)$ac['id'], $selAgeIds, true) ? 'checked' : '' ?>>
              <label class="form-check-label" for="ageCfg<?= (int)$ac['id'] ?>"><?= e($ac['name']) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Save Filter</button>
        <?php if (!empty($selAgeIds)): ?>
          <span class="badge bg-warning-subtle text-warning-emphasis ms-2">
            <i class="bi bi-funnel-fill me-1"></i>Filtered to <?= count($selAgeIds) ?> age categor<?= count($selAgeIds) === 1 ? 'y' : 'ies' ?>
          </span>
        <?php endif; ?>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php
  $showPrint = true;
  $printBase = '/event-staff/result-reports/track-medal/print';
  $auto_refresh = false;   // manual Refresh button instead of a 60s auto-reload
  $show_top_units = true;  // staff-only extra tab: Age-category Top Institutions
  require APP_ROOT . '/views/partials/track-medal-tabs.php';
?>
