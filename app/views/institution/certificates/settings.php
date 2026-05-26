<?php
$pageTitle = 'Certificate Settings — ' . $event['name'];
$bg     = $event['cert_bg_image'] ?? '';
$body   = $event['cert_body_template'] ?? '';
$prefix = $event['cert_no_prefix'] ?? '';
$next   = (int)($event['cert_no_next'] ?? 1);
if ($body === '') {
    // A reasonable starter template the admin can edit. Placeholders
    // (in double-braces) are substituted at render time.
    $body = "This is to certify that {{name}} (Comp. No. {{competitor_no}}) "
          . "from {{unit_name}} has participated in {{event_name}} held at "
          . "{{event_location}} on {{event_dates}}.";
}
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
          Use these placeholders — they're replaced when a certificate is rendered:
          <code>{{certificate_no}}</code>, <code>{{date}}</code>,
          <code>{{competitor_no}}</code>, <code>{{name}}</code>,
          <code>{{unit_name}}</code>, <code>{{event_name}}</code>,
          <code>{{event_dates}}</code>, <code>{{event_location}}</code>,
          <code>{{age}}</code>, <code>{{gender}}</code>.
        </p>
        <textarea name="cert_body_template" rows="8" class="form-control"
                  placeholder="This is to certify that {{name}} from {{unit_name}} …"><?= e($body) ?></textarea>
      </div>

      <div class="sms-card p-3 mb-3">
        <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-hash me-2"></i>Certificate Number</h6>
        <div class="row g-2 align-items-end">
          <div class="col-md-6">
            <label class="form-label small mb-1">Prefix</label>
            <input type="text" name="cert_no_prefix" maxlength="64" value="<?= e($prefix) ?>"
                   class="form-control form-control-sm"
                   placeholder="<?= e($event['event_code'] ?? 'CERT') ?>">
            <small class="text-muted d-block mt-1">
              Used as <code>PREFIX/0001</code> etc. Defaults to the Event Code when blank.
            </small>
          </div>
          <div class="col-md-6">
            <label class="form-label small mb-1">Next Sequence #</label>
            <input type="text" value="<?= (int)$next ?>" class="form-control form-control-sm" disabled>
            <small class="text-muted d-block mt-1">Auto-incremented as certificates are generated.</small>
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
