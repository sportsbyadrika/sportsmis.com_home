<?php $pageTitle = 'Events – Admin'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-event me-2"></i>Event Management</h5>
</div>

<div class="sms-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Event</th>
          <th>Institution</th>
          <th>Dates</th>
          <th>Submitted</th>
          <th>Status</th>
          <th>Change Status</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $statusMap = [
          'draft'             => ['label'=>'Draft',     'class'=>'bg-secondary'],
          'active'            => ['label'=>'Active',    'class'=>'bg-success'],
          'completed'         => ['label'=>'Completed', 'class'=>'bg-info text-dark'],
          'suspended'         => ['label'=>'Suspended', 'class'=>'bg-danger'],
          // legacy values still in DB before backfill runs
          'pending_approval'  => ['label'=>'Pending',   'class'=>'bg-warning text-dark'],
          'approved'          => ['label'=>'Active',    'class'=>'bg-success'],
          'rejected'          => ['label'=>'Suspended', 'class'=>'bg-danger'],
          'cancelled'         => ['label'=>'Suspended', 'class'=>'bg-danger'],
        ];
        ?>
        <?php foreach ($events as $event):
            $cur = $event['status'] ?? 'draft';
            $disp = $statusMap[$cur] ?? $statusMap['draft'];
        ?>
        <tr>
          <td>
            <div class="fw-medium"><?= e($event['name']) ?></div>
            <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($event['location']) ?></small>
          </td>
          <td class="text-muted"><?= e($event['institution_name']) ?></td>
          <td class="text-muted small">
            <?= formatDate($event['event_date_from']) ?> – <?= formatDate($event['event_date_to']) ?>
          </td>
          <td class="text-muted small"><?= formatDate($event['created_at']) ?></td>
          <td><span class="badge <?= $disp['class'] ?>"><?= $disp['label'] ?></span></td>
          <td>
            <form method="POST" action="/admin/events/<?= (int)$event['id'] ?>/status" class="d-flex gap-2">
              <?= csrf() ?>
              <select name="status" class="form-select form-select-sm" style="width:140px">
                <option value="draft"     <?= $cur==='draft'     ? 'selected':'' ?>>Draft</option>
                <option value="active"    <?= in_array($cur,['active','approved','pending_approval'],true) ? 'selected':'' ?>>Active</option>
                <option value="completed" <?= $cur==='completed' ? 'selected':'' ?>>Completed</option>
                <option value="suspended" <?= in_array($cur,['suspended','rejected','cancelled'],true) ? 'selected':'' ?>>Suspended</option>
              </select>
              <button class="btn btn-sm btn-primary" onclick="return confirm('Change event status?')">
                <i class="bi bi-save"></i>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($events)): ?>
          <tr><td colspan="6" class="text-muted text-center py-4">No events yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
