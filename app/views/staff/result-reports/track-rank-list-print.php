<?php
/**
 * Athletics / Skating — Event-wise Rank List (print, landscape). One table per
 * sport-event; each participant's round-wise Time / Rank / Qualified results.
 * Rendered directly by EventStaffController::trackRankListPrint.
 * Expects: $event, $groups.
 */
$evName = trim((string)($event['name'] ?? ''));
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Event-wise Rank List — <?= e($evName) ?></title>
<style>
  @page {
    size: A4 landscape;
    margin: 10mm 10mm 12mm 10mm;
    @bottom-right { content: "Page " counter(page) " of " counter(pages); font-size: 9pt; color: #555; }
  }
  * { font-family: Arial, "DejaVu Sans", sans-serif; }
  html, body { background:#fff; color:#111; margin:0; }
  .doc-head { display:flex; align-items:center; gap:12px; border-bottom:2px solid #333; padding-bottom:6px; margin-bottom:8px; }
  .doc-head img { width:42px; height:42px; object-fit:contain; }
  .doc-head h1 { font-size:14pt; margin:0; }
  .doc-head .sub { font-size:9.5pt; color:#555; margin-top:2px; }
  .grp { page-break-inside: avoid; margin-bottom:12px; }
  .grp-head { font-size:11.5pt; font-weight:bold; margin:4px 0 3px; }
  .grp-head .meta { font-weight:normal; font-size:9pt; color:#555; }
  table { width:100%; border-collapse:collapse; table-layout:fixed; font-size:9pt; }
  th, td { border:1px solid #333; padding:3px 5px; vertical-align:middle; word-wrap:break-word; }
  thead th { background:#eee; text-align:center; font-size:8pt; text-transform:uppercase;
             -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  td.c { text-align:center; }
</style>
</head>
<body>
  <div class="doc-head">
    <?php if (!empty($event['logo'])): ?><img src="<?= e($event['logo']) ?>" alt=""><?php endif; ?>
    <div>
      <h1><?= e($evName) ?></h1>
      <div class="sub">Event-wise Rank List (Athletics / Skating) &middot; <?= count($groups) ?> sport-event<?= count($groups) === 1 ? '' : 's' ?></div>
    </div>
  </div>

  <?php if (empty($groups)): ?>
    <p style="color:#666">No sport-events with configured rounds found for this selection.</p>
  <?php else: foreach ($groups as $g):
    $nr = count($g['rounds']);
    // Column widths: fixed lead columns, rounds share the rest.
    $lead = 4; $roundCols = $nr * 3;
  ?>
    <div class="grp">
      <div class="grp-head">
        <?= e($g['sport_event']) ?><?php if ($g['gender'] !== ''): ?> — <?= e(ucfirst($g['gender'])) ?><?php endif; ?>
        <span class="meta">
          (<?= e($g['category']) ?><?php if ($g['age_name'] !== ''): ?> · <?= e($g['age_name']) ?><?php endif; ?><?php if ($g['event_code'] !== ''): ?> · <?= e($g['event_code']) ?><?php endif; ?>)
        </span>
      </div>
      <table>
        <colgroup>
          <col style="width:5%"><col style="width:9%"><col style="width:22%"><col style="width:22%">
          <?php for ($i = 0; $i < $roundCols; $i++): ?><col style="width:<?= $roundCols ? (42 / $roundCols) : 0 ?>%"><?php endfor; ?>
        </colgroup>
        <thead>
          <tr>
            <th rowspan="2">Sl.</th>
            <th rowspan="2">Chest</th>
            <th rowspan="2">Name of Athlete</th>
            <th rowspan="2">Unit / Institution</th>
            <?php foreach ($g['rounds'] as $rd): ?>
              <th colspan="3"><?= e($rd['name']) ?></th>
            <?php endforeach; ?>
          </tr>
          <tr>
            <?php foreach ($g['rounds'] as $rd): ?>
              <th>Time</th><th>Rank</th><th>Qual.</th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($g['athletes'])): ?>
            <tr><td colspan="<?= $lead + $roundCols ?>" class="c" style="color:#666;padding:8px">No results entered yet.</td></tr>
          <?php else: $sl = 0; foreach ($g['athletes'] as $a): $sl++; ?>
            <tr>
              <td class="c"><?= $sl ?></td>
              <td class="c"><?= $a['competitor_number'] > 0 ? (int)$a['competitor_number'] : '' ?></td>
              <td><?= e($a['athlete_name']) ?></td>
              <td><?= e($a['unit_name'] ?: '') ?></td>
              <?php foreach ($g['rounds'] as $rd):
                $res = $a['results'][$rd['id']] ?? null; ?>
                <td class="c"><?= $res ? e($res['time']) : '' ?></td>
                <td class="c"><?= ($res && $res['rank'] > 0) ? (int)$res['rank'] : '' ?></td>
                <td class="c"><?= ($res && $res['qualified']) ? 'Yes' : '' ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  <?php endforeach; endif; ?>

<script>window.addEventListener('load', function(){ setTimeout(function(){ try{ window.print(); }catch(e){} }, 200); });</script>
</body>
</html>
