<?php
$pageTitle = 'NOC Management';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$nocBadge = [
  'accepted' => ['Accepted', 'bg-success'],
  'rejected' => ['Rejected', 'bg-danger'],
  'pending'  => ['Pending',  'bg-warning text-dark'],
];
?>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="nocToast" class="toast align-items-center border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body fw-medium" id="nocToastMsg"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-check me-2"></i>NOC Management</h5>
    <div class="text-muted small mt-1">
      Event: <strong><?= e($event['name']) ?></strong> · Code: <code><?= e($event['event_code'] ?? '') ?></code>
    </div>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <?php if (!empty($units) && count($units) > 1): ?>
      <form method="GET" action="/unit/noc" class="d-flex align-items-center gap-2">
        <label class="form-label mb-0 small text-muted">Unit:</label>
        <select name="unit_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:220px">
          <?php foreach ($units as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= $active_unit && (int)$active_unit['id'] === (int)$u['id'] ? 'selected' : '' ?>>
              <?= e($u['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php endif; ?>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="nocPrint()">
      <i class="bi bi-printer me-1"></i>Print
    </button>
  </div>
</div>

<?php if (empty($active_unit)): ?>
  <div class="sms-empty-state">
    <i class="bi bi-building"></i>
    <h5>No Unit Assigned</h5>
    <p>No units are assigned to your account yet. Please contact the event organiser.</p>
  </div>
<?php else: ?>

<!-- Filters -->
<div class="sms-card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-6 col-md-3">
      <label class="form-label small mb-1">NOC Status</label>
      <select id="fNocStatus" class="form-select form-select-sm" onchange="renderNoc()">
        <option value="">All</option>
        <option value="accepted">Accepted</option>
        <option value="rejected">Rejected</option>
        <option value="pending">Pending</option>
      </select>
    </div>
    <div class="col-6 col-md-4">
      <label class="form-label small mb-1">Athlete Name</label>
      <input type="text" id="fNocName" class="form-control form-control-sm"
             placeholder="Search name…" oninput="renderNoc()">
    </div>
    <div class="col-6 col-md-2">
      <button type="button" class="btn btn-sm btn-outline-secondary w-100" onclick="clearNocFilters()">
        <i class="bi bi-x-circle me-1"></i>Clear
      </button>
    </div>
    <div class="col-6 col-md-3 text-md-end">
      <span class="small text-muted" id="nocCount"></span>
    </div>
  </div>
</div>

<?php if (empty($athletes)): ?>
  <div class="sms-empty-state">
    <i class="bi bi-people"></i>
    <h5>No Approved Athletes</h5>
    <p>There are no approved athletes under this unit yet.</p>
  </div>
<?php else: ?>

<!-- Desktop table -->
<div class="sms-card d-none d-md-block">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Athlete</th>
          <th>Competitor No.</th>
          <th>Event(s) Registered</th>
          <th>Unit</th>
          <th>NOC Status</th>
          <th class="text-end">NOC Action</th>
        </tr>
      </thead>
      <tbody id="nocRows"></tbody>
    </table>
  </div>
</div>

<!-- Mobile cards -->
<div class="d-md-none" id="nocCards"></div>

<script>
const CSRF = '<?= e($csrfToken) ?>';
const ACTIVE_UNIT = <?= (int)($active_unit['id'] ?? 0) ?>;
const NOC_BADGE = { accepted:['Accepted','bg-success'], rejected:['Rejected','bg-danger'], pending:['Pending','bg-warning text-dark'] };
const ATHLETES = <?= json_encode(array_map(fn($a) => [
  'registration_id'   => (int)$a['registration_id'],
  'competitor_number' => $a['competitor_number'] ? (int)$a['competitor_number'] : null,
  'athlete_name'      => $a['athlete_name'],
  'gender'            => $a['gender'],
  'mobile'            => $a['mobile'],
  'passport_photo'    => $a['passport_photo'],
  'events_label'      => $a['events_label'],
  'unit_name'         => $a['unit_name'],
  'noc_status'        => $a['noc_status'] ?: 'pending',
  'noc_status_at'     => $a['noc_status_at'],
  'noc_status_by'     => $a['noc_status_by'],
], $athletes), JSON_UNESCAPED_UNICODE) ?>;

function nocToast(msg, type) {
  const el = document.getElementById('nocToast');
  el.className = 'toast align-items-center border-0 text-bg-' + (type || 'success');
  document.getElementById('nocToastMsg').textContent = msg;
  if (window.bootstrap && bootstrap.Toast) bootstrap.Toast.getOrCreateInstance(el, {delay:2500}).show();
}
function esc(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g, c =>
    ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function nocBadge(st) {
  const b = NOC_BADGE[st] || NOC_BADGE.pending;
  return `<span class="badge ${b[1]}" data-noc-badge>${b[0]}</span>`;
}
function nocActions(rid, st) {
  const btn = (s, cls, icon, label) =>
    `<button type="button" class="btn btn-sm ${st===s?cls:'btn-outline-'+cls.replace('btn-','')}"
       onclick="setNoc(${rid},'${s}')" title="${label}"><i class="bi ${icon}"></i></button>`;
  return '<div class="btn-group btn-group-sm">'
    + btn('accepted','btn-success','bi-check-lg','Accept')
    + btn('rejected','btn-danger','bi-x-lg','Reject')
    + btn('pending','btn-warning','bi-clock-history','Pending')
    + '</div>';
}

function filtered() {
  const fs = document.getElementById('fNocStatus').value;
  const fn = (document.getElementById('fNocName').value || '').trim().toLowerCase();
  return ATHLETES.filter(a => {
    if (fs && a.noc_status !== fs) return false;
    if (fn && !(a.athlete_name || '').toLowerCase().includes(fn)) return false;
    return true;
  });
}
function renderNoc() {
  const list = filtered();
  document.getElementById('nocCount').textContent =
    'Showing ' + list.length + ' of ' + ATHLETES.length + ' athletes';

  document.getElementById('nocRows').innerHTML = list.map(a => `
    <tr data-rid="${a.registration_id}">
      <td>
        <div class="d-flex align-items-center gap-2">
          ${a.passport_photo
            ? `<img src="${esc(a.passport_photo)}" width="38" height="38" class="rounded-circle" style="object-fit:cover">`
            : `<div class="sms-avatar sms-avatar-sm">${esc((a.athlete_name||'?').charAt(0))}</div>`}
          <div>
            <div class="fw-medium">${esc(a.athlete_name)}</div>
            <small class="text-muted">${esc((a.gender||'').replace(/^./,c=>c.toUpperCase()))}
              ${a.mobile ? '· ' + esc(a.mobile) : ''}</small>
          </div>
        </div>
      </td>
      <td>${a.competitor_number ? '<code>#'+a.competitor_number+'</code>' : '<span class="text-muted">—</span>'}</td>
      <td class="small">${esc(a.events_label || '—')}</td>
      <td class="small">${esc(a.unit_name || '—')}</td>
      <td data-noc-cell>${nocBadge(a.noc_status)}</td>
      <td class="text-end" data-noc-act>${nocActions(a.registration_id, a.noc_status)}</td>
    </tr>`).join('')
    || '<tr><td colspan="6" class="text-muted text-center py-4">No athletes match the filters.</td></tr>';

  document.getElementById('nocCards').innerHTML = list.map(a => `
    <div class="sms-card p-3 mb-2" data-rid="${a.registration_id}">
      <div class="d-flex gap-2 align-items-start">
        ${a.passport_photo
          ? `<img src="${esc(a.passport_photo)}" width="44" height="44" class="rounded-circle flex-shrink-0" style="object-fit:cover">`
          : `<div class="sms-avatar flex-shrink-0">${esc((a.athlete_name||'?').charAt(0))}</div>`}
        <div class="flex-grow-1 min-w-0">
          <div class="fw-medium text-break">${esc(a.athlete_name)}</div>
          <div class="small text-muted">${esc((a.gender||'').replace(/^./,c=>c.toUpperCase()))}
            ${a.mobile ? '· ' + esc(a.mobile) : ''}
            ${a.competitor_number ? '· #'+a.competitor_number : ''}</div>
          <div class="small text-muted">${esc(a.events_label || '—')}</div>
          <div class="mt-1" data-noc-cell>${nocBadge(a.noc_status)}</div>
        </div>
      </div>
      <div class="mt-2 text-end" data-noc-act>${nocActions(a.registration_id, a.noc_status)}</div>
    </div>`).join('')
    || '<div class="text-muted text-center py-3">No athletes match the filters.</div>';
}
function clearNocFilters() {
  document.getElementById('fNocStatus').value = '';
  document.getElementById('fNocName').value   = '';
  renderNoc();
}

async function setNoc(rid, status) {
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('registration_id', rid);
  fd.append('status', status);
  try {
    const res  = await fetch('/unit/noc/set', { method:'POST', body: fd });
    const data = await res.json();
    if (!data.success) { nocToast(data.message || 'Update failed.', 'danger'); return; }
    const a = ATHLETES.find(x => x.registration_id === rid);
    if (a) { a.noc_status = status; a.noc_status_at = data.at; a.noc_status_by = data.by; }
    // Refresh just this row's badge + actions in both layouts.
    document.querySelectorAll('[data-rid="'+rid+'"]').forEach(row => {
      const cell = row.querySelector('[data-noc-cell]');
      const act  = row.querySelector('[data-noc-act]');
      if (cell) cell.innerHTML = nocBadge(status);
      if (act)  act.innerHTML  = nocActions(rid, status);
    });
    nocToast('NOC marked ' + status + ' ✓', 'success');
  } catch (e) {
    nocToast('Network error — could not save. Please retry.', 'danger');
  }
}

function nocPrint() {
  const params = new URLSearchParams();
  params.set('unit_id', ACTIVE_UNIT);
  const fs = document.getElementById('fNocStatus').value;
  const fn = document.getElementById('fNocName').value.trim();
  if (fs) params.set('status', fs);
  if (fn) params.set('name', fn);
  window.open('/unit/noc/print?' + params.toString(), '_blank');
}

document.addEventListener('DOMContentLoaded', renderNoc);
</script>
<?php endif; ?>
<?php endif; ?>
