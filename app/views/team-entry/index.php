<?php
$pageTitle = 'Team Entries';
$isStaffView = ($actor['type'] ?? '') === 'event_staff';
$canSubmit   = $isStaffView || eventTeamEntryWindowOpen($event);
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i>Team Entries</h5>
    <div class="text-muted small mt-1">
      Event: <strong><?= e($event['name']) ?></strong> · Code: <code><?= e($event['event_code'] ?? '') ?></code>
      <?php if ($isStaffView): ?>
        · <span class="badge bg-info-subtle text-info-emphasis">Showing all team entries (Staff view)</span>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($canSubmit): ?>
    <a href="/team-entry/new" class="btn btn-primary">
      <i class="bi bi-plus-lg me-1"></i>New Team Entry
    </a>
  <?php else: ?>
    <button class="btn btn-primary" type="button" disabled
            title="Team entry submissions are closed by the event administrator">
      <i class="bi bi-lock me-1"></i>Submissions Closed
    </button>
  <?php endif; ?>
</div>

<?php if (!$canSubmit): ?>
  <div class="alert alert-warning py-2 small mb-3">
    <i class="bi bi-lock me-1"></i>
    Team entry submissions are <strong>closed</strong> by the event administrator.
    You can still view your existing entries and their status, but new entries
    and final submissions are paused until they re-open the window.
  </div>
<?php endif; ?>

<?php if (empty($teams)): ?>
  <div class="sms-empty-state">
    <i class="bi bi-people"></i>
    <h5>No Team Entries Yet</h5>
    <p>Start a new team entry to register a team of three approved athletes for a team-eligible event.</p>
    <?php if ($canSubmit): ?>
      <a href="/team-entry/new" class="btn btn-primary mt-2">Create Team Entry</a>
    <?php endif; ?>
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
          <?php if ($isStaffView): ?><th>Submitted By</th><?php endif; ?>
          <th>Submission</th>
          <th>Payment</th>
          <th class="text-end"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($teams as $t):
          $submitted = !empty($t['submitted_at']);
          $byType = $t['created_by_type'] ?? ($t['athlete_id'] ? 'athlete' : '');
          $byLabel = ['athlete'=>'Athlete','unit_user'=>'Unit User','event_staff'=>'Event Staff'][$byType] ?? '';
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
            <?php if ($isStaffView): ?>
              <td class="small">
                <?= e($t['submitted_by_name'] ?? $t['captain_name'] ?? '—') ?>
                <?php if ($byLabel): ?>
                  <div><span class="badge bg-secondary-subtle text-secondary"><?= e($byLabel) ?></span></div>
                <?php endif; ?>
              </td>
            <?php endif; ?>
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
