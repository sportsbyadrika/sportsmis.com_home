<?php
$pageTitle = 'Athlete Dashboard';
$profileComplete = (bool)($athlete['profile_completed'] ?? false);

// This account's own participations (pending or accepted). When any exist, the
// Events (for Participation) panel opens by default instead of Active Events.
$myParticipations = array_values(array_filter($participation_events ?? [], function ($pe) {
    $st = (string)($pe['request_status'] ?? '');
    return $st === 'pending' || $st === 'approved' || !empty($pe['linked_unit_id']);
}));
$hasParticipations = !empty($has_institution) && !empty($myParticipations);
?>

<?php if (!$profileComplete): ?>
<div class="alert alert-warning d-flex align-items-center gap-3 mb-4" role="alert">
  <i class="bi bi-person-exclamation fs-4 flex-shrink-0"></i>
  <div>
    <strong>Profile Incomplete.</strong> Complete your profile to register for events.
    <a href="/athlete/profile" class="alert-link ms-2">Complete Now →</a>
  </div>
</div>
<?php endif; ?>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
  <div class="d-flex align-items-center gap-3 flex-grow-1 min-w-0">
    <?php if ($athlete['passport_photo']): ?>
      <img src="<?= e($athlete['passport_photo']) ?>" alt="Photo"
           class="rounded-circle flex-shrink-0" width="56" height="56" style="object-fit:cover">
    <?php else: ?>
      <div class="sms-avatar sms-avatar-lg flex-shrink-0"><?= avatarInitials($athlete['name']) ?></div>
    <?php endif; ?>
    <div class="min-w-0">
      <h4 class="mb-0 fw-bold text-break"><?= e($athlete['name']) ?></h4>
      <small class="text-muted"><?= ucfirst($athlete['gender'] ?? '') ?>
        <?php if ($athlete['date_of_birth']): ?>
          &nbsp;·&nbsp; <?= ageFromDob($athlete['date_of_birth']) ?> yrs
        <?php endif; ?>
      </small>
    </div>
  </div>
  <?php if ($profileComplete): ?>
    <a href="#panel-events" data-panel="#panel-events" class="btn btn-primary w-100 w-sm-auto flex-shrink-0 dash-toggle">
      <i class="bi bi-search me-2"></i>Browse Active Events
    </a>
  <?php else: ?>
    <a href="/athlete/profile" class="btn btn-warning w-100 w-sm-auto flex-shrink-0">
      <i class="bi bi-pencil me-2"></i>Complete Profile
    </a>
  <?php endif; ?>
</div>

<!-- Count cards (3/4 width) with the Organise-an-event panel on the right (1/4) -->
<div class="row g-3 mb-4">
  <div class="col-lg-9">

    <!-- Stats -->
    <div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="sms-stat-card">
      <div class="sms-stat-icon bg-primary-subtle text-primary"><i class="bi bi-list-check"></i></div>
      <div class="sms-stat-body">
        <div class="sms-stat-value"><?= count($registrations) ?></div>
        <div class="sms-stat-label">Events Registered</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="sms-stat-card">
      <div class="sms-stat-icon bg-success-subtle text-success"><i class="bi bi-check2-circle"></i></div>
      <div class="sms-stat-body">
        <div class="sms-stat-value"><?= count(array_filter($registrations, fn($r) => $r['status'] === 'confirmed')) ?></div>
        <div class="sms-stat-label">Confirmed</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="sms-stat-card">
      <div class="sms-stat-icon bg-warning-subtle text-warning"><i class="bi bi-credit-card"></i></div>
      <div class="sms-stat-body">
        <div class="sms-stat-value"><?= count(array_filter($registrations, fn($r) => $r['payment_status'] === 'pending')) ?></div>
        <div class="sms-stat-label">Payments Pending</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="sms-stat-card">
      <div class="sms-stat-icon bg-info-subtle text-info"><i class="bi bi-person-check"></i></div>
      <div class="sms-stat-body">
        <div class="sms-stat-value"><?= $profileComplete ? '100%' : 'Incomplete' ?></div>
        <div class="sms-stat-label">Profile Status</div>
      </div>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <a href="/athlete/profile" class="sms-action-card text-decoration-none">
      <div class="sms-action-icon text-primary"><i class="bi bi-person-badge"></i></div>
      <div>
        <div class="fw-semibold">My Profile</div>
        <small class="text-muted">Update photo, sports &amp; ID proof</small>
      </div>
      <i class="bi bi-chevron-right ms-auto text-muted"></i>
    </a>
  </div>
  <div class="col-md-6">
    <a href="/athlete/my-registrations" class="sms-action-card text-decoration-none">
      <div class="sms-action-icon text-info"><i class="bi bi-list-check"></i></div>
      <div>
        <div class="fw-semibold">My Registrations</div>
        <small class="text-muted">Track event &amp; payment status</small>
      </div>
      <i class="bi bi-chevron-right ms-auto text-muted"></i>
    </a>
  </div>
