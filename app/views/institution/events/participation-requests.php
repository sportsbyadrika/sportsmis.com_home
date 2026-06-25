<?php
$pageTitle = 'Participation Requests — ' . $event['name'];
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$badge = function (string $s): string {
    return match ($s) {
        'pending'  => '<span class="badge bg-warning-subtle text-warning-emphasis">Pending</span>',
        'approved' => '<span class="badge bg-success-subtle text-success-emphasis">Approved</span>',
        'rejected' => '<span class="badge bg-danger-subtle text-danger-emphasis">Rejected</span>',
        default    => '<span class="badge bg-secondary-subtle text-secondary">—</span>',
    };
};
$pendingCount = 0;
foreach ($rows as $r) if ($r['status'] === 'pending') $pendingCount++;
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/edit" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-inbox me-2"></i>Participation Requests</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <?php if ($pendingCount > 0): ?>
    <span class="badge bg-danger ms-1"><?= $pendingCount ?> pending</span>
  <?php endif; ?>
</div>

<?= flashBag() ?>

<p class="small text-muted mb-3">
  <i class="bi bi-info-circle me-1"></i>
  Every other institution that has clicked &ldquo;Request to Participate&rdquo; on this
  event lands here. Approving creates an <strong>Event Unit</strong> tagged with the
  institution&rsquo;s identity — they then open the Unit Console with their own login
  (no separate unit_user password). Rejecting sends the request back with your
  note; the institution can re-submit later if they want.
</p>

<div class="sms-card p-3">
  <?php if (empty($rows)): ?>
    <div class="text-center text-muted py-4">
      <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
      No requests have arrived yet. Flip the &ldquo;Institution Join Requests&rdquo; switch
      on the Event Edit page to start accepting them.
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Institution</th>
            <th>Proposed Unit Name</th>
            <th>Notes</th>
            <th>Submitted</th>
            <th>Status</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php if (!empty($r['institution_logo'])): ?>
                    <img src="<?= e($r['institution_logo']) ?>" width="32" height="32"
                         class="rounded" style="object-fit:cover;background:#fff;border:1px solid #e2e8f0">
                  <?php else: ?>
                    <div class="rounded d-inline-flex align-items-center justify-content-center bg-light text-muted"
                         style="width:32px;height:32px"><i class="bi bi-building"></i></div>
                  <?php endif; ?>
                  <div>
                    <div class="fw-semibold"><?= e($r['institution_name']) ?></div>
                    <?php if (!empty($r['institution_email'])): ?>
                      <small class="text-muted"><?= e($r['institution_email']) ?></small>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td>
                <div class="fw-medium"><?= e($r['proposed_unit_name']) ?></div>
                <?php if (!empty($r['proposed_unit_address'])): ?>
                  <small class="text-muted d-block" style="max-width:240px">
                    <?= e($r['proposed_unit_address']) ?>
                  </small>
                <?php endif; ?>
              </td>
              <td class="small text-muted" style="max-width:280px">
                <?= !empty($r['request_notes']) ? nl2br(e($r['request_notes'])) : '—' ?>
                <?php if ($r['status'] !== 'pending' && !empty($r['reviewer_notes'])): ?>
                  <div class="mt-2 ps-2 border-start border-2">
                    <em class="text-secondary">Your note:</em>
                    <div><?= nl2br(e($r['reviewer_notes'])) ?></div>
                  </div>
                <?php endif; ?>
              </td>
              <td class="small text-muted text-nowrap">
                <?= !empty($r['requested_at']) ? formatDate($r['requested_at'], 'd M Y H:i') : '—' ?>
                <?php if ($r['status'] !== 'pending' && !empty($r['reviewed_at'])): ?>
                  <div class="small">
                    <?= $r['status'] === 'approved' ? 'Approved' : 'Rejected' ?>
                    on <?= e(formatDate($r['reviewed_at'], 'd M Y')) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td><?= $badge($r['status']) ?></td>
              <td class="text-end text-nowrap">
                <?php if ($r['status'] === 'pending'): ?>
                  <form method="POST" class="d-inline"
                        action="/institution/events/<?= e($eventHash) ?>/participation-requests/<?= (int)$r['id'] ?>/decide"
                        onsubmit="return confirm('Approve this request and create an Event Unit for them?');">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="approve">
                    <button class="btn btn-sm btn-success">
                      <i class="bi bi-check-circle me-1"></i>Approve
                    </button>
                  </form>
                  <button type="button" class="btn btn-sm btn-outline-danger"
                          data-bs-toggle="modal" data-bs-target="#rejectModal"
                          data-action="/institution/events/<?= e($eventHash) ?>/participation-requests/<?= (int)$r['id'] ?>/decide"
                          data-name="<?= e($r['institution_name']) ?>">
                    <i class="bi bi-x-circle me-1"></i>Reject
                  </button>
                <?php elseif ($r['status'] === 'approved' && !empty($r['linked_unit_id'])): ?>
                  <span class="small text-muted">
                    Unit linked &middot; <strong>#<?= (int)$r['linked_unit_id'] ?></strong>
                  </span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Reject modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="POST" id="rejectForm">
      <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="action" value="reject">
      <div class="modal-header">
        <h5 class="modal-title text-danger"><i class="bi bi-x-circle me-2"></i>Reject Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small">Rejecting request from <strong id="rejectName">—</strong>. The institution sees your note on their browse page and can re-submit.</p>
        <div class="mb-1">
          <label class="form-label small mb-1">Reason / Note <span class="text-muted">(optional)</span></label>
          <textarea name="reviewer_notes" rows="3" maxlength="500" class="form-control form-control-sm"
                    placeholder="e.g. Event is full / Your unit is already registered / Please update your profile and try again…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Reject Request</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const m = document.getElementById('rejectModal');
  if (!m) return;
  m.addEventListener('show.bs.modal', function (ev) {
    const btn = ev.relatedTarget;
    if (!btn) return;
    document.getElementById('rejectForm').setAttribute('action', btn.dataset.action || '#');
    document.getElementById('rejectName').textContent = btn.dataset.name || '—';
    document.querySelector('#rejectForm [name="reviewer_notes"]').value = '';
  });
})();
</script>
