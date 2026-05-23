<?php
$pageTitle = 'Medal Report — ' . $event['name'];
$fmtScore = function ($v): string {
    if ($v === null || $v === '') return '';
    $f = (float)$v;
    if ($f == (int)$f) return (string)(int)$f;
    return rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');
};
$compNo = function ($n) {
    $n = (int)$n;
    return $n > 0 ? str_pad((string)$n, 4, '0', STR_PAD_LEFT) : '';
};
$hasAnyData = !empty($unit_ranked) || !empty($by_category_events) || !empty($by_category_top);
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/result-reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-award me-2"></i>Medal Report</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <?php if ($hasAnyData): ?>
    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="printMedalReport()">
      <i class="bi bi-printer me-1"></i>Print / PDF
    </button>
  <?php endif; ?>
</div>

<div class="sms-card p-3 mb-3 small">
  <strong>Points</strong> —
  Individual: Gold <?= (int)($points['indiv'][1] ?? 0) ?> ·
  Silver <?= (int)($points['indiv'][2] ?? 0) ?> ·
  Bronze <?= (int)($points['indiv'][3] ?? 0) ?> ·
  Team: Gold <?= (int)($points['team'][1] ?? 0) ?> ·
  Silver <?= (int)($points['team'][2] ?? 0) ?> ·
  Bronze <?= (int)($points['team'][3] ?? 0) ?>.
  <span class="text-muted">Configurable from the event admin's Medal Points panel.</span>
</div>

<?php if (!$hasAnyData): ?>
  <div class="sms-empty-state">
    <i class="bi bi-info-circle"></i>
    <h5>No Medal Data Yet</h5>
    <p>No saved or final scores are available on this event. Run the scoring first, then this report will populate.</p>
  </div>
<?php else: ?>

