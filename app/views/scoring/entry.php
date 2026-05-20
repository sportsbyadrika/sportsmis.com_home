<?php
$pageTitle = 'Score Entry — Relay ' . ($relay['relay_number'] ?? '');
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$st = $relay['result_status'] ?: 'pending';
[$stLabel, $stCls] = $statuses[$st] ?? ['—','bg-secondary'];
$cfg = $config ?: [
    'series_count' => $entry['series_count'] ?? 6,
    'shots_per_series' => $entry['shots_per_series'] ?? 10,
    'score_type' => $entry['score_type'] ?? 'integer',
    'category_id' => $entry['sport_category_id'] ?? null,
    'category_name' => null,
    'abbreviation' => null,
    'inner_ten' => false,
];
$availableCats = $prefill['categories'] ?? [];
$readOnly = !empty($view_only);
?>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="seToast" class="toast align-items-center border-0" role="alert">
    <div class="d-flex"><div class="toast-body fw-medium" id="seToastMsg"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div>
  </div>
</div>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-staff/scoring/relays/<?= (int)$relay['id'] ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i>Score Entry</h5>
  <span class="text-muted small ms-2">Relay <?= e($relay['relay_number']) ?> · Lane <?= (int)$lane['lane_number'] ?></span>
  <span class="badge <?= e($stCls) ?> ms-auto"><?= e($stLabel) ?></span>
</div>

<?php if ($locked): ?>
  <div class="alert alert-info d-flex align-items-center gap-2">
    <i class="bi bi-lock-fill"></i>
    <div>Relay result status is <strong>Final</strong> — scores are locked.</div>
  </div>
<?php endif; ?>

<!-- Panel 1 — Relay / Lane Details -->
<div class="sms-card p-3 mb-3">
  <h6 class="fw-semibold border-bottom pb-2 mb-2">Relay &amp; Lane</h6>
  <div class="row g-2 small">
    <div class="col-md-3"><span class="text-muted">Relay</span><br>
      <strong><?= e($relay['relay_number']) ?></strong> (Order <?= (int)($relay['order_no'] ?? 0) ?>)
    </div>
    <div class="col-md-3"><span class="text-muted">Date / Time</span><br>
      <?= !empty($relay['relay_date']) ? e(formatDate($relay['relay_date'], 'd M Y')) : '—' ?>
      <?php if (!empty($relay['match_time'])): ?> · <?= e(substr($relay['match_time'],0,5)) ?><?php endif; ?>
    </div>
    <div class="col-md-3"><span class="text-muted">Lane</span><br>
      <strong>Lane <?= (int)$lane['lane_number'] ?></strong> · <?= e(ucfirst((string)$lane['lane_type'])) ?>
    </div>
    <div class="col-md-3"><span class="text-muted">Lane Category</span><br>
      <?= e($lane['category'] ?: ($lane['default_category'] ?: '—')) ?>
    </div>
  </div>
</div>

