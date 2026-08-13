<?php $pageTitle = 'Delete Institution'; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold"><i class="bi bi-trash me-2"></i>Delete Institution</h5>
  <a href="/admin/institutions" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Back to Institutions
  </a>
</div>

<?= flashBag() ?>

<div class="alert alert-danger d-flex gap-2">
  <i class="bi bi-exclamation-octagon-fill fs-5 flex-shrink-0"></i>
  <div>
    <strong>Dangerous action.</strong> Deleting an institution permanently removes its profile,
    registration and institution staff, and drops organiser access from its login account
    (an institution-only login is removed; a login that is also an athlete keeps its athlete access).
    <strong>This cannot be undone.</strong> An institution linked to any event — one it owns or
    participates in — cannot be deleted.
  </div>
</div>

<div class="sms-card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Institution</th>
          <th>Type</th>
          <th>Owner email</th>
          <th class="text-center">Events owned</th>
          <th class="text-center">Participations</th>
          <th class="text-end">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($institutions)): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">No institutions.</td></tr>
        <?php else: foreach ($institutions as $i):
          $linked = (int)$i['event_count'] > 0 || (int)$i['participation_count'] > 0;
        ?>
          <tr>
            <td>
              <div class="fw-medium"><?= e($i['name'] ?? '') ?></div>
              <?php if (!empty($i['address'])): ?><small class="text-muted"><?= e($i['address']) ?></small><?php endif; ?>
            </td>
            <td class="text-muted"><?= e($i['type_name'] ?? '—') ?: '—' ?></td>
            <td class="text-muted"><?= e($i['owner_email'] ?? '—') ?: '—' ?></td>
            <td class="text-center"><?= (int)$i['event_count'] ?></td>
            <td class="text-center"><?= (int)$i['participation_count'] ?></td>
            <td class="text-end">
              <?php if ($linked): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                        title="Linked to one or more events — cannot be deleted">
                  <i class="bi bi-lock me-1"></i>Locked
                </button>
              <?php else: ?>
                <button type="button" class="btn btn-sm btn-outline-danger"
                        onclick="askDeleteInstitution(<?= (int)$i['id'] ?>, '<?= e(addslashes($i['name'] ?? '')) ?>')">
                  <i class="bi bi-trash me-1"></i>Delete
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Typed-confirmation modal -->
<div class="modal fade" id="delInstModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="POST" id="delInstForm">
      <?= csrf() ?>
      <div class="modal-header">
        <h6 class="modal-title fw-semibold text-danger">
          <i class="bi bi-exclamation-octagon me-2"></i>Delete institution
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small mb-3">
          You are about to permanently delete <strong id="delInstName"></strong>. This action
          cannot be undone.
        </p>
        <label class="form-label small mb-1">Type the institution name to confirm</label>
        <input type="text" id="delInstConfirm" class="form-control form-control-sm"
               autocomplete="off" oninput="delInstCheck()" placeholder="Institution name">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" id="delInstBtn" class="btn btn-danger" disabled>
          <i class="bi bi-trash me-1"></i>Delete permanently
        </button>
      </div>
    </form>
  </div>
</div>

<script>
let _delInstModal = null, _delInstName = '';
function askDeleteInstitution(id, name) {
  if (!_delInstModal) _delInstModal = new bootstrap.Modal(document.getElementById('delInstModal'));
  _delInstName = (name || '').trim().toLowerCase();
  document.getElementById('delInstForm').action = '/admin/institutions/' + id + '/delete';
  document.getElementById('delInstName').textContent = name || '';
  const c = document.getElementById('delInstConfirm');
  c.value = '';
  document.getElementById('delInstBtn').disabled = true;
  _delInstModal.show();
  setTimeout(function () { c.focus(); }, 300);
}
function delInstCheck() {
  const v = document.getElementById('delInstConfirm').value.trim().toLowerCase();
  document.getElementById('delInstBtn').disabled = (v === '' || v !== _delInstName);
}
</script>
