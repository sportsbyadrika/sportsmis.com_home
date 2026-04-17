<?php $pageTitle = e($event['name']); ?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="/athlete/events" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold">Event Details</h5>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-start gap-4 mb-4">
        <?php if ($event['logo']): ?>
          <img src="<?= e($event['logo']) ?>" alt="Logo" width="80" height="80"
               class="rounded-3 flex-shrink-0" style="object-fit:cover">
        <?php else: ?>
          <div class="sms-event-icon sms-event-icon-lg flex-shrink-0"><i class="bi bi-trophy"></i></div>
        <?php endif; ?>
        <div>
          <h4 class="fw-bold mb-1"><?= e($event['name']) ?></h4>
          <div class="text-muted mb-2"><i class="bi bi-building me-1"></i><?= e($event['institution_name']) ?></div>
          <?= statusBadge($event['status']) ?>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-sm-6">
          <div class="sms-info-item">
            <i class="bi bi-geo-alt text-primary"></i>
            <div>
              <small class="text-muted d-block">Venue</small>
              <strong><?= e($event['location']) ?></strong>
            </div>
          </div>
        </div>
        <div class="col-sm-6">
          <div class="sms-info-item">
            <i class="bi bi-calendar3 text-success"></i>
            <div>
              <small class="text-muted d-block">Event Dates</small>
              <strong><?= formatDate($event['event_date_from']) ?> – <?= formatDate($event['event_date_to']) ?></strong>
            </div>
          </div>
        </div>
        <div class="col-sm-6">
          <div class="sms-info-item">
            <i class="bi bi-person-plus text-warning"></i>
            <div>
              <small class="text-muted d-block">Registration Period</small>
              <strong><?= formatDate($event['reg_date_from']) ?> – <?= formatDate($event['reg_date_to']) ?></strong>
            </div>
          </div>
        </div>
        <div class="col-sm-6">
          <div class="sms-info-item">
            <i class="bi bi-credit-card text-info"></i>
            <div>
              <small class="text-muted d-block">Payment Mode</small>
              <strong><?= implode(', ', array_map('ucfirst', $event['payment_modes'])) ?></strong>
            </div>
          </div>
        </div>
      </div>

      <?php if ($event['latitude'] && $event['longitude']): ?>
      <div class="mt-4">
        <h6 class="fw-semibold mb-2"><i class="bi bi-map me-2"></i>Event Location</h6>
        <div id="detailMap" style="height:220px;border-radius:12px;border:1px solid #e2e8f0"></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Sports -->
    <?php if ($event['sports']): ?>
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-trophy me-2"></i>Sports in this Event</h6>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light">
            <tr><th>Sport</th><th>Category</th><th>Entry Fee</th></tr>
          </thead>
          <tbody>
            <?php foreach ($event['sports'] as $s): ?>
            <tr>
              <td class="fw-medium"><?= e($s['sport_name']) ?></td>
              <td class="text-muted"><?= e($s['category'] ?? '—') ?></td>
              <td><?= $s['entry_fee'] > 0 ? '₹ ' . number_format($s['entry_fee'], 2) : '<span class="text-success fw-medium">Free</span>' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right: Contact & Registration -->
  <div class="col-lg-4">

    <!-- Contact -->
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-person-lines-fill me-2"></i>Contact Person</h6>
      <div class="mb-2"><strong><?= e($event['contact_name']) ?></strong>
        <?php if ($event['contact_designation']): ?>
          <small class="text-muted d-block"><?= e($event['contact_designation']) ?></small>
        <?php endif; ?>
      </div>
      <div class="text-muted small">
        <div><i class="bi bi-phone me-1"></i><?= e($event['contact_mobile']) ?></div>
        <div><i class="bi bi-envelope me-1"></i><?= e($event['contact_email']) ?></div>
      </div>
    </div>

    <!-- Register -->
    <?php $regOpen = strtotime($event['reg_date_from']) <= time() && strtotime($event['reg_date_to']) >= time(); ?>
    <div class="sms-card p-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-person-plus me-2"></i>Register for Event</h6>

      <?php if (!$athlete['profile_completed']): ?>
        <div class="alert alert-warning small">Complete your profile first to register.</div>
        <a href="/athlete/profile" class="btn btn-warning w-100">Complete Profile</a>

      <?php elseif (!$regOpen): ?>
        <div class="alert alert-secondary small text-center">
          <?= time() < strtotime($event['reg_date_from']) ? 'Registration opens ' . formatDate($event['reg_date_from']) : 'Registration closed.' ?>
        </div>

      <?php elseif ($event['sports']): ?>
        <form method="POST" action="/athlete/events/<?= $event['id'] ?>/register">
          <?= csrf() ?>
          <div class="mb-3">
            <label class="form-label fw-medium">Select Sport</label>
            <select name="sport_id" class="form-select" required>
              <option value="">-- Choose Sport --</option>
              <?php foreach ($event['sports'] as $s): ?>
                <option value="<?= $s['sport_id'] ?>"><?= e($s['sport_name']) ?>
                  <?= $s['entry_fee'] > 0 ? ' (₹' . number_format($s['entry_fee'], 2) . ')' : ' (Free)' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if (count($event['payment_modes']) > 1): ?>
          <div class="mb-3">
            <label class="form-label fw-medium">Payment Mode</label>
            <select name="payment_mode" class="form-select" required>
              <?php foreach ($event['payment_modes'] as $mode): ?>
                <option value="<?= $mode ?>"><?= ucfirst($mode) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php else: ?>
            <input type="hidden" name="payment_mode" value="<?= $event['payment_modes'][0] ?? '' ?>">
          <?php endif; ?>
          <button type="submit" class="btn btn-primary w-100 fw-semibold">
            <i class="bi bi-check-circle me-2"></i>Register Now
          </button>
        </form>
      <?php else: ?>
        <p class="text-muted small">No sports listed for this event.</p>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php if ($event['latitude'] && $event['longitude']): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v8.2.0/ol.css">
<script src="https://cdn.jsdelivr.net/npm/ol@v8.2.0/dist/ol.js"></script>
<script>
new ol.Map({
  target: 'detailMap',
  layers: [
    new ol.layer.Tile({ source: new ol.source.OSM() }),
    new ol.layer.Vector({
      source: new ol.source.Vector({
        features: [new ol.Feature({ geometry: new ol.geom.Point(ol.proj.fromLonLat([<?= $event['longitude'] ?>, <?= $event['latitude'] ?>])) })]
      }),
      style: new ol.style.Style({ image: new ol.style.Icon({ src: 'https://cdn.jsdelivr.net/npm/ol@v8.2.0/examples/data/icon.png', anchor:[0.5,1] }) })
    })
  ],
  view: new ol.View({ center: ol.proj.fromLonLat([<?= $event['longitude'] ?>, <?= $event['latitude'] ?>]), zoom: 14 })
});
</script>
<?php endif; ?>
