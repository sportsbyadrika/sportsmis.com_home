<?php
$pageTitle = 'Search — ' . $event['name'];
$by = in_array($by ?? '', ['competitor','name','unit','mobile'], true) ? $by : 'competitor';
$statusBadgeMap = [
  'approved' => ['Approved', 'bg-success'],
  'pending'  => ['Pending',  'bg-warning text-dark'],
  'rejected' => ['Rejected', 'bg-danger'],
  'returned' => ['Returned', 'bg-info text-dark'],
];
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold"><i class="bi bi-search me-2"></i>Search Competitors</h5>
    <div class="text-muted small mt-1">
      Event: <strong><?= e($event['name']) ?></strong> · Code: <code><?= e($event['event_code'] ?? '') ?></code>
    </div>
  </div>
</div>

<form method="GET" action="/event-staff/search" class="sms-card p-3 mb-3" id="searchForm">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small mb-1">Search By</label>
      <select name="by" id="searchBy" class="form-select form-select-sm" onchange="toggleSearchField()">
        <option value="competitor" <?= $by==='competitor' ? 'selected' : '' ?>>Competitor No.</option>
        <option value="name"       <?= $by==='name'       ? 'selected' : '' ?>>Name of Athlete</option>
        <option value="unit"       <?= $by==='unit'       ? 'selected' : '' ?>>Unit / Club / Institution</option>
        <option value="mobile"     <?= $by==='mobile'     ? 'selected' : '' ?>>Mobile Number</option>
      </select>
    </div>

    <!-- Competitor No. -->
    <div class="col-md-6 search-field" data-field="competitor">
      <label class="form-label small mb-1">Competitor No.</label>
      <div class="input-group input-group-sm">
        <input type="text" name="q" id="qCompetitor" class="form-control"
               value="<?= $by==='competitor' ? e($q) : '' ?>" placeholder="e.g. 1024"
               <?= $by==='competitor' ? '' : 'disabled' ?>>
        <button class="btn btn-outline-primary" type="button" onclick="openQrScanner()">
          <i class="bi bi-qr-code-scan me-1"></i>Scan QR
        </button>
      </div>
      <small class="text-muted">Type the number or scan the competitor card QR with your camera.</small>
    </div>

    <!-- Name -->
    <div class="col-md-6 search-field" data-field="name">
      <label class="form-label small mb-1">Name of Athlete</label>
      <input type="text" name="q" id="qName" class="form-control form-control-sm"
             value="<?= $by==='name' ? e($q) : '' ?>" placeholder="Full or partial name"
             <?= $by==='name' ? '' : 'disabled' ?>>
    </div>

    <!-- Unit -->
    <div class="col-md-6 search-field" data-field="unit">
      <label class="form-label small mb-1">Unit / Club / Institution</label>
      <select name="unit_id" id="qUnit" class="form-select form-select-sm"
              <?= $by==='unit' ? '' : 'disabled' ?>>
        <option value="0">— Select Unit —</option>
        <?php foreach ($units as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= (int)($unit_id ?? 0)===(int)$u['id'] ? 'selected' : '' ?>>
            <?= e($u['name']) ?><?= !empty($u['address']) ? ' — ' . e($u['address']) : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Mobile -->
    <div class="col-md-6 search-field" data-field="mobile">
      <label class="form-label small mb-1">Mobile Number</label>
      <input type="text" name="q" id="qMobile" class="form-control form-control-sm"
             value="<?= $by==='mobile' ? e($q) : '' ?>" placeholder="Full or partial mobile number"
             <?= $by==='mobile' ? '' : 'disabled' ?>>
    </div>

    <div class="col-md-3 d-flex gap-2">
      <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-search me-1"></i>Search</button>
      <a href="/event-staff/search" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
    </div>
  </div>
</form>

<?php if (!empty($notice)): ?>
  <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i><?= e($notice) ?></div>
<?php endif; ?>

<?php if ($searched): ?>
  <div class="sms-card p-3">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h6 class="fw-semibold mb-0"><i class="bi bi-list-ul me-2"></i>Results</h6>
      <span class="badge bg-secondary"><?= count($results) ?> found</span>
    </div>
    <?php if (empty($results)): ?>
      <p class="text-muted small mb-0 text-center py-3">No competitors match your search.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:56px">Photo</th>
            <th>Name</th>
            <th style="width:110px">Comp. No.</th>
            <th>Unit</th>
            <th style="width:100px">Status</th>
            <th style="width:90px" class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($results as $r):
            $unit = $r['unit_name'] ?: ($r['unit_name_other'] ? $r['unit_name_other'] . ' (Other)' : '—');
            $rs   = (string)($r['admin_review_status'] ?? '');
            $sb   = $statusBadgeMap[$rs] ?? ['Draft', 'bg-secondary'];
          ?>
            <tr>
              <td>
                <?php if (!empty($r['passport_photo'])): ?>
                  <img src="<?= e($r['passport_photo']) ?>" width="40" height="40"
                       class="rounded-circle" style="object-fit:cover">
                <?php else: ?>
                  <div class="sms-avatar sms-avatar-sm"><?= e(substr($r['athlete_name'] ?? '?',0,1)) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <div class="fw-medium"><?= e($r['athlete_name']) ?></div>
                <?php if (!empty($r['mobile'])): ?>
                  <small class="text-muted"><i class="bi bi-phone me-1"></i><?= e($r['mobile']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($r['competitor_number'])): ?>
                  <code class="fw-bold"><?= str_pad((string)(int)$r['competitor_number'], 4, '0', STR_PAD_LEFT) ?></code>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="small"><?= e($unit) ?></td>
              <td><span class="badge <?= e($sb[1]) ?>"><?= e($sb[0]) ?></span></td>
              <td class="text-end">
                <a href="/event-staff/search/<?= e(hid_reg((int)$r['registration_id'])) ?>"
                   class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>View</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="sms-empty-state">
    <i class="bi bi-search"></i>
    <h5>Search Competitors</h5>
    <p>Pick a field above and search by competitor number (typed or scanned), athlete name, unit, or mobile number.</p>
  </div>
<?php endif; ?>

<!-- QR scanner modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-qr-code-scan me-2"></i>Scan Competitor Card QR</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="stopQrScanner()"></button>
      </div>
      <div class="modal-body">
        <div id="qrReader" style="width:100%"></div>
        <div id="qrStatus" class="small text-muted mt-2 text-center">Point the camera at the QR code on the competitor card.</div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
function toggleSearchField() {
  const by = document.getElementById('searchBy').value;
  document.querySelectorAll('.search-field').forEach(el => {
    const on = el.dataset.field === by;
    el.style.display = on ? '' : 'none';
    // Only the visible field's inputs should submit.
    el.querySelectorAll('input, select').forEach(i => { i.disabled = !on; });
  });
}

let qrInstance = null;
function openQrScanner() {
  const modalEl = document.getElementById('qrModal');
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  modal.show();
  const status = document.getElementById('qrStatus');
  if (typeof Html5Qrcode === 'undefined') {
    status.textContent = 'QR scanner could not load. Please type the competitor number instead.';
    return;
  }
  qrInstance = new Html5Qrcode('qrReader');
  qrInstance.start(
    { facingMode: 'environment' },
    { fps: 10, qrbox: 220 },
    (decodedText) => {
      // Fill the competitor field with the scanned value and submit.
      const sel = document.getElementById('searchBy');
      sel.value = 'competitor';
      toggleSearchField();
      document.getElementById('qCompetitor').value = decodedText;
      stopQrScanner();
      bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      document.getElementById('searchForm').submit();
    },
    () => { /* per-frame decode failure — ignore */ }
  ).catch(err => {
    status.textContent = 'Could not start the camera: ' + err;
  });
}
function stopQrScanner() {
  if (qrInstance) {
    qrInstance.stop().then(() => { qrInstance.clear(); qrInstance = null; })
                     .catch(() => { qrInstance = null; });
  }
}
document.getElementById('qrModal').addEventListener('hidden.bs.modal', stopQrScanner);

toggleSearchField();
</script>
