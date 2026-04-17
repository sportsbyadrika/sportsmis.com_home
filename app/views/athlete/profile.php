<?php
$pageTitle = 'My Profile';
$dob = $athlete['date_of_birth'] ?? '';
$minor = $dob && ageFromDob($dob) < 18;
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h5 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2"></i>Athlete Profile</h5>
  <?php if (!$athlete['profile_completed']): ?>
    <span class="badge bg-warning text-dark px-3 py-2">
      <i class="bi bi-exclamation-triangle me-1"></i>Profile Incomplete
    </span>
  <?php else: ?>
    <span class="badge bg-success px-3 py-2"><i class="bi bi-check-circle me-1"></i>Profile Complete</span>
  <?php endif; ?>
</div>

<form method="POST" action="/athlete/profile" enctype="multipart/form-data" novalidate>
  <?= csrf() ?>

  <div class="row g-4">

    <!-- Photo Column -->
    <div class="col-lg-3">
      <div class="sms-card p-4 text-center">
        <div class="mb-3">
          <?php if ($athlete['passport_photo']): ?>
            <img src="<?= e($athlete['passport_photo']) ?>" alt="Photo"
                 class="rounded-circle" width="130" height="130" style="object-fit:cover;border:3px solid #e2e8f0">
          <?php else: ?>
            <div class="sms-avatar sms-avatar-xl mx-auto mb-2"><?= avatarInitials($athlete['name']) ?></div>
          <?php endif; ?>
        </div>
        <label class="form-label fw-medium">Passport Photo <span class="text-danger">*</span></label>
        <input type="file" name="passport_photo" class="form-control form-control-sm <?= hasError('passport_photo') ?>"
               accept="image/jpeg,image/png,image/webp">
        <?= fieldError('passport_photo') ?>
        <small class="text-muted d-block mt-1">JPG/PNG · Max 2 MB<br>Passport size (white background)</small>
      </div>
    </div>

    <!-- Details Column -->
    <div class="col-lg-9">

      <!-- Personal Info -->
      <div class="sms-card p-4 mb-4">
        <h6 class="fw-semibold border-bottom pb-2 mb-3">Personal Information</h6>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-medium">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="name" value="<?= e(old('name', $athlete['name'])) ?>"
                   class="form-control <?= hasError('name') ?>" required>
            <?= fieldError('name') ?>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-medium">Date of Birth <span class="text-danger">*</span></label>
            <input type="date" name="date_of_birth" id="dob"
                   value="<?= e(old('date_of_birth', $athlete['date_of_birth'] ?? '')) ?>"
                   class="form-control <?= hasError('date_of_birth') ?>"
                   max="<?= date('Y-m-d') ?>" required onchange="checkMinor()">
            <?= fieldError('date_of_birth') ?>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-medium">Gender <span class="text-danger">*</span></label>
            <select name="gender" class="form-select <?= hasError('gender') ?>" required>
              <option value="">Select</option>
              <?php foreach (['male'=>'Male','female'=>'Female','other'=>'Other'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= old('gender',$athlete['gender']??'') === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
            <?= fieldError('gender') ?>
          </div>

          <div class="col-md-4">
            <label class="form-label fw-medium">Mobile <span class="text-danger">*</span></label>
            <input type="tel" name="mobile" value="<?= e(old('mobile', $athlete['mobile'] ?? '')) ?>"
                   class="form-control <?= hasError('mobile') ?>" maxlength="10" required>
            <?= fieldError('mobile') ?>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-medium">WhatsApp Number</label>
            <input type="tel" name="whatsapp_number" value="<?= e(old('whatsapp_number', $athlete['whatsapp_number'] ?? '')) ?>"
                   class="form-control" maxlength="10">
          </div>
          <div class="col-md-2">
            <label class="form-label fw-medium">Weight (kg)</label>
            <input type="number" name="weight" value="<?= e(old('weight', $athlete['weight'] ?? '')) ?>"
                   class="form-control" min="20" max="300" step="0.1">
          </div>
          <div class="col-md-2">
            <label class="form-label fw-medium">Height (cm)</label>
            <input type="number" name="height" value="<?= e(old('height', $athlete['height'] ?? '')) ?>"
                   class="form-control" min="50" max="300" step="0.1">
          </div>

          <!-- Guardian (shows for minors) -->
          <div class="col-md-6" id="guardianField" style="<?= $minor ? '' : 'display:none' ?>">
            <label class="form-label fw-medium">
              Guardian Name <span class="text-danger" id="guardianRequired">*</span>
              <small class="text-warning fw-normal">(Required for age &lt; 18)</small>
            </label>
            <input type="text" name="guardian_name" value="<?= e(old('guardian_name', $athlete['guardian_name'] ?? '')) ?>"
                   class="form-control <?= hasError('guardian_name') ?>" id="guardianInput"
                   <?= $minor ? 'required' : '' ?>>
            <?= fieldError('guardian_name') ?>
          </div>

          <div class="col-12">
            <label class="form-label fw-medium">Address <span class="text-danger">*</span></label>
            <textarea name="address" rows="2" class="form-control <?= hasError('address') ?>"
                      required><?= e(old('address', $athlete['address'] ?? '')) ?></textarea>
            <?= fieldError('address') ?>
          </div>
          <div class="col-12">
            <label class="form-label fw-medium">Communication Address</label>
            <textarea name="communication_address" rows="2"
                      class="form-control"><?= e(old('communication_address', $athlete['communication_address'] ?? '')) ?></textarea>
            <small class="text-muted">Leave blank if same as above.</small>
          </div>
        </div>
      </div>

      <!-- Location -->
      <div class="sms-card p-4 mb-4">
        <h6 class="fw-semibold border-bottom pb-2 mb-3">Location</h6>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label fw-medium">Country</label>
            <select name="country_id" id="countrySelect" class="form-select" onchange="loadStates(this.value)">
              <?php foreach ($countries as $c): ?>
                <option value="<?= $c['id'] ?>" <?= old('country_id',$athlete['country_id']??1) == $c['id'] ? 'selected' : '' ?>>
                  <?= e($c['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-medium">State</label>
            <select name="state_id" id="stateSelect" class="form-select" onchange="loadDistricts(this.value)">
              <option value="">-- Select State --</option>
              <?php foreach ($states as $s): ?>
                <option value="<?= $s['id'] ?>" <?= old('state_id',$athlete['state_id']??'') == $s['id'] ? 'selected' : '' ?>>
                  <?= e($s['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-medium">District</label>
            <select name="district_id" id="districtSelect" class="form-select">
              <option value="">-- Select District --</option>
              <?php foreach ($districts as $d): ?>
                <option value="<?= $d['id'] ?>" <?= old('district_id',$athlete['district_id']??'') == $d['id'] ? 'selected' : '' ?>>
                  <?= e($d['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-medium">Nationality <span class="text-danger">*</span></label>
            <input type="text" name="nationality" value="<?= e(old('nationality', $athlete['nationality'] ?? 'Indian')) ?>"
                   class="form-control <?= hasError('nationality') ?>" required>
            <?= fieldError('nationality') ?>
          </div>
        </div>
      </div>

      <!-- ID Proof -->
      <div class="sms-card p-4 mb-4">
        <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-card-text me-2"></i>ID Proof</h6>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label fw-medium">ID Proof Type</label>
            <select name="id_proof_type_id" class="form-select">
              <option value="">-- Select --</option>
              <?php foreach ($id_proofs as $ip): ?>
                <option value="<?= $ip['id'] ?>" <?= old('id_proof_type_id',$athlete['id_proof_type_id']??'') == $ip['id'] ? 'selected' : '' ?>>
                  <?= e($ip['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-medium">ID Number</label>
            <input type="text" name="id_proof_number" value="<?= e(old('id_proof_number', $athlete['id_proof_number'] ?? '')) ?>"
                   class="form-control" placeholder="e.g. Aadhaar number">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-medium">Upload ID Proof</label>
            <input type="file" name="id_proof_file" class="form-control <?= hasError('id_proof_file') ?>"
                   accept="image/jpeg,image/png,application/pdf">
            <?= fieldError('id_proof_file') ?>
            <?php if ($athlete['id_proof_file']): ?>
              <small class="text-success"><i class="bi bi-check-circle me-1"></i>Uploaded
                <a href="<?= e($athlete['id_proof_file']) ?>" target="_blank">View</a>
              </small>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Sports Preferences -->
      <div class="sms-card p-4 mb-4">
        <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-trophy me-2"></i>Preferred Sports</h6>
        <div class="row g-2">
          <?php
          $athleteSportIds = array_column($athlete_sports, 'sport_id');
          $athleteSportMap = array_column($athlete_sports, null, 'sport_id');
          ?>
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

      <div class="d-flex gap-2 justify-content-end">
        <a href="/athlete/dashboard" class="btn btn-light">Cancel</a>
        <button type="submit" class="btn btn-primary px-4">
          <i class="bi bi-check-circle me-2"></i>Save Profile
        </button>
      </div>

    </div>
  </div>
</form>

<script>
function checkMinor() {
  const dob = document.getElementById('dob').value;
  if (!dob) return;
  const age = Math.floor((new Date() - new Date(dob)) / (365.25 * 24 * 3600 * 1000));
  const field = document.getElementById('guardianField');
  const input = document.getElementById('guardianInput');
  if (age < 18) { field.style.display = 'block'; input.required = true; }
  else          { field.style.display = 'none';  input.required = false; }
}

function loadStates(countryId) {
  fetch('/api/states/' + countryId)
    .then(r => r.json())
    .then(data => {
      const sel = document.getElementById('stateSelect');
      sel.innerHTML = '<option value="">-- Select State --</option>';
      data.forEach(s => sel.insertAdjacentHTML('beforeend', `<option value="${s.id}">${s.name}</option>`));
      document.getElementById('districtSelect').innerHTML = '<option value="">-- Select District --</option>';
    });
}

function loadDistricts(stateId) {
  if (!stateId) return;
  fetch('/api/districts/' + stateId)
    .then(r => r.json())
    .then(data => {
      const sel = document.getElementById('districtSelect');
      sel.innerHTML = '<option value="">-- Select District --</option>';
      data.forEach(d => sel.insertAdjacentHTML('beforeend', `<option value="${d.id}">${d.name}</option>`));
    });
}

document.querySelectorAll('.sport-check').forEach(cb => {
  cb.addEventListener('change', function() {
    this.closest('.sms-sport-row').querySelector('.sport-fields').style.display = this.checked ? 'block' : 'none';
  });
});
</script>
