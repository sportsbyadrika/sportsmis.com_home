<?php
$pageTitle = 'Edit Event';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$eventId = (int)$event['id'];
$paymentModes = $event['payment_modes'] ?? [];
$eventSports  = $event['sports'] ?? [];
$units        = $units ?? [];
$nocRequired  = $event['noc_required'] ?? 'optional';
?>

<!-- Toast -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="evToast" class="toast align-items-center border-0" role="alert" aria-live="assertive">
    <div class="d-flex">
      <div class="toast-body fw-medium" id="toastMsg"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<!-- Header -->
<?php
  $statusMap = [
    'draft'     => ['label' => 'Draft',     'class' => 'bg-secondary'],
    'active'    => ['label' => 'Active',    'class' => 'bg-success'],
    'completed' => ['label' => 'Completed', 'class' => 'bg-info text-dark'],
    'suspended' => ['label' => 'Suspended', 'class' => 'bg-danger'],
  ];
  $currentStatus = $event['status'] ?? 'draft';
  if (!isset($statusMap[$currentStatus])) $currentStatus = 'draft';
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2">
    <a href="/institution/events" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-event me-2"></i><?= e($event['name']) ?></h5>
    <span id="statusBadge" class="badge ms-1 <?= $statusMap[$currentStatus]['class'] ?>"><?= $statusMap[$currentStatus]['label'] ?></span>
  </div>
  <div class="d-flex align-items-center gap-2">
    <label for="ev_status" class="form-label small mb-0 me-1 text-muted">Status</label>
    <select id="ev_status" class="form-select form-select-sm" style="width:160px">
      <?php foreach ($statusMap as $k => $v): ?>
        <option value="<?= $k ?>" <?= $currentStatus === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
      <?php endforeach; ?>
    </select>
    <button type="button" id="statusBtn" class="btn btn-primary btn-sm fw-semibold" onclick="saveSection('status')">
      <i class="bi bi-save me-1"></i>Save Status
    </button>
  </div>
</div>
<div class="alert alert-info py-2 small mb-3">
  <i class="bi bi-info-circle me-1"></i>
  Set <strong>Active</strong> to publish this event to athletes; <strong>Draft</strong> while you are still editing;
  <strong>Suspended</strong> to temporarily hide it; <strong>Completed</strong> after the event has finished.
</div>

