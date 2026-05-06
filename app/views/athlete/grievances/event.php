<?php
$pageTitle = 'Grievances — ' . $event['name'];
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>

<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
  <a href="/athlete/events/<?= e(hid_event((int)$event['id'])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Event</a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-chat-square-dots me-2"></i>Grievances — <?= e($event['name']) ?></h5>
</div>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="sms-card p-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3">Raise a new grievance</h6>
      <p class="small text-muted">Use this to send a question, complaint or comment to the event administrator. They'll reply here and you'll see the thread.</p>
      <form method="POST" action="/athlete/events/<?= e(hid_event((int)$event['id'])) ?>/grievances">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <div class="mb-3">
          <label class="form-label fw-medium">Subject <span class="text-danger">*</span></label>
          <input type="text" name="subject" class="form-control" maxlength="255" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-medium">Message <span class="text-danger">*</span></label>
          <textarea name="message" class="form-control" rows="6" required></textarea>
        </div>
        <button class="btn btn-primary"><i class="bi bi-send me-1"></i>Submit</button>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="sms-card p-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3">My grievances for this event</h6>
      <?php if (empty($grievances)): ?>
        <p class="text-muted small mb-0">You haven't filed any grievance for this event yet.</p>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($grievances as $g): ?>
          <li class="list-group-item d-flex justify-content-between align-items-start">
            <div class="me-2">
              <a href="/athlete/grievances/<?= (int)$g['id'] ?>" class="fw-medium text-decoration-none"><?= e($g['subject']) ?></a>
              <div class="text-muted small"><?= formatDate($g['updated_at'], 'd M Y H:i') ?> · <?= (int)$g['reply_count'] ?> repl<?= (int)$g['reply_count'] === 1 ? 'y' : 'ies' ?></div>
            </div>
            <?= statusBadge($g['status']) ?>
          </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>
