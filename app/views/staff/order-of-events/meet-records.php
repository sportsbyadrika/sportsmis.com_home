<?php
$pageTitle = 'Meet Records';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];
?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/order-of-events" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-trophy me-2"></i>Meet Records</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
</div>

<?= flashBag() ?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="sms-card p-3">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-pencil-square me-1"></i><span id="mrFormTitle">Add / Update a Record</span></h6>
      <input type="hidden" id="mrEsid" value="">
      <div class="mb-2">
        <label class="form-label small mb-1">Filter by Event Category</label>
        <select id="mrCat" class="form-select form-select-sm">
          <option value="">All categories</option>
          <?php foreach (($categories ?? []) as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="mb-2">
        <label class="form-label small mb-1">Event <span class="text-danger">*</span></label>
        <select id="mrEvent" class="form-select form-select-sm"><option value="">— Select event —</option></select>
      </div>
      <div class="mb-2">
        <label class="form-label small mb-1">Record Value <span class="text-danger">*</span></label>
        <input type="text" id="mrValue" maxlength="60" class="form-control form-control-sm" placeholder="e.g. 10.85 s / 6.42 m">
      </div>
      <div class="row g-2">
        <div class="col-7"><label class="form-label small mb-1">Name of Meet</label>
          <input type="text" id="mrMeet" maxlength="160" class="form-control form-control-sm" placeholder="e.g. 45th State Meet"></div>
        <div class="col-5"><label class="form-label small mb-1">Year</label>
          <input type="text" id="mrYear" maxlength="10" class="form-control form-control-sm" placeholder="e.g. 2023"></div>
      </div>
      <div class="mb-3 mt-2">
        <label class="form-label small mb-1">Name of Athlete</label>
        <input type="text" id="mrAthlete" maxlength="160" class="form-control form-control-sm" placeholder="Record holder">
      </div>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm" id="mrSaveBtn"><i class="bi bi-save me-1"></i>Save Record</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="mrResetBtn">Reset</button>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="sms-card p-3">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-list-columns me-1"></i>Stored Records</h6>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light"><tr>
            <th>Event</th><th>Record</th><th>Meet</th><th>Year</th><th>Athlete</th><th class="text-end">—</th>
          </tr></thead>
          <tbody id="mrRows"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="mrToast" class="toast align-items-center border-0" role="alert">
    <div class="d-flex"><div class="toast-body fw-medium" id="mrToastMsg"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div>
  </div>
</div>

<script>
const MR_EVENTS = <?= json_encode($events_json ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let MR_RECORDS  = <?= json_encode($records ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const MR_CSRF = '<?= e($csrfToken) ?>';
const $ = id => document.getElementById(id);
const esc = s => (s == null ? '' : String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])));

function fillEvents() {
  const cat = $('mrCat').value, sel = $('mrEvent'), keep = sel.value;
  sel.innerHTML = '<option value="">— Select event —</option>';
  MR_EVENTS.filter(ev => !cat || ev.category === cat).forEach(ev => {
    const o = document.createElement('option'); o.value = ev.esid; o.textContent = ev.label; sel.appendChild(o);
  });
  if ([...sel.options].some(o => o.value === keep)) sel.value = keep;
}
function renderList() {
  const b = $('mrRows');
  if (!MR_RECORDS.length) { b.innerHTML = '<tr><td colspan="6" class="text-muted text-center py-3">No records stored yet.</td></tr>'; return; }
  b.innerHTML = MR_RECORDS.map(r => {
    const evLabel = [r.sport_event_name, r.age_name].filter(Boolean).join(' · ');
    return `<tr>
      <td class="small">${esc(evLabel)}</td>
      <td class="fw-semibold">${esc(r.record_value)}</td>
      <td class="small text-muted">${esc(r.meet_name || '')}</td>
      <td class="small text-muted">${esc(r.record_year || '')}</td>
      <td class="small text-muted">${esc(r.athlete_name || '')}</td>
      <td class="text-end text-nowrap">
        <button class="btn btn-sm btn-outline-primary py-0 px-1" data-es="${r.event_sport_id}" onclick="mrEdit(this)"><i class="bi bi-pencil"></i></button>
        <button class="btn btn-sm btn-outline-danger py-0 px-1" data-id="${r.id}" onclick="mrDelete(this)"><i class="bi bi-trash"></i></button>
      </td></tr>`;
  }).join('');
}
function mrEdit(btn) {
  const r = MR_RECORDS.find(x => x.event_sport_id == btn.dataset.es);
  if (!r) return;
  // Clear category filter so the event is present in the select.
  $('mrCat').value = ''; fillEvents();
  $('mrEsid').value = r.event_sport_id; $('mrEvent').value = r.event_sport_id;
  $('mrValue').value = r.record_value || ''; $('mrMeet').value = r.meet_name || '';
  $('mrYear').value = r.record_year || ''; $('mrAthlete').value = r.athlete_name || '';
  $('mrFormTitle').textContent = 'Update Record';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
function mrReset() {
  $('mrEsid').value = ''; $('mrEvent').value = ''; $('mrValue').value = '';
  $('mrMeet').value = ''; $('mrYear').value = ''; $('mrAthlete').value = '';
  $('mrFormTitle').textContent = 'Add / Update a Record';
}
function mrToast(msg, type) {
  const el = $('mrToast'); el.className = 'toast align-items-center border-0 text-bg-' + (type || 'primary');
  $('mrToastMsg').textContent = msg;
  if (window.bootstrap && bootstrap.Toast) bootstrap.Toast.getOrCreateInstance(el, { delay: 2500 }).show();
}
async function post(url, fd) {
  fd.append('_token', MR_CSRF);
  const res = await fetch(url, { method: 'POST', body: fd });
  try { return await res.json(); } catch (_) { return { success: false, message: 'Error.' }; }
}
async function mrSave() {
  const esid = $('mrEvent').value, value = $('mrValue').value.trim();
  if (!esid) { mrToast('Pick an event.', 'warning'); return; }
  if (!value) { mrToast('Enter the record value.', 'warning'); return; }
  const fd = new FormData();
  fd.append('event_sport_id', esid); fd.append('record_value', value);
  fd.append('meet_name', $('mrMeet').value.trim()); fd.append('record_year', $('mrYear').value.trim());
  fd.append('athlete_name', $('mrAthlete').value.trim());
  const d = await post('/event-staff/meet-records/save', fd);
  mrToast(d.message, d.success ? 'success' : 'danger');
  if (d.success) { MR_RECORDS = d.records || []; renderList(); mrReset(); }
}
async function mrDelete(btn) {
  if (!confirm('Delete this meet record?')) return;
  const fd = new FormData(); fd.append('id', btn.dataset.id);
  const d = await post('/event-staff/meet-records/delete', fd);
  mrToast(d.message, d.success ? 'success' : 'danger');
  if (d.success) { MR_RECORDS = d.records || []; renderList(); }
}
document.addEventListener('DOMContentLoaded', () => {
  fillEvents(); renderList();
  $('mrCat').addEventListener('change', fillEvents);
  $('mrSaveBtn').addEventListener('click', mrSave);
  $('mrResetBtn').addEventListener('click', mrReset);
});
</script>
