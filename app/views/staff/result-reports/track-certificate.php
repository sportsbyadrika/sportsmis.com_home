<?php
/**
 * Athletics / Skating — Certificate layout config (for pre-printed paper).
 * Set X/Y (mm from top-left) + font size for each variable field, orientation,
 * the two constant values, and (Merit) the certificate-number format.
 * Expects: $event, $cert_type ('merit'|'appreciation'), $defs, $config,
 *          $categories, $age_categories.
 */
$isMerit  = $cert_type === 'merit';
$title    = $isMerit ? 'Certificate of Merit' : 'Certificate of Appreciation';
$pageTitle = $title . ' — ' . $event['name'];
$printUrl = $isMerit
  ? '/event-staff/result-reports/merit-certificate/print'
  : '/event-staff/result-reports/appreciation-certificate/print';
$f = $config['fields'];
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/result-reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-patch-check me-2"></i><?= e($title) ?></h5>
  <span class="badge bg-info-subtle text-info-emphasis">Athletics / Skating</span>
</div>

<?= flashBag() ?>

<div class="alert alert-info py-2 small">
  <i class="bi bi-info-circle me-1"></i>These certificates print onto your <strong>pre-printed</strong> certificate paper.
  Only the variable fields below are printed, at the exact <strong>X / Y position (mm from the top-left)</strong> you set.
  Adjust positions, print one test page, and fine-tune.
</div>

