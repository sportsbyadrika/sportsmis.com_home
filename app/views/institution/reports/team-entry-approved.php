<?php $pageTitle = 'Team Entry Approved List — ' . $event['name']; ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i>Team Entry Approved List</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <button class="btn btn-sm btn-outline-secondary ms-auto" type="button" onclick="printTeamApproved()">
    <i class="bi bi-printer me-1"></i>Print / PDF
  </button>
</div>

<p class="small text-muted mb-3">
  <i class="bi bi-info-circle me-1"></i>
  Approved team entries only, grouped by Unit / Club / Institution.
</p>

<div class="sms-card p-3">
  <?php if (empty($teams)): ?>
    <p class="text-muted small mb-0 text-center py-3">No approved team entries for this event yet.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:50px">#</th>
          <th>Unit — Code &amp; Address</th>
          <th>Event — Code &amp; Label</th>
          <th>Team Name</th>
          <th>Team Members</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($teams as $i => $t): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td>
              <div><code>#<?= (int)$t['unit_id'] ?></code> <span class="fw-medium"><?= e($t['unit_name'] ?? '—') ?></span></div>
              <?php if (!empty($t['unit_address'])): ?>
                <small class="text-muted"><?= e($t['unit_address']) ?></small>
              <?php endif; ?>
            </td>
            <td>
              <div><code><?= e($t['event_code'] ?? '—') ?></code></div>
              <small class="text-muted">
                <?= e($t['sport_name'] ?? '') ?>
                <?php if (!empty($t['sport_event_name'])): ?> · <?= e($t['sport_event_name']) ?><?php endif; ?>
              </small>
            </td>
            <td class="fw-medium"><?= e($t['team_name']) ?></td>
            <td>
              <?php if (empty($t['members'])): ?>
                <span class="text-muted">—</span>
              <?php else: ?>
                <ol class="mb-0 ps-3 small">
                  <?php foreach ($t['members'] as $m): ?>
                    <li>
                      <strong>Comp No <?= $m['competitor_number'] ? (int)$m['competitor_number'] : '—' ?></strong>
                      — <?= e($m['athlete_name'] ?? '') ?>
                    </li>
                  <?php endforeach; ?>
                </ol>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light">
        <tr>
          <th colspan="4" class="text-end">Total Approved Teams</th>
          <th><?= count($teams) ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
const TEA_DATA = {
  event_name:        <?= json_encode($event['name'] ?? '') ?>,
  event_code:        <?= json_encode($event['event_code'] ?? '') ?>,
  institution_name:  <?= json_encode($event['institution_name'] ?? '') ?>,
  event_logo:        <?= json_encode($event['logo'] ?? '') ?>,
  location:          <?= json_encode($event['location'] ?? '') ?>,
  event_date_from:   <?= json_encode($event['event_date_from'] ?? '') ?>,
  event_date_to:     <?= json_encode($event['event_date_to'] ?? '') ?>,
  teams:             <?= json_encode(array_map(function ($t) {
    return [
      'unit_id'         => (int)($t['unit_id'] ?? 0),
      'unit_name'       => (string)($t['unit_name'] ?? ''),
      'unit_address'    => (string)($t['unit_address'] ?? ''),
      'event_code'      => (string)($t['event_code'] ?? ''),
      'sport_name'      => (string)($t['sport_name'] ?? ''),
      'sport_event_name'=> (string)($t['sport_event_name'] ?? ''),
      'team_name'       => (string)($t['team_name'] ?? ''),
      'members'         => array_map(fn($m) => [
        'competitor_number' => (int)($m['competitor_number'] ?? 0),
        'athlete_name'      => (string)($m['athlete_name'] ?? ''),
      ], $t['members'] ?? []),
    ];
  }, $teams ?? [])) ?>,
};

