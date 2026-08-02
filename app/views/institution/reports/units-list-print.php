<?php
/**
 * List of Units — one row per event unit with the approved-athlete count and
 * its Unit Relay Code. Rendered inside the 'print' layout.
 * Expects: $event, $eventHash, $units (each: unit_name, relay_code, count).
 */
$pageTitle = 'List of Units — ' . ($event['name'] ?? '');
$totalAthletes = 0;
foreach ($units as $u) { $totalAthletes += (int)$u['count']; }
?>
<h1 class="h4 fw-bold mb-1"><?= e($event['name'] ?? '') ?></h1>
<div class="text-muted mb-3">
  List of Units
  &middot; <?= count($units) ?> unit<?= count($units) === 1 ? '' : 's' ?>
  &middot; <?= $totalAthletes ?> athlete<?= $totalAthletes === 1 ? '' : 's' ?>
</div>

<table>
  <thead>
    <tr>
      <th style="width:60px" class="text-center">Sl. No</th>
      <th>Name of Institution</th>
      <th style="width:150px" class="text-center">Number of Athletes</th>
      <th style="width:110px" class="text-center">Relay Code</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($units)): ?>
      <tr><td colspan="4" class="text-center text-muted" style="padding:14px">No units on this event yet.</td></tr>
    <?php else: $sl = 0; foreach ($units as $u): $sl++; ?>
      <tr>
        <td class="text-center"><?= $sl ?></td>
        <td><?= e($u['unit_name']) ?></td>
        <td class="text-center"><?= (int)$u['count'] ?></td>
        <td class="text-center"><?= e($u['relay_code']) !== '' ? e($u['relay_code']) : '—' ?></td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>
