<?php
$pageTitle = 'ePayment Summary';
$f = $filters;
$mask = function (?string $acct): string {
    $acct = trim((string)$acct);
    if ($acct === '') return '—';
    if (strlen($acct) <= 4) return $acct;
    return str_repeat('•', max(0, strlen($acct) - 4)) . substr($acct, -4);
};
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/admin/reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-credit-card-2-front me-2"></i>ePayment Summary by Event Administrator</h5>
  <div class="ms-auto d-flex gap-2">
    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="downloadCsv()">
      <i class="bi bi-download me-1"></i>Download CSV
    </button>
    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="window.print()">
      <i class="bi bi-printer me-1"></i>Print
    </button>
  </div>
</div>
<p class="small text-muted mb-3">
  <i class="bi bi-info-circle me-1"></i>One row per event; counts &amp; amounts cover every Razorpay
  transaction recorded against that event. Bank account is the payout destination configured by the event administrator.
</p>

<form method="GET" class="sms-card p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-md-2">
      <label class="form-label small mb-1">Txn From</label>
      <input type="date" name="from" value="<?= e($f['from']) ?>" class="form-control form-control-sm">
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1">Txn To</label>
      <input type="date" name="to" value="<?= e($f['to']) ?>" class="form-control form-control-sm">
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1">Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">All</option>
        <option value="approved" <?= $f['status']==='approved' ? 'selected':'' ?>>Approved</option>
        <option value="pending"  <?= $f['status']==='pending'  ? 'selected':'' ?>>Pending</option>
        <option value="rejected" <?= $f['status']==='rejected' ? 'selected':'' ?>>Rejected</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1">Search (event / institution / Razorpay ID)</label>
      <input type="text" name="q" value="<?= e($f['q']) ?>" class="form-control form-control-sm" placeholder="…">
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Apply</button>
      <a href="/admin/reports/epayments" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
    </div>
  </div>
</form>

<?php if (empty($rows)): ?>
  <div class="sms-empty-state">
    <i class="bi bi-receipt"></i>
    <h5>No ePayment Transactions</h5>
    <p>No epayment rows match the current filters.</p>
  </div>
<?php else: ?>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Total Txns</div>
      <div class="fs-3 fw-bold"><?= (int)$totals['txn_count'] ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Approved Amount</div>
      <div class="fs-3 fw-bold text-success">₹<?= number_format($totals['approved_amount'], 2) ?></div>
      <div class="small text-muted"><?= (int)$totals['approved_count'] ?> txns</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Pending Amount</div>
      <div class="fs-3 fw-bold text-warning">₹<?= number_format($totals['pending_amount'], 2) ?></div>
      <div class="small text-muted"><?= (int)$totals['pending_count'] ?> txns</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Grand Total</div>
      <div class="fs-3 fw-bold">₹<?= number_format($totals['total_amount'], 2) ?></div>
    </div>
  </div>
</div>

<div class="sms-card">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0" id="epaymentsTable">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Event Administrator</th>
          <th>Event</th>
          <th>Bank / Branch</th>
          <th>A/C No.</th>
          <th>IFSC</th>
          <th class="text-end">Txns</th>
          <th class="text-end">Approved (₹)</th>
          <th class="text-end">Pending (₹)</th>
          <th class="text-end">Rejected (₹)</th>
          <th class="text-end">Total (₹)</th>
        </tr>
      </thead>
      <tbody>
        <?php $i = 1; foreach ($rows as $r): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td class="fw-medium"><?= e($r['institution_name']) ?></td>
            <td>
              <?= e($r['event_name']) ?>
              <div class="small text-muted">
                <?= $r['first_txn'] ? formatDate($r['first_txn'], 'd M Y') : '—' ?>
                – <?= $r['last_txn'] ? formatDate($r['last_txn'], 'd M Y') : '—' ?>
              </div>
            </td>
            <td>
              <?php if (!empty($r['bank_name'])): ?>
                <div class="fw-medium"><?= e($r['bank_name']) ?></div>
                <div class="small text-muted"><?= e($r['bank_branch'] ?? '') ?></div>
              <?php else: ?>
                <span class="badge bg-warning-subtle text-warning">Not configured</span>
              <?php endif; ?>
            </td>
            <td><code><?= e($mask($r['bank_account_number'] ?? '')) ?></code></td>
            <td><code><?= e($r['bank_ifsc'] ?? '—') ?></code></td>
            <td class="text-end">
              <?= (int)$r['txn_count'] ?>
              <div class="small text-muted">
                <?= (int)$r['approved_count'] ?>A / <?= (int)$r['pending_count'] ?>P / <?= (int)$r['rejected_count'] ?>R
              </div>
            </td>
            <td class="text-end text-success">₹<?= number_format((float)$r['approved_amount'], 2) ?></td>
            <td class="text-end text-warning">₹<?= number_format((float)$r['pending_amount'], 2) ?></td>
            <td class="text-end text-danger">₹<?= number_format((float)$r['rejected_amount'], 2) ?></td>
            <td class="text-end fw-bold">₹<?= number_format((float)$r['total_amount'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light">
        <tr>
          <th colspan="6" class="text-end">Grand Total</th>
          <th class="text-end"><?= (int)$totals['txn_count'] ?></th>
          <th class="text-end">₹<?= number_format($totals['approved_amount'], 2) ?></th>
          <th class="text-end">₹<?= number_format($totals['pending_amount'], 2) ?></th>
          <th class="text-end">₹<?= number_format($totals['rejected_amount'], 2) ?></th>
          <th class="text-end">₹<?= number_format($totals['total_amount'], 2) ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
<?php endif; ?>

<script>
function downloadCsv() {
  const tbl = document.getElementById('epaymentsTable');
  if (!tbl) return;
  const rows = [...tbl.querySelectorAll('tr')].map(tr =>
    [...tr.querySelectorAll('th,td')]
      .map(c => '"' + (c.innerText || '').replace(/\s+/g, ' ').trim().replace(/"/g, '""') + '"')
      .join(',')
  ).join('\n');
  const blob = new Blob([rows], { type: 'text/csv;charset=utf-8' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'epayment-summary-' + new Date().toISOString().slice(0,10) + '.csv';
  a.click();
}
</script>
