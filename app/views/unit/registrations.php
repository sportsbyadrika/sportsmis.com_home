<?php
$pageTitle = 'Registrations';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
// Bulk payment is only offered when the event admin set Unit Payment Mode
// to "bulk"; otherwise fees are logged per-athlete on each registration.
$bulkPay = (($event['unit_payment_mode'] ?? 'individual') === 'bulk');

// In bulk mode registrations can only be submitted once the unit's committed
// (submitted + approved) collection covers the whole demand — this mirrors the
// server-side bulkPaymentGate(). The Submit Applications button stays locked
// until then, and a banner explains the shortfall.
$bulkDemand    = (float)($bulk_demand_total ?? 0);
$bulkCommitted = (float)($bulk_committed ?? 0);
$paymentCovered = !$bulkPay || ($bulkDemand <= 0) || (($bulkCommitted + 0.005) >= $bulkDemand);
$bulkShortfall  = max(0.0, round($bulkDemand - $bulkCommitted, 2));

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
    // Submittable gate.
    //  · Individual mode: editable, has demand, and the claimed (pending +
    //    approved) transactions settle the per-registration demand.
    //  · Bulk mode: payment is validated at the unit level (total collection
    //    ≥ total demand) on the Transactions page, so per-registration only
    //    requires it be editable with at least one event. The server re-checks
    //    the unit-level collection and blocks with a message if it's short.
    $canSubmit  = $bulkPay
        ? ($isEditable && (int)($r['items_count'] ?? 0) > 0)
        : ($isEditable && $demand > 0 && abs($balance) < 0.005);
    // Delete is allowed for a clean never-submitted draft (no events) OR for
    // a rejected / returned registration bounced back by the admin — matches
    // the server-side guard in the controller.
    $isCleanDraft = ($rs === '' || $rs === null)
        && empty($r['submitted_at'])
        && (int)($r['items_count'] ?? 0) === 0;
    $isBounced  = in_array($rs, ['rejected', 'returned'], true);
    $canDelete  = $isCleanDraft || $isBounced;
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
        'can_submit'   => $canSubmit,
        'can_delete'   => $canDelete,
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
    <?php if ($bulkPay): ?>
    <a href="/unit/transactions" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-cash-coin me-1"></i>Payment Transactions
    </a>
    <?php endif; ?>
    <button type="button" id="bulkSubmitBtn" class="btn btn-sm btn-warning" disabled
            onclick="submitBulkApplications()">
      <i class="bi bi-send-check me-1"></i>Submit Applications
      <span class="badge bg-light text-dark ms-1" id="bulkSubmitBtnCount">0</span>
    </button>
  </div>
</div>

<?= flashBag() ?>

<?php if ($bulkPay && !empty($displayRows)): ?>
  <?php if ($paymentCovered): ?>
    <div class="alert alert-success d-flex align-items-start gap-2 py-2">
      <i class="bi bi-check-circle-fill mt-1"></i>
      <div class="small">
        Your unit&rsquo;s committed transactions
        (<strong>₹<?= number_format($bulkCommitted, 2) ?></strong>) cover the total demand
        (<strong>₹<?= number_format($bulkDemand, 2) ?></strong>).
        You can now select athletes and <strong>Submit Applications</strong> for the event
        administrator&rsquo;s review.
      </div>
    </div>
  <?php else: ?>
    <div class="alert alert-warning d-flex align-items-start gap-2 py-2">
      <i class="bi bi-exclamation-triangle-fill mt-1"></i>
      <div class="small">
        <strong>Submit Applications is locked.</strong>
        Athletes can be submitted only after your bulk payment transactions are
        <strong>submitted</strong> and cover the total demand.
        Committed so far: <strong>₹<?= number_format($bulkCommitted, 2) ?></strong>
        of <strong>₹<?= number_format($bulkDemand, 2) ?></strong>
        (shortfall <strong>₹<?= number_format($bulkShortfall, 2) ?></strong>).
        Go to <a href="/unit/transactions" class="alert-link">Payment Transactions</a>,
        add and <strong>submit</strong> your transfer(s), then return here to submit the athletes.
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

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
                data-submittable="<?= $d['can_submit'] ? '1' : '0' ?>"
                data-name-label="<?= e($r['athlete_name'] ?? '') ?>">
              <td>
                <input type="checkbox" class="form-check-input row-check"
                       value="<?= (int)$r['id'] ?>"
                       title="Select for bulk payment or application submission"
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
              <td class="text-end text-nowrap">
                <a href="/unit/athletes/<?= e($d['reg_hash']) ?>" class="btn btn-sm btn-outline-primary" title="Open">
                  <i class="bi bi-eye"></i>
                </a>
                <?php if ($d['can_delete']):
                  $delMsg = $d['rs_key'] === 'rejected'
                    ? 'Delete rejected athlete ' . addslashes((string)($r['athlete_name'] ?? 'this athlete')) . ' from this event? This removes the registration and its events.'
                    : ($d['rs_key'] === 'returned'
                        ? 'Delete returned athlete ' . addslashes((string)($r['athlete_name'] ?? 'this athlete')) . ' from this event? This removes the registration and its events.'
                        : 'Delete ' . addslashes((string)($r['athlete_name'] ?? 'this athlete')) . ' from this event? This draft has no events and has not been submitted.');
                ?>
                  <form method="POST" action="/unit/athletes/<?= e($d['reg_hash']) ?>/delete"
                        class="d-inline"
                        onsubmit="return confirm('<?= e($delMsg) ?>');">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                            title="Delete <?= $d['rs_key'] === 'rejected' ? 'rejected' : ($d['rs_key'] === 'returned' ? 'returned' : 'draft') ?> athlete">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                <?php endif; ?>
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
      Select any rows with the checkboxes.
      <?php if ($bulkPay): ?>
        Fees are collected at the <strong>unit level</strong> on the
        <a href="/unit/transactions">Payment Transactions</a> page — registrations can be submitted
        only once your unit's committed collection covers its total demand.
        <strong>Submit Applications</strong> applies to selected editable rows with at least one event.
      <?php else: ?>
        <strong>Submit Applications</strong> applies only to selected rows that are editable,
        have at least one event, and are fully paid. Ineligible rows are skipped automatically.
      <?php endif; ?>
    </p>
  </div>

  <!-- Hidden form used by Submit Applications (bulk). -->
  <form id="bulkSubmitForm" method="POST" action="/unit/registrations/bulk-submit" class="d-none">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
  </form>

