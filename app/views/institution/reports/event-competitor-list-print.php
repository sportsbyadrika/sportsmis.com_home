<?php
$pageTitle = 'Event-wise Competitor List — ' . $event['name'];
$compLbl   = $comp_label ?? 'Comp. No.';
$fmtDob = function ($d) {
    $d = trim((string)$d);
    return ($d !== '' && ($ts = strtotime($d))) ? date('d M Y', $ts) : '';
};
$totalAthletes = 0;
foreach ($groups as $g) { $totalAthletes += count($g['athletes']); }
?>

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
  .grp-title { font-size: 11pt; font-weight: 700; margin: 12px 0 4px;
               border-left: 4px solid #333; padding-left: 8px; page-break-after: avoid; }
  table.cc-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 9.5pt; margin-bottom: 8px; }
  table.cc-table thead { display: table-header-group; }
  table.cc-table th, table.cc-table td {
    border: 1px solid #444; padding: 4px 6px; vertical-align: middle;
    word-wrap: break-word; overflow-wrap: anywhere;
  }
  table.cc-table thead th { background: #f1f3f5 !important; font-weight: 600; text-align: center; }
  table.cc-table tr { page-break-inside: avoid; }
  .ph { width: 34px; height: 42px; object-fit: cover; border: 1px solid #999; }
  col.c-sl  { width: 4%; }
  col.c-uc  { width: 8%; }
  col.c-un  { width: 17%; }
  col.c-cn  { width: 8%; }
  col.c-nm  { width: 20%; }
  col.c-age { width: 5%; }
  col.c-gen { width: 7%; }
  col.c-dob { width: 10%; }
  col.c-ac  { width: 13%; }
  col.c-ph  { width: 8%; }
</style>

<div class="head-bar">
  <?php if (!empty($event['logo'])): ?>
    <img src="<?= e($event['logo']) ?>" alt="" class="event-logo">
  <?php endif; ?>
  <div>
    <h1><?= e($event['name']) ?></h1>
    <div class="meta">
      Event-wise Competitor List
      &middot; <strong><?= e($selected_category) ?></strong>
      <?php if (!empty($event['event_date_from'])): ?>
        &middot; <?= e(formatDate($event['event_date_from'])) ?>
      <?php endif; ?>
      &middot; <?= count($groups) ?> event<?= count($groups) === 1 ? '' : 's' ?>
      &middot; <?= $totalAthletes ?> entr<?= $totalAthletes === 1 ? 'y' : 'ies' ?>
    </div>
  </div>
</div>

<?php if (empty($groups)): ?>
  <p class="text-muted">No approved athletes are registered for this category.</p>
<?php else: ?>
  <?php foreach ($groups as $g): ?>
    <div class="grp-title"><?= e($g['event_label']) ?> · <?= count($g['athletes']) ?></div>
    <table class="cc-table">
      <colgroup>
        <col class="c-sl"><col class="c-uc"><col class="c-un"><col class="c-cn"><col class="c-nm">
        <col class="c-age"><col class="c-gen"><col class="c-dob"><col class="c-ac"><col class="c-ph">
      </colgroup>
      <thead>
        <tr>
          <th>Sl. No</th>
          <th>Unit Code</th>
          <th>Unit Name</th>
          <th><?= e($compLbl) ?></th>
          <th>Name of Candidate</th>
          <th>Age</th>
          <th>Gender</th>
          <th>DOB</th>
          <th>Age Category</th>
          <th>Photo</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($g['athletes'] as $i => $a): ?>
          <tr>
            <td class="text-center"><?= $i + 1 ?></td>
            <td class="text-center"><?= e($a['unit_code']) ?: '—' ?></td>
            <td><?= e($a['unit_name_field']) ?: '—' ?></td>
            <td class="text-center fw-bold"><?= $a['competitor_no'] !== '' ? '#' . e($a['competitor_no']) : '—' ?></td>
            <td><?= e($a['athlete_name']) ?></td>
            <td class="text-center"><?= $a['age'] === '' ? '—' : e($a['age']) ?></td>
            <td class="text-center"><?= e($a['gender']) ?: '—' ?></td>
            <td class="text-center"><?= e($fmtDob($a['dob'])) ?: '—' ?></td>
            <td><?= e($a['age_category']) ?: '—' ?></td>
            <td class="text-center">
              <?php if (!empty($a['photo'])): ?>
                <img src="<?= e($a['photo']) ?>" alt="" class="ph">
              <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endforeach; ?>
<?php endif; ?>
