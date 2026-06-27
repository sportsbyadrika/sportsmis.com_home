<?php
$pageTitle = 'Registrations';
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-data me-2"></i>Registrations</h5>
  <span class="text-muted small ms-2">on <?= e($event['name'] ?? '') ?></span>
  <a href="/unit/athletes/new" class="btn btn-sm btn-success ms-auto">
    <i class="bi bi-person-plus me-1"></i>Add Athlete
  </a>
</div>

<?= flashBag() ?>

<div class="sms-card p-3">
  <?php if (empty($registrations)): ?>
    <div class="text-center text-muted py-4">
      <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
      You haven&rsquo;t registered any athletes on this event yet.
      Click <a href="/unit/athletes/new">Add Athlete</a> to start.
    </div>
  <?php else:
    // Roll-up totals across all registrations on the page.
    $rTotalDemand = 0.0; $rTotalClaimed = 0.0; $rTotalApproved = 0.0;
    foreach ($registrations as $r) {
      $rTotalDemand   += (float)($r['total_amount']    ?? 0);
      $rTotalClaimed  += (float)($r['claimed_amount']  ?? 0);
      $rTotalApproved += (float)($r['approved_amount'] ?? 0);
    }
  ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Athlete</th>
            <th>Unit</th>
            <th class="text-center">Events</th>
            <th class="text-end">Demand</th>
            <th class="text-end">Claimed</th>
            <th class="text-end">Balance</th>
            <th>Transactions</th>
            <th>Submission</th>
            <th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($registrations as $r):
            $demand   = (float)($r['total_amount']    ?? 0);
            $claimed  = (float)($r['claimed_amount']  ?? 0);
            $approved = (float)($r['approved_amount'] ?? 0);
            $balance  = $demand - $claimed;
            // Transaction-status verdict — driven by the gap between
            // demand / claimed / approved so it's never out-of-sync
            // with the per-registration page.
            if ($demand <= 0) {
              [$txCls, $txLbl] = ['secondary', 'No demand'];
            } elseif ($approved + 0.005 >= $demand) {
              [$txCls, $txLbl] = ['success', 'Paid'];
            } elseif ($claimed + 0.005 >= $demand) {
              [$txCls, $txLbl] = ['warning text-dark', 'Awaiting review'];
            } elseif ($claimed > 0) {
              [$txCls, $txLbl] = ['warning text-dark', 'Partial'];
            } else {
              [$txCls, $txLbl] = ['danger', 'No payment'];
            }
            // Submission-status verdict.
            $rs = (string)($r['admin_review_status'] ?? '');
            $rsMap = [
              ''         => ['secondary', 'Draft'],
              'pending'  => ['info',      'Pending review'],
              'approved' => ['success',   'Approved'],
              'rejected' => ['danger',    'Rejected'],
              'returned' => ['warning text-dark', 'Returned for edit'],
            ];
            [$rsCls, $rsLbl] = $rsMap[$rs] ?? ['secondary', ucfirst($rs ?: 'Draft')];
            $regHash = hid_reg((int)$r['id']);
          ?>
            <tr>
              <td>
                <div class="fw-medium"><?= e($r['athlete_name']) ?></div>
                <div class="small text-muted">
                  <?= e(genderLabel((string)($r['gender'] ?? ''), $event)) ?>
                  <?php if (!empty($r['date_of_birth'])): ?>
                    · <?= (int)ageFromDob($r['date_of_birth']) ?> yrs
                  <?php endif; ?>
                </div>
              </td>
              <td class="small"><?= e($r['unit_name'] ?? '—') ?></td>
              <td class="text-center"><?= (int)($r['items_count'] ?? 0) ?></td>
              <td class="text-end">₹<?= number_format($demand, 2) ?></td>
              <td class="text-end">₹<?= number_format($claimed, 2) ?></td>
              <td class="text-end <?= $balance > 0.005 ? 'text-danger' : ($balance < -0.005 ? 'text-warning' : 'text-success') ?>">
                ₹<?= number_format($balance, 2) ?>
              </td>
              <td><span class="badge bg-<?= $txCls ?>"><?= $txLbl ?></span></td>
              <td><span class="badge bg-<?= $rsCls ?>"><?= $rsLbl ?></span></td>
              <td class="text-end">
                <a href="/unit/athletes/<?= e($regHash) ?>" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-eye"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light">
          <tr>
            <th colspan="3" class="text-end">Totals</th>
            <th class="text-end">₹<?= number_format($rTotalDemand, 2) ?></th>
            <th class="text-end">₹<?= number_format($rTotalClaimed, 2) ?></th>
            <th class="text-end <?= ($rTotalDemand - $rTotalClaimed) > 0.005 ? 'text-danger' : 'text-success' ?>">
              ₹<?= number_format($rTotalDemand - $rTotalClaimed, 2) ?>
            </th>
            <th colspan="3" class="text-end small text-muted">
              Approved: ₹<?= number_format($rTotalApproved, 2) ?>
            </th>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>
