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
// $payments is already filtered to exclude legacy 'demand' placeholder
// rows (see EventRegistrationPayment::forRegistration).
$totalDemand = 0.0;
foreach ($items as $it) { $totalDemand += (float)($it['fee'] ?? 0); }
$claimed = 0.0;
foreach ($payments ?? [] as $p) {
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
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-person me-2"></i>Profile</h6>
        <?php if ($canEdit): ?>
          <button type="button" class="btn btn-sm btn-outline-primary"
                  data-bs-toggle="modal" data-bs-target="#editProfileModal">
            <i class="bi bi-pencil me-1"></i>Edit Profile
          </button>
        <?php endif; ?>
      </div>
      <div class="text-center mb-3">
        <?php if (!empty($athlete['passport_photo'])): ?>
          <img src="<?= e($athlete['passport_photo']) ?>" class="rounded-circle"
               width="96" height="96" style="object-fit:cover;border:3px solid #e2e8f0">
        <?php else: ?>
          <div class="sms-avatar sms-avatar-xl mx-auto"><?= avatarInitials($athlete['name'] ?? '') ?></div>
        <?php endif; ?>
        <div class="fw-bold mt-2"><?= e($athlete['name'] ?? '') ?></div>
        <?php
          // Age reckoned on the event's age-calc date (defaults to event
          // start) so it lines up with the eligible age category.
          $ageAsOn = !empty($athlete['date_of_birth'])
            ? ageOnDate($athlete['date_of_birth'], $age_calc_date ?? null)
            : null;
        ?>
        <div class="text-muted small">
          <?= e(genderLabel((string)($athlete['gender'] ?? ''), $event)) ?>
          <?php if ($ageAsOn !== null): ?> · <?= (int)$ageAsOn ?> yrs<?php endif; ?>
        </div>
        <?php if (!empty($age_category_label)): ?>
          <div class="mt-1">
            <span class="badge bg-primary-subtle text-primary-emphasis">
              <i class="bi bi-people me-1"></i><?= e($age_category_label) ?>
            </span>
          </div>
        <?php endif; ?>
        <?php if ($ageAsOn !== null && !empty($age_calc_date)): ?>
          <div class="text-muted mt-1" style="font-size:.72rem">
            Age &amp; category as on <?= e(formatDate($age_calc_date)) ?>
          </div>
        <?php endif; ?>
      </div>
      <dl class="small mb-0">
        <dt class="text-muted">Mobile</dt><dd><?= e($athlete['mobile'] ?? '—') ?></dd>
        <dt class="text-muted">Email</dt><dd><?= e($registration['athlete_email'] ?? '—') ?></dd>
        <dt class="text-muted">Date of Birth</dt>
        <dd><?= !empty($athlete['date_of_birth']) ? formatDate($athlete['date_of_birth']) : '—' ?></dd>
        <dt class="text-muted">Aadhaar</dt>
        <dd>
          <?= e($athlete['id_proof_number'] ?? '—') ?>
          <?php if (!empty($athlete['id_proof_file'])): ?>
            <a href="<?= e($athlete['id_proof_file']) ?>" target="_blank" rel="noopener"
               class="ms-1 text-decoration-none" title="View Aadhaar proof">
              <i class="bi bi-paperclip"></i> View
            </a>
          <?php endif; ?>
        </dd>
        <dt class="text-muted">Date of Birth Proof</dt>
        <dd>
          <?php
            $dobType = trim((string)($athlete['dob_proof_type_name'] ?? ''));
            $dobNum  = trim((string)($athlete['dob_proof_number'] ?? ''));
            $dobBits = array_filter([$dobType, $dobNum]);
          ?>
          <?php if ($dobBits || !empty($athlete['dob_proof_file'])): ?>
            <?= $dobBits ? e(implode(' · ', $dobBits)) : '—' ?>
            <?php if (!empty($athlete['dob_proof_file'])): ?>
              <a href="<?= e($athlete['dob_proof_file']) ?>" target="_blank" rel="noopener"
                 class="ms-1 text-decoration-none" title="View DOB proof">
                <i class="bi bi-paperclip"></i> View
              </a>
            <?php endif; ?>
          <?php else: ?>
            —
          <?php endif; ?>
        </dd>
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

          // Age-category lock: every event on a registration must share one
          // age category. Derive the locked category from the already-added
          // items (first one that carries an age category) and the set of
          // categories still available for the filter dropdown.
          $esAgeById = [];
          foreach ($event_sports as $es) {
            $esAgeById[(int)$es['id']] = (string)($es['sport_event_age_category'] ?? '');
          }
          $lockedAgeCat = '';
          foreach ($items as $it) {
            $a = $esAgeById[(int)($it['event_sport_id'] ?? 0)] ?? '';
            if ($a !== '') { $lockedAgeCat = $a; break; }
          }
          $availAgeCats = [];
          foreach ($available as $es) {
            $a = (string)($es['sport_event_age_category'] ?? '');
            if ($a !== '') $availAgeCats[$a] = true;
          }
          $availAgeCats = array_keys($availAgeCats);
          sort($availAgeCats);
        ?>
        <form method="POST" action="/unit/athletes/<?= e($regHash) ?>/items/add" class="row g-2 align-items-end mb-2">
          <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
          <div class="col-md-3">
            <label class="form-label small mb-1">Age Category
              <?php if ($lockedAgeCat !== ''): ?><span class="text-muted">(locked)</span><?php endif; ?>
            </label>
            <select id="esAgeCatFilter" class="form-select form-select-sm" onchange="filterSportEvents()"
                    <?= $lockedAgeCat !== '' ? 'disabled' : '' ?>>
              <?php if ($lockedAgeCat !== ''): ?>
                <option value="<?= e($lockedAgeCat) ?>" selected><?= e($lockedAgeCat) ?></option>
              <?php else: ?>
                <option value="">All eligible</option>
                <?php foreach ($availAgeCats as $ac): ?>
                  <option value="<?= e($ac) ?>"><?= e($ac) ?></option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small mb-1">Pick an eligible sport event</label>
            <select id="esPicker" name="event_sport_id" class="form-select form-select-sm" required <?= empty($available) ? 'disabled' : '' ?>>
              <option value="">— Select —</option>
              <?php foreach ($available as $es): ?>
                <option value="<?= (int)$es['id'] ?>" data-age-cat="<?= e($es['sport_event_age_category'] ?? '') ?>">
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
        <?php if ($lockedAgeCat !== ''): ?>
          <p class="small text-info mb-2">
            <i class="bi bi-lock me-1"></i>
            Events are locked to the <strong><?= e($lockedAgeCat) ?></strong> age category because an event from it
            is already added. Remove all added events to switch to a different eligible category.
          </p>
        <?php endif; ?>
        <script>
          function filterSportEvents() {
            var f   = document.getElementById('esAgeCatFilter');
            var sel = document.getElementById('esPicker');
            if (!sel) return;
            var val = f ? f.value : '';
            Array.prototype.forEach.call(sel.options, function (o) {
              if (!o.value) return; // keep the placeholder
              var ac = o.getAttribute('data-age-cat') || '';
              var show = (val === '' || ac === val);
              o.hidden = !show;
              o.disabled = !show;
              if (!show && o.selected) sel.value = '';
            });
          }
          document.addEventListener('DOMContentLoaded', filterSportEvents);
        </script>
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
                            onsubmit="return confirm('Remove this event from the registration? The Total Demand will refresh.');">
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
                $isEpay = $method === 'epayment';
                $txnNo  = $isEpay ? ($p['razorpay_payment_id'] ?: $p['razorpay_order_id'] ?: $p['transaction_number'])
                                  : $p['transaction_number'];
                $typeBadge = $isEpay
                  ? '<span class="badge bg-info-subtle text-info">ePayment</span>'
                  : '<span class="badge bg-secondary-subtle text-secondary">Manual</span>';
                // Only the Unit User's own pending manual rows can be
                // removed. Approved and ePayment rows stay.
                $canRemove = $canEdit && !$isEpay
                             && (($p['status'] ?? '') === 'pending');
              ?>
                <tr>
                  <td class="small"><?= formatDate($p['transaction_date']) ?></td>
                  <td><?= $typeBadge ?></td>
                  <td>
                    <code class="small"><?= e($txnNo) ?></code>
                    <?php if (!empty($p['proof_file'])): ?>
                      <a href="<?= e($p['proof_file']) ?>" target="_blank" rel="noopener"
                         class="ms-1 small text-decoration-none" title="View proof">
                        <i class="bi bi-paperclip"></i>
                      </a>
                    <?php endif; ?>
                  </td>
                  <td class="text-end fw-medium">₹<?= number_format((float)$p['amount'], 2) ?></td>
                  <td><?= statusBadge($p['status']) ?></td>
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

<?php if ($canEdit):
  $aadhaarReq        = $event['aadhaar_required']   ?? 'optional';
  $aadhaarMandatory  = $aadhaarReq === 'mandatory';
  $aadhaarHide       = $aadhaarReq === 'hide';
  $dobProofReq       = $event['dob_proof_required'] ?? 'optional';
  $dobProofMandatory = $dobProofReq === 'mandatory';
  $dobProofHide      = $dobProofReq === 'hide';
  $pwdCur = strtolower((string)($athlete['pwd_status'] ?? 'no'));
  if (!in_array($pwdCur, ['no','deaf','para'], true)) $pwdCur = 'no';
?>
<!-- ── Edit Profile modal (available until the registration is submitted) ── -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST" action="/unit/athletes/<?= e($regHash) ?>/profile" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <div class="modal-header">
          <h6 class="modal-title fw-semibold"><i class="bi bi-pencil-square me-2"></i>Edit Athlete Profile</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-12">
              <label class="form-label fw-medium">Passport Photo <small class="text-muted">(optional — leave blank to keep current)</small></label>
              <div class="d-flex align-items-center gap-3">
                <div id="epPhotoPreview" class="flex-shrink-0">
                  <?php if (!empty($athlete['passport_photo'])): ?>
                    <img src="<?= e($athlete['passport_photo']) ?>" alt="Photo" width="64" height="64"
                         style="object-fit:cover;border-radius:.5rem;border:1px solid #e2e8f0">
                  <?php else: ?>
                    <div class="sms-avatar d-flex align-items-center justify-content-center text-muted"
                         style="width:64px;height:64px;border-radius:.5rem;border:1px dashed #cbd5e1;background:#f8fafc;">
                      <i class="bi bi-person"></i>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="flex-grow-1">
                  <input type="file" id="epPhotoInput" accept="image/jpeg,image/png,image/webp"
                         class="form-control form-control-sm" onchange="epInitCropper(this)">
                  <input type="file" name="passport_photo" id="epPhotoFinal" class="d-none">
                  <small class="text-muted d-block mt-1">JPG/PNG/WEBP · You can crop after selecting.</small>
                </div>
              </div>
            </div>

            <div class="col-md-8">
              <label class="form-label fw-medium">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="name" maxlength="255" required class="form-control form-control-sm"
                     value="<?= e($athlete['name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-medium">Gender <span class="text-danger">*</span></label>
              <select name="gender" required class="form-select form-select-sm">
                <option value="">— Select —</option>
                <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $g => $lbl): ?>
                  <option value="<?= $g ?>" <?= ($athlete['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label fw-medium">Date of Birth <span class="text-danger">*</span></label>
              <input type="date" name="date_of_birth" max="<?= date('Y-m-d') ?>" required
                     class="form-control form-control-sm" value="<?= e($athlete['date_of_birth'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-medium">Mobile <small class="text-muted">(optional)</small></label>
              <input type="tel" name="mobile" maxlength="10" inputmode="numeric" pattern="\d{10}"
                     class="form-control form-control-sm" value="<?= e($athlete['mobile'] ?? '') ?>" placeholder="10-digit">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-medium">Is Person with Disability (PwD)?</label>
              <select name="pwd_status" class="form-select form-select-sm">
                <?php foreach (['no' => 'No', 'deaf' => 'Deaf', 'para' => 'Para'] as $v => $l): ?>
                  <option value="<?= $v ?>" <?= $pwdCur === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- ID Proof — Aadhaar (grouped) -->
            <?php if (!$aadhaarHide): ?>
            <div class="col-12">
              <div class="border rounded-3 p-3 bg-light-subtle">
                <div class="small fw-semibold mb-2">
                  <i class="bi bi-card-text me-1"></i>ID Proof — Aadhaar
                  <?php if ($aadhaarMandatory): ?><span class="text-danger">*</span><?php endif; ?>
                </div>
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label fw-medium">Aadhaar Number
                      <?php if ($aadhaarMandatory): ?><span class="text-danger">*</span><?php else: ?><small class="text-muted">(optional)</small><?php endif; ?>
                    </label>
                    <input type="text" name="id_proof_number" inputmode="numeric" pattern="\d{12}" maxlength="12"
                           <?= $aadhaarMandatory ? 'required' : '' ?>
                           class="form-control form-control-sm" value="<?= e($athlete['id_proof_number'] ?? '') ?>" placeholder="12-digit">
                  </div>
                  <div class="col-md-8">
                    <label class="form-label fw-medium">Aadhaar Proof File
                      <small class="text-muted">(leave blank to keep current)</small>
                    </label>
                    <input type="file" name="id_proof_file" class="form-control form-control-sm"
                           accept="image/jpeg,image/png,image/webp,application/pdf">
                    <?php if (!empty($athlete['id_proof_file'])): ?>
                      <small class="text-success d-block mt-1">
                        <i class="bi bi-check-circle me-1"></i>On file —
                        <a href="<?= e($athlete['id_proof_file']) ?>" target="_blank" rel="noopener">View</a>
                      </small>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <!-- Date of Birth Proof (grouped) -->
            <?php if (!$dobProofHide): ?>
            <div class="col-12">
              <div class="border rounded-3 p-3 bg-light-subtle">
                <div class="small fw-semibold mb-2">
                  <i class="bi bi-calendar-check me-1"></i>Date of Birth Proof
                  <?php if ($dobProofMandatory): ?><span class="text-danger">*</span><?php else: ?><small class="text-muted fw-normal">(optional)</small><?php endif; ?>
                </div>
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label fw-medium">DOB Proof Type
                      <?php if ($dobProofMandatory): ?><span class="text-danger">*</span><?php endif; ?>
                    </label>
                    <select name="dob_proof_type_id" class="form-select form-select-sm" <?= $dobProofMandatory ? 'required' : '' ?>>
                      <option value="">— Select —</option>
                      <?php foreach (($dob_proof_types ?? []) as $ip): ?>
                        <option value="<?= (int)$ip['id'] ?>"
                                <?= (int)($athlete['dob_proof_type_id'] ?? 0) === (int)$ip['id'] ? 'selected' : '' ?>>
                          <?= e($ip['name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-medium">Document Number
                      <?php if ($dobProofMandatory): ?><span class="text-danger">*</span><?php endif; ?>
                    </label>
                    <input type="text" name="dob_proof_number" maxlength="100" class="form-control form-control-sm"
                           <?= $dobProofMandatory ? 'required' : '' ?>
                           value="<?= e($athlete['dob_proof_number'] ?? '') ?>" placeholder="e.g. DL-12345">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-medium">Upload DOB Proof
                      <?php if ($dobProofMandatory && empty($athlete['dob_proof_file'])): ?><span class="text-danger">*</span><?php else: ?><small class="text-muted">(keep blank to retain)</small><?php endif; ?>
                    </label>
                    <input type="file" name="dob_proof_file" class="form-control form-control-sm"
                           <?= $dobProofMandatory && empty($athlete['dob_proof_file']) ? 'required' : '' ?>
                           accept="image/jpeg,image/png,image/webp,application/pdf">
                    <?php if (!empty($athlete['dob_proof_file'])): ?>
                      <small class="text-success d-block mt-1">
                        <i class="bi bi-check-circle me-1"></i>On file —
                        <a href="<?= e($athlete['dob_proof_file']) ?>" target="_blank" rel="noopener">View</a>
                      </small>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <div class="col-12">
              <label class="form-label fw-medium">Address <small class="text-muted">(optional)</small></label>
              <textarea name="address" rows="2" maxlength="500" class="form-control form-control-sm"><?= e($athlete['address'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-save me-1"></i>Save Profile</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Cropper Modal ── -->
<div class="modal fade" id="epCropperModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-semibold"><i class="bi bi-crop me-2"></i>Crop Passport Photo</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-3">
        <div style="max-height:420px;overflow:hidden">
          <img id="epCropperImg" src="" alt="Crop" style="max-width:100%;display:block">
        </div>
        <small class="text-muted d-block mt-2">Drag to reposition · Scroll to zoom · 7:9 passport crop</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary fw-semibold" onclick="epApplyCrop()">
          <i class="bi bi-check-lg me-1"></i>Use Photo
        </button>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script>
/* Passport photo crop for the Edit Profile modal — the cropped 7:9 JPEG is
   written into the hidden #epPhotoFinal input the form submits. */
let epCropper = null, _epCropModal = null;
function epGetCropModal() {
  if (!_epCropModal) {
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) { alert('Page still loading, try again.'); return null; }
    _epCropModal = new bootstrap.Modal(document.getElementById('epCropperModal'));
  }
  return _epCropModal;
}
function epInitCropper(input) {
  if (!input.files || !input.files[0]) return;
  const file = input.files[0];
  if (!/^image\/(jpeg|png|webp)$/i.test(file.type)) { alert('Please choose a JPG, PNG or WEBP image.'); input.value=''; return; }
  const reader = new FileReader();
  reader.onload = function(e) {
    const img = document.getElementById('epCropperImg');
    const modalEl = document.getElementById('epCropperModal');
    modalEl.addEventListener('shown.bs.modal', function startCrop() {
      const build = () => {
        if (epCropper) epCropper.destroy();
        epCropper = new Cropper(img, { aspectRatio: 7/9, viewMode: 1, dragMode: 'move',
          autoCropArea: 0.9, guides: true, center: true, highlight: false, toggleDragModeOnDblclick: false });
      };
      if (img.complete && img.naturalWidth > 0) build();
      else img.addEventListener('load', build, { once: true });
    }, { once: true });
    img.src = e.target.result;
    const m = epGetCropModal(); if (m) m.show();
  };
  reader.onerror = function(){ alert('Failed to read the selected file.'); input.value=''; };
  reader.readAsDataURL(file);
}
function epApplyCrop() {
  if (!epCropper) return;
  let canvas;
  try { canvas = epCropper.getCroppedCanvas({ width: 350, height: 450, fillColor: '#fff', imageSmoothingQuality: 'high' }); }
  catch (e) { canvas = null; }
  if (!canvas) { alert('Could not generate the cropped image. Please re-select the photo.'); return; }
  const m = epGetCropModal(); if (m) m.hide();
  canvas.toBlob(function(blob) {
    if (!blob) { alert('Could not encode the cropped image.'); return; }
    const dt = new DataTransfer();
    dt.items.add(new File([blob], 'photo.jpg', { type: 'image/jpeg' }));
    document.getElementById('epPhotoFinal').files = dt.files;
    const url = URL.createObjectURL(blob);
    document.getElementById('epPhotoPreview').innerHTML =
      '<img src="' + url + '" alt="Photo" width="64" height="64" style="object-fit:cover;border-radius:.5rem;border:1px solid #e2e8f0">';
    if (epCropper) { epCropper.destroy(); epCropper = null; }
  }, 'image/jpeg', 0.92);
}
document.getElementById('epCropperModal').addEventListener('hidden.bs.modal', function() {
  if (epCropper) { epCropper.destroy(); epCropper = null; }
});
</script>
<?php endif; ?>
