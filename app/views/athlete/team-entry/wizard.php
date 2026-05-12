<?php
$pageTitle = 'Team Entry — ' . ($team['team_name'] ?? '');
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$paymentModes = $event['payment_modes'] ?? [];
$fee     = (float)($team['total_amount'] ?? 0);
$members = $members ?? [];
$payments = $payments ?? [];
$locked  = !\Models\TeamRegistration::isEditable($team);
$currentMode = $team['payment_mode'] ?? '';
$submittedAmt = (float)($pay_totals['submitted_amount'] ?? 0);
$approvedAmt  = (float)($pay_totals['approved_amount']  ?? 0);
?>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="teToast" class="toast align-items-center border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body fw-medium" id="teToastMsg"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/athlete/my-registrations" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i>Team Entry — <?= e($team['team_name']) ?></h5>
  <?= appStatusBadge($team['admin_review_status'] ?? null, $team['submitted_at'] ?? null) ?>
  <?= statusBadge($team['payment_status'] ?? 'pending') ?>
</div>

<?php if ($locked): ?>
  <div class="alert alert-info d-flex align-items-start gap-2 mb-4">
    <i class="bi bi-lock-fill fs-5"></i>
    <div>
      <strong>This team entry has been submitted and is locked for review.</strong>
      The event administrator will review your submission and either approve, reject, or return it for changes.
    </div>
  </div>
<?php endif; ?>

