<?php
$pageTitle = 'Certificates — ' . $event['name'];
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-award me-2"></i>Certificates</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <a href="/institution/events/<?= e($eventHash) ?>/certificates/settings"
     class="btn btn-sm btn-outline-secondary ms-auto">
    <i class="bi bi-gear me-1"></i>Template Settings
  </a>
</div>

<?= flashBag() ?>

<form method="POST"
      action="/institution/events/<?= e($eventHash) ?>/certificates/athlete-view-toggle"
      class="sms-card p-3 mb-3 d-flex align-items-center gap-3 flex-wrap">
  <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
  <div class="form-check form-switch mb-0">
    <input class="form-check-input" type="checkbox" role="switch"
           id="athleteViewSwitch" name="enabled" value="1"
           <?= !empty($event['cert_athlete_view_enabled']) ? 'checked' : '' ?>
           onchange="this.form.submit()">
    <label class="form-check-label fw-medium" for="athleteViewSwitch">
      View certificate in Athlete login
    </label>
  </div>
  <small class="text-muted">
    When ON, athletes see a <em>Certificate</em> button on their My Registrations page once their certificate is generated. OFF hides the button and blocks the URL even if a certificate row exists.
  </small>
  <noscript>
    <button class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Save</button>
  </noscript>
</form>

<?php if (!$configured): ?>
  <div class="alert alert-warning small">
    <i class="bi bi-exclamation-triangle me-1"></i>
    The certificate template isn't configured yet. Upload a background image and
    write the body paragraph in
    <a href="/institution/events/<?= e($eventHash) ?>/certificates/settings">Template Settings</a>
    before generating.
  </div>
<?php endif; ?>

<!-- Progress modal — drives chunked Generate and Send-by-Email runs so
     large units never hit PHP's max_execution_time. -->
<div class="modal fade" id="certProgressModal" tabindex="-1" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="certProgressTitle"><i class="bi bi-hourglass-split me-2"></i>Working…</h5>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-2" id="certProgressLead">Preparing…</p>
        <div class="progress" style="height:18px">
          <div id="certProgressBar" class="progress-bar progress-bar-striped progress-bar-animated"
               role="progressbar" style="width:0%">0%</div>
        </div>
        <div class="small text-muted mt-2" id="certProgressStats">&nbsp;</div>
        <div class="alert alert-danger small mt-3 d-none" id="certProgressError"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm d-none"
                id="certProgressCancel">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm d-none"
                id="certProgressClose" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="sms-card p-3">
  <?php if (empty($units)): ?>
    <p class="text-muted small mb-0 text-center py-3">No Units / Clubs / Institutions configured on this event.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:50px">#</th>
          <th>Unit</th>
          <th>Address</th>
          <th style="width:120px" class="text-center">Approved</th>
          <th style="width:120px" class="text-center">Issued</th>
          <th class="text-end" style="width:240px">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($units as $i => $u): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <?php if (!empty($u['logo'])): ?>
                  <img src="<?= e($u['logo']) ?>" width="32" height="32"
                       class="rounded" style="object-fit:cover">
                <?php else: ?>
                  <div class="rounded d-inline-flex align-items-center justify-content-center bg-light text-muted"
                       style="width:32px;height:32px"><i class="bi bi-building"></i></div>
                <?php endif; ?>
                <div class="fw-medium"><?= e($u['name']) ?></div>
              </div>
            </td>
            <td class="small text-muted"><?= e($u['address']) ?: '—' ?></td>
            <td class="text-center"><?= (int)$u['approved_count'] ?></td>
            <td class="text-center"><?= (int)$u['issued_count'] ?></td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-primary"
                      <?= !$configured || (int)$u['approved_count'] === 0 ? 'disabled' : '' ?>
                      onclick="certChunkRun('generate', <?= (int)$u['id'] ?>, '<?= e(addslashes($u['name'])) ?>')">
                <i class="bi bi-magic me-1"></i>Generate
              </button>
              <?php if ((int)$u['issued_count'] > 0): ?>
                <a class="btn btn-sm btn-outline-success"
                   href="/institution/events/<?= e($eventHash) ?>/certificates/units/<?= (int)$u['id'] ?>/view"
                   target="_blank" rel="noopener">
                  <i class="bi bi-eye me-1"></i>View
                </a>
                <button type="button" class="btn btn-sm btn-outline-primary"
                        title="Send each athlete their certificate as a PDF attachment"
                        onclick="certChunkRun('email', <?= (int)$u['id'] ?>, '<?= e(addslashes($u['name'])) ?>')">
                  <i class="bi bi-envelope me-1"></i>Send by Email
                </button>
                <form method="POST" class="d-inline" target="_blank"
                      action="/institution/events/<?= e($eventHash) ?>/certificates/units/<?= (int)$u['id'] ?>/reset"
                      onsubmit="return confirm('Delete the existing <?= (int)$u['issued_count'] ?> certificate(s) for <?= e($u['name']) ?> and re-issue fresh numbers? This cannot be undone.');">
                  <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                  <button class="btn btn-sm btn-outline-warning"
                          <?= !$configured ? 'disabled' : '' ?>>
                    <i class="bi bi-arrow-clockwise me-1"></i>Reset
                  </button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
