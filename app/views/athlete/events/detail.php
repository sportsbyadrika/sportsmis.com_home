<?php $pageTitle = e($event['name']); ?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="/athlete/events" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold">Event Details</h5>
</div>

<div class="row g-4">
  <div class="col-lg-8">

    <!-- ─ Main Event Details card ─ -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-start gap-4 mb-4 flex-wrap">
        <?php if ($event['logo']): ?>
          <img src="<?= e($event['logo']) ?>" alt="Logo" width="80" height="80"
               class="rounded-3 flex-shrink-0" style="object-fit:cover">
        <?php else: ?>
          <div class="sms-event-icon sms-event-icon-lg flex-shrink-0"><i class="bi bi-trophy"></i></div>
        <?php endif; ?>
        <div class="min-w-0">
          <h4 class="fw-bold mb-1 text-break"><?= e($event['name']) ?></h4>
          <div class="text-muted mb-2 text-break"><i class="bi bi-building me-1"></i><?= e($event['institution_name']) ?></div>
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
    </div>

    <!-- ─ Contact + Register (two-column layout under main details) ─ -->
    <div class="row g-4 mb-4">
      <div class="col-md-6">
        <div class="sms-card p-4 h-100">
          <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-person-lines-fill me-2"></i>Contact Person</h6>
          <div class="mb-2"><strong><?= e($event['contact_name']) ?></strong>
            <?php if ($event['contact_designation']): ?>
              <small class="text-muted d-block"><?= e($event['contact_designation']) ?></small>
            <?php endif; ?>
          </div>
          <div class="text-muted small">
            <div><i class="bi bi-phone me-1"></i><?= e($event['contact_mobile']) ?></div>
            <div class="text-break"><i class="bi bi-envelope me-1"></i><?= e($event['contact_email']) ?></div>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <?php
          $regOpen   = strtotime($event['reg_date_from']) <= time() && strtotime($event['reg_date_to']) >= time();
          $hasMyReg  = !empty($my_registration);
        ?>
        <div class="sms-card p-4 h-100">
          <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-person-plus me-2"></i>Register for Event</h6>

          <?php if (!$athlete['profile_completed']): ?>
            <div class="alert alert-warning small">Complete your profile first to register.</div>
            <a href="/athlete/profile" class="btn btn-warning w-100">Complete Profile</a>

          <?php elseif (!$regOpen): ?>
            <div class="alert alert-secondary small text-center mb-0">
              <?= time() < strtotime($event['reg_date_from'])
                    ? 'Registration opens ' . formatDate($event['reg_date_from'])
                    : 'Registration closed.' ?>
            </div>

          <?php elseif (empty($event['sports'])): ?>
            <p class="text-muted small mb-0">No sports listed for this event.</p>

          <?php elseif ($hasMyReg): ?>
            <p class="small text-muted mb-3">
              You have already started registering for this event.
              <?= !empty($my_registration['admin_review_status'])
                    ? 'Status: <strong>' . e(ucfirst($my_registration['admin_review_status'])) . '</strong>'
                    : 'Status: <strong>Draft</strong>' ?>
            </p>
            <div class="d-grid gap-2">
              <a href="/athlete/events/<?= e(hid_event((int)$event['id'])) ?>/register" class="btn btn-primary fw-semibold">
                <i class="bi bi-pencil me-2"></i>Edit Registration
              </a>
              <a href="/athlete/registrations/<?= e(hid_reg((int)$my_registration['id'])) ?>" class="btn btn-outline-secondary">
                <i class="bi bi-eye me-2"></i>View Registration
              </a>
            </div>

          <?php else: ?>
            <p class="small text-muted mb-3">
              You'll pick your Unit, upload an NOC letter (if required), choose your sport events
              and complete payment on the next step.
            </p>
            <a href="/athlete/events/<?= e(hid_event((int)$event['id'])) ?>/register" class="btn btn-primary w-100 fw-semibold">
              <i class="bi bi-check-circle me-2"></i>Start Registration
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ─ Sports in this Event (table on md+, cards on <md) ─ -->
    <?php if ($event['sports']): ?>
    <?php
      $categories = [];
      foreach ($event['sports'] as $s) {
        $cat = $s['sport_event_category'] ?? $s['category'] ?? '';
        if ($cat !== '' && !in_array($cat, $categories, true)) $categories[] = $cat;
      }
      sort($categories);
    ?>
    <div class="sms-card p-4 mb-4">
      <div class="border-bottom pb-2 mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <h6 class="fw-semibold mb-0"><i class="bi bi-trophy me-2"></i>Sports in this Event</h6>
          <?php if (!empty($categories)): ?>
            <!-- Inline group on sm+, full-width below the heading on xs -->
            <div class="d-none d-sm-flex align-items-center gap-2">
              <label for="catFilter" class="form-label small mb-0 text-muted">Category</label>
              <select id="catFilter" class="form-select form-select-sm" style="min-width:160px"
                      onchange="filterSportRows()">
                <option value="">All categories</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= e($c) ?>"><?= e($c) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>
        </div>
        <?php if (!empty($categories)): ?>
          <!-- xs version: filter takes the full width below the title -->
          <div class="d-sm-none mt-2">
            <label for="catFilterXs" class="form-label small text-muted mb-1">Filter by Category</label>
            <select id="catFilterXs" class="form-select form-select-sm w-100"
                    onchange="syncCatFilter(this.value)">
              <option value="">All categories</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= e($c) ?>"><?= e($c) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
      </div>

      <!-- Desktop: table (md+) -->
      <div class="table-responsive d-none d-md-block">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:7%" class="text-center">Sl. No</th>
              <th>Sport</th>
              <th>Category</th>
              <th>Event Code</th>
              <th>Sport Event</th>
              <th class="text-end">Entry Fee</th>
            </tr>
          </thead>
          <tbody id="sportsTbody">
            <?php $sl = 0; foreach ($event['sports'] as $s):
              $sl++;
              $cat = $s['sport_event_category'] ?? $s['category'] ?? '';
            ?>
            <tr data-category="<?= e($cat) ?>">
              <td class="text-center sl-cell"><?= $sl ?></td>
              <td class="fw-medium"><?= e($s['sport_name']) ?></td>
              <td class="text-muted"><?= e($cat ?: '—') ?></td>
              <td><code><?= e($s['event_code'] ?? '—') ?></code></td>
              <td><?= e($s['sport_event_name'] ?? '—') ?></td>
              <td class="text-end"><?= $s['entry_fee'] > 0 ? '₹ ' . number_format($s['entry_fee'], 2) : '<span class="text-success fw-medium">Free</span>' ?></td>
            </tr>
            <?php endforeach; ?>
            <tr id="emptyFilteredRow" class="d-none">
              <td colspan="6" class="text-muted text-center py-3">No sport events match this category.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile: card stack (<md) -->
      <div class="d-md-none" id="sportsCards">
        <?php $sl = 0; foreach ($event['sports'] as $s):
          $sl++;
          $cat = $s['sport_event_category'] ?? $s['category'] ?? '';
        ?>
          <div class="border rounded-3 p-3 mb-2 small" data-category="<?= e($cat) ?>">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
              <div class="fw-semibold text-break">
                <span class="badge bg-secondary-subtle text-secondary me-1 sl-cell"><?= $sl ?></span>
                <?= e($s['sport_event_name'] ?? '—') ?>
              </div>
              <div class="fw-bold text-nowrap"><?= $s['entry_fee'] > 0 ? '₹' . number_format($s['entry_fee'], 2) : '<span class="text-success">Free</span>' ?></div>
            </div>
            <div class="text-muted">
              <i class="bi bi-trophy me-1"></i><?= e($s['sport_name']) ?>
              <?php if ($cat): ?> · <?= e($cat) ?><?php endif; ?>
            </div>
            <?php if (!empty($s['event_code'])): ?>
              <div class="text-muted"><code><?= e($s['event_code']) ?></code></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <div id="sportsCardsEmpty" class="text-muted text-center py-3 d-none">No sport events match this category.</div>
      </div>
    </div>
    <script>
    function syncCatFilter(value) {
      // Mirror the xs select into the sm+ select and run the existing filter.
      const wide = document.getElementById('catFilter');
      if (wide) wide.value = value;
      filterSportRows();
    }
    function filterSportRows() {
      const wide = document.getElementById('catFilter');
      const xs   = document.getElementById('catFilterXs');
      const cat  = (wide && wide.value !== '') ? wide.value
                 : (xs   && xs.value   !== '') ? xs.value
                 : '';
      // Keep the two selects in sync for the next time either is opened.
      if (wide && xs && wide.value !== xs.value) {
        if (document.activeElement === wide) xs.value   = wide.value;
        else                                  wide.value = xs.value;
      }
      // Desktop rows
      const rows = document.querySelectorAll('#sportsTbody tr[data-category]');
      let sl = 0, shown = 0;
      rows.forEach(tr => {
        const match = !cat || tr.dataset.category === cat;
        tr.classList.toggle('d-none', !match);
        if (match) { sl++; shown++; tr.querySelector('.sl-cell').textContent = sl; }
      });
      document.getElementById('emptyFilteredRow').classList.toggle('d-none', shown !== 0);
      // Mobile cards
      const cards = document.querySelectorAll('#sportsCards [data-category]');
      let slM = 0, shownM = 0;
      cards.forEach(c => {
        const match = !cat || c.dataset.category === cat;
        c.classList.toggle('d-none', !match);
        if (match) { slM++; shownM++; const sc = c.querySelector('.sl-cell'); if (sc) sc.textContent = slM; }
      });
      document.getElementById('sportsCardsEmpty').classList.toggle('d-none', shownM !== 0);
    }
    </script>
    <?php endif; ?>
  </div>

  <!-- ─ Right column: Map ─ -->
  <div class="col-lg-4">
    <?php if ($event['latitude'] && $event['longitude']): ?>
      <div class="sms-card p-4 mb-4">
        <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-geo-alt me-2"></i>Event Location</h6>
        <div id="detailMap" style="height:300px;border-radius:12px;border:1px solid #e2e8f0"></div>
        <div class="small text-muted mt-2 text-break">
          <i class="bi bi-pin-map me-1"></i><?= e($event['location']) ?>
        </div>
      </div>
    <?php else: ?>
      <div class="sms-card p-4 mb-4">
        <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-geo-alt me-2"></i>Event Location</h6>
        <div class="text-muted small text-center py-3">
          <i class="bi bi-geo-alt-slash fs-3 d-block mb-2"></i>
          The organiser hasn't set a map pin for this event yet.
        </div>
        <div class="small text-muted text-break">
          <i class="bi bi-pin-map me-1"></i><?= e($event['location']) ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($event['latitude'] && $event['longitude']): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v8.2.0/ol.css">
