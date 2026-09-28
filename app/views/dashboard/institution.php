<?php
$pageTitle   = 'Dashboard';
$active_menu = 'dashboard';
// Show a "switch to athlete workspace" link only when this account also holds
// the athlete capability.
$showAthleteWs = \Core\Auth::role() !== 'super_admin'
              && in_array('athlete', \Core\Auth::capabilities(), true);
$show_athlete_ws = $showAthleteWs;
?>

<div class="row g-3">

  <!-- ── LEFT RAIL: institution profile + menu ── -->
  <div class="col-12 col-lg-4 col-xl-3">
    <?php require APP_ROOT . '/views/partials/institution-rail.php'; ?>
  </div>

  <!-- ── MAIN ── -->
  <div class="col-12 col-lg-8 col-xl-9">

    <!-- Events Open for Participation — join other events as a unit or register
         as an athlete. One list, status-aware (no separate duplicate). -->
    <?php require APP_ROOT . '/views/partials/eligible-events.php'; ?>

    <!-- My Active Events — events this institution organises. -->
    <?php if ($events): ?>
    <div class="sms-card p-3 mb-4">
      <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-event me-2"></i>My Events</h6>
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

  </div><!-- /main -->
</div><!-- /row -->

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