<!-- Athlete profile panel -->
<div class="sms-card p-3 mb-4">
  <div class="d-flex align-items-center gap-3 flex-wrap">
    <?php if (!empty($athlete['passport_photo'])): ?>
      <img src="<?= e($athlete['passport_photo']) ?>" alt="Photo"
           class="rounded-3 flex-shrink-0"
           style="width:64px;height:80px;object-fit:cover;border:1px solid #e2e8f0;background:#fff">
    <?php else: ?>
      <div class="sms-avatar sms-avatar-lg flex-shrink-0"><?= avatarInitials($athlete['name']) ?></div>
    <?php endif; ?>
    <div class="flex-grow-1 min-w-0">
      <div class="fw-bold fs-5 text-break"><?= e($athlete['name']) ?> <small class="text-muted">(Captain)</small></div>
      <div class="d-flex flex-wrap gap-3 small text-muted mt-1">
        <span><i class="bi bi-calendar-event me-1"></i><?= e($team['event_name']) ?></span>
        <?php if (!empty($team['unit_name'])): ?>
          <span><i class="bi bi-building me-1"></i><?= e($team['unit_name']) ?></span>
        <?php endif; ?>
        <?php if (!empty($team['sport_event_name'])): ?>
          <span><i class="bi bi-trophy me-1"></i><?= e($team['sport_event_name']) ?>
            <?php if (!empty($team['event_code'])): ?>(<code><?= e($team['event_code']) ?></code>)<?php endif; ?>
          </span>
        <?php endif; ?>
      </div>
    </div>
    <div class="text-end flex-shrink-0">
      <div class="text-muted small text-uppercase" style="letter-spacing:.04em">Team Fee</div>
      <div class="fs-4 fw-bold text-success">₹<?= number_format($fee, 2) ?></div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-8">

    <!-- Step 1 (read-only summary) -->
    <div class="sms-card p-4 mb-4">
      <div class="sms-step-head bg-primary-subtle text-primary-emphasis rounded-3 px-3 py-2 d-flex align-items-center mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-1-circle me-2"></i>Step 1 — Event & Team</h6>
      </div>
      <div class="row g-3 small">
        <div class="col-md-6">
          <div class="text-muted">Event</div>
          <div class="fw-medium"><?= e($team['event_name']) ?></div>
        </div>
        <div class="col-md-6">
          <div class="text-muted">Sport Event</div>
          <div class="fw-medium"><?= e($team['sport_event_name'] ?? '—') ?> <?php if (!empty($team['event_code'])): ?><code class="ms-1"><?= e($team['event_code']) ?></code><?php endif; ?></div>
        </div>
        <div class="col-md-6">
          <div class="text-muted">Team Name</div>
          <div class="fw-medium"><?= e($team['team_name']) ?></div>
        </div>
        <div class="col-md-6">
          <div class="text-muted">Club / Institution</div>
          <div class="fw-medium"><?= e($team['unit_name'] ?? '—') ?></div>
        </div>
      </div>
    </div>

    <!-- Step 2 — Team Members -->
    <div class="sms-card p-4 mb-4">
      <div class="sms-step-head bg-primary-subtle text-primary-emphasis rounded-3 px-3 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-2-circle me-2"></i>Step 2 — Select Team Members</h6>
        <span class="small">Members: <strong id="memberCount"><?= count($members) ?></strong> / 3</span>
      </div>

      <?php if (!$locked): ?>
      <div class="row g-2 align-items-end mb-3">
        <div class="col-md-4">
          <label class="form-label small mb-1">Competitor Number</label>
          <input type="number" id="te_comp" class="form-control" placeholder="e.g. 1023"
                 min="1" oninput="document.getElementById('lookupRes').innerHTML=''">
        </div>
        <div class="col-md-3">
          <button type="button" class="btn btn-outline-primary w-100" onclick="lookupMember()">
            <i class="bi bi-search me-1"></i>Validate
          </button>
        </div>
        <div class="col-md-5">
          <button type="button" class="btn btn-primary w-100" id="addMemberBtn"
                  onclick="addMember()" disabled>
            <i class="bi bi-plus-circle me-1"></i>Add as Team Member
          </button>
        </div>
      </div>
      <div id="lookupRes" class="small mb-2"></div>
      <p class="small text-muted">
        Each member must already have an <strong>approved</strong> registration on this event under
        the same Unit / Club / Institution (<strong><?= e($team['unit_name'] ?? '—') ?></strong>). A team can have up to 3 members.
      </p>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Competitor No.</th>
              <th>Name</th>
              <th>Mobile</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="memberRows">
            <?php if (empty($members)): ?>
              <tr id="emptyMembers"><td colspan="5" class="text-muted text-center py-3">No team members added yet.</td></tr>
            <?php else: foreach ($members as $idx => $m): ?>
              <tr data-id="<?= (int)$m['id'] ?>">
                <td><?= $idx + 1 ?></td>
                <td><code><?= (int)$m['competitor_number'] ?></code></td>
                <td><?= e($m['athlete_name'] ?? '') ?></td>
                <td class="small text-muted"><?= e($m['athlete_mobile'] ?? '') ?></td>
                <td class="text-end">
                  <?php if (!$locked): ?>
                  <button class="btn btn-sm btn-outline-danger" type="button"
                          onclick="removeMember(<?= (int)$m['id'] ?>)"><i class="bi bi-trash"></i></button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Step 3 — Fees & Payment -->
    <div class="sms-card p-4 mb-4">
      <div class="sms-step-head bg-primary-subtle text-primary-emphasis rounded-3 px-3 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-3-circle me-2"></i>Step 3 — Fees &amp; Payment</h6>
        <span class="badge bg-success">Total: ₹<?= number_format($fee, 2) ?></span>
      </div>

      <?php if ($locked): ?>
        <div class="alert alert-info small mb-0">Payment is locked while the team is under review.</div>
      <?php else: ?>

        <div class="mb-3">
          <label class="form-label fw-medium">Payment Mode <span class="text-danger">*</span></label>
          <div class="d-flex gap-2 flex-wrap">
            <?php foreach ($paymentModes as $mode): ?>
              <div class="form-check form-check-inline border rounded-3 px-3 py-2">
                <input class="form-check-input" type="radio" name="payment_mode_choice"
                       value="<?= e($mode) ?>" id="tpm_<?= e($mode) ?>"
                       <?= $currentMode === $mode ? 'checked' : '' ?>
                       onchange="onPaymentModeChange()">
                <label class="form-check-label fw-medium" for="tpm_<?= e($mode) ?>">
                  <?= $mode === 'manual'
                        ? '<i class="bi bi-bank me-1"></i>Manual Submission'
                        : '<i class="bi bi-credit-card me-1"></i>Online Payment' ?>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div id="manualBlock" class="border rounded-3 p-3 mb-3" style="display:<?= $currentMode === 'manual' ? 'block' : 'none' ?>">
          <div class="row g-3 mb-3">
            <div class="col-md-12">
              <h6 class="fw-semibold"><i class="bi bi-bank me-2"></i>Bank Details</h6>
              <pre class="bg-light p-3 rounded small mb-0" style="white-space:pre-wrap"><?= e($event['bank_details'] ?? 'Bank details not yet provided.') ?></pre>
            </div>
          </div>

          <h6 class="fw-semibold mt-3 mb-2"><i class="bi bi-plus-circle me-1"></i>Add Transaction</h6>
          <p class="small text-muted mb-2">
            Submit a payment of <strong>₹<?= number_format($fee, 2) ?></strong> and add the transaction details here.
          </p>
          <div class="border rounded-3 p-3 mb-3 bg-light-subtle">
            <div class="row g-2">
              <div class="col-md-3">
                <label class="form-label small mb-1">Date <span class="text-danger">*</span></label>
                <input type="date" id="t_date" class="form-control form-control-sm" max="<?= date('Y-m-d') ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Transaction No. <span class="text-danger">*</span></label>
                <input type="text" id="t_num" class="form-control form-control-sm" placeholder="UTR / Ref">
              </div>
              <div class="col-md-2">
                <label class="form-label small mb-1">Amount <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" id="t_amount" class="form-control form-control-sm"
                       value="<?= number_format($fee, 2, '.', '') ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label small mb-1">Proof <span class="text-danger">*</span></label>
                <input type="file" id="t_proof" class="form-control form-control-sm" accept="image/jpeg,image/png,application/pdf">
              </div>
              <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-primary btn-sm w-100" onclick="addPayment()">
                  <i class="bi bi-plus-lg me-1"></i>Add
                </button>
              </div>
            </div>
          </div>
        </div>

        <div id="onlineBlock" class="border rounded-3 p-3 mb-3" style="display:<?= $currentMode === 'online' ? 'block' : 'none' ?>">
          <p class="small text-muted mb-2">
            <i class="bi bi-info-circle me-1"></i>
            Online payment for team entries is being rolled out. For now, please use Manual Submission with the bank-transfer details.
          </p>
        </div>

      <?php endif; ?>
    </div>

    <!-- Transactions -->
    <div class="sms-card p-4 mb-4">
      <div class="sms-step-head bg-primary-subtle text-primary-emphasis rounded-3 px-3 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-receipt me-2"></i>Fee Payment Transactions</h6>
        <div class="d-flex align-items-center gap-2">
          <span class="small">Submitted: <strong id="submittedAmt">₹<?= number_format($submittedAmt, 2) ?></strong></span>
          <span class="small">Approved: <strong class="text-success" id="approvedAmt">₹<?= number_format($approvedAmt, 2) ?></strong></span>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr><th>Date</th><th>Transaction No.</th><th class="text-end">Amount</th><th>Proof</th><th>Status</th><th></th></tr>
          </thead>
          <tbody id="paymentRows">
            <?php if (empty($payments)): ?>
              <tr id="emptyPayments"><td colspan="6" class="text-muted text-center py-3">No transactions added yet.</td></tr>
            <?php else: foreach ($payments as $p): ?>
              <tr data-id="<?= (int)$p['id'] ?>">
                <td class="small"><?= formatDate($p['transaction_date']) ?></td>
                <td><code class="small"><?= e($p['transaction_number']) ?></code></td>
                <td class="text-end">₹<?= number_format((float)$p['amount'], 2) ?></td>
                <td>
                  <?php if (!empty($p['proof_file'])): ?>
                    <a href="<?= e($p['proof_file']) ?>" target="_blank" rel="noopener"><i class="bi bi-eye me-1"></i>View</a>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td><?= statusBadge($p['status']) ?></td>
                <td class="text-end">
                  <?php if (!$locked && $p['status'] !== 'approved'): ?>
                    <button class="btn btn-sm btn-outline-danger" type="button"
                            onclick="removePayment(<?= (int)$p['id'] ?>)"><i class="bi bi-trash"></i></button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if (!$locked): ?>
    <div class="d-flex justify-content-end mb-4">
      <button type="button" id="submitBtn" class="btn btn-success btn-lg fw-semibold" onclick="finalSubmit()">
        <i class="bi bi-check-circle me-2"></i>Submit Team Entry
      </button>
    </div>
    <?php endif; ?>

  </div>
