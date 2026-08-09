<?php
/**
 * "Unit / Club Access" cards for a main-account (athlete/institution)
 * dashboard. Shown when the signed-in account's login email matches one or
 * more ACTIVE unit-user accounts. Each card opens the Unit console for that
 * event directly — no separate event-code / email / password login.
 * Expects: $unit_access_cards (from UnitUser::activeForEmail()).
 */
$cards = $unit_access_cards ?? [];
if (!empty($cards)):
?>
<div class="sms-card p-3 mb-4" id="unitAccess">
  <div class="d-flex align-items-center border-bottom pb-2 mb-3">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-buildings me-2"></i>Unit / Club Access</h6>
    <span class="badge bg-primary-subtle text-primary-emphasis ms-2"><?= count($cards) ?></span>
  </div>
  <p class="small text-muted mb-3">
    You&rsquo;ve been added as a unit / club operator using this account&rsquo;s email. Open the
    console directly here &mdash; no separate event-code login needed.
  </p>
  <div class="row g-3">
    <?php foreach ($cards as $c):
      $from  = !empty($c['event_date_from']) ? formatDate($c['event_date_from'], 'd M Y') : '';
      $to    = !empty($c['event_date_to'])   ? formatDate($c['event_date_to'],   'd M Y') : '';
      $units = trim((string)($c['units'] ?? ''));
    ?>
      <div class="col-md-6 col-xl-4">
        <div class="border rounded-3 p-3 h-100 d-flex flex-column gap-2">
          <div class="d-flex align-items-center gap-2">
            <?php if (!empty($c['event_logo'])): ?>
              <img src="<?= e($c['event_logo']) ?>" alt="" width="40" height="40"
                   class="rounded" style="object-fit:cover;flex-shrink:0">
            <?php else: ?>
              <div class="rounded d-flex align-items-center justify-content-center flex-shrink-0"
                   style="width:40px;height:40px;background:#eef2f7;color:#94a3b8"><i class="bi bi-calendar-event"></i></div>
            <?php endif; ?>
            <div class="min-w-0">
              <div class="fw-semibold text-truncate" title="<?= e($c['event_name']) ?>"><?= e($c['event_name']) ?></div>
              <?php if (!empty($c['location'])): ?>
                <div class="small text-muted text-truncate"><i class="bi bi-geo-alt me-1"></i><?= e($c['location']) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($from || $to): ?>
            <div class="small text-muted"><i class="bi bi-calendar3 me-1"></i><?= e($from) ?><?= ($from && $to && $from !== $to) ? ' &ndash; ' . e($to) : '' ?></div>
          <?php endif; ?>
          <?php if ($units !== ''): ?>
            <div class="small text-muted"><i class="bi bi-people me-1"></i><span title="<?= e($units) ?>"><?= e($units) ?></span></div>
          <?php else: ?>
            <div class="small text-muted fst-italic">No units assigned yet</div>
          <?php endif; ?>
          <div class="mt-auto pt-1">
            <form method="POST" action="/unit/enter" class="m-0">
              <?= csrf() ?>
              <input type="hidden" name="unit_user_id" value="<?= (int)$c['id'] ?>">
              <button class="btn btn-sm btn-primary w-100">
                <i class="bi bi-box-arrow-in-right me-1"></i>Open Unit Console
              </button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
