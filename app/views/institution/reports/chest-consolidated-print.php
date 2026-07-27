<?php
/**
 * Consolidated Chest Number report — one row per unit / institution with the
 * unit's approved chest (competitor) numbers listed comma-separated, ascending.
 * Rendered inside the 'print' layout.
 * Expects: $event, $eventHash, $units (each: unit_name, chests[], count).
 */
$pageTitle = 'Consolidated Chest Numbers — ' . ($event['name'] ?? '');
$totalChests = 0;
foreach ($units as $u) { $totalChests += (int)$u['count']; }
?>
<h1 class="h4 fw-bold mb-1"><?= e($event['name'] ?? '') ?></h1>
<div class="text-muted mb-3">
  Consolidated Chest Numbers
  &middot; <?= count($units) ?> unit<?= count($units) === 1 ? '' : 's' ?>
  &middot; <?= $totalChests ?> chest number<?= $totalChests === 1 ? '' : 's' ?>
</div>

<table>
  <thead>
    <tr>
      <th style="width:60px" class="text-center">Sl. No</th>
      <th style="width:34%">Name of Unit / Institution</th>
      <th>Chest Numbers</th>
      <th style="width:70px" class="text-center">Count</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($units)): ?>
      <tr><td colspan="4" class="text-center text-muted" style="padding:14px">No approved chest numbers allocated yet.</td></tr>
    <?php else: $sl = 0; foreach ($units as $u): $sl++; ?>
      <tr>
        <td class="text-center"><?= $sl ?></td>
        <td><?= e($u['unit_name']) ?></td>
        <td><?= e(implode(', ', $u['chests'])) ?></td>
        <td class="text-center"><?= (int)$u['count'] ?></td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>
