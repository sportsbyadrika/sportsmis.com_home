<?php
$pageTitle = 'Events — ' . ($category['name'] ?? '');
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/admin/settings/sports/<?= (int)$category['sport_id'] ?>/categories" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Categories
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-trophy me-2"></i>Sport Events</h5>
  <span class="text-muted small ms-2">
    — <?= e($category['sport_name']) ?> &middot; <?= e($category['name']) ?>
  </span>
  <button type="button" class="btn btn-sm btn-outline-primary ms-auto" onclick="openBulkModal()">
    <i class="bi bi-grid-3x3-gap me-1"></i>Bulk Add
  </button>
  <button type="button" class="btn btn-sm btn-primary" onclick="openEvtModal()">
    <i class="bi bi-plus-circle me-1"></i>Add Event
  </button>
</div>

<?= flashBag() ?>

<div class="sms-card p-3">
  <?php if (empty($sport_events)): ?>
    <p class="small text-muted text-center mb-0 py-3">No events yet — click <strong>Add Event</strong> to create one.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:50px">#</th>
          <th>Name</th>
          <th>Age Category</th>
          <th style="width:80px">Gender</th>
          <th style="width:90px">Weight</th>
          <th style="width:90px">Height</th>
          <th style="width:70px" class="text-center">Para</th>
          <th class="text-end" style="width:130px">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sport_events as $i => $se): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td class="fw-medium"><?= e($se['name']) ?>
              <?php if (trim((string)($se['event_label'] ?? '')) !== ''): ?>
                <div class="small text-muted"><i class="bi bi-award me-1"></i><?= e($se['event_label']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= e($se['age_category_name'] ?? '—') ?></td>
            <td><?= e(ucfirst((string)$se['gender'])) ?></td>
            <td><?= e($se['weight'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
            <td><?= e($se['height'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
            <td class="text-center">
              <?= !empty($se['para']) ? '<i class="bi bi-check-lg text-success"></i>' : '<span class="text-muted">—</span>' ?>
            </td>
            <td class="text-end">
              <div class="d-inline-flex gap-1">
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        onclick='editEvt(<?= json_encode($se, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'
                        title="Edit">
                  <i class="bi bi-pencil"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger"
                        onclick="deleteEvt(<?= (int)$se['id'] ?>, '<?= e($se['name']) ?>')"
                        title="Delete">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ── Add / Edit modal ─────────────────────────────────────────── -->
<div class="modal fade" id="evtModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form class="modal-content" id="evtForm" onsubmit="return saveEvt(event)">
      <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>">
      <input type="hidden" name="id" id="evt_id" value="">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-trophy me-2"></i><span id="evtModalTitle">Add Sport Event</span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-3">
          Leave the Name blank to auto-generate it from category + age category + gender (+ weight / para).
        </p>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small mb-1">Name</label>
            <input type="text" name="name" id="evt_name" class="form-control form-control-sm" placeholder="(auto-generated if blank)">
          </div>
          <div class="col-md-6">
            <label class="form-label small mb-1">Certificate Label <span class="text-muted">(event name on certificates)</span></label>
            <input type="text" name="event_label" id="evt_label" class="form-control form-control-sm" maxlength="255" placeholder="(falls back to Name if blank)">
          </div>
          <div class="col-md-5">
            <label class="form-label small mb-1">Age Category <span class="text-danger">*</span></label>
            <select name="age_category_id" id="evt_age" class="form-select form-select-sm" required>
              <option value="">— Select —</option>
              <?php foreach ($age_categories as $ac): ?>
                <option value="<?= (int)$ac['id'] ?>"><?= e($ac['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">Gender <span class="text-danger">*</span></label>
            <select name="gender" id="evt_gender" class="form-select form-select-sm" required>
              <option value="">— Select —</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="mixed">Mixed</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-1">Weight</label>
            <input type="text" name="weight" id="evt_weight" class="form-control form-control-sm" maxlength="40">
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-1">Height</label>
            <input type="text" name="height" id="evt_height" class="form-control form-control-sm" maxlength="40">
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="evt_para" name="para" value="1">
              <label class="form-check-label" for="evt_para">Para event</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Bulk Add modal ───────────────────────────────────────────── -->
<div class="modal fade" id="bulkModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-grid-3x3-gap me-2"></i>Bulk Add Events</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label small mb-1">Event</label>
            <input type="text" class="form-control form-control-sm" value="<?= e($category['name']) ?>" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label small mb-1">Age Set</label>
            <select id="bulkSet" class="form-select form-select-sm" onchange="bulkRenderTable()">
              <?php foreach (($age_sets ?? ['master']) as $s): ?>
                <option value="<?= e($s) ?>"><?= e(\Models\AgeCategory::setLabel($s)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <p class="small text-muted mb-2">
          Tick each Age Category × Gender you want to add. Combinations already created are pre-ticked and
          disabled so they aren&rsquo;t duplicated. Click <strong>Preview</strong> to review, then <strong>Save</strong>.
        </p>
        <div id="bulkTableWrap" class="table-responsive"></div>
        <div id="bulkPreviewBox" class="small mt-3"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="bulkPreview()"><i class="bi bi-eye me-1"></i>Preview</button>
        <button type="button" class="btn btn-sm btn-primary" id="bulkSaveBtn" onclick="bulkSave()" disabled><i class="bi bi-save me-1"></i>Save</button>
      </div>
    </div>
  </div>
</div>

<script>
const CSRF = '<?= e($csrfToken) ?>';
const CATEGORY_ID = <?= (int)$category['id'] ?>;
const EVENT_NAME  = <?= json_encode((string)$category['name']) ?>;
const AGE_CATS = <?= json_encode(array_map(fn($a) => [
    'id'         => (int)$a['id'],
    'name'       => (string)$a['name'],
    'set_code'   => (string)($a['set_code'] ?? 'master'),
    'status'     => (string)($a['status'] ?? 'active'),
    'sort_order' => (int)($a['sort_order'] ?? 0),
  ], $age_categories), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
const EXISTING = <?= json_encode(array_fill_keys(array_map(
    fn($se) => (int)$se['age_category_id'] . ':' . (string)$se['gender'],
    array_filter($sport_events, fn($se) => !empty($se['age_category_id']))
  ), true), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
const BULK_GENDERS = [['male','Male'],['female','Female'],['mixed','Mixed']];

function bulkEsc(s){ return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function bulkGenderName(g){ return g==='male'?'Men':(g==='female'?'Women':'Mixed'); }

let bulkModalInst = null;
function openBulkModal(){
  if (!bulkModalInst) bulkModalInst = bootstrap.Modal.getOrCreateInstance(document.getElementById('bulkModal'));
  bulkRenderTable();
  bulkModalInst.show();
}
function bulkRenderTable(){
  const set = document.getElementById('bulkSet').value;
  const cats = AGE_CATS
    .filter(c => c.status === 'active' && c.set_code === set)
    .sort((a,b) => (a.sort_order - b.sort_order) || a.name.localeCompare(b.name));
  const wrap = document.getElementById('bulkTableWrap');
  document.getElementById('bulkPreviewBox').innerHTML = '';
  document.getElementById('bulkSaveBtn').disabled = true;
  if (!cats.length){
    wrap.innerHTML = '<div class="text-muted small py-3 text-center">No active age categories in this set. Add them under Settings → Age Categories.</div>';
    return;
  }
  let h = '<table class="table table-sm table-bordered align-middle mb-0"><thead class="table-light"><tr><th>Age Category</th>';
  BULK_GENDERS.forEach(g => h += '<th class="text-center" style="width:110px">'+g[1]+'</th>');
  h += '</tr></thead><tbody>';
  cats.forEach(c => {
    h += '<tr><td class="fw-medium">'+bulkEsc(c.name)+'</td>';
    BULK_GENDERS.forEach(g => {
      const key = c.id+':'+g[0], exists = !!EXISTING[key];
      h += '<td class="text-center">'
        + '<input type="checkbox" class="form-check-input bulk-cb" data-key="'+key+'" data-cat="'+bulkEsc(c.name)+'" data-gender="'+g[0]+'"'
        + (exists ? ' checked disabled' : '') + '>'
        + (exists ? '<div class="small text-success" style="font-size:.7rem">added</div>' : '')
        + '</td>';
    });
    h += '</tr>';
  });
  h += '</tbody></table>';
  wrap.innerHTML = h;
  wrap.querySelectorAll('.bulk-cb').forEach(cb => cb.addEventListener('change', () => {
    document.getElementById('bulkPreviewBox').innerHTML = '';
    document.getElementById('bulkSaveBtn').disabled = true;
  }));
}
function bulkSelected(){
  return Array.from(document.querySelectorAll('#bulkTableWrap .bulk-cb')).filter(cb => cb.checked && !cb.disabled);
}
function bulkPreview(){
  const sel = bulkSelected();
  const box = document.getElementById('bulkPreviewBox');
  if (!sel.length){
    box.innerHTML = '<span class="text-muted">Nothing new selected to add.</span>';
    document.getElementById('bulkSaveBtn').disabled = true;
    return;
  }
  let h = '<div class="fw-semibold mb-1">'+sel.length+' event(s) will be added:</div><ul class="mb-0 ps-3">';
  sel.forEach(cb => h += '<li>'+bulkEsc(EVENT_NAME+' '+cb.dataset.cat+' '+bulkGenderName(cb.dataset.gender))+'</li>');
  h += '</ul>';
  box.innerHTML = h;
  document.getElementById('bulkSaveBtn').disabled = false;
}
async function bulkSave(){
  const sel = bulkSelected();
  if (!sel.length) return;
  const btn = document.getElementById('bulkSaveBtn');
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('category_id', CATEGORY_ID);
  sel.forEach(cb => fd.append('combos[]', cb.dataset.key));
  try {
    const res = await fetch('/admin/settings/sport-events/bulk-save', { method:'POST', body: fd });
    const d = await res.json();
    if (!d.success){ alert(d.message || 'Save failed.'); btn.disabled = false; btn.innerHTML = '<i class="bi bi-save me-1"></i>Save'; return; }
    window.location.reload();
  } catch (e) {
    alert('Network error: ' + e.message);
    btn.disabled = false; btn.innerHTML = '<i class="bi bi-save me-1"></i>Save';
  }
}

let evtModalInst = null;
document.addEventListener('DOMContentLoaded', () => {
  evtModalInst = bootstrap.Modal.getOrCreateInstance(document.getElementById('evtModal'));
});

function openEvtModal() {
  document.getElementById('evtForm').reset();
  document.getElementById('evt_id').value = '';
  document.getElementById('evtModalTitle').textContent = 'Add Sport Event';
  evtModalInst.show();
}
function editEvt(s) {
  const $ = id => document.getElementById(id);
  $('evt_id').value     = s.id;
  $('evt_name').value   = s.name || '';
  $('evt_label').value  = s.event_label || '';
  $('evt_age').value    = s.age_category_id || '';
  $('evt_gender').value = s.gender || '';
  $('evt_weight').value = s.weight || '';
  $('evt_height').value = s.height || '';
  $('evt_para').checked = !!Number(s.para);
  document.getElementById('evtModalTitle').textContent = 'Edit Sport Event';
  evtModalInst.show();
}
async function saveEvt(ev) {
  ev.preventDefault();
  const fd  = new FormData(document.getElementById('evtForm'));
  const res = await fetch('/admin/settings/sport-events/save', { method: 'POST', body: fd });
  const d   = await res.json();
  if (!d.success) { alert(d.message || 'Save failed.'); return false; }
  evtModalInst.hide();
  window.location.reload();
  return false;
}
async function deleteEvt(id, name) {
  if (!confirm('Delete sport event "' + name + '"?')) return;
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('id', id);
  const res = await fetch('/admin/settings/sport-events/delete', { method: 'POST', body: fd });
  const d   = await res.json();
  if (!d.success) { alert(d.message || 'Delete failed.'); return; }
  window.location.reload();
}
</script>
