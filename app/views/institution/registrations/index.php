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

<!-- ─ Units + application status cards (institution / event totals) ─ -->
<?php $unitCount = count(array_filter($rows, fn($r) => (int)$r['unit_id'] !== 0)); ?>
<div class="d-flex flex-wrap gap-2 mb-3">
  <div class="flex-fill" style="min-width:120px">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Units</div>
      <div class="fs-4 fw-bold text-primary"><?= (int)$unitCount ?></div>
    </div>
  </div>
  <div class="flex-fill" style="min-width:120px">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Total</div>
      <div class="fs-4 fw-bold"><?= (int)$a['total'] ?></div>
    </div>
  </div>
  <div class="flex-fill" style="min-width:120px">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Pending</div>
      <div class="fs-4 fw-bold text-warning"><?= (int)$a['pending'] ?></div>
    </div>
  </div>
  <div class="flex-fill" style="min-width:120px">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Approved</div>
      <div class="fs-4 fw-bold text-success"><?= (int)$a['approved'] ?></div>
    </div>
  </div>
  <div class="flex-fill" style="min-width:120px">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Rejected</div>
      <div class="fs-4 fw-bold text-danger"><?= (int)$a['rejected'] ?></div>
    </div>
  </div>
  <div class="flex-fill" style="min-width:120px">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase">Returned</div>
      <div class="fs-4 fw-bold text-info"><?= (int)$a['returned'] ?></div>
    </div>
  </div>
  <div class="flex-fill" style="min-width:120px">
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
          <th class="text-center">Draft</th>
          <th class="text-center">Submitted</th>
          <th class="text-center">Approved</th>
          <th class="text-center">Rejected</th>
          <th class="text-center">Returned</th>
          <th class="text-center">Total</th>
          <th class="text-end">Total Demand</th>
          <th class="text-end">Txn Submitted</th>
          <th class="text-end">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="10" class="text-muted text-center py-4">No units match the filters.</td></tr>
        <?php else: foreach ($rows as $r):
          $spoc = $r['spoc'] ?? null;
          $tm   = $r['team'] ?? ['total'=>0,'draft'=>0,'submitted'=>0,'approved'=>0,'rejected'=>0,'returned'=>0];
          // Each status cell: individual count on the first line, team count
          // on the second (muted, prefixed "T").
          $cell = function (int $ind, int $team) {
              $out = '<div>' . $ind . '</div>';
              $out .= '<div class="small text-muted" title="Team entries">T ' . $team . '</div>';
              return $out;
          };
        ?>
          <tr>
            <td>
              <div class="fw-medium"><?= e($r['unit_name']) ?></div>
              <?php if (!empty($spoc)): ?>
                <div class="small text-muted">
                  <i class="bi bi-person-badge me-1"></i><?= e($spoc['name'] ?? '') ?>
                  <?php if (!empty($spoc['mobile'])): ?> · <i class="bi bi-telephone me-1"></i><?= e($spoc['mobile']) ?><?php endif; ?>
                  <?php if (!empty($spoc['email'])): ?><br><i class="bi bi-envelope me-1"></i><?= e($spoc['email']) ?><?php endif; ?>
                </div>
              <?php endif; ?>
              <?php if (!$event_id && !empty($r['event_name'])): ?>
                <div class="small text-muted"><i class="bi bi-calendar-event me-1"></i><?= e($r['event_name']) ?></div>
              <?php endif; ?>
            </td>
            <td class="text-center"><?= $cell((int)$r['draft'],     (int)$tm['draft']) ?></td>
            <td class="text-center"><?= $cell((int)$r['submitted'], (int)$tm['submitted']) ?></td>
            <td class="text-center"><?= $cell((int)$r['approved'],  (int)$tm['approved']) ?></td>
            <td class="text-center"><?= $cell((int)$r['rejected'],  (int)$tm['rejected']) ?></td>
            <td class="text-center"><?= $cell((int)$r['returned'],  (int)$tm['returned']) ?></td>
            <td class="text-center fw-bold">
              <div><?= (int)$r['total'] ?></div>
              <div class="small text-muted fw-normal" title="Team entries">T <?= (int)$tm['total'] ?></div>
            </td>
            <td class="text-end fw-medium">
              ₹<?= number_format((float)$r['demand'], 2) ?>
              <div class="small text-muted">
                Ind ₹<?= number_format((float)($r['demand_individual'] ?? 0), 2) ?>
                · Team ₹<?= number_format((float)($r['demand_team'] ?? 0), 2) ?>
              </div>
            </td>
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
    Each status column shows individual entries on the first line and team entries (prefixed <strong>T</strong>)
    on the second. Total Demand is the sum of individual and team demand.
    <strong>View more</strong> opens the unit's athletes, team entries and fund transfers.
  </div>
</div>
