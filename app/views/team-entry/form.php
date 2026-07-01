<?php
$pageTitle = $team ? 'Team Entry' : 'New Team Entry';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$ro        = !empty($read_only);
$teamId    = $team['id'] ?? 0;
$submitted = $team && !empty($team['submitted_at']);
$paymentRequired = !empty($actor['payment_required']);
// Bulk mode: hide the per-team Fee Payment section and the Save & Submit
// button — fees are logged and entries submitted in bulk from the list.
$bulk = !empty($bulk);
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
  <a href="/team-entry" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i><?= $team ? 'Team Entry' : 'New Team Entry' ?></h5>
  <?php if ($team): ?>
    <?php if (!$submitted): ?>
      <span class="badge bg-secondary">Draft</span>
    <?php else: ?>
      <?= appStatusBadge($team['admin_review_status'] ?? null, $team['submitted_at']) ?>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php if ($ro): ?>
  <div class="alert alert-info d-flex align-items-start gap-2">
    <i class="bi bi-lock-fill fs-5"></i>
    <div>
      <strong>This team entry has been submitted and is read-only.</strong>
      <?php if (($team['admin_review_status'] ?? '') === 'returned'): ?>
        It was returned for changes — you can edit and resubmit it.
      <?php else: ?>
        The event administrator will review it.
      <?php endif; ?>
      <?php if (!empty($team['admin_review_notes'])): ?>
        <div class="mt-1"><strong>Admin note:</strong> <?= e($team['admin_review_notes']) ?></div>
      <?php endif; ?>
    </div>
  </div>
<?php elseif ($team && ($team['admin_review_status'] ?? '') === 'returned'): ?>
  <div class="alert alert-warning">
    <strong>Returned for changes.</strong>
    <?php if (!empty($team['admin_review_notes'])): ?><?= e($team['admin_review_notes']) ?><?php endif; ?>
  </div>
<?php endif; ?>