<form id="seForm" onsubmit="return false">
  <input type="hidden" id="se_relay_id" value="<?= (int)$relay['id'] ?>">
  <input type="hidden" id="se_lane_id"  value="<?= (int)$lane['lane_id'] ?>">

  <!-- Panel 2 — Athlete Details -->
  <div class="sms-card p-3 mb-3">
    <h6 class="fw-semibold border-bottom pb-2 mb-2">Athlete</h6>
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small mb-1">Competitor No.</label>
        <input type="number" id="se_comp" class="form-control form-control-sm" min="1"
               value="<?= e($entry['competitor_number'] ?? ($lane['competitor_number'] ?? '')) ?>"
               <?= $readOnly ? 'disabled' : '' ?>>
      </div>
      <div class="col-md-2">
        <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="seFind()" <?= $readOnly ? 'disabled' : '' ?>>
          <i class="bi bi-search me-1"></i>Find
        </button>
      </div>
      <div class="col-md-7">
        <div class="d-flex gap-2 align-items-center" id="seAthBox">
          <?php if ($prefill): ?>
            <?php if (!empty($prefill['passport_photo'])): ?>
              <img src="<?= e($prefill['passport_photo']) ?>" width="42" height="42" class="rounded-circle" style="object-fit:cover">
            <?php else: ?>
              <div class="sms-avatar sms-avatar-sm"><?= e(substr($prefill['athlete_name'] ?? '?',0,1)) ?></div>
            <?php endif; ?>
            <div class="min-w-0 small">
              <div class="fw-medium" id="seAthName"><?= e($prefill['athlete_name'] ?? '') ?></div>
              <div class="text-muted">
                <?= e(ucfirst((string)($prefill['gender'] ?? ''))) ?>
                · <?= e(($prefill['unit_name'] ?? '—')) ?>
              </div>
            </div>
          <?php else: ?>
            <span class="text-muted small">Enter a competitor number and click Find.</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="row g-2 mt-2">
      <div class="col-md-4">
        <label class="form-label small mb-1">Event Category</label>
        <select id="se_category" class="form-select form-select-sm" onchange="seCategoryChange()" <?= $readOnly ? 'disabled' : '' ?>>
          <option value="">— Select —</option>
          <?php foreach ($availableCats as $c): ?>
            <option value="<?= (int)$c['id'] ?>"
                    <?= ($cfg['category_id'] ?? null) == $c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?><?= !empty($c['abbreviation']) ? ' ('.e($c['abbreviation']).')' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <!-- Panel 3 — Target Details -->
  <div class="sms-card p-3 mb-3">
    <h6 class="fw-semibold border-bottom pb-2 mb-2">Targets</h6>
    <div class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label small mb-1">Target From</label>
        <input type="number" id="se_target_from" class="form-control form-control-sm" min="1"
               value="<?= e($entry['target_from'] ?? '') ?>" oninput="seTargetCount()" <?= $readOnly ? 'disabled' : '' ?>>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Target To</label>
        <input type="number" id="se_target_to" class="form-control form-control-sm" min="1"
               value="<?= e($entry['target_to'] ?? '') ?>" oninput="seTargetCount()" <?= $readOnly ? 'disabled' : '' ?>>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Target Count</label>
        <input type="text" id="se_target_count" class="form-control form-control-sm bg-light" readonly>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">No. of Shots</label>
        <input type="number" id="se_shots_total" class="form-control form-control-sm" min="1"
               value="<?= (int)(($entry['series_count'] ?? $cfg['series_count']) * ($entry['shots_per_series'] ?? $cfg['shots_per_series'])) ?>"
               <?= $readOnly ? 'disabled' : '' ?>>
        <small class="text-muted">Series × Shots/series</small>
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Score Type (override)</label>
        <select id="se_score_type" class="form-select form-select-sm" onchange="seValidateAll()" <?= $readOnly ? 'disabled' : '' ?>>
          <option value="integer"  <?= ($entry['score_type'] ?? $cfg['score_type']) === 'integer'  ? 'selected' : '' ?>>Non-negative integer</option>
          <option value="decimal_1" <?= ($entry['score_type'] ?? $cfg['score_type']) === 'decimal_1' ? 'selected' : '' ?>>Decimal (1 dp)</option>
          <option value="decimal_2" <?= ($entry['score_type'] ?? $cfg['score_type']) === 'decimal_2' ? 'selected' : '' ?>>Decimal (2 dp)</option>
        </select>
      </div>
    </div>
  </div>

  <!-- Panel 4 — Score Grid -->
  <div class="sms-card p-3 mb-3">
    <h6 class="fw-semibold border-bottom pb-2 mb-2">Score Grid <small class="text-muted fw-normal" id="seCfgLabel"></small></h6>
    <div class="table-responsive">
      <table class="table table-bordered align-middle mb-0" id="seGrid" style="font-size:.9rem"></table>
    </div>
    <small class="text-muted">
      <i class="bi bi-info-circle me-1"></i>Tip: press <strong>Enter</strong> to jump to the next box ·
      type <strong>00</strong> to record a 10.
    </small>
  </div>

  <!-- Panel 5 — Remarks -->
  <div class="sms-card p-3 mb-3">
    <h6 class="fw-semibold border-bottom pb-2 mb-2">Remarks</h6>
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label small mb-1">Status</label>
        <select id="se_remarks" class="form-select form-select-sm" onchange="seToggleRemarks()" <?= $readOnly ? 'disabled' : '' ?>>
          <option value="">— None —</option>
          <option value="dns"          <?= ($entry['remarks'] ?? '') === 'dns'          ? 'selected':'' ?>>DNS — Did Not Start</option>
          <option value="dnf"          <?= ($entry['remarks'] ?? '') === 'dnf'          ? 'selected':'' ?>>DNF — Did Not Finish</option>
          <option value="disqualified" <?= ($entry['remarks'] ?? '') === 'disqualified' ? 'selected':'' ?>>Disqualified</option>
          <option value="other"        <?= ($entry['remarks'] ?? '') === 'other'        ? 'selected':'' ?>>Other</option>
        </select>
      </div>
      <div class="col-md-9">
        <label class="form-label small mb-1">Notes / Additional Remarks</label>
        <textarea id="se_notes" rows="2" class="form-control form-control-sm" <?= $readOnly ? 'disabled' : '' ?>><?= e($entry['notes'] ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <?php if (!$readOnly): ?>
  <div class="d-flex gap-2 justify-content-end mb-4">
    <button type="button" class="btn btn-success" onclick="seSave('here')">
      <i class="bi bi-save me-1"></i>Save
    </button>
    <button type="button" class="btn btn-primary" onclick="seSave('next_lane')">
      <i class="bi bi-save me-1"></i>Save &amp; Next Lane
    </button>
  </div>
  <?php endif; ?>
</form>

<script>
const CSRF       = '<?= e($csrfToken) ?>';
const READ_ONLY  = <?= $readOnly ? 'true' : 'false' ?>;
const ALL_CONFIGS= <?= json_encode($all_configs ?? []) ?>;
const INITIAL    = {
  config: <?= json_encode($cfg ?? []) ?>,
  series: <?= json_encode(array_map(fn($s) => [
      'series_no'    => (int)$s['series_no'],
      'shots'        => json_decode($s['shots_json'] ?? '[]', true) ?: [],
      'inner_tens'   => (int)($s['inner_tens'] ?? 0),
      'penalty'      => (float)($s['penalty'] ?? 0),
      'sub_total'    => (float)($s['sub_total'] ?? 0),
      'series_total' => (float)($s['series_total'] ?? 0),
  ], $series ?? [])) ?>,
};

function seToast(msg, type) {
  const el = document.getElementById('seToast');
  el.className = 'toast align-items-center border-0 text-bg-' + (type || 'primary');
  document.getElementById('seToastMsg').textContent = msg;
  if (window.bootstrap && bootstrap.Toast) bootstrap.Toast.getOrCreateInstance(el, {delay:2200}).show();
}
function esc(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g, c =>
    ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

let SERIES = 6, SHOTS = 10, SCORE_TYPE = 'integer', INNER_TEN = false;
function applyConfig(cfg) {
  if (!cfg) return;
  SERIES     = parseInt(cfg.series_count, 10) || SERIES;
  SHOTS      = parseInt(cfg.shots_per_series, 10) || SHOTS;
  SCORE_TYPE = document.getElementById('se_score_type').value || cfg.score_type || 'integer';
  INNER_TEN  = !!cfg.inner_ten;
  document.getElementById('se_shots_total').value = SERIES * SHOTS;
  document.getElementById('seCfgLabel').textContent =
    '— ' + (cfg.category_name || '') + ' · ' + SERIES + ' series × ' + SHOTS + ' shots';
  renderGrid();
}

function renderGrid() {
  const t = document.getElementById('seGrid');
  let head = '<thead class="table-light"><tr><th>Series</th>';
  for (let k = 1; k <= SHOTS; k++) head += `<th class="text-center">${k}</th>`;
  if (INNER_TEN) head += '<th class="text-center">X</th>';
  head += '<th class="text-end">Sub-Total</th><th class="text-end">Penalty</th><th class="text-end">Series Total</th></tr></thead>';

  let body = '<tbody>';
  for (let s = 1; s <= SERIES; s++) {
    const existing = INITIAL.series.find(x => x.series_no === s) || {};
    body += `<tr data-series="${s}"><th class="bg-light">S${s}</th>`;
    for (let k = 1; k <= SHOTS; k++) {
      const v = (existing.shots && existing.shots[k-1] != null) ? existing.shots[k-1] : '';
      body += `<td class="p-1"><input type="text" data-series="${s}" data-shot="${k}"
                 class="form-control form-control-sm text-center se-shot" value="${esc(v)}"
                 ${READ_ONLY ? 'disabled' : ''}></td>`;
    }
    if (INNER_TEN) {
      body += `<td class="p-1"><input type="number" min="0" data-series="${s}"
                 class="form-control form-control-sm text-center se-inner"
                 value="${esc(existing.inner_tens ?? 0)}" ${READ_ONLY ? 'disabled' : ''}></td>`;
    }
    body += `<td class="text-end fw-bold" data-sub="${s}">${(existing.sub_total ?? 0).toFixed(2)}</td>`;
    body += `<td class="p-1"><input type="number" step="0.01" min="0" data-pen="${s}"
                 class="form-control form-control-sm text-end se-pen"
                 value="${esc(existing.penalty ?? 0)}" ${READ_ONLY ? 'disabled' : ''}></td>`;
    body += `<td class="text-end fw-bold" data-tot="${s}">${(existing.series_total ?? 0).toFixed(2)}</td>`;
    body += '</tr>';
  }
  body += '</tbody><tfoot class="table-light"><tr>'
    + `<th class="text-end" colspan="${SHOTS + (INNER_TEN ? 1 : 0) + 1}">Grand Total</th>`
    + '<th class="text-end" id="seGrandSub">0.00</th>'
    + '<th class="text-end" id="seGrandPen">0.00</th>'
    + '<th class="text-end fw-bold fs-5" id="seGrand">0.00</th></tr></tfoot>';

  t.innerHTML = head + body;
  wireGrid();
  recomputeAll();
}

function wireGrid() {
  document.querySelectorAll('.se-shot, .se-pen, .se-inner').forEach(inp => {
    inp.addEventListener('input', onCellInput);
    inp.addEventListener('keydown', onCellKey);
  });
}

function normaliseShotValue(raw) {
  if (raw == null) return '';
  const s = String(raw).trim();
  if (s === '00') return '10';      // operator shortcut
  return s;
}
function isValidShot(raw) {
  if (raw === '' || raw == null) return true;
  if (!/^-?\d+(\.\d+)?$/.test(raw)) return false;
  const v = parseFloat(raw); if (v < 0) return false;
  if (SCORE_TYPE === 'integer'   && !/^\d+$/.test(raw)) return false;
  if (SCORE_TYPE === 'integer'   && v > 10) return false;
  if (SCORE_TYPE === 'decimal_1' && (v > 10.9 || (raw.split('.')[1]||'').length > 1)) return false;
  if (SCORE_TYPE === 'decimal_2' && (v > 10.99 || (raw.split('.')[1]||'').length > 2)) return false;
  return true;
}

function onCellInput(ev) {
  const inp = ev.target;
  if (inp.classList.contains('se-shot')) {
    // 00 → 10 shortcut applied on keyup so the operator can keep typing.
    if (inp.value === '00') inp.value = '10';
  }
  validateCell(inp);
  recomputeAll();
}
function validateCell(inp) {
  if (inp.classList.contains('se-shot')) {
    inp.classList.toggle('is-invalid', !isValidShot(inp.value));
  }
}
function seValidateAll() {
  SCORE_TYPE = document.getElementById('se_score_type').value;
  document.querySelectorAll('.se-shot').forEach(validateCell);
  recomputeAll();
}

/* Enter / Tab navigation — left-to-right, top-to-bottom across shot cells,
   then fall through to the penalty cells in order. */
function onCellKey(ev) {
  if (ev.key !== 'Enter' && ev.key !== 'ArrowRight' && ev.key !== 'ArrowDown') return;
  ev.preventDefault();
  const cells = [...document.querySelectorAll('.se-shot, .se-inner, .se-pen')]
                  .filter(c => !c.disabled);
  const idx = cells.indexOf(ev.target);
  const next = cells[idx + 1];
  if (next) next.focus(), next.select();
}

function recomputeAll() {
  let grandSub = 0, grandPen = 0, grandTot = 0;
  for (let s = 1; s <= SERIES; s++) {
    let sub = 0;
    document.querySelectorAll(`.se-shot[data-series="${s}"]`).forEach(inp => {
      const v = parseFloat(inp.value);
      if (!isNaN(v) && v >= 0) sub += v;
    });
    const pen = parseFloat((document.querySelector(`.se-pen[data-pen="${s}"]`) || {}).value) || 0;
    const tot = +(sub - pen).toFixed(2);
    const subCell = document.querySelector(`[data-sub="${s}"]`);
    const totCell = document.querySelector(`[data-tot="${s}"]`);
    if (subCell) subCell.textContent = sub.toFixed(2);
    if (totCell) totCell.textContent = tot.toFixed(2);
    grandSub += sub; grandPen += pen; grandTot += tot;
  }
  document.getElementById('seGrandSub').textContent = grandSub.toFixed(2);
  document.getElementById('seGrandPen').textContent = grandPen.toFixed(2);
  document.getElementById('seGrand').textContent    = grandTot.toFixed(2);
}

function seTargetCount() {
  const a = parseInt(document.getElementById('se_target_from').value, 10);
  const b = parseInt(document.getElementById('se_target_to').value, 10);
  document.getElementById('se_target_count').value =
    (!isNaN(a) && !isNaN(b) && b >= a) ? (b - a + 1) : '';
}

function seCategoryChange() {
  const id = document.getElementById('se_category').value;
  const cfg = id && ALL_CONFIGS[id] ? ALL_CONFIGS[id] : null;
  if (cfg) applyConfig(cfg);
}

function seToggleRemarks() {
  const v = document.getElementById('se_remarks').value;
  const disable = ['dns','dnf','disqualified'].includes(v);
  document.querySelectorAll('.se-shot, .se-pen, .se-inner').forEach(i => i.disabled = disable || READ_ONLY);
  document.getElementById('seGrid').classList.toggle('opacity-50', disable);
}

async function seFind() {
  const c = parseInt(document.getElementById('se_comp').value, 10);
  if (!c) { seToast('Enter a competitor number first.', 'warning'); return; }
  const res  = await fetch('/event-staff/scoring/lookup-competitor?competitor_number=' + c);
  const data = await res.json();
  if (!data.success) { seToast(data.message, 'danger'); return; }
  const a = data.data;
  document.getElementById('seAthBox').innerHTML =
    (a.passport_photo
      ? `<img src="${esc(a.passport_photo)}" width="42" height="42" class="rounded-circle" style="object-fit:cover">`
      : `<div class="sms-avatar sms-avatar-sm">${esc((a.athlete_name||'?').charAt(0))}</div>`)
    + `<div class="min-w-0 small">
         <div class="fw-medium">${esc(a.athlete_name)}</div>
         <div class="text-muted">
           ${esc((a.gender||'').replace(/^./,c=>c.toUpperCase()))}
           · ${esc(a.unit_name||'—')}
           ${a.age_categories && a.age_categories.length ? '· ' + a.age_categories.map(esc).join(' / ') : ''}
         </div>
       </div>`;
  const sel = document.getElementById('se_category');
  sel.innerHTML = '<option value="">— Select —</option>'
    + a.categories.map(c => `<option value="${c.id}">${esc(c.name)}${c.abbreviation ? ' (' + esc(c.abbreviation) + ')' : ''}</option>`).join('');
  Object.keys(a.configs || {}).forEach(k => { ALL_CONFIGS[k] = a.configs[k]; });
  if (a.categories.length) {
    sel.value = a.categories[0].id;
    seCategoryChange();
  }
}

async function seSave(next) {
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('relay_id', document.getElementById('se_relay_id').value);
  fd.append('lane_id',  document.getElementById('se_lane_id').value);
  fd.append('competitor_number', document.getElementById('se_comp').value);
  fd.append('sport_category_id', document.getElementById('se_category').value);
  fd.append('target_from', document.getElementById('se_target_from').value);
  fd.append('target_to',   document.getElementById('se_target_to').value);
  fd.append('series_count',     SERIES);
  fd.append('shots_per_series', SHOTS);
  fd.append('score_type',  document.getElementById('se_score_type').value);
  fd.append('remarks',     document.getElementById('se_remarks').value);
  fd.append('notes',       document.getElementById('se_notes').value);
  fd.append('next',        next === 'next_lane' ? 'next_lane' : 'here');

  for (let s = 1; s <= SERIES; s++) {
    document.querySelectorAll(`.se-shot[data-series="${s}"]`).forEach(inp => {
      fd.append(`shots[${s}][${inp.dataset.shot}]`, normaliseShotValue(inp.value));
    });
    const pen = document.querySelector(`.se-pen[data-pen="${s}"]`);
    if (pen) fd.append(`penalty[${s}]`, pen.value || 0);
    const inn = document.querySelector(`.se-inner[data-series="${s}"]`);
    if (inn) fd.append(`inner_tens[${s}]`, inn.value || 0);
  }

  const res  = await fetch('/event-staff/scoring/save', { method:'POST', body: fd });
  const data = await res.json();
  if (!data.success) { seToast(data.message || 'Save failed.', 'danger'); return; }
  seToast('Saved ✓', 'success');
  if (next === 'next_lane' && data.redirect) {
    setTimeout(() => window.location.href = data.redirect, 400);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  applyConfig(INITIAL.config);
  seTargetCount();
  seToggleRemarks();
});
</script>
