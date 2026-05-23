<?php
$pageTitle = 'Event — Rank List — ' . $event['name'];
// Format a decimal like 98.00 -> "98" but keep meaningful fractions.
$fmtScore = function ($v): string {
    if ($v === null || $v === '') return '';
    $f = (float)$v;
    if ($f == (int)$f) return (string)(int)$f;
    return rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');
};
$remarksLabel = function ($r): string {
    return match ((string)$r) {
        'dns'          => 'DNS',
        'dnf'          => 'DNF',
        'disqualified' => 'DQ',
        'other'        => 'Other',
        default        => '',
    };
};
$N = (int)($max_series ?? 4);
$selectedCategory = null;
foreach ($categories as $c) {
    if ((int)$c['id'] === (int)($category_id ?? 0)) { $selectedCategory = $c; break; }
}
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/result-reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-list-ol me-2"></i>Event — Rank List</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <?php if ($selectedCategory && !empty($groups)): ?>
    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="printEventRankList()">
      <i class="bi bi-printer me-1"></i>Print / PDF
    </button>
  <?php endif; ?>
</div>

<form method="GET" class="sms-card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-6">
      <label class="form-label small mb-1">Event Category</label>
      <select name="category_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="0">— Select a category —</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)($category_id ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
            <?= e($c['name']) ?><?= !empty($c['abbreviation']) ? ' (' . e($c['abbreviation']) . ')' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Show</button>
      <a href="/event-staff/result-reports/event-rank-list" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
    </div>
  </div>
</form>

<?php if (!$selectedCategory): ?>
  <div class="sms-empty-state">
    <i class="bi bi-list-ol"></i>
    <h5>Select an Event Category</h5>
    <p>Pick a category from the dropdown above to see ranked results, grouped by sport-event.</p>
  </div>
<?php elseif (empty($groups)): ?>
  <div class="sms-empty-state">
    <i class="bi bi-info-circle"></i>
    <h5>No Scores Yet</h5>
    <p>No saved or final score entries are available for <strong><?= e($selectedCategory['name']) ?></strong>.</p>
  </div>
