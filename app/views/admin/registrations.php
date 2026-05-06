<?php $pageTitle = 'All Registrations'; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-check me-2"></i>All Registrations</h5>
</div>

<form method="GET" action="/admin/registrations" class="sms-card p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-md-9">
      <label class="form-label small mb-1">Search</label>
      <input type="search" name="q" value="<?= e($q) ?>" class="form-control form-control-sm"
             placeholder="Athlete name, event name, institution name…">
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Search</button>
      <a href="/admin/registrations" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
    </div>
  </div>
</form>

<div class="sms-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Athlete</th>
          <th>Event</th>
          <th>Institution</th>
          <th class="text-end">Total</th>
          <th>Application</th>
          <th>Payment</th>
          <th>Submitted</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="8" class="text-muted text-center py-4">No registrations match the search.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['athlete_name']) ?></td>
            <td class="small text-muted"><?= e($r['event_name']) ?></td>
            <td class="small text-muted"><?= e($r['institution_name']) ?></td>
            <td class="text-end"><?= !empty($r['total_amount']) ? '₹' . number_format((float)$r['total_amount'], 2) : '—' ?></td>
            <td><?= appStatusBadge($r['admin_review_status'] ?? null, $r['submitted_at'] ?? null) ?></td>
            <td><?= statusBadge($r['payment_status'] ?? 'pending') ?></td>
            <td class="text-muted small">
              <?= !empty($r['submitted_at']) ? formatDate($r['submitted_at'], 'd M Y') : '<em>not submitted</em>' ?>
            </td>
            <td class="text-end">
              <form method="POST" action="/admin/registrations/<?= (int)$r['id'] ?>/delete"
                    onsubmit="return confirm('Delete registration #<?= (int)$r['id'] ?> for <?= e(addslashes($r['athlete_name'])) ?>? This removes its line items, payment transactions and proof files. This cannot be undone.')">
                <?= csrf() ?>
                <button class="btn btn-sm btn-outline-danger" title="Delete registration">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