</div>

<script>
const CSRF = '<?= e($csrfToken) ?>';
const TEAM_ID = <?= (int)$team['id'] ?>;
const TEAM_FEE = <?= json_encode($fee) ?>;
let pendingMember = null; // {competitor_number, athlete_name}

function showToast(msg, type) {
  const el = document.getElementById('teToast');
  el.className = 'toast align-items-center border-0 text-bg-' + (type || 'primary');
  document.getElementById('teToastMsg').textContent = msg;
  bootstrap.Toast.getOrCreateInstance(el).show();
}

async function lookupMember() {
  const num = document.getElementById('te_comp').value.trim();
  if (!num) { showToast('Enter a competitor number.', 'warning'); return; }
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('competitor_number', num);
  const res = await fetch('/athlete/team-entry/' + TEAM_ID + '/member-validate', { method:'POST', body: fd });
  const data = await res.json();
  const box = document.getElementById('lookupRes');
  if (!data.success) {
    box.innerHTML = '<div class="alert alert-danger py-2 mb-2"><i class="bi bi-exclamation-circle me-1"></i>' + data.message + '</div>';
    document.getElementById('addMemberBtn').disabled = true;
    pendingMember = null;
    return;
  }
  box.innerHTML = '<div class="alert alert-success py-2 mb-2"><i class="bi bi-check2-circle me-1"></i>'
    + '<strong>' + data.athlete_name + '</strong> — ' + (data.athlete_mobile || '')
    + ' · ' + (data.unit_name || '—') + ' — ready to add.</div>';
  pendingMember = { competitor_number: num, athlete_name: data.athlete_name };
  document.getElementById('addMemberBtn').disabled = false;
}

