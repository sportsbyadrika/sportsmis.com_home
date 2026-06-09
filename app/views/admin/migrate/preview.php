<?php
$pageTitle = 'Event Migrate — Step 3';
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];
$anyCopy = false;
foreach ($checks as $c) if (!empty($c['will_copy'])) { $anyCopy = true; break; }
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/admin/event-migrate/items" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-eye me-2"></i>Event Migrate</h5>
  <span class="text-muted small ms-2">Step 3 of 3 — review &amp; commit</span>
</div>

<div class="sms-card p-3 mb-3 small">
  <div class="d-flex flex-wrap gap-3 align-items-center">
    <span class="badge bg-primary-subtle text-primary-emphasis">
      <i class="bi bi-box-arrow-up-right me-1"></i>Source: <?= e($src_event['name'] ?? '—') ?>
    </span>
    <i class="bi bi-arrow-right text-muted"></i>
    <span class="badge bg-success-subtle text-success-emphasis">
      <i class="bi bi-box-arrow-down-right me-1"></i>Destination: <?= e($dst_event['name'] ?? '—') ?>
    </span>
  </div>
</div>

<div class="sms-card p-3 mb-3">
  <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-clipboard-check me-2"></i>Per-set check</h6>
  <table class="table table-sm align-middle mb-0">
    <thead class="table-light">
      <tr><th>Set</th><th class="text-center">Picked</th><th class="text-center">Destination rows</th><th>Result</th></tr>
    </thead>
    <tbody>
      <?php foreach ($checks as $key => $c): ?>
        <tr>
          <td><?= e($c['label']) ?></td>
          <td class="text-center"><?= (int)$c['picked'] ?></td>
          <td class="text-center"><?= (int)$c['dest_count'] ?></td>
          <td>
            <?php if ($c['will_copy']): ?>
              <span class="badge bg-success-subtle text-success-emphasis">
                <i class="bi bi-check-circle me-1"></i>Will copy <?= (int)$c['picked'] ?>
              </span>
            <?php else: ?>
              <span class="badge bg-warning-subtle text-warning-emphasis">
                <i class="bi bi-skip-forward me-1"></i>Skip
              </span>
              <small class="text-muted ms-1"><?= e($c['skip_reason']) ?></small>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if (!$anyCopy): ?>
  <div class="alert alert-warning small">
    Nothing will be copied — all selected sets either had no items picked or the destination already has data.
  </div>
<?php endif; ?>

<form method="POST" action="/admin/event-migrate/run"
      onsubmit="return confirm('Commit the migration? This cannot be undone (no auto-rollback after success).');"
      class="d-flex justify-content-end gap-2">
  <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
  <a href="/admin/event-migrate/items" class="btn btn-outline-secondary">Back to items</a>
  <button type="submit" class="btn btn-primary" <?= $anyCopy ? '' : 'disabled' ?>>
    <i class="bi bi-play-circle me-1"></i>Commit migration
  </button>
</form>