<div class="row g-3">
  <!-- Config form -->
  <div class="col-lg-8">
    <div class="sms-card p-3">
      <form method="POST" action="/event-staff/result-reports/certificate/save">
        <?= csrf() ?>
        <input type="hidden" name="cert_type" value="<?= e($cert_type) ?>">

        <div class="row g-2 align-items-end mb-3">
          <div class="col-sm-4">
            <label class="form-label small mb-1">Orientation</label>
            <select name="orientation" class="form-select form-select-sm">
              <option value="landscape" <?= $config['orientation'] === 'landscape' ? 'selected' : '' ?>>Landscape (default)</option>
              <option value="portrait"  <?= $config['orientation'] === 'portrait'  ? 'selected' : '' ?>>Portrait</option>
            </select>
          </div>
          <?php if ($isMerit): ?>
            <div class="col-sm-3">
              <label class="form-label small mb-1">Cert. No. Prefix</label>
              <input type="text" name="cert_prefix" class="form-control form-control-sm" value="<?= e($config['cert_prefix']) ?>" placeholder="e.g. SZSC/">
            </div>
            <div class="col-sm-2">
              <label class="form-label small mb-1">Start No.</label>
              <input type="number" name="cert_seq_start" min="1" class="form-control form-control-sm" value="<?= (int)$config['cert_seq_start'] ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label small mb-1">Cert. No. Suffix</label>
              <input type="text" name="cert_suffix" class="form-control form-control-sm" value="<?= e($config['cert_suffix']) ?>" placeholder="e.g. /2025">
            </div>
          <?php endif; ?>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-sm-4">
            <label class="form-label small mb-1">Certificate Date</label>
            <input type="date" name="cert_date" class="form-control form-control-sm" value="<?= e($config['cert_date']) ?>">
          </div>
          <div class="col-sm-4">
            <label class="form-label small mb-1">Constant Value 1 (text)</label>
            <input type="text" name="const1_text" class="form-control form-control-sm" value="<?= e($config['const1_text']) ?>">
          </div>
          <div class="col-sm-4">
            <label class="form-label small mb-1">Constant Value 2 (text)</label>
            <input type="text" name="const2_text" class="form-control form-control-sm" value="<?= e($config['const2_text']) ?>">
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm align-middle mb-2">
            <thead class="table-light">
              <tr>
                <th style="width:40px" class="text-center">On</th>
                <th>Field</th>
                <th style="width:90px" class="text-center">X (mm)</th>
                <th style="width:90px" class="text-center">Y (mm)</th>
                <th style="width:90px" class="text-center">Size (pt)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($defs as $k => $d): $fv = $f[$k]; ?>
                <tr>
                  <td class="text-center"><input type="checkbox" class="form-check-input" name="en_<?= e($k) ?>" value="1" <?= $fv['enabled'] ? 'checked' : '' ?>></td>
                  <td class="small fw-medium"><?= e($d[0]) ?></td>
                  <td><input type="number" step="0.5" name="x_<?= e($k) ?>" class="form-control form-control-sm" value="<?= e($fv['x']) ?>"></td>
                  <td><input type="number" step="0.5" name="y_<?= e($k) ?>" class="form-control form-control-sm" value="<?= e($fv['y']) ?>"></td>
                  <td><input type="number" step="0.5" min="5" name="size_<?= e($k) ?>" class="form-control form-control-sm" value="<?= e($fv['size']) ?>"></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Save Layout</button>
      </form>
    </div>
  </div>

  <!-- Generate / print -->
  <div class="col-lg-4">
    <div class="sms-card p-3">
      <h6 class="fw-semibold border-bottom pb-2 mb-2"><i class="bi bi-printer me-1"></i>Generate Certificates</h6>
      <p class="small text-muted">
        <?php if ($isMerit): ?>
          Generates a certificate for every 1st / 2nd / 3rd place (individual &amp; team) — numbered sequentially from the start number.
        <?php else: ?>
          Generates a certificate for every approved participant.
        <?php endif; ?>
      </p>
      <form method="GET" action="<?= e($printUrl) ?>" target="_blank" rel="noopener" id="certGenForm">
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small mb-1">Event Category</label>
            <select id="certFilterCat" class="form-select form-select-sm" onchange="certFilterEvents()">
              <option value="">All categories</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label small mb-1">Age Category</label>
            <select id="certFilterAge" class="form-select form-select-sm" onchange="certFilterEvents()">
              <option value="">All ages</option>
              <?php foreach ($age_categories as $ac): ?>
                <option value="<?= (int)$ac['id'] ?>"><?= e($ac['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="d-flex align-items-center mb-1">
          <label class="form-label small mb-0">Events <span class="text-muted">(tick to include)</span></label>
          <div class="form-check ms-auto mb-0">
            <input class="form-check-input" type="checkbox" id="certSelAll" onchange="certToggleAll(this)">
            <label class="form-check-label small" for="certSelAll">All visible</label>
          </div>
        </div>
        <div id="certEventList" class="border rounded p-2 mb-2" style="max-height:280px;overflow:auto">
          <?php if (empty($events)): ?>
            <div class="text-muted small">No sport-events found.</div>
          <?php else: foreach ($events as $ev):
            $label = trim((string)($ev['sport_event_name'] ?? '')) ?: (string)($ev['event_code'] ?? '');
          ?>
            <div class="form-check cert-ev-row"
                 data-cat="<?= (int)($ev['category_id'] ?? 0) ?>" data-age="<?= (int)($ev['age_id'] ?? 0) ?>">
              <input class="form-check-input cert-ev" type="checkbox" name="esid[]" value="<?= (int)$ev['esid'] ?>" id="certEv<?= (int)$ev['esid'] ?>">
              <label class="form-check-label small" for="certEv<?= (int)$ev['esid'] ?>">
                <?= e($label) ?><?php if (($ev['gender'] ?? '') !== ''): ?> — <?= e(ucfirst($ev['gender'])) ?><?php endif; ?>
                <span class="text-muted"><?= e($ev['category_name'] ?? '') ?><?php if (($ev['age_name'] ?? '') !== ''): ?> · <?= e($ev['age_name']) ?><?php endif; ?></span>
              </label>
            </div>
          <?php endforeach; endif; ?>
        </div>
        <div class="small text-muted mb-2" id="certPickCount"></div>

        <button type="submit" class="btn btn-sm btn-success w-100" onclick="return certBeforeGen()">
          <i class="bi bi-file-earmark-pdf me-1"></i>Generate &amp; Print
        </button>
        <div class="form-text">Save the layout first. Leaving all unticked generates for every listed event.</div>
      </form>
      <script>
        function certFilterEvents() {
          var cat = document.getElementById('certFilterCat').value;
          var age = document.getElementById('certFilterAge').value;
          document.querySelectorAll('#certEventList .cert-ev-row').forEach(function (row) {
            var ok = (!cat || row.dataset.cat === cat) && (!age || row.dataset.age === age);
            row.classList.toggle('d-none', !ok);
            if (!ok) { var cb = row.querySelector('.cert-ev'); if (cb) cb.checked = false; }
          });
          var sa = document.getElementById('certSelAll'); if (sa) sa.checked = false;
          certCount();
        }
        function certToggleAll(cb) {
          document.querySelectorAll('#certEventList .cert-ev-row:not(.d-none) .cert-ev').forEach(function (x) { x.checked = cb.checked; });
          certCount();
        }
        function certCount() {
          var n = document.querySelectorAll('#certEventList .cert-ev:checked').length;
          var el = document.getElementById('certPickCount');
          if (el) el.textContent = n ? (n + ' event' + (n === 1 ? '' : 's') + ' selected') : 'No events selected — will generate for all listed.';
        }
        function certBeforeGen() {
          // Drop hidden (filtered-out) checkboxes so they don't submit.
          document.querySelectorAll('#certEventList .cert-ev-row.d-none .cert-ev').forEach(function (x) { x.checked = false; });
          return true;
        }
        document.addEventListener('DOMContentLoaded', function () {
          document.querySelectorAll('#certEventList .cert-ev').forEach(function (x) { x.addEventListener('change', certCount); });
          certCount();
        });
      </script>
    </div>
  </div>
</div>
