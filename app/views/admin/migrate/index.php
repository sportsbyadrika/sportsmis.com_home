<?php
$pageTitle = 'Event Migrate — Step 1';
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];
$state = $state ?? null;
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-left-right me-2"></i>Event Migrate</h5>
  <span class="text-muted small ms-2">Step 1 of 3 — pick source &amp; destination + the sets to copy</span>
</div>

<p class="small text-muted mb-3">
  Copy event-level master data from one event to another. Each
  selected set is validated against the destination — if the
  destination already has rows in that table, that set is skipped
  and the rest are still copied. <strong>Picking Relays auto-selects Shooting Ranges</strong>
  since relay rows reference range distances and lanes.
</p>

<form method="POST" action="/admin/event-migrate" class="sms-card p-4">
  <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

  <div class="row g-4">
    <div class="col-lg-6">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-box-arrow-up-right me-2"></i>Source</h6>
      <label class="form-label small mb-1">Source Institution</label>
      <select id="srcInst" class="form-select form-select-sm mb-3" onchange="loadEvents('src')">
        <option value="">— Select institution —</option>
        <?php foreach ($institutions as $i): ?>
          <option value="<?= (int)$i['id'] ?>"><?= e($i['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="form-label small mb-1">Source Event</label>
      <select name="source_event_id" id="srcEvent" class="form-select form-select-sm" required>
        <option value="">— Pick an institution first —</option>
      </select>
    </div>

    <div class="col-lg-6">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-box-arrow-down-right me-2"></i>Destination</h6>
      <label class="form-label small mb-1">Destination Institution</label>
      <select id="dstInst" class="form-select form-select-sm mb-3" onchange="loadEvents('dst')">
        <option value="">— Select institution —</option>
        <?php foreach ($institutions as $i): ?>
          <option value="<?= (int)$i['id'] ?>"><?= e($i['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="form-label small mb-1">Destination Event</label>
      <select name="dest_event_id" id="dstEvent" class="form-select form-select-sm" required>
        <option value="">— Pick an institution first —</option>
      </select>
    </div>
  </div>

  <hr class="my-4">

  <h6 class="fw-semibold mb-3"><i class="bi bi-collection me-2"></i>Data Sets to Copy</h6>
  <div class="row g-3">
    <?php foreach ($sets as $s): ?>
      <div class="col-md-6">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="set_<?= $s ?>" name="sets[]"
                 value="<?= $s ?>"
                 <?= ($state && in_array($s, $state['sets'], true)) ? 'checked' : '' ?>
                 onchange="onSetChange(this)">
          <label class="form-check-label" for="set_<?= $s ?>">
            <?= e($set_labels[$s]) ?>
            <?php if ($s === 'relays'): ?>
              <small class="text-muted d-block">Auto-selects <em>Shooting Range Venues</em></small>
            <?php endif; ?>
          </label>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="d-flex justify-content-end gap-2 mt-4">
    <button type="submit" class="btn btn-primary">
      Next: Pick items <i class="bi bi-arrow-right ms-1"></i>
    </button>
  </div>
</form>

<script>
async function loadEvents(which) {
  const sel  = document.getElementById(which === 'src' ? 'srcInst'  : 'dstInst').value;
  const evt  = document.getElementById(which === 'src' ? 'srcEvent' : 'dstEvent');
  evt.innerHTML = '<option value="">Loading…</option>';
  if (!sel) { evt.innerHTML = '<option value="">— Pick an institution first —</option>'; return; }
  const r = await fetch('/admin/event-migrate/events-for-institution?id=' + encodeURIComponent(sel));
  const d = await r.json();
  evt.innerHTML = '<option value="">— Select event —</option>'
    + (d.events || []).map(e => `<option value="${e.id}">${e.name}${e.event_code ? ' [' + e.event_code + ']' : ''}</option>`).join('');
}
function onSetChange(el) {
  if (el.value === 'relays' && el.checked) {
    const ranges = document.getElementById('set_ranges');
    if (ranges) ranges.checked = true;
  }
}
</script>
