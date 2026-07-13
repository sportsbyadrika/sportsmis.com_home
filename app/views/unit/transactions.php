<?php
$pageTitle = 'Transactions';
$bulkMode  = !empty($bulk_mode);
$units     = $assigned_units ?? [];
$pool      = $pool_rows      ?? [];
$poolRejd  = $pool_rejected  ?? [];
$col       = $collection     ?? ['total'=>0,'draft'=>0,'submitted'=>0,'approved'=>0,'committed'=>0];
$dem       = $demand         ?? ['individual'=>0,'team'=>0,'total'=>0];
$committed = (float)$col['committed'];
$demandTot = (float)$dem['total'];
$shortfall = round($demandTot - $committed, 2);
$money = fn($v) => '₹' . number_format((float)$v, 2);
$poolBadge = [
    'draft'     => ['secondary',          'Draft'],
    'submitted' => ['info',               'Submitted'],
    'approved'  => ['success',            'Approved'],
    'rejected'  => ['danger',             'Rejected'],
];
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <h5 class="mb-0 fw-bold"><i class="bi bi-cash-stack me-2"></i>Transactions</h5>
  <span class="text-muted small ms-2">on <?= e($event['name'] ?? '') ?></span>
  <?php if ($bulkMode): ?>
  <button type="button" class="btn btn-sm btn-primary ms-auto" data-bs-toggle="modal" data-bs-target="#addUnitPayModal">
    <i class="bi bi-plus-circle me-1"></i>Add Payment Transaction
  </button>
  <?php endif; ?>
</div>

<?= flashBag() ?>

