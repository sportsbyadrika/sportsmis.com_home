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
  .head-bar {
    display: flex; align-items: center; gap: 12px;
    border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 10px;
    page-break-after: avoid;
  }
  .head-bar .event-logo { width: 46px; height: 46px; object-fit: contain; }
  .head-bar h1 { font-size: 13pt; margin: 0; }
  .head-bar .meta { font-size: 9.5pt; color: #555; }
  table.qa-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 9.5pt; }
  table.qa-table thead { display: table-header-group; }
  table.qa-table th, table.qa-table td {
    border: 1px solid #444; padding: 4px 6px; vertical-align: top;
    word-wrap: break-word; overflow-wrap: anywhere;
  }
  table.qa-table thead th { background: #f1f3f5 !important; font-weight: 600; text-align: center; }
  table.qa-table tr { page-break-inside: avoid; }
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

<div class="head-bar">
  <?php if (!empty($event['logo'])): ?>
    <img src="<?= e($event['logo']) ?>" alt="" class="event-logo">
  <?php endif; ?>
  <div>
    <h1><?= e($event['name']) ?></h1>
    <div class="meta">
      Qualified Athletes (MQS-based)
      <?php if (!empty($event['event_date_from'])): ?>
        &middot; <?= e(formatDate($event['event_date_from'])) ?>
      <?php endif; ?>
      &middot; <?= count($athletes) ?> athlete<?= count($athletes) === 1 ? '' : 's' ?>
    </div>
  </div>
</div>

<?php if (empty($athletes)): ?>
  <p class="text-muted">No athletes have qualified — no MQS configured, or no recorded score reaches it.</p>
<?php else: ?>
  <table class="qa-table">
    <colgroup>
      <col class="c-sl"><col class="c-cn"><col class="c-nm"><col class="c-gen">
      <col class="c-age"><col class="c-ac"><col class="c-un">
      <col class="c-qno"><col class="c-qcat"><col class="c-qev"><col class="c-mqs"><col class="c-ts">
    </colgroup>
    <thead>
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
