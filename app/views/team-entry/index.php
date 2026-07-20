<?php
$pageTitle = 'Team Entries';
$isStaffView = ($actor['type'] ?? '') === 'event_staff';
$canSubmit   = $isStaffView || eventTeamEntryWindowOpen($event);
// Bulk payment / submission mode (Unit users only). When on, the list adds
// per-team demand/balance, checkboxes, and Log-Bulk-Payment / Submit buttons.
$bulk = !empty($bulk) && !$isStaffView;
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i>Team Entries</h5>
    <div class="text-muted small mt-1">
      Event: <strong><?= e($event['name']) ?></strong> · Code: <code><?= e($event['event_code'] ?? '') ?></code>
      <?php if ($isStaffView): ?>
        · <span class="badge bg-info-subtle text-info-emphasis">Showing all team entries (Staff view)</span>
      <?php endif; ?>
    </div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($bulk): ?>
      <button type="button" id="bulkPayBtn" class="btn btn-outline-primary" disabled
              data-bs-toggle="modal" data-bs-target="#teamBulkPayModal">
        <i class="bi bi-cash-coin me-1"></i>Log Bulk Payment Transaction
        <span class="badge bg-light text-dark ms-1" id="bulkPayBtnCount">0</span>
      </button>
      <button type="button" id="bulkSubmitBtn" class="btn btn-warning" disabled onclick="submitBulkTeams()">
        <i class="bi bi-send-check me-1"></i>Submit Team Entries
        <span class="badge bg-light text-dark ms-1" id="bulkSubmitBtnCount">0</span>
      </button>
    <?php endif; ?>
    <?php if ($canSubmit): ?>
      <a href="/team-entry/new" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New Team Entry
      </a>
    <?php else: ?>
      <button class="btn btn-primary" type="button" disabled
              title="Team entry submissions are closed by the event administrator">
        <i class="bi bi-lock me-1"></i>Submissions Closed
      </button>
    <?php endif; ?>
  </div>
</div>

<?php if (!$canSubmit): ?>
  <div class="alert alert-warning py-2 small mb-3">
    <i class="bi bi-lock me-1"></i>
    Team entry submissions are <strong>closed</strong> by the event administrator.
    You can still view your existing entries and their status, but new entries
    and final submissions are paused until they re-open the window.
  </div>
<?php endif; ?>

<?php if (empty($teams)): ?>
  <div class="sms-empty-state">
    <i class="bi bi-people"></i>
    <h5>No Team Entries Yet</h5>
    <p>Start a new team entry to register a team of three approved athletes for a team-eligible event.</p>
    <?php if ($canSubmit): ?>
      <a href="/team-entry/new" class="btn btn-primary mt-2">Create Team Entry</a>
    <?php endif; ?>
  </div>
