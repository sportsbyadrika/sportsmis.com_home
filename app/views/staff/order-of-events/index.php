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
// Print URL honours every active filter (day + category + age + gender).
$printParams = array_filter([
    'date'     => $filter,
    'category' => $f_category ?? '',
    'age'      => $f_age ?? '',
    'gender'   => $f_gender ?? '',
], fn($v) => $v !== '');
$printUrl = '/event-staff/order-of-events/print.pdf'
          . ($printParams ? '?' . http_build_query($printParams) : '');
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
  <a href="<?= e($printUrl) ?>" target="_blank" class="btn btn-sm btn-outline-danger ms-auto">
    <i class="bi bi-file-earmark-pdf me-1"></i>Print PDF
  </a>
</div>

<?php
  $anyFilter = ($filter !== '') || ($f_category ?? '') !== '' || ($f_age ?? '') !== '' || ($f_gender ?? '') !== '';
?>
<form method="get" action="/event-staff/order-of-events"
      class="d-flex align-items-end gap-2 mb-3 flex-wrap">
  <div>
    <label class="small text-muted mb-0 d-block"><i class="bi bi-funnel me-1"></i>Day</label>
    <select name="date" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
      <option value="">All days</option>
      <?php foreach ($dates as $d): ?>
        <option value="<?= e($d) ?>" <?= $filter === $d ? 'selected' : '' ?>><?= e($fmtDateLabel($d)) ?></option>
      <?php endforeach; ?>
      <?php if ($unscheduled): ?>
        <option value="unscheduled" <?= $filter === 'unscheduled' ? 'selected' : '' ?>>Unscheduled</option>
      <?php endif; ?>
    </select>
  </div>
  <?php if (!empty($facets['categories'])): ?>
  <div>
    <label class="small text-muted mb-0 d-block">Event Category</label>
    <select name="category" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
      <option value="">All categories</option>
      <?php foreach ($facets['categories'] as $c): ?>
        <option value="<?= e($c) ?>" <?= ($f_category ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <?php if (!empty($facets['ages'])): ?>
  <div>
    <label class="small text-muted mb-0 d-block">Age Category</label>
    <select name="age" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
      <option value="">All age categories</option>
      <?php foreach ($facets['ages'] as $a): ?>
        <option value="<?= e($a) ?>" <?= ($f_age ?? '') === $a ? 'selected' : '' ?>><?= e($a) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <?php if (!empty($facets['genders'])): ?>
  <div>
    <label class="small text-muted mb-0 d-block">Gender</label>
    <select name="gender" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
      <option value="">All genders</option>
      <?php foreach ($facets['genders'] as $g): ?>
        <option value="<?= e($g) ?>" <?= ($f_gender ?? '') === $g ? 'selected' : '' ?>><?= e(genderLabel($g, $event)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <?php if ($anyFilter): ?>
  <div>
    <a href="/event-staff/order-of-events" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-x-circle me-1"></i>Clear
    </a>
  </div>
  <?php endif; ?>
</form>

<div class="sms-card p-3">
  <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
    <span class="badge bg-primary-subtle text-primary-emphasis">
      <i class="bi bi-list-ol me-1"></i><?= count($rows) ?> event<?= count($rows) === 1 ? '' : 's' ?>
      <?= $anyFilter ? 'matched' : 'total' ?>
    </span>
  </div>
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
      <p><?= $anyFilter ? 'No events match the selected filters.' : 'This event has no sport-events configured yet. Ask the event administrator to add them under Event Configuration.' ?></p>
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
              <div class="fw-medium"><?= e($r['sport_name']) ?><?php if ($sev !== ''): ?>
                <span class="text-muted fw-normal">— <?= e($sev) ?></span>
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
