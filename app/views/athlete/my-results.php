<?php $pageTitle = 'My Results'; ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <h5 class="mb-0 fw-bold"><i class="bi bi-trophy me-2"></i>My Results</h5>
  <span class="text-muted small ms-2">
    One row per approved registration — click <em>View Results</em> once the certificate is generated.
  </span>
</div>

<?= flashBag() ?>

<?php if (empty($registrations)): ?>
  <div class="sms-card p-4 text-center text-muted">
    No approved registrations yet — results show up here after the event admin generates your certificate.
  </div>
<?php else: ?>
  <div class="sms-card d-none d-md-block">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Event</th>
            <th>Unit</th>
            <th>Sports / Events</th>
            <th style="width:200px" class="text-center">Certificate Status</th>
            <th style="width:140px" class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($registrations as $reg):
            $hasCert = !empty($reg['has_certificate']);
            $eventLabel = $reg['event_label']  ?? '';
            $sportName  = $reg['sport_name']   ?? '';
          ?>
            <tr>
              <td>
                <div class="fw-medium"><?= e($reg['event_name']) ?></div>
                <small class="text-muted">
                  <?= !empty($reg['event_date_from']) ? e(formatDate($reg['event_date_from'])) : '' ?>
                  <?php if (!empty($reg['event_date_to']) && $reg['event_date_to'] !== $reg['event_date_from']): ?>
                    – <?= e(formatDate($reg['event_date_to'])) ?>
                  <?php endif; ?>
                </small>
              </td>
              <td><?= e($reg['unit_name'] ?? ($reg['unit_name_other'] ?? '—')) ?></td>
              <td class="small">
                <?php if ($eventLabel): ?>
                  <?= e($eventLabel) ?>
                <?php elseif ($sportName): ?>
                  <?= e($sportName) ?>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ($hasCert): ?>
                  <span class="badge bg-success-subtle text-success-emphasis">
                    <i class="bi bi-award me-1"></i>Generated
                  </span>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary-emphasis">
                    <i class="bi bi-hourglass-split me-1"></i>Not yet generated
                  </span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <?php if ($hasCert): ?>
                  <button type="button" class="btn btn-sm btn-primary"
                          data-bs-toggle="modal" data-bs-target="#resultModal"
                          data-reg-id="<?= e(hid_reg((int)$reg['id'])) ?>">
                    <i class="bi bi-eye me-1"></i>View Results
                  </button>
                <?php else: ?>
                  <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                          title="Results unlock once the event admin generates your certificate.">
                    <i class="bi bi-lock"></i><span class="ms-1">Locked</span>
                  </button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Mobile card stack -->
  <div class="d-md-none">
    <?php foreach ($registrations as $reg):
      $hasCert = !empty($reg['has_certificate']);
    ?>
      <div class="sms-card p-3 mb-2">
        <div class="fw-medium"><?= e($reg['event_name']) ?></div>
        <div class="small text-muted mb-1"><?= e($reg['unit_name'] ?? ($reg['unit_name_other'] ?? '—')) ?></div>
        <div class="small mb-2"><?= e($reg['event_label'] ?: ($reg['sport_name'] ?? '—')) ?></div>
        <div class="d-flex justify-content-between align-items-center gap-2">
          <?php if ($hasCert): ?>
            <span class="badge bg-success-subtle text-success-emphasis">
              <i class="bi bi-award me-1"></i>Certificate generated
            </span>
            <button type="button" class="btn btn-sm btn-primary"
                    data-bs-toggle="modal" data-bs-target="#resultModal"
                    data-reg-id="<?= e(hid_reg((int)$reg['id'])) ?>">
              <i class="bi bi-eye me-1"></i>View Results
            </button>
          <?php else: ?>
            <span class="badge bg-secondary-subtle text-secondary-emphasis">
              <i class="bi bi-hourglass-split me-1"></i>Not yet generated
            </span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- ── Results Modal ───────────────────────────────────────────────── -->