async function addMember() {
  if (!pendingMember) { showToast('Validate a competitor number first.', 'warning'); return; }
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('competitor_number', pendingMember.competitor_number);
  const res = await fetch('/athlete/team-entry/' + TEAM_ID + '/member-add', { method:'POST', body: fd });
  const data = await res.json();
  if (!data.success) { showToast(data.message || 'Failed.', 'danger'); return; }
  renderMembers(data.members);
  showToast(data.message);
  pendingMember = null;
  document.getElementById('te_comp').value = '';
  document.getElementById('lookupRes').innerHTML = '';
  document.getElementById('addMemberBtn').disabled = true;
}

async function removeMember(id) {
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('member_id', id);
  const res = await fetch('/athlete/team-entry/' + TEAM_ID + '/member-remove', { method:'POST', body: fd });
  const data = await res.json();
  if (!data.success) { showToast(data.message, 'danger'); return; }
  renderMembers(data.members);
  showToast('Removed.');
}

function renderMembers(list) {
  const tb = document.getElementById('memberRows');
  document.getElementById('memberCount').textContent = list.length;
  if (!list.length) {
    tb.innerHTML = '<tr id="emptyMembers"><td colspan="5" class="text-muted text-center py-3">No team members added yet.</td></tr>';
    return;
  }
  tb.innerHTML = list.map((m, i) => `
    <tr data-id="${m.id}">
      <td>${i + 1}</td>
      <td><code>${m.competitor_number}</code></td>
      <td>${m.athlete_name || ''}</td>
      <td class="small text-muted">${m.athlete_mobile || ''}</td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-danger" type="button" onclick="removeMember(${m.id})">
          <i class="bi bi-trash"></i>
        </button>
      </td>
    </tr>`).join('');
}

