<?php
/**
 * "Events Open for Participation" — the directory of active events a viewer
 * can take part in, combining events open for individual athlete registration
 * and events open for institution/unit join requests. Shared by the athlete
 * and institution dashboards.
 *
 * Expects:
 *   $eligible_events (array)  — from Event::eligibleParticipationEvents()
 * Optional:
 *   $reg_by_event (array)            — this account's athlete registrations, keyed by event id
 *   $viewer_is_athlete (bool)        — account has an athlete profile
 *   $athlete_profile_complete (bool) — that profile is complete (can register)
 *   $viewer_has_institution (bool)   — account owns an institution (can join as unit)
 */
$eligEvents        = $eligible_events ?? [];
$eligRegByEvent    = $reg_by_event ?? [];
$eligIsAthlete     = !empty($viewer_is_athlete);
$eligProfileOk     = !empty($athlete_profile_complete);
$eligHasInstitution= !empty($viewer_has_institution);
// Institution-type options for the "become a unit" modal (only needed when the
// viewer has no institution yet).
$eligTypes = [];
if (!$eligHasInstitution) {
    try { $eligTypes = \Models\Institution::getTypes(); } catch (\Throwable $e) { $eligTypes = []; }
}
?>
<div class="sms-card p-3 mb-4" id="eligibleEvents">
  <div class="d-flex align-items-center border-bottom pb-2 mb-3">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-check me-2"></i>Events Open for Participation</h6>
    <span class="badge bg-primary-subtle text-primary-emphasis ms-2"><?= count($eligEvents) ?></span>
  </div>

  <?php if (empty($eligEvents)): ?>
    <div class="text-center text-muted small py-3">
      <i class="bi bi-calendar2-x fs-3 d-block mb-2"></i>
      No events are open for participation right now. Check back later.
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($eligEvents as $ev):
        $evHash    = hid_event((int)$ev['id']);
        $eligAth   = !empty($ev['elig_athlete']);
        $eligUnit  = !empty($ev['elig_unit']);
        $reqStat   = (string)($ev['request_status'] ?? '');
        $hasUnit   = !empty($ev['linked_unit_id']);
        $myReg     = $eligRegByEvent[(int)$ev['id']] ?? null;
        $from = !empty($ev['event_date_from']) ? formatDate($ev['event_date_from'], 'd M Y') : '';
        $to   = !empty($ev['event_date_to'])   ? formatDate($ev['event_date_to'],   'd M Y') : '';
        $rFrom = !empty($ev['reg_date_from']) ? formatDate($ev['reg_date_from'], 'd M Y') : '';
        $rTo   = !empty($ev['reg_date_to'])   ? formatDate($ev['reg_date_to'],   'd M Y') : '';
        // Can this viewer act on the athlete side? (The unit side is offered to
        // everyone — individuals get a modal to add a basic institution first.)
        $canAthlete = $eligAth && $eligIsAthlete;
      ?>
        <div class="col-md-6 col-xl-4">
          <div class="border rounded-3 p-3 h-100 d-flex flex-column gap-2 bg-white">
            <div class="d-flex align-items-center gap-2">
              <?php if (!empty($ev['logo']) || !empty($ev['organiser_logo'])): ?>
                <img src="<?= e($ev['logo'] ?: $ev['organiser_logo']) ?>" alt="" width="40" height="40"
                     class="rounded flex-shrink-0" style="object-fit:cover">
              <?php else: ?>
                <div class="rounded d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:40px;height:40px;background:#eef2f7;color:#94a3b8"><i class="bi bi-calendar-event"></i></div>
              <?php endif; ?>
              <div class="min-w-0">
                <div class="fw-semibold text-truncate" title="<?= e($ev['name']) ?>"><?= e($ev['name']) ?></div>
                <div class="small text-muted text-truncate"><?= e($ev['organiser_name'] ?? '') ?></div>
              </div>
            </div>

            <div class="small text-muted">
              <?php if (!empty($ev['location'])): ?>
                <div><i class="bi bi-geo-alt me-1"></i><?= e($ev['location']) ?></div>
              <?php endif; ?>
              <?php if ($from || $to): ?>
                <div><i class="bi bi-calendar3 me-1"></i><?= e($from) ?><?= ($from && $to && $from !== $to) ? ' – ' . e($to) : '' ?></div>
              <?php endif; ?>
              <?php if ($eligAth && ($rFrom || $rTo)): ?>
                <div><i class="bi bi-person-plus me-1"></i>Registration: <?= e($rFrom) ?><?= ($rFrom && $rTo && $rFrom !== $rTo) ? ' – ' . e($rTo) : '' ?></div>
              <?php endif; ?>
            </div>

            <div class="d-flex flex-wrap gap-1">
              <?php if ($eligAth): ?>
                <span class="badge bg-info-subtle text-info-emphasis" style="font-size:.65rem"><i class="bi bi-person-arms-up me-1"></i>Athlete registration</span>
              <?php endif; ?>
              <?php if ($eligUnit): ?>
                <span class="badge bg-warning-subtle text-warning-emphasis" style="font-size:.65rem"><i class="bi bi-building me-1"></i>Institution / Unit</span>
              <?php endif; ?>
            </div>

            <div class="mt-auto pt-1 d-flex flex-column gap-2">
              <?php /* Athlete-registration action */ ?>
              <?php if ($canAthlete): ?>
                <?php if (!$eligProfileOk): ?>
                  <a href="/athlete/profile" class="btn btn-sm btn-outline-warning w-100">
                    <i class="bi bi-person-exclamation me-1"></i>Complete profile to register
                  </a>
                <?php elseif ($myReg): ?>
                  <a href="/athlete/registrations/<?= e(hid_reg((int)$myReg['id'])) ?>" class="btn btn-sm btn-outline-secondary w-100">
                    <i class="bi bi-eye me-1"></i>View my registration
                  </a>
                <?php else: ?>
                  <a href="/athlete/events/<?= e($evHash) ?>/register" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-check-circle me-1"></i>Register as athlete
                  </a>
                <?php endif; ?>
              <?php endif; ?>

              <?php /* Institution / unit-join action — offered to every viewer.
                       An individual with no institution profile gets a modal to
                       add a basic one first, then the request is submitted. */ ?>
              <?php if ($eligUnit): ?>
                <?php if ($eligHasInstitution && ($hasUnit || $reqStat === 'approved')): ?>
                  <form method="POST" action="/institution/events/<?= e($evHash) ?>/open-as-unit" class="m-0">
                    <?= csrf() ?>
                    <button class="btn btn-sm btn-success w-100"><i class="bi bi-box-arrow-in-right me-1"></i>Login to Event</button>
                  </form>
                <?php elseif ($eligHasInstitution && $reqStat === 'pending'): ?>
                  <button type="button" class="btn btn-sm btn-outline-secondary w-100" disabled>
                    <i class="bi bi-hourglass-split me-1"></i>Join request submitted
                  </button>
                <?php elseif ($eligHasInstitution): ?>
                  <form method="POST" action="/account/events/<?= e($evHash) ?>/join-as-unit" class="m-0"
                        onsubmit="return confirm('Send a participation request to join this event as a unit?');">
                    <?= csrf() ?>
                    <button class="btn btn-sm btn-primary w-100"><i class="bi bi-send me-1"></i>Register to Join</button>
                  </form>
                <?php else: ?>
                  <button type="button" class="btn btn-sm btn-primary w-100"
                          onclick="openJoinAsUnitModal('<?= e($evHash) ?>', <?= htmlspecialchars(json_encode((string)$ev['name']), ENT_QUOTES) ?>)">
                    <i class="bi bi-send me-1"></i>Register to Join
                  </button>
                <?php endif; ?>
              <?php endif; ?>

              <?php /* Athlete-only event viewed by a non-athlete — informational. */ ?>
              <?php if (!$canAthlete && !$eligUnit): ?>
                <span class="small text-muted">
                  <i class="bi bi-info-circle me-1"></i>Open for individual athlete registration.
                </span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div><!-- /eligibleEvents -->