<?php else: ?>
<div class="sms-card p-3">
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <?php if ($bulk): ?>
            <th style="width:40px"><input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleAll(this)"></th>
          <?php endif; ?>
          <th>Team Name</th>
          <th>Unit</th>
          <th>Category / Event</th>
          <th>Members</th>
          <th class="text-end"><?= $bulk ? 'Demand' : 'Team Fee' ?></th>
          <?php if ($bulk): ?><th class="text-end">Balance</th><?php endif; ?>
          <?php if ($isStaffView): ?><th>Submitted By</th><?php endif; ?>
          <th>Submission</th>
          <th>Payment</th>
          <th class="text-end"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($teams as $t):
          $submitted = !empty($t['submitted_at']);
          $byType = $t['created_by_type'] ?? ($t['athlete_id'] ? 'athlete' : '');
          $byLabel = ['athlete'=>'Athlete','unit_user'=>'Unit User','event_staff'=>'Event Staff'][$byType] ?? '';
          $demand  = (float)($t['total_amount'] ?? 0);
          $claimed = (float)($t['claimed_amount'] ?? 0);
          $balance = round($demand - $claimed, 2);
          $isEditable = \Models\TeamRegistration::isEditable($t);
          $teamSizeReq = max(1, (int)($t['team_member_count'] ?? 3));
          $membersFull = (int)$t['members_count'] >= $teamSizeReq;
          $canBulkPay  = $isEditable && $balance > 0.005;
          // Fee cleared for submission: free team (demand 0), per-team balance
          // settled, or — in unit-pool bulk mode — the unit's pool covers it.
          $poolOk    = !empty($pool_covers[(int)($t['unit_id'] ?? 0)]);
          $feeClear  = ($demand <= 0.005) || (abs($balance) < 0.005) || $poolOk;
          $canBulkSub  = $isEditable && $membersFull && $feeClear;
        ?>
          <tr data-payable="<?= $canBulkPay ? '1' : '0' ?>"
              data-submittable="<?= $canBulkSub ? '1' : '0' ?>"
              data-balance="<?= e(number_format($balance, 2, '.', '')) ?>"
              data-name-label="<?= e($t['team_name']) ?>">
            <?php if ($bulk): ?>
              <td>
                <input type="checkbox" class="form-check-input row-check" value="<?= (int)$t['id'] ?>"
                       <?= $isEditable ? '' : 'disabled' ?>
                       title="<?= $isEditable ? 'Select for bulk payment / submission' : 'Locked — submitted for review' ?>"
                       onchange="updateBulkBar()">
              </td>
            <?php endif; ?>
            <td class="fw-medium"><?= e($t['team_name']) ?></td>
            <td class="small"><?= e($t['unit_name'] ?? '—') ?></td>
            <td class="small text-muted">
              <?= e($t['category_name'] ?? '—') ?>
              <?php if (!empty($t['sport_event_name'])): ?>
                <div><?= e($t['sport_event_name']) ?>
                  <?php if (!empty($t['event_code'])): ?><code class="ms-1"><?= e($t['event_code']) ?></code><?php endif; ?>
                </div>
              <?php endif; ?>
            </td>
            <?php $cap = (int)($t['team_member_count'] ?? 3) + (int)($t['reserve_count'] ?? 0); ?>
            <td><?= (int)$t['members_count'] ?> / <?= $cap > 0 ? $cap : 3 ?></td>
            <td class="text-end">
              <?= $demand > 0 ? '₹' . number_format($demand, 2) : '—' ?>
            </td>
            <?php if ($bulk): ?>
              <td class="text-end <?= $balance > 0.005 ? 'text-danger' : ($balance < -0.005 ? 'text-warning' : 'text-success') ?>">
                ₹<?= number_format($balance, 2) ?>
              </td>
            <?php endif; ?>
            <?php if ($isStaffView): ?>
              <td class="small">
                <?= e($t['submitted_by_name'] ?? $t['captain_name'] ?? '—') ?>
                <?php if ($byLabel): ?>
                  <div><span class="badge bg-secondary-subtle text-secondary"><?= e($byLabel) ?></span></div>
                <?php endif; ?>
              </td>
            <?php endif; ?>
            <td>
              <?php if (!$submitted): ?>
                <span class="badge bg-secondary">Draft</span>
              <?php else: ?>
                <?= appStatusBadge($t['admin_review_status'] ?? null, $t['submitted_at']) ?>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($bulk):
                $ps = ($pool_status ?? [])[(int)($t['unit_id'] ?? 0)]
                    ?? ['class' => 'danger', 'label' => 'No payment transaction'];
              ?>
                <span class="badge bg-<?= e($ps['class']) ?>"><?= e($ps['label']) ?></span>
              <?php else: ?>
                <?= statusBadge($t['payment_status'] ?? 'pending') ?>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <a href="/team-entry/<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-<?= $submitted && \Models\TeamRegistration::isEditable($t) === false ? 'eye' : 'pencil' ?> me-1"></i>
                <?= $submitted && \Models\TeamRegistration::isEditable($t) === false ? 'View' : 'Open' ?>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($bulk): ?>
    <p class="small text-muted mt-2 mb-0">
      <i class="bi bi-info-circle me-1"></i>
      Select team entries with the checkboxes. <strong>Log Bulk Payment</strong> applies to selected
      Draft / Returned teams with a positive balance; <strong>Submit Team Entries</strong> applies to
      selected teams with a full playing team and a settled fee. Ineligible rows are skipped automatically.
    </p>
  <?php endif; ?>
</div>

