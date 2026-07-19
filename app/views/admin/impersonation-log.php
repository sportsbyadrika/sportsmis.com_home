<?php $pageTitle = 'Support Login Log'; $rows = $rows ?? []; ?>

<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
  <a href="/admin/institutions?tab=all" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Support Login Log</h5>
  <span class="text-muted small ms-2">Super Admin &ldquo;login as institution&rdquo; sessions</span>
</div>

<?= flashBag() ?>

<?php if (empty($rows)): ?>
  <div class="sms-card p-4 text-muted small text-center">
    No support sign-ins recorded yet.
  </div>
<?php else: ?>
  <div class="sms-card">
    <div class="table-responsive">
      <table class="table table-hover table-striped mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>Institution</th>
            <th>Super Admin</th>
            <th>Institution Login</th>
            <th>IP</th>
            <th>Started</th>
            <th>Ended</th>
            <th>Duration</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $start = !empty($r['started_at']) ? strtotime((string)$r['started_at']) : null;
            $end   = !empty($r['ended_at'])   ? strtotime((string)$r['ended_at'])   : null;
            $dur = '';
            if ($start && $end) {
                $s = max(0, $end - $start);
                $dur = $s >= 3600 ? floor($s/3600).'h '.floor(($s%3600)/60).'m'
                     : ($s >= 60 ? floor($s/60).'m '.($s%60).'s' : $s.'s');
            }
          ?>
            <tr>
              <td class="fw-medium"><?= e($r['institution_name'] ?? ('#' . (int)($r['institution_id'] ?? 0))) ?></td>
              <td class="small"><?= e($r['admin_email'] ?? ('user #' . (int)$r['admin_user_id'])) ?></td>
              <td class="small text-muted"><?= e($r['target_email'] ?? ('user #' . (int)$r['target_user_id'])) ?></td>
              <td class="small text-muted"><code><?= e($r['ip'] ?? '') ?></code></td>
              <td class="small"><?= $start ? date('d M Y H:i', $start) : '—' ?></td>
              <td class="small">
                <?php if ($end): ?>
                  <?= date('d M Y H:i', $end) ?>
                <?php else: ?>
                  <span class="badge bg-warning text-dark">Active</span>
                <?php endif; ?>
              </td>
              <td class="small text-muted"><?= $dur !== '' ? e($dur) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <p class="small text-muted mt-2 mb-0">
    <i class="bi bi-info-circle me-1"></i>
    Each row is one support session where a Super Admin signed in as an institution.
    &ldquo;Active&rdquo; means the session hasn&rsquo;t been returned from yet.
  </p>
<?php endif; ?>