<div class="row g-4">

  <div class="col-lg-8">

    <!-- Event Details -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0">Event Details</h6>
        <button type="button" class="btn btn-sm btn-primary px-3" onclick="saveSection('details')"><i class="bi bi-save me-1"></i>Save</button>
      </div>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label fw-medium">Event Name <span class="text-danger">*</span></label>
          <input type="text" id="ev_name" value="<?= e($event['name']) ?>" class="form-control">
        </div>
        <div class="col-md-12">
          <label class="form-label fw-medium">Venue / Location <span class="text-danger">*</span></label>
          <input type="text" id="ev_location" value="<?= e($event['location']) ?>" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-medium">Registration From <span class="text-danger">*</span></label>
          <input type="date" id="ev_reg_from" value="<?= e($event['reg_date_from']) ?>" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-medium">Registration To <span class="text-danger">*</span></label>
          <input type="date" id="ev_reg_to" value="<?= e($event['reg_date_to']) ?>" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-medium">Event Starts <span class="text-danger">*</span></label>
          <input type="date" id="ev_event_from" value="<?= e($event['event_date_from']) ?>" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-medium">Event Ends <span class="text-danger">*</span></label>
          <input type="date" id="ev_event_to" value="<?= e($event['event_date_to']) ?>" class="form-control">
        </div>
      </div>
    </div>

    <!-- Map Location -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-geo-alt me-2"></i>Geographic Location</h6>
        <button type="button" class="btn btn-sm btn-primary px-3" onclick="saveSection('location')"><i class="bi bi-save me-1"></i>Save</button>
      </div>
      <div id="eventMap" style="height:300px;border-radius:12px;border:1px solid #e2e8f0"></div>
      <div class="row g-3 mt-2">
        <div class="col-md-6">
          <label class="form-label fw-medium">Latitude</label>
          <input type="text" id="latitude" value="<?= e($event['latitude'] ?? '') ?>" class="form-control" placeholder="e.g. 9.9312">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-medium">Longitude</label>
          <input type="text" id="longitude" value="<?= e($event['longitude'] ?? '') ?>" class="form-control" placeholder="e.g. 76.2673">
        </div>
      </div>
      <small class="text-muted mt-1 d-block">Click on the map to set coordinates, or enter manually.</small>
    </div>

    <!-- Payment -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-credit-card me-2"></i>Payment Settings</h6>
        <button type="button" class="btn btn-sm btn-primary px-3" onclick="saveSection('payment')"><i class="bi bi-save me-1"></i>Save</button>
      </div>
      <label class="form-label fw-medium">Payment Mode(s) <span class="text-danger">*</span></label>
      <div class="d-flex gap-3 mb-3 flex-wrap">
        <div class="form-check form-check-inline border rounded-3 px-3 py-2">
          <input class="form-check-input" type="checkbox" name="payment_modes[]" value="manual" id="pm_manual"
                 <?= in_array('manual', $paymentModes) ? 'checked' : '' ?> onchange="toggleManualFields()">
          <label class="form-check-label fw-medium" for="pm_manual"><i class="bi bi-bank me-1"></i>Manual Submission</label>
        </div>
        <div class="form-check form-check-inline border rounded-3 px-3 py-2">
          <input class="form-check-input" type="checkbox" name="payment_modes[]" value="online" id="pm_online"
                 <?= in_array('online', $paymentModes) ? 'checked' : '' ?>>
          <label class="form-check-label fw-medium" for="pm_online"><i class="bi bi-credit-card me-1"></i>Online Payment</label>
        </div>
      </div>
      <div id="manualFields" style="display:none">
        <div class="mb-3">
          <label class="form-label fw-medium">Bank Details</label>
          <textarea id="bank_details" rows="3" class="form-control"
                    placeholder="Bank Name, Account Number, IFSC, Branch…"><?= e($event['bank_details'] ?? '') ?></textarea>
        </div>
        <div>
          <label class="form-label fw-medium">Bank QR Code <small class="text-muted fw-normal">(Optional)</small></label>
          <?php if ($event['bank_qr_code']): ?>
            <div class="mb-1" id="qrPreview"><img src="<?= e($event['bank_qr_code']) ?>" alt="QR" height="60" class="rounded"></div>
          <?php else: ?>
            <div class="mb-1 d-none" id="qrPreview"></div>
          <?php endif; ?>
          <input type="file" id="bank_qr_code" class="form-control" accept="image/jpeg,image/png">
        </div>
      </div>
    </div>

    <!-- Sports in this Event -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 flex-wrap gap-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-trophy me-2"></i>Sports in this Event</h6>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="downloadSportsCsv()">
          <i class="bi bi-download me-1"></i>Download CSV
        </button>
      </div>

      <!-- Picker -->
      <div class="row g-2 align-items-end mb-3">
        <div class="col-md-2">
          <label class="form-label small mb-1">Sport</label>
          <select id="picker_sport" class="form-select form-select-sm" onchange="loadCategories()">
            <option value="">— Select Sport —</option>
            <?php foreach ($sports as $sp): ?>
              <option value="<?= (int)$sp['id'] ?>"><?= e($sp['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small mb-1">Category</label>
          <select id="picker_category" class="form-select form-select-sm" disabled onchange="loadSportEvents()">
            <option value="">— Select Category —</option>
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label small mb-1">Gender</label>
          <select id="picker_gender" class="form-select form-select-sm" onchange="loadSportEvents()">
            <option value="">All</option>
            <option value="male">Men</option>
            <option value="female">Women</option>
            <option value="mixed">Mixed</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">Sport Event</label>
          <select id="picker_sport_event" class="form-select form-select-sm" disabled>
            <option value="">— Select Event —</option>
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label small mb-1">Fee ₹</label>
          <input id="picker_fee" type="number" min="0" step="0.01" class="form-control form-control-sm" value="0">
        </div>
        <div class="col-md-2">
          <label class="form-label small mb-1">Event Code <span class="text-danger">*</span></label>
          <input id="picker_event_code" type="text" maxlength="50" class="form-control form-control-sm"
                 placeholder="e.g. AP-10M-SR-M">
        </div>
        <div class="col-md-1">
          <button type="button" class="btn btn-sm btn-primary w-100" onclick="addSportEvent()"><i class="bi bi-plus"></i></button>
        </div>
      </div>

      <p class="small text-muted mb-3">No matching events for the selected category/gender? Ask the super admin to add them under <em>Settings → Sports</em>.</p>

      <!-- Search & filter on the added list -->
      <div class="row g-2 align-items-center mb-2">
        <div class="col-md-5">
          <input id="rowsSearch" type="search" class="form-control form-control-sm"
                 placeholder="Search added events…" oninput="applyRowFilters()">
        </div>
        <div class="col-md-3">
          <select id="rowsSportFilter" class="form-select form-select-sm" onchange="applyRowFilters()">
            <option value="">All sports</option>
          </select>
        </div>
        <div class="col-md-3">
          <select id="rowsGenderFilter" class="form-select form-select-sm" onchange="applyRowFilters()">
            <option value="">All genders</option>
            <option value="male">Men</option>
            <option value="female">Women</option>
            <option value="mixed">Mixed</option>
          </select>
        </div>
        <div class="col-md-1 text-end">
          <span class="badge bg-secondary" id="rowsCount">0</span>
        </div>
      </div>

      <div class="table-responsive" id="sportsTableWrap">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Sport</th>
              <th>Event Code</th>
              <th>Category / Event</th>
              <th>Age / Gender</th>
              <th class="text-end">Entry Fee</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="sportsRows">
            <?php if ($eventSports): foreach ($eventSports as $row): ?>
              <tr data-row-id="<?= (int)$row['id'] ?>"
                  data-sport="<?= e($row['sport_name']) ?>"
                  data-gender="<?= e($row['sport_event_gender'] ?? '') ?>"
                  data-label="<?= e($row['sport_event_name'] ?? $row['category'] ?? '') ?>">
                <td><?= e($row['sport_name']) ?></td>
                <td><code><?= e($row['event_code'] ?? '') ?></code></td>
                <td><?= e($row['sport_event_category'] ?? '') ?> <span class="text-muted"><?= e($row['sport_event_name'] ?? $row['category'] ?? '') ?></span></td>
                <td><?= e($row['sport_event_age_category'] ?? '') ?> <span class="text-muted small"><?= e($row['sport_event_gender'] ?? '') ?></span></td>
                <td class="text-end">₹<?= number_format((float)$row['entry_fee'], 2) ?></td>
                <td class="text-end"><button class="btn btn-sm btn-outline-danger" onclick="removeSportEvent(this)"><i class="bi bi-trash"></i></button></td>
              </tr>
            <?php endforeach; else: ?>
              <tr id="emptyRow"><td colspan="6" class="text-muted text-center py-3">No sport events added yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Registration Settings (NOC) -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-shield-check me-2"></i>Registration Settings</h6>
        <button type="button" class="btn btn-sm btn-primary px-3" onclick="saveSection('noc')"><i class="bi bi-save me-1"></i>Save</button>
      </div>
      <label class="form-label fw-medium">NOC Letter from Unit</label>
      <select id="noc_required" class="form-select form-select-sm" style="max-width:280px">
        <option value="none"      <?= $nocRequired==='none'      ? 'selected':'' ?>>Not Required</option>
        <option value="optional"  <?= $nocRequired==='optional'  ? 'selected':'' ?>>Optional</option>
        <option value="mandatory" <?= $nocRequired==='mandatory' ? 'selected':'' ?>>Mandatory</option>
      </select>
      <small class="text-muted d-block mt-1">Athletes will see this on the registration page when picking a Unit.</small>
    </div>

    <!-- Units / Clubs / Institutions -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-buildings me-2"></i>Units / Clubs / Institutions</h6>
      </div>
      <p class="small text-muted mb-3">Athletes pick from this list while registering. Each unit needs a name; address is optional.</p>

      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light"><tr><th style="width:35%">Name</th><th>Address</th><th style="width:120px"></th></tr></thead>
          <tbody id="unitRows">
            <?php foreach ($units as $u): ?>
              <tr data-id="<?= (int)$u['id'] ?>">
                <td><input class="form-control form-control-sm" data-field="name" value="<?= e($u['name']) ?>"></td>
                <td><input class="form-control form-control-sm" data-field="address" value="<?= e($u['address'] ?? '') ?>"></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary me-1" type="button" onclick="unitSave(this)"><i class="bi bi-save"></i></button>
                  <button class="btn btn-sm btn-outline-danger" type="button" onclick="unitDelete(this)"><i class="bi bi-trash"></i></button>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($units)): ?>
              <tr id="emptyUnits"><td colspan="3" class="text-muted text-center py-3">No units added yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="row g-2 align-items-end border-top pt-3">
        <div class="col-md-4"><label class="form-label small mb-1">Add New Unit</label>
          <input id="newUnitName" class="form-control form-control-sm" placeholder="Unit / Club / Institution name"></div>
        <div class="col-md-7">
          <input id="newUnitAddress" class="form-control form-control-sm" placeholder="Address (optional)">
        </div>
        <div class="col-md-1"><button type="button" class="btn btn-primary btn-sm w-100" onclick="unitAdd()"><i class="bi bi-plus"></i></button></div>
      </div>
    </div>

  </div>

  <!-- Right column -->
  <div class="col-lg-4">

    <!-- Logo -->
    <div class="sms-card p-4 mb-4 text-center">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-image me-2"></i>Event Logo</h6>
      </div>
      <div id="logoPreview" class="mb-3">
        <?php if (!empty($event['logo'])): ?>
          <img src="<?= e($event['logo']) ?>?t=<?= time() ?>" alt="Logo" id="currentLogo"
               class="rounded-3" width="160" height="160" style="object-fit:contain;border:1px solid #e2e8f0;background:#fff">
        <?php else: ?>
          <div class="text-muted small" id="currentLogo">No logo uploaded yet.</div>
        <?php endif; ?>
      </div>
      <input type="file" id="logoFileInput" accept="image/jpeg,image/png,image/webp" class="d-none" onchange="onLogoSelect(this)">
      <button type="button" class="btn btn-outline-primary btn-sm w-100"
              onclick="document.getElementById('logoFileInput').click()">
        <i class="bi bi-camera me-1"></i>Change Logo
      </button>
      <small class="text-muted d-block mt-2">JPG/PNG/WEBP · Max 2 MB</small>
      <div id="logoSaving" class="mt-2 d-none text-center">
        <div class="spinner-border spinner-border-sm text-primary"></div>
        <small class="text-muted ms-1">Uploading…</small>
      </div>
    </div>

    <!-- Contact -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-person-lines-fill me-2"></i>Contact (SPOC)</h6>
        <button type="button" class="btn btn-sm btn-primary px-3" onclick="saveSection('contact')"><i class="bi bi-save me-1"></i>Save</button>
      </div>
      <div class="mb-3">
        <label class="form-label fw-medium">Name <span class="text-danger">*</span></label>
        <input type="text" id="contact_name" value="<?= e($event['contact_name']) ?>" class="form-control">
      </div>
      <div class="mb-3">
        <label class="form-label fw-medium">Designation</label>
        <input type="text" id="contact_designation" value="<?= e($event['contact_designation'] ?? '') ?>" class="form-control" placeholder="e.g. Event Coordinator">
      </div>
      <div class="mb-3">
        <label class="form-label fw-medium">Mobile <span class="text-danger">*</span></label>
        <input type="tel" id="contact_mobile" value="<?= e($event['contact_mobile']) ?>" class="form-control" placeholder="10-digit" maxlength="10">
      </div>
      <div>
        <label class="form-label fw-medium">Email <span class="text-danger">*</span></label>
        <input type="email" id="contact_email" value="<?= e($event['contact_email']) ?>" class="form-control">
      </div>
    </div>

  </div>

</div>

<!-- OpenLayers Map -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v8.2.0/ol.css">
<script src="https://cdn.jsdelivr.net/npm/ol@v8.2.0/dist/ol.js"></script>

<script>
const CSRF = '<?= e($csrfToken) ?>';
const EV_ID = <?= $eventId ?>;
const SAVE_URL = '/institution/events/' + EV_ID + '/save';

function showToast(msg, type) {
  type = type || 'success';
  const el  = document.getElementById('evToast');
  el.className = 'toast align-items-center border-0 text-bg-' + type;
  document.getElementById('toastMsg').textContent = msg;
  if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
    bootstrap.Toast.getOrCreateInstance(el, { delay: 3500 }).show();
  } else {
    alert(msg);
  }
}

