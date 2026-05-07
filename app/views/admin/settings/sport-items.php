<?php $pageTitle = 'Sports Items / Weapons'; ?>

<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
  <a href="/admin/settings" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Settings
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-tools me-2"></i>Sports Items / Weapons</h5>
  <span class="text-muted small ms-2">Per-sport catalogue used by event admins and athletes.</span>
</div>

<?= flashBag() ?>

<?php foreach ($sports as $sp): ?>
  <div class="sms-card p-4 mb-4" id="sport-<?= (int)$sp['id'] ?>">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3 flex-wrap gap-2">
      <h6 class="fw-semibold mb-0"><i class="bi bi-trophy me-2"></i><?= e($sp['name']) ?></h6>
      <button type="button" class="btn btn-sm btn-primary"
              onclick="openItemForm(<?= (int)$sp['id'] ?>)">
        <i class="bi bi-plus-lg me-1"></i>Add Item
      </button>
    </div>

    <?php if (empty($sp['items'])): ?>
      <p class="text-muted small mb-0">No items yet for this sport.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr><th>Name</th><th>Description</th><th>Status</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($sp['items'] as $it): ?>
            <tr data-id="<?= (int)$it['id'] ?>">
              <td class="fw-medium"><?= e($it['name']) ?></td>
              <td class="text-muted small"><?= e($it['description'] ?? '—') ?></td>
              <td>
                <?php if (($it['status'] ?? 'active') === 'active'): ?>
                  <span class="badge bg-success">Active</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Inactive</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-primary"
                        onclick='openItemForm(<?= (int)$sp['id'] ?>, <?= json_encode($it, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
                  <i class="bi bi-pencil"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger"
                        onclick="deleteItem(<?= (int)$it['id'] ?>, '<?= e(addslashes($it['name'])) ?>')">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<!-- Modal for add / edit -->
<div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="itemForm" class="modal-content">
      <?= csrf() ?>
      <input type="hidden" name="id"       id="i_id">
      <input type="hidden" name="sport_id" id="i_sport_id">
      <div class="modal-header">
        <h5 class="modal-title" id="itemModalTitle">Add Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="name" id="i_name" maxlength="255" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea class="form-control" name="description" id="i_description" rows="2" maxlength="2000"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Status</label>
          <select class="form-select" name="status" id="i_status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save</button>
      </div>
    </form>
  </div>
</div>

<script>
const itemModal = new bootstrap.Modal(document.getElementById('itemModal'));

function openItemForm(sportId, item) {
  document.getElementById('i_sport_id').value   = sportId;
  document.getElementById('i_id').value         = item ? item.id : '';
  document.getElementById('i_name').value       = item ? item.name : '';
  document.getElementById('i_description').value = item ? (item.description || '') : '';
  document.getElementById('i_status').value     = item ? (item.status || 'active') : 'active';
  document.getElementById('itemModalTitle').textContent = item ? 'Edit Item' : 'Add Item';
  itemModal.show();
}

document.getElementById('itemForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  const fd = new FormData(this);
  try {
    const res = await fetch('/admin/settings/sport-items/save', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) { alert(data.message || 'Save failed.'); return; }
    itemModal.hide();
    location.reload();
  } catch (err) {
    alert('Network error: ' + err.message);
  }
});

async function deleteItem(id, name) {
  if (!confirm('Delete item "' + name + '"?\n\nIf any event has selected it or any athlete has registered it, the delete will fail.')) return;
  const fd = new FormData();
  fd.append('id', id);
  fd.append('csrf_token', '<?= e($_SESSION['csrf_token'] ?? '') ?>');
  try {
    const res = await fetch('/admin/settings/sport-items/delete', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) { alert(data.message || 'Delete failed.'); return; }
    location.reload();
  } catch (err) {
    alert('Network error: ' + err.message);
  }
}
</script>
