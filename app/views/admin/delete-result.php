<?php $pageTitle = 'Delete ' . $kind . ' — Result'; ?>

<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
  <a href="<?= e($back ?? '/admin/dashboard') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-trash me-2"></i>Delete <?= e($kind) ?></h5>
</div>

<?php
  $blocked = false; $errored = false;
  foreach ($log as $line) {
    if (str_starts_with($line, 'BLOCKED')) $blocked = true;
    if (str_starts_with($line, 'ERROR'))   $errored = true;
  }
?>

<div class="sms-card p-4">
  <div class="mb-3">
    <span class="text-muted">Target:</span>
    <strong class="ms-2"><?= e($target ?? '') ?></strong>
  </div>

  <?php if ($blocked): ?>
    <div class="alert alert-warning"><i class="bi bi-shield-exclamation me-2"></i>The delete was blocked — see the log below.</div>
  <?php elseif ($errored): ?>
    <div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>The delete failed and was rolled back. The database is unchanged.</div>
  <?php else: ?>
    <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= e($kind) ?> deleted successfully. Audit log below.</div>
  <?php endif; ?>

  <h6 class="fw-semibold mt-3 mb-2"><i class="bi bi-list-task me-2"></i>Audit Log</h6>
  <pre class="bg-light p-3 rounded small mb-0" style="white-space:pre-wrap"><?php
    foreach ($log as $line) echo e($line) . "\n";
  ?></pre>
</div>
