<?php
$pageTitle = 'Register — ' . $event['name'];
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$nocReq      = $event['noc_required'] ?? 'optional';
$paymentModes = $event['payment_modes'] ?? [];
$selectedSet = array_column($items, 'event_sport_id');
$selectedSet = array_map('intval', $selectedSet);
$total       = (float)($registration['total_amount'] ?? 0);
?>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="regToast" class="toast align-items-center border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body fw-medium" id="toastMsg"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="/athlete/events/<?= (int)$event['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold">Register — <?= e($event['name']) ?></h5>
</div>

<div class="row g-4">
  <!-- ── Step 1: Select unit, NOC, sport events ── -->
  <div class="col-lg-8">
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-1-circle me-2"></i>Step 1 — Registration Details</h6>
        <span class="badge bg-success px-3 py-2 fs-6">Total: ₹<span id="totalAmount"><?= number_format($total, 2) ?></span></span>
      </div>

      <div class="mb-3">
        <label class="form-label fw-medium">Unit / Club / Institution <span class="text-danger">*</span></label>
        <?php if (empty($units)): ?>
          <div class="alert alert-warning small mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i>
            The event organiser hasn't added any Units yet. Please contact them and try again later.
          </div>
        <?php else: ?>
          <select id="r_unit" class="form-select">
            <option value="">— Select Unit —</option>
            <?php foreach ($units as $u): ?>
              <option value="<?= (int)$u['id'] ?>" <?= (int)($registration['unit_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
                <?= e($u['name']) ?><?php if (!empty($u['address'])): ?> — <?= e($u['address']) ?><?php endif; ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </div>

      <?php if ($nocReq !== 'none'): ?>
      <div class="mb-3">
        <label class="form-label fw-medium">
          NOC Letter from Unit
          <?= $nocReq === 'mandatory'
                ? '<span class="text-danger">* Mandatory</span>'
                : '<span class="text-muted small">(Optional)</span>' ?>
        </label>
        <input type="file" id="r_noc" class="form-control" accept="image/jpeg,image/png,application/pdf">
        <?php if (!empty($registration['noc_letter'])): ?>
          <small class="text-success">
            <i class="bi bi-check-circle me-1"></i>Already uploaded
            <a href="<?= e($registration['noc_letter']) ?>" target="_blank" class="ms-1">View</a>
          </small>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <h6 class="fw-semibold border-bottom pb-2 mb-3 mt-4"><i class="bi bi-trophy me-2"></i>Available Sport Events</h6>
      <?php if (empty($event['sports'])): ?>
        <p class="text-muted small">The organiser hasn't published any sport events yet.</p>
      <?php else: ?>

      <!-- Filter picker -->
      <div class="row g-2 align-items-end mb-3">
        <div class="col-md-3">
          <label class="form-label small mb-1">Sport</label>
          <select id="f_sport" class="form-select form-select-sm" onchange="onSportChange()"></select>
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">Event Category</label>
          <select id="f_category" class="form-select form-select-sm" onchange="onCategoryChange()"></select>
        </div>
        <div class="col-md-4">
          <label class="form-label small mb-1">Event</label>
          <select id="f_event" class="form-select form-select-sm"></select>
        </div>
        <div class="col-md-2">
          <button type="button" class="btn btn-sm btn-primary w-100" onclick="addSelectedEvent()">
            <i class="bi bi-plus-lg me-1"></i>Add
          </button>
        </div>
      </div>

      <!-- Selected sport events -->
      <div class="d-flex align-items-center justify-content-between mb-2">
        <strong class="small text-muted text-uppercase">Selected events</strong>
        <span class="small text-muted">Sum of fees:&nbsp;<strong>₹<span id="totalAmountInline"><?= number_format($total, 2) ?></span></strong></span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Sport</th>
              <th>Event Code</th>
              <th>Category / Event</th>
              <th>Age / Gender</th>
              <th class="text-end">Entry Fee</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="selectedRows">
            <tr id="emptySelected"><td colspan="6" class="text-muted text-center py-3">No events selected yet.</td></tr>
          </tbody>
          <tfoot>
            <tr class="table-light">
              <th colspan="4" class="text-end">Total</th>
              <th class="text-end fw-bold">₹<span id="totalAmountTbl"><?= number_format($total, 2) ?></span></th>
              <th></th>
            </tr>
          </tfoot>
        </table>
      </div>
      <?php endif; ?>

      <div class="d-flex justify-content-end border-top pt-3 mt-3">
        <button type="button" class="btn btn-primary px-4 fw-semibold" onclick="saveStep1()">
          <i class="bi bi-save me-2"></i>Save &amp; Continue
        </button>
      </div>
    </div>

    <!-- ── Step 2: Payment ── -->
    <div class="sms-card p-4 mb-4 <?= empty($registration['unit_id']) ? 'opacity-50' : '' ?>" id="paymentCard">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-2-circle me-2"></i>Step 2 — Payment</h6>
        <span class="badge bg-success">Total: ₹<span id="totalAmount2"><?= number_format($total, 2) ?></span></span>
      </div>

      <?php if (empty($registration['unit_id'])): ?>
        <div class="alert alert-secondary small mb-0">Save Step 1 first to choose a payment mode.</div>
      <?php else: ?>
        <div class="mb-3">
          <label class="form-label fw-medium">Payment Mode <span class="text-danger">*</span></label>
          <div class="d-flex gap-2 flex-wrap">
            <?php foreach ($paymentModes as $mode): ?>
              <div class="form-check form-check-inline border rounded-3 px-3 py-2">
                <input class="form-check-input" type="radio" name="payment_mode_choice"
                       value="<?= e($mode) ?>" id="pm_<?= e($mode) ?>" onchange="togglePaymentSection()">
                <label class="form-check-label fw-medium" for="pm_<?= e($mode) ?>">
                  <?= $mode === 'manual' ? '<i class="bi bi-bank me-1"></i>Manual Submission' : '<i class="bi bi-credit-card me-1"></i>Online Payment' ?>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Manual payment block -->
        <div id="manualBlock" class="border rounded-3 p-3 mb-3" style="display:none">
          <div class="row g-3">
            <div class="col-md-7">
              <h6 class="fw-semibold"><i class="bi bi-bank me-2"></i>Bank Details</h6>
              <pre class="bg-light p-3 rounded small mb-0" style="white-space:pre-wrap"><?= e($event['bank_details'] ?? 'Bank details not yet provided.') ?></pre>
            </div>
            <div class="col-md-5 text-center">
              <?php if (!empty($event['bank_qr_code'])): ?>
                <img src="<?= e($event['bank_qr_code']) ?>" alt="UPI QR" class="img-fluid rounded" style="max-height:200px">
                <div class="small text-muted mt-1">Scan to pay via UPI</div>
              <?php else: ?>
                <div class="text-muted small">QR not provided.</div>
              <?php endif; ?>
            </div>
          </div>

          <hr>
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label fw-medium">Transaction Date <span class="text-danger">*</span></label>
              <input type="date" id="t_date" class="form-control" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-medium">Transaction Number <span class="text-danger">*</span></label>
              <input type="text" id="t_num" class="form-control" placeholder="UTR / Ref number">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-medium">Amount Paid <span class="text-danger">*</span></label>
              <input type="number" step="0.01" min="0" id="t_amount" class="form-control"
                     value="<?= number_format($total, 2, '.', '') ?>">
            </div>
            <div class="col-md-12">
              <label class="form-label fw-medium">Transaction Proof <span class="text-danger">*</span></label>
              <input type="file" id="t_proof" class="form-control" accept="image/jpeg,image/png,application/pdf">
              <small class="text-muted">Screenshot / receipt of the payment (mandatory).</small>
            </div>
          </div>
          <div class="text-end mt-3">
            <button type="button" class="btn btn-success px-4 fw-semibold" onclick="submitManual()">
              <i class="bi bi-send me-2"></i>Submit Registration
            </button>
          </div>
        </div>

        <!-- Online payment block -->
        <div id="onlineBlock" class="border rounded-3 p-3 mb-3" style="display:none">
          <h6 class="fw-semibold"><i class="bi bi-receipt me-2"></i>Summary</h6>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead class="table-light"><tr><th>Sport</th><th>Code</th><th>Event</th><th class="text-end">Fee</th></tr></thead>
              <tbody>
                <?php foreach ($items as $it): ?>
                  <tr>
                    <td><?= e($it['sport_name']) ?></td>
                    <td><code><?= e($it['event_code'] ?? '') ?></code></td>
                    <td><?= e($it['sport_event_name'] ?? $it['category'] ?? '') ?></td>
                    <td class="text-end">₹<?= number_format((float)$it['fee'], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr class="table-light"><th colspan="3" class="text-end">Total</th><th class="text-end">₹<?= number_format($total, 2) ?></th></tr></tfoot>
            </table>
          </div>
          <div class="text-end mt-2">
            <button type="button" class="btn btn-primary px-4 fw-semibold" onclick="submitOnline()">
              <i class="bi bi-credit-card me-2"></i>Initiate Payment
            </button>
            <div class="small text-muted mt-1">Online payment gateway will open in a new tab once configured.</div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Right column: event summary + documents -->
  <div class="col-lg-4">
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-calendar-event me-2"></i>Event Info</h6>
      <div class="mb-2"><strong><?= e($event['name']) ?></strong></div>
      <div class="text-muted small mb-1"><i class="bi bi-geo-alt me-1"></i><?= e($event['location']) ?></div>
      <div class="text-muted small mb-1"><i class="bi bi-calendar3 me-1"></i><?= formatDate($event['event_date_from']) ?> – <?= formatDate($event['event_date_to']) ?></div>
      <div class="text-muted small mb-1"><i class="bi bi-person me-1"></i><?= e($event['contact_name']) ?></div>
      <div class="text-muted small"><i class="bi bi-credit-card me-1"></i><?= implode(', ', array_map('ucfirst', $paymentModes)) ?></div>
    </div>

    <?php if (!empty($documents)): ?>
    <div class="sms-card p-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-file-earmark-text me-2"></i>Documents</h6>
      <p class="small text-muted mb-3">Forms and notices published by the event organiser.</p>
      <ul class="list-unstyled mb-0">
        <?php foreach ($documents as $d): ?>
          <li class="d-flex align-items-start gap-2 py-2 <?= !$d === end($documents) ? '' : '' ?>" style="border-bottom:1px dashed #e2e8f0">
            <i class="bi bi-file-earmark-pdf text-primary fs-5 mt-1"></i>
            <div class="flex-grow-1">
              <div class="fw-semibold small"><?= e($d['name']) ?></div>
              <?php if (!empty($d['purpose'])): ?>
                <div class="text-muted small"><?= e($d['purpose']) ?></div>
              <?php endif; ?>
            </div>
            <?php if (!empty($d['file'])): ?>
              <a href="<?= e($d['file']) ?>" target="_blank" rel="noopener"
                 class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye me-1"></i>View
              </a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
const CSRF = '<?= e($csrfToken) ?>';
const EV_ID = <?= (int)$event['id'] ?>;
const SAVE_URL   = '/athlete/events/' + EV_ID + '/register/save';
const SUBMIT_URL = '/athlete/events/' + EV_ID + '/register/submit';
const NOC_REQ = '<?= e($nocReq) ?>';

// Catalog of sport events offered by this event. Each row corresponds to
// one event_sports record (the institution's per-event entry).
const SPORT_EVENTS = <?php
  $rows = [];
  foreach (($event['sports'] ?? []) as $r) {
    $rows[] = [
      'id'           => (int)$r['id'],
      'sport_id'     => (int)$r['sport_id'],
      'sport_name'   => (string)($r['sport_name'] ?? ''),
      'category'     => (string)($r['sport_event_category'] ?? ($r['category'] ?? '— Uncategorised —')),
      'event_name'   => (string)($r['sport_event_name'] ?? ($r['category'] ?? '')),
      'event_code'   => (string)($r['event_code'] ?? ''),
      'age_category' => (string)($r['sport_event_age_category'] ?? ''),
      'gender'       => (string)($r['sport_event_gender'] ?? ''),
      'fee'          => (float)$r['entry_fee'],
    ];
  }
  echo json_encode($rows);
?>;
// Pre-existing selections from a saved draft.
let SELECTED_IDS = <?= json_encode(array_values(array_map('intval', $selectedSet))) ?>;

function showToast(msg, type) {
  type = type || 'success';
  const el  = document.getElementById('regToast');
  el.className = 'toast align-items-center border-0 text-bg-' + type;
  document.getElementById('toastMsg').textContent = msg;
  if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
    bootstrap.Toast.getOrCreateInstance(el, { delay: 3500 }).show();
  } else { alert(msg); }
}

/* ── Sport Event Picker (chained Sport → Category → Event) ── */

function uniq(arr) { return [...new Set(arr)]; }
function byId(id)  { return SPORT_EVENTS.find(r => r.id === id); }

function rebuildSportDropdown() {
  const sel = document.getElementById('f_sport');
  if (!sel) return;
  const sports = uniq(SPORT_EVENTS.map(r => r.sport_name)).sort();
  sel.innerHTML = sports.map(s => `<option value="${s}">${s}</option>`).join('');
  // Pick the first sport by default.
  if (sports.length) sel.value = sports[0];
  onSportChange();
}

function onSportChange() {
  const sport = document.getElementById('f_sport').value;
  const catSel = document.getElementById('f_category');
  const cats = uniq(SPORT_EVENTS.filter(r => r.sport_name === sport).map(r => r.category)).sort();
  catSel.innerHTML = cats.length
    ? cats.map(c => `<option value="${c}">${c}</option>`).join('')
    : '<option value="">— No categories —</option>';
  if (cats.length) catSel.value = cats[0];
  onCategoryChange();
}

function onCategoryChange() {
  const sport = document.getElementById('f_sport').value;
  const cat   = document.getElementById('f_category').value;
  const evSel = document.getElementById('f_event');
  const list  = SPORT_EVENTS.filter(r => r.sport_name === sport && r.category === cat);
  evSel.innerHTML = list.length
    ? list.map(r => {
        const bits = [r.event_name, r.age_category, r.gender]
          .filter(Boolean).join(' · ');
        return `<option value="${r.id}">${bits} — ₹${r.fee.toFixed(2)}</option>`;
      }).join('')
    : '<option value="">— No events —</option>';
}

function addSelectedEvent() {
  const id = parseInt(document.getElementById('f_event').value, 10);
  if (!id) { showToast('Pick an event from the dropdown first.', 'warning'); return; }
  if (SELECTED_IDS.includes(id)) {
    showToast('This event is already in your selection.', 'warning');
    return;
  }
  SELECTED_IDS.push(id);
  renderSelectedRows();
}

function removeSelected(id) {
  SELECTED_IDS = SELECTED_IDS.filter(x => x !== id);
  renderSelectedRows();
}

function esc(s) {
  return (s == null ? '' : String(s)).replace(/[&<>"']/g, c =>
    ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function renderSelectedRows() {
  const body = document.getElementById('selectedRows');
  if (!body) return;
  if (!SELECTED_IDS.length) {
    body.innerHTML = '<tr id="emptySelected"><td colspan="6" class="text-muted text-center py-3">No events selected yet.</td></tr>';
  } else {
    body.innerHTML = SELECTED_IDS.map(id => {
      const r = byId(id);
      if (!r) return '';
      return `<tr data-id="${r.id}">
        <td>${esc(r.sport_name)}</td>
        <td><code>${esc(r.event_code)}</code></td>
        <td>${esc(r.category)} <span class="text-muted">${esc(r.event_name)}</span></td>
        <td>${esc(r.age_category)} <span class="text-muted small">${esc(r.gender)}</span></td>
        <td class="text-end">₹${r.fee.toFixed(2)}</td>
        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSelected(${r.id})"><i class="bi bi-trash"></i></button></td>
      </tr>`;
    }).join('');
  }
  recomputeTotal();
}

function recomputeTotal() {
  let sum = 0;
  SELECTED_IDS.forEach(id => { const r = byId(id); if (r) sum += r.fee; });
  const text = sum.toFixed(2);
  ['totalAmount','totalAmount2','totalAmountInline','totalAmountTbl']
    .forEach(eid => { const el = document.getElementById(eid); if (el) el.textContent = text; });
  const ta = document.getElementById('t_amount'); if (ta) ta.value = text;
}

async function saveStep1() {
  const unitSel = document.getElementById('r_unit');
  if (!unitSel) { showToast('No units configured for this event yet.', 'warning'); return; }
  const unitId = unitSel.value;
  if (!unitId) { showToast('Please select a Unit.', 'warning'); return; }

  if (!SELECTED_IDS.length) { showToast('Add at least one sport event to your selection.', 'warning'); return; }

  const noc = document.getElementById('r_noc');
  if (NOC_REQ === 'mandatory' && noc && !noc.files.length && !document.querySelector('a[href][target="_blank"]')) {
    showToast('NOC letter is mandatory for this event.', 'warning'); return;
  }

  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('unit_id', unitId);
  SELECTED_IDS.forEach(id => fd.append('event_sport_ids[]', String(id)));
  if (noc && noc.files[0]) fd.append('noc_letter', noc.files[0]);

  const res = await fetch(SAVE_URL, { method: 'POST', body: fd });
  let data; try { data = await res.json(); } catch(_) { data = { success:false, message:'Server error.' }; }
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) {
    // Reveal Step 2.
    location.reload();
  }
}

function togglePaymentSection() {
  const mode = document.querySelector('input[name="payment_mode_choice"]:checked')?.value;
  document.getElementById('manualBlock').style.display = (mode === 'manual') ? 'block' : 'none';
  document.getElementById('onlineBlock').style.display = (mode === 'online') ? 'block' : 'none';
}

async function submitManual() {
  const date = document.getElementById('t_date').value;
  const num  = document.getElementById('t_num').value.trim();
  const amt  = document.getElementById('t_amount').value;
  const file = document.getElementById('t_proof').files[0];
  if (!date || !num || !amt) { showToast('Date, transaction number and amount are required.', 'warning'); return; }
  if (!file) { showToast('Transaction proof file is mandatory.', 'warning'); return; }

  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('payment_mode', 'manual');
  fd.append('transaction_date',   date);
  fd.append('transaction_number', num);
  fd.append('transaction_amount', amt);
  fd.append('transaction_proof',  file);
  const res = await fetch(SUBMIT_URL, { method:'POST', body: fd });
  let data; try { data = await res.json(); } catch(_) { data = { success:false, message:'Server error.' }; }
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) setTimeout(() => { window.location.href = data.redirect || '/athlete/my-registrations'; }, 800);
}

async function submitOnline() {
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('payment_mode', 'online');
  const res = await fetch(SUBMIT_URL, { method:'POST', body: fd });
  let data; try { data = await res.json(); } catch(_) { data = { success:false, message:'Server error.' }; }
  showToast(data.message, data.success ? 'success' : 'warning');
  if (data.success) setTimeout(() => { window.location.href = data.redirect || '/athlete/my-registrations'; }, 800);
}

document.addEventListener('DOMContentLoaded', () => {
  rebuildSportDropdown();
  renderSelectedRows();
});
</script>
