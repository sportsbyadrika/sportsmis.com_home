<?php
$pageTitle = 'Team — Rank List — ' . $event['name'];
$fmtScore = function ($v): string {
    if ($v === null || $v === '') return '';
    $f = (float)$v;
    if ($f == (int)$f) return (string)(int)$f;
    return rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');
};
$selectedCategory = null;
foreach ($categories as $c) {
    if ((int)$c['id'] === (int)($category_id ?? 0)) { $selectedCategory = $c; break; }
}
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/result-reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i>Team — Rank List</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <?php if ($selectedCategory && !empty($groups)): ?>
    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="printTeamRankList()">
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
      <a href="/event-staff/result-reports/team-rank-list" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
    </div>
  </div>
</form>

<?php if (!$selectedCategory): ?>
  <div class="sms-empty-state">
    <i class="bi bi-people"></i>
    <h5>Select an Event Category</h5>
    <p>Pick a category from the dropdown above to see team standings, grouped by sport-event.</p>
  </div>
<?php elseif (empty($groups)): ?>
  <div class="sms-empty-state">
    <i class="bi bi-info-circle"></i>
    <h5>No Approved Teams</h5>
    <p>No approved team registrations are available for <strong><?= e($selectedCategory['name']) ?></strong>.</p>
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
        <span class="text-muted small ms-2">
          <?= count($g['teams']) ?> team<?= count($g['teams']) === 1 ? '' : 's' ?>
        </span>
      </h6>

      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light text-center">
            <tr>
              <th style="width:60px">Rank</th>
              <th style="width:150px">Unit</th>
              <th style="width:150px">Team Name</th>
              <th style="width:90px">Comp. No.</th>
              <th>Member</th>
              <th style="width:110px">Member Score</th>
              <th style="width:120px" class="text-end">Team Total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($g['teams'] as $t):
              $members  = $t['members'] ?: [['competitor_number' => 0, 'athlete_name' => '', 'score' => null]];
              $rowspan  = max(1, count($members));
              $firstRow = true;
            ?>
              <?php foreach ($members as $m): ?>
                <tr<?= empty($t['all_scored']) ? ' class="table-secondary"' : '' ?>>
                  <?php if ($firstRow): ?>
                    <td rowspan="<?= $rowspan ?>" class="text-center fw-bold align-middle">
                      <?= $t['rank'] !== null ? (int)$t['rank'] : '<span class="text-muted">—</span>' ?>
                    </td>
                    <td rowspan="<?= $rowspan ?>" class="align-middle"><?= e($t['unit_name'] ?: '—') ?></td>
                    <td rowspan="<?= $rowspan ?>" class="fw-medium align-middle"><?= e($t['team_name']) ?></td>
                  <?php endif; ?>
                  <td class="text-center">
                    <?php if (!empty($m['competitor_number'])): ?>
                      <code class="fw-bold"><?= str_pad((string)(int)$m['competitor_number'], 4, '0', STR_PAD_LEFT) ?></code>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                  </td>
                  <td><?= e($m['athlete_name'] ?: '—') ?></td>
                  <td class="text-end small">
                    <?= $m['score'] !== null
                          ? e($fmtScore($m['score']))
                          : '<span class="text-muted fst-italic">—</span>' ?>
                  </td>
                  <?php if ($firstRow): ?>
                    <td rowspan="<?= $rowspan ?>" class="text-end fw-bold align-middle">
                      <?= !empty($t['all_scored'])
                            ? e($fmtScore($t['team_total']))
                            : '<span class="text-muted">—</span>' ?>
                    </td>
                  <?php endif; ?>
                </tr>
                <?php $firstRow = false; ?>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<script>
const TRL_DATA = {
  event_name:       <?= json_encode($event['name'] ?? '') ?>,
  event_code:       <?= json_encode($event['event_code'] ?? '') ?>,
  institution_name: <?= json_encode($event['institution_name'] ?? '') ?>,
  event_logo:       <?= json_encode($event['logo'] ?? '') ?>,
  location:         <?= json_encode($event['location'] ?? '') ?>,
  category: {
    name:         <?= json_encode($selectedCategory['name'] ?? '') ?>,
    abbreviation: <?= json_encode($selectedCategory['abbreviation'] ?? '') ?>,
  },
  groups: <?= json_encode(array_values(array_map(function ($g) use ($fmtScore) {
    return [
      'event_code'    => (string)($g['event_code'] ?? ''),
      'sport_event'   => (string)($g['sport_event'] ?? ''),
      'category_abbr' => (string)($g['category_abbr'] ?? ''),
      'category'      => (string)($g['category'] ?? ''),
      'teams'         => array_map(function ($t) use ($fmtScore) {
        $members = array_map(function ($m) use ($fmtScore) {
          $cn = (int)($m['competitor_number'] ?? 0);
          return [
            'comp_no'      => $cn ? str_pad((string)$cn, 4, '0', STR_PAD_LEFT) : '',
            'athlete_name' => (string)($m['athlete_name'] ?? ''),
            'score'        => $m['score'] !== null ? $fmtScore($m['score']) : '',
          ];
        }, $t['members'] ?? []);
        return [
          'rank'        => $t['rank'] !== null ? (int)$t['rank'] : '',
          'unit_name'   => (string)($t['unit_name'] ?? ''),
          'team_name'   => (string)($t['team_name'] ?? ''),
          'members'     => $members,
          'team_total'  => !empty($t['all_scored']) ? $fmtScore($t['team_total']) : '',
          'all_scored'  => !empty($t['all_scored']),
        ];
      }, $g['teams']),
    ];
  }, $groups ?? []))) ?>,
};

