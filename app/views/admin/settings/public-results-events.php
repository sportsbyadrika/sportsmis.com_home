<?php $pageTitle = 'Events — ' . ($site['title'] ?? ''); $selected = $selected ?? []; ?>

<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
  <a href="/admin/settings/public-results" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Sites</a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-event me-2"></i>Events — <?= e($site['title']) ?></h5>
  <span class="badge bg-secondary-subtle text-secondary-emphasis"><code><?= e($site['slug']) ?></code></span>
</div>

<?= flashBag() ?>

<div class="sms-card p-3">
  <p class="small text-muted">Tick the events to publish on this site. The public page shows a card per event, ordered as listed.</p>
  <form method="POST" action="/admin/settings/public-results/events/save">
    <?= csrf() ?>
    <input type="hidden" name="site_id" value="<?= (int)$site['id'] ?>">
    <div class="d-flex align-items-center gap-2 mb-2">
      <input type="text" id="evFilter" class="form-control form-control-sm" style="max-width:280px"
             placeholder="Filter events…" onkeyup="prFilter()">
      <span class="small text-muted ms-auto"><?= count($selected) ?> selected</span>
    </div>
    <?php if (empty($events)): ?>
      <p class="text-muted small mb-0">No events found.</p>
    <?php else: ?>
      <div class="border rounded p-2" style="max-height:60vh;overflow:auto" id="evList">
        <?php foreach ($events as $ev): $eid = (int)$ev['id']; ?>
          <div class="form-check ev-row" data-name="<?= e(strtolower((string)$ev['name'] . ' ' . (string)($ev['event_code'] ?? ''))) ?>">
            <input class="form-check-input" type="checkbox" name="event_ids[]" value="<?= $eid ?>" id="ev<?= $eid ?>"
                   <?= in_array($eid, $selected, true) ? 'checked' : '' ?>>
            <label class="form-check-label small" for="ev<?= $eid ?>">
              <?= e($ev['name']) ?>
              <?php if (!empty($ev['event_code'])): ?><code class="ms-1"><?= e($ev['event_code']) ?></code><?php endif; ?>
              <?php if (!empty($ev['event_date_from'])): ?><span class="text-muted ms-1"><?= e(formatDate($ev['event_date_from'], 'd M Y')) ?></span><?php endif; ?>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <button type="submit" class="btn btn-sm btn-primary mt-3"><i class="bi bi-save me-1"></i>Save Events</button>
  </form>
</div>

<script>
function prFilter() {
  var q = (document.getElementById('evFilter').value || '').toLowerCase();
  document.querySelectorAll('#evList .ev-row').forEach(function (r) {
    r.classList.toggle('d-none', q !== '' && (r.dataset.name || '').indexOf(q) === -1);
  });
}
</script>