</div>

<!-- Participation summary — the ways this one account takes part.
     Each card reveals its matching panel below (see .dash-toggle JS). -->
<?php $partCol = !empty($has_institution) ? 'col-sm-6 col-lg-3' : 'col-md-4'; ?>
<div class="row g-3 mb-4">
  <div class="<?= $partCol ?>">
    <a href="#panel-events" data-panel="#panel-events"
       class="sms-card dash-toggle dash-card<?= $hasParticipations ? '' : ' active' ?> p-3 h-100 text-decoration-none d-flex align-items-center gap-3 sms-hover-lift">
      <div class="sms-stat-icon bg-primary-subtle text-primary"><i class="bi bi-calendar-check"></i></div>
      <div class="min-w-0">
        <div class="fw-bold fs-4 lh-1"><?= count($registrations) ?></div>
        <div class="small text-muted">My Events</div>
      </div>
      <i class="bi bi-chevron-right ms-auto text-muted"></i>
    </a>
  </div>
  <div class="<?= $partCol ?>">
    <a href="#panel-units" data-panel="#panel-units"
       class="sms-card dash-toggle dash-card p-3 h-100 text-decoration-none d-flex align-items-center gap-3 sms-hover-lift">
      <div class="sms-stat-icon bg-success-subtle text-success"><i class="bi bi-buildings"></i></div>
      <div class="min-w-0">
        <div class="fw-bold fs-4 lh-1"><?= count($unit_access_cards ?? []) ?></div>
        <div class="small text-muted">My Participation as Unit</div>
      </div>
      <i class="bi bi-chevron-right ms-auto text-muted"></i>
    </a>
  </div>
  <div class="<?= $partCol ?>">
    <a href="#panel-staff" data-panel="#panel-staff"
       class="sms-card dash-toggle dash-card p-3 h-100 text-decoration-none d-flex align-items-center gap-3 sms-hover-lift">
      <div class="sms-stat-icon bg-info-subtle text-info"><i class="bi bi-clipboard-check"></i></div>
      <div class="min-w-0">
        <div class="fw-bold fs-4 lh-1"><?= count($event_staff_cards ?? []) ?></div>
        <div class="small text-muted">Event Staff Access</div>
      </div>
      <i class="bi bi-chevron-right ms-auto text-muted"></i>
    </a>
  </div>
  <?php if (!empty($has_institution)): ?>
  <div class="<?= $partCol ?>">
    <a href="#panel-participation" data-panel="#panel-participation"
       class="sms-card dash-toggle dash-card<?= $hasParticipations ? ' active' : '' ?> p-3 h-100 text-decoration-none d-flex align-items-center gap-3 sms-hover-lift">
      <div class="sms-stat-icon bg-warning-subtle text-warning"><i class="bi bi-bag-check"></i></div>
      <div class="min-w-0">
        <div class="fw-bold fs-4 lh-1"><?= (int)($participating_count ?? 0) ?></div>
        <div class="small text-muted">Events I&rsquo;m Participating In</div>
      </div>
      <i class="bi bi-chevron-right ms-auto text-muted"></i>
    </a>
  </div>
  <?php endif; ?>
    </div><!-- /participation summary row -->

  </div><!-- /col-lg-9 (count cards) -->

  <!-- Organise-an-event panel — right 1/4 of the count-cards band -->
  <div class="col-lg-3">
    <?php require APP_ROOT . '/views/partials/organiser-request-card.php'; ?>
  </div>
</div><!-- /count-cards + organise row -->

<!-- ── Toggleable panels — each revealed by its participation-summary card ── -->

