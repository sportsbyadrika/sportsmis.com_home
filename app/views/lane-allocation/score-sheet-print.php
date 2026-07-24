<?php
/**
 * Track score sheet (landscape) — one page per heat, blank time/rank/remarks
 * columns for the field referee. Rendered directly by
 * LaneAllocationController::scoreSheet. Expects: $event, $round (roundContext),
 * $heats (heat_no => [assignment,...]).
 */
$numHeats  = (int)$round['num_heats'];
$numTracks = (int)($round['track_num_tracks'] ?? 0);
$evName    = trim((string)($round['sport_event_name'] ?? '')) ?: trim((string)($round['event_code'] ?? ''));
$total     = (int)($round['approved'] ?? 0);
$chest = fn($n) => $n ? '#' . (string)(int)$n : '';
$fmtDob = function ($d) {
    $d = trim((string)$d);
    return ($d !== '' && ($ts = strtotime($d))) ? date('d M Y', $ts) : '';
};
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Score Sheet — <?= e($evName) ?> · <?= e($round['round_name']) ?></title>
<style>
  @page {
    size: A4 landscape;
    margin: 10mm 10mm 14mm 10mm;
    @bottom-right { content: "Page " counter(page) " of " counter(pages); font-size: 9pt; color: #555; }
  }
  * { font-family: Arial, "DejaVu Sans", sans-serif; }
  html, body { background:#fff; color:#111; margin:0; }
  .heat-page { page-break-after: always; }
  .heat-page:last-child { page-break-after: auto; }
  .doc-head { display:flex; align-items:center; gap:12px; border-bottom:2px solid #333; padding-bottom:6px; margin-bottom:6px; }
  .doc-head img { width:46px; height:46px; object-fit:contain; }
  .doc-head h1 { font-size:14pt; margin:0; }
  .doc-head .sub { font-size:9.5pt; color:#555; }
  .heat-head { display:flex; flex-wrap:wrap; gap:8px; align-items:baseline; margin:8px 0 4px; }
  .heat-head .lbl { font-size:12pt; font-weight:bold; }
  .heat-head .meta { font-size:10pt; color:#333; }
  table { width:100%; border-collapse:collapse; table-layout:fixed; font-size:10.5pt; }
  th, td { border:1px solid #333; padding:6px 6px; vertical-align:middle; word-wrap:break-word; }
  thead th { background:#eee; text-align:center; font-size:9.5pt; text-transform:uppercase; }
  td.c { text-align:center; }
  .blank td { height:26px; }
  col.c-sl{width:5%} col.c-tr{width:6%} col.c-ch{width:8%} col.c-nm{width:22%}
  col.c-db{width:11%} col.c-in{width:20%} col.c-tm{width:10%} col.c-rk{width:6%} col.c-rm{width:12%}
</style>
</head>
<body>
<?php for ($h = 1; $h <= $numHeats; $h++):
  $rows = $heats[$h] ?? [];
  // Sort by track and pad blank lines up to the track count.
  usort($rows, fn($a, $b) => (int)$a['track_no'] <=> (int)$b['track_no']);
?>
  <div class="heat-page">
    <div class="doc-head">
      <?php if (!empty($event['logo'])): ?><img src="<?= e($event['logo']) ?>" alt=""><?php endif; ?>
      <div>
        <h1><?= e($round['event_name']) ?></h1>
        <div class="sub"><strong><?= e($evName) ?></strong> &middot; Total Athletes: <?= $total ?></div>
      </div>
    </div>

    <div class="heat-head">
      <span class="lbl"><?= e($round['round_name']) ?></span>
      <span class="meta">— Heat <?= $h ?> of <?= $numHeats ?> heat<?= $numHeats === 1 ? '' : 's' ?></span>
      <span class="meta ms-auto" style="margin-left:auto"><?= $numTracks ?> track<?= $numTracks === 1 ? '' : 's' ?></span>
    </div>

    <table>
      <colgroup>
        <col class="c-sl"><col class="c-tr"><col class="c-ch"><col class="c-nm"><col class="c-db">
        <col class="c-in"><col class="c-tm"><col class="c-rk"><col class="c-rm">
      </colgroup>
      <thead>
        <tr>
          <th>Sl. No</th><th>Track</th><th>Chest No</th><th>Name of Athlete</th><th>DOB</th>
          <th>Name of Institution</th><th>Time</th><th>Rank</th><th>Remarks</th>
        </tr>
      </thead>
      <tbody>
        <?php $sl = 0; foreach ($rows as $a): $sl++; ?>
          <tr>
            <td class="c"><?= $sl ?></td>
            <td class="c"><?= (int)$a['track_no'] ?></td>
            <td class="c"><?= e($chest($a['competitor_number'])) ?></td>
            <td><?= e($a['athlete_name']) ?></td>
            <td class="c"><?= e($fmtDob($a['date_of_birth'] ?? '')) ?></td>
            <td><?= e($a['unit_name'] ?? '') ?></td>
            <td></td><td></td><td></td>
          </tr>
        <?php endforeach; ?>
        <?php
          // Pad to the track count so every lane has a printed line.
          for ($p = count($rows); $p < max($numTracks, 1); $p++): ?>
          <tr class="blank">
            <td class="c"><?= $p + 1 ?></td>
            <td class="c"><?= $numTracks > 0 ? ($p + 1) : '' ?></td>
            <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
          </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>
<?php endfor; ?>

<script>window.addEventListener('load', function(){ setTimeout(function(){ try{ window.print(); }catch(e){} }, 200); });</script>
</body>
</html>
