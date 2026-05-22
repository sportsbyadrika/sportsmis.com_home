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
            <?= str_pad((string)(int)$reg['competitor_number'], 4, '0', STR_PAD_LEFT) ?>
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
          <div class="fw-medium"><?= e(ucfirst((string)($reg['gender'] ?? ''))) ?: '—' ?></div>
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
  </div>
</div>
