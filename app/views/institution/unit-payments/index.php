<?php
$pageTitle = 'Unit Payment Transactions — ' . ($event['name'] ?? '');
$txns  = $txns ?? [];
$money = fn($v) => '₹' . number_format((float)$v, 2);
$eid   = (int)($event['id'] ?? 0);
$eh    = $eventHash ?? hid_event($eid);
$approvedUnits = $approved_units ?? [];
$badge = [
    'submitted' => ['info',    'Submitted'],
    'approved'  => ['success', 'Approved'],
    'rejected'  => ['danger',  'Rejected'],
];
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/registrations?event_id=<?= $eid ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Registrations
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-bank me-2"></i>Unit Payment Transactions</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
</div>

<?= flashBag() ?>

<!-- Roll-up cards -->
<div class="row g-2 mb-3">
  <div class="col-6 col-md-3">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Approved Collection</div>
      <div class="fs-5 fw-bold text-success"><?= $money($sum_approved ?? 0) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Awaiting Review</div>
      <div class="fs-5 fw-bold text-info-emphasis"><?= $money($sum_submitted ?? 0) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Rejected</div>
      <div class="fs-5 fw-bold text-danger"><?= $money($sum_rejected ?? 0) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Transactions</div>
      <div class="fs-5 fw-bold"><?= count($txns) ?></div>
    </div>
  </div>
</div>

<?php if (empty($txns)): ?>
  <div class="sms-card p-4 text-muted small text-center">
    No unit payment transactions have been submitted for this event yet.
  </div>
<?php else: ?>
  <div class="sms-card p-0">
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Unit</th>
            <th>Date</th>
            <th>Reference No.</th>
            <th class="text-end">Amount</th>
            <th class="text-center">Proof</th>
            <th>Status</th>
            <th>Reviewed</th>
            <th class="text-end">Action</th>
            <th class="text-center">Receipt</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($txns as $t):
            [$cls, $lbl] = $badge[$t['status']] ?? ['secondary', ucfirst((string)$t['status'])];
            $uid = (int)$t['unit_id'];
          ?>
            <tr>
              <td class="fw-medium"><?= e($t['unit_name'] ?? ('Unit #' . $uid)) ?></td>
              <td class="small"><?= formatDate($t['transaction_date']) ?></td>
              <td><code class="small"><?= e($t['reference_number'] ?? '') ?></code></td>
              <td class="text-end fw-medium"><?= $money($t['amount']) ?></td>
              <td class="text-center">
                <?php if (!empty($t['proof_file'])): ?>
                  <a href="<?= e($t['proof_file']) ?>" target="_blank" rel="noopener" title="View proof">
                    <i class="bi bi-paperclip"></i>
                  </a>
                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
              </td>
              <td>
                <span class="badge bg-<?= e($cls) ?>"><?= e($lbl) ?></span>
                <?php if (($t['status'] ?? '') === 'rejected' && !empty($t['reject_reason'])): ?>
                  <div class="small text-danger mt-1"><?= e($t['reject_reason']) ?></div>
                <?php endif; ?>
              </td>
              <td class="small text-muted">
                <?php if (!empty($t['reviewed_at'])): ?>
                  <?= e($t['reviewed_by_name'] ?? '') ?><br><?= formatDate($t['reviewed_at']) ?>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td class="text-end text-nowrap">
                <?php if (($t['status'] ?? '') === 'submitted'): ?>
                  <form method="POST" action="/institution/unit-payments/<?= (int)$t['id'] ?>/decision"
                        class="d-inline" onsubmit="return confirm('Approve this transaction of <?= e($money($t['amount'])) ?>?');">
                    <?= csrf() ?>
                    <input type="hidden" name="action" value="approve">
                    <button class="btn btn-sm btn-success"><i class="bi bi-check2 me-1"></i>Approve</button>
                  </form>
                  <button type="button" class="btn btn-sm btn-outline-danger"
                          onclick="rejectUnitPay(<?= (int)$t['id'] ?>, '<?= e(addslashes($t['reference_number'] ?? '')) ?>')">
                    <i class="bi bi-x-lg me-1"></i>Reject
                  </button>
                <?php else: ?>
                  <span class="small text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if (!empty($approvedUnits[$uid])): ?>
                  <a href="/institution/events/<?= e($eh) ?>/units/<?= $uid ?>/receipt.pdf"
                     target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark"
                     title="Download consolidated payment receipt for this unit">
                    <i class="bi bi-receipt"></i>
                  </a>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <p class="small text-muted mt-2 mb-0">
    <i class="bi bi-info-circle me-1"></i>
    Transactions are listed by unit (alphabetical), newest first per unit. The
    <strong>Receipt</strong> button downloads that unit's consolidated receipt covering all its approved transactions.
  </p>
<?php endif; ?>

<!-- Reject modal -->
<div class="modal fade" id="rejectUnitPayModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" id="rejectUnitPayForm">
        <?= csrf() ?>
        <input type="hidden" name="action" value="reject">
        <div class="modal-header">
          <h6 class="modal-title fw-semibold"><i class="bi bi-x-octagon me-2"></i>Reject Transaction</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted mb-2">
            Rejecting removes this transaction from the unit's active collection — they will need to
            enter a fresh one. <span id="rejectUnitPayRef" class="fw-medium"></span>
          </p>
          <label class="form-label small mb-1">Reason <span class="text-danger">*</span></label>
          <textarea name="reason" class="form-control form-control-sm" rows="3" required></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger"><i class="bi bi-x-lg me-1"></i>Reject</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
let _rejUnitPayModal = null;
function rejectUnitPay(id, ref) {
  if (!_rejUnitPayModal) _rejUnitPayModal = new bootstrap.Modal(document.getElementById('rejectUnitPayModal'));
  document.getElementById('rejectUnitPayForm').action = '/institution/unit-payments/' + id + '/decision';
  document.getElementById('rejectUnitPayRef').textContent = ref ? ('Ref: ' + ref) : '';
  _rejUnitPayModal.show();
}
</script>
