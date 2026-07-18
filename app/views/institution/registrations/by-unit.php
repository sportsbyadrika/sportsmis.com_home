<?php
$pageTitle = 'Athletes by Unit — ' . ($event['name'] ?? '');
$groups = $groups ?? [];
$bulk   = !empty($bulk);
$show   = ($show ?? 'submitted') === 'all' ? 'all' : 'submitted';
$money  = fn($v) => '₹' . number_format((float)$v, 2);
$eid    = (int)($event['id'] ?? 0);
$focusUnitId   = $focus_unit_id   ?? null;   // null = all units; 0 = direct
$focusUnitName = $focus_unit_name ?? '';
$isFocused     = $focusUnitId !== null;

// Query-string builder preserving the current focus + toggle.
$qsFor = function (string $showMode) use ($focusUnitId) {
    $parts = [];
    if ($focusUnitId !== null) $parts['unit_id'] = $focusUnitId;
    if ($showMode === 'all')   $parts['show']    = 'all';
    return $parts ? '?' . http_build_query($parts) : '';
};

// This page's own URL — posted back with each decision so the admin returns
// here (preserving the focus + submitted/all toggle) instead of the detail screen.
$backUrl = '/institution/events/' . $eid . '/athletes-by-unit' . $qsFor($show);

// Submission status → badge.
$rsBadge = [
    ''         => ['secondary',          'Draft'],
    'pending'  => ['info',               'Pending review'],
    'approved' => ['success',            'Approved'],
    'rejected' => ['danger',             'Rejected'],
    'returned' => ['warning text-dark',  'Returned'],
];

// Fund-transfer (bulk pool) badge.
$poolBadge = [
    'submitted' => ['info',    'Submitted'],
    'approved'  => ['success', 'Approved'],
    'rejected'  => ['danger',  'Rejected'],
];

// Roll-ups.
$totAthletes = 0; $totDraft = 0;
foreach ($groups as $g) {
    $totAthletes += count($g['rows']);
    $totDraft    += (int)$g['count_draft'];
}
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/registrations?event_id=<?= $eid ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Registrations
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-diagram-3 me-2"></i>Athletes by Unit</h5>
  <span class="text-muted small ms-2">
    <?= e($event['name'] ?? '') ?>
    <?php if ($isFocused && $focusUnitName !== ''): ?>
      · <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= e($focusUnitName) ?></span>
    <?php endif; ?>
  </span>
  <div class="ms-auto d-flex gap-2 flex-wrap">
    <?php if ($isFocused): ?>
      <a href="/institution/events/<?= $eid ?>/athletes-by-unit<?= $show === 'all' ? '?show=all' : '' ?>"
         class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-list-ul me-1"></i>All units
      </a>
    <?php endif; ?>
    <?php if ($bulk): ?>
      <a href="/institution/events/<?= $eid ?>/unit-payments" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-bank me-1"></i>Unit Payment Transactions
      </a>
    <?php endif; ?>
    <div class="btn-group btn-group-sm" role="group">
      <a href="/institution/events/<?= $eid ?>/athletes-by-unit<?= $qsFor('submitted') ?>"
         class="btn btn-outline-secondary <?= $show === 'submitted' ? 'active' : '' ?>">
        Submitted only
      </a>
      <a href="/institution/events/<?= $eid ?>/athletes-by-unit<?= $qsFor('all') ?>"
         class="btn btn-outline-secondary <?= $show === 'all' ? 'active' : '' ?>">
        Include drafts
        <?php if (!empty($draft_total)): ?>
          <span class="badge bg-secondary ms-1"><?= (int)$draft_total ?></span>
        <?php endif; ?>
      </a>
    </div>
  </div>
</div>

<?= flashBag() ?>

<div class="alert alert-light border small d-flex align-items-start gap-2 py-2">
  <i class="bi bi-info-circle mt-1"></i>
  <div>
    Athletes are grouped under their unit. By default only <strong>submitted</strong> registrations are shown —
    switch to <strong>Include drafts</strong> to see athletes the unit hasn't submitted yet (drafts are view-only
    until the unit submits them).
    <?php if ($bulk): ?>
      Fund-transfer transactions for each unit are shown inside the same group. Approving an athlete whose unit
      payment isn't fully covered is allowed, but flagged with a warning.
    <?php endif; ?>
  </div>
