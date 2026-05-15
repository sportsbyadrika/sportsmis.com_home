<?php $pageTitle = 'Unit Dashboard'; ?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold"><i class="bi bi-speedometer2 me-2"></i>Unit Dashboard</h5>
    <div class="text-muted small mt-1">
      Event: <strong><?= e($event['name']) ?></strong>
      · Code: <code><?= e($event['event_code'] ?? '') ?></code>
    </div>
  </div>

  <?php if (!empty($units) && count($units) > 1): ?>
    <form method="GET" action="/unit/dashboard" class="d-flex align-items-center gap-2">
      <label class="form-label mb-0 small text-muted">Unit:</label>
      <select name="unit_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:240px">
        <?php foreach ($units as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= $active_unit && (int)$active_unit['id'] === (int)$u['id'] ? 'selected' : '' ?>>
            <?= e($u['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  <?php endif; ?>
</div>

<?php if (empty($units)): ?>
  <div class="sms-empty-state">
    <i class="bi bi-building"></i>
    <h5>No Unit Assigned</h5>
    <p>The event administrator hasn't assigned any units to your account yet. Please contact the organiser.</p>
  </div>
<?php else: ?>

<!-- Unit details + stat tiles -->
<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="sms-card p-3 h-100">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-building me-2"></i>Unit Details</h6>
      <dl class="row small mb-0">
        <dt class="col-sm-4 text-muted">Code</dt>
        <dd class="col-sm-8"><code>#<?= (int)$active_unit['id'] ?></code></dd>
        <dt class="col-sm-4 text-muted">Name</dt>
        <dd class="col-sm-8 fw-medium"><?= e($active_unit['name']) ?></dd>
        <?php if (!empty($active_unit['address'])): ?>
          <dt class="col-sm-4 text-muted">Address</dt>
          <dd class="col-sm-8"><?= e($active_unit['address']) ?></dd>
        <?php endif; ?>
      </dl>
    </div>
  </div>
  <div class="col-lg-3 col-sm-6">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase" style="letter-spacing:.04em">Total Athletes Registered</div>
      <div class="display-6 fw-bold mt-1"><?= (int)$stats['total'] ?></div>
      <div class="small text-muted"><i class="bi bi-people me-1"></i>under this unit</div>
    </div>
  </div>
  <div class="col-lg-3 col-sm-6">
    <div class="sms-card p-3 h-100">
      <div class="text-muted small text-uppercase" style="letter-spacing:.04em">Approved Athletes</div>
      <div class="display-6 fw-bold text-success mt-1"><?= (int)$stats['approved'] ?></div>
      <div class="small text-muted"><i class="bi bi-check2-circle me-1"></i>approved by event admin</div>
    </div>
  </div>
</div>

<!-- Athletes list -->
<div class="sms-card p-3 mb-4">
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-list-check me-2"></i>Athletes</h6>
    <span class="badge bg-secondary"><?= count($registrations) ?> total</span>
  </div>

  <?php if (empty($registrations)): ?>
    <p class="text-muted small mb-0 text-center py-3">No athletes registered under this unit yet.</p>
  <?php else: ?>
    <!-- Desktop table (md+) -->
    <div class="table-responsive d-none d-md-block">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Athlete</th>
            <th>Events</th>
            <th>Application</th>
            <th>Payment</th>
            <th>Competitor No.</th>
            <th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($registrations as $r): ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php if (!empty($r['passport_photo'])): ?>
                    <img src="<?= e($r['passport_photo']) ?>" alt="" class="rounded-circle"
                         width="36" height="36" style="object-fit:cover">
                  <?php else: ?>
                    <div class="sms-avatar"><?= avatarInitials($r['athlete_name'] ?? '') ?></div>
                  <?php endif; ?>
                  <div>
                    <div class="fw-medium"><?= e($r['athlete_name']) ?></div>
                    <small class="text-muted">
                      <?= ucfirst($r['gender'] ?? '') ?>
                      <?php if (!empty($r['athlete_mobile'])): ?> · <?= e($r['athlete_mobile']) ?><?php endif; ?>
                    </small>
                  </div>
                </div>
              </td>
              <td class="small text-muted"><?= (int)$r['items_count'] ?></td>
              <td><?= appStatusBadge($r['admin_review_status'] ?? null, $r['submitted_at'] ?? null) ?></td>
              <td><?= statusBadge($r['payment_status'] ?? 'pending') ?></td>
              <td class="small">
                <?php if (!empty($r['competitor_number'])): ?>
                  <code class="fw-bold">#<?= str_pad((string)(int)$r['competitor_number'], 4, '0', STR_PAD_LEFT) ?></code>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <a href="/unit/athletes/<?= e(hid_reg((int)$r['id'])) ?>" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-eye me-1"></i>View
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobile card stack -->
    <div class="d-md-none">
      <?php foreach ($registrations as $r): ?>
        <div class="border rounded-3 p-3 mb-2">
          <div class="d-flex gap-3 align-items-start">
            <?php if (!empty($r['passport_photo'])): ?>
              <img src="<?= e($r['passport_photo']) ?>" alt="" class="rounded-circle flex-shrink-0"
                   width="42" height="42" style="object-fit:cover">
            <?php else: ?>
              <div class="sms-avatar flex-shrink-0"><?= avatarInitials($r['athlete_name'] ?? '') ?></div>
            <?php endif; ?>
            <div class="flex-grow-1 min-w-0">
              <div class="fw-medium text-break"><?= e($r['athlete_name']) ?></div>
              <div class="small text-muted">
                <?= ucfirst($r['gender'] ?? '') ?>
                <?php if (!empty($r['athlete_mobile'])): ?> · <?= e($r['athlete_mobile']) ?><?php endif; ?>
              </div>
              <div class="d-flex flex-wrap gap-1 mt-2">
                <?= appStatusBadge($r['admin_review_status'] ?? null, $r['submitted_at'] ?? null) ?>
                <?= statusBadge($r['payment_status'] ?? 'pending') ?>
                <?php if (!empty($r['competitor_number'])): ?>
                  <span class="badge bg-success-subtle text-success">
                    #<?= str_pad((string)(int)$r['competitor_number'], 4, '0', STR_PAD_LEFT) ?>
                  </span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="mt-3">
            <a href="/unit/athletes/<?= e(hid_reg((int)$r['id'])) ?>" class="btn btn-sm btn-outline-primary w-100">
              <i class="bi bi-eye me-1"></i>View Registration Details
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>
