<?php
$pageTitle = 'Unit Users — ' . $event['name'];
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="uuToast" class="toast align-items-center border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body fw-medium" id="uuToastMsg"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/edit" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Back to Event
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-person-gear me-2"></i>Unit / Institution / Club Users</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
</div>

<div class="row g-4">
  <!-- Main column: list + add/edit form -->
  <div class="col-lg-9">

    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 flex-wrap gap-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-plus-circle me-2"></i><span id="formTitle">Add Unit User</span></h6>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetForm()" id="resetBtn" style="display:none">
          <i class="bi bi-x-lg me-1"></i>Cancel Edit
        </button>
      </div>

      <input type="hidden" id="uu_id" value="0">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-medium">Name <span class="text-danger">*</span></label>
          <input type="text" id="uu_name" class="form-control" maxlength="255" placeholder="e.g. Ravi Kumar">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-medium">Email <span class="text-danger">*</span></label>
          <input type="email" id="uu_email" class="form-control" placeholder="user@example.com" maxlength="255">
          <small class="text-muted">Must be unique within this event.</small>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-medium">Mobile</label>
          <input type="tel" id="uu_mobile" class="form-control" maxlength="10" placeholder="10-digit">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-medium">Status <span class="text-danger">*</span></label>
          <select id="uu_status" class="form-select">
            <option value="active" selected>Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div class="col-md-5 d-flex align-items-end">
          <button type="button" class="btn btn-primary w-100" onclick="saveUnitUser()">
            <i class="bi bi-save me-1"></i><span id="saveLabel">Add User</span>
          </button>
        </div>
        <div class="col-12">
          <label class="form-label fw-medium">Assign Units <span class="text-danger">*</span></label>
          <?php if (empty($units)): ?>
            <div class="alert alert-warning small mb-0">
              No units exist for this event yet. Add them from the
              <a href="/institution/events/<?= e($eventHash) ?>/edit#units" class="alert-link">event editor</a> first.
            </div>
          <?php else: ?>
            <div class="row g-2" id="unitChecks">
              <?php foreach ($units as $u): ?>
                <div class="col-md-4 col-sm-6">
                  <div class="form-check border rounded-3 px-3 py-2">
                    <input class="form-check-input uu-unit" type="checkbox"
                           value="<?= (int)$u['id'] ?>" id="unit_<?= (int)$u['id'] ?>">
                    <label class="form-check-label" for="unit_<?= (int)$u['id'] ?>">
                      <?= e($u['name']) ?>
                      <?php if (!empty($u['address'])): ?>
                        <small class="d-block text-muted"><?= e($u['address']) ?></small>
                      <?php endif; ?>
                    </label>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <small class="text-muted">Pick one or more units this user can manage.</small>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="sms-card p-3">
      <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-list-ul me-2"></i>Existing Users</h6>
        <span class="badge bg-secondary" id="uuCount"><?= count($unit_users) ?></span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Mobile</th>
              <th>Units</th>
              <th>Status</th>
              <th>Last Login</th>
              <th class="text-end"></th>
            </tr>
          </thead>
          <tbody id="uuRows">
            <?php if (empty($unit_users)): ?>
              <tr id="uuEmpty"><td colspan="7" class="text-muted text-center py-3">No unit users yet.</td></tr>
            <?php else: foreach ($unit_users as $u): ?>
              <tr data-id="<?= (int)$u['id'] ?>">
                <td class="fw-medium"><?= e($u['name']) ?></td>
                <td class="small"><?= e($u['email']) ?></td>
                <td class="small text-muted"><?= e($u['mobile'] ?? '—') ?></td>
                <td class="small">
                  <?php foreach ($u['units'] as $au): ?>
                    <span class="badge bg-info-subtle text-info-emphasis me-1"><?= e($au['name']) ?></span>
                  <?php endforeach; ?>
                  <?php if (empty($u['units'])): ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td>
                  <?php if ($u['status'] === 'active'): ?>
                    <span class="badge bg-success">Active</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Inactive</span>
                  <?php endif; ?>
                </td>
                <td class="small text-muted">
                  <?= !empty($u['last_login_at']) ? formatDate($u['last_login_at'], 'd M Y H:i') : '—' ?>
                </td>
                <td class="text-end text-nowrap">
                  <button class="btn btn-sm btn-outline-primary me-1" type="button"
                          data-name="<?= e($u['name']) ?>"
                          data-email="<?= e($u['email']) ?>"
                          data-mobile="<?= e($u['mobile'] ?? '') ?>"
                          data-status="<?= e($u['status']) ?>"
                          data-units="<?= e(json_encode(array_map(fn($x)=>(int)$x['id'], $u['units']))) ?>"
                          onclick="editUnitUser(this)" title="Edit"><i class="bi bi-pencil"></i></button>
                  <button class="btn btn-sm btn-outline-warning me-1" type="button"
                          onclick="resetPassword(<?= (int)$u['id'] ?>)" title="Reset Password">
                    <i class="bi bi-key"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-danger" type="button"
                          onclick="deleteUnitUser(<?= (int)$u['id'] ?>)" title="Delete">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Side panel: Event Code -->
  <div class="col-lg-3">
    <div class="sms-card p-4 mb-3 text-center"
         style="background:linear-gradient(135deg,#0b1f3a,#1e3a8a);color:#fff">
      <div class="small text-uppercase fw-semibold" style="letter-spacing:.08em;opacity:.8">Event Code</div>
      <div class="mt-2" style="font-size:1.75rem;font-weight:700;letter-spacing:.05em;font-family:monospace">
        <?= e($event['event_code'] ?? '—') ?>
      </div>
      <button class="btn btn-sm btn-outline-light mt-3" type="button"
              onclick="navigator.clipboard.writeText('<?= e($event['event_code'] ?? '') ?>'); showToast('Code copied','success')">
        <i class="bi bi-clipboard me-1"></i>Copy
      </button>
    </div>
    <div class="sms-card p-3 small text-muted">
      <h6 class="fw-semibold mb-2"><i class="bi bi-info-circle me-1"></i>How unit users sign in</h6>
      <p class="mb-1">Share the Event Code along with the user's email + temporary password (sent on creation).</p>
      <p class="mb-0">Login URL: <a href="/unit/login" target="_blank">/unit/login</a></p>
    </div>
  </div>
