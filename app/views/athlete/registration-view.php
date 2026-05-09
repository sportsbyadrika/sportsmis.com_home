<?php
$pageTitle = 'Registration — ' . $event['name'];
$payments    = $payments ?? [];
$payTotals   = $pay_totals ?? ['approved'=>0,'pending'=>0,'rejected'=>0,'submitted_amount'=>0,'approved_amount'=>0];
$sportItems  = $sport_items ?? [];
$totalFee    = (float)($registration['total_amount'] ?? 0);
$totalPaid   = (float)($payTotals['submitted_amount'] ?? 0);
$totalApprov = (float)($payTotals['approved_amount']  ?? 0);

$payModeLabel = function (?string $method): string {
    return match (strtolower((string)$method)) {
        'epayment' => 'ePayment',
        'manual'   => 'Manual',
        default    => 'Manual',
    };
};
$payModeIcon = function (?string $method): string {
    return strtolower((string)$method) === 'epayment' ? 'bi-credit-card' : 'bi-bank';
};
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <a href="/athlete/my-registrations" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h5 class="mb-0 fw-bold"><i class="bi bi-receipt me-2"></i>Registration Details</h5>
    <?= appStatusBadge($registration['admin_review_status'] ?? null, $registration['submitted_at'] ?? null) ?>
    <?= statusBadge($registration['payment_status'] ?? 'pending') ?>
  </div>
  <?php
    $isApproved = ($registration['admin_review_status'] ?? '') === 'approved' && !empty($registration['competitor_number']);
  ?>
  <?php if ($isApproved): ?>
    <a href="/athlete/registrations/<?= e(hid_reg((int)$registration['id'])) ?>/card" target="_blank"
       class="btn btn-success">
      <i class="bi bi-card-heading me-2"></i>Download Competitor Card #<?= (int)$registration['competitor_number'] ?>
    </a>
  <?php elseif (\Models\EventRegistration::isEditable($registration)): ?>
    <a href="/athlete/events/<?= e(hid_event((int)$event['id'])) ?>/register" class="btn btn-primary">
      <i class="bi bi-pencil me-2"></i>Edit Registration
    </a>
  <?php else: ?>
    <button type="button" class="btn btn-outline-secondary" disabled
            title="Locked — registration is under review">
      <i class="bi bi-lock me-2"></i>Locked
    </button>
  <?php endif; ?>
</div>

<!-- ─ Event Snapshot ─ -->
<div class="sms-card p-4 mb-4">
  <div class="d-flex align-items-start gap-3 flex-wrap">
    <?php if (!empty($event['logo'])): ?>
      <img src="<?= e($event['logo']) ?>" alt="" width="64" height="64"
           class="rounded-3 flex-shrink-0" style="object-fit:cover;border:1px solid #e2e8f0;background:#fff">
    <?php else: ?>
      <div class="sms-event-icon sms-event-icon-lg flex-shrink-0"><i class="bi bi-trophy"></i></div>
    <?php endif; ?>
    <div class="flex-grow-1 min-w-0">
      <h5 class="fw-bold mb-1 text-break"><?= e($event['name']) ?></h5>
      <div class="text-muted small mb-1 text-break"><i class="bi bi-building me-1"></i><?= e($event['institution_name'] ?? '') ?></div>
      <div class="text-muted small mb-1"><i class="bi bi-geo-alt me-1"></i><?= e($event['location']) ?></div>
      <div class="text-muted small"><i class="bi bi-calendar3 me-1"></i><?= formatDate($event['event_date_from']) ?> – <?= formatDate($event['event_date_to']) ?></div>
    </div>
  </div>
</div>

