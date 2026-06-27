<?php
$pageTitle = 'Athlete — ' . ($registration['athlete_name'] ?? '');
$reviewStatus = $registration['admin_review_status'] ?? null;
$hasCard      = $reviewStatus === 'approved' && !empty($registration['competitor_number']);
$cardPending  = $reviewStatus === 'approved' && empty($registration['competitor_number']);
$canEdit      = !empty($can_edit);
$pickedSports = array_column($items, 'event_sport_id');
$pickedSet    = array_fill_keys(array_map('intval', $pickedSports), true);
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$regHash   = hid_reg((int)$registration['id']);

// Demand vs. claimed totals — used to gate the Submit-for-Review CTA.
$totalDemand = 0.0;
foreach ($items as $it) { $totalDemand += (float)($it['fee'] ?? 0); }
$claimed = 0.0;
foreach ($payments ?? [] as $p) {
    if (($p['payment_method'] ?? 'manual') === 'demand') continue;
    if (($p['status'] ?? '') === 'rejected') continue;
    $claimed += (float)$p['amount'];
}
$balanceDue   = $totalDemand - $claimed;
$readyToSubmit = $totalDemand > 0 && abs($balanceDue) < 0.005;
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
          <?= e(genderLabel((string)($athlete['gender'] ?? ''), $event)) ?>
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

    <?php if ($canEdit): ?>
      <!-- Sport Events: dropdown picker + picked-items list with × remove -->
      <div class="sms-card p-4 mb-4">
        <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
          <h6 class="fw-semibold mb-0">
            <i class="bi bi-trophy me-2"></i>Sport Events
            <span class="badge bg-warning-subtle text-warning-emphasis ms-1">Draft</span>
          </h6>
          <div class="small text-muted">
            <?php if (!empty($filter_note ?? '')): ?>
              <i class="bi bi-info-circle me-1"></i><?= e($filter_note) ?>
            <?php elseif (!empty($eligible_age_categories ?? [])): ?>
              <i class="bi bi-funnel me-1"></i>Filtered by age category
              (<strong><?= e(implode(', ', $eligible_age_categories)) ?></strong>)
              and gender (<strong><?= e(genderLabel((string)($athlete['gender'] ?? ''), $event)) ?></strong>)
            <?php else: ?>
              <i class="bi bi-info-circle me-1"></i>No DOB on the athlete profile — showing all gender-matched events.
            <?php endif; ?>
          </div>
        </div>

        <?php
          // Build the dropdown's option set — every eligible event_sport
          // minus the ones the unit has already added.
          $available = array_values(array_filter(
            $event_sports,
            fn($es) => empty($pickedSet[(int)$es['id']])
          ));
        ?>
        <form method="POST" action="/unit/athletes/<?= e($regHash) ?>/items/add" class="row g-2 align-items-end mb-3">
          <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
          <div class="col-md-9">
            <label class="form-label small mb-1">Pick an eligible sport event</label>
            <select name="event_sport_id" class="form-select form-select-sm" required <?= empty($available) ? 'disabled' : '' ?>>
              <option value="">— Select —</option>
              <?php foreach ($available as $es): ?>
                <option value="<?= (int)$es['id'] ?>">
                  <?= e($es['sport_name'] ?? '') ?>
                  · <?= e($es['sport_event_name'] ?? $es['category'] ?? '') ?>
                  · <?= e($es['sport_event_age_category'] ?? '—') ?>
                  / <?= e(genderLabel((string)($es['sport_event_gender'] ?? ''), $event)) ?>
                  · ₹<?= number_format((float)$es['entry_fee'], 2) ?>
                  <?= !empty($es['event_code']) ? ' (' . $es['event_code'] . ')' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <button type="submit" class="btn btn-sm btn-primary w-100" <?= empty($available) ? 'disabled' : '' ?>>
              <i class="bi bi-plus-circle me-1"></i>Add Event
            </button>
          </div>
        </form>
        <?php if (empty($event_sports)): ?>
          <p class="text-muted small mb-0">No eligible sport-events. Either none are configured on this event for the selected Age Category Set, or the athlete's gender / DOB rules out every row.</p>
        <?php elseif (empty($available) && !empty($items)): ?>
          <p class="text-muted small mb-0">All eligible events have already been added below.</p>
        <?php endif; ?>

        <?php if (!empty($items)): ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Sport</th>
                  <th>Code</th>
                  <th>Event</th>
                  <th>Age / Gender</th>
                  <th class="text-end">Fee</th>
                  <th class="text-end" style="width:60px"></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $it):
                  // Find the matching event_sport row so we have the
                  // age-category / gender display values (items only
                  // carries the basics).
                  $meta = null;
                  foreach ($event_sports as $es) {
                    if ((int)$es['id'] === (int)($it['event_sport_id'] ?? 0)) { $meta = $es; break; }
                  }
                ?>
                  <tr>
                    <td><?= e($it['sport_name'] ?? ($meta['sport_name'] ?? '')) ?></td>
                    <td><code><?= e($it['event_code'] ?? ($meta['event_code'] ?? '')) ?></code></td>
                    <td><?= e($it['sport_event_name'] ?? ($meta['sport_event_name'] ?? $it['category'] ?? '')) ?></td>
                    <td class="small text-muted">
                      <?= e($meta['sport_event_age_category'] ?? '—') ?> ·
                      <?= e(genderLabel((string)($meta['sport_event_gender'] ?? ''), $event)) ?>
                    </td>
                    <td class="text-end">₹<?= number_format((float)($it['fee'] ?? 0), 2) ?></td>
                    <td class="text-end">
                      <form method="POST" action="/unit/athletes/<?= e($regHash) ?>/items/remove" class="d-inline"
                            onsubmit="return confirm('Remove this event from the registration? The demand row will refresh.');">
                        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="event_sport_id" value="<?= (int)($it['event_sport_id'] ?? 0) ?>">
                        <button type="submit" class="btn btn-link btn-sm text-danger p-0" title="Remove">
                          <i class="bi bi-x-circle"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot class="table-light">
                <tr>
                  <th colspan="4" class="text-end">Total Demand</th>
                  <th class="text-end">₹<?= number_format($totalDemand, 2) ?></th>
                  <th></th>
                </tr>
              </tfoot>
            </table>
          </div>
        <?php else: ?>
          <p class="text-muted small mb-0">No sport-events added yet. Pick one above and click <em>Add Event</em>.</p>
        <?php endif; ?>
      </div>

      <!-- Manual Payment Transactions — multiple rows accumulate to the total demand -->
      <div class="sms-card p-4 mb-4">
        <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 flex-wrap gap-2">
          <h6 class="fw-semibold mb-0"><i class="bi bi-cash-coin me-2"></i>Add Payment Transaction</h6>
          <div class="small">
            <span class="text-muted">Demand</span>
            <strong class="ms-1">₹<?= number_format($totalDemand, 2) ?></strong>
            <span class="text-muted ms-3">Claimed</span>
            <strong class="ms-1">₹<?= number_format($claimed, 2) ?></strong>
            <span class="text-muted ms-3">Balance</span>
            <strong class="ms-1 <?= $balanceDue > 0.005 ? 'text-danger' : ($balanceDue < -0.005 ? 'text-warning' : 'text-success') ?>">
              ₹<?= number_format($balanceDue, 2) ?>
            </strong>
          </div>
        </div>
        <?php if ($totalDemand <= 0): ?>
          <p class="text-muted small mb-0">Add at least one sport-event above first — there's nothing to pay yet.</p>
        <?php else: ?>
          <form method="POST" action="/unit/athletes/<?= e($regHash) ?>/payments" enctype="multipart/form-data" class="row g-2 align-items-end">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <div class="col-md-3">
              <label class="form-label small mb-1">Transaction Date <span class="text-danger">*</span></label>
              <input type="date" name="transaction_date" class="form-control form-control-sm"
                     max="<?= date('Y-m-d') ?>" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label small mb-1">Transaction Number <span class="text-danger">*</span></label>
              <input type="text" name="transaction_number" class="form-control form-control-sm" maxlength="100" required>
            </div>
            <div class="col-md-2">
              <label class="form-label small mb-1">Amount (₹) <span class="text-danger">*</span></label>
              <input type="number" name="transaction_amount" class="form-control form-control-sm"
                     min="0.01" step="0.01" required
                     value="<?= $balanceDue > 0 ? number_format($balanceDue, 2, '.', '') : '' ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label small mb-1">Proof File <span class="text-danger">*</span></label>
              <input type="file" name="transaction_proof" class="form-control form-control-sm"
                     accept="image/jpeg,image/png,image/webp,application/pdf" required>
            </div>
            <div class="col-md-1">
              <button type="submit" class="btn btn-sm btn-primary w-100">
                <i class="bi bi-plus-circle"></i>
              </button>
            </div>
          </form>
          <small class="text-muted d-block mt-2">
            <i class="bi bi-info-circle me-1"></i>
            You can add multiple transactions. Submit-for-Review is enabled when the sum of
            non-rejected transactions equals the total demand (₹<?= number_format($totalDemand, 2) ?>).
          </small>
        <?php endif; ?>
      </div>
    <?php else: ?>
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
    <?php endif; ?>

    <?php if ($canEdit): ?>
      <div class="sms-card p-4 mb-4 border-start border-4 border-warning">
        <div class="row g-3 align-items-center">
          <div class="col-md-8">
            <h6 class="fw-semibold mb-1"><i class="bi bi-send me-2"></i>Submit Registration</h6>
            <p class="text-muted small mb-1">
              Once submitted, the registration goes to the event administrator for review.
              You won&rsquo;t be able to edit the profile or sport-event selection from
              here unless the admin returns it.
            </p>
            <?php if (!$readyToSubmit): ?>
              <div class="small text-danger">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <?php if ($totalDemand <= 0): ?>
                  Pick at least one sport event before submitting.
                <?php else: ?>
                  Add transactions totalling
                  <strong>₹<?= number_format($totalDemand, 2) ?></strong>
                  before submitting (current balance:
                  <strong>₹<?= number_format($balanceDue, 2) ?></strong>).
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="col-md-4 text-md-end">
            <form method="POST" action="/unit/athletes/<?= e($regHash) ?>/submit"
                  onsubmit="return confirm('Submit this registration to the event administrator?');">
              <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
              <button type="submit" class="btn btn-warning fw-semibold" <?= $readyToSubmit ? '' : 'disabled' ?>>
                <i class="bi bi-send-check me-1"></i>Submit for Review
              </button>
            </form>
          </div>
        </div>
      </div>
    <?php endif; ?>

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
        <p class="text-muted small mb-0">No transactions yet.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Date</th><th>Type</th><th>Txn No.</th>
                <th class="text-end">Amount</th><th>Status</th>
                <?php if ($canEdit): ?><th class="text-end" style="width:60px"></th><?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($payments as $p):
                $method = (string)($p['payment_method'] ?? 'manual');
                $isDemand = $method === 'demand';
                $isEpay   = $method === 'epayment';
                $txnNo    = $isEpay ? ($p['razorpay_payment_id'] ?: $p['razorpay_order_id'] ?: $p['transaction_number'])
                                    : $p['transaction_number'];
                $typeBadge = $isDemand
                  ? '<span class="badge bg-warning-subtle text-warning-emphasis" title="Auto-generated when sport-events were saved — this is what the unit owes.">Demand</span>'
                  : ($isEpay
                      ? '<span class="badge bg-info-subtle text-info">ePayment</span>'
                      : '<span class="badge bg-secondary-subtle text-secondary">Manual</span>');
                // The demand row is informational — show it as "Due"
                // rather than the standard pending/approved badge.
                $statusBadgeHtml = $isDemand
                  ? '<span class="badge bg-warning text-dark">Due</span>'
                  : statusBadge($p['status']);
                // Only the Unit User's own pending manual rows can be
                // removed. Demand, approved, and ePayment rows stay.
                $canRemove = $canEdit && !$isDemand && !$isEpay
                             && (($p['status'] ?? '') === 'pending');
              ?>
                <tr<?= $isDemand ? ' class="table-warning"' : '' ?>>
                  <td class="small"><?= formatDate($p['transaction_date']) ?></td>
                  <td><?= $typeBadge ?></td>
                  <td>
                    <code class="small"><?= e($txnNo) ?></code>
                    <?php if (!$isDemand && !empty($p['proof_file'])): ?>
                      <a href="<?= e($p['proof_file']) ?>" target="_blank" rel="noopener"
                         class="ms-1 small text-decoration-none" title="View proof">
                        <i class="bi bi-paperclip"></i>
                      </a>
                    <?php endif; ?>
                  </td>
                  <td class="text-end fw-medium">₹<?= number_format((float)$p['amount'], 2) ?></td>
                  <td><?= $statusBadgeHtml ?></td>
                  <?php if ($canEdit): ?>
                    <td class="text-end">
                      <?php if ($canRemove): ?>
                        <form method="POST" action="/unit/athletes/<?= e($regHash) ?>/payments/remove" class="d-inline"
                              onsubmit="return confirm('Remove this transaction?');">
                          <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                          <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                          <button type="submit" class="btn btn-link btn-sm text-danger p-0" title="Remove">
                            <i class="bi bi-x-circle"></i>
                          </button>
                        </form>
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
