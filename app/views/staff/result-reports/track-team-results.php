<?php
/**
 * Athletics / Skating — Team-entry results entry. One form per team event with
 * Time / Position / Qualified inputs for every approved team.
 * Expects: $event, $groups (each: esid, sport_event_name, category, age, teams[]).
 */
$pageTitle = 'Team Results — ' . $event['name'];
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf_token'];
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/result-reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i>Team Results</h5>
  <span class="badge bg-info-subtle text-info-emphasis">Athletics / Skating</span>
</div>

<?= flashBag() ?>

<?php if (empty($groups)): ?>
  <div class="sms-card p-4 text-muted small text-center">
    <i class="bi bi-inbox me-1"></i>No approved team entries found for this event.
  </div>
<?php else: foreach ($groups as $g): ?>
  <div class="sms-card p-3 mb-3">
    <form method="POST" action="/event-staff/result-reports/team-results/save">
      <input type="hidden" name="_token" value="<?= e($csrf) ?>">
      <div class="d-flex align-items-center gap-2 flex-wrap border-bottom pb-2 mb-2">
        <strong><?= e(trim((string)($g['sport_event_name'] ?? '')) ?: (string)($g['event_code'] ?? '')) ?></strong>
        <?php if (($g['gender'] ?? '') !== ''): ?><span class="badge bg-secondary-subtle text-secondary-emphasis"><?= e(ucfirst($g['gender'])) ?></span><?php endif; ?>
        <span class="small text-muted">
          <?= e($g['category_name'] ?? '') ?><?php if (($g['age_name'] ?? '') !== ''): ?> · <?= e($g['age_name']) ?><?php endif; ?>
        </span>
        <button type="submit" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-save me-1"></i>Save</button>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:48px">Sl.</th>
              <th>Team</th>
              <th>Unit / Institution</th>
              <th>Members</th>
              <th style="width:140px">Time</th>
              <th style="width:100px">Position</th>
              <th style="width:90px" class="text-center">Qualified</th>
            </tr>
          </thead>
          <tbody>
            <?php $sl = 0; foreach ($g['teams'] as $t): $sl++; $tid = (int)$t['id']; ?>
              <tr>
                <td class="text-center"><?= $sl ?></td>
                <td class="fw-medium"><?= e($t['team_name']) ?></td>
                <td class="small text-muted"><?= e($t['unit_name'] ?? '—') ?></td>
                <td class="small text-muted"><?= e($t['members'] ?? '') ?></td>
                <td><input type="text" class="form-control form-control-sm" name="time[<?= $tid ?>]"
                           value="<?= e($t['result_time'] ?? '') ?>" placeholder="mm:ss.SSS"></td>
                <td><input type="number" min="1" step="1" class="form-control form-control-sm" name="rank[<?= $tid ?>]"
                           value="<?= (int)($t['result_rank'] ?? 0) > 0 ? (int)$t['result_rank'] : '' ?>"></td>
                <td class="text-center"><input type="checkbox" class="form-check-input" name="qualified[<?= $tid ?>]"
                           value="1" <?= !empty($t['is_qualified']) ? 'checked' : '' ?>></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </form>
  </div>
<?php endforeach; endif; ?>
