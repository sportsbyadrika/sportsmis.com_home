<?php
$pageTitle = ($athlete['name'] ?? 'Athlete') . ' — Results';
$unitLabel = fn($u) => $u === 'height' ? 'Height (m)' : ($u === 'length' ? 'Length (m)' : 'Time');
$base      = $base ?? '';
$ageGroups = $age_groups ?? [];
$ageText   = '';
if (!empty($ageGroups)) {
  $ageText = implode(', ', array_map(fn($a) => (string)($a['name'] ?? $a), $ageGroups));
}
?>
<div class="container">
  <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a href="<?= e($base) ?>/search" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>New Search</a>
    <h4 class="fw-bold mb-0"><i class="bi bi-person-badge me-2"></i>Athlete Result</h4>
  </div>

  <!-- Athlete summary -->
  <div class="pr-card p-3 mb-3">
    <div class="d-flex flex-wrap gap-3 align-items-center">
      <div>
        <div class="h5 fw-bold mb-1"><?= e($athlete['name']) ?></div>
        <div class="d-flex flex-wrap gap-2">
          <span class="badge bg-primary-subtle text-primary-emphasis">Chest / BIB: <?= e($athlete['chest']) ?></span>
          <?php if (($athlete['gender'] ?? '') !== ''): ?>
            <span class="badge bg-info-subtle text-info-emphasis">Gender: <?= e(ucfirst((string)$athlete['gender'])) ?></span>
          <?php endif; ?>
          <?php if ($ageText !== ''): ?>
            <span class="badge bg-secondary-subtle text-secondary-emphasis">Age Group: <?= e($ageText) ?></span>
          <?php endif; ?>
          <?php if (!empty($athlete['date_of_birth'])): ?>
            <span class="badge bg-light text-muted border">DOB: <?= e(formatDate($athlete['date_of_birth'], 'd M Y')) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?php
    $anyEvents = false;
    foreach ($blocks as $b) { if (!empty($b['track_results'])) { $anyEvents = true; break; } }
  ?>
  <?php if (!$anyEvents): ?>
    <div class="pr-card p-4 text-center text-muted">No Athletics / Skating events found for this athlete.</div>
  <?php else: ?>
    <?php foreach ($blocks as $bi => $b): if (empty($b['track_results'])) continue; ?>
      <div class="pr-card p-3 mb-3">
        <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
          <?php if (!empty($b['event_logo'])): ?><img src="<?= e($b['event_logo']) ?>" alt="" style="height:28px" class="rounded"><?php endif; ?>
          <strong><?= e($b['event_name']) ?></strong>
        </div>
        <p class="small text-muted mb-2"><i class="bi bi-hand-index me-1"></i>Click an event to see its round-wise result.</p>
        <div class="d-flex flex-column gap-2">
          <?php foreach ($b['track_results'] as $tr): $esid = (int)$tr['esid']; $mid = 'm' . $bi . 'e' . $esid;
            $finalRank = null;
            foreach ($tr['rounds'] as $rrr) { if (!empty($rrr['assign']) && $rrr['assign']['result_rank'] !== null) $finalRank = (int)$rrr['assign']['result_rank']; }
          ?>
            <button type="button" class="btn btn-outline-primary text-start d-flex align-items-center gap-2 flex-wrap"
                    data-bs-toggle="modal" data-bs-target="#<?= $mid ?>">
              <span class="fw-medium"><?= e($tr['event']) ?></span>
              <?php if ($tr['category'] !== '' || $tr['age'] !== '' || $tr['gender'] !== ''): ?>
                <span class="small text-muted"><?= e(trim(implode(' · ', array_filter([$tr['category'], $tr['age'], $tr['gender'] !== '' ? ucfirst($tr['gender']) : '']))) ) ?></span>
              <?php endif; ?>
              <span class="badge bg-<?= $tr['is_field'] ? 'success' : 'primary' ?>-subtle text-<?= $tr['is_field'] ? 'success' : 'primary' ?>-emphasis"><?= $tr['is_field'] ? 'Field' : 'Track' ?></span>
              <span class="ms-auto d-flex gap-1">
                <?php if ($finalRank !== null): ?>
                  <span class="badge bg-warning-subtle text-warning-emphasis">Rank <?= $finalRank ?></span>
                <?php elseif (!$tr['placed']): ?>
                  <span class="badge bg-light text-muted border">Not drawn</span>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary-emphasis">Awaiting result</span>
                <?php endif; ?>
                <i class="bi bi-eye"></i>
              </span>
            </button>

            <!-- Round-wise modal -->
            <div class="modal fade" id="<?= $mid ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                  <div class="modal-header">
                    <div>
                      <h6 class="modal-title mb-0"><?= e($tr['event']) ?></h6>
                      <div class="small text-muted"><?= e(trim(implode(' · ', array_filter([$tr['category'], $tr['age'], $tr['gender'] !== '' ? ucfirst($tr['gender']) : '']))) ) ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body" style="max-height:70vh;overflow:auto">
                    <?php if (empty($tr['rounds'])): ?>
                      <p class="text-muted small mb-0">No rounds configured for this event yet.</p>
                    <?php else: $isField = !empty($tr['is_field']); ?>
                      <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                          <thead class="table-light">
                            <tr>
                              <th>Round</th>
                              <th class="text-center" style="width:90px">Participants</th>
                              <th class="text-center" style="width:80px">Results</th>
                              <?php if ($isField): ?><th class="text-center" style="width:80px">Order No</th>
                              <?php else: ?><th class="text-center" style="width:70px">Heat</th><th class="text-center" style="width:70px">Track</th><?php endif; ?>
                              <th style="width:130px"><?= e($unitLabel($tr['result_unit'])) ?></th>
                              <th class="text-center" style="width:70px">Rank</th>
                              <th class="text-center" style="width:90px">Qualified</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($tr['rounds'] as $rrr): $a = $rrr['assign']; ?>
                              <tr>
                                <td class="fw-medium"><?= e($rrr['round_name']) ?></td>
                                <td class="text-center"><span class="badge bg-secondary-subtle text-secondary-emphasis"><?= (int)($rrr['participants'] ?? 0) ?></span></td>
                                <td class="text-center"><span class="badge bg-info-subtle text-info-emphasis"><?= (int)($rrr['results'] ?? 0) ?></span></td>
                                <?php if (!$a): ?>
                                  <td colspan="<?= $isField ? 4 : 5 ?>" class="text-muted small"><em>Not drawn in this round</em></td>
                                <?php else: ?>
                                  <?php if ($isField): ?>
                                    <td class="text-center"><?= (int)$a['track_no'] ?></td>
                                  <?php else: ?>
                                    <td class="text-center"><?= (int)$a['heat_no'] ?></td>
                                    <td class="text-center"><?= (int)$a['track_no'] ?></td>
                                  <?php endif; ?>
                                  <td class="font-monospace"><?= trim((string)($a['result_time'] ?? '')) !== '' ? e($a['result_time']) : '<span class="text-muted">—</span>' ?></td>
                                  <td class="text-center fw-bold"><?= $a['result_rank'] !== null ? (int)$a['result_rank'] : '<span class="text-muted">—</span>' ?></td>
                                  <td class="text-center"><?= !empty($a['is_qualified']) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<span class="text-muted">—</span>' ?></td>
                                <?php endif; ?>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Close</button>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
