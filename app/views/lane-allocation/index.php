<?php
$pageTitle = 'Lane Allocation';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$isAdmin   = ($actor['mode'] === 'admin');
?>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="laToast" class="toast align-items-center border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body fw-medium" id="laToastMsg"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<!-- ── Top Bar ───────────────────────────────────────────────── -->
<div class="sms-card p-3 mb-3">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h5 class="mb-0 fw-bold"><i class="bi bi-bullseye me-2"></i>Lane Allocation</h5>
      <div class="text-muted small mt-1">
        <span class="badge bg-primary-subtle text-primary-emphasis"><i class="bi bi-hash"></i> <?= e($event['event_code'] ?? '') ?></span>
        <span class="ms-1"><?= e($event['name']) ?></span>
      </div>
    </div>
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <span class="small text-muted" id="laMeta"></span>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="laLoad()">
        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
      </button>
      <?php if ($isAdmin): ?>
      <div class="form-check form-switch m-0 border rounded-3 px-3 py-2 bg-light">
        <input class="form-check-input" type="checkbox" role="switch" id="unitAccessToggle"
               onchange="laToggleUnitAccess(this)">
        <label class="form-check-label fw-medium small" for="unitAccessToggle">
          Allow Unit Users to Manage Lane Allocation
        </label>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($isAdmin): ?>
<!-- ── Panel 1 — Unit × Event Category Pivot ─────────────────── -->
<div class="sms-card p-3 mb-3">
  <h6 class="fw-semibold border-bottom pb-2 mb-2">
    <i class="bi bi-grid-3x3-gap me-2"></i>Unit &times; Event Category — Allocation Summary
  </h6>
  <p class="small text-muted mb-2">Each cell: <strong>Reg</strong> registered &middot; <strong>Assigned</strong> lanes assigned to the unit &middot; <strong>Allotted</strong> athletes on a lane. Click a cell to filter the workspace.</p>
  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle mb-0" id="pivotTable">
      <thead class="table-light"><tr><th>Unit</th></tr></thead>
      <tbody></tbody>
      <tfoot class="table-light"></tfoot>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ── Unit list (draggable) ─────────────────────────────────── -->
<?php if ($isAdmin): ?>
<div class="sms-card p-3 mb-3">
  <h6 class="fw-semibold mb-2"><i class="bi bi-buildings me-2"></i>Units <small class="text-muted fw-normal">— drag a unit onto a lane row to assign it</small></h6>
  <div class="d-flex gap-2 overflow-auto pb-2" id="unitChips" style="min-height:56px"></div>
</div>
<?php endif; ?>

<!-- ── Workspace tabs (small screens) ────────────────────────── -->
<ul class="nav nav-pills d-lg-none mb-2" id="wsTabs">
  <li class="nav-item"><button class="nav-link active" type="button" onclick="wsTab('left')">Lanes</button></li>
  <li class="nav-item"><button class="nav-link" type="button" onclick="wsTab('right')">Pending Athletes</button></li>
</ul>

<div class="row g-3 mb-3">
  <!-- Left column — relay/lane table -->
  <div class="col-lg-7" id="wsLeft">
    <div class="sms-card p-3">
      <h6 class="fw-semibold mb-2"><i class="bi bi-list-ol me-2"></i>Lanes by Relay</h6>
      <div class="row g-2 mb-2">
        <div class="col-6 col-md-3">
          <select id="fRelay" class="form-select form-select-sm" onchange="renderLanes()">
            <option value="">All relays</option>
          </select>
        </div>
        <div class="col-6 col-md-3">
          <select id="fCategory" class="form-select form-select-sm" onchange="renderLanes()">
            <option value="">All categories</option>
          </select>
        </div>
        <div class="col-6 col-md-3">
          <select id="fUnit" class="form-select form-select-sm" onchange="renderLanes()">
            <option value="">All units</option>
          </select>
        </div>
        <div class="col-6 col-md-3">
          <select id="fStatus" class="form-select form-select-sm" onchange="renderLanes()">
            <option value="">All statuses</option>
            <option value="unit">Unit Assigned</option>
            <option value="athlete">Athlete Allotted</option>
            <option value="none">Unassigned</option>
          </select>
        </div>
      </div>
      <div class="table-responsive" style="max-height:520px;overflow:auto">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light" style="position:sticky;top:0;z-index:1">
            <tr>
              <th>Relay</th><th>Date / Time</th><th>Lane</th><th>Type</th>
              <th>Category</th><th>Assigned Unit</th><th>Assigned Athlete</th>
            </tr>
          </thead>
          <tbody id="laneRows"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Right column — pending athletes -->
  <div class="col-lg-5 d-none d-lg-block" id="wsRight">
    <div class="sms-card p-3">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-people me-2"></i>Pending Athletes</h6>
        <span class="badge bg-warning text-dark" id="pendingCount">0</span>
      </div>
      <input type="search" id="fPending" class="form-control form-control-sm mb-2"
             placeholder="Search name, competitor #, unit…" oninput="renderPending()">
      <div id="pendingList" style="max-height:520px;overflow:auto"></div>
    </div>
  </div>
