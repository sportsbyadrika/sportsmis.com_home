<?php
$pageTitle = 'Unit-wise Competitor List — ' . $event['name'];
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];
$emailSentCounts = $email_sent_counts ?? [];
?>

<?= flashBag() ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-buildings me-2"></i>Unit-wise Competitor List</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <div class="ms-auto d-flex gap-2">
    <a class="btn btn-sm btn-outline-success"
       href="/institution/events/<?= e($eventHash) ?>/reports/unit-competitor-list.csv">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i>Download CSV
    </a>
    <a class="btn btn-sm btn-outline-secondary"
       href="/institution/events/<?= e($eventHash) ?>/reports/unit-competitor-list/print"
       target="_blank" rel="noopener">
      <i class="bi bi-printer me-1"></i>Print
    </a>
  </div>
</div>

<p class="small text-muted mb-3">
  <i class="bi bi-info-circle me-1"></i>
  One row per (athlete, event category). An athlete registered for multiple
  categories appears on multiple rows; the events column lists every event the
  athlete is registered for in that category (event code + label).
</p>

<?php if (!empty($units)): ?>
<div class="d-flex align-items-center justify-content-end gap-2 mb-2 flex-wrap">
  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="ucToggleAll(true)">
    <i class="bi bi-arrows-expand me-1"></i>Expand all
  </button>
  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="ucToggleAll(false)">
    <i class="bi bi-arrows-collapse me-1"></i>Collapse all
  </button>
</div>
<?php endif; ?>

