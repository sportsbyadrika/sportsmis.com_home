<?php
$pageTitle = 'Browse Public Events';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$statusBadge = function (?string $s): string {
    return match ($s) {
        'pending'  => '<span class="badge bg-warning-subtle text-warning-emphasis">Pending Review</span>',
        'approved' => '<span class="badge bg-success-subtle text-success-emphasis">Approved</span>',
        'rejected' => '<span class="badge bg-danger-subtle text-danger-emphasis">Rejected</span>',
        default    => '<span class="badge bg-secondary-subtle text-secondary">Not Requested</span>',
    };
};
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/dashboard" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-binoculars me-2"></i>Browse Public Events</h5>
  <span class="text-muted small ms-2">Events you can request to participate in as a Unit</span>
</div>

<?= flashBag() ?>

<p class="small text-muted mb-3">
  <i class="bi bi-info-circle me-1"></i>
  Each event below is currently accepting institution participation requests.
  Submit a request, the organiser reviews it, and once approved you can open
  the Unit Console for that event from <strong>Events I&rsquo;m Participating In</strong>
  using your own institution login.
</p>

<div class="sms-card p-3">
  <?php if (empty($rows)): ?>
    <div class="text-center text-muted py-4">
      <i class="bi bi-emoji-frown fs-1 d-block mb-2 text-secondary"></i>
      No events are accepting institution join requests right now.
      Check back later or contact an organiser directly.
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Event</th>
            <th>Organiser</th>
            <th>Dates</th>
            <th>Status</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $hash = hid_event((int)$r['id']);
            $reqSt  = $r['request_status'] ?? null;
            $linked = !empty($r['linked_unit_id']);
          ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php if (!empty($r['logo'])): ?>
                    <img src="<?= e($r['logo']) ?>" alt="" width="36" height="36"
                         class="rounded" style="object-fit:contain;background:#fff;border:1px solid #e2e8f0">
                  <?php endif; ?>
                  <div>
                    <div class="fw-semibold"><?= e($r['name']) ?></div>
                    <div class="small text-muted">
                      <code><?= e($r['event_code'] ?? '') ?></code>
                      <?php if (!empty($r['location'])): ?>
                        &middot; <?= e($r['location']) ?>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>
              <td class="small"><?= e($r['organiser_name'] ?? '—') ?></td>
              <td class="small">
                <?= !empty($r['event_date_from']) ? formatDate($r['event_date_from']) : '—' ?>
                <?php if (!empty($r['event_date_to']) && $r['event_date_to'] !== $r['event_date_from']): ?>
                  – <?= formatDate($r['event_date_to']) ?>
                <?php endif; ?>
              </td>
              <td>
                <?= $statusBadge($reqSt) ?>
                <?php if ($reqSt === 'rejected' && !empty($r['reviewer_notes'])): ?>
                  <div class="small text-muted mt-1" style="max-width:280px"
                       title="<?= e($r['reviewer_notes']) ?>">
                    <?= e(mb_strimwidth($r['reviewer_notes'], 0, 60, '…')) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td class="text-end text-nowrap">
                <?php if ($linked || $reqSt === 'approved'): ?>
                  <a href="/institution/participating-events"
                     class="btn btn-sm btn-success">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Open Unit Console
                  </a>
                <?php elseif ($reqSt === 'pending'): ?>
                  <button class="btn btn-sm btn-outline-secondary" disabled>
                    <i class="bi bi-hourglass-split me-1"></i>Waiting for review
                  </button>
                <?php else: ?>
                  <button type="button" class="btn btn-sm btn-primary"
                          data-bs-toggle="modal" data-bs-target="#reqModal"
                          data-event-hash="<?= e($hash) ?>"
                          data-event-name="<?= e($r['name']) ?>"
                          data-default-unit="<?= e($institution['name'] ?? '') ?>"
                          data-default-address="<?= e($institution['address'] ?? '') ?>"
                          data-was-rejected="<?= $reqSt === 'rejected' ? '1' : '0' ?>">
                    <i class="bi bi-send me-1"></i><?= $reqSt === 'rejected' ? 'Request Again' : 'Request to Join' ?>
                  </button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Request modal -->
<div class="modal fade" id="reqModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="POST" id="reqForm">
      <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-send me-2"></i>Request to Participate
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-3">
          Submitting this request to <strong id="reqEventName">—</strong>.
          The organiser will review and either approve or reject. You&rsquo;ll
          see the outcome on this page.
        </p>
        <div class="mb-3">
          <label class="form-label small mb-1">Unit / Club Name <span class="text-danger">*</span></label>
          <input type="text" name="proposed_unit_name" class="form-control form-control-sm"
                 maxlength="255" required>
          <small class="text-muted d-block mt-1">How you want this unit to appear on the event.</small>
        </div>
        <div class="mb-3">
          <label class="form-label small mb-1">Unit Address <small class="text-muted">(optional)</small></label>
          <textarea name="proposed_unit_address" rows="2" class="form-control form-control-sm"
                    maxlength="500"></textarea>
        </div>
        <div class="mb-1">
          <label class="form-label small mb-1">Message to Organiser <small class="text-muted">(optional)</small></label>
          <textarea name="request_notes" rows="3" class="form-control form-control-sm"
                    maxlength="1000" placeholder="Anything the organiser should know about your team / club…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="bi bi-send me-1"></i>Send Request
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const modal = document.getElementById('reqModal');
  if (!modal) return;
  modal.addEventListener('show.bs.modal', function (ev) {
    const btn  = ev.relatedTarget;
    if (!btn) return;
    const hash = btn.dataset.eventHash || '';
    document.getElementById('reqEventName').textContent = btn.dataset.eventName || '—';
    document.getElementById('reqForm').setAttribute('action',
      '/institution/events/' + encodeURIComponent(hash) + '/request-participation');
    document.querySelector('[name="proposed_unit_name"]').value =
      btn.dataset.defaultUnit || '';
    document.querySelector('[name="proposed_unit_address"]').value =
      btn.dataset.defaultAddress || '';
    document.querySelector('[name="request_notes"]').value =
      btn.dataset.wasRejected === '1'
        ? 'Updated request after earlier rejection — please reconsider.'
        : '';
  });
})();
</script>
