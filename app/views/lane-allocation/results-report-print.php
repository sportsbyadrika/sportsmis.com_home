<?php
/**
 * Results report (landscape) — every heat of a round in one document. Each heat
 * is a block with a small heading and a table of Rank, Track, Chest No, Name of
 * Athlete, Institution, Time and Qualified. Rows are ordered by rank (blank
 * ranks last), then track. Rendered directly by
 * LaneAllocationController::resultsReport.
 * Expects: $event, $round (roundContext), $heats (heat_no => [assignment,...]).
 */
$numHeats = (int)$round['num_heats'];
$evName   = trim((string)($round['sport_event_name'] ?? '')) ?: trim((string)($round['event_code'] ?? ''));
$total    = (int)($round['approved'] ?? 0);
$numLaps  = (int)($round['track_num_laps'] ?? 0);
$chest = fn($n) => $n ? (string)(int)$n : '';
// Numeric heat number -> spreadsheet-style letter (1->A, 26->Z, 27->AA).
$heatLetter = function (int $n): string {
    $s = '';
    while ($n > 0) { $n--; $s = chr(65 + $n % 26) . $s; $n = intdiv($n, 26); }
    return $s;
};
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Results — <?= e($evName) ?> · <?= e($round['round_name']) ?></title>
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
  .doc-head .round { margin-left:auto; text-align:right; }
  .doc-head .round .badge { display:inline-block; border:1px solid #333; border-radius:4px; padding:3px 10px; font-size:11pt; font-weight:bold; }
  .doc-head .round .laps { font-size:9pt; color:#555; margin-top:3px; }
  .heat-block { page-break-inside: avoid; margin-bottom:10px; }
  .heat-head { display:flex; align-items:baseline; gap:8px; margin:6px 0 3px; }
  .heat-head .lbl { font-size:11.5pt; font-weight:bold; }
  .heat-head .meta { font-size:9pt; color:#333; }
  table { width:100%; border-collapse:collapse; table-layout:fixed; font-size:10pt; }
  th, td { border:1px solid #333; padding:4px 6px; vertical-align:middle; word-wrap:break-word; }
  thead th { background:#eee; text-align:center; font-size:9pt; text-transform:uppercase; }
  td.c { text-align:center; }
  col.c-rk{width:7%} col.c-tr{width:7%} col.c-ch{width:10%} col.c-nm{width:28%}
  col.c-in{width:28%} col.c-tm{width:12%} col.c-ql{width:8%}
</style>
</head>
<body>
  <div class="doc-head">
    <?php if (!empty($event['logo'])): ?><img src="<?= e($event['logo']) ?>" alt=""><?php endif; ?>
    <div>
      <h1><?= e($round['event_name']) ?></h1>
      <div class="sub"><strong><?= e($evName) ?></strong> &middot; Results &middot; Total Athletes: <?= $total ?></div>
    </div>
    <div class="round">
      <span class="badge"><?= e($round['round_name']) ?></span>
      <?php if ($numLaps > 0): ?><div class="laps">No. of Laps: <?= $numLaps ?></div><?php endif; ?>
    </div>
  </div>

<?php for ($h = 1; $h <= $numHeats; $h++):
  $rows = $heats[$h] ?? [];
  // Order by rank (blank ranks last), then track.
  usort($rows, function ($a, $b) {
    $ra = (int)($a['result_rank'] ?? 0); $rb = (int)($b['result_rank'] ?? 0);
    $ra = $ra > 0 ? $ra : PHP_INT_MAX;   $rb = $rb > 0 ? $rb : PHP_INT_MAX;
    if ($ra !== $rb) return $ra <=> $rb;
    return (int)$a['track_no'] <=> (int)$b['track_no'];
  });
?>
  <div class="heat-block">
    <div class="heat-head">
      <span class="lbl">Heat <?= $heatLetter($h) ?> <span class="meta">(<?= $h ?> of <?= $numHeats ?>)</span></span>
    </div>
    <table>
      <colgroup>
        <col class="c-rk"><col class="c-tr"><col class="c-ch"><col class="c-nm">
        <col class="c-in"><col class="c-tm"><col class="c-ql">
      </colgroup>
      <thead>
        <tr>
          <th>Rank</th><th>Track</th><th>Chest No</th><th>Name of Athlete</th>
          <th>Name of Institution</th><th>Time</th><th>Qualified</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="7" class="c" style="padding:10px;color:#666">No athletes assigned to this heat.</td></tr>
        <?php else: foreach ($rows as $a): ?>
          <tr>
            <td class="c"><?= (int)($a['result_rank'] ?? 0) > 0 ? (int)$a['result_rank'] : '' ?></td>
            <td class="c"><?= (int)$a['track_no'] ?></td>
            <td class="c"><?= e($chest($a['competitor_number'])) ?></td>
            <td><?= e($a['athlete_name']) ?></td>
            <td><?= e($a['unit_name'] ?? '') ?></td>
            <td class="c"><?= e($a['result_time'] ?? '') ?></td>
            <td class="c"><?= !empty($a['is_qualified']) ? 'Yes' : '' ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
<?php endfor; ?>

<script>window.addEventListener('load', function(){ setTimeout(function(){ try{ window.print(); }catch(e){} }, 200); });</script>
</body>
</html>
