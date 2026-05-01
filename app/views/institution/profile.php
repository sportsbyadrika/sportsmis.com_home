<?php
$pageTitle = 'Institution Profile';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
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
  <div class="d-flex align-items-center gap-2">
    <a href="/institution/dashboard" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h5 class="mb-0 fw-bold"><i class="bi bi-building me-2"></i>Institution Profile</h5>
  </div>
  <div class="d-flex align-items-center gap-2">
    <span id="completeBadge" class="badge px-3 py-2 <?= !empty($institution['profile_completed']) ? 'bg-success' : 'bg-warning text-dark' ?>">
      <?php if (!empty($institution['profile_completed'])): ?>
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

  <!-- ── Logo Column ───────────────────────────────────────────────────── -->
  <div class="col-lg-3">
    <div class="sms-card p-4 text-center">
      <div class="mb-3" id="logoPreview">
        <?php if (!empty($institution['logo'])): ?>
          <img src="<?= e($institution['logo']) ?>?t=<?= time() ?>" alt="Logo" id="currentLogo"
               class="rounded-3" width="140" height="140"
               style="object-fit:contain;border:1px solid #e2e8f0;background:#fff"
               onerror="this.onerror=null;this.replaceWith(Object.assign(document.createElement('div'),{id:'currentLogo',className:'sms-avatar sms-avatar-xl mx-auto mb-2',textContent:<?= json_encode(avatarInitials($institution['name'] ?? 'I')) ?>}));">
        <?php else: ?>
          <div class="sms-avatar sms-avatar-xl mx-auto mb-2" id="currentLogo">
            <?= avatarInitials($institution['name'] ?? 'I') ?>
          </div>
        <?php endif; ?>
      </div>
      <label class="form-label fw-medium">Institution Logo <span class="text-danger">*</span></label>
      <input type="file" id="logoFileInput" accept="image/jpeg,image/png,image/webp"
             class="d-none" onchange="initCropper(this)">
      <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-2"
              onclick="document.getElementById('logoFileInput').click()">
        <i class="bi bi-image me-1"></i>Change Logo
      </button>
      <small class="text-muted d-block">JPG/PNG/WEBP · Max 2 MB<br>Square aspect ratio</small>
      <div id="logoSaving" class="mt-2 d-none text-center">
        <div class="spinner-border spinner-border-sm text-primary"></div>
        <small class="text-muted ms-1">Uploading…</small>
      </div>
    </div>
  </div>

  <!-- ── Details Column ────────────────────────────────────────────────── -->
  <div class="col-lg-9">

    <!-- Institution Details -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0">Institution Details</h6>
        <button type="button" class="btn btn-sm btn-primary px-3" onclick="saveSection('details')">
          <i class="bi bi-save me-1"></i>Save
        </button>
      </div>
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label fw-medium">Institution Name <span class="text-danger">*</span></label>
          <input type="text" id="i_name" value="<?= e($institution['name'] ?? '') ?>" class="form-control">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-medium">Institution Type</label>
          <select id="i_type_id" class="form-select">
            <option value="">-- Select Type --</option>
            <?php foreach ($institution_types as $type): ?>
              <option value="<?= $type['id'] ?>"
                <?= (($institution['type_id'] ?? '') == $type['id']) ? 'selected' : '' ?>>
                <?= e($type['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-medium">Registration Number <span class="text-danger">*</span></label>
          <input type="text" id="i_reg_number" value="<?= e($institution['reg_number'] ?? '') ?>"
                 class="form-control" placeholder="e.g. REG/2024/001">
        </div>
        <div class="col-12">
          <label class="form-label fw-medium">Address <span class="text-danger">*</span></label>
          <textarea id="i_address" rows="3" class="form-control"><?= e($institution['address'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <!-- Contact & Affiliation -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-globe me-2"></i>Contact &amp; Affiliation</h6>
        <button type="button" class="btn btn-sm btn-primary px-3" onclick="saveSection('contact')">
          <i class="bi bi-save me-1"></i>Save
        </button>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-medium">Institution Email <span class="text-danger">*</span></label>
          <input type="email" id="i_email" value="<?= e($institution['email'] ?? '') ?>"
                 class="form-control" placeholder="contact@institution.org">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-medium">Website</label>
          <input type="url" id="i_website" value="<?= e($institution['website'] ?? '') ?>"
                 class="form-control" placeholder="https://www.institution.org">
        </div>
        <div class="col-12">
          <label class="form-label fw-medium">Affiliated To</label>
          <input type="text" id="i_affiliated_to" value="<?= e($institution['affiliated_to'] ?? '') ?>"
                 class="form-control" placeholder="e.g. CBSE, Sports Authority of India, State Sports Council">
          <small class="text-muted">Parent body / federation / board your institution is affiliated with.</small>
        </div>
      </div>
    </div>

    <!-- SPOC -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-person-badge me-2"></i>Single Point of Contact (SPOC)</h6>
        <button type="button" class="btn btn-sm btn-primary px-3" onclick="saveSection('spoc')">
          <i class="bi bi-save me-1"></i>Save
        </button>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-medium">SPOC Name <span class="text-danger">*</span></label>
          <input type="text" id="i_spoc_name" value="<?= e($institution['spoc_name'] ?? '') ?>"
                 class="form-control" placeholder="Full name">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-medium">Contact Number <span class="text-danger">*</span></label>
          <input type="tel" id="i_spoc_mobile" value="<?= e($institution['spoc_mobile'] ?? '') ?>"
                 class="form-control" maxlength="10" placeholder="10-digit">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-medium">SPOC Email</label>
          <input type="email" id="i_spoc_email" value="<?= e($institution['spoc_email'] ?? '') ?>"
                 class="form-control" placeholder="spoc@institution.org">
        </div>
      </div>
    </div>

    <!-- Registration Document -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-file-earmark-text me-2"></i>Registration Certificate</h6>
        <button type="button" id="docSaveBtn" class="btn btn-sm btn-primary px-3" onclick="saveDocument()">
          <i class="bi bi-upload me-1"></i>Upload
        </button>
      </div>
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label fw-medium">Upload Certificate <span class="text-danger">*</span></label>
          <input type="file" id="i_reg_document" class="form-control"
                 accept="image/jpeg,image/png,application/pdf">
          <small class="text-muted">JPG/PNG/PDF · Max 5 MB</small>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <div id="docStatus" class="w-100">
            <?php if (!empty($institution['reg_document'])): ?>
              <span class="text-success small">
                <i class="bi bi-check-circle me-1"></i>Uploaded
                <a href="<?= e($institution['reg_document']) ?>" target="_blank" class="ms-1">View</a>
              </span>
            <?php else: ?>
              <span class="text-muted small">No document uploaded yet.</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Validity Info -->
    <?php if (!empty($institution['validity_from'])): ?>
    <div class="sms-card p-3 mb-4 border-start border-4 border-success">
      <div class="d-flex align-items-center gap-3">
        <i class="bi bi-shield-check fs-4 text-success"></i>
        <div>
          <div class="fw-semibold">Institution Approved</div>
          <small class="text-muted">
            Valid from <strong><?= formatDate($institution['validity_from']) ?></strong>
            to <strong><?= formatDate($institution['validity_to']) ?></strong>
          </small>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-end">
      <a href="/institution/dashboard" class="btn btn-light px-4">Back to Dashboard</a>
    </div>

  </div>
</div>

<!-- ── Cropper Modal ─────────────────────────────────────────────────────── -->
<div class="modal fade" id="cropperModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-semibold"><i class="bi bi-crop me-2"></i>Crop Institution Logo</h6>
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
          <i class="bi bi-check-lg me-1"></i>Use Logo
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

  if (section === 'details') {
    fd.append('name',       document.getElementById('i_name').value);
    fd.append('type_id',    document.getElementById('i_type_id').value);
    fd.append('reg_number', document.getElementById('i_reg_number').value);
    fd.append('address',    document.getElementById('i_address').value);
  }

  if (section === 'contact') {
    fd.append('email',         document.getElementById('i_email').value);
    fd.append('website',       document.getElementById('i_website').value);
    fd.append('affiliated_to', document.getElementById('i_affiliated_to').value);
  }

  if (section === 'spoc') {
    fd.append('spoc_name',   document.getElementById('i_spoc_name').value);
    fd.append('spoc_mobile', document.getElementById('i_spoc_mobile').value);
    fd.append('spoc_email',  document.getElementById('i_spoc_email').value);
  }

  try {
    const res  = await fetch('/institution/profile/save', { method: 'POST', body: fd });
    let data;
    try { data = await res.json(); }
    catch (_) { data = { success: false, message: 'Server returned an invalid response (HTTP ' + res.status + ').' }; }
    showToast(data.message || (data.success ? 'Saved!' : 'Save failed.'), data.success ? 'success' : 'danger');
  } catch (e) {
    showToast('Network error. Please try again.', 'danger');
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-save me-1"></i>Save'; }
  }
}

/* ── Document Upload ─────────────────────────────────────────────────────── */
async function saveDocument() {
  const input = document.getElementById('i_reg_document');
  if (!input.files || !input.files[0]) {
    showToast('Please choose a file to upload.', 'warning');
    return;
  }
  const btn = document.getElementById('docSaveBtn');
  btn.disabled = true;
  const orig = btn.innerHTML;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('section', 'document');
  fd.append('reg_document', input.files[0]);

  try {
    const res  = await fetch('/institution/profile/save', { method: 'POST', body: fd });
    let data;
    try { data = await res.json(); }
    catch (_) { data = { success: false, message: 'Server returned an invalid response (HTTP ' + res.status + ').' }; }
    showToast(data.message || (data.success ? 'Uploaded!' : 'Upload failed.'), data.success ? 'success' : 'danger');
    if (data.success && data.document_url) {
      document.getElementById('docStatus').innerHTML =
        '<span class="text-success small"><i class="bi bi-check-circle me-1"></i>Uploaded ' +
        '<a href="' + data.document_url + '" target="_blank" class="ms-1">View</a></span>';
      input.value = '';
    }
  } catch (e) {
    showToast('Network error. Please try again.', 'danger');
  } finally {
    btn.disabled = false;
    btn.innerHTML = orig;
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
    const res  = await fetch('/institution/profile/submit', { method: 'POST', body: fd });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'warning');
    if (data.success) {
      const badge = document.getElementById('completeBadge');
      badge.className = 'badge bg-success px-3 py-2';
      badge.innerHTML = '<i class="bi bi-check-circle me-1"></i>Profile Complete';
      setTimeout(() => { window.location.href = data.redirect || '/institution/dashboard'; }, 800);
    }
  } catch (e) {
    showToast('Network error. Please try again.', 'danger');
  } finally {
    btn.disabled = false;
    btn.innerHTML = orig;
  }
}

/* ── Cropper.js ──────────────────────────────────────────────────────────── */
let cropper = null;
let _cropModal = null;
function getCropModal() {
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
      if (img.complete && img.naturalWidth > 0) buildCropper();
      else img.addEventListener('load', buildCropper, { once: true });
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
  document.getElementById('logoSaving').classList.remove('d-none');

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
    document.getElementById('logoSaving').classList.add('d-none');
    showToast('Could not generate the cropped logo. Please re-select the image.', 'danger');
    return;
  }

  const m = getCropModal();
  if (m) m.hide();

  canvas.toBlob(async function(blob) {
    if (!blob) {
      document.getElementById('logoSaving').classList.add('d-none');
      showToast('Could not encode the cropped logo. Please try a different image.', 'danger');
      return;
    }

    const fd = new FormData();
    fd.append('_token', CSRF);
    fd.append('section', 'logo');
    fd.append('logo', blob, 'logo.jpg');

    try {
      const res  = await fetch('/institution/profile/save', { method: 'POST', body: fd });
      let data;
      try { data = await res.json(); }
      catch (_) { data = { success: false, message: 'Server returned an invalid response (HTTP ' + res.status + ').' }; }

      showToast(data.message || (data.success ? 'Logo updated!' : 'Upload failed.'),
                data.success ? 'success' : 'danger');

      if (data.success && data.logo_url) {
        const container = document.getElementById('logoPreview');
        const existing  = document.getElementById('currentLogo');
        const url = data.logo_url + (data.logo_url.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now();
        if (existing && existing.tagName === 'IMG') {
          existing.src = url;
        } else {
          container.innerHTML =
            '<img src="' + url + '" alt="Logo" id="currentLogo"' +
            ' class="rounded-3" width="140" height="140"' +
            ' style="object-fit:contain;border:1px solid #e2e8f0;background:#fff">';
        }
      }
    } catch (e) {
      showToast('Network error while uploading the logo. Please try again.', 'danger');
    } finally {
      document.getElementById('logoSaving').classList.add('d-none');
      if (cropper) { cropper.destroy(); cropper = null; }
      document.getElementById('logoFileInput').value = '';
    }
  }, 'image/jpeg', 0.92);
}

document.getElementById('cropperModal').addEventListener('hidden.bs.modal', function() {
  if (cropper) { cropper.destroy(); cropper = null; }
  document.getElementById('logoFileInput').value = '';
});
</script>
