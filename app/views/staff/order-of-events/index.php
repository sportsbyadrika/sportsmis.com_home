<?php
$pageTitle = 'Order of Events';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$fmtDateLabel = function ($d) {
    $d = trim((string)$d);
    return ($d !== '' && ($ts = strtotime($d))) ? date('D, d M Y', $ts) : $d;
};
// Print URL honours the current date filter.
$printUrl = '/event-staff/order-of-events/print.pdf'
          . ($filter !== '' ? '?date=' . urlencode($filter) : '');
?>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="ooeToast" class="toast align-items-center border-0" role="alert">
    <div class="d-flex"><div class="toast-body fw-medium" id="ooeToastMsg"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div>
  </div>
</div>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <h5 class="mb-0 fw-bold"><i class="bi bi-list-ol me-2"></i>Order of Events</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?> · <code><?= e($event['event_code']) ?></code></span>
  <div class="ms-auto d-flex gap-2 flex-wrap align-items-center">
    <form method="get" action="/event-staff/order-of-events" class="d-flex align-items-center gap-1">
      <label class="small text-muted mb-0 me-1"><i class="bi bi-funnel me-1"></i>Day</label>
      <select name="date" class="form-select form-select-sm" style="width:auto"
              onchange="this.form.submit()">
        <option value="">All days</option>
        <?php foreach ($dates as $d): ?>
          <option value="<?= e($d) ?>" <?= $filter === $d ? 'selected' : '' ?>><?= e($fmtDateLabel($d)) ?></option>
        <?php endforeach; ?>
        <?php if ($unscheduled): ?>
          <option value="unscheduled" <?= $filter === 'unscheduled' ? 'selected' : '' ?>>Unscheduled</option>
        <?php endif; ?>
      </select>
    </form>
    <a href="<?= e($printUrl) ?>" target="_blank" class="btn btn-sm btn-outline-danger">
      <i class="bi bi-file-earmark-pdf me-1"></i>Print PDF
    </a>
  </div>
</div>

<div class="sms-card p-3">
  <p class="small text-muted mb-3">
    Edit the <strong>date</strong>, <strong>time</strong>, <strong>serial number</strong> or
    <strong>Call Status</strong> for any event — every change <strong>saves automatically</strong>.
    The list is sorted by date, time and serial number; change the <em>Day</em> filter (or refresh)
    to re-sort after edits.
  </p>

  <?php if (empty($rows)): ?>
    <div class="sms-empty-state">
      <i class="bi bi-calendar-x"></i>
      <h5>No events to schedule</h5>
      <p><?= $filter !== '' ? 'No events match the selected day.' : 'This event has no sport-events configured yet. Ask the event administrator to add them under Event Configuration.' ?></p>
    </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:70px" class="text-center" title="Serial number in the programme">Sl. No</th>
          <th style="width:150px">Date</th>
          <th style="width:120px">Time</th>
          <th>Event</th>
          <th style="width:180px">Call Status</th>
          <th style="width:90px" class="text-center">Saved</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r):
          $gender = genderLabel($r['sport_event_gender'] ?? '', $event);
          $cat    = trim((string)($r['sport_event_category'] ?? ''));
          $sev    = trim((string)($r['sport_event_name'] ?? ''));
          $age    = trim((string)($r['sport_event_age_category'] ?? ''));
          $code   = trim((string)($r['event_code'] ?? ''));
          $meta   = trim(implode(' · ', array_filter([$age, $gender])));
          $status = (string)($r['order_call_status'] ?? 'scheduled');
          $time   = $r['order_time'] ? substr((string)$r['order_time'], 0, 5) : '';
        ?>
          <tr data-row-id="<?= (int)$r['id'] ?>">
            <td>
              <input type="number" min="1" step="1" class="form-control form-control-sm text-center ooe-sl"
                     value="<?= $r['order_sl_no'] !== null ? (int)$r['order_sl_no'] : '' ?>" placeholder="—"
                     onchange="ooeAutoSave(this)">
            </td>
            <td>
              <input type="date" class="form-control form-control-sm ooe-date"
                     value="<?= e((string)($r['order_date'] ?? '')) ?>" onchange="ooeAutoSave(this)">
            </td>
            <td>
              <input type="time" class="form-control form-control-sm ooe-time"
                     value="<?= e($time) ?>" onchange="ooeAutoSave(this)">
            </td>
            <td>
              <div class="fw-medium"><?= e($r['sport_name']) ?><?php if ($cat !== '' || $sev !== ''): ?>
                <span class="text-muted fw-normal">— <?= e(trim($cat . ($sev !== '' ? ' · ' . $sev : ''))) ?></span>
              <?php endif; ?></div>
              <div class="small text-muted">
                <?= $meta !== '' ? e($meta) : '' ?>
                <?php if ($code !== ''): ?><span class="font-monospace ms-1"><?= e($code) ?></span><?php endif; ?>
              </div>
            </td>
            <td>
              <select class="form-select form-select-sm ooe-status" onchange="ooeStatus(this)"
                      data-current="<?= e($status) ?>">
                <?php foreach ($statuses as $key => $label): ?>
                  <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td class="text-center">
              <span class="ooe-saved small text-muted">—</span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
