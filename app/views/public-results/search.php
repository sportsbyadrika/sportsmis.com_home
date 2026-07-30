<?php $pageTitle = 'Search by Athlete'; $old = $old ?? []; $base = $base ?? ''; ?>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
      <div class="d-flex align-items-center gap-2 mb-3">
        <a href="<?= e($base) ?: '/' ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Events</a>
        <h4 class="fw-bold mb-0"><i class="bi bi-person-badge me-2"></i>Search by Athlete</h4>
      </div>

      <div class="pr-card p-4">
        <?php if (!empty($error)): ?>
          <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle me-1"></i><?= e($error) ?></div>
        <?php endif; ?>
        <p class="text-muted small mb-3">Enter the athlete's Chest number and date of birth to view their results.</p>
        <form method="POST" action="<?= e($base) ?>/search">
          <div class="mb-3">
            <label class="form-label fw-medium">BIB / Chest Number</label>
            <input type="text" name="chest" class="form-control" maxlength="20" required
                   value="<?= e($old['chest'] ?? '') ?>" placeholder="e.g. 1024">
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Date of Birth</label>
            <input type="date" name="dob" class="form-control" required
                   max="<?= date('Y-m-d') ?>" value="<?= e($old['dob'] ?? '') ?>">
          </div>
          <button type="submit" class="btn btn-warning w-100"><i class="bi bi-search me-1"></i>Find Athlete</button>
        </form>
      </div>
    </div>
  </div>
</div>
