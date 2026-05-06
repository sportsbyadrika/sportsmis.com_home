<?php
$pageTitle = 'Grievance #' . (int)$grievance['id'];
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>

<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
  <a href="/athlete/events/<?= e(hid_event((int)$grievance['event_id'])) ?>/grievances"
     class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-chat-square-dots me-2"></i>Grievance #<?= (int)$grievance['id'] ?></h5>
  <?= statusBadge($grievance['status']) ?>
</div>

<div class="sms-card p-4 mb-4">
  <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
    <div>
      <div class="fw-semibold"><?= e($grievance['subject']) ?></div>
      <div class="text-muted small">Event: <?= e($grievance['event_name']) ?> · Filed <?= formatDate($grievance['created_at'], 'd M Y H:i') ?></div>
    </div>
  </div>
  <div class="border-top pt-3" style="white-space:pre-wrap"><?= e($grievance['message']) ?></div>
</div>

<div class="sms-card p-4 mb-4">
  <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-chat-dots me-2"></i>Conversation</h6>
  <?php if (empty($replies)): ?>
    <p class="text-muted small mb-0">No replies yet. The event administrator will respond here.</p>
  <?php else: ?>
    <?php foreach ($replies as $r):
      $isMine = $r['author_role'] === 'athlete';
      $cls    = $isMine ? 'bg-primary-subtle text-primary-emphasis ms-auto' : 'bg-light';
    ?>
      <div class="border rounded-3 p-3 mb-2 <?= $cls ?>" style="max-width:85%; <?= $isMine ? 'margin-left:auto' : '' ?>">
        <div class="small text-muted mb-1">
          <?= $isMine ? 'You' : 'Event Administrator' ?> · <?= formatDate($r['created_at'], 'd M Y H:i') ?>
        </div>
        <div style="white-space:pre-wrap"><?= e($r['message']) ?></div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php if (!in_array($grievance['status'], ['closed'], true)): ?>
<div class="sms-card p-4">
  <h6 class="fw-semibold border-bottom pb-2 mb-3">Add a reply</h6>
  <form method="POST" action="/athlete/grievances/<?= (int)$grievance['id'] ?>/reply">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <textarea name="message" class="form-control mb-2" rows="4" placeholder="Type your follow-up..." required></textarea>
    <button class="btn btn-primary"><i class="bi bi-send me-1"></i>Send</button>
  </form>
</div>
<?php else: ?>
<div class="alert alert-secondary"><i class="bi bi-lock me-2"></i>This grievance has been closed. Contact the event administrator if you need to reopen it.</div>
<?php endif; ?>
