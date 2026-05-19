<?php
$pageTitle = 'NOC Report — ' . ($event['name'] ?? '');
$nocLabel = ['accepted' => 'Accepted', 'rejected' => 'Rejected', 'pending' => 'Pending'];
$counts = ['accepted' => 0, 'rejected' => 0, 'pending' => 0];
foreach ($athletes as $a) {
    $s = $a['noc_status'] ?: 'pending';
    if (isset($counts[$s])) $counts[$s]++;
}
$filterNote = [];
if (!empty($filter_status)) $filterNote[] = 'NOC Status: ' . ($nocLabel[$filter_status] ?? $filter_status);
if (!empty($filter_name))   $filterNote[] = 'Name: "' . $filter_name . '"';
?>

<h2 class="mb-1" style="text-align:center"><?= e($event['name'] ?? '') ?></h2>
<div class="text-center text-muted small mb-1">NOC Status Report</div>
<div class="text-center small mb-3">
  Event Code: <strong><?= e($event['event_code'] ?? '—') ?></strong>
  &middot; Dates:
  <?= e(formatDate($event['event_date_from'] ?? null)) ?> – <?= e(formatDate($event['event_date_to'] ?? null)) ?>
</div>

<table class="mb-3" style="border:none">
  <tr style="border:none">
    <td style="border:none;padding:2px 0">
      <strong>Unit:</strong>
      #<?= (int)($active_unit['id'] ?? 0) ?> — <?= e($active_unit['name'] ?? '—') ?>
      <?php if (!empty($active_unit['address'])): ?><br><span class="small text-muted"><?= e($active_unit['address']) ?></span><?php endif; ?>
    </td>
    <td style="border:none;padding:2px 0;text-align:right" class="small text-muted">
      Generated: <?= date('d M Y, h:i A') ?>
    </td>
  </tr>
</table>

<?php if ($filterNote): ?>
  <div class="small mb-2" style="background:#fff3cd;border:1px solid #ffe69c;padding:4px 8px">
    <strong>Filtered by</strong> — <?= e(implode(' · ', $filterNote)) ?>
  </div>
<?php endif; ?>

<table>
  <thead>
    <tr>
      <th style="width:42px">Sl.</th>
      <th style="width:56px">Photo</th>
      <th>Athlete Name</th>
      <th style="width:90px">Comp. No.</th>
      <th>Event(s) Registered</th>
      <th style="width:90px">NOC Status</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($athletes)): ?>
      <tr><td colspan="6" class="text-center text-muted">No athletes match the report criteria.</td></tr>
    <?php else: foreach ($athletes as $i => $a): ?>
      <tr>
        <td class="text-center"><?= $i + 1 ?></td>
        <td class="text-center">
          <?php if (!empty($a['passport_photo'])): ?>
            <img src="<?= e($a['passport_photo']) ?>" width="40" height="40" style="object-fit:cover">
          <?php else: ?>—<?php endif; ?>
        </td>
        <td><?= e($a['athlete_name']) ?></td>
        <td class="text-center"><?= $a['competitor_number'] ? '#' . (int)$a['competitor_number'] : '—' ?></td>
        <td class="small"><?= e($a['events_label'] ?? '—') ?></td>
        <td class="text-center fw-bold"><?= e($nocLabel[$a['noc_status'] ?: 'pending'] ?? 'Pending') ?></td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>

<div class="no-break" style="margin-top:14px">
  <strong>Summary</strong>
  <table style="width:auto;margin-top:4px">
    <tr><td>Total Athletes</td><td class="text-end fw-bold"><?= count($athletes) ?></td></tr>
    <tr><td>Accepted</td><td class="text-end fw-bold"><?= (int)$counts['accepted'] ?></td></tr>
    <tr><td>Rejected</td><td class="text-end fw-bold"><?= (int)$counts['rejected'] ?></td></tr>
    <tr><td>Pending</td><td class="text-end fw-bold"><?= (int)$counts['pending'] ?></td></tr>
  </table>
</div>

<div class="no-break" style="margin-top:48px;display:flex;justify-content:flex-end">
  <div style="text-align:center">
    <div style="border-top:1px solid #444;width:220px;padding-top:4px">
      Unit Representative<br>
      <span class="small text-muted">Signature &amp; Date</span>
    </div>
  </div>
</div>

<script>
  // Auto-open the print dialog when the report tab loads.
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 350); });
</script>
