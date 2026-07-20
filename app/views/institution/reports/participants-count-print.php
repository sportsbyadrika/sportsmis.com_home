<?php
$pageTitle = 'Event-wise Participants Count — ' . $event['name'];
$total = 0;
foreach ($rows as $r) { $total += (int)$r['participants']; }
?>

<style>
  @page {
    size: A4 portrait;
    margin: 12mm 12mm 16mm 12mm;
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
  table.cc-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 10pt; }
  table.cc-table thead { display: table-header-group; }
  table.cc-table th, table.cc-table td {
    border: 1px solid #444; padding: 5px 7px; vertical-align: top;
    word-wrap: break-word; overflow-wrap: anywhere;
  }
  table.cc-table thead th { background: #f1f3f5 !important; font-weight: 600; text-align: center; }
  table.cc-table tr { page-break-inside: avoid; }
  table.cc-table tfoot th { background: #f1f3f5 !important; }
  col.c-sl  { width: 9%; }
  col.c-cat { width: 33%; }
  col.c-evt { width: 40%; }
  col.c-num { width: 18%; }
  .text-end { text-align: right; }
  .text-center { text-align: center; }
</style>

<div class="head-bar">
  <?php if (!empty($event['logo'])): ?>
    <img src="<?= e($event['logo']) ?>" alt="" class="event-logo">
  <?php endif; ?>
  <div>
    <h1><?= e($event['name']) ?></h1>
    <div class="meta">
      Event-wise Participants Count
      &middot; <strong><?= $selected_category !== '' ? e($selected_category) : 'All categories' ?></strong>
      <?php if (!empty($event['event_date_from'])): ?>
        &middot; <?= e(formatDate($event['event_date_from'])) ?>
      <?php endif; ?>
      &middot; <?= count($rows) ?> sport event<?= count($rows) === 1 ? '' : 's' ?>
      &middot; <?= $total ?> participant<?= $total === 1 ? '' : 's' ?>
    </div>
  </div>
</div>

<?php if (empty($rows)): ?>
  <p class="text-muted">No approved participants for this selection.</p>
<?php else: ?>
  <table class="cc-table">
    <colgroup>
      <col class="c-sl"><col class="c-cat"><col class="c-evt"><col class="c-num">
    </colgroup>
    <thead>
      <tr>
        <th>Sl. No</th>
        <th>Event Category</th>
        <th>Sport Event</th>
        <th>No. of Participants</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $i => $r): ?>
        <tr>
          <td class="text-center"><?= $i + 1 ?></td>
          <td><?= e($r['category_name']) ?: '—' ?></td>
          <td><?= e($r['sport_event']) ?></td>
          <td class="text-end"><?= (int)$r['participants'] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <th colspan="3" class="text-end">Total</th>
        <th class="text-end"><?= $total ?></th>
      </tr>
    </tfoot>
  </table>
<?php endif; ?>
