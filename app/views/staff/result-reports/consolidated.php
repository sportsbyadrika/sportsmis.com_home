<?php
$pageTitle = 'Consolidated Report — ' . $event['name'];
$totMed  = ['gold' => 0, 'silver' => 0, 'bronze' => 0];
foreach (['gold', 'silver', 'bronze'] as $k) {
    $totMed[$k] = (int)($indiv_medals[$k] ?? 0) + (int)($team_medals[$k] ?? 0);
}
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/result-reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-bar-chart-line me-2"></i>Consolidated Report</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <button type="button" class="btn btn-sm btn-outline-secondary ms-auto"
          onclick="window.print()">
    <i class="bi bi-printer me-1"></i>Print
  </button>
</div>

<p class="small text-muted mb-3">
  <i class="bi bi-info-circle me-1"></i>
  Counts are based on <strong>approved</strong> athlete registrations and approved
  team entries on this event. Medals are awarded as the top three per sport-event
  (DNS / DNF / DQ excluded). MQS-qualified counts every athlete whose total score
  reaches at least the configured MQS in any sport-event they&rsquo;re registered for.
</p>

<!-- ── Top tiles ─────────────────────────────────────────────────── -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="sms-card p-3 h-100 text-center">
      <div class="text-muted small text-uppercase mb-1">Total Participants</div>
      <div class="display-6 fw-bold text-primary mb-1"><?= (int)$participants['total'] ?></div>
      <div class="small">
        <span class="badge bg-primary-subtle text-primary-emphasis">M <?= (int)$participants['male'] ?></span>
        <span class="badge bg-danger-subtle text-danger-emphasis">F <?= (int)$participants['female'] ?></span>
        <?php if ((int)$participants['other'] > 0): ?>
          <span class="badge bg-secondary-subtle text-secondary">O <?= (int)$participants['other'] ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="sms-card p-3 h-100 text-center">
      <div class="text-muted small text-uppercase mb-1">Total Sport-Events</div>
      <div class="display-6 fw-bold text-info mb-1"><?= (int)$total_events ?></div>
      <div class="small text-muted">configured on this event</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="sms-card p-3 h-100 text-center">
      <div class="text-muted small text-uppercase mb-1">Total Teams</div>
      <div class="display-6 fw-bold text-success mb-1"><?= (int)$total_teams ?></div>
      <div class="small text-muted">approved team entries</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="sms-card p-3 h-100 text-center">
      <div class="text-muted small text-uppercase mb-1">MQS Qualified</div>
      <div class="display-6 fw-bold text-warning mb-1"><?= (int)$qualified['total'] ?></div>
      <div class="small">
        <span class="badge bg-primary-subtle text-primary-emphasis">M <?= (int)$qualified['male'] ?></span>
        <span class="badge bg-danger-subtle text-danger-emphasis">F <?= (int)$qualified['female'] ?></span>
        <?php if ((int)$qualified['other'] > 0): ?>
          <span class="badge bg-secondary-subtle text-secondary">O <?= (int)$qualified['other'] ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ── Medals ─────────────────────────────────────────────────────── -->
<div class="sms-card p-3 mb-3">
  <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-trophy me-2"></i>Medals</h6>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light text-center">
        <tr>
          <th class="text-start">Category</th>
          <th style="width:120px">Gold</th>
          <th style="width:120px">Silver</th>
          <th style="width:120px">Bronze</th>
          <th style="width:120px" class="text-end">Total</th>
        </tr>
      </thead>
      <tbody class="text-center">
        <tr>
          <td class="text-start fw-medium"><i class="bi bi-person-fill me-1"></i>Individual</td>
          <td><span class="badge bg-warning-subtle text-warning-emphasis fs-6"><?= (int)$indiv_medals['gold'] ?></span></td>
          <td><span class="badge bg-light text-secondary border fs-6"><?= (int)$indiv_medals['silver'] ?></span></td>
          <td><span class="badge bg-warning-subtle text-warning-emphasis fs-6"><?= (int)$indiv_medals['bronze'] ?></span></td>
          <td class="text-end fw-bold"><?= (int)$indiv_medals['gold'] + (int)$indiv_medals['silver'] + (int)$indiv_medals['bronze'] ?></td>
        </tr>
        <tr>
          <td class="text-start fw-medium"><i class="bi bi-people-fill me-1"></i>Team</td>
          <td><span class="badge bg-warning-subtle text-warning-emphasis fs-6"><?= (int)$team_medals['gold'] ?></span></td>
          <td><span class="badge bg-light text-secondary border fs-6"><?= (int)$team_medals['silver'] ?></span></td>
          <td><span class="badge bg-warning-subtle text-warning-emphasis fs-6"><?= (int)$team_medals['bronze'] ?></span></td>
          <td class="text-end fw-bold"><?= (int)$team_medals['gold'] + (int)$team_medals['silver'] + (int)$team_medals['bronze'] ?></td>
        </tr>
      </tbody>
      <tfoot class="table-light text-center">
        <tr>
          <th class="text-start">Grand Total</th>
          <th><?= (int)$totMed['gold']   ?></th>
          <th><?= (int)$totMed['silver'] ?></th>
          <th><?= (int)$totMed['bronze'] ?></th>
          <th class="text-end"><?= (int)$totMed['gold'] + (int)$totMed['silver'] + (int)$totMed['bronze'] ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- ── Per-category participation ─────────────────────────────────── -->
