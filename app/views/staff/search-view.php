<?php
$pageTitle = 'Athlete — ' . ($reg['athlete_name'] ?? '');
$photo = $reg['passport_photo'] ?? ($athlete['passport_photo'] ?? '');
$address = $athlete['address'] ?? ($athlete['communication_address'] ?? '');
$statusBadgeMap = [
  'approved' => ['Approved', 'bg-success'],
  'pending'  => ['Pending',  'bg-warning text-dark'],
  'rejected' => ['Rejected', 'bg-danger'],
  'returned' => ['Returned', 'bg-info text-dark'],
];
$rs = (string)($reg['admin_review_status'] ?? '');
$sb = $statusBadgeMap[$rs] ?? ['Draft', 'bg-secondary'];
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-person-vcard me-2"></i><?= e($reg['athlete_name'] ?? '') ?></h5>
  <span class="badge <?= e($sb[1]) ?> ms-1"><?= e($sb[0]) ?></span>
  <a href="/event-staff/search" class="btn btn-sm btn-outline-secondary ms-auto">
    <i class="bi bi-search me-1"></i>New Search
  </a>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="sms-card p-3 text-center">
      <?php if (!empty($photo)): ?>
        <img src="<?= e($photo) ?>" alt="" class="rounded-3 mb-2"
             style="width:160px;height:160px;object-fit:cover;border:3px solid #0b1f3a">
      <?php else: ?>
        <div class="rounded-3 mb-2 d-inline-flex align-items-center justify-content-center bg-light text-muted"
             style="width:160px;height:160px;font-size:54px;font-weight:700">
          <?= e(strtoupper(substr($reg['athlete_name'] ?? 'A', 0, 1))) ?>
        </div>
      <?php endif; ?>
      <div class="fw-bold fs-5"><?= e($reg['athlete_name'] ?? '') ?></div>
      <?php if (!empty($reg['competitor_number'])): ?>
        <div class="mt-1">
          <span class="text-muted small text-uppercase">Competitor No.</span>
          <div class="fw-bold fs-4 text-primary">
            <?= (string)(int)$reg['competitor_number'] ?>
          </div>
        </div>
      <?php else: ?>
        <div class="text-muted small mt-1">Competitor number not allocated</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="sms-card p-3 mb-3">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-info-circle me-2"></i>Athlete Details</h6>
      <div class="row g-3 small">
        <div class="col-md-6">
          <div class="text-muted">Name</div>
          <div class="fw-medium"><?= e($reg['athlete_name'] ?? '—') ?></div>
        </div>
        <div class="col-md-3">
          <div class="text-muted">Age</div>
          <div class="fw-medium"><?= $age !== null ? (int)$age . ' yrs' : '—' ?></div>
        </div>
        <div class="col-md-3">
          <div class="text-muted">Gender</div>
          <div class="fw-medium"><?= e(genderLabel((string)($reg['gender'] ?? ''), $event ?? null)) ?: '—' ?></div>
        </div>
        <div class="col-md-3">
          <div class="text-muted">Date of Birth</div>
          <div class="fw-medium">
            <?= !empty($reg['date_of_birth']) ? e(formatDate($reg['date_of_birth'], 'd M Y')) : '—' ?>
          </div>
        </div>
        <div class="col-md-9">
          <div class="text-muted">Age Category</div>
          <div class="fw-medium">
            <?= !empty($age_categories) ? e(implode(' / ', $age_categories)) : '—' ?>
          </div>
        </div>
        <div class="col-md-4">
          <div class="text-muted">Mobile Number</div>
          <div class="fw-medium">
            <?php if (!empty($reg['athlete_mobile'])): ?>
              <i class="bi bi-phone me-1"></i><?= e($reg['athlete_mobile']) ?>
            <?php else: ?>—<?php endif; ?>
          </div>
        </div>
        <div class="col-md-8">
          <div class="text-muted">Address</div>
          <div class="fw-medium"><?= e($address) ?: '—' ?></div>
        </div>
        <div class="col-md-12">
          <div class="text-muted">Unit / Club / Institution</div>
          <div class="fw-medium">
            <?= e($reg['unit_name'] ?? $reg['unit_name_other'] ?? '—') ?>
            <?php if (!empty($reg['unit_address'])): ?>
              <span class="text-muted">— <?= e($reg['unit_address']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="sms-card p-3">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-trophy me-2"></i>Registration Details — Events</h6>
      <?php if (empty($items)): ?>
        <p class="text-muted small mb-0">No events registered.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:50px">#</th>
              <th>Event Code</th>
              <th>Sport</th>
              <th>Event</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $i => $it): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><code><?= e($it['event_code'] ?? '—') ?></code></td>
                <td><?= e($it['sport_name'] ?? '') ?></td>
                <td><?= e($it['sport_event_name'] ?? $it['category'] ?? '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── Team Entries ───────────────────────────────────────────── -->
    <div class="sms-card p-3 mt-3">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-people me-2"></i>Team Entry Details</h6>
      <?php if (empty($team_entries)): ?>
        <p class="text-muted small mb-0">This athlete is not part of any team entry for this event.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:50px">#</th>
              <th>Event Code</th>
              <th>Event</th>
              <th>Team Name</th>
              <th>Members</th>
              <th style="width:110px">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($team_entries as $i => $te): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><code><?= e($te['event_code'] ?? '—') ?></code></td>
                <td><?= e($te['sport_event_name'] ?? $te['category_name'] ?? '—') ?></td>
                <td class="fw-medium"><?= e($te['team_name']) ?></td>
                <td class="small">
                  <?php $mems = $te['members'] ?? []; ?>
                  <?php if (empty($mems)): ?>
                    <span class="text-muted">—</span>
                  <?php else: ?>
                    <?php foreach ($mems as $m): ?>
                      <div>
                        <?php if (!empty($m['competitor_number'])): ?>
                          <code class="me-1">#<?= (string)(int)$m['competitor_number'] ?></code>
                        <?php endif; ?>
                        <?= e($m['athlete_name']) ?>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </td>
                <td>
                  <?php $tstatus = (string)($te['admin_review_status'] ?? 'pending');
                        $tbadge = $statusBadgeMap[$tstatus] ?? ['Draft', 'bg-secondary']; ?>
                  <span class="badge <?= e($tbadge[1]) ?>"><?= e($tbadge[0]) ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── Results ────────────────────────────────────────────────── -->
    <div class="sms-card p-3 mt-3">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-bullseye me-2"></i>Results</h6>
      <?php if (empty($results)): ?>
        <p class="text-muted small mb-0">No scoring data yet for this athlete.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:50px">#</th>
              <th>Event Code</th>
              <th>Event</th>
              <th>Relay</th>
              <th>Date / Time</th>
              <th>Series</th>
              <th class="text-end">Penalty</th>
              <th class="text-center">No. of 10x</th>
              <th class="text-end">Final Score</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($results as $i => $r): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><code><?= e($r['event_code']) ?: '—' ?></code></td>
                <td><?= e($r['sport_event_name']) ?: '—' ?></td>
                <td><?= $r['relay_number'] !== '' ? e($r['relay_number']) : '<span class="text-muted">—</span>' ?></td>
                <td class="small">
                  <?= $r['relay_date'] !== '' ? e(formatDate($r['relay_date'], 'd M Y')) : '<span class="text-muted">—</span>' ?>
                  <?php if ($r['match_time'] !== ''): ?>
                    <br><small class="text-muted"><?= e(substr($r['match_time'], 0, 5)) ?></small>
                  <?php endif; ?>
                </td>
                <td class="small font-monospace">
                  <?php if (empty($r['series'])): ?>
                    <span class="text-muted">—</span>
                  <?php else: ?>
                    <?= e(implode(' · ', array_map(
                          fn($s) => rtrim(rtrim(number_format((float)$s['sub_total'], 2, '.', ''), '0'), '.') ?: '0',
                          $r['series']
                        ))) ?>
                  <?php endif; ?>
                </td>
                <td class="text-end"><?= $r['penalty'] !== null && $r['penalty'] > 0 ? number_format($r['penalty'], 2) : '<span class="text-muted">—</span>' ?></td>
                <td class="text-center"><?= $r['tens_count'] !== null ? (int)$r['tens_count'] : '<span class="text-muted">—</span>' ?></td>
                <td class="text-end fw-bold"><?= $r['final_score'] !== null ? (int)round((float)$r['final_score']) : '<span class="text-muted">—</span>' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── Round-wise Results (Athletics / Skating) ─────────────────── -->
    <?php $trackResults = $track_results ?? []; if (!empty($trackResults)):
      $unitLabel = fn($u) => $u === 'height' ? 'Height (m)' : ($u === 'length' ? 'Length (m)' : 'Time');
    ?>
    <div class="sms-card p-3 mt-3">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-flag me-2"></i>Round-wise Results
        <span class="small text-muted">(Athletics / Skating)</span>
      </h6>
      <p class="small text-muted mb-2"><i class="bi bi-hand-index me-1"></i>Click an event to see its round-wise result.</p>
      <div class="d-flex flex-column gap-2">
        <?php foreach ($trackResults as $tr): $esid = (int)$tr['esid'];
          // Best (final-round) rank, if any, for the summary chip.
          $finalRank = null; $finalQual = null;
          foreach ($tr['rounds'] as $rrr) { if (!empty($rrr['assign'])) { $a = $rrr['assign'];
            if ($a['result_rank'] !== null) $finalRank = (int)$a['result_rank'];
            $finalQual = !empty($a['is_qualified']); } }
        ?>
          <button type="button" class="btn btn-outline-primary text-start d-flex align-items-center gap-2 flex-wrap"
                  data-bs-toggle="modal" data-bs-target="#trkRes<?= $esid ?>">
            <span class="fw-medium"><?= e($tr['event']) ?></span>
            <?php if ($tr['category'] !== '' || $tr['age'] !== '' || $tr['gender'] !== ''): ?>
              <span class="small text-muted">
                <?= e(trim(implode(' · ', array_filter([$tr['category'], $tr['age'], $tr['gender'] !== '' ? ucfirst($tr['gender']) : '']))) ) ?>
              </span>
            <?php endif; ?>
            <span class="badge bg-<?= $tr['is_field'] ? 'success' : 'primary' ?>-subtle text-<?= $tr['is_field'] ? 'success' : 'primary' ?>-emphasis"><?= $tr['is_field'] ? 'Field' : 'Track' ?></span>
            <span class="ms-auto d-flex gap-1">
              <?php if (!$tr['placed']): ?>
                <span class="badge bg-light text-muted border">Not drawn</span>
              <?php elseif ($finalRank !== null): ?>
                <span class="badge bg-warning-subtle text-warning-emphasis">Rank <?= $finalRank ?></span>
              <?php elseif ($tr['has_result']): ?>
                <span class="badge bg-info-subtle text-info-emphasis">Result recorded</span>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary-emphasis">Awaiting result</span>
              <?php endif; ?>
              <i class="bi bi-eye"></i>
            </span>
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Per-event round-wise result modals -->
    <?php foreach ($trackResults as $tr): $esid = (int)$tr['esid']; $isField = !empty($tr['is_field']); ?>
      <div class="modal fade" id="trkRes<?= $esid ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header">
              <div>
                <h6 class="modal-title mb-0"><i class="bi bi-flag me-2"></i><?= e($tr['event']) ?></h6>
                <div class="small text-muted"><?= e(trim(implode(' · ', array_filter([$tr['category'], $tr['age'], $tr['gender'] !== '' ? ucfirst($tr['gender']) : '']))) ) ?></div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height:70vh;overflow:auto">
              <?php if (empty($tr['rounds'])): ?>
                <p class="text-muted small mb-0">No rounds configured for this event yet.</p>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Round</th>
                        <?php if ($isField): ?><th class="text-center" style="width:80px">Order No</th>
                        <?php else: ?><th class="text-center" style="width:70px">Heat</th><th class="text-center" style="width:70px">Track</th><?php endif; ?>
                        <th style="width:130px"><?= e($unitLabel($tr['result_unit'])) ?></th>
                        <th class="text-center" style="width:70px">Rank</th>
                        <th class="text-center" style="width:90px">Qualified</th>
                        <th class="text-center" style="width:90px">Published</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($tr['rounds'] as $rrr): $a = $rrr['assign']; ?>
                        <tr>
                          <td class="fw-medium"><?= e($rrr['round_name']) ?></td>
                          <?php if (!$a): ?>
                            <td colspan="<?= $isField ? 5 : 6 ?>" class="text-muted small"><em>Not drawn in this round</em></td>
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
                            <td class="text-center"><?= !empty($a['is_published']) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<span class="text-muted">—</span>' ?></td>
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
    <?php endif; ?>
  </div>
</div>