<!-- My Events → Active Events (hidden by default when participations exist) -->
<div id="panel-events" class="dash-panel"<?= $hasParticipations ? ' style="display:none"' : '' ?>>
<?php if (!$profileComplete): ?>
  <div class="sms-card p-3 mb-4 text-center text-muted">
    <i class="bi bi-lock fs-3 d-block mb-2"></i>
    Complete your profile to browse and register for active events.
  </div>
<?php else: ?>
<div class="sms-card p-3 mb-4" id="activeEvents">
  <div class="d-flex align-items-center border-bottom pb-2 mb-3">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-event me-2"></i>Active Events</h6>
    <span class="badge bg-primary-subtle text-primary-emphasis ms-2"><?= count($active_events ?? []) ?></span>
  </div>

  <?php if (empty($active_events)): ?>
    <div class="text-center text-muted small py-3">
      <i class="bi bi-calendar2-x fs-3 d-block mb-2"></i>
      No active events open for registration right now. Check back later.
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($active_events as $ev): $myReg = $reg_by_event[(int)$ev['id']] ?? null; ?>
        <div class="col-12 col-md-6 col-xl-4">
          <div class="border rounded-3 h-100 d-flex flex-column bg-white">
          <div class="p-3 d-flex align-items-start gap-3 border-bottom">
            <?php if (!empty($ev['logo'])): ?>
              <img src="<?= e($ev['logo']) ?>" alt="" width="56" height="56"
                   class="rounded-3 flex-shrink-0" style="object-fit:cover;border:1px solid #e2e8f0;background:#fff">
            <?php else: ?>
              <div class="sms-event-icon sms-event-icon-lg flex-shrink-0"><i class="bi bi-trophy"></i></div>
            <?php endif; ?>
            <div class="flex-grow-1 min-w-0">
              <div class="fw-semibold text-break" title="<?= e($ev['name']) ?>"><?= e($ev['name']) ?></div>
              <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                <?= statusBadge($ev['status']) ?>
                <small class="text-muted text-break"><?= e($ev['institution_name']) ?></small>
              </div>
            </div>
          </div>
          <div class="p-3 small flex-grow-1">
            <div class="d-flex align-items-start gap-2 mb-2">
              <i class="bi bi-geo-alt text-primary mt-1"></i>
              <div class="flex-grow-1"><span class="text-muted d-block">Venue</span><strong><?= e($ev['location']) ?></strong></div>
            </div>
            <div class="d-flex align-items-start gap-2 mb-2">
              <i class="bi bi-calendar3 text-success mt-1"></i>
              <div class="flex-grow-1"><span class="text-muted d-block">Event Dates</span><strong><?= formatDate($ev['event_date_from']) ?> – <?= formatDate($ev['event_date_to']) ?></strong></div>
            </div>
            <div class="d-flex align-items-start gap-2 <?= $myReg ? 'mb-2' : '' ?>">
              <i class="bi bi-person-plus text-warning mt-1"></i>
              <div class="flex-grow-1"><span class="text-muted d-block">Registration</span><strong><?= formatDate($ev['reg_date_from']) ?> – <?= formatDate($ev['reg_date_to']) ?></strong></div>
            </div>

            <?php if ($myReg): ?>
              <div class="border-top pt-2 mt-2">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="text-muted">Date of Application</span>
                  <strong><?= formatDate($myReg['submitted_at'] ?? $myReg['registered_at'], 'd M Y') ?></strong>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="text-muted">Application Status</span>
                  <?= appStatusBadge($myReg['admin_review_status'] ?? null, $myReg['submitted_at'] ?? null) ?>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                  <span class="text-muted">Payment Status</span>
                  <?= statusBadge($myReg['payment_status'] ?? 'pending') ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
          <div class="p-3 pt-0 d-flex gap-2 mt-auto">
            <a href="/athlete/events/<?= e(hid_event((int)$ev['id'])) ?>" class="btn btn-sm btn-outline-primary flex-fill">
              <i class="bi bi-info-circle me-1"></i>Details
            </a>
            <?php if ($myReg):
              $myStatus    = $myReg['admin_review_status'] ?? '';
              $hasCard     = $myStatus === 'approved' && !empty($myReg['competitor_number']);
              $cardPending = $myStatus === 'approved' && empty($myReg['competitor_number']);
            ?>
              <a href="/athlete/registrations/<?= e(hid_reg((int)$myReg['id'])) ?>" class="btn btn-sm btn-outline-secondary flex-fill">
                <i class="bi bi-eye me-1"></i>View
              </a>
              <?php if ($hasCard): ?>
                <a href="/athlete/registrations/<?= e(hid_reg((int)$myReg['id'])) ?>/card" target="_blank"
                   class="btn btn-sm btn-success flex-fill">
                  <i class="bi bi-card-heading me-1"></i>Card
                </a>
              <?php elseif ($cardPending): ?>
                <button type="button" class="btn btn-sm btn-outline-success flex-fill" disabled
                        title="Approved — Competitor Card will be issued by the organiser shortly.">
                  <i class="bi bi-hourglass-split me-1"></i>Card pending
                </button>
              <?php elseif (\Models\EventRegistration::isEditable($myReg)): ?>
                <a href="/athlete/events/<?= e(hid_event((int)$ev['id'])) ?>/register" class="btn btn-sm btn-primary flex-fill">
                  <i class="bi bi-pencil me-1"></i>Edit
                </a>
              <?php else: ?>
                <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" disabled
                        title="Locked — registration is under review">
                  <i class="bi bi-lock me-1"></i>Locked
                </button>
              <?php endif; ?>
            <?php else: ?>
              <a href="/athlete/events/<?= e(hid_event((int)$ev['id'])) ?>/register" class="btn btn-sm btn-primary flex-fill">
                <i class="bi bi-check-circle me-1"></i>Register
              </a>
            <?php endif; ?>
          </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
  <?php if (empty($registrations)): ?>
  <div class="sms-empty-state">
    <i class="bi bi-calendar-plus"></i>
    <h5>No Registrations Yet</h5>
    <p>Pick an active event from the list above to get started.</p>
  </div>
  <?php endif; ?>
