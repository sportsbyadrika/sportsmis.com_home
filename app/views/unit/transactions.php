<?php
$pageTitle = 'Transactions';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Pre-compute the picker's display data, including default amount =
// outstanding balance so a single click usually fills the form correctly.
$pickerOpts = [];
foreach ($picker as $p) {
    $demand   = (float)($p['total_amount']   ?? 0);
    $claimed  = (float)($p['claimed_amount'] ?? 0);
    $balance  = max(0, $demand - $claimed);
    $pickerOpts[(int)$p['id']] = [
        'label'   => trim(($p['athlete_name'] ?? '') . ' — ' . ($p['unit_name'] ?? '—'))
                     . ' · Demand ₹' . number_format($demand, 2)
                     . ' · Balance ₹' . number_format($balance, 2),
        'balance' => $balance,
    ];
}
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <h5 class="mb-0 fw-bold"><i class="bi bi-cash-stack me-2"></i>Transactions</h5>
  <span class="text-muted small ms-2">on <?= e($event['name'] ?? '') ?></span>
</div>

<?= flashBag() ?>

<!-- Add Transaction form -->
<div class="sms-card p-4 mb-4">
  <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-plus-circle me-2"></i>Log a Payment Transaction</h6>
  <?php if (empty($picker)): ?>
    <p class="text-muted small mb-0">
      No registrations are currently accepting transactions. Add an athlete and pick at least one
      sport-event first — the registration must be in <em>Draft</em> or <em>Returned</em> state and
      have a non-zero demand.
    </p>
  <?php else: ?>
    <form method="POST" action="/unit/transactions" enctype="multipart/form-data" class="row g-2 align-items-end">
      <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
      <div class="col-md-4">
        <label class="form-label small mb-1">Apply to Registration <span class="text-danger">*</span></label>
        <select name="registration_id" id="txRegSel" class="form-select form-select-sm" required onchange="txPrefillAmount()">
          <option value="">— Select —</option>
          <?php foreach ($pickerOpts as $rid => $opt): ?>
            <option value="<?= (int)$rid ?>" data-balance="<?= e(number_format((float)$opt['balance'], 2, '.', '')) ?>">
              <?= e($opt['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Date <span class="text-danger">*</span></label>
        <input type="date" name="transaction_date" class="form-control form-control-sm"
               max="<?= date('Y-m-d') ?>" required value="<?= date('Y-m-d') ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Txn Number <span class="text-danger">*</span></label>
        <input type="text" name="transaction_number" class="form-control form-control-sm" maxlength="100" required>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Amount (₹) <span class="text-danger">*</span></label>
        <input type="number" id="txAmount" name="transaction_amount" class="form-control form-control-sm"
               min="0.01" step="0.01" required>
      </div>
      <div class="col-md-1">
        <label class="form-label small mb-1">Proof <span class="text-danger">*</span></label>
        <input type="file" name="transaction_proof" class="form-control form-control-sm"
               accept="image/jpeg,image/png,image/webp,application/pdf" required>
      </div>
      <div class="col-md-1">
        <button type="submit" class="btn btn-sm btn-primary w-100" style="margin-top:1.45rem">
          <i class="bi bi-plus-circle"></i>
        </button>
      </div>
    </form>
    <small class="text-muted d-block mt-2">
      <i class="bi bi-info-circle me-1"></i>
      Picking a registration auto-fills the outstanding balance as the amount. Edit if you're
      paying a different sum.
    </small>
  <?php endif; ?>
</div>

<!-- All Transactions list -->
<div class="sms-card p-3">
  <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-list-ul me-2"></i>All Transactions</h6>
  <?php if (empty($transactions)): ?>
    <p class="text-muted small mb-0">No transactions logged yet.</p>
  <?php else:
    // Footer totals across the page (exclude demand rows).
    $sumApproved = 0.0; $sumPending = 0.0; $sumRejected = 0.0;
    foreach ($transactions as $t) {
      if (($t['payment_method'] ?? 'manual') === 'demand') continue;
      $st = (string)($t['status'] ?? '');
      if ($st === 'approved') $sumApproved += (float)$t['amount'];
      elseif ($st === 'rejected') $sumRejected += (float)$t['amount'];
      else $sumPending += (float)$t['amount'];
    }
  ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Date</th>
            <th>Athlete</th>
            <th>Unit</th>
            <th>Type</th>
            <th>Txn No.</th>
            <th class="text-end">Amount</th>
            <th>Status</th>
            <th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($transactions as $t):
            $method  = (string)($t['payment_method'] ?? 'manual');
            $isDemand= $method === 'demand';
            $isEpay  = $method === 'epayment';
            $txnNo   = $isEpay ? ($t['razorpay_payment_id'] ?: $t['razorpay_order_id'] ?: $t['transaction_number'])
                               : $t['transaction_number'];
            $typeBadge = $isDemand
              ? '<span class="badge bg-warning-subtle text-warning-emphasis" title="Auto-generated when sport-events were saved.">Demand</span>'
              : ($isEpay
                  ? '<span class="badge bg-info-subtle text-info">ePayment</span>'
                  : '<span class="badge bg-secondary-subtle text-secondary">Manual</span>');
            $statusBadgeHtml = $isDemand
              ? '<span class="badge bg-warning text-dark">Due</span>'
              : statusBadge($t['status']);
            $regHash = hid_reg((int)$t['registration_id']);
          ?>
            <tr<?= $isDemand ? ' class="table-warning"' : '' ?>>
              <td class="small"><?= formatDate($t['transaction_date']) ?></td>
              <td>
                <a href="/unit/athletes/<?= e($regHash) ?>" class="text-decoration-none">
                  <?= e($t['athlete_name']) ?>
                </a>
              </td>
              <td class="small text-muted"><?= e($t['unit_name'] ?? '—') ?></td>
              <td><?= $typeBadge ?></td>
              <td>
                <code class="small"><?= e($txnNo) ?></code>
                <?php if (!$isDemand && !empty($t['proof_file'])): ?>
                  <a href="<?= e($t['proof_file']) ?>" target="_blank" rel="noopener"
                     class="ms-1 small text-decoration-none" title="View proof">
                    <i class="bi bi-paperclip"></i>
                  </a>
                <?php endif; ?>
              </td>
              <td class="text-end fw-medium">₹<?= number_format((float)$t['amount'], 2) ?></td>
              <td><?= $statusBadgeHtml ?></td>
              <td class="text-end">
                <a href="/unit/athletes/<?= e($regHash) ?>" class="btn btn-sm btn-outline-primary"
                   title="Open registration">
                  <i class="bi bi-eye"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light">
          <tr>
            <th colspan="5" class="text-end">Totals (non-demand)</th>
            <th class="text-end">
              ₹<?= number_format($sumApproved + $sumPending + $sumRejected, 2) ?>
            </th>
            <th colspan="2" class="small text-muted">
              Approved ₹<?= number_format($sumApproved, 2) ?>
              · Pending ₹<?= number_format($sumPending, 2) ?>
              · Rejected ₹<?= number_format($sumRejected, 2) ?>
            </th>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
function txPrefillAmount() {
  const sel = document.getElementById('txRegSel');
  const amt = document.getElementById('txAmount');
  if (!sel || !amt) return;
  const opt = sel.selectedOptions[0];
  const bal = opt ? parseFloat(opt.dataset.balance || '0') : 0;
  if (bal > 0) amt.value = bal.toFixed(2);
}
</script>
