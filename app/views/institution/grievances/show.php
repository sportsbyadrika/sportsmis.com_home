<?php $pageTitle = 'Grievance #' . (int)$grievance['id']; ?>

<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/grievances"
     class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-chat-square-dots me-2"></i>Grievance #<?= (int)$grievance['id'] ?></h5>
  <?= statusBadge($grievance['status']) ?>
</div>

<?= flashBag() ?>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="sms-card p-4 mb-4">
      <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
        <div>
          <div class="fw-semibold"><?= e($grievance['subject']) ?></div>
          <div class="text-muted small">From <?= e($grievance['athlete_name']) ?> · Filed <?= formatDate($grievance['created_at'], 'd M Y H:i') ?></div>
        </div>
      </div>
      <div class="border-top pt-3" style="white-space:pre-wrap"><?= e($grievance['message']) ?></div>
    </div>

    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-chat-dots me-2"></i>Conversation</h6>
      <?php if (empty($replies)): ?>
        <p class="text-muted small mb-0">No replies yet.</p>
      <?php else: ?>
        <?php foreach ($replies as $r):
          $isAdmin = $r['author_role'] !== 'athlete';
          $cls    = $isAdmin ? 'bg-primary-subtle text-primary-emphasis ms-auto' : 'bg-light';
        ?>
          <div class="border rounded-3 p-3 mb-2 <?= $cls ?>" style="max-width:85%; <?= $isAdmin ? 'margin-left:auto' : '' ?>">
            <div class="small text-muted mb-1">
              <?= $isAdmin ? 'You (Event Administrator)' : 'Athlete' ?> · <?= formatDate($r['created_at'], 'd M Y H:i') ?>
            </div>
            <div style="white-space:pre-wrap"><?= e($r['message']) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="sms-card p-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-reply me-2"></i>Reply / update status</h6>
      <form method="POST" action="/institution/grievances/<?= (int)$grievance['id'] ?>/reply">
        <?= csrf() ?>
        <textarea name="message" class="form-control mb-3" rows="4" placeholder="Type your reply (optional if only changing status)..."></textarea>
        <div class="d-flex flex-wrap align-items-center gap-2">
          <label class="small text-muted me-1">Set status:</label>
          <select name="status" class="form-select form-select-sm" style="width:160px">
            <option value="">— keep as-is —</option>
            <option value="open">Open</option>
            <option value="in_progress">In Progress</option>
            <option value="resolved">Resolved</option>
            <option value="closed">Closed</option>
          </select>
          <button class="btn btn-primary btn-sm ms-auto"><i class="bi bi-send me-1"></i>Send</button>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="sms-card p-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3">Athlete</h6>
      <div class="fw-medium"><?= e($grievance['athlete_name']) ?></div>
      <?php if (!empty($grievance['athlete_mobile'])): ?>
        <div class="text-muted small"><i class="bi bi-phone me-1"></i><?= e($grievance['athlete_mobile']) ?></div>
      <?php endif; ?>
      <?php if (!empty($grievance['athlete_email'])): ?>
        <div class="text-muted small text-break"><i class="bi bi-envelope me-1"></i><?= e($grievance['athlete_email']) ?></div>
      <?php endif; ?>
      <hr>
      <div class="text-muted small">Event</div>
      <div class="fw-medium"><?= e($grievance['event_name']) ?></div>
    </div>
  </div>
</div>
