<?php
/**
 * Athletics / Skating — Certificate main page (Merit / Appreciation).
 * Generate new certificates, view the issued register (generated / printed
 * flags), delete all issued, and (collapsible) configure the layout for the
 * pre-printed paper.
 * Expects: $event, $cert_type, $defs, $config, $categories, $age_categories,
 *          $events, $issued.
 */
$isMerit  = $cert_type === 'merit';
$title    = $isMerit ? 'Certificate of Merit' : 'Certificate of Appreciation';
$pageTitle = $title . ' — ' . $event['name'];
$printUrl = $isMerit
  ? '/event-staff/result-reports/merit-certificate/print'
  : '/event-staff/result-reports/appreciation-certificate/print';
$f = $config['fields'];
$issued = $issued ?? [];
$printedCount = 0;
foreach ($issued as $ic) { if (!empty($ic['is_printed'])) $printedCount++; }
$fmtDt = function ($d) { $d = trim((string)$d); return ($d !== '' && ($ts = strtotime($d))) ? date('d M Y H:i', $ts) : ''; };
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/result-reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-patch-check me-2"></i><?= e($title) ?></h5>
  <span class="badge bg-info-subtle text-info-emphasis">Athletics / Skating</span>
  <button class="btn btn-sm btn-outline-secondary ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#certLayout">
    <i class="bi bi-sliders me-1"></i>Layout Settings
  </button>
</div>

<?= flashBag() ?>

<div class="row g-3">
  <!-- Generate -->
  <div class="col-lg-5">
    <div class="sms-card p-3 h-100">
      <h6 class="fw-semibold border-bottom pb-2 mb-2"><i class="bi bi-printer me-1"></i>Generate Certificates</h6>
      <p class="small text-muted mb-2">
        <?php if ($isMerit): ?>Filter, pick events, and generate 1st / 2nd / 3rd (individual &amp; team) certificates — numbered sequentially.
        <?php else: ?>Filter, pick events, and generate a certificate for every approved participant.<?php endif; ?>
      </p>
      <form method="GET" action="<?= e($printUrl) ?>" target="_blank" rel="noopener" id="certGenForm">
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small mb-1">Event Category</label>
            <select id="certFilterCat" class="form-select form-select-sm" onchange="certFilterEvents()">
              <option value="">All categories</option>
              <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label small mb-1">Age Category</label>
            <select id="certFilterAge" class="form-select form-select-sm" onchange="certFilterEvents()">
              <option value="">All ages</option>
              <?php foreach ($age_categories as $ac): ?><option value="<?= (int)$ac['id'] ?>"><?= e($ac['name']) ?></option><?php endforeach; ?>
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
        <div id="certEventList" class="border rounded p-2 mb-2" style="max-height:240px;overflow:auto">
          <?php if (empty($events)): ?>
            <div class="text-muted small">No sport-events found.</div>
          <?php else:
            $finalCounts = $final_counts ?? [];
            foreach ($events as $ev):
            $label = trim((string)($ev['sport_event_name'] ?? '')) ?: (string)($ev['event_code'] ?? '');
            $fc    = $finalCounts[(int)$ev['esid']] ?? null;
          ?>
            <div class="form-check cert-ev-row d-flex align-items-center gap-2" data-cat="<?= (int)($ev['category_id'] ?? 0) ?>" data-age="<?= (int)($ev['age_id'] ?? 0) ?>">
              <input class="form-check-input cert-ev mt-0" type="checkbox" name="esid[]" value="<?= (int)$ev['esid'] ?>" id="certEv<?= (int)$ev['esid'] ?>">
              <label class="form-check-label small flex-grow-1" for="certEv<?= (int)$ev['esid'] ?>">
                <?= e($label) ?><?php if (($ev['gender'] ?? '') !== ''): ?> — <?= e(ucfirst($ev['gender'])) ?><?php endif; ?>
                <span class="text-muted"><?= e($ev['category_name'] ?? '') ?><?php if (($ev['age_name'] ?? '') !== ''): ?> · <?= e($ev['age_name']) ?><?php endif; ?></span>
              </label>
              <?php if ($fc === null): ?>
                <span class="badge bg-light text-muted border" title="No final round configured">Final: —</span>
              <?php else:
                $med  = (int)$fc['medalists'];      // rank 1/2/3 entered (certs use this)
                $medP = (int)$fc['medalists_pub'];  // of those, published
                $ent  = (int)$fc['entered'];
                // Certificates generate from entered medal places (published or not),
                // so green whenever there is at least one rank 1/2/3.
                $cls = $med > 0 ? 'bg-success-subtle text-success-emphasis'
                     : ($ent > 0 ? 'bg-warning-subtle text-warning-emphasis' : 'bg-light text-muted border');
                $tip = $ent . ' result' . ($ent === 1 ? '' : 's') . ' entered · '
                     . $med . ' medal place' . ($med === 1 ? '' : 's') . ' (' . $medP . ' published)';
              ?>
                <span class="badge <?= $cls ?>" title="<?= e($tip) ?>">
                  Final: <?= $med ?> medal<?= $med === 1 ? '' : 's' ?><?php if ($med > 0): ?> · <?= $medP ?> pub<?php endif; ?>
                </span>
              <?php endif; ?>
            </div>
          <?php endforeach; endif; ?>
        </div>
        <div class="small text-muted mb-2" id="certPickCount"></div>
        <button type="submit" class="btn btn-sm btn-success w-100" onclick="return certBeforeGen()">
          <i class="bi bi-file-earmark-pdf me-1"></i>Generate &amp; Print
        </button>
        <div class="form-text">Save the layout first. Leaving all unticked generates for every listed event.</div>
      </form>
    </div>
  </div>

  <!-- Issued register -->
  <div class="col-lg-7">
    <div class="sms-card p-3 h-100">
      <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-2 flex-wrap">
        <h6 class="fw-semibold mb-0"><i class="bi bi-card-checklist me-1"></i>Issued Certificates</h6>
        <span class="badge bg-secondary-subtle text-secondary-emphasis"><?= count($issued) ?> issued · <?= $printedCount ?> printed</span>
        <?php if (!empty($issued)): ?>
          <form method="POST" action="/event-staff/result-reports/certificate/delete-all" class="ms-auto m-0"
                onsubmit="return confirm('Delete ALL <?= count($issued) ?> issued <?= e($title) ?> records? Numbering will restart from the first. This does not affect results.');">
            <?= csrf() ?>
            <input type="hidden" name="cert_type" value="<?= e($cert_type) ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete All &amp; Reset</button>
          </form>
        <?php endif; ?>
      </div>
      <?php if (empty($issued)): ?>
        <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i>No certificates issued yet. Generate above — each generated certificate is recorded here with a stable number and generated / printed status.</p>
      <?php else: ?>
        <div class="table-responsive" style="max-height:60vh;overflow:auto">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:44px">#</th>
                <?php if ($isMerit): ?><th style="width:120px">Cert. No.</th><?php endif; ?>
                <th>Recipient</th>
                <th>Institution</th>
                <?php if ($isMerit): ?><th style="width:80px">Prize</th><?php endif; ?>
                <th class="text-center" style="width:80px">Generated</th>
                <th class="text-center" style="width:80px">Printed</th>
              </tr>
            </thead>
            <tbody>
              <?php $i = 0; foreach ($issued as $ic): $i++; ?>
                <tr>
                  <td class="text-center"><?= $i ?></td>
                  <?php if ($isMerit): ?><td><code><?= e($ic['cert_number'] ?? '') ?></code></td><?php endif; ?>
                  <td><?= e($ic['recipient_name'] ?? '') ?>
                    <?php if (($ic['recipient_type'] ?? '') === 'team'): ?><span class="badge bg-primary-subtle text-primary-emphasis">Team</span><?php endif; ?>
                  </td>
                  <td class="small text-muted"><?= e($ic['school'] ?? '') ?></td>
                  <?php if ($isMerit): ?><td class="small"><?= e($ic['prize'] ?? '') ?></td><?php endif; ?>
                  <td class="text-center"><i class="bi bi-check-circle-fill text-success" title="<?= e($fmtDt($ic['generated_at'] ?? '')) ?>"></i></td>
                  <td class="text-center">
                    <?php if (!empty($ic['is_printed'])): ?>
                      <i class="bi bi-check-circle-fill text-success" title="<?= e($fmtDt($ic['printed_at'] ?? '')) ?>"></i>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Collapsible layout settings -->
