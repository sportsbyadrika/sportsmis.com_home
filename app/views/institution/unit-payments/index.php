<?php
$pageTitle = 'Unit Payment Transactions — ' . ($event['name'] ?? '');
$groups = $groups ?? [];
$money  = fn($v) => '₹' . number_format((float)$v, 2);
$badge  = [
    'submitted' => ['info',    'Submitted'],
    'approved'  => ['success', 'Approved'],
    'rejected'  => ['danger',  'Rejected'],
];
// Totals across all units.
$gTxns = 0; $gApproved = 0.0; $gSubmitted = 0.0; $gDemand = 0.0;
foreach ($groups as $g) {
    $gTxns      += count($g['rows']);
    $gApproved  += (float)$g['approved'];
    $gSubmitted += (float)$g['submitted'];
    $gDemand    += (float)$g['demand_total'];
}
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/registrations?event_id=<?= (int)$event['id'] ?>" class="btn btn-sm btn-outline-secondary">
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
      <div class="text-muted small text-uppercase">Total Demand</div>
      <div class="fs-5 fw-bold text-primary"><?= $money($gDemand) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Approved Collection</div>
      <div class="fs-5 fw-bold text-success"><?= $money($gApproved) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Awaiting Review</div>
      <div class="fs-5 fw-bold text-info-emphasis"><?= $money($gSubmitted) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Transactions</div>
      <div class="fs-5 fw-bold"><?= (int)$gTxns ?></div>
    </div>
  </div>
</div>

<?php if (empty($groups)): ?>
  <div class="sms-card p-4 text-muted small text-center">
    No unit payment transactions have been submitted for this event yet.
  </div>
<?php else: ?>
  <?php foreach ($groups as $g):
    $reconOk = (float)$g['approved'] + 0.005 >= (float)$g['demand_total'] && (float)$g['demand_total'] > 0;
  ?>
    <div class="sms-card p-3 mb-3">
      <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-2 flex-wrap">
        <strong><i class="bi bi-building me-1"></i><?= e($g['unit_name']) ?></strong>
        <span class="badge bg-light text-dark border">
          Demand <?= $money($g['demand_total']) ?>
          <span class="text-muted">(Indiv <?= $money($g['demand_individual']) ?> · Team <?= $money($g['demand_team']) ?>)</span>
        </span>
        <span class="badge bg-success-subtle text-success-emphasis">Approved <?= $money($g['approved']) ?></span>
        <span class="badge bg-info-subtle text-info-emphasis">Awaiting <?= $money($g['submitted']) ?></span>
        <span class="ms-auto badge <?= $reconOk ? 'bg-success' : 'bg-warning text-dark' ?>">
          <?php if ((float)$g['demand_total'] <= 0): ?>
            No demand
          <?php elseif ($reconOk): ?>
            <i class="bi bi-check-circle me-1"></i>Approved collection covers demand
          <?php else: ?>
            <i class="bi bi-exclamation-triangle me-1"></i>Short by <?= $money((float)$g['demand_total'] - (float)$g['approved']) ?>
          <?php endif; ?>
        </span>
        <?php if ((float)$g['approved'] > 0.005): ?>
          <a href="/institution/events/<?= e($eventHash) ?>/units/<?= (int)$g['unit_id'] ?>/receipt.pdf"
             target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark"
             title="Download consolidated payment receipt for approved transactions">
            <i class="bi bi-receipt me-1"></i>Receipt
          </a>
        <?php endif; ?>
      </div>

      <?php if (empty($g['rows'])): ?>
        <p class="text-muted small mb-0">No transactions submitted by this unit yet.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Date</th>
                <th>Reference No.</th>
                <th class="text-end">Amount</th>
                <th class="text-center">Proof</th>
                <th>Status</th>
                <th>Reviewed</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($g['rows'] as $t):
                [$cls, $lbl] = $badge[$t['status']] ?? ['secondary', ucfirst((string)$t['status'])];
              ?>
                <tr>
                  <td class="small"><?= formatDate($t['transaction_date']) ?></td>
                  <td><code class="small"><?= e($t['reference_number'] ?? '') ?></code></td>
                  <td class="text-end fw-medium"><?= $money($t['amount']) ?></td>
                  <td class="text-center">
                    <?php if (!empty($t['proof_file'])): ?>
                      <a href="<?= e($t['proof_file']) ?>" target="_blank" rel="noopener" title="View proof">
                        <i class="bi bi-paperclip"></i> View
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
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
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