<?php if (!$eligHasInstitution): ?>
<!-- "Join as a unit" — collect a basic institution profile from an individual,
     then submit the participation request in one go. -->
<div class="modal fade" id="joinAsUnitModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="POST" id="joinAsUnitForm" action="">
      <?= csrf() ?>
      <div class="modal-header">
        <h6 class="modal-title fw-semibold"><i class="bi bi-building-add me-2"></i>Join as a Unit</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-3">
          To join <strong id="joinAsUnitEventName">this event</strong> as a unit, add your
          institution / club basic details. You can complete the full profile later
          in your organiser workspace.
        </p>
        <div class="mb-3">
          <label class="form-label small mb-1">Institution / Unit name <span class="text-danger">*</span></label>
          <input type="text" name="org_name" class="form-control form-control-sm"
                 placeholder="e.g. City Sports Club" maxlength="255" required>
        </div>
        <div class="mb-3">
          <label class="form-label small mb-1">Institution type</label>
          <select name="type_id" class="form-select form-select-sm">
            <option value="">— Select type —</option>
            <?php foreach ($eligTypes as $t): ?>
              <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label small mb-1">Address</label>
          <textarea name="org_address" class="form-control form-control-sm" rows="2"
                    placeholder="Institution / unit address" maxlength="500"></textarea>
        </div>
        <p class="small text-muted mb-0">
          <i class="bi bi-info-circle me-1"></i>Your name &amp; contact (SPOC) are taken from your profile.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Create &amp; send request</button>
      </div>
    </form>
  </div>
</div>
<script>
let _joinAsUnitModal = null;
function openJoinAsUnitModal(eventHash, eventName) {
  var form = document.getElementById('joinAsUnitForm');
  form.action = '/account/events/' + eventHash + '/join-as-unit';
  document.getElementById('joinAsUnitEventName').textContent = eventName || 'this event';
  if (!_joinAsUnitModal) _joinAsUnitModal = new bootstrap.Modal(document.getElementById('joinAsUnitModal'));
  _joinAsUnitModal.show();
}
</script>
<?php endif; ?>
