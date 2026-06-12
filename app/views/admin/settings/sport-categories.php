<?php
$pageTitle = 'Categories — ' . ($sport['name'] ?? '');
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/admin/settings/sports/catalog" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Sports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-tags me-2"></i>Categories</h5>
  <span class="text-muted small ms-2">— <?= e($sport['name']) ?></span>
  <button type="button" class="btn btn-sm btn-primary ms-auto" onclick="openCatModal()">
    <i class="bi bi-plus-circle me-1"></i>Add Category
  </button>
</div>

<?= flashBag() ?>

<div class="sms-card p-3">
  <?php if (empty($categories)): ?>
    <p class="small text-muted text-center mb-0 py-3">No categories yet — click <strong>Add Category</strong> to create one.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:50px">#</th>
          <th>Name</th>
          <th style="width:90px">Abbr</th>
          <th style="width:80px" class="text-center">Sort</th>
          <th style="width:90px">PwD</th>
          <th style="width:90px" class="text-center">Series</th>
          <th style="width:90px" class="text-center">Shots</th>
          <th style="width:120px">Score Type</th>
          <th style="width:80px" class="text-center">Inner-X</th>
          <th style="width:90px" class="text-center">Events</th>
          <th class="text-end" style="width:230px">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($categories as $i => $c): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td class="fw-medium"><?= e($c['name']) ?></td>
            <td><?= e($c['abbreviation'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
            <td class="text-center"><?= (int)$c['sort_order'] ?></td>
            <td><span class="small"><?= e($c['pwd_status'] ?? 'no') ?></span></td>
            <td class="text-center"><?= $c['default_series_count']     !== null ? (int)$c['default_series_count']     : '<span class="text-muted">—</span>' ?></td>
            <td class="text-center"><?= $c['default_shots_per_series'] !== null ? (int)$c['default_shots_per_series'] : '<span class="text-muted">—</span>' ?></td>
            <td><?= e($c['default_score_type'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
            <td class="text-center">
              <?= !empty($c['inner_ten']) ? '<i class="bi bi-check-lg text-success"></i>' : '<span class="text-muted">—</span>' ?>
            </td>
            <td class="text-center fw-medium"><?= (int)$c['event_count'] ?></td>
            <td class="text-end">
              <div class="d-inline-flex gap-1">
                <a class="btn btn-sm btn-outline-primary"
                   href="/admin/settings/sport-categories/<?= (int)$c['id'] ?>/sport-events"
                   title="Events">
                  <i class="bi bi-trophy"></i><span class="ms-1 d-none d-xl-inline">Events</span>
                </a>
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        onclick='editCat(<?= json_encode($c, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'
                        title="Edit">
                  <i class="bi bi-pencil"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger"
                        onclick="deleteCat(<?= (int)$c['id'] ?>, '<?= e($c['name']) ?>')"
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
<div class="modal fade" id="catModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form class="modal-content" id="catForm" onsubmit="return saveCat(event)">
      <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="sport_id" value="<?= (int)$sport['id'] ?>">
      <input type="hidden" name="id" id="cat_id" value="">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-tag me-2"></i><span id="catModalTitle">Add Category</span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-7">
            <label class="form-label small mb-1">Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="cat_name" class="form-control form-control-sm" required maxlength="150">
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">Abbreviation</label>
            <input type="text" name="abbreviation" id="cat_abbr" class="form-control form-control-sm" maxlength="20">
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-1">Sort</label>
            <input type="number" name="sort_order" id="cat_sort" value="0" class="form-control form-control-sm">
          </div>

          <div class="col-md-3">
            <label class="form-label small mb-1">PwD Status</label>
            <select name="pwd_status" id="cat_pwd" class="form-select form-select-sm">
              <option value="no">No</option>
              <option value="deaf">Deaf</option>
              <option value="para">Para</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">Default Series</label>
            <input type="number" name="default_series_count" id="cat_series" min="0" class="form-control form-control-sm">
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">Default Shots/Series</label>
            <input type="number" name="default_shots_per_series" id="cat_shots" min="0" class="form-control form-control-sm">
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">Default Score Type</label>
            <select name="default_score_type" id="cat_stype" class="form-select form-select-sm">
              <option value="">— none —</option>
              <option value="integer">Integer</option>
              <option value="decimal_1">Decimal (1 dp)</option>
              <option value="decimal_2">Decimal (2 dp)</option>
            </select>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="cat_inner" name="inner_ten" value="1">
              <label class="form-check-label" for="cat_inner">Track Inner-X count</label>
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

<script>
const CSRF = '<?= e($csrfToken) ?>';
let catModalInst = null;
document.addEventListener('DOMContentLoaded', () => {
  catModalInst = bootstrap.Modal.getOrCreateInstance(document.getElementById('catModal'));
});

function openCatModal() {
  document.getElementById('catForm').reset();
  document.getElementById('cat_id').value = '';
  document.getElementById('catModalTitle').textContent = 'Add Category';
  catModalInst.show();
}
function editCat(c) {
  const $ = id => document.getElementById(id);
  $('cat_id').value     = c.id;
  $('cat_name').value   = c.name || '';
  $('cat_abbr').value   = c.abbreviation || '';
  $('cat_sort').value   = c.sort_order || 0;
  $('cat_pwd').value    = c.pwd_status || 'no';
  $('cat_series').value = c.default_series_count     != null ? c.default_series_count     : '';
  $('cat_shots').value  = c.default_shots_per_series != null ? c.default_shots_per_series : '';
  $('cat_stype').value  = c.default_score_type || '';
  $('cat_inner').checked = !!Number(c.inner_ten);
  document.getElementById('catModalTitle').textContent = 'Edit Category — ' + (c.name || '');
  catModalInst.show();
}
async function saveCat(ev) {
  ev.preventDefault();
  const fd = new FormData(document.getElementById('catForm'));
  const res = await fetch('/admin/settings/sport-categories/save', { method: 'POST', body: fd });
  const d   = await res.json();
  if (!d.success) { alert(d.message || 'Save failed.'); return false; }
  catModalInst.hide();
  window.location.reload();
  return false;
}
async function deleteCat(id, name) {
  if (!confirm('Delete category "' + name + '"? This cannot be undone.')) return;
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('id', id);
  const res = await fetch('/admin/settings/sport-categories/delete', { method: 'POST', body: fd });
  const d   = await res.json();
  if (!d.success) { alert(d.message || 'Delete failed.'); return; }
  window.location.reload();
}
</script>
