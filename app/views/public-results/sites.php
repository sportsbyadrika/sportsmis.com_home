<?php $pageTitle = 'Public Results'; ?>
<div class="container">
  <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <h4 class="fw-bold mb-0"><i class="bi bi-trophy me-2"></i>Public Result Sites</h4>
    <span class="badge bg-secondary-subtle text-secondary-emphasis"><?= count($sites) ?> site<?= count($sites) === 1 ? '' : 's' ?></span>
  </div>
  <p class="text-muted small mb-4">Pick a result site to see its events and published results.</p>

  <?php if (empty($sites)): ?>
    <div class="pr-card p-4 text-center text-muted">No public result sites are available yet.</div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($sites as $s): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <a href="/<?= e(rawurlencode((string)$s['slug'])) ?>" class="pr-card ev-card p-3 h-100 text-center">
            <?php if (!empty($s['logo'])): ?>
              <img src="<?= e($s['logo']) ?>" alt="" class="ev-logo mb-2">
            <?php else: ?>
              <div class="ev-logo mb-2 d-inline-flex align-items-center justify-content-center rounded-circle bg-light">
                <i class="bi bi-trophy fs-3 text-secondary"></i>
              </div>
            <?php endif; ?>
            <div class="fw-semibold small"><?= e($s['title'] ?? $s['slug']) ?></div>
            <div class="text-muted small mt-1"><?= (int)$s['event_count'] ?> event<?= (int)$s['event_count'] === 1 ? '' : 's' ?></div>
            <div class="mt-2"><span class="badge bg-primary-subtle text-primary-emphasis">View Results <i class="bi bi-arrow-right"></i></span></div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
