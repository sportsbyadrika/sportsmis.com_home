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
  thead th { background:#e5e7eb; text-align:center; font-size:8pt; text-transform:uppercase;
             -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  td.c, th.c { text-align:center; }
  td.l { text-align:left; }
  tbody tr { page-break-inside: avoid; }
  tr.alt td { background:#f4f6f8; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  tr.grp td { background:#d8e2ef; font-weight:bold; text-align:left; font-size:9pt;
              -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  tr.sub td { background:#eef1f5; font-weight:bold;
              -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  tfoot th { background:#cbd5e1; font-size:8.5pt;
             -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  .foot-note { font-size:8.5pt; color:#555; margin-top:6px; }
  col.c-sl{width:3.5%} col.c-cat{width:4%} col.c-ev{width:16%}
  col.c-sub{width:5.5%} col.c-app{width:5.5%} col.c-pr{width:5%} col.c-lap{width:4.5%}
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
        <th rowspan="2" class="c">EC</th>
        <th rowspan="2">Sport Event</th>
        <th rowspan="2" class="c">Submitted</th>
        <th rowspan="2" class="c">Approved</th>
        <th rowspan="2" class="c">Heats</th>
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
      <?php else:
        // Group rows by age category. A subtotal row closes each group.
        $subKeys = ['submitted','approved','prelim_h','prelim_l','quarter_h','quarter_l',
                    'semi_h','semi_l','final_h','final_l','total_h','total_l'];
        $zero = array_fill_keys($subKeys, 0);
        $sub  = $zero; $curAge = null; $sl = 0; $alt = false;
        $renderSub = function (array $s, string $label) use ($n) {
          echo '<tr class="sub"><td colspan="3" class="c">Subtotal — ' . e($label) . '</td>'
             . '<td class="c">' . (int)$s['submitted'] . '</td>'
             . '<td class="c">' . (int)$s['approved'] . '</td>'
             . '<td class="c">—</td><td class="c">—</td>'
             . '<td class="c">' . (int)$s['prelim_h'] . '</td><td class="c">' . (int)$s['prelim_l'] . '</td>'
             . '<td class="c">' . (int)$s['quarter_h'] . '</td><td class="c">' . (int)$s['quarter_l'] . '</td>'
             . '<td class="c">' . (int)$s['semi_h'] . '</td><td class="c">' . (int)$s['semi_l'] . '</td>'
             . '<td class="c">' . (int)$s['final_h'] . '</td><td class="c">' . (int)$s['final_l'] . '</td>'
             . '<td class="c">' . (int)$s['total_h'] . '</td><td class="c">' . (int)$s['total_l'] . '</td></tr>';
        };
        foreach ($rows as $r):
          $age = $r['age_name'] !== '' ? $r['age_name'] : '(No age category)';
          if ($age !== $curAge):
            if ($curAge !== null) { $renderSub($sub, $curAge); }
            $curAge = $age; $sub = $zero; $alt = false;
      ?>
        <tr class="grp"><td colspan="17"><i class="bi"></i>Age Category: <?= e($age) ?></td></tr>
      <?php endif; $sl++; foreach ($subKeys as $k) { $sub[$k] += (int)$r[$k]; } ?>
        <tr class="<?= $alt ? 'alt' : '' ?>">
          <td class="c"><?= $sl ?></td>
          <td class="c"><?= e($r['category']) ?></td>
          <td class="l"><?= e($r['sport_event']) ?></td>
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
      <?php $alt = !$alt; endforeach; if ($curAge !== null) { $renderSub($sub, $curAge); } endif; ?>
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

  <div class="foot-note">
    <strong>EC</strong> = Event Category &nbsp;·&nbsp; <strong>Heats</strong> = primary rounds (approved ÷ tracks, rounded up)
    &nbsp;·&nbsp; <strong>Heats×Laps</strong> = heats × number of laps
  </div>

<script>window.addEventListener('load', function(){ setTimeout(function(){ try{ window.print(); }catch(e){} }, 200); });</script>
</body>
</html>
