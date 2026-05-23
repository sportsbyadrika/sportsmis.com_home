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
// Sum the per-series sub_totals coming out of GROUP_CONCAT. Used to
// render the Sub Total column (Sub Total - Penalty = Total Score).
$sumSeries = function (string $csv): float {
    $s = 0.0;
    foreach (array_filter(array_map('trim', explode(',', $csv)), 'strlen') as $v) {
        $s += (float)$v;
    }
    return $s;
};
// Compact label for the score_entries.remarks ENUM value.
$remarksLabel = function ($r): string {
    return match ((string)$r) {
        'dns'          => 'DNS',
        'dnf'          => 'DNF',
        'disqualified' => 'DQ',
        'other'        => 'Other',
        default        => '',
    };
};
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/result-reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-bullseye me-2"></i>Relay Result</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <?php if ($relay && !empty($lanes)): ?>
    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="printRelayResult()">
      <i class="bi bi-printer me-1"></i>Print / PDF
    </button>
  <?php endif; ?>
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
      <table class="table table-sm align-middle mb-0" style="table-layout:fixed">
        <!-- Width grouping:
             • Name of Athlete + Event Category share width.
             • Unit + Penalty + No. of 10s + Total Score + Remarks share width.
             • Score (Series) becomes a pivot band of $max_series sub-columns. -->
        <colgroup>
          <col style="width:54px">   <!-- Lane -->
          <col style="width:90px">   <!-- Comp. No. -->
          <col style="width:180px">  <!-- Name of Athlete -->
          <col style="width:110px">  <!-- Unit -->
          <col style="width:130px">  <!-- Event Category -->
          <?php for ($i = 0; $i < (int)$max_series; $i++): ?>
            <col style="width:60px">  <!-- Series #<?= $i + 1 ?> -->
          <?php endfor; ?>
          <col style="width:110px">  <!-- Sub Total -->
          <col style="width:110px">  <!-- Penalty -->
          <col style="width:110px">  <!-- No. of 10s -->
          <col style="width:110px">  <!-- Total Score -->
          <col style="width:110px">  <!-- Remarks -->
        </colgroup>
        <thead class="table-light text-center">
          <tr>
            <th rowspan="2" class="align-middle">Lane</th>
            <th rowspan="2" class="align-middle">Comp. No.</th>
            <th rowspan="2" class="align-middle text-start">Name of Athlete</th>
            <th rowspan="2" class="align-middle text-start">Unit</th>
            <th rowspan="2" class="align-middle">Category</th>
            <th colspan="<?= (int)$max_series ?>">Score (Series)</th>
            <th rowspan="2" class="align-middle">Sub Total</th>
            <th rowspan="2" class="align-middle">Penalty</th>
            <th rowspan="2" class="align-middle">No. of 10s</th>
            <th rowspan="2" class="align-middle text-end">Total Score</th>
            <th rowspan="2" class="align-middle text-start">Remarks</th>
          </tr>
          <tr>
            <?php for ($i = 1; $i <= (int)$max_series; $i++): ?>
              <th class="text-center small"><?= $i ?></th>
            <?php endfor; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lanes as $l):
            $hasAthlete  = !empty($l['athlete_name']);
            $seriesParts = [];
            if (!empty($l['series_subs_csv'])) {
                $seriesParts = array_map('trim', explode(',', (string)$l['series_subs_csv']));
            }
            $compNo = (int)($l['competitor_number'] ?: $l['score_competitor_number'] ?? 0);
            $hasScore = $l['score_total'] !== null;
          ?>
            <tr>
              <td class="text-center fw-bold"><?= (int)$l['lane_number'] ?></td>
              <td>
                <?php if ($compNo): ?>
                  <code class="fw-bold"><?= str_pad((string)$compNo, 4, '0', STR_PAD_LEFT) ?></code>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="fw-medium"><?= e($l['athlete_name'] ?: '—') ?></td>
              <td class="small"><?= e($l['unit_name'] ?: '—') ?></td>
              <td class="small text-center">
                <?= e(
                    trim((string)($l['category_abbr'] ?? '')) !== ''
                        ? $l['category_abbr']
                        : ($l['category'] ?: ($l['default_category'] ?: '—'))
                ) ?>
              </td>
              <?php for ($i = 0; $i < (int)$max_series; $i++):
                $v = $seriesParts[$i] ?? '';
                $disp = ($v === '' || $v === null) ? '' : $fmtScore($v);
              ?>
                <td class="text-center small font-monospace">
                  <?= $disp !== '' ? e($disp) : '<span class="text-muted">—</span>' ?>
                </td>
              <?php endfor; ?>
              <?php
                $subTotal = $sumSeries((string)($l['series_subs_csv'] ?? ''));
              ?>
              <td class="text-center small fw-medium">
                <?= $hasScore ? e($fmtScore($subTotal)) : '<span class="text-muted">—</span>' ?>
              </td>
              <td class="text-center small">
                <?= ($l['score_penalty'] !== null && (float)$l['score_penalty'] > 0)
                      ? e($fmtScore($l['score_penalty']))
                      : '<span class="text-muted">—</span>' ?>
              </td>
              <td class="text-center small">
                <?= !empty($l['tens_count'])
                      ? (int)$l['tens_count']
                      : '<span class="text-muted">—</span>' ?>
              </td>
              <td class="text-end fw-bold">
                <?= $hasScore ? (int)round((float)$l['score_total'])
                              : '<span class="text-muted">—</span>' ?>
              </td>
              <?php
                $rLabel = $remarksLabel($l['score_remarks'] ?? '');
                $rNotes = trim((string)($l['score_notes'] ?? ''));
              ?>
              <td class="small">
                <?php if ($rLabel !== ''): ?>
                  <span class="badge bg-secondary-subtle text-secondary"><?= e($rLabel) ?></span>
                <?php endif; ?>
                <?php if ($rNotes !== ''): ?>
                  <div class="text-muted" <?= $rLabel !== '' ? 'style="margin-top:2px"' : '' ?>><?= e($rNotes) ?></div>
                <?php elseif ($rLabel === ''): ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

