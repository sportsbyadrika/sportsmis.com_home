<?php $pageTitle = ($site['title'] ?? 'Results'); $base = $base ?? ''; ?>
<div class="container">
  <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <h4 class="fw-bold mb-0"><i class="bi bi-trophy me-2"></i>Events</h4>
    <span class="badge bg-secondary-subtle text-secondary-emphasis"><?= count($events) ?> event<?= count($events) === 1 ? '' : 's' ?></span>
    <a href="<?= e($base) ?>/search" class="btn btn-sm btn-warning ms-auto"><i class="bi bi-search me-1"></i>Search by Athlete</a>
  </div>

  <?php if (empty($events)): ?>
    <div class="pr-card p-4 text-center text-muted">No events are available yet.</div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($events as $ev): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <a href="<?= e($base) ?>/event/<?= (int)$ev['id'] ?>" class="pr-card ev-card p-3 h-100 text-center">
            <?php if (!empty($ev['logo'])): ?>
              <img src="<?= e($ev['logo']) ?>" alt="" class="ev-logo mb-2">
            <?php else: ?>
              <div class="ev-logo mb-2 d-inline-flex align-items-center justify-content-center rounded-circle bg-light">
                <i class="bi bi-flag fs-3 text-secondary"></i>
              </div>
            <?php endif; ?>
            <div class="fw-semibold small"><?= e($ev['name']) ?></div>
            <?php if (!empty($ev['event_date_from'])): ?>
              <div class="text-muted small mt-1"><?= e(formatDate($ev['event_date_from'], 'd M Y')) ?></div>
            <?php endif; ?>
            <div class="mt-2"><span class="badge bg-primary-subtle text-primary-emphasis">View Results <i class="bi bi-arrow-right"></i></span></div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
