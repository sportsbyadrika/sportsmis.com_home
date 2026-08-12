<?php
$pageTitle = 'Institution Dashboard';
$incomplete = !($institution['profile_completed'] ?? false);
$pendingEvents  = array_filter($events, fn($e) => in_array($e['status'], ['draft','pending_approval'], true));
$approvedEvents = array_filter($events, fn($e) => in_array($e['status'], ['active','approved'], true));
// Show the Athlete-workspace side panel only when this account can actually
// open an athlete workspace (holds the athlete capability).
$showAthleteWs = \Core\Auth::role() !== 'super_admin'
              && in_array('athlete', \Core\Auth::capabilities(), true);
?>

<?php if ($incomplete): ?>
<div class="alert alert-warning d-flex align-items-center gap-3 mb-4" role="alert">
  <i class="bi bi-exclamation-triangle-fill fs-4 flex-shrink-0"></i>
  <div>
    <strong>Profile Incomplete.</strong> Please complete your institution profile to unlock all features.
    <a href="/institution/profile" class="alert-link ms-2">Complete Profile →</a>
  </div>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-4">
  <div class="d-flex align-items-center gap-3">
    <?php if ($institution['logo']): ?>
      <img src="<?= e($institution['logo']) ?>" alt="Logo" class="rounded-circle" width="56" height="56" style="object-fit:cover">
    <?php else: ?>
      <div class="sms-avatar sms-avatar-lg"><?= avatarInitials($institution['name']) ?></div>
    <?php endif; ?>
    <div>
      <h4 class="mb-0 fw-bold"><?= e($institution['name']) ?></h4>
      <small class="text-muted"><?= e($institution['type_name'] ?? 'Institution') ?></small>
    </div>
  </div>
  <?php if (!empty($institution['event_creation_enabled'])): ?>
  <a href="/institution/events/create" class="btn btn-primary">
    <i class="bi bi-plus-circle me-2"></i>New Event
  </a>
  <?php else: ?>
  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEventDisabledModal">
    <i class="bi bi-plus-circle me-2"></i>New Event
  </button>
  <?php endif; ?>
</div>

<!-- Count cards (3/4 width) with the Athlete-workspace panel on the right (1/4) -->
<div class="row g-3 mb-4">
  <div class="<?= $showAthleteWs ? 'col-lg-9' : 'col-12' ?>">

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="sms-stat-card">
      <div class="sms-stat-icon bg-primary-subtle text-primary"><i class="bi bi-calendar-event"></i></div>
      <div class="sms-stat-body">
        <div class="sms-stat-value"><?= count($events) ?></div>
        <div class="sms-stat-label">Total Events</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="sms-stat-card">
      <div class="sms-stat-icon bg-success-subtle text-success"><i class="bi bi-check-circle"></i></div>
      <div class="sms-stat-body">
        <div class="sms-stat-value"><?= count($approvedEvents) ?></div>
        <div class="sms-stat-label">Approved Events</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="sms-stat-card">
      <div class="sms-stat-icon bg-warning-subtle text-warning"><i class="bi bi-hourglass-split"></i></div>
      <div class="sms-stat-body">
        <div class="sms-stat-value"><?= count($pendingEvents) ?></div>
        <div class="sms-stat-label">Pending Approval</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="sms-stat-card">
      <div class="sms-stat-icon bg-info-subtle text-info">
        <?= $institution['validity_to'] ? '<i class="bi bi-shield-check"></i>' : '<i class="bi bi-shield-exclamation"></i>' ?>
      </div>
      <div class="sms-stat-body">
        <div class="sms-stat-value" style="font-size:1rem">
          <?= $institution['validity_to'] ? formatDate($institution['validity_to']) : 'Pending' ?>
        </div>
        <div class="sms-stat-label">Validity Till</div>
      </div>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <a href="/institution/profile" class="sms-action-card text-decoration-none">
      <div class="sms-action-icon text-primary"><i class="bi bi-building"></i></div>
      <div>
        <div class="fw-semibold">Institution Profile</div>
        <small class="text-muted">Update logo, type &amp; registration</small>
      </div>
      <i class="bi bi-chevron-right ms-auto text-muted"></i>
    </a>
  </div>
  <div class="col-md-4">
    <a href="/institution/events" class="sms-action-card text-decoration-none">
      <div class="sms-action-icon text-success"><i class="bi bi-calendar-event"></i></div>
      <div>
        <div class="fw-semibold">Manage Events</div>
        <small class="text-muted">Create and manage your events</small>
      </div>
      <i class="bi bi-chevron-right ms-auto text-muted"></i>
    </a>
  </div>
  <div class="col-md-4">
    <a href="#panel-participation" data-panel="#panel-participation"
       class="sms-action-card text-decoration-none dash-toggle dash-card">
      <div class="sms-action-icon text-success"><i class="bi bi-bag-check"></i></div>
      <div>
        <div class="fw-semibold">
          Events I&rsquo;m Participating In
          <?php $pc = (int)($participating_count ?? 0); if ($pc > 0): ?>
            <span class="badge bg-success ms-1"><?= $pc ?></span>
          <?php endif; ?>
        </div>
        <small class="text-muted">
          <?php if ($pc === 0): ?>
            None yet — browse public events to join
          <?php elseif ($pc === 1): ?>
            1 approved participation — open its Unit Console
          <?php else: ?>
            <?= $pc ?> approved participations
          <?php endif; ?>
        </small>
      </div>
      <i class="bi bi-chevron-right ms-auto text-muted"></i>
    </a>
  </div>
    </div><!-- /quick actions row -->

  </div><!-- /col-lg-9 (count cards) -->

  <?php if ($showAthleteWs): ?>
  <!-- Athlete-workspace panel — right 1/4 of the count-cards band -->
  <div class="col-lg-3">
    <?php require APP_ROOT . '/views/partials/athlete-workspace-card.php'; ?>
  </div>
  <?php endif; ?>