async function postSection(fd) {
  fd.append('_token', CSRF);
  const res = await fetch(SAVE_URL, { method: 'POST', body: fd });
  let data; try { data = await res.json(); } catch (_) { data = { success:false, message:'Server error.' }; }
  return data;
}

async function saveSection(section) {
  const btn = document.querySelector(`button[onclick="saveSection('${section}')"]`);
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }

  const fd = new FormData();
  fd.append('section', section);

  if (section === 'details') {
    fd.append('name',            document.getElementById('ev_name').value);
    fd.append('location',        document.getElementById('ev_location').value);
    fd.append('reg_date_from',   document.getElementById('ev_reg_from').value);
    fd.append('reg_date_to',     document.getElementById('ev_reg_to').value);
    fd.append('event_date_from', document.getElementById('ev_event_from').value);
    fd.append('event_date_to',   document.getElementById('ev_event_to').value);
  }
  if (section === 'location') {
    fd.append('latitude',  document.getElementById('latitude').value);
    fd.append('longitude', document.getElementById('longitude').value);
  }
  if (section === 'payment') {
    document.querySelectorAll('input[name="payment_modes[]"]:checked').forEach(cb => fd.append('payment_modes[]', cb.value));
    fd.append('bank_details', document.getElementById('bank_details').value);
    const qr = document.getElementById('bank_qr_code');
    if (qr.files[0]) fd.append('bank_qr_code', qr.files[0]);
  }
  if (section === 'contact') {
    fd.append('contact_name',        document.getElementById('contact_name').value);
    fd.append('contact_designation', document.getElementById('contact_designation').value);
    fd.append('contact_mobile',      document.getElementById('contact_mobile').value);
    fd.append('contact_email',       document.getElementById('contact_email').value);
  }
  if (section === 'noc') {
    fd.append('noc_required', document.getElementById('noc_required').value);
  }
  if (section === 'status') {
    fd.append('status', document.getElementById('ev_status').value);
  }

  const data = await postSection(fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success && section === 'status') {
    const map = {
      draft:     ['Draft',     'bg-secondary'],
      active:    ['Active',    'bg-success'],
      completed: ['Completed', 'bg-info text-dark'],
      suspended: ['Suspended', 'bg-danger'],
    };
    const v = document.getElementById('ev_status').value;
    const badge = document.getElementById('statusBadge');
    if (badge && map[v]) { badge.className = 'badge ms-1 ' + map[v][1]; badge.textContent = map[v][0]; }
  }
  if (data.success && section === 'payment' && data.qr_url) {
    const prev = document.getElementById('qrPreview');
    prev.classList.remove('d-none');
    prev.innerHTML = '<img src="' + data.qr_url + '?t=' + Date.now() + '" alt="QR" height="60" class="rounded">';
    document.getElementById('bank_qr_code').value = '';
  }
  if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-save me-1"></i>Save'; }
}

