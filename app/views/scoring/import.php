<?php
$pageTitle = 'Scoring — Import CSV — ' . $event['name'];
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];
$r = $results ?? null;
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/scoring" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-upload me-2"></i>Import Scores (CSV)</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?> · <code><?= e($event['event_code'] ?? '') ?></code></span>
</div>

<?= flashBag() ?>

<div class="sms-card p-3 mb-3">
  <form method="POST" enctype="multipart/form-data" action="/event-staff/scoring/import">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <div class="row g-2 align-items-end">
      <div class="col-md-7">
        <label class="form-label small mb-1">CSV file</label>
        <input type="file" name="csv" accept=".csv,text/csv" class="form-control form-control-sm" required>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-cloud-upload me-1"></i>Upload &amp; Import</button>
      </div>
    </div>
  </form>
  <details class="small text-muted mt-3">
    <summary>CSV format expected</summary>
    <ul class="mt-2 mb-0">
      <li>Required columns: <code>RELAY</code>, <code>LANE</code>, <code>COMP. NO</code>, <code>NAME OF ATHLETE</code>, <code>UNIT</code>, <code>CATEGORY</code>.</li>
      <li>Shot columns: <code>SHOT1</code> … <code>SHOTn</code> where <em>n</em> matches the lane's category configuration (series × shots-per-series).</li>
      <li>Optional: <code>SERIES 1</code> … <code>SERIES m</code>, <code>SUB-TOTAL</code>, <code>PENALITY</code>, <code>TOTAL</code>, <code>REMARKS</code>.</li>
      <li><strong>Rules applied:</strong>
        <ul>
          <li>Relay number, lane number, competitor number and category abbreviation must match the configured allotment.</li>
          <li>Lanes that already have a saved score are <strong>not</strong> overwritten.</li>
          <li>If CSV's <code>SUB-TOTAL</code> / <code>TOTAL</code> disagree with the computed values, the row is skipped.</li>
        </ul>
      </li>
    </ul>
  </details>
</div>

<?php if ($r): ?>
  <?php
    $okCount   = count($r['success']);
    $failCount = count($r['failed']);
    $tot       = (int)$r['total'];
  ?>
  <div class="row g-2 mb-3">
    <div class="col-md-4">
      <div class="border rounded-3 p-3 text-center bg-light-subtle">
        <div class="small text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em">Rows Read</div>
        <div class="fw-bold fs-4"><?= $tot ?></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="border rounded-3 p-3 text-center bg-light-subtle">
        <div class="small text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em">Imported</div>
        <div class="fw-bold fs-4 text-success"><?= $okCount ?></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="border rounded-3 p-3 text-center bg-light-subtle">
        <div class="small text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em">Failed / Skipped</div>
        <div class="fw-bold fs-4 text-danger"><?= $failCount ?></div>
      </div>
    </div>
  </div>

  <?php if ($okCount > 0): ?>
    <div class="sms-card p-3 mb-3">
      <h6 class="fw-semibold mb-2"><i class="bi bi-check2-circle me-2 text-success"></i>Imported</h6>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Row</th>
              <th>Athlete</th>
              <th>Unit</th>
              <th class="text-end">Total Score</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($r['success'] as $i => $row): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td class="small"><?= e($row['row']) ?></td>
                <td><?= e($row['name'] ?: '') ?></td>
                <td class="small text-muted"><?= e($row['unit'] ?: '') ?></td>
                <td class="text-end fw-bold"><?= (int)round((float)$row['total']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($failCount > 0): ?>
    <div class="sms-card p-3 mb-3">
      <h6 class="fw-semibold mb-2"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Failed / Skipped</h6>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Row</th>
              <th>Reason</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($r['failed'] as $i => $row): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td class="small"><?= e($row['row']) ?></td>
                <td class="text-danger small"><?= e($row['reason']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>
