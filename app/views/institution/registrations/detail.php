<?php
$pageTitle = 'Registration #' . (int)$registration['id'];
$reviewStatus = $registration['admin_review_status'] ?? null;
?>

<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
  <a href="/institution/registrations" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-check me-2"></i>Registration #<?= (int)$registration['id'] ?></h5>
  <?= statusBadge($reviewStatus ?: 'draft') ?>
  <?= statusBadge($registration['payment_status'] ?? 'pending') ?>
</div>

<?= flashBag() ?>

<div class="row g-4">
  <!-- Athlete profile + decision panel -->
  <div class="col-lg-4">
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-person-badge me-2"></i>Athlete Profile</h6>
      <div class="text-center mb-3">
        <?php if (!empty($athlete['passport_photo'])): ?>
          <img src="<?= e($athlete['passport_photo']) ?>" class="rounded-circle"
               width="96" height="96" style="object-fit:cover;border:3px solid #e2e8f0">
        <?php else: ?>
          <div class="sms-avatar sms-avatar-xl mx-auto"><?= avatarInitials($athlete['name']) ?></div>
        <?php endif; ?>
        <div class="fw-bold mt-2"><?= e($athlete['name']) ?></div>
        <div class="text-muted small">
          <?= ucfirst($athlete['gender'] ?? '') ?>
          <?php if (!empty($athlete['date_of_birth'])): ?> · <?= ageFromDob($athlete['date_of_birth']) ?> yrs<?php endif; ?>
        </div>
      </div>
      <dl class="small mb-0">
        <dt class="text-muted">Mobile</dt><dd><?= e($athlete['mobile'] ?? '') ?></dd>
        <dt class="text-muted">Email</dt><dd><?= e($registration['athlete_email'] ?? '') ?></dd>
        <dt class="text-muted">Aadhaar</dt><dd><?= e($athlete['id_proof_number'] ?? '—') ?>
          <?php if (!empty($athlete['id_proof_file'])): ?>
            <a href="<?= e($athlete['id_proof_file']) ?>" target="_blank" class="ms-1"><i class="bi bi-eye"></i></a>
          <?php endif; ?>
        </dd>
        <dt class="text-muted">DOB Proof</dt><dd><?= e($athlete['dob_proof_number'] ?? '—') ?>
          <?php if (!empty($athlete['dob_proof_file'])): ?>
            <a href="<?= e($athlete['dob_proof_file']) ?>" target="_blank" class="ms-1"><i class="bi bi-eye"></i></a>
          <?php endif; ?>
        </dd>
        <dt class="text-muted">Address</dt><dd><?= e($athlete['address'] ?? '—') ?></dd>
      </dl>
    </div>

    <!-- Application decision -->
    <?php if ($reviewStatus !== 'approved' && $reviewStatus !== 'rejected'): ?>
      <div class="sms-card p-4">
        <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-shield-check me-2"></i>Application Decision</h6>
        <form method="POST" action="/institution/registrations/<?= (int)$registration['id'] ?>/decision">
          <?= csrf() ?>
          <textarea name="notes" class="form-control form-control-sm mb-2" rows="3" placeholder="Notes for the athlete (shown if returned/rejected)"><?= e($registration['admin_review_notes'] ?? '') ?></textarea>
          <div class="d-flex gap-2">
            <button name="action" value="approve" class="btn btn-sm btn-success flex-fill"
                    onclick="return confirm('Approve this registration?')"><i class="bi bi-check-circle me-1"></i>Approve</button>
            <button name="action" value="return" class="btn btn-sm btn-warning flex-fill"
                    onclick="return confirm('Return for changes?')"><i class="bi bi-arrow-counterclockwise me-1"></i>Return</button>
            <button name="action" value="reject" class="btn btn-sm btn-danger flex-fill"
                    onclick="return confirm('Reject this registration?')"><i class="bi bi-x-circle me-1"></i>Reject</button>
          </div>
          <small class="text-muted d-block mt-2">Approve only after the payment transactions below are verified.</small>
        </form>
      </div>
    <?php else: ?>
      <div class="sms-card p-3 border-start border-4 <?= $reviewStatus==='approved' ? 'border-success' : 'border-danger' ?>">
        <strong>Status:</strong> <?= ucfirst($reviewStatus) ?>
        <?php if (!empty($registration['admin_review_notes'])): ?>
          <div class="small text-muted mt-1"><?= e($registration['admin_review_notes']) ?></div>
        <?php endif; ?>
        <?php if (!empty($registration['admin_reviewed_at'])): ?>
          <div class="small text-muted">Reviewed on <?= formatDate($registration['admin_reviewed_at'], 'd M Y H:i') ?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Registration + items + payments -->
  <div class="col-lg-8">
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-info-circle me-2"></i>Registration Details</h6>
      <dl class="row small mb-0">
        <dt class="col-sm-4 text-muted">Event</dt><dd class="col-sm-8 fw-medium"><?= e($registration['event_name']) ?></dd>
        <dt class="col-sm-4 text-muted">Unit</dt><dd class="col-sm-8"><?= e($registration['unit_name'] ?? '—') ?>
          <?php if (!empty($registration['unit_address'])): ?><div class="text-muted"><?= e($registration['unit_address']) ?></div><?php endif; ?>
        </dd>
        <dt class="col-sm-4 text-muted">NOC Letter</dt>
        <dd class="col-sm-8">
          <?php if (!empty($registration['noc_letter'])): ?>
            <a href="<?= e($registration['noc_letter']) ?>" target="_blank"><i class="bi bi-eye me-1"></i>View NOC</a>
          <?php else: ?>—<?php endif; ?>
        </dd>
        <dt class="col-sm-4 text-muted">Date of Application</dt>
        <dd class="col-sm-8"><?= formatDate($registration['registered_at'], 'd M Y H:i') ?></dd>
        <dt class="col-sm-4 text-muted">Submitted At</dt>
        <dd class="col-sm-8"><?= !empty($registration['submitted_at']) ? formatDate($registration['submitted_at'], 'd M Y H:i') : '<em>not yet submitted</em>' ?></dd>
        <dt class="col-sm-4 text-muted">Payment Mode</dt>
        <dd class="col-sm-8"><?= !empty($registration['payment_mode']) ? ucfirst($registration['payment_mode']) : '—' ?></dd>
        <dt class="col-sm-4 text-muted">Total Amount</dt>
        <dd class="col-sm-8 fw-medium">₹<?= number_format((float)($registration['total_amount'] ?? 0), 2) ?></dd>
      </dl>
    </div>

    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-trophy me-2"></i>Selected Sport Events</h6>
      <?php if (empty($items)): ?>
        <p class="text-muted small mb-0">No items.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Sport</th><th>Code</th><th>Event</th><th class="text-end">Fee</th></tr></thead>
            <tbody>
              <?php foreach ($items as $it): ?>
                <tr>
                  <td><?= e($it['sport_name'] ?? '') ?></td>
                  <td><code><?= e($it['event_code'] ?? '') ?></code></td>
                  <td><?= e($it['sport_event_name'] ?? $it['category'] ?? '') ?></td>
                  <td class="text-end">₹<?= number_format((float)$it['fee'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot><tr class="table-light"><th colspan="3" class="text-end">Total</th><th class="text-end">₹<?= number_format((float)($registration['total_amount'] ?? 0), 2) ?></th></tr></tfoot>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-receipt me-2"></i>Payment Transactions</h6>
      <?php if (empty($payments)): ?>
        <p class="text-muted small mb-0">No transactions submitted yet.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr><th>Date</th><th>Transaction No.</th><th class="text-end">Amount</th><th>Proof</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($payments as $p): ?>
                <tr>
                  <td class="small"><?= formatDate($p['transaction_date']) ?></td>
                  <td><code><?= e($p['transaction_number']) ?></code></td>
                  <td class="text-end">₹<?= number_format((float)$p['amount'], 2) ?></td>
                  <td>
                    <?php if (!empty($p['proof_file'])): ?>
                      <a href="<?= e($p['proof_file']) ?>" target="_blank"><i class="bi bi-eye me-1"></i>View</a>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                  <td>
                    <?= statusBadge($p['status']) ?>
                    <?php if ($p['status'] === 'rejected' && !empty($p['rejection_reason'])): ?>
                      <div class="small text-muted"><?= e($p['rejection_reason']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <?php if ($p['status'] === 'pending'): ?>
                      <form method="POST" action="/institution/registrations/payments/<?= (int)$p['id'] ?>/decision"
                            class="d-inline" onsubmit="return confirm('Approve this transaction?')">
                        <?= csrf() ?>
                        <input type="hidden" name="action" value="approve">
                        <button class="btn btn-sm btn-outline-success" title="Approve"><i class="bi bi-check"></i></button>
                      </form>
                      <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                              data-bs-target="#rejectPay<?= (int)$p['id'] ?>" title="Reject"><i class="bi bi-x"></i></button>
                      <div class="modal fade" id="rejectPay<?= (int)$p['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                          <form class="modal-content" method="POST"
                                action="/institution/registrations/payments/<?= (int)$p['id'] ?>/decision">
                            <?= csrf() ?>
                            <input type="hidden" name="action" value="reject">
                            <div class="modal-header"><h6 class="modal-title">Reject Transaction</h6>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">
                              <label class="form-label">Reason</label>
                              <textarea name="reason" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                              <button type="submit" class="btn btn-danger">Reject Transaction</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