<?php endif; ?>
</div><!-- /panel-events -->

<!-- My Participation as Unit → Unit / Club Access -->
<div id="panel-units" class="dash-panel" style="display:none">
<?php if (empty($unit_access_cards ?? [])): ?>
  <div class="sms-card p-3 mb-4 text-center text-muted">
    <i class="bi bi-buildings fs-3 d-block mb-2"></i>
    No unit / club access yet. When an organiser adds you as a unit / club
    operator using this account&rsquo;s email, it appears here.
  </div>
<?php else: ?>
  <?php require APP_ROOT . '/views/partials/unit-access-cards.php'; ?>
<?php endif; ?>
</div><!-- /panel-units -->

<!-- Event Staff Access -->
<div id="panel-staff" class="dash-panel" style="display:none">
<?php if (empty($event_staff_cards ?? [])): ?>
  <div class="sms-card p-3 mb-4 text-center text-muted">
    <i class="bi bi-clipboard-check fs-3 d-block mb-2"></i>
    No event staff access yet. When an organiser adds you as event staff
    using this account&rsquo;s email, it appears here.
  </div>
<?php else: ?>
  <?php require APP_ROOT . '/views/partials/event-staff-cards.php'; ?>
<?php endif; ?>
</div><!-- /panel-staff -->

<!-- Events I'm Participating In (organiser-side) — only for accounts with an institution.
     Athlete view shows just this account's pending + accepted participations. -->
<?php if (!empty($has_institution)): ?>
  <?php $participation_mine_only = true; $participation_open = $hasParticipations; ?>
  <?php require APP_ROOT . '/views/partials/participation-events.php'; ?>
<?php endif; ?>

<!-- Participation cards reveal their matching panel (see .dash-toggle). -->
<script>
(function(){
  var triggers = document.querySelectorAll('.dash-toggle');
  var panels   = document.querySelectorAll('.dash-panel');
  var cards    = document.querySelectorAll('.dash-card');
  function activate(sel){
    panels.forEach(function(p){ p.style.display = ('#'+p.id === sel) ? '' : 'none'; });
    cards.forEach(function(c){ c.classList.toggle('active', c.getAttribute('data-panel') === sel); });
  }
  triggers.forEach(function(t){
    t.addEventListener('click', function(e){
      var sel = t.getAttribute('data-panel');
      if (!sel) return;
      e.preventDefault();
      activate(sel);
      var el = document.querySelector(sel);
      if (el) el.scrollIntoView({behavior:'smooth', block:'start'});
    });
  });
})();
</script>
