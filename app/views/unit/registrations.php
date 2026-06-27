<?php
$pageTitle = 'Registrations';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Pre-compute per-row derived values so JS doesn't have to re-derive
// when it filters / decides whether the row is bulk-payable.
$displayRows = [];
$totalDemandAll = 0.0; $totalClaimedAll = 0.0; $totalApprovedAll = 0.0;
foreach ($registrations as $r) {
    $demand   = (float)($r['total_amount']    ?? 0);
    $claimed  = (float)($r['claimed_amount']  ?? 0);
    $approved = (float)($r['approved_amount'] ?? 0);
    $balance  = round($demand - $claimed, 2);
    $rs       = (string)($r['admin_review_status'] ?? '');
    if ($demand <= 0) {
        [$txCls, $txLbl, $txKey] = ['secondary', 'No demand',       'no_demand'];
    } elseif ($approved + 0.005 >= $demand) {
        [$txCls, $txLbl, $txKey] = ['success',   'Paid',            'paid'];
    } elseif ($claimed + 0.005 >= $demand) {
        [$txCls, $txLbl, $txKey] = ['warning text-dark', 'Awaiting review', 'awaiting'];
    } elseif ($claimed > 0) {
        [$txCls, $txLbl, $txKey] = ['warning text-dark', 'Partial', 'partial'];
    } else {
        [$txCls, $txLbl, $txKey] = ['danger', 'No payment', 'no_payment'];
    }
    // Submission verdicts.
    $rsMap = [
        ''         => ['secondary', 'Draft',             'draft'],
        'pending'  => ['info',      'Pending review',    'submitted'],
        'approved' => ['success',   'Approved',          'approved'],
        'rejected' => ['danger',    'Rejected',          'rejected'],
        'returned' => ['warning text-dark', 'Returned for edit', 'returned'],
    ];
    [$rsCls, $rsLbl, $rsKey] = $rsMap[$rs] ?? ['secondary', ucfirst($rs ?: 'Draft'), 'draft'];
    $isEditable = in_array($rsKey, ['draft', 'returned'], true);
    $canBulkPay = $isEditable && $balance > 0.005;
    $displayRows[] = [
        'row'          => $r,
        'demand'       => $demand,
        'claimed'      => $claimed,
        'approved'     => $approved,
        'balance'      => $balance,
        'tx_class'     => $txCls,
        'tx_label'     => $txLbl,
        'tx_key'       => $txKey,
        'rs_class'     => $rsCls,
        'rs_label'     => $rsLbl,
        'rs_key'       => $rsKey,
        'can_bulk_pay' => $canBulkPay,
        'reg_hash'     => hid_reg((int)$r['id']),
    ];
    $totalDemandAll   += $demand;
    $totalClaimedAll  += $claimed;
    $totalApprovedAll += $approved;
}
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-data me-2"></i>Registrations</h5>
  <span class="text-muted small ms-2">on <?= e($event['name'] ?? '') ?></span>
  <div class="ms-auto d-flex gap-2 flex-wrap">
    <a href="/unit/athletes/new" class="btn btn-sm btn-success">
      <i class="bi bi-person-plus me-1"></i>Add Athlete
    </a>
    <button type="button" id="bulkPayBtn" class="btn btn-sm btn-primary" disabled
            data-bs-toggle="modal" data-bs-target="#bulkPayModal">
      <i class="bi bi-cash-coin me-1"></i>Log Bulk Payment Transaction
      <span class="badge bg-light text-dark ms-1" id="bulkPayBtnCount">0</span>
    </button>
  </div>
</div>

<?= flashBag() ?>

<?php if (empty($displayRows)): ?>
  <div class="sms-card p-3">
    <div class="text-center text-muted py-4">
      <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
      You haven&rsquo;t registered any athletes on this event yet.
      Click <a href="/unit/athletes/new">Add Athlete</a> to start.
    </div>
  </div>
