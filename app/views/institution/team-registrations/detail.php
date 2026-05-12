<?php
$pageTitle = 'Team — ' . $team['team_name'];
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$fee = (float)($team['total_amount'] ?? 0);
$reviewStatus = $team['admin_review_status'] ?? null;
$alreadyDecided = in_array($reviewStatus, ['approved','rejected'], true);
?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e(hid_event((int)$event['id'])) ?>/team-registrations" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i><?= e($team['team_name']) ?></h5>
  <?= appStatusBadge($team['admin_review_status'] ?? null, $team['submitted_at'] ?? null) ?>
  <?= statusBadge($team['payment_status'] ?? 'pending') ?>
</div>

<div class="row g-4">
  <div class="col-lg-8">

    <!-- Summary -->
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-info-circle me-2"></i>Team Details</h6>
      <div class="row g-3 small">
        <div class="col-md-6">
          <div class="text-muted">Event</div>
          <div class="fw-medium"><?= e($team['event_name']) ?></div>
        </div>
        <div class="col-md-6">
          <div class="text-muted">Captain</div>
          <div class="fw-medium"><?= e($team['captain_name']) ?>
            <?php if (!empty($team['captain_mobile'])): ?>
              <span class="text-muted">— <?= e($team['captain_mobile']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-md-6">
          <div class="text-muted">Sport Event</div>
          <div class="fw-medium">
            <?= e($team['sport_event_name'] ?? '—') ?>
            <?php if (!empty($team['event_code'])): ?><code class="ms-1"><?= e($team['event_code']) ?></code><?php endif; ?>
          </div>
        </div>
        <div class="col-md-6">
          <div class="text-muted">Club / Institution</div>
          <div class="fw-medium"><?= e($team['unit_name'] ?? '—') ?></div>
        </div>
        <div class="col-md-6">
          <div class="text-muted">Team Fee</div>
          <div class="fw-bold text-success">₹<?= number_format($fee, 2) ?></div>
        </div>
        <div class="col-md-6">
          <div class="text-muted">Submitted</div>
          <div><?= !empty($team['submitted_at']) ? formatDate($team['submitted_at'], 'd M Y H:i') : '—' ?></div>
        </div>
      </div>
    </div>

    <!-- Members -->
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-people me-2"></i>Members</h6>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr><th>#</th><th>Competitor No.</th><th>Name</th><th>Mobile</th><th>Unit</th></tr>
          </thead>
          <tbody>
            <?php if (empty($members)): ?>
              <tr><td colspan="5" class="text-muted text-center py-3">No members.</td></tr>
            <?php else: foreach ($members as $i => $m): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><code><?= (int)$m['competitor_number'] ?></code></td>
                <td><?= e($m['athlete_name'] ?? '') ?></td>
                <td class="small text-muted"><?= e($m['athlete_mobile'] ?? '') ?></td>
                <td class="small"><?= e($m['unit_name'] ?? '—') ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Payments -->
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3 d-flex justify-content-between flex-wrap gap-2">
        <span><i class="bi bi-receipt me-2"></i>Payment Transactions</span>
        <small class="text-muted">
          Approved: <strong class="text-success">₹<?= number_format((float)($pay_totals['approved_amount'] ?? 0), 2) ?></strong>
          · Required: <strong>₹<?= number_format($fee, 2) ?></strong>
        </small>
      </h6>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr><th>Date</th><th>Transaction No.</th><th class="text-end">Amount</th><th>Proof</th><th>Status</th><th class="text-end">Decision</th></tr>
          </thead>
          <tbody>
            <?php if (empty($payments)): ?>
              <tr><td colspan="6" class="text-muted text-center py-3">No transactions.</td></tr>
            <?php else: foreach ($payments as $p): ?>
              <tr>
                <td class="small"><?= formatDate($p['transaction_date']) ?></td>
                <td><code class="small"><?= e($p['transaction_number']) ?></code></td>
                <td class="text-end">₹<?= number_format((float)$p['amount'], 2) ?></td>
                <td>
                  <?php if (!empty($p['proof_file'])): ?>
                    <a href="<?= e($p['proof_file']) ?>" target="_blank"><i class="bi bi-eye me-1"></i>View</a>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td><?= statusBadge($p['status']) ?></td>
                <td class="text-end">
                  <?php if ($p['status'] === 'pending'): ?>
                    <form method="post" action="/institution/team-registrations/payments/<?= (int)$p['id'] ?>/decision" class="d-inline">
                      <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                      <input type="hidden" name="action" value="approve">
                      <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i></button>
                    </form>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            data-bs-toggle="modal" data-bs-target="#rejectPay<?= (int)$p['id'] ?>">
                      <i class="bi bi-x-lg"></i>
                    </button>
                    <div class="modal fade" id="rejectPay<?= (int)$p['id'] ?>" tabindex="-1">
                      <div class="modal-dialog">
                        <form method="post" action="/institution/team-registrations/payments/<?= (int)$p['id'] ?>/decision" class="modal-content">
                          <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                          <input type="hidden" name="action" value="reject">
                          <div class="modal-header"><h5 class="modal-title">Reject Transaction</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                          <div class="modal-body">
                            <label class="form-label small">Reason</label>
                            <textarea name="reason" rows="3" class="form-control" required></textarea>
                          </div>
                          <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
                            <button class="btn btn-danger" type="submit">Reject</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  <?php elseif ($p['status'] === 'rejected' && !empty($p['rejection_reason'])): ?>
                    <small class="text-muted" title="<?= e($p['rejection_reason']) ?>">Reason: <?= e(mb_substr($p['rejection_reason'], 0, 30)) ?>…</small>
                  <?php else: ?>—<?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <div class="col-lg-4">
    <!-- Approval card -->
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-shield-check me-2"></i>Registration Decision</h6>
      <?php if ($alreadyDecided): ?>
        <div class="alert alert-<?= $reviewStatus === 'approved' ? 'success' : 'danger' ?> small mb-0">
          <strong>Already <?= e(ucfirst($reviewStatus)) ?></strong>
          <?php if (!empty($team['admin_reviewed_at'])): ?>
            <div class="text-muted">at <?= formatDate($team['admin_reviewed_at'], 'd M Y H:i') ?></div>
          <?php endif; ?>
          <?php if (!empty($team['admin_review_notes'])): ?>
            <div class="mt-2"><strong>Notes:</strong> <?= e($team['admin_review_notes']) ?></div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <form method="post" action="/institution/team-registrations/<?= (int)$team['id'] ?>/decision">
          <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
          <label class="form-label small">Notes (optional)</label>
          <textarea name="notes" rows="3" class="form-control mb-3"
                    placeholder="Visible to the captain."></textarea>
          <div class="d-grid gap-2">
            <button class="btn btn-success" name="action" value="approve" type="submit"
                    <?= empty($team['submitted_at']) ? 'disabled' : '' ?>>
              <i class="bi bi-check-circle me-1"></i>Approve Registration
            </button>
            <button class="btn btn-outline-warning" name="action" value="return" type="submit"
                    <?= empty($team['submitted_at']) ? 'disabled' : '' ?>>
              <i class="bi bi-arrow-counterclockwise me-1"></i>Return for Changes
            </button>
            <button class="btn btn-outline-danger" name="action" value="reject" type="submit"
                    <?= empty($team['submitted_at']) ? 'disabled' : '' ?>>
              <i class="bi bi-x-circle me-1"></i>Reject Registration
            </button>
          </div>
          <?php if (empty($team['submitted_at'])): ?>
            <small class="text-muted d-block mt-2">The team hasn't submitted the entry yet.</small>
          <?php endif; ?>
          <small class="text-muted d-block mt-2">
            Registration approval is independent of individual transaction approvals.
          </small>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