<?php else: ?>
  <?php foreach ($groups as $g): ?>
    <div class="sms-card p-3 mb-3">
      <h6 class="fw-semibold border-bottom pb-2 mb-2">
        <code class="me-1"><?= e($g['event_code'] ?: '—') ?></code>
        <?= e($g['sport_event'] ?: '—') ?>
        <?php if (!empty($g['category_abbr']) || !empty($g['category'])): ?>
          <span class="badge bg-secondary-subtle text-secondary ms-1">
            <?= e($g['category_abbr'] ?: $g['category']) ?>
          </span>
        <?php endif; ?>
        <span class="text-muted small ms-2"><?= count($g['entries']) ?> competitor<?= count($g['entries']) === 1 ? '' : 's' ?></span>
      </h6>

      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0" style="table-layout:fixed">
          <colgroup>
            <col style="width:60px">
            <col style="width:90px">
            <col style="width:180px">
            <col style="width:130px">
            <?php for ($i = 0; $i < $N; $i++): ?>
              <col style="width:60px">
            <?php endfor; ?>
            <col style="width:90px">
            <col style="width:90px">
            <col style="width:100px">
            <col style="width:130px">
          </colgroup>
          <thead class="table-light text-center">
            <tr>
              <th rowspan="2" class="align-middle">Rank</th>
              <th rowspan="2" class="align-middle">Comp. No.</th>
              <th rowspan="2" class="align-middle text-start">Name of Athlete</th>
              <th rowspan="2" class="align-middle text-start">Unit</th>
              <th colspan="<?= $N ?>">Score (Series)</th>
              <th rowspan="2" class="align-middle">Penalty</th>
              <th rowspan="2" class="align-middle">No. of 10s</th>
              <th rowspan="2" class="align-middle text-end">Total Score</th>
              <th rowspan="2" class="align-middle text-start">Remarks</th>
            </tr>
            <tr>
              <?php for ($i = 1; $i <= $N; $i++): ?>
                <th class="text-center small"><?= $i ?></th>
              <?php endfor; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($g['entries'] as $row):
              $compNo = (int)($row['competitor_number'] ?? 0);
              $rLabel = $remarksLabel($row['score_remarks'] ?? '');
              $rNotes = trim((string)($row['score_notes'] ?? ''));
              $hasScore = !empty($row['has_score']);
            ?>
              <tr<?= !$hasScore ? ' class="table-secondary"' : '' ?>>
                <td class="text-center fw-bold">
                  <?= $row['rank'] !== null ? (int)$row['rank'] : '<span class="text-muted">—</span>' ?>
                </td>
                <td>
                  <?php if ($compNo): ?>
                    <code class="fw-bold"><?= str_pad((string)$compNo, 4, '0', STR_PAD_LEFT) ?></code>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td class="fw-medium"><?= e($row['athlete_name'] ?: '—') ?></td>
                <td class="small"><?= e($row['unit_name'] ?: '—') ?></td>
                <?php for ($i = 0; $i < $N; $i++):
                  $v = $row['series_array'][$i] ?? '';
                  $disp = ($v === '' || $v === null) ? '' : $fmtScore($v);
                ?>
                  <td class="text-center small font-monospace">
                    <?= $disp !== '' ? e($disp) : '<span class="text-muted">—</span>' ?>
                  </td>
                <?php endfor; ?>
                <td class="text-center small">
                  <?= ($row['total_penalty'] !== null && (float)$row['total_penalty'] > 0)
                        ? e($fmtScore($row['total_penalty']))
                        : '<span class="text-muted">—</span>' ?>
                </td>
                <td class="text-center small">
                  <?= !empty($row['tens_count'])
                        ? (int)$row['tens_count']
                        : '<span class="text-muted">—</span>' ?>
                </td>
                <td class="text-end fw-bold">
                  <?= $hasScore && $row['grand_total'] !== null
                        ? (int)round((float)$row['grand_total'])
                        : '<span class="text-muted">—</span>' ?>
                </td>
                <td class="small">
                  <?php if (!$hasScore): ?>
                    <span class="text-muted fst-italic">No score</span>
                  <?php else: ?>
                    <?php if ($rLabel !== ''): ?>
                      <span class="badge bg-secondary-subtle text-secondary"><?= e($rLabel) ?></span>
                    <?php endif; ?>
                    <?php if ($rNotes !== ''): ?>
                      <div class="text-muted" <?= $rLabel !== '' ? 'style="margin-top:2px"' : '' ?>><?= e($rNotes) ?></div>
                    <?php elseif ($rLabel === ''): ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>

