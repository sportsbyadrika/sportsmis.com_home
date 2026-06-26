<?php
$pageTitle = 'Age Categories';
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
  <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-event me-2"></i>Age Categories</h5>
</div>

<?php
// Distinct sets present in the master list, plus 'master' guaranteed.
$sets = array_values(array_unique(array_filter(array_map(
    fn($a) => (string)($a['set_code'] ?? 'master'),
    $age_categories
))));
if (!in_array('master', $sets, true)) array_unshift($sets, 'master');
sort($sets);
// Friendly labels for the set codes the system ships with.
$setLabels = ['master' => 'Master (default)', 'cbse' => 'CBSE School Sports'];
?>

<div class="sms-card p-4">
  <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 flex-wrap gap-2">
    <h6 class="fw-semibold mb-0">Master List</h6>
    <div class="d-flex align-items-center gap-2">
      <label class="form-label small mb-0">Filter by Set</label>
      <select id="setFilter" class="form-select form-select-sm" style="width:auto" onchange="filterBySet()">
        <option value="">All sets</option>
        <?php foreach ($sets as $s): ?>
          <option value="<?= e($s) ?>"><?= e($setLabels[$s] ?? ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-sm align-middle mb-3">
      <thead class="table-light">
        <tr>
          <th>Name</th>
          <th title="Which Age Category set this row belongs to. Events choose a set on the Event Edit page; only rows in that set show on the sport-events picker.">Set</th>
          <th class="text-end" title="Minimum age in years">Min Age</th>
          <th class="text-end" title="Maximum age in years">Max Age</th>
          <th class="text-end" title="Earliest birth year accepted">Min Age Year</th>
          <th class="text-end" title="Latest birth year accepted">Max Age Year</th>
          <th title="Other age categories the athlete may also play in (Ctrl/Cmd-click to multi-select)">Also Eligible In</th>
          <th class="text-end">Order</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="ageCatRows">
        <?php foreach ($age_categories as $a):
          $myUpgrades = (array)($a['upgrades'] ?? []);
          $rowSet     = (string)($a['set_code'] ?? 'master');
        ?>
          <tr data-id="<?= (int)$a['id'] ?>" data-set="<?= e($rowSet) ?>">
            <td><input class="form-control form-control-sm" data-field="name" value="<?= e($a['name']) ?>"></td>
            <td style="min-width:140px">
              <select class="form-select form-select-sm" data-field="set_code">
                <?php foreach ($sets as $s): ?>
                  <option value="<?= e($s) ?>" <?= $s === $rowSet ? 'selected' : '' ?>><?= e($setLabels[$s] ?? ucfirst($s)) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input class="form-control form-control-sm text-end" data-field="min_age" type="number" min="0" value="<?= e($a['min_age'] ?? '') ?>"></td>
            <td><input class="form-control form-control-sm text-end" data-field="max_age" type="number" min="0" value="<?= e($a['max_age'] ?? '') ?>"></td>
            <td><input class="form-control form-control-sm text-end" data-field="min_age_year" type="number" min="1900" max="2100" value="<?= e($a['min_age_year'] ?? '') ?>" placeholder="e.g. 2007"></td>
            <td><input class="form-control form-control-sm text-end" data-field="max_age_year" type="number" min="1900" max="2100" value="<?= e($a['max_age_year'] ?? '') ?>" placeholder="e.g. 2010"></td>
            <td style="min-width:160px">
              <select multiple class="form-select form-select-sm" data-field="upgrades" size="3" style="min-height:5rem">
                <?php foreach ($age_categories as $other):
                  if ((int)$other['id'] === (int)$a['id']) continue;
                  // Only offer "Also Eligible" targets from the SAME set —
                  // mixing sets in upgrade chains makes no sense.
                  if ((string)($other['set_code'] ?? 'master') !== $rowSet) continue;
                  $sel = in_array((int)$other['id'], $myUpgrades, true) ? 'selected' : '';
                ?>
                  <option value="<?= (int)$other['id'] ?>" <?= $sel ?>><?= e($other['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input class="form-control form-control-sm text-end" data-field="sort_order" type="number" value="<?= (int)$a['sort_order'] ?>" style="width:70px"></td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="ageCatSave(this)"><i class="bi bi-save"></i></button>
              <button type="button" class="btn btn-sm btn-outline-danger"  onclick="ageCatDelete(this)"><i class="bi bi-trash"></i></button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="small text-muted mb-3">
      <i class="bi bi-info-circle me-1"></i>"Also Eligible In" lets a younger bracket compete in older brackets too — e.g. a <em>Sub Youth</em> athlete can also pick events tagged Youth, Junior or Senior. Each event picks its Age Category <strong>Set</strong> on its edit page (default <em>Master</em>); only rows in that set show on the sport-events picker.
    </p>
  </div>

  <div class="border-top pt-3">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-sm-3"><label class="form-label small mb-1">Name</label>
        <input id="newAgeCatName" class="form-control form-control-sm" placeholder="e.g. Youth"></div>
      <div class="col-6 col-sm-2"><label class="form-label small mb-1">Set</label>
        <select id="newAgeCatSet" class="form-select form-select-sm">
          <?php foreach ($sets as $s): ?>
            <option value="<?= e($s) ?>"><?= e($setLabels[$s] ?? ucfirst($s)) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-6 col-sm-1"><label class="form-label small mb-1">Min Age</label>
        <input id="newAgeCatMin" class="form-control form-control-sm" type="number" min="0"></div>
      <div class="col-6 col-sm-1"><label class="form-label small mb-1">Max Age</label>
        <input id="newAgeCatMax" class="form-control form-control-sm" type="number" min="0"></div>
      <div class="col-6 col-sm-2"><label class="form-label small mb-1">Min Age Year</label>
        <input id="newAgeCatMinYear" class="form-control form-control-sm" type="number" min="1900" max="2100" placeholder="e.g. 2007"></div>
      <div class="col-6 col-sm-2"><label class="form-label small mb-1">Max Age Year</label>
        <input id="newAgeCatMaxYear" class="form-control form-control-sm" type="number" min="1900" max="2100" placeholder="e.g. 2010"></div>
      <div class="col-9 col-sm-1"><label class="form-label small mb-1">Order</label>
        <input id="newAgeCatSort" class="form-control form-control-sm" type="number" value="0"></div>
      <div class="col-3 col-sm-12 col-md-12 mt-2">
        <button class="btn btn-sm btn-primary" onclick="ageCatAdd()"><i class="bi bi-plus me-1"></i>Add Age Category</button>
      </div>
    </div>
  </div>
</div>

<script>
const CSRF = '<?= e($csrfToken) ?>';

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

async function ageCatSave(btn) {
  const tr = btn.closest('tr');
  const fd = new FormData();
  fd.append('id',           tr.dataset.id);
  fd.append('name',         tr.querySelector('[data-field=name]').value);
  fd.append('set_code',     tr.querySelector('[data-field=set_code]').value);
  fd.append('min_age',      tr.querySelector('[data-field=min_age]').value);
  fd.append('max_age',      tr.querySelector('[data-field=max_age]').value);
  fd.append('min_age_year', tr.querySelector('[data-field=min_age_year]').value);
  fd.append('max_age_year', tr.querySelector('[data-field=max_age_year]').value);
  fd.append('sort_order',   tr.querySelector('[data-field=sort_order]').value);
  const upSel = tr.querySelector('[data-field=upgrades]');
  if (upSel) Array.from(upSel.selectedOptions).forEach(opt => fd.append('upgrades[]', opt.value));
  const data = await postForm('/admin/settings/age-categories/save', fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) {
    // Keep the row's data-set in sync with what we just saved so the
    // Filter-by-Set dropdown immediately reflects the new value without
    // a page reload.
    tr.dataset.set = fd.get('set_code');
    filterBySet();
  }
}

function filterBySet() {
  const want = (document.getElementById('setFilter') || {}).value || '';
  document.querySelectorAll('#ageCatRows tr').forEach(tr => {
    tr.style.display = (!want || tr.dataset.set === want) ? '' : 'none';
  });
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
  fd.append('name',         document.getElementById('newAgeCatName').value);
  fd.append('set_code',     document.getElementById('newAgeCatSet').value);
  fd.append('min_age',      document.getElementById('newAgeCatMin').value);
  fd.append('max_age',      document.getElementById('newAgeCatMax').value);
  fd.append('min_age_year', document.getElementById('newAgeCatMinYear').value);
  fd.append('max_age_year', document.getElementById('newAgeCatMaxYear').value);
  fd.append('sort_order',   document.getElementById('newAgeCatSort').value);
  const data = await postForm('/admin/settings/age-categories/save', fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) location.reload();
}
</script>
