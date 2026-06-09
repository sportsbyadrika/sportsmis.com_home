<?php $pageTitle = 'Event Migrate — Result'; ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <h5 class="mb-0 fw-bold"><i class="bi bi-flag-fill me-2"></i>Event Migrate — Result</h5>
</div>

<div class="sms-card p-3 mb-3 small">
  <div class="d-flex flex-wrap gap-3 align-items-center">
    <span class="badge bg-primary-subtle text-primary-emphasis">
      Source: <?= e($report['src_event']['name'] ?? '—') ?>
    </span>
    <i class="bi bi-arrow-right text-muted"></i>
    <span class="badge bg-success-subtle text-success-emphasis">
      Destination: <?= e($report['dst_event']['name'] ?? '—') ?>
    </span>
  </div>
</div>

<?php if (!empty($report['error'])): ?>
  <div class="alert alert-danger">
    <strong>Migration failed — all changes rolled back.</strong>
    <div class="small mt-1"><?= e($report['error']) ?></div>
  </div>
<?php elseif (empty($report['copied'])): ?>
  <div class="alert alert-warning">
    Nothing was copied. All selected sets were skipped (destination not blank or no items picked).
  </div>
<?php else: ?>
  <div class="alert alert-success small">
    Migration committed.
  </div>
<?php endif; ?>

<div class="sms-card p-3 mb-3">
  <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-check2-circle me-2"></i>Copied</h6>
  <?php if (empty($report['copied'])): ?>
    <p class="small text-muted mb-0">—</p>
  <?php else: ?>
    <table class="table table-sm mb-0">
      <thead class="table-light"><tr><th>Set</th><th class="text-end">Rows</th></tr></thead>
      <tbody>
        <?php foreach ($report['copied'] as $k => $n): ?>
          <tr>
            <td><?= e($set_labels[$k] ?? $k) ?></td>
            <td class="text-end fw-medium"><?= (int)$n ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if (!empty($report['skipped'])): ?>
<div class="sms-card p-3 mb-3">
  <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-skip-forward me-2"></i>Skipped</h6>
  <table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Set</th><th>Reason</th></tr></thead>
    <tbody>
      <?php foreach ($report['skipped'] as $k => $reason): ?>
        <tr>
          <td><?= e($set_labels[$k] ?? $k) ?></td>
          <td class="small text-muted"><?= e($reason) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<a href="/admin/event-migrate" class="btn btn-outline-secondary">
  <i class="bi bi-arrow-counterclockwise me-1"></i>Start a new migration
</a>
