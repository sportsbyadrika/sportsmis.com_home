<?php $pageTitle = 'Pending ePayments'; ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/admin/reports/epayments" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>ePayment Summary
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-hourglass-split me-2"></i>Pending ePayment Transactions</h5>
  <span class="text-muted small ms-2"><?= count($rows) ?> open</span>
</div>
<p class="small text-muted mb-3">
  <i class="bi bi-info-circle me-1"></i>Rows where Razorpay returned an
  <code>order_id</code> but no captured payment has been recorded yet. Use
  <strong>Re-check</strong> to ask Razorpay <code>GET /v1/orders/{id}/payments</code>
  and update the row from their truth — same logic the reconcile cron runs every 15 minutes.
</p>

<?php if (empty($rows)): ?>
  <div class="sms-empty-state">
    <i class="bi bi-check-circle text-success"></i>
    <h5>No Pending ePayments</h5>
    <p>Every ePayment row has been resolved (paid / failed) by browser, webhook, or cron.</p>
  </div>
<?php else: ?>
<div class="sms-card">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Athlete</th>
          <th>Event / Institution</th>
          <th>Razorpay Order</th>
          <th class="text-end">Amount</th>
          <th>Created</th>
          <th>Last reconciled</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $i => $r): ?>
          <tr id="row-<?= (int)$r['id'] ?>">
            <td><?= $i + 1 ?></td>
            <td>
              <div class="fw-medium"><?= e($r['athlete_name']) ?></div>
              <div class="small text-muted">reg #<?= (int)$r['registration_id'] ?></div>
            </td>
            <td>
              <div><?= e($r['event_name']) ?></div>
              <div class="small text-muted"><?= e($r['institution_name']) ?></div>
            </td>
            <td><code class="small"><?= e($r['razorpay_order_id']) ?></code></td>
            <td class="text-end fw-medium">₹<?= number_format((float)$r['amount'], 2) ?></td>
            <td class="text-muted small"><?= formatDate($r['created_at'], 'd M Y H:i') ?></td>
            <td class="text-muted small">
              <?= !empty($r['reconciled_at']) ? formatDate($r['reconciled_at'], 'd M Y H:i') : '<em>never</em>' ?>
            </td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-outline-primary"
                      data-row-id="<?= (int)$r['id'] ?>"
                      onclick="recheckRow(this)">
                <i class="bi bi-arrow-clockwise me-1"></i>Re-check
              </button>
              <span class="d-block small text-muted mt-1" data-outcome></span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script>
async function recheckRow(btn) {
  const rowId = btn.dataset.rowId;
  const tr = document.getElementById('row-' + rowId);
  const outcomeEl = tr.querySelector('[data-outcome]');
  btn.disabled = true;
  outcomeEl.textContent = 'Querying Razorpay…';
  outcomeEl.className = 'd-block small text-muted mt-1';
  const fd = new FormData();
  fd.append('_token', '<?= e($_SESSION['csrf_token'] ?? '') ?>');
  fd.append('row_id', rowId);
  try {
    const res = await fetch('/admin/reports/epayments/recheck', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) {
      outcomeEl.textContent = data.message || 'Failed';
      outcomeEl.className = 'd-block small text-danger mt-1';
      btn.disabled = false;
      return;
    }
    outcomeEl.textContent = data.message + ' (status now: ' + (data.status || '?') + ')';
    const cls = data.outcome === 'paid'   ? 'text-success'
              : data.outcome === 'failed' ? 'text-danger'
              : 'text-muted';
    outcomeEl.className = 'd-block small mt-1 ' + cls;
    if (data.status === 'approved' || data.status === 'rejected') {
      // Row resolved — hide it after a beat to keep the queue tight.
      setTimeout(() => tr.classList.add('table-success', 'opacity-50'), 600);
    } else {
      btn.disabled = false;
    }
  } catch (e) {
    outcomeEl.textContent = 'Network error: ' + e.message;
    outcomeEl.className = 'd-block small text-danger mt-1';
    btn.disabled = false;
  }
}
</script>
