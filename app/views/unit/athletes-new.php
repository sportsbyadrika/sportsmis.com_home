<?php
$pageTitle = 'Add Athlete';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$old       = $old    ?? [];
$errors    = $errors ?? [];
$activeUnit = $active_unit_id;
$err = function (string $key) use ($errors): string {
    return isset($errors[$key]) ? '<div class="invalid-feedback d-block">' . htmlspecialchars((string)$errors[$key]) . '</div>' : '';
};
// Per-event Aadhaar requirement — controls the asterisk + the
// browser-side `required` attribute on both the Aadhaar number field
// and the proof-file upload. 'hide' drops the whole section. Server-side
// validation in UnitController::storeAthlete() enforces the same rule.
$aadhaarReq       = $event['aadhaar_required'] ?? 'optional';
$aadhaarMandatory = $aadhaarReq === 'mandatory';
$aadhaarHide      = $aadhaarReq === 'hide';
// Per-event DOB-proof requirement (optional / mandatory / hide).
$dobProofReq      = $event['dob_proof_required'] ?? 'optional';
$dobProofMandatory= $dobProofReq === 'mandatory';
$dobProofHide     = $dobProofReq === 'hide';
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold"><i class="bi bi-person-plus me-2"></i>Add Athlete</h5>
    <div class="text-muted small mt-1">
      Event: <strong><?= e($event['name']) ?></strong>
      · Code: <code><?= e($event['event_code'] ?? '') ?></code>
    </div>
  </div>
  <a href="/unit/dashboard" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
  </a>
</div>

<?= flashBag() ?>

