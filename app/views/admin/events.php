<?php $pageTitle = 'Events – Admin'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-event me-2"></i>Event Management</h5>
</div>

<div class="sms-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th>Event</th><th>Institution</th><th>Dates</th><th>Submitted</th><th>Status</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($events as $event): ?>
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
          <td><?= statusBadge($event['status']) ?></td>
          <td>
            <?php if ($event['status'] === 'pending_approval'): ?>
            <div class="d-flex gap-1">
              <form method="POST" action="/admin/events/<?= $event['id'] ?>/approve">
                <?= csrf() ?>
                <button class="btn btn-sm btn-success" onclick="return confirm('Approve this event?')">
                  <i class="bi bi-check-circle me-1"></i>Approve
                </button>
              </form>
              <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                      data-bs-target="#rejectModal<?= $event['id'] ?>">Reject</button>

              <!-- Reject Modal -->
              <div class="modal fade" id="rejectModal<?= $event['id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                  <div class="modal-content">
                    <form method="POST" action="/admin/events/<?= $event['id'] ?>/reject">
                      <?= csrf() ?>
                      <div class="modal-header">
                        <h6 class="modal-title">Reject Event</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <label class="form-label">Reason for rejection</label>
                        <textarea name="reason" class="form-control" rows="3" required></textarea>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
            <?php else: ?>
              <span class="text-muted small">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
