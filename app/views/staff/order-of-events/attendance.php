<?php
$pageTitle = 'Unit-wise Attendance';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];
$fmtDate = function ($d) { $d = trim((string)$d); return ($d !== '' && ($t = strtotime($d))) ? date('D, d M Y', $t) : $d; };
$selDate = (string)($sel_date ?? '');
$selUnit = (int)($sel_unit ?? 0);
?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/order-of-events" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-check me-2"></i>Unit-wise Attendance</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <button type="button" class="btn btn-sm btn-outline-primary ms-auto"
          data-bs-toggle="modal" data-bs-target="#unitRosterModal">
    <i class="bi bi-file-earmark-pdf me-1"></i>Unit-wise Roster (PDF)
  </button>
</div>

<?= flashBag() ?>

<form method="get" action="/event-staff/attendance" class="sms-card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-5">
      <label class="form-label small mb-1">Competition Date</label>
      <select name="date" class="form-select form-select-sm">
        <option value="">— Select date —</option>
        <?php foreach (($dates ?? []) as $d): ?>
          <option value="<?= e($d) ?>" <?= $selDate === $d ? 'selected' : '' ?>><?= e($fmtDate($d)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-5">
      <label class="form-label small mb-1">Unit / Club</label>
      <select name="unit_id" class="form-select form-select-sm">
        <option value="0">— Select unit —</option>
        <?php foreach (($units ?? []) as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= $selUnit === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <button class="btn btn-sm btn-primary w-100"><i class="bi bi-search me-1"></i>Show</button>
    </div>
  </div>
  <p class="small text-muted mb-0 mt-2">
    <i class="bi bi-info-circle me-1"></i>Athletes scheduled on the selected date for this unit. Mark each
    <strong>Present</strong> or <strong>Absent</strong>. Absent athletes are dropped from that day&rsquo;s
    heats, heat reports and the LED wall.
  </p>
</form>

<?php if (!empty($warnings)): ?>
  <div class="alert alert-danger py-2">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Already assigned to a heat but marked ABSENT:</strong>
    <?= e(implode(', ', array_map(fn($w) => trim(($w['bib'] ? '#' . (int)$w['bib'] . ' ' : '') . $w['name']) . (!empty($w['ev_label']) ? ' (' . $w['ev_label'] . ')' : ''), $warnings))) ?>.
    Remove them from their heat(s) in <a href="/event-staff/lane-allocation">Lane Allocation</a>, otherwise they will still show there.
  </div>
<?php endif; ?>

<?php if ($selDate !== '' && $selUnit > 0): ?>
  <div class="sms-card p-3">
    <?php if (empty($athletes)): ?>
      <div class="text-muted text-center py-4">
        <i class="bi bi-people fs-3 d-block mb-2"></i>No athletes scheduled for this unit on <?= e($fmtDate($selDate)) ?>.
      </div>
    <?php else: ?>
      <form method="post" action="/event-staff/attendance/save">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="date" value="<?= e($selDate) ?>">
        <input type="hidden" name="unit_id" value="<?= $selUnit ?>">
        <div class="d-flex align-items-center mb-2 flex-wrap gap-2">
          <span class="badge bg-primary-subtle text-primary-emphasis"><?= count($athletes) ?> athlete<?= count($athletes) === 1 ? '' : 's' ?></span>
          <div class="ms-auto d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-success" onclick="crAll('present')">All Present</button>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="crAll('absent')">All Absent</button>
            <button class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Save Attendance</button>
          </div>
        </div>
        <p class="small text-muted mb-2">
          <i class="bi bi-info-circle me-1"></i>Attendance is recorded <strong>per event</strong> — an athlete
          registered for more than one event can be present for one and absent for another.
        </p>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr>
              <th style="width:44px" class="text-center">#</th>
              <th style="width:70px">BIB</th>
              <th>Name</th>
              <th>Event</th>
              <th style="width:210px" class="text-center">Attendance</th>
            </tr></thead>
            <tbody>
              <?php foreach ($athletes as $i => $a):
                $aid = (int)($a['athlete_id'] ?? 0);
                $evs = $a['event_list'] ?? [];
                if (empty($evs)) continue;
                $rc = count($evs);
              ?>
                <?php foreach ($evs as $j => $ev):
                  $esid = (int)$ev['esid']; $abs = !empty($ev['absent']);
                  $rid = $esid . '_' . $aid;
                ?>
                  <tr>
                    <?php if ($j === 0): ?>
                      <td class="text-center" rowspan="<?= $rc ?>"><?= $i + 1 ?></td>
                      <td rowspan="<?= $rc ?>"><?= $a['bib'] !== '' ? '<code>' . e($a['bib']) . '</code>' : '<span class="text-muted">—</span>' ?></td>
                      <td class="fw-medium" rowspan="<?= $rc ?>"><?= e($a['name']) ?></td>
                    <?php endif; ?>
                    <td class="small"><?= e($ev['label'] !== '' ? $ev['label'] : ('Event #' . $esid)) ?></td>
                    <td class="text-center">
                      <div class="btn-group btn-group-sm" role="group">
                        <input type="radio" class="btn-check" name="att[<?= $esid ?>][<?= $aid ?>]" id="p<?= $rid ?>" value="present" <?= $abs ? '' : 'checked' ?>>
                        <label class="btn btn-outline-success" for="p<?= $rid ?>">Present</label>
                        <input type="radio" class="btn-check" name="att[<?= $esid ?>][<?= $aid ?>]" id="x<?= $rid ?>" value="absent" <?= $abs ? 'checked' : '' ?>>
                        <label class="btn btn-outline-danger" for="x<?= $rid ?>">Absent</label>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- Unit-wise Roster (PDF): pick a date before generating (moved here) -->
<div class="modal fade" id="unitRosterModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-semibold"><i class="bi bi-file-earmark-pdf me-2"></i>Unit-wise Roster (PDF)</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-2">
          Choose a competition date. The PDF lists each unit&rsquo;s athletes scheduled that day (one unit per
          page) with BIB, name, employee number, designation, participating events and the marked
          <strong>attendance status</strong>, plus a signature line for the team manager.
        </p>
        <label class="form-label small mb-1">Competition Date</label>
        <?php if (!empty($dates)): ?>
          <select id="rosterDate" class="form-select form-select-sm">
            <?php foreach ($dates as $d): ?>
              <option value="<?= e($d) ?>" <?= $selDate === $d ? 'selected' : '' ?>><?= e($fmtDate($d)) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <input type="date" id="rosterDate" class="form-control form-control-sm" value="<?= e($selDate) ?>">
          <div class="small text-muted mt-1">No scheduled dates yet — set dates on the Order of Events rows.</div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-primary" onclick="openUnitRoster()">
          <i class="bi bi-download me-1"></i>Generate PDF
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function crAll(v) {
  document.querySelectorAll('input.btn-check[value="' + v + '"]').forEach(r => { r.checked = true; });
}
function openUnitRoster() {
  var el = document.getElementById('rosterDate');
  var d  = el ? (el.value || '').trim() : '';
  if (!d) { alert('Please pick a date first.'); return; }
  window.open('/event-staff/order-of-events/unit-roster.pdf?date=' + encodeURIComponent(d), '_blank');
}
</script>
