<?php
$pageTitle = 'Unit Dashboard';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold"><i class="bi bi-speedometer2 me-2"></i>Unit Dashboard</h5>
    <div class="text-muted small mt-1">
      Event: <strong><?= e($event['name']) ?></strong>
      · Code: <code><?= e($event['event_code'] ?? '') ?></code>
    </div>
  </div>

  <?php if (!empty($event['allow_unit_registration'])): ?>
    <a href="/unit/athletes/new" class="btn btn-sm btn-primary">
      <i class="bi bi-person-plus me-1"></i>Add Athlete
    </a>
  <?php endif; ?>

  <?php if (!empty($units) && count($units) > 1): ?>
    <form method="GET" action="/unit/dashboard" class="d-flex align-items-center gap-2">
      <label class="form-label mb-0 small text-muted">Unit:</label>
      <select name="unit_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:240px">
        <?php foreach ($units as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= $active_unit && (int)$active_unit['id'] === (int)$u['id'] ? 'selected' : '' ?>>
            <?= e($u['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  <?php endif; ?>
</div>

<?php if (empty($units)): ?>
  <div class="sms-empty-state">
    <i class="bi bi-building"></i>
    <h5>No Unit Assigned</h5>
    <p>The event administrator hasn't assigned any units to your account yet. Please contact the organiser.</p>
  </div>
<?php else: ?>

<!-- Unit details + stat tiles -->
<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="sms-card p-3 h-100">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-building me-2"></i>Unit Details</h6>
      <div class="d-flex gap-3 align-items-start">
        <div class="text-center flex-shrink-0">
          <div id="unitLogoBox">
            <?php if (!empty($active_unit['logo'])): ?>
              <img src="<?= e($active_unit['logo']) ?>?t=<?= time() ?>" id="unitLogoImg" alt="Unit Logo"
                   width="84" height="84" class="rounded" style="object-fit:cover;border:1px solid #e2e8f0;background:#fff">
            <?php else: ?>
              <div id="unitLogoImg" class="rounded d-flex align-items-center justify-content-center bg-light text-muted"
                   style="width:84px;height:84px;border:1px dashed #cbd5e1">
                <i class="bi bi-image"></i>
              </div>
            <?php endif; ?>
          </div>
          <input type="file" id="unitLogoFile" accept="image/jpeg,image/png,image/webp"
                 class="d-none" onchange="initUnitLogoCrop(this)">
          <button type="button" class="btn btn-sm btn-outline-primary mt-2"
                  onclick="document.getElementById('unitLogoFile').click()">
            <i class="bi bi-upload me-1"></i>Logo
          </button>
          <div id="unitLogoSaving" class="small text-primary mt-1 d-none">
            <span class="spinner-border spinner-border-sm"></span>
          </div>
        </div>
        <dl class="row small mb-0 flex-grow-1">
          <dt class="col-sm-4 text-muted">Code</dt>
          <dd class="col-sm-8"><code>#<?= (int)$active_unit['id'] ?></code></dd>
          <dt class="col-sm-4 text-muted">Name</dt>
          <dd class="col-sm-8 fw-medium"><?= e($active_unit['name']) ?></dd>
          <?php if (!empty($active_unit['address'])): ?>
            <dt class="col-sm-4 text-muted">Address</dt>
            <dd class="col-sm-8"><?= e($active_unit['address']) ?></dd>
          <?php endif; ?>
        </dl>
      </div>

      <!-- Notices from the event administrator -->
      <?php $msgs = $messages ?? []; if (!empty($msgs)): ?>
        <div class="border-top mt-3 pt-2">
          <div class="small fw-semibold text-muted mb-2">
            <i class="bi bi-megaphone me-1"></i>Notices from the Event Administrator
          </div>
          <div style="max-height:260px;overflow-y:auto">
            <?php foreach ($msgs as $m):
              $urgent = ($m['priority'] ?? 'normal') === 'urgent';
            ?>
              <div class="border rounded-3 p-2 mb-2 <?= $urgent ? 'border-danger bg-danger-subtle' : 'bg-light-subtle' ?>">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                  <span class="badge bg-<?= $urgent ? 'danger' : 'secondary' ?>">
                    <?= $urgent ? 'Urgent' : 'Normal' ?>
                  </span>
                  <?php if (!empty($m['due_date'])): ?>
                    <span class="small fw-semibold text-danger">
                      <i class="bi bi-calendar-event me-1"></i>Due <?= e(formatDate($m['due_date'], 'd M Y')) ?>
                    </span>
                  <?php endif; ?>
                  <span class="small text-muted ms-auto"><?= e(formatDate($m['created_at'], 'd M Y')) ?></span>
                </div>
                <div class="small" style="white-space:pre-wrap"><?= e($m['body']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="row g-3 h-100">
      <div class="col-6 col-lg-4">
        <div class="sms-card p-3 h-100">
          <div class="text-muted small text-uppercase" style="letter-spacing:.04em">Total Athletes</div>
          <div class="fs-2 fw-bold mt-1"><?= (int)$stats['total'] ?></div>
          <div class="small text-muted">
            <i class="bi bi-check2-circle me-1 text-success"></i>
            <?= (int)$stats['approved'] ?> approved
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-4">
        <div class="sms-card p-3 h-100">
          <div class="text-muted small text-uppercase" style="letter-spacing:.04em">Team Entries</div>
          <div class="fs-2 fw-bold mt-1"><?= (int)($team_count ?? 0) ?></div>
          <div class="small text-muted"><i class="bi bi-people me-1"></i>teams created by this unit</div>
        </div>
      </div>
      <div class="col-6 col-lg-4">
        <div class="sms-card p-3 h-100">
          <div class="text-muted small text-uppercase" style="letter-spacing:.04em">Total Demand</div>
          <div class="fs-2 fw-bold mt-1">₹<?= number_format((float)$stats['demand'], 2) ?></div>
          <div class="small text-muted"><i class="bi bi-cash me-1"></i>individual + team</div>
        </div>
      </div>
      <div class="col-6 col-lg-4">
        <div class="sms-card p-3 h-100">
          <div class="text-muted small text-uppercase" style="letter-spacing:.04em">Total Transaction</div>
          <div class="fs-2 fw-bold mt-1">₹<?= number_format((float)$stats['claimed'], 2) ?></div>
          <div class="small text-muted"><i class="bi bi-receipt me-1"></i>all logged (rejected excl.)</div>
        </div>
      </div>
      <div class="col-6 col-lg-4">
        <?php
          $bal = (float)$stats['demand'] - (float)$stats['claimed'];
          $settled = ($bal > -0.005 && $bal < 0.005) && ((float)$stats['demand'] > 0.005);
        ?>
        <div class="sms-card p-3 h-100 d-flex flex-column">
          <div class="text-muted small text-uppercase" style="letter-spacing:.04em">Balance</div>
          <div class="fs-2 fw-bold mt-1 <?= $bal > 0.005 ? 'text-danger' : ($bal < -0.005 ? 'text-warning' : 'text-success') ?>">
            ₹<?= number_format($bal, 2) ?>
          </div>
          <div class="small text-muted"><i class="bi bi-calculator me-1"></i>demand − transactions</div>
          <?php if ($settled && $active_unit): ?>
            <a href="/unit/receipt/<?= (int)$active_unit['id'] ?>" target="_blank" rel="noopener"
               class="btn btn-sm btn-outline-dark mt-auto pt-1" style="margin-top:.5rem !important">
              <i class="bi bi-receipt me-1"></i>Download Receipt
            </a>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-6 col-lg-4">
        <div class="sms-card p-3 h-100 d-flex flex-column">
          <div class="text-muted small text-uppercase" style="letter-spacing:.04em">Reports</div>
          <div class="small text-muted mt-1 mb-2">
            <i class="bi bi-file-earmark-text me-1"></i>Approved participants, team entries &amp; transactions —
            for the unit head&rsquo;s signature.
          </div>
          <?php if ($active_unit): ?>
            <a href="/unit/participants-report/<?= (int)$active_unit['id'] ?>" target="_blank" rel="noopener"
               class="btn btn-sm btn-primary mt-auto">
              <i class="bi bi-download me-1"></i>Registration Report
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($workflow) && ($workflow['state'] ?? '') !== 'done'):
  $lvl = $workflow['level'] ?? 'info';
  $icon = ['info'=>'bi-info-circle','warning'=>'bi-exclamation-triangle','danger'=>'bi-exclamation-octagon','success'=>'bi-check-circle'][$lvl] ?? 'bi-info-circle';
?>
  <div class="alert alert-<?= e($lvl) ?> d-flex align-items-start gap-3 mb-4">
    <i class="bi <?= e($icon) ?> fs-4 mt-1"></i>
    <div class="flex-grow-1">
      <div class="fw-semibold"><?= e($workflow['title'] ?? '') ?></div>
      <div class="small mt-1"><?= e($workflow['text'] ?? '') ?></div>
    </div>
    <?php if (($workflow['state'] ?? '') === 'submit_athletes'): ?>
      <button type="button"
              class="btn btn-sm btn-danger flex-shrink-0 align-self-center"
              data-bs-toggle="modal" data-bs-target="#submitAllModal">
        <i class="bi bi-send-check me-1"></i>Submit Registration
      </button>
    <?php elseif (!empty($workflow['action_url'])): ?>
      <a href="<?= e($workflow['action_url']) ?>"
         class="btn btn-sm btn-<?= $lvl === 'danger' ? 'danger' : ($lvl === 'warning' ? 'warning' : 'primary') ?> flex-shrink-0 align-self-center">
        <?= e($workflow['action_label'] ?? 'Go') ?>
      </a>
    <?php endif; ?>
  </div>

  <!-- Submit-all confirmation modal -->
  <?php $sc = $submittable ?? ['athletes'=>0,'teams'=>0]; ?>
  <div class="modal fade" id="submitAllModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title fw-semibold"><i class="bi bi-send-check me-2"></i>Submit for Review</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted mb-3">
            The following ready entries for <strong><?= e($active_unit['name'] ?? '') ?></strong> will be
            submitted to the event administrator. Only athletes with at least one event (and a settled fee)
            and team entries with a full squad are included.
          </p>
          <div class="row g-2 text-center">
            <div class="col-6">
              <div class="border rounded-3 p-3">
                <div class="text-muted small text-uppercase" style="font-size:.7rem">Athletes</div>
                <div class="fs-3 fw-bold text-primary"><?= (int)$sc['athletes'] ?></div>
              </div>
            </div>
            <div class="col-6">
              <div class="border rounded-3 p-3">
                <div class="text-muted small text-uppercase" style="font-size:.7rem">Team Entries</div>
                <div class="fs-3 fw-bold text-primary"><?= (int)$sc['teams'] ?></div>
              </div>
            </div>
          </div>
          <?php if ((int)$sc['athletes'] === 0 && (int)$sc['teams'] === 0): ?>
            <div class="alert alert-warning small mt-3 mb-0">
              Nothing is ready to submit yet — add events and settle the payment first.
            </div>
          <?php else: ?>
            <div class="alert alert-warning small mt-3 mb-0 d-flex align-items-start gap-2">
              <i class="bi bi-exclamation-triangle-fill mt-1"></i>
              <div>
                <strong>Please note:</strong> once submitted, these entries are locked — you
                <strong>cannot edit or delete</strong> them unless the event administrator returns or rejects them.
              </div>
            </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <form method="POST" action="/unit/submit-all" class="d-inline">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="unit_id" value="<?= (int)($active_unit['id'] ?? 0) ?>">
            <button type="submit" class="btn btn-danger" <?= ((int)$sc['athletes'] + (int)$sc['teams']) === 0 ? 'disabled' : '' ?>>
              <i class="bi bi-send-check me-1"></i>Submit
              <?= (int)$sc['athletes'] + (int)$sc['teams'] ?> entr<?= ((int)$sc['athletes'] + (int)$sc['teams']) === 1 ? 'y' : 'ies' ?>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
<?php elseif (!empty($workflow) && ($workflow['state'] ?? '') === 'done'): ?>
  <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-4">
    <i class="bi bi-check-circle"></i>
    <div class="small"><?= e($workflow['text'] ?? 'All caught up.') ?></div>
  </div>
<?php endif; ?>

<!-- Sport-Event × Gender pivot -->
<div class="sms-card p-3 mb-4">
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-grid-3x3-gap me-2"></i>Sport Events — Registration &amp; Team-Entry Counts</h6>
    <a href="/unit/registrations" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-clipboard-data me-1"></i>View Full Registrations
    </a>
  </div>

  <?php if (empty($pivot_rows)): ?>
    <p class="text-muted small mb-0 text-center py-3">
      No registrations yet for this unit. Click <a href="/unit/athletes/new">Add Athlete</a>
      to register the first one.
    </p>
  <?php else:
    // Roll-up totals for the footer.
    $tot = ['t' => 0, 'team' => 0, 'd' => 0.0];
    foreach ($pivot_rows as $r) {
      $tot['t']    += (int)$r['total_count'];
      $tot['team'] += (int)($r['team_count'] ?? 0);
      $tot['d']    += (float)$r['demand'];
    }
  ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Sport</th>
            <th>Event Code</th>
            <th>Event</th>
            <th>Age / Gender</th>
            <th class="text-center fw-semibold">Total</th>
            <th class="text-center">Team Entries</th>
            <th class="text-end">Demand</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pivot_rows as $r): ?>
            <tr>
              <td><?= e($r['sport_name'] ?? '') ?></td>
              <td><code><?= e($r['event_code'] ?? '') ?></code></td>
              <td><?= e($r['event_name'] ?? '') ?></td>
              <td class="small text-muted">
                <?= e($r['sport_event_age_category'] ?? '—') ?> ·
                <?= e(genderLabel((string)($r['sport_event_gender'] ?? ''), $event)) ?>
              </td>
              <td class="text-center fw-semibold">
                <?php if ((int)$r['total_count'] > 0): ?>
                  <a href="#" class="text-decoration-none"
                     onclick="showSeParticipants(<?= (int)$r['event_sport_id'] ?>, '<?= e(addslashes(($r['sport_name'] ?? '') . ' · ' . ($r['event_name'] ?? ''))) ?>'); return false;"
                     title="View participants"><?= (int)$r['total_count'] ?></a>
                <?php else: ?>
                  <?= (int)$r['total_count'] ?>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php $tc = (int)($r['team_count'] ?? 0); ?>
                <?php if ($tc > 0): ?>
                  <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= $tc ?></span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="text-end">₹<?= number_format((float)$r['demand'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light">
          <tr>
            <th colspan="4" class="text-end">Totals</th>
            <th class="text-center fw-semibold"><?= $tot['t'] ?></th>
            <th class="text-center"><?= $tot['team'] ?></th>
            <th class="text-end">₹<?= number_format($tot['d'], 2) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ── Sport-event participants modal (Total drill-down) ──────── -->
<div class="modal fade" id="seParticipantsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-semibold"><i class="bi bi-people me-2"></i>Participants —
          <span id="seParticipantsTitle" class="fw-normal"></span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="seParticipantsBody" class="table-responsive">
          <p class="text-muted small mb-0 text-center py-3">Loading…</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  const ACTIVE_UNIT_ID = <?= (int)($active_unit['id'] ?? 0) ?>;
  let _seModal = null;
  const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

  window.showSeParticipants = function (esId, title) {
    if (!_seModal) _seModal = new bootstrap.Modal(document.getElementById('seParticipantsModal'));
    document.getElementById('seParticipantsTitle').textContent = title || '';
    const body = document.getElementById('seParticipantsBody');
    body.innerHTML = '<p class="text-muted small mb-0 text-center py-3">Loading…</p>';
    _seModal.show();
    fetch('/unit/sport-events/' + esId + '/participants?unit_id=' + ACTIVE_UNIT_ID, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
      if (!data || !data.success) { body.innerHTML = '<p class="text-danger small mb-0">Could not load participants.</p>'; return; }
      const list = data.participants || [];
      if (!list.length) { body.innerHTML = '<p class="text-muted small mb-0">No participants found.</p>'; return; }
      let html = '<table class="table table-sm align-middle mb-0"><thead class="table-light"><tr>'
               + '<th style="width:50px">#</th><th>Name</th><th class="text-center" style="width:90px">Age</th><th style="width:120px">Gender</th></tr></thead><tbody>';
      list.forEach((p, i) => {
        html += '<tr><td>' + (i + 1) + '</td><td class="fw-medium">' + esc(p.name)
              + '</td><td class="text-center">' + (p.age == null ? '—' : esc(p.age))
              + '</td><td>' + esc(p.gender || '—') + '</td></tr>';
      });
      html += '</tbody></table>';
      body.innerHTML = html;
    })
    .catch(() => { body.innerHTML = '<p class="text-danger small mb-0">Network error while loading participants.</p>'; });
  };
})();
</script>

<!-- ── Unit Logo crop modal ──────────────────────────────────── -->
<div class="modal fade" id="unitLogoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-semibold"><i class="bi bi-crop me-2"></i>Crop Unit Logo</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-3">
        <div style="max-height:420px;overflow:hidden">
          <img id="unitLogoCropImg" src="" alt="Crop" style="max-width:100%;display:block">
        </div>
        <small class="text-muted d-block mt-2">Drag to reposition · Scroll to zoom · 1:1 square crop</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary fw-semibold" onclick="applyUnitLogoCrop()">
          <i class="bi bi-check-lg me-1"></i>Use Logo
        </button>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script>
(function () {
  const CSRF    = '<?= e($csrfToken) ?>';
  const UNIT_ID = <?= (int)($active_unit['id'] ?? 0) ?>;
  let cropper = null, modal = null;

  function getModal() {
    if (!modal) {
      if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return null;
      modal = new bootstrap.Modal(document.getElementById('unitLogoModal'));
    }
    return modal;
  }

  window.initUnitLogoCrop = function (input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    if (!/^image\/(jpeg|png|webp)$/i.test(file.type)) {
      alert('Please choose a JPG, PNG or WEBP image.');
      input.value = ''; return;
    }
    const reader = new FileReader();
    reader.onload = function (e) {
      const img = document.getElementById('unitLogoCropImg');
      const modalEl = document.getElementById('unitLogoModal');
      modalEl.addEventListener('shown.bs.modal', function build() {
        const make = () => {
          if (cropper) cropper.destroy();
          cropper = new Cropper(img, {
            aspectRatio: 1,          // square
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 0.9,
            guides: true,
            center: true,
          });
        };
        if (img.complete && img.naturalWidth > 0) make();
        else img.addEventListener('load', make, { once: true });
      }, { once: true });
      img.src = e.target.result;
      const m = getModal();
      if (m) m.show();
    };
    reader.readAsDataURL(file);
  };

  window.applyUnitLogoCrop = function () {
    if (!cropper) return;
    let canvas;
    try {
      canvas = cropper.getCroppedCanvas({
        width: 400, height: 400,
        fillColor: '#fff', imageSmoothingQuality: 'high',
      });
    } catch (e) { canvas = null; }
    if (!canvas) { alert('Could not generate the cropped image.'); return; }

    const m = getModal();
    if (m) m.hide();
    document.getElementById('unitLogoSaving').classList.remove('d-none');

    canvas.toBlob(async function (blob) {
      if (!blob) {
        document.getElementById('unitLogoSaving').classList.add('d-none');
        alert('Could not encode the cropped image.'); return;
      }
      const fd = new FormData();
      fd.append('_token', CSRF);
      fd.append('unit_id', UNIT_ID);
      fd.append('logo', blob, 'logo.jpg');
      try {
        const res  = await fetch('/unit/unit-logo', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success && data.logo_url) {
          const url = data.logo_url + '?t=' + Date.now();
          const box = document.getElementById('unitLogoBox');
          box.innerHTML = '<img src="' + url + '" id="unitLogoImg" alt="Unit Logo" width="84" height="84"'
            + ' class="rounded" style="object-fit:cover;border:1px solid #e2e8f0;background:#fff">';
        } else {
          alert(data.message || 'Logo upload failed.');
        }
      } catch (e) {
        alert('Network error while uploading the logo.');
      }
      document.getElementById('unitLogoSaving').classList.add('d-none');
      document.getElementById('unitLogoFile').value = '';
    }, 'image/jpeg', 0.9);
  };
})();
</script>
<?php endif; ?>
