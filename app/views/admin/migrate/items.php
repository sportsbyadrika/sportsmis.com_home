<?php
$pageTitle = 'Event Migrate — Step 2';
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];
$sel = $state['sets'];
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/admin/event-migrate?keep=1" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-check2-square me-2"></i>Event Migrate</h5>
  <span class="text-muted small ms-2">Step 2 of 3 — pick items from each selected set</span>
</div>

<div class="sms-card p-3 mb-3 small">
  <div class="d-flex flex-wrap gap-3 align-items-center">
    <span class="badge bg-primary-subtle text-primary-emphasis">
      <i class="bi bi-box-arrow-up-right me-1"></i>Source: <?= e($src_event['name'] ?? '—') ?>
    </span>
    <i class="bi bi-arrow-right text-muted"></i>
    <span class="badge bg-success-subtle text-success-emphasis">
      <i class="bi bi-box-arrow-down-right me-1"></i>Destination: <?= e($dst_event['name'] ?? '—') ?>
    </span>
  </div>
</div>

<form method="POST" action="/admin/event-migrate/items">
  <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

  <?php if (in_array('sports', $sel, true)): ?>
    <div class="sms-card p-3 mb-3">
      <div class="d-flex align-items-center mb-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-trophy me-2"></i><?= e($set_labels['sports']) ?></h6>
        <button type="button" class="btn btn-sm btn-link ms-auto" onclick="toggleAll('sports')">Select all</button>
      </div>
      <?php if (empty($data['sports'])): ?>
        <p class="small text-muted mb-0">Source event has no sport-events configured.</p>
      <?php else: ?>
      <div class="table-responsive" style="max-height:340px;overflow:auto">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light"><tr>
            <th style="width:36px"></th><th>Code</th><th>Category</th><th>Sport Event</th>
            <th>Gender</th><th class="text-end">Entry Fee</th><th class="text-end">Team Fee</th>
          </tr></thead>
          <tbody>
            <?php foreach ($data['sports'] as $r): ?>
              <tr>
                <td><input class="form-check-input cb-sports" type="checkbox" name="sports[]" value="<?= (int)$r['id'] ?>"></td>
                <td><?= e($r['event_code']) ?></td>
                <td class="small"><?= e($r['category_name'] ?? '—') ?></td>
                <td><?= e($r['sport_event_name'] ?? $r['sport_name']) ?></td>
                <td class="small text-muted"><?= e($r['gender'] ?? '') ?></td>
                <td class="text-end"><?= number_format((float)$r['entry_fee'], 2) ?></td>
                <td class="text-end"><?= $r['team_entry_fee'] !== null ? number_format((float)$r['team_entry_fee'], 2) : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (in_array('units', $sel, true)): ?>
    <div class="sms-card p-3 mb-3">
      <div class="d-flex align-items-center mb-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-buildings me-2"></i><?= e($set_labels['units']) ?></h6>
        <button type="button" class="btn btn-sm btn-link ms-auto" onclick="toggleAll('units')">Select all</button>
      </div>
      <?php if (empty($data['units'])): ?>
        <p class="small text-muted mb-0">Source event has no units configured.</p>
      <?php else: ?>
      <div class="table-responsive" style="max-height:340px;overflow:auto">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light"><tr>
            <th style="width:36px"></th><th>Name</th><th>Address</th>
          </tr></thead>
          <tbody>
            <?php foreach ($data['units'] as $r): ?>
              <tr>
                <td><input class="form-check-input cb-units" type="checkbox" name="units[]" value="<?= (int)$r['id'] ?>"></td>
                <td class="fw-medium"><?= e($r['name']) ?></td>
                <td class="small text-muted"><?= e($r['address'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (in_array('items', $sel, true)): ?>
    <div class="sms-card p-3 mb-3">
      <div class="d-flex align-items-center mb-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-tools me-2"></i><?= e($set_labels['items']) ?></h6>
        <button type="button" class="btn btn-sm btn-link ms-auto" onclick="toggleAll('items')">Select all</button>
      </div>
      <?php if (empty($data['items'])): ?>
        <p class="small text-muted mb-0">Source event has no Sport Items / Weapons configured.</p>
      <?php else: ?>
      <div class="table-responsive" style="max-height:340px;overflow:auto">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light"><tr>
            <th style="width:36px"></th><th>Sport</th><th>Item / Weapon</th>
          </tr></thead>
          <tbody>
            <?php foreach ($data['items'] as $r): ?>
              <tr>
                <td><input class="form-check-input cb-items" type="checkbox" name="items[]" value="<?= (int)$r['id'] ?>"></td>
                <td class="small text-muted"><?= e($r['sport_name'] ?? '') ?></td>
                <td><?= e($r['item_name']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (in_array('ranges', $sel, true)): ?>
    <div class="sms-card p-3 mb-3">
      <div class="d-flex align-items-center mb-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-target me-2"></i><?= e($set_labels['ranges']) ?></h6>
        <small class="text-muted ms-2">Picking a range copies its distances + lanes</small>
        <button type="button" class="btn btn-sm btn-link ms-auto" onclick="toggleAll('ranges')">Select all</button>
      </div>
      <?php if (empty($data['ranges'])): ?>
        <p class="small text-muted mb-0">Source event has no shooting ranges configured.</p>
      <?php else: ?>
        <?php foreach ($data['ranges'] as $r): ?>
          <div class="border rounded p-2 mb-2">
            <div class="d-flex align-items-center gap-2">
              <input class="form-check-input cb-ranges" type="checkbox" name="ranges[]" value="<?= (int)$r['id'] ?>" id="range_<?= (int)$r['id'] ?>">
              <label for="range_<?= (int)$r['id'] ?>" class="fw-medium mb-0">
                <?= e($r['name']) ?>
                <?php if (!empty($r['location'])): ?> <small class="text-muted">— <?= e($r['location']) ?></small><?php endif; ?>
              </label>
            </div>
            <?php if (!empty($r['distances'])): ?>
              <div class="ps-4 mt-1 small text-muted">
                <?php foreach ($r['distances'] as $d): ?>
                  <div>
                    Distance: <strong class="text-dark"><?= e($d['name']) ?></strong>
                    <?php if (!empty($d['distance_meters'])): ?> (<?= (int)$d['distance_meters'] ?>m)<?php endif; ?>
                    <?php if (!empty($d['lanes'])): ?>
                      &middot; Lanes: <?= count($d['lanes']) ?>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (in_array('relays', $sel, true)): ?>
    <div class="sms-card p-3 mb-3">
      <div class="d-flex align-items-center mb-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-bullseye me-2"></i><?= e($set_labels['relays']) ?></h6>
        <small class="text-muted ms-2">Lane allocations (athletes/units) are NOT copied — only the lane slots</small>
        <button type="button" class="btn btn-sm btn-link ms-auto" onclick="toggleAll('relays')">Select all</button>
      </div>
      <?php if (empty($data['relays'])): ?>
        <p class="small text-muted mb-0">Source event has no relays configured.</p>
      <?php else: ?>
      <div class="table-responsive" style="max-height:340px;overflow:auto">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light"><tr>
            <th style="width:36px"></th><th>Relay #</th><th>Date / Time</th>
            <th>Range / Distance</th><th>Status</th>
          </tr></thead>
          <tbody>
            <?php foreach ($data['relays'] as $r): ?>
              <tr>
                <td><input class="form-check-input cb-relays" type="checkbox" name="relays[]" value="<?= (int)$r['id'] ?>"></td>
                <td><?= e($r['relay_number']) ?></td>
                <td class="small">
                  <?= e($r['relay_date'] ?? '—') ?>
                  <?php if (!empty($r['match_time'])): ?><br><small class="text-muted">Match <?= e(substr((string)$r['match_time'],0,5)) ?></small><?php endif; ?>
                </td>
                <td class="small"><?= e(($r['range_name'] ?? '') . ' / ' . ($r['distance_name'] ?? '')) ?></td>
                <td><span class="badge bg-secondary-subtle text-secondary"><?= e($r['result_status']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="d-flex justify-content-end gap-2">
    <a href="/admin/event-migrate?keep=1" class="btn btn-outline-secondary">Back</a>
    <button type="submit" class="btn btn-primary">
      Next: Preview <i class="bi bi-arrow-right ms-1"></i>
    </button>
  </div>
</form>

<script>
function toggleAll(set) {
  const boxes = document.querySelectorAll('.cb-' + set);
  const allOn = [...boxes].every(b => b.checked);
  boxes.forEach(b => b.checked = !allOn);
}
</script>