<script>
const RR_DATA = {
  event_name:       <?= json_encode($event['name'] ?? '') ?>,
  event_code:       <?= json_encode($event['event_code'] ?? '') ?>,
  institution_name: <?= json_encode($event['institution_name'] ?? '') ?>,
  event_logo:       <?= json_encode($event['logo'] ?? '') ?>,
  location:         <?= json_encode($event['location'] ?? '') ?>,
  relay: {
    relay_number:   <?= json_encode($relay['relay_number']   ?? '') ?>,
    relay_date:     <?= json_encode($relay['relay_date']     ?? '') ?>,
    match_time:     <?= json_encode($relay['match_time']     ?? '') ?>,
    reporting_time: <?= json_encode($relay['reporting_time'] ?? '') ?>,
    venue_name:     <?= json_encode($relay['venue_name']     ?? '') ?>,
    range_name:     <?= json_encode($relay['range_name']     ?? '') ?>,
  },
  max_series: <?= (int)$max_series ?>,
  lanes: <?= json_encode(array_map(function ($l) use ($fmtScore, $remarksLabel, $sumSeries) {
    $compNo = (int)($l['competitor_number'] ?: ($l['score_competitor_number'] ?? 0));
    $series = [];
    if (!empty($l['series_subs_csv'])) {
        $parts = array_map('trim', explode(',', (string)$l['series_subs_csv']));
        foreach ($parts as $p) {
            $series[] = $p === '' ? '' : $fmtScore($p);
        }
    }
    $sub = $sumSeries((string)($l['series_subs_csv'] ?? ''));
    return [
      'lane_number'      => (int)$l['lane_number'],
      'comp_no'          => $compNo ? str_pad((string)$compNo, 4, '0', STR_PAD_LEFT) : '',
      'athlete_name'     => (string)($l['athlete_name'] ?? ''),
      'unit_name'        => (string)($l['unit_name'] ?? ''),
      'category'         => (string)(trim((string)($l['category_abbr'] ?? '')) !== ''
                                ? $l['category_abbr']
                                : ($l['category'] ?: ($l['default_category'] ?? ''))),
      'series'           => $series,
      'sub_total'        => $l['score_entry_id'] ? $fmtScore($sub) : '',
      'penalty'          => ($l['score_penalty'] !== null && (float)$l['score_penalty'] > 0)
                              ? $fmtScore($l['score_penalty']) : '',
      'tens'             => !empty($l['tens_count']) ? (int)$l['tens_count'] : '',
      'grand_total'      => $l['score_total'] !== null ? (string)(int)round((float)$l['score_total']) : '',
      'remarks_label'    => $remarksLabel($l['score_remarks'] ?? ''),
      'remarks_notes'    => trim((string)($l['score_notes'] ?? '')),
    ];
  }, $lanes)) ?>,
};