/* ── Logo Upload (no cropper for now — direct upload) ── */
async function onLogoSelect(input) {
  if (!input.files || !input.files[0]) return;
  if (input.files[0].size > 2 * 1024 * 1024) {
    showToast('Image is larger than 2 MB.', 'danger'); input.value = ''; return;
  }
  document.getElementById('logoSaving').classList.remove('d-none');
  const fd = new FormData();
  fd.append('section', 'logo');
  fd.append('logo', input.files[0]);
  const data = await postSection(fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success && data.logo_url) {
    const url = data.logo_url + '?t=' + Date.now();
    const wrap = document.getElementById('logoPreview');
    wrap.innerHTML = '<img src="' + url + '" alt="Logo" id="currentLogo" class="rounded-3" width="160" height="160" style="object-fit:contain;border:1px solid #e2e8f0;background:#fff">';
  }
  document.getElementById('logoSaving').classList.add('d-none');
  input.value = '';
}

/* ── Sport Picker (chained dropdowns) ── */
async function loadCategories() {
  const sportId = document.getElementById('picker_sport').value;
  const cat = document.getElementById('picker_category');
  const ev  = document.getElementById('picker_sport_event');
  cat.innerHTML = '<option value="">— Select Category —</option>';
  ev.innerHTML  = '<option value="">— Select Event —</option>';
  cat.disabled = !sportId; ev.disabled = true;
  if (!sportId) return;
  const res  = await fetch('/institution/events/sports/' + sportId + '/categories');
  const data = await res.json();
  (data.categories || []).forEach(c =>
    cat.insertAdjacentHTML('beforeend', '<option value="' + c.id + '">' + c.name + '</option>'));
}
async function loadSportEvents() {
  const catId  = document.getElementById('picker_category').value;
  const gender = document.getElementById('picker_gender').value;
  const ev = document.getElementById('picker_sport_event');
  ev.innerHTML = '<option value="">— Select Event —</option>';
  ev.disabled = !catId;
  if (!catId) return;
  const url = '/institution/events/categories/' + catId + '/events' + (gender ? ('?gender=' + encodeURIComponent(gender)) : '');
  const res  = await fetch(url);
  const data = await res.json();
  const list = data.sport_events || [];
  if (!list.length) {
    ev.insertAdjacentHTML('beforeend', '<option value="" disabled>No events for this filter</option>');
  }
  list.forEach(se =>
    ev.insertAdjacentHTML('beforeend', '<option value="' + se.id + '">' + se.name + '</option>'));
}

