<?php $pageTitle = 'Events – Admin'; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-event me-2"></i>Event Management</h5>
  <button type="button" class="btn btn-sm btn-outline-primary" onclick="pushSpoc()">
    <i class="bi bi-arrow-repeat me-1"></i>Push SPOC to Units
    <span class="badge bg-primary ms-1" id="spocCount">0</span>
  </button>
</div>

<?= flashBag() ?>

<div class="alert alert-light border small d-flex align-items-start gap-2 py-2">
  <i class="bi bi-info-circle mt-1"></i>
  <div>
    Select events and click <strong>Push SPOC to Units</strong> to (re)copy each participating
    institution's SPOC name, mobile and email onto its linked unit(s) for those events. SPOC
    details are also carried over automatically when a participation request is approved and
    refreshed each time the institution opens the event's Unit Console.
  </div>
</div>

<!-- Hidden form used by the bulk SPOC push (kept outside the table so it
     never nests with the per-row status / delete forms). -->
<form method="POST" action="/admin/events/push-spoc" id="spocForm" class="d-none">
  <?= csrf() ?>
</form>

<div class="sms-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:36px"><input type="checkbox" class="form-check-input" id="spocAll" onchange="spocToggleAll(this)"></th>
          <th>Event</th>
          <th>Institution</th>
          <th>Dates</th>
          <th>Submitted</th>
          <th>Status</th>
          <th>Change Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php
        $statusMap = [
          'draft'             => ['label'=>'Draft',     'class'=>'bg-secondary'],
          'active'            => ['label'=>'Active',    'class'=>'bg-success'],
          'completed'         => ['label'=>'Completed', 'class'=>'bg-info text-dark'],
          'suspended'         => ['label'=>'Suspended', 'class'=>'bg-danger'],
          // legacy values still in DB before backfill runs
          'pending_approval'  => ['label'=>'Pending',   'class'=>'bg-warning text-dark'],
          'approved'          => ['label'=>'Active',    'class'=>'bg-success'],
          'rejected'          => ['label'=>'Suspended', 'class'=>'bg-danger'],
          'cancelled'         => ['label'=>'Suspended', 'class'=>'bg-danger'],
        ];
        ?>
        <?php foreach ($events as $event):
            $cur = $event['status'] ?? 'draft';
            $disp = $statusMap[$cur] ?? $statusMap['draft'];
        ?>
        <tr>
          <td>
            <input type="checkbox" class="form-check-input spoc-check" value="<?= (int)$event['id'] ?>"
                   onchange="spocSync()">
          </td>
          <td>
            <div class="fw-medium"><?= e($event['name']) ?></div>
            <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($event['location']) ?></small>
          </td>
          <td class="text-muted"><?= e($event['institution_name']) ?></td>
          <td class="text-muted small">
            <?= formatDate($event['event_date_from']) ?> – <?= formatDate($event['event_date_to']) ?>
          </td>
          <td class="text-muted small"><?= formatDate($event['created_at']) ?></td>
          <td><span class="badge <?= $disp['class'] ?>"><?= $disp['label'] ?></span></td>
          <td>
            <form method="POST" action="/admin/events/<?= (int)$event['id'] ?>/status" class="d-flex gap-2">
              <?= csrf() ?>
              <select name="status" class="form-select form-select-sm" style="width:140px">
                <option value="draft"     <?= $cur==='draft'     ? 'selected':'' ?>>Draft</option>
                <option value="active"    <?= in_array($cur,['active','approved','pending_approval'],true) ? 'selected':'' ?>>Active</option>
                <option value="completed" <?= $cur==='completed' ? 'selected':'' ?>>Completed</option>
                <option value="suspended" <?= in_array($cur,['suspended','rejected','cancelled'],true) ? 'selected':'' ?>>Suspended</option>
              </select>
              <button class="btn btn-sm btn-primary" onclick="return confirm('Change event status?')">
                <i class="bi bi-save"></i>
              </button>
            </form>
          </td>
          <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger"
                    data-bs-toggle="modal" data-bs-target="#smsDeleteModal"
                    data-action="/admin/events/<?= (int)$event['id'] ?>/delete"
                    data-kind="event"
                    data-name="<?= e($event['name']) ?>"
                    data-warning="Removes the event ONLY if no athletes have registered. Attached files (logo, QR code, documents) will also be deleted from disk."
                    title="Delete event (no registrations)">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($events)): ?>
          <tr><td colspan="8" class="text-muted text-center py-4">No events yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function spocSync() {
  document.getElementById('spocCount').textContent =
    document.querySelectorAll('.spoc-check:checked').length;
}
function spocToggleAll(master) {
  document.querySelectorAll('.spoc-check').forEach(cb => { cb.checked = master.checked; });
  spocSync();
}
function pushSpoc() {
  const checked = Array.from(document.querySelectorAll('.spoc-check:checked'));
  if (!checked.length) { alert('Select at least one event first.'); return; }
  if (!confirm('Push SPOC details from the participating institutions onto the linked units of '
      + checked.length + ' selected event(s)?')) return;
  const form = document.getElementById('spocForm');
  form.querySelectorAll('input.spoc-id').forEach(n => n.remove());
  checked.forEach(cb => {
    const i = document.createElement('input');
    i.type = 'hidden'; i.name = 'event_ids[]'; i.value = cb.value; i.className = 'spoc-id';
    form.appendChild(i);
  });
  form.submit();
}
</script>

<?php include __DIR__ . '/_delete-modal.php'; ?>