async function onPaymentModeChange() {
  const mode = document.querySelector('input[name="payment_mode_choice"]:checked')?.value;
  if (!mode) return;
  document.getElementById('manualBlock').style.display = mode === 'manual' ? 'block' : 'none';
  document.getElementById('onlineBlock').style.display = mode === 'online' ? 'block' : 'none';
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('payment_mode', mode);
  const res = await fetch('/athlete/team-entry/' + TEAM_ID + '/payment-mode', { method:'POST', body: fd });
  const data = await res.json();
  if (!data.success) showToast(data.message, 'danger');
}

async function addPayment() {
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('transaction_date',   document.getElementById('t_date').value);
  fd.append('transaction_number', document.getElementById('t_num').value.trim());
  fd.append('transaction_amount', document.getElementById('t_amount').value);
  const proof = document.getElementById('t_proof');
  if (proof.files[0]) fd.append('transaction_proof', proof.files[0]);
  const res = await fetch('/athlete/team-entry/' + TEAM_ID + '/payment', { method:'POST', body: fd });
  const data = await res.json();
  if (!data.success) { showToast(data.message, 'danger'); return; }
  renderPayments(data.payments);
  showToast('Transaction added.');
  document.getElementById('t_date').value  = '';
  document.getElementById('t_num').value   = '';
  document.getElementById('t_proof').value = '';
}

async function removePayment(id) {
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('payment_id', id);
  const res = await fetch('/athlete/team-entry/' + TEAM_ID + '/payment-remove', { method:'POST', body: fd });
  const data = await res.json();
  if (!data.success) { showToast(data.message, 'danger'); return; }
  renderPayments(data.payments);
  showToast('Removed.');
}

function renderPayments(list) {
  const tb = document.getElementById('paymentRows');
  let sub = 0, app = 0;
  list.forEach(p => { sub += Number(p.amount); if (p.status === 'approved') app += Number(p.amount); });
  document.getElementById('submittedAmt').textContent = '₹' + sub.toFixed(2);
  document.getElementById('approvedAmt').textContent  = '₹' + app.toFixed(2);
  if (!list.length) {
    tb.innerHTML = '<tr id="emptyPayments"><td colspan="6" class="text-muted text-center py-3">No transactions added yet.</td></tr>';
    return;
  }
  const dateFmt = (s) => { const d = new Date(s); return isNaN(d) ? s : d.toLocaleDateString('en-IN', {day:'2-digit',month:'short',year:'numeric'}); };
  const badge = (s) => {
    const map = {pending:'bg-warning text-dark', approved:'bg-success', rejected:'bg-danger', paid:'bg-success', failed:'bg-danger'};
    return `<span class="badge ${map[s] || 'bg-secondary'}">${s}</span>`;
  };
  tb.innerHTML = list.map(p => `
    <tr data-id="${p.id}">
      <td class="small">${dateFmt(p.transaction_date)}</td>
      <td><code class="small">${p.transaction_number || ''}</code></td>
      <td class="text-end">₹${Number(p.amount).toFixed(2)}</td>
      <td>${p.proof_file ? '<a href="' + p.proof_file + '" target="_blank"><i class="bi bi-eye me-1"></i>View</a>' : '—'}</td>
      <td>${badge(p.status)}</td>
      <td class="text-end">
        ${p.status !== 'approved' ? `<button class="btn btn-sm btn-outline-danger" type="button" onclick="removePayment(${p.id})"><i class="bi bi-trash"></i></button>` : ''}
      </td>
    </tr>`).join('');
}

async function finalSubmit() {
  const btn = document.getElementById('submitBtn');
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Submitting…';
  const fd = new FormData();
  fd.append('_token', CSRF);
  const res = await fetch('/athlete/team-entry/' + TEAM_ID + '/submit', { method:'POST', body: fd });
  const data = await res.json();
  if (data.success && data.redirect) { window.location.href = data.redirect; return; }
  showToast(data.message, 'danger');
  btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Submit Team Entry';
}
</script>
