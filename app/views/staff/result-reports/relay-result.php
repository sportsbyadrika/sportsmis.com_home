<?php
$pageTitle = 'Relay Result — ' . $event['name'];
// Format a decimal like 98.00 as "98" but keep meaningful fractions
// (96.5 → "96.5", 96.75 → "96.75"). Mirrors the formatter used on
// the scoring lanes page so the two surfaces look identical.
$fmtScore = function ($v): string {
    if ($v === null || $v === '') return '';
    $f = (float)$v;
    if ($f == (int)$f) return (string)(int)$f;
    return rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');
};
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/result-reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-bullseye me-2"></i>Relay Result</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
</div>

<form method="GET" class="sms-card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-6">
      <label class="form-label small mb-1">Relay</label>
      <select name="relay_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="0">— Select a relay —</option>
        <?php foreach ($relays as $r):
          $label = 'Relay ' . ($r['relay_number'] ?? '');
          $bits  = [];
          if (!empty($r['relay_date'])) $bits[] = formatDate($r['relay_date'], 'd M Y');
          if (!empty($r['match_time'])) $bits[] = substr($r['match_time'], 0, 5);
          if ($bits) $label .= '  (' . implode(' · ', $bits) . ')';
        ?>
          <option value="<?= (int)$r['id'] ?>" <?= (int)$selected === (int)$r['id'] ? 'selected' : '' ?>>
            <?= e($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Show</button>
      <a href="/event-staff/result-reports/relay-result" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
    </div>
  </div>
</form>

<?php if (!$relay): ?>
  <div class="sms-empty-state">
    <i class="bi bi-bullseye"></i>
    <h5>Select a relay</h5>
    <p>Pick a relay from the dropdown above to see lane-wise results.</p>
  </div>
<?php else: ?>
  <div class="sms-card p-3 mb-3">
    <h6 class="fw-semibold border-bottom pb-2 mb-2">
      Relay <?= e($relay['relay_number']) ?>
    </h6>
    <div class="row g-2 small">
      <div class="col-md-3"><span class="text-muted">Date</span><br>
        <strong><?= !empty($relay['relay_date']) ? e(formatDate($relay['relay_date'], 'd M Y')) : '—' ?></strong>
      </div>
      <div class="col-md-3"><span class="text-muted">Match Time</span><br>
        <strong><?= !empty($relay['match_time']) ? e(substr($relay['match_time'],0,5)) : '—' ?></strong>
      </div>
      <div class="col-md-3"><span class="text-muted">Reporting Time</span><br>
        <strong><?= !empty($relay['reporting_time']) ? e(substr($relay['reporting_time'],0,5)) : '—' ?></strong>
      </div>
      <div class="col-md-3"><span class="text-muted">Venue / Range</span><br>
        <strong><?= e($relay['venue_name'] ?? '') ?></strong>
        <?php if (!empty($relay['range_name'])): ?>
          <span class="text-muted">→ <?= e($relay['range_name']) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="sms-card p-3">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h6 class="fw-semibold mb-0"><i class="bi bi-list-ol me-2"></i>Lane Results</h6>
      <span class="badge bg-secondary"><?= count($lanes) ?> lane<?= count($lanes) === 1 ? '' : 's' ?></span>
    </div>
    <?php if (empty($lanes)): ?>
      <p class="text-muted small mb-0 text-center py-3">No lanes configured on this relay.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:50px">Lane</th>
            <th style="width:60px">Photo</th>
            <th style="width:90px">Comp. No.</th>
            <th>Name of Athlete</th>
            <th>Unit</th>
            <th style="width:90px">Category</th>
            <th>Score (per series)</th>
            <th class="text-end" style="width:80px">Penalty</th>
            <th class="text-end" style="width:80px">No. of 10s</th>
            <th class="text-end" style="width:90px">Grand Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lanes as $l):
            $hasAthlete  = !empty($l['athlete_name']);
            $seriesPipe  = '';
            if (!empty($l['series_totals_csv'])) {
                $parts = array_filter(array_map('trim', explode(',', (string)$l['series_totals_csv'])), 'strlen');
                $seriesPipe = implode(' | ', array_map($fmtScore, $parts));
            }
            $compNo = (int)($l['competitor_number'] ?: $l['score_competitor_number'] ?? 0);
            $hasScore = $l['score_total'] !== null;
          ?>
            <tr>
              <td class="text-center fw-bold"><?= (int)$l['lane_number'] ?></td>
              <td class="text-center">
                <?php if (!empty($l['passport_photo'])): ?>
                  <img src="<?= e($l['passport_photo']) ?>" width="36" height="36"
                       class="rounded-circle" style="object-fit:cover">
                <?php elseif ($hasAthlete): ?>
                  <div class="sms-avatar sms-avatar-sm"><?= e(substr($l['athlete_name'] ?? '?', 0, 1)) ?></div>
                <?php else: ?>
                  <div class="rounded-circle bg-light text-muted d-inline-flex align-items-center justify-content-center"
                       style="width:36px;height:36px;font-size:.85rem">&nbsp;</div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($compNo): ?>
                  <code class="fw-bold"><?= str_pad((string)$compNo, 4, '0', STR_PAD_LEFT) ?></code>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="fw-medium"><?= e($l['athlete_name'] ?: '—') ?></td>
              <td class="small"><?= e($l['unit_name'] ?: '—') ?></td>
              <td class="small text-center"><?= e($l['category'] ?: ($l['default_category'] ?: '—')) ?></td>
              <td class="small font-monospace">
                <?= $seriesPipe !== '' ? e($seriesPipe) : '<span class="text-muted">—</span>' ?>
              </td>
              <td class="text-end small">
                <?= ($l['score_penalty'] !== null && (float)$l['score_penalty'] > 0)
                      ? e($fmtScore($l['score_penalty']))
                      : '<span class="text-muted">—</span>' ?>
              </td>
              <td class="text-end small">
                <?= !empty($l['tens_count'])
                      ? (int)$l['tens_count']
                      : '<span class="text-muted">—</span>' ?>
              </td>
              <td class="text-end fw-bold">
                <?= $hasScore ? number_format((float)$l['score_total'], 2)
                              : '<span class="text-muted">—</span>' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
