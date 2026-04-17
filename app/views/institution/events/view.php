<?php $pageTitle = e($event['name']); ?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="/institution/events" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold">Event Details</h5>
  <?= statusBadge($event['status']) ?>
  <?php if ($event['rejection_reason']): ?>
    <span class="ms-2 text-danger small"><i class="bi bi-exclamation-circle me-1"></i><?= e($event['rejection_reason']) ?></span>
  <?php endif; ?>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="sms-card p-4">
      <div class="d-flex align-items-start gap-4 mb-4">
        <?php if ($event['logo']): ?>
          <img src="<?= e($event['logo']) ?>" alt="Logo" width="72" height="72" class="rounded-3 flex-shrink-0" style="object-fit:cover">
        <?php endif; ?>
        <div>
          <h4 class="fw-bold mb-1"><?= e($event['name']) ?></h4>
          <div class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($event['location']) ?></div>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-sm-6"><small class="text-muted">Event Dates</small>
          <div class="fw-medium"><?= formatDate($event['event_date_from']) ?> – <?= formatDate($event['event_date_to']) ?></div></div>
        <div class="col-sm-6"><small class="text-muted">Registration</small>
          <div class="fw-medium"><?= formatDate($event['reg_date_from']) ?> – <?= formatDate($event['reg_date_to']) ?></div></div>
        <div class="col-sm-6"><small class="text-muted">Payment Modes</small>
          <div class="fw-medium"><?= implode(', ', array_map('ucfirst', $event['payment_modes'])) ?></div></div>
        <div class="col-sm-6"><small class="text-muted">Contact</small>
          <div class="fw-medium"><?= e($event['contact_name']) ?> &nbsp;|&nbsp; <?= e($event['contact_mobile']) ?></div></div>
      </div>

      <?php if ($event['sports']): ?>
      <div class="mt-4">
        <h6 class="fw-semibold mb-2">Sports</h6>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($event['sports'] as $s): ?>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
              <?= e($s['sport_name']) ?>
              <?= $s['category'] ? ' – ' . e($s['category']) : '' ?>
              <?= $s['entry_fee'] > 0 ? ' (₹' . number_format($s['entry_fee'], 2) . ')' : ' (Free)' ?>
            </span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-4">
    <?php if (!in_array($event['status'], ['approved', 'completed', 'cancelled'])): ?>
    <div class="sms-card p-4">
      <h6 class="fw-semibold mb-3">Actions</h6>
      <div class="d-grid gap-2">
        <a href="/institution/events/<?= $event['id'] ?>/edit" class="btn btn-outline-primary">
          <i class="bi bi-pencil me-2"></i>Edit Event
        </a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
