<?php
$pageTitle = 'Relay ' . ($relay['relay_number'] ?? '') . ' — Score Report';
$cols = max(1, (int)$max_series);
$scored = array_filter($lanes, fn($l) => $l['score_entry_id']);
$totals = array_map(fn($l) => (float)$l['score_total'], $scored);
$avg = $totals ? array_sum($totals) / count($totals) : 0;
$hi  = $totals ? max($totals) : 0;
$lo  = $totals ? min($totals) : 0;
?>

<style>@page { size: A4 landscape; }</style>

<h2 style="text-align:center;margin:0"><?= e($event['name']) ?></h2>
<div class="text-center small">
  Code <strong><?= e($event['event_code'] ?? '') ?></strong> ·
  Relay <strong><?= e($relay['relay_number']) ?></strong>
  <?php if (!empty($relay['relay_date'])): ?> · <?= e(formatDate($relay['relay_date'],'d M Y')) ?><?php endif; ?>
  <?php if (!empty($relay['match_time'])): ?> · <?= e(substr($relay['match_time'],0,5)) ?><?php endif; ?>
</div>
<div class="text-center small text-muted mb-2">Generated <?= date('d M Y, h:i A') ?></div>

<table>
  <thead>
    <tr>
      <th>Lane</th>
      <th>Comp.</th>
      <th>Athlete</th>
      <th>Unit</th>
      <th>Category</th>
      <?php for ($i = 1; $i <= $cols; $i++): ?><th class="text-end">S<?= $i ?></th><?php endfor; ?>
      <th class="text-end">Penalty</th>
      <th class="text-end">Total</th>
      <th>Remarks</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($lanes)): ?>
      <tr><td colspan="<?= 5 + $cols + 3 ?>" class="text-center text-muted">No lanes / scores.</td></tr>
    <?php else: foreach ($lanes as $l):
      $bySer = []; foreach ($l['series_rows'] as $r) $bySer[(int)$r['series_no']] = $r;
    ?>
      <tr>
        <td class="text-center">Lane <?= (int)$l['lane_number'] ?></td>
        <td class="text-center"><?= $l['score_competitor_number'] ?? $l['competitor_number'] ?? '—' ?></td>
        <td><?= e($l['athlete_name'] ?? '—') ?></td>
        <td class="small">#<?= (int)($l['assigned_unit_id'] ?? 0) ?> <?= e($l['unit_name'] ?? '') ?></td>
        <td class="small"><?= e($l['category'] ?? '—') ?></td>
        <?php for ($i = 1; $i <= $cols; $i++):
          $r = $bySer[$i] ?? null; ?>
          <td class="text-end"><?= $r ? number_format((float)$r['series_total'], 2) : '—' ?></td>
        <?php endfor; ?>
        <td class="text-end"><?= $l['score_penalty'] !== null ? number_format((float)$l['score_penalty'], 2) : '—' ?></td>
        <td class="text-end fw-bold"><?= $l['score_total']   !== null ? number_format((float)$l['score_total'],   2) : '—' ?></td>
        <td class="small"><?= e(($l['score_remarks'] ?? '') ? strtoupper((string)$l['score_remarks']) : '') ?></td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>

<div class="no-break" style="margin-top:14px">
  <strong>Summary</strong>
  <table style="width:auto;margin-top:4px">
    <tr><td>Athletes Scored</td>     <td class="text-end fw-bold"><?= count($scored) ?></td></tr>
    <tr><td>Highest Total</td>       <td class="text-end fw-bold"><?= $totals ? number_format($hi, 2) : '—' ?></td></tr>
    <tr><td>Lowest Total</td>        <td class="text-end fw-bold"><?= $totals ? number_format($lo, 2) : '—' ?></td></tr>
    <tr><td>Average Total</td>       <td class="text-end fw-bold"><?= $totals ? number_format($avg, 2) : '—' ?></td></tr>
  </table>
</div>

<script>
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 350); });
</script>
