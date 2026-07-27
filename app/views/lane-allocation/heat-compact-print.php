<?php
/**
 * Compact heat-wise participants sheet (portrait). Eight small heat tables per
 * page, laid out two-per-row across four rows. Each table lists just the Chest
 * Number (no leading #) and the athlete name, under a small heat heading.
 * Rendered directly by LaneAllocationController::heatCompact.
 * Expects: $event, $round (roundContext), $heats (heat_no => [assignment,...]).
 */
$numHeats  = (int)$round['num_heats'];
$evName    = trim((string)($round['sport_event_name'] ?? '')) ?: trim((string)($round['event_code'] ?? ''));
$total     = (int)($round['approved'] ?? 0);
$perPage   = 8; // heats per printed page (2 columns × 4 rows)
$chest     = fn($n) => $n ? (string)(int)$n : '';
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
<title>Heat-wise Participants — <?= e($evName) ?> · <?= e($round['round_name']) ?></title>
<style>
  @page {
    size: A4 portrait;
    margin: 10mm 10mm 12mm 10mm;
    @bottom-right { content: "Page " counter(page) " of " counter(pages); font-size: 9pt; color: #555; }
  }
  * { font-family: Arial, "DejaVu Sans", sans-serif; }
  html, body { background:#fff; color:#111; margin:0; }
  .sheet-page { page-break-after: always; }
  .sheet-page:last-child { page-break-after: auto; }
  .doc-head { display:flex; align-items:center; gap:10px; border-bottom:2px solid #333; padding-bottom:5px; margin-bottom:8px; }
  .doc-head img { width:34px; height:34px; object-fit:contain; }
  .doc-head .titles { flex:1 1 auto; min-width:0; }
  .doc-head h1 { font-size:11pt; font-weight:bold; margin:0; color:#333; }
  /* Sub heading (sport event) is deliberately larger than the main heading. */
  .doc-head .sub { font-size:16pt; font-weight:bold; line-height:1.1; margin-top:1px; }
  .doc-head .meta { font-size:9pt; color:#555; margin-top:1px; }
  .doc-head .round-label {
    margin-left:auto; flex:0 0 auto;
    background:#ffd400; color:#111; font-weight:bold; font-size:12pt;
    padding:5px 14px; border-radius:5px; border:1px solid #caa500; white-space:nowrap;
    -webkit-print-color-adjust:exact; print-color-adjust:exact;
  }
  .heats-grid { display:flex; flex-wrap:wrap; gap:6px 10px; }
  .heat-cell { width:calc(50% - 5px); page-break-inside:avoid; margin-bottom:4px; }
  .heat-cell .hh { font-size:10pt; font-weight:bold; margin:0 0 2px;
                   border-bottom:1.5px solid #333; padding-bottom:2px; }
  .heat-cell .hh .mut { font-weight:normal; font-size:8.5pt; color:#555; }
  table { width:100%; border-collapse:collapse; table-layout:fixed; font-size:9.5pt; }
  th, td { border:1px solid #444; padding:2px 5px; vertical-align:middle; word-wrap:break-word; }
  thead th { background:#eee; text-align:left; font-size:8pt; text-transform:uppercase;
             -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  col.c-ch{width:24%} col.c-nm{width:58%} col.c-rm{width:18%}
  td.c { text-align:center; }
  td.empty { color:#888; font-style:italic; text-align:center; }
</style>
</head>
<body>
<?php for ($h = 1; $h <= $numHeats; $h++):
  // Open a page at the start of every group of $perPage heats.
  if (($h - 1) % $perPage === 0): ?>
  <div class="sheet-page">
    <div class="doc-head">
      <?php if (!empty($event['logo'])): ?><img src="<?= e($event['logo']) ?>" alt=""><?php endif; ?>
      <div class="titles">
        <h1><?= e($round['event_name']) ?></h1>
        <div class="sub"><?= e($evName) ?></div>
        <div class="meta">Total Athletes: <?= $total ?></div>
      </div>
      <div class="round-label"><?= e($round['round_name']) ?></div>
    </div>
    <div class="heats-grid">
  <?php endif; ?>

  <?php
    $rows = $heats[$h] ?? [];
    usort($rows, fn($a, $b) => (int)$a['track_no'] <=> (int)$b['track_no']);
  ?>
      <div class="heat-cell">
        <div class="hh">Heat <?= $heatLetter($h) ?> <span class="mut">(<?= $h ?> of <?= $numHeats ?>)</span></div>
        <table>
          <colgroup><col class="c-ch"><col class="c-nm"><col class="c-rm"></colgroup>
          <thead>
            <tr><th>Chest No</th><th>Name of Participant</th><th>Remarks</th></tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td class="empty" colspan="3">No athletes</td></tr>
            <?php else: foreach ($rows as $a): ?>
              <tr>
                <td class="c"><?= e($chest($a['competitor_number'])) ?></td>
                <td><?= e($a['athlete_name']) ?></td>
                <td></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

  <?php // Close the page after every group of $perPage heats or at the very end.
    if ($h % $perPage === 0 || $h === $numHeats): ?>
    </div><!-- .heats-grid -->
  </div><!-- .sheet-page -->
  <?php endif; ?>
<?php endfor; ?>

<script>window.addEventListener('load', function(){ setTimeout(function(){ try{ window.print(); }catch(e){} }, 200); });</script>
</body>
</html>
