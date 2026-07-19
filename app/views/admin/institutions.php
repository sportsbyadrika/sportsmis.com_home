<?php $pageTitle = 'Institutions'; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold"><i class="bi bi-building me-2"></i>Institutions</h5>
  <a href="/admin/impersonation-log" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-clock-history me-1"></i>Support Login Log
  </a>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4">
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'pending' ? 'active' : '' ?>" href="?tab=pending">
      Pending Registrations
      <?php if (count($pending_registrations)): ?>
        <span class="badge bg-danger ms-1"><?= count($pending_registrations) ?></span>
      <?php endif; ?>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'all' ? 'active' : '' ?>" href="?tab=all">All Institutions</a>
  </li>
</ul>

<?php if ($tab === 'pending'): ?>
  <?php if (empty($pending_registrations)): ?>
    <div class="sms-empty-state">
      <i class="bi bi-check-circle text-success"></i>
      <h5>All Clear!</h5>
      <p>No pending institution registrations.</p>
    </div>
  <?php else: ?>
  <div class="sms-card">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr><th>Institution</th><th>SPOC</th><th>Mobile</th><th>Email</th><th>Submitted</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php foreach ($pending_registrations as $r): ?>
          <tr>
            <td class="fw-medium"><?= e($r['institution_name']) ?></td>
            <td><?= e($r['spoc_name']) ?></td>
            <td class="text-muted"><?= e($r['spoc_mobile']) ?></td>
            <td class="text-muted"><?= e($r['email']) ?></td>
            <td class="text-muted small"><?= formatDate($r['created_at']) ?></td>
            <td class="d-flex gap-1">
              <a href="/admin/institutions/<?= $r['id'] ?>" class="btn btn-sm btn-primary">Review</a>
              <form method="POST" action="/admin/institutions/<?= $r['id'] ?>/reject"
                    onsubmit="return confirm('Reject this registration?')">
                <?= csrf() ?>
                <button class="btn btn-sm btn-outline-danger">Reject</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

<?php else: ?>
  <div class="sms-card">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr><th>Institution</th><th>Type</th><th>Email</th><th>Valid Till</th><th>Status</th><th class="text-center">Event Creation</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($institutions as $inst): ?>
          <tr>
            <td>
              <div class="fw-medium"><?= e($inst['name']) ?></div>
              <small class="text-muted"><?= e($inst['address'] ?? '') ?></small>
            </td>
            <td class="text-muted"><?= e($inst['type_name'] ?? '—') ?></td>
            <td class="text-muted"><?= e($inst['email']) ?></td>
            <td class="text-muted small"><?= formatDate($inst['validity_to']) ?></td>
            <td><?= statusBadge($inst['status']) ?></td>
            <td class="text-center">
              <form method="POST" action="/admin/institutions/<?= (int)$inst['id'] ?>/toggle-event-creation" class="m-0 d-inline-block">
                <?= csrf() ?>
                <input type="hidden" name="enabled" value="0">
                <div class="form-check form-switch d-inline-flex align-items-center gap-2 mb-0">
                  <input class="form-check-input" type="checkbox" role="switch"
                         id="ec<?= (int)$inst['id'] ?>"
                         <?= !empty($inst['event_creation_enabled']) ? 'checked' : '' ?>
                         onchange="this.form.enabled.value = this.checked ? 1 : 0; this.form.submit();">
                  <label class="form-check-label small text-muted" for="ec<?= (int)$inst['id'] ?>">
                    <?= !empty($inst['event_creation_enabled']) ? 'Enabled' : 'Disabled' ?>
                  </label>
                </div>
              </form>
            </td>
            <td class="text-end text-nowrap">
              <?php if (!empty($inst['user_id'])): ?>
                <form method="POST" action="/admin/institutions/<?= (int)$inst['id'] ?>/login-as" class="d-inline"
                      onsubmit="return confirm('Sign in as <?= e(addslashes($inst['name'] ?? 'this institution')) ?> for support? You can return to your Super Admin account anytime from the banner.');">
                  <?= csrf() ?>
                  <button type="submit" class="btn btn-sm btn-outline-primary" title="Sign in as this institution for support">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Login
                  </button>
                </form>
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        onclick="resetInstPassword(<?= (int)$inst['id'] ?>, '<?= e(addslashes($inst['name'] ?? '')) ?>', '<?= e(addslashes($inst['email'] ?? '')) ?>')">
                  <i class="bi bi-key me-1"></i>Reset Password
                </button>
              <?php else: ?>
                <span class="small text-muted">No login</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Reset password modal -->
  <div class="modal fade" id="resetInstPwdModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" method="POST" id="resetInstPwdForm">
        <?= csrf() ?>
        <div class="modal-header">
          <h6 class="modal-title fw-semibold"><i class="bi bi-key me-2"></i>Reset Password</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted mb-3">
            Set a new login password for <strong id="resetInstName"></strong>
            (<span id="resetInstEmail" class="font-monospace"></span>). Share it securely — it
            replaces their current password immediately.
          </p>
          <div class="mb-3">
            <label class="form-label small">New Password</label>
            <div class="input-group input-group-sm">
              <input type="text" name="password" id="resetInstPwd" class="form-control"
                     minlength="8" required autocomplete="off" placeholder="min. 8 characters">
              <button type="button" class="btn btn-outline-secondary" onclick="genInstPwd()">
                <i class="bi bi-shuffle me-1"></i>Generate
              </button>
            </div>
          </div>
          <div class="mb-1">
            <label class="form-label small">Confirm Password</label>
            <input type="text" name="password_confirmation" id="resetInstPwdC"
                   class="form-control form-control-sm" minlength="8" required autocomplete="off">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Set Password</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  let _resetInstModal = null;
  function resetInstPassword(id, name, email) {
    if (!_resetInstModal) _resetInstModal = new bootstrap.Modal(document.getElementById('resetInstPwdModal'));
    document.getElementById('resetInstPwdForm').action = '/admin/institutions/' + id + '/reset-password';
    document.getElementById('resetInstName').textContent  = name || '';
    document.getElementById('resetInstEmail').textContent = email || '';
    document.getElementById('resetInstPwd').value  = '';
    document.getElementById('resetInstPwdC').value = '';
    _resetInstModal.show();
  }
  function genInstPwd() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789@#%';
    let p = '';
    const a = new Uint32Array(12);
    (window.crypto || window.msCrypto).getRandomValues(a);
    for (let i = 0; i < 12; i++) p += chars[a[i] % chars.length];
    document.getElementById('resetInstPwd').value  = p;
    document.getElementById('resetInstPwdC').value = p;
  }
  </script>
<?php endif; ?>
