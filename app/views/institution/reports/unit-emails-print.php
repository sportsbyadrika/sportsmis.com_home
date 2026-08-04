<?php
/**
 * Institution Emails — institutions with at least one approved athlete and
 * their contact email ids (SPOC / login / unit-user), one row per institution.
 * Rendered inside the 'print' layout.
 * Expects: $event, $eventHash, $units (each: unit_name, count, emails[]).
 */
$pageTitle = 'Institution Emails — ' . ($event['name'] ?? '');
$totalAthletes = 0;
foreach ($units as $u) { $totalAthletes += (int)$u['count']; }
?>
<h1 class="h4 fw-bold mb-1"><?= e($event['name'] ?? '') ?></h1>
<div class="text-muted mb-3">
  Institution Emails
  &middot; <?= count($units) ?> institution<?= count($units) === 1 ? '' : 's' ?>
  &middot; <?= $totalAthletes ?> athlete<?= $totalAthletes === 1 ? '' : 's' ?>
</div>

<table>
  <thead>
    <tr>
      <th style="width:60px" class="text-center">Sl. No</th>
      <th>Name of Institution</th>
      <th style="width:130px" class="text-center">No. of Athletes</th>
      <th>Email ID(s)</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($units)): ?>
      <tr><td colspan="4" class="text-center text-muted" style="padding:14px">No institutions with athletes yet.</td></tr>
    <?php else: $sl = 0; foreach ($units as $u): $sl++; ?>
      <tr>
        <td class="text-center"><?= $sl ?></td>
        <td><?= e($u['unit_name']) ?></td>
        <td class="text-center"><?= (int)$u['count'] ?></td>
        <td><?= !empty($u['emails']) ? e(implode(', ', $u['emails'])) : '<span style="color:#888">—</span>' ?></td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>
