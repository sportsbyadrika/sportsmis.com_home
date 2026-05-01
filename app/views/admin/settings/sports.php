<?php
$pageTitle = 'Sports Settings';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>

<!-- Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="settingsToast" class="toast align-items-center border-0" role="alert" aria-live="assertive">
    <div class="d-flex">
      <div class="toast-body fw-medium" id="toastMsg"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold"><i class="bi bi-gear me-2"></i>Sports Settings</h5>
</div>

<div class="row g-4">

  <!-- ── Age Categories ──────────────────────────────────────────────────── -->
  <div class="col-lg-5">
    <div class="sms-card p-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-calendar-event me-2"></i>Age Categories</h6>
      </div>

      <div class="table-responsive">
        <table class="table table-sm align-middle mb-3">
          <thead class="table-light">
            <tr>
              <th>Name</th>
              <th class="text-end">Min</th>
              <th class="text-end">Max</th>
              <th class="text-end">Order</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="ageCatRows">
            <?php foreach ($age_categories as $a): ?>
              <tr data-id="<?= (int)$a['id'] ?>">
                <td><input class="form-control form-control-sm" data-field="name" value="<?= e($a['name']) ?>"></td>
                <td><input class="form-control form-control-sm text-end" data-field="min_age" type="number" min="0" value="<?= e($a['min_age'] ?? '') ?>"></td>
                <td><input class="form-control form-control-sm text-end" data-field="max_age" type="number" min="0" value="<?= e($a['max_age'] ?? '') ?>"></td>
                <td><input class="form-control form-control-sm text-end" data-field="sort_order" type="number" value="<?= (int)$a['sort_order'] ?>" style="width:70px"></td>
                <td class="text-end">
                  <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="ageCatSave(this)"><i class="bi bi-save"></i></button>
                  <button type="button" class="btn btn-sm btn-outline-danger"  onclick="ageCatDelete(this)"><i class="bi bi-trash"></i></button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="border-top pt-3">
        <div class="row g-2 align-items-end">
          <div class="col-5"><label class="form-label small mb-1">Name</label>
            <input id="newAgeCatName" class="form-control form-control-sm" placeholder="e.g. Youth"></div>
          <div class="col-2"><label class="form-label small mb-1">Min</label>
            <input id="newAgeCatMin" class="form-control form-control-sm" type="number" min="0"></div>
          <div class="col-2"><label class="form-label small mb-1">Max</label>
            <input id="newAgeCatMax" class="form-control form-control-sm" type="number" min="0"></div>
          <div class="col-2"><label class="form-label small mb-1">Order</label>
            <input id="newAgeCatSort" class="form-control form-control-sm" type="number" value="0"></div>
          <div class="col-1"><button class="btn btn-sm btn-primary w-100" onclick="ageCatAdd()"><i class="bi bi-plus"></i></button></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Sports / Categories / Sport Events ──────────────────────────────── -->
  <div class="col-lg-7">
    <div class="sms-card p-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-trophy me-2"></i>Sports → Categories → Events</h6>
      </div>

      <?php foreach ($sports as $sport): ?>
        <div class="border rounded-3 p-3 mb-3">
          <div class="d-flex align-items-center justify-content-between">
            <div class="fw-semibold"><i class="bi bi-bullseye me-2"></i><?= e($sport['name']) ?></div>
            <button class="btn btn-sm btn-outline-primary" type="button"
                    onclick="addCategoryRow(<?= (int)$sport['id'] ?>)">
              <i class="bi bi-plus me-1"></i>Add Category
            </button>
          </div>

          <div class="mt-3" id="cats-sport-<?= (int)$sport['id'] ?>">
            <?php foreach ($sport['categories'] as $cat): ?>
              <?php $catId = (int)$cat['id']; ?>
              <div class="border rounded-3 p-3 mt-2 cat-row" data-id="<?= $catId ?>" data-sport-id="<?= (int)$sport['id'] ?>">
                <div class="row g-2 align-items-center">
                  <div class="col-md-6">
                    <input class="form-control form-control-sm" data-field="name" value="<?= e($cat['name']) ?>" placeholder="Category name (e.g. 10m Air Pistol)">
                  </div>
                  <div class="col-md-2">
                    <input class="form-control form-control-sm text-end" data-field="sort_order" type="number" value="<?= (int)$cat['sort_order'] ?>" placeholder="Order">
                  </div>
                  <div class="col-md-4 text-end">
                    <button class="btn btn-sm btn-outline-primary me-1" type="button" onclick="categorySave(this)"><i class="bi bi-save"></i> Save</button>
                    <button class="btn btn-sm btn-outline-secondary me-1" type="button" onclick="toggleEvents(this)"><i class="bi bi-list me-1"></i>Events</button>
                    <button class="btn btn-sm btn-outline-danger" type="button" onclick="categoryDelete(this)"><i class="bi bi-trash"></i></button>
                  </div>
                </div>
                <div class="cat-events mt-3 border-top pt-3" style="display:none">
                  <div class="text-muted small">Loading…</div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<script>
