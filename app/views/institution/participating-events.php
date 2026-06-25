<?php
$pageTitle = "Events I'm Participating In";
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/dashboard" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-bag-check me-2"></i>Events I&rsquo;m Participating In</h5>
  <a href="/institution/public-events" class="btn btn-sm btn-outline-primary ms-auto">
    <i class="bi bi-binoculars me-1"></i>Browse More Events
  </a>
</div>

<?= flashBag() ?>

<p class="small text-muted mb-3">
  <i class="bi bi-info-circle me-1"></i>
  Events listed here have approved you as a participating unit. Click
  <strong>Open Unit Console</strong> to switch into the unit-side view for that event
  &mdash; you stay logged in as your institution, but the screens are the same ones
  a stand-alone unit user would see.
</p>

<div class="sms-card p-3">
  <?php if (empty($rows)): ?>
    <div class="text-center text-muted py-4">
      <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
      You don&rsquo;t have any approved participations yet.
      Head to <a href="/institution/public-events">Browse Public Events</a> to
      send a join request.
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Event</th>
            <th>Organiser</th>
            <th>Your Unit on this Event</th>
            <th>Dates</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $hash = hid_event((int)$r['id']);
          ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php if (!empty($r['logo'])): ?>
                    <img src="<?= e($r['logo']) ?>" alt="" width="36" height="36"
                         class="rounded" style="object-fit:contain;background:#fff;border:1px solid #e2e8f0">
                  <?php endif; ?>
                  <div>
                    <div class="fw-semibold"><?= e($r['name']) ?></div>
                    <div class="small text-muted">
                      <code><?= e($r['event_code'] ?? '') ?></code>
                      <?php if (!empty($r['location'])): ?>
                        &middot; <?= e($r['location']) ?>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>
              <td class="small"><?= e($r['organiser_name'] ?? '—') ?></td>
              <td>
                <div class="fw-medium"><?= e($r['unit_name']) ?></div>
                <?php if (!empty($r['unit_address'])): ?>
                  <small class="text-muted d-block" style="max-width:240px">
                    <?= e($r['unit_address']) ?>
                  </small>
                <?php endif; ?>
              </td>
              <td class="small">
                <?= !empty($r['event_date_from']) ? formatDate($r['event_date_from']) : '—' ?>
                <?php if (!empty($r['event_date_to']) && $r['event_date_to'] !== $r['event_date_from']): ?>
                  – <?= formatDate($r['event_date_to']) ?>
                <?php endif; ?>
              </td>
              <td class="text-end text-nowrap">
                <form method="POST" class="d-inline"
                      action="/institution/events/<?= e($hash) ?>/open-as-unit">
                  <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                  <button class="btn btn-sm btn-success">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Open Unit Console
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