<!-- ─ Three summary cards: Total Fees, Total Paid, Total Approved ─ -->
<div class="row g-3 mb-4">
  <div class="col-12 col-md-4">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Total Event Fees</div>
      <div class="fs-4 fw-bold">₹<?= number_format($totalFee, 2) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Total Paid</div>
      <div class="fs-4 fw-bold text-warning">₹<?= number_format($totalPaid, 2) ?></div>
      <div class="small text-muted"><?= (int)($payTotals['total'] ?? count($payments)) ?> txn<?= (int)($payTotals['total'] ?? count($payments)) === 1 ? '' : 's' ?> submitted</div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Total Approved</div>
      <div class="fs-4 fw-bold text-success">₹<?= number_format($totalApprov, 2) ?></div>
      <div class="small text-muted"><?= (int)($payTotals['approved'] ?? 0) ?> approved</div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-8">

    <!-- ─ 1. Registration Details ─ -->
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3">
        <i class="bi bi-1-circle me-2"></i>Registration Details
      </h6>
      <div class="row g-3 small">
        <div class="col-md-6">
          <div class="text-muted">Unit / Club / Institution</div>
          <div class="fw-semibold"><?= !empty($unit) ? e($unit['name']) : '—' ?></div>
          <?php if (!empty($unit['address'])): ?>
            <div class="text-muted small"><?= e($unit['address']) ?></div>
          <?php endif; ?>
        </div>
        <div class="col-md-6">
          <div class="text-muted">NOC / Undertaking</div>
          <?php if (!empty($registration['noc_letter'])): ?>
            <a href="<?= e($registration['noc_letter']) ?>" target="_blank" rel="noopener"><i class="bi bi-eye me-1"></i>View NOC</a>
          <?php else: ?>
            <span class="text-muted">Not uploaded</span>
          <?php endif; ?>
        </div>
        <div class="col-md-6">
          <div class="text-muted">Registration ID</div>
          <div class="fw-semibold">#<?= (int)$registration['id'] ?></div>
        </div>
        <div class="col-md-6">
          <div class="text-muted">Registered On</div>
          <div class="fw-semibold"><?= formatDate($registration['registered_at'] ?? null, 'd M Y H:i') ?></div>
        </div>
        <?php if (!empty($registration['competitor_number'])): ?>
          <div class="col-md-6">
            <div class="text-muted">Competitor Number</div>
            <div class="fw-bold text-success" style="font-size:1.4rem;letter-spacing:1px">
              #<?= str_pad((string)(int)$registration['competitor_number'], 4, '0', STR_PAD_LEFT) ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ─ 2. Selected Sport Events ─ -->
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3">
        <i class="bi bi-2-circle me-2"></i>Selected Sport Events
      </h6>
      <?php if (empty($items)): ?>
        <p class="text-muted small mb-0">No sport events were added to this registration.</p>
      <?php else: ?>
      <!-- Desktop table (md+) -->
      <div class="table-responsive d-none d-md-block">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr><th>Sport</th><th>Event Code</th><th>Event</th><th class="text-end">Fee</th></tr>
          </thead>
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
          <tfoot>
            <tr class="table-light">
              <th colspan="3" class="text-end">Total</th>
              <th class="text-end fw-bold">₹<?= number_format($totalFee, 2) ?></th>
            </tr>
          </tfoot>
        </table>
      </div>
      <!-- Mobile cards (<md) -->
      <div class="d-md-none">
        <?php foreach ($items as $it): ?>
          <div class="border rounded-3 p-3 mb-2 small">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
              <div class="fw-semibold text-break"><?= e($it['sport_event_name'] ?? $it['category'] ?? '') ?></div>
              <div class="fw-bold text-nowrap">₹<?= number_format((float)$it['fee'], 2) ?></div>
            </div>
            <div class="text-muted">
              <i class="bi bi-trophy me-1"></i><?= e($it['sport_name'] ?? '') ?>
              <?php if (!empty($it['event_code'])): ?> · <code><?= e($it['event_code']) ?></code><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2">
          <span class="text-muted small">Total</span>
          <span class="fw-bold">₹<?= number_format($totalFee, 2) ?></span>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- ─ 3. Sports Items / Weapons Sharing Details ─ -->
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3">
        <i class="bi bi-3-circle me-2"></i>Sports Items / Weapons Sharing Details
      </h6>
      <?php if (empty($sportItems)): ?>
        <p class="text-muted small mb-0">No items / weapons declared for this registration.</p>
      <?php else: ?>
      <!-- Desktop table (md+) -->
      <div class="table-responsive d-none d-md-block">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr><th>Sport</th><th>Item / Weapon</th><th>Model</th><th>Serial Number</th></tr>
          </thead>
          <tbody>
            <?php foreach ($sportItems as $r): ?>
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
      <!-- Mobile cards (<md) -->
      <div class="d-md-none">
        <?php foreach ($sportItems as $r): ?>
          <div class="border rounded-3 p-3 mb-2 small">
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
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- ─ 4. Payment Transactions ─ -->
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3">
        <i class="bi bi-4-circle me-2"></i>Payment Transactions
      </h6>
      <?php if (empty($payments)): ?>
        <p class="text-muted small mb-0">No payment transactions recorded yet.</p>
      <?php else: ?>
      <!-- Desktop table (md+) -->
      <div class="table-responsive d-none d-md-block">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Date</th>
              <th>Mode</th>
              <th>Transaction Number</th>
              <th class="text-end">Amount</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $p):
              $when = $p['transaction_date'] ?? $p['created_at'] ?? null;
              $txn  = !empty($p['razorpay_payment_id'])
                        ? $p['razorpay_payment_id']
                        : ($p['transaction_number'] ?? '—');
            ?>
              <tr>
                <td class="small"><?= $when ? formatDate($when, 'd M Y') : '—' ?></td>
                <td><i class="bi <?= $payModeIcon($p['payment_method'] ?? 'manual') ?> me-1"></i><?= e($payModeLabel($p['payment_method'] ?? 'manual')) ?></td>
                <td><code class="small"><?= e($txn) ?></code></td>
                <td class="text-end fw-medium">₹<?= number_format((float)$p['amount'], 2) ?></td>
                <td><?= statusBadge($p['status'] ?? 'pending') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light">
            <tr>
              <th colspan="3" class="text-end">Grand Total (submitted)</th>
              <th class="text-end fw-bold">₹<?= number_format($totalPaid, 2) ?></th>
              <th></th>
            </tr>
            <tr>
              <th colspan="3" class="text-end">Total Approved</th>
              <th class="text-end fw-bold text-success">₹<?= number_format($totalApprov, 2) ?></th>
              <th></th>
            </tr>
          </tfoot>
        </table>
      </div>
      <!-- Mobile cards (<md) -->
      <div class="d-md-none">
        <?php foreach ($payments as $p):
          $when = $p['transaction_date'] ?? $p['created_at'] ?? null;
          $txn  = !empty($p['razorpay_payment_id'])
                    ? $p['razorpay_payment_id']
                    : ($p['transaction_number'] ?? '—');
        ?>
          <div class="border rounded-3 p-3 mb-2 small">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
              <div>
                <div class="fw-semibold">₹<?= number_format((float)$p['amount'], 2) ?></div>
                <div class="text-muted"><?= $when ? formatDate($when, 'd M Y') : '—' ?></div>
              </div>
              <?= statusBadge($p['status'] ?? 'pending') ?>
            </div>
            <div class="text-muted small">
              <i class="bi <?= $payModeIcon($p['payment_method'] ?? 'manual') ?> me-1"></i><?= e($payModeLabel($p['payment_method'] ?? 'manual')) ?>
              · <code><?= e($txn) ?></code>
            </div>
          </div>
        <?php endforeach; ?>
        <div class="border-top pt-2 mt-2 small">
          <div class="d-flex justify-content-between"><span class="text-muted">Grand Total (submitted)</span><strong>₹<?= number_format($totalPaid, 2) ?></strong></div>
          <div class="d-flex justify-content-between"><span class="text-muted">Total Approved</span><strong class="text-success">₹<?= number_format($totalApprov, 2) ?></strong></div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- ─ Grievances panel (left column, below Payment) ─ -->
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-chat-square-dots me-2"></i>Grievances</h6>
      <p class="small text-muted mb-3">
        Have a question or concern about this event? Raise a grievance with the event administrator.
        Existing threads from this event are listed under Raise / View Grievance.
      </p>
      <a href="/athlete/events/<?= e(hid_event((int)$event['id'])) ?>/grievances" class="btn btn-outline-primary">
        <i class="bi bi-chat-square-dots me-2"></i>Raise / View Grievance
      </a>
    </div>

    <div class="d-flex justify-content-end gap-2 flex-wrap">
      <a href="/athlete/my-registrations" class="btn btn-light">Back to List</a>
      <?php if (\Models\EventRegistration::isEditable($registration)): ?>
        <a href="/athlete/events/<?= e(hid_event((int)$event['id'])) ?>/register" class="btn btn-primary">
          <i class="bi bi-pencil me-2"></i>Edit Registration
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Right column -->
  <div class="col-lg-4">
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-person-lines-fill me-2"></i>Event Contact</h6>
      <div class="mb-2"><strong><?= e($event['contact_name'] ?? '') ?></strong>
        <?php if (!empty($event['contact_designation'])): ?>
          <small class="text-muted d-block"><?= e($event['contact_designation']) ?></small>
        <?php endif; ?>
      </div>
      <div class="text-muted small">
        <?php if (!empty($event['contact_mobile'])): ?>
          <div><i class="bi bi-phone me-1"></i><?= e($event['contact_mobile']) ?></div>
        <?php endif; ?>
        <?php if (!empty($event['contact_email'])): ?>
          <div class="text-break"><i class="bi bi-envelope me-1"></i><?= e($event['contact_email']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($documents)): ?>
    <div class="sms-card p-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-file-earmark-text me-2"></i>Documents</h6>
      <ul class="list-unstyled mb-0">
        <?php foreach ($documents as $d): ?>
          <li class="d-flex align-items-start gap-2 py-2" style="border-bottom:1px dashed #e2e8f0">
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
