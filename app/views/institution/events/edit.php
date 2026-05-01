<?php
$pageTitle = 'Edit Event';
$eventSports = array_column($event['sports'], null, 'sport_id');
$paymentModes = $event['payment_modes'] ?? [];
?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="/institution/events" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold">Edit Event</h5>
  <span class="badge bg-warning text-dark ms-1"><?= ucfirst(str_replace('_', ' ', $event['status'])) ?></span>
</div>

<form method="POST" action="/institution/events/<?= (int)$event['id'] ?>/edit" enctype="multipart/form-data" novalidate>
  <?= csrf() ?>

  <div class="row g-4">

    <!-- Left Column -->
    <div class="col-lg-8">

      <!-- Basic Details -->
      <div class="sms-card p-4 mb-4">
        <h6 class="fw-semibold border-bottom pb-2 mb-3">Event Details</h6>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-medium">Event Name <span class="text-danger">*</span></label>
            <input type="text" name="name" value="<?= e(old('name', $event['name'])) ?>"
                   class="form-control <?= hasError('name') ?>" required>
            <?= fieldError('name') ?>
          </div>
          <div class="col-md-8">
            <label class="form-label fw-medium">Venue / Location <span class="text-danger">*</span></label>
            <input type="text" name="location" value="<?= e(old('location', $event['location'])) ?>"
                   class="form-control <?= hasError('location') ?>" required>
            <?= fieldError('location') ?>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-medium">Event Logo</label>
            <?php if ($event['logo']): ?>
              <div class="mb-1">
                <img src="<?= e($event['logo']) ?>" alt="Logo" height="40" class="rounded">
                <small class="text-muted ms-1">Current logo</small>
              </div>
            <?php endif; ?>
            <input type="file" name="logo" class="form-control <?= hasError('logo') ?>"
                   accept="image/jpeg,image/png,image/webp">
            <?= fieldError('logo') ?>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-medium">Registration From <span class="text-danger">*</span></label>
            <input type="date" name="reg_date_from"
                   value="<?= e(old('reg_date_from', $event['reg_date_from'])) ?>"
                   class="form-control <?= hasError('reg_date_from') ?>" required>
            <?= fieldError('reg_date_from') ?>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-medium">Registration To <span class="text-danger">*</span></label>
            <input type="date" name="reg_date_to"
                   value="<?= e(old('reg_date_to', $event['reg_date_to'])) ?>"
                   class="form-control <?= hasError('reg_date_to') ?>" required>
            <?= fieldError('reg_date_to') ?>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-medium">Event Starts <span class="text-danger">*</span></label>
            <input type="date" name="event_date_from"
                   value="<?= e(old('event_date_from', $event['event_date_from'])) ?>"
                   class="form-control <?= hasError('event_date_from') ?>" required>
            <?= fieldError('event_date_from') ?>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-medium">Event Ends <span class="text-danger">*</span></label>
            <input type="date" name="event_date_to"
                   value="<?= e(old('event_date_to', $event['event_date_to'])) ?>"
                   class="form-control <?= hasError('event_date_to') ?>" required>
            <?= fieldError('event_date_to') ?>
          </div>
        </div>
      </div>

      <!-- Map Location -->
      <div class="sms-card p-4 mb-4">
        <h6 class="fw-semibold border-bottom pb-2 mb-3">
          <i class="bi bi-geo-alt me-2"></i>Geographic Location (OpenStreetMap)
        </h6>
        <div id="eventMap" style="height:300px;border-radius:12px;border:1px solid #e2e8f0"></div>
        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <label class="form-label fw-medium">Latitude</label>
            <input type="text" name="latitude" id="latitude"
                   value="<?= e(old('latitude', $event['latitude'] ?? '')) ?>"
                   class="form-control" placeholder="e.g. 9.9312">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-medium">Longitude</label>
            <input type="text" name="longitude" id="longitude"
                   value="<?= e(old('longitude', $event['longitude'] ?? '')) ?>"
                   class="form-control" placeholder="e.g. 76.2673">
          </div>
        </div>
        <small class="text-muted mt-1 d-block">Click on the map to set coordinates, or enter manually.</small>
      </div>

      <!-- Payment -->
      <div class="sms-card p-4 mb-4">
        <h6 class="fw-semibold border-bottom pb-2 mb-3">
          <i class="bi bi-credit-card me-2"></i>Payment Settings
        </h6>
        <label class="form-label fw-medium">Payment Mode(s) <span class="text-danger">*</span></label>
        <?= fieldError('payment_modes') ?>
        <div class="d-flex gap-3 mb-3 flex-wrap">
          <div class="form-check form-check-inline border rounded-3 px-3 py-2">
            <input class="form-check-input" type="checkbox" name="payment_modes[]"
                   value="manual" id="pm_manual"
                   <?= in_array('manual', old('payment_modes', $paymentModes)) ? 'checked' : '' ?>
                   onchange="toggleManualFields()">
            <label class="form-check-label fw-medium" for="pm_manual">
              <i class="bi bi-bank me-1"></i>Manual Submission
            </label>
          </div>
          <div class="form-check form-check-inline border rounded-3 px-3 py-2">
            <input class="form-check-input" type="checkbox" name="payment_modes[]"
                   value="online" id="pm_online"
                   <?= in_array('online', old('payment_modes', $paymentModes)) ? 'checked' : '' ?>>
            <label class="form-check-label fw-medium" for="pm_online">
              <i class="bi bi-credit-card me-1"></i>Online Payment
            </label>
          </div>
        </div>

        <div id="manualFields" style="display:none">
          <div class="mb-3">
            <label class="form-label fw-medium">Bank Details</label>
            <textarea name="bank_details" rows="3" class="form-control"
                      placeholder="Bank Name, Account Number, IFSC, Branch…"><?= e(old('bank_details', $event['bank_details'] ?? '')) ?></textarea>
          </div>
          <div>
            <label class="form-label fw-medium">
              Bank QR Code <small class="text-muted fw-normal">(Optional — upload new to replace)</small>
            </label>
            <?php if ($event['bank_qr_code']): ?>
              <div class="mb-1">
                <img src="<?= e($event['bank_qr_code']) ?>" alt="QR" height="60" class="rounded">
              </div>
            <?php endif; ?>
            <input type="file" name="bank_qr_code" class="form-control" accept="image/jpeg,image/png">
          </div>
        </div>
      </div>

      <!-- Sports -->
      <div class="sms-card p-4 mb-4">
        <h6 class="fw-semibold border-bottom pb-2 mb-3">
          <i class="bi bi-trophy me-2"></i>Sports in this Event
        </h6>
        <div class="row g-2">
          <?php foreach ($sports as $sport): ?>
          <?php
          $oldSports  = old('sports', []);
          $isSaved    = isset($eventSports[$sport['id']]);
          $isChecked  = $oldSports ? !empty($oldSports[$sport['id']]['selected']) : $isSaved;
          $savedData  = $eventSports[$sport['id']] ?? [];
          $category   = $oldSports[$sport['id']]['category']  ?? ($savedData['category']  ?? '');
          $entryFee   = $oldSports[$sport['id']]['entry_fee'] ?? ($savedData['entry_fee'] ?? '');
          ?>
          <div class="col-md-6">
            <div class="border rounded-3 p-3 sms-sport-row">
              <div class="form-check mb-2">
                <input class="form-check-input sport-check" type="checkbox"
                       name="sports[<?= $sport['id'] ?>][selected]"
                       id="sport_<?= $sport['id'] ?>" value="1"
                       <?= $isChecked ? 'checked' : '' ?>>
                <label class="form-check-label fw-medium" for="sport_<?= $sport['id'] ?>">
                  <?= e($sport['name']) ?>
                </label>
              </div>
              <div class="sport-fields ps-4" style="<?= $isChecked ? '' : 'display:none' ?>">
                <div class="row g-2">
                  <div class="col-6">
                    <input type="text" name="sports[<?= $sport['id'] ?>][category]"
                           value="<?= e($category) ?>"
                           class="form-control form-control-sm" placeholder="Category (e.g. U-18)">
                  </div>
                  <div class="col-6">
                    <div class="input-group input-group-sm">
                      <span class="input-group-text">₹</span>
                      <input type="number" name="sports[<?= $sport['id'] ?>][entry_fee]"
                             value="<?= e($entryFee) ?>"
                             class="form-control" placeholder="Fee" min="0" step="0.01">
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>

    <!-- Right Column -->
    <div class="col-lg-4">

      <!-- Contact -->
      <div class="sms-card p-4 mb-4">
        <h6 class="fw-semibold border-bottom pb-2 mb-3">
          <i class="bi bi-person-lines-fill me-2"></i>Contact Person (SPOC)
        </h6>
        <div class="mb-3">
          <label class="form-label fw-medium">Name <span class="text-danger">*</span></label>
          <input type="text" name="contact_name"
                 value="<?= e(old('contact_name', $event['contact_name'])) ?>"
                 class="form-control <?= hasError('contact_name') ?>" required>
          <?= fieldError('contact_name') ?>
        </div>
        <div class="mb-3">
          <label class="form-label fw-medium">Designation</label>
          <input type="text" name="contact_designation"
                 value="<?= e(old('contact_designation', $event['contact_designation'] ?? '')) ?>"
                 class="form-control" placeholder="e.g. Event Coordinator">
        </div>
        <div class="mb-3">
          <label class="form-label fw-medium">Mobile <span class="text-danger">*</span></label>
          <input type="tel" name="contact_mobile"
                 value="<?= e(old('contact_mobile', $event['contact_mobile'])) ?>"
                 class="form-control <?= hasError('contact_mobile') ?>"
                 placeholder="10-digit" maxlength="10" required>
          <?= fieldError('contact_mobile') ?>
        </div>
        <div>
          <label class="form-label fw-medium">Email <span class="text-danger">*</span></label>
          <input type="email" name="contact_email"
                 value="<?= e(old('contact_email', $event['contact_email'])) ?>"
                 class="form-control <?= hasError('contact_email') ?>" required>
          <?= fieldError('contact_email') ?>
        </div>
      </div>

      <!-- Actions -->
      <div class="sms-card p-4">
        <p class="text-muted small mb-3">
          <i class="bi bi-info-circle me-1"></i>
          Saving changes will resubmit the event for admin approval.
        </p>
        <div class="d-grid gap-2">
          <button type="submit" class="btn btn-primary py-2 fw-semibold">
            <i class="bi bi-send me-2"></i>Save &amp; Resubmit
          </button>
          <a href="/institution/events" class="btn btn-light">Cancel</a>
        </div>
      </div>

    </div>
  </div>
</form>

<!-- OpenLayers Map -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v8.2.0/ol.css">
<script src="https://cdn.jsdelivr.net/npm/ol@v8.2.0/dist/ol.js"></script>
<script>
const lat = parseFloat(document.getElementById('latitude').value) || 20.5937;
const lon = parseFloat(document.getElementById('longitude').value) || 78.9629;

const marker = new ol.Feature({ geometry: new ol.geom.Point(ol.proj.fromLonLat([lon, lat])) });
const vectorLayer = new ol.layer.Vector({
  source: new ol.source.Vector({ features: [marker] }),
  style: new ol.style.Style({
    image: new ol.style.Icon({
      src: 'https://cdn.jsdelivr.net/npm/ol@v8.2.0/examples/data/icon.png',
      anchor: [0.5, 1]
    })
  })
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
  const checked = document.getElementById('pm_manual').checked;
  document.getElementById('manualFields').style.display = checked ? 'block' : 'none';
}
if (document.getElementById('pm_manual').checked) toggleManualFields();

document.querySelectorAll('.sport-check').forEach(function(cb) {
  cb.addEventListener('change', function() {
    this.closest('.sms-sport-row').querySelector('.sport-fields').style.display =
      this.checked ? 'block' : 'none';
  });
});
</script>
