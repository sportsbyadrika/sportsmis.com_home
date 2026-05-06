<?php $pageTitle = 'My Grievances'; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold"><i class="bi bi-chat-square-dots me-2"></i>My Grievances</h5>
  <a href="/athlete/dashboard" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
</div>

<p class="small text-muted">Pick the event you registered for from the dashboard, then use <em>"Raise a grievance"</em> on that event's registration view.</p>

<?php if (empty($grievances)): ?>
  <div class="sms-empty-state">
    <i class="bi bi-chat-square"></i>
    <h5>No Grievances Filed</h5>
    <p>If you have any concern about an event, you can raise it from the event registration page.</p>
  </div>
<?php else: ?>
<div class="sms-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Subject</th><th>Event</th><th>Status</th><th>Replies</th><th>Last update</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($grievances as $g): ?>
        <tr style="cursor:pointer" onclick="window.location.href='/athlete/grievances/<?= (int)$g['id'] ?>'">
          <td class="fw-medium"><?= e($g['subject']) ?></td>
          <td class="text-muted small"><?= e($g['event_name']) ?></td>
          <td><?= statusBadge($g['status']) ?></td>
          <td class="text-muted"><?= (int)$g['reply_count'] ?></td>
          <td class="text-muted small"><?= formatDate($g['updated_at'], 'd M Y H:i') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
