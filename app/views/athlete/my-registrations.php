<?php $pageTitle = 'My Registrations'; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>My Event Registrations</h5>
  <a href="/athlete/dashboard" class="btn btn-outline-primary">
    <i class="bi bi-search me-2"></i>Find More Events
  </a>
</div>

<?php if (empty($registrations)): ?>
<div class="sms-empty-state">
  <i class="bi bi-calendar-plus"></i>
  <h5>No Registrations Yet</h5>
  <p>You haven't registered for any events. Browse active events from the dashboard to get started.</p>
  <a href="/athlete/dashboard" class="btn btn-primary">Go to Dashboard</a>
</div>
<?php else: ?>
<div class="sms-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Event</th>
          <th>Unit</th>
          <th>Sports / Events</th>
          <th class="text-end">Total Fee</th>
          <th>Payment</th>
          <th>Status</th>
          <th>Registered</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($registrations as $reg): ?>
        <tr>
          <td>
            <div class="fw-medium"><?= e($reg['event_name']) ?></div>
            <small class="text-muted">
              <i class="bi bi-building me-1"></i><?= e($reg['institution_name']) ?><br>
              <i class="bi bi-geo-alt me-1"></i><?= e($reg['location']) ?><br>
              <i class="bi bi-calendar3 me-1"></i><?= formatDate($reg['event_date_from']) ?> – <?= formatDate($reg['event_date_to']) ?>
            </small>
          </td>
          <td class="text-muted small">
            <?php if (!empty($reg['unit_name'])): ?>
              <i class="bi bi-people me-1"></i><?= e($reg['unit_name']) ?>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
          <td class="small">
            <?php if (!empty($reg['sport_name'])): ?>
              <div><?= e($reg['sport_name']) ?></div>
            <?php endif; ?>
            <?php if (!empty($reg['event_label'])): ?>
              <small class="text-muted"><?= e($reg['event_label']) ?></small>
            <?php endif; ?>
            <?php if ((int)($reg['items_count'] ?? 0) > 0): ?>
              <span class="badge bg-secondary-subtle text-secondary mt-1"><?= (int)$reg['items_count'] ?> event<?= (int)$reg['items_count'] === 1 ? '' : 's' ?></span>
            <?php endif; ?>
          </td>
          <td class="text-end fw-medium">
            <?php $tot = (float)($reg['total_amount'] ?? 0); ?>
            <?= $tot > 0 ? '₹' . number_format($tot, 2) : '<span class="text-muted">—</span>' ?>
          </td>
          <td class="text-muted small">
            <?php if (!empty($reg['payment_mode'])): ?>
              <i class="bi bi-<?= $reg['payment_mode'] === 'manual' ? 'bank' : 'credit-card' ?> me-1"></i>
              <?= ucfirst($reg['payment_mode']) ?><br>
            <?php endif; ?>
            <?= statusBadge($reg['payment_status']) ?>
            <?php if (!empty($reg['transaction_proof'])): ?>
              <a href="<?= e($reg['transaction_proof']) ?>" target="_blank" class="d-block mt-1"><i class="bi bi-receipt me-1"></i>Proof</a>
            <?php endif; ?>
          </td>
          <td><?= statusBadge($reg['status']) ?></td>
          <td class="text-muted small"><?= formatDate($reg['registered_at'], 'd M Y H:i') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