async function addSportEvent(force) {
  const seId = document.getElementById('picker_sport_event').value;
  const fee  = document.getElementById('picker_fee').value || '0';
  const code = document.getElementById('picker_event_code').value.trim();
  if (!seId) { showToast('Pick a sport event first.', 'warning'); return; }
  if (!code) { showToast('Enter an Event Code (a short label/identifier).', 'warning'); return; }

  const fd = new FormData();
  fd.append('section', 'sport_event_add');
  fd.append('sport_event_id', seId);
  fd.append('entry_fee', fee);
  fd.append('event_code', code);
  if (force) fd.append('force', '1');

  const data = await postSection(fd);
  if (!data.success && data.duplicate) {
    if (confirm(data.message + '\n\nUpdate the entry fee for this event to ₹' + fee + ' and code to "' + code + '"?')) {
      return addSportEvent(true);
    }
    showToast('Already in this event — kept as-is.', 'warning');
    return;
  }
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) {
    renderSportRows(data.list || []);
    document.getElementById('picker_event_code').value = '';
  }
}
async function removeSportEvent(btn) {
  const tr = btn.closest('tr');
  const code  = (tr.children[1]?.textContent || '').trim();
  const label = (tr.dataset.label || tr.children[2]?.textContent || '').trim();
  const summary = code ? (code + (label ? ' — ' + label : '')) : (label || 'this entry');
  if (!confirm('Remove "' + summary + '" from this event?\n\nThis cannot be undone — athletes who already registered for it will lose this option.')) {
    return;
  }
  btn.disabled = true;
  const fd = new FormData();
  fd.append('section', 'sport_event_remove');
  fd.append('row_id', tr.dataset.rowId);
  const data = await postSection(fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) renderSportRows(data.list || []);
  else btn.disabled = false;
}
function renderSportRows(list) {
  const body = document.getElementById('sportsRows');
  if (!list.length) {
    body.innerHTML = '<tr id="emptyRow"><td colspan="6" class="text-muted text-center py-3">No sport events added yet.</td></tr>';
    refreshSportFilterOptions();
    applyRowFilters();
    return;
  }
  const esc = s => (s == null ? '' : String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])));
  body.innerHTML = list.map(r => `
    <tr data-row-id="${r.id}"
        data-sport="${esc(r.sport_name)}"
        data-gender="${esc(r.sport_event_gender)}"
        data-label="${esc(r.sport_event_name || r.category)}">
      <td>${esc(r.sport_name)}</td>
      <td><code>${esc(r.event_code)}</code></td>
      <td>${esc(r.sport_event_category)} <span class="text-muted">${esc(r.sport_event_name || r.category)}</span></td>
      <td>${esc(r.sport_event_age_category)} <span class="text-muted small">${esc(r.sport_event_gender)}</span></td>
      <td class="text-end">₹${parseFloat(r.entry_fee).toFixed(2)}</td>
      <td class="text-end"><button class="btn btn-sm btn-outline-danger" onclick="removeSportEvent(this)"><i class="bi bi-trash"></i></button></td>
    </tr>`).join('');
  refreshSportFilterOptions();
  applyRowFilters();
}

