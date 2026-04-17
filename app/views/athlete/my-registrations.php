<?php $pageTitle = 'My Registrations'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h5 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>My Event Registrations</h5>
  <a href="/athlete/events" class="btn btn-outline-primary">
    <i class="bi bi-search me-2"></i>Find More Events
  </a>
</div>

<?php if (empty($registrations)): ?>
<div class="sms-empty-state">
  <i class="bi bi-calendar-plus"></i>
  <h5>No Registrations Yet</h5>
  <p>You haven't registered for any events. Browse active events to get started.</p>
  <a href="/athlete/events" class="btn btn-primary">Browse Events</a>
</div>
<?php else: ?>
<div class="sms-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Event</th>
          <th>Sport</th>
          <th>Event Dates</th>
          <th>Payment Mode</th>
          <th>Payment Status</th>
          <th>Status</th>
          <th>Registered On</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($registrations as $reg): ?>
        <tr>
          <td>
            <div class="fw-medium"><?= e($reg['event_name']) ?></div>
            <small class="text-muted">
              <i class="bi bi-building me-1"></i><?= e($reg['institution_name']) ?><br>
              <i class="bi bi-geo-alt me-1"></i><?= e($reg['location']) ?>
            </small>
          </td>
          <td><?= e($reg['sport_name']) ?></td>
          <td class="text-muted small">
            <?= formatDate($reg['event_date_from']) ?><br>– <?= formatDate($reg['event_date_to']) ?>
          </td>
          <td class="text-muted"><?= $reg['payment_mode'] ? ucfirst($reg['payment_mode']) : '—' ?></td>
          <td><?= statusBadge($reg['payment_status']) ?></td>
          <td><?= statusBadge($reg['status']) ?></td>
          <td class="text-muted small"><?= formatDate($reg['registered_at'], 'd M Y H:i') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
