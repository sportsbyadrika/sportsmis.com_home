<?php
$pageTitle = 'Event Staff — ' . $event['name'];
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="suToast" class="toast align-items-center border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body fw-medium" id="suToastMsg"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/edit" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Back to Event
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-person-vcard me-2"></i>Event Staff Users</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
</div>

<div class="row g-4">
  <div class="col-lg-9">
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 flex-wrap gap-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-plus-circle me-2"></i><span id="formTitle">Add Staff User</span></h6>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetForm()" id="resetBtn" style="display:none">
          <i class="bi bi-x-lg me-1"></i>Cancel Edit
        </button>
      </div>

      <input type="hidden" id="su_id" value="0">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-medium">Name <span class="text-danger">*</span></label>
          <input type="text" id="su_name" class="form-control" maxlength="255" placeholder="e.g. Anita Sharma">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-medium">Email <span class="text-danger">*</span></label>
          <input type="email" id="su_email" class="form-control" placeholder="staff@example.com" maxlength="255">
          <small class="text-muted">Must be unique within this event.</small>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-medium">Mobile</label>
          <input type="tel" id="su_mobile" class="form-control" maxlength="10" placeholder="10-digit">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-medium">Status <span class="text-danger">*</span></label>
          <select id="su_status" class="form-select">
            <option value="active" selected>Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div class="col-md-5 d-flex align-items-end">
          <button type="button" class="btn btn-primary w-100" onclick="saveStaff()">
            <i class="bi bi-save me-1"></i><span id="saveLabel">Add Staff</span>
          </button>
        </div>
        <div class="col-12">
          <label class="form-label fw-medium">Assigned Privileges</label>
          <div class="row g-2" id="privChecks">
            <?php foreach ($privileges as $key => $label): ?>
              <div class="col-md-6">
                <div class="form-check border rounded-3 px-3 py-2">
                  <input class="form-check-input su-priv" type="checkbox"
                         value="<?= e($key) ?>" id="priv_<?= e($key) ?>">
                  <label class="form-check-label" for="priv_<?= e($key) ?>"><?= e($label) ?></label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <small class="text-muted">Dashboard menu items shown to the staff member depend on the privileges ticked here.</small>
        </div>
      </div>
    </div>

    <div class="sms-card p-3">
      <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-list-ul me-2"></i>Existing Staff</h6>
        <span class="badge bg-secondary"><?= count($staff) ?></span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Name</th><th>Email</th><th>Mobile</th><th>Privileges</th>
              <th>Status</th><th>Last Login</th><th class="text-end"></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($staff)): ?>
              <tr><td colspan="7" class="text-muted text-center py-3">No staff users yet.</td></tr>
            <?php else: foreach ($staff as $s): ?>
              <tr>
                <td class="fw-medium"><?= e($s['name']) ?></td>
                <td class="small"><?= e($s['email']) ?></td>
                <td class="small text-muted"><?= e($s['mobile'] ?? '—') ?></td>
                <td class="small">
                  <?php foreach ($s['privileges'] as $p): ?>
                    <span class="badge bg-info-subtle text-info-emphasis me-1"><?= e($privileges[$p] ?? $p) ?></span>
                  <?php endforeach; ?>
                  <?php if (empty($s['privileges'])): ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td>
                  <?php if ($s['status'] === 'active'): ?>
                    <span class="badge bg-success">Active</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Inactive</span>
                  <?php endif; ?>
                </td>
                <td class="small text-muted">
                  <?= !empty($s['last_login_at']) ? formatDate($s['last_login_at'], 'd M Y H:i') : '—' ?>
                </td>
                <td class="text-end text-nowrap">
                  <button class="btn btn-sm btn-outline-primary me-1" type="button"
                          data-id="<?= (int)$s['id'] ?>"
                          data-name="<?= e($s['name']) ?>"
                          data-email="<?= e($s['email']) ?>"
                          data-mobile="<?= e($s['mobile'] ?? '') ?>"
                          data-status="<?= e($s['status']) ?>"
                          data-privs="<?= e(json_encode($s['privileges'])) ?>"
                          onclick="editStaff(this)" title="Edit"><i class="bi bi-pencil"></i></button>
                  <button class="btn btn-sm btn-outline-warning me-1" type="button"
                          onclick="resetPassword(<?= (int)$s['id'] ?>)" title="Reset Password">
                    <i class="bi bi-key"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-danger" type="button"
                          onclick="deleteStaff(<?= (int)$s['id'] ?>)" title="Delete">
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

  <div class="col-lg-3">
    <div class="sms-card p-4 mb-3 text-center"
         style="background:linear-gradient(135deg,#0b1f3a,#166534);color:#fff">
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
      <h6 class="fw-semibold mb-2"><i class="bi bi-info-circle me-1"></i>How staff sign in</h6>
      <p class="mb-1">Share the Event Code with the staff member's email + temporary password (emailed on creation).</p>
      <p class="mb-0">Login URL: <a href="/staff/login" target="_blank">/staff/login</a></p>
    </div>
  </div>