<!-- Panel A: Unit-wise points -->
<div class="sms-card p-3 mb-4">
  <h6 class="fw-semibold border-bottom pb-2 mb-2"><i class="bi bi-buildings me-2"></i>Unit-wise Points &amp; Rank</h6>
  <?php if (empty($unit_ranked)): ?>
    <p class="text-muted small mb-0">No medal-bearing results yet.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle mb-0">
      <thead class="table-light text-center">
        <tr>
          <th style="width:60px">Rank</th>
          <th class="text-start">Unit</th>
          <th class="text-start">Address</th>
          <th style="width:130px">Individual</th>
          <th style="width:120px">Team</th>
          <th style="width:130px" class="text-end">Grand Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($unit_ranked as $u): ?>
          <tr>
            <td class="text-center fw-bold"><?= (int)$u['rank'] ?></td>
            <td class="fw-medium"><?= e($u['name'] ?: '—') ?></td>
            <td class="small text-muted"><?= e($u['address']) ?: '—' ?></td>
            <td class="text-center"><?= (int)$u['indiv'] ?></td>
            <td class="text-center"><?= (int)$u['team'] ?></td>
            <td class="text-end fw-bold"><?= (int)$u['grand'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Panel B: per-category event medalists -->
<div class="sms-card p-3 mb-4">
  <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-trophy me-2"></i>Event Category &times; Sport-Event Medalists</h6>
  <?php if (empty($by_category_events)): ?>
    <p class="text-muted small mb-0">No medalists yet.</p>
  <?php else: ?>
    <?php foreach ($by_category_events as $c):
      $catLabel = $c['category_name'] . (!empty($c['category_abbr']) ? ' (' . $c['category_abbr'] . ')' : '');
    ?>
      <div class="mb-3">
        <h6 class="fw-semibold mb-2 text-primary">
          <i class="bi bi-collection me-1"></i><?= e($catLabel ?: '—') ?>
        </h6>
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:130px">Sport-Event</th>
                <th>🥇 Gold</th>
                <th>🥈 Silver</th>
                <th>🥉 Bronze</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($c['events'] as $ev): ?>
                <tr>
                  <td>
                    <code><?= e($ev['event_code'] ?: '—') ?></code>
                    <?php if (!empty($ev['sport_event'])): ?>
                      <div class="small text-muted"><?= e($ev['sport_event']) ?></div>
                    <?php endif; ?>
                  </td>
                  <?php foreach (['gold','silver','bronze'] as $slot):
                    $m = $ev[$slot] ?? null;
                  ?>
                    <td>
                      <?php if (!$m): ?>
                        <span class="text-muted">—</span>
                      <?php else: ?>
                        <div class="small">
                          <code class="me-1"><?= e($compNo($m['competitor_number'])) ?: '—' ?></code>
                          <span class="fw-medium"><?= e($m['athlete_name']) ?></span>
                        </div>
                        <div class="small text-muted">
                          <?= e($m['unit_name'] ?: '—') ?>
                          <?php if (!empty($m['unit_address'])): ?>
                            <span class="text-muted"> · <?= e($m['unit_address']) ?></span>
                          <?php endif; ?>
                        </div>
                        <div class="small text-primary">Score: <?= e($fmtScore($m['grand_total'])) ?></div>
                      <?php endif; ?>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Panel C: per-category top-5 -->
<div class="sms-card p-3 mb-4">
  <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-bar-chart me-2"></i>Event Category &times; Top Scorers (Top 5)</h6>
  <?php if (empty($by_category_top)): ?>
    <p class="text-muted small mb-0">No scored entries yet.</p>
  <?php else: ?>
    <?php foreach ($by_category_top as $c):
      $catLabel = $c['category_name'] . (!empty($c['category_abbr']) ? ' (' . $c['category_abbr'] . ')' : '');
    ?>
      <div class="mb-3">
        <h6 class="fw-semibold mb-2 text-primary">
          <i class="bi bi-collection me-1"></i><?= e($catLabel ?: '—') ?>
        </h6>
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:60px">Rank</th>
                <th style="width:90px">Comp. No.</th>
                <th>Name of Athlete</th>
                <th>Unit</th>
                <th>Address</th>
                <th style="width:90px" class="text-end">Total Score</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($c['entries'] as $i => $row): ?>
                <tr>
                  <td class="text-center fw-bold"><?= $i + 1 ?></td>
                  <td>
                    <?php if ($row['competitor_number']): ?>
                      <code class="fw-bold"><?= e($compNo($row['competitor_number'])) ?></code>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="fw-medium"><?= e($row['athlete_name'] ?: '—') ?></td>
                  <td class="small"><?= e($row['unit_name'] ?: '—') ?></td>
                  <td class="small text-muted"><?= e($row['unit_address']) ?: '—' ?></td>
                  <td class="text-end fw-bold"><?= e($fmtScore($row['grand_total'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php endif; ?>

<script>
const MR_DATA = {
  event_name:       <?= json_encode($event['name'] ?? '') ?>,
  event_code:       <?= json_encode($event['event_code'] ?? '') ?>,
  institution_name: <?= json_encode($event['institution_name'] ?? '') ?>,
  event_logo:       <?= json_encode($event['logo'] ?? '') ?>,
  location:         <?= json_encode($event['location'] ?? '') ?>,
  points:           <?= json_encode($points) ?>,
  unit_ranked: <?= json_encode(array_map(function ($u) {
    return [
      'rank'    => (int)($u['rank'] ?? 0),
      'name'    => (string)($u['name'] ?? ''),
      'address' => (string)($u['address'] ?? ''),
      'indiv'   => (int)($u['indiv'] ?? 0),
      'team'    => (int)($u['team'] ?? 0),
      'grand'   => (int)($u['grand'] ?? 0),
    ];
  }, $unit_ranked ?? [])) ?>,
  by_category_events: <?= json_encode(array_values(array_map(function ($c) use ($fmtScore, $compNo) {
    return [
      'category_name' => (string)($c['category_name'] ?? ''),
      'category_abbr' => (string)($c['category_abbr'] ?? ''),
      'events'        => array_map(function ($ev) use ($fmtScore, $compNo) {
        $pack = function ($m) use ($fmtScore, $compNo) {
          if (!$m) return null;
          return [
            'comp_no'      => $compNo($m['competitor_number'] ?? 0),
            'athlete_name' => (string)($m['athlete_name'] ?? ''),
            'unit_name'    => (string)($m['unit_name'] ?? ''),
            'unit_address' => (string)($m['unit_address'] ?? ''),
            'score'        => $fmtScore($m['grand_total'] ?? null),
          ];
        };
        return [
          'event_code'  => (string)($ev['event_code'] ?? ''),
          'sport_event' => (string)($ev['sport_event'] ?? ''),
          'gold'        => $pack($ev['gold']   ?? null),
          'silver'      => $pack($ev['silver'] ?? null),
          'bronze'      => $pack($ev['bronze'] ?? null),
        ];
      }, $c['events'] ?? []),
    ];
  }, $by_category_events ?? []))) ?>,
  by_category_top: <?= json_encode(array_values(array_map(function ($c) use ($fmtScore, $compNo) {
    return [
      'category_name' => (string)($c['category_name'] ?? ''),
      'category_abbr' => (string)($c['category_abbr'] ?? ''),
      'entries'       => array_map(function ($r) use ($fmtScore, $compNo) {
        return [
          'comp_no'      => $compNo($r['competitor_number'] ?? 0),
          'athlete_name' => (string)($r['athlete_name'] ?? ''),
          'unit_name'    => (string)($r['unit_name'] ?? ''),
          'unit_address' => (string)($r['unit_address'] ?? ''),
          'score'        => $fmtScore($r['grand_total'] ?? null),
        ];
      }, $c['entries'] ?? []),
    ];
  }, $by_category_top ?? []))) ?>,
};

function mrEsc(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g,
    c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function printMedalReport() {
  const d = MR_DATA;
  const logo = d.event_logo ? `<img src="${mrEsc(d.event_logo)}" alt="" class="event-logo">` : '';
  const evMeta = [d.institution_name, d.location].filter(Boolean).map(mrEsc).join(' · ');

  const ptsLine =
    `Individual — Gold ${d.points.indiv['1']} · Silver ${d.points.indiv['2']} · Bronze ${d.points.indiv['3']} | `
  + `Team — Gold ${d.points.team['1']} · Silver ${d.points.team['2']} · Bronze ${d.points.team['3']}`;

  // (a) Unit table
  const unitRows = (d.unit_ranked || []).map(u => `<tr>
    <td class="text-center fw-bold">${u.rank}</td>
    <td>${mrEsc(u.name)}</td>
    <td class="small">${mrEsc(u.address)}</td>
    <td class="text-center">${u.indiv}</td>
    <td class="text-center">${u.team}</td>
    <td class="text-end fw-bold">${u.grand}</td>
  </tr>`).join('') || '<tr><td colspan="6" class="text-center text-muted">No medal-bearing results yet.</td></tr>';

  const unitPanel = `<section class="panel">
    <h3 class="panel-title">Unit-wise Points &amp; Rank</h3>
    <table class="report-table">
      <colgroup>
        <col style="width:14mm"><col><col><col style="width:24mm"><col style="width:24mm"><col style="width:28mm">
      </colgroup>
      <thead><tr>
        <th>Rank</th><th>Unit</th><th>Address</th>
        <th>Individual</th><th>Team</th><th>Grand Total</th>
      </tr></thead>
      <tbody>${unitRows}</tbody>
    </table>
  </section>`;

  // (b) per-category event medalists
  function medalCell(m) {
    if (!m) return '<span class="muted">—</span>';
    return `<div><code>${mrEsc(m.comp_no || '—')}</code> <strong>${mrEsc(m.athlete_name)}</strong></div>
            <div class="muted">${mrEsc(m.unit_name)}${m.unit_address ? ' · ' + mrEsc(m.unit_address) : ''}</div>
            <div class="score">Score: ${mrEsc(m.score)}</div>`;
  }
  const eventsHtml = (d.by_category_events || []).map(c => {
    const lbl = (c.category_name || '') + (c.category_abbr ? ` (${c.category_abbr})` : '');
    const rows = (c.events || []).map(ev => `<tr>
      <td><code>${mrEsc(ev.event_code || '—')}</code>${ev.sport_event ? `<div class="muted small">${mrEsc(ev.sport_event)}</div>` : ''}</td>
      <td>${medalCell(ev.gold)}</td>
      <td>${medalCell(ev.silver)}</td>
      <td>${medalCell(ev.bronze)}</td>
    </tr>`).join('') || '<tr><td colspan="4" class="text-center muted">No medalists.</td></tr>';
    return `<div class="cat-block">
      <h4 class="cat-title">${mrEsc(lbl || '—')}</h4>
      <table class="report-table">
        <colgroup><col style="width:42mm"><col><col><col></colgroup>
        <thead><tr><th>Sport-Event</th><th>🥇 Gold</th><th>🥈 Silver</th><th>🥉 Bronze</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>
    </div>`;
  }).join('');
  const eventsPanel = `<section class="panel">
    <h3 class="panel-title">Event Category &times; Sport-Event Medalists</h3>
    ${eventsHtml || '<p class="muted">No medalists.</p>'}
  </section>`;

  // (c) per-category top 5
  const topHtml = (d.by_category_top || []).map(c => {
    const lbl = (c.category_name || '') + (c.category_abbr ? ` (${c.category_abbr})` : '');
    const rows = (c.entries || []).map((r, i) => `<tr>
      <td class="text-center fw-bold">${i + 1}</td>
      <td class="text-center fw-bold"><code>${mrEsc(r.comp_no || '—')}</code></td>
      <td>${mrEsc(r.athlete_name)}</td>
      <td>${mrEsc(r.unit_name)}</td>
      <td class="muted">${mrEsc(r.unit_address)}</td>
      <td class="text-end fw-bold">${mrEsc(r.score)}</td>
    </tr>`).join('') || '<tr><td colspan="6" class="text-center muted">No scored entries.</td></tr>';
    return `<div class="cat-block">
      <h4 class="cat-title">${mrEsc(lbl || '—')}</h4>
      <table class="report-table">
        <colgroup><col style="width:14mm"><col style="width:22mm"><col><col><col><col style="width:24mm"></colgroup>
        <thead><tr>
          <th>Rank</th><th>Comp. No.</th><th>Name of Athlete</th><th>Unit</th><th>Address</th><th>Total Score</th>
        </tr></thead>
        <tbody>${rows}</tbody>
      </table>
    </div>`;
  }).join('');
  const topPanel = `<section class="panel">
    <h3 class="panel-title">Event Category &times; Top 5 Scorers</h3>
    ${topHtml || '<p class="muted">No scored entries.</p>'}
  </section>`;

  const html = `<!doctype html>
<html><head>
<meta charset="utf-8">
<title>Medal Report — ${mrEsc(d.event_name)}</title>
<style>
  @page { size: A4 landscape; margin: 12mm 10mm 16mm 10mm;
          @bottom-right { content: "Page " counter(page) " of " counter(pages);
                          font-size: 8pt; color:#666; } }
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
         color:#111; margin:0; padding:0; }
  .event-head { display:flex; align-items:center; gap:14px;
                border-bottom:2px solid #333; padding-bottom:8px; margin-bottom:10px; }
  .event-head .event-logo { width:56px; height:56px; object-fit:contain; flex-shrink:0; }
  .event-head .text { flex:1; min-width:0; }
  .event-head h1 { font-size:14pt; margin:0 0 2px; }
  .event-head .meta { font-size:9.5pt; color:#555; }
  .event-head h2 { font-size:11pt; margin:4px 0 0; }
  .pts-line { font-size:9.5pt; color:#444; background:#f8fafc; border:1px solid #e2e8f0;
              border-radius:4px; padding:5px 8px; margin-bottom:10px; }
  .panel { page-break-inside: auto; margin-bottom:14px; }
  .panel + .panel { page-break-before: always; }
  .panel-title { font-size:12pt; font-weight:700; margin:0 0 8px;
                 padding-bottom:4px; border-bottom:2px solid #0b1f3a; }
  .cat-block { margin-bottom:10px; }
  .cat-title { font-size:10.5pt; font-weight:700; color:#0b1f3a; margin:0 0 4px; }
  table.report-table { width:100%; border-collapse:collapse; table-layout:fixed; }
  table.report-table thead { display:table-header-group; }
  table.report-table th, table.report-table td {
    border:1px solid #555; padding:4px 6px; font-size:9.5pt;
    vertical-align:top; word-wrap:break-word; overflow:hidden;
  }
  table.report-table thead th { background:#e9ecef; font-size:9pt; text-align:center; }
  table.report-table tbody tr { page-break-inside: avoid; }
  .fw-bold { font-weight:700; }
  .text-center { text-align:center; }
  .text-end { text-align:right; }
  .muted { color:#6c757d; }
  .score { color:#0b6c2f; font-weight:600; font-size:9pt; }
  code { background:#f1f3f5; padding:1px 4px; border-radius:3px; font-size:9pt; }
  .actions { margin: 8px 0; }
  @media print { .actions { display:none; } }
</style>
</head><body>
<div class="actions">
  <button onclick="window.print()" style="padding:4px 10px">Print</button>
  <button onclick="window.close()" style="padding:4px 10px;margin-left:4px">Close</button>
</div>
<header class="event-head">
  ${logo}
  <div class="text">
    <h1>${mrEsc(d.event_name)}${d.event_code ? ' · ' + mrEsc(d.event_code) : ''}</h1>
    ${evMeta ? `<div class="meta">${evMeta}</div>` : ''}
    <h2>Medal Report</h2>
  </div>
</header>
<div class="pts-line">${mrEsc(ptsLine)}</div>
${unitPanel}
${eventsPanel}
${topPanel}
<script>
  window.addEventListener('load', () => { setTimeout(() => window.print(), 300); });
<\/script>
</body></html>`;

  const w = window.open('', '_blank');
  if (!w) { alert('Pop-up blocked — allow pop-ups to use Print / PDF.'); return; }
  w.document.open();
  w.document.write(html);
  w.document.close();
}
</script>
