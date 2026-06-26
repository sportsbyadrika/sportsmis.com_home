<?php
$pageTitle = 'Category — Event Top 3 — ' . $event['name'];
$selectedName = '';
foreach ($categories as $c) {
    if ((int)$c['id'] === (int)$selected_category) { $selectedName = (string)$c['name']; break; }
}
// Delegate to the global helper so the label honours this event's
// gender_label_set switch ('standard' Male/Female vs 'cbse' Boys/Girls).
$genderLabel = fn(string $g): string => genderLabel($g, $event);
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/result-reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-podium me-2"></i>Category — Event Top 3</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <?php if (!empty($sport_events)): ?>
    <div class="ms-auto d-flex gap-2">
      <a class="btn btn-sm btn-success"
         href="/event-staff/result-reports/category-event-top3/live?category_id=<?= (int)$selected_category ?>"
         target="_blank" rel="noopener"
         title="Slide-show with a green-screen background for live streaming">
        <i class="bi bi-broadcast me-1"></i>Live Screen
      </a>
      <a class="btn btn-sm btn-outline-secondary"
         href="/event-staff/result-reports/category-event-top3/print?category_id=<?= (int)$selected_category ?>"
         target="_blank" rel="noopener">
        <i class="bi bi-printer me-1"></i>Print
      </a>
    </div>
  <?php endif; ?>
</div>

<p class="small text-muted mb-3">
  <i class="bi bi-info-circle me-1"></i>
  Pick an Event Category; the report lists every sport-event in it with
  its Gold / Silver / Bronze. Print places each sport-event on a fresh page.
  DNS / DNF / DQ entries are excluded from the ranking.
</p>

<!-- ── Category picker (hidden on print) ─────────────────────────── -->
<form method="GET" class="sms-card p-3 mb-3 no-print"
      action="/event-staff/result-reports/category-event-top3">
  <div class="row g-2 align-items-end">
    <div class="col-md-6">
      <label class="form-label small mb-1">Event Category <span class="text-danger">*</span></label>
      <select name="category_id" class="form-select form-select-sm" required>
        <option value="">— Select a category —</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>"
                  <?= (int)$selected_category === (int)$c['id'] ? 'selected' : '' ?>>
            <?= e($c['name']) ?>
            <?php if (!empty($c['abbreviation'])): ?> (<?= e($c['abbreviation']) ?>)<?php endif; ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Show</button>
      <a href="/event-staff/result-reports/category-event-top3"
         class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
    </div>
  </div>
</form>

<?php if ((int)$selected_category === 0): ?>
  <div class="sms-card p-4 text-muted small text-center no-print">
    Select an Event Category above to load the per-event Top 3 tables.
  </div>
<?php elseif (empty($sport_events)): ?>
  <div class="sms-card p-4 text-muted small text-center">
    No sport-events with scored athletes were found for
    <strong><?= e($selectedName) ?></strong>.
  </div>
<?php else: ?>

  <!-- Banner on the printed page only — picks up the event + category names. -->
  <div class="print-banner d-none">
    <h2 class="mb-0"><?= e($event['name']) ?></h2>
    <div class="text-muted small mt-1">
      Category — Event Top 3 &middot; <strong><?= e($selectedName) ?></strong>
      &middot; <?= count($sport_events) ?> sport-event<?= count($sport_events) === 1 ? '' : 's' ?>
    </div>
  </div>

  <?php foreach ($sport_events as $i => $ev): ?>
    <div class="sms-card p-3 mb-3 event-card">
      <div class="d-flex flex-wrap align-items-baseline gap-2 border-bottom pb-2 mb-2">
        <?php if (!empty($ev['event_code'])): ?>
          <code class="fs-6"><?= e($ev['event_code']) ?></code>
        <?php endif; ?>
        <h6 class="fw-bold mb-0"><?= e($ev['sport_event'] ?: '—') ?></h6>
        <?php if (!empty($ev['age_category'])): ?>
          <span class="badge bg-secondary-subtle text-secondary-emphasis">
            <?= e($ev['age_category']) ?>
          </span>
        <?php endif; ?>
        <?php if (!empty($ev['gender'])): ?>
          <span class="badge bg-secondary-subtle text-secondary-emphasis">
            <?= e($genderLabel($ev['gender'])) ?>
          </span>
        <?php endif; ?>
        <span class="text-muted small ms-auto">
          <?= count($ev['top3']) ?> medalist<?= count($ev['top3']) === 1 ? '' : 's' ?>
        </span>
      </div>

      <?php if (empty($ev['top3'])): ?>
        <p class="small text-muted mb-0">No scored athletes recorded yet for this sport-event.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light text-center">
              <tr>
                <th style="width:90px">Rank</th>
                <th style="width:90px">Comp. No.</th>
                <th class="text-start">Name of Athlete</th>
                <th class="text-start">Unit / Club</th>
                <th style="width:110px" class="text-end">Score</th>
                <th style="width:80px" class="text-center">No. of 10x</th>
                <th style="width:110px" class="text-center">Medal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ev['top3'] as $rk => $a):
                $rank = $rk + 1;
                $medalRow = $rank === 1 ? 'table-warning'
                          : ($rank === 2 ? 'table-secondary'
                          : ($rank === 3 ? 'table-warning' : ''));
                $medalLabel = $rank === 1 ? 'Gold'
                            : ($rank === 2 ? 'Silver'
                            : ($rank === 3 ? 'Bronze' : ''));
                $medalBadge = $rank === 1 ? 'bg-warning-subtle text-warning-emphasis'
                            : ($rank === 2 ? 'bg-light text-secondary border'
                            : ($rank === 3 ? 'bg-warning-subtle text-warning-emphasis'
                            : ''));
              ?>
                <tr class="<?= $medalRow ?>">
                  <td class="text-center fw-bold"><?= $rank ?></td>
                  <td class="text-center fw-bold">
                    <?= $a['competitor_number']
                          ? '#' . str_pad((string)(int)$a['competitor_number'], 4, '0', STR_PAD_LEFT)
                          : '—' ?>
                  </td>
                  <td class="fw-medium"><?= e($a['athlete_name']) ?></td>
                  <td class="small">
                    <?= e($a['unit_name'] ?: '—') ?>
                    <?php if (!empty($a['unit_address'])): ?>
                      <div class="text-muted small"><?= e($a['unit_address']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="text-end fw-bold"><?= (int)round((float)$a['grand_total']) ?></td>
                  <td class="text-center"><?= (int)$a['tens_count'] ?></td>
                  <td class="text-center">
                    <span class="badge <?= $medalBadge ?>"><?= $medalLabel ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<style>
  /* Print: one sport-event per page, hide chrome. */
  @media print {
    @page { size: A4 portrait; margin: 14mm 12mm; }
    .no-print { display: none !important; }
    .print-banner { display: block !important; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 2px solid #333; }
    .sms-card { border: 1px solid #ccc !important; box-shadow: none !important; padding: 12px !important; }
    .event-card { page-break-after: always; page-break-inside: avoid; }
    .event-card:last-child { page-break-after: auto; }
    body { background: #fff !important; }
    table.table-bordered { font-size: 10.5pt; }
    .table-warning   { background-color: #fff3cd !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .table-secondary { background-color: #e9ecef !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>
