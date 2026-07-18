<?php
  $pageTitle = 'Registrations by Unit';
  $a  = $app_counts;
  $pm = $pay_counts['manual'];
  $po = $pay_counts['online'];
  $rows = $unit_rows ?? [];
  // "View more" target — the Athletes by Unit page focused on one unit.
  $viewMore = function (array $r): string {
      return '/institution/events/' . (int)$r['event_id'] . '/athletes-by-unit?unit_id=' . (int)$r['unit_id'] . '&show=all';
  };
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <h5 class="mb-0 fw-bold"><i class="bi bi-diagram-3 me-2"></i>Registrations by Unit</h5>
    <?php if ($selected_event): ?>
      <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
        <i class="bi bi-calendar-event me-1"></i><?= e($selected_event['name']) ?>
      </span>
    <?php endif; ?>
  </div>
  <?php if ($selected_event): ?>
    <div class="d-flex gap-2 flex-wrap">
      <a href="/institution/events/<?= (int)$selected_event['id'] ?>/athletes-by-unit" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-diagram-3 me-1"></i>Athletes by Unit
      </a>
      <?php if (($selected_event['unit_payment_mode'] ?? 'individual') === 'bulk'): ?>
        <a href="/institution/events/<?= (int)$selected_event['id'] ?>/unit-payments" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-bank me-1"></i>Unit Payment Transactions
        </a>
      <?php endif; ?>
      <a href="/institution/events/<?= (int)$selected_event['id'] ?>/view" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-eye me-1"></i>View Event Details
      </a>
    </div>
  <?php endif; ?>
</div>

<!-- ─ Application status cards (institution / event totals) ─ -->
<div class="row g-2 mb-3">
  <div class="col-6 col-md-2">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Total</div>
      <div class="fs-4 fw-bold"><?= (int)$a['total'] ?></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Pending</div>
      <div class="fs-4 fw-bold text-warning"><?= (int)$a['pending'] ?></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Approved</div>
      <div class="fs-4 fw-bold text-success"><?= (int)$a['approved'] ?></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Rejected</div>
      <div class="fs-4 fw-bold text-danger"><?= (int)$a['rejected'] ?></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Returned</div>
      <div class="fs-4 fw-bold text-info"><?= (int)$a['returned'] ?></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Drafts</div>
      <div class="fs-4 fw-bold text-secondary"><?= (int)$a['draft'] ?></div>
    </div>
  </div>
</div>