const CSRF = '<?= e($csrfToken) ?>';
const AGE_CATEGORIES = <?= json_encode(array_map(fn($a) => ['id'=>(int)$a['id'],'name'=>$a['name']], $age_categories)) ?>;

function showToast(msg, type) {
  type = type || 'success';
  const el  = document.getElementById('settingsToast');
  el.className = 'toast align-items-center border-0 text-bg-' + type;
  document.getElementById('toastMsg').textContent = msg;
  if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
    bootstrap.Toast.getOrCreateInstance(el, { delay: 3000 }).show();
  } else {
    alert(msg);
  }
}

async function postForm(url, fd) {
  fd.append('_token', CSRF);
  const res = await fetch(url, { method: 'POST', body: fd });
  let data; try { data = await res.json(); } catch (_) { data = { success:false, message:'Server returned invalid response.' }; }
  return data;
}

/* ── Age Categories ─── */
async function ageCatSave(btn) {
  const tr = btn.closest('tr');
  const fd = new FormData();
  fd.append('id',         tr.dataset.id);
  fd.append('name',       tr.querySelector('[data-field=name]').value);
  fd.append('min_age',    tr.querySelector('[data-field=min_age]').value);
  fd.append('max_age',    tr.querySelector('[data-field=max_age]').value);
  fd.append('sort_order', tr.querySelector('[data-field=sort_order]').value);
  const data = await postForm('/admin/settings/age-categories/save', fd);
  showToast(data.message, data.success ? 'success' : 'danger');
}
async function ageCatDelete(btn) {
  if (!confirm('Delete this age category?')) return;
  const tr = btn.closest('tr');
  const fd = new FormData();
  fd.append('id', tr.dataset.id);
  const data = await postForm('/admin/settings/age-categories/delete', fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) tr.remove();
}
async function ageCatAdd() {
  const fd = new FormData();
  fd.append('id', 0);
  fd.append('name',       document.getElementById('newAgeCatName').value);
  fd.append('min_age',    document.getElementById('newAgeCatMin').value);
  fd.append('max_age',    document.getElementById('newAgeCatMax').value);
  fd.append('sort_order', document.getElementById('newAgeCatSort').value);
  const data = await postForm('/admin/settings/age-categories/save', fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) location.reload();
}

/* ── Sport Categories ─── */
function addCategoryRow(sportId) {
  const wrap = document.getElementById('cats-sport-' + sportId);
  const div = document.createElement('div');
  div.className = 'border rounded-3 p-3 mt-2 cat-row';
  div.dataset.id = '0';
  div.dataset.sportId = sportId;
  div.innerHTML = `
    <div class="row g-2 align-items-center">
      <div class="col-md-6"><input class="form-control form-control-sm" data-field="name" placeholder="Category name (e.g. 10m Air Pistol)"></div>
      <div class="col-md-2"><input class="form-control form-control-sm text-end" data-field="sort_order" type="number" value="0" placeholder="Order"></div>
      <div class="col-md-4 text-end">
        <button class="btn btn-sm btn-outline-primary me-1" type="button" onclick="categorySave(this)"><i class="bi bi-save"></i> Save</button>
        <button class="btn btn-sm btn-outline-danger" type="button" onclick="this.closest('.cat-row').remove()"><i class="bi bi-x"></i></button>
      </div>
    </div>`;
  wrap.appendChild(div);
}
async function categorySave(btn) {
  const row = btn.closest('.cat-row');
  const fd = new FormData();
  fd.append('id',         row.dataset.id);
  fd.append('sport_id',   row.dataset.sportId);
  fd.append('name',       row.querySelector('[data-field=name]').value);
  fd.append('sort_order', row.querySelector('[data-field=sort_order]').value);
  const data = await postForm('/admin/settings/sport-categories/save', fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success && data.id) row.dataset.id = data.id;
}
async function categoryDelete(btn) {
  if (!confirm('Delete this category and all its events?')) return;
  const row = btn.closest('.cat-row');
  if (row.dataset.id === '0') { row.remove(); return; }
  const fd = new FormData();
  fd.append('id', row.dataset.id);
  const data = await postForm('/admin/settings/sport-categories/delete', fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) row.remove();
}

