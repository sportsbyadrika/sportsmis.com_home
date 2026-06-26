<?php
$pageTitle = 'Registration Statistics — ' . $event['name'];
// Labels track this event's gender_label_set switch ('standard'
// Male/Female vs 'cbse' Boys/Girls). 'other' has no CBSE analogue, so
// it stays "Other" for both sets.
$genders = [
    'male'   => genderLabel('male',   $event),
    'female' => genderLabel('female', $event),
    'mixed'  => genderLabel('mixed',  $event),
    'other'  => 'Other',
];
$unit_meta = $unit_meta ?? [];

// Preserve the active filters on the CSV download links so the
// downloaded file matches what the user is looking at on screen.
$csvQs = http_build_query(array_filter([
    'sport_id' => $sport_filter ?: null,
    'category' => $category_filter ?: null,
], fn($v) => $v !== null && $v !== ''));
$csvUrl = function (string $table) use ($eventHash, $csvQs) {
    $base = '/institution/events/' . e($eventHash) . '/reports/registration-stats.csv?table=' . urlencode($table);
    return $base . ($csvQs ? '&' . $csvQs : '');
};
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-pie-chart me-2"></i>Registration Statistics</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <button class="btn btn-sm btn-outline-secondary ms-auto" type="button" onclick="window.print()">
    <i class="bi bi-printer me-1"></i>Print
  </button>
</div>
<p class="small text-muted mb-3"><i class="bi bi-info-circle me-1"></i>Counts are per <em>approved</em> registration — one row per (athlete, sport-event) line.</p>