function trlEsc(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g,
    c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function printTeamRankList() {
  const groups = TRL_DATA.groups || [];

  const groupSections = groups.map(g => {
    const teamRows = (g.teams || []).map(t => {
      const members  = t.members && t.members.length ? t.members
                       : [{comp_no:'', athlete_name:'', score:''}];
      const rowspan  = Math.max(1, members.length);
      const trCls    = t.all_scored ? '' : ' class="noscore"';
      return members.map((m, idx) => {
        const lead = idx === 0;
        return `<tr${trCls}>
          ${lead ? `<td rowspan="${rowspan}" class="text-center fw-bold align-middle">${t.rank || ''}</td>` : ''}
          ${lead ? `<td rowspan="${rowspan}" class="align-middle">${trlEsc(t.unit_name)}</td>` : ''}
          ${lead ? `<td rowspan="${rowspan}" class="align-middle fw-medium">${trlEsc(t.team_name)}</td>` : ''}
          <td class="text-center fw-bold">${trlEsc(m.comp_no)}</td>
          <td>${trlEsc(m.athlete_name)}</td>
          <td class="text-end">${trlEsc(m.score)}</td>
          ${lead ? `<td rowspan="${rowspan}" class="text-end fw-bold align-middle">${trlEsc(t.team_total)}</td>` : ''}
        </tr>`;
      }).join('');
    }).join('') || `<tr><td colspan="7" class="text-center text-muted">No teams.</td></tr>`;

    const catBadge = g.category_abbr || g.category || '';
    return `<section class="event-block">
      <table class="lane-table">
        <colgroup>
          <col style="width:14mm">  <!-- Rank -->
          <col style="width:42mm">  <!-- Unit -->
          <col style="width:42mm">  <!-- Team Name -->
          <col style="width:18mm">  <!-- Comp. No. -->
          <col>                     <!-- Member -->
          <col style="width:22mm">  <!-- Member Score -->
          <col style="width:26mm">  <!-- Team Total -->
        </colgroup>
        <thead>
          <tr><td class="event-strip" colspan="7">
            <span class="evt-code">${trlEsc(g.event_code || '—')}</span>
            <span class="evt-name">${trlEsc(g.sport_event || '')}</span>
            ${catBadge ? `<span class="evt-cat">${trlEsc(catBadge)}</span>` : ''}
          </td></tr>
          <tr>
            <th>Rank</th>
            <th>Unit</th>
            <th>Team Name</th>
            <th>Comp. No.</th>
            <th>Member</th>
            <th>Member Score</th>
            <th>Team Total</th>
          </tr>
        </thead>
        <tbody>${teamRows}</tbody>
      </table>
    </section>`;
  }).join('');

  const logo  = TRL_DATA.event_logo
    ? `<img src="${trlEsc(TRL_DATA.event_logo)}" alt="" class="event-logo">` : '';
  const evMeta = [TRL_DATA.institution_name, TRL_DATA.location].filter(Boolean).map(trlEsc).join(' · ');
  const catLine = [TRL_DATA.category.name, TRL_DATA.category.abbreviation ? `(${TRL_DATA.category.abbreviation})` : '']
                    .filter(Boolean).join(' ');

  const html = `<!doctype html>
<html><head>
<meta charset="utf-8">
<title>Team — Rank List — ${trlEsc(TRL_DATA.event_name)}</title>
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
  table.lane-table tbody tr { page-break-inside: avoid; }
  table.lane-table th, table.lane-table td {
    border:1px solid #555; padding:4px 6px; font-size:9.5pt;
    vertical-align:middle; word-wrap:break-word; overflow:hidden;
  }
  table.lane-table thead th { background:#e9ecef; font-size:9pt; text-align:center; }
  td.event-strip { background:#f5f7fa; border:1px solid #d0d6dd; padding:5px 8px; text-align:left; font-size:11pt; }
  td.event-strip .evt-code { font-family: ui-monospace, Menlo, Consolas, monospace; background:#fff; padding:1px 5px; border:1px solid #cfd6dd; border-radius:3px; margin-right:6px; }
  td.event-strip .evt-name { font-weight:700; }
  td.event-strip .evt-cat  { display:inline-block; margin-left:8px; padding:1px 6px;
                             background:#eef2f7; color:#3c4859; border-radius:8px;
                             font-size:9pt; font-weight:600; }
  .fw-bold { font-weight:700; }
  .fw-medium { font-weight:500; }
  .text-center { text-align:center; }
  .text-end { text-align:right; }
  .align-middle { vertical-align:middle; }
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
    <h1>${trlEsc(TRL_DATA.event_name)}${TRL_DATA.event_code ? ' · ' + trlEsc(TRL_DATA.event_code) : ''}</h1>
    ${evMeta ? `<div class="meta">${evMeta}</div>` : ''}
    <h2>Team — Rank List${catLine ? ' · ' + trlEsc(catLine) : ''}</h2>
  </div>
</header>
${groupSections || '<p class="text-center text-muted">No teams.</p>'}
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
