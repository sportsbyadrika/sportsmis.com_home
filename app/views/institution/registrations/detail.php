<?php
$pageTitle = 'Registration #' . (int)$registration['id'];
$reviewStatus = $registration['admin_review_status'] ?? null;
?>

<?php $backHref = '/institution/registrations' . (!empty($list_qs) ? '?' . $list_qs : ''); ?>
<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
  <a href="<?= e($backHref) ?>" class="btn btn-sm btn-outline-secondary"
     title="Back to the filtered list"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-check me-2"></i>Registration #<?= (int)$registration['id'] ?></h5>
  <?= statusBadge($reviewStatus ?: 'draft') ?>
  <?= statusBadge($registration['payment_status'] ?? 'pending') ?>
  <a href="/institution/registrations/<?= (int)$registration['id'] ?>/edit"
     class="btn btn-sm btn-outline-primary ms-auto">
    <i class="bi bi-pencil-square me-1"></i>Edit Registration
  </a>
</div>

<?= flashBag() ?>

<div class="row g-4">
  <!-- Athlete profile + decision panel -->
  <div class="col-lg-4">
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-person-badge me-2"></i>Athlete Profile</h6>
        <button type="button" class="btn btn-sm btn-outline-primary"
                data-bs-toggle="modal" data-bs-target="#editAthleteModal">
          <i class="bi bi-pencil me-1"></i>Edit Profile
        </button>
      </div>
      <div class="text-center mb-3">
        <?php if (!empty($athlete['passport_photo'])): ?>
          <img src="<?= e($athlete['passport_photo']) ?>" class="rounded-circle"
               width="96" height="96" style="object-fit:cover;border:3px solid #e2e8f0">
        <?php else: ?>
          <div class="sms-avatar sms-avatar-xl mx-auto"><?= avatarInitials($athlete['name']) ?></div>
        <?php endif; ?>
        <div class="fw-bold mt-2"><?= e($athlete['name']) ?></div>
        <div class="text-muted small">
          <?= e(genderLabel((string)($athlete['gender'] ?? ''), $event ?? null)) ?>
          <?php if (!empty($athlete['date_of_birth'])): ?> · <?= ageFromDob($athlete['date_of_birth']) ?> yrs<?php endif; ?>
        </div>
      </div>
      <?php
        // Resolve athlete age + the age category bracket(s) they fall into.
        // Categories are driven by the age_categories master table (Super
        // Admin → Settings → Sports → Age Categories) rather than hard-coded
        // brackets, so they always reflect the current configuration.
        $athleteAge    = !empty($athlete['date_of_birth']) ? \ageFromDob($athlete['date_of_birth']) : null;
        $athleteAgeCats = \Models\Athlete::baseAgeCategories($athlete['date_of_birth'] ?? null);
      ?>
      <dl class="small mb-0">
        <dt class="text-muted">Mobile</dt><dd><?= e($athlete['mobile'] ?? '') ?></dd>
        <dt class="text-muted">Email</dt><dd><?= e($registration['athlete_email'] ?? '') ?></dd>
        <dt class="text-muted">Date of Birth</dt>
        <dd>
          <?php if (!empty($athlete['date_of_birth'])): ?>
            <?= formatDate($athlete['date_of_birth']) ?>
            <span class="text-muted">(<?= (int)$athleteAge ?> yrs)</span>
          <?php else: ?>—<?php endif; ?>
        </dd>
        <dt class="text-muted">Age Category</dt>
        <dd>
          <?php if (!empty($athleteAgeCats)): ?>
            <?php foreach ($athleteAgeCats as $cat): ?>
              <span class="badge bg-info-subtle text-info me-1"><?= e($cat) ?></span>
            <?php endforeach; ?>
          <?php else: ?>—<?php endif; ?>
        </dd>
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
    <?php
      // A decision may only be taken once submitted. Draft (never submitted)
      // is view-only; approved/rejected are terminal; pending/returned are
      // open for a decision.
      $isDraft   = ($reviewStatus === null || $reviewStatus === '');
      $decidable = in_array($reviewStatus, ['pending', 'returned'], true);
    ?>
    <?php if ($isDraft): ?>
      <div class="sms-card p-3 border-start border-4 border-secondary">
        <strong><i class="bi bi-hourglass-split me-1"></i>Not submitted yet</strong>
        <div class="small text-muted mt-1">
          This registration is still a <strong>Draft</strong> — the unit / athlete hasn't submitted it.
          You can review it once it's submitted.
        </div>
      </div>
    <?php elseif ($decidable): ?>
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
        <?php if ($reviewStatus === 'returned'): ?>
          <form method="POST" action="/institution/registrations/<?= (int)$registration['id'] ?>/revoke" class="mt-2"
                onsubmit="return confirm('Revoke this decision and reopen the registration for review?');">
            <?= csrf() ?>
            <button class="btn btn-sm btn-outline-secondary w-100">
              <i class="bi bi-arrow-counterclockwise me-1"></i>Revoke decision (reopen)
            </button>
          </form>
        <?php endif; ?>
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
        <form method="POST" action="/institution/registrations/<?= (int)$registration['id'] ?>/revoke" class="mt-2"
              onsubmit="return confirm('Revoke this <?= e($reviewStatus) ?> decision? The registration returns to <?= !empty($registration['submitted_at']) ? 'Pending review' : 'Draft' ?>.');">
          <?= csrf() ?>
          <button class="btn btn-sm btn-outline-secondary w-100">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Revoke <?= e($reviewStatus) ?> (reopen)
          </button>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <!-- Registration + items + payments -->
  <div class="col-lg-8">
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-info-circle me-2"></i>Registration Details</h6>
      <dl class="row small mb-0">
        <?php if (!empty($registration['competitor_number'])): ?>
          <dt class="col-sm-4 text-muted">Competitor Number</dt>
          <dd class="col-sm-8" style="font-size:1.2rem;letter-spacing:1px">
            <span class="fw-bold text-success">
              #<?= str_pad((string)(int)$registration['competitor_number'], 4, '0', STR_PAD_LEFT) ?>
            </span>
            <a href="/athlete/registrations/<?= e(hid_reg((int)$registration['id'])) ?>/card" target="_blank"
               class="btn btn-sm btn-outline-success ms-2" style="font-size:.875rem">
               <i class="bi bi-card-heading me-1"></i>View Card
            </a>
            <form method="POST" action="/institution/registrations/<?= (int)$registration['id'] ?>/resend-card"
                  class="d-inline-block ms-1" style="font-size:.875rem"
                  onsubmit="return confirm('Resend the competitor card to the athlete via email?')">
              <?= csrf() ?>
              <button class="btn btn-sm btn-outline-primary"><i class="bi bi-envelope me-1"></i>Resend Email</button>
            </form>
          </dd>
        <?php endif; ?>
        <dt class="col-sm-4 text-muted">Event</dt><dd class="col-sm-8 fw-medium"><?= e($registration['event_name']) ?></dd>
        <dt class="col-sm-4 text-muted">Unit</dt><dd class="col-sm-8"><?= e($registration['unit_name'] ?? '—') ?>
          <?php if (!empty($registration['unit_address'])): ?><div class="text-muted"><?= e($registration['unit_address']) ?></div><?php endif; ?>
        </dd>
        <dt class="col-sm-4 text-muted">NOC / Undertaking</dt>
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
        <?php
          $txAll      = is_array($payments ?? null) ? $payments : [];
          $txCount    = count($txAll);
          $txApproved = 0; $txPending = 0; $txRejected = 0;
          $txApprovedAmt = 0.0; $txSubmittedAmt = 0.0;
          foreach ($txAll as $p) {
              $txSubmittedAmt += (float)$p['amount'];
              if (($p['status'] ?? '') === 'approved') { $txApproved++; $txApprovedAmt += (float)$p['amount']; }
              elseif (($p['status'] ?? '') === 'rejected') $txRejected++;
              else $txPending++;
          }
        ?>
        <dt class="col-sm-4 text-muted">Total Transactions</dt>
        <dd class="col-sm-8">
          <strong><?= $txCount ?></strong>
          <span class="text-muted small">submitted ₹<?= number_format($txSubmittedAmt, 2) ?></span>
          <?php if ($txPending || $txRejected): ?>
            <div class="small text-muted">
              <?php if ($txPending): ?><span class="badge bg-warning text-dark me-1"><?= $txPending ?> pending</span><?php endif; ?>
              <?php if ($txRejected): ?><span class="badge bg-danger me-1"><?= $txRejected ?> rejected</span><?php endif; ?>
            </div>
          <?php endif; ?>
        </dd>
        <dt class="col-sm-4 text-muted">Approved Transactions</dt>
        <dd class="col-sm-8">
          <strong><?= $txApproved ?></strong>
          <span class="text-muted small">approved ₹<?= number_format($txApprovedAmt, 2) ?></span>
        </dd>
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

    <!-- Sports Items / Weapons Sharing Details -->
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-tools me-2"></i>Sports Items / Weapons</h6>
      <?php if (empty($sport_items)): ?>
        <p class="text-muted small mb-0">The athlete has not declared any items / weapons.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr><th>Sport</th><th>Item / Weapon</th><th>Model</th><th>Serial Number</th></tr>
            </thead>
            <tbody>
              <?php foreach ($sport_items as $r): ?>
                <tr>
                  <td class="text-muted small"><?= e($r['sport_name']) ?></td>
                  <td class="fw-medium"><?= e($r['item_name']) ?></td>
                  <td><?= e($r['model'] ?? '—') ?></td>
                  <td><?= e($r['serial_number'] ?? '—') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 flex-wrap gap-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-receipt me-2"></i>Payment Transactions</h6>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addManualPayModal">
          <i class="bi bi-plus-lg me-1"></i>Add Manual Transaction
        </button>
      </div>
      <?php if (empty($payments)): ?>
        <p class="text-muted small mb-0">No transactions submitted yet.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Transaction No.</th>
                <th class="text-end">Amount</th>
                <th>Proof</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($payments as $p):
                $isEpay = ($p['payment_method'] ?? 'manual') === 'epayment';
                $txnNo  = $isEpay ? ($p['razorpay_payment_id'] ?: $p['razorpay_order_id'] ?: $p['transaction_number'])
                                  : $p['transaction_number'];
              ?>
                <tr>
                  <td class="small"><?= formatDate($p['transaction_date']) ?></td>
                  <td>
                    <?php if ($isEpay): ?>
                      <span class="badge bg-info-subtle text-info"><i class="bi bi-credit-card me-1"></i>ePayment</span>
                    <?php else: ?>
                      <span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-bank me-1"></i>Manual</span>
                    <?php endif; ?>
                  </td>
                  <td><code class="small"><?= e($txnNo) ?></code></td>
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
                    <?php if ($p['status'] === 'pending' && !$isDraft): ?>
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

    <!-- Add Manual Transaction modal -->
    <div class="modal fade" id="addManualPayModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST"
              action="/institution/registrations/<?= (int)$registration['id'] ?>/payments/add"
              enctype="multipart/form-data">
          <?= csrf() ?>
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Manual Transaction</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p class="small text-muted mb-3">
              Log a manual payment on behalf of the athlete (cash, NEFT, UPI etc.).
              You can mark the new row as <em>Approved</em>, <em>Rejected</em>, or leave it
              as <em>Pending</em> for review.
            </p>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Transaction Date <span class="text-danger">*</span></label>
                <input type="date" name="transaction_date" class="form-control" max="<?= date('Y-m-d') ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Transaction Number <span class="text-danger">*</span></label>
                <input type="text" name="transaction_number" class="form-control" maxlength="100" placeholder="UTR / Ref no." required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Amount <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">₹</span>
                  <input type="number" name="transaction_amount" class="form-control" min="0.01" step="0.01" required>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Proof <small class="text-muted">(Optional)</small></label>
                <input type="file" name="transaction_proof" class="form-control" accept="image/jpeg,image/png,application/pdf">
              </div>
              <div class="col-12">
                <label class="form-label">Initial Decision</label>
                <div class="d-flex gap-3 flex-wrap">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="decision" id="dec_pending" value="pending" checked>
                    <label class="form-check-label" for="dec_pending"><span class="badge bg-warning text-dark">Pending</span> — leave for review</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="decision" id="dec_approve" value="approve">
                    <label class="form-check-label" for="dec_approve"><span class="badge bg-success">Approve</span> — mark as received</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="decision" id="dec_reject" value="reject">
                    <label class="form-check-label" for="dec_reject"><span class="badge bg-danger">Reject</span> — with reason</label>
                  </div>
                </div>
              </div>
              <div class="col-12 d-none" id="rejReasonWrap">
                <label class="form-label">Rejection Reason</label>
                <textarea name="rejection_reason" class="form-control" rows="2" placeholder="Why is this transaction being rejected?"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Transaction</button>
          </div>
        </form>
      </div>
    </div>
    <script>
    (function () {
      const wrap = document.getElementById('rejReasonWrap');
      document.querySelectorAll('#addManualPayModal input[name="decision"]').forEach(r => {
        r.addEventListener('change', () => {
          wrap.classList.toggle('d-none', r.value !== 'reject' || !r.checked);
        });
      });
    })();
    </script>
  </div>
