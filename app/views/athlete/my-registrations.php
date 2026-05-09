<?php $pageTitle = 'My Registrations'; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>My Event Registrations</h5>
  <a href="/athlete/dashboard" class="btn btn-outline-primary">
    <i class="bi bi-search me-2"></i>Find More Events
  </a>
</div>

<?php if (empty($registrations)): ?>
<div class="sms-empty-state">
  <i class="bi bi-calendar-plus"></i>
  <h5>No Registrations Yet</h5>
  <p>You haven't registered for any events. Browse active events from the dashboard to get started.</p>
  <a href="/athlete/dashboard" class="btn btn-primary">Go to Dashboard</a>
</div>
<?php else: ?>
<!-- Desktop: table layout (md+) -->
<div class="sms-card d-none d-md-block">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Event</th>
          <th>Unit</th>
          <th>Sports / Events</th>
          <th class="text-end">Total Fee</th>
          <th>Application</th>
          <th>Payment</th>
          <th>Submitted</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($registrations as $reg): ?>
        <tr>
          <td>
            <div class="fw-medium"><?= e($reg['event_name']) ?></div>
            <small class="text-muted">
              <i class="bi bi-building me-1"></i><?= e($reg['institution_name']) ?><br>
              <i class="bi bi-geo-alt me-1"></i><?= e($reg['location']) ?><br>
              <i class="bi bi-calendar3 me-1"></i><?= formatDate($reg['event_date_from']) ?> – <?= formatDate($reg['event_date_to']) ?>
            </small>
          </td>
          <td class="text-muted small">
            <?php if (!empty($reg['unit_name'])): ?>
              <i class="bi bi-people me-1"></i><?= e($reg['unit_name']) ?>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
          <td class="small">
            <?php if (!empty($reg['sport_name'])): ?>
              <div><?= e($reg['sport_name']) ?></div>
            <?php endif; ?>
            <?php if (!empty($reg['event_label'])): ?>
              <small class="text-muted"><?= e($reg['event_label']) ?></small>
            <?php endif; ?>
            <?php if ((int)($reg['items_count'] ?? 0) > 0): ?>
              <span class="badge bg-secondary-subtle text-secondary mt-1"><?= (int)$reg['items_count'] ?> event<?= (int)$reg['items_count'] === 1 ? '' : 's' ?></span>
            <?php endif; ?>
          </td>
          <td class="text-end fw-medium">
            <?php $tot = (float)($reg['total_amount'] ?? 0); ?>
            <?= $tot > 0 ? '₹' . number_format($tot, 2) : '<span class="text-muted">—</span>' ?>
          </td>
          <td><?= appStatusBadge($reg['admin_review_status'] ?? null, $reg['submitted_at'] ?? null) ?></td>
          <td class="text-muted small">
            <?php if (!empty($reg['payment_mode'])): ?>
              <i class="bi bi-<?= $reg['payment_mode'] === 'manual' ? 'bank' : 'credit-card' ?> me-1"></i>
              <?= ucfirst($reg['payment_mode']) ?><br>
            <?php endif; ?>
            <?= statusBadge($reg['payment_status'] ?? 'pending') ?>
          </td>
          <td class="text-muted small">
            <?php if (!empty($reg['submitted_at'])): ?>
              <?= formatDate($reg['submitted_at'], 'd M Y H:i') ?>
            <?php else: ?>
              <em class="text-muted">not submitted</em><br>
              <small><?= formatDate($reg['registered_at'], 'd M Y') ?></small>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <?php
              $editable = \Models\EventRegistration::isEditable($reg);
              $isApproved = ($reg['admin_review_status'] ?? '') === 'approved' && !empty($reg['competitor_number']);
            ?>
            <div class="btn-group btn-group-sm" role="group">
              <a href="/athlete/registrations/<?= e(hid_reg((int)$reg['id'])) ?>"
                 class="btn btn-outline-secondary" title="View">
                <i class="bi bi-eye"></i><span class="d-none d-lg-inline ms-1">View</span>
              </a>
              <?php if ($isApproved): ?>
                <a href="/athlete/registrations/<?= e(hid_reg((int)$reg['id'])) ?>/card" target="_blank"
                   class="btn btn-success" title="Download Competitor Card">
                  <i class="bi bi-card-heading"></i><span class="d-none d-lg-inline ms-1">Card #<?= (int)$reg['competitor_number'] ?></span>
                </a>
              <?php elseif ($editable): ?>
                <a href="/athlete/events/<?= e(hid_event((int)$reg['event_id'])) ?>/register"
                   class="btn btn-outline-primary" title="Edit">
                  <i class="bi bi-pencil"></i><span class="d-none d-lg-inline ms-1">Edit</span>
                </a>
              <?php else: ?>
                <button type="button" class="btn btn-outline-secondary" disabled
                        title="Locked — registration is under review"><i class="bi bi-lock"></i></button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Mobile: card stack (xs–sm) -->
