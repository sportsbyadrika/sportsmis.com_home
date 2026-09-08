<?php
$pageTitle = 'Call Room LED Wall';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];
$state = $state ?? [];
?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/order-of-events" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-tv me-2"></i>Call Room LED Wall</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <a href="/event-staff/call-room/wall" target="_blank" rel="noopener" class="btn btn-sm btn-success ms-auto">
    <i class="bi bi-display me-1"></i>Open LED Wall (new tab)
  </a>
</div>

<?= flashBag() ?>

<div class="row g-3">
  <!-- Control: pick event / round / heat -->
  <div class="col-lg-7">
    <div class="sms-card p-3 mb-3">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-broadcast me-1"></i>Show on the Wall</h6>
      <div class="btn-group btn-group-sm w-100 mb-3" role="group" aria-label="What to show">
        <input type="radio" class="btn-check" name="crMode" id="crModeHeat" value="heat"
               <?= ($state['mode'] ?? 'heat') === 'results' ? '' : 'checked' ?>>
        <label class="btn btn-outline-primary" for="crModeHeat"><i class="bi bi-people me-1"></i>Call Room — Heat</label>
        <input type="radio" class="btn-check" name="crMode" id="crModeResults" value="results"
               <?= ($state['mode'] ?? 'heat') === 'results' ? 'checked' : '' ?>>
        <label class="btn btn-outline-primary" for="crModeResults"><i class="bi bi-trophy me-1"></i>Results — Top 6</label>
        <input type="radio" class="btn-check" name="crMode" id="crModeMedal" value="medal"
               <?= ($state['mode'] ?? 'heat') === 'medal' ? 'checked' : '' ?>>
        <label class="btn btn-outline-primary" for="crModeMedal"><i class="bi bi-award me-1"></i>Medal Tally</label>
        <input type="radio" class="btn-check" name="crMode" id="crModeNmr" value="nmr"
               <?= ($state['mode'] ?? 'heat') === 'nmr' ? 'checked' : '' ?>>
        <label class="btn btn-outline-primary" for="crModeNmr"><i class="bi bi-stopwatch me-1"></i>New Meet Record</label>
      </div>

      <?php $selNmrEsid = (($state['mode'] ?? '') === 'nmr') ? (int)($state['event_sport_id'] ?? 0) : 0; ?>
      <div id="crNmrCfg" class="border rounded p-2 mb-3" hidden>
        <div class="small fw-semibold text-muted mb-1"><i class="bi bi-stopwatch me-1"></i>New Meet Record to display</div>
        <?php if (empty($nmr_json)): ?>
          <div class="small text-muted">No New Meet Records yet — a result must beat the event&rsquo;s standing meet record (set under Order of Events &rarr; Meet Records).</div>
        <?php else: ?>
          <select id="crNmrSel" class="form-select form-select-sm">
            <option value="">— Select a record —</option>
            <?php foreach (($nmr_json ?? []) as $n): ?>
              <option value="<?= (int)$n['esid'] ?>" <?= $selNmrEsid === (int)$n['esid'] ? 'selected' : '' ?>>
                <?= e($n['event']) ?><?= $n['sub'] !== '' ? ' — ' . e($n['sub']) : '' ?> · <?= e($n['new']) ?> (old <?= e($n['old']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </div>

      <?php
        $selAges = array_filter(array_map('intval', explode(',', (string)($state['medal_age_ids'] ?? ''))));
      ?>
      <div id="crMedalCfg" class="border rounded p-2 mb-3" hidden>
        <div class="small fw-semibold text-muted mb-1"><i class="bi bi-funnel me-1"></i>Age categories in the medal tally</div>
        <?php if (empty($age_cats)): ?>
          <div class="small text-muted">No age categories found for this event.</div>
        <?php else: ?>
          <div class="d-flex flex-wrap gap-3">
            <?php foreach (($age_cats ?? []) as $ac): ?>
              <div class="form-check form-check-inline m-0">
                <input class="form-check-input cr-age" type="checkbox" value="<?= (int)$ac['id'] ?>"
                       id="crAge<?= (int)$ac['id'] ?>" <?= in_array((int)$ac['id'], $selAges, true) ? 'checked' : '' ?>>
                <label class="form-check-label small" for="crAge<?= (int)$ac['id'] ?>"><?= e($ac['name']) ?></label>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="form-text small mb-0">Tick the age categories to count. Leave all unticked to include every age category.</div>
        <?php endif; ?>
      </div>

      <div class="row g-2">
        <div class="col-md-12">
          <label class="form-label small mb-1">Event</label>
          <select id="crEvent" class="form-select form-select-sm">
            <option value="">— Select event —</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label small mb-1">Round</label>
          <select id="crRound" class="form-select form-select-sm" disabled><option value="">—</option></select>
        </div>
        <div class="col-md-6">
          <label class="form-label small mb-1">Heat</label>
          <select id="crHeat" class="form-select form-select-sm" disabled><option value="">—</option></select>
        </div>
        <div class="col-md-6">
          <label class="form-label small mb-1">Heading top margin (px)</label>
          <input type="number" id="crTop" class="form-control form-control-sm" min="0" max="2000" step="10"
                 value="<?= (int)($state['head_top_px'] ?? 0) ?>" placeholder="0">
          <div class="form-text small">The event title starts this far below the top (to clear the background art).</div>
        </div>
        <div class="col-md-6">
          <label class="form-label small mb-1">Heading font size (px)</label>
          <input type="number" id="crFont" class="form-control form-control-sm" min="12" max="400" step="2"
                 value="<?= (int)($state['head_font_px'] ?? 0) ?: '' ?>" placeholder="Auto">
          <div class="form-text small">Blank = automatic size.</div>
        </div>
        <div class="col-12"><hr class="my-1"><div class="small fw-semibold text-muted"><i class="bi bi-bounding-box me-1"></i>Athlete-cards box (blank = default)</div></div>
        <div class="col-md-3">
          <label class="form-label small mb-1">Table top (px)</label>
          <input type="number" id="crTblTop" class="form-control form-control-sm" min="0" max="2000" step="10"
                 value="<?= (int)($state['table_top_px'] ?? 0) ?: '' ?>" placeholder="Auto">
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">Left (px)</label>
          <input type="number" id="crLeft" class="form-control form-control-sm" min="0" max="2000" step="10"
                 value="<?= (int)($state['margin_left_px'] ?? 0) ?: '' ?>" placeholder="Auto">
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">Right (px)</label>
          <input type="number" id="crRight" class="form-control form-control-sm" min="0" max="2000" step="10"
                 value="<?= (int)($state['margin_right_px'] ?? 0) ?: '' ?>" placeholder="Auto">
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">Bottom (px)</label>
          <input type="number" id="crBottom" class="form-control form-control-sm" min="0" max="2000" step="10"
                 value="<?= (int)($state['margin_bottom_px'] ?? 0) ?: '' ?>" placeholder="Auto">
        </div>
        <div class="col-12"><div class="form-text small">The cards table fills the box between these margins; card height shrinks so all rows fit.</div></div>
      </div>
      <div class="d-flex gap-2 mt-3">
        <button type="button" class="btn btn-primary btn-sm" id="crDisplayBtn" disabled>
          <i class="bi bi-play-fill me-1"></i><span id="crDisplayLbl">Display</span>
        </button>
        <button type="button" class="btn btn-outline-danger btn-sm" id="crClearBtn">
          <i class="bi bi-x-octagon me-1"></i>Clear Wall
        </button>
        <button type="button" class="btn btn-outline-success btn-sm" id="crFlowersBtn" title="Play a flower-shower celebration on the wall">
          🌸 Flowers
        </button>
        <span class="ms-auto small text-muted align-self-center" id="crLive"></span>
      </div>
    </div>

    <!-- Preview of the selected heat -->
    <div class="sms-card p-3">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-eye me-1"></i>Preview</h6>
      <div id="crPreviewHead" class="small text-muted mb-2">Select an event, round and heat to preview.</div>
      <div id="crPreview" class="row g-2"></div>
    </div>
  </div>

  <!-- Backgrounds -->
  <div class="col-lg-5">
    <div class="sms-card p-3">
      <h6 class="fw-semibold border-bottom pb-2 mb-3"><i class="bi bi-image me-1"></i>Backgrounds</h6>
      <p class="small text-muted">Pick a background for the wall, or upload your own (a wide 16:9 image works best).</p>
      <div class="row g-2 mb-3" id="crBgList">
        <div class="col-6">
          <label class="cr-bg d-block position-relative">
            <input type="radio" name="cr_bg" value="0" class="form-check-input position-absolute" style="top:6px;left:6px" checked>
            <span class="border rounded d-flex align-items-center justify-content-center text-muted"
                  style="height:70px;background:#0b1220">No background</span>
          </label>
        </div>
        <?php foreach (($backgrounds ?? []) as $bg): ?>
          <div class="col-6">
            <label class="cr-bg d-block position-relative">
              <input type="radio" name="cr_bg" value="<?= (int)$bg['id'] ?>" class="form-check-input position-absolute" style="top:6px;left:6px"
                     <?= (int)($state['background_id'] ?? 0) === (int)$bg['id'] ? 'checked' : '' ?>>
              <img src="<?= e($bg['image_path']) ?>" alt="" class="border rounded w-100" style="height:70px;object-fit:cover">
              <form method="POST" action="/event-staff/call-room/background/delete" class="position-absolute" style="top:4px;right:4px"
                    onsubmit="return confirm('Delete this background?');">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= (int)$bg['id'] ?>">
                <button class="btn btn-sm btn-danger py-0 px-1" title="Delete"><i class="bi bi-trash"></i></button>
              </form>
              <?php if (!empty($bg['label'])): ?><div class="small text-truncate"><?= e($bg['label']) ?></div><?php endif; ?>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
      <form method="POST" action="/event-staff/call-room/background" enctype="multipart/form-data" class="border-top pt-3">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <label class="form-label small mb-1">Upload background (JPG/PNG/WEBP)</label>
        <input type="file" name="background" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm mb-2" required>
        <input type="text" name="label" maxlength="120" class="form-control form-control-sm mb-2" placeholder="Label (optional)">
        <button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-upload me-1"></i>Upload</button>
      </form>
    </div>
  </div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="crToast" class="toast align-items-center border-0" role="alert">
    <div class="d-flex"><div class="toast-body fw-medium" id="crToastMsg"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div>
  </div>
</div>

<script>
const CR_EVENTS = <?= json_encode($events_json ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const CR_NMR = <?= json_encode($nmr_json ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const CR_CSRF = '<?= e($csrfToken) ?>';
const CR_STATE = { esid: <?= (int)($state['event_sport_id'] ?? 0) ?>, round: <?= (int)($state['round_id'] ?? 0) ?>, heat: <?= (int)($state['heat_no'] ?? 0) ?>, live: <?= !empty($state['is_live']) ? 1 : 0 ?> };
const $ = id => document.getElementById(id);
const esc = s => (s == null ? '' : String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])));

function crToast(msg, type) {
  const el = $('crToast');
  el.className = 'toast align-items-center border-0 text-bg-' + (type || 'primary');
  $('crToastMsg').textContent = msg;
  if (window.bootstrap && bootstrap.Toast) bootstrap.Toast.getOrCreateInstance(el, { delay: 2500 }).show();
}

function fillEvents() {
  const sel = $('crEvent');
  CR_EVENTS.forEach(ev => {
    const o = document.createElement('option');
    o.value = ev.esid; o.textContent = ev.label;
    sel.appendChild(o);
  });
}
function eventById(esid) { return CR_EVENTS.find(e => e.esid == esid); }

function fillRounds() {
  const ev = eventById($('crEvent').value);
  const rs = $('crRound');
  rs.innerHTML = '<option value="">—</option>';
  rs.disabled = !ev;
  if (ev) ev.rounds.forEach(r => {
    const o = document.createElement('option');
    o.value = r.id; o.textContent = r.name + ' (' + r.heats + ' heat' + (r.heats === 1 ? '' : 's') + ')';
    o.dataset.heats = r.heats;
    rs.appendChild(o);
  });
  fillHeats();
}
function fillHeats() {
  const opt = $('crRound').selectedOptions[0];
  const n = opt ? parseInt(opt.dataset.heats || '0', 10) : 0;
  const hs = $('crHeat');
  hs.innerHTML = '<option value="">—</option>';
  hs.disabled = !n;
  for (let i = 1; i <= n; i++) {
    const o = document.createElement('option'); o.value = i; o.textContent = 'Heat ' + i; hs.appendChild(o);
  }
  updateReady();
}
function updateReady() {
  var mode = crMode();
  if (mode === 'medal') {
    $('crDisplayBtn').disabled = false;
  } else if (mode === 'nmr') {
    var sel = $('crNmrSel');
    $('crDisplayBtn').disabled = !(sel && sel.value);
  } else {
    $('crDisplayBtn').disabled = !($('crRound').value && $('crHeat').value);
  }
  preview();
}

function crMode() {
  const r = document.querySelector('input[name="crMode"]:checked');
  return r ? r.value : 'heat';
}
function crAgeIds() {
  return Array.from(document.querySelectorAll('.cr-age:checked')).map(c => c.value);
}

async function preview() {
  const round = $('crRound').value, heat = $('crHeat').value;
  const head = $('crPreviewHead'), box = $('crPreview');
  const mode = crMode();
  const isResults = mode === 'results';
  if (mode === 'medal') { previewMedal(); return; }
  if (mode === 'nmr') { previewNmr(); return; }
  if (!round || !heat) { head.textContent = 'Select an event, round and heat to preview.'; box.innerHTML = ''; return; }
  try {
    const url = isResults
      ? '/event-staff/call-room/results.json?round_id=' + round + '&heat_no=' + heat
      : '/event-staff/call-room/heat.json?round_id=' + round + '&heat_no=' + heat;
    const res = await fetch(url);
    const d = await res.json();
    if (!d.ok) { head.textContent = isResults ? 'No data for these results.' : 'No data for this heat.'; box.innerHTML = ''; return; }
    // Finals drop the Heat label.
    const heatLbl = (isResults && d.is_final) ? '' : ' · Heat ' + d.heat;
    if (isResults) {
      head.innerHTML = '<strong>' + esc(d.event) + '</strong> · ' + esc(d.round) + heatLbl +
        ' <span class="badge bg-warning-subtle text-warning-emphasis">Top ' + d.athletes.length + '</span>';
      box.innerHTML = d.athletes.map(a => `
        <div class="col-6"><div class="border rounded d-flex align-items-center gap-2 p-1">
          <span class="badge bg-warning text-dark">${a.rank || '-'}</span>
          ${a.photo ? '<img src="' + esc(a.photo) + '" style="width:30px;height:36px;object-fit:cover;border-radius:.2rem">' : ''}
          <div class="small" style="min-width:0"><div class="fw-medium text-truncate">${a.bib ? '<code>' + a.bib + '</code> ' : ''}${esc(a.name)}</div>
          <div class="text-muted text-truncate">${esc(a.unit || '')}${a.time ? ' · ' + esc(a.time) : ''}</div></div>
        </div></div>`).join('') || '<div class="col-12 text-muted small">No results entered for this heat yet.</div>';
    } else {
      head.innerHTML = '<strong>' + esc(d.event) + '</strong> · ' + esc(d.round) + ' · Heat ' + d.heat +
        ' <span class="badge bg-secondary-subtle text-secondary-emphasis">' + d.athletes.length + ' athlete' + (d.athletes.length === 1 ? '' : 's') + '</span>';
      box.innerHTML = d.athletes.map(a => `
        <div class="col-6"><div class="border rounded d-flex align-items-center gap-2 p-1">
          <span class="badge bg-dark">${a.lane || '-'}</span>
          ${a.photo ? '<img src="' + esc(a.photo) + '" style="width:30px;height:36px;object-fit:cover;border-radius:.2rem">' : ''}
          <div class="small" style="min-width:0"><div class="fw-medium text-truncate">${a.bib ? '<code>' + a.bib + '</code> ' : ''}${esc(a.name)}</div>
          <div class="text-muted text-truncate">${esc(a.unit || '')}</div></div>
        </div></div>`).join('') || '<div class="col-12 text-muted small">No athletes assigned to this heat yet.</div>';
    }
  } catch (e) { head.textContent = 'Could not load preview.'; box.innerHTML = ''; }
}

async function previewMedal() {
  const head = $('crPreviewHead'), box = $('crPreview');
  try {
    const qs = crAgeIds().map(id => 'age_ids[]=' + encodeURIComponent(id)).join('&');
    const res = await fetch('/event-staff/call-room/medal.json' + (qs ? '?' + qs : ''));
    const d = await res.json();
    if (!d.ok || !(d.units || []).length) { head.textContent = 'No published medal results yet.'; box.innerHTML = ''; return; }
    const mp = d.max_position || 3;
    head.innerHTML = '<strong>' + esc(d.event) + '</strong> · Unit-wise Medal Tally' +
      ' <span class="badge bg-warning-subtle text-warning-emphasis">' + d.units.length + ' unit' + (d.units.length === 1 ? '' : 's') + '</span>';
    let extra = '';
    for (let p = 4; p <= mp; p++) extra += '<th class="text-center">' + p + '</th>';
    const rows = d.units.map(u => {
      let ex = '';
      for (let p = 4; p <= mp; p++) ex += '<td class="text-center">' + (u['p' + p] || 0) + '</td>';
      return '<tr><td class="text-center fw-bold">' + u.pos + '</td>' +
        '<td class="text-truncate">' + (u.logo ? '<img src="' + esc(u.logo) + '" style="width:20px;height:20px;object-fit:contain;border-radius:.2rem;margin-right:.3rem">' : '') + esc(u.unit) + '</td>' +
        '<td class="text-center">' + u.g + '</td><td class="text-center">' + u.s + '</td><td class="text-center">' + u.b + '</td>' +
        ex + '<td class="text-center fw-bold">' + u.points + '</td></tr>';
    }).join('');
    box.innerHTML = '<div class="col-12"><div class="table-responsive"><table class="table table-sm align-middle mb-0">' +
      '<thead><tr><th class="text-center">#</th><th>Unit</th><th class="text-center" title="Gold">🥇</th>' +
      '<th class="text-center" title="Silver">🥈</th><th class="text-center" title="Bronze">🥉</th>' + extra +
      '<th class="text-center">Pts</th></tr></thead><tbody>' + rows + '</tbody></table></div></div>';
  } catch (e) { head.textContent = 'Could not load medal tally.'; box.innerHTML = ''; }
}

function nmrById(esid) { return CR_NMR.find(n => String(n.esid) === String(esid)); }

function previewNmr() {
  const head = $('crPreviewHead'), box = $('crPreview');
  const sel = $('crNmrSel');
  const n = sel && sel.value ? nmrById(sel.value) : null;
  if (!n) { head.textContent = (CR_NMR.length ? 'Select a New Meet Record to preview.' : 'No New Meet Records yet.'); box.innerHTML = ''; return; }
  head.innerHTML = '<strong>' + esc(n.event) + '</strong>' + (n.sub ? ' · ' + esc(n.sub) : '') +
    ' <span class="badge bg-danger">NMR</span>';
  box.innerHTML =
    '<div class="col-12"><div class="border rounded p-3 d-flex align-items-center gap-3">' +
      (n.photo ? '<img src="' + esc(n.photo) + '" style="width:64px;height:80px;object-fit:cover;border-radius:.4rem;border:1px solid #ccc">' : '') +
      '<div class="flex-grow-1 text-center">' +
        '<div class="fw-bold">' + esc(n.athlete) + (n.bib ? ' <span class="text-muted">#' + n.bib + '</span>' : '') + '</div>' +
        (n.unit ? '<div class="small text-muted mb-2">' + esc(n.unit) + '</div>' : '<div class="mb-2"></div>') +
        '<div class="d-flex justify-content-center align-items-center gap-3">' +
          '<div><div class="small text-muted">OLD</div><div class="fs-5 text-decoration-line-through text-muted">' + esc(n.old) + '</div>' +
            (n.old_meta ? '<div class="small text-muted">' + esc(n.old_meta) + '</div>' : '') + '</div>' +
          '<div class="fs-4">&rarr;</div>' +
          '<div><div class="small text-muted">NEW</div><div class="fs-4 fw-bold text-danger">' + esc(n.new) + '</div></div>' +
        '</div>' +
      '</div>' +
    '</div></div>';
}

function crModeChanged() {
  const mode = crMode();
  const lbl = $('crDisplayLbl');
  const lblMap = { results: 'Display Results', medal: 'Display Medal Tally', nmr: 'Display Record' };
  if (lbl) lbl.textContent = lblMap[mode] || 'Display';
  // Medal Tally + NMR are not round/heat based.
  const noHeat = (mode === 'medal' || mode === 'nmr');
  $('crRound').disabled = noHeat || !$('crEvent').value;
  $('crHeat').disabled  = noHeat || !$('crRound').value;
  const mcfg = $('crMedalCfg'); if (mcfg) mcfg.hidden = (mode !== 'medal');
  const ncfg = $('crNmrCfg');   if (ncfg) ncfg.hidden = (mode !== 'nmr');
  updateReady();
}

async function post(url, fd) {
  fd.append('_token', CR_CSRF);
  const res = await fetch(url, { method: 'POST', body: fd });
  try { return await res.json(); } catch (_) { return { success: false, message: 'Error.' }; }
}
async function doDisplay() {
  const fd = new FormData();
  fd.append('round_id', $('crRound').value);
  fd.append('heat_no', $('crHeat').value);
  fd.append('mode', crMode());
  crAgeIds().forEach(id => fd.append('medal_age_ids[]', id));
  var nmrSel = $('crNmrSel');
  fd.append('nmr_esid', (crMode() === 'nmr' && nmrSel) ? (nmrSel.value || '') : '');
  const bg = document.querySelector('input[name="cr_bg"]:checked');
  fd.append('background_id', bg ? bg.value : '0');
  fd.append('head_top_px', $('crTop').value || '0');
  fd.append('head_font_px', $('crFont').value || '0');
  fd.append('table_top_px', $('crTblTop').value || '0');
  fd.append('margin_left_px', $('crLeft').value || '0');
  fd.append('margin_right_px', $('crRight').value || '0');
  fd.append('margin_bottom_px', $('crBottom').value || '0');
  const d = await post('/event-staff/call-room/display', fd);
  crToast(d.message, d.success ? 'success' : 'danger');
  if (d.success) $('crLive').innerHTML = '<i class="bi bi-broadcast text-success"></i> Live on the wall';
}
async function doClear() {
  const d = await post('/event-staff/call-room/clear', new FormData());
  crToast(d.message, d.success ? 'success' : 'danger');
  if (d.success) $('crLive').textContent = '';
}
async function doFlowers() {
  const d = await post('/event-staff/call-room/flowers', new FormData());
  crToast(d.message, d.success ? 'success' : 'danger');
}

document.addEventListener('DOMContentLoaded', () => {
  fillEvents();
  $('crEvent').addEventListener('change', fillRounds);
  $('crRound').addEventListener('change', fillHeats);
  $('crHeat').addEventListener('change', updateReady);
  $('crDisplayBtn').addEventListener('click', doDisplay);
  $('crClearBtn').addEventListener('click', doClear);
  $('crFlowersBtn').addEventListener('click', doFlowers);
  document.querySelectorAll('input[name="crMode"]').forEach(r => r.addEventListener('change', crModeChanged));
  document.querySelectorAll('.cr-age').forEach(c => c.addEventListener('change', () => { if (crMode() === 'medal') previewMedal(); }));
  var nmrSel = $('crNmrSel'); if (nmrSel) nmrSel.addEventListener('change', updateReady);
  crModeChanged();
  // Restore the current live selection.
  if (CR_STATE.esid) {
    $('crEvent').value = CR_STATE.esid; fillRounds();
    if (CR_STATE.round) { $('crRound').value = CR_STATE.round; fillHeats();
      if (CR_STATE.heat) { $('crHeat').value = CR_STATE.heat; updateReady(); } }
    if (CR_STATE.live) $('crLive').innerHTML = '<i class="bi bi-broadcast text-success"></i> Live on the wall';
  }
});
</script>