<?php else: ?>

  <!-- Filters -->
  <div class="sms-card p-3 mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label small mb-1">Athlete name</label>
        <input type="search" id="fName" class="form-control form-control-sm"
               placeholder="Type to filter…" oninput="applyFilters()">
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Payment Status</label>
        <select id="fPay" class="form-select form-select-sm" onchange="applyFilters()">
          <option value="">All</option>
          <option value="paid">Demand fully paid (approved)</option>
          <option value="awaiting">Awaiting review (claimed)</option>
          <option value="partial">Partial</option>
          <option value="no_payment">Not paid</option>
          <option value="no_demand">No demand</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Registration Status</label>
        <select id="fReg" class="form-select form-select-sm" onchange="applyFilters()">
          <option value="">All</option>
          <option value="draft">Draft</option>
          <option value="submitted">Submitted</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
          <option value="returned">Returned</option>
        </select>
      </div>
      <div class="col-md-2 text-md-end">
        <button type="button" class="btn btn-sm btn-outline-secondary w-100" onclick="clearFilters()">
          <i class="bi bi-x-circle me-1"></i>Clear
        </button>
      </div>
    </div>
  </div>

  <div class="sms-card p-3">
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0" id="regsTable">
        <thead class="table-light">
          <tr>
            <th style="width:40px">
              <input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleAll(this)">
            </th>
            <th>Athlete</th>
            <th>Unit</th>
            <th class="text-center">Events</th>
            <th class="text-end">Demand</th>
            <th class="text-end">Claimed</th>
            <th class="text-end">Balance</th>
            <th>Transactions</th>
            <th>Submission</th>
            <th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($displayRows as $d): $r = $d['row']; ?>
            <tr data-name="<?= e(mb_strtolower((string)($r['athlete_name'] ?? ''))) ?>"
                data-pay="<?= e($d['tx_key']) ?>"
                data-reg="<?= e($d['rs_key']) ?>"
                data-balance="<?= e(number_format($d['balance'], 2, '.', '')) ?>"
                data-payable="<?= $d['can_bulk_pay'] ? '1' : '0' ?>"
                data-name-label="<?= e($r['athlete_name'] ?? '') ?>">
              <td>
                <input type="checkbox" class="form-check-input row-check"
                       value="<?= (int)$r['id'] ?>"
                       <?= $d['can_bulk_pay'] ? '' : 'disabled' ?>
                       title="<?= $d['can_bulk_pay'] ? 'Include in bulk payment' : 'Not eligible — no balance or registration locked' ?>"
                       onchange="updateBulkBar()">
              </td>
              <td>
                <div class="fw-medium"><?= e($r['athlete_name']) ?></div>
                <div class="small text-muted">
                  <?= e(genderLabel((string)($r['gender'] ?? ''), $event)) ?>
                  <?php if (!empty($r['date_of_birth'])): ?>
                    · <?= (int)ageFromDob($r['date_of_birth']) ?> yrs
                  <?php endif; ?>
                </div>
              </td>
              <td class="small"><?= e($r['unit_name'] ?? '—') ?></td>
              <td class="text-center"><?= (int)($r['items_count'] ?? 0) ?></td>
              <td class="text-end">₹<?= number_format($d['demand'], 2) ?></td>
              <td class="text-end">₹<?= number_format($d['claimed'], 2) ?></td>
              <td class="text-end <?= $d['balance'] > 0.005 ? 'text-danger' : ($d['balance'] < -0.005 ? 'text-warning' : 'text-success') ?>">
                ₹<?= number_format($d['balance'], 2) ?>
              </td>
              <td><span class="badge bg-<?= e($d['tx_class']) ?>"><?= e($d['tx_label']) ?></span></td>
              <td><span class="badge bg-<?= e($d['rs_class']) ?>"><?= e($d['rs_label']) ?></span></td>
              <td class="text-end">
                <a href="/unit/athletes/<?= e($d['reg_hash']) ?>" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-eye"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light">
          <tr id="totalsRow">
            <th colspan="4" class="text-end">Totals (visible)</th>
            <th class="text-end" id="totDemand">₹<?= number_format($totalDemandAll, 2) ?></th>
            <th class="text-end" id="totClaimed">₹<?= number_format($totalClaimedAll, 2) ?></th>
            <th class="text-end" id="totBalance">₹<?= number_format($totalDemandAll - $totalClaimedAll, 2) ?></th>
            <th colspan="3" class="text-end small text-muted">
              Approved across all rows: ₹<?= number_format($totalApprovedAll, 2) ?>
            </th>
          </tr>
        </tfoot>
      </table>
    </div>
    <p class="small text-muted mt-2 mb-0">
      <i class="bi bi-info-circle me-1"></i>
      Only rows in <em>Draft</em> / <em>Returned</em> with a positive balance can be bulk-paid;
      others have their checkbox disabled.
    </p>
  </div>

  <!-- ── Bulk Payment Transaction modal ── -->
  <div class="modal fade" id="bulkPayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <form method="POST" action="/unit/registrations/bulk-pay" enctype="multipart/form-data" onsubmit="return prepareBulkSubmit(this);">
          <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
          <div class="modal-header">
            <h6 class="modal-title fw-semibold">
              <i class="bi bi-cash-coin me-2"></i>Log Bulk Payment Transaction
            </h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p class="small text-muted">
              One bank transaction covering the selected athletes. We&rsquo;ll create
              one pending payment row per athlete using their outstanding balance,
              all sharing the same date / number / proof file.
            </p>
            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label small mb-1">Date <span class="text-danger">*</span></label>
                <input type="date" name="transaction_date" class="form-control form-control-sm"
                       max="<?= date('Y-m-d') ?>" required value="<?= date('Y-m-d') ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Transaction Number <span class="text-danger">*</span></label>
                <input type="text" name="transaction_number" class="form-control form-control-sm" maxlength="100" required>
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1"># Transactions</label>
                <input type="text" id="modalTxnCount" class="form-control form-control-sm bg-light" readonly>
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Total Amount (₹)</label>
                <input type="text" id="modalTxnTotal" class="form-control form-control-sm bg-light" readonly>
              </div>
              <div class="col-12">
                <label class="form-label small mb-1">Proof File <span class="text-danger">*</span></label>
                <input type="file" name="transaction_proof" class="form-control form-control-sm"
                       accept="image/jpeg,image/png,image/webp,application/pdf" required>
                <small class="text-muted d-block mt-1">
                  Same file is attached to every created payment row.
                </small>
              </div>
              <div class="col-12">
                <label class="form-label small mb-1">Selected Athletes</label>
                <ul id="modalAthleteList" class="small mb-0" style="max-height:160px;overflow:auto;border:1px solid #e2e8f0;border-radius:6px;padding:.5rem .75rem"></ul>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary fw-semibold">
              <i class="bi bi-save me-1"></i>Save Bulk Transaction
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<script>
function rowMatchesFilters(tr) {
  const name = (document.getElementById('fName').value || '').toLowerCase().trim();
  const pay  = document.getElementById('fPay').value;
  const reg  = document.getElementById('fReg').value;
  if (name && !(tr.dataset.name || '').includes(name)) return false;
  if (pay  && tr.dataset.pay !== pay) return false;
  if (reg  && tr.dataset.reg !== reg) return false;
  return true;
}
function applyFilters() {
  const rows = document.querySelectorAll('#regsTable tbody tr');
  let demand = 0, claimed = 0;
  rows.forEach(tr => {
    if (rowMatchesFilters(tr)) {
      tr.style.display = '';
      // Pull demand/claimed from the rendered cells (₹X,XXX.XX format).
      const cells = tr.querySelectorAll('td');
      demand  += parseFloat((cells[4]?.innerText || '0').replace(/[^\d.-]/g, '')) || 0;
      claimed += parseFloat((cells[5]?.innerText || '0').replace(/[^\d.-]/g, '')) || 0;
    } else {
      tr.style.display = 'none';
      // Uncheck filtered-out rows so they don't sneak into the bulk POST.
      const cb = tr.querySelector('.row-check');
      if (cb && cb.checked) cb.checked = false;
    }
  });
  const fmt = v => '₹' + (v).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  document.getElementById('totDemand').innerText  = fmt(demand);
  document.getElementById('totClaimed').innerText = fmt(claimed);
  document.getElementById('totBalance').innerText = fmt(demand - claimed);
  // Re-sync select-all + bulk button.
  syncSelectAll();
  updateBulkBar();
}
function clearFilters() {
  document.getElementById('fName').value = '';
  document.getElementById('fPay').value  = '';
  document.getElementById('fReg').value  = '';
  applyFilters();
}
function toggleAll(master) {
  document.querySelectorAll('#regsTable tbody tr').forEach(tr => {
    if (tr.style.display === 'none') return;
    const cb = tr.querySelector('.row-check');
    if (cb && !cb.disabled) cb.checked = master.checked;
  });
  updateBulkBar();
}
function syncSelectAll() {
  const master = document.getElementById('selectAll');
  if (!master) return;
  const eligible = Array.from(document.querySelectorAll('#regsTable tbody tr'))
    .filter(tr => tr.style.display !== 'none')
    .map(tr => tr.querySelector('.row-check'))
    .filter(cb => cb && !cb.disabled);
  if (!eligible.length) { master.checked = false; master.indeterminate = false; return; }
  const checked = eligible.filter(cb => cb.checked).length;
  master.checked      = checked === eligible.length;
  master.indeterminate= checked > 0 && checked < eligible.length;
}
function updateBulkBar() {
  syncSelectAll();
  const checked = Array.from(document.querySelectorAll('.row-check:checked'));
  let total = 0;
  checked.forEach(cb => {
    const tr = cb.closest('tr');
    total += parseFloat(tr.dataset.balance || '0') || 0;
  });
  document.getElementById('bulkPayBtnCount').innerText = checked.length;
  document.getElementById('bulkPayBtn').disabled       = checked.length === 0 || total <= 0;
  // Mirror into the modal too in case it's already open.
  const c = document.getElementById('modalTxnCount');
  const a = document.getElementById('modalTxnTotal');
  if (c) c.value = checked.length;
  if (a) a.value = total.toFixed(2);
  const list = document.getElementById('modalAthleteList');
  if (list) {
    list.innerHTML = checked.map(cb => {
      const tr  = cb.closest('tr');
      const nm  = tr.dataset.nameLabel || '';
      const bal = parseFloat(tr.dataset.balance || '0') || 0;
      return '<li class="d-flex justify-content-between"><span>'
           + nm.replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]))
           + '</span><span class="text-muted">₹' + bal.toFixed(2) + '</span></li>';
    }).join('') || '<li class="text-muted">No rows selected.</li>';
  }
}
function prepareBulkSubmit(form) {
  // Append the selected registration_ids[] right before submit so the
  // form payload always matches the live checkbox state.
  form.querySelectorAll('input.bulkHidden').forEach(n => n.remove());
  const checked = document.querySelectorAll('.row-check:checked');
  if (!checked.length) {
    alert('Pick at least one athlete to bulk-pay.');
    return false;
  }
  checked.forEach(cb => {
    const i = document.createElement('input');
    i.type = 'hidden'; i.name = 'registration_ids[]'; i.value = cb.value;
    i.className = 'bulkHidden';
    form.appendChild(i);
  });
  return true;
}
document.addEventListener('DOMContentLoaded', () => { applyFilters(); });
</script>
