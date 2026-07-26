<?php
/**
 * Participants list (portrait) for one round — a flat roster of every approved
 * participant. Heading carries the event logo + name, the sport-event name, the
 * number of laps (track events) and the round label on the right. Table: Sl.No,
 * Chest No, Name of Athlete, Date of Birth, Name of School.
 * Rendered directly by LaneAllocationController::participantsList.
 * Expects: $event, $round (roundContext), $participants.
 */
$evName   = trim((string)($round['sport_event_name'] ?? '')) ?: trim((string)($round['event_code'] ?? ''));
$isTrack  = (string)($round['track_event_type'] ?? '') === 'track';
$numLaps  = (int)($round['track_num_laps'] ?? 0);
$total    = count($participants);
$chest = fn($n) => $n ? '#' . (string)(int)$n : '';
$fmtDob = function ($d) {
    $d = trim((string)$d);
    return ($d !== '' && ($ts = strtotime($d))) ? date('d M Y', $ts) : '';
};
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Participants List — <?= e($evName) ?> · <?= e($round['round_name']) ?></title>
<style>
  @page {
    size: A4 portrait;
    margin: 12mm 12mm 14mm 12mm;
    @bottom-right { content: "Page " counter(page) " of " counter(pages); font-size: 9pt; color: #555; }
  }
  * { font-family: Arial, "DejaVu Sans", sans-serif; }
  html, body { background:#fff; color:#111; margin:0; }
  .doc-head { display:flex; align-items:center; gap:12px; border-bottom:2px solid #333; padding-bottom:8px; margin-bottom:8px; }
  .doc-head img { width:52px; height:52px; object-fit:contain; }
  .doc-head h1 { font-size:15pt; margin:0; }
  .doc-head .sub { font-size:10pt; color:#555; margin-top:2px; }
  .doc-head .round { margin-left:auto; text-align:right; }
  .doc-head .round .badge { display:inline-block; border:1px solid #333; border-radius:4px; padding:3px 10px; font-size:11pt; font-weight:bold; }
  .doc-head .round .laps { font-size:9.5pt; color:#555; margin-top:4px; }
  .ev-line { font-size:12pt; font-weight:bold; margin:2px 0 8px; }
  .ev-line .laps { font-weight:normal; font-size:10pt; color:#333; margin-left:10px; }
  table { width:100%; border-collapse:collapse; table-layout:fixed; font-size:10.5pt; }
  th, td { border:1px solid #333; padding:5px 7px; vertical-align:middle; word-wrap:break-word; }
  thead th { background:#eee; text-align:center; font-size:9.5pt; text-transform:uppercase; }
  tbody tr { page-break-inside: avoid; }
  td.c { text-align:center; }
  col.c-sl{width:8%} col.c-ch{width:13%} col.c-nm{width:33%} col.c-db{width:17%} col.c-sch{width:29%}
</style>
</head>
<body>
  <div class="doc-head">
    <?php if (!empty($event['logo'])): ?><img src="<?= e($event['logo']) ?>" alt=""><?php endif; ?>
    <div>
      <h1><?= e($round['event_name']) ?></h1>
      <div class="sub">Participants List &middot; Total: <?= $total ?></div>
    </div>
    <div class="round">
      <span class="badge"><?= e($round['round_name']) ?></span>
      <?php if ($isTrack && $numLaps > 0): ?>
        <div class="laps"><?= $numLaps ?> lap<?= $numLaps === 1 ? '' : 's' ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="ev-line">
    <?= e($evName) ?>
    <?php if ($isTrack && $numLaps > 0): ?><span class="laps">No. of Laps: <?= $numLaps ?></span><?php endif; ?>
  </div>

  <table>
    <colgroup>
      <col class="c-sl"><col class="c-ch"><col class="c-nm"><col class="c-db"><col class="c-sch">
    </colgroup>
    <thead>
      <tr>
        <th>Sl. No</th><th>Chest No</th><th>Name of Athlete</th><th>Date of Birth</th><th>Name of School</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($participants)): ?>
        <tr><td colspan="5" class="c" style="padding:14px">No approved participants yet.</td></tr>
      <?php else: $sl = 0; foreach ($participants as $p): $sl++; ?>
        <tr>
          <td class="c"><?= $sl ?></td>
          <td class="c"><?= e($chest($p['competitor_number'])) ?></td>
          <td><?= e($p['athlete_name']) ?></td>
          <td class="c"><?= e($fmtDob($p['date_of_birth'] ?? '')) ?></td>
          <td><?= e($p['unit_name'] ?? '') ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

<script>window.addEventListener('load', function(){ setTimeout(function(){ try{ window.print(); }catch(e){} }, 200); });</script>
</body>
</html>
