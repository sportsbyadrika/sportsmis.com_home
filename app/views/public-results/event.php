<?php $pageTitle = ($event['name'] ?? 'Results') . ' — Results'; $base = $base ?? ''; ?>
<div class="container">
  <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a href="<?= e($base) ?: '/' ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Events</a>
    <?php if (!empty($event['logo'])): ?><img src="<?= e($event['logo']) ?>" alt="" style="height:40px" class="rounded"><?php endif; ?>
    <h4 class="fw-bold mb-0"><?= e($event['name']) ?></h4>
    <span class="badge bg-success-subtle text-success-emphasis">Published Results</span>
    <a href="<?= e($base) ?>/search" class="btn btn-sm btn-warning ms-auto"><i class="bi bi-search me-1"></i>Search by Athlete</a>
  </div>

  <div class="pr-card p-3">
    <?php
      // Shared Medal Tally tabs (Unit-wise Points + Event-wise Winners),
      // published-only. No print buttons on the public page.
      $showPrint = false;
      require APP_ROOT . '/views/partials/track-medal-tabs.php';
    ?>
  </div>
</div>