<script>
const ERL_DATA = {
  event_name:       <?= json_encode($event['name'] ?? '') ?>,
  event_code:       <?= json_encode($event['event_code'] ?? '') ?>,
  institution_name: <?= json_encode($event['institution_name'] ?? '') ?>,
  event_logo:       <?= json_encode($event['logo'] ?? '') ?>,
  location:         <?= json_encode($event['location'] ?? '') ?>,
  category: {
    name:         <?= json_encode($selectedCategory['name'] ?? '') ?>,
    abbreviation: <?= json_encode($selectedCategory['abbreviation'] ?? '') ?>,
  },
  max_series: <?= (int)$N ?>,
  groups: <?= json_encode(array_map(function ($g) use ($fmtScore, $remarksLabel) {
    return [
      'event_code'    => (string)($g['event_code'] ?? ''),
      'sport_event'   => (string)($g['sport_event'] ?? ''),
      'category_abbr' => (string)($g['category_abbr'] ?? ''),
      'category'      => (string)($g['category'] ?? ''),
      'entries'       => array_map(function ($r) use ($fmtScore, $remarksLabel) {
        $compNo   = (int)($r['competitor_number'] ?? 0);
        $hasScore = !empty($r['has_score']);
        $series   = [];
        foreach ($r['series_array'] ?? [] as $v) {
          $series[] = ($v === '' || $v === null) ? '' : $fmtScore($v);
        }
        return [
          'rank'          => $r['rank'] !== null ? (int)$r['rank'] : '',
          'has_score'     => $hasScore,
          'comp_no'       => $compNo ? str_pad((string)$compNo, 4, '0', STR_PAD_LEFT) : '',
          'athlete_name'  => (string)($r['athlete_name'] ?? ''),
          'unit_name'     => (string)($r['unit_name'] ?? ''),
          'series'        => $series,
          'penalty'       => ($r['total_penalty'] !== null && (float)$r['total_penalty'] > 0)
                              ? $fmtScore($r['total_penalty']) : '',
          'tens'          => !empty($r['tens_count']) ? (int)$r['tens_count'] : '',
          'grand_total'   => $hasScore && $r['grand_total'] !== null
                              ? (string)(int)round((float)$r['grand_total']) : '',
          'remarks_label' => $hasScore ? $remarksLabel($r['score_remarks'] ?? '') : '',
          'remarks_notes' => $hasScore ? trim((string)($r['score_notes'] ?? '')) : 'No score',
        ];
      }, $g['entries']),
    ];
  }, $groups)) ?>,
};