<div class="modal fade" id="resultModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">
          <i class="bi bi-trophy me-2"></i>Results
          <span class="text-muted small ms-1" id="resEventName"></span>
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="resLoading" class="text-center py-4 text-muted">
          <div class="spinner-border spinner-border-sm me-2"></div>Loading results…
        </div>
        <div id="resError" class="alert alert-warning d-none"></div>

        <!-- Athlete header (from certificate generated details) -->
        <div id="resHeader" class="sms-card p-3 mb-3 d-none">
          <div class="row g-2 small">
            <div class="col-md-4"><div class="text-muted">Athlete</div>
              <div class="fw-bold" id="rhName"></div>
              <div class="text-muted" id="rhCertNo"></div>
            </div>
            <div class="col-md-4"><div class="text-muted">Gender / Age / Category</div>
              <div id="rhGenderAge"></div>
            </div>
            <div class="col-md-4"><div class="text-muted">Unit</div>
              <div id="rhUnitName"></div>
              <div class="text-muted small" id="rhUnitAddress"></div>
            </div>
          </div>
        </div>

        <!-- Per-event detail -->
        <div id="resEvents"></div>
        <div id="resEmpty" class="text-muted small text-center py-3 d-none">
          No score entries recorded yet for this registration.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const modal = document.getElementById('resultModal');
  if (!modal) return;
  const esc = s => String(s ?? '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  const fmt = n => (n === null || n === undefined || n === '') ? '—' : Number(n).toFixed(2);

  function medalBadge(remarks) {
    if (!remarks) return '';
    const m = String(remarks).toLowerCase();
    let cls = 'bg-secondary-subtle text-secondary-emphasis';
    if (m === 'gold')   cls = 'bg-warning-subtle  text-warning-emphasis';
    if (m === 'silver') cls = 'bg-light text-secondary border';
    if (m === 'bronze') cls = 'bg-warning-subtle text-warning-emphasis';
    if (['dns','dnf','dq'].includes(m))
      cls = 'bg-danger-subtle text-danger-emphasis';
    return '<span class="badge ' + cls + '">' + esc(remarks) + '</span>';
  }

  function reset() {
    document.getElementById('resLoading').classList.remove('d-none');
    document.getElementById('resError').classList.add('d-none');
    document.getElementById('resHeader').classList.add('d-none');
    document.getElementById('resEmpty').classList.add('d-none');
    document.getElementById('resEvents').innerHTML = '';
    document.getElementById('resEventName').textContent = '';
  }

  function showError(msg) {
    document.getElementById('resLoading').classList.add('d-none');
    const box = document.getElementById('resError');
    box.classList.remove('d-none');
    box.textContent = msg;
  }

  function renderHeader(h) {
    document.getElementById('resEventName').textContent = h.event_name ? '— ' + h.event_name : '';
    document.getElementById('rhName').textContent       = h.athlete_name || '—';
    document.getElementById('rhCertNo').textContent     = h.certificate_no ? 'Cert #' + h.certificate_no : '';
    const bits = [];
    if (h.gender)       bits.push(h.gender);
    if (h.age != null)  bits.push(h.age + ' yrs');
    if (h.age_category) bits.push(h.age_category);
    document.getElementById('rhGenderAge').textContent = bits.length ? bits.join(' / ') : '—';
    document.getElementById('rhUnitName').textContent    = h.unit_name    || '—';
    document.getElementById('rhUnitAddress').textContent = h.unit_address || '';
    document.getElementById('resHeader').classList.remove('d-none');
  }

  function renderEvents(events) {
    const box = document.getElementById('resEvents');
    if (!events || !events.length) {
      document.getElementById('resEmpty').classList.remove('d-none');
      return;
    }
    box.innerHTML = events.map(ev => {
      const seriesHeader = (ev.series || []).map(s =>
        '<th class="text-center">S' + s.series_no + '</th>').join('');
      const seriesRow = (ev.series || []).map(s =>
        '<td class="text-center">' + fmt(s.sub_total) + '</td>').join('');
      const kindBadge = ev.kind === 'Team'
        ? '<span class="badge bg-info-subtle text-info-emphasis ms-1">Team</span>' : '';
      return `
        <div class="sms-card p-3 mb-3">
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="badge bg-secondary-subtle text-secondary-emphasis">${esc(ev.category_name) || '—'}</span>
            <strong class="me-2">${esc(ev.event_label) || '—'}</strong>
            ${kindBadge}
            <span class="ms-auto">${medalBadge(ev.remarks)}</span>
          </div>
          ${(ev.series && ev.series.length) ? `
          <div class="table-responsive">
            <table class="table table-sm table-bordered mb-2">
              <thead class="table-light text-center">
                <tr>${seriesHeader}<th>Total</th><th>Penalty</th><th>Final</th></tr>
              </thead>
              <tbody>
                <tr>
                  ${seriesRow}
                  <td class="text-center">${fmt(ev.total_score)}</td>
                  <td class="text-center">${fmt(ev.penalty)}</td>
                  <td class="text-center fw-bold">${fmt(ev.final_score)}</td>
                </tr>
              </tbody>
            </table>
          </div>` : `
          <div class="row g-2 small">
            <div class="col-sm-4"><span class="text-muted">Total:</span> ${fmt(ev.total_score)}</div>
            <div class="col-sm-4"><span class="text-muted">Penalty:</span> ${fmt(ev.penalty)}</div>
            <div class="col-sm-4"><span class="text-muted">Final:</span> <strong>${fmt(ev.final_score)}</strong></div>
          </div>`}
          ${ev.position ? '<small class="text-muted">Position: ' + ev.position + '</small>' : ''}
        </div>`;
    }).join('');
  }

  modal.addEventListener('show.bs.modal', async function (ev) {
    const trigger = ev.relatedTarget;
    if (!trigger) return;
    const regId = trigger.getAttribute('data-reg-id');
    reset();
    try {
      const res  = await fetch('/athlete/my-results/' + encodeURIComponent(regId) + '/details');
      const data = await res.json();
      document.getElementById('resLoading').classList.add('d-none');
      if (!data.success) { showError(data.message || 'Could not load results.'); return; }
      renderHeader(data.header || {});
      renderEvents(data.events || []);
    } catch (e) {
      showError('Network error while loading results.');
    }
  });
})();
</script>