</div>

<!-- Edit Athlete Profile modal -->
<div class="modal fade" id="editAthleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <form class="modal-content" method="POST"
          action="/institution/registrations/<?= (int)$registration['id'] ?>/athlete-profile"
          enctype="multipart/form-data">
      <?= csrf() ?>
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-gear me-2"></i>Edit Athlete Profile</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted">
          Update the athlete's basic details. This applies even when the athlete's
          profile is locked by an approved registration.
        </p>
        <div class="mb-3 text-center">
          <?php if (!empty($athlete['passport_photo'])): ?>
            <img src="<?= e($athlete['passport_photo']) ?>" class="rounded-circle mb-2"
                 width="84" height="84" style="object-fit:cover;border:2px solid #e2e8f0">
          <?php endif; ?>
          <label class="form-label d-block">Passport Photo <small class="text-muted">(JPG/PNG/WEBP · max 7 MB · leave blank to keep)</small></label>
          <input type="file" name="passport_photo" class="form-control" accept="image/jpeg,image/png,image/webp">
        </div>
        <div class="mb-3">
          <label class="form-label">Full Name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" maxlength="255"
                 value="<?= e($athlete['name'] ?? '') ?>" required>
        </div>
        <div class="row g-3">
          <div class="col-sm-6">
            <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
            <input type="date" name="date_of_birth" class="form-control" max="<?= date('Y-m-d') ?>"
                   value="<?= e($athlete['date_of_birth'] ?? '') ?>" required>
          </div>
          <div class="col-sm-6">
            <label class="form-label">Mobile <span class="text-danger">*</span></label>
            <input type="tel" name="mobile" class="form-control" maxlength="10"
                   value="<?= e($athlete['mobile'] ?? '') ?>" placeholder="10-digit" required>
          </div>
        </div>
        <hr class="my-3">
        <div class="mb-3">
          <label class="form-label">Aadhaar Number</label>
          <input type="text" name="id_proof_number" class="form-control"
                 inputmode="numeric" pattern="\d{12}" maxlength="12"
                 value="<?= e($athlete['id_proof_number'] ?? '') ?>" placeholder="12-digit Aadhaar">
        </div>
        <div class="mb-1">
          <?php if (!empty($athlete['id_proof_file'])): ?>
            <div class="small mb-1">
              <i class="bi bi-paperclip me-1"></i>Current Aadhaar proof:
              <a href="<?= e($athlete['id_proof_file']) ?>" target="_blank"
                 class="ms-1"><i class="bi bi-eye"></i> View</a>
            </div>
          <?php endif; ?>
          <label class="form-label d-block">
            Aadhaar Proof File <small class="text-muted">(JPG/PNG/WEBP/PDF · max 7 MB · leave blank to keep)</small>
          </label>
          <input type="file" name="id_proof_file" class="form-control"
                 accept="image/jpeg,image/png,image/webp,application/pdf">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Profile</button>
      </div>
    </form>
  </div>
</div>
