<?php
/**
 * Public results — a single medal-tally section (opened from an event home card),
 * with a Back button to the event home. Expects: $event, $base, $section
 * (units|events|agetop|qual), $section_title, plus the tally arrays.
 */
$pageTitle = ($event['name'] ?? 'Results') . ' — ' . ($section_title ?? '');
$base = $base ?? '';
$eid  = (int)($event['id'] ?? 0);
?>
<div class="container">
  <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a href="<?= e($base) ?>/event/<?= $eid ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <?php if (!empty($event['logo'])): ?><img src="<?= e($event['logo']) ?>" alt="" style="height:36px" class="rounded"><?php endif; ?>
    <h4 class="fw-bold mb-0"><?= e($event['name']) ?></h4>
    <span class="badge bg-primary-subtle text-primary-emphasis"><?= e($section_title ?? '') ?></span>
    <button type="button" class="btn btn-sm btn-outline-primary ms-auto" onclick="location.reload()">
      <i class="bi bi-arrow-clockwise me-1"></i>Refresh
    </button>
  </div>

  <div class="pr-card p-3 pr-cardify">
    <?php
      $showPrint    = false;
      $isPublicView = true;
      $auto_refresh = false;
      $only_section = $section ?? '';
      require APP_ROOT . '/views/partials/track-medal-tabs.php';
    ?>
  </div>
</div>
