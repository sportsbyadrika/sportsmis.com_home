<?php
$pageTitle = 'Sports — Visibility';
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/admin/settings/sports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Sports Setting
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>Sports — Visibility</h5>
  <span class="text-muted small ms-2">Enable a sport to make its events available to institutions.</span>
</div>

<?= flashBag() ?>

<div class="sms-card p-3">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:50px">#</th>
          <th>Sport</th>
          <th style="width:140px" class="text-center">Categories</th>
          <th style="width:140px" class="text-center">Sport Events</th>
          <th style="width:130px" class="text-center">Visibility</th>
          <th class="text-end" style="width:170px">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sports as $i => $s): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td class="fw-medium"><?= e($s['name']) ?></td>
            <td class="text-center"><?= (int)$s['category_count'] ?></td>
            <td class="text-center"><?= (int)$s['event_count'] ?></td>
            <td class="text-center">
              <div class="form-check form-switch d-inline-flex mb-0">
                <input class="form-check-input" type="checkbox" role="switch"
                       data-sport-id="<?= (int)$s['id'] ?>"
                       <?= !empty($s['enabled_for_events']) ? 'checked' : '' ?>
                       onchange="toggleSport(this)">
              </div>
            </td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary"
                 href="/admin/settings/sports/<?= (int)$s['id'] ?>/categories">
                <i class="bi bi-tags me-1"></i>Categories
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const CSRF = '<?= e($csrfToken) ?>';
async function toggleSport(el) {
  const fd = new FormData();
  fd.append('_token',   CSRF);
  fd.append('sport_id', el.dataset.sportId);
  if (el.checked) fd.append('enabled', '1');
  const res = await fetch('/admin/settings/sports/toggle', { method: 'POST', body: fd });
  const d   = await res.json();
  if (!d.success) {
    el.checked = !el.checked;
    alert(d.message || 'Toggle failed.');
  }
}
</script>
