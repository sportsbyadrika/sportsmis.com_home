<?php
$pageTitle = 'Certificate Issue Register — ' . ($event['name'] ?? '');
$totalI = 0; $totalT = 0; $totalD = 0;
foreach ($rows as $r) {
    $totalI += (int)($r['individual_count'] ?? 0);
    $totalT += (int)($r['team_count']       ?? 0);
    $totalD += (int)($r['download_count']   ?? 0);
}
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-journal-bookmark me-2"></i>Certificate Issue Register</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <div class="ms-auto d-flex gap-2">
    <a class="btn btn-sm btn-outline-primary"
       href="/institution/events/<?= e($eventHash) ?>/certificates/register.csv">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i>CSV
    </a>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
      <i class="bi bi-printer me-1"></i>Print
    </button>
    <a class="btn btn-sm btn-outline-success"
       href="/institution/events/<?= e($eventHash) ?>/certificates">
      <i class="bi bi-award me-1"></i>Back to Certificates
    </a>
  </div>
</div>

<div class="sms-card p-3">
  <?php if (empty($rows)): ?>
    <p class="text-muted small mb-0 text-center py-3">
      No certificates have been generated for this event yet.
    </p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:50px">#</th>
          <th>Certificate No</th>
          <th style="width:110px">Date</th>
          <th style="width:140px">Timestamp</th>
          <th style="width:90px" class="text-center">Comp. No</th>
          <th>Athlete</th>
          <th>Unit</th>
          <th style="width:80px" class="text-center">Indiv.<br><small class="text-muted">events</small></th>
          <th style="width:80px" class="text-center">Team<br><small class="text-muted">events</small></th>
          <th style="width:80px" class="text-center" title="Number of times the athlete has downloaded this certificate from their portal.">Downloads</th>
          <th style="width:70px" class="text-center no-print">PDF</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $i => $r):
            $ts = $r['generated_at'] ?? '';
            try {
                $dt = $ts ? new DateTimeImmutable($ts) : null;
            } catch (\Throwable $e) { $dt = null; }
            $compNo = $r['competitor_number'] !== null
                ? str_pad((string)(int)$r['competitor_number'], 4, '0', STR_PAD_LEFT)
                : '—';
        ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td class="font-monospace small"><?= e($r['certificate_no'] ?? '') ?></td>
            <td class="small"><?= $dt ? e($dt->format('d M Y'))  : '—' ?></td>
            <td class="small text-muted"><?= $dt ? e($dt->format('H:i:s')) : '—' ?></td>
            <td class="text-center"><?= e($compNo) ?></td>
            <td class="fw-medium"><?= e($r['athlete_name'] ?? '—') ?></td>
            <td class="small"><?= e($r['unit_label'] ?? '—') ?></td>
            <td class="text-center"><?= (int)($r['individual_count'] ?? 0) ?></td>
            <td class="text-center"><?= (int)($r['team_count']       ?? 0) ?></td>
            <td class="text-center">
              <?php $dc = (int)($r['download_count'] ?? 0); ?>
              <?php if ($dc > 0): ?>
                <span class="fw-medium" <?= !empty($r['last_downloaded_at'])
                    ? 'title="Last: ' . e($r['last_downloaded_at']) . '"' : '' ?>><?= $dc ?></span>
              <?php else: ?>
                <span class="text-muted">0</span>
              <?php endif; ?>
            </td>
            <td class="text-center no-print">
              <a class="btn btn-sm btn-outline-primary py-0 px-2"
                 href="/institution/events/<?= e($eventHash) ?>/certificates/<?= (int)$r['id'] ?>/view"
                 target="_blank" rel="noopener"
                 title="Download / view this athlete's certificate PDF">
                <i class="bi bi-file-earmark-pdf"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light">
        <tr class="fw-semibold">
          <td colspan="7" class="text-end">Total</td>
          <td class="text-center"><?= $totalI ?></td>
          <td class="text-center"><?= $totalT ?></td>
          <td class="text-center"><?= $totalD ?></td>
          <td class="no-print"></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <p class="text-muted small mb-0 mt-2">
    <?= count($rows) ?> certificate<?= count($rows) === 1 ? '' : 's' ?> issued.
  </p>
  <?php endif; ?>
</div>

<style>
  @media print {
    .btn, form, .ms-auto { display: none !important; }
    .sms-card { border: 0 !important; box-shadow: none !important; padding: 0 !important; }
    body { background: #fff !important; }
  }
</style>