<div class="sms-card p-3 mb-3">
  <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-collection me-2"></i>Participation by Sport Category</h6>
  <?php if (empty($by_category)): ?>
    <p class="text-muted small mb-0">No approved registrations under any sport category yet.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0 text-center">
        <thead class="table-light">
          <tr>
            <th rowspan="2" class="text-start align-middle">Category</th>
            <th colspan="3" class="border-start">Total Participants</th>
            <th colspan="3" class="border-start">Qualified (MQS)</th>
            <th colspan="3" class="border-start">Not Qualified</th>
          </tr>
          <tr>
            <th class="border-start small">M</th>
            <th class="small">F</th>
            <th class="small fw-bold">Total</th>
            <th class="border-start small">M</th>
            <th class="small">F</th>
            <th class="small fw-bold">Total</th>
            <th class="border-start small">M</th>
            <th class="small">F</th>
            <th class="small fw-bold">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $dash = '<span class="text-muted">·</span>';
            $sumM=$sumF=$sumT=$qM=$qF=$qT=$nM=$nF=$nT=0;
          ?>
          <?php foreach ($by_category as $c):
            $sumM += (int)$c['male'];   $sumF += (int)$c['female'];  $sumT += (int)$c['total'];
            $qM   += (int)$c['q_male']; $qF   += (int)$c['q_female']; $qT  += (int)$c['q_total'];
            $nM   += (int)$c['nq_male'];$nF   += (int)$c['nq_female'];$nT += (int)$c['nq_total'];
          ?>
            <tr>
              <td class="text-start fw-medium">
                <?= e($c['name']) ?>
                <?php if (!empty($c['abbr'])): ?>
                  <span class="text-muted small">(<?= e($c['abbr']) ?>)</span>
                <?php endif; ?>
              </td>
              <td class="border-start"><?= (int)$c['male']   ?: $dash ?></td>
              <td><?= (int)$c['female'] ?: $dash ?></td>
              <td class="fw-bold"><?= (int)$c['total'] ?></td>
              <td class="border-start text-success-emphasis"><?= (int)$c['q_male']   ?: $dash ?></td>
              <td class="text-success-emphasis"><?= (int)$c['q_female'] ?: $dash ?></td>
              <td class="fw-bold text-success-emphasis"><?= (int)$c['q_total'] ?></td>
              <td class="border-start text-muted"><?= (int)$c['nq_male']   ?: $dash ?></td>
              <td class="text-muted"><?= (int)$c['nq_female'] ?: $dash ?></td>
              <td class="fw-bold text-muted"><?= (int)$c['nq_total'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light">
          <tr>
            <th class="text-start">All Categories</th>
            <th class="border-start"><?= $sumM ?></th>
            <th><?= $sumF ?></th>
            <th><?= $sumT ?></th>
            <th class="border-start text-success-emphasis"><?= $qM ?></th>
            <th class="text-success-emphasis"><?= $qF ?></th>
            <th class="text-success-emphasis"><?= $qT ?></th>
            <th class="border-start text-muted"><?= $nM ?></th>
            <th class="text-muted"><?= $nF ?></th>
            <th class="text-muted"><?= $nT ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
    <p class="text-muted small mt-2 mb-0">
      An athlete registered for sport-events in multiple categories is counted
      once per category &mdash; the row totals can therefore exceed the headline
      <em>Total Participants</em> tile above. <em>Qualified</em> means the athlete&rsquo;s
      best total in that category reached the MQS of at least one sport-event
      they entered; sport-events without an MQS configured can&rsquo;t produce
      qualifiers and feed straight into <em>Not Qualified</em>.
    </p>
  <?php endif; ?>
</div>

<style>
  @media print {
    @page { size: A4 portrait; margin: 14mm 12mm; }
    body { background: #fff !important; }
    .btn, .sms-sidebar, .sms-topbar, nav, header.sms-topbar { display: none !important; }
    .sms-card { border: 1px solid #ccc !important; box-shadow: none !important; }
    .display-6 { font-size: 22pt !important; }
  }
</style>
