<?php $pageTitle = 'Registration — ' . $event['name']; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2">
    <a href="/athlete/my-registrations" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h5 class="mb-0 fw-bold"><i class="bi bi-receipt me-2"></i>Registration Details</h5>
    <?= appStatusBadge($registration['admin_review_status'] ?? null, $registration['submitted_at'] ?? null) ?>
    <?= statusBadge($registration['payment_status'] ?? 'pending') ?>
  </div>
  <?php
    $isApproved = ($registration['admin_review_status'] ?? '') === 'approved' && !empty($registration['competitor_number']);
  ?>
  <?php if ($isApproved): ?>
    <a href="/athlete/registrations/<?= (int)$registration['id'] ?>/card" target="_blank"
       class="btn btn-success">
      <i class="bi bi-card-heading me-2"></i>Download Competitor Card #<?= (int)$registration['competitor_number'] ?>
    </a>
  <?php elseif (\Models\EventRegistration::isEditable($registration)): ?>
    <a href="/athlete/events/<?= (int)$event['id'] ?>/register" class="btn btn-primary">
      <i class="bi bi-pencil me-2"></i>Edit Registration
    </a>
  <?php else: ?>
    <button type="button" class="btn btn-outline-secondary" disabled
            title="Locked — registration is under review">
      <i class="bi bi-lock me-2"></i>Locked
    </button>
  <?php endif; ?>
</div>

<div class="row g-4">
  <div class="col-lg-8">

    <!-- Event Snapshot -->
    <div class="sms-card p-4 mb-4">
      <div class="d-flex align-items-start gap-3">
        <?php if (!empty($event['logo'])): ?>
          <img src="<?= e($event['logo']) ?>" alt="" width="64" height="64"
               class="rounded-3 flex-shrink-0" style="object-fit:cover;border:1px solid #e2e8f0;background:#fff">
        <?php else: ?>
          <div class="sms-event-icon sms-event-icon-lg flex-shrink-0"><i class="bi bi-trophy"></i></div>
        <?php endif; ?>
        <div class="flex-grow-1">
          <h5 class="fw-bold mb-1"><?= e($event['name']) ?></h5>
          <div class="text-muted small mb-1"><i class="bi bi-building me-1"></i><?= e($event['institution_name'] ?? '') ?></div>
          <div class="text-muted small mb-1"><i class="bi bi-geo-alt me-1"></i><?= e($event['location']) ?></div>
          <div class="text-muted small"><i class="bi bi-calendar3 me-1"></i><?= formatDate($event['event_date_from']) ?> – <?= formatDate($event['event_date_to']) ?></div>
        </div>
      </div>
    </div>

    <!-- Registration Details -->
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-info-circle me-2"></i>Registration Details</h6>
      <div class="row g-3 small">
        <div class="col-md-6">
          <div class="text-muted">Unit / Club / Institution</div>
          <div class="fw-semibold"><?= !empty($unit) ? e($unit['name']) : '—' ?></div>
          <?php if (!empty($unit['address'])): ?>
            <div class="text-muted small"><?= e($unit['address']) ?></div>
          <?php endif; ?>
        </div>
        <div class="col-md-6">
          <div class="text-muted">NOC Letter</div>
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

    <!-- Selected Events -->
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-trophy me-2"></i>Selected Sport Events</h6>
      <?php if (empty($items)): ?>
        <p class="text-muted small mb-0">No sport events were added to this registration.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Sport</th>
              <th>Event Code</th>
              <th>Event</th>
              <th class="text-end">Fee</th>
            </tr>
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
              <th class="text-end fw-bold">₹<?= number_format((float)($registration['total_amount'] ?? 0), 2) ?></th>
            </tr>
          </tfoot>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Payment -->
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-credit-card me-2"></i>Payment</h6>
      <div class="row g-3 small">
        <div class="col-md-4">
          <div class="text-muted">Payment Mode</div>
          <div class="fw-semibold">
            <?php if (!empty($registration['payment_mode'])): ?>
              <i class="bi bi-<?= $registration['payment_mode'] === 'manual' ? 'bank' : 'credit-card' ?> me-1"></i>
              <?= ucfirst($registration['payment_mode']) ?>
            <?php else: ?>
              <span class="text-muted">Not chosen</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-md-4">
          <div class="text-muted">Payment Status</div>
          <div><?= statusBadge($registration['payment_status'] ?? 'pending') ?></div>
        </div>
        <div class="col-md-4">
          <div class="text-muted">Amount</div>
          <div class="fw-semibold">₹<?= number_format((float)($registration['payment_amount'] ?? $registration['total_amount'] ?? 0), 2) ?></div>
        </div>
        <?php if (($registration['payment_mode'] ?? '') === 'manual'): ?>
          <div class="col-md-4">
            <div class="text-muted">Transaction Date</div>
            <div class="fw-semibold"><?= !empty($registration['transaction_date']) ? formatDate($registration['transaction_date']) : '—' ?></div>
          </div>
          <div class="col-md-4">
            <div class="text-muted">Transaction Number</div>
            <div class="fw-semibold"><?= !empty($registration['transaction_number']) ? e($registration['transaction_number']) : '—' ?></div>
          </div>
          <div class="col-md-4">
            <div class="text-muted">Transaction Proof</div>
            <?php if (!empty($registration['transaction_proof'])): ?>
              <a href="<?= e($registration['transaction_proof']) ?>" target="_blank" rel="noopener"><i class="bi bi-receipt me-1"></i>View Proof</a>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
      <a href="/athlete/my-registrations" class="btn btn-light">Back to List</a>
      <?php if (\Models\EventRegistration::isEditable($registration)): ?>
        <a href="/athlete/events/<?= (int)$event['id'] ?>/register" class="btn btn-primary">
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
          <div><i class="bi bi-envelope me-1"></i><?= e($event['contact_email']) ?></div>
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