<?php
// Event bank / QR payment details (configured by the event admin).
$bankName   = trim((string)($event['bank_name'] ?? ''));
$bankBranch = trim((string)($event['bank_branch'] ?? ''));
$bankAcct   = trim((string)($event['bank_account_number'] ?? ''));
$bankIfsc   = trim((string)($event['bank_ifsc'] ?? ''));
$bankFree   = trim((string)($event['bank_details'] ?? ''));
$bankQr     = trim((string)($event['bank_qr_code'] ?? ''));
$hasBank    = ($bankName || $bankBranch || $bankAcct || $bankIfsc || $bankFree || $bankQr);
?>
<?php if ($hasBank): ?>
<div class="sms-card p-3 mb-3">
  <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-bank me-2"></i>Payment Details</h6>
  <div class="row g-3 align-items-start">
    <div class="col-md<?= $bankQr !== '' ? '-8' : '' ?>">
      <?php if ($bankName || $bankBranch || $bankAcct || $bankIfsc): ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-<?= $bankFree !== '' ? '3' : '0' ?>">
            <tbody>
              <?php if ($bankName): ?>
              <tr><th class="text-muted fw-normal" style="width:170px">Bank Name</th><td class="fw-medium"><?= e($bankName) ?></td></tr>
              <?php endif; ?>
              <?php if ($bankBranch): ?>
              <tr><th class="text-muted fw-normal">Branch</th><td><?= e($bankBranch) ?></td></tr>
              <?php endif; ?>
              <?php if ($bankAcct): ?>
              <tr><th class="text-muted fw-normal">Account Number</th><td class="font-monospace fw-medium"><?= e($bankAcct) ?></td></tr>
              <?php endif; ?>
              <?php if ($bankIfsc): ?>
              <tr><th class="text-muted fw-normal">IFSC</th><td class="font-monospace fw-medium"><?= e($bankIfsc) ?></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
      <?php if ($bankFree !== ''): ?>
        <div class="small text-muted"><i class="bi bi-info-circle me-1"></i><?= nl2br(e($bankFree)) ?></div>
      <?php endif; ?>
    </div>
    <?php if ($bankQr !== ''): ?>
      <div class="col-md-4 text-center">
        <div class="small text-muted mb-1">Scan to pay</div>
        <a href="<?= e($bankQr) ?>" target="_blank" rel="noopener">
          <img src="<?= e($bankQr) ?>" alt="Payment QR code"
               class="img-fluid rounded border" style="max-height:180px">
        </a>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($bulkMode): ?>
  <!-- ── Demand vs Collection summary ── -->
  <div class="row g-2 mb-3">
    <div class="col-6 col-md-3 col-xl">
      <div class="sms-card p-3 h-100">
        <div class="text-muted small text-uppercase">Individual Demand</div>
        <div class="fs-5 fw-bold"><?= $money($dem['individual']) ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-xl">
      <div class="sms-card p-3 h-100">
        <div class="text-muted small text-uppercase">Team Demand</div>
        <div class="fs-5 fw-bold"><?= $money($dem['team']) ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-xl">
      <div class="sms-card p-3 h-100 border-primary">
        <div class="text-muted small text-uppercase">Total Demand</div>
        <div class="fs-5 fw-bold text-primary"><?= $money($dem['total']) ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-xl">
      <div class="sms-card p-3 h-100">
        <div class="text-muted small text-uppercase">Transaction Total</div>
        <div class="fs-5 fw-bold"><?= $money($col['total']) ?></div>
        <div class="small text-muted">incl. drafts <?= $money($col['draft']) ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-xl">
      <div class="sms-card p-3 h-100">
        <div class="text-muted small text-uppercase">Submitted</div>
        <div class="fs-5 fw-bold text-info-emphasis"><?= $money($col['submitted']) ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-xl">
      <div class="sms-card p-3 h-100">
        <div class="text-muted small text-uppercase">Approved</div>
        <div class="fs-5 fw-bold text-success"><?= $money($col['approved']) ?></div>
      </div>
    </div>
  </div>

  <?php if ($demandTot > 0): ?>
    <div class="alert <?= $shortfall > 0.005 ? 'alert-warning' : 'alert-success' ?> d-flex align-items-center gap-2 py-2">
      <i class="bi <?= $shortfall > 0.005 ? 'bi-exclamation-triangle' : 'bi-check-circle' ?>"></i>
      <div class="small">
        <?php if ($shortfall > 0.005): ?>
          Committed collection (submitted + approved) is <strong><?= $money($committed) ?></strong> against a total demand of
          <strong><?= $money($demandTot) ?></strong> — a shortfall of <strong><?= $money($shortfall) ?></strong>.
          Add &amp; <strong>submit</strong> payment transactions until the collection meets the demand; only then can
          registrations be submitted for review.
        <?php else: ?>
          Committed collection of <strong><?= $money($committed) ?></strong> covers the total demand of
          <strong><?= $money($demandTot) ?></strong>. You can submit registrations for review.
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- ── Unit payment transactions (the pool) ── -->
  <div class="sms-card p-3 mb-4">
    <h6 class="fw-semibold border-bottom pb-2 mb-3">
      <i class="bi bi-wallet2 me-2"></i>Payment Transactions
    </h6>
    <?php if (empty($pool)): ?>
      <p class="text-muted small mb-0">
        No payment transactions yet. Use <strong>Add Payment Transaction</strong> to log a bank transfer,
        then submit it to the event admin.
      </p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Date</th>
              <?php if (count($units) > 1): ?><th>Unit</th><?php endif; ?>
              <th>Reference No.</th>
              <th class="text-end">Amount</th>
              <th class="text-center">Proof</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pool as $p):
              [$cls, $lbl] = $poolBadge[$p['status']] ?? ['secondary', ucfirst((string)$p['status'])];
              $isDraft = ($p['status'] ?? '') === 'draft';
            ?>
              <tr>
                <td class="small"><?= formatDate($p['transaction_date']) ?></td>
                <?php if (count($units) > 1): ?>
                  <td class="small text-muted"><?= e($p['unit_name'] ?? '—') ?></td>
                <?php endif; ?>
                <td><code class="small"><?= e($p['reference_number'] ?? '') ?></code></td>
                <td class="text-end fw-medium"><?= $money($p['amount']) ?></td>
                <td class="text-center">
                  <?php if (!empty($p['proof_file'])): ?>
                    <a href="<?= e($p['proof_file']) ?>" target="_blank" rel="noopener" title="View proof">
                      <i class="bi bi-paperclip"></i>
                    </a>
                  <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td><span class="badge bg-<?= e($cls) ?>"><?= e($lbl) ?></span></td>
                <td class="text-end text-nowrap">
                  <?php if ($isDraft): ?>
                    <form method="POST" action="/unit/transactions/payments/<?= (int)$p['id'] ?>/submit"
                          class="d-inline" onsubmit="return confirm('Submit this transaction to the event admin? You won\'t be able to delete it afterwards.');">
                      <?= csrf() ?>
                      <button class="btn btn-sm btn-outline-success" title="Submit to event admin">
                        <i class="bi bi-send me-1"></i>Submit
                      </button>
                    </form>
                    <form method="POST" action="/unit/transactions/payments/<?= (int)$p['id'] ?>/delete"
                          class="d-inline" onsubmit="return confirm('Delete this draft transaction?');">
                      <?= csrf() ?>
                      <button class="btn btn-sm btn-outline-danger" title="Delete draft">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  <?php elseif (($p['status'] ?? '') === 'submitted'): ?>
                    <span class="small text-muted">Awaiting review</span>
                  <?php else: ?>
                    <span class="small text-muted">Locked</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light">
            <tr class="fw-semibold">
              <th colspan="<?= count($units) > 1 ? 3 : 2 ?>" class="text-end">Total (non-rejected)</th>
              <th class="text-end"><?= $money($col['total']) ?></th>
              <th colspan="3"></th>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>

    <?php if (!empty($poolRejd)): ?>
      <div class="mt-3">
        <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#rejectedTxns">
          <i class="bi bi-x-octagon me-1"></i>Rejected transactions (<?= count($poolRejd) ?>)
        </button>
        <div class="collapse mt-2" id="rejectedTxns">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Date</th><th>Reference No.</th>
                  <th class="text-end">Amount</th><th>Reason</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($poolRejd as $p): ?>
                  <tr class="text-muted">
                    <td class="small"><?= formatDate($p['transaction_date']) ?></td>
                    <td><code class="small"><?= e($p['reference_number'] ?? '') ?></code></td>
                    <td class="text-end"><?= $money($p['amount']) ?></td>
                    <td class="small"><?= e($p['reject_reason'] ?? '—') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <p class="small text-muted mt-2 mb-0">
            Rejected transactions are removed from your totals — please add a fresh transaction.
          </p>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- ── Add Payment Transaction modal ── -->
  <div class="modal fade" id="addUnitPayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST" action="/unit/transactions/payments/add" enctype="multipart/form-data">
          <?= csrf() ?>
          <div class="modal-header">
            <h6 class="modal-title fw-semibold"><i class="bi bi-cash-coin me-2"></i>Add Payment Transaction</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p class="small text-muted">
              Log a bank transaction covering your unit's fees. Saved as a <strong>draft</strong>;
              submit it to send it to the event admin. You may add several transactions.
            </p>
            <div class="row g-3">
              <?php if (count($units) > 1): ?>
              <div class="col-12">
                <label class="form-label small mb-1">Unit <span class="text-danger">*</span></label>
                <select name="unit_id" class="form-select form-select-sm" required>
                  <option value="">— Select unit —</option>
                  <?php foreach ($units as $u): ?>
                    <option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php elseif (!empty($units)): ?>
                <input type="hidden" name="unit_id" value="<?= (int)$units[0]['id'] ?>">
              <?php endif; ?>
              <div class="col-md-6">
                <label class="form-label small mb-1">Transaction Date <span class="text-danger">*</span></label>
                <input type="date" name="transaction_date" class="form-control form-control-sm"
                       max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small mb-1">Reference Number <span class="text-danger">*</span></label>
                <input type="text" name="reference_number" class="form-control form-control-sm" maxlength="100" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small mb-1">Amount (₹) <span class="text-danger">*</span></label>
                <input type="number" name="amount" class="form-control form-control-sm" min="0.01" step="0.01" required>
              </div>
              <div class="col-12">
                <label class="form-label small mb-1">Screenshot / Proof <span class="text-danger">*</span></label>
                <input type="file" name="proof_file" class="form-control form-control-sm"
                       accept="image/jpeg,image/png,image/webp,application/pdf" required>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-save me-1"></i>Save Draft</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endif; // /bulkMode ?>