<script src="https://cdn.jsdelivr.net/npm/ol@v8.2.0/dist/ol.js"></script>
<script>
(function () {
  const lon = <?= (float)$event['longitude'] ?>;
  const lat = <?= (float)$event['latitude']  ?>;
  // Inline SVG pin marker — no external image dependency, renders sharp
  // on retina displays and tints to the project's primary colour.
  const pinSvg = encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 48" width="32" height="48">' +
      '<path d="M16 0C7.2 0 0 7.2 0 16c0 11 16 32 16 32s16-21 16-32C32 7.2 24.8 0 16 0z" fill="#dc2626"/>' +
      '<circle cx="16" cy="16" r="6" fill="#ffffff"/>' +
    '</svg>'
  );
  const pinUrl = 'data:image/svg+xml;charset=utf-8,' + pinSvg;

  new ol.Map({
    target: 'detailMap',
    layers: [
      new ol.layer.Tile({ source: new ol.source.OSM() }),
      new ol.layer.Vector({
        source: new ol.source.Vector({
          features: [new ol.Feature({
            geometry: new ol.geom.Point(ol.proj.fromLonLat([lon, lat]))
          })]
        }),
        style: new ol.style.Style({
          image: new ol.style.Icon({
            src: pinUrl,
            anchor: [0.5, 1],   // bottom-tip of the pin sits on the coord
            scale: 1
          })
        })
      })
    ],
    controls: ol.control.defaults.defaults({ attributionOptions: { collapsible: true } }),
    view: new ol.View({
      center: ol.proj.fromLonLat([lon, lat]),
      zoom: 15,
      maxZoom: 19
    })
  });
})();
</script>
<?php endif; ?>
