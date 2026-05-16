<?php $pageTitle = 'Team Entries'; ?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i>Team Entries</h5>
    <div class="text-muted small mt-1">
      Event: <strong><?= e($event['name']) ?></strong> · Code: <code><?= e($event['event_code'] ?? '') ?></code>
    </div>
  </div>
  <a href="/team-entry/new" class="btn btn-primary">
    <i class="bi bi-plus-lg me-1"></i>New Team Entry
  </a>
</div>

<?php if (empty($teams)): ?>
  <div class="sms-empty-state">
    <i class="bi bi-people"></i>
    <h5>No Team Entries Yet</h5>
    <p>Start a new team entry to register a team of three approved athletes for a team-eligible event.</p>
    <a href="/team-entry/new" class="btn btn-primary mt-2">Create Team Entry</a>
  </div>
<?php else: ?>
<div class="sms-card p-3">
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Team Name</th>
          <th>Unit</th>
          <th>Category / Event</th>
          <th>Members</th>
          <th class="text-end">Team Fee</th>
          <th>Submission</th>
          <th>Payment</th>
          <th class="text-end"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($teams as $t):
          $submitted = !empty($t['submitted_at']);
        ?>
          <tr>
            <td class="fw-medium"><?= e($t['team_name']) ?></td>
            <td class="small"><?= e($t['unit_name'] ?? '—') ?></td>
            <td class="small text-muted">
              <?= e($t['category_name'] ?? '—') ?>
              <?php if (!empty($t['sport_event_name'])): ?>
                <div><?= e($t['sport_event_name']) ?>
                  <?php if (!empty($t['event_code'])): ?><code class="ms-1"><?= e($t['event_code']) ?></code><?php endif; ?>
                </div>
              <?php endif; ?>
            </td>
            <td><?= (int)$t['members_count'] ?> / 3</td>
            <td class="text-end">
              <?php $f = (float)($t['total_amount'] ?? 0); ?>
              <?= $f > 0 ? '₹' . number_format($f, 2) : '—' ?>
            </td>
            <td>
              <?php if (!$submitted): ?>
                <span class="badge bg-secondary">Draft</span>
              <?php else: ?>
                <?= appStatusBadge($t['admin_review_status'] ?? null, $t['submitted_at']) ?>
              <?php endif; ?>
            </td>
            <td><?= statusBadge($t['payment_status'] ?? 'pending') ?></td>
            <td class="text-end">
              <a href="/team-entry/<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-<?= $submitted && \Models\TeamRegistration::isEditable($t) === false ? 'eye' : 'pencil' ?> me-1"></i>
                <?= $submitted && \Models\TeamRegistration::isEditable($t) === false ? 'View' : 'Open' ?>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
