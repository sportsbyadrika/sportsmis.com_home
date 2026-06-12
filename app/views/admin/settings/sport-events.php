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
  <button type="button" class="btn btn-sm btn-primary ms-auto" onclick="openEvtModal()">
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
            <td class="fw-medium"><?= e($se['name']) ?></td>
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
          <div class="col-12">
            <label class="form-label small mb-1">Name</label>
            <input type="text" name="name" id="evt_name" class="form-control form-control-sm" placeholder="(auto-generated if blank)">
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

<script>
const CSRF = '<?= e($csrfToken) ?>';
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
