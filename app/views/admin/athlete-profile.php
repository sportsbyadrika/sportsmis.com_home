<?php
$pageTitle = 'Athlete — ' . ($athlete['name'] ?? '');
$age = !empty($athlete['date_of_birth']) ? ageFromDob($athlete['date_of_birth']) : null;
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/admin/athletes" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Athletes
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2"></i><?= e($athlete['name']) ?></h5>
  <?php if (!empty($athlete['user_status'] ?? null)): ?>
    <?= statusBadge($athlete['user_status']) ?>
  <?php endif; ?>
</div>

<div class="row g-4">
  <!-- Profile -->
  <div class="col-lg-4">
    <div class="sms-card p-4 mb-4 text-center">
      <?php if (!empty($athlete['passport_photo'])): ?>
        <img src="<?= e($athlete['passport_photo']) ?>" class="rounded-circle mb-2"
             width="120" height="120" style="object-fit:cover;border:3px solid #e2e8f0">
      <?php else: ?>
        <div class="sms-avatar sms-avatar-xl mx-auto mb-2"><?= avatarInitials($athlete['name']) ?></div>
      <?php endif; ?>
      <div class="fw-bold fs-5"><?= e($athlete['name']) ?></div>
      <div class="text-muted small">
        <?= ucfirst($athlete['gender'] ?? '') ?>
        <?php if ($age !== null): ?> · <?= (int)$age ?> yrs<?php endif; ?>
      </div>
      <div class="mt-2">
        <?= $athlete['profile_completed']
              ? '<span class="badge bg-success">Profile Complete</span>'
              : '<span class="badge bg-warning text-dark">Profile Incomplete</span>' ?>
      </div>
    </div>

    <div class="sms-card p-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-info-circle me-2"></i>Basic Details</h6>
      <dl class="row small mb-0">
        <dt class="col-5 text-muted">Email</dt><dd class="col-7"><?= e($athlete['email'] ?? '—') ?></dd>
        <dt class="col-5 text-muted">Mobile</dt><dd class="col-7"><?= e($athlete['mobile'] ?? '—') ?></dd>
        <dt class="col-5 text-muted">WhatsApp</dt><dd class="col-7"><?= e($athlete['whatsapp_number'] ?? '—') ?></dd>
        <dt class="col-5 text-muted">Date of Birth</dt>
        <dd class="col-7"><?= !empty($athlete['date_of_birth']) ? formatDate($athlete['date_of_birth']) : '—' ?></dd>
        <dt class="col-5 text-muted">PwD Status</dt>
        <dd class="col-7"><?= e(ucfirst($athlete['pwd_status'] ?? 'no')) ?></dd>
        <dt class="col-5 text-muted">Guardian</dt><dd class="col-7"><?= e($athlete['guardian_name'] ?? '—') ?></dd>
        <dt class="col-5 text-muted">Weight / Height</dt>
        <dd class="col-7"><?= e($athlete['weight'] ?? '—') ?> kg / <?= e($athlete['height'] ?? '—') ?> cm</dd>
        <dt class="col-5 text-muted">Nationality</dt><dd class="col-7"><?= e($athlete['nationality'] ?? '—') ?></dd>
        <dt class="col-5 text-muted">Country</dt><dd class="col-7"><?= e($athlete['country_name'] ?? '—') ?></dd>
        <dt class="col-5 text-muted">State</dt><dd class="col-7"><?= e($athlete['state_name'] ?? '—') ?></dd>
        <dt class="col-5 text-muted">District</dt><dd class="col-7"><?= e($athlete['district_name'] ?? '—') ?></dd>
        <dt class="col-5 text-muted">Address</dt><dd class="col-7"><?= e($athlete['address'] ?? '—') ?></dd>
        <dt class="col-5 text-muted">Comm. Address</dt><dd class="col-7"><?= e($athlete['communication_address'] ?? '—') ?></dd>
      </dl>
    </div>
  </div>

  <div class="col-lg-8">
    <!-- ID proofs -->
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-card-text me-2"></i>ID &amp; DOB Proof</h6>
      <dl class="row small mb-0">
        <dt class="col-sm-3 text-muted">Aadhaar No.</dt>
        <dd class="col-sm-9"><?= e($athlete['id_proof_number'] ?? '—') ?>
          <?php if (!empty($athlete['id_proof_file'])): ?>
            <a href="<?= e($athlete['id_proof_file']) ?>" target="_blank" class="ms-1"><i class="bi bi-eye"></i> View</a>
          <?php endif; ?>
        </dd>
        <dt class="col-sm-3 text-muted">DOB Proof</dt>
        <dd class="col-sm-9"><?= e($athlete['dob_proof_number'] ?? '—') ?>
          <?php if (!empty($athlete['dob_proof_file'])): ?>
            <a href="<?= e($athlete['dob_proof_file']) ?>" target="_blank" class="ms-1"><i class="bi bi-eye"></i> View</a>
          <?php endif; ?>
        </dd>
      </dl>
    </div>

    <!-- Sports preferences -->
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-trophy me-2"></i>Sports</h6>
      <?php if (empty($athlete_sports)): ?>
        <p class="text-muted small mb-0">No sports selected.</p>
      <?php else: ?>
        <?php foreach ($athlete_sports as $sp): ?>
          <span class="badge bg-secondary-subtle text-secondary me-1 mb-1"><?= e($sp['sport_name']) ?></span>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Event registrations -->
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3 d-flex justify-content-between flex-wrap gap-2">
        <span><i class="bi bi-calendar-check me-2"></i>Event Registrations</span>
        <span class="badge bg-secondary"><?= count($registrations) ?></span>
      </h6>
      <?php if (empty($registrations)): ?>
        <p class="text-muted small mb-0">This athlete has not registered for any events.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Event</th>
                <th>Unit</th>
                <th>Sports / Events</th>
                <th class="text-end">Total Fee</th>
                <th>Application</th>
                <th>Payment</th>
                <th>Competitor No.</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($registrations as $r): ?>
                <tr>
                  <td>
                    <div class="fw-medium"><?= e($r['event_name']) ?></div>
                    <small class="text-muted"><?= e($r['institution_name'] ?? '') ?></small>
                  </td>
                  <td class="small"><?= e($r['unit_name'] ?? '—') ?></td>
                  <td class="small">
                    <?= e($r['sport_name'] ?? '') ?>
                    <?php if (!empty($r['event_label'])): ?>
                      <div class="text-muted"><?= e($r['event_label']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <?php $tot = (float)($r['total_amount'] ?? 0); ?>
                    <?= $tot > 0 ? '₹' . number_format($tot, 2) : '—' ?>
                  </td>
                  <td><?= appStatusBadge($r['admin_review_status'] ?? null, $r['submitted_at'] ?? null) ?></td>
                  <td><?= statusBadge($r['payment_status'] ?? 'pending') ?></td>
                  <td class="small">
                    <?php if (!empty($r['competitor_number'])): ?>
                      <code>#<?= str_pad((string)(int)$r['competitor_number'], 4, '0', STR_PAD_LEFT) ?></code>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
