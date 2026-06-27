<?php
$pageTitle = 'Unit Dashboard';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold"><i class="bi bi-speedometer2 me-2"></i>Unit Dashboard</h5>
    <div class="text-muted small mt-1">
      Event: <strong><?= e($event['name']) ?></strong>
      · Code: <code><?= e($event['event_code'] ?? '') ?></code>
    </div>
  </div>

  <?php if (!empty($event['allow_unit_registration'])): ?>
    <a href="/unit/athletes/new" class="btn btn-sm btn-primary">
      <i class="bi bi-person-plus me-1"></i>Add Athlete
    </a>
  <?php endif; ?>

  <?php if (!empty($units) && count($units) > 1): ?>
    <form method="GET" action="/unit/dashboard" class="d-flex align-items-center gap-2">
      <label class="form-label mb-0 small text-muted">Unit:</label>
      <select name="unit_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:240px">
        <?php foreach ($units as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= $active_unit && (int)$active_unit['id'] === (int)$u['id'] ? 'selected' : '' ?>>
            <?= e($u['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  <?php endif; ?>
</div>

<?php if (empty($units)): ?>
  <div class="sms-empty-state">
    <i class="bi bi-building"></i>
    <h5>No Unit Assigned</h5>
    <p>The event administrator hasn't assigned any units to your account yet. Please contact the organiser.</p>
  </div>
<?php else: ?>

<!-- Unit details + stat tiles -->
<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="sms-card p-3 h-100">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-building me-2"></i>Unit Details</h6>
      <div class="d-flex gap-3 align-items-start">
        <div class="text-center flex-shrink-0">
          <div id="unitLogoBox">
            <?php if (!empty($active_unit['logo'])): ?>
              <img src="<?= e($active_unit['logo']) ?>?t=<?= time() ?>" id="unitLogoImg" alt="Unit Logo"
                   width="84" height="84" class="rounded" style="object-fit:cover;border:1px solid #e2e8f0;background:#fff">
            <?php else: ?>
              <div id="unitLogoImg" class="rounded d-flex align-items-center justify-content-center bg-light text-muted"
                   style="width:84px;height:84px;border:1px dashed #cbd5e1">
                <i class="bi bi-image"></i>
              </div>
            <?php endif; ?>
          </div>
          <input type="file" id="unitLogoFile" accept="image/jpeg,image/png,image/webp"
                 class="d-none" onchange="initUnitLogoCrop(this)">
          <button type="button" class="btn btn-sm btn-outline-primary mt-2"
                  onclick="document.getElementById('unitLogoFile').click()">
            <i class="bi bi-upload me-1"></i>Logo
          </button>
          <div id="unitLogoSaving" class="small text-primary mt-1 d-none">
            <span class="spinner-border spinner-border-sm"></span>
          </div>
        </div>
        <dl class="row small mb-0 flex-grow-1">
          <dt class="col-sm-4 text-muted">Code</dt>
          <dd class="col-sm-8"><code>#<?= (int)$active_unit['id'] ?></code></dd>
          <dt class="col-sm-4 text-muted">Name</dt>
          <dd class="col-sm-8 fw-medium"><?= e($active_unit['name']) ?></dd>
          <?php if (!empty($active_unit['address'])): ?>
            <dt class="col-sm-4 text-muted">Address</dt>
            <dd class="col-sm-8"><?= e($active_unit['address']) ?></dd>
          <?php endif; ?>
        </dl>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="row g-3 h-100">
      <div class="col-sm-6">
        <div class="sms-card p-3 h-100">
          <div class="text-muted small text-uppercase" style="letter-spacing:.04em">Total Athletes</div>
          <div class="display-6 fw-bold mt-1"><?= (int)$stats['total'] ?></div>
          <div class="small text-muted">
            <i class="bi bi-check2-circle me-1 text-success"></i>
            <?= (int)$stats['approved'] ?> approved
          </div>
        </div>
      </div>
      <div class="col-sm-6">
        <div class="sms-card p-3 h-100">
          <div class="text-muted small text-uppercase" style="letter-spacing:.04em">Total Demand Amount</div>
          <div class="display-6 fw-bold mt-1">₹<?= number_format((float)$stats['demand'], 2) ?></div>
          <div class="small text-muted"><i class="bi bi-cash me-1"></i>summed across this unit's registrations</div>
        </div>
      </div>
      <div class="col-sm-6">
        <div class="sms-card p-3 h-100">
          <div class="text-muted small text-uppercase" style="letter-spacing:.04em">Total Transaction Amount</div>
          <div class="display-6 fw-bold mt-1">₹<?= number_format((float)$stats['claimed'], 2) ?></div>
          <div class="small text-muted"><i class="bi bi-receipt me-1"></i>pending + approved (rejected excluded)</div>
        </div>
      </div>
      <div class="col-sm-6">
        <?php $bal = (float)$stats['demand'] - (float)$stats['claimed']; ?>
        <div class="sms-card p-3 h-100">
          <div class="text-muted small text-uppercase" style="letter-spacing:.04em">Balance</div>
          <div class="display-6 fw-bold mt-1 <?= $bal > 0.005 ? 'text-danger' : ($bal < -0.005 ? 'text-warning' : 'text-success') ?>">
            ₹<?= number_format($bal, 2) ?>
          </div>
          <div class="small text-muted"><i class="bi bi-calculator me-1"></i>demand minus transactions</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Sport-Event × Gender pivot -->
<div class="sms-card p-3 mb-4">
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-grid-3x3-gap me-2"></i>Sport Events × Gender — Registration Counts</h6>
    <a href="/unit/registrations" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-clipboard-data me-1"></i>View Full Registrations
    </a>
  </div>

  <?php if (empty($pivot_rows)): ?>
    <p class="text-muted small mb-0 text-center py-3">
      No registrations yet for this unit. Click <a href="/unit/athletes/new">Add Athlete</a>
      to register the first one.
    </p>
  <?php else:
    // Roll-up totals for the footer.
    $tot = ['m' => 0, 'f' => 0, 'o' => 0, 't' => 0, 'd' => 0.0];
    foreach ($pivot_rows as $r) {
      $tot['m'] += (int)$r['male_count'];
      $tot['f'] += (int)$r['female_count'];
      $tot['o'] += (int)$r['other_count'];
      $tot['t'] += (int)$r['total_count'];
      $tot['d'] += (float)$r['demand'];
    }
  ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Sport</th>
            <th>Event Code</th>
            <th>Event</th>
            <th>Age / Gender</th>
            <th class="text-center"><?= e(genderLabel('male',   $event)) ?></th>
            <th class="text-center"><?= e(genderLabel('female', $event)) ?></th>
            <th class="text-center">Other</th>
            <th class="text-center fw-semibold">Total</th>
            <th class="text-end">Demand</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pivot_rows as $r):
            // Highlight the cell that matches the catalog row's own
            // gender — that's the one expected to accrue counts.
            $g = strtolower((string)($r['sport_event_gender'] ?? ''));
            $hl = fn($col) => $g === $col ? ' class="table-success fw-semibold"' : '';
          ?>
            <tr>
              <td><?= e($r['sport_name'] ?? '') ?></td>
              <td><code><?= e($r['event_code'] ?? '') ?></code></td>
              <td><?= e($r['event_name'] ?? '') ?></td>
              <td class="small text-muted">
                <?= e($r['sport_event_age_category'] ?? '—') ?> ·
                <?= e(genderLabel((string)($r['sport_event_gender'] ?? ''), $event)) ?>
              </td>
              <td<?= $hl('male')   ?> style="text-align:center"><?= (int)$r['male_count']   ?></td>
              <td<?= $hl('female') ?> style="text-align:center"><?= (int)$r['female_count'] ?></td>
              <td class="text-center"><?= (int)$r['other_count']  ?></td>
              <td class="text-center fw-semibold"><?= (int)$r['total_count'] ?></td>
              <td class="text-end">₹<?= number_format((float)$r['demand'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light">
          <tr>
            <th colspan="4" class="text-end">Totals</th>
            <th class="text-center"><?= $tot['m'] ?></th>
            <th class="text-center"><?= $tot['f'] ?></th>
            <th class="text-center"><?= $tot['o'] ?></th>
            <th class="text-center fw-semibold"><?= $tot['t'] ?></th>
            <th class="text-end">₹<?= number_format($tot['d'], 2) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
    <small class="text-muted d-block mt-2">
      <i class="bi bi-info-circle me-1"></i>
      Green-shaded cells mark each catalog row's own gender — that column is the one
      that normally accrues registrations because the picker filters by athlete gender.
    </small>
  <?php endif; ?>
</div>

<!-- ── Unit Logo crop modal ──────────────────────────────────── -->
<div class="modal fade" id="unitLogoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-semibold"><i class="bi bi-crop me-2"></i>Crop Unit Logo</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-3">
        <div style="max-height:420px;overflow:hidden">
          <img id="unitLogoCropImg" src="" alt="Crop" style="max-width:100%;display:block">
        </div>
        <small class="text-muted d-block mt-2">Drag to reposition · Scroll to zoom · 1:1 square crop</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary fw-semibold" onclick="applyUnitLogoCrop()">
          <i class="bi bi-check-lg me-1"></i>Use Logo
        </button>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script>
(function () {
  const CSRF    = '<?= e($csrfToken) ?>';
  const UNIT_ID = <?= (int)($active_unit['id'] ?? 0) ?>;
  let cropper = null, modal = null;

  function getModal() {
    if (!modal) {
      if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return null;
      modal = new bootstrap.Modal(document.getElementById('unitLogoModal'));
    }
    return modal;
  }

  window.initUnitLogoCrop = function (input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    if (!/^image\/(jpeg|png|webp)$/i.test(file.type)) {
      alert('Please choose a JPG, PNG or WEBP image.');
      input.value = ''; return;
    }
    const reader = new FileReader();
    reader.onload = function (e) {
      const img = document.getElementById('unitLogoCropImg');
      const modalEl = document.getElementById('unitLogoModal');
      modalEl.addEventListener('shown.bs.modal', function build() {
        const make = () => {
          if (cropper) cropper.destroy();
          cropper = new Cropper(img, {
            aspectRatio: 1,          // square
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 0.9,
            guides: true,
            center: true,
          });
        };
        if (img.complete && img.naturalWidth > 0) make();
        else img.addEventListener('load', make, { once: true });
      }, { once: true });
      img.src = e.target.result;
      const m = getModal();
      if (m) m.show();
    };
    reader.readAsDataURL(file);
  };

  window.applyUnitLogoCrop = function () {
    if (!cropper) return;
    let canvas;
    try {
      canvas = cropper.getCroppedCanvas({
        width: 400, height: 400,
        fillColor: '#fff', imageSmoothingQuality: 'high',
      });
    } catch (e) { canvas = null; }
    if (!canvas) { alert('Could not generate the cropped image.'); return; }

    const m = getModal();
    if (m) m.hide();
    document.getElementById('unitLogoSaving').classList.remove('d-none');

    canvas.toBlob(async function (blob) {
      if (!blob) {
        document.getElementById('unitLogoSaving').classList.add('d-none');
        alert('Could not encode the cropped image.'); return;
      }
      const fd = new FormData();
      fd.append('_token', CSRF);
      fd.append('unit_id', UNIT_ID);
      fd.append('logo', blob, 'logo.jpg');
      try {
        const res  = await fetch('/unit/unit-logo', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success && data.logo_url) {
          const url = data.logo_url + '?t=' + Date.now();
          const box = document.getElementById('unitLogoBox');
          box.innerHTML = '<img src="' + url + '" id="unitLogoImg" alt="Unit Logo" width="84" height="84"'
            + ' class="rounded" style="object-fit:cover;border:1px solid #e2e8f0;background:#fff">';
        } else {
          alert(data.message || 'Logo upload failed.');
        }
      } catch (e) {
        alert('Network error while uploading the logo.');
      }
      document.getElementById('unitLogoSaving').classList.add('d-none');
      document.getElementById('unitLogoFile').value = '';
    }, 'image/jpeg', 0.9);
  };
})();
</script>
<?php endif; ?>