<form method="GET" class="sms-card p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label small mb-1">Sport</label>
      <select name="sport_id" class="form-select form-select-sm">
        <option value="0">All sports</option>
        <?php foreach ($sports as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= (int)$sport_filter === (int)$s['id'] ? 'selected' : '' ?>>
            <?= e($s['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label small mb-1">Category</label>
      <select name="category" class="form-select form-select-sm">
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= e($c['name']) ?>" <?= $category_filter === $c['name'] ? 'selected' : '' ?>>
            <?= e($c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4 d-flex gap-2">
      <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Apply</button>
      <a href="/institution/events/<?= e($eventHash) ?>/reports/registration-stats" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i> Reset</a>
    </div>
  </div>
</form>

<!-- Pivot 1: Sport Category × Gender -->
<div class="sms-card p-4 mb-4">
  <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-collection me-2"></i>By Sport Category</h6>
  <?php if (empty($by_category)): ?>
    <p class="text-muted small mb-0">No approved registrations match the filters.</p>
  <?php else: ?>
  <p class="small text-muted mb-2">Count of <strong>unique athletes</strong> per category &times; gender — an athlete entered in several events of the same category is counted once.</p>
  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle mb-0">
      <thead class="table-light text-center">
        <tr>
          <th class="text-start">Category</th>
          <?php foreach ($genders as $key => $label): ?>
            <th><?= e($label) ?></th>
          <?php endforeach; ?>
          <th>Total</th>
        </tr>
      </thead>
      <tbody class="text-center">
        <?php
          $colTotals = ['male'=>0,'female'=>0,'mixed'=>0,'other'=>0,'total'=>0];
        ?>
        <?php foreach ($by_category as $cat => $counts): ?>
          <tr>
            <td class="text-start"><?= e($cat) ?></td>
            <?php foreach ($genders as $key => $label):
              $v = (int)($counts[$key] ?? 0);
              $colTotals[$key] += $v;
            ?>
              <td><?= $v > 0 ? $v : '<span class="text-muted">·</span>' ?></td>
            <?php endforeach; ?>
            <?php $colTotals['total'] += (int)$counts['total']; ?>
            <td class="fw-bold"><?= (int)$counts['total'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light text-center">
        <tr>
          <th class="text-end">Grand Total</th>
          <?php foreach ($genders as $key => $label): ?>
            <th><?= $colTotals[$key] ?></th>
          <?php endforeach; ?>
          <th><?= $colTotals['total'] ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Pivot 2: Sport-Event under Category × Gender -->
<div class="sms-card p-4 mb-4">
  <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-trophy me-2"></i>By Sport-Event (grouped by Category)</h6>
  <?php if (empty($by_event)): ?>
    <p class="text-muted small mb-0">No approved registrations match the filters.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle mb-0">
      <thead class="table-light text-center">
        <tr>
          <th class="text-start">Category</th>
          <th>Sl. No</th>
          <th class="text-start">Sport Event</th>
          <?php foreach ($genders as $key => $label): ?>
            <th><?= e($label) ?></th>
          <?php endforeach; ?>
          <th>Total</th>
        </tr>
      </thead>
      <tbody class="text-center">
        <?php
          $grand2 = ['male'=>0,'female'=>0,'mixed'=>0,'other'=>0,'total'=>0];
          foreach ($by_event as $cat => $rows):
            $catTotals = ['male'=>0,'female'=>0,'mixed'=>0,'other'=>0,'total'=>0];
            $sl = 0;
            foreach ($rows as $r):
              $sl++;
        ?>
          <tr>
            <td class="text-start"><?= $sl === 1 ? e($cat) : '' ?></td>
            <td><?= $sl ?></td>
            <td class="text-start"><?= e($r['event_name']) ?></td>
            <?php foreach ($genders as $key => $label):
              $v = (int)($r[$key] ?? 0);
              $catTotals[$key] += $v;
              $grand2[$key]    += $v;
            ?>
              <td><?= $v > 0 ? $v : '<span class="text-muted">·</span>' ?></td>
            <?php endforeach; ?>
            <td class="fw-bold"><?= (int)$r['total'] ?></td>
          </tr>
        <?php
            endforeach;
            $catTotals['total'] = array_sum([$catTotals['male'],$catTotals['female'],$catTotals['mixed'],$catTotals['other']]);
            $grand2['total']   += $catTotals['total'];
        ?>
          <tr class="table-secondary">
            <th class="text-end" colspan="3"><?= e($cat) ?> Subtotal</th>
            <?php foreach ($genders as $key => $label): ?>
              <th><?= $catTotals[$key] ?></th>
            <?php endforeach; ?>
            <th><?= $catTotals['total'] ?></th>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light text-center">
        <tr>
          <th class="text-end" colspan="3">Grand Total</th>
          <?php foreach ($genders as $key => $label): ?>
            <th><?= $grand2[$key] ?></th>
          <?php endforeach; ?>
          <th><?= $grand2['total'] ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Pivot: Unit/Club/Institution × Event Category -->
<div class="sms-card p-4 mb-4">
  <div class="d-flex align-items-center border-bottom pb-2 mb-3">
    <h6 class="fw-semibold mb-0">
      <i class="bi bi-grid-3x3-gap me-2"></i>Unit / Club / Institution &times; Event Category
    </h6>
    <?php if (!empty($by_unit_category) && !empty($pivot_categories)): ?>
      <a class="btn btn-sm btn-outline-success ms-auto"
         href="<?= $csvUrl('unit_category') ?>">
        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Download CSV
      </a>
    <?php endif; ?>
  </div>
  <?php if (empty($by_unit_category) || empty($pivot_categories)): ?>
    <p class="text-muted small mb-0">No approved registrations match the filters.</p>
  <?php else: ?>
  <p class="small text-muted mb-2">Count of <strong>unique athletes</strong> per Unit (rows) in each Event Category (columns) — an athlete entered in several events of the same category is counted once. Row- and column-wise totals included.</p>
  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle mb-0">
      <thead class="table-light text-center">
        <tr>
          <th class="text-start">Unit Code</th>
          <th class="text-start">Unit Name</th>
          <?php foreach ($pivot_categories as $cat): ?>
            <th><?= e($cat) ?></th>
          <?php endforeach; ?>
          <th>Total</th>
        </tr>
      </thead>
      <tbody class="text-center">
        <?php
          $colTot = array_fill_keys($pivot_categories, 0);
          $grandPivot = 0;
          foreach ($by_unit_category as $unit => $catCounts):
            $rowTot = 0;
            $meta = $unit_meta[$unit] ?? ['code' => $unit, 'name' => ''];
        ?>
          <tr>
            <td class="text-start"><?= e($meta['code']) ?></td>
            <td class="text-start small text-muted"><?= e($meta['name']) ?: '<span class="text-muted">—</span>' ?></td>
            <?php foreach ($pivot_categories as $cat):
              $v = (int)($catCounts[$cat] ?? 0);
              $rowTot      += $v;
              $colTot[$cat] += $v;
            ?>
              <td><?= $v > 0 ? $v : '<span class="text-muted">·</span>' ?></td>
            <?php endforeach; ?>
            <td class="fw-bold"><?= $rowTot ?></td>
          </tr>
        <?php
            $grandPivot += $rowTot;
          endforeach;
        ?>
      </tbody>
      <tfoot class="table-light text-center">
        <tr>
          <th class="text-end" colspan="2">Grand Total</th>
          <?php foreach ($pivot_categories as $cat): ?>
            <th><?= $colTot[$cat] ?></th>
          <?php endforeach; ?>
          <th><?= $grandPivot ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Pivot 3: Unit/Club/Institution × Gender -->
<div class="sms-card p-4 mb-4">
  <div class="d-flex align-items-center border-bottom pb-2 mb-3">
    <h6 class="fw-semibold mb-0"><i class="bi bi-building me-2"></i>By Unit / Club / Institution</h6>
    <?php if (!empty($by_unit)): ?>
      <a class="btn btn-sm btn-outline-success ms-auto"
         href="<?= $csvUrl('by_unit') ?>">
        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Download CSV
      </a>
    <?php endif; ?>
  </div>
  <?php if (empty($by_unit)): ?>
    <p class="text-muted small mb-0">No approved registrations match the filters.</p>
  <?php else: ?>
  <p class="small text-muted mb-2">Count of <strong>unique athletes</strong> per Unit &times; gender — an athlete registered for several events or categories under the same unit is counted once.</p>
  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle mb-0">
      <thead class="table-light text-center">
        <tr>
          <th class="text-start">Unit Code</th>
          <th class="text-start">Unit Name</th>
          <?php foreach ($genders as $key => $label): ?>
            <th><?= e($label) ?></th>
          <?php endforeach; ?>
          <th>Total</th>
        </tr>
      </thead>
      <tbody class="text-center">
        <?php $colTotalsU = ['male'=>0,'female'=>0,'mixed'=>0,'other'=>0,'total'=>0]; ?>
        <?php foreach ($by_unit as $unit => $counts):
          $meta = $unit_meta[$unit] ?? ['code' => $unit, 'name' => ''];
        ?>
          <tr>
            <td class="text-start"><?= e($meta['code']) ?></td>
            <td class="text-start small text-muted"><?= e($meta['name']) ?: '<span class="text-muted">—</span>' ?></td>
            <?php foreach ($genders as $key => $label):
              $v = (int)($counts[$key] ?? 0);
              $colTotalsU[$key] += $v;
            ?>
              <td><?= $v > 0 ? $v : '<span class="text-muted">·</span>' ?></td>
            <?php endforeach; ?>
            <?php $colTotalsU['total'] += (int)$counts['total']; ?>
            <td class="fw-bold"><?= (int)$counts['total'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light text-center">
        <tr>
          <th class="text-end" colspan="2">Grand Total</th>
          <?php foreach ($genders as $key => $label): ?>
            <th><?= $colTotalsU[$key] ?></th>
          <?php endforeach; ?>
          <th><?= $colTotalsU['total'] ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Pivot 4: Unit × Sport-Event × Gender -->
<div class="sms-card p-4 mb-4">
  <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-diagram-3 me-2"></i>By Unit × Sport-Event</h6>
  <?php if (empty($by_unit_event)): ?>
    <p class="text-muted small mb-0">No approved registrations match the filters.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle mb-0">
      <thead class="table-light text-center">
        <tr>
          <th class="text-start">Unit Code</th>
          <th class="text-start">Unit Name</th>
          <th>Sl. No</th>
          <th class="text-start">Sport Event</th>
          <th class="text-start">Category</th>
          <?php foreach ($genders as $key => $label): ?>
            <th><?= e($label) ?></th>
          <?php endforeach; ?>
          <th>Total</th>
        </tr>
      </thead>
      <tbody class="text-center">
        <?php
          $grand4 = ['male'=>0,'female'=>0,'mixed'=>0,'other'=>0,'total'=>0];
          foreach ($by_unit_event as $unit => $rows):
            $unitTotals = ['male'=>0,'female'=>0,'mixed'=>0,'other'=>0,'total'=>0];
            $meta = $unit_meta[$unit] ?? ['code' => $unit, 'name' => ''];
            $sl = 0;
            foreach ($rows as $r):
              $sl++;
        ?>
          <tr>
            <td class="text-start"><?= $sl === 1 ? e($meta['code']) : '' ?></td>
            <td class="text-start small text-muted"><?= $sl === 1 ? (e($meta['name']) ?: '<span class="text-muted">—</span>') : '' ?></td>
            <td><?= $sl ?></td>
            <td class="text-start"><?= e($r['event_name']) ?></td>
            <td class="text-start small text-muted"><?= e($r['category']) ?></td>
            <?php foreach ($genders as $key => $label):
              $v = (int)($r[$key] ?? 0);
              $unitTotals[$key] += $v;
              $grand4[$key]     += $v;
            ?>
              <td><?= $v > 0 ? $v : '<span class="text-muted">·</span>' ?></td>
            <?php endforeach; ?>
            <td class="fw-bold"><?= (int)$r['total'] ?></td>
          </tr>
        <?php
            endforeach;
            $unitTotals['total'] = $unitTotals['male']+$unitTotals['female']+$unitTotals['mixed']+$unitTotals['other'];
            $grand4['total']    += $unitTotals['total'];
        ?>
          <tr class="table-secondary">
            <th class="text-end" colspan="5"><?= e($meta['code']) ?> Subtotal</th>
            <?php foreach ($genders as $key => $label): ?>
              <th><?= $unitTotals[$key] ?></th>
            <?php endforeach; ?>
            <th><?= $unitTotals['total'] ?></th>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light text-center">
        <tr>
          <th class="text-end" colspan="5">Grand Total</th>
          <?php foreach ($genders as $key => $label): ?>
            <th><?= $grand4[$key] ?></th>
          <?php endforeach; ?>
          <th><?= $grand4['total'] ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>
</div>