</div><!-- /count-cards + athlete-workspace row -->

<!-- Events (for Participation) — revealed by the "Events I'm Participating In" card -->
<?php $partEvents = $participation_events ?? []; ?>
<div id="panel-participation" class="dash-panel" style="display:none">
<?php if (empty($partEvents)): ?>
  <div class="sms-card p-3 mb-4 text-center text-muted">
    <i class="bi bi-people fs-3 d-block mb-2"></i>
    You haven&rsquo;t joined any events for participation yet.
    <div class="mt-3"><a href="/institution/public-events" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-search me-1"></i>Browse public events</a></div>
  </div>
<?php else: ?>
<div class="sms-card p-3 mb-4">
  <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-people me-2"></i>Events (for Participation)</h6>
    <a href="/institution/participating-events" class="btn btn-sm btn-outline-secondary">My Participations</a>
  </div>
  <div class="row g-3">
    <?php foreach ($partEvents as $pe):
      $peHash   = hid_event((int)$pe['id']);
      $reqStat  = (string)($pe['request_status'] ?? '');
      $hasUnit  = !empty($pe['linked_unit_id']);
      $canLogin = $hasUnit || $reqStat === 'approved';
      $pending  = !$canLogin && $reqStat === 'pending';
      $from = !empty($pe['event_date_from']) ? formatDate($pe['event_date_from'], 'd M Y') : '';
      $to   = !empty($pe['event_date_to'])   ? formatDate($pe['event_date_to'],   'd M Y') : '';
    ?>
      <div class="col-md-6 col-xl-4">
        <div class="border rounded-3 p-3 h-100 d-flex flex-column gap-2">
          <div class="d-flex align-items-center gap-2">
            <?php if (!empty($pe['logo'])): ?>
              <img src="<?= e($pe['logo']) ?>" alt="" width="40" height="40"
                   class="rounded" style="object-fit:cover;flex-shrink:0">
            <?php else: ?>
              <div class="rounded d-flex align-items-center justify-content-center flex-shrink-0"
                   style="width:40px;height:40px;background:#eef2f7;color:#94a3b8"><i class="bi bi-calendar-event"></i></div>
            <?php endif; ?>
            <div class="min-w-0">
              <div class="fw-semibold text-truncate" title="<?= e($pe['name']) ?>"><?= e($pe['name']) ?></div>
              <div class="small text-muted text-truncate"><?= e($pe['organiser_name'] ?? '') ?></div>
            </div>
          </div>
          <div class="small text-muted">
            <?php if (!empty($pe['location'])): ?>
              <div><i class="bi bi-geo-alt me-1"></i><?= e($pe['location']) ?></div>
            <?php endif; ?>
            <?php if ($from || $to): ?>
              <div><i class="bi bi-calendar3 me-1"></i><?= e($from) ?><?= ($from && $to && $from !== $to) ? ' – ' . e($to) : '' ?></div>
            <?php endif; ?>
          </div>
          <div class="d-flex flex-wrap gap-1">
            <?php if (!empty($pe['allow_athlete_registration'])): ?>
              <span class="badge bg-info-subtle text-info-emphasis" style="font-size:.65rem"><i class="bi bi-person-arms-up me-1"></i>Athlete</span>
            <?php endif; ?>
            <span class="badge bg-warning-subtle text-warning-emphasis" style="font-size:.65rem"><i class="bi bi-building me-1"></i>Institution</span>
          </div>
          <div class="mt-auto pt-1">
            <?php if ($canLogin): ?>
              <form method="POST" action="/institution/events/<?= e($peHash) ?>/open-as-unit" class="m-0">
                <?= csrf() ?>
                <button class="btn btn-sm btn-success w-100"><i class="bi bi-box-arrow-in-right me-1"></i>Login to Event</button>
              </form>
            <?php elseif ($pending): ?>
              <button type="button" class="btn btn-sm btn-outline-secondary w-100"
                      onclick="showEventSpoc('<?= e(addslashes($pe['name'] ?? '')) ?>', '<?= e(addslashes($pe['contact_name'] ?? '')) ?>', '<?= e(addslashes($pe['contact_email'] ?? '')) ?>', '<?= e(addslashes($pe['contact_mobile'] ?? '')) ?>')">
                <i class="bi bi-hourglass-split me-1"></i>Submitted
              </button>
            <?php else: ?>
              <form method="POST" action="/institution/events/<?= e($peHash) ?>/request-participation" class="m-0"
                    onsubmit="return confirm('Send a participation request to join this event as a unit?');">
                <?= csrf() ?>
                <button class="btn btn-sm btn-primary w-100"><i class="bi bi-send me-1"></i>Register to Join</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
