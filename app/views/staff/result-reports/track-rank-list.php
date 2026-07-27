<?php
/**
 * Athletics / Skating — Event-wise Rank List (screen).
 * Filters: event category + age category. One table per sport-event with each
 * participant's round-wise Time / Rank / Qualified results.
 * Expects: $event, $categories, $age_categories, $category_id, $age_category_id, $groups.
 */
$pageTitle = 'Event-wise Rank List — ' . $event['name'];
$catId = (int)$category_id;
$ageId = (int)$age_category_id;
$printQs = 'category_id=' . $catId . '&age_category_id=' . $ageId;
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/result-reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-list-ol me-2"></i>Event-wise Rank List</h5>
  <span class="badge bg-info-subtle text-info-emphasis">Athletics / Skating</span>
  <?php if (($catId > 0 || $ageId > 0) && !empty($groups)): ?>
    <a class="btn btn-sm btn-outline-dark ms-auto" target="_blank" rel="noopener"
       href="/event-staff/result-reports/track-rank-list/print?<?= e($printQs) ?>">
      <i class="bi bi-printer me-1"></i>Print
    </a>
  <?php endif; ?>
</div>

<?= flashBag() ?>

<div class="sms-card p-3 mb-3">
  <form method="GET" action="/event-staff/result-reports/track-rank-list" class="row g-2 align-items-end">
    <div class="col-sm-5">
      <label class="form-label small mb-1">Event Category</label>
      <select name="category_id" class="form-select form-select-sm">
        <option value="">All event categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $catId === (int)$c['id'] ? 'selected' : '' ?>>
            <?= e($c['name']) ?><?= trim((string)($c['abbreviation'] ?? '')) !== '' ? ' (' . e($c['abbreviation']) . ')' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-5">
      <label class="form-label small mb-1">Age Category</label>
      <select name="age_category_id" class="form-select form-select-sm">
        <option value="">All age categories</option>
        <?php foreach ($age_categories as $ac): ?>
          <option value="<?= (int)$ac['id'] ?>" <?= $ageId === (int)$ac['id'] ? 'selected' : '' ?>>
            <?= e($ac['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-2 d-grid">
      <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Show</button>
    </div>
  </form>
</div>

<?php if ($catId <= 0 && $ageId <= 0): ?>
  <div class="sms-card p-4 text-muted small text-center">
    <i class="bi bi-info-circle me-1"></i>Choose an event category <strong>or</strong> an age category (or both) to view the rank list.
  </div>
<?php elseif (empty($groups)): ?>
  <div class="sms-card p-4 text-muted small text-center">
    <i class="bi bi-inbox me-1"></i>No sport-events with configured rounds found for this selection.
  </div>
<?php else: ?>
  <?php foreach ($groups as $g): ?>
    <div class="sms-card p-3 mb-3">
      <div class="d-flex align-items-center gap-2 flex-wrap border-bottom pb-2 mb-2">
        <strong><?= e($g['sport_event']) ?></strong>
        <?php if ($g['gender'] !== ''): ?><span class="badge bg-secondary-subtle text-secondary-emphasis"><?= e(ucfirst($g['gender'])) ?></span><?php endif; ?>
        <span class="small text-muted">
          <?= e($g['category']) ?><?php if ($g['age_name'] !== ''): ?> · <?= e($g['age_name']) ?><?php endif; ?>
          <?php if ($g['event_code'] !== ''): ?> · <code><?= e($g['event_code']) ?></code><?php endif; ?>
        </span>
        <span class="badge bg-primary-subtle text-primary-emphasis ms-auto"><?= count($g['athletes']) ?> participant<?= count($g['athletes']) === 1 ? '' : 's' ?></span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th rowspan="2" style="width:48px">Sl.</th>
              <th rowspan="2" style="width:80px">Chest</th>
              <th rowspan="2">Name of Athlete</th>
              <th rowspan="2">Unit / Institution</th>
              <?php foreach ($g['rounds'] as $rd): ?>
                <th colspan="3" class="text-center"><?= e($rd['name']) ?></th>
              <?php endforeach; ?>
            </tr>
            <tr>
              <?php foreach ($g['rounds'] as $rd): ?>
                <th class="text-center small">Time</th>
                <th class="text-center small">Rank</th>
                <th class="text-center small">Qual.</th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($g['athletes'])): ?>
              <tr><td colspan="<?= 4 + count($g['rounds']) * 3 ?>" class="text-center text-muted py-3">No results entered yet.</td></tr>
            <?php else: $sl = 0; foreach ($g['athletes'] as $a): $sl++; ?>
              <tr>
                <td class="text-center"><?= $sl ?></td>
                <td class="text-center"><?= $a['competitor_number'] > 0 ? (int)$a['competitor_number'] : '' ?></td>
                <td><?= e($a['athlete_name']) ?></td>
                <td class="small text-muted"><?= e($a['unit_name'] ?: '—') ?></td>
                <?php foreach ($g['rounds'] as $rd):
                  $res = $a['results'][$rd['id']] ?? null; ?>
                  <td class="text-center small"><?= $res ? e($res['time']) : '' ?></td>
                  <td class="text-center small fw-medium"><?= ($res && $res['rank'] > 0) ? (int)$res['rank'] : '' ?></td>
                  <td class="text-center"><?= ($res && $res['qualified']) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '' ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
