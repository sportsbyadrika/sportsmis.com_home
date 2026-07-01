<?php
$pageTitle = 'Transactions';
?>

<?php $bulkPay = (($event['unit_payment_mode'] ?? 'individual') === 'bulk'); ?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <h5 class="mb-0 fw-bold"><i class="bi bi-cash-stack me-2"></i>Transactions</h5>
  <span class="text-muted small ms-2">on <?= e($event['name'] ?? '') ?></span>
  <?php if ($bulkPay): ?>
  <a href="/unit/registrations" class="btn btn-sm btn-outline-primary ms-auto">
    <i class="bi bi-cash-coin me-1"></i>Log Bulk Payment Transaction
  </a>
  <?php endif; ?>
</div>

<?= flashBag() ?>

<!-- All Transactions list -->
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