<!-- All Transactions list (per-athlete payment ledger) -->
<?php if (!$bulkMode || !empty($transactions)): ?>
<div class="sms-card p-3">
  <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-list-ul me-2"></i>All Transactions</h6>
  <?php if (empty($transactions)): ?>
    <p class="text-muted small mb-0">No transactions logged yet.</p>
  <?php else:
    // Footer totals across the page. $transactions is already filtered
    // to exclude legacy 'demand' placeholder rows in the controller.
    $sumApproved = 0.0; $sumPending = 0.0; $sumRejected = 0.0;
    foreach ($transactions as $t) {
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
            $isEpay  = $method === 'epayment';
            $txnNo   = $isEpay ? ($t['razorpay_payment_id'] ?: $t['razorpay_order_id'] ?: $t['transaction_number'])
                               : $t['transaction_number'];
            $typeBadge = $isEpay
              ? '<span class="badge bg-info-subtle text-info">ePayment</span>'
              : '<span class="badge bg-secondary-subtle text-secondary">Manual</span>';
            $regHash = hid_reg((int)$t['registration_id']);
          ?>
            <tr>
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
                <?php if (!empty($t['proof_file'])): ?>
                  <a href="<?= e($t['proof_file']) ?>" target="_blank" rel="noopener"
                     class="ms-1 small text-decoration-none" title="View proof">
                    <i class="bi bi-paperclip"></i>
                  </a>
                <?php endif; ?>
              </td>
              <td class="text-end fw-medium">₹<?= number_format((float)$t['amount'], 2) ?></td>
              <td><?= statusBadge($t['status']) ?></td>
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
            <th colspan="5" class="text-end">Totals</th>
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
<?php endif; ?>

<?php if (!empty($team_transactions)): ?>
<div class="sms-card p-3 mt-4">
  <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-people me-2"></i>Team Entry Transactions</h6>
  <?php
    $tSumApproved = 0.0; $tSumPending = 0.0; $tSumRejected = 0.0;
    foreach ($team_transactions as $t) {
      $st = (string)($t['status'] ?? '');
      if ($st === 'approved') $tSumApproved += (float)$t['amount'];
      elseif ($st === 'rejected') $tSumRejected += (float)$t['amount'];
      else $tSumPending += (float)$t['amount'];
    }
  ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Date</th>
          <th>Team</th>
          <th>Unit</th>
          <th>Txn No.</th>
          <th class="text-end">Amount</th>
          <th>Status</th>
          <th class="text-end"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($team_transactions as $t): ?>
          <tr>
            <td class="small"><?= formatDate($t['transaction_date']) ?></td>
            <td class="fw-medium"><?= e($t['team_name'] ?? '—') ?></td>
            <td class="small text-muted"><?= e($t['unit_name'] ?? '—') ?></td>
            <td>
              <code class="small"><?= e($t['transaction_number'] ?? '') ?></code>
              <?php if (!empty($t['proof_file'])): ?>
                <a href="<?= e($t['proof_file']) ?>" target="_blank" rel="noopener"
                   class="ms-1 small text-decoration-none" title="View proof">
                  <i class="bi bi-paperclip"></i>
                </a>
              <?php endif; ?>
            </td>
            <td class="text-end fw-medium">₹<?= number_format((float)$t['amount'], 2) ?></td>
            <td><?= statusBadge($t['status'] ?? 'pending') ?></td>
            <td class="text-end">
              <a href="/team-entry/<?= (int)$t['team_registration_id'] ?>" class="btn btn-sm btn-outline-secondary"
                 title="Open team entry"><i class="bi bi-eye"></i></a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light">
        <tr>
          <th colspan="4" class="text-end">Totals</th>
          <th class="text-end">₹<?= number_format($tSumApproved + $tSumPending + $tSumRejected, 2) ?></th>
          <th colspan="2" class="small text-muted">
            Approved ₹<?= number_format($tSumApproved, 2) ?>
            · Pending ₹<?= number_format($tSumPending, 2) ?>
            · Rejected ₹<?= number_format($tSumRejected, 2) ?>
          </th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
<?php endif; ?>