</div><!-- /panel-participation -->

<!-- My Active Events — card grid (matches the Participation cards above) -->
<?php if ($events): ?>
<div class="sms-card p-3 mb-4">
  <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-event me-2"></i>My Active Events</h6>
    <a href="/institution/events" class="btn btn-sm btn-outline-primary">View All</a>
  </div>
  <div class="row g-3">
    <?php foreach (array_slice($events, 0, 6) as $event):
      $from = !empty($event['event_date_from']) ? formatDate($event['event_date_from'], 'd M Y') : '';
      $to   = !empty($event['event_date_to'])   ? formatDate($event['event_date_to'],   'd M Y') : '';
    ?>
      <div class="col-md-6 col-xl-4">
        <div class="border rounded-3 p-3 h-100 d-flex flex-column gap-2">
          <div class="d-flex align-items-center gap-2">
            <?php if (!empty($event['logo'])): ?>
              <img src="<?= e($event['logo']) ?>" alt="" width="40" height="40"
                   class="rounded" style="object-fit:cover;flex-shrink:0">
            <?php else: ?>
              <div class="rounded d-flex align-items-center justify-content-center flex-shrink-0"
                   style="width:40px;height:40px;background:#eef2f7;color:#94a3b8"><i class="bi bi-calendar-event"></i></div>
            <?php endif; ?>
            <div class="min-w-0">
              <div class="fw-semibold text-truncate" title="<?= e($event['name']) ?>"><?= e($event['name']) ?></div>
              <div class="mt-1"><?= statusBadge($event['status']) ?></div>
            </div>
          </div>
          <div class="small text-muted">
            <?php if (!empty($event['location'])): ?>
              <div><i class="bi bi-geo-alt me-1"></i><?= e($event['location']) ?></div>
            <?php endif; ?>
            <?php if ($from || $to): ?>
              <div><i class="bi bi-calendar3 me-1"></i><?= e($from) ?><?= ($from && $to && $from !== $to) ? ' – ' . e($to) : '' ?></div>
            <?php endif; ?>
          </div>
          <div class="mt-auto pt-1">
            <a href="/institution/events/<?= $event['id'] ?>/view" class="btn btn-sm btn-outline-secondary w-100">
              <i class="bi bi-eye me-1"></i>View
            </a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php else: ?>
