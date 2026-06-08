<?php
$pageTitle = 'Athlete — ' . ($registration['athlete_name'] ?? '');
$reviewStatus = $registration['admin_review_status'] ?? null;
$hasCard      = $reviewStatus === 'approved' && !empty($registration['competitor_number']);
$cardPending  = $reviewStatus === 'approved' && empty($registration['competitor_number']);
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/unit/dashboard" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2"></i><?= e($registration['athlete_name']) ?></h5>
  <?= appStatusBadge($reviewStatus ?? null, $registration['submitted_at'] ?? null) ?>
  <?= statusBadge($registration['payment_status'] ?? 'pending') ?>
</div>

<div class="row g-4">
  <!-- Profile panel -->
  <div class="col-lg-4">
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-person me-2"></i>Profile</h6>
      <div class="text-center mb-3">
        <?php if (!empty($athlete['passport_photo'])): ?>
          <img src="<?= e($athlete['passport_photo']) ?>" class="rounded-circle"
               width="96" height="96" style="object-fit:cover;border:3px solid #e2e8f0">
        <?php else: ?>
          <div class="sms-avatar sms-avatar-xl mx-auto"><?= avatarInitials($athlete['name'] ?? '') ?></div>
        <?php endif; ?>
        <div class="fw-bold mt-2"><?= e($athlete['name'] ?? '') ?></div>
        <div class="text-muted small">
          <?= ucfirst($athlete['gender'] ?? '') ?>
          <?php if (!empty($athlete['date_of_birth'])): ?> · <?= ageFromDob($athlete['date_of_birth']) ?> yrs<?php endif; ?>
        </div>
      </div>
      <dl class="small mb-0">
        <dt class="text-muted">Mobile</dt><dd><?= e($athlete['mobile'] ?? '—') ?></dd>
        <dt class="text-muted">Email</dt><dd><?= e($registration['athlete_email'] ?? '—') ?></dd>
        <dt class="text-muted">Date of Birth</dt>
        <dd><?= !empty($athlete['date_of_birth']) ? formatDate($athlete['date_of_birth']) : '—' ?></dd>
        <dt class="text-muted">Aadhaar</dt><dd><?= e($athlete['id_proof_number'] ?? '—') ?></dd>
        <dt class="text-muted">Address</dt><dd><?= e($athlete['address'] ?? '—') ?></dd>
      </dl>
    </div>

    <!-- Competitor Card status (view-only) -->
    <div class="sms-card p-3">
      <h6 class="fw-semibold border-bottom pb-2 mb-2"><i class="bi bi-card-heading me-2"></i>Competitor Card</h6>
      <?php if ($hasCard): ?>
        <div class="alert alert-success small mb-2">
          <i class="bi bi-check-circle me-1"></i>
          <strong>Issued</strong> · Competitor #<?= str_pad((string)(int)$registration['competitor_number'], 4, '0', STR_PAD_LEFT) ?>
          <?php if (!empty($registration['card_issued_at'])): ?>
            <div class="text-muted">on <?= formatDate($registration['card_issued_at'], 'd M Y H:i') ?></div>
          <?php endif; ?>
        </div>
        <a href="/athlete/registrations/<?= e(hid_reg((int)$registration['id'])) ?>/card"
           target="_blank" rel="noopener"
           class="btn btn-sm btn-outline-primary w-100">
          <i class="bi bi-eye me-1"></i>View / Print Card
        </a>
      <?php elseif ($cardPending): ?>
        <div class="alert alert-warning small mb-0">
          <i class="bi bi-hourglass-split me-1"></i>Approved — card not yet issued.
        </div>
      <?php else: ?>
        <div class="alert alert-secondary small mb-0">
          <i class="bi bi-dash-circle me-1"></i>Not issued.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Registration data -->
  <div class="col-lg-8">
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-info-circle me-2"></i>Registration Details</h6>
      <dl class="row small mb-0">
        <dt class="col-sm-4 text-muted">Event</dt>
        <dd class="col-sm-8 fw-medium"><?= e($registration['event_name']) ?></dd>
        <dt class="col-sm-4 text-muted">Unit</dt>
        <dd class="col-sm-8"><?= e($registration['unit_name'] ?? '—') ?></dd>
        <dt class="col-sm-4 text-muted">Submitted</dt>
        <dd class="col-sm-8"><?= !empty($registration['submitted_at']) ? formatDate($registration['submitted_at'], 'd M Y H:i') : '—' ?></dd>
        <dt class="col-sm-4 text-muted">Payment Mode</dt>
        <dd class="col-sm-8"><?= !empty($registration['payment_mode']) ? ucfirst($registration['payment_mode']) : '—' ?></dd>
        <dt class="col-sm-4 text-muted">Total Amount</dt>
        <dd class="col-sm-8 fw-medium">₹<?= number_format((float)($registration['total_amount'] ?? 0), 2) ?></dd>
        <dt class="col-sm-4 text-muted">Approved Paid</dt>
        <dd class="col-sm-8 fw-medium text-success">₹<?= number_format((float)($pay_totals['approved_amount'] ?? 0), 2) ?></dd>
      </dl>
    </div>

    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-trophy me-2"></i>Events Registered</h6>
      <?php if (empty($items)): ?>
        <p class="text-muted small mb-0">No sport events selected.</p>
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
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-tools me-2"></i>Weapon / Item Sharing Details</h6>
      <?php if (empty($sport_items)): ?>
        <p class="text-muted small mb-0">No items / weapons declared.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr><th>Sport</th><th>Item</th><th>Model</th><th>Serial</th></tr>
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
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-receipt me-2"></i>Payment Transactions</h6>
      <?php if (empty($payments)): ?>
        <p class="text-muted small mb-0">No transactions submitted.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr><th>Date</th><th>Type</th><th>Txn No.</th><th class="text-end">Amount</th><th>Status</th></tr>
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
                    <?= $isEpay ? '<span class="badge bg-info-subtle text-info">ePayment</span>'
                                : '<span class="badge bg-secondary-subtle text-secondary">Manual</span>' ?>
                  </td>
                  <td><code class="small"><?= e($txnNo) ?></code></td>
                  <td class="text-end">₹<?= number_format((float)$p['amount'], 2) ?></td>
                  <td><?= statusBadge($p['status']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