function rrEsc(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g,
    c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function rrFormatDate(d) {
  if (!d) return '';
  const dt = new Date(d + 'T00:00:00');
  if (isNaN(dt)) return d;
  return dt.toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric' });
}
function rrTimeShort(t) { return t ? String(t).slice(0,5) : ''; }

function printRelayResult() {
  const lanes = RR_DATA.lanes || [];
  const ev    = RR_DATA;
  const r     = RR_DATA.relay || {};

  const logo  = ev.event_logo
    ? `<img src="${rrEsc(ev.event_logo)}" alt="" class="event-logo">` : '';
  const evMeta = [ev.institution_name, ev.location].filter(Boolean).map(rrEsc).join(' · ');

  // Relay strip — same idea as the Relay-wise Participant List: lives
  // inside the table's thead so it repeats on continuation pages.
  const stripBits = [
    ['Date',           rrFormatDate(r.relay_date)],
    ['Match Time',     rrTimeShort(r.match_time)],
    ['Reporting Time', rrTimeShort(r.reporting_time)],
    ['Venue / Range',  [r.venue_name, r.range_name].filter(Boolean).join(' → ')],
  ];
  const stripHtml = stripBits.map(([k,v]) =>
    `<div><div class="lbl">${rrEsc(k)}</div><div class="val">${rrEsc(v) || '—'}</div></div>`
  ).join('');

  const N = Math.max(1, parseInt(RR_DATA.max_series, 10) || 4);
  const totalCols = 5 + N + 5;  // Lane, CompNo, Name, Unit, Cat + N series + SubTot, Pen, 10s, Total, Remarks
  const bodyHtml = lanes.length ? lanes.map(l => {
    const remarksParts = [];
    if (l.remarks_label) remarksParts.push(`<span class="rem-pill">${rrEsc(l.remarks_label)}</span>`);
    if (l.remarks_notes) remarksParts.push(`<div class="rem-notes">${rrEsc(l.remarks_notes)}</div>`);
    const remarksCell = remarksParts.length ? remarksParts.join('') : '';

    let seriesCells = '';
    for (let i = 0; i < N; i++) {
      const v = (l.series && l.series[i]) ? l.series[i] : '';
      seriesCells += `<td class="series text-center">${rrEsc(v)}</td>`;
    }
    return `<tr>
      <td class="text-center fw-bold">${l.lane_number}</td>
      <td class="text-center fw-bold">${rrEsc(l.comp_no)}</td>
      <td>${rrEsc(l.athlete_name)}</td>
      <td>${rrEsc(l.unit_name)}</td>
      <td class="text-center">${rrEsc(l.category)}</td>
      ${seriesCells}
      <td class="text-center fw-medium">${rrEsc(l.sub_total)}</td>
      <td class="text-center">${rrEsc(l.penalty)}</td>
      <td class="text-center">${rrEsc(l.tens)}</td>
      <td class="text-end fw-bold">${rrEsc(l.grand_total)}</td>
      <td>${remarksCell}</td>
    </tr>`;
  }).join('') : `<tr><td colspan="${totalCols}" class="text-center text-muted">No lanes configured on this relay.</td></tr>`;

  // Width split for the per-series pivot band — the band claims a fixed
  // 58mm regardless of N, so 4 series get ~14mm each, 6 series get
  // ~10mm each. Tight but always inside A4 landscape.
  const SCORE_BAND_MM = 58;
  const seriesColMm   = (SCORE_BAND_MM / N).toFixed(2);
  let seriesColgroup  = '';
  let seriesHeadCols  = '';
  for (let i = 1; i <= N; i++) {
    seriesColgroup += `<col style="width:${seriesColMm}mm">`;
    seriesHeadCols += `<th>${i}</th>`;
  }

  const html = `<!doctype html>
<html><head>
<meta charset="utf-8">
<title>Relay Result — Relay ${rrEsc(r.relay_number)} — ${rrEsc(ev.event_name)}</title>
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
  /* The thead — including the relay-strip row — repeats on every print
     continuation page so an overflowed table never loses its context. */
  table.lane-table { width:100%; border-collapse:collapse; table-layout:fixed; }
  table.lane-table thead { display:table-header-group; }
  table.lane-table tbody tr { page-break-inside: avoid; height: 14mm; }
  table.lane-table th, table.lane-table td {
    border:1px solid #555; padding:3px 6px; font-size:9.5pt;
    vertical-align:middle; word-wrap:break-word; overflow:hidden;
  }
  table.lane-table thead th { background:#e9ecef; font-size:9pt; text-align:center; }
  td.relay-strip {
    background:#f5f7fa; border:1px solid #d0d6dd; padding:6px 8px; text-align:left;
  }
  td.relay-strip .relay-title { font-size:12pt; font-weight:700; margin-bottom:4px; }
  td.relay-strip .grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:4px 14px; font-size:10pt; }
  td.relay-strip .lbl { color:#666; font-size:9pt; }
  td.relay-strip .val { font-weight:600; }
  .fw-bold { font-weight:700; }
  .text-center { text-align:center; }
  .text-end { text-align:right; }
  .series { font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace; font-size:9pt; white-space: nowrap; }
  .rem-pill { display:inline-block; padding:1px 5px; border-radius:8px;
              background:#eef2f7; color:#3c4859; font-weight:600;
              font-size:8.5pt; letter-spacing:.02em; }
  .rem-notes { color:#475569; font-size:8.5pt; margin-top:2px; }
  .athlete-photo, .athlete-photo-fallback {
    width:30px; height:30px; object-fit:cover; border:1px solid #b7bec5;
    border-radius:50%; display:block; margin:0 auto;
  }
  .athlete-photo-fallback {
    background:#e9ecef; color:#6c757d; text-align:center; line-height:30px;
    font-weight:600; font-size:9.5pt;
  }
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
    <h1>${rrEsc(ev.event_name)}${ev.event_code ? ' · ' + rrEsc(ev.event_code) : ''}</h1>
    ${evMeta ? `<div class="meta">${evMeta}</div>` : ''}
    <h2>Relay Result</h2>
  </div>
</header>
<table class="lane-table">
  <colgroup>
    <col style="width:12mm">  <!-- Lane -->
    <col style="width:16mm">  <!-- Comp. No. -->
    <col style="width:38mm">  <!-- Name of Athlete -->
    <col style="width:20mm">  <!-- Unit -->
    <col style="width:22mm">  <!-- Category -->
    ${seriesColgroup}         <!-- Score (Series) — N sub-columns -->
    <col style="width:18mm">  <!-- Sub Total -->
    <col style="width:18mm">  <!-- Penalty -->
    <col style="width:18mm">  <!-- No. of 10s -->
    <col style="width:20mm">  <!-- Total Score -->
    <col style="width:20mm">  <!-- Remarks -->
  </colgroup>
  <thead>
    <tr><td class="relay-strip" colspan="${totalCols}">
      <div class="relay-title">Relay ${rrEsc(r.relay_number)}</div>
      <div class="grid">${stripHtml}</div>
    </td></tr>
    <tr>
      <th rowspan="2">Lane</th>
      <th rowspan="2">Comp. No.</th>
      <th rowspan="2">Name of Athlete</th>
      <th rowspan="2">Unit</th>
      <th rowspan="2">Category</th>
      <th colspan="${N}">Score (Series)</th>
      <th rowspan="2">Sub Total</th>
      <th rowspan="2">Penalty</th>
      <th rowspan="2">No. of 10s</th>
      <th rowspan="2">Total Score</th>
      <th rowspan="2">Remarks</th>
    </tr>
    <tr>${seriesHeadCols}</tr>
  </thead>
  <tbody>${bodyHtml}</tbody>
</table>
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
<?php endif; ?>