function refreshSportFilterOptions() {
  const sel = document.getElementById('rowsSportFilter');
  if (!sel) return;
  const current = sel.value;
  const sports = [...new Set([...document.querySelectorAll('#sportsRows tr[data-sport]')]
    .map(tr => tr.dataset.sport).filter(Boolean))].sort();
  sel.innerHTML = '<option value="">All sports</option>' + sports.map(s => `<option>${s}</option>`).join('');
  if (sports.includes(current)) sel.value = current;
}

function applyRowFilters() {
  const q       = (document.getElementById('rowsSearch')?.value || '').toLowerCase().trim();
  const sport   =  document.getElementById('rowsSportFilter')?.value || '';
  const gender  =  document.getElementById('rowsGenderFilter')?.value || '';
  let visible = 0;
  document.querySelectorAll('#sportsRows tr[data-row-id]').forEach(tr => {
    const text = tr.textContent.toLowerCase();
    const ok = (!q || text.includes(q))
            && (!sport || tr.dataset.sport === sport)
            && (!gender || tr.dataset.gender === gender);
    tr.style.display = ok ? '' : 'none';
    if (ok) visible++;
  });
  const badge = document.getElementById('rowsCount');
  if (badge) badge.textContent = visible;
}

function downloadSportsCsv() {
  const rows = document.querySelectorAll('#sportsRows tr[data-row-id]');
  if (!rows.length) { showToast('Nothing to export yet.', 'warning'); return; }
  const lines = [['Sport', 'Event Code', 'Category', 'Event', 'Age Category', 'Gender', 'Entry Fee']];
  rows.forEach(tr => {
    if (tr.style.display === 'none') return;
    const cells = tr.querySelectorAll('td');
    const sport = (cells[0]?.textContent || '').trim();
    const code  = (cells[1]?.textContent || '').trim();
    // Cell 3 = "Category EventName" — split on the trailing muted span text.
    const catCell = cells[2];
    const cat   = (catCell?.firstChild?.textContent || '').trim();
    const evNm  = (catCell?.querySelector('.text-muted')?.textContent || '').trim();
    const ageCell = cells[3];
    const age   = (ageCell?.firstChild?.textContent || '').trim();
    const gen   = (ageCell?.querySelector('.text-muted')?.textContent || '').trim();
    const fee   = (cells[4]?.textContent || '').replace('₹', '').trim();
    lines.push([sport, code, cat, evNm, age, gen, fee]);
  });
  if (lines.length === 1) { showToast('No visible rows to export.', 'warning'); return; }
  const csv = lines.map(r => r.map(c => `"${(c || '').replace(/"/g, '""')}"`).join(',')).join('\r\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'event-' + EV_ID + '-sports.csv';
  document.body.appendChild(a); a.click(); a.remove();
  URL.revokeObjectURL(url);
}

// initial filter state on page load
document.addEventListener('DOMContentLoaded', () => { refreshSportFilterOptions(); applyRowFilters(); });

/* ── Units / Clubs / Institutions ── */
function renderUnits(list) {
  const body = document.getElementById('unitRows');
  if (!list || !list.length) {
    body.innerHTML = '<tr id="emptyUnits"><td colspan="3" class="text-muted text-center py-3">No units added yet.</td></tr>';
    return;
  }
  const esc = s => (s == null ? '' : String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])));
  body.innerHTML = list.map(u => `
    <tr data-id="${u.id}">
      <td><input class="form-control form-control-sm" data-field="name" value="${esc(u.name)}"></td>
      <td><input class="form-control form-control-sm" data-field="address" value="${esc(u.address || '')}"></td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-primary me-1" type="button" onclick="unitSave(this)"><i class="bi bi-save"></i></button>
        <button class="btn btn-sm btn-outline-danger" type="button" onclick="unitDelete(this)"><i class="bi bi-trash"></i></button>
      </td>
    </tr>`).join('');
}
async function unitSave(btn) {
  const tr = btn.closest('tr');
  const fd = new FormData();
  fd.append('section', 'unit_save');
  fd.append('unit_id', tr.dataset.id);
  fd.append('name',    tr.querySelector('[data-field=name]').value);
  fd.append('address', tr.querySelector('[data-field=address]').value);
  const data = await postSection(fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) renderUnits(data.list || []);
}
async function unitDelete(btn) {
  const tr = btn.closest('tr');
  const name = (tr.querySelector('[data-field=name]')?.value || 'this unit').trim();
  if (!confirm('Delete unit "' + name + '"?')) return;
  const fd = new FormData();
  fd.append('section', 'unit_delete');
  fd.append('unit_id', tr.dataset.id);
  const data = await postSection(fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) renderUnits(data.list || []);
}
async function unitAdd() {
  const name    = document.getElementById('newUnitName').value.trim();
  const address = document.getElementById('newUnitAddress').value.trim();
  if (!name) { showToast('Unit name is required.', 'warning'); return; }
  const fd = new FormData();
  fd.append('section', 'unit_save');
  fd.append('unit_id', '0');
  fd.append('name',    name);
  fd.append('address', address);
  const data = await postSection(fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) {
    renderUnits(data.list || []);
    document.getElementById('newUnitName').value = '';
    document.getElementById('newUnitAddress').value = '';
  }
}

