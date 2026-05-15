<?php
$pageTitle = 'Edit Registration #' . (int)$registration['id'];
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken    = $_SESSION['csrf_token'];
$isOtherUnit  = !empty($registration['unit_name_other']) && empty($registration['unit_id']);
$totalAmount  = (float)($registration['total_amount'] ?? 0);
$selectedIds  = array_map(fn($r) => (int)$r['event_sport_id'], $items);
// Pool of sport events on this event that aren't already selected — fed
// to the "Add available sport events" picker.
$availableSportEvents = array_values(array_filter($event_sports, fn($r) => !in_array((int)$r['id'], $selectedIds, true)));
// Lookup of "items pool" by sport so the Items / Weapons section can
// chain Sport → Item without an extra round-trip.
$itemsBySport = [];
foreach ($event_items as $ei) {
    $sid = (int)$ei['sport_id'];
    if (!isset($itemsBySport[$sid])) {
        $itemsBySport[$sid] = ['name' => $ei['sport_name'], 'items' => []];
    }
    $itemsBySport[$sid]['items'][] = [
        'id'   => (int)$ei['sport_item_id'],
        'name' => $ei['item_name'],
    ];
}
?>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="editToast" class="toast align-items-center border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body fw-medium" id="editToastMsg"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/registrations/<?= (int)$registration['id'] ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Back to View
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Registration #<?= (int)$registration['id'] ?></h5>
  <span class="text-muted small ms-2">
    <?= e($registration['athlete_name'] ?? '') ?>
    <?php if (!empty($registration['competitor_number'])): ?>
      · Competitor #<?= (int)$registration['competitor_number'] ?>
    <?php endif; ?>
  </span>
</div>

<?= flashBag() ?>

