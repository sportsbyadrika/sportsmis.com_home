<?php $pageTitle = 'Find Events'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h5 class="mb-0 fw-bold"><i class="bi bi-search me-2"></i>Active Events</h5>
  <div class="input-group" style="max-width:280px">
    <input type="text" id="eventSearch" class="form-control" placeholder="Search events...">
    <span class="input-group-text"><i class="bi bi-search"></i></span>
  </div>
</div>

<?php if (empty($events)): ?>
<div class="sms-empty-state">
  <i class="bi bi-calendar-x"></i>
  <h5>No Active Events</h5>
  <p>There are no events open for registration right now. Check back later.</p>
</div>
<?php else: ?>
<div class="row g-3" id="eventsGrid">
  <?php foreach ($events as $event): ?>
  <div class="col-md-6 col-xl-4 event-item" data-name="<?= strtolower(e($event['name'])) ?> <?= strtolower(e($event['location'])) ?>">
    <div class="sms-event-card h-100">
      <div class="sms-event-status-bar status-approved"></div>
      <div class="sms-event-card-body d-flex flex-column">
        <div class="d-flex align-items-start gap-3 mb-3">
          <?php if ($event['logo']): ?>
            <img src="<?= e($event['logo']) ?>" alt="Logo" width="56" height="56"
                 class="rounded-3 flex-shrink-0" style="object-fit:cover">
          <?php else: ?>
            <div class="sms-event-icon flex-shrink-0"><i class="bi bi-trophy"></i></div>
          <?php endif; ?>
          <div>
            <h6 class="fw-bold mb-1"><?= e($event['name']) ?></h6>
            <small class="text-muted"><i class="bi bi-building me-1"></i><?= e($event['institution_name']) ?></small>
          </div>
        </div>

        <div class="mb-3">
          <div class="d-flex align-items-center gap-2 text-muted small mb-1">
            <i class="bi bi-geo-alt text-primary"></i><?= e($event['location']) ?>
          </div>
          <div class="d-flex align-items-center gap-2 text-muted small mb-1">
            <i class="bi bi-calendar3 text-success"></i>
            Event: <?= formatDate($event['event_date_from']) ?> – <?= formatDate($event['event_date_to']) ?>
          </div>
          <div class="d-flex align-items-center gap-2 small mb-1">
            <i class="bi bi-person-plus text-warning"></i>
            <span class="<?= strtotime($event['reg_date_to']) >= time() ? 'text-success fw-medium' : 'text-danger' ?>">
              Register by <?= formatDate($event['reg_date_to']) ?>
            </span>
          </div>
        </div>

        <div class="mt-auto">
          <a href="/athlete/events/<?= $event['id'] ?>" class="btn btn-primary w-100">
            <i class="bi bi-eye me-2"></i>View & Register
          </a>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
document.getElementById('eventSearch')?.addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.event-item').forEach(el => {
    el.style.display = el.dataset.name.includes(q) ? '' : 'none';
  });
});
</script>
