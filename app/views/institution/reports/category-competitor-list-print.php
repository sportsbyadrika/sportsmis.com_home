<?php $pageTitle = 'Event Category-wise Competitor List — ' . $event['name']; ?>

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
  table.cc-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 9.5pt; }
  table.cc-table thead { display: table-header-group; }
  table.cc-table th, table.cc-table td {
    border: 1px solid #444; padding: 4px 6px; vertical-align: top;
    word-wrap: break-word; overflow-wrap: anywhere;
  }
  table.cc-table thead th { background: #f1f3f5 !important; font-weight: 600; text-align: center; }
  table.cc-table tr { page-break-inside: avoid; }
  td.events-cell { font-size: 8.5pt; line-height: 1.35; }
  td.events-cell ol { margin: 0; padding-left: 18px; }
  td.events-cell ol li { margin: 0; padding: 0; }
  /* Column widths tuned for A4 landscape (~277mm usable). */
  col.c-sl   { width: 5%; }
  col.c-uc   { width: 14%; }
  col.c-un   { width: 16%; }
  col.c-cn   { width: 8%; }
  col.c-nm   { width: 17%; }
  col.c-age  { width: 5%; }
  col.c-gen  { width: 7%; }
  col.c-evt  { width: 28%; }
</style>

<div class="head-bar">
  <?php if (!empty($event['logo'])): ?>
    <img src="<?= e($event['logo']) ?>" alt="" class="event-logo">
  <?php endif; ?>
  <div>
    <h1><?= e($event['name']) ?></h1>
    <div class="meta">
      Event Category-wise Competitor List
      &middot; <strong><?= e($selected_category) ?></strong>
      <?php if (!empty($event['event_date_from'])): ?>
        &middot; <?= e(formatDate($event['event_date_from'])) ?>
      <?php endif; ?>
      &middot; <?= count($athletes) ?> athlete<?= count($athletes) === 1 ? '' : 's' ?>
    </div>
  </div>
</div>

<?php if (empty($athletes)): ?>
  <p class="text-muted">No approved athletes are registered for this category.</p>
<?php else: ?>
  <table class="cc-table">
    <colgroup>
      <col class="c-sl"><col class="c-uc"><col class="c-un"><col class="c-cn"><col class="c-nm">
      <col class="c-age"><col class="c-gen"><col class="c-evt">
    </colgroup>
    <thead>
      <tr>
        <th>Sl. No</th>
        <th>Unit Code</th>
        <th>Unit Name</th>
        <th>Comp. No.</th>
        <th>Name of Candidate</th>
        <th>Age</th>
        <th>Gender</th>
        <th>Events</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($athletes as $i => $a): ?>
        <tr>
          <td class="text-center"><?= $i + 1 ?></td>
          <td><?= e($a['unit_code']) ?: '—' ?></td>
          <td><?= e($a['unit_name_field']) ?: '—' ?></td>
          <td class="text-center fw-bold"><?= $a['competitor_no'] !== '' ? '#' . e($a['competitor_no']) : '—' ?></td>
          <td><?= e($a['athlete_name']) ?></td>
          <td class="text-center"><?= $a['age'] === '' ? '—' : e($a['age']) ?></td>
          <td class="text-center"><?= e($a['gender']) ?: '—' ?></td>
          <td class="events-cell">
            <?php if (empty($a['events'])): ?>
              —
            <?php else: ?>
              <ol>
                <?php foreach ($a['events'] as $ev): ?>
                  <li><?= e($ev) ?></li>
                <?php endforeach; ?>
              </ol>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
