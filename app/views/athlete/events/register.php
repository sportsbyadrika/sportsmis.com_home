<?php
$pageTitle = 'Register — ' . $event['name'];
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$nocReq      = $event['noc_required'] ?? 'optional';
$paymentModes = $event['payment_modes'] ?? [];
$selectedSet = array_column($items, 'event_sport_id');
$selectedSet = array_map('intval', $selectedSet);
$total       = (float)($registration['total_amount'] ?? 0);
?>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="regToast" class="toast align-items-center border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body fw-medium" id="toastMsg"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<?php
$regLocked = $registration && !\Models\EventRegistration::isEditable($registration);
?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/athlete/events/<?= e(hid_event((int)$event['id'])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold">Register — <?= e($event['name']) ?></h5>
  <?php if ($registration): ?>
    <?= appStatusBadge($registration['admin_review_status'] ?? null, $registration['submitted_at'] ?? null) ?>
    <?= statusBadge($registration['payment_status'] ?? 'pending') ?>
  <?php endif; ?>
</div>

<?php if ($regLocked): ?>
  <div class="alert alert-info d-flex align-items-start gap-2 mb-4">
    <i class="bi bi-lock-fill fs-5"></i>
    <div>
      <strong>This registration has been submitted and is locked for review.</strong>
      You can no longer change the unit, NOC or selected sport events. The event administrator will review
      your submission and either approve, reject, or return it for changes.
    </div>
  </div>
<?php endif; ?>

<div class="row g-4">
  <!-- ── Step 1: Select unit, NOC, sport events ── -->
  <div class="col-lg-8">
    <div class="sms-card p-4 mb-4">
      <div class="sms-step-head bg-primary-subtle text-primary-emphasis rounded-3 px-3 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-1-circle me-2"></i>Step 1 — Registration Details</h6>
        <span class="badge bg-success px-3 py-2 fs-6">Total: ₹<span id="totalAmount"><?= number_format($total, 2) ?></span></span>
      </div>

      <?php
        $isOtherUnit = !empty($registration['unit_name_other']);
      ?>
      <div class="row g-3 mb-3">
        <div class="col-md-7">
          <label class="form-label fw-medium">Unit / Club / Institution <span class="text-danger">*</span></label>
          <select id="r_unit" class="form-select" onchange="onUnitChange()">
            <option value="">— Select Unit —</option>
            <?php foreach ($units as $u): ?>
              <option value="<?= (int)$u['id'] ?>"
                <?= !$isOtherUnit && (int)($registration['unit_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
                <?= e($u['name']) ?><?php if (!empty($u['address'])): ?> — <?= e($u['address']) ?><?php endif; ?>
              </option>
            <?php endforeach; ?>
            <option value="OTHER" <?= $isOtherUnit ? 'selected' : '' ?>>Other (specify name)</option>
          </select>
          <div id="r_unit_other_wrap" class="mt-2" style="<?= $isOtherUnit ? '' : 'display:none' ?>">
            <input type="text" id="r_unit_other" class="form-control"
                   value="<?= e($registration['unit_name_other'] ?? '') ?>"
                   maxlength="255" placeholder="Type the Unit / Club / Institution name"
                   oninput="updateStep1Button()">
          </div>
          <?php if (empty($units)): ?>
            <small class="text-muted">The event organiser hasn't added any Units yet — pick "Other" and type the name.</small>
          <?php endif; ?>
        </div>
        <div class="col-md-5">
          <label class="form-label fw-medium">Unit / Club / Institution Registration No.</label>
          <input type="text" id="r_unit_reg_no" class="form-control"
                 value="<?= e($registration['unit_reg_no'] ?? '') ?>"
                 maxlength="100" placeholder="e.g. SAI/2024/12345">
          <small class="text-muted">Optional — registration number issued by the parent body.</small>
        </div>
      </div>

      <?php if ($nocReq !== 'none'): ?>
      <div class="mb-3">
        <label class="form-label fw-medium">
          NOC / Undertaking from Unit
          <?= $nocReq === 'mandatory'
                ? '<span class="text-danger">* Mandatory</span>'
                : '<span class="text-muted small">(Optional)</span>' ?>
        </label>
        <input type="file" id="r_noc" class="form-control" accept="image/jpeg,image/png,application/pdf"
               onchange="updateStep1Button()">
        <?php if (!empty($registration['noc_letter'])): ?>
          <small class="text-success">
            <i class="bi bi-check-circle me-1"></i>Already uploaded
            <a href="<?= e($registration['noc_letter']) ?>" target="_blank" class="ms-1">View</a>
          </small>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div><!-- /Step 1 panel -->

    <!-- ── Step 2: Select Sport Event ── -->
    <div class="sms-card p-4 mb-4">
      <div class="sms-step-head bg-primary-subtle text-primary-emphasis rounded-3 px-3 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-2-circle me-2"></i>Step 2 — Select Sport Event</h6>
      </div>
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-trophy me-2"></i>Available Sport Events</h6>
      <?php if (empty($event['sports'])): ?>
        <p class="text-muted small">The organiser hasn't published any sport events yet.</p>
      <?php else: ?>

      <!-- Filter picker -->
      <div class="row g-2 align-items-end mb-3">
        <div class="col-md-3">
          <label class="form-label small mb-1">Sport</label>
          <select id="f_sport" class="form-select form-select-sm" onchange="onSportChange()"></select>
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">Event Category</label>
          <select id="f_category" class="form-select form-select-sm" onchange="onCategoryChange()"></select>
        </div>
        <div class="col-md-4">
          <label class="form-label small mb-1">Event</label>
          <select id="f_event" class="form-select form-select-sm"></select>
        </div>
        <div class="col-md-2">
          <button type="button" class="btn btn-sm btn-primary w-100" onclick="addSelectedEvent()">
            <i class="bi bi-plus-lg me-1"></i>Add
          </button>
        </div>
      </div>
      <div id="pickerNote" class="small text-muted mb-1"></div>
      <div id="eventNote"  class="small text-warning mb-2"></div>

      <!-- Selected sport events -->
      <div class="d-flex align-items-center justify-content-between mb-2">
        <strong class="small text-muted text-uppercase">Selected events</strong>
        <span class="small text-muted">Sum of fees:&nbsp;<strong>₹<span id="totalAmountInline"><?= number_format($total, 2) ?></span></strong></span>
      </div>
      <!-- Desktop table (md+) -->
      <div class="table-responsive d-none d-md-block">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Sport</th>
              <th>Event Code</th>
              <th>Category / Event</th>
              <th>Age / Gender</th>
              <th class="text-end">Entry Fee</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="selectedRows">
            <tr id="emptySelected"><td colspan="6" class="text-muted text-center py-3">No events selected yet.</td></tr>
          </tbody>
          <tfoot>
            <tr class="table-light">
              <th colspan="4" class="text-end">Total</th>
              <th class="text-end fw-bold">₹<span id="totalAmountTbl"><?= number_format($total, 2) ?></span></th>
              <th></th>
            </tr>
          </tfoot>
        </table>
      </div>
      <!-- Mobile cards (<md). renderSelectedRows() keeps both containers in sync. -->
      <div class="d-md-none" id="selectedCards">
        <div class="text-muted text-center small py-3" id="emptySelectedCard">No events selected yet.</div>
      </div>
      <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2 d-md-none">
        <span class="text-muted small">Total</span>
        <strong>₹<span id="totalAmountTblMobile"><?= number_format($total, 2) ?></span></strong>
      </div>
      <?php endif; ?>
    </div><!-- /Step 2 panel -->

    <!-- ── Step 3: Sports Items / Weapons Sharing Details ── -->
    <div class="sms-card p-4 mb-4">
      <div class="sms-step-head bg-primary-subtle text-primary-emphasis rounded-3 px-3 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-3-circle me-2"></i>Step 3 — Sports Items / Weapons Sharing Details</h6>
      </div>
      <?php
        // Build sport→items map from the event's allow-list, restricted to
        // sports the athlete actually picked in their selections.
        $eventItemsBySport = [];
        foreach (($event_items ?? []) as $ei) {
          $eventItemsBySport[(int)$ei['sport_id']]['name'] = $ei['sport_name'];
          $eventItemsBySport[(int)$ei['sport_id']]['items'][] = [
            'id'   => (int)$ei['sport_item_id'],
            'name' => $ei['item_name'],
          ];
        }
      ?>
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-tools me-2"></i>Declared Items / Weapons</h6>
      <?php if (empty($event_items)): ?>
        <p class="text-muted small mb-0">The organiser hasn't published any items / weapons for this event.</p>
      <?php else: ?>
      <p class="small text-muted mb-2">If you carry your own items (rifle, bow, pads, etc.), declare each piece below. Pick a sport, then the item, and add the model and serial number.</p>

      <div class="row g-2 align-items-end mb-3">
        <input type="hidden" id="rsi_id" value="">
        <div class="col-md-3">
          <label class="form-label small mb-1">Sport</label>
          <select id="rsi_sport" class="form-select form-select-sm" onchange="onRsiSportChange()">
            <option value="">Select…</option>
            <?php foreach ($eventItemsBySport as $sid => $row): ?>
              <option value="<?= (int)$sid ?>"><?= e($row['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">Item / Weapon</label>
          <select id="rsi_item" class="form-select form-select-sm">
            <option value="">— pick a sport first —</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small mb-1">Model</label>
          <input type="text" id="rsi_model" class="form-control form-control-sm" maxlength="255" placeholder="e.g. Anschutz 1907">
        </div>
        <div class="col-md-2">
          <label class="form-label small mb-1">Serial Number</label>
          <input type="text" id="rsi_serial" class="form-control form-control-sm" maxlength="255" placeholder="e.g. SN-12345">
        </div>
        <div class="col-md-2">
          <button type="button" class="btn btn-primary btn-sm w-100" onclick="addRsi()">
            <i class="bi bi-plus-lg me-1"></i><span id="rsiBtnLabel">Add details</span>
          </button>
        </div>
      </div>

      <!-- Desktop table (md+) -->
      <div class="table-responsive d-none d-md-block">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr><th>Sport</th><th>Item / Weapon</th><th>Model</th><th>Serial Number</th><th class="text-end"></th></tr>
          </thead>
          <tbody id="rsiTbody">
            <?php if (empty($sport_items)): ?>
              <tr id="rsiEmpty"><td colspan="5" class="text-muted text-center py-3">No items declared yet.</td></tr>
            <?php else: foreach ($sport_items as $r): ?>
              <tr data-id="<?= (int)$r['id'] ?>" data-sport-id="<?= (int)$r['sport_id'] ?>" data-item-id="<?= (int)$r['sport_item_id'] ?>">
                <td class="text-muted small"><?= e($r['sport_name']) ?></td>
                <td class="fw-medium"><?= e($r['item_name']) ?></td>
                <td><?= e($r['model'] ?? '—') ?></td>
                <td><?= e($r['serial_number'] ?? '—') ?></td>
                <td class="text-end">
                  <button type="button" class="btn btn-sm btn-outline-primary"
                          onclick='editRsi(<?= json_encode($r, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteRsi(<?= (int)$r['id'] ?>)">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <!-- Mobile cards (<md). renderRsi() keeps both containers in sync. -->
      <div class="d-md-none" id="rsiCards">
        <?php if (empty($sport_items)): ?>
          <div class="text-muted text-center small py-3" id="rsiEmptyCard">No items declared yet.</div>
        <?php else: foreach ($sport_items as $r): ?>
          <div class="border rounded-3 p-3 mb-2 small" data-id="<?= (int)$r['id'] ?>">
            <div class="fw-semibold text-break"><?= e($r['item_name']) ?></div>
            <div class="text-muted"><i class="bi bi-trophy me-1"></i><?= e($r['sport_name']) ?></div>
            <div class="row g-1 mt-1">
              <div class="col-6">
                <div class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.04em">Model</div>
                <div class="text-break"><?= e($r['model'] ?? '—') ?></div>
              </div>
              <div class="col-6">
                <div class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.04em">Serial #</div>
                <div class="text-break"><?= e($r['serial_number'] ?? '—') ?></div>
              </div>
            </div>
            <div class="d-flex gap-2 justify-content-end mt-2">
              <button type="button" class="btn btn-sm btn-outline-primary"
                      onclick='editRsi(<?= json_encode($r, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
                <i class="bi bi-pencil"></i>
              </button>
              <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteRsi(<?= (int)$r['id'] ?>)">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
      <?php endif; ?>

      <div class="d-flex justify-content-end border-top pt-3 mt-3">
        <button type="button" id="step1SaveBtn" class="btn btn-primary px-4 fw-semibold"
                onclick="saveStep1()" disabled
                title="Pick a Unit and at least one Sport Event first">
          <i class="bi bi-save me-2"></i>Save &amp; Continue
        </button>
      </div>
    </div><!-- /Step 3 panel -->

    <!-- ── Step 4: Payment ── -->
    <?php
      $regSubmitted = !empty($registration['admin_review_status']);
      $regApproved  = ($registration['admin_review_status'] ?? '') === 'approved';
      $regReturned  = ($registration['admin_review_status'] ?? '') === 'returned';
      $payments     = $payments ?? [];
      $approvedAmt  = 0.0;
      foreach ($payments as $p) if ($p['status'] === 'approved') $approvedAmt += (float)$p['amount'];
      $submittedAmt = 0.0;
      foreach ($payments as $p) $submittedAmt += (float)$p['amount'];
      $currentMode  = $registration['payment_mode'] ?? '';
    ?>
    <div class="sms-card p-4 mb-4 <?= empty($registration['unit_id']) ? 'opacity-50' : '' ?>" id="paymentCard">
      <div class="sms-step-head bg-primary-subtle text-primary-emphasis rounded-3 px-3 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-4-circle me-2"></i>Step 4 — Payment</h6>
        <div class="d-flex gap-2 flex-wrap">
          <span class="badge bg-success">Total: ₹<span id="totalAmount2"><?= number_format($total, 2) ?></span></span>
          <?php if ($regSubmitted): ?>
            <span class="badge bg-info text-dark">Review: <?= ucfirst($registration['admin_review_status']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <?php if (empty($registration['unit_id'])): ?>
        <div class="alert alert-secondary small mb-0">Save Step 1 first to choose a payment mode.</div>
      <?php elseif ($regApproved): ?>
        <div class="alert alert-success small mb-0">
          <i class="bi bi-check-circle me-1"></i>
          Your registration has been approved by the event administrator.
        </div>
      <?php else: ?>
        <?php if ($regReturned && !empty($registration['admin_review_notes'])): ?>
          <div class="alert alert-warning small">
            <strong>Returned for changes:</strong> <?= e($registration['admin_review_notes']) ?>
          </div>
        <?php endif; ?>

        <div class="mb-3">
          <label class="form-label fw-medium">Payment Mode <span class="text-danger">*</span></label>
          <div class="d-flex gap-2 flex-wrap">
            <?php foreach ($paymentModes as $mode): ?>
              <div class="form-check form-check-inline border rounded-3 px-3 py-2">
                <input class="form-check-input" type="radio" name="payment_mode_choice"
                       value="<?= e($mode) ?>" id="pm_<?= e($mode) ?>"
                       <?= $currentMode === $mode ? 'checked' : '' ?>
                       onchange="onPaymentModeChange()">
                <label class="form-check-label fw-medium" for="pm_<?= e($mode) ?>">
                  <?= $mode === 'manual'
                        ? '<i class="bi bi-bank me-1"></i>Manual Submission'
                        : '<i class="bi bi-credit-card me-1"></i>Online Payment' ?>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Manual payment block -->
        <div id="manualBlock" class="border rounded-3 p-3 mb-3" style="display:<?= $currentMode === 'manual' ? 'block' : 'none' ?>">
          <div class="row g-3 mb-3">
            <div class="col-md-7">
              <h6 class="fw-semibold"><i class="bi bi-bank me-2"></i>Bank Details</h6>
              <pre class="bg-light p-3 rounded small mb-0" style="white-space:pre-wrap"><?= e($event['bank_details'] ?? 'Bank details not yet provided.') ?></pre>
            </div>
            <div class="col-md-5 text-center">
              <?php if (!empty($event['bank_qr_code'])): ?>
                <img src="<?= e($event['bank_qr_code']) ?>" alt="UPI QR" class="img-fluid rounded" style="max-height:200px">
                <div class="small text-muted mt-1">Scan to pay via UPI</div>
              <?php else: ?>
                <div class="text-muted small">QR not provided.</div>
              <?php endif; ?>
            </div>
          </div>

          <h6 class="fw-semibold mt-3 mb-2"><i class="bi bi-plus-circle me-1"></i>Add Transaction</h6>
          <p class="small text-muted mb-2">
            Add one row per transaction. You can split a single payment across
            multiple transactions if needed — the total of your transactions
            must equal the total fee (₹<span id="reqTotalHint"><?= number_format($total, 2) ?></span>) before you can do Final Submit.
          </p>
          <div class="border rounded-3 p-3 mb-3 bg-light-subtle" id="addTransactionPanel">
            <div class="row g-2">
              <div class="col-md-3">
                <label class="form-label small mb-1">Transaction Date <span class="text-danger">*</span></label>
                <input type="date" id="t_date" class="form-control form-control-sm" max="<?= date('Y-m-d') ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Transaction No. <span class="text-danger">*</span></label>
                <input type="text" id="t_num" class="form-control form-control-sm" placeholder="UTR / Ref">
              </div>
              <div class="col-md-2">
                <label class="form-label small mb-1">Amount <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" id="t_amount" class="form-control form-control-sm">
              </div>
              <div class="col-md-2">
                <label class="form-label small mb-1">Proof <span class="text-danger">*</span></label>
                <input type="file" id="t_proof" class="form-control form-control-sm" accept="image/jpeg,image/png,application/pdf">
              </div>
              <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-primary btn-sm w-100" onclick="addPayment()"><i class="bi bi-plus-lg me-1"></i>Add Transaction</button>
              </div>
            </div>
            <div id="t_addProgress" class="small text-primary mt-2 d-none">
              <span class="spinner-border spinner-border-sm me-1" role="status"></span>Saving transaction…
            </div>
          </div>

          <p class="small text-muted mb-0">
            <i class="bi bi-info-circle me-1"></i>Once added, your transactions appear in the Transactions panel below.
            The total of approved transactions must equal the required fee
            (<strong>₹<span id="reqTotal2"><?= number_format($total, 2) ?></span></strong>) before you can do Final Submit.
            <span id="amountMatchHint" class="ms-2 text-muted"></span>
          </p>
        </div>

        <!-- Online payment block -->
        <div id="onlineBlock" class="border rounded-3 p-3 mb-3" style="display:<?= $currentMode === 'online' ? 'block' : 'none' ?>">
          <h6 class="fw-semibold"><i class="bi bi-receipt me-2"></i>Summary</h6>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead class="table-light"><tr><th>Sport</th><th>Code</th><th>Event</th><th class="text-end">Fee</th></tr></thead>
              <tbody>
                <?php foreach ($items as $it): ?>
                  <tr>
                    <td><?= e($it['sport_name']) ?></td>
                    <td><code><?= e($it['event_code'] ?? '') ?></code></td>
                    <td><?= e($it['sport_event_name'] ?? $it['category'] ?? '') ?></td>
                    <td class="text-end">₹<?= number_format((float)$it['fee'], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr class="table-light"><th colspan="3" class="text-end">Total</th><th class="text-end">₹<?= number_format($total, 2) ?></th></tr></tfoot>
            </table>
          </div>
          <?php
            // Outstanding amount the athlete still owes for this registration:
            // total minus already-approved payments (manual or epayment).
            $approvedPaidNow = 0.0;
            foreach (($payments ?? []) as $p) if (($p['status'] ?? '') === 'approved') $approvedPaidNow += (float)$p['amount'];
            $epayOutstanding = max(0, round(((float)$total) - $approvedPaidNow, 2));
            $alreadyPaid = $approvedPaidNow > 0;
          ?>
          <div id="epayStatus" class="alert alert-info py-2 small mb-2 <?= $epayOutstanding > 0 ? 'd-none' : '' ?>">
            <i class="bi bi-check2-circle me-1"></i>This registration is fully paid. Nothing more to pay.
          </div>
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2">
            <small class="text-muted">
              Outstanding: <strong>₹<span id="epayOutstanding"><?= number_format($epayOutstanding, 2) ?></span></strong>
              <?php if ($alreadyPaid): ?>
                <span class="text-success ms-1">(₹<?= number_format($approvedPaidNow, 2) ?> already paid)</span>
              <?php endif; ?>
            </small>
            <button type="button" id="payOnlineBtn" class="btn btn-primary fw-semibold"
                    onclick="payOnline()" <?= $epayOutstanding <= 0 ? 'disabled' : '' ?>>
              <i class="bi bi-credit-card me-2"></i>Pay ₹<span id="payBtnAmount"><?= number_format($epayOutstanding, 2) ?></span> Online
            </button>
          </div>
          <div id="epayInline" class="small mt-2"></div>
          <p class="small text-muted mt-2 mb-0">
            <i class="bi bi-shield-lock me-1"></i>Payments are processed securely by Razorpay. We never see your card details.
          </p>
          <div class="alert alert-warning small py-2 px-3 mt-2 mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>Don't retry if your bank has debited the amount.</strong>
            Please wait 2&ndash;3 minutes &mdash; the payment status will update automatically once the bank confirms.
            If the page still shows "Outstanding" after 5 minutes, refresh once. Repeated retries can charge you twice.
          </div>

        </div>

        <div class="alert alert-info small d-flex gap-2 align-items-start mt-3 mb-2" role="alert">
          <i class="bi bi-shield-check fs-5"></i>
          <div>
            <strong>Please review carefully before submitting.</strong>
            By clicking Final Submit you confirm that all the details entered above
            (Unit, NOC, Sport Events, Items / Weapons, Payment) are true and correct
            to the best of your knowledge. Once submitted, the registration is locked
            for the event administrator's review and you will not be able to edit it.
          </div>
        </div>
        <div class="d-flex justify-content-end border-top pt-3 mt-3">
          <button type="button" id="finalSubmitBtn" class="btn btn-success px-4 fw-semibold"
                  onclick="finalSubmit()" disabled
                  title="Add at least one transaction whose total equals the required fee">
            <i class="bi bi-send me-2"></i>Final Submit Registration
          </button>
        </div>
      <?php endif; ?>
    </div><!-- /Step 4 panel -->

    <!-- ── Transactions panel (combined manual + ePayment) ── -->
    <div class="sms-card p-4 mb-4 <?= empty($registration['unit_id']) ? 'opacity-50' : '' ?>">
      <div class="sms-step-head bg-primary-subtle text-primary-emphasis rounded-3 px-3 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-receipt me-2"></i>Transactions</h6>
        <div class="small">
          <span class="me-3">Submitted: <strong id="submittedAmt">₹<?= number_format($submittedAmt, 2) ?></strong></span>
          <span>Approved: <strong class="text-success" id="approvedAmt">₹<?= number_format($approvedAmt, 2) ?></strong></span>
        </div>
      </div>
      <!-- Desktop table (md+) -->
      <div class="table-responsive d-none d-md-block">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Date</th>
              <th>Mode</th>
              <th>Transaction No.</th>
              <th class="text-end">Amount</th>
              <th>Proof</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="paymentRows">
            <?php if (empty($payments)): ?>
              <tr id="emptyPayments"><td colspan="7" class="text-muted text-center py-3">No transactions added yet.</td></tr>
            <?php else: foreach ($payments as $p):
              $isEpay = ($p['payment_method'] ?? 'manual') === 'epayment';
              $txnNo  = $isEpay ? ($p['razorpay_payment_id'] ?: $p['razorpay_order_id'] ?: $p['transaction_number']) : $p['transaction_number'];
            ?>
              <tr data-id="<?= (int)$p['id'] ?>" data-amount="<?= (float)$p['amount'] ?>" data-method="<?= e($p['payment_method'] ?? 'manual') ?>">
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
                    <a href="<?= e($p['proof_file']) ?>" target="_blank" rel="noopener"><i class="bi bi-eye me-1"></i>View</a>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td><?= statusBadge($p['status']) ?></td>
                <td class="text-end">
                  <?php if (!$isEpay && $p['status'] !== 'approved'): ?>
                    <button class="btn btn-sm btn-outline-danger" type="button" onclick="removePayment(<?= (int)$p['id'] ?>)"><i class="bi bi-trash"></i></button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
          <tfoot>
            <tr class="small table-light">
              <th colspan="6" class="text-end">
                Required: <strong>₹<?= number_format($total, 2) ?></strong>
              </th>
              <th></th>
            </tr>
          </tfoot>
        </table>
      </div>
      <!-- Mobile cards (<md). renderPaymentRows() keeps both containers in sync. -->
      <div class="d-md-none" id="paymentCards">
        <?php if (empty($payments)): ?>
          <div class="text-muted text-center small py-3" id="emptyPaymentsCard">No transactions added yet.</div>
        <?php else: foreach ($payments as $p):
          $isEpay = ($p['payment_method'] ?? 'manual') === 'epayment';
          $txnNo  = $isEpay ? ($p['razorpay_payment_id'] ?: $p['razorpay_order_id'] ?: $p['transaction_number']) : $p['transaction_number'];
        ?>
          <div class="border rounded-3 p-3 mb-2 small" data-id="<?= (int)$p['id'] ?>" data-method="<?= e($p['payment_method'] ?? 'manual') ?>">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
              <div>
                <div class="fw-bold">₹<?= number_format((float)$p['amount'], 2) ?></div>
                <div class="text-muted"><?= formatDate($p['transaction_date']) ?></div>
              </div>
              <?= statusBadge($p['status']) ?>
            </div>
            <div class="text-muted small mb-1">
              <?php if ($isEpay): ?>
                <i class="bi bi-credit-card me-1"></i>ePayment
              <?php else: ?>
                <i class="bi bi-bank me-1"></i>Manual
              <?php endif; ?>
              · <code><?= e($txnNo) ?></code>
            </div>
            <?php if (!empty($p['proof_file'])): ?>
              <a href="<?= e($p['proof_file']) ?>" target="_blank" rel="noopener" class="small">
                <i class="bi bi-eye me-1"></i>View Proof
              </a>
            <?php endif; ?>
            <?php if (!$isEpay && $p['status'] !== 'approved'): ?>
              <div class="text-end mt-2">
                <button class="btn btn-sm btn-outline-danger" type="button" onclick="removePayment(<?= (int)$p['id'] ?>)">
                  <i class="bi bi-trash me-1"></i>Remove
                </button>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <!-- Right column: event summary + documents -->
  <div class="col-lg-4">
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-calendar-event me-2"></i>Event Info</h6>
      <div class="mb-2"><strong><?= e($event['name']) ?></strong></div>
      <div class="text-muted small mb-1"><i class="bi bi-geo-alt me-1"></i><?= e($event['location']) ?></div>
      <div class="text-muted small mb-1"><i class="bi bi-calendar3 me-1"></i><?= formatDate($event['event_date_from']) ?> – <?= formatDate($event['event_date_to']) ?></div>
      <div class="text-muted small mb-2"><i class="bi bi-credit-card me-1"></i><?= implode(', ', array_map('ucfirst', $paymentModes)) ?></div>

      <div class="border-top pt-2 mt-2 small">
        <div class="text-muted text-uppercase mb-1" style="font-size:.7rem;letter-spacing:.05em">Event SPOC</div>
        <div class="fw-medium"><i class="bi bi-person me-1"></i><?= e($event['contact_name'] ?? '—') ?>
          <?php if (!empty($event['contact_designation'])): ?>
            <span class="text-muted"> · <?= e($event['contact_designation']) ?></span>
          <?php endif; ?>
        </div>
        <?php if (!empty($event['contact_mobile'])): ?>
          <div class="text-muted">
            <i class="bi bi-phone me-1"></i><a href="tel:<?= e($event['contact_mobile']) ?>" class="text-reset text-decoration-none"><?= e($event['contact_mobile']) ?></a>
          </div>
        <?php endif; ?>
        <?php if (!empty($event['contact_email'])): ?>
          <div class="text-muted text-break">
            <i class="bi bi-envelope me-1"></i><a href="mailto:<?= e($event['contact_email']) ?>" class="text-reset text-decoration-none"><?= e($event['contact_email']) ?></a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($documents)): ?>
    <div class="sms-card p-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-file-earmark-text me-2"></i>Documents</h6>
      <p class="small text-muted mb-3">Forms and notices published by the event organiser.</p>
      <ul class="list-unstyled mb-0">
        <?php foreach ($documents as $d): ?>
          <li class="d-flex align-items-start gap-2 py-2 <?= !$d === end($documents) ? '' : '' ?>" style="border-bottom:1px dashed #e2e8f0">
            <i class="bi bi-file-earmark-pdf text-primary fs-5 mt-1"></i>
            <div class="flex-grow-1">
              <div class="fw-semibold small"><?= e($d['name']) ?></div>
              <?php if (!empty($d['purpose'])): ?>
                <div class="text-muted small"><?= e($d['purpose']) ?></div>
              <?php endif; ?>
            </div>
            <?php if (!empty($d['file'])): ?>
              <a href="<?= e($d['file']) ?>" target="_blank" rel="noopener"
                 class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye me-1"></i>View
              </a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
const CSRF = '<?= e($csrfToken) ?>';
// Hashed event id used in every fetch URL — keeps the integer id out of the address bar.
const EV_ID = <?= json_encode(hid_event((int)$event['id'])) ?>;
const SAVE_URL   = '/athlete/events/' + EV_ID + '/register/save';
const SUBMIT_URL = '/athlete/events/' + EV_ID + '/register/submit';
const NOC_REQ = '<?= e($nocReq) ?>';

<?php
  // Build the full event-sports catalog. We do NOT pre-filter here:
  // the Sport and Event-Category dropdowns must show every option the
  // organiser configured. Eligibility filtering happens only at the
  // last step (the Event dropdown) inside isEventEligible() in JS.
  $athleteRawGender = strtolower((string)($athlete['gender'] ?? ''));
  $athleteGenderNorm = match ($athleteRawGender) {
      'men'   => 'male',
      'women' => 'female',
      default => $athleteRawGender,
  };
  $athleteAge   = !empty($athlete['date_of_birth']) ? \ageFromDob($athlete['date_of_birth']) : null;
  $eligibleAge  = \Models\Athlete::eligibleAgeCategories($athleteAge);
  $canFilterGen = ($athleteGenderNorm === 'male' || $athleteGenderNorm === 'female');

  $rows = [];
  foreach (($event['sports'] ?? []) as $r) {
      $rgRaw = strtolower((string)($r['sport_event_gender'] ?? ''));
      $rg    = match ($rgRaw) { 'men' => 'male', 'women' => 'female', default => $rgRaw };
      $rows[] = [
        'id'           => (int)$r['id'],
        'sport_id'     => (int)$r['sport_id'],
        'sport_name'   => (string)($r['sport_name'] ?? ''),
        'category'     => (string)($r['sport_event_category'] ?? ($r['category'] ?? '— Uncategorised —')),
        'event_name'   => (string)($r['sport_event_name'] ?? ($r['category'] ?? '')),
        'event_code'   => (string)($r['event_code'] ?? ''),
        'age_category' => (string)($r['sport_event_age_category'] ?? ''),
        'gender'       => $rg,
        'fee'          => (float)$r['entry_fee'],
      ];
  }
?>
const SPORT_EVENTS         = <?= json_encode($rows) ?>;
const ATHLETE_GENDER       = <?= json_encode($athleteGenderNorm) ?>;
const ATHLETE_AGE          = <?= json_encode($athleteAge) ?>;
const ELIGIBLE_AGE_CATS    = <?= json_encode($eligibleAge) ?>;
const NORM_ATHLETE_GENDER  = ATHLETE_GENDER;
const CAN_GENDER_FILTER    = <?= $canFilterGen ? 'true' : 'false' ?>;

function normGender(g) {
  g = String(g || '').trim().toLowerCase();
  if (g === 'men')   return 'male';
  if (g === 'women') return 'female';
  return g;
}

/**
 * Eligibility check applied ONLY when populating the Event dropdown.
 * Sport and Category lists are always full. Age-category eligibility
 * has been disabled — only the gender rule is enforced here.
 */
function isEventEligible(row) {
  const rg = normGender(row.gender);
  if (CAN_GENDER_FILTER && rg && rg !== 'mixed' && rg !== NORM_ATHLETE_GENDER) return false;
  return true;
}
// Pre-existing selections from a saved draft.
let SELECTED_IDS = <?= json_encode(array_values(array_map('intval', $selectedSet))) ?>;

function showToast(msg, type) {
  type = type || 'success';
  const el  = document.getElementById('regToast');
  el.className = 'toast align-items-center border-0 text-bg-' + type;
  document.getElementById('toastMsg').textContent = msg;
  if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
    bootstrap.Toast.getOrCreateInstance(el, { delay: 3500 }).show();
  } else { alert(msg); }
}

/* ── Sport Event Picker (chained Sport → Category → Event) ── */

function uniq(arr) { return [...new Set(arr)]; }
function byId(id)  { return SPORT_EVENTS.find(r => r.id === id); }

// All event-sports rows are eligible for the Sport / Category dropdowns;
// the Event dropdown narrows the rows by gender + eligible age category.
function eligiblePool() { return SPORT_EVENTS; }

function rebuildSportDropdown() {
  const sel = document.getElementById('f_sport');
  if (!sel) return;
  const sports = uniq(SPORT_EVENTS.map(r => r.sport_name).filter(Boolean)).sort();
  sel.innerHTML = sports.length
    ? sports.map(s => `<option value="${s}">${s}</option>`).join('')
    : '<option value="">— No sports configured for this event —</option>';
  if (sports.length) sel.value = sports[0];

  const note = document.getElementById('pickerNote');
  if (note) {
    if (CAN_GENDER_FILTER) {
      const genderLabel = NORM_ATHLETE_GENDER === 'male' ? 'Men' : 'Women';
      note.innerHTML = `<i class="bi bi-funnel me-1"></i>The Event list is filtered to <strong>${genderLabel}</strong> or Mixed events.`;
    } else {
      note.textContent = '';
    }
  }
  onSportChange();
}

function onSportChange() {
  const sport = document.getElementById('f_sport').value;
  const catSel = document.getElementById('f_category');
  const cats = uniq(
    SPORT_EVENTS.filter(r => r.sport_name === sport).map(r => r.category).filter(Boolean)
  ).sort();
  catSel.innerHTML = cats.length
    ? cats.map(c => `<option value="${c}">${c}</option>`).join('')
    : '<option value="">— No categories —</option>';
  if (cats.length) catSel.value = cats[0];
  onCategoryChange();
}

function onCategoryChange() {
  const sport = document.getElementById('f_sport').value;
  const cat   = document.getElementById('f_category').value;
  const evSel = document.getElementById('f_event');
  // Step 1: narrow to chosen sport + category. Step 2: apply gender +
  // age eligibility ONLY to this Event dropdown.
  const inThisCategory = SPORT_EVENTS.filter(r => r.sport_name === sport && r.category === cat);
  const list = inThisCategory.filter(isEventEligible);

  evSel.innerHTML = list.length
    ? list.map(r => {
        const bits = [r.event_name, r.age_category, r.gender]
          .filter(Boolean).join(' · ');
        return `<option value="${r.id}">${bits} — ₹${r.fee.toFixed(2)}</option>`;
      }).join('')
    : '<option value="">— No eligible events for your profile —</option>';

  const evNote = document.getElementById('eventNote');
  if (evNote) {
    const hidden = inThisCategory.length - list.length;
    evNote.innerHTML = (inThisCategory.length && hidden > 0)
      ? `<i class="bi bi-info-circle me-1"></i>${hidden} event(s) in this category are hidden (different gender).`
      : '';
  }
}

function onUnitChange() {
  const v = document.getElementById('r_unit').value;
  const wrap = document.getElementById('r_unit_other_wrap');
  if (wrap) wrap.style.display = v === 'OTHER' ? '' : 'none';
  updateStep1Button();
}

/* ── Gate Step 1's "Save & Continue" until the mandatory bits are in. */
const STEP1_MANDATORY_NOC = NOC_REQ === 'mandatory';
const HAS_EXISTING_NOC = <?= !empty($registration['noc_letter']) ? 'true' : 'false' ?>;
function updateStep1Button() {
  const btn = document.getElementById('step1SaveBtn');
  if (!btn || REG_LOCKED) return;
  const unitSel = document.getElementById('r_unit');
  const unitVal = unitSel ? unitSel.value : '';
  let unitOk = !!unitVal;
  if (unitVal === 'OTHER') {
    const otherEl = document.getElementById('r_unit_other');
    unitOk = otherEl && otherEl.value.trim() !== '';
  }
  const hasSport = SELECTED_IDS.length > 0;
  let nocOk = true;
  if (STEP1_MANDATORY_NOC) {
    const nocEl = document.getElementById('r_noc');
    nocOk = HAS_EXISTING_NOC || (nocEl && nocEl.files && nocEl.files[0]);
  }
  const ok = unitOk && hasSport && nocOk;
  btn.disabled = !ok;
  btn.title = ok ? '' : (
    !unitOk    ? 'Select a Unit (or Other + name)' :
    !hasSport  ? 'Add at least one Sport Event'    :
    !nocOk     ? 'Upload the NOC / Undertaking (mandatory for this event)' : ''
  );
}

function addSelectedEvent() {
  const id = parseInt(document.getElementById('f_event').value, 10);
  if (!id) { showToast('Pick an event from the dropdown first.', 'warning'); return; }
  if (SELECTED_IDS.includes(id)) {
    showToast('This event is already in your selection.', 'warning');
    return;
  }
  SELECTED_IDS.push(id);
  renderSelectedRows();
}

function removeSelected(id) {
  SELECTED_IDS = SELECTED_IDS.filter(x => x !== id);
  renderSelectedRows();
}

function esc(s) {
  return (s == null ? '' : String(s)).replace(/[&<>"']/g, c =>
    ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function renderSelectedRows() {
  const body  = document.getElementById('selectedRows');
  const cards = document.getElementById('selectedCards');
  if (!body) return;
  if (!SELECTED_IDS.length) {
    body.innerHTML  = '<tr id="emptySelected"><td colspan="6" class="text-muted text-center py-3">No events selected yet.</td></tr>';
    if (cards) cards.innerHTML = '<div class="text-muted text-center small py-3" id="emptySelectedCard">No events selected yet.</div>';
  } else {
    body.innerHTML = SELECTED_IDS.map(id => {
      const r = byId(id);
      if (!r) return '';
      return `<tr data-id="${r.id}">
        <td>${esc(r.sport_name)}</td>
        <td><code>${esc(r.event_code)}</code></td>
        <td>${esc(r.category)} <span class="text-muted">${esc(r.event_name)}</span></td>
        <td>${esc(r.age_category)} <span class="text-muted small">${esc(r.gender)}</span></td>
        <td class="text-end">₹${r.fee.toFixed(2)}</td>
        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSelected(${r.id})"><i class="bi bi-trash"></i></button></td>
      </tr>`;
    }).join('');
    if (cards) {
      cards.innerHTML = SELECTED_IDS.map(id => {
        const r = byId(id);
        if (!r) return '';
        return `<div class="border rounded-3 p-3 mb-2 small" data-id="${r.id}">
          <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
            <div class="fw-semibold text-break">${esc(r.event_name)}</div>
            <div class="fw-bold text-nowrap">₹${r.fee.toFixed(2)}</div>
          </div>
          <div class="text-muted">
            <i class="bi bi-trophy me-1"></i>${esc(r.sport_name)}
            ${r.event_code ? ` · <code>${esc(r.event_code)}</code>` : ''}
          </div>
          <div class="text-muted small mt-1">
            ${esc(r.category || '')}${r.age_category ? ' · ' + esc(r.age_category) : ''}${r.gender ? ' · ' + esc(r.gender) : ''}
          </div>
          <div class="text-end mt-2">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSelected(${r.id})">
              <i class="bi bi-trash me-1"></i>Remove
            </button>
          </div>
        </div>`;
      }).join('');
    }
  }
  recomputeTotal();
  updateStep1Button();
}

function recomputeTotal() {
  let sum = 0;
  SELECTED_IDS.forEach(id => { const r = byId(id); if (r) sum += r.fee; });
  const text = sum.toFixed(2);
  ['totalAmount','totalAmount2','totalAmountInline','totalAmountTbl','totalAmountTblMobile']
    .forEach(eid => { const el = document.getElementById(eid); if (el) el.textContent = text; });
  const ta = document.getElementById('t_amount'); if (ta) ta.value = text;
}

/* ── Spinner helpers (reused everywhere on this page) ── */
function startBtnSpinner(btn) {
  if (!btn) return null;
  if (btn.dataset.busy === '1') return null;
  btn.dataset.busy = '1';
  btn.dataset.origHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Working…';
  return btn;
}
function stopBtnSpinner(btn) {
  if (!btn || btn.dataset.busy !== '1') return;
  btn.disabled = false;
  btn.innerHTML = btn.dataset.origHtml || btn.innerHTML;
  btn.dataset.busy = '';
}

/* ── Sports Items / Weapons Sharing Details ── */
const RSI_BY_SPORT = <?= json_encode($eventItemsBySport ?? new \stdClass()) ?>;
const RSI_SAVE_URL = '/athlete/events/' + EV_ID + '/register/items/save';
const RSI_DEL_URL  = '/athlete/events/' + EV_ID + '/register/items/delete';

function rsiEsc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function onRsiSportChange() {
  const sportId = document.getElementById('rsi_sport').value;
  const sel = document.getElementById('rsi_item');
  if (!sportId || !RSI_BY_SPORT[sportId]) {
    sel.innerHTML = '<option value="">— pick a sport first —</option>';
    return;
  }
  const items = RSI_BY_SPORT[sportId].items || [];
  sel.innerHTML = items.length
    ? '<option value="">Select an item…</option>' + items.map(i => `<option value="${i.id}">${rsiEsc(i.name)}</option>`).join('')
    : '<option value="">No items configured for this sport</option>';
}

function rsiClearForm() {
  document.getElementById('rsi_id').value     = '';
  document.getElementById('rsi_sport').value  = '';
  document.getElementById('rsi_item').innerHTML = '<option value="">— pick a sport first —</option>';
  document.getElementById('rsi_model').value  = '';
  document.getElementById('rsi_serial').value = '';
  document.getElementById('rsiBtnLabel').textContent = 'Add details';
}

function renderRsi(list) {
  const body  = document.getElementById('rsiTbody');
  const cards = document.getElementById('rsiCards');
  if (!body) return;
  if (!list || !list.length) {
    body.innerHTML = '<tr id="rsiEmpty"><td colspan="5" class="text-muted text-center py-3">No items declared yet.</td></tr>';
    if (cards) cards.innerHTML = '<div class="text-muted text-center small py-3" id="rsiEmptyCard">No items declared yet.</div>';
    return;
  }
  body.innerHTML = list.map(r => `
    <tr data-id="${r.id}" data-sport-id="${r.sport_id}" data-item-id="${r.sport_item_id}">
      <td class="text-muted small">${rsiEsc(r.sport_name)}</td>
      <td class="fw-medium">${rsiEsc(r.item_name)}</td>
      <td>${rsiEsc(r.model || '—')}</td>
      <td>${rsiEsc(r.serial_number || '—')}</td>
      <td class="text-end">
        <button type="button" class="btn btn-sm btn-outline-primary" onclick='editRsi(${JSON.stringify(r).replace(/'/g, "&#39;")})'><i class="bi bi-pencil"></i></button>
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteRsi(${r.id})"><i class="bi bi-trash"></i></button>
      </td>
    </tr>`).join('');
  if (cards) {
    cards.innerHTML = list.map(r => `
      <div class="border rounded-3 p-3 mb-2 small" data-id="${r.id}">
        <div class="fw-semibold text-break">${rsiEsc(r.item_name)}</div>
        <div class="text-muted"><i class="bi bi-trophy me-1"></i>${rsiEsc(r.sport_name)}</div>
        <div class="row g-1 mt-1">
          <div class="col-6">
            <div class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.04em">Model</div>
            <div class="text-break">${rsiEsc(r.model || '—')}</div>
          </div>
          <div class="col-6">
            <div class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.04em">Serial #</div>
            <div class="text-break">${rsiEsc(r.serial_number || '—')}</div>
          </div>
        </div>
        <div class="d-flex gap-2 justify-content-end mt-2">
          <button type="button" class="btn btn-sm btn-outline-primary" onclick='editRsi(${JSON.stringify(r).replace(/'/g, "&#39;")})'><i class="bi bi-pencil"></i></button>
          <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteRsi(${r.id})"><i class="bi bi-trash"></i></button>
        </div>
      </div>`).join('');
  }
}

function editRsi(row) {
  document.getElementById('rsi_id').value     = row.id;
  document.getElementById('rsi_sport').value  = row.sport_id;
  onRsiSportChange();
  document.getElementById('rsi_item').value   = row.sport_item_id;
  document.getElementById('rsi_model').value  = row.model || '';
  document.getElementById('rsi_serial').value = row.serial_number || '';
  document.getElementById('rsiBtnLabel').textContent = 'Update details';
  document.getElementById('rsi_sport').scrollIntoView({behavior: 'smooth', block: 'center'});
}

async function addRsi() {
  const sportItemId = document.getElementById('rsi_item').value;
  if (!sportItemId) { showToast('Pick an item first.', 'warning'); return; }
  const fd = new FormData();
  fd.append('_token',    CSRF);
  fd.append('id',            document.getElementById('rsi_id').value || '0');
  fd.append('sport_item_id', sportItemId);
  fd.append('model',         document.getElementById('rsi_model').value);
  fd.append('serial_number', document.getElementById('rsi_serial').value);
  try {
    const res = await fetch(RSI_SAVE_URL, { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) { showToast(data.message || 'Save failed.', 'error'); return; }
    renderRsi(data.list || []);
    rsiClearForm();
    showToast(data.message || 'Saved.', 'success');
  } catch (e) {
    showToast('Network error: ' + e.message, 'error');
  }
}

async function deleteRsi(id) {
  if (!confirm('Remove this item?')) return;
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('id', id);
  try {
    const res = await fetch(RSI_DEL_URL, { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) { showToast(data.message || 'Remove failed.', 'error'); return; }
    renderRsi(data.list || []);
  } catch (e) {
    showToast('Network error: ' + e.message, 'error');
  }
}

async function saveStep1() {
  const unitSel = document.getElementById('r_unit');
  if (!unitSel) { showToast('No units configured for this event yet.', 'warning'); return; }
  const unitId = unitSel.value;
  if (!unitId) { showToast('Please select a Unit / Club / Institution.', 'warning'); return; }

  let unitOther = '';
  if (unitId === 'OTHER') {
    unitOther = (document.getElementById('r_unit_other').value || '').trim();
    if (!unitOther) { showToast('Type the Unit / Club / Institution name.', 'warning'); return; }
  }
  const unitRegNo = (document.getElementById('r_unit_reg_no')?.value || '').trim();

  if (!SELECTED_IDS.length) { showToast('Add at least one sport event to your selection.', 'warning'); return; }

  const noc = document.getElementById('r_noc');
  if (NOC_REQ === 'mandatory' && noc && !noc.files.length && !document.querySelector('a[href][target="_blank"]')) {
    showToast('NOC / Undertaking is mandatory for this event.', 'warning'); return;
  }

  const btn = document.querySelector('button[onclick="saveStep1()"]');
  startBtnSpinner(btn);

  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('unit_id', unitId);
  if (unitOther) fd.append('unit_name_other', unitOther);
  if (unitRegNo) fd.append('unit_reg_no',     unitRegNo);
  SELECTED_IDS.forEach(id => fd.append('event_sport_ids[]', String(id)));
  if (noc && noc.files[0]) fd.append('noc_letter', noc.files[0]);

  let res, data;
  try { res = await fetch(SAVE_URL, { method: 'POST', body: fd }); }
  catch (e) { stopBtnSpinner(btn); showToast('Network error: ' + e.message, 'danger'); return; }
  try { data = await res.json(); } catch(_) { data = { success:false, message:'Server error (' + res.status + ').' }; }

  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) {
    // Reveal Step 2.
    location.reload();
  } else {
    stopBtnSpinner(btn);
  }
}

const PAYMODE_URL    = '/athlete/events/' + EV_ID + '/register/payment-mode';
const PAY_ADD_URL    = '/athlete/events/' + EV_ID + '/register/payment';
const PAY_REMOVE_URL = '/athlete/events/' + EV_ID + '/register/payment-remove';

async function onPaymentModeChange() {
  const mode = document.querySelector('input[name="payment_mode_choice"]:checked')?.value;
  document.getElementById('manualBlock').style.display = (mode === 'manual') ? 'block' : 'none';
  document.getElementById('onlineBlock').style.display = (mode === 'online') ? 'block' : 'none';
  if (!mode) return;
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('payment_mode', mode);
  const res = await fetch(PAYMODE_URL, { method: 'POST', body: fd });
  let data; try { data = await res.json(); } catch(_) { data = { success:false, message:'Server error.' }; }
  if (!data.success) showToast(data.message, 'danger');
}

async function addPayment() {
  const date = document.getElementById('t_date').value;
  const num  = document.getElementById('t_num').value.trim();
  const amt  = document.getElementById('t_amount').value;
  const file = document.getElementById('t_proof').files[0];
  if (!date || !num || !amt) { showToast('Date, transaction number and amount are required.', 'warning'); return; }
  if (!file) { showToast('Transaction proof file is mandatory.', 'warning'); return; }

  const btn = document.querySelector('button[onclick="addPayment()"]');
  const progress = document.getElementById('t_addProgress');
  startBtnSpinner(btn);
  if (progress) progress.classList.remove('d-none');

  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('transaction_date',   date);
  fd.append('transaction_number', num);
  fd.append('transaction_amount', amt);
  fd.append('transaction_proof',  file);

  let res, data;
  try { res = await fetch(PAY_ADD_URL, { method:'POST', body: fd }); }
  catch (e) {
    stopBtnSpinner(btn);
    if (progress) progress.classList.add('d-none');
    showToast('Network error: ' + e.message, 'danger'); return;
  }
  try { data = await res.json(); } catch(_) { data = { success:false, message:'Server error (' + res.status + ').' }; }

  stopBtnSpinner(btn);
  if (progress) progress.classList.add('d-none');
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) {
    renderPaymentRows(data.payments || []);
    ['t_date','t_num','t_amount','t_proof'].forEach(eid => {
      const el = document.getElementById(eid); if (el) el.value = '';
    });
  }
}

async function removePayment(id) {
  if (!confirm('Remove this transaction?')) return;
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('payment_id', id);
  const res = await fetch(PAY_REMOVE_URL, { method:'POST', body: fd });
  let data; try { data = await res.json(); } catch(_) { data = { success:false, message:'Server error.' }; }
  showToast(data.message, data.success ? 'success' : 'danger');
  if (data.success) renderPaymentRows(data.payments || []);
}

function renderPaymentRows(list) {
  const body  = document.getElementById('paymentRows');
  const cards = document.getElementById('paymentCards');
  if (!body) return;
  const submitEl = document.getElementById('submittedAmt');
  const approveEl = document.getElementById('approvedAmt');
  let submitted = 0, approved = 0;
  if (!list.length) {
    body.innerHTML = '<tr id="emptyPayments"><td colspan="7" class="text-muted text-center py-3">No transactions added yet.</td></tr>';
    if (cards) cards.innerHTML = '<div class="text-muted text-center small py-3" id="emptyPaymentsCard">No transactions added yet.</div>';
  } else {
    const esc = s => (s == null ? '' : String(s)).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const badge = s => s === 'approved' ? '<span class="badge bg-success">Approved</span>'
                    : s === 'rejected' ? '<span class="badge bg-danger">Rejected</span>'
                    : '<span class="badge bg-warning text-dark">Pending</span>';
    const modeBadge = m => m === 'epayment'
      ? '<span class="badge bg-info-subtle text-info"><i class="bi bi-credit-card me-1"></i>ePayment</span>'
      : '<span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-bank me-1"></i>Manual</span>';
    body.innerHTML = list.map(p => {
      const a = parseFloat(p.amount); submitted += a; if (p.status === 'approved') approved += a;
      const isEpay = (p.payment_method || 'manual') === 'epayment';
      const txnNo  = isEpay ? (p.razorpay_payment_id || p.razorpay_order_id || p.transaction_number) : p.transaction_number;
      const proof  = p.proof_file ? `<a href="${esc(p.proof_file)}" target="_blank"><i class="bi bi-eye me-1"></i>View</a>` : '—';
      const del    = (!isEpay && p.status !== 'approved') ? `<button class="btn btn-sm btn-outline-danger" type="button" onclick="removePayment(${p.id})"><i class="bi bi-trash"></i></button>` : '';
      return `<tr data-id="${p.id}" data-method="${esc(p.payment_method || 'manual')}">
        <td class="small">${esc(p.transaction_date)}</td>
        <td>${modeBadge(p.payment_method || 'manual')}</td>
        <td><code class="small">${esc(txnNo)}</code></td>
        <td class="text-end">₹${a.toFixed(2)}</td>
        <td>${proof}</td>
        <td>${badge(p.status)}</td>
        <td class="text-end">${del}</td>
      </tr>`;
    }).join('');
    if (cards) {
      cards.innerHTML = list.map(p => {
        const a = parseFloat(p.amount);
        const isEpay = (p.payment_method || 'manual') === 'epayment';
        const txnNo  = isEpay ? (p.razorpay_payment_id || p.razorpay_order_id || p.transaction_number) : p.transaction_number;
        const proof  = p.proof_file ? `<a href="${esc(p.proof_file)}" target="_blank" class="small"><i class="bi bi-eye me-1"></i>View Proof</a>` : '';
        const del    = (!isEpay && p.status !== 'approved')
          ? `<div class="text-end mt-2"><button class="btn btn-sm btn-outline-danger" type="button" onclick="removePayment(${p.id})"><i class="bi bi-trash me-1"></i>Remove</button></div>`
          : '';
        const modeIcon = isEpay
          ? '<i class="bi bi-credit-card me-1"></i>ePayment'
          : '<i class="bi bi-bank me-1"></i>Manual';
        return `<div class="border rounded-3 p-3 mb-2 small" data-id="${p.id}" data-method="${esc(p.payment_method || 'manual')}">
          <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
            <div>
              <div class="fw-bold">₹${a.toFixed(2)}</div>
              <div class="text-muted">${esc(p.transaction_date)}</div>
            </div>
            ${badge(p.status)}
          </div>
          <div class="text-muted small mb-1">${modeIcon} · <code>${esc(txnNo)}</code></div>
          ${proof}
          ${del}
        </div>`;
      }).join('');
    }
  }
  if (submitEl)  submitEl.textContent  = '₹' + submitted.toFixed(2);
  if (approveEl) approveEl.textContent = '₹' + approved.toFixed(2);
  updateFinalSubmitButton(submitted, list.length);
}

const REQUIRED_TOTAL = <?= json_encode((float)$total) ?>;
function updateFinalSubmitButton(submittedAmt, txCount) {
  const btn = document.getElementById('finalSubmitBtn');
  if (!btn) return;
  // The amount totals come straight from the on-screen tfoot when we
  // weren't passed explicit numbers (e.g. on initial page load).
  if (typeof submittedAmt === 'undefined') {
    submittedAmt = parseFloat((document.getElementById('submittedAmt')?.textContent || '').replace(/[^\d.]/g, '')) || 0;
    txCount = document.querySelectorAll('#paymentRows tr[data-id]').length;
  }
  const hint = document.getElementById('amountMatchHint');
  const eps = 0.005; // tolerate rounding
  const ok = txCount > 0 && Math.abs(submittedAmt - REQUIRED_TOTAL) < eps;
  btn.disabled = !ok;
  if (hint) {
    if (!txCount) {
      hint.textContent = ' · add at least one transaction';
      hint.className   = 'ms-2 text-warning';
    } else if (submittedAmt + eps < REQUIRED_TOTAL) {
      hint.textContent = ' · short by ₹' + (REQUIRED_TOTAL - submittedAmt).toFixed(2);
      hint.className   = 'ms-2 text-warning';
    } else if (submittedAmt - eps > REQUIRED_TOTAL) {
      hint.textContent = ' · over by ₹' + (submittedAmt - REQUIRED_TOTAL).toFixed(2);
      hint.className   = 'ms-2 text-danger';
    } else {
      hint.innerHTML = ' · <span class="text-success"><i class="bi bi-check2"></i> matches</span>';
      hint.className = 'ms-2';
    }
  }
  btn.title = ok ? '' : 'The total of your transactions must equal the required fee before you can do Final Submit.';
}

async function finalSubmit() {
  if (!confirm('Submit this registration to the event administrator? You can still add more transactions afterwards but the application moves to review.')) return;
  const fd = new FormData();
  fd.append('_token', CSRF);
  // Forward the chosen mode in case the radio onchange didn't fire
  // (e.g. slow networks, restored selection on reload).
  const mode = document.querySelector('input[name="payment_mode_choice"]:checked')?.value;
  if (mode) fd.append('payment_mode', mode);

  let res, data;
  try {
    res  = await fetch(SUBMIT_URL, { method:'POST', body: fd });
  } catch (e) {
    showToast('Network error: ' + e.message, 'danger'); return;
  }
  try { data = await res.json(); }
  catch (_) { data = { success:false, message:'Server returned ' + res.status + ' instead of JSON.' }; }

  showToast(data.message, data.success ? 'success' : 'warning');
  if (data.success) setTimeout(() => { window.location.href = data.redirect || '/athlete/my-registrations'; }, 800);
}

/* ── ePayment (Razorpay Checkout) ──────────────────────────────────────── */
const PAY_CREATE_URL = '/athlete/events/' + EV_ID + '/pay/create-order';
const PAY_VERIFY_URL = '/athlete/events/' + EV_ID + '/pay/verify';

function epayInlineMsg(text, kind) {
  const el = document.getElementById('epayInline');
  if (!el) return;
  if (!text) { el.innerHTML = ''; return; }
  const cls = kind === 'success' ? 'text-success' : kind === 'danger' ? 'text-danger' : 'text-muted';
  el.innerHTML = '<span class="' + cls + '"><i class="bi bi-info-circle me-1"></i>' + text + '</span>';
}

async function payOnline() {
  const btn = document.getElementById('payOnlineBtn');
  if (!btn || btn.disabled) return;

  if (typeof Razorpay === 'undefined') {
    epayInlineMsg('Payment gateway is still loading — please try again in a moment.', 'danger');
    return;
  }

  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Preparing…';
  epayInlineMsg('Creating order…');

  let order;
  try {
    const fd = new FormData();
    fd.append('_token', CSRF);
    const res  = await fetch(PAY_CREATE_URL, { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) {
      epayInlineMsg(data.message || 'Could not start the payment.', 'danger');
      btn.disabled = false; btn.innerHTML = orig; return;
    }
    order = data;
  } catch (e) {
    epayInlineMsg('Network error: ' + e.message, 'danger');
    btn.disabled = false; btn.innerHTML = orig; return;
  }

  const opts = {
    key:      order.key_id,
    order_id: order.order_id,
    amount:   order.amount,
    currency: order.currency,
    name:     'SportsMIS',
    description: 'Event registration fee',
    prefill:  order.prefill || {},
    theme:    { color: '#0b1f3a' },
    handler:  async function (resp) {
      epayInlineMsg('Verifying payment…');
      try {
        const fd = new FormData();
        fd.append('_token', CSRF);
        fd.append('razorpay_order_id',   resp.razorpay_order_id);
        fd.append('razorpay_payment_id', resp.razorpay_payment_id);
        fd.append('razorpay_signature',  resp.razorpay_signature);
        const r = await fetch(PAY_VERIFY_URL, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
          epayInlineMsg('Payment successful! Reloading…', 'success');
          showToast('Payment received and verified.', 'success');
          setTimeout(() => window.location.reload(), 900);
        } else {
          epayInlineMsg(d.message || 'Verification failed.', 'danger');
          showToast(d.message || 'Verification failed.', 'danger');
          btn.disabled = false; btn.innerHTML = orig;
        }
      } catch (e) {
        epayInlineMsg('Verification network error: ' + e.message, 'danger');
        btn.disabled = false; btn.innerHTML = orig;
      }
    },
    modal: {
      ondismiss: function () {
        epayInlineMsg('Payment cancelled. The order stays on your record as not-paid until you retry.', 'danger');
        btn.disabled = false; btn.innerHTML = orig;
      },
    },
  };

  try {
    const rzp = new Razorpay(opts);
    rzp.on('payment.failed', function (resp) {
      const d = resp.error || {};
      epayInlineMsg('Payment failed: ' + (d.description || d.reason || 'unknown error') + ' (code ' + (d.code || '?') + ').', 'danger');
      btn.disabled = false; btn.innerHTML = orig;
    });
    rzp.open();
  } catch (e) {
    epayInlineMsg('Could not open the payment modal: ' + e.message, 'danger');
    btn.disabled = false; btn.innerHTML = orig;
  }
}

const REG_LOCKED = <?= $regLocked ? 'true' : 'false' ?>;

document.addEventListener('DOMContentLoaded', () => {
  rebuildSportDropdown();
  renderSelectedRows();
  updateStep1Button();
  updateFinalSubmitButton();
  if (REG_LOCKED) lockStep1();
});

function lockStep1() {
  // Disable all the Step 1 inputs / picker when the registration is under review.
  ['r_unit', 'r_noc', 'f_sport', 'f_category', 'f_event']
    .forEach(id => { const el = document.getElementById(id); if (el) el.disabled = true; });
  document.querySelectorAll('#selectedRows button').forEach(b => b.disabled = true);
  // Picker Add button is the only inline button in the picker row.
  document.querySelectorAll('button[onclick="addSelectedEvent()"]').forEach(b => b.disabled = true);
}
</script>
