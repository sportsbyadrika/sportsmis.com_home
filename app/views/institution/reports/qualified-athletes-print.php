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
  /* Inner events table */
  table.qa-inner { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
  table.qa-inner th, table.qa-inner td { border: 1px solid #777; padding: 2px 4px; }
  table.qa-inner thead th { background: #f6f7f8 !important; font-weight: 600; text-align: center; }
  .text-end { text-align: right; }
  .text-center { text-align: center; }
  /* Column widths tuned for A4 landscape (~277mm usable). */
  col.c-sl  { width: 4%; }
  col.c-cn  { width: 8%; }
  col.c-nm  { width: 15%; }
  col.c-gen { width: 7%; }
  col.c-age { width: 4%; }
  col.c-ac  { width: 11%; }
  col.c-un  { width: 13%; }
  col.c-evt { width: 38%; }
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
      <col class="c-age"><col class="c-ac"><col class="c-un"><col class="c-evt">
    </colgroup>
    <thead>
      <tr>
        <th>Sl. No</th>
        <th>Comp. No.</th>
        <th>Name</th>
        <th>Gender</th>
        <th>Age</th>
        <th>Age Category</th>
        <th>Unit</th>
        <th>Qualified Events</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($athletes as $i => $a): ?>
        <tr>
          <td class="text-center"><?= $i + 1 ?></td>
          <td class="text-center"><?= $a['competitor_number'] !== '' ? '#' . e($a['competitor_number']) : '—' ?></td>
          <td><?= e($a['athlete_name']) ?></td>
          <td class="text-center"><?= e($a['gender']) ?: '—' ?></td>
          <td class="text-center"><?= $a['age'] === '' ? '—' : e($a['age']) ?></td>
          <td><?= e($a['age_category']) ?: '—' ?></td>
          <td><?= e($a['unit_name']) ?: '—' ?></td>
          <td>
            <table class="qa-inner">
              <thead>
                <tr>
                  <th style="width:24px">#</th>
                  <th>Category</th>
                  <th>Event</th>
                  <th class="text-end" style="width:50px">MQS</th>
                  <th class="text-end" style="width:64px">Total Score</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($a['qualified'] as $j => $q): ?>
                  <tr>
                    <td class="text-center"><?= $j + 1 ?></td>
                    <td><?= e($q['category_name']) ?></td>
                    <td><?= e($q['event_label']) ?></td>
                    <td class="text-end"><?= e($q['mqs']) ?></td>
                    <td class="text-end"><?= e($q['total_score']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
