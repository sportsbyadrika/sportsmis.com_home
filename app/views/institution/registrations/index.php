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

<style>
  /* Alternate a subtle shade per unit group (both of its rows). Uses
     !important so it wins over Bootstrap's per-cell --bs-table-bg. */
  #unitTable tbody tr.unit-shade > td { background-color: #eef2f7 !important; }
</style>
<div class="sms-card">
  <div class="table-responsive">
    <table id="unitTable" class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Unit</th>
          <th class="text-center">Entry</th>
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
          <tr><td colspan="11" class="text-muted text-center py-4">No units match the filters.</td></tr>
        <?php else:
          $col = function (int $n, string $kind) {
              if ($n <= 0) return '<span class="text-muted">0</span>';
              $cls = ['submitted' => 'text-warning fw-semibold',
                      'approved'  => 'text-success fw-semibold',
                      'rejected'  => 'text-danger fw-semibold'][$kind] ?? '';
              return '<span class="' . $cls . '">' . $n . '</span>';
          };
          // Running totals for the footer.
          $sum = ['i'=>['draft'=>0,'submitted'=>0,'approved'=>0,'rejected'=>0,'returned'=>0,'total'=>0],
                  't'=>['draft'=>0,'submitted'=>0,'approved'=>0,'rejected'=>0,'returned'=>0,'total'=>0],
                  'demand'=>0.0,'demand_i'=>0.0,'demand_t'=>0.0,'txn'=>0.0];
          $gi = 0;
          foreach ($rows as $r):
            $spoc = $r['spoc'] ?? null;
            $tm   = $r['team'] ?? ['total'=>0,'draft'=>0,'submitted'=>0,'approved'=>0,'rejected'=>0,'returned'=>0];
            // Alternate a subtle shade per unit group (both of its rows).
            $shade = ($gi % 2 === 1) ? ' class="unit-shade"' : '';
            $gi++;
            // Accumulate totals.
            foreach (['draft','submitted','approved','rejected','returned','total'] as $k) {
                $sum['i'][$k] += (int)($r[$k] ?? 0);
                $sum['t'][$k] += (int)($tm[$k] ?? 0);
            }
            $sum['demand']   += (float)$r['demand'];
            $sum['demand_i'] += (float)($r['demand_individual'] ?? 0);
            $sum['demand_t'] += (float)($r['demand_team'] ?? 0);
            $sum['txn']      += (float)$r['txn'];
            // Txn colour: green when the full submitted transaction amount is
            // approved, orange when part/none is approved yet.
            $txnVal = (float)$r['txn'];
            $txnApp = (float)($r['txn_approved'] ?? 0);
            $txnCls = $txnVal <= 0.005 ? 'text-muted'
                    : (($txnApp + 0.005 >= $txnVal) ? 'text-success fw-semibold' : 'text-warning fw-semibold');
        ?>
          <tr<?= $shade ?>>
            <td rowspan="2" class="align-middle">
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
            <!-- Individual line -->
            <td class="text-center small text-muted">Individual</td>
            <td class="text-center"><?= $col((int)$r['draft'], 'draft') ?></td>
            <td class="text-center"><?= $col((int)$r['submitted'], 'submitted') ?></td>
            <td class="text-center"><?= $col((int)$r['approved'], 'approved') ?></td>
            <td class="text-center"><?= $col((int)$r['rejected'], 'rejected') ?></td>
            <td class="text-center"><?= $col((int)$r['returned'], 'returned') ?></td>
            <td class="text-center fw-bold"><?= (int)$r['total'] ?></td>
            <td rowspan="2" class="text-end fw-medium align-middle">
              ₹<?= number_format((float)$r['demand'], 2) ?>
              <div class="small text-muted">
                Ind ₹<?= number_format((float)($r['demand_individual'] ?? 0), 2) ?>
                <br>Team ₹<?= number_format((float)($r['demand_team'] ?? 0), 2) ?>
              </div>
            </td>
            <td rowspan="2" class="text-end align-middle <?= $txnCls ?>">
              ₹<?= number_format($txnVal, 2) ?>
            </td>
            <td rowspan="2" class="text-end align-middle">
              <a href="<?= e($viewMore($r)) ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye"></i><span class="d-none d-lg-inline ms-1">View more</span>
              </a>
            </td>
          </tr>
          <tr<?= $shade ?>>
            <!-- Team line -->
            <td class="text-center small text-muted">Team</td>
            <td class="text-center"><?= $col((int)$tm['draft'], 'draft') ?></td>
            <td class="text-center"><?= $col((int)$tm['submitted'], 'submitted') ?></td>
            <td class="text-center"><?= $col((int)$tm['approved'], 'approved') ?></td>
            <td class="text-center"><?= $col((int)$tm['rejected'], 'rejected') ?></td>
            <td class="text-center"><?= $col((int)$tm['returned'], 'returned') ?></td>
            <td class="text-center fw-bold"><?= (int)$tm['total'] ?></td>
          </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <?php if (!empty($rows)): ?>
      <tfoot class="table-light border-top">
        <tr class="fw-semibold">
          <td class="text-end">Total — Individual</td>
          <td></td>
          <td class="text-center"><?= (int)$sum['i']['draft'] ?></td>
          <td class="text-center"><?= (int)$sum['i']['submitted'] ?></td>
          <td class="text-center"><?= (int)$sum['i']['approved'] ?></td>
          <td class="text-center"><?= (int)$sum['i']['rejected'] ?></td>
          <td class="text-center"><?= (int)$sum['i']['returned'] ?></td>
          <td class="text-center"><?= (int)$sum['i']['total'] ?></td>
          <td rowspan="2" class="text-end align-middle">
            ₹<?= number_format($sum['demand'], 2) ?>
            <div class="small text-muted fw-normal">
              Ind ₹<?= number_format($sum['demand_i'], 2) ?><br>Team ₹<?= number_format($sum['demand_t'], 2) ?>
            </div>
          </td>
          <td rowspan="2" class="text-end align-middle">₹<?= number_format($sum['txn'], 2) ?></td>
          <td rowspan="2"></td>
        </tr>
        <tr class="fw-semibold">
          <td class="text-end">Total — Team</td>
          <td></td>
          <td class="text-center"><?= (int)$sum['t']['draft'] ?></td>
          <td class="text-center"><?= (int)$sum['t']['submitted'] ?></td>
          <td class="text-center"><?= (int)$sum['t']['approved'] ?></td>
          <td class="text-center"><?= (int)$sum['t']['rejected'] ?></td>
          <td class="text-center"><?= (int)$sum['t']['returned'] ?></td>
          <td class="text-center"><?= (int)$sum['t']['total'] ?></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
  <div class="p-2 border-top small text-muted">
    <i class="bi bi-info-circle me-1"></i>
    Each unit has two lines — <strong>Individual</strong> and <strong>Team</strong> — under the same status columns.
    Submitted counts show <span class="text-warning">orange</span>, approved <span class="text-success">green</span>,
    rejected <span class="text-danger">red</span>. Total Demand is the sum of individual and team demand;
    Txn Submitted is <span class="text-success">green</span> when fully approved, <span class="text-warning">orange</span> otherwise.
    <strong>View more</strong> opens the unit's athletes, team entries and fund transfers.
  </div>
</div>