</div>

<?php if (empty($groups)): ?>
  <div class="sms-card p-4 text-muted small text-center">
    No <?= $show === 'submitted' ? 'submitted ' : '' ?>registrations found for this event
    <?php if ($show === 'submitted' && $totDraft === 0 && !empty($draft_total)): ?>
      — there are <?= (int)$draft_total ?> draft(s); use <strong>Include drafts</strong> to view them.
    <?php endif; ?>.
  </div>
<?php else: ?>

  <?php foreach ($groups as $g):
    $reconOk = (float)$g['approved'] + 0.005 >= (float)$g['demand_total'] && (float)$g['demand_total'] > 0;
  ?>
    <div class="sms-card p-3 mb-3">
      <!-- Unit header -->
      <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-2 flex-wrap">
        <strong><i class="bi bi-building me-1"></i><?= e($g['unit_name']) ?></strong>
        <span class="badge bg-light text-dark border">
          <?= (int)$g['count_submitted'] ?> submitted
          <?php if ((int)$g['count_draft'] > 0): ?>· <?= (int)$g['count_draft'] ?> draft<?php endif; ?>
        </span>
        <?php if ($bulk): ?>
          <span class="badge bg-light text-dark border">
            Demand <?= $money($g['demand_total']) ?>
            <span class="text-muted">(Indiv <?= $money($g['demand_individual']) ?> · Team <?= $money($g['demand_team']) ?>)</span>
          </span>
          <span class="badge bg-success-subtle text-success-emphasis">Approved <?= $money($g['approved']) ?></span>
          <?php if ((float)$g['submitted'] > 0): ?>
            <span class="badge bg-info-subtle text-info-emphasis">Awaiting <?= $money($g['submitted']) ?></span>
          <?php endif; ?>
          <span class="ms-auto badge <?= $reconOk ? 'bg-success' : 'bg-warning text-dark' ?>">
            <?php if ((float)$g['demand_total'] <= 0): ?>
              No demand
            <?php elseif ($reconOk): ?>
              <i class="bi bi-check-circle me-1"></i>Approved collection covers demand
            <?php else: ?>
              <i class="bi bi-exclamation-triangle me-1"></i>Short by <?= $money((float)$g['demand_total'] - (float)$g['approved']) ?>
            <?php endif; ?>
          </span>
        <?php endif; ?>
      </div>

      <!-- 1. Athletes -->
      <div class="small text-muted fw-semibold mb-1"><i class="bi bi-people me-1"></i>Athletes</div>
      <?php if (empty($g['rows'])): ?>
        <p class="text-muted small mb-3">No athletes in this group.</p>
      <?php else: ?>
        <div class="table-responsive mb-3">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Athlete</th>
                <th class="text-center">Events</th>
                <th class="text-end">Demand</th>
                <th>Payment</th>
                <th>Submission</th>
                <th class="text-end">Decision</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($g['rows'] as $r):
                $rs = (string)($r['admin_review_status'] ?? '');
                [$rcls, $rlbl] = $rsBadge[$rs] ?? ['secondary', ucfirst($rs ?: 'Draft')];
                $decidable = in_array($rs, ['pending', 'returned'], true);
                $payOk = !empty($r['payment_ok']);
                $rid   = (int)$r['id'];
              ?>
                <tr>
                  <td>
                    <div class="fw-medium"><?= e($r['athlete_name']) ?></div>
                    <div class="small text-muted">
                      <?= e(genderLabel((string)($r['gender'] ?? ''), $event)) ?>
                      <?php if (!empty($r['date_of_birth'])): ?>· <?= (int)ageFromDob($r['date_of_birth']) ?> yrs<?php endif; ?>
                      <?php if (!empty($r['mobile'])): ?>· <?= e($r['mobile']) ?><?php endif; ?>
                    </div>
                  </td>
                  <td class="text-center"><?= (int)($r['items_count'] ?? 0) ?></td>
                  <td class="text-end"><?= $money($r['total_amount'] ?? 0) ?></td>
                  <td>
                    <?php if ($payOk): ?>
                      <span class="badge bg-success"><i class="bi bi-check2 me-1"></i>Settled</span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Not settled</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge bg-<?= e($rcls) ?>"><?= e($rlbl) ?></span>
                  </td>
                  <td class="text-end text-nowrap">
                    <a href="/institution/registrations/<?= $rid ?>" class="btn btn-sm btn-outline-secondary" title="Open">
                      <i class="bi bi-eye"></i>
                    </a>
                    <?php if ($decidable): ?>
                      <form method="POST" action="/institution/registrations/<?= $rid ?>/decision" class="d-inline"
                            onsubmit="return confirmApprove(this, <?= $payOk ? '1' : '0' ?>, '<?= e(addslashes($r['athlete_name'] ?? '')) ?>');">
                        <?= csrf() ?>
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="back" value="<?= e($backUrl) ?>">
                        <button class="btn btn-sm btn-success" title="Approve"><i class="bi bi-check2"></i></button>
                      </form>
                      <button type="button" class="btn btn-sm btn-outline-warning" title="Return for edit"
                              onclick="decide(<?= $rid ?>, 'return', '<?= e(addslashes($r['athlete_name'] ?? '')) ?>')">
                        <i class="bi bi-arrow-counterclockwise"></i>
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-danger" title="Reject"
                              onclick="decide(<?= $rid ?>, 'reject', '<?= e(addslashes($r['athlete_name'] ?? '')) ?>')">
                        <i class="bi bi-x-lg"></i>
                      </button>
                    <?php else: ?>
                      <span class="small text-muted">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <!-- 2. Team Entries -->
      <?php if (!empty($g['teams'])): ?>
        <div class="small text-muted fw-semibold mb-1"><i class="bi bi-people-fill me-1"></i>Team Entries</div>
        <div class="table-responsive mb-3">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Team</th>
                <th>Event</th>
                <th class="text-center">Members</th>
                <th class="text-end">Demand</th>
                <th>Submission</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($g['teams'] as $t):
                $trs = (string)($t['admin_review_status'] ?? '');
                [$tcls, $tlbl] = $rsBadge[$trs] ?? ['secondary', ucfirst($trs ?: 'Draft')];
              ?>
                <tr>
                  <td class="fw-medium"><?= e($t['team_name'] ?? '') ?></td>
                  <td class="small text-muted">
                    <?= e($t['sport_name'] ?? '') ?>
                    <?php if (!empty($t['sport_event_name'])): ?> · <?= e($t['sport_event_name']) ?><?php endif; ?>
                    <?php if (!empty($t['event_code'])): ?> <code><?= e($t['event_code']) ?></code><?php endif; ?>
                  </td>
                  <td class="text-center"><?= (int)($t['members_count'] ?? 0) ?></td>
                  <td class="text-end"><?= $money($t['total_amount'] ?? 0) ?></td>
                  <td><span class="badge bg-<?= e($tcls) ?>"><?= e($tlbl) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <!-- 3. Fund Transfers (bulk mode) -->
      <?php if ($bulk && !empty($g['pool'])): ?>
        <div class="small text-muted fw-semibold mb-1"><i class="bi bi-cash-stack me-1"></i>Fund Transfers</div>
        <div class="table-responsive mb-1">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Date</th><th>Reference No.</th><th class="text-end">Amount</th>
                <th class="text-center">Proof</th><th>Status</th><th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($g['pool'] as $t):
                [$pcls, $plbl] = $poolBadge[$t['status']] ?? ['secondary', ucfirst((string)$t['status'])];
              ?>
                <tr>
                  <td class="small"><?= formatDate($t['transaction_date']) ?></td>
                  <td><code class="small"><?= e($t['reference_number'] ?? '') ?></code></td>
                  <td class="text-end fw-medium"><?= $money($t['amount']) ?></td>
                  <td class="text-center">
                    <?php if (!empty($t['proof_file'])): ?>
                      <a href="<?= e($t['proof_file']) ?>" target="_blank" rel="noopener" title="View proof"><i class="bi bi-paperclip"></i></a>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                  </td>
                  <td>
                    <span class="badge bg-<?= e($pcls) ?>"><?= e($plbl) ?></span>
                    <?php if (($t['status'] ?? '') === 'rejected' && !empty($t['reject_reason'])): ?>
                      <div class="small text-danger mt-1"><?= e($t['reject_reason']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="text-end text-nowrap">
                    <?php if (($t['status'] ?? '') === 'submitted'): ?>
                      <form method="POST" action="/institution/unit-payments/<?= (int)$t['id'] ?>/decision"
                            class="d-inline" onsubmit="return confirm('Approve this transaction of <?= e($money($t['amount'])) ?>?');">
                        <?= csrf() ?>
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="back" value="<?= e($backUrl) ?>">
                        <button class="btn btn-sm btn-success"><i class="bi bi-check2"></i></button>
                      </form>
                      <button type="button" class="btn btn-sm btn-outline-danger"
                              onclick="rejectPay(<?= (int)$t['id'] ?>, '<?= e(addslashes($t['reference_number'] ?? '')) ?>')">
                        <i class="bi bi-x-lg"></i>
                      </button>
                    <?php else: ?><span class="small text-muted">—</span><?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<!-- Return / Reject athlete modal -->
<div class="modal fade" id="decideModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" id="decideForm">
        <?= csrf() ?>
        <input type="hidden" name="action" id="decideAction" value="">
        <input type="hidden" name="back" value="<?= e($backUrl) ?>">
        <div class="modal-header">
          <h6 class="modal-title fw-semibold" id="decideTitle"></h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted mb-2" id="decideHint"></p>
          <label class="form-label small mb-1">Note to the unit / athlete <span id="decideReq" class="text-danger d-none">*</span></label>
          <textarea name="notes" id="decideNotes" class="form-control form-control-sm" rows="3"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn" id="decideSubmit"></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Reject fund-transfer modal -->
<div class="modal fade" id="rejectPayModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" id="rejectPayForm">
        <?= csrf() ?>
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="back" value="<?= e($backUrl) ?>">
        <div class="modal-header">
          <h6 class="modal-title fw-semibold"><i class="bi bi-x-octagon me-2"></i>Reject Transaction</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted mb-2">
            Rejecting removes this transaction from the unit's active collection — they will need to enter a fresh one.
            <span id="rejectPayRef" class="fw-medium"></span>
          </p>
          <label class="form-label small mb-1">Reason <span class="text-danger">*</span></label>
          <textarea name="reason" class="form-control form-control-sm" rows="3" required></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger"><i class="bi bi-x-lg me-1"></i>Reject</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function confirmApprove(form, payOk, name) {
  if (!payOk) {
    return confirm('Payment for ' + name + ' is NOT settled yet. Approve the registration anyway?\n\n'
      + '(Approval is independent of payment approval — you can still approve the fund transfer separately.)');
  }
  return confirm('Approve ' + name + '?');
}
let _decideModal = null;
function decide(id, action, name) {
  if (!_decideModal) _decideModal = new bootstrap.Modal(document.getElementById('decideModal'));
  const isReject = action === 'reject';
  document.getElementById('decideForm').action = '/institution/registrations/' + id + '/decision';
  document.getElementById('decideAction').value = action;
  document.getElementById('decideTitle').innerHTML = isReject
    ? '<i class="bi bi-x-octagon me-2"></i>Reject ' + name
    : '<i class="bi bi-arrow-counterclockwise me-2"></i>Return ' + name + ' for edit';
  document.getElementById('decideHint').textContent = isReject
    ? 'Rejecting is final for this submission. The athlete/unit is notified.'
    : 'Returning re-opens the registration so the unit/athlete can edit and resubmit.';
  const notes = document.getElementById('decideNotes');
  notes.value = '';
  notes.required = isReject;
  document.getElementById('decideReq').classList.toggle('d-none', !isReject);
  const btn = document.getElementById('decideSubmit');
  btn.className = 'btn ' + (isReject ? 'btn-danger' : 'btn-warning');
  btn.innerHTML = isReject ? '<i class="bi bi-x-lg me-1"></i>Reject' : '<i class="bi bi-arrow-counterclockwise me-1"></i>Return';
  _decideModal.show();
}
let _rejectPayModal = null;
function rejectPay(id, ref) {
  if (!_rejectPayModal) _rejectPayModal = new bootstrap.Modal(document.getElementById('rejectPayModal'));
  document.getElementById('rejectPayForm').action = '/institution/unit-payments/' + id + '/decision';
  document.getElementById('rejectPayRef').textContent = ref ? ('Ref: ' + ref) : '';
  _rejectPayModal.show();
}
</script>
