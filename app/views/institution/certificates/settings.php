<?php
$pageTitle = 'Certificate Settings — ' . $event['name'];
$bg     = $event['cert_bg_image'] ?? '';
$body   = $event['cert_body_template'] ?? '';
$prefix = $event['cert_no_prefix'] ?? '';
$suffix = $event['cert_no_suffix'] ?? '';
$next   = (int)($event['cert_no_next'] ?? 1);
$metaTop    = (int)($event['cert_meta_top_mm']     ?? 60);
$bodyTop    = (int)($event['cert_body_top_mm']     ?? 100);
$partbTop   = (int)($event['cert_partb_top_mm']         ?? 200);
$partbBot   = (int)($event['cert_partb_bottom_mm']      ?? 250);
$contTop    = (int)($event['cert_partb_cont_top_mm']    ?? 60);
$contBot    = (int)($event['cert_partb_cont_bottom_mm'] ?? 270);

// Sample row count — disable the sequence input only after the very first
// certificate has been issued so the starting number can be edited once.
require_once APP_ROOT . '/models/Event.php';
$issued = (int)(\Models\Event::rowsRaw(
    "SELECT COUNT(*) AS c FROM event_certificates WHERE event_id = ?",
    [(int)$event['id']])[0]['c'] ?? 0);

if ($body === '') {
    $body = "This is to certify that <strong><span style=\"font-size:18pt\">{{name}}</span></strong><br>"
          . "(Comp. No. {{competitor_no}}) of <strong>{{unit_name}}</strong>, {{unit_address}}<br>"
          . "has participated in <strong>{{event_name}}</strong> held at {{event_location}}<br>"
          . "on {{event_dates}}.";
}
$exampleNo = ($prefix ?: ($event['event_code'] ?? 'CERT'))
           . '/' . str_pad((string)$next, 4, '0', STR_PAD_LEFT)
           . ($suffix !== '' ? '/' . $suffix : '');
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/certificates" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-gear me-2"></i>Certificate Settings</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
</div>

<?= flashBag() ?>

