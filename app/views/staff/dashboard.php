<?php
$pageTitle = 'Staff Dashboard';
$priv = $staff['privileges'] ?? [];
// Card definitions. The display ORDER is controlled by $cardOrder below,
// not by the privileges stored against the staff member.
$cards = [
  'order_of_events' => [
    'url'   => '/event-staff/order-of-events',
    'icon'  => 'bi-list-ol',
    'title' => 'Order of Events',
    'desc'  => 'Schedule each event (date, time, serial no.) and track its call-room status.',
  ],
  'lane_allocation' => [
    'url'   => '/lane-allocation',
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
  'team_entry' => [
    'url'   => '/team-entry',
    'icon'  => 'bi-people',
    'title' => 'Team Entry',
    'desc'  => 'Capture and submit team entries for units competing in this event.',
  ],
];
// Fixed left-to-right order requested by the event organiser.
$cardOrder = ['order_of_events', 'lane_allocation', 'scoring', 'result_reports', 'team_entry'];
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
    <?php
      $teamEntryAllowed = in_array('event_staff', \eventTeamEntryMethods($event), true);
      foreach ($cardOrder as $p):
        if (!in_array($p, $priv, true)) continue;   // staff must hold the privilege
        if (!isset($cards[$p])) continue;
        // Team Entry card only when the event admin allows the Event Staff method.
        if ($p === 'team_entry' && !$teamEntryAllowed) continue;
        $c = $cards[$p];
    ?>
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
