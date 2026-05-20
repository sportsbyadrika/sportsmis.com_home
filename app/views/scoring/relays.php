<?php
$pageTitle = 'Scoring — Relays';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="scToast" class="toast align-items-center border-0" role="alert">
    <div class="d-flex"><div class="toast-body fw-medium" id="scToastMsg"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div>
  </div>
</div>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <h5 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i>Scoring — Relays</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?> · <code><?= e($event['event_code']) ?></code></span>
</div>

<div class="sms-card p-3">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:60px">Order</th>
          <th>Relay</th>
          <th>Date / Time</th>
          <th class="text-center">Lanes</th>
          <th class="text-center">Lines Captured</th>
          <th>Result Status</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($relays)): ?>
          <tr><td colspan="7" class="text-muted text-center py-3">No relays configured for this event.</td></tr>
        <?php else: foreach ($relays as $r):
          $st = $r['result_status'] ?: 'pending';
          [$stLabel, $stCls] = $statuses[$st] ?? ['—','bg-secondary'];
        ?>
          <tr>
            <td class="text-center fw-bold"><?= (int)($r['order_no'] ?? 0) ?></td>
            <td class="fw-medium">Relay <?= e($r['relay_number']) ?></td>
            <td class="small text-muted">
              <?= !empty($r['relay_date']) ? formatDate($r['relay_date'], 'd M Y') : '—' ?>
              <?php if (!empty($r['match_time'])): ?> · <?= e(substr($r['match_time'],0,5)) ?><?php endif; ?>
            </td>
            <td class="text-center"><?= (int)$r['lane_count'] ?></td>
            <td class="text-center">
              <span class="badge bg-info-subtle text-info-emphasis">
                <?= (int)$r['lines_count'] ?> / <?= (int)$r['lane_count'] ?>
              </span>
            </td>
            <td><span class="badge <?= e($stCls) ?>"><?= e($stLabel) ?></span></td>
            <td class="text-end text-nowrap">
              <a href="/event-staff/scoring/relays/<?= (int)$r['id'] ?>"
                 class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Entry</a>
              <a href="/event-staff/scoring/relays/<?= (int)$r['id'] ?>?view=1"
                 class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye me-1"></i>View</a>
              <button class="btn btn-sm btn-outline-warning" type="button"
                      onclick="scStatusModal(<?= (int)$r['id'] ?>, '<?= e($st) ?>', 'Relay <?= e(addslashes($r['relay_number'])) ?>')">
                <i class="bi bi-arrow-repeat me-1"></i>Status
              </button>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Status change modal -->
<div class="modal fade" id="scStatusModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" onsubmit="return scSubmitStatus(event)">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-arrow-repeat me-2"></i>Change Result Status</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="scStatusRelayId">
        <p class="small text-muted">Changing the status to <strong>Final</strong> locks every score on this relay (read-only).</p>
        <div class="mb-2"><strong id="scStatusRelayLabel"></strong></div>
        <div class="mb-3">
          <label class="form-label small">New Result Status</label>
          <select id="scStatusValue" class="form-select form-select-sm">
            <?php foreach ($statuses as $k => $v): ?>
              <option value="<?= e($k) ?>"><?= e($v[0]) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label small">Notes (optional)</label>
          <textarea id="scStatusNotes" class="form-control form-control-sm" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Apply</button>
      </div>
    </form>
  </div>
</div>

<script>
const CSRF = '<?= e($csrfToken) ?>';
function scToast(msg, type) {
  const el = document.getElementById('scToast');
  el.className = 'toast align-items-center border-0 text-bg-' + (type || 'primary');
  document.getElementById('scToastMsg').textContent = msg;
  if (window.bootstrap && bootstrap.Toast) bootstrap.Toast.getOrCreateInstance(el, {delay:2500}).show();
}
let _scModal = null;
function scStatusModal(relayId, current, label) {
  if (!_scModal) _scModal = new bootstrap.Modal(document.getElementById('scStatusModal'));
  document.getElementById('scStatusRelayId').value = relayId;
  document.getElementById('scStatusRelayLabel').textContent = label + ' — currently ' + current;
  document.getElementById('scStatusValue').value = current;
  document.getElementById('scStatusNotes').value = '';
  _scModal.show();
}
async function scSubmitStatus(ev) {
  ev.preventDefault();
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('relay_id', document.getElementById('scStatusRelayId').value);
  fd.append('status',   document.getElementById('scStatusValue').value);
  fd.append('notes',    document.getElementById('scStatusNotes').value);
  if (!confirm('Apply the new status?\n\nFinal will lock every score on this relay.')) return false;
  const res = await fetch('/event-staff/scoring/relay-status', { method:'POST', body: fd });
  const data = await res.json();
  scToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) { _scModal.hide(); setTimeout(() => location.reload(), 600); }
  return false;
}
</script>
