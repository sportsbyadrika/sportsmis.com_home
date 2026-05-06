<?php $pageTitle = 'Grievances — ' . $event['name']; ?>

<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/edit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Event</a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-chat-square-dots me-2"></i>Grievances — <?= e($event['name']) ?></h5>
</div>

<form method="GET" class="sms-card p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label small mb-1">Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">All</option>
        <option value="open"        <?= $status==='open'        ? 'selected':'' ?>>Open</option>
        <option value="in_progress" <?= $status==='in_progress' ? 'selected':'' ?>>In Progress</option>
        <option value="resolved"    <?= $status==='resolved'    ? 'selected':'' ?>>Resolved</option>
        <option value="closed"      <?= $status==='closed'      ? 'selected':'' ?>>Closed</option>
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
      <a href="/institution/events/<?= e($eventHash) ?>/grievances" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
    </div>
  </div>
</form>

<?php if (empty($grievances)): ?>
  <div class="sms-empty-state">
    <i class="bi bi-check-circle text-success"></i>
    <h5>No Grievances</h5>
    <p>Athletes haven't filed any grievance for this event<?= $status ? ' with this filter' : '' ?>.</p>
  </div>
<?php else: ?>
<div class="sms-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Subject</th><th>Athlete</th><th>Status</th><th class="text-end">Replies</th><th>Filed</th><th>Last update</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($grievances as $g): ?>
        <tr style="cursor:pointer" onclick="window.location.href='/institution/grievances/<?= (int)$g['id'] ?>'">
          <td class="fw-medium"><?= e($g['subject']) ?></td>
          <td>
            <div><?= e($g['athlete_name']) ?></div>
            <small class="text-muted"><?= e($g['athlete_mobile'] ?? '') ?></small>
          </td>
          <td><?= statusBadge($g['status']) ?></td>
          <td class="text-end"><?= (int)$g['reply_count'] ?></td>
          <td class="text-muted small"><?= formatDate($g['created_at'], 'd M Y') ?></td>
          <td class="text-muted small"><?= formatDate($g['updated_at'], 'd M Y H:i') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
