<?php
$pageTitle = 'Team Entry';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="teToast" class="toast align-items-center border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body fw-medium" id="teToastMsg"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/athlete/my-registrations" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i>Team Entry</h5>
</div>

<!-- Athlete profile panel -->
<div class="sms-card p-3 mb-4">
  <div class="d-flex align-items-center gap-3 flex-wrap">
    <?php if (!empty($athlete['passport_photo'])): ?>
      <img src="<?= e($athlete['passport_photo']) ?>" alt="Photo"
           class="rounded-3 flex-shrink-0"
           style="width:64px;height:80px;object-fit:cover;border:1px solid #e2e8f0;background:#fff">
    <?php else: ?>
      <div class="sms-avatar sms-avatar-lg flex-shrink-0"><?= avatarInitials($athlete['name']) ?></div>
    <?php endif; ?>
    <div class="flex-grow-1 min-w-0">
      <div class="fw-bold fs-5 text-break"><?= e($athlete['name']) ?></div>
      <div class="d-flex flex-wrap gap-3 small text-muted mt-1">
        <?php if (!empty($athlete['date_of_birth'])): ?>
          <span><i class="bi bi-cake2 me-1"></i>DOB: <strong class="text-body"><?= formatDate($athlete['date_of_birth']) ?></strong></span>
        <?php endif; ?>
        <?php if (!empty($athlete['gender'])): ?>
          <span><i class="bi bi-person me-1"></i>Gender: <strong class="text-body"><?= e(ucfirst($athlete['gender'])) ?></strong></span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if (empty($eligible_events)): ?>
  <div class="sms-empty-state">
    <i class="bi bi-people"></i>
    <h5>No Eligible Events</h5>
    <p>Team Entry is available only on events where:</p>
    <ul class="text-start small d-inline-block">
      <li>your athlete registration is <strong>approved</strong>, and</li>
      <li>the organiser has <strong>enabled Team Entry</strong> in Registration Settings.</li>
    </ul>
    <a href="/athlete/my-registrations" class="btn btn-primary mt-3">Back to My Registrations</a>
  </div>
<?php else: ?>

<div class="row g-4">
  <div class="col-lg-8">
    <!-- Step 1 — Select Event + Team Name -->
    <div class="sms-card p-4 mb-4">
      <div class="sms-step-head bg-primary-subtle text-primary-emphasis rounded-3 px-3 py-2 d-flex align-items-center mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-1-circle me-2"></i>Step 1 — Select Event</h6>
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-medium">Event <span class="text-danger">*</span></label>
          <select id="te_event" class="form-select" onchange="onEventChange()">
            <option value="">— Select Event —</option>
            <?php foreach ($eligible_events as $ev):
              $evWindowOpen = !array_key_exists('team_entry_window_open', $ev)
                || (int)$ev['team_entry_window_open'] !== 0;
            ?>
              <option value="<?= (int)$ev['id'] ?>"
                      data-unit-id="<?= (int)($ev['my_unit_id'] ?? 0) ?>"
                      data-unit-name="<?= e($ev['my_unit_name'] ?? '') ?>"
                      data-comp="<?= (int)($ev['competitor_number'] ?? 0) ?>"
                      data-window-open="<?= $evWindowOpen ? '1' : '0' ?>">
                <?= e($ev['name']) ?>
                <?= !empty($ev['institution_name']) ? ' — ' . e($ev['institution_name']) : '' ?>
                <?= $evWindowOpen ? '' : '  (Submissions Closed)' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted d-block mt-1">Only events you have approved registration in and where Team Entry is enabled.</small>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-medium">Sport Event <span class="text-danger">*</span></label>
          <select id="te_sport_event" class="form-select" disabled>
            <option value="">— Pick an event first —</option>
          </select>
          <small class="text-muted d-block mt-1">Showing only sport events with a Team Entry Fee configured.</small>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-medium">Team Name <span class="text-danger">*</span></label>
          <input type="text" id="te_team_name" class="form-control" maxlength="255"
                 placeholder="e.g. Eagles Senior Squad">
        </div>

        <div class="col-md-6">
          <label class="form-label fw-medium">Club / Institution</label>
          <div class="form-control bg-light" id="te_unit_label" style="min-height:38px">
            <span class="text-muted">Select an event above…</span>
          </div>
          <small class="text-muted d-block mt-1">This is your unit on the selected event — your team will be registered under it and members must share it.</small>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-medium">Team Entry Fee</label>
          <div class="form-control bg-light fw-bold text-success" id="te_fee_label" style="min-height:38px">
            ₹0.00
          </div>
        </div>
      </div>

      <div id="teWindowClosed" class="alert alert-warning small mt-3 mb-0 d-none">
        <i class="bi bi-lock me-1"></i>
        Team entry submissions are <strong>closed</strong> for the selected event.
        You cannot create a new entry until the event administrator re-opens the window.
      </div>

      <div class="d-flex justify-content-end mt-3">
        <button type="button" class="btn btn-primary fw-semibold" id="teCreateBtn"
                onclick="createTeam()" disabled>
          <i class="bi bi-arrow-right-circle me-1"></i>Create Team & Continue
        </button>
      </div>
    </div>

    <?php if (!empty($team_registrations)): ?>
    <div class="sms-card p-3 mb-4">
      <h6 class="fw-semibold mb-2"><i class="bi bi-clock-history me-2"></i>Existing Team Entries</h6>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Event</th><th>Team</th><th>Sport Event</th><th>Members</th>
              <th>Application</th><th>Payment</th><th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($team_registrations as $tr): ?>
            <tr>
              <td><?= e($tr['event_name']) ?></td>
              <td class="fw-medium"><?= e($tr['team_name']) ?></td>
              <td class="small text-muted"><?= e($tr['sport_event_name'] ?? '') ?></td>
              <td><?= (int)$tr['members_count'] ?> / 3</td>
              <td><?= appStatusBadge($tr['admin_review_status'] ?? null, $tr['submitted_at'] ?? null) ?></td>
              <td><?= statusBadge($tr['payment_status'] ?? 'pending') ?></td>
              <td class="text-end">
                <a href="/athlete/team-entry/<?= (int)$tr['id'] ?>" class="btn btn-sm btn-outline-secondary">Open</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
