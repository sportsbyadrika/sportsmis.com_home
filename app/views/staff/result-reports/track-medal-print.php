<?php
/**
 * Athletics / Skating — Medal Tally (print, portrait).
 * Expects: $event, $unit_tally, $events.
 */
$evName  = trim((string)($event['name'] ?? ''));
$age_top = $age_top ?? [];
$section = in_array(($section ?? 'all'), ['units', 'events', 'agetop'], true) ? $section : 'all';
$showUnits  = $section === 'all' || $section === 'units';
$showEvents = $section === 'all' || $section === 'events';
$showAgeTop = $section === 'all' || $section === 'agetop';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Medal Tally — <?= e($evName) ?></title>
<style>
  @page {
    size: A4 portrait;
    margin: 12mm 12mm 14mm 12mm;
    @bottom-right { content: "Page " counter(page) " of " counter(pages); font-size: 9pt; color: #555; }
  }
  * { font-family: Arial, "DejaVu Sans", sans-serif; }
  html, body { background:#fff; color:#111; margin:0; }
  .doc-head { display:flex; align-items:center; gap:12px; border-bottom:2px solid #333; padding-bottom:6px; margin-bottom:8px; }
  .doc-head img { width:44px; height:44px; object-fit:contain; }
  .doc-head h1 { font-size:14pt; margin:0; }
  .doc-head .sub { font-size:9.5pt; color:#555; margin-top:2px; }
  h2 { font-size:11.5pt; margin:12px 0 4px; }
  table { width:100%; border-collapse:collapse; table-layout:fixed; font-size:9.5pt; }
  th, td { border:1px solid #333; padding:4px 6px; vertical-align:top; word-wrap:break-word; }
  thead th { background:#eee; text-align:center; font-size:8.5pt; text-transform:uppercase;
             -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  td.c { text-align:center; } td.r { text-align:right; }
  tr { page-break-inside: avoid; }
  .muted { color:#555; font-size:8.5pt; }
</style>
</head>
<body>
  <div class="doc-head">
    <?php if (!empty($event['logo'])): ?><img src="<?= e($event['logo']) ?>" alt=""><?php endif; ?>
    <div>
      <h1><?= e($evName) ?></h1>
      <div class="sub">Medal Tally (Athletics / Skating)</div>
    </div>
  </div>

  <?php if ($showUnits): ?>
  <h2>Unit-wise Points</h2>
  <table>
    <colgroup><col style="width:10%"><col><col style="width:14%"><col style="width:14%"><col style="width:14%"><col style="width:14%"></colgroup>
    <thead>
      <tr><th>Rank</th><th style="text-align:left">Unit / Institution</th><th>Gold</th><th>Silver</th><th>Bronze</th><th>Points</th></tr>
    </thead>
    <tbody>
      <?php if (empty($unit_tally)): ?>
        <tr><td colspan="6" class="c muted" style="padding:10px">No medals recorded yet.</td></tr>
      <?php else: $i = 0; foreach ($unit_tally as $u): $i++; ?>
        <tr>
          <td class="c"><?= $i ?></td>
          <td><?= e($u['unit']) ?></td>
          <td class="c"><?= (int)$u['g'] ?></td>
          <td class="c"><?= (int)$u['s'] ?></td>
          <td class="c"><?= (int)$u['b'] ?></td>
          <td class="r"><strong><?= (int)$u['points'] ?></strong></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <?php if ($showEvents): ?>
  <h2>Event-wise Winners</h2>
  <table>
    <colgroup><col style="width:6%"><col style="width:30%"><col style="width:8%"><col><col><col></colgroup>
    <thead>
      <tr><th>Sl.</th><th style="text-align:left">Sport Event</th><th>Type</th><th>First</th><th>Second</th><th>Third</th></tr>
    </thead>
    <tbody>
      <?php if (empty($events)): ?>
        <tr><td colspan="6" class="c muted" style="padding:10px">No event winners recorded yet.</td></tr>
      <?php else:
        // Group by the day the result was updated (chronological), undated last.
        $evGroups = [];
        foreach ($events as $ev) { $d = trim((string)($ev['result_date'] ?? '')); $evGroups[$d][] = $ev; }
        $gDates = array_values(array_filter(array_keys($evGroups), fn($d) => $d !== ''));
        sort($gDates);
        $dayNo = [];
        foreach ($gDates as $ix => $d) { $dayNo[$d] = $ix + 1; }
        if (isset($evGroups[''])) $gDates[] = '';
        $sl = 0;
        foreach ($gDates as $gd): $grp = $evGroups[$gd];
      ?>
        <tr>
          <td colspan="6" style="text-align:left;font-weight:bold;background:#e5e5e5;border-top:2px solid #333;-webkit-print-color-adjust:exact;print-color-adjust:exact">
            <?php if ($gd !== ''): ?>Day <?= $dayNo[$gd] ?> &mdash; <?= e(formatDate($gd, 'd M Y')) ?> (<?= count($grp) ?>)
            <?php else: ?>Results awaited (<?= count($grp) ?>)<?php endif; ?>
          </td>
        </tr>
        <?php foreach ($grp as $ev): $sl++; ?>
        <tr>
          <td class="c"><?= $sl ?></td>
          <td><?= e($ev['sport_event']) ?></td>
          <td class="c"><?= e($ev['type']) ?></td>
          <?php for ($rk = 1; $rk <= 3; $rk++): $list = $ev['places'][$rk] ?? []; if (!is_array($list)) $list = $list ? [$list] : []; ?>
            <td>
              <?php if (empty($list)): ?>—
              <?php elseif (count($list) === 1): $p = $list[0]; ?>
                <?php if ($p['chest'] !== ''): ?><strong><?= e($p['chest']) ?></strong> <?php endif; ?><?= e($p['name']) ?>
                <?php if ($p['unit'] !== ''): ?><div class="muted"><?= e($p['unit']) ?></div><?php endif; ?>
              <?php else: ?>
                <?php foreach ($list as $ix => $p): ?><?= $ix ? ', ' : '' ?><?php if ($p['chest'] !== ''): ?><strong><?= e($p['chest']) ?></strong> <?php endif; ?><?= e($p['name']) ?><?php if ($p['unit'] !== ''): ?> <span class="muted">(<?= e($p['unit']) ?>)</span><?php endif; ?><?php endforeach; ?>
              <?php endif; ?>
            </td>
          <?php endfor; ?>
        </tr>
        <?php endforeach; ?>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <?php if ($showAgeTop): ?>
  <h2>Age-category Top Athletes</h2>
  <?php if (empty($age_top)): ?>
    <p class="muted">No published individual medals yet.</p>
  <?php else:
    $atLbl = [0 => '1st', 1 => '2nd', 2 => '3rd'];
    foreach ($age_top as $ag): ?>
    <h3 style="font-size:10.5pt;margin:10px 0 3px"><?= e($ag['age']) ?></h3>
    <table style="margin-bottom:6px">
      <colgroup><col style="width:12%"><col style="width:8%"><col><col style="width:40%"><col style="width:12%"></colgroup>
      <thead>
        <tr><th>Gender</th><th>Pos</th><th style="text-align:left">Athlete</th><th style="text-align:left">Unit / Institution</th><th>Points</th></tr>
      </thead>
      <tbody>
        <?php foreach (($ag['genders'] ?? []) as $gp): ?>
          <?php foreach ($gp['athletes'] as $i => $at): ?>
            <tr>
              <td class="c"><?= $i === 0 ? e($gp['gender']) : '' ?></td>
              <td class="c"><?= $atLbl[$i] ?? ($i + 1) ?></td>
              <td><?php if ($at['chest'] !== ''): ?><strong><?= e($at['chest']) ?></strong> <?php endif; ?><?= e($at['name']) ?></td>
              <td><?= e($at['unit']) ?></td>
              <td class="r"><strong><?= (int)$at['points'] ?></strong>
                <span class="muted"> (G<?= (int)$at['gold'] ?> S<?= (int)$at['silver'] ?> B<?= (int)$at['bronze'] ?>)</span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endforeach; endif; ?>
  <?php endif; ?>

<script>window.addEventListener('load', function(){ setTimeout(function(){ try{ window.print(); }catch(e){} }, 200); });</script>
</body>
</html>