<?php endif; ?>

<script>
// In bulk mode the Submit Applications button is held closed until the unit's
// committed payment collection covers the total demand (server re-checks too).
const PAYMENT_LOCKED = <?= $paymentCovered ? 'false' : 'true' ?>;
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
  // Every visible row is now selectable — eligibility is decided per
  // action (pay vs submit), not by disabling the checkbox.
  const eligible = Array.from(document.querySelectorAll('#regsTable tbody tr'))
    .filter(tr => tr.style.display !== 'none')
    .map(tr => tr.querySelector('.row-check'))
    .filter(Boolean);
  if (!eligible.length) { master.checked = false; master.indeterminate = false; return; }
  const checked = eligible.filter(cb => cb.checked).length;
  master.checked      = checked === eligible.length;
  master.indeterminate= checked > 0 && checked < eligible.length;
}
function updateBulkBar() {
  syncSelectAll();
  const checked = Array.from(document.querySelectorAll('.row-check:checked'));
  // Bulk pay considers only the payable subset (Draft/Returned + balance).
  const payable = checked.filter(cb => cb.closest('tr').dataset.payable === '1');
  const submittable = checked.filter(cb => cb.closest('tr').dataset.submittable === '1');
  let total = 0;
  payable.forEach(cb => { total += parseFloat(cb.closest('tr').dataset.balance || '0') || 0; });

  // The bulk-pay button only exists in bulk mode — guard its references.
  var payBtn   = document.getElementById('bulkPayBtn');
  var payCount = document.getElementById('bulkPayBtnCount');
  if (payCount) payCount.innerText = payable.length;
  if (payBtn)   payBtn.disabled    = payable.length === 0 || total <= 0;
  document.getElementById('bulkSubmitBtnCount').innerText = submittable.length;
  document.getElementById('bulkSubmitBtn').disabled      = PAYMENT_LOCKED || submittable.length === 0;

  // Mirror the payable selection into the bulk-pay modal.
  const c = document.getElementById('modalTxnCount');
  const a = document.getElementById('modalTxnTotal');
  if (c) c.value = payable.length;
  if (a) a.value = total.toFixed(2);
  const list = document.getElementById('modalAthleteList');
  if (list) {
    list.innerHTML = payable.map(cb => {
      const tr  = cb.closest('tr');
      const nm  = tr.dataset.nameLabel || '';
      const bal = parseFloat(tr.dataset.balance || '0') || 0;
      return '<li class="d-flex justify-content-between"><span>'
           + nm.replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]))
           + '</span><span class="text-muted">₹' + bal.toFixed(2) + '</span></li>';
    }).join('') || '<li class="text-muted">No payable rows selected.</li>';
  }
}
function prepareBulkSubmit(form) {
  // Append only the PAYABLE selected registration_ids[] right before
  // submit so the payload matches the live checkbox state.
  form.querySelectorAll('input.bulkHidden').forEach(n => n.remove());
  const payable = Array.from(document.querySelectorAll('.row-check:checked'))
    .filter(cb => cb.closest('tr').dataset.payable === '1');
  if (!payable.length) {
    alert('Pick at least one payable athlete (Draft/Returned with a balance) to bulk-pay.');
    return false;
  }
  payable.forEach(cb => {
    const i = document.createElement('input');
    i.type = 'hidden'; i.name = 'registration_ids[]'; i.value = cb.value;
    i.className = 'bulkHidden';
    form.appendChild(i);
  });
  return true;
}
function submitBulkApplications() {
  if (PAYMENT_LOCKED) {
    alert('Applications are locked until your unit’s submitted payment transactions cover the total demand. '
        + 'Add and submit your transfer on the Payment Transactions page first.');
    return;
  }
  const submittable = Array.from(document.querySelectorAll('.row-check:checked'))
    .filter(cb => cb.closest('tr').dataset.submittable === '1');
  if (!submittable.length) {
    alert('Pick at least one fully-paid, editable registration to submit.');
    return;
  }
  if (!confirm('Submit ' + submittable.length + ' registration' + (submittable.length === 1 ? '' : 's')
      + ' to the event administrator for review?\n\n'
      + 'Note: once submitted, you cannot edit or delete these entries unless the administrator returns or rejects them.')) {
    return;
  }
  const form = document.getElementById('bulkSubmitForm');
  form.querySelectorAll('input.bulkHidden').forEach(n => n.remove());
  submittable.forEach(cb => {
    const i = document.createElement('input');
    i.type = 'hidden'; i.name = 'registration_ids[]'; i.value = cb.value;
    i.className = 'bulkHidden';
    form.appendChild(i);
  });
  form.submit();
}
document.addEventListener('DOMContentLoaded', () => { applyFilters(); });
</script>
