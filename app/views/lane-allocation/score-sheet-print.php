<?php
/**
 * Heats participation list — four heats per page. Compact table with Track,
 * Chest No, Name of Athlete, Name of Institution and a small blank Remarks
 * column. Heats are lettered A, B, C… in numeric order. Orientation is chosen
 * by the caller (?orientation=portrait|landscape, default portrait): portrait
 * stacks 4 heats down the page, landscape lays them out 2×2.
 * Rendered directly by LaneAllocationController::scoreSheet.
 * Expects: $event, $round (roundContext), $heats (heat_no => [assignment,...]),
 *          $orientation.
 */
$numHeats    = (int)$round['num_heats'];
$numTracks   = (int)($round['track_num_tracks'] ?? 0);
$evName      = trim((string)($round['sport_event_name'] ?? '')) ?: trim((string)($round['event_code'] ?? ''));
$total       = (int)($round['approved'] ?? 0);
$orientation = ($orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
$isLandscape = $orientation === 'landscape';
$perPage     = 4; // heats per printed page
$isTeam      = !empty($is_team);
$chest = fn($n) => $n ? '#' . (string)(int)$n : '';
$rowCode = fn($a) => $isTeam ? (trim((string)($a['relay_code'] ?? '')) ?: '—') : ($a['competitor_number'] ? '#' . (int)$a['competitor_number'] : '');
$rowName = fn($a) => $isTeam ? (string)($a['team_name'] ?? '') : (string)($a['athlete_name'] ?? '');
$rowMem  = fn($a) => $isTeam ? (string)($a['members'] ?? '') : '';
// Team members rendered one per line as "<bold BIB> Name". The members string
// is "BIB Name, BIB Name" (playing order); split it and bold each leading BIB.
$membersHtml = function (string $members): string {
    if (trim($members) === '') return '';
    $out = [];
    foreach (explode(',', $members) as $part) {
        $p = trim($part);
        if ($p === '') continue;
        if (preg_match('/^(\d+)\s+(.*)$/', $p, $m)) {
            $out[] = '<strong>' . e($m[1]) . '</strong> ' . e($m[2]);
        } else {
            $out[] = e($p);
        }
    }
    return implode('<br>', $out);
};
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
<title>Participation List — <?= e($evName) ?> · <?= e($round['round_name']) ?></title>
<style>
  @page {
    size: A4 <?= $isLandscape ? 'landscape' : 'portrait' ?>;
    margin: 8mm 10mm 12mm 10mm;
    @bottom-right { content: "Page " counter(page) " of " counter(pages); font-size: 9pt; color: #555; }
  }
  * { font-family: Arial, "DejaVu Sans", sans-serif; }
  html, body { background:#fff; color:#111; margin:0; }
  .sheet-page { page-break-after: always; }
  .sheet-page:last-child { page-break-after: auto; }
  .doc-head { display:flex; align-items:center; gap:10px; border-bottom:2px solid #333; padding-bottom:5px; margin-bottom:6px; }
  .doc-head img { width:38px; height:38px; object-fit:contain; }
  .doc-head .titles { flex:1 1 auto; min-width:0; }
  .doc-head h1 { font-size:13pt; margin:0; }
  .doc-head .sub { font-size:9pt; color:#555; }
  .doc-head .round-label {
    margin-left:auto; flex:0 0 auto;
    background:#ffd400; color:#111; font-weight:bold; font-size:12pt;
    padding:5px 14px; border-radius:5px; border:1px solid #caa500; white-space:nowrap;
    -webkit-print-color-adjust:exact; print-color-adjust:exact;
  }
  .heats-wrap { display:flex; flex-wrap:wrap; gap:8px 12px; }
  .heat-block { page-break-inside: avoid; width:<?= $isLandscape ? 'calc(50% - 6px)' : '100%' ?>; }
  .heat-head { display:flex; flex-wrap:wrap; gap:8px; align-items:baseline; margin:4px 0 2px; }
  .heat-head .lbl { font-size:11pt; font-weight:bold; }
  .heat-head .meta { font-size:9pt; color:#333; }
  table { width:100%; border-collapse:collapse; table-layout:fixed; font-size:10pt; }
  th, td { border:1px solid #333; padding:3px 6px; vertical-align:middle; word-wrap:break-word; }
  thead th { background:#eee; text-align:center; font-size:9pt; text-transform:uppercase; }
  td.c { text-align:center; }
  .blank td { height:20px; }
  col.c-tr{width:9%} col.c-ch{width:12%} col.c-nm{width:34%} col.c-in{width:33%} col.c-rm{width:12%}
</style>
</head>
<body>
<?php for ($h = 1; $h <= $numHeats; $h++):
  // Open a new page block at the start of every group of $perPage heats.
  if (($h - 1) % $perPage === 0): ?>
  <div class="sheet-page">
    <div class="doc-head">
      <?php if (!empty($event['logo'])): ?><img src="<?= e($event['logo']) ?>" alt=""><?php endif; ?>
      <div class="titles">
        <h1><?= e($round['event_name']) ?></h1>
        <div class="sub"><strong><?= e($evName) ?></strong> &middot; Total Athletes: <?= $total ?></div>
      </div>
      <div class="round-label"><?= e($round['round_name']) ?></div>
    </div>
    <div class="heats-wrap">
  <?php endif; ?>

  <?php
    $rows = $heats[$h] ?? [];
    usort($rows, fn($a, $b) => (int)$a['track_no'] <=> (int)$b['track_no']);
  ?>
      <div class="heat-block">
        <div class="heat-head">
          <span class="lbl">Heat Team <?= $heatLetter($h) ?> (<?= $h ?> of <?= $numHeats ?>)</span>
          <span class="meta ms-auto" style="margin-left:auto"><?= $numTracks ?> track<?= $numTracks === 1 ? '' : 's' ?></span>
        </div>
        <table>
          <colgroup>
            <col class="c-tr"><col class="c-ch"><col class="c-nm"><col class="c-in"><col class="c-rm">
          </colgroup>
          <thead>
            <tr>
              <th>Track</th><th><?= $isTeam ? 'Team Code' : 'Chest No' ?></th><th><?= $isTeam ? 'Team Members' : 'Name of Athlete' ?></th><th>Name of Institution</th><th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $a): ?>
              <tr>
                <td class="c"><?= (int)$a['track_no'] ?></td>
                <td class="c"><?= e($rowCode($a)) ?></td>
                <td><?php if ($isTeam): ?><?= $membersHtml($rowMem($a)) ?><?php else: ?><?= e($rowName($a)) ?><?php endif; ?></td>
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
    </div><!-- .heats-wrap -->
  </div><!-- .sheet-page -->
  <?php endif; ?>
<?php endfor; ?>

<script>window.addEventListener('load', function(){ setTimeout(function(){ try{ window.print(); }catch(e){} }, 200); });</script>
</body>
</html>
