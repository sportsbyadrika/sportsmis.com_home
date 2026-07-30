<?php $pageTitle = 'Public Result Sites'; ?>

<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
  <a href="/admin/settings" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Settings</a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-globe me-2"></i>Public Result Sites</h5>
  <button class="btn btn-sm btn-primary ms-auto" onclick="openSiteModal()"><i class="bi bi-plus-lg me-1"></i>Add Site</button>
</div>

<?= flashBag() ?>

<div class="sms-card p-3">
  <p class="small text-muted">
    Each site publishes a group of events' <strong>published results</strong> on a public, no-login page at
    <code><?= e($baseDomain) ?>/&lt;slug&gt;</code> — no DNS or subdomain setup needed.
  </p>
  <?php if (empty($sites)): ?>
    <p class="text-muted small mb-0">No sites yet. Add one above.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:44px">#</th>
            <th>Logo</th>
            <th>Title</th>
            <th>Public URL</th>
            <th class="text-center">Events</th>
            <th class="text-center">Status</th>
            <th class="text-end" style="width:180px">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sites as $i => $s): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?php if (!empty($s['logo'])): ?><img src="<?= e($s['logo']) ?>" alt="" style="height:32px" class="rounded"><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
              <td class="fw-medium"><?= e($s['title']) ?></td>
              <td><code><?= e($baseDomain) ?>/<?= e($s['slug']) ?></code></td>
              <td class="text-center"><span class="badge bg-primary-subtle text-primary-emphasis"><?= (int)$s['event_count'] ?></span></td>
              <td class="text-center">
                <span class="badge bg-<?= $s['status'] === 'active' ? 'success' : 'secondary' ?>-subtle text-<?= $s['status'] === 'active' ? 'success' : 'secondary' ?>-emphasis"><?= e(ucfirst($s['status'])) ?></span>
              </td>
              <td class="text-end">
                <div class="d-inline-flex gap-1">
                  <a href="/admin/settings/public-results/<?= (int)$s['id'] ?>/events" class="btn btn-sm btn-outline-primary" title="Manage events">
                    <i class="bi bi-calendar-event"></i> Events
                  </a>
                  <button type="button" class="btn btn-sm btn-outline-secondary" title="Edit"
                          onclick='editSite(<?= json_encode($s, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
                    <i class="bi bi-pencil"></i>
                  </button>
                  <form method="POST" action="/admin/settings/public-results/delete" class="m-0"
                        onsubmit="return confirm('Delete this site and its event list?');">
                    <?= csrf() ?>
                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Add / Edit modal -->
<div class="modal fade" id="siteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="POST" action="/admin/settings/public-results/save" enctype="multipart/form-data">
      <?= csrf() ?>
      <input type="hidden" name="id" id="site_id" value="">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-globe me-2"></i><span id="siteModalTitle">Add Site</span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-medium">URL slug <span class="text-danger">*</span></label>
          <div class="input-group input-group-sm">
            <span class="input-group-text"><?= e($baseDomain) ?>/</span>
            <input type="text" name="slug" id="site_slug" class="form-control" required
                   pattern="[a-z0-9][a-z0-9-]{0,62}" placeholder="sahodaya">
          </div>
          <div class="form-text">Lowercase letters, numbers and hyphens only.</div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-medium">Title <span class="text-danger">*</span></label>
          <input type="text" name="title" id="site_title" class="form-control form-control-sm" maxlength="160" required
                 placeholder="Sahodaya Sports Meet 2026">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-medium">Logo <span class="text-muted">(optional)</span></label>
          <input type="file" name="logo" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp">
        </div>
        <div class="mb-1">
          <label class="form-label small fw-medium">Status</label>
          <select name="status" id="site_status" class="form-select form-select-sm">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Save</button>
      </div>
    </form>
  </div>
</div>

<script>
let siteModalInst = null;
document.addEventListener('DOMContentLoaded', () => {
  siteModalInst = bootstrap.Modal.getOrCreateInstance(document.getElementById('siteModal'));
});
function openSiteModal() {
  const $ = id => document.getElementById(id);
  $('site_id').value = ''; $('site_slug').value = ''; $('site_title').value = ''; $('site_status').value = 'active';
  $('site_slug').removeAttribute('readonly');
  document.getElementById('siteModalTitle').textContent = 'Add Site';
  siteModalInst.show();
}
function editSite(s) {
  const $ = id => document.getElementById(id);
  $('site_id').value = s.id; $('site_slug').value = s.slug || ''; $('site_title').value = s.title || '';
  $('site_status').value = s.status || 'active';
  document.getElementById('siteModalTitle').textContent = 'Edit Site';
  siteModalInst.show();
}
</script>
