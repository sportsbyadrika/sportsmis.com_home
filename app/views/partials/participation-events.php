<?php
/**
 * "Events (for Participation)" panel — the events this account can join as a
 * unit/club, plus an event-SPOC contact popup. Shared by the organiser
 * dashboard and the (merged) athlete dashboard. Toggled by the
 * "Events I'm Participating In" card (.dash-toggle → #panel-participation).
 * Expects: $participation_events (array).
 */
$partEvents = $participation_events ?? [];
?>
<!-- Events (for Participation) — revealed by the "Events I'm Participating In" card -->
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