</div>

<!-- ── Panel 2 — Lane Allocation table by relay ──────────────── -->
<div class="sms-card p-3 mb-4">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
    <h6 class="fw-semibold mb-0"><i class="bi bi-table me-2"></i>Lane Allocation Summary</h6>
    <div class="d-flex gap-2 flex-wrap">
      <select id="p2Relay" class="form-select form-select-sm" style="width:auto" onchange="renderPanel2()">
        <option value="">All relays</option>
      </select>
      <button class="btn btn-sm btn-outline-secondary" type="button" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Print
      </button>
      <button class="btn btn-sm btn-outline-success" type="button" onclick="exportPanel2()">
        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel
      </button>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle mb-0" id="panel2Table">
      <thead class="table-light">
        <tr>
          <th>Relay</th><th>Date &amp; Time</th><th>Lane</th><th>Type</th><th>Category</th>
          <th>Assigned Unit</th><th>Allocated Athlete</th><th>Events Registered</th><th>Status</th>
        </tr>
      </thead>
      <tbody id="panel2Rows"></tbody>
      <tfoot class="table-light"><tr id="panel2Foot"></tr></tfoot>
    </table>
  </div>
</div>

<style>
.la-droptarget.la-over { outline:2px dashed #0d6efd; outline-offset:-2px; background:#e7f1ff }
.la-chip { cursor:grab; user-select:none }
.la-chip:active { cursor:grabbing }
.la-row-allotted { background:#d1e7dd33 }
.la-cell-amber { background:#fff3cd }
.la-cell-green { background:#d1e7dd }
.la-pivot-cell { cursor:pointer }
@media print { .nav-pills,.toast-container,#unitChips,#wsRight,.btn,select,#unitAccessToggle { display:none !important } }
</style>

<script>
const CSRF      = '<?= e($csrfToken) ?>';
const IS_ADMIN  = <?= $isAdmin ? 'true' : 'false' ?>;
let STATE = null;

function laToast(msg, type) {
  const el = document.getElementById('laToast');
  el.className = 'toast align-items-center border-0 text-bg-' + (type || 'primary');
  document.getElementById('laToastMsg').textContent = msg;
  if (window.bootstrap && bootstrap.Toast) bootstrap.Toast.getOrCreateInstance(el, {delay:2500}).show();
}
function esc(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g, c =>
    ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

/* ── Data load ── */
async function laLoad() {
  const res  = await fetch('/lane-allocation/data');
  const data = await res.json();
  if (!data.success) { laToast('Could not load allocation data.', 'danger'); return; }
  STATE = data;
  hydrateFilters();
  if (IS_ADMIN) { renderPivot(); renderUnitChips(); syncToggle(); }
  renderLanes();
  renderPending();
  renderPanel2();
  renderMeta();
}

function renderMeta() {
  const m = STATE.last_modified;
  document.getElementById('laMeta').innerHTML = m && m.allocated_at
    ? 'Last saved ' + esc(m.allocated_at) + ' by <strong>' + esc(m.allocated_by || '—') + '</strong>'
    : 'No allocations saved yet';
}
function syncToggle() {
  const t = document.getElementById('unitAccessToggle');
  if (t) t.checked = STATE.unit_access == 1;
}

function hydrateFilters() {
  const cats = [...new Set(STATE.relay_lanes.map(l => l.category).filter(Boolean))].sort();
  const relays = STATE.relay_numbers || [];
  const fill = (id, vals, label) => {
    const el = document.getElementById(id);
    if (!el) return;
    const cur = el.value;
    el.innerHTML = '<option value="">' + label + '</option>'
      + vals.map(v => `<option value="${esc(v)}">${esc(v)}</option>`).join('');
    el.value = cur;
  };
  fill('fRelay', relays, 'All relays');
  fill('fCategory', cats, 'All categories');
  fill('p2Relay', relays, 'All relays');
  const fu = document.getElementById('fUnit');
  if (fu) {
    const cur = fu.value;
    fu.innerHTML = '<option value="">All units</option>'
      + STATE.units.map(u => `<option value="${u.id}">${esc(u.name)}</option>`).join('');
    fu.value = cur;
  }
}

/* ── Panel 1 — pivot ── */
function renderPivot() {
  if (!STATE.pivot) return;
  const cats = STATE.pivot.categories, rows = STATE.pivot.rows;
  const thead = document.querySelector('#pivotTable thead');
  thead.innerHTML = '<tr><th>Unit</th>' + cats.map(c => `<th class="text-center">${esc(c)}</th>`).join('')
    + '<th class="text-center">Total</th></tr>';
  const tbody = document.querySelector('#pivotTable tbody');
  const colTot = {}; cats.forEach(c => colTot[c] = {reg:0,assigned:0,allotted:0});
  let html = '';
  Object.keys(rows).forEach(unit => {
    const rt = {reg:0,assigned:0,allotted:0};
    html += '<tr><td class="fw-medium">' + esc(unit) + '</td>';
    cats.forEach(c => {
      const cell = rows[unit][c] || {reg:0,assigned:0,allotted:0};
      ['reg','assigned','allotted'].forEach(k => { rt[k]+=cell[k]; colTot[c][k]+=cell[k]; });
      let cls = '';
      if (cell.reg > 0 && cell.allotted >= cell.reg) cls = 'la-cell-green';
      else if (cell.reg > 0 && cell.allotted < cell.reg) cls = 'la-cell-amber';
      html += `<td class="text-center la-pivot-cell ${cls}" onclick="pivotFilter('${esc(unit)}','${esc(c)}')">`
        + `<div class="small">Reg: <strong>${cell.reg}</strong> | Assigned: <strong>${cell.assigned}</strong>`
        + ` | Allotted: <strong>${cell.allotted}</strong></div></td>`;
    });
    html += `<td class="text-center fw-bold small">R${rt.reg}/A${rt.assigned}/L${rt.allotted}</td></tr>`;
  });
  tbody.innerHTML = html || `<tr><td colspan="${cats.length+2}" class="text-muted text-center py-3">No data.</td></tr>`;
  const tfoot = document.querySelector('#pivotTable tfoot');
  tfoot.innerHTML = '<tr><th>Total</th>' + cats.map(c =>
    `<th class="text-center small">Reg: ${colTot[c].reg} | Assigned: ${colTot[c].assigned} | Allotted: ${colTot[c].allotted}</th>`
  ).join('') + '<th></th></tr>';
}
function pivotFilter(unit, cat) {
  const u = STATE.units.find(x => x.name === unit);
  if (u) document.getElementById('fUnit').value = u.id;
  document.getElementById('fCategory').value = cat;
  renderLanes();
  document.getElementById('wsLeft').scrollIntoView({behavior:'smooth'});
}

/* ── Unit chips ── */
function renderUnitChips() {
  const box = document.getElementById('unitChips');
  if (!box) return;
  box.innerHTML = STATE.units.map(u => `
    <div class="la-chip border rounded-3 px-3 py-2 bg-white flex-shrink-0" draggable="true"
         data-kind="unit" data-id="${u.id}"
         ondragstart="laDragStart(event,'unit',${u.id})">
      <div class="fw-medium small">${esc(u.name)}</div>
      <div class="text-muted" style="font-size:.72rem">Code #${u.id} ·
        <span class="badge bg-secondary-subtle text-secondary">${u.lane_count} lane(s)</span></div>
    </div>`).join('') || '<span class="text-muted small">No units configured.</span>';
}

/* ── Left — lane table ── */
function laneFiltered() {
  const fr = document.getElementById('fRelay').value;
  const fc = document.getElementById('fCategory').value;
  const fu = document.getElementById('fUnit') ? document.getElementById('fUnit').value : '';
  const fs = document.getElementById('fStatus').value;
  return STATE.relay_lanes.filter(l => {
    if (fr && String(l.relay_number) !== fr) return false;
    if (fc && (l.category || '') !== fc) return false;
    if (fu && String(l.assigned_unit_id || '') !== fu) return false;
    if (fs === 'unit'    && !l.assigned_unit_id) return false;
    if (fs === 'athlete' && !l.assigned_registration_id) return false;
    if (fs === 'none'    && (l.assigned_unit_id || l.assigned_registration_id)) return false;
    return true;
  });
}
function renderLanes() {
  const tb = document.getElementById('laneRows');
  const list = laneFiltered();
  if (!list.length) { tb.innerHTML = '<tr><td colspan="7" class="text-muted text-center py-3">No lanes match the filters.</td></tr>'; return; }
  tb.innerHTML = list.map(l => {
    const allotted = !!l.assigned_registration_id;
    const unitCell = l.assigned_unit_id
      ? `<span class="badge bg-info-subtle text-info-emphasis">${esc(l.unit_name)}</span>`
        + (IS_ADMIN ? ` <button class="btn btn-link btn-sm p-0 text-danger" onclick="clearLane(${l.relay_id},${l.lane_id},'unit')" title="Remove">&times;</button>` : '')
      : '<span class="text-muted small">— drop unit —</span>';
    const athCell = allotted
      ? `<span class="badge bg-success-subtle text-success-emphasis">#${l.competitor_number||'—'} ${esc(l.athlete_name)}</span>`
        + ` <button class="btn btn-link btn-sm p-0 text-danger" onclick="clearLane(${l.relay_id},${l.lane_id},'athlete')" title="Remove">&times;</button>`
      : '<span class="text-muted small">— drop athlete —</span>';
    return `<tr class="la-droptarget ${allotted?'la-row-allotted':''}"
              data-relay="${l.relay_id}" data-lane="${l.lane_id}"
              ondragover="laDragOver(event)" ondragleave="laDragLeave(event)"
              ondrop="laDrop(event,${l.relay_id},${l.lane_id})">
      <td class="fw-medium">${esc(l.relay_number)}</td>
      <td class="small text-muted">${esc(l.relay_date||'—')}<br>${esc(l.match_time||'')}</td>
      <td>Lane ${esc(l.lane_number)}</td>
      <td class="small">${esc((l.lane_type||'').replace(/^./,c=>c.toUpperCase()))}</td>
      <td class="small">${esc(l.category||'—')}</td>
      <td>${unitCell}</td>
      <td>${athCell}</td>
    </tr>`;
  }).join('');
}

/* ── Right — pending athletes ── */
function renderPending() {
  const box = document.getElementById('pendingList');
  const q = (document.getElementById('fPending').value || '').toLowerCase();
  let list = STATE.pending;
  if (q) list = list.filter(p =>
    (p.athlete_name||'').toLowerCase().includes(q) ||
    String(p.competitor_number||'').includes(q) ||
    (p.unit_name||'').toLowerCase().includes(q));
  document.getElementById('pendingCount').textContent = STATE.pending.length;
  if (!list.length) { box.innerHTML = '<div class="text-muted small text-center py-3">No pending athletes.</div>'; return; }
  box.innerHTML = list.map(p => `
    <div class="la-chip border rounded-3 p-2 mb-2 d-flex gap-2 align-items-center bg-white"
         draggable="true" ondragstart="laDragStart(event,'athlete',${p.registration_id})">
      ${p.passport_photo
        ? `<img src="${esc(p.passport_photo)}" width="38" height="38" class="rounded-circle" style="object-fit:cover">`
        : `<div class="sms-avatar sms-avatar-sm">${esc((p.athlete_name||'?').charAt(0))}</div>`}
      <div class="min-w-0">
        <div class="fw-medium small text-truncate">${esc(p.athlete_name)}
          ${p.competitor_number ? `<span class="badge bg-secondary-subtle text-secondary">#${p.competitor_number}</span>` : ''}</div>
        <div class="text-muted text-truncate" style="font-size:.72rem">
          ${esc(p.unit_name||'—')} · ${esc(p.events_label||'')}</div>
      </div>
    </div>`).join('');
}

/* ── Panel 2 ── */
function panel2Filtered() {
  const fr = document.getElementById('p2Relay').value;
  return STATE.relay_lanes.filter(l => !fr || String(l.relay_number) === fr);
}
function laneStatus(l) {
  if (l.assigned_registration_id) return 'Athlete Allotted';
  if (l.assigned_unit_id)         return 'Unit Assigned';
  return 'Unassigned';
}
function renderPanel2() {
  const list = panel2Filtered();
  const tb = document.getElementById('panel2Rows');
  tb.innerHTML = list.map(l => {
    const st = laneStatus(l);
    const cls = l.assigned_registration_id ? 'table-success' : (!l.assigned_unit_id ? 'table-warning' : '');
    return `<tr class="${cls}">
      <td>${esc(l.relay_number)}</td>
      <td class="small">${esc(l.relay_date||'—')} ${esc(l.match_time||'')}</td>
      <td>Lane ${esc(l.lane_number)}</td>
      <td class="small">${esc(l.lane_type||'')}</td>
      <td class="small">${esc(l.category||'—')}</td>
      <td class="small">${l.assigned_unit_id ? '#'+l.assigned_unit_id+' '+esc(l.unit_name) : '—'}</td>
      <td class="small">${l.assigned_registration_id ? '#'+(l.competitor_number||'—')+' '+esc(l.athlete_name) : '—'}</td>
      <td class="small">${esc(l.events_label||'—')}</td>
      <td class="small">${st}</td>
    </tr>`;
  }).join('') || '<tr><td colspan="9" class="text-muted text-center py-3">No lanes.</td></tr>';
  const tot = list.length;
  const asg = list.filter(l => l.assigned_unit_id).length;
  const alt = list.filter(l => l.assigned_registration_id).length;
  document.getElementById('panel2Foot').innerHTML =
    `<th colspan="8" class="text-end">Total lanes: ${tot} &middot; Unit assigned: ${asg} &middot; Athlete allotted: ${alt}</th><th></th>`;
}
function exportPanel2() {
  const list = panel2Filtered();
  const head = ['Relay','Date','Time','Lane','Type','Category','Assigned Unit','Allocated Athlete','Events Registered','Status'];
  const rows = list.map(l => [
    l.relay_number, l.relay_date||'', l.match_time||'', l.lane_number, l.lane_type||'',
    l.category||'', l.assigned_unit_id ? '#'+l.assigned_unit_id+' '+(l.unit_name||'') : '',
    l.assigned_registration_id ? '#'+(l.competitor_number||'')+' '+(l.athlete_name||'') : '',
    l.events_label||'', laneStatus(l)
  ]);
  const csv = [head, ...rows].map(r => r.map(c => '"'+String(c).replace(/"/g,'""')+'"').join(',')).join('\n');
  const blob = new Blob([csv], {type:'application/vnd.ms-excel'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'lane-allocation.xls';
  a.click();
}

/* ── Drag & drop ── */
let DRAG = null;
function laDragStart(ev, kind, id) {
  DRAG = {kind, id};
  ev.dataTransfer.effectAllowed = 'move';
  ev.dataTransfer.setData('text/plain', kind + ':' + id);
}
function laDragOver(ev) { ev.preventDefault(); ev.currentTarget.classList.add('la-over'); }
function laDragLeave(ev) { ev.currentTarget.classList.remove('la-over'); }
function laDrop(ev, relayId, laneId) {
  ev.preventDefault();
  ev.currentTarget.classList.remove('la-over');
  if (!DRAG) return;
  doAssign(relayId, laneId, DRAG.kind, DRAG.id);
  DRAG = null;
}
function clearLane(relayId, laneId, field) {
  if (!confirm('Remove this ' + field + ' assignment from the lane?')) return;
  doAssign(relayId, laneId, field, 0);
}
async function doAssign(relayId, laneId, field, value) {
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('relay_id', relayId);
  fd.append('lane_id', laneId);
  fd.append('field', field);
  fd.append('value', value);
  const res = await fetch('/lane-allocation/assign', {method:'POST', body:fd});
  const data = await res.json();
  if (!data.success) { laToast(data.message || 'Assignment failed.', 'danger'); return; }
  laToast('Allocation saved.', 'success');
  laLoad();
}

/* ── Admin toggle ── */
async function laToggleUnitAccess(el) {
  const fd = new FormData();
  fd.append('_token', CSRF);
  if (el.checked) fd.append('enabled', '1');
  const res = await fetch('/lane-allocation/toggle-unit-access', {method:'POST', body:fd});
  const data = await res.json();
  if (!data.success) { laToast('Could not update the toggle.', 'danger'); el.checked = !el.checked; return; }
  laToast(el.checked ? 'Unit users can now manage lane allocation.' : 'Unit user access disabled.', 'success');
}

/* ── Small-screen workspace tabs ── */
function wsTab(which) {
  document.getElementById('wsLeft').classList.toggle('d-none', which !== 'left');
  document.getElementById('wsRight').classList.toggle('d-none', which !== 'right');
  document.querySelectorAll('#wsTabs .nav-link').forEach((b,i) =>
    b.classList.toggle('active', (i===0) === (which==='left')));
}
function applyResponsive() {
  if (window.innerWidth >= 992) {
    document.getElementById('wsLeft').classList.remove('d-none');
    document.getElementById('wsRight').classList.remove('d-none');
    document.getElementById('wsRight').classList.add('d-lg-block');
  } else {
    wsTab('left');
  }
}
window.addEventListener('resize', applyResponsive);

document.addEventListener('DOMContentLoaded', () => { applyResponsive(); laLoad(); });
/* Periodic refresh so changes from other users surface in near real time. */
setInterval(() => { if (document.visibilityState === 'visible') laLoad(); }, 20000);
</script>
