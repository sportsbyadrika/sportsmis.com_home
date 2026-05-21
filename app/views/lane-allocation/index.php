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
  <p class="small text-muted mb-2">Per category: <strong>Reg</strong> registered &middot; <strong>Asgn</strong> lanes assigned &middot; <strong>Allot</strong> athletes allotted. Click a cell to filter the workspace.</p>
  <div class="table-responsive" style="max-height:480px">
    <table class="table table-sm table-bordered align-middle mb-0 la-pivot" id="pivotTable">
      <thead class="table-light"></thead>
      <tbody></tbody>
      <tfoot class="table-light"></tfoot>
    </table>
  </div>
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-2">
    <div class="d-flex align-items-center gap-2">
      <label class="small text-muted mb-0">Rows per page</label>
      <select id="pivotPerPage" class="form-select form-select-sm" style="width:auto" onchange="pivotSetPerPage()">
        <option value="10" selected>10</option>
        <option value="25">25</option>
        <option value="50">50</option>
        <option value="all">All</option>
      </select>
    </div>
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-sm btn-outline-secondary" type="button" onclick="pivotPage(-1)">
        <i class="bi bi-chevron-left"></i> Prev
      </button>
      <span class="small text-muted" id="pivotPageInfo">Page 1 of 1</span>
      <button class="btn btn-sm btn-outline-secondary" type="button" onclick="pivotPage(1)">
        Next <i class="bi bi-chevron-right"></i>
      </button>
    </div>
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
        <span class="badge bg-warning text-dark" id="pendingCount">Showing 0 of 0 pending</span>
      </div>
      <button class="btn btn-sm btn-outline-secondary w-100 d-md-none mb-2" type="button"
              data-bs-toggle="collapse" data-bs-target="#pendingFilters">
        <i class="bi bi-funnel me-1"></i>Filters
      </button>
      <div class="collapse d-md-block" id="pendingFilters">
        <div class="row g-2 mb-2">
          <div class="col-6">
            <select id="fpUnit" class="form-select form-select-sm" onchange="renderPending()">
              <option value="">All units</option>
            </select>
          </div>
          <div class="col-6">
            <select id="fpCategory" class="form-select form-select-sm" onchange="renderPending()">
              <option value="">All categories</option>
            </select>
          </div>
          <div class="col-6">
            <input type="text" id="fpComp" class="form-control form-control-sm"
                   placeholder="Competitor #" oninput="renderPending()">
          </div>
          <div class="col-6">
            <input type="text" id="fpName" class="form-control form-control-sm"
                   placeholder="Athlete name" oninput="renderPending()">
          </div>
          <div class="col-12">
            <button class="btn btn-sm btn-outline-secondary w-100" type="button" onclick="clearPendingFilters()">
              <i class="bi bi-x-circle me-1"></i>Clear Filters
            </button>
          </div>
        </div>
      </div>
      <div id="pendingList" style="max-height:520px;overflow:auto"></div>
    </div>
  </div>
</div>

<!-- ── Relay × Lane Allocation Overview (pivot) ──────────────── -->
<div class="sms-card p-3 mb-4">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
    <div>
      <h6 class="fw-semibold mb-0"><i class="bi bi-diagram-3 me-2"></i>Relay &times; Lane Allocation Overview</h6>
      <div class="small text-muted">Pivot view of lane assignments across all relays</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <button class="btn btn-sm btn-outline-secondary" type="button" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Print / PDF
      </button>
      <button class="btn btn-sm btn-outline-success" type="button" onclick="exportRelayPivot()">
        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel
      </button>
    </div>
  </div>

  <!-- Filters -->
  <div class="row g-2 mb-2">
    <div class="col-6 col-md-3">
      <select id="rpRelay" class="form-select form-select-sm" onchange="renderRelayPivot()">
        <option value="">All relays</option>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <select id="rpCategory" class="form-select form-select-sm" onchange="renderRelayPivot()">
        <option value="">All categories</option>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <select id="rpUnit" class="form-select form-select-sm" onchange="renderRelayPivot()">
        <option value="">All units (highlight)</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <select id="rpStatus" class="form-select form-select-sm" onchange="renderRelayPivot()">
        <option value="">All statuses</option>
        <option value="none">Unassigned</option>
        <option value="unit">Unit Assigned</option>
        <option value="full">Fully Allotted</option>
      </select>
    </div>
    <div class="col-6 col-md-1">
      <button class="btn btn-sm btn-outline-secondary w-100" type="button" onclick="clearRelayPivotFilters()">
        <i class="bi bi-x-circle"></i>
      </button>
    </div>
  </div>

  <div class="table-responsive" style="max-height:520px">
    <table class="table table-sm table-bordered align-middle mb-0 la-rpivot" id="relayPivotTable">
      <thead class="table-light"></thead>
      <tbody></tbody>
      <tfoot class="table-light"></tfoot>
    </table>
  </div>
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-2">
    <div class="d-flex align-items-center gap-2">
      <label class="small text-muted mb-0">Rows per page</label>
      <select id="rpPerPage" class="form-select form-select-sm" style="width:auto" onchange="rpSetPerPage()">
        <option value="10" selected>10</option>
        <option value="25">25</option>
        <option value="50">50</option>
        <option value="all">All</option>
      </select>
    </div>
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-sm btn-outline-secondary" type="button" onclick="rpPage(-1)">
        <i class="bi bi-chevron-left"></i> Prev
      </button>
      <span class="small text-muted" id="rpPageInfo">Page 1 of 1</span>
      <button class="btn btn-sm btn-outline-secondary" type="button" onclick="rpPage(1)">
        Next <i class="bi bi-chevron-right"></i>
      </button>
    </div>
  </div>