function teaEsc(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g,
    c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function teaFormatDate(d) {
  if (!d) return '';
  const dt = new Date(d + 'T00:00:00');
  if (isNaN(dt)) return d;
  return dt.toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric' });
}

function printTeamApproved() {
  const teams = TEA_DATA.teams || [];
  const dateRange = (() => {
    const a = teaFormatDate(TEA_DATA.event_date_from);
    const b = teaFormatDate(TEA_DATA.event_date_to);
    if (a && b && a !== b) return a + ' – ' + b;
    return a || b || '';
  })();

  const logo = TEA_DATA.event_logo
    ? `<img src="${teaEsc(TEA_DATA.event_logo)}" alt="" class="event-logo">`
    : '';
  const meta = [TEA_DATA.institution_name, TEA_DATA.location, dateRange].filter(Boolean)
                .map(teaEsc).join(' · ');

  let body = '';
  if (!teams.length) {
    body = `<tr><td colspan="5" class="text-muted text-center py-3">No approved team entries.</td></tr>`;
  } else {
    teams.forEach((t, i) => {
      const eventLabel = [t.sport_name, t.sport_event_name].filter(Boolean).map(teaEsc).join(' · ');
      const membersHtml = (t.members || []).length
        ? '<ol class="member-list">'
          + t.members.map(m =>
            `<li><strong>Comp No ${m.competitor_number || '—'}</strong> — ${teaEsc(m.athlete_name)}</li>`
          ).join('')
          + '</ol>'
        : '<span class="muted">—</span>';
      body += `<tr>
        <td class="text-center">${i + 1}</td>
        <td><div><code>#${t.unit_id || '—'}</code> <strong>${teaEsc(t.unit_name) || '—'}</strong></div>${t.unit_address ? `<div class="muted">${teaEsc(t.unit_address)}</div>` : ''}</td>
        <td><div><code>${teaEsc(t.event_code) || '—'}</code></div>${eventLabel ? `<div class="muted">${eventLabel}</div>` : ''}</td>
        <td class="fw-bold">${teaEsc(t.team_name)}</td>
        <td>${membersHtml}</td>
      </tr>`;
    });
  }

  const html = `<!doctype html>
<html><head>
<meta charset="utf-8">
<title>Team Entry Approved List — ${teaEsc(TEA_DATA.event_name)}</title>
<style>
  @page { size: A4 landscape; margin: 12mm 10mm 16mm 10mm;
          @bottom-right { content: "Page " counter(page) " of " counter(pages);
                          font-size: 8pt; color:#666; } }
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
         color:#111; margin:0; padding:0; }
  .head { display:flex; align-items:center; gap:14px;
          border-bottom:2px solid #333; padding-bottom:8px; margin-bottom:10px; }
  .head .event-logo { width:56px; height:56px; object-fit:contain; flex-shrink:0; }
  .head .text { flex:1; min-width:0; }
  .head h1 { font-size:14pt; margin:0 0 2px; }
  .head .meta { font-size:9.5pt; color:#555; }
  .head h2 { font-size:11pt; margin:4px 0 0; }
  table { width:100%; border-collapse:collapse; table-layout:fixed; font-size:10pt; }
  thead { display:table-header-group; }
  tfoot { display:table-footer-group; }
  th, td { border:1px solid #555; padding:5px 7px; vertical-align:top;
           word-wrap:break-word; overflow:hidden; }
  thead th { background:#e9ecef; text-align:left; font-size:9pt;
             text-transform:uppercase; letter-spacing:.02em; }
  tfoot th { background:#f1f3f5; font-size:9pt; }
  tr { page-break-inside: avoid; }
  .muted { color:#555; font-size:9pt; }
  .fw-bold { font-weight:700; }
  .text-center { text-align:center; }
  .text-end { text-align:right; }
  code { background:#f1f3f5; padding:1px 4px; border-radius:3px; font-size:9pt; }
  ol.member-list { margin:0; padding:0 0 0 18px; font-size:9.5pt; }
  ol.member-list li { margin:1px 0; }
  .actions { margin: 8px 0; }
  @media print { .actions { display:none; } }
</style>
</head><body>
<div class="actions">
  <button onclick="window.print()" style="padding:4px 10px">Print</button>
  <button onclick="window.close()" style="padding:4px 10px;margin-left:4px">Close</button>
</div>
<div class="head">
  ${logo}
  <div class="text">
    <h1>${teaEsc(TEA_DATA.event_name)}${TEA_DATA.event_code ? ' · ' + teaEsc(TEA_DATA.event_code) : ''}</h1>
    ${meta ? `<div class="meta">${meta}</div>` : ''}
    <h2>Team Entry Approved List</h2>
  </div>
</div>
<table>
  <thead>
    <tr>
      <th style="width:14mm" class="text-center">#</th>
      <th style="width:62mm">Unit — Code &amp; Address</th>
      <th style="width:62mm">Event — Code &amp; Label</th>
      <th style="width:50mm">Team Name</th>
      <th>Team Members</th>
    </tr>
  </thead>
  <tbody>${body}</tbody>
  <tfoot>
    <tr><th colspan="4" class="text-end">Total Approved Teams</th>
        <th>${teams.length}</th></tr>
  </tfoot>
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
