<?php $pageTitle = 'Qualified Athletes — ' . $event['name']; ?>

<style>
  @page {
    size: A4 landscape;
    margin: 12mm 10mm 16mm 10mm;
    @bottom-right {
      content: "Page " counter(page) " of " counter(pages);
      font-size: 9pt;
      color: #666;
    }
  }
  html, body { background: #fff !important; color: #111; }
  table.qa-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 9.5pt; }
  /* Repeat the whole header (title block + column headers) on every page. */
  table.qa-table thead { display: table-header-group; }
  table.qa-table th, table.qa-table td {
    border: 1px solid #444; padding: 4px 6px; vertical-align: top;
    word-wrap: break-word; overflow-wrap: anywhere;
  }
  table.qa-table thead th { background: #f1f3f5 !important; font-weight: 600; text-align: center; }
  table.qa-table tr { page-break-inside: avoid; }
  /* Title row — event + institution + meta, drawn like a banner, no fill. */
  table.qa-table thead tr.title-row th {
    background: #fff !important; border: none; border-bottom: 2px solid #333;
    text-align: left; padding: 0 0 8px 0;
  }
  .qa-title-wrap { display: flex; align-items: center; gap: 12px; }
  .qa-logo { width: 46px; height: 46px; object-fit: contain; }
  .qa-title { font-size: 13pt; font-weight: 700; margin: 0; }
  .qa-inst  { font-size: 10.5pt; font-weight: 600; color: #333; margin-top: 1px; }
  .qa-meta  { font-size: 9.5pt; font-weight: 400; color: #555; margin-top: 1px; }
  .text-end { text-align: right; }
  .text-center { text-align: center; }
  /* Column widths tuned for A4 landscape (~277mm usable). */
  col.c-sl  { width: 4%; }
  col.c-cn  { width: 7%; }
  col.c-nm  { width: 14%; }
  col.c-gen { width: 6%; }
  col.c-age { width: 4%; }
  col.c-ac  { width: 10%; }
  col.c-un  { width: 12%; }
  col.c-qno { width: 3%; }
  col.c-qcat{ width: 12%; }
  col.c-qev { width: 16%; }
  col.c-mqs { width: 6%; }
  col.c-ts  { width: 6%; }
</style>

<?php
  // Event date range — show "from – to"; collapse to one when they match
  // or only one is set.
  $dFrom = !empty($event['event_date_from']) ? formatDate($event['event_date_from']) : '';
  $dTo   = !empty($event['event_date_to'])   ? formatDate($event['event_date_to'])   : '';
  if ($dFrom !== '' && $dTo !== '') {
      $dateLabel = $dFrom === $dTo ? $dFrom : ($dFrom . ' – ' . $dTo);
  } else {
      $dateLabel = $dFrom !== '' ? $dFrom : $dTo;
  }
?>

<?php
  // Title banner markup — reused inside the repeating <thead> (and shown
  // standalone when there are no rows).
  ob_start(); ?>
  <div class="qa-title-wrap">
    <?php if (!empty($event['logo'])): ?>
      <img src="<?= e($event['logo']) ?>" alt="" class="qa-logo">
    <?php endif; ?>
    <div>
      <div class="qa-title"><?= e($event['name']) ?></div>
      <?php if (!empty($institution['name'])): ?>
        <div class="qa-inst"><?= e($institution['name']) ?></div>
      <?php endif; ?>
      <div class="qa-meta">
        Qualified Athletes (MQS-based)
        <?php if ($dateLabel !== ''): ?>&middot; <?= e($dateLabel) ?><?php endif; ?>
        &middot; <?= count($athletes) ?> athlete<?= count($athletes) === 1 ? '' : 's' ?>
      </div>
    </div>
  </div>
<?php $titleBanner = ob_get_clean(); ?>

<?php if (empty($athletes)): ?>
  <div style="border-bottom:2px solid #333;padding-bottom:8px;margin-bottom:10px;"><?= $titleBanner ?></div>
  <p class="text-muted">No athletes have qualified — no MQS configured, or no recorded score reaches it.</p>
<?php else: ?>
  <table class="qa-table">
    <colgroup>
      <col class="c-sl"><col class="c-cn"><col class="c-nm"><col class="c-gen">
      <col class="c-age"><col class="c-ac"><col class="c-un">
      <col class="c-qno"><col class="c-qcat"><col class="c-qev"><col class="c-mqs"><col class="c-ts">
    </colgroup>
    <thead>
      <tr class="title-row">
        <th colspan="12"><?= $titleBanner ?></th>
      </tr>
      <tr>
        <th rowspan="2">Sl. No</th>
        <th rowspan="2">Comp. No.</th>
        <th rowspan="2">Name</th>
        <th rowspan="2">Gender</th>
        <th rowspan="2">Age</th>
        <th rowspan="2">Age Category</th>
        <th rowspan="2">Unit</th>
        <th colspan="5">Qualified Events</th>
      </tr>
      <tr>
        <th>#</th>
        <th>Category</th>
        <th>Event</th>
        <th>MQS</th>
        <th>Total Score</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($athletes as $i => $a): $span = count($a['qualified']); ?>
        <?php foreach ($a['qualified'] as $j => $q): ?>
          <tr>
            <?php if ($j === 0): ?>
              <td rowspan="<?= $span ?>" class="text-center"><?= $i + 1 ?></td>
              <td rowspan="<?= $span ?>" class="text-center"><?= $a['competitor_number'] !== '' ? '#' . e($a['competitor_number']) : '—' ?></td>
              <td rowspan="<?= $span ?>"><?= e($a['athlete_name']) ?></td>
              <td rowspan="<?= $span ?>" class="text-center"><?= e($a['gender']) ?: '—' ?></td>
              <td rowspan="<?= $span ?>" class="text-center"><?= $a['age'] === '' ? '—' : e($a['age']) ?></td>
              <td rowspan="<?= $span ?>"><?= e($a['age_category']) ?: '—' ?></td>
              <td rowspan="<?= $span ?>"><?= e($a['unit_name']) ?: '—' ?></td>
            <?php endif; ?>
            <td class="text-center"><?= $j + 1 ?></td>
            <td><?= e($q['category_name']) ?></td>
            <td><?= e($q['event_label']) ?></td>
            <td class="text-end"><?= e($q['mqs']) ?></td>
            <td class="text-end"><?= e($q['total_score']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