<?php if ($bulk): ?>
  <!-- Hidden form used by Submit Team Entries (bulk). -->
  <form id="teamBulkSubmitForm" method="POST" action="/team-entry/bulk-submit" class="d-none">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
  </form>

  <!-- ── Bulk Payment Transaction modal ── -->
  <div class="modal fade" id="teamBulkPayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <form method="POST" action="/team-entry/bulk-pay" enctype="multipart/form-data" onsubmit="return prepareTeamBulkPay(this);">
          <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
          <div class="modal-header">
            <h6 class="modal-title fw-semibold"><i class="bi bi-cash-coin me-2"></i>Log Bulk Payment Transaction</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p class="small text-muted">
              One bank transaction covering the selected team entries. We&rsquo;ll create one pending
              payment row per team using its outstanding balance, all sharing the same date / number / proof.
            </p>
            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label small mb-1">Date <span class="text-danger">*</span></label>
                <input type="date" name="transaction_date" class="form-control form-control-sm"
                       max="<?= date('Y-m-d') ?>" required value="<?= date('Y-m-d') ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Transaction Number <span class="text-danger">*</span></label>
                <input type="text" name="transaction_number" class="form-control form-control-sm" maxlength="100" required>
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1"># Teams</label>
                <input type="text" id="modalTxnCount" class="form-control form-control-sm bg-light" readonly>
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Total Amount (₹)</label>
                <input type="text" id="modalTxnTotal" class="form-control form-control-sm bg-light" readonly>
              </div>
              <div class="col-12">
                <label class="form-label small mb-1">Proof File <span class="text-danger">*</span></label>
                <input type="file" name="payment_proof" class="form-control form-control-sm"
                       accept="image/jpeg,image/png,image/webp,application/pdf" required>
                <small class="text-muted d-block mt-1">Same file is attached to every created payment row.</small>
              </div>
              <div class="col-12">
                <label class="form-label small mb-1">Selected Teams</label>
                <ul id="modalTeamList" class="small mb-0" style="max-height:160px;overflow:auto;border:1px solid #e2e8f0;border-radius:6px;padding:.5rem .75rem"></ul>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-save me-1"></i>Save Bulk Transaction</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
  function toggleAll(master) {
    document.querySelectorAll('.row-check').forEach(function (cb) { if (!cb.disabled) cb.checked = master.checked; });
    updateBulkBar();
  }
  function updateBulkBar() {
    var checked = Array.prototype.slice.call(document.querySelectorAll('.row-check:checked'));
    var payable = checked.filter(function (cb) { return cb.closest('tr').dataset.payable === '1'; });
    var submittable = checked.filter(function (cb) { return cb.closest('tr').dataset.submittable === '1'; });
    var total = 0;
    payable.forEach(function (cb) { total += parseFloat(cb.closest('tr').dataset.balance || '0') || 0; });

    var payBtn = document.getElementById('bulkPayBtn');
    var payCount = document.getElementById('bulkPayBtnCount');
    if (payCount) payCount.innerText = payable.length;
    if (payBtn) payBtn.disabled = payable.length === 0 || total <= 0;
    var subBtn = document.getElementById('bulkSubmitBtn');
    var subCount = document.getElementById('bulkSubmitBtnCount');
    if (subCount) subCount.innerText = submittable.length;
    if (subBtn) subBtn.disabled = submittable.length === 0;

    var c = document.getElementById('modalTxnCount');
    var a = document.getElementById('modalTxnTotal');
    if (c) c.value = payable.length;
    if (a) a.value = total.toFixed(2);
    var list = document.getElementById('modalTeamList');
    if (list) {
      list.innerHTML = payable.map(function (cb) {
        var tr = cb.closest('tr');
        var nm = tr.dataset.nameLabel || '';
        var bal = parseFloat(tr.dataset.balance || '0') || 0;
        return '<li class="d-flex justify-content-between"><span>'
          + nm.replace(/[&<>]/g, function (ch) { return {'&':'&amp;','<':'&lt;','>':'&gt;'}[ch]; })
          + '</span><span class="text-muted">₹' + bal.toFixed(2) + '</span></li>';
      }).join('') || '<li class="text-muted">No payable teams selected.</li>';
    }
  }
  function prepareTeamBulkPay(form) {
    form.querySelectorAll('input.bulkHidden').forEach(function (n) { n.remove(); });
    var payable = Array.prototype.slice.call(document.querySelectorAll('.row-check:checked'))
      .filter(function (cb) { return cb.closest('tr').dataset.payable === '1'; });
    if (!payable.length) { alert('Pick at least one payable team (Draft/Returned with a balance).'); return false; }
    payable.forEach(function (cb) {
      var i = document.createElement('input');
      i.type = 'hidden'; i.name = 'team_ids[]'; i.value = cb.value; i.className = 'bulkHidden';
      form.appendChild(i);
    });
    return true;
  }
  function submitBulkTeams() {
    var submittable = Array.prototype.slice.call(document.querySelectorAll('.row-check:checked'))
      .filter(function (cb) { return cb.closest('tr').dataset.submittable === '1'; });
    if (!submittable.length) { alert('Pick at least one team with a full squad and a settled fee.'); return; }
    if (!confirm('Submit ' + submittable.length + ' team entr' + (submittable.length === 1 ? 'y' : 'ies')
        + ' to the event administrator for review?\n\n'
        + 'Note: once submitted, you cannot edit or delete these entries unless the administrator returns or rejects them.')) return;
    var form = document.getElementById('teamBulkSubmitForm');
    form.querySelectorAll('input.bulkHidden').forEach(function (n) { n.remove(); });
    submittable.forEach(function (cb) {
      var i = document.createElement('input');
      i.type = 'hidden'; i.name = 'team_ids[]'; i.value = cb.value; i.className = 'bulkHidden';
      form.appendChild(i);
    });
    form.submit();
  }
  document.addEventListener('DOMContentLoaded', updateBulkBar);
  </script>
<?php endif; ?>
<?php endif; ?>