</div>

<!-- ── Lane Allocation Summary (filtered by Relay) ───────────── -->
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

<!-- Rich hover tooltip for the Relay × Lane pivot -->
<div id="rpTip" class="shadow rounded-3 border bg-white p-2"
     style="position:fixed;display:none;z-index:9998;max-width:300px;font-size:.8rem;pointer-events:none"></div>


<style>
.la-droptarget.la-over { outline:2px dashed #0d6efd; outline-offset:-2px; background:#e7f1ff }
/* Category/unit drag-validation feedback on lane rows */
.la-droptarget.la-valid   { outline:2px solid #198754; outline-offset:-2px; background:#d1e7dd55 }
.la-droptarget.la-invalid { outline:2px solid #dc3545; outline-offset:-2px; background:#f8d7da66 }
.la-droptarget.la-faded   { opacity:.32 }
.la-chip { cursor:grab; user-select:none }
.la-chip:active { cursor:grabbing }
.la-row-allotted { background:#d1e7dd33 }
.la-cell-amber { background:#fff3cd }
.la-cell-green { background:#d1e7dd }
.la-pivot-cell { cursor:pointer }
/* Frozen first column on the pivot table */
.la-pivot .la-frozen {
  position:sticky; left:0; background:#fff; z-index:2; box-shadow:1px 0 0 #dee2e6;
}
.la-pivot thead .la-frozen { z-index:3; background:#f8f9fa }
.la-pivot tfoot .la-frozen { background:#f8f9fa }
/* Relay × Lane pivot */
.la-rpivot .la-frozen {
  position:sticky; left:0; background:#fff; z-index:2; box-shadow:1px 0 0 #dee2e6; min-width:150px;
}
.la-rpivot thead .la-frozen { z-index:3; background:#f8f9fa }
.la-rpivot tfoot .la-frozen { background:#f8f9fa }
.la-rpivot td.rp-cell { text-align:center; cursor:default; min-width:74px }
.la-rpivot td.rp-none  { background:#f1f3f5; color:#adb5bd }
.la-rpivot td.rp-unit  { background:#fff3cd }
.la-rpivot td.rp-full  { background:#d1e7dd }
.la-rpivot td.rp-dim   { opacity:.28 }
.la-rpivot td.rp-hl    { outline:3px solid #0d6efd; outline-offset:-3px; font-weight:700 }
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
  renderRelayPivot();
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
  // Pending-panel filters — units + categories present in the pending list.
  const pUnits = [];
  const seenU = {};
  STATE.pending.forEach(p => {
    if (p.unit_id && !seenU[p.unit_id]) { seenU[p.unit_id] = 1; pUnits.push({id:p.unit_id, name:p.unit_name||('Unit #'+p.unit_id)}); }
  });
  pUnits.sort((a,b) => (a.name||'').localeCompare(b.name||''));
  const fpu = document.getElementById('fpUnit');
  if (fpu) {
    const cur = fpu.value;
    fpu.innerHTML = '<option value="">All units</option>'
      + pUnits.map(u => `<option value="${u.id}">${esc(u.name)}</option>`).join('');
    fpu.value = cur;
  }
  const pCats = [...new Set(STATE.pending.map(p => p.category).filter(Boolean))].sort();
  const fpc = document.getElementById('fpCategory');
  if (fpc) {
    const cur = fpc.value;
    fpc.innerHTML = '<option value="">All categories</option>'
      + pCats.map(c => `<option value="${esc(c)}">${esc(c)}</option>`).join('');
    fpc.value = cur;
  }
  // Relay × Lane pivot filters.
  fill('rpRelay', relays, 'All relays');
  fill('rpCategory', cats, 'All categories');
  const rpu = document.getElementById('rpUnit');
  if (rpu) {
    const cur = rpu.value;
    rpu.innerHTML = '<option value="">All units (highlight)</option>'
      + STATE.units.map(u => `<option value="${u.id}">${esc(u.name)}</option>`).join('');
    rpu.value = cur;
  }
}

/* ── Panel 1 — pivot (3 sub-columns per category, paginated) ── */
let PIVOT = { page: 1, perPage: 10 };

function pivotCellShade(field, val, reg) {
  if (reg <= 0) return '';
  if (val >= reg) return 'la-cell-green';
  return 'la-cell-amber';
}
function unitAddress(unitName) {
  const u = (STATE.units || []).find(x => x.name === unitName);
  return u && u.address ? u.address : '';
}
function unitLogo(unitName) {
  const u = (STATE.units || []).find(x => x.name === unitName);
  return u && u.logo ? u.logo : '';
}
function renderPivot() {
  if (!STATE.pivot) return;
  const cats = STATE.pivot.categories, rows = STATE.pivot.rows;

  // Two-row header: category spans 3, sub-columns Reg/Asgn/Allot.
  const thead = document.querySelector('#pivotTable thead');
  let h1 = '<tr><th rowspan="2" class="la-frozen" style="vertical-align:middle">Unit</th>';
  let h2 = '<tr>';
  cats.forEach(c => {
    h1 += `<th colspan="3" class="text-center">${esc(c)}</th>`;
    h2 += '<th class="text-center small">Reg</th><th class="text-center small">Asgn</th><th class="text-center small">Allot</th>';
  });
  h1 += '<th colspan="3" class="text-center">Total</th></tr>';
  h2 += '<th class="text-center small">Reg</th><th class="text-center small">Asgn</th><th class="text-center small">Allot</th></tr>';
  thead.innerHTML = h1 + h2;

  // Column totals across ALL units (not just the page).
  const colTot = {}; cats.forEach(c => colTot[c] = {reg:0,assigned:0,allotted:0});
  const grand = {reg:0,assigned:0,allotted:0};
  const units = Object.keys(rows);
  units.forEach(unit => {
    cats.forEach(c => {
      const cell = rows[unit][c] || {reg:0,assigned:0,allotted:0};
      ['reg','assigned','allotted'].forEach(k => { colTot[c][k]+=cell[k]; grand[k]+=cell[k]; });
    });
  });

  // Pagination slice.
  const per = PIVOT.perPage === 'all' ? units.length : PIVOT.perPage;
  const pages = Math.max(1, Math.ceil(units.length / (per || 1)));
  if (PIVOT.page > pages) PIVOT.page = pages;
  const start = (PIVOT.page - 1) * per;
  const pageUnits = PIVOT.perPage === 'all' ? units : units.slice(start, start + per);

  const tbody = document.querySelector('#pivotTable tbody');
  let html = '';
  pageUnits.forEach(unit => {
    const rt = {reg:0,assigned:0,allotted:0};
    const addr = unitAddress(unit);
    const logo = unitLogo(unit);
    const logoHtml = logo
      ? `<img src="${esc(logo)}" alt="" width="34" height="34" class="rounded flex-shrink-0"
             style="object-fit:cover;border:1px solid #e2e8f0;background:#fff">`
      : `<div class="rounded flex-shrink-0 d-flex align-items-center justify-content-center bg-light text-muted"
             style="width:34px;height:34px;border:1px solid #e2e8f0"><i class="bi bi-building"></i></div>`;
    html += '<tr><td class="la-frozen">'
      + '<div class="d-flex align-items-center gap-2">' + logoHtml
      + '<div class="min-w-0"><div class="fw-medium small text-truncate">' + esc(unit) + '</div>'
      + (addr ? '<div class="text-muted text-truncate" style="font-size:.72rem">' + esc(addr) + '</div>' : '')
      + '</div></div></td>';
    cats.forEach(c => {
      const cell = rows[unit][c] || {reg:0,assigned:0,allotted:0};
      ['reg','assigned','allotted'].forEach(k => rt[k]+=cell[k]);
      const click = `onclick="pivotFilter('${esc(unit).replace(/'/g,"\\'")}','${esc(c).replace(/'/g,"\\'")}')"`;
      html += `<td class="text-center la-pivot-cell" ${click}>${cell.reg}</td>`
        + `<td class="text-center la-pivot-cell ${pivotCellShade('asgn',cell.assigned,cell.reg)}" ${click}>${cell.assigned}</td>`
        + `<td class="text-center la-pivot-cell ${pivotCellShade('allot',cell.allotted,cell.reg)}" ${click}>${cell.allotted}</td>`;
    });
    html += `<td class="text-center fw-bold">${rt.reg}</td>`
      + `<td class="text-center fw-bold">${rt.assigned}</td>`
      + `<td class="text-center fw-bold">${rt.allotted}</td></tr>`;
  });
  tbody.innerHTML = html || `<tr><td colspan="${cats.length*3+4}" class="text-muted text-center py-3">No data.</td></tr>`;

  // Totals row (always visible — tfoot, never paginated away).
  let tf = '<tr><th class="la-frozen">Total (all units)</th>';
  cats.forEach(c => {
    tf += `<th class="text-center">${colTot[c].reg}</th>`
       +  `<th class="text-center">${colTot[c].assigned}</th>`
       +  `<th class="text-center">${colTot[c].allotted}</th>`;
  });
  tf += `<th class="text-center">${grand.reg}</th><th class="text-center">${grand.assigned}</th>`
     +  `<th class="text-center">${grand.allotted}</th></tr>`;
  document.querySelector('#pivotTable tfoot').innerHTML = tf;

  document.getElementById('pivotPageInfo').textContent = 'Page ' + PIVOT.page + ' of ' + pages;
}
function pivotPage(dir) {
  PIVOT.page += dir;
  if (PIVOT.page < 1) PIVOT.page = 1;
  renderPivot();
}
function pivotSetPerPage() {
  const v = document.getElementById('pivotPerPage').value;
  PIVOT.perPage = v === 'all' ? 'all' : parseInt(v, 10);
  PIVOT.page = 1;
  renderPivot();
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
/* Returns a mismatch warning string for an allotted lane, or '' if fine. */
function laneMismatch(l) {
  if (!l.assigned_registration_id) return '';
  const reasons = [];
  const cats = (l.athlete_categories || '').split(',').map(s => s.trim()).filter(Boolean);
  if (l.category && cats.length && !cats.includes(l.category)) {
    reasons.push('athlete is not registered for the lane category (' + l.category + ')');
  }
  if (l.assigned_unit_id && l.athlete_unit_id
      && Number(l.assigned_unit_id) !== Number(l.athlete_unit_id)) {
    reasons.push('athlete belongs to a different unit than the lane');
  }
  return reasons.join('; ');
}
function renderLanes() {
  const tb = document.getElementById('laneRows');
  const list = laneFiltered();
  if (!list.length) { tb.innerHTML = '<tr><td colspan="7" class="text-muted text-center py-3">No lanes match the filters.</td></tr>'; return; }
  tb.innerHTML = list.map(l => {
    const allotted = !!l.assigned_registration_id;
    const mismatch = laneMismatch(l);
    const warn = mismatch
      ? ` <i class="bi bi-exclamation-triangle-fill text-warning" title="Category/unit mismatch: ${esc(mismatch)}"></i>`
      : '';
    const unitCell = l.assigned_unit_id
      ? `<span class="badge bg-info-subtle text-info-emphasis">${esc(l.unit_name)}</span>`
        + (IS_ADMIN ? ` <button class="btn btn-link btn-sm p-0 text-danger" onclick="clearLane(${l.relay_id},${l.lane_id},'unit')" title="Remove">&times;</button>` : '')
      : '<span class="text-muted small">— drop unit —</span>';
    const athCell = allotted
      ? `<span class="badge bg-success-subtle text-success-emphasis">#${l.competitor_number||'—'} ${esc(l.athlete_name)}</span>${warn}`
        + ` <button class="btn btn-link btn-sm p-0 text-danger" onclick="clearLane(${l.relay_id},${l.lane_id},'athlete')" title="Remove">&times;</button>`
      : '<span class="text-muted small">— drop athlete —</span>';
    return `<tr class="la-droptarget ${allotted?'la-row-allotted':''}"
              data-relay="${l.relay_id}" data-lane="${l.lane_id}"
              data-cat="${esc(l.category||'')}" data-unit="${l.assigned_unit_id||''}"
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
function clearPendingFilters() {
  ['fpUnit','fpCategory'].forEach(id => { const e = document.getElementById(id); if (e) e.value = ''; });
  ['fpComp','fpName'].forEach(id => { const e = document.getElementById(id); if (e) e.value = ''; });
  renderPending();
}
function renderPending() {
  const box  = document.getElementById('pendingList');
  const fUnit = (document.getElementById('fpUnit')     || {}).value || '';
  const fCat  = (document.getElementById('fpCategory') || {}).value || '';
  const fComp = ((document.getElementById('fpComp')    || {}).value || '').trim().toLowerCase();
  const fName = ((document.getElementById('fpName')    || {}).value || '').trim().toLowerCase();

  let list = STATE.pending.filter(p => {
    if (fUnit && String(p.unit_id || '') !== fUnit) return false;
    if (fCat  && (p.category || '') !== fCat) return false;
    if (fComp && !String(p.competitor_number || '').toLowerCase().includes(fComp)) return false;
    if (fName && !(p.athlete_name || '').toLowerCase().includes(fName)) return false;
    return true;
  });

  document.getElementById('pendingCount').textContent =
    'Showing ' + list.length + ' of ' + STATE.pending.length + ' pending';
  if (!list.length) { box.innerHTML = '<div class="text-muted small text-center py-3">No pending athletes match the filters.</div>'; return; }

  // One row per (athlete, category) — each is its own draggable item.
  box.innerHTML = list.map((p, i) => {
    const abbr  = STATE.category_abbr ? STATE.category_abbr[p.category] : '';
    const badge = p.category ? rpCatBadge(p.category, abbr || p.category) : '';
    return `
    <div class="la-chip border rounded-3 p-2 mb-2 d-flex gap-2 align-items-center bg-white"
         draggable="true"
         ondragstart="laDragStart(event,'athlete',${p.registration_id},${i})"
         ondragend="laDragEnd()">
      ${p.passport_photo
        ? `<img src="${esc(p.passport_photo)}" width="38" height="38" class="rounded-circle" style="object-fit:cover">`
        : `<div class="sms-avatar sms-avatar-sm">${esc((p.athlete_name||'?').charAt(0))}</div>`}
      <div class="min-w-0 flex-grow-1">
        <div class="d-flex justify-content-between align-items-start gap-1">
          <div class="fw-medium small text-truncate">${esc(p.athlete_name)}
            ${p.competitor_number ? `<span class="badge bg-secondary-subtle text-secondary">#${p.competitor_number}</span>` : ''}</div>
          ${badge}
        </div>
        <div class="text-muted text-truncate" style="font-size:.72rem">
          ${esc(p.unit_name||'—')} · ${esc(p.events_label||'')}</div>
      </div>
    </div>`;
  }).join('');
  PENDING_VIEW = list;   // index-aligned with the rendered rows
}
let PENDING_VIEW = [];

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
    const mismatch = laneMismatch(l);
    const warn = mismatch
      ? ` <i class="bi bi-exclamation-triangle-fill text-warning" title="Category/unit mismatch: ${esc(mismatch)}"></i>`
      : '';
    return `<tr class="${cls}">
      <td>${esc(l.relay_number)}</td>
      <td class="small">${esc(l.relay_date||'—')} ${esc(l.match_time||'')}</td>
      <td>Lane ${esc(l.lane_number)}</td>
      <td class="small">${esc(l.lane_type||'')}</td>
      <td class="small">${esc(l.category||'—')}</td>
      <td class="small">${l.assigned_unit_id ? '#'+l.assigned_unit_id+' '+esc(l.unit_name) : '—'}</td>
      <td class="small">${l.assigned_registration_id ? '#'+(l.competitor_number||'—')+' '+esc(l.athlete_name)+warn : '—'}</td>
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

/* ── Panel 3 — Relay × Lane pivot ── */
let RP = { page: 1, perPage: 10 };
let RP_CELLS = {};   // "relayId|laneNumber" => relay_lane object (for tooltips)

/* Deterministic, consistent colour per event category for the abbr badge. */
const RP_CAT_PALETTE = ['#0d6efd','#6610f2','#20c997','#6f42c1','#0dcaf0','#d63384','#fd7e14','#198754','#2563eb'];
function catColor(name) {
  let h = 0;
  for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) >>> 0;
  return RP_CAT_PALETTE[h % RP_CAT_PALETTE.length];
}
function rpCatBadge(name, abbr) {
  return `<span class="badge rounded-pill" style="background:${catColor(name)};color:#fff">${esc(abbr)}</span>`;
}

function rpDateTime(l) {
  const parts = [];
  if (l.relay_date) {
    const d = new Date(l.relay_date + 'T00:00:00');
    parts.push(isNaN(d) ? l.relay_date : d.toLocaleDateString('en-IN', {day:'2-digit',month:'short',year:'numeric'}));
  }
  if (l.match_time) parts.push(String(l.match_time).slice(0,5));
  return parts.join(', ');
}
function rpCellStatus(c) {
  if (!c) return 'absent';
  if (c.assigned_registration_id) return 'full';
  if (c.assigned_unit_id) return 'unit';
  return 'none';
}
function renderRelayPivot() {
  const fRelay = document.getElementById('rpRelay').value;
  const fCat   = document.getElementById('rpCategory').value;
  const fUnit  = document.getElementById('rpUnit').value;
  const fStat  = document.getElementById('rpStatus').value;

  // Relays (rows) + lane numbers (columns).
  const relayMap = {};
  STATE.relay_lanes.forEach(l => {
    if (!relayMap[l.relay_id]) {
      relayMap[l.relay_id] = {
        relay_id: l.relay_id, relay_number: l.relay_number,
        order_no: l.order_no, relay_date: l.relay_date, match_time: l.match_time
      };
    }
  });
  // Sort by the relay Order No. (canonical sort key), id as a tiebreaker.
  let relays = Object.values(relayMap).sort((a,b) =>
    ((parseInt(a.order_no,10)||999999) - (parseInt(b.order_no,10)||999999))
    || (a.relay_id - b.relay_id));
  if (fRelay) relays = relays.filter(r => String(r.relay_number) === fRelay);

  const laneNums = [...new Set(STATE.relay_lanes.map(l => parseInt(l.lane_number,10)))]
    .filter(n => !isNaN(n)).sort((a,b) => a-b);

  // Cell index.
  RP_CELLS = {};
  STATE.relay_lanes.forEach(l => {
    RP_CELLS[l.relay_id + '|' + parseInt(l.lane_number,10)] = l;
  });

  // Header.
  document.querySelector('#relayPivotTable thead').innerHTML =
    '<tr><th class="la-frozen">Relay</th>'
    + laneNums.map(n => `<th class="text-center">Lane ${n}</th>`).join('') + '</tr>';

  // Pagination.
  const per = RP.perPage === 'all' ? relays.length : RP.perPage;
  const pages = Math.max(1, Math.ceil(relays.length / (per || 1)));
  if (RP.page > pages) RP.page = pages;
  if (RP.page < 1) RP.page = 1;
  const pageRelays = RP.perPage === 'all'
    ? relays : relays.slice((RP.page-1)*per, (RP.page-1)*per + per);

  // Body.
  let body = '';
  pageRelays.forEach(r => {
    body += `<tr><td class="la-frozen"><div class="fw-medium small">Relay ${esc(r.relay_number)}</div>`
      + `<div class="text-muted" style="font-size:.72rem">${esc(rpDateTime(r)) || '—'}</div></td>`;
    laneNums.forEach(n => {
      const c = RP_CELLS[r.relay_id + '|' + n];
      // Category filter blanks out non-matching lanes.
      const catOk = !fCat || (c && (c.category||'') === fCat);
      if (!c || !catOk) { body += '<td class="rp-cell rp-none">—</td>'; return; }
      const st = rpCellStatus(c);
      const shade = st === 'full' ? 'rp-full' : (st === 'unit' ? 'rp-unit' : 'rp-none');
      // Unit / status filters dim or highlight.
      let dim = false, hl = false;
      if (fUnit && String(c.assigned_unit_id||'') !== fUnit) dim = true;
      if (fUnit && String(c.assigned_unit_id||'') === fUnit) hl = true;
      if (fStat && st !== fStat) dim = true;
      const cls = ['rp-cell', shade, dim?'rp-dim':'', hl?'rp-hl':''].filter(Boolean).join(' ');
      const abbr = STATE.category_abbr ? STATE.category_abbr[c.category] : '';
      // Compact avatar for the allotted athlete — passport photo when
      // present, otherwise a circular initial-letter fallback so the
      // row height stays uniform across the pivot.
      const athletePhoto = (c.assigned_registration_id && c.passport_photo)
        ? `<img src="${esc(c.passport_photo)}" alt=""
             style="width:26px;height:26px;border-radius:50%;object-fit:cover;border:1px solid #cbd5e1;display:block;margin:0 auto 2px">`
        : (c.assigned_registration_id
            ? `<div style="width:26px;height:26px;border-radius:50%;background:#e2e8f0;color:#475569;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.72rem;margin:0 auto 2px">${esc((c.athlete_name||'?').charAt(0).toUpperCase())}</div>`
            : '');
      let inner = '—';
      if (c.assigned_unit_id) {
        inner = athletePhoto
          + '<span>' + esc(c.unit_name || ('#' + c.assigned_unit_id)) + '</span>'
          + (abbr ? ' ' + rpCatBadge(c.category, abbr) : '');
      } else if (c.category) {
        // Unassigned lane that still has a configured category — badge only.
        inner = rpCatBadge(c.category, abbr || c.category);
      }
      body += `<td class="${cls}" data-rk="${r.relay_id}|${n}"`
        + ` onmouseenter="rpTip(event,this)" onmousemove="rpTipMove(event)" onmouseleave="rpTipHide()">`
        + inner + '</td>';
    });
    body += '</tr>';
  });
  document.querySelector('#relayPivotTable tbody').innerHTML =
    body || `<tr><td class="la-frozen">—</td>${laneNums.map(()=>'<td class="rp-cell rp-none">—</td>').join('')}</tr>`;

  // Footer — per-lane totals across ALL filtered relays (every page).
  let foot = '<tr><th class="la-frozen">Lane Totals</th>';
  laneNums.forEach(n => {
    let used=0, unit=0, full=0;
    relays.forEach(r => {
      const c = RP_CELLS[r.relay_id + '|' + n];
      if (!c) return;
      if (fCat && (c.category||'') !== fCat) return;
      used++;
      if (c.assigned_unit_id) unit++;
      if (c.assigned_registration_id) full++;
    });
    foot += `<th class="text-center small">Relays: ${used}<br>Unit: ${unit} · Full: ${full}</th>`;
  });
  foot += '</tr>';
  document.querySelector('#relayPivotTable tfoot').innerHTML = foot;

  document.getElementById('rpPageInfo').textContent = 'Page ' + RP.page + ' of ' + pages;
}
function rpPage(d) { RP.page += d; renderRelayPivot(); }
function rpSetPerPage() {
  const v = document.getElementById('rpPerPage').value;
  RP.perPage = v === 'all' ? 'all' : parseInt(v,10);
  RP.page = 1;
  renderRelayPivot();
}
function clearRelayPivotFilters() {
  ['rpRelay','rpCategory','rpUnit','rpStatus'].forEach(id => {
    const e = document.getElementById(id); if (e) e.value = '';
  });
  RP.page = 1;
  renderRelayPivot();
}

/* Rich hover tooltip */
function rpTip(ev, td) {
  const c = RP_CELLS[td.dataset.rk];
  if (!c) return;
  // No tooltip for cells with neither a unit nor a configured category.
  if (!c.assigned_unit_id && !c.category) return;
  const tip = document.getElementById('rpTip');
  const abbr = STATE.category_abbr ? STATE.category_abbr[c.category] : '';

  // Unassigned lane that carries a category — minimal tooltip only.
  if (!c.assigned_unit_id) {
    tip.innerHTML = '<div class="fw-bold border-bottom pb-1 mb-1">Lane Details</div>'
      + `<div>Lane ${esc(c.lane_number)}</div>`
      + `<div>Category: ${esc(c.category||'—')}${abbr ? ' (' + esc(abbr) + ')' : ''}</div>`
      + '<div class="text-muted fst-italic mt-1">No unit assigned yet</div>';
    tip.style.display = 'block';
    rpTipMove(ev);
    return;
  }

  let html = '<div class="fw-bold border-bottom pb-1 mb-1">Unit Details</div>'
    + `<div>Unit: <strong>${esc(c.unit_name||'')}</strong></div>`
    + `<div>Address: ${esc(c.unit_address||'—')}</div>`
    + `<div>Relay ${esc(c.relay_number)} · ${esc(rpDateTime(c))||'—'}</div>`
    + `<div>Lane ${esc(c.lane_number)}</div>`
    + `<div>Category: ${esc(c.category||'—')}</div>`;
  if (c.assigned_registration_id) {
    html += '<div class="fw-bold border-bottom pb-1 mb-1 mt-2">Athlete Details</div>'
      + '<div class="d-flex gap-2 align-items-start">'
      + (c.passport_photo
          ? `<img src="${esc(c.passport_photo)}" width="48" height="48" class="rounded" style="object-fit:cover">`
          : '<div class="sms-avatar sms-avatar-sm">'+esc((c.athlete_name||'?').charAt(0))+'</div>')
      + '<div class="min-w-0">'
      + `<div class="fw-medium">${esc(c.athlete_name||'')}</div>`
      + `<div>Competitor #${esc(c.competitor_number||'—')}</div>`
      + `<div class="text-muted">${esc(c.events_label||'')}</div>`
      + '</div></div>';
  } else {
    html += '<div class="text-muted fst-italic mt-2">No athlete allotted yet</div>';
  }
  tip.innerHTML = html;
  tip.style.display = 'block';
  rpTipMove(ev);
}
function rpTipMove(ev) {
  const tip = document.getElementById('rpTip');
  if (tip.style.display !== 'block') return;
  let x = ev.clientX + 14, y = ev.clientY + 14;
  const w = tip.offsetWidth, h = tip.offsetHeight;
  if (x + w > window.innerWidth)  x = ev.clientX - w - 14;
  if (y + h > window.innerHeight) y = ev.clientY - h - 14;
  tip.style.left = Math.max(4,x) + 'px';
  tip.style.top  = Math.max(4,y) + 'px';
}
function rpTipHide() { document.getElementById('rpTip').style.display = 'none'; }

function exportRelayPivot() {
  const table = document.getElementById('relayPivotTable');
  const rowsOut = [];
  table.querySelectorAll('tr').forEach(tr => {
    const cells = [...tr.querySelectorAll('th,td')].map(td =>
      td.innerText.replace(/\s+/g,' ').trim());
    rowsOut.push(cells);
  });
  const csv = rowsOut.map(r => r.map(c => '"'+String(c).replace(/"/g,'""')+'"').join(',')).join('\n');
  const blob = new Blob([csv], {type:'application/vnd.ms-excel'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'relay-lane-pivot.xls';
  a.click();
}

/* ── Drag & drop ── */
let DRAG = null;
function laDragStart(ev, kind, id, pendingIdx) {
  DRAG = { kind, id };
  if (kind === 'athlete' && pendingIdx != null && PENDING_VIEW[pendingIdx]) {
    const p = PENDING_VIEW[pendingIdx];
    DRAG.category = p.category || '';
    DRAG.unitId   = Number(p.unit_id || 0);
    laHighlightLanes();
  }
  ev.dataTransfer.effectAllowed = 'move';
  ev.dataTransfer.setData('text/plain', kind + ':' + id);
}
function laDragEnd() { laClearLaneHighlight(); DRAG = null; }

/* Can the current athlete drag legally drop on this lane row? */
function laneRowValid(tr) {
  if (!DRAG || DRAG.kind !== 'athlete') return true;   // unit drags: any lane
  const cat  = tr.dataset.cat  || '';
  const unit = tr.dataset.unit || '';
  if (!cat) return false;                              // lane has no category
  if (cat !== (DRAG.category || '')) return false;     // category mismatch
  if (unit && DRAG.unitId && Number(unit) !== DRAG.unitId) return false; // unit mismatch
  return true;
}
/* During an athlete drag: glow valid lanes, dim the rest. */
function laHighlightLanes() {
  document.querySelectorAll('#laneRows tr.la-droptarget').forEach(tr => {
    tr.classList.remove('la-valid','la-faded','la-invalid','la-over');
    tr.classList.add(laneRowValid(tr) ? 'la-valid' : 'la-faded');
  });
}
function laClearLaneHighlight() {
  document.querySelectorAll('#laneRows tr.la-droptarget').forEach(tr =>
    tr.classList.remove('la-valid','la-faded','la-invalid','la-over'));
}
function laDragOver(ev) {
  ev.preventDefault();
  const tr = ev.currentTarget;
  if (DRAG && DRAG.kind === 'athlete') {
    tr.classList.remove('la-faded','la-valid');
    tr.classList.add(laneRowValid(tr) ? 'la-valid' : 'la-invalid');
  } else {
    tr.classList.add('la-over');
  }
}
function laDragLeave(ev) {
  const tr = ev.currentTarget;
  tr.classList.remove('la-over','la-invalid','la-valid');
  if (DRAG && DRAG.kind === 'athlete') {
    tr.classList.add(laneRowValid(tr) ? 'la-valid' : 'la-faded');
  }
}
function laDrop(ev, relayId, laneId) {
  ev.preventDefault();
  const tr = ev.currentTarget;
  tr.classList.remove('la-over','la-invalid');
  if (!DRAG) return;
  if (DRAG.kind === 'athlete' && !laneRowValid(tr)) {
    const cat = tr.dataset.cat || '';
    if (!cat) {
      laToast('⚠ Cannot allocate: this lane has no Event Category configured.', 'danger');
    } else if (cat !== (DRAG.category || '')) {
      laToast('⚠ Cannot allocate: athlete is registered for ' + (DRAG.category || '—')
        + ' but this lane is configured for ' + cat + '.', 'danger');
    } else {
      laToast('⚠ Cannot allocate: athlete belongs to a different unit than this lane.', 'danger');
    }
    laClearLaneHighlight();
    DRAG = null;
    return;
  }
  doAssign(relayId, laneId, DRAG.kind, DRAG.id);
  laClearLaneHighlight();
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
