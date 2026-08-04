<?php
/**
 * Athletics / Skating — Institution-wise Result Status (screen + print).
 * One row per institution (registrations > 0): athletes registered, events
 * registered (incl team), events whose final result is published (incl team),
 * athletes holding at least one medal, and athletes with no medal.
 * Expects: $event, $rows, $totals.
 */
$pageTitle = 'Institution-wise Result Status — ' . $event['name'];
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap sms-noprint">
  <a href="/event-staff/result-reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-data me-2"></i>Institution-wise Result Status</h5>
  <span class="badge bg-info-subtle text-info-emphasis">Athletics / Skating</span>
  <?php if (!empty($rows)): ?>
    <button type="button" class="btn btn-sm btn-outline-dark ms-auto" onclick="window.print()">
      <i class="bi bi-printer me-1"></i>Print
    </button>
  <?php endif; ?>
</div>

<?= flashBag() ?>

<div class="sms-print-head d-none">
  <h4 class="fw-bold mb-0"><?= e($event['name']) ?></h4>
  <div class="text-muted">Institution-wise Result Status</div>
</div>

<div class="sms-card p-3">
  <?php if (empty($rows)): ?>
    <div class="text-center text-muted py-4">
      <i class="bi bi-inbox display-6 d-block mb-2"></i>
      No institutions with registrations yet.
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle mb-0 sms-instab">
        <thead class="table-light">
          <tr>
            <th class="text-center" style="width:56px">Sl.No</th>
            <th>Name of Institution</th>
            <th class="text-center">Athletes<br>Registered</th>
            <th class="text-center">Events Registered<br>(incl. team)</th>
            <th class="text-center">Events (Final)<br>Published (incl. team)</th>
            <th class="text-center">Athletes with<br>&ge; 1 Medal</th>
            <th class="text-center">Athletes with<br>No Medal</th>
          </tr>
        </thead>
        <tbody>
          <?php $sl = 0; foreach ($rows as $r): $sl++; ?>
            <tr<?= $sl % 2 === 0 ? ' class="mt-alt"' : '' ?>>
              <td class="text-center"><?= $sl ?></td>
              <td><?= e($r['name']) ?></td>
              <td class="text-center"><?= (int)$r['athletes'] ?></td>
              <td class="text-center"><?= (int)$r['events'] ?></td>
              <td class="text-center">
                <?= (int)$r['events_pub'] ?><?php if ((int)$r['events'] > 0): ?><span class="text-muted small"> / <?= (int)$r['events'] ?></span><?php endif; ?>
              </td>
              <td class="text-center"><?= (int)$r['with_medal'] ?></td>
              <td class="text-center"><?= (int)$r['no_medal'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light fw-bold">
          <tr>
            <td class="text-center" colspan="2">Total (<?= count($rows) ?> institution<?= count($rows) === 1 ? '' : 's' ?>)</td>
            <td class="text-center"><?= (int)$totals['athletes'] ?></td>
            <td class="text-center"><?= (int)$totals['events'] ?></td>
            <td class="text-center"><?= (int)$totals['events_pub'] ?></td>
            <td class="text-center"><?= (int)$totals['medal'] ?></td>
            <td class="text-center"><?= (int)$totals['nomedal'] ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<style>
  .sms-instab tbody tr.mt-alt > td { background: #f7f9fc; }
  @media print {
    .sms-noprint, .sidebar, .navbar, .app-sidebar, footer { display: none !important; }
    .sms-print-head { display: block !important; margin-bottom: 12px; }
    .sms-card { border: 0 !important; box-shadow: none !important; padding: 0 !important; }
    .sms-instab { font-size: 12px; }
    .sms-instab th, .sms-instab td { border: 1px solid #333 !important; }
    .sms-instab tbody tr.mt-alt > td { background: #f2f2f2 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    a[href]:after { content: ''; }
  }
</style>
