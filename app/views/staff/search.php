<?php
$pageTitle = 'Search — ' . $event['name'];
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold"><i class="bi bi-search me-2"></i>Search Competitors</h5>
    <div class="text-muted small mt-1">
      Event: <strong><?= e($event['name']) ?></strong> · Code: <code><?= e($event['event_code'] ?? '') ?></code>
    </div>
  </div>
</div>

<?php
  $searchAction = '/event-staff/search';
  $showQr       = true;
  $viewUrlFn    = fn(int $regId) => '/event-staff/search/' . hid_reg($regId);
  require APP_ROOT . '/views/partials/registration-search.php';
?>

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
</script>