<form method="POST" enctype="multipart/form-data"
      action="/institution/events/<?= e($eventHash) ?>/certificates/settings">
  <?= csrf() ?>
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="sms-card p-3 mb-3">
        <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-card-text me-2"></i>Certificate Body</h6>
        <p class="small text-muted mb-2">
          HTML allowed — use <code>&lt;strong&gt;</code>, <code>&lt;br&gt;</code>,
          <code>&lt;span style="font-size:18pt"&gt;</code>, etc. Placeholders are
          replaced when a certificate is rendered:
          <code>{{certificate_no}}</code>, <code>{{date}}</code>,
          <code>{{competitor_no}}</code>, <code>{{name}}</code>,
          <code>{{unit_name}}</code>, <code>{{unit_address}}</code>,
          <code>{{event_name}}</code>, <code>{{event_dates}}</code>,
          <code>{{event_location}}</code>, <code>{{age}}</code>, <code>{{gender}}</code>.
        </p>
        <textarea name="cert_body_template" rows="9" class="form-control font-monospace"
                  style="font-size:12px"
                  placeholder="This is to certify that {{name}} from {{unit_name}} …"><?= e($body) ?></textarea>
      </div>

      <div class="sms-card p-3 mb-3">
        <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-hash me-2"></i>Certificate Number</h6>
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label small mb-1">Prefix</label>
            <input type="text" name="cert_no_prefix" maxlength="64" value="<?= e($prefix) ?>"
                   class="form-control form-control-sm"
                   placeholder="<?= e($event['event_code'] ?? 'CERT') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label small mb-1">Suffix <span class="text-muted">(optional)</span></label>
            <input type="text" name="cert_no_suffix" maxlength="64" value="<?= e($suffix) ?>"
                   class="form-control form-control-sm" placeholder="e.g. 2026">
          </div>
          <div class="col-md-4">
            <label class="form-label small mb-1">Next Sequence #</label>
            <input type="number" name="cert_no_next" min="1" value="<?= (int)$next ?>"
                   class="form-control form-control-sm"
                   <?= $issued > 0 ? 'readonly' : '' ?>>
            <?php if ($issued > 0): ?>
              <small class="text-muted d-block mt-1">
                Locked — <?= $issued ?> certificate<?= $issued === 1 ? '' : 's' ?> already issued.
              </small>
            <?php else: ?>
              <small class="text-muted d-block mt-1">
                Editable until the first certificate is generated.
              </small>
            <?php endif; ?>
          </div>
          <div class="col-12">
            <small class="text-muted">Format: <code><?= e($exampleNo) ?></code></small>
          </div>
        </div>
      </div>

      <div class="sms-card p-3 mb-3">
        <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-arrows-collapse me-2"></i>Layout Positions (mm from page top)</h6>
        <p class="small text-muted mb-2">
          A4 portrait page is 297 mm tall. Tune each block to clear the
          background template's heading, body and signature areas.
        </p>
        <div class="row g-2 align-items-end">
          <div class="col-md-6">
            <label class="form-label small mb-1">Certificate-No row (Cert No / Comp No / Date)</label>
            <input type="number" name="cert_meta_top_mm" min="5" max="200"
                   value="<?= $metaTop ?>" class="form-control form-control-sm">
            <small class="text-muted d-block mt-1">
              Top edge of the top meta strip on the certificate.
            </small>
          </div>
          <div class="col-md-6">
            <label class="form-label small mb-1">"This is to certify that" label</label>
            <input type="number" name="cert_body_top_mm" min="20" max="250"
                   value="<?= $bodyTop ?>" class="form-control form-control-sm">
            <small class="text-muted d-block mt-1">
              Start of the photo + body paragraph block.
            </small>
          </div>
          <div class="col-md-6">
            <label class="form-label small mb-1">Event scoring table — Top</label>
            <input type="number" name="cert_partb_top_mm" min="20" max="280"
                   value="<?= $partbTop ?>" class="form-control form-control-sm">
            <small class="text-muted d-block mt-1">
              Top of the Part B participation table (the "virtual box" begins here).
            </small>
          </div>
          <div class="col-md-6">
            <label class="form-label small mb-1">Event scoring table — Bottom (max)</label>
            <input type="number" name="cert_partb_bottom_mm" min="40" max="290"
                   value="<?= $partbBot ?>" class="form-control form-control-sm">
            <small class="text-muted d-block mt-1">
              Hard ceiling for the table — rows that don't fit between
              Top and Bottom continue on a new page (<em>"Continued — page X of Y"</em>).
              Keep a margin above the background's signature area.
            </small>
          </div>
        </div>

        <hr class="my-3">
        <h6 class="fw-semibold small text-uppercase text-muted mb-2"
            style="letter-spacing:.05em">Overflow continuation page</h6>
        <p class="small text-muted mb-2">
          When the participation table needs a second sheet, these
          positions control where the "Continued" table sits. The
          continuation page has no certificate body, so it usually
          starts higher and can extend lower than the first.
        </p>
        <div class="row g-2 align-items-end">
          <div class="col-md-6">
            <label class="form-label small mb-1">Overflow table — Top</label>
            <input type="number" name="cert_partb_cont_top_mm" min="5" max="280"
                   value="<?= $contTop ?>" class="form-control form-control-sm">
          </div>
          <div class="col-md-6">
            <label class="form-label small mb-1">Overflow table — Bottom (max)</label>
            <input type="number" name="cert_partb_cont_bottom_mm" min="40" max="290"
                   value="<?= $contBot ?>" class="form-control form-control-sm">
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="sms-card p-3 mb-3">
        <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-image me-2"></i>Background Image</h6>
        <p class="small text-muted">
          Upload an A4 portrait image (PNG / JPG / WebP). It fills the entire
          page; the body text + photo + Part B table are overlaid on top.
        </p>
        <?php if ($bg): ?>
          <div class="mb-2">
            <img src="<?= e($bg) ?>?t=<?= time() ?>" alt="" class="img-fluid border rounded">
          </div>
        <?php else: ?>
          <div class="border rounded bg-light text-muted text-center py-5 mb-2">
            <i class="bi bi-image fs-1"></i>
            <div class="small mt-1">No background uploaded yet.</div>
          </div>
        <?php endif; ?>
        <input type="file" name="cert_bg_image" accept="image/*" class="form-control form-control-sm">
      </div>

      <button class="btn btn-primary w-100"><i class="bi bi-save me-1"></i>Save Settings</button>
    </div>
  </div>
</form>

<!-- ─ Live Preview ────────────────────────────────────────────────── -->
<div class="sms-card p-3 mt-4">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <h6 class="fw-semibold mb-0"><i class="bi bi-eye me-2"></i>Preview (saved settings)</h6>
    <a href="/institution/events/<?= e($eventHash) ?>/certificates/preview"
       class="btn btn-sm btn-outline-secondary" target="_blank">
      <i class="bi bi-arrows-fullscreen me-1"></i>Open full page
    </a>
  </div>
  <p class="small text-muted mb-2">
    Preview uses the <strong>last saved</strong> settings with a sample
    competitor record. Save your changes above to refresh.
  </p>
  <div style="border:1px solid #d0d6dd;background:#eef2f7;padding:12px;
              max-height:520px;overflow:auto;text-align:center">
    <iframe src="/institution/events/<?= e($eventHash) ?>/certificates/preview"
            style="width:210mm;height:297mm;border:0;display:inline-block;
                   transform:scale(0.5);transform-origin:top center;
                   background:#fff;box-shadow:0 6px 18px rgba(0,0,0,.12)">
    </iframe>
  </div>
</div>
