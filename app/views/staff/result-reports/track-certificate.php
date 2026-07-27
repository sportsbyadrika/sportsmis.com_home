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
      <form method="GET" action="<?= e($printUrl) ?>" target="_blank" rel="noopener">
        <div class="mb-2">
          <label class="form-label small mb-1">Event Category</label>
          <select name="category_id" class="form-select form-select-sm">
            <option value="">All event categories</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label small mb-1">Age Category</label>
          <select name="age_category_id" class="form-select form-select-sm">
            <option value="">All age categories</option>
            <?php foreach ($age_categories as $ac): ?>
              <option value="<?= (int)$ac['id'] ?>"><?= e($ac['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-file-earmark-pdf me-1"></i>Generate &amp; Print</button>
        <div class="form-text">Save the layout first, then generate. Print onto the pre-printed paper.</div>
      </form>
    </div>
  </div>
</div>
