<?php
$pageTitle = 'My Profile';
$dob = $athlete['date_of_birth'] ?? '';
$minor = $dob && ageFromDob($dob) < 18;
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$athleteSportIds = array_column($athlete_sports, 'sport_id');
$athleteSportMap = array_column($athlete_sports, null, 'sport_id');
?>

<!-- Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="profileToast" class="toast align-items-center border-0" role="alert" aria-live="assertive">
    <div class="d-flex">
      <div class="toast-body fw-medium" id="toastMsg"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2"></i>Athlete Profile</h5>
  <div class="d-flex align-items-center gap-2">
    <span id="completeBadge" class="badge px-3 py-2 <?= $athlete['profile_completed'] ? 'bg-success' : 'bg-warning text-dark' ?>">
      <?php if ($athlete['profile_completed']): ?>
        <i class="bi bi-check-circle me-1"></i>Profile Complete
      <?php else: ?>
        <i class="bi bi-exclamation-triangle me-1"></i>Profile Incomplete
      <?php endif; ?>
    </span>
    <button type="button" id="submitProfileBtn" class="btn btn-success px-4 fw-semibold" onclick="submitProfile()">
      <i class="bi bi-check2-all me-2"></i>Submit Profile
    </button>
  </div>
</div>

<div class="row g-4">

  <!-- ── Photo Column ──────────────────────────────────────────────────── -->
  <div class="col-lg-3">
    <div class="sms-card p-4 text-center">
      <div class="mb-3" id="photoPreview">
        <?php if ($athlete['passport_photo']): ?>
          <img src="<?= e($athlete['passport_photo']) ?>?t=<?= time() ?>" alt="Photo" id="currentPhoto"
               class="rounded-circle" width="130" height="130"
               style="object-fit:cover;border:3px solid #e2e8f0"
               onerror="this.onerror=null;this.replaceWith(Object.assign(document.createElement('div'),{id:'currentPhoto',className:'sms-avatar sms-avatar-xl mx-auto mb-2',textContent:<?= json_encode(avatarInitials($athlete['name'])) ?>}));">
        <?php else: ?>
          <div class="sms-avatar sms-avatar-xl mx-auto mb-2" id="currentPhoto">
            <?= avatarInitials($athlete['name']) ?>
          </div>
        <?php endif; ?>
      </div>
      <label class="form-label fw-medium">Passport Photo <span class="text-danger">*</span></label>
      <input type="file" id="photoFileInput" accept="image/jpeg,image/png,image/webp"
             class="d-none" onchange="initCropper(this)">
      <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-2"
              onclick="document.getElementById('photoFileInput').click()">
        <i class="bi bi-camera me-1"></i>Change Photo
      </button>
      <small class="text-muted d-block">JPG/PNG/WEBP · Max 2 MB<br>Passport size, white background</small>
      <div id="photoSaving" class="mt-2 d-none text-center">
        <div class="spinner-border spinner-border-sm text-primary"></div>
        <small class="text-muted ms-1">Uploading…</small>
      </div>
    </div>
  </div>

  <!-- ── Details Column ────────────────────────────────────────────────── -->
  <div class="col-lg-9">

    <!-- Personal Info -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0">Personal Information</h6>
        <button type="button" class="btn btn-sm btn-primary px-3" onclick="saveSection('personal')">
          <i class="bi bi-save me-1"></i>Save
        </button>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-medium">Full Name <span class="text-danger">*</span></label>
          <input type="text" id="p_name" value="<?= e($athlete['name']) ?>" class="form-control">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-medium">Date of Birth <span class="text-danger">*</span></label>
          <input type="date" id="p_dob" value="<?= e($athlete['date_of_birth'] ?? '') ?>"
                 class="form-control" max="<?= date('Y-m-d') ?>" onchange="checkMinor()">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-medium">Gender <span class="text-danger">*</span></label>
          <select id="p_gender" class="form-select">
            <option value="">Select</option>
            <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $v => $l): ?>
              <option value="<?= $v ?>" <?= ($athlete['gender'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-medium">Mobile <span class="text-danger">*</span></label>
          <input type="tel" id="p_mobile" value="<?= e($athlete['mobile'] ?? '') ?>"
                 class="form-control" maxlength="10" placeholder="10-digit">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-medium">WhatsApp Number</label>
          <input type="tel" id="p_whatsapp" value="<?= e($athlete['whatsapp_number'] ?? '') ?>"
                 class="form-control" maxlength="10">
        </div>
        <div class="col-md-2">
          <label class="form-label fw-medium">Weight (kg)</label>
          <input type="number" id="p_weight" value="<?= e($athlete['weight'] ?? '') ?>"
                 class="form-control" min="20" max="300" step="0.1">
        </div>
        <div class="col-md-2">
          <label class="form-label fw-medium">Height (cm)</label>
          <input type="number" id="p_height" value="<?= e($athlete['height'] ?? '') ?>"
                 class="form-control" min="50" max="300" step="0.1">
        </div>

        <div class="col-md-6" id="guardianField" style="<?= $minor ? '' : 'display:none' ?>">
          <label class="form-label fw-medium">
            Guardian Name <span class="text-danger">*</span>
            <small class="text-warning fw-normal">(Required for age &lt; 18)</small>
          </label>
          <input type="text" id="p_guardian" value="<?= e($athlete['guardian_name'] ?? '') ?>"
                 class="form-control">
        </div>

        <div class="col-12">
          <label class="form-label fw-medium">Address <span class="text-danger">*</span></label>
          <textarea id="p_address" rows="2" class="form-control"><?= e($athlete['address'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label fw-medium">Communication Address</label>
          <textarea id="p_comm_address" rows="2" class="form-control"><?= e($athlete['communication_address'] ?? '') ?></textarea>
          <small class="text-muted">Leave blank if same as above.</small>
        </div>
      </div>
    </div>

    <!-- Location -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-geo-alt me-2"></i>Location</h6>
        <button type="button" class="btn btn-sm btn-primary px-3" onclick="saveSection('location')">
          <i class="bi bi-save me-1"></i>Save
        </button>
      </div>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label fw-medium">Country</label>
          <select id="l_country" class="form-select" onchange="loadStates(this.value)">
            <?php foreach ($countries as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($athlete['country_id'] ?? 1) == $c['id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-medium">State</label>
          <select id="l_state" class="form-select" onchange="loadDistricts(this.value)">
            <option value="">-- Select State --</option>
            <?php foreach ($states as $s): ?>
              <option value="<?= $s['id'] ?>" <?= ($athlete['state_id'] ?? '') == $s['id'] ? 'selected' : '' ?>>
                <?= e($s['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-medium">District</label>
          <select id="l_district" class="form-select">
            <option value="">-- Select District --</option>
            <?php foreach ($districts as $d): ?>
              <option value="<?= $d['id'] ?>" <?= ($athlete['district_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                <?= e($d['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-medium">Nationality <span class="text-danger">*</span></label>
          <input type="text" id="l_nationality" value="<?= e($athlete['nationality'] ?? 'Indian') ?>"
                 class="form-control">
        </div>
      </div>
    </div>

    <!-- ID Proof — Aadhaar (mandatory) -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0">
          <i class="bi bi-card-text me-2"></i>ID Proof — Aadhaar
          <span class="text-danger">*</span>
        </h6>
        <button type="button" class="btn btn-sm btn-primary px-3" onclick="saveSection('idproof')">
          <i class="bi bi-save me-1"></i>Save
        </button>
      </div>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label fw-medium">ID Proof Type</label>
          <input type="text" class="form-control" value="Aadhaar Card" disabled>
          <input type="hidden" id="id_type" value="<?= (int)($aadhaar_type['id'] ?? 0) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-medium">Aadhaar Number <span class="text-danger">*</span></label>
          <input type="text" id="id_number" value="<?= e($athlete['id_proof_number'] ?? '') ?>"
                 class="form-control" maxlength="14" placeholder="12-digit Aadhaar">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-medium">Upload Aadhaar</label>
          <input type="file" id="id_file" class="form-control"
                 accept="image/jpeg,image/png,application/pdf">
          <?php if (!empty($athlete['id_proof_file'])): ?>
            <small class="text-success mt-1 d-block">
              <i class="bi bi-check-circle me-1"></i>Uploaded
              <a href="<?= e($athlete['id_proof_file']) ?>" target="_blank">View</a>
            </small>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Date of Birth Proof (used when Aadhaar doesn't carry DOB) -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-calendar-check me-2"></i>Date of Birth Proof</h6>
        <button type="button" class="btn btn-sm btn-primary px-3" onclick="saveSection('dobproof')">
          <i class="bi bi-save me-1"></i>Save
        </button>
      </div>
      <div class="alert alert-info py-2 small mb-3">
        <i class="bi bi-info-circle me-1"></i>
        <strong>Is Aadhaar doesn't have Date of Birth?</strong>
        Provide an alternate proof of date of birth here.
      </div>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label fw-medium">DOB Proof Type</label>
          <select id="dob_type" class="form-select">
            <option value="">-- Select --</option>
            <?php foreach ($dob_proof_types as $ip): ?>
              <option value="<?= (int)$ip['id'] ?>" <?= (int)($athlete['dob_proof_type_id'] ?? 0) === (int)$ip['id'] ? 'selected' : '' ?>>
                <?= e($ip['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">Driving Licence · Birth Certificate · School Certificate · Passport</small>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-medium">Document Number</label>
          <input type="text" id="dob_number" value="<?= e($athlete['dob_proof_number'] ?? '') ?>"
                 class="form-control" placeholder="e.g. DL-12345 / BC-001">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-medium">Upload DOB Proof</label>
          <input type="file" id="dob_file" class="form-control"
                 accept="image/jpeg,image/png,application/pdf">
          <?php if (!empty($athlete['dob_proof_file'])): ?>
            <small class="text-success mt-1 d-block">
              <i class="bi bi-check-circle me-1"></i>Uploaded
              <a href="<?= e($athlete['dob_proof_file']) ?>" target="_blank">View</a>
            </small>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Sports Preferences -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-trophy me-2"></i>Preferred Sports</h6>
        <button type="button" class="btn btn-sm btn-primary px-3" onclick="saveSection('sports')">
          <i class="bi bi-save me-1"></i>Save
        </button>
      </div>
      <div class="row g-2">
        <?php foreach ($sports as $sport): ?>
        <?php $checked = in_array($sport['id'], $athleteSportIds); ?>
        <div class="col-md-6">
          <div class="border rounded-3 p-3 sms-sport-row">
            <div class="form-check mb-2">
              <input class="form-check-input sport-check" type="checkbox"
                     name="sports[<?= $sport['id'] ?>][selected]"
                     id="sport_<?= $sport['id'] ?>" value="1"
                     <?= $checked ? 'checked' : '' ?>>
              <label class="form-check-label fw-medium" for="sport_<?= $sport['id'] ?>">
                <?= e($sport['name']) ?>
              </label>
            </div>
            <div class="sport-fields ps-4" style="<?= $checked ? '' : 'display:none' ?>">
              <div class="mb-2">
                <input type="text" name="sports[<?= $sport['id'] ?>][sport_specific_id]"
                       value="<?= e($athleteSportMap[$sport['id']]['sport_specific_id'] ?? '') ?>"
                       class="form-control form-control-sm"
                       placeholder="Sport-specific ID (e.g. Shooter ID)">
              </div>
              <textarea name="sports[<?= $sport['id'] ?>][licenses]"
                        class="form-control form-control-sm" rows="2"
                        placeholder="Licences / Registrations"><?= e($athleteSportMap[$sport['id']]['licenses'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="d-flex justify-content-end">
      <a href="/athlete/dashboard" class="btn btn-light px-4">Back to Dashboard</a>
    </div>

  </div>
</div>

<!-- ── Cropper Modal ─────────────────────────────────────────────────────── -->
<div class="modal fade" id="cropperModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-semibold"><i class="bi bi-crop me-2"></i>Crop Passport Photo</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-3">
        <div style="max-height:420px;overflow:hidden">
          <img id="cropperImg" src="" alt="Crop" style="max-width:100%;display:block">
        </div>
        <small class="text-muted d-block mt-2">Drag to reposition · Scroll to zoom · 1:1 crop</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary fw-semibold" onclick="applyCrop()">
          <i class="bi bi-check-lg me-1"></i>Use Photo
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Cropper.js -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>

<script>
const CSRF = '<?= e($csrfToken) ?>';

/* ── Toast ───────────────────────────────────────────────────────────────── */
function showToast(msg, type) {
  type = type || 'success';
  const el  = document.getElementById('profileToast');
  const btn = el.querySelector('.btn-close');
  el.className = 'toast align-items-center border-0 text-bg-' + type;
  btn.className = 'btn-close' + (type === 'success' || type === 'danger' ? ' btn-close-white' : '') + ' me-2 m-auto';
  document.getElementById('toastMsg').textContent = msg;
  if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
    bootstrap.Toast.getOrCreateInstance(el, { delay: 3500 }).show();
  } else {
    // Bootstrap JS hasn't finished loading — fall back to a visible alert.
    alert(msg);
  }
}

/* ── Section AJAX Save ───────────────────────────────────────────────────── */
async function saveSection(section) {
  const btn = document.querySelector(`button[onclick="saveSection('${section}')"]`);
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }

  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('section', section);

  if (section === 'personal') {
    fd.append('name',                  document.getElementById('p_name').value);
    fd.append('date_of_birth',         document.getElementById('p_dob').value);
    fd.append('gender',                document.getElementById('p_gender').value);
    fd.append('mobile',                document.getElementById('p_mobile').value);
    fd.append('whatsapp_number',       document.getElementById('p_whatsapp').value);
    fd.append('weight',                document.getElementById('p_weight').value);
    fd.append('height',                document.getElementById('p_height').value);
    const guardian = document.getElementById('p_guardian');
    fd.append('guardian_name',         guardian ? guardian.value : '');
    fd.append('address',               document.getElementById('p_address').value);
    fd.append('communication_address', document.getElementById('p_comm_address').value);
  }

  if (section === 'location') {
    fd.append('country_id',  document.getElementById('l_country').value);
    fd.append('state_id',    document.getElementById('l_state').value);
    fd.append('district_id', document.getElementById('l_district').value);
    fd.append('nationality', document.getElementById('l_nationality').value);
  }

  if (section === 'idproof') {
    fd.append('id_proof_type_id', document.getElementById('id_type').value);
    fd.append('id_proof_number',  document.getElementById('id_number').value);
    const fileEl = document.getElementById('id_file');
    if (fileEl.files[0]) fd.append('id_proof_file', fileEl.files[0]);
  }

  if (section === 'dobproof') {
    fd.append('dob_proof_type_id', document.getElementById('dob_type').value);
    fd.append('dob_proof_number',  document.getElementById('dob_number').value);
    const dobFile = document.getElementById('dob_file');
    if (dobFile.files[0]) fd.append('dob_proof_file', dobFile.files[0]);
  }

  if (section === 'sports') {
    document.querySelectorAll('.sport-check:checked').forEach(cb => {
      const sid = cb.name.match(/sports\[(\d+)\]/)[1];
      fd.append('sports[' + sid + '][selected]', '1');
      const row  = cb.closest('.sms-sport-row');
      const ssid = row.querySelector('input[name="sports[' + sid + '][sport_specific_id]"]');
      const lic  = row.querySelector('textarea[name="sports[' + sid + '][licenses]"]');
      if (ssid) fd.append('sports[' + sid + '][sport_specific_id]', ssid.value);
      if (lic)  fd.append('sports[' + sid + '][licenses]', lic.value);
    });
  }

  try {
    const res  = await fetch('/athlete/profile/save', { method: 'POST', body: fd });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'danger');
  } catch (e) {
    showToast('Network error. Please try again.', 'danger');
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-save me-1"></i>Save'; }
  }
}

/* ── Submit Profile ──────────────────────────────────────────────────────── */
async function submitProfile() {
  const btn = document.getElementById('submitProfileBtn');
  btn.disabled = true;
  const orig = btn.innerHTML;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';

  const fd = new FormData();
  fd.append('_token', CSRF);

  try {
    const res  = await fetch('/athlete/profile/submit', { method: 'POST', body: fd });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'warning');
    if (data.success) {
      const badge = document.getElementById('completeBadge');
      badge.className = 'badge bg-success px-3 py-2';
      badge.innerHTML = '<i class="bi bi-check-circle me-1"></i>Profile Complete';
      setTimeout(() => { window.location.href = data.redirect || '/athlete/dashboard'; }, 800);
    }
  } catch (e) {
    showToast('Network error. Please try again.', 'danger');
  } finally {
    btn.disabled = false;
    btn.innerHTML = orig;
  }
}

/* ── Minor check ─────────────────────────────────────────────────────────── */
function checkMinor() {
  const dob = document.getElementById('p_dob').value;
  if (!dob) return;
  const age   = Math.floor((Date.now() - new Date(dob).getTime()) / (365.25 * 86400000));
  const field = document.getElementById('guardianField');
  if (age < 18) { field.style.display = 'block'; }
  else          { field.style.display = 'none'; }
}

/* ── Location dropdowns ──────────────────────────────────────────────────── */
function loadStates(countryId) {
  fetch('/api/states/' + countryId)
    .then(r => r.json())
    .then(data => {
      const sel = document.getElementById('l_state');
      sel.innerHTML = '<option value="">-- Select State --</option>';
      data.forEach(s => sel.insertAdjacentHTML('beforeend',
        '<option value="' + s.id + '">' + s.name + '</option>'));
      document.getElementById('l_district').innerHTML = '<option value="">-- Select District --</option>';
    });
}

function loadDistricts(stateId) {
  if (!stateId) return;
  fetch('/api/districts/' + stateId)
    .then(r => r.json())
    .then(data => {
      const sel = document.getElementById('l_district');
      sel.innerHTML = '<option value="">-- Select District --</option>';
      data.forEach(d => sel.insertAdjacentHTML('beforeend',
        '<option value="' + d.id + '">' + d.name + '</option>'));
    });
}

/* ── Sport checkboxes ────────────────────────────────────────────────────── */
document.querySelectorAll('.sport-check').forEach(function(cb) {
  cb.addEventListener('change', function() {
    this.closest('.sms-sport-row').querySelector('.sport-fields').style.display =
      this.checked ? 'block' : 'none';
  });
});

/* ── Cropper.js ──────────────────────────────────────────────────────────── */
let cropper = null;
let _cropModal = null;
function getCropModal() {
  // Lazy-init: bootstrap.bundle.min.js is loaded at the END of the layout,
  // AFTER this inline script parses. Touching `bootstrap` at parse time
  // throws ReferenceError and silently breaks the whole upload flow.
  if (!_cropModal) {
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
      showToast('Page is still loading. Please try again in a moment.', 'warning');
      return null;
    }
    _cropModal = new bootstrap.Modal(document.getElementById('cropperModal'));
  }
  return _cropModal;
}

function initCropper(input) {
  if (!input.files || !input.files[0]) return;
  const file = input.files[0];

  if (!/^image\/(jpeg|png|webp)$/i.test(file.type)) {
    showToast('Please choose a JPG, PNG or WEBP image.', 'danger');
    input.value = '';
    return;
  }
  if (file.size > 2 * 1024 * 1024) {
    showToast('Image is larger than 2 MB.', 'danger');
    input.value = '';
    return;
  }

  const reader = new FileReader();
  reader.onload = function(e) {
    const img = document.getElementById('cropperImg');
    const modalEl = document.getElementById('cropperModal');

    // Attach the listener BEFORE show() so we never miss the event.
    modalEl.addEventListener('shown.bs.modal', function startCrop() {
      const buildCropper = () => {
        if (cropper) cropper.destroy();
        cropper = new Cropper(img, {
          aspectRatio: 1,
          viewMode: 1,
          dragMode: 'move',
          autoCropArea: 0.9,
          guides: true,
          center: true,
          highlight: false,
          toggleDragModeOnDblclick: false,
        });
      };
      // Ensure the image is fully decoded before initializing Cropper —
      // otherwise the cropper can render with width/height 0 and silently
      // produce empty crops.
      if (img.complete && img.naturalWidth > 0) {
        buildCropper();
      } else {
        img.addEventListener('load', buildCropper, { once: true });
      }
    }, { once: true });

    img.src = e.target.result;
    const m = getCropModal();
    if (m) m.show();
  };
  reader.onerror = function() {
    showToast('Failed to read the selected file.', 'danger');
    input.value = '';
  };
  reader.readAsDataURL(file);
}

function applyCrop() {
  if (!cropper) return;
  document.getElementById('photoSaving').classList.remove('d-none');

  let canvas;
  try {
    canvas = cropper.getCroppedCanvas({
      width: 400,
      height: 400,
      fillColor: '#fff',
      imageSmoothingQuality: 'high',
    });
  } catch (e) {
    canvas = null;
  }

  if (!canvas) {
    document.getElementById('photoSaving').classList.add('d-none');
    showToast('Could not generate the cropped image. Please re-select the photo.', 'danger');
    return;
  }

  const m = getCropModal();
  if (m) m.hide();

  canvas.toBlob(async function(blob) {
    if (!blob) {
      document.getElementById('photoSaving').classList.add('d-none');
      showToast('Could not encode the cropped image. Please try a different photo.', 'danger');
      return;
    }

    const fd = new FormData();
    fd.append('_token', CSRF);
    fd.append('section', 'photo');
    fd.append('passport_photo', blob, 'photo.jpg');

    try {
      const res  = await fetch('/athlete/profile/save', { method: 'POST', body: fd });
      let data;
      try { data = await res.json(); }
      catch (_) { data = { success: false, message: 'Server returned an invalid response (HTTP ' + res.status + ').' }; }

      showToast(data.message || (data.success ? 'Photo updated!' : 'Upload failed.'),
                data.success ? 'success' : 'danger');

      if (data.success && data.photo_url) {
        const container = document.getElementById('photoPreview');
        const existing  = document.getElementById('currentPhoto');
        const url = data.photo_url + (data.photo_url.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now();
        if (existing && existing.tagName === 'IMG') {
          existing.src = url;
        } else {
          container.innerHTML =
            '<img src="' + url + '" alt="Photo" id="currentPhoto"' +
            ' class="rounded-circle" width="130" height="130"' +
            ' style="object-fit:cover;border:3px solid #e2e8f0">';
        }
      }
    } catch (e) {
      showToast('Network error while uploading the photo. Please try again.', 'danger');
    } finally {
      document.getElementById('photoSaving').classList.add('d-none');
      if (cropper) { cropper.destroy(); cropper = null; }
      document.getElementById('photoFileInput').value = '';
    }
  }, 'image/jpeg', 0.92);
}

document.getElementById('cropperModal').addEventListener('hidden.bs.modal', function() {
  if (cropper) { cropper.destroy(); cropper = null; }
  document.getElementById('photoFileInput').value = '';
});
</script>
