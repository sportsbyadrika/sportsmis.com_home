<?php
/**
 * Shared competitor-search form + results. Included by the Event-Staff search
 * page and the Institution event search page.
 *
 * Expects: $searchAction, $showQr (bool), $viewUrlFn (fn(int $regId): string),
 *          $by, $q, $unit_id, $units, $results, $searched, $notice,
 *          $has_emp, $has_des, $emp_label, $des_label.
 */
$valid = ['competitor', 'name', 'unit', 'mobile'];
if (!empty($has_emp)) $valid[] = 'employee';
if (!empty($has_des)) $valid[] = 'designation';
$by = in_array($by ?? '', $valid, true) ? $by : 'competitor';
$showQr = $showQr ?? true;
$statusBadgeMap = [
  'approved' => ['Approved', 'bg-success'],
  'pending'  => ['Pending',  'bg-warning text-dark'],
  'rejected' => ['Rejected', 'bg-danger'],
  'returned' => ['Returned', 'bg-info text-dark'],
];
?>
<form method="GET" action="<?= e($searchAction) ?>" class="sms-card p-3 mb-3" id="searchForm">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small mb-1">Search By</label>
      <select name="by" id="searchBy" class="form-select form-select-sm" onchange="toggleSearchField()">
        <option value="competitor" <?= $by==='competitor' ? 'selected' : '' ?>>Competitor No.</option>
        <option value="name"       <?= $by==='name'       ? 'selected' : '' ?>>Name of Athlete</option>
        <option value="unit"       <?= $by==='unit'       ? 'selected' : '' ?>>Unit / Club / Institution</option>
        <option value="mobile"     <?= $by==='mobile'     ? 'selected' : '' ?>>Mobile Number</option>
        <?php if (!empty($has_emp)): ?><option value="employee" <?= $by==='employee' ? 'selected' : '' ?>><?= e($emp_label) ?></option><?php endif; ?>
        <?php if (!empty($has_des)): ?><option value="designation" <?= $by==='designation' ? 'selected' : '' ?>><?= e($des_label) ?></option><?php endif; ?>
      </select>
    </div>

    <!-- Competitor No. -->
    <div class="col-md-6 search-field" data-field="competitor">
      <label class="form-label small mb-1">Competitor No.</label>
      <div class="input-group input-group-sm">
        <input type="text" name="q" id="qCompetitor" class="form-control"
               value="<?= $by==='competitor' ? e($q) : '' ?>" placeholder="e.g. 1024"
               <?= $by==='competitor' ? '' : 'disabled' ?>>
        <?php if ($showQr): ?>
          <button class="btn btn-outline-primary" type="button" onclick="openQrScanner()">
            <i class="bi bi-qr-code-scan me-1"></i>Scan QR
          </button>
        <?php endif; ?>
      </div>
      <small class="text-muted">Type the number<?= $showQr ? ' or scan the competitor card QR with your camera' : '' ?>.</small>
    </div>

    <!-- Name -->
    <div class="col-md-6 search-field" data-field="name">
      <label class="form-label small mb-1">Name of Athlete</label>
      <input type="text" name="q" id="qName" class="form-control form-control-sm"
             value="<?= $by==='name' ? e($q) : '' ?>" placeholder="Full or partial name"
             <?= $by==='name' ? '' : 'disabled' ?>>
    </div>

    <!-- Unit -->
    <div class="col-md-6 search-field" data-field="unit">
      <label class="form-label small mb-1">Unit / Club / Institution</label>
      <select name="unit_id" id="qUnit" class="form-select form-select-sm"
              <?= $by==='unit' ? '' : 'disabled' ?>>
        <option value="0">— Select Unit —</option>
        <?php foreach (($units ?? []) as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= (int)($unit_id ?? 0)===(int)$u['id'] ? 'selected' : '' ?>>
            <?= e($u['name']) ?><?= !empty($u['address']) ? ' — ' . e($u['address']) : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Mobile -->
    <div class="col-md-6 search-field" data-field="mobile">
      <label class="form-label small mb-1">Mobile Number</label>
      <input type="text" name="q" id="qMobile" class="form-control form-control-sm"
             value="<?= $by==='mobile' ? e($q) : '' ?>" placeholder="Full or partial mobile number"
             <?= $by==='mobile' ? '' : 'disabled' ?>>
    </div>

    <?php if (!empty($has_emp)): ?>
    <!-- Employee No / dynamic field -->
    <div class="col-md-6 search-field" data-field="employee">
      <label class="form-label small mb-1"><?= e($emp_label) ?></label>
      <input type="text" name="q" id="qEmployee" class="form-control form-control-sm"
             value="<?= $by==='employee' ? e($q) : '' ?>" placeholder="Full or partial <?= e($emp_label) ?>"
             <?= $by==='employee' ? '' : 'disabled' ?>>
    </div>
    <?php endif; ?>

    <?php if (!empty($has_des)): ?>
    <!-- Designation / dynamic field -->
    <div class="col-md-6 search-field" data-field="designation">
      <label class="form-label small mb-1"><?= e($des_label) ?></label>
      <input type="text" name="q" id="qDesignation" class="form-control form-control-sm"
             value="<?= $by==='designation' ? e($q) : '' ?>" placeholder="Full or partial <?= e($des_label) ?>"
             <?= $by==='designation' ? '' : 'disabled' ?>>
    </div>
    <?php endif; ?>

    <div class="col-md-3 d-flex gap-2">
      <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-search me-1"></i>Search</button>
      <a href="<?= e($searchAction) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
    </div>
  </div>
</form>

<?php if (!empty($notice)): ?>
  <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i><?= e($notice) ?></div>
<?php endif; ?>

<?php if (!empty($searched)): ?>
  <div class="sms-card p-3">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h6 class="fw-semibold mb-0"><i class="bi bi-list-ul me-2"></i>Results</h6>
      <span class="badge bg-secondary"><?= count($results) ?> found</span>
    </div>
    <?php if (empty($results)): ?>
      <p class="text-muted small mb-0 text-center py-3">No competitors match your search.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:56px">Photo</th>
            <th>Name</th>
            <th style="width:110px">Comp. No.</th>
            <?php if (!empty($has_emp)): ?><th style="width:130px"><?= e($emp_label) ?></th><?php endif; ?>
            <?php if (!empty($has_des)): ?><th style="width:130px"><?= e($des_label) ?></th><?php endif; ?>
            <th>Unit</th>
            <th style="width:100px">Status</th>
            <th style="width:90px" class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($results as $r):
            $unit = $r['unit_name'] ?: ($r['unit_name_other'] ? $r['unit_name_other'] . ' (Other)' : '—');
            $rs   = (string)($r['admin_review_status'] ?? '');
            $sb   = $statusBadgeMap[$rs] ?? ['Draft', 'bg-secondary'];
          ?>
            <tr>
              <td>
                <?php if (!empty($r['passport_photo'])): ?>
                  <img src="<?= e($r['passport_photo']) ?>" width="40" height="40"
                       class="rounded-circle" style="object-fit:cover">
                <?php else: ?>
                  <div class="sms-avatar sms-avatar-sm"><?= e(substr($r['athlete_name'] ?? '?',0,1)) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <div class="fw-medium"><?= e($r['athlete_name']) ?></div>
                <?php if (!empty($r['mobile'])): ?>
                  <small class="text-muted"><i class="bi bi-phone me-1"></i><?= e($r['mobile']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($r['competitor_number'])): ?>
                  <code class="fw-bold"><?= (string)(int)$r['competitor_number'] ?></code>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <?php if (!empty($has_emp)): ?><td class="small"><?= e($r['employee'] ?? '') ?: '<span class="text-muted">—</span>' ?></td><?php endif; ?>
              <?php if (!empty($has_des)): ?><td class="small"><?= e($r['designation'] ?? '') ?: '<span class="text-muted">—</span>' ?></td><?php endif; ?>
              <td class="small"><?= e($unit) ?></td>
              <td><span class="badge <?= e($sb[1]) ?>"><?= e($sb[0]) ?></span></td>
              <td class="text-end">
                <a href="<?= e($viewUrlFn((int)$r['registration_id'])) ?>"
                   class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>View</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="sms-empty-state">
    <i class="bi bi-search"></i>
    <h5>Search Competitors</h5>
    <p>Pick a field above and search by competitor number<?= $showQr ? ' (typed or scanned)' : '' ?>, athlete name, unit, mobile number<?= (!empty($has_emp) || !empty($has_des)) ? ', or a registration field' : '' ?>.</p>
  </div>
<?php endif; ?>

<script>
function toggleSearchField() {
  const by = document.getElementById('searchBy').value;
  document.querySelectorAll('.search-field').forEach(el => {
    const on = el.dataset.field === by;
    el.style.display = on ? '' : 'none';
    el.querySelectorAll('input, select').forEach(i => { i.disabled = !on; });
  });
}
toggleSearchField();
</script>