(function () {
  const CSRF       = <?= json_encode($csrfToken) ?>;
  const EVENT_HASH = <?= json_encode($eventHash) ?>;
  let aborted = false;
  const modalEl   = document.getElementById('certProgressModal');
  const titleEl   = document.getElementById('certProgressTitle');
  const leadEl    = document.getElementById('certProgressLead');
  const barEl     = document.getElementById('certProgressBar');
  const statsEl   = document.getElementById('certProgressStats');
  const errEl     = document.getElementById('certProgressError');
  const cancelBtn = document.getElementById('certProgressCancel');
  const closeBtn  = document.getElementById('certProgressClose');
  cancelBtn.addEventListener('click', () => { aborted = true; cancelBtn.disabled = true; });

  function setBar(processed, total) {
    const pct = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;
    barEl.style.width = pct + '%';
    barEl.textContent = pct + '%';
    statsEl.textContent = 'Processed ' + processed + ' of ' + total;
  }
  function showError(msg) {
    errEl.textContent = msg;
    errEl.classList.remove('d-none');
    barEl.classList.remove('progress-bar-animated');
  }
  function summarise(kind, totals) {
    if (kind === 'generate') {
      const parts = [];
      if (totals.issued)   parts.push(totals.issued + ' issued');
      if (totals.existing) parts.push(totals.existing + ' already existed');
      if (totals.failed)   parts.push(totals.failed + ' failed');
      return parts.join(' · ') || 'Nothing to do.';
    }
    const parts = [];
    if (totals.sent)             parts.push(totals.sent + ' sent');
    if (totals.skipped_no_email) parts.push(totals.skipped_no_email + ' skipped (no email)');
    if (totals.failed)           parts.push(totals.failed + ' failed');
    return parts.join(' · ') || 'Nothing to do.';
  }

  window.certChunkRun = async function (kind, unitId, unitName) {
    aborted = false;
    errEl.classList.add('d-none');
    barEl.classList.add('progress-bar-animated');
    setBar(0, 0);
    titleEl.innerHTML = kind === 'generate'
      ? '<i class="bi bi-magic me-2"></i>Generating certificates'
      : '<i class="bi bi-envelope me-2"></i>Sending certificates by email';
    leadEl.textContent = kind === 'generate'
      ? 'Issuing certificate numbers and rendering PDFs for ' + unitName + '…'
      : 'Emailing each athlete in ' + unitName + ' their certificate…';
    cancelBtn.classList.remove('d-none');
    cancelBtn.disabled = false;
    closeBtn.classList.add('d-none');

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    const url = '/institution/events/' + EVENT_HASH
      + '/certificates/units/' + unitId
      + (kind === 'generate' ? '/generate-chunk' : '/email-chunk');
    const totals = kind === 'generate'
      ? { issued: 0, existing: 0, failed: 0 }
      : { sent: 0, skipped_no_email: 0, failed: 0 };

    let offset = 0, total = 0, done = false;
    while (!done) {
      if (aborted) { leadEl.textContent = 'Cancelled — partial work saved.'; break; }
      const fd = new FormData();
      fd.append('_token', CSRF);
      fd.append('offset', offset);
      fd.append('limit',  kind === 'generate' ? '5' : '3');
      let data;
      try {
        const res = await fetch(url, { method: 'POST', body: fd });
        data = await res.json();
      } catch (e) {
        showError('Network error: ' + e.message);
        break;
      }
      if (!data.success) { showError(data.message || 'Server error.'); break; }
      total  = data.total;
      offset = data.next_offset;
      for (const k of Object.keys(data.summary || {})) {
        totals[k] = (totals[k] || 0) + (data.summary[k] || 0);
      }
      setBar(offset, total);
      done = data.done;
    }

    barEl.classList.remove('progress-bar-animated');
    leadEl.innerHTML = '<strong>Done.</strong> ' + summarise(kind, totals);
    cancelBtn.classList.add('d-none');
    closeBtn.classList.remove('d-none');
    // Refresh the page in a moment so the issued / approved counters
    // pick up the new totals.
    closeBtn.addEventListener('click', () => { setTimeout(() => window.location.reload(), 50); },
                              { once: true });
  };
})();
</script>
