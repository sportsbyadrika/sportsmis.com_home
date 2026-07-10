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
$rowsFirst  = (int)($event['cert_partb_rows_first']     ?? 7);
$rowsCont   = (int)($event['cert_partb_rows_cont']      ?? 25);
$contNameSz = (int)($event['cert_cont_name_size_pt']    ?? 13);
$contNameBd = (int)($event['cert_cont_name_bold']       ?? 1);
$contNameUc = (int)($event['cert_cont_name_uppercase']  ?? 1);
$showMqs        = (int)($event['cert_show_mqs']             ?? 0);
$noLabel        = (string)($event['cert_no_label']             ?? 'Certificate No:');
$showCompNo     = (int)($event['cert_show_competitor_no']    ?? 1);
$compNoLabel    = (string)($event['cert_competitor_no_label'] ?? 'Competitor No:');
$showPhoto      = (int)($event['cert_show_photo']            ?? 1);
$photoW         = (int)($event['cert_photo_width_mm']        ?? 32);
$photoH         = (int)($event['cert_photo_height_mm']       ?? 38);
$photoNameGap   = (int)($event['cert_photo_name_gap_mm']     ?? 6);
$showMedalBg    = (int)($event['cert_show_medal_row_bg']     ?? 1);
$dateFormat     = (string)($event['cert_date_format']        ?? 'd M Y');
// Supported certificate date formats → live example (using a fixed sample date).
$sampleDate     = new DateTimeImmutable('2026-06-13');
$dateFormats    = ['d M Y', 'd F Y', 'd/m/Y', 'd-m-Y'];
if (!in_array($dateFormat, $dateFormats, true)) $dateFormat = 'd M Y';

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
            <label class="form-label small mb-1">Prefix <span class="text-muted">(optional)</span></label>
            <input type="text" name="cert_no_prefix" maxlength="64" value="<?= e($prefix) ?>"
                   class="form-control form-control-sm"
                   placeholder="leave blank for no prefix">
            <small class="text-muted d-block mt-1">
              Leave blank to format certs as <code>0001/2026</code> instead of <code>PREFIX/0001/2026</code>.
            </small>
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
        <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-tags me-2"></i>Labels &amp; Photo</h6>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small mb-1">Certificate Number Label</label>
            <input type="text" name="cert_no_label" maxlength="60"
                   value="<?= e($noLabel) ?>" class="form-control form-control-sm"
                   placeholder="Certificate No:">
            <small class="text-muted d-block mt-1">
              Text shown before the certificate number. Examples:
              <code>Certificate No:</code>, <code>Cert No:</code>, <code>No:</code>
            </small>
          </div>
          <div class="col-md-6">
            <label class="form-label small mb-1">Competitor Number Label</label>
            <input type="text" name="cert_competitor_no_label" maxlength="60"
                   value="<?= e($compNoLabel) ?>" class="form-control form-control-sm"
                   placeholder="Competitor No:">
            <div class="form-check form-switch mt-2">
              <input class="form-check-input" type="checkbox" role="switch"
                     id="showCompNoSwitch" name="cert_show_competitor_no" value="1"
                     <?= $showCompNo ? 'checked' : '' ?>>
              <label class="form-check-label small" for="showCompNoSwitch">
                Show the Competitor Number line on the meta strip
              </label>
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label small mb-1">Date Format</label>
            <select name="cert_date_format" class="form-select form-select-sm">
              <?php foreach ($dateFormats as $df): ?>
                <option value="<?= e($df) ?>" <?= $df === $dateFormat ? 'selected' : '' ?>>
                  <?= e($sampleDate->format($df)) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted d-block mt-1">
              Applies to the <code>{{date}}</code> and <code>{{event_dates}}</code> placeholders on the certificate.
            </small>
          </div>

          <div class="col-12">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch"
                     id="showPhotoSwitch" name="cert_show_photo" value="1"
                     onchange="document.getElementById('photoSizingBlock').style.display = this.checked ? '' : 'none'"
                     <?= $showPhoto ? 'checked' : '' ?>>
              <label class="form-check-label small" for="showPhotoSwitch">
                Show the athlete <strong>photo</strong> in the certificate body
              </label>
              <div class="text-muted small">Turn off for text-only certificates. When off the body paragraph slides up to fill the photo slot.</div>
            </div>
          </div>

          <div class="col-12">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch"
                     id="showMedalBgSwitch" name="cert_show_medal_row_bg" value="1"
                     <?= $showMedalBg ? 'checked' : '' ?>>
              <label class="form-check-label small" for="showMedalBgSwitch">
                Tint Gold / Silver / Bronze rows with their <strong>medal background colour</strong> in Part B
              </label>
              <div class="text-muted small">Turn off for a cleaner monochrome table — medal-winning rows still show GOLD / SILVER / BRONZE in the Remarks column, just without the tint.</div>
            </div>
          </div>

          <div id="photoSizingBlock" class="col-12" style="<?= $showPhoto ? '' : 'display:none' ?>">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label small mb-1">Photo Width (mm)</label>
                <input type="number" name="cert_photo_width_mm" min="10" max="120"
                       value="<?= (int)$photoW ?>" class="form-control form-control-sm">
              </div>
              <div class="col-md-4">
                <label class="form-label small mb-1">Photo Height (mm)</label>
                <input type="number" name="cert_photo_height_mm" min="10" max="160"
                       value="<?= (int)$photoH ?>" class="form-control form-control-sm">
              </div>
              <div class="col-md-4">
                <label class="form-label small mb-1">Gap below Photo (mm)</label>
                <input type="number" name="cert_photo_name_gap_mm" min="0" max="60"
                       value="<?= (int)$photoNameGap ?>" class="form-control form-control-sm">
                <small class="text-muted d-block mt-1">
                  Vertical spacing between the photo and the first line of body text (usually the athlete name).
                </small>
              </div>
            </div>
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

        <hr class="my-3">
        <h6 class="fw-semibold small text-uppercase text-muted mb-2"
            style="letter-spacing:.05em">Rows per page</h6>
        <p class="small text-muted mb-2">
          The exact number of event rows printed on each page. Use this
          to dial in the overflow split without depending on row-height
          guesses — if 7 rows fit comfortably on the first page above
          the signatures, set Rows on first page = 7.
        </p>
        <div class="row g-2 align-items-end">
          <div class="col-md-6">
            <label class="form-label small mb-1">Rows on first page</label>
            <input type="number" name="cert_partb_rows_first" min="1" max="50"
                   value="<?= $rowsFirst ?>" class="form-control form-control-sm">
            <small class="text-muted d-block mt-1">
              First page carries the body + photo, so usually fits fewer rows.
            </small>
          </div>
          <div class="col-md-6">
            <label class="form-label small mb-1">Rows per overflow page</label>
            <input type="number" name="cert_partb_rows_cont" min="1" max="80"
                   value="<?= $rowsCont ?>" class="form-control form-control-sm">
            <small class="text-muted d-block mt-1">
              Continuation pages have no body — they can fit many more rows.
            </small>
          </div>
        </div>

        <hr class="my-3">
        <h6 class="fw-semibold small text-uppercase text-muted mb-2"
            style="letter-spacing:.05em">Athlete name on overflow pages</h6>
        <p class="small text-muted mb-2">
          The athlete's name printed in place of the body block on each
          continuation page, so the reader can tell whose participation
          table continues there.
        </p>
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label small mb-1">Font size (pt)</label>
            <input type="number" name="cert_cont_name_size_pt" min="6" max="60"
                   value="<?= $contNameSz ?>" class="form-control form-control-sm">
          </div>
          <div class="col-md-4">
            <label class="form-label small mb-1 d-block">Style</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" id="contNameBold"
                     name="cert_cont_name_bold" value="1"
                     <?= $contNameBd ? 'checked' : '' ?>>
              <label class="form-check-label small" for="contNameBold">Bold</label>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label small mb-1 d-block">Case</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" id="contNameUpper"
                     name="cert_cont_name_uppercase" value="1"
                     <?= $contNameUc ? 'checked' : '' ?>>
              <label class="form-check-label small" for="contNameUpper">UPPERCASE</label>
            </div>
          </div>
        </div>

        <hr class="my-3">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <label class="form-label small mb-0 fw-semibold" for="certShowMqs">
              Show MQS column in Part B
            </label>
            <div class="text-muted small">
              When on, every printed certificate includes an MQS column sourced
              from the per-sport-event MQS configured on the event. Team rows
              stay blank.
            </div>
          </div>
          <div class="form-check form-switch m-0">
            <input class="form-check-input" type="checkbox" role="switch"
                   id="certShowMqs" name="cert_show_mqs" value="1"
                   <?= $showMqs ? 'checked' : '' ?>>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="sms-card p-3 mb-3">
        <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
          <h6 class="fw-semibold mb-0">
            <i class="bi bi-image me-2"></i>Background &amp; Live Preview
          </h6>
          <a href="/institution/events/<?= e($eventHash) ?>/certificates/preview"
             class="btn btn-sm btn-outline-secondary" target="_blank">
            <i class="bi bi-arrows-fullscreen me-1"></i>Open full page
          </a>
        </div>
        <p class="small text-muted mb-2">
          Upload an A4 portrait image (PNG / JPG / WebP). The preview
          below uses the <strong>last saved</strong> settings with a
          sample competitor record &mdash; save your changes to refresh it.
        </p>
        <input type="file" name="cert_bg_image" accept="image/*"
               class="form-control form-control-sm mb-3">
        <?php if ($bg): ?>
          <div style="border:1px solid #d0d6dd;background:#eef2f7;padding:10px;
                      max-height:520px;overflow:auto;text-align:center">
            <iframe src="/institution/events/<?= e($eventHash) ?>/certificates/preview"
                    style="width:210mm;height:297mm;border:0;display:inline-block;
                           transform:scale(0.5);transform-origin:top center;
                           background:#fff;box-shadow:0 6px 18px rgba(0,0,0,.12)">
            </iframe>
          </div>
        <?php else: ?>
          <div class="border rounded bg-light text-muted text-center py-5">
            <i class="bi bi-image fs-1"></i>
            <div class="small mt-1">
              No background uploaded yet &mdash; save a background image to see the live preview.
            </div>
          </div>
        <?php endif; ?>
      </div>

      <button class="btn btn-primary w-100"><i class="bi bi-save me-1"></i>Save Settings</button>
    </div>
  </div>
</form>