const CSRF = '<?= e($csrfToken) ?>';

function showToast(msg, type) {
  const el = document.getElementById('teToast');
  el.className = 'toast align-items-center border-0 text-bg-' + (type || 'primary');
  document.getElementById('teToastMsg').textContent = msg;
  bootstrap.Toast.getOrCreateInstance(el).show();
}

function updateCreateBtn() {
  const evSel = document.getElementById('te_event');
  const ev = evSel.value;
  const se = document.getElementById('te_sport_event').value;
  const tn = document.getElementById('te_team_name').value.trim();
  const opt = evSel.selectedOptions[0];
  const windowOpen = !opt || opt.dataset.windowOpen !== '0';
  document.getElementById('teWindowClosed').classList.toggle('d-none', !ev || windowOpen);
  document.getElementById('teCreateBtn').disabled = !(ev && se && tn && windowOpen);
}

document.getElementById('te_team_name').addEventListener('input', updateCreateBtn);
document.getElementById('te_sport_event').addEventListener('change', () => {
  const opt = document.getElementById('te_sport_event').selectedOptions[0];
  const fee = opt?.dataset.fee || '0';
  document.getElementById('te_fee_label').textContent = '₹' + Number(fee).toFixed(2);
  updateCreateBtn();
});

async function onEventChange() {
  const sel = document.getElementById('te_event');
  const opt = sel.selectedOptions[0];
  const unitLabel = document.getElementById('te_unit_label');
  const se = document.getElementById('te_sport_event');
  document.getElementById('te_fee_label').textContent = '₹0.00';
  se.innerHTML = '<option value="">— Pick an event first —</option>';
  se.disabled = true;
  if (!sel.value) {
    unitLabel.innerHTML = '<span class="text-muted">Select an event above…</span>';
    updateCreateBtn();
    return;
  }
  const unitName = opt.dataset.unitName || '—';
  const comp     = opt.dataset.comp || '';
  unitLabel.innerHTML = '<i class="bi bi-building me-1"></i>' + unitName
    + (comp ? '<span class="badge bg-secondary-subtle text-secondary ms-2">Competitor #' + comp + '</span>' : '');

  const res = await fetch('/athlete/team-entry/sport-events?event_id=' + sel.value);
  const data = await res.json();
  se.innerHTML = '<option value="">— Select Sport Event —</option>';
  if (data.success && (data.sport_events || []).length) {
    data.sport_events.forEach(s => {
      const label = (s.event_code ? '[' + s.event_code + '] ' : '')
                  + s.sport_name + ' · ' + (s.sport_event_name || s.sport_event_category || '')
                  + (s.sport_event_gender ? ' (' + s.sport_event_gender + ')' : '');
      const opt = document.createElement('option');
      opt.value = s.id;
      opt.dataset.fee = s.team_entry_fee || 0;
      opt.textContent = label + ' — ₹' + Number(s.team_entry_fee || 0).toFixed(2);
      se.appendChild(opt);
    });
    se.disabled = false;
  } else {
    se.innerHTML = '<option value="">No team-eligible sport events</option>';
  }
  updateCreateBtn();
}

async function createTeam() {
  const btn = document.getElementById('teCreateBtn');
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
  const fd = new FormData();
  fd.append('_token',          CSRF);
  fd.append('event_id',        document.getElementById('te_event').value);
  fd.append('event_sport_id',  document.getElementById('te_sport_event').value);
  fd.append('team_name',       document.getElementById('te_team_name').value.trim());
  const res = await fetch('/athlete/team-entry/create', { method:'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    window.location.href = data.redirect;
    return;
  }
  showToast(data.message || 'Could not create team.', 'danger');
  if (data.redirect) {
    setTimeout(() => { window.location.href = data.redirect; }, 1500);
  }
  btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-right-circle me-1"></i>Create Team &amp; Continue';
}
</script>
<?php endif; ?>
