<?php
$pageTitle = 'Staff Dashboard';
$priv = $staff['privileges'] ?? [];
$cards = [
  'team_entry' => [
    'url'   => '/team-entry',
    'icon'  => 'bi-people',
    'title' => 'Team Entry',
    'desc'  => 'Capture and submit team entries for units competing in this event.',
  ],
  'lane_allocation' => [
    'url'   => '/event-staff/lane-allocation',
    'icon'  => 'bi-bullseye',
    'title' => 'Lane Allocation — Admin',
    'desc'  => 'Admin-side allocation of lanes per unit for the event.',
  ],
  'scoring' => [
    'url'   => '/event-staff/scoring',
    'icon'  => 'bi-pencil-square',
    'title' => 'Scoring',
    'desc'  => 'Score entry and management.',
  ],
  'result_reports' => [
    'url'   => '/event-staff/result-reports',
    'icon'  => 'bi-trophy',
    'title' => 'Result Reports',
    'desc'  => 'Generation and display of event results.',
  ],
];
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold"><i class="bi bi-speedometer2 me-2"></i>Staff Dashboard</h5>
    <div class="text-muted small mt-1">
      Event: <strong><?= e($event['name']) ?></strong>
      · Code: <code><?= e($event['event_code'] ?? '') ?></code>
    </div>
  </div>
</div>

<div class="sms-card p-3 mb-4">
  <div class="d-flex align-items-center gap-3 flex-wrap">
    <div class="sms-avatar sms-avatar-lg"><?= avatarInitials($staff['name'] ?? '') ?></div>
    <div>
      <div class="fw-bold fs-5"><?= e($staff['name']) ?></div>
      <div class="small text-muted"><?= e($staff['email']) ?> · Event Staff</div>
    </div>
  </div>
</div>

<?php if (empty($priv)): ?>
  <div class="sms-empty-state">
    <i class="bi bi-shield-exclamation"></i>
    <h5>No Privileges Assigned</h5>
    <p>The event administrator hasn't assigned any privileges to your account yet. Please contact the organiser.</p>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($priv as $p): if (!isset($cards[$p])) continue; $c = $cards[$p]; ?>
      <div class="col-md-6 col-lg-3">
        <a href="<?= e($c['url']) ?>" class="text-decoration-none">
          <div class="sms-card p-4 h-100 text-center sms-hover-lift">
            <div class="display-6 text-primary mb-2"><i class="bi <?= e($c['icon']) ?>"></i></div>
            <h6 class="fw-bold mb-1"><?= e($c['title']) ?></h6>
            <p class="small text-muted mb-0"><?= e($c['desc']) ?></p>
            <?php if ($p === 'team_entry'): ?>
              <span class="badge bg-secondary-subtle text-secondary mt-2"><?= (int)$team_count ?> team entr<?= (int)$team_count === 1 ? 'y' : 'ies' ?></span>
            <?php endif; ?>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
