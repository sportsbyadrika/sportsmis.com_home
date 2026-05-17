<?php
$pageTitle = 'Sports → Categories → Events';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="settingsToast" class="toast align-items-center border-0" role="alert" aria-live="assertive">
    <div class="d-flex">
      <div class="toast-body fw-medium" id="toastMsg"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
  <a href="/admin/settings/sports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Sports Setting
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-diagram-3 me-2"></i>Sports &rarr; Categories &rarr; Events</h5>
</div>

<div class="sms-card p-4">
  <p class="small text-muted mb-3">
    Toggle <strong>Visible</strong> on the sports that institutions and athletes can pick.
    Disabled sports stay in the catalog but are hidden from event editors and athlete profiles.
  </p>

  <?php foreach ($sports as $sport): ?>
    <div class="border rounded-3 p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="fw-semibold"><i class="bi bi-bullseye me-2"></i><?= e($sport['name']) ?></div>
        <div class="d-flex align-items-center gap-2">
          <div class="form-check form-switch m-0">
            <input class="form-check-input" type="checkbox"
                   id="visSport<?= (int)$sport['id'] ?>"
                   <?= !empty($sport['enabled_for_events']) ? 'checked' : '' ?>
                   onchange="toggleSport(<?= (int)$sport['id'] ?>, this.checked)">
            <label class="form-check-label small" for="visSport<?= (int)$sport['id'] ?>">Visible</label>
          </div>
          <button class="btn btn-sm btn-outline-primary" type="button"
                  onclick="addCategoryRow(<?= (int)$sport['id'] ?>)">
            <i class="bi bi-plus me-1"></i>Add Category
          </button>
        </div>
      </div>

      <div class="mt-3" id="cats-sport-<?= (int)$sport['id'] ?>">
        <?php foreach ($sport['categories'] as $cat): ?>
          <?php
            $catId = (int)$cat['id'];
            $catPwd = strtolower((string)($cat['pwd_status'] ?? 'no'));
            if (!in_array($catPwd, ['no','deaf','para'], true)) $catPwd = 'no';
          ?>
          <div class="border rounded-3 p-3 mt-2 cat-row" data-id="<?= $catId ?>" data-sport-id="<?= (int)$sport['id'] ?>">
            <div class="row g-2 align-items-center">
              <div class="col-md-4">
                <input class="form-control form-control-sm" data-field="name" value="<?= e($cat['name']) ?>" placeholder="Category name (e.g. 10m Air Pistol)">
              </div>
              <div class="col-md-2">
                <input class="form-control form-control-sm" data-field="abbreviation" maxlength="20"
                       value="<?= e($cat['abbreviation'] ?? '') ?>" placeholder="Abbr (e.g. AP)" title="Category abbreviation">
              </div>
              <div class="col-md-2">
                <select class="form-select form-select-sm" data-field="pwd_status" title="Is PwD category?">
                  <?php foreach (['no' => 'PwD: No', 'deaf' => 'PwD: Deaf', 'para' => 'PwD: Para'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= $catPwd === $v ? 'selected' : '' ?>><?= $l ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-1">
                <input class="form-control form-control-sm text-end" data-field="sort_order" type="number" value="<?= (int)$cat['sort_order'] ?>" placeholder="Order">
              </div>
              <div class="col-md-3 text-end">
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
  } else { alert(msg); }
}

async function postForm(url, fd) {
  fd.append('_token', CSRF);
  const res = await fetch(url, { method: 'POST', body: fd });
  let data; try { data = await res.json(); } catch (_) { data = { success:false, message:'Server returned invalid response.' }; }
  return data;
}

async function toggleSport(sportId, enabled) {
  const fd = new FormData();
  fd.append('sport_id', sportId);
  if (enabled) fd.append('enabled', '1');
  const data = await postForm('/admin/settings/sports/toggle', fd);
  showToast(data.message, data.success ? 'success' : 'danger');
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
      <div class="col-md-4"><input class="form-control form-control-sm" data-field="name" placeholder="Category name (e.g. 10m Air Pistol)"></div>
      <div class="col-md-2"><input class="form-control form-control-sm" data-field="abbreviation" maxlength="20" placeholder="Abbr (e.g. AP)"></div>
      <div class="col-md-2">
        <select class="form-select form-select-sm" data-field="pwd_status" title="Is PwD category?">
          <option value="no" selected>PwD: No</option>
          <option value="deaf">PwD: Deaf</option>
          <option value="para">PwD: Para</option>
        </select>
      </div>
      <div class="col-md-1"><input class="form-control form-control-sm text-end" data-field="sort_order" type="number" value="0" placeholder="Order"></div>
      <div class="col-md-3 text-end">
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
  const abbrEl = row.querySelector('[data-field=abbreviation]');
  fd.append('abbreviation', abbrEl ? abbrEl.value : '');
  fd.append('sort_order', row.querySelector('[data-field=sort_order]').value);
  const pwdEl = row.querySelector('[data-field=pwd_status]');
  fd.append('pwd_status', pwdEl ? pwdEl.value : 'no');
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
