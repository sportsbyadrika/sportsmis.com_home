<?php
$pageTitle = 'Edit Event';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$eventId = (int)$event['id'];
$paymentModes = $event['payment_modes'] ?? [];
$eventSports  = $event['sports'] ?? [];
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
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2">
    <a href="/institution/events" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-event me-2"></i><?= e($event['name']) ?></h5>
    <span class="badge bg-warning text-dark ms-1"><?= ucfirst(str_replace('_', ' ', $event['status'])) ?></span>
  </div>
  <button type="button" id="submitBtn" class="btn btn-success px-4 fw-semibold" onclick="submitEvent()">
    <i class="bi bi-send me-2"></i>Submit for Approval
  </button>
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
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-trophy me-2"></i>Sports in this Event</h6>
      </div>

      <div class="row g-2 align-items-end mb-3">
        <div class="col-md-3">
          <label class="form-label small mb-1">Sport</label>
          <select id="picker_sport" class="form-select form-select-sm" onchange="loadCategories()">
            <option value="">— Select Sport —</option>
            <?php foreach ($sports as $sp): ?>
              <option value="<?= (int)$sp['id'] ?>"><?= e($sp['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">Category</label>
          <select id="picker_category" class="form-select form-select-sm" disabled onchange="loadSportEvents()">
            <option value="">— Select Category —</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label small mb-1">Sport Event</label>
          <select id="picker_sport_event" class="form-select form-select-sm" disabled>
            <option value="">— Select Event —</option>
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label small mb-1">Fee ₹</label>
          <input id="picker_fee" type="number" min="0" step="0.01" class="form-control form-control-sm" value="0">
        </div>
        <div class="col-md-1">
          <button type="button" class="btn btn-sm btn-primary w-100" onclick="addSportEvent()"><i class="bi bi-plus"></i></button>
        </div>
      </div>

      <p class="small text-muted mb-2">No matching events for the selected category? Ask the super admin to add them under <em>Settings → Sports</em>.</p>

      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Sport</th>
              <th>Category / Event</th>
              <th>Age / Gender</th>
              <th class="text-end">Entry Fee</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="sportsRows">
            <?php if ($eventSports): foreach ($eventSports as $row): ?>
              <tr data-row-id="<?= (int)$row['id'] ?>">
                <td><?= e($row['sport_name']) ?></td>
                <td><?= e($row['sport_event_category'] ?? '') ?> <span class="text-muted"><?= e($row['sport_event_name'] ?? $row['category'] ?? '') ?></span></td>
                <td><?= e($row['sport_event_age_category'] ?? '') ?> <span class="text-muted small"><?= e($row['sport_event_gender'] ?? '') ?></span></td>
                <td class="text-end">₹<?= number_format((float)$row['entry_fee'], 2) ?></td>
                <td class="text-end"><button class="btn btn-sm btn-outline-danger" onclick="removeSportEvent(this)"><i class="bi bi-trash"></i></button></td>
              </tr>
            <?php endforeach; else: ?>
              <tr id="emptyRow"><td colspan="5" class="text-muted text-center py-3">No sport events added yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
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

  const data = await postSection(fd);
  showToast(data.message, data.success ? 'success' : 'danger');
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
  const catId = document.getElementById('picker_category').value;
  const ev = document.getElementById('picker_sport_event');
  ev.innerHTML = '<option value="">— Select Event —</option>';
  ev.disabled = !catId;
  if (!catId) return;
  const res  = await fetch('/institution/events/categories/' + catId + '/events');
  const data = await res.json();
  (data.sport_events || []).forEach(se =>
    ev.insertAdjacentHTML('beforeend', '<option value="' + se.id + '">' + se.name + '</option>'));
}
async function addSportEvent() {
  const seId = document.getElementById('picker_sport_event').value;
  const fee  = document.getElementById('picker_fee').value || '0';
  if (!seId) { showToast('Pick a sport event first.', 'warning'); return; }

  const fd = new FormData();
  fd.append('section', 'sport_event_add');
  fd.append('sport_event_id', seId);
  fd.append('entry_fee', fee);
  const data = await postSection(fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) renderSportRows(data.list || []);
}
async function removeSportEvent(btn) {
  const tr = btn.closest('tr');
  const fd = new FormData();
  fd.append('section', 'sport_event_remove');
  fd.append('row_id', tr.dataset.rowId);
  const data = await postSection(fd);
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) renderSportRows(data.list || []);
}
function renderSportRows(list) {
  const body = document.getElementById('sportsRows');
  if (!list.length) {
    body.innerHTML = '<tr id="emptyRow"><td colspan="5" class="text-muted text-center py-3">No sport events added yet.</td></tr>';
    return;
  }
  body.innerHTML = list.map(r => `
    <tr data-row-id="${r.id}">
      <td>${r.sport_name || ''}</td>
      <td>${r.sport_event_category || ''} <span class="text-muted">${r.sport_event_name || r.category || ''}</span></td>
      <td>${r.sport_event_age_category || ''} <span class="text-muted small">${r.sport_event_gender || ''}</span></td>
      <td class="text-end">₹${parseFloat(r.entry_fee).toFixed(2)}</td>
      <td class="text-end"><button class="btn btn-sm btn-outline-danger" onclick="removeSportEvent(this)"><i class="bi bi-trash"></i></button></td>
    </tr>`).join('');
}

/* ── Submit Event ── */
async function submitEvent() {
  const btn = document.getElementById('submitBtn');
  btn.disabled = true; const orig = btn.innerHTML;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';
  const fd = new FormData(); fd.append('_token', CSRF);
  try {
    const res  = await fetch('/institution/events/' + EV_ID + '/submit', { method:'POST', body: fd });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'warning');
    if (data.success) setTimeout(() => { window.location.href = data.redirect || '/institution/events'; }, 800);
  } catch (e) {
    showToast('Network error. Please try again.', 'danger');
  } finally {
    btn.disabled = false; btn.innerHTML = orig;
  }
}

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