<!-- ─ Payment status cards (Online + Manual) ─ -->
<div class="row g-2 mb-4">
  <div class="col-md-6">
    <div class="sms-card p-3 h-100">
      <div class="d-flex align-items-center mb-2">
        <i class="bi bi-credit-card-2-front text-primary me-2"></i>
        <strong>Online Payment</strong>
        <span class="ms-auto small text-muted">₹<?= number_format($po['amount_paid'], 2) ?> received</span>
      </div>
      <div class="row g-2 text-center small">
        <div class="col-4">
          <div class="border rounded-2 p-2">
            <div class="text-muted text-uppercase" style="font-size:.7rem">Paid</div>
            <div class="fw-bold text-success fs-5"><?= (int)$po['paid'] ?></div>
          </div>
        </div>
        <div class="col-4">
          <div class="border rounded-2 p-2">
            <div class="text-muted text-uppercase" style="font-size:.7rem">Pending</div>
            <div class="fw-bold text-warning fs-5"><?= (int)$po['pending'] ?></div>
          </div>
        </div>
        <div class="col-4">
          <div class="border rounded-2 p-2">
            <div class="text-muted text-uppercase" style="font-size:.7rem">Failed</div>
            <div class="fw-bold text-danger fs-5"><?= (int)$po['failed'] ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="sms-card p-3 h-100">
      <div class="d-flex align-items-center mb-2">
        <i class="bi bi-bank text-secondary me-2"></i>
        <strong>Manual Payment</strong>
        <span class="ms-auto small text-muted">₹<?= number_format($pm['amount_paid'], 2) ?> received</span>
      </div>
      <div class="row g-2 text-center small">
        <div class="col-4">
          <div class="border rounded-2 p-2">
            <div class="text-muted text-uppercase" style="font-size:.7rem">Paid</div>
            <div class="fw-bold text-success fs-5"><?= (int)$pm['paid'] ?></div>
          </div>
        </div>
        <div class="col-4">
          <div class="border rounded-2 p-2">
            <div class="text-muted text-uppercase" style="font-size:.7rem">Pending</div>
            <div class="fw-bold text-warning fs-5"><?= (int)$pm['pending'] ?></div>
          </div>
        </div>
        <div class="col-4">
          <div class="border rounded-2 p-2">
            <div class="text-muted text-uppercase" style="font-size:.7rem">Failed</div>
            <div class="fw-bold text-danger fs-5"><?= (int)$pm['failed'] ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<form method="GET" action="/institution/registrations" class="sms-card p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label small mb-1">Search Unit</label>
      <input type="search" name="q" value="<?= e($q) ?>" class="form-control form-control-sm"
             placeholder="Unit / club name…">
    </div>
    <div class="col-md-5">
      <label class="form-label small mb-1">Event</label>
      <select name="event_id" class="form-select form-select-sm" onchange="this.form.submit();">
        <option value="0">All events</option>
        <?php foreach ($events as $ev): ?>
          <option value="<?= (int)$ev['id'] ?>" <?= (int)$event_id === (int)$ev['id'] ? 'selected' : '' ?>>
            <?= e($ev['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
      <a href="/institution/registrations" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
    </div>
  </div>
</form>

<div class="sms-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Unit</th>
          <th>SPOC</th>
          <th class="text-center">Athletes<br><small class="fw-normal text-muted">D · S · A · R · Rt</small></th>
          <th class="text-end">Total Demand</th>
          <th class="text-end">Txn Submitted</th>
          <th class="text-end">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="6" class="text-muted text-center py-4">No units match the filters.</td></tr>
        <?php else: foreach ($rows as $r):
          $isDirect = (int)$r['unit_id'] === 0;
        ?>
          <tr>
            <td>
              <div class="fw-medium"><?= e($r['unit_name']) ?></div>
              <?php if (!$event_id && !empty($r['event_name'])): ?>
                <small class="text-muted"><i class="bi bi-calendar-event me-1"></i><?= e($r['event_name']) ?></small>
              <?php endif; ?>
            </td>
            <td class="small">
              <?php if (!empty($r['spoc'])): ?>
                <div class="fw-medium"><?= e($r['spoc']['name'] ?? '') ?></div>
                <div class="text-muted">
                  <?php if (!empty($r['spoc']['mobile'])): ?><i class="bi bi-telephone me-1"></i><?= e($r['spoc']['mobile']) ?><?php endif; ?>
                  <?php if (!empty($r['spoc']['email'])): ?><br><i class="bi bi-envelope me-1"></i><?= e($r['spoc']['email']) ?><?php endif; ?>
                </div>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <div class="fw-bold"><?= (int)$r['total'] ?> <small class="text-muted fw-normal">athletes</small></div>
              <div class="mt-1 d-flex flex-wrap gap-1 justify-content-center small">
                <span class="badge bg-secondary" title="Draft"><?= (int)$r['draft'] ?></span>
                <span class="badge bg-info" title="Submitted / pending review"><?= (int)$r['submitted'] ?></span>
                <span class="badge bg-success" title="Approved"><?= (int)$r['approved'] ?></span>
                <span class="badge bg-danger" title="Rejected"><?= (int)$r['rejected'] ?></span>
                <span class="badge bg-warning text-dark" title="Returned"><?= (int)$r['returned'] ?></span>
              </div>
            </td>
            <td class="text-end fw-medium">₹<?= number_format((float)$r['demand'], 2) ?></td>
            <td class="text-end">₹<?= number_format((float)$r['txn'], 2) ?></td>
            <td class="text-end">
              <a href="<?= e($viewMore($r)) ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye"></i><span class="d-none d-lg-inline ms-1">View more</span>
              </a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <div class="p-2 border-top small text-muted">
    <i class="bi bi-info-circle me-1"></i>
    Athlete counts: <span class="badge bg-secondary">D</span> Draft ·
    <span class="badge bg-info">S</span> Submitted ·
    <span class="badge bg-success">A</span> Approved ·
    <span class="badge bg-danger">R</span> Rejected ·
    <span class="badge bg-warning text-dark">Rt</span> Returned.
    <strong>View more</strong> opens the unit's athletes, team entries and fund transfers.
  </div>
</div>