const CSRF = '<?= e($csrfToken) ?>';
function ooeToast(msg, type) {
  const el = document.getElementById('ooeToast');
  el.className = 'toast align-items-center border-0 text-bg-' + (type || 'primary');
  document.getElementById('ooeToastMsg').textContent = msg;
  if (window.bootstrap && bootstrap.Toast) bootstrap.Toast.getOrCreateInstance(el, {delay:2200}).show();
}

// Per-row save-state indicator: 'saving' | 'saved' | 'error'.
function ooeMark(tr, state, msg) {
  const el = tr.querySelector('.ooe-saved');
  if (!el) return;
  if (state === 'saving') {
    el.className = 'ooe-saved small text-muted';
    el.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:.8rem;height:.8rem"></span>';
    el.title = 'Saving…';
  } else if (state === 'saved') {
    el.className = 'ooe-saved small text-success';
    el.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
    el.title = 'Saved';
  } else {
    el.className = 'ooe-saved small text-danger';
    el.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i>';
    el.title = msg || 'Save failed';
  }
}

// Auto-save the schedule fields (serial no / date / time) whenever one changes.
async function ooeAutoSave(input) {
  const tr   = input.closest('tr');
  const sl   = (tr.querySelector('.ooe-sl').value   || '').trim();
  const date = (tr.querySelector('.ooe-date').value || '').trim();
  const time = (tr.querySelector('.ooe-time').value || '').trim();
  ooeMark(tr, 'saving');
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('row_id', tr.dataset.rowId);
  fd.append('sl_no',  sl);
  fd.append('date',   date);
  fd.append('time',   time);
  try {
    const res  = await fetch('/event-staff/order-of-events/save', { method:'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      ooeMark(tr, 'saved');
    } else {
      ooeMark(tr, 'error', data.message);
      ooeToast(data.message || 'Save failed.', 'danger');
    }
  } catch (e) {
    ooeMark(tr, 'error');
    ooeToast('Save failed — please retry.', 'danger');
  }
}

// Auto-save the call-room status when the dropdown changes.
async function ooeStatus(sel) {
  const tr = sel.closest('tr');
  const prev = sel.dataset.current || '';
  ooeMark(tr, 'saving');
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('row_id', tr.dataset.rowId);
  fd.append('status', sel.value);
  sel.disabled = true;
  try {
    const res  = await fetch('/event-staff/order-of-events/status', { method:'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      sel.dataset.current = sel.value;
      ooeMark(tr, 'saved');
    } else {
      sel.value = prev;
      ooeMark(tr, 'error', data.message);
      ooeToast(data.message || 'Could not change status.', 'danger');
    }
  } catch (e) {
    sel.value = prev;
    ooeMark(tr, 'error');
    ooeToast('Could not change status — please retry.', 'danger');
  }
  sel.disabled = false;
}
</script>
