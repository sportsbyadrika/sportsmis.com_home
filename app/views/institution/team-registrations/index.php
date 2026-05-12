<?php $pageTitle = 'Team Registrations — ' . $event['name']; ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e(hid_event((int)$event['id'])) ?>/edit" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i>Team Registrations — <?= e($event['name']) ?></h5>
  <span class="badge bg-secondary"><?= count($teams) ?> team<?= count($teams) === 1 ? '' : 's' ?></span>
</div>

<?php if (empty($teams)): ?>
  <div class="sms-empty-state">
    <i class="bi bi-people"></i>
    <h5>No Team Registrations Yet</h5>
    <p>Athletes haven't submitted any team entries for this event yet.</p>
  </div>
<?php else: ?>
<div class="sms-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Event Name</th>
          <th>Unit / Club / Institution</th>
          <th>Team Name</th>
          <th>Athlete 1</th>
          <th>Athlete 2</th>
          <th>Athlete 3</th>
          <th class="text-end">Total Fees</th>
          <th>Application</th>
          <th>Submitted</th>
          <th>Status</th>
          <th>Payment</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($teams as $i => $t): ?>
          <?php
            $ms = $members_by_team[(int)$t['id']] ?? [];
            $a = [null, null, null];
            foreach ($ms as $idx => $m) { if ($idx < 3) $a[$idx] = $m; }
            $tot = (float)($t['total_amount'] ?? 0);
          ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td>
              <div class="fw-medium"><?= e($event['name']) ?></div>
              <?php if (!empty($t['sport_event_name'])): ?>
                <small class="text-muted">
                  <i class="bi bi-trophy me-1"></i><?= e($t['sport_name'] ?? '') ?>
                  · <?= e($t['sport_event_name']) ?>
                </small>
              <?php endif; ?>
            </td>
            <td class="small"><?= e($t['unit_name'] ?? '—') ?></td>
            <td class="fw-medium"><?= e($t['team_name']) ?></td>
            <?php for ($k = 0; $k < 3; $k++): ?>
              <td class="small">
                <?php if ($a[$k]): ?>
                  <div><code><?= (int)$a[$k]['competitor_number'] ?></code></div>
                  <div class="text-muted"><?= e($a[$k]['athlete_name'] ?? '') ?></div>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
            <?php endfor; ?>
            <td class="text-end fw-medium">
              <?= $tot > 0 ? '₹' . number_format($tot, 2) : '—' ?>
            </td>
            <td><?= appStatusBadge($t['admin_review_status'] ?? null, $t['submitted_at'] ?? null) ?></td>
            <td class="small text-muted">
              <?= !empty($t['submitted_at']) ? formatDate($t['submitted_at'], 'd M Y H:i') : '<em>—</em>' ?>
            </td>
            <td class="small"><?= e(ucfirst((string)$t['status'])) ?></td>
            <td><?= statusBadge($t['payment_status'] ?? 'pending') ?></td>
            <td class="text-end">
              <a href="/institution/team-registrations/<?= (int)$t['id'] ?>"
                 class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye me-1"></i>View</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