</div>

<script>
const CSRF = '<?= e($csrfToken) ?>';
const SAVE_URL   = '/institution/events/<?= e($eventHash) ?>/staff-users/save';
const DELETE_URL = '/institution/events/<?= e($eventHash) ?>/staff-users/delete';
const RESET_URL  = '/institution/events/<?= e($eventHash) ?>/staff-users/reset-password';

function showToast(msg, type) {
  const el = document.getElementById('suToast');
  el.className = 'toast align-items-center border-0 text-bg-' + (type || 'primary');
  document.getElementById('suToastMsg').textContent = msg;
  if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
    bootstrap.Toast.getOrCreateInstance(el, { delay: 3500 }).show();
  } else { alert(msg); }
}

function resetForm() {
  document.getElementById('su_id').value     = '0';
  document.getElementById('su_name').value   = '';
  document.getElementById('su_email').value  = '';
  document.getElementById('su_mobile').value = '';
  document.getElementById('su_status').value = 'active';
  document.querySelectorAll('.su-priv').forEach(cb => { cb.checked = false; });
  document.getElementById('formTitle').textContent = 'Add Staff User';
  document.getElementById('saveLabel').textContent = 'Add Staff';
  document.getElementById('resetBtn').style.display = 'none';
}

async function postForm(url, fd) {
  fd.append('_token', CSRF);
  const res = await fetch(url, { method: 'POST', body: fd });
  let data; try { data = await res.json(); } catch (_) { data = { success:false, message:'Invalid response.' }; }
  return data;
}

async function saveStaff() {
  const fd = new FormData();
  fd.append('id',     document.getElementById('su_id').value);
  fd.append('name',   document.getElementById('su_name').value.trim());
  fd.append('email',  document.getElementById('su_email').value.trim());
  fd.append('mobile', document.getElementById('su_mobile').value.trim());
  fd.append('status', document.getElementById('su_status').value);
  document.querySelectorAll('.su-priv:checked').forEach(cb => fd.append('privileges[]', cb.value));

  const data = await postForm(SAVE_URL, fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) {
    if (data.temp_password) {
      alert('Temporary password (also emailed):\n\n' + data.temp_password
        + '\n\nShare this with the staff member along with the Event Code.');
    }
    location.reload();
  }
}

function editStaff(btn) {
  document.getElementById('su_id').value     = btn.dataset.id;
  document.getElementById('su_name').value   = btn.dataset.name;
  document.getElementById('su_email').value  = btn.dataset.email;
  document.getElementById('su_mobile').value = btn.dataset.mobile;
  document.getElementById('su_status').value = btn.dataset.status;
  const privs = JSON.parse(btn.dataset.privs || '[]');
  document.querySelectorAll('.su-priv').forEach(cb => { cb.checked = privs.includes(cb.value); });
  document.getElementById('formTitle').textContent = 'Edit Staff User';
  document.getElementById('saveLabel').textContent = 'Save Changes';
  document.getElementById('resetBtn').style.display = '';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function deleteStaff(id) {
  if (!confirm('Remove this staff user? Their login will stop working immediately.')) return;
  const fd = new FormData(); fd.append('id', id);
  const data = await postForm(DELETE_URL, fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) location.reload();
}

async function resetPassword(id) {
  if (!confirm('Generate a fresh password and email it to this staff member?')) return;
  const fd = new FormData(); fd.append('id', id);
  const data = await postForm(RESET_URL, fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success && data.temp_password) {
    alert('Temporary password (also emailed):\n\n' + data.temp_password);
  }
}
</script>