</div>

<script>
const CSRF = '<?= e($csrfToken) ?>';
const SAVE_URL   = '/institution/events/<?= e($eventHash) ?>/unit-users/save';
const DELETE_URL = '/institution/events/<?= e($eventHash) ?>/unit-users/delete';
const RESET_URL  = '/institution/events/<?= e($eventHash) ?>/unit-users/reset-password';

function showToast(msg, type) {
  const el = document.getElementById('uuToast');
  el.className = 'toast align-items-center border-0 text-bg-' + (type || 'primary');
  document.getElementById('uuToastMsg').textContent = msg;
  if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
    bootstrap.Toast.getOrCreateInstance(el, { delay: 3500 }).show();
  } else { alert(msg); }
}

function resetForm() {
  document.getElementById('uu_id').value     = '0';
  document.getElementById('uu_name').value   = '';
  document.getElementById('uu_email').value  = '';
  document.getElementById('uu_mobile').value = '';
  document.getElementById('uu_status').value = 'active';
  document.querySelectorAll('.uu-unit').forEach(cb => { cb.checked = false; });
  document.getElementById('formTitle').textContent = 'Add Unit User';
  document.getElementById('saveLabel').textContent = 'Add User';
  document.getElementById('resetBtn').style.display = 'none';
}

async function postForm(url, fd) {
  fd.append('_token', CSRF);
  const res = await fetch(url, { method: 'POST', body: fd });
  let data; try { data = await res.json(); } catch (_) { data = { success:false, message:'Invalid response.' }; }
  return data;
}

async function saveUnitUser() {
  const fd = new FormData();
  fd.append('id',     document.getElementById('uu_id').value);
  fd.append('name',   document.getElementById('uu_name').value.trim());
  fd.append('email',  document.getElementById('uu_email').value.trim());
  fd.append('mobile', document.getElementById('uu_mobile').value.trim());
  fd.append('status', document.getElementById('uu_status').value);
  document.querySelectorAll('.uu-unit:checked').forEach(cb => fd.append('unit_ids[]', cb.value));

  const data = await postForm(SAVE_URL, fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) {
    if (data.temp_password) {
      alert('Temporary password (also emailed):\n\n' + data.temp_password
        + '\n\nShare this with the unit user along with the Event Code.');
    }
    location.reload();
  }
}

function editUnitUser(btn) {
  const tr = btn.closest('tr');
  document.getElementById('uu_id').value     = tr.dataset.id;
  document.getElementById('uu_name').value   = btn.dataset.name;
  document.getElementById('uu_email').value  = btn.dataset.email;
  document.getElementById('uu_mobile').value = btn.dataset.mobile;
  document.getElementById('uu_status').value = btn.dataset.status;
  const assigned = JSON.parse(btn.dataset.units || '[]');
  document.querySelectorAll('.uu-unit').forEach(cb => {
    cb.checked = assigned.includes(parseInt(cb.value, 10));
  });
  document.getElementById('formTitle').textContent = 'Edit Unit User';
  document.getElementById('saveLabel').textContent = 'Save Changes';
  document.getElementById('resetBtn').style.display = '';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function deleteUnitUser(id) {
  if (!confirm('Remove this unit user? Their login will stop working immediately.')) return;
  const fd = new FormData(); fd.append('id', id);
  const data = await postForm(DELETE_URL, fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) location.reload();
}

async function resetPassword(id) {
  if (!confirm('Generate a fresh password and email it to this user?')) return;
  const fd = new FormData(); fd.append('id', id);
  const data = await postForm(RESET_URL, fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success && data.temp_password) {
    alert('Temporary password (also emailed):\n\n' + data.temp_password);
  }
}
</script>
