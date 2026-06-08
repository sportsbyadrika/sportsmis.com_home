<?php
$pageTitle = 'Certificates — ' . $event['name'];
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-award me-2"></i>Certificates</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <a href="/institution/events/<?= e($eventHash) ?>/certificates/settings"
     class="btn btn-sm btn-outline-secondary ms-auto">
    <i class="bi bi-gear me-1"></i>Template Settings
  </a>
</div>

<?= flashBag() ?>

<form method="POST"
      action="/institution/events/<?= e($eventHash) ?>/certificates/athlete-view-toggle"
      class="sms-card p-3 mb-3 d-flex align-items-center gap-3 flex-wrap">
  <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
  <div class="form-check form-switch mb-0">
    <input class="form-check-input" type="checkbox" role="switch"
           id="athleteViewSwitch" name="enabled" value="1"
           <?= !empty($event['cert_athlete_view_enabled']) ? 'checked' : '' ?>
           onchange="this.form.submit()">
    <label class="form-check-label fw-medium" for="athleteViewSwitch">
      View certificate in Athlete login
    </label>
  </div>
  <small class="text-muted">
    When ON, athletes see a <em>Certificate</em> button on their My Registrations page once their certificate is generated. OFF hides the button and blocks the URL even if a certificate row exists.
  </small>
  <noscript>
    <button class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Save</button>
  </noscript>
</form>

<?php if (!$configured): ?>
  <div class="alert alert-warning small">
    <i class="bi bi-exclamation-triangle me-1"></i>
    The certificate template isn't configured yet. Upload a background image and
    write the body paragraph in
    <a href="/institution/events/<?= e($eventHash) ?>/certificates/settings">Template Settings</a>
    before generating.
  </div>
<?php endif; ?>

<div class="sms-card p-3">
  <?php if (empty($units)): ?>
    <p class="text-muted small mb-0 text-center py-3">No Units / Clubs / Institutions configured on this event.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:50px">#</th>
          <th>Unit</th>
          <th>Address</th>
          <th style="width:120px" class="text-center">Approved</th>
          <th style="width:120px" class="text-center">Issued</th>
          <th class="text-end" style="width:240px">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($units as $i => $u): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <?php if (!empty($u['logo'])): ?>
                  <img src="<?= e($u['logo']) ?>" width="32" height="32"
                       class="rounded" style="object-fit:cover">
                <?php else: ?>
                  <div class="rounded d-inline-flex align-items-center justify-content-center bg-light text-muted"
                       style="width:32px;height:32px"><i class="bi bi-building"></i></div>
                <?php endif; ?>
                <div class="fw-medium"><?= e($u['name']) ?></div>
              </div>
            </td>
            <td class="small text-muted"><?= e($u['address']) ?: '—' ?></td>
            <td class="text-center"><?= (int)$u['approved_count'] ?></td>
            <td class="text-center"><?= (int)$u['issued_count'] ?></td>
            <td class="text-end">
              <form method="POST" class="d-inline" target="_blank"
                    action="/institution/events/<?= e($eventHash) ?>/certificates/units/<?= (int)$u['id'] ?>"
                    onsubmit="return confirm('Generate certificates for <?= e($u['name']) ?>?');">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <button class="btn btn-sm btn-primary"
                        <?= !$configured || (int)$u['approved_count'] === 0 ? 'disabled' : '' ?>>
                  <i class="bi bi-magic me-1"></i>Generate
                </button>
              </form>
              <?php if ((int)$u['issued_count'] > 0): ?>
                <a class="btn btn-sm btn-outline-success"
                   href="/institution/events/<?= e($eventHash) ?>/certificates/units/<?= (int)$u['id'] ?>/view"
                   target="_blank" rel="noopener">
                  <i class="bi bi-eye me-1"></i>View
                </a>
                <form method="POST" class="d-inline" target="_blank"
                      action="/institution/events/<?= e($eventHash) ?>/certificates/units/<?= (int)$u['id'] ?>/reset"
                      onsubmit="return confirm('Delete the existing <?= (int)$u['issued_count'] ?> certificate(s) for <?= e($u['name']) ?> and re-issue fresh numbers? This cannot be undone.');">
                  <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                  <button class="btn btn-sm btn-outline-warning"
                          <?= !$configured ? 'disabled' : '' ?>>
                    <i class="bi bi-arrow-clockwise me-1"></i>Reset
                  </button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