<form id="teForm" enctype="multipart/form-data" onsubmit="return false">
  <input type="hidden" id="te_id" value="<?= (int)$teamId ?>">

  <div class="sms-card p-4 mb-4">
    <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-card-text me-2"></i>Team Details</h6>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label fw-medium">Team Name <span class="text-danger">*</span></label>
        <input type="text" id="te_team_name" class="form-control" maxlength="255"
               value="<?= e($team['team_name'] ?? '') ?>" <?= $ro ? 'disabled' : '' ?>
               placeholder="e.g. Eagles Senior Squad">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-medium">Unit / Club / Institution <span class="text-danger">*</span></label>
        <select id="te_unit" class="form-select" <?= $ro ? 'disabled' : '' ?> onchange="loadMembers()">
          <option value="">— Select Unit —</option>
          <?php foreach ($actor['units'] as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= $team && (int)($team['unit_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
              <?= e($u['name']) ?><?php if (!empty($u['address'])): ?> — <?= e($u['address']) ?><?php endif; ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if ($actor['type'] === 'unit_user'): ?>
          <small class="text-muted">Only units assigned to your account are listed.</small>
        <?php else: ?>
          <small class="text-muted">Type to search across all event units.</small>
        <?php endif; ?>
      </div>

      <div class="col-md-6">
        <label class="form-label fw-medium">Event Category <span class="text-danger">*</span></label>
        <select id="te_category" class="form-select" <?= $ro ? 'disabled' : '' ?> onchange="loadEvents()">
          <option value="">— Select Category —</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>"
                    <?= $team && (int)($team['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
              <?= e(categoryLabel($c['name'], $c['abbreviation'] ?? null)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label fw-medium">Event <span class="text-danger">*</span></label>
        <select id="te_event" class="form-select" <?= $ro ? 'disabled' : '' ?> onchange="onEventChange()">
          <option value="">— Select Category first —</option>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label fw-medium">Team Entry Fee</label>
        <div class="form-control bg-light fw-bold text-success" id="te_fee" style="min-height:38px">
          <?= isset($team['team_entry_fee']) && $team['team_entry_fee'] !== null
                ? '₹' . number_format((float)$team['team_entry_fee'], 2) : '—' ?>
        </div>
      </div>
    </div>
  </div>

  <div class="sms-card p-4 mb-4">
    <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-person-lines-fill me-2"></i>Team Members</h6>
    <p class="small text-muted">Each dropdown lists only <strong>approved</strong> participants of the selected unit who are registered for the selected event. The same athlete cannot be picked twice. The number of slots follows the event&rsquo;s <strong>Team Size</strong> plus any <strong>Reserve</strong> members — reserves don&rsquo;t play but share the team&rsquo;s benefits.</p>
    <div class="row g-3" id="memberSlots">
      <div class="col-12 text-muted small" id="memberSlotsEmpty">Select an Event above to load the member slots.</div>
    </div>
  </div>

  <?php if ($bulk): ?>
    <div class="sms-card p-4 mb-4">
      <div class="alert alert-info small mb-0">
        <i class="bi bi-info-circle me-1"></i>
        This event uses <strong>bulk payment</strong>. Save this team entry as a draft, then log the
        payment and submit it (with your other team entries) from the
        <a href="/team-entry">Team Entry list</a>.
      </div>
    </div>
  <?php else: ?>
  <div class="sms-card p-4 mb-4">
    <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-receipt me-2"></i>Fee Payment</h6>
    <?php if (!empty($payment)): ?>
      <div class="alert alert-success py-2 small">
        <i class="bi bi-check-circle me-1"></i>
        Payment proof on file
        <?php if (!empty($payment['proof_file'])): ?>
          — <a href="<?= e($payment['proof_file']) ?>" target="_blank">View current proof</a>
        <?php endif; ?>
        · Status: <?= statusBadge($payment['status'] ?? 'pending') ?>
      </div>
    <?php endif; ?>
    <?php if (!$ro): ?>
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label fw-medium">Transaction Number</label>
        <input type="text" id="te_txn_no" class="form-control" maxlength="100"
               value="<?= e($payment['transaction_number'] ?? '') ?>" placeholder="UTR / Ref">
      </div>
      <div class="col-md-3">
        <label class="form-label fw-medium">Transaction Date</label>
        <input type="date" id="te_txn_date" class="form-control" max="<?= date('Y-m-d') ?>"
               value="<?= e($payment['transaction_date'] ?? '') ?>">
      </div>
      <div class="col-md-5">
        <label class="form-label fw-medium">
          Payment Proof
          <?php if ($paymentRequired): ?>
            <span class="text-danger">* (mandatory to submit)</span>
          <?php else: ?>
            <span class="text-muted small">(optional for staff)</span>
          <?php endif; ?>
        </label>
        <input type="file" id="te_proof" class="form-control" accept="image/jpeg,image/png,application/pdf">
      </div>
    </div>
    <small class="text-muted d-block mt-2">
      <?php if ($paymentRequired): ?>
        As a Unit user you must upload payment proof before submitting.
      <?php else: ?>
        As Event Staff you may submit without uploading payment proof.
      <?php endif; ?>
    </small>
    <?php endif; ?>
  </div>
  <?php endif; // /Fee Payment (non-bulk) ?>

  <?php if (!$ro): ?>
  <div class="d-flex justify-content-between gap-2 mb-4 flex-wrap">
    <div>
      <?php if ($team && !$submitted): ?>
        <button type="button" class="btn btn-outline-danger" onclick="deleteDraft()">
          <i class="bi bi-trash me-1"></i>Delete Draft
        </button>
      <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-outline-secondary" onclick="submitForm('draft')">
        <i class="bi bi-save me-1"></i>Save as Draft
      </button>
      <?php if (!$bulk): ?>
      <button type="button" class="btn btn-success" onclick="submitForm('submit')">
        <i class="bi bi-send-check me-1"></i>Save &amp; Submit
      </button>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</form>

<script>
const CSRF = '<?= e($csrfToken) ?>';
const READ_ONLY = <?= $ro ? 'true' : 'false' ?>;
const PRESELECT = {
  event_sport_id: <?= (int)($team['event_sport_id'] ?? 0) ?>,
  members: <?= json_encode(array_map(fn($m) => (int)$m['registration_id'], $members ?? [])) ?>
};

function showToast(msg, type) {
  const el = document.getElementById('teToast');
  el.className = 'toast align-items-center border-0 text-bg-' + (type || 'primary');
  document.getElementById('teToastMsg').textContent = msg;
  if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
    bootstrap.Toast.getOrCreateInstance(el, { delay: 3500 }).show();
  } else { alert(msg); }
}

async function loadEvents(preselectId, preselectMembers) {
  const catId = document.getElementById('te_category').value;
  const evSel = document.getElementById('te_event');
  evSel.innerHTML = '<option value="">— Select Event —</option>';
  if (!catId) { evSel.innerHTML = '<option value="">— Select Category first —</option>'; onEventChange(); return; }
  const res  = await fetch('/team-entry/category-events?category_id=' + encodeURIComponent(catId));
  const data = await res.json();
  (data.events || []).forEach(ev => {
    const o = document.createElement('option');
    o.value = ev.id;
    o.dataset.fee = ev.team_entry_fee;
    o.dataset.teamSize = ev.team_member_count == null ? 3 : parseInt(ev.team_member_count, 10);
    o.dataset.reserve  = ev.reserve_count == null ? 0 : parseInt(ev.reserve_count, 10);
    o.textContent = (ev.event_code ? '[' + ev.event_code + '] ' : '')
      + ev.sport_name + ' · ' + (ev.sport_event_name || '')
      + (ev.gender ? ' (' + ev.gender + ')' : '');
    if (preselectId && parseInt(preselectId,10) === parseInt(ev.id,10)) o.selected = true;
    evSel.appendChild(o);
  });
  onEventChange(preselectMembers);
}

function onEventChange(preselectMembers) {
  const opt = document.getElementById('te_event').selectedOptions[0];
  const fee = opt ? opt.dataset.fee : null;
  document.getElementById('te_fee').textContent =
    (fee !== undefined && fee !== null && fee !== '') ? '₹' + Number(fee).toFixed(2) : '—';
  const teamSize = opt && opt.dataset.teamSize ? parseInt(opt.dataset.teamSize, 10) : 0;
  const reserve  = opt && opt.dataset.reserve  ? parseInt(opt.dataset.reserve, 10)  : 0;
  buildMemberSlots(teamSize, reserve);
  loadMembers(preselectMembers);
}

/* (Re)build the member dropdowns: team_size playing slots + reserve slots. */
function buildMemberSlots(teamSize, reserve) {
  const wrap  = document.getElementById('memberSlots');
  const total = (teamSize || 0) + (reserve || 0);
  if (!total) {
    wrap.innerHTML = '<div class="col-12 text-muted small" id="memberSlotsEmpty">Select an Event above to load the member slots.</div>';
    return;
  }
  let html = '';
  for (let i = 1; i <= total; i++) {
    const isReserve = i > teamSize;
    const label = isReserve ? ('Reserve ' + (i - teamSize)) : ('Member ' + i);
    const star  = isReserve ? '<span class="text-muted small">(optional)</span>' : '<span class="text-danger">*</span>';
    html += `<div class="col-md-4">
        <label class="form-label fw-medium">${label} ${star}</label>
        <select id="te_member_${i}" class="form-select te-member" data-reserve="${isReserve ? 1 : 0}"
                ${READ_ONLY ? 'disabled' : ''} onchange="syncMemberLocks()">
          <option value="">— Select Member —</option>
        </select>
      </div>`;
  }
  wrap.innerHTML = html;
}

function memberSelects() {
  return Array.from(document.querySelectorAll('.te-member'));
}

async function loadMembers(preselect) {
  const unitId = document.getElementById('te_unit').value;
  const esId   = document.getElementById('te_event').value;
  const sels   = memberSelects();
  const chosen = preselect || sels.map(s => s.value);
  sels.forEach(s => { s.innerHTML = '<option value="">— Select Member —</option>'; });
  if (!unitId || !esId || !sels.length) { syncMemberLocks(); return; }
  const res  = await fetch('/team-entry/members?unit_id=' + encodeURIComponent(unitId)
              + '&event_sport_id=' + encodeURIComponent(esId));
  const data = await res.json();
  if (!data.success) { showToast(data.message || 'Could not load members.', 'danger'); return; }
  (data.candidates || []).forEach(c => {
    sels.forEach((s, idx) => {
      const o = document.createElement('option');
      o.value = c.registration_id;
      o.textContent = '#' + (c.competitor_number || '—') + ' · ' + c.athlete_name;
      if (chosen[idx] && parseInt(chosen[idx],10) === parseInt(c.registration_id,10)) o.selected = true;
      s.appendChild(o);
    });
  });
  syncMemberLocks();
}

/* Prevent the same athlete being picked in two slots. */
function syncMemberLocks() {
  if (READ_ONLY) return;
  const sels = memberSelects();
  const picked = sels.map(s => s.value).filter(Boolean);
  sels.forEach(s => {
    Array.from(s.options).forEach(o => {
      if (!o.value) return;
      o.disabled = picked.includes(o.value) && o.value !== s.value;
    });
  });
}

async function deleteDraft() {
  const id = document.getElementById('te_id').value;
  if (!id || id === '0') return;
  if (!confirm('Delete this draft team entry permanently? This cannot be undone.')) return;
  const fd = new FormData();
  fd.append('_token', CSRF);
  const res  = await fetch('/team-entry/' + id + '/delete', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) { window.location.href = data.redirect; return; }
  showToast(data.message || 'Delete failed.', 'danger');
}

async function submitForm(action) {
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('id',     document.getElementById('te_id').value);
  fd.append('action', action);
  fd.append('team_name',      document.getElementById('te_team_name').value.trim());
  fd.append('unit_id',        document.getElementById('te_unit').value);
  fd.append('event_sport_id', document.getElementById('te_event').value);
  memberSelects().forEach((s, idx) => fd.append('member_' + (idx + 1), s.value));
  // Payment fields are absent in bulk mode — guard their reads.
  var txnNo = document.getElementById('te_txn_no');
  var txnDt = document.getElementById('te_txn_date');
  var proof = document.getElementById('te_proof');
  if (txnNo) fd.append('transaction_number', txnNo.value.trim());
  if (txnDt) fd.append('transaction_date',   txnDt.value);
  if (proof && proof.files[0]) fd.append('payment_proof', proof.files[0]);

  const res  = await fetch('/team-entry/save', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) { window.location.href = data.redirect; return; }
  showToast(data.message || 'Save failed.', 'danger');
}

document.addEventListener('DOMContentLoaded', () => {
  // Editing an existing draft: rebuild the dependent dropdowns and
  // re-apply the saved event + member selection in one pass (loadEvents
  // forwards the member preselect through onEventChange → loadMembers,
  // so there's no second race-prone fetch).
  if (document.getElementById('te_category').value) {
    loadEvents(PRESELECT.event_sport_id, PRESELECT.members);
  }
});
</script>
