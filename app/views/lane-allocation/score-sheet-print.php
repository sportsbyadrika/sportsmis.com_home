<?php
/**
 * Heats participation list (landscape) — three heats per page. Compact table
 * with Track, Chest No, Name of Athlete, Name of Institution and a small blank
 * Remarks column. Rendered directly by LaneAllocationController::scoreSheet.
 * Expects: $event, $round (roundContext), $heats (heat_no => [assignment,...]).
 */
$numHeats  = (int)$round['num_heats'];
$numTracks = (int)($round['track_num_tracks'] ?? 0);
$evName    = trim((string)($round['sport_event_name'] ?? '')) ?: trim((string)($round['event_code'] ?? ''));
$total     = (int)($round['approved'] ?? 0);
$chest = fn($n) => $n ? '#' . (string)(int)$n : '';
$perPage = 3; // heats per printed page
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Participation List — <?= e($evName) ?> · <?= e($round['round_name']) ?></title>
<style>
  @page {
    size: A4 landscape;
    margin: 8mm 10mm 12mm 10mm;
    @bottom-right { content: "Page " counter(page) " of " counter(pages); font-size: 9pt; color: #555; }
  }
  * { font-family: Arial, "DejaVu Sans", sans-serif; }
  html, body { background:#fff; color:#111; margin:0; }
  .sheet-page { page-break-after: always; }
  .sheet-page:last-child { page-break-after: auto; }
  .doc-head { display:flex; align-items:center; gap:10px; border-bottom:2px solid #333; padding-bottom:5px; margin-bottom:4px; }
  .doc-head img { width:38px; height:38px; object-fit:contain; }
  .doc-head h1 { font-size:13pt; margin:0; }
  .doc-head .sub { font-size:9pt; color:#555; }
  .heat-block { page-break-inside: avoid; margin-bottom:8px; }
  .heat-head { display:flex; flex-wrap:wrap; gap:8px; align-items:baseline; margin:6px 0 2px; }
  .heat-head .lbl { font-size:11pt; font-weight:bold; }
  .heat-head .meta { font-size:9pt; color:#333; }
  table { width:100%; border-collapse:collapse; table-layout:fixed; font-size:10pt; }
  th, td { border:1px solid #333; padding:3px 6px; vertical-align:middle; word-wrap:break-word; }
  thead th { background:#eee; text-align:center; font-size:9pt; text-transform:uppercase; }
  td.c { text-align:center; }
  .blank td { height:22px; }
  col.c-tr{width:8%} col.c-ch{width:11%} col.c-nm{width:35%} col.c-in{width:34%} col.c-rm{width:12%}
</style>
</head>
<body>
<?php for ($h = 1; $h <= $numHeats; $h++):
  // Open a new page block at the start of every group of $perPage heats.
  if (($h - 1) % $perPage === 0): ?>
  <div class="sheet-page">
    <div class="doc-head">
      <?php if (!empty($event['logo'])): ?><img src="<?= e($event['logo']) ?>" alt=""><?php endif; ?>
      <div>
        <h1><?= e($round['event_name']) ?></h1>
        <div class="sub"><strong><?= e($evName) ?></strong> &middot; <?= e($round['round_name']) ?> &middot; Total Athletes: <?= $total ?></div>
      </div>
    </div>
  <?php endif; ?>

  <?php
    $rows = $heats[$h] ?? [];
    usort($rows, fn($a, $b) => (int)$a['track_no'] <=> (int)$b['track_no']);
  ?>
    <div class="heat-block">
      <div class="heat-head">
        <span class="lbl">Heat <?= $h ?> of <?= $numHeats ?></span>
        <span class="meta ms-auto" style="margin-left:auto"><?= $numTracks ?> track<?= $numTracks === 1 ? '' : 's' ?></span>
      </div>
      <table>
        <colgroup>
          <col class="c-tr"><col class="c-ch"><col class="c-nm"><col class="c-in"><col class="c-rm">
        </colgroup>
        <thead>
          <tr>
            <th>Track</th><th>Chest No</th><th>Name of Athlete</th><th>Name of Institution</th><th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $a): ?>
            <tr>
              <td class="c"><?= (int)$a['track_no'] ?></td>
              <td class="c"><?= e($chest($a['competitor_number'])) ?></td>
              <td><?= e($a['athlete_name']) ?></td>
              <td><?= e($a['unit_name'] ?? '') ?></td>
              <td></td>
            </tr>
          <?php endforeach; ?>
          <?php
            // Pad to the track count so every lane has a printed line.
            for ($p = count($rows); $p < max($numTracks, 1); $p++): ?>
            <tr class="blank">
              <td class="c"><?= $numTracks > 0 ? ($p + 1) : '' ?></td>
              <td></td><td></td><td></td><td></td>
            </tr>
          <?php endfor; ?>
        </tbody>
      </table>
    </div>

  <?php // Close the page block after every group of $perPage heats or at the very end.
    if ($h % $perPage === 0 || $h === $numHeats): ?>
  </div>
  <?php endif; ?>
<?php endfor; ?>

<script>window.addEventListener('load', function(){ setTimeout(function(){ try{ window.print(); }catch(e){} }, 200); });</script>
</body>
</html>