<div class="collapse mt-3" id="certLayout">
  <div class="sms-card p-3">
    <div class="alert alert-info py-2 small">
      <i class="bi bi-info-circle me-1"></i>Certificates print onto <strong>pre-printed</strong> paper — only the fields below print, at the exact
      <strong>X / Y (mm from the top-left)</strong> and size you set. Print one test page and fine-tune.
    </div>
    <form method="POST" action="/event-staff/result-reports/certificate/save">
      <?= csrf() ?>
      <input type="hidden" name="cert_type" value="<?= e($cert_type) ?>">
      <div class="row g-2 align-items-end mb-3">
        <div class="col-sm-3">
          <label class="form-label small mb-1">Orientation</label>
          <select name="orientation" class="form-select form-select-sm">
            <option value="landscape" <?= $config['orientation'] === 'landscape' ? 'selected' : '' ?>>Landscape (default)</option>
            <option value="portrait"  <?= $config['orientation'] === 'portrait'  ? 'selected' : '' ?>>Portrait</option>
          </select>
        </div>
        <div class="col-sm-3">
          <label class="form-label small mb-1">Certificate Date</label>
          <input type="date" name="cert_date" class="form-control form-control-sm" value="<?= e($config['cert_date']) ?>">
        </div>
        <?php if ($isMerit): ?>
          <div class="col-sm-2">
            <label class="form-label small mb-1">No. Prefix</label>
            <input type="text" name="cert_prefix" class="form-control form-control-sm" value="<?= e($config['cert_prefix']) ?>" placeholder="SZSC/">
          </div>
          <div class="col-sm-2">
            <label class="form-label small mb-1">Start No.</label>
            <input type="number" name="cert_seq_start" min="1" class="form-control form-control-sm" value="<?= (int)$config['cert_seq_start'] ?>">
          </div>
          <div class="col-sm-2">
            <label class="form-label small mb-1">No. Suffix</label>
            <input type="text" name="cert_suffix" class="form-control form-control-sm" value="<?= e($config['cert_suffix']) ?>" placeholder="/2025">
          </div>
        <?php endif; ?>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-sm-6">
          <label class="form-label small mb-1">Constant Value 1 (text)</label>
          <input type="text" name="const1_text" class="form-control form-control-sm" value="<?= e($config['const1_text']) ?>">
        </div>
        <div class="col-sm-6">
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
    document.querySelectorAll('#certEventList .cert-ev-row.d-none .cert-ev').forEach(function (x) { x.checked = false; });
    return true;
  }
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#certEventList .cert-ev').forEach(function (x) { x.addEventListener('change', certCount); });
    certCount();
  });
</script>