function erlEsc(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g,
    c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function printEventRankList() {
  const N = Math.max(1, parseInt(ERL_DATA.max_series, 10) || 4);
  const totalCols = 4 + N + 4;
  const SCORE_BAND_MM = 58;
  const seriesColMm   = (SCORE_BAND_MM / N).toFixed(2);
  let seriesColgroup  = '';
  let seriesHeadCols  = '';
  for (let i = 1; i <= N; i++) {
    seriesColgroup += `<col style="width:${seriesColMm}mm">`;
    seriesHeadCols += `<th>${i}</th>`;
  }

  const groupSections = (ERL_DATA.groups || []).map(g => {
    const rows = (g.entries || []).map(r => {
      const remarksParts = [];
      if (r.remarks_label) remarksParts.push(`<span class="rem-pill">${erlEsc(r.remarks_label)}</span>`);
      if (r.remarks_notes) remarksParts.push(`<div class="rem-notes">${erlEsc(r.remarks_notes)}</div>`);
      let seriesCells = '';
      for (let i = 0; i < N; i++) {
        const v = (r.series && r.series[i]) ? r.series[i] : '';
        seriesCells += `<td class="series text-center">${erlEsc(v)}</td>`;
      }
      const trCls = r.has_score ? '' : ' class="noscore"';
      return `<tr${trCls}>
        <td class="text-center fw-bold">${r.rank || ''}</td>
        <td class="text-center fw-bold">${erlEsc(r.comp_no)}</td>
        <td>${erlEsc(r.athlete_name)}</td>
        <td>${erlEsc(r.unit_name)}</td>
        ${seriesCells}
        <td class="text-center">${erlEsc(r.penalty)}</td>
        <td class="text-center">${erlEsc(r.tens)}</td>
        <td class="text-end fw-bold">${erlEsc(r.grand_total)}</td>
        <td>${remarksParts.join('')}</td>
      </tr>`;
    }).join('');

    const catBadge = g.category_abbr || g.category || '';
    return `<section class="event-block">
      <table class="lane-table">
        <colgroup>
          <col style="width:14mm">
          <col style="width:18mm">
          <col style="width:46mm">
          <col style="width:28mm">
          ${seriesColgroup}
          <col style="width:20mm">
          <col style="width:20mm">
          <col style="width:24mm">
          <col style="width:30mm">
        </colgroup>
        <thead>
          <tr><td class="event-strip" colspan="${totalCols}">
            <span class="evt-code">${erlEsc(g.event_code || '—')}</span>
            <span class="evt-name">${erlEsc(g.sport_event || '')}</span>
            ${catBadge ? `<span class="evt-cat">${erlEsc(catBadge)}</span>` : ''}
          </td></tr>
          <tr>
            <th rowspan="2">Rank</th>
            <th rowspan="2">Comp. No.</th>
            <th rowspan="2">Name of Athlete</th>
            <th rowspan="2">Unit</th>
            <th colspan="${N}">Score (Series)</th>
            <th rowspan="2">Penalty</th>
            <th rowspan="2">No. of 10s</th>
            <th rowspan="2">Total Score</th>
            <th rowspan="2">Remarks</th>
          </tr>
          <tr>${seriesHeadCols}</tr>
        </thead>
        <tbody>${rows || `<tr><td colspan="${totalCols}" class="text-center text-muted">No entries.</td></tr>`}</tbody>
      </table>
    </section>`;
  }).join('');

  const logo  = ERL_DATA.event_logo
    ? `<img src="${erlEsc(ERL_DATA.event_logo)}" alt="" class="event-logo">` : '';
  const evMeta = [ERL_DATA.institution_name, ERL_DATA.location].filter(Boolean).map(erlEsc).join(' · ');
  const catLine = [ERL_DATA.category.name, ERL_DATA.category.abbreviation ? `(${ERL_DATA.category.abbreviation})` : '']
                    .filter(Boolean).join(' ');

  const html = `<!doctype html>
<html><head>
<meta charset="utf-8">
<title>Event — Rank List — ${erlEsc(ERL_DATA.event_name)}</title>
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
  .event-block { margin-bottom: 12px; }
  table.lane-table { width:100%; border-collapse:collapse; table-layout:fixed; }
  table.lane-table thead { display:table-header-group; }
  table.lane-table tbody tr { page-break-inside: avoid; height: 11mm; }
  table.lane-table th, table.lane-table td {
    border:1px solid #555; padding:3px 6px; font-size:9.5pt;
    vertical-align:middle; word-wrap:break-word; overflow:hidden;
  }
  table.lane-table thead th { background:#e9ecef; font-size:9pt; text-align:center; }
  td.event-strip { background:#f5f7fa; border:1px solid #d0d6dd; padding:5px 8px; text-align:left; font-size:11pt; }
  td.event-strip .evt-code { font-family: ui-monospace, Menlo, Consolas, monospace; background:#fff; padding:1px 5px; border:1px solid #cfd6dd; border-radius:3px; margin-right:6px; }
  td.event-strip .evt-name { font-weight:700; }
  td.event-strip .evt-cat  { display:inline-block; margin-left:8px; padding:1px 6px;
                             background:#eef2f7; color:#3c4859; border-radius:8px;
                             font-size:9pt; font-weight:600; }
  .series { font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace; font-size:9pt; white-space: nowrap; }
  .fw-bold { font-weight:700; }
  .text-center { text-align:center; }
  .text-end { text-align:right; }
  .rem-pill { display:inline-block; padding:1px 5px; border-radius:8px;
              background:#eef2f7; color:#3c4859; font-weight:600;
              font-size:8.5pt; letter-spacing:.02em; }
  .rem-notes { color:#475569; font-size:8.5pt; margin-top:2px; }
  tr.noscore td { background:#fafafa; color:#6c757d; font-style:italic; }
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
    <h1>${erlEsc(ERL_DATA.event_name)}${ERL_DATA.event_code ? ' · ' + erlEsc(ERL_DATA.event_code) : ''}</h1>
    ${evMeta ? `<div class="meta">${evMeta}</div>` : ''}
    <h2>Event — Rank List${catLine ? ' · ' + erlEsc(catLine) : ''}</h2>
  </div>
</header>
${groupSections || '<p class="text-center text-muted">No entries.</p>'}
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
