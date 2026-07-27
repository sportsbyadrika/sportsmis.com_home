<?php
/**
 * Participants, Rounds & Heats report (landscape). One row per sport-event with
 * submitted / approved counts, primary rounds (ceil(approved/tracks)), laps, and
 * per-round (Preliminary / Semifinal / Final) heats and heats×laps, plus a Total
 * per event and a grand-total row. Rendered directly by
 * LaneAllocationController::roundsReport.
 * Expects: $event, $rows, $totals.
 */
$evName = trim((string)($event['name'] ?? ''));
$n = fn($v) => (int)$v > 0 ? (int)$v : '';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Participants, Rounds &amp; Heats — <?= e($evName) ?></title>
<style>
  @page {
    size: A4 landscape;
    margin: 10mm 10mm 12mm 10mm;
    @bottom-right { content: "Page " counter(page) " of " counter(pages); font-size: 9pt; color: #555; }
  }
  * { font-family: Arial, "DejaVu Sans", sans-serif; }
  html, body { background:#fff; color:#111; margin:0; }
  .doc-head { display:flex; align-items:center; gap:12px; border-bottom:2px solid #333; padding-bottom:6px; margin-bottom:8px; }
  .doc-head img { width:44px; height:44px; object-fit:contain; }
  .doc-head h1 { font-size:14pt; margin:0; }
  .doc-head .sub { font-size:9.5pt; color:#555; margin-top:2px; }
  table { width:100%; border-collapse:collapse; table-layout:fixed; font-size:9pt; }
  th, td { border:1px solid #333; padding:3px 4px; vertical-align:middle; word-wrap:break-word; }
  thead th { background:#eee; text-align:center; font-size:8pt; text-transform:uppercase; }
  td.c, th.c { text-align:center; }
  td.l { text-align:left; }
  tbody tr { page-break-inside: avoid; }
  tfoot th { background:#e9ecef; font-size:8.5pt; }
  col.c-sl{width:3.5%} col.c-cat{width:7%} col.c-ev{width:13%}
  col.c-sub{width:5.5%} col.c-app{width:5.5%} col.c-pr{width:5.5%} col.c-lap{width:4.5%}
  col.c-h{width:4.4%} col.c-hl{width:4.9%}
</style>
</head>
<body>
  <div class="doc-head">
    <?php if (!empty($event['logo'])): ?><img src="<?= e($event['logo']) ?>" alt=""><?php endif; ?>
    <div>
      <h1><?= e($evName) ?></h1>
      <div class="sub">Participants, Rounds &amp; Heats &middot; <?= count($rows) ?> sport-event<?= count($rows) === 1 ? '' : 's' ?></div>
    </div>
  </div>

  <table>
    <colgroup>
      <col class="c-sl"><col class="c-cat"><col class="c-ev">
      <col class="c-sub"><col class="c-app"><col class="c-pr"><col class="c-lap">
      <col class="c-h"><col class="c-hl"><col class="c-h"><col class="c-hl">
      <col class="c-h"><col class="c-hl"><col class="c-h"><col class="c-hl">
      <col class="c-h"><col class="c-hl">
    </colgroup>
    <thead>
      <tr>
        <th rowspan="2" class="c">Sl. No</th>
        <th rowspan="2">Event Category</th>
        <th rowspan="2">Sport Event</th>
        <th rowspan="2" class="c">Submitted</th>
        <th rowspan="2" class="c">Approved</th>
        <th rowspan="2" class="c">Primary Rounds</th>
        <th rowspan="2" class="c">Laps</th>
        <th colspan="2" class="c">Preliminary</th>
        <th colspan="2" class="c">Quarter Final</th>
        <th colspan="2" class="c">Semi Final</th>
        <th colspan="2" class="c">Final</th>
        <th colspan="2" class="c">Total</th>
      </tr>
      <tr>
        <th class="c">Heats</th><th class="c">Heats×Laps</th>
        <th class="c">Heats</th><th class="c">Heats×Laps</th>
        <th class="c">Heats</th><th class="c">Heats×Laps</th>
        <th class="c">Heats</th><th class="c">Heats×Laps</th>
        <th class="c">Heats</th><th class="c">Heats×Laps</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="17" class="c" style="padding:12px;color:#666">No sport events with approved athletes yet.</td></tr>
      <?php else: $sl = 0; foreach ($rows as $r): $sl++; ?>
        <tr>
          <td class="c"><?= $sl ?></td>
          <td class="l"><?= e($r['category']) ?></td>
          <td class="l"><?= e($r['sport_event']) ?><?php if ($r['age_name'] !== ''): ?> <span style="color:#666">· <?= e($r['age_name']) ?></span><?php endif; ?></td>
          <td class="c"><?= (int)$r['submitted'] ?></td>
          <td class="c"><?= (int)$r['approved'] ?></td>
          <td class="c"><?= $r['primary'] !== null ? (int)$r['primary'] : '—' ?></td>
          <td class="c"><?= $n($r['laps']) ?: '—' ?></td>
          <td class="c"><?= $n($r['prelim_h']) ?></td><td class="c"><?= $n($r['prelim_l']) ?></td>
          <td class="c"><?= $n($r['quarter_h']) ?></td><td class="c"><?= $n($r['quarter_l']) ?></td>
          <td class="c"><?= $n($r['semi_h']) ?></td><td class="c"><?= $n($r['semi_l']) ?></td>
          <td class="c"><?= $n($r['final_h']) ?></td><td class="c"><?= $n($r['final_l']) ?></td>
          <td class="c fw-bold"><?= $n($r['total_h']) ?></td><td class="c fw-bold"><?= $n($r['total_l']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
    <?php if (!empty($rows)): ?>
    <tfoot>
      <tr>
        <th colspan="3" class="c">Grand Total</th>
        <th class="c"><?= (int)$totals['submitted'] ?></th>
        <th class="c"><?= (int)$totals['approved'] ?></th>
        <th class="c">—</th>
        <th class="c">—</th>
        <th class="c"><?= (int)$totals['prelim_h'] ?></th><th class="c"><?= (int)$totals['prelim_l'] ?></th>
        <th class="c"><?= (int)$totals['quarter_h'] ?></th><th class="c"><?= (int)$totals['quarter_l'] ?></th>
        <th class="c"><?= (int)$totals['semi_h'] ?></th><th class="c"><?= (int)$totals['semi_l'] ?></th>
        <th class="c"><?= (int)$totals['final_h'] ?></th><th class="c"><?= (int)$totals['final_l'] ?></th>
        <th class="c"><?= (int)$totals['total_h'] ?></th><th class="c"><?= (int)$totals['total_l'] ?></th>
      </tr>
    </tfoot>
    <?php endif; ?>
  </table>

<script>window.addEventListener('load', function(){ setTimeout(function(){ try{ window.print(); }catch(e){} }, 200); });</script>
</body>
</html>
