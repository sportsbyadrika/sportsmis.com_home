<?php
$pageTitle = 'Team Entries — ' . $event['name'];
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken    = $_SESSION['csrf_token'];
$eventHash    = hid_event((int)$event['id']);
$windowOpen   = eventTeamEntryWindowOpen($event);
$statusLabels = [
  'pending'  => 'Pending',
  'approved' => 'Approved',
  'rejected' => 'Rejected',
  'returned' => 'Returned',
  'draft'    => 'Draft (not submitted)',
];
?>

<?= flashBag() ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/edit" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i>Team Entries — <?= e($event['name']) ?></h5>
  <span class="badge bg-secondary"><?= count($teams) ?> team<?= count($teams) === 1 ? '' : 's' ?></span>

  <form method="POST"
        action="/institution/events/<?= e($eventHash) ?>/team-registrations/toggle-window"
        class="ms-auto m-0">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="open" value="<?= $windowOpen ? '0' : '1' ?>">
    <div class="form-check form-switch m-0 border rounded-3 px-3 py-2 bg-light d-inline-flex align-items-center gap-2">
      <input class="form-check-input m-0" type="checkbox" role="switch"
             id="teamWindowSwitch" <?= $windowOpen ? 'checked' : '' ?>
             onchange="this.form.submit()">
      <label class="form-check-label fw-medium small mb-0" for="teamWindowSwitch">
        Team Entry Submission
        <span class="badge ms-1 <?= $windowOpen ? 'bg-success' : 'bg-danger' ?>">
          <?= $windowOpen ? 'Open' : 'Closed' ?>
        </span>
      </label>
    </div>
  </form>
</div>

<?php if (!$windowOpen): ?>
<div class="alert alert-warning py-2 small mb-3">
  <i class="bi bi-lock me-1"></i>
  Team entry submissions are <strong>closed</strong>. Unit users and athletes can
  view their entries but cannot submit new ones. Event Staff can still submit
  on their behalf from the Team Entry portal.
</div>
<?php endif; ?>

<form method="GET" class="sms-card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label small mb-1">Sport Event</label>
      <select name="event_sport_id" class="form-select form-select-sm">
        <option value="0">All sport events</option>
        <?php foreach (($sport_events ?? []) as $se):
          $label = trim(($se['event_code'] ? $se['event_code'] . ' · ' : '') . ($se['sport_event_name'] ?? ''));
          if ($label === '') $label = $se['sport_name'] ?? ('#' . $se['id']);
        ?>
          <option value="<?= (int)$se['id'] ?>" <?= (int)($event_sport_filter ?? 0) === (int)$se['id'] ? 'selected' : '' ?>>
            <?= e($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1">Unit / Club / Institution</label>
      <select name="unit_id" class="form-select form-select-sm">
        <option value="0">All units</option>
        <?php foreach (($units ?? []) as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= (int)($unit_filter ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
            <?= e($u['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1">Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">All statuses</option>
        <?php foreach ($statusLabels as $val => $label): ?>
          <option value="<?= e($val) ?>" <?= ($status_filter ?? '') === $val ? 'selected' : '' ?>>
            <?= e($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Apply</button>
      <a href="/institution/events/<?= e($eventHash) ?>/team-registrations" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
    </div>
  </div>
</form>

<?php if (empty($teams)): ?>
  <div class="sms-empty-state">
    <i class="bi bi-people"></i>
    <h5>No Team Entries Yet</h5>
    <p>No team entries have been submitted for this event yet.</p>
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