<div class="d-md-none">
  <?php foreach ($registrations as $reg):
    $editable   = \Models\EventRegistration::isEditable($reg);
    $isApproved = ($reg['admin_review_status'] ?? '') === 'approved' && !empty($reg['competitor_number']);
    $tot        = (float)($reg['total_amount'] ?? 0);
  ?>
    <div class="sms-card p-3 mb-3">
      <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
        <div class="min-w-0">
          <div class="fw-semibold text-break"><?= e($reg['event_name']) ?></div>
          <div class="small text-muted text-break">
            <i class="bi bi-building me-1"></i><?= e($reg['institution_name']) ?>
          </div>
        </div>
        <div class="text-end flex-shrink-0">
          <?= appStatusBadge($reg['admin_review_status'] ?? null, $reg['submitted_at'] ?? null) ?>
        </div>
      </div>

      <div class="small text-muted mb-2">
        <div><i class="bi bi-geo-alt me-1"></i><?= e($reg['location']) ?></div>
        <div><i class="bi bi-calendar3 me-1"></i><?= formatDate($reg['event_date_from']) ?> – <?= formatDate($reg['event_date_to']) ?></div>
      </div>

      <div class="row g-2 small mb-2">
        <div class="col-6">
          <div class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.04em">Unit</div>
          <div class="text-break"><?= !empty($reg['unit_name']) ? e($reg['unit_name']) : '—' ?></div>
        </div>
        <div class="col-6 text-end">
          <div class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.04em">Total Fee</div>
          <div class="fw-medium"><?= $tot > 0 ? '₹' . number_format($tot, 2) : '<span class="text-muted">—</span>' ?></div>
        </div>
        <div class="col-6">
          <div class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.04em">Sports</div>
          <div class="text-break">
            <?php if (!empty($reg['sport_name'])): ?>
              <?= e($reg['sport_name']) ?>
            <?php endif; ?>
            <?php if ((int)($reg['items_count'] ?? 0) > 0): ?>
              <span class="badge bg-secondary-subtle text-secondary"><?= (int)$reg['items_count'] ?> event<?= (int)$reg['items_count'] === 1 ? '' : 's' ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-6 text-end">
          <div class="text-muted text-uppercase" style="font-size:.65rem;letter-spacing:.04em">Payment</div>
          <div>
            <?php if (!empty($reg['payment_mode'])): ?>
              <small class="text-muted"><i class="bi bi-<?= $reg['payment_mode'] === 'manual' ? 'bank' : 'credit-card' ?> me-1"></i><?= ucfirst($reg['payment_mode']) ?></small>
            <?php endif; ?>
            <?= statusBadge($reg['payment_status'] ?? 'pending') ?>
          </div>
        </div>
      </div>

      <div class="small text-muted mb-2">
        <i class="bi bi-clock-history me-1"></i>
        <?php if (!empty($reg['submitted_at'])): ?>
          Submitted <?= formatDate($reg['submitted_at'], 'd M Y H:i') ?>
        <?php else: ?>
          <em>Not submitted</em> · created <?= formatDate($reg['registered_at'], 'd M Y') ?>
        <?php endif; ?>
      </div>

      <div class="d-flex gap-2 flex-wrap">
        <a href="/athlete/registrations/<?= e(hid_reg((int)$reg['id'])) ?>"
           class="btn btn-sm btn-outline-secondary flex-fill">
          <i class="bi bi-eye me-1"></i>View
        </a>
        <?php if ($isApproved): ?>
          <a href="/athlete/registrations/<?= e(hid_reg((int)$reg['id'])) ?>/card" target="_blank"
             class="btn btn-sm btn-success flex-fill">
            <i class="bi bi-card-heading me-1"></i>Card #<?= (int)$reg['competitor_number'] ?>
          </a>
        <?php elseif ($editable): ?>
          <a href="/athlete/events/<?= e(hid_event((int)$reg['event_id'])) ?>/register"
             class="btn btn-sm btn-outline-primary flex-fill">
            <i class="bi bi-pencil me-1"></i>Edit
          </a>
        <?php else: ?>
          <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" disabled
                  title="Locked — registration is under review">
            <i class="bi bi-lock me-1"></i>Locked
          </button>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
