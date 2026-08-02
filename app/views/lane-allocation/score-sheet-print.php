<?php
/**
 * Heats participation list. Compact table per heat with Track, Chest No, Name
 * of Athlete, Name of Institution and a small blank Remarks column. Heats are
 * numbered Heat 1, Heat 2, … in numeric order. Orientation is chosen by the
 * caller (?orientation=portrait|landscape, default portrait): portrait stacks
 * heats one per row, landscape lays them out two per row. When ?photo=1, an
 * athlete photo is shown (a Photo column for individual events; a picture
 * beside each member for team events).
 *
 * The document heading sits in a <thead> of an outer wrapper table, so the
 * browser REPEATS it on every printed page, and each heat is a table row so
 * pages break cleanly between heats (no "heading only" pages).
 *
 * Rendered directly by LaneAllocationController::scoreSheet.
 * Expects: $event, $round (roundContext), $heats (heat_no => [assignment,...]),
 *          $orientation, $show_photo.
 */
$numHeats    = (int)$round['num_heats'];
$numTracks   = (int)($round['track_num_tracks'] ?? 0);
$evName      = trim((string)($round['sport_event_name'] ?? '')) ?: trim((string)($round['event_code'] ?? ''));
$total       = (int)($round['approved'] ?? 0);
$orientation = ($orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
$isLandscape = $orientation === 'landscape';
$cols        = $isLandscape ? 2 : 1;   // heats per row
$isTeam      = !empty($is_team);
$showPhoto   = !empty($show_photo);
$photoCol    = $showPhoto && !$isTeam;   // individual events get a Photo column
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
// A photo box (real image or a grey placeholder to keep rows aligned).
$photoImg = function ($src, int $w = 34, int $h = 40): string {
    if (trim((string)$src) !== '') {
        return '<img src="' . e($src) . '" alt="" style="width:' . $w . 'px;height:' . $h
             . 'px;object-fit:cover;border:1px solid #bbb;border-radius:2px">';
    }
    return '<span style="display:inline-block;width:' . $w . 'px;height:' . $h
         . 'px;background:#f0f0f0;border:1px solid #ddd;border-radius:2px"></span>';
};
// Team members with a photo each: picture + "<bold BIB> Name", one per line.
$teamMembersPhotoHtml = function (array $members) use ($photoImg): string {
    $out = '';
    foreach ($members as $m) {
        $bib  = (int)($m['chest'] ?? 0) > 0 ? (string)(int)$m['chest'] : '';
        $out .= '<div style="display:flex;align-items:center;gap:5px;margin:2px 0">'
              . $photoImg((string)($m['photo'] ?? ''), 24, 28)
              . '<span>' . ($bib !== '' ? '<strong>' . e($bib) . '</strong> ' : '')
              . e((string)($m['athlete_name'] ?? '')) . '</span></div>';
    }
    return $out;
};

// One heat block (heading + its table). Returned as HTML so it can drop into a
// wrapper-table cell.
$heatBlock = function (int $h) use ($heats, $numTracks, $isTeam, $photoCol, $showPhoto,
                                    $rowCode, $rowName, $rowMem, $membersHtml,
                                    $photoImg, $teamMembersPhotoHtml): string {
    $rows = $heats[$h] ?? [];
    usort($rows, fn($a, $b) => (int)$a['track_no'] <=> (int)$b['track_no']);
    $colspan = $photoCol ? 6 : 5;
    ob_start(); ?>
    <div class="heat-block">
      <div class="heat-head">
        <span class="lbl">Heat <?= $h ?></span>
        <span class="meta" style="margin-left:auto"><?= $numTracks ?> track<?= $numTracks === 1 ? '' : 's' ?></span>
      </div>
      <table>
        <colgroup>
          <col class="c-tr"><col class="c-ch"><?php if ($photoCol): ?><col class="c-ph"><?php endif; ?><col class="c-nm"><col class="c-in"><col class="c-rm">
        </colgroup>
        <thead>
          <tr>
            <th>Track</th>
            <th><?= $isTeam ? 'Team Code' : 'Chest No' ?></th>
            <?php if ($photoCol): ?><th>Photo</th><?php endif; ?>
            <th><?= $isTeam ? 'Team Members' : 'Name of Athlete' ?></th>
            <th>Name of Institution</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $a): ?>
            <tr>
              <td class="c"><?= (int)$a['track_no'] ?></td>
              <td class="c"><?= e($rowCode($a)) ?></td>
              <?php if ($photoCol): ?><td class="c"><?= $photoImg($a['photo'] ?? '') ?></td><?php endif; ?>
              <td>
                <?php if ($isTeam && $showPhoto): ?>
                  <?= $teamMembersPhotoHtml($a['member_list'] ?? []) ?>
                <?php elseif ($isTeam): ?>
                  <?= $membersHtml($rowMem($a)) ?>
                <?php else: ?>
                  <?= e($rowName($a)) ?>
                <?php endif; ?>
              </td>
              <td><?= e($a['unit_name'] ?? '') ?></td>
              <td></td>
            </tr>
          <?php endforeach; ?>
          <?php for ($p = count($rows); $p < max($numTracks, 1); $p++): ?>
            <tr class="blank">
              <td class="c"><?= $numTracks > 0 ? ($p + 1) : '' ?></td>
              <td></td>
              <?php for ($cc = 2; $cc < $colspan; $cc++): ?><td></td><?php endfor; ?>
            </tr>
          <?php endfor; ?>
        </tbody>
      </table>
    </div>
    <?php
    return (string)ob_get_clean();
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

  /* Wrapper table — its <thead> repeats the heading on every printed page. */
  table.report { width:100%; border-collapse:collapse; }
  table.report > thead { display:table-header-group; }
  table.report > thead > tr > td { padding:0 0 6px; border:none; }
  table.report > tbody > tr { page-break-inside:avoid; break-inside:avoid; }
  table.report > tbody > tr > td { padding:0 0 8px; border:none; vertical-align:top; }
  <?php if ($isLandscape): ?>
  table.report > tbody > tr > td { width:50%; }
  table.report > tbody > tr > td + td { padding-left:12px; }
  <?php endif; ?>

  .doc-head { display:flex; align-items:center; gap:10px; border-bottom:2px solid #333; padding-bottom:5px; }
  .doc-head img { width:38px; height:38px; object-fit:contain; }
  .doc-head .titles { flex:1 1 auto; min-width:0; }
  .doc-head h1 { font-size:13pt; margin:0; }
  /* Sub head (sport event) is intentionally prominent. */
  .doc-head .sub { font-size:12pt; color:#222; margin-top:2px; }
  .doc-head .round-label {
    margin-left:auto; flex:0 0 auto;
    background:#ffd400; color:#111; font-weight:bold; font-size:12pt;
    padding:5px 14px; border-radius:5px; border:1px solid #caa500; white-space:nowrap;
    -webkit-print-color-adjust:exact; print-color-adjust:exact;
  }

  .heat-block { page-break-inside:avoid; break-inside:avoid; }
  .heat-head { display:flex; flex-wrap:wrap; gap:8px; align-items:baseline; margin:2px 0; }
  .heat-head .lbl { font-size:12pt; font-weight:bold; }
  .heat-head .meta { font-size:9pt; color:#333; }

  /* Inner heat tables (scoped so the wrapper cells stay border-free). */
  .heat-block table { width:100%; border-collapse:collapse; table-layout:fixed; font-size:10pt; }
  .heat-block th, .heat-block td { border:1px solid #333; padding:3px 6px; vertical-align:middle; word-wrap:break-word; }
  .heat-block thead th { background:#eee; text-align:center; font-size:9pt; text-transform:uppercase; }
  .heat-block td.c { text-align:center; }
  .heat-block .blank td { height:<?= $showPhoto ? 44 : 20 ?>px; }
  <?php if ($photoCol): ?>
  col.c-tr{width:8%} col.c-ch{width:11%} col.c-ph{width:13%} col.c-nm{width:27%} col.c-in{width:29%} col.c-rm{width:12%}
  <?php else: ?>
  col.c-tr{width:9%} col.c-ch{width:12%} col.c-nm{width:34%} col.c-in{width:33%} col.c-rm{width:12%}
  <?php endif; ?>
</style>
</head>
<body>
  <table class="report">
    <thead>
      <tr>
        <td colspan="<?= $cols ?>">
          <div class="doc-head">
            <?php if (!empty($event['logo'])): ?><img src="<?= e($event['logo']) ?>" alt=""><?php endif; ?>
            <div class="titles">
              <h1><?= e($round['event_name']) ?></h1>
              <div class="sub"><strong><?= e($evName) ?></strong> &middot; Total Athletes: <?= $total ?></div>
            </div>
            <div class="round-label"><?= e($round['round_name']) ?></div>
          </div>
        </td>
      </tr>
    </thead>
    <tbody>
      <?php for ($h = 1; $h <= $numHeats; $h += $cols): ?>
        <tr>
          <?php for ($c = 0; $c < $cols; $c++): $hn = $h + $c; ?>
            <?php if ($hn > $numHeats): ?>
              <td></td>
            <?php else: ?>
              <td><?= $heatBlock($hn) ?></td>
            <?php endif; ?>
          <?php endfor; ?>
        </tr>
      <?php endfor; ?>
    </tbody>
  </table>

<script>window.addEventListener('load', function(){ setTimeout(function(){ try{ window.print(); }catch(e){} }, 200); });</script>
</body>
</html>
