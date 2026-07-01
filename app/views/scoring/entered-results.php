<?php
$pageTitle = 'Entered Results';
// Group the flat result rows by category for a participant-wise layout.
$byCat = [];
foreach ($results as $r) {
    $key = (string)($r['category_name'] ?? '—');
    $byCat[$key][] = $r;
}
// Score formatter — drop the decimal tail for whole numbers.
$fmt = static function ($v): string {
    if ($v === null || $v === '') return '';
    $f = (float)$v;
    return ($f == floor($f)) ? (string)(int)$f : rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');
};
?>

<style>
@media print {
  .no-print { display: none !important; }
  .sms-card { box-shadow: none !important; border: 0 !important; }
}
</style>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/scoring" class="btn btn-sm btn-outline-secondary no-print">
    <i class="bi bi-arrow-left me-1"></i>Back to Relays
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>Entered Results</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?> · <code><?= e($event['event_code'] ?? '') ?></code></span>
  <button type="button" class="btn btn-sm btn-outline-primary ms-auto no-print" onclick="window.print()">
    <i class="bi bi-printer me-1"></i>Print
  </button>
</div>

<?= flashBag() ?>

<!-- Category filter -->
<form method="GET" action="/event-staff/scoring/results" class="sms-card p-3 mb-3 no-print">
  <div class="row g-2 align-items-end">
    <div class="col-md-6">
      <label class="form-label small mb-1">Event Category</label>
      <select name="category_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="0">All categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$selected_category === (int)$c['id'] ? 'selected' : '' ?>>
            <?= e($c['name']) ?><?= !empty($c['abbreviation']) ? ' (' . e($c['abbreviation']) . ')' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6 text-muted small">
      <i class="bi bi-info-circle me-1"></i>
      Only <strong>saved</strong> / <strong>final</strong> scores are shown, one row per participant.
    </div>
  </div>
</form>

<?php if (empty($results)): ?>
  <div class="sms-card p-4 text-muted small text-center">
    No results have been entered yet<?= $selected_category ? ' for the selected category' : '' ?>.
  </div>
<?php else: ?>
  <?php foreach ($byCat as $catName => $rows): ?>
    <div class="sms-card p-3 mb-3">
      <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-2">
        <strong><?= e($catName) ?></strong>
        <span class="badge bg-secondary-subtle text-secondary-emphasis ms-auto">
          <?= count($rows) ?> participant<?= count($rows) === 1 ? '' : 's' ?>
        </span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:50px">Sl</th>
              <th style="width:90px">Comp. No.</th>
              <th>Participant</th>
              <th>Unit</th>
              <th>Event</th>
              <th>Series</th>
              <th class="text-end">Total</th>
              <th class="text-center">10s</th>
              <th class="text-end">Penalty</th>
              <th>Remarks</th>
              <th>Status</th>
              <th class="small text-muted">Relay / Lane</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i => $r):
              $series = array_values(array_filter(array_map('trim', explode(',', (string)($r['series_subs_csv'] ?? '')))));
              $pen    = (float)($r['total_penalty'] ?? 0);
              $isFinal = ($r['lane_status'] ?? '') === 'final';
            ?>
              <tr>
                <td class="text-center"><?= $i + 1 ?></td>
                <td class="text-center fw-bold"><?= $r['competitor_number'] !== null ? '#' . (int)$r['competitor_number'] : '—' ?></td>
                <td><?= e($r['athlete_name'] ?? '—') ?></td>
                <td class="small text-muted"><?= e($r['unit_name'] ?? '—') ?></td>
                <td class="small">
                  <?= e($r['sport_event_name'] ?? '—') ?>
                  <?php if (!empty($r['event_code'])): ?><code class="ms-1 small"><?= e($r['event_code']) ?></code><?php endif; ?>
                </td>
                <td class="small text-muted"><?= $series ? e(implode(' · ', array_map($fmt, $series))) : '—' ?></td>
                <td class="text-end fw-bold"><?= $r['grand_total'] !== null ? e($fmt($r['grand_total'])) : '—' ?></td>
                <td class="text-center"><?= $r['inner_ten_count'] !== null ? (int)$r['inner_ten_count'] : '—' ?></td>
                <td class="text-end"><?= $pen > 0 ? e($fmt($pen)) : '—' ?></td>
                <td class="small"><?= !empty($r['remarks']) ? '<span class="badge bg-warning-subtle text-warning-emphasis">' . e(strtoupper($r['remarks'])) . '</span>' : '—' ?></td>
                <td>
                  <span class="badge <?= $isFinal ? 'bg-success' : 'bg-info-subtle text-info-emphasis' ?>">
                    <?= $isFinal ? 'Final' : 'Saved' ?>
                  </span>
                </td>
                <td class="small text-muted">
                  <?= $r['relay_number'] !== null ? 'R' . (int)$r['relay_number'] : '—' ?>
                  <?= $r['lane_number'] !== null ? ' / L' . (int)$r['lane_number'] : '' ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
