<?php $pageTitle = 'Team Entry Approved List — ' . $event['name']; ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i>Team Entry Approved List</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <button class="btn btn-sm btn-outline-secondary ms-auto" type="button" onclick="window.print()">
    <i class="bi bi-printer me-1"></i>Print
  </button>
</div>

<p class="small text-muted mb-3">
  <i class="bi bi-info-circle me-1"></i>
  Approved team entries only, grouped by Unit / Club / Institution.
</p>

<div class="sms-card p-3">
  <?php if (empty($teams)): ?>
    <p class="text-muted small mb-0 text-center py-3">No approved team entries for this event yet.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:50px">#</th>
          <th>Unit — Code &amp; Address</th>
          <th>Event — Code &amp; Label</th>
          <th>Team Name</th>
          <th>Team Members</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($teams as $i => $t): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td>
              <div><code>#<?= (int)$t['unit_id'] ?></code> <span class="fw-medium"><?= e($t['unit_name'] ?? '—') ?></span></div>
              <?php if (!empty($t['unit_address'])): ?>
                <small class="text-muted"><?= e($t['unit_address']) ?></small>
              <?php endif; ?>
            </td>
            <td>
              <div><code><?= e($t['event_code'] ?? '—') ?></code></div>
              <small class="text-muted">
                <?= e($t['sport_name'] ?? '') ?>
                <?php if (!empty($t['sport_event_name'])): ?> · <?= e($t['sport_event_name']) ?><?php endif; ?>
              </small>
            </td>
            <td class="fw-medium"><?= e($t['team_name']) ?></td>
            <td>
              <?php if (empty($t['members'])): ?>
                <span class="text-muted">—</span>
              <?php else: ?>
                <ol class="mb-0 ps-3 small">
                  <?php foreach ($t['members'] as $m): ?>
                    <li>
                      <strong>Comp No <?= $m['competitor_number'] ? (int)$m['competitor_number'] : '—' ?></strong>
                      — <?= e($m['athlete_name'] ?? '') ?>
                    </li>
                  <?php endforeach; ?>
                </ol>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light">
        <tr>
          <th colspan="4" class="text-end">Total Approved Teams</th>
          <th><?= count($teams) ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>
</div>