<div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
  <i class="bi bi-exclamation-triangle-fill"></i>
  <div class="small">
    You're editing this registration as the event administrator. Changes here
    bypass the athlete's own draft state — review the application + payment
    decisions afterwards if needed.
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-8">

    <!-- Header: Unit / Reg No -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 flex-wrap gap-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-building me-2"></i>Unit / Club / Institution</h6>
        <button type="button" class="btn btn-sm btn-primary" onclick="saveHeader()">
          <i class="bi bi-save me-1"></i>Save
        </button>
      </div>
      <div class="row g-3">
        <div class="col-md-7">
          <label class="form-label fw-medium">Unit / Club / Institution <span class="text-danger">*</span></label>
          <select id="r_unit" class="form-select" onchange="onUnitChange()">
            <option value="">— Select Unit —</option>
            <?php foreach ($units as $u): ?>
              <option value="<?= (int)$u['id'] ?>"
                      <?= !$isOtherUnit && (int)($registration['unit_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
                <?= e($u['name']) ?><?php if (!empty($u['address'])): ?> — <?= e($u['address']) ?><?php endif; ?>
              </option>
            <?php endforeach; ?>
            <option value="OTHER" <?= $isOtherUnit ? 'selected' : '' ?>>Other (specify name)</option>
          </select>
          <div id="r_unit_other_wrap" class="mt-2" style="<?= $isOtherUnit ? '' : 'display:none' ?>">
            <input type="text" id="r_unit_other" class="form-control"
                   value="<?= e($registration['unit_name_other'] ?? '') ?>"
                   maxlength="255" placeholder="Type the Unit / Club / Institution name">
          </div>
        </div>
        <div class="col-md-5">
          <label class="form-label fw-medium">Unit Registration No.</label>
          <input type="text" id="r_unit_reg_no" class="form-control"
                 value="<?= e($registration['unit_reg_no'] ?? '') ?>"
                 maxlength="100" placeholder="e.g. SAI/2024/12345">
          <small class="text-muted">Optional — registration number issued by the parent body.</small>
        </div>
      </div>
    </div>

    <!-- Selected Sport Events -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 flex-wrap gap-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-trophy me-2"></i>Selected Sport Events</h6>
        <span class="small text-muted">Total: <strong>₹<span id="totalAmt"><?= number_format($totalAmount, 2) ?></span></strong></span>
      </div>

      <div class="row g-2 align-items-end mb-3">
        <div class="col-md-9">
          <label class="form-label small mb-1">Add available sport event</label>
          <select id="addEsId" class="form-select form-select-sm">
            <option value="">— Pick a sport event to add —</option>
            <?php foreach ($availableSportEvents as $r): ?>
              <option value="<?= (int)$r['id'] ?>" data-fee="<?= (float)$r['entry_fee'] ?>">
                <?= e($r['sport_name']) ?>
                <?php if (!empty($r['event_code'])): ?> [<?= e($r['event_code']) ?>]<?php endif; ?>
                · <?= e($r['sport_event_name'] ?? $r['category'] ?? '') ?>
                · <?= e($r['sport_event_age_category'] ?? '') ?>
                <?= e($r['sport_event_gender'] ?? '') ?>
                — ₹<?= number_format((float)$r['entry_fee'], 2) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <button type="button" class="btn btn-sm btn-primary w-100" onclick="addSportEvent()">
            <i class="bi bi-plus-lg me-1"></i>Add
          </button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr><th>Sport</th><th>Code</th><th>Event</th><th class="text-end">Fee</th><th></th></tr>
          </thead>
          <tbody id="itemRows">
            <?php if (empty($items)): ?>
              <tr id="emptyItems"><td colspan="5" class="text-muted text-center py-3">No sport events selected.</td></tr>
            <?php else: foreach ($items as $it): ?>
              <tr data-id="<?= (int)$it['id'] ?>">
                <td><?= e($it['sport_name'] ?? '') ?></td>
                <td><code><?= e($it['event_code'] ?? '') ?></code></td>
                <td><?= e($it['sport_event_name'] ?? $it['category'] ?? '') ?></td>
                <td class="text-end">₹<?= number_format((float)$it['fee'], 2) ?></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-danger" type="button"
                          onclick="removeSportEvent(<?= (int)$it['id'] ?>)" title="Remove">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Sports Items / Weapons Sharing -->
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-tools me-2"></i>Sports Items / Weapons Sharing Details</h6>

      <?php if (empty($itemsBySport)): ?>
        <p class="text-muted small mb-0">The organiser hasn't published any allow-listed items / weapons for this event.</p>
      <?php else: ?>
      <div class="row g-2 align-items-end mb-3">
        <input type="hidden" id="rsi_id" value="">
        <div class="col-md-3">
          <label class="form-label small mb-1">Sport</label>
          <select id="rsi_sport" class="form-select form-select-sm" onchange="onRsiSportChange()">
            <option value="">Select…</option>
            <?php foreach ($itemsBySport as $sid => $info): ?>
              <option value="<?= (int)$sid ?>"><?= e($info['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">Item / Weapon</label>
          <select id="rsi_item" class="form-select form-select-sm">
            <option value="">— pick a sport first —</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small mb-1">Model</label>
          <input type="text" id="rsi_model" class="form-control form-control-sm" placeholder="Model">
        </div>
        <div class="col-md-2">
          <label class="form-label small mb-1">Serial No.</label>
          <input type="text" id="rsi_serial" class="form-control form-control-sm" placeholder="Serial #">
        </div>
        <div class="col-md-2 d-flex gap-1">
          <button type="button" class="btn btn-sm btn-primary flex-fill" onclick="saveItem()">
            <i class="bi bi-save me-1"></i><span id="rsi_btn_label">Add</span>
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetItemForm()" title="Reset">
            <i class="bi bi-x"></i>
          </button>
        </div>
      </div>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr><th>Sport</th><th>Item / Weapon</th><th>Model</th><th>Serial Number</th><th></th></tr>
          </thead>
          <tbody id="rsiRows">
            <?php if (empty($sport_items)): ?>
              <tr id="emptyRsi"><td colspan="5" class="text-muted text-center py-3">No items declared.</td></tr>
            <?php else: foreach ($sport_items as $r): ?>
              <tr data-id="<?= (int)$r['id'] ?>"
                  data-sport-id="<?= (int)$r['sport_id'] ?>"
                  data-item-id="<?= (int)$r['sport_item_id'] ?>"
                  data-model="<?= e($r['model'] ?? '') ?>"
                  data-serial="<?= e($r['serial_number'] ?? '') ?>">
                <td class="small text-muted"><?= e($r['sport_name']) ?></td>
                <td class="fw-medium"><?= e($r['item_name']) ?></td>
                <td><?= e($r['model'] ?? '—') ?></td>
                <td><?= e($r['serial_number'] ?? '—') ?></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-secondary me-1" type="button" onclick="editItem(this)" title="Edit">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-danger" type="button" onclick="deleteItem(<?= (int)$r['id'] ?>)" title="Delete">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <!-- Athlete summary -->
    <div class="sms-card p-3 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-person-badge me-2"></i>Athlete</h6>
      <div class="small">
        <div class="fw-bold"><?= e($registration['athlete_name'] ?? '') ?></div>
        <div class="text-muted"><?= e($registration['athlete_mobile'] ?? '') ?> · <?= e($registration['athlete_email'] ?? '') ?></div>
        <div class="text-muted mt-1"><?= e($registration['event_name']) ?></div>
      </div>
    </div>

    <!-- Payment transactions admin override -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 flex-wrap gap-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-receipt me-2"></i>Payment Transactions</h6>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addManualPayModal">
          <i class="bi bi-plus-lg me-1"></i>Add
        </button>
      </div>
      <div class="small text-muted mb-3">
        Approved: <strong class="text-success">₹<?= number_format((float)($pay_totals['approved_amount'] ?? 0), 2) ?></strong>
        · Required: <strong>₹<?= number_format($totalAmount, 2) ?></strong>
      </div>
      <?php if (empty($payments)): ?>
        <p class="text-muted small mb-0">No transactions submitted yet.</p>
      <?php else: foreach ($payments as $p):
        $isEpay = ($p['payment_method'] ?? 'manual') === 'epayment';
        $txnNo  = $isEpay ? ($p['razorpay_payment_id'] ?: $p['razorpay_order_id'] ?: $p['transaction_number'])
                          : $p['transaction_number'];
      ?>
        <div class="border rounded-3 p-2 mb-2 small">
          <div class="d-flex justify-content-between align-items-start mb-1">
            <div>
              <div class="fw-bold">₹<?= number_format((float)$p['amount'], 2) ?></div>
              <div class="text-muted"><?= formatDate($p['transaction_date']) ?>
                · <?= $isEpay ? '<span class="badge bg-info-subtle text-info">ePayment</span>'
                              : '<span class="badge bg-secondary-subtle text-secondary">Manual</span>' ?>
              </div>
              <code class="text-muted"><?= e($txnNo) ?></code>
              <?php if (!empty($p['proof_file'])): ?>
                <a href="<?= e($p['proof_file']) ?>" target="_blank" class="ms-1">
                  <i class="bi bi-eye"></i>
                </a>
              <?php endif; ?>
            </div>
            <?= statusBadge($p['status']) ?>
          </div>
          <?php if ($p['status'] === 'rejected' && !empty($p['rejection_reason'])): ?>
            <div class="text-muted small fst-italic mb-1"><?= e($p['rejection_reason']) ?></div>
          <?php endif; ?>
          <form method="POST" action="/institution/registrations/payments/<?= (int)$p['id'] ?>/status"
                class="row g-1 align-items-center mt-1"
                onsubmit="return confirm('Change this transaction\\'s status?')">
            <?= csrf() ?>
            <div class="col-6">
              <select name="status" class="form-select form-select-sm">
                <option value="pending"  <?= $p['status']==='pending'  ? 'selected':'' ?>>Pending</option>
                <option value="approved" <?= $p['status']==='approved' ? 'selected':'' ?>>Approved</option>
                <option value="rejected" <?= $p['status']==='rejected' ? 'selected':'' ?>>Rejected</option>
              </select>
            </div>
            <div class="col-6">
              <button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-save me-1"></i>Apply</button>
            </div>
            <div class="col-12 mt-1">
              <input type="text" name="reason" class="form-control form-control-sm"
                     value="<?= e($p['rejection_reason'] ?? '') ?>"
                     placeholder="Rejection reason (if rejected)">
            </div>
          </form>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<!-- Add Manual Transaction modal (reuses existing endpoint) -->
<div class="modal fade" id="addManualPayModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="POST"
          action="/institution/registrations/<?= (int)$registration['id'] ?>/payments/add"
          enctype="multipart/form-data">
      <?= csrf() ?>
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Manual Transaction</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Transaction Date <span class="text-danger">*</span></label>
            <input type="date" name="transaction_date" class="form-control" max="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Transaction Number <span class="text-danger">*</span></label>
            <input type="text" name="transaction_number" class="form-control" maxlength="100" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Amount <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">₹</span>
              <input type="number" name="transaction_amount" class="form-control" min="0.01" step="0.01" required>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Proof <small class="text-muted">(Optional)</small></label>
            <input type="file" name="transaction_proof" class="form-control" accept="image/jpeg,image/png,application/pdf">
          </div>
          <div class="col-12">
            <label class="form-label">Initial Decision</label>
            <div class="d-flex gap-3 flex-wrap">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="decision" id="ndec_pending" value="pending" checked>
                <label class="form-check-label" for="ndec_pending">Pending</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="decision" id="ndec_approve" value="approve">
                <label class="form-check-label" for="ndec_approve">Approve</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="decision" id="ndec_reject" value="reject">
                <label class="form-check-label" for="ndec_reject">Reject</label>
              </div>
            </div>
          </div>
          <div class="col-12 d-none" id="ndecRejWrap">
            <label class="form-label">Rejection Reason</label>
            <textarea name="rejection_reason" class="form-control" rows="2"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Transaction</button>
      </div>
    </form>
  </div>
</div>

<script>
const CSRF   = '<?= e($csrfToken) ?>';
const REG_ID = <?= (int)$registration['id'] ?>;
const ITEMS_BY_SPORT = <?= json_encode($itemsBySport) ?>;
const SAVE_URL = '/institution/registrations/' + REG_ID + '/edit/save';

function showToast(msg, type) {
  const el = document.getElementById('editToast');
  el.className = 'toast align-items-center border-0 text-bg-' + (type || 'primary');
  document.getElementById('editToastMsg').textContent = msg;
  if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
    bootstrap.Toast.getOrCreateInstance(el, { delay: 2800 }).show();
  } else { alert(msg); }
}

async function postSection(section, extra) {
  const fd = new FormData();
  fd.append('_token',  CSRF);
  fd.append('section', section);
  Object.entries(extra || {}).forEach(([k, v]) => fd.append(k, v));
  const res = await fetch(SAVE_URL, { method: 'POST', body: fd });
  let data; try { data = await res.json(); } catch (_) { data = { success:false, message:'Server returned invalid response.' }; }
  return data;
}

/* ── Unit / Reg-No ── */
function onUnitChange() {
  const v = document.getElementById('r_unit').value;
  document.getElementById('r_unit_other_wrap').style.display = v === 'OTHER' ? '' : 'none';
}
async function saveHeader() {
  const unit = document.getElementById('r_unit').value;
  const data = await postSection('header', {
    unit_id:         unit,
    unit_name_other: document.getElementById('r_unit_other').value,
    unit_reg_no:     document.getElementById('r_unit_reg_no').value,
  });
  showToast(data.message, data.success ? 'success' : 'danger');
}

/* ── Selected Sport Events ── */
async function addSportEvent() {
  const sel = document.getElementById('addEsId');
  const v = sel.value;
  if (!v) { showToast('Pick a sport event to add.', 'warning'); return; }
  const data = await postSection('sport_event_add', { event_sport_id: v });
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) {
    sel.querySelector(`option[value="${v}"]`)?.remove();
    sel.value = '';
    renderItems(data.items, data.total);
  }
}

async function removeSportEvent(itemId) {
  if (!confirm('Remove this sport event from the registration?')) return;
  const data = await postSection('sport_event_remove', { item_id: itemId });
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) renderItems(data.items, data.total);
}

function renderItems(items, total) {
  const tb = document.getElementById('itemRows');
  if (!items || !items.length) {
    tb.innerHTML = '<tr id="emptyItems"><td colspan="5" class="text-muted text-center py-3">No sport events selected.</td></tr>';
  } else {
    tb.innerHTML = items.map(it => `
      <tr data-id="${it.id}">
        <td>${escapeHtml(it.sport_name || '')}</td>
        <td><code>${escapeHtml(it.event_code || '')}</code></td>
        <td>${escapeHtml(it.sport_event_name || it.category || '')}</td>
        <td class="text-end">₹${Number(it.fee).toFixed(2)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-danger" type="button" onclick="removeSportEvent(${it.id})">
            <i class="bi bi-trash"></i>
          </button>
        </td>
      </tr>`).join('');
  }
  document.getElementById('totalAmt').textContent = Number(total || 0).toFixed(2);
}

/* ── Sports Items / Weapons ── */
function onRsiSportChange() {
  const sid = document.getElementById('rsi_sport').value;
  const sel = document.getElementById('rsi_item');
  sel.innerHTML = '<option value="">— Select Item —</option>';
  if (!sid || !ITEMS_BY_SPORT[sid]) return;
  ITEMS_BY_SPORT[sid].items.forEach(it => {
    const o = document.createElement('option');
    o.value = it.id; o.textContent = it.name;
    sel.appendChild(o);
  });
}
function resetItemForm() {
  document.getElementById('rsi_id').value     = '';
  document.getElementById('rsi_sport').value  = '';
  document.getElementById('rsi_item').innerHTML  = '<option value="">— pick a sport first —</option>';
  document.getElementById('rsi_model').value  = '';
  document.getElementById('rsi_serial').value = '';
  document.getElementById('rsi_btn_label').textContent = 'Add';
}
function editItem(btn) {
  const row = btn.closest('tr');
  document.getElementById('rsi_id').value     = row.dataset.id;
  document.getElementById('rsi_sport').value  = row.dataset.sportId;
  onRsiSportChange();
  document.getElementById('rsi_item').value   = row.dataset.itemId;
  document.getElementById('rsi_model').value  = row.dataset.model;
  document.getElementById('rsi_serial').value = row.dataset.serial;
  document.getElementById('rsi_btn_label').textContent = 'Update';
}
async function saveItem() {
  const id     = document.getElementById('rsi_id').value || '0';
  const itemId = document.getElementById('rsi_item').value;
  if (!itemId) { showToast('Pick an item / weapon.', 'warning'); return; }
  const data = await postSection('item_save', {
    id:            id,
    sport_item_id: itemId,
    model:         document.getElementById('rsi_model').value,
    serial_number: document.getElementById('rsi_serial').value,
  });
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) {
    renderRsi(data.list);
    resetItemForm();
  }
}
async function deleteItem(id) {
  if (!confirm('Remove this item / weapon entry?')) return;
  const data = await postSection('item_delete', { id });
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) renderRsi(data.list);
}
function renderRsi(list) {
  const tb = document.getElementById('rsiRows');
  if (!list || !list.length) {
    tb.innerHTML = '<tr id="emptyRsi"><td colspan="5" class="text-muted text-center py-3">No items declared.</td></tr>';
    return;
  }
  tb.innerHTML = list.map(r => `
    <tr data-id="${r.id}" data-sport-id="${r.sport_id}" data-item-id="${r.sport_item_id}"
        data-model="${escapeHtml(r.model || '')}" data-serial="${escapeHtml(r.serial_number || '')}">
      <td class="small text-muted">${escapeHtml(r.sport_name || '')}</td>
      <td class="fw-medium">${escapeHtml(r.item_name || '')}</td>
      <td>${escapeHtml(r.model || '—')}</td>
      <td>${escapeHtml(r.serial_number || '—')}</td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-secondary me-1" type="button" onclick="editItem(this)"><i class="bi bi-pencil"></i></button>
        <button class="btn btn-sm btn-outline-danger" type="button" onclick="deleteItem(${r.id})"><i class="bi bi-trash"></i></button>
      </td>
    </tr>`).join('');
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

/* Modal: toggle rejection-reason visibility */
(function () {
  const wrap = document.getElementById('ndecRejWrap');
  document.querySelectorAll('#addManualPayModal input[name="decision"]').forEach(r => {
    r.addEventListener('change', () => {
      wrap.classList.toggle('d-none', r.value !== 'reject' || !r.checked);
    });
  });
})();
</script>
