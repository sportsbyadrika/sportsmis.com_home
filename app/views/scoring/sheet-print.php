<?php
$pageTitle = 'Score Sheet — Lane ' . (int)$lane['lane_number'];
$seriesCount = (int)($entry['series_count'] ?? ($config['series_count'] ?? 6));
$shotsCount  = (int)($entry['shots_per_series'] ?? ($config['shots_per_series'] ?? 10));
$bySer = [];
foreach ($series as $s) $bySer[(int)$s['series_no']] = $s;
$athlete = $entry; // header carries comp number; name is on entry context via lookup
?>

<table style="border:none;width:100%">
  <tr style="border:none">
    <td style="border:none;text-align:left;width:40%">
      <div class="fw-bold" style="font-size:13pt"><?= e($event['name']) ?></div>
      <div class="small">Code: <strong><?= e($event['event_code'] ?? '') ?></strong></div>
    </td>
    <td style="border:none;text-align:center;width:30%;font-size:14pt;font-weight:700">SHOOTING CHAMPIONSHIP — SCORE SHEET</td>
    <td style="border:none;text-align:right;width:30%" class="small">
      Generated: <?= date('d M Y, h:i A') ?>
    </td>
  </tr>
</table>

<table style="margin-top:6px">
  <tr>
    <th style="width:18%">NAME OF EVENT</th>
    <td colspan="3"><?= e($event['name']) ?></td>
    <th style="width:10%">DATE</th>
    <td style="width:14%"><?= !empty($relay['relay_date']) ? e(formatDate($relay['relay_date'],'d/m/Y')) : '' ?></td>
    <th style="width:8%">TIME</th>
    <td><?= !empty($relay['match_time']) ? e(substr($relay['match_time'],0,5)) : '' ?></td>
  </tr>
  <tr>
    <th>RELAY NO</th><td><?= e($relay['relay_number']) ?></td>
    <th>LANE NO</th><td><?= (int)$lane['lane_number'] ?></td>
    <th>COMP NO</th><td colspan="3"><?= e($entry['competitor_number'] ?? ($lane['competitor_number'] ?? '')) ?></td>
  </tr>
  <tr>
    <th>TARGET NOS FROM</th><td><?= e($entry['target_from'] ?? '') ?></td>
    <th>TO</th><td><?= e($entry['target_to'] ?? '') ?></td>
    <th>NAME</th><td colspan="3"><?= e($lane['athlete_name'] ?? '') ?></td>
  </tr>
  <tr>
    <th>EVENT</th>
    <td colspan="6">
      <strong><?= e($config['category_name'] ?? ($lane['category'] ?? '—')) ?></strong>
      <?php if (!empty($config['abbreviation'])): ?> (<?= e($config['abbreviation']) ?>)<?php endif; ?>
    </td>
    <th>NO OF SHOTS</th>
    <td><?= $seriesCount * $shotsCount ?></td>
  </tr>
</table>

<table style="margin-top:6px">
  <thead>
    <tr>
      <th style="width:6%">SERIES</th>
      <?php for ($k = 1; $k <= $shotsCount; $k++): ?><th class="text-center"><?= $k ?></th><?php endfor; ?>
      <th class="text-end">SUB-TOTAL</th>
      <th class="text-end">PENALTY</th>
      <th class="text-end">SER. TOTAL</th>
    </tr>
  </thead>
  <tbody>
    <?php for ($s = 1; $s <= $seriesCount; $s++):
      $row = $bySer[$s] ?? null;
      $shots = $row ? (json_decode($row['shots_json'] ?? '[]', true) ?: []) : [];
    ?>
      <tr>
        <th class="text-center">S<?= $s ?></th>
        <?php for ($k = 0; $k < $shotsCount; $k++): ?>
          <td class="text-center"><?= isset($shots[$k]) && $shots[$k] !== null ? e($shots[$k]) : '' ?></td>
        <?php endfor; ?>
        <td class="text-end"><?= $row ? number_format((float)$row['sub_total'], 2) : '' ?></td>
        <td class="text-end"><?= $row ? number_format((float)$row['penalty'], 2)   : '' ?></td>
        <td class="text-end fw-bold"><?= $row ? number_format((float)$row['series_total'], 2) : '' ?></td>
      </tr>
    <?php endfor; ?>
    <tr>
      <th class="text-end" colspan="<?= $shotsCount + 3 ?>">GRAND TOTAL</th>
      <th class="text-end fw-bold" style="font-size:12pt"><?= $entry ? number_format((float)$entry['grand_total'], 2) : '' ?></th>
    </tr>
  </tbody>
</table>

<table style="margin-top:6px">
  <tr>
    <th style="width:18%">REMARKS / STATUS</th>
    <td colspan="3">
      <?php
        $remarks = $entry['remarks'] ?? '';
        $marks = ['dns'=>'DNS — Did Not Start','dnf'=>'DNF — Did Not Finish','disqualified'=>'Disqualified','other'=>'Other'];
        foreach ($marks as $k=>$lbl): $chk = ($remarks === $k) ? '☑' : '☐'; ?>
          <span style="margin-right:14px"><?= $chk ?> <?= e($lbl) ?></span>
        <?php endforeach; ?>
    </td>
  </tr>
  <tr>
    <th>Notes / Additional Remarks</th>
    <td colspan="3"><?= e($entry['notes'] ?? '') ?></td>
  </tr>
</table>

<table style="margin-top:40px;border:none">
  <tr style="border:none">
    <td style="border:none;width:50%;border-top:1px solid #444;padding-top:4px">Scorer's Signature</td>
    <td style="border:none;width:50%;border-top:1px solid #444;padding-top:4px">Range Officer's Signature</td>
  </tr>
</table>

<script>
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 350); });
</script>