/* Submit-for-approval button removed — institutions now control event
   status directly via the Status dropdown in the page header. */

/* ── Map ── */
const lat = parseFloat(document.getElementById('latitude').value) || 20.5937;
const lon = parseFloat(document.getElementById('longitude').value) || 78.9629;
const marker = new ol.Feature({ geometry: new ol.geom.Point(ol.proj.fromLonLat([lon, lat])) });
const vectorLayer = new ol.layer.Vector({
  source: new ol.source.Vector({ features: [marker] }),
  style: new ol.style.Style({ image: new ol.style.Icon({ src: 'https://cdn.jsdelivr.net/npm/ol@v8.2.0/examples/data/icon.png', anchor: [0.5, 1] }) })
});
const map = new ol.Map({
  target: 'eventMap',
  layers: [new ol.layer.Tile({ source: new ol.source.OSM() }), vectorLayer],
  view: new ol.View({ center: ol.proj.fromLonLat([lon, lat]), zoom: 6 })
});
map.on('click', function(e) {
  const [lng, lt] = ol.proj.toLonLat(e.coordinate);
  document.getElementById('latitude').value  = lt.toFixed(6);
  document.getElementById('longitude').value = lng.toFixed(6);
  marker.getGeometry().setCoordinates(e.coordinate);
});

function toggleManualFields() {
  document.getElementById('manualFields').style.display = document.getElementById('pm_manual').checked ? 'block' : 'none';
}
if (document.getElementById('pm_manual').checked) toggleManualFields();
</script>