<div class="sms-empty-state">
  <i class="bi bi-calendar-plus"></i>
  <h5>No Events Yet</h5>
  <p>Create your first event to get started.</p>
  <?php if (!empty($institution['event_creation_enabled'])): ?>
  <a href="/institution/events/create" class="btn btn-primary">
    <i class="bi bi-plus-circle me-2"></i>Create Event
  </a>
  <?php else: ?>
  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEventDisabledModal">
    <i class="bi bi-plus-circle me-2"></i>Create Event
  </button>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- The "Events I'm Participating In" card reveals its panel (see .dash-toggle). -->
<script>
(function(){
  var triggers = document.querySelectorAll('.dash-toggle');
  var panels   = document.querySelectorAll('.dash-panel');
  var cards    = document.querySelectorAll('.dash-card');
  function activate(sel){
    var open = false;
    panels.forEach(function(p){
      var show = ('#'+p.id === sel);
      p.style.display = show ? '' : 'none';
      if (show) open = true;
    });
    cards.forEach(function(c){ c.classList.toggle('active', c.getAttribute('data-panel') === sel); });
    return open;
  }
  triggers.forEach(function(t){
    t.addEventListener('click', function(e){
      var sel = t.getAttribute('data-panel');
      if (!sel) return;
      e.preventDefault();
      var target = document.querySelector(sel);
      // Toggle off if this panel is already open.
      if (target && target.style.display !== 'none' && t.classList.contains('active')) {
        target.style.display = 'none';
        t.classList.remove('active');
        return;
      }
      activate(sel);
      if (target) target.scrollIntoView({behavior:'smooth', block:'start'});
    });
  });
})();
</script>

<!-- Event SPOC details (for a submitted participation request) -->
<div class="modal fade" id="eventSpocModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-semibold"><i class="bi bi-person-vcard me-2"></i>Event Contact (SPOC)</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-3">
          Your participation request for <strong id="spocEventName"></strong> is
          <span class="badge bg-info-subtle text-info-emphasis">Submitted</span> and awaiting the
          organiser's review. For any query, contact the event SPOC:
        </p>
        <ul class="list-unstyled mb-0 small">
          <li class="mb-2"><i class="bi bi-person me-2 text-muted"></i><span id="spocName">—</span></li>
          <li class="mb-2"><i class="bi bi-envelope me-2 text-muted"></i><span id="spocEmail">—</span></li>
          <li><i class="bi bi-telephone me-2 text-muted"></i><span id="spocMobile">—</span></li>
        </ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<script>
let _spocModal = null;
function showEventSpoc(eventName, name, email, mobile) {
  if (!_spocModal) _spocModal = new bootstrap.Modal(document.getElementById('eventSpocModal'));
  document.getElementById('spocEventName').textContent = eventName || 'this event';
  document.getElementById('spocName').textContent   = name   || '—';
  document.getElementById('spocEmail').textContent  = email  || '—';
  document.getElementById('spocMobile').textContent = mobile || '—';
  _spocModal.show();
}
</script>

<!-- Create Event — facility-not-enabled notice -->
<div class="modal fade" id="createEventDisabledModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-semibold">
          <i class="bi bi-info-circle me-2 text-primary"></i>Feature Not Enabled
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">
          It looks like this facility isn&rsquo;t enabled for your profile yet.
          Want to activate it? Please reach out to the SportsMIS team.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Got it</button>
      </div>
    </div>
  </div>
</div>