<style>
  /* Accordion-style unit panels — the header row is always visible; the
     table below toggles via Bootstrap's collapse component. */
  .uc-unit { overflow: hidden; }
  .uc-unit-head {
    cursor: pointer;
    user-select: none;
    transition: background-color .15s ease;
  }
  .uc-unit-head:hover { background-color: #f8fafc; }
  .uc-chevron {
    color: #94a3b8;
    transition: transform .2s ease;
    flex-shrink: 0;
  }
  .uc-unit-head[aria-expanded="true"] .uc-chevron { transform: rotate(90deg); color: #475569; }
  /* Don't make the header look "linky". */
  .uc-unit-head, .uc-unit-head:focus, .uc-unit-head:active { text-decoration: none; color: inherit; }
</style>

<?php if (empty($units)): ?>
  <div class="sms-card p-4 text-center text-muted">No approved competitors yet.</div>
<?php else: ?>
  <?php $accIdx = 0; foreach ($units as $u): $accIdx++;
        $bodyId = 'ucUnitBody-' . $accIdx; ?>
    <div class="sms-card mb-3 uc-unit">
      <div class="d-flex align-items-stretch">
        <!-- Header row (clickable toggle) — wraps logo + name + address. -->
        <a class="uc-unit-head d-flex align-items-center gap-3 p-3 flex-grow-1 min-w-0"
           data-bs-toggle="collapse" href="#<?= e($bodyId) ?>"
           role="button" aria-expanded="false" aria-controls="<?= e($bodyId) ?>">
          <i class="bi bi-chevron-right uc-chevron fs-6"></i>
          <?php if (!empty($u['unit_logo'])): ?>
            <img src="<?= e($u['unit_logo']) ?>" alt="" width="48" height="48"
                 class="rounded flex-shrink-0" style="object-fit:cover;border:1px solid #e2e8f0;background:#fff">
          <?php else: ?>
            <div class="rounded flex-shrink-0 d-flex align-items-center justify-content-center bg-light text-muted"
                 style="width:48px;height:48px;border:1px solid #e2e8f0">
              <i class="bi bi-building"></i>
            </div>
          <?php endif; ?>
          <div class="min-w-0">
            <div class="fw-bold text-truncate"><?= e($u['unit_name']) ?></div>
            <?php if (!empty($u['unit_address'])): ?>
              <div class="small text-muted text-truncate"><?= e($u['unit_address']) ?></div>
            <?php endif; ?>
          </div>
        </a>

        <!-- Action area — sibling of the toggle, so clicks here don't expand. -->
        <div class="d-flex align-items-center gap-2 flex-wrap p-3">
          <?php $sentN = isset($u['unit_id']) ? (int)($emailSentCounts[(int)$u['unit_id']] ?? 0) : 0; ?>
          <span class="badge bg-info-subtle text-info-emphasis" title="Total emails sent to athletes in this unit">
            <i class="bi bi-envelope-check me-1"></i><?= $sentN ?> email<?= $sentN === 1 ? '' : 's' ?> sent
          </span>
          <span class="small text-muted">
            <?= count($u['rows']) ?> row<?= count($u['rows']) === 1 ? '' : 's' ?>
          </span>
          <?php if (!empty($u['unit_id'])): ?>
            <button type="button" class="btn btn-sm btn-outline-primary"
                    data-bs-toggle="modal" data-bs-target="#unitEmailModal"
                    data-unit-id="<?= (int)$u['unit_id'] ?>"
                    data-unit-name="<?= e($u['unit_name']) ?>"
                    data-unit-rows="<?= count($u['rows']) ?>">
              <i class="bi bi-envelope-paper me-1"></i>Send Email
            </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- Collapsible body — table + (in future) anything else. -->
      <div id="<?= e($bodyId) ?>" class="collapse">
        <div class="table-responsive border-top">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:50px">Sl. No</th>
                <th style="width:60px">Photo</th>
                <th style="width:100px">Comp. No.</th>
                <th>Athlete Name</th>
                <th style="width:60px">Age</th>
                <th style="width:80px">Gender</th>
                <th>Event Category</th>
                <th>Events</th>
                <th>Team Events</th>
                <th>Relay &amp; Lane</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($u['rows'] as $i => $r): ?>
                <tr>
                  <td class="text-center"><?= $i + 1 ?></td>
                  <td class="text-center">
                    <?php if (!empty($r['photo'])): ?>
                      <img src="<?= e($r['photo']) ?>" alt="" width="40" height="40"
                           class="rounded" style="object-fit:cover;border:1px solid #e2e8f0">
                    <?php else: ?>
                      <div class="rounded d-inline-flex align-items-center justify-content-center bg-light text-muted"
                           style="width:40px;height:40px;border:1px solid #e2e8f0"><i class="bi bi-person"></i></div>
                    <?php endif; ?>
                  </td>
                  <td class="text-center fw-bold">
                    <?= $r['competitor_number']
                          ? '#' . str_pad((string)(int)$r['competitor_number'], 4, '0', STR_PAD_LEFT)
                          : '—' ?>
                  </td>
                  <td><?= e($r['athlete_name']) ?></td>
                  <td class="text-center"><?= e($r['age']) ?></td>
                  <td class="text-center"><?= e($r['gender']) ?></td>
                  <td><?= e($r['category_name']) ?></td>
                  <td class="small">
                    <?= e(implode(', ', $r['events'])) ?: '<span class="text-muted">—</span>' ?>
                  </td>
                  <td class="small">
                    <?= !empty($r['team_events'])
                          ? e(implode(', ', $r['team_events']))
                          : '<span class="text-muted">—</span>' ?>
                  </td>
                  <td class="small">
                    <?php if (empty($r['relays'])): ?>
                      <span class="text-muted">—</span>
                    <?php else: ?>
                      <?php foreach ($r['relays'] as $rl): ?>
                        <div>
                          <span class="fw-medium">Relay <?= e($rl['relay_number']) ?></span>
                          <?php if (!empty($rl['relay_date'])): ?> · <?= e(formatDate($rl['relay_date'])) ?><?php endif; ?>
                          <?php if (!empty($rl['match_time'])): ?> · <?= e(substr((string)$rl['match_time'], 0, 5)) ?><?php endif; ?>
                          · <span class="badge bg-secondary-subtle text-secondary">Lane <?= e($rl['lane_number']) ?></span>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<!-- ── Shared "Send Email" modal — one form, populated per click. ─── -->
<div class="modal fade" id="unitEmailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form method="POST" id="unitEmailForm" class="modal-content"
          onsubmit="return confirm('Send this email to every approved athlete in this unit?')">
      <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
      <div class="modal-header">
        <h6 class="modal-title">
          <i class="bi bi-envelope-paper me-2"></i>Send Email to Unit
          <span class="text-muted small ms-1" id="ueUnitName"></span>
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-3">
          The email greeting (<em>Dear &lt;athlete&gt;,</em>) and the sign-off
          (<em>Thanks, &lt;institution&gt;</em>) are added automatically using
          the standard SportsMIS template. You only need to compose the
          message body below. Will go out to up to
          <strong><span id="ueRowCount">0</span></strong> approved athlete(s) in
          this unit (rows without a valid email on file are skipped).
        </p>
        <div class="mb-3">
          <label class="form-label small mb-1">Subject</label>
          <input type="text" name="subject" class="form-control form-control-sm"
                 placeholder="<?= e($event['name'] ?? '') ?> – Update">
          <small class="text-muted">Defaults to "<?= e($event['name'] ?? '') ?> – Update" if left blank.</small>
        </div>
        <div class="mb-2">
          <label class="form-label small mb-1">Message Body <span class="text-danger">*</span></label>
          <textarea name="body" rows="9" class="form-control" required
                    placeholder="Write the message body here. Line breaks are preserved."></textarea>
          <small class="text-muted">Plain text — line breaks are preserved when rendered into the email template.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="bi bi-send me-1"></i>Send to Unit
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const modal = document.getElementById('unitEmailModal');
  if (!modal) return;
  modal.addEventListener('show.bs.modal', function (ev) {
    const trigger = ev.relatedTarget;
    if (!trigger) return;
    const unitId   = trigger.getAttribute('data-unit-id')   || '';
    const unitName = trigger.getAttribute('data-unit-name') || '';
    const rows     = trigger.getAttribute('data-unit-rows') || '0';
    const form = document.getElementById('unitEmailForm');
    form.action = '/institution/events/<?= e($eventHash) ?>/reports/unit-competitor-list/units/'
                + encodeURIComponent(unitId) + '/email';
    document.getElementById('ueUnitName').textContent = unitName ? '— ' + unitName : '';
    document.getElementById('ueRowCount').textContent = rows;
    // Clear previous values so the modal starts blank on the next open.
    form.querySelector('input[name="subject"]').value = '';
    form.querySelector('textarea[name="body"]').value = '';
  });
})();

/* Expand / collapse every unit panel in one go — Bootstrap's collapse
   needs an instance per element. */
function ucToggleAll(expand) {
  document.querySelectorAll('.uc-unit .collapse').forEach(function (el) {
    const inst = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
    expand ? inst.show() : inst.hide();
  });
}
</script>
