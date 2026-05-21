<?php $pageTitle = 'Fee Collection — ' . $event['name']; ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-cash-coin me-2"></i>Fee Collection</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <div class="ms-auto d-flex gap-2">
    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="downloadCsv()">
      <i class="bi bi-download me-1"></i>Download CSV
    </button>
    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="window.print()">
      <i class="bi bi-printer me-1"></i>Print
    </button>
  </div>
</div>
<p class="small text-muted mb-3"><i class="bi bi-info-circle me-1"></i>One row per submitted transaction (Individual + Team entry, manual + ePayment combined).</p>

<form method="GET" class="sms-card p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small mb-1">Transaction From</label>
      <input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm">
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1">Transaction To</label>
      <input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm">
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1">Mode</label>
      <select name="mode" class="form-select form-select-sm">
        <option value="">All</option>
        <option value="manual"   <?= $mode==='manual'   ? 'selected' : '' ?>>Manual</option>
        <option value="epayment" <?= $mode==='epayment' ? 'selected' : '' ?>>ePayment</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1">Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">All</option>
        <option value="approved" <?= $status==='approved' ? 'selected' : '' ?>>Approved</option>
        <option value="pending"  <?= $status==='pending'  ? 'selected' : '' ?>>Pending</option>
        <option value="rejected" <?= $status==='rejected' ? 'selected' : '' ?>>Rejected</option>
      </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Apply</button>
      <a href="/institution/events/<?= e($eventHash) ?>/reports/fee-collection" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
    </div>
  </div>
</form>

<!-- Summary tiles -->
<div class="row g-2 mb-3">
  <div class="col-6 col-md-3">
    <div class="border rounded-3 p-3 text-center bg-light-subtle">
      <div class="small text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em">Approved</div>
      <div class="fw-bold text-success fs-5">₹<?= number_format($approved_total, 2) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="border rounded-3 p-3 text-center bg-light-subtle">
      <div class="small text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em">Pending</div>
      <div class="fw-bold text-warning fs-5">₹<?= number_format($pending_total, 2) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="border rounded-3 p-3 text-center bg-light-subtle">
      <div class="small text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em">Rejected</div>
      <div class="fw-bold text-danger fs-5">₹<?= number_format($rejected_total, 2) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="border rounded-3 p-3 text-center bg-light-subtle">
      <div class="small text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em">Grand Total</div>
      <div class="fw-bold text-primary fs-5">₹<?= number_format($grand_total, 2) ?></div>
    </div>
  </div>
</div>

<div class="sms-card">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0" id="feeTable">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Type</th>
          <th>Participant / Team</th>
          <th>Club / Unit</th>
          <th>Contact</th>
          <th>Mode</th>
          <th>Txn Number</th>
          <th>Txn Date</th>
          <th class="text-end">Amount</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="10" class="text-muted text-center py-4">No transactions match the filters.</td></tr>
        <?php else: foreach ($rows as $i => $r):
            $type = $r['entry_type'] ?? 'Individual';
        ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td>
              <?php if ($type === 'Team'): ?>
                <span class="badge bg-primary-subtle text-primary"><i class="bi bi-people me-1"></i>Team</span>
              <?php else: ?>
                <span class="badge bg-warning-subtle text-warning"><i class="bi bi-person me-1"></i>Individual</span>
              <?php endif; ?>
            </td>
            <td class="fw-medium"><?= e($r['payer_name'] ?? '') ?></td>
            <td class="small text-muted"><?= e($r['unit_name'] ?? $r['unit_name_other'] ?? '—') ?></td>
            <td class="small text-muted"><?= e($r['payer_mobile'] ?? '') ?></td>
            <td>
              <?php if (($r['payment_method'] ?? 'manual') === 'epayment'): ?>
                <span class="badge bg-info-subtle text-info"><i class="bi bi-credit-card me-1"></i>ePayment</span>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-bank me-1"></i>Manual</span>
              <?php endif; ?>
            </td>
            <td>
              <code class="small"><?= e($r['transaction_number']) ?></code>
              <?php if (!empty($r['razorpay_payment_id'])): ?>
                <div class="text-muted small">pay <code><?= e($r['razorpay_payment_id']) ?></code></div>
              <?php endif; ?>
            </td>
            <td class="small text-muted"><?= formatDate($r['transaction_date']) ?></td>
            <td class="text-end fw-medium">₹<?= number_format((float)$r['amount'], 2) ?></td>
            <td><?= statusBadge($r['status']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
      <?php if (!empty($rows)): ?>
      <tfoot class="table-light">
        <tr>
          <th colspan="8" class="text-end">Grand Total (<?= count($rows) ?> transaction<?= count($rows) === 1 ? '' : 's' ?>)</th>
          <th class="text-end">₹<?= number_format($grand_total, 2) ?></th>
          <th></th>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<script>
function downloadCsv() {
  const rows = document.querySelectorAll('#feeTable tbody tr');
  if (!rows.length || rows[0].cells.length === 1) { alert('Nothing to export.'); return; }
  const lines = [['#','Type','Participant / Team','Club / Unit','Contact','Mode','Txn Number','Txn Date','Amount','Status']];
  rows.forEach(tr => {
    const cells = tr.cells;
    if (cells.length < 10) return;
    lines.push([
      cells[0].textContent.trim(),
      cells[1].textContent.trim(),
      cells[2].textContent.trim(),
      cells[3].textContent.trim(),
      cells[4].textContent.trim(),
      cells[5].textContent.trim(),
      cells[6].textContent.trim().replace(/\s+/g, ' '),
      cells[7].textContent.trim(),
      cells[8].textContent.replace('₹','').trim(),
      cells[9].textContent.trim(),
    ]);
  });
  lines.push([]);
  lines.push(['', '', '', '', '', '', '', 'Grand Total', '<?= number_format($grand_total, 2) ?>', '']);
  const csv = lines.map(r => r.map(c => '"' + (c || '').replace(/"/g, '""') + '"').join(',')).join('\r\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'fee-collection-event-<?= (int)$event['id'] ?>-' + (new Date().toISOString().slice(0,10)) + '.csv';
  document.body.appendChild(a); a.click(); a.remove();
  URL.revokeObjectURL(url);
}
</script>