/* ── Sport Events (per-category) ─── */
async function toggleEvents(btn) {
  const row = btn.closest('.cat-row');
  const box = row.querySelector('.cat-events');
  if (box.style.display === 'block') { box.style.display = 'none'; return; }
  if (row.dataset.id === '0') { showToast('Save the category first.', 'warning'); return; }
  box.style.display = 'block';
  box.innerHTML = '<div class="text-muted small">Loading…</div>';
  const res  = await fetch('/admin/settings/sport-categories/' + row.dataset.id + '/events');
  const data = await res.json();
  renderEvents(box, row.dataset.id, data.sport_events || []);
}
function renderEvents(box, categoryId, list) {
  let html = '<div class="mb-2 fw-semibold small text-muted">Events under this category</div>';
  html += '<div class="event-rows">';
  list.forEach(ev => html += eventRowHtml(ev, categoryId));
  html += '</div>';
  html += '<div class="mt-2"><button class="btn btn-sm btn-outline-primary" type="button" onclick="addEventRow(this, ' + categoryId + ')"><i class="bi bi-plus me-1"></i>Add Event</button></div>';
  box.innerHTML = html;
}
function eventRowHtml(ev, categoryId) {
  const ageOpts = AGE_CATEGORIES.map(a => '<option value="'+a.id+'"'+((ev && ev.age_category_id == a.id)?' selected':'')+'>'+a.name+'</option>').join('');
  const id = ev ? ev.id : 0;
  return `
  <div class="border rounded-3 p-2 mb-2 ev-row" data-id="${id}" data-category-id="${categoryId}">
    <div class="row g-2 align-items-center">
      <div class="col-md-3"><select class="form-select form-select-sm" data-field="age_category_id"><option value="">Age cat…</option>${ageOpts}</select></div>
      <div class="col-md-2"><select class="form-select form-select-sm" data-field="gender">
        <option value="male"${ev && ev.gender==='male'?' selected':''}>Men</option>
        <option value="female"${ev && ev.gender==='female'?' selected':''}>Women</option>
        <option value="mixed"${ev && ev.gender==='mixed'?' selected':''}>Mixed</option>
      </select></div>
      <div class="col-md-2"><input class="form-control form-control-sm" data-field="weight" placeholder="Weight" value="${ev && ev.weight ? ev.weight : ''}"></div>
      <div class="col-md-1"><input class="form-control form-control-sm" data-field="height" placeholder="Ht"     value="${ev && ev.height ? ev.height : ''}"></div>
      <div class="col-md-1 form-check form-switch ms-2 mt-2"><input class="form-check-input" data-field="para" type="checkbox" ${ev && ev.para==1?'checked':''}><label class="form-check-label small">Para</label></div>
      <div class="col-md-3 text-end">
        <button class="btn btn-sm btn-outline-primary me-1" type="button" onclick="eventSave(this)"><i class="bi bi-save"></i></button>
        <button class="btn btn-sm btn-outline-danger" type="button" onclick="eventDelete(this)"><i class="bi bi-trash"></i></button>
      </div>
      <div class="col-12"><input class="form-control form-control-sm" data-field="name" placeholder="Display name (auto-generated if blank)" value="${ev && ev.name ? ev.name.replace(/"/g,'&quot;') : ''}"></div>
    </div>
  </div>`;
}
function addEventRow(btn, categoryId) {
  const box = btn.closest('.cat-events').querySelector('.event-rows');
  const tmp = document.createElement('div'); tmp.innerHTML = eventRowHtml(null, categoryId);
  box.appendChild(tmp.firstElementChild);
}
async function eventSave(btn) {
  const row = btn.closest('.ev-row');
  const fd = new FormData();
  fd.append('id',              row.dataset.id);
  fd.append('category_id',     row.dataset.categoryId);
  fd.append('age_category_id', row.querySelector('[data-field=age_category_id]').value);
  fd.append('gender',          row.querySelector('[data-field=gender]').value);
  fd.append('weight',          row.querySelector('[data-field=weight]').value);
  fd.append('height',          row.querySelector('[data-field=height]').value);
  fd.append('name',            row.querySelector('[data-field=name]').value);
  if (row.querySelector('[data-field=para]').checked) fd.append('para', '1');
  const data = await postForm('/admin/settings/sport-events/save', fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success && data.id) {
    row.dataset.id = data.id;
    if (data.name) row.querySelector('[data-field=name]').value = data.name;
  }
}
async function eventDelete(btn) {
  const row = btn.closest('.ev-row');
  if (row.dataset.id === '0') { row.remove(); return; }
  if (!confirm('Delete this sport event?')) return;
  const fd = new FormData(); fd.append('id', row.dataset.id);
  const data = await postForm('/admin/settings/sport-events/delete', fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) row.remove();
}
</script>