<div class="sms-card p-4">
  <p class="text-muted small">
    Create a new athlete on this event under your Unit. Email is optional &mdash;
    leave it blank for a managed athlete who won&rsquo;t log in. You can add
    sport-events and submit the registration from the dashboard once the
    athlete is created.
  </p>

  <form method="POST" action="/unit/athletes" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

    <div class="row g-3">
      <?php if (count($units) > 1): ?>
        <div class="col-md-6">
          <label class="form-label fw-medium">Unit / Club <span class="text-danger">*</span></label>
          <select name="unit_id" class="form-select form-select-sm" required>
            <?php foreach ($units as $u): ?>
              <option value="<?= (int)$u['id'] ?>"
                      <?= (int)($old['unit_id'] ?? $activeUnit) === (int)$u['id'] ? 'selected' : '' ?>>
                <?= e($u['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php else: ?>
        <input type="hidden" name="unit_id" value="<?= (int)($units[0]['id'] ?? 0) ?>">
      <?php endif; ?>

      <div class="col-md-6">
        <label class="form-label fw-medium">Passport Photo
          <small class="text-muted">(optional)</small></label>
        <div class="d-flex align-items-center gap-3">
          <div id="photoPreview" class="flex-shrink-0">
            <div class="sms-avatar mx-auto d-flex align-items-center justify-content-center text-muted"
                 id="currentPhoto"
                 style="width:64px;height:64px;border-radius:.5rem;border:1px dashed #cbd5e1;background:#f8fafc;">
              <i class="bi bi-person"></i>
            </div>
          </div>
          <div class="flex-grow-1">
            <!-- Chooser only opens the cropper; the cropped JPEG is written
                 into the hidden #passportPhotoFinal input that is submitted. -->
            <input type="file" id="photoFileInput" accept="image/jpeg,image/png,image/webp"
                   class="form-control form-control-sm" onchange="initCropper(this)">
            <input type="file" name="passport_photo" id="passportPhotoFinal" class="d-none">
            <small class="text-muted d-block mt-1">JPG/PNG/WEBP · Passport size, white background. You can crop after selecting.</small>
          </div>
        </div>
      </div>

      <div class="col-md-8">
        <label class="form-label fw-medium">Full Name <span class="text-danger">*</span></label>
        <input type="text" name="name" maxlength="255" required
               class="form-control form-control-sm <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
               value="<?= e($old['name'] ?? '') ?>" placeholder="As per identity document">
        <?= $err('name') ?>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-medium">Gender <span class="text-danger">*</span></label>
        <select name="gender" required
                class="form-select form-select-sm <?= isset($errors['gender']) ? 'is-invalid' : '' ?>">
          <option value="">— Select —</option>
          <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $g => $lbl): ?>
            <option value="<?= $g ?>" <?= ($old['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
        <?= $err('gender') ?>
      </div>

      <div class="col-md-4">
        <label class="form-label fw-medium">Date of Birth <span class="text-danger">*</span></label>
        <input type="date" name="date_of_birth" max="<?= date('Y-m-d') ?>" required
               class="form-control form-control-sm <?= isset($errors['date_of_birth']) ? 'is-invalid' : '' ?>"
               value="<?= e($old['date_of_birth'] ?? '') ?>">
        <?= $err('date_of_birth') ?>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-medium">Mobile <small class="text-muted">(optional)</small></label>
        <input type="tel" name="mobile" maxlength="10" inputmode="numeric" pattern="\d{10}"
               class="form-control form-control-sm <?= isset($errors['mobile']) ? 'is-invalid' : '' ?>"
               value="<?= e($old['mobile'] ?? '') ?>" placeholder="10-digit">
        <?= $err('mobile') ?>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-medium">Email <small class="text-muted">(optional &mdash; enables athlete login)</small></label>
        <input type="email" name="email" maxlength="255"
               class="form-control form-control-sm <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
               value="<?= e($old['email'] ?? '') ?>">
        <?= $err('email') ?>
      </div>

      <?php $pwdOld = strtolower((string)($old['pwd_status'] ?? 'no')); if (!in_array($pwdOld, ['no','deaf','para'], true)) $pwdOld = 'no'; ?>
      <div class="col-md-4">
        <label class="form-label fw-medium">Is Person with Disability (PwD)?</label>
        <select name="pwd_status" class="form-select form-select-sm">
          <?php foreach (['no' => 'No', 'deaf' => 'Deaf', 'para' => 'Para'] as $v => $l): ?>
            <option value="<?= $v ?>" <?= $pwdOld === $v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- ID Proof — Aadhaar (number + file grouped, like the athlete profile) -->
      <?php if (!$aadhaarHide): ?>
      <div class="col-12">
        <div class="border rounded-3 p-3 bg-light-subtle">
          <div class="small fw-semibold mb-2">
            <i class="bi bi-card-text me-1"></i>ID Proof — Aadhaar
            <?php if ($aadhaarMandatory): ?>
              <span class="text-danger">*</span>
            <?php else: ?>
              <small class="text-muted fw-normal">(optional)</small>
            <?php endif; ?>
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label fw-medium">Aadhaar Number
                <?php if ($aadhaarMandatory): ?>
                  <span class="text-danger">*</span>
                <?php else: ?>
                  <small class="text-muted">(optional)</small>
                <?php endif; ?>
              </label>
              <input type="text" name="id_proof_number" inputmode="numeric" pattern="\d{12}" maxlength="12"
                     <?= $aadhaarMandatory ? 'required' : '' ?>
                     class="form-control form-control-sm <?= isset($errors['id_proof_number']) ? 'is-invalid' : '' ?>"
                     value="<?= e($old['id_proof_number'] ?? '') ?>" placeholder="12-digit">
              <?= $err('id_proof_number') ?>
            </div>
            <div class="col-md-8">
              <label class="form-label fw-medium">Aadhaar Proof File
                <?php if ($aadhaarMandatory): ?>
                  <span class="text-danger">*</span>
                <?php else: ?>
                  <small class="text-muted">(optional)</small>
                <?php endif; ?>
              </label>
              <input type="file" name="id_proof_file" class="form-control form-control-sm <?= isset($errors['id_proof_file']) ? 'is-invalid' : '' ?>"
                     <?= $aadhaarMandatory ? 'required' : '' ?>
                     accept="image/jpeg,image/png,image/webp,application/pdf">
              <?= $err('id_proof_file') ?>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Date of Birth Proof — alternate proof when Aadhaar doesn't carry DOB -->
      <?php if (!$dobProofHide): ?>
      <div class="col-12">
        <div class="border rounded-3 p-3 bg-light-subtle">
          <div class="small fw-semibold mb-1">
            <i class="bi bi-calendar-check me-1"></i>Date of Birth Proof
            <?php if ($dobProofMandatory): ?>
              <span class="text-danger">*</span>
            <?php else: ?>
              <small class="text-muted fw-normal">(optional)</small>
            <?php endif; ?>
          </div>
          <div class="text-muted small mb-2">
            If the Aadhaar doesn&rsquo;t carry the date of birth, provide an alternate proof here.
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label fw-medium">DOB Proof Type
                <?php if ($dobProofMandatory): ?><span class="text-danger">*</span><?php endif; ?>
              </label>
              <select name="dob_proof_type_id" class="form-select form-select-sm <?= isset($errors['dob_proof_type_id']) ? 'is-invalid' : '' ?>"
                      <?= $dobProofMandatory ? 'required' : '' ?>>
                <option value="">— Select —</option>
                <?php foreach (($dob_proof_types ?? []) as $ip): ?>
                  <option value="<?= (int)$ip['id'] ?>"
                          <?= (int)($old['dob_proof_type_id'] ?? 0) === (int)$ip['id'] ? 'selected' : '' ?>>
                    <?= e($ip['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">Driving Licence · Birth Certificate · School Certificate · Passport</small>
              <?= $err('dob_proof_type_id') ?>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-medium">Document Number
                <?php if ($dobProofMandatory): ?><span class="text-danger">*</span><?php endif; ?>
              </label>
              <input type="text" name="dob_proof_number" maxlength="100"
                     <?= $dobProofMandatory ? 'required' : '' ?>
                     class="form-control form-control-sm <?= isset($errors['dob_proof_number']) ? 'is-invalid' : '' ?>"
                     value="<?= e($old['dob_proof_number'] ?? '') ?>" placeholder="e.g. DL-12345 / BC-001">
              <?= $err('dob_proof_number') ?>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-medium">Upload DOB Proof
                <?php if ($dobProofMandatory): ?><span class="text-danger">*</span><?php endif; ?>
              </label>
              <input type="file" name="dob_proof_file" class="form-control form-control-sm <?= isset($errors['dob_proof_file']) ? 'is-invalid' : '' ?>"
                     <?= $dobProofMandatory ? 'required' : '' ?>
                     accept="image/jpeg,image/png,image/webp,application/pdf">
              <?= $err('dob_proof_file') ?>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="col-12">
        <label class="form-label fw-medium">Address <small class="text-muted">(optional)</small></label>
        <textarea name="address" rows="2" maxlength="500"
                  class="form-control form-control-sm"><?= e($old['address'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="d-flex justify-content-end mt-3 gap-2">
      <a href="/unit/dashboard" class="btn btn-outline-secondary btn-sm">Cancel</a>
      <button type="submit" class="btn btn-primary btn-sm">
        <i class="bi bi-save me-1"></i>Create Athlete &amp; Start Registration
      </button>
    </div>
  </form>
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
        <small class="text-muted d-block mt-2">Drag to reposition · Scroll to zoom · 7:9 passport crop</small>
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
/* ── Passport photo crop ───────────────────────────────────────────────────
   The visible chooser (#photoFileInput) only opens the cropper. The cropped
   7:9 JPEG is written into the hidden #passportPhotoFinal input that the form
   actually submits as `passport_photo`. */
let cropper = null;
let _cropModal = null;

function getCropModal() {
  if (!_cropModal) {
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
      alert('Page is still loading. Please try again in a moment.');
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
    alert('Please choose a JPG, PNG or WEBP image.');
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
          aspectRatio: 7 / 9,
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
    alert('Failed to read the selected file.');
    input.value = '';
  };
  reader.readAsDataURL(file);
}

function applyCrop() {
  if (!cropper) return;
  let canvas;
  try {
    canvas = cropper.getCroppedCanvas({
      width: 350,
      height: 450,
      fillColor: '#fff',
      imageSmoothingQuality: 'high',
    });
  } catch (e) { canvas = null; }
  if (!canvas) {
    alert('Could not generate the cropped image. Please re-select the photo.');
    return;
  }
  const m = getCropModal();
  if (m) m.hide();

  canvas.toBlob(function(blob) {
    if (!blob) {
      alert('Could not encode the cropped image. Please try a different photo.');
      return;
    }
    // Write the cropped JPEG into the submitted file input.
    const finalInput = document.getElementById('passportPhotoFinal');
    const dt = new DataTransfer();
    dt.items.add(new File([blob], 'photo.jpg', { type: 'image/jpeg' }));
    finalInput.files = dt.files;

    // Refresh the preview thumbnail.
    const url = URL.createObjectURL(blob);
    document.getElementById('photoPreview').innerHTML =
      '<img src="' + url + '" alt="Photo" width="64" height="64"' +
      ' style="object-fit:cover;border-radius:.5rem;border:1px solid #e2e8f0">';

    if (cropper) { cropper.destroy(); cropper = null; }
  }, 'image/jpeg', 0.92);
}

document.getElementById('cropperModal').addEventListener('hidden.bs.modal', function() {
  if (cropper) { cropper.destroy(); cropper = null; }
});
</script>
