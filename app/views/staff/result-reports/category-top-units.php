<?php $pageTitle = 'Category — Top 5 Units — ' . $event['name']; ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/result-reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-buildings me-2"></i>Category — Top 5 Units / Clubs</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="window.print()">
    <i class="bi bi-printer me-1"></i>Print
  </button>
</div>

<p class="small text-muted mb-3">
  <i class="bi bi-info-circle me-1"></i>
  Points use the event's configured Gold / Silver / Bronze values
  &mdash; Indiv <strong><?= (int)$points['indiv'][1] ?>/<?= (int)$points['indiv'][2] ?>/<?= (int)$points['indiv'][3] ?></strong>,
  Team <strong><?= (int)$points['team'][1] ?>/<?= (int)$points['team'][2] ?>/<?= (int)$points['team'][3] ?></strong>.
  Ranked by total points; ties broken by total medals, then alphabetic unit name.
</p>

<?php if (empty($per_category)): ?>
  <div class="sms-card p-4 text-center text-muted">
    No medal-eligible scores recorded yet for this event.
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($per_category as $cat): ?>
      <div class="col-lg-6">
        <div class="sms-card p-3 h-100">
          <h6 class="fw-semibold border-bottom pb-2 mb-2">
            <i class="bi bi-collection me-2"></i><?= e($cat['category_name']) ?>
            <?php if (!empty($cat['category_abbr'])): ?>
              <span class="text-muted small">— <?= e($cat['category_abbr']) ?></span>
            <?php endif; ?>
            <span class="badge bg-secondary-subtle text-secondary-emphasis ms-2">
              <?= count($cat['units']) ?> unit<?= count($cat['units']) === 1 ? '' : 's' ?>
            </span>
          </h6>
          <?php if (empty($cat['units'])): ?>
            <p class="small text-muted mb-0">No medals awarded yet in this category.</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light text-center">
                <tr>
                  <th style="width:48px">Rank</th>
                  <th class="text-start">Unit / Club</th>
                  <th title="Individual Gold / Silver / Bronze" style="width:90px">Indiv (G/S/B)</th>
                  <th title="Team Gold / Silver / Bronze" style="width:90px">Team (G/S/B)</th>
                  <th class="text-end" style="width:80px">Points</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($cat['units'] as $u):
                  $rankBadge = match ((int)$u['rank']) {
                    1 => 'bg-warning-subtle text-warning-emphasis',
                    2 => 'bg-light text-secondary border',
                    3 => 'bg-warning-subtle text-warning-emphasis',
                    default => 'bg-secondary-subtle text-secondary',
                  };
                ?>
                <tr>
                  <td class="text-center">
                    <span class="badge <?= $rankBadge ?> fs-6"><?= (int)$u['rank'] ?></span>
                  </td>
                  <td>
                    <div class="fw-medium"><?= e($u['name']) ?: '<span class="text-muted">—</span>' ?></div>
                    <?php if (!empty($u['address'])): ?>
                      <small class="text-muted"><?= e($u['address']) ?></small>
                    <?php endif; ?>
                  </td>
                  <td class="text-center small font-monospace">
                    <?= (int)$u['indiv_g'] ?> / <?= (int)$u['indiv_s'] ?> / <?= (int)$u['indiv_b'] ?>
                  </td>
                  <td class="text-center small font-monospace">
                    <?= (int)$u['team_g'] ?> / <?= (int)$u['team_s'] ?> / <?= (int)$u['team_b'] ?>
                  </td>
                  <td class="text-end fw-bold"><?= (int)$u['points'] ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<style>
  @media print {
    .btn, .ms-auto button { display: none !important; }
    .sms-card { border: 1px solid #ddd !important; box-shadow: none !important; }
    body { background: #fff !important; }
  }
</style>
