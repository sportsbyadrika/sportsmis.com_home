<?php
/**
 * Public LED-wall slideshow — published medal-tally view.
 * Winner slides show three events per slide (event · 1st · 2nd · 3rd) with
 * athlete photos / unit logos. After every three winner slides the unit-wise
 * points table is shown, looping in a seamless circular scroll three times;
 * the page reloads just before each unit slide so the standings are fresh.
 * A live clock sits top-right and a brand footer runs along the bottom.
 * Expects: $event, $events (published event winners), $units (unit tally),
 *          $interval (slide seconds), $unit_scroll (sec per circular loop).
 */
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$interval   = max(3, min(60, (int)($interval ?? 8)));
$unitScroll = max(5, min(120, (int)($unit_scroll ?? 20)));   // sec per full scroll pass
$winnerSlides = array_chunk($events ?? [], 3);
$units = $units ?? [];

// Render one winner (individual: photo; team: unit logo + members).
$renderPlace = function (array $list) use ($h): string {
    if (empty($list)) return '<div class="win empty">—</div>';
    $out = '';
    foreach ($list as $p) {
        $isTeam = !empty($p['team_id']);
        $img    = $isTeam ? (string)($p['unit_logo'] ?? '') : (string)($p['photo'] ?? '');
        $chest  = (string)($p['chest'] ?? '');
        $name   = (string)($p['name'] ?? '');
        $unit   = (string)($p['unit'] ?? '');
        $sub    = $isTeam ? (string)($p['sub'] ?? '') : '';
        $ph = $img !== ''
            ? '<img src="' . $h($img) . '" alt="">'
            : '<span class="ph-ph"><i class="bi bi-' . ($isTeam ? 'people' : 'person') . '"></i></span>';
        $out .= '<div class="win">'
              . '<div class="ph' . ($isTeam ? ' team' : '') . '">' . $ph . '</div>'
              . '<div class="wi">'
              . '<div class="wn">' . ($chest !== '' ? '<span class="ch">' . $h($chest) . '</span> ' : '') . $h($name) . '</div>'
              . ($unit !== '' ? '<div class="wu">' . $h($unit) . '</div>' : '')
              . ($sub  !== '' ? '<div class="wm">' . $h($sub) . '</div>' : '')
              . '</div></div>';
    }
    return $out;
};
$evSub = function (array $e) use ($h): string {
    $bits = [];
    foreach (['category', 'age_name', 'gender'] as $k) {
        $v = trim((string)($e[$k] ?? ''));
        if ($v !== '') $bits[] = $h($v);
    }
    return implode(' &middot; ', $bits);
};
$hasDeck = !empty($winnerSlides) || !empty($units);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>LED Wall &mdash; <?= $h($event['name']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    :root { --bg-1:#04122a; --bg-2:#1a3470; --ink:#0b1f3a; --accent:#FFD23F;
            --gold:#FFE17B; --silver:#E5E7EB; --bronze:#E8B485; --row:rgba(255,255,255,.06); }
    * { box-sizing: border-box; }
    html,body { height:100%; margin:0; overflow:hidden; color:#fff;
                background: radial-gradient(circle at 20% 10%, #1a3470, #04122a);
                font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    .stage { position: fixed; inset: 0; display: flex; flex-direction: column; }
    .topbar { display:flex; align-items:center; gap:14px; padding:10px 22px;
              border-bottom:1px solid rgba(255,255,255,.10); flex:0 0 auto; }
    .topbar .logo { width:44px; height:44px; object-fit:contain; background:#fff; border-radius:8px; padding:4px; }
    .topbar .meta { flex:1; min-width:0; }
    .topbar h1 { margin:0; font-size:20px; font-weight:800; text-overflow:ellipsis; overflow:hidden; white-space:nowrap; }
    .topbar .sub { font-size:12px; color:#cbd5e1; margin-top:2px; }
    .topbar .actions { display:flex; align-items:center; gap:14px; flex:0 0 auto; }
    .topbar .clock { text-align:right; line-height:1.05; }
    .topbar .clock .t { font-size:22px; font-weight:800; font-variant-numeric:tabular-nums; letter-spacing:.02em; }
    .topbar .clock .d { font-size:12px; color:#cbd5e1; margin-top:1px; }
    .topbar .actions button { background:#0ea5e9; color:#fff; border:0; width:40px; height:40px;
                  border-radius:10px; cursor:pointer; display:inline-flex; align-items:center;
                  justify-content:center; font-size:18px; }

    .slide-host { flex:1 1 auto; position:relative; overflow:hidden; padding:1.4vh 2vw 3vh; }
    .slide { position:absolute; inset:1.4vh 2vw 3vh; display:none; flex-direction:column; }
    .slide.active { display:flex; }

    /* ── Winner slides ─────────────────────────────────────── */
    .wtable { display:flex; flex-direction:column; gap:1vh; height:100%; }
    .wrow { display:flex; gap:1vw; align-items:stretch; flex:1; }
    .wrow.whead { flex:0 0 auto; }
    .wc { flex:1 1 0; background:var(--row); border-radius:12px; padding:1vh 1.1vw;
          display:flex; flex-direction:column; justify-content:center; min-width:0; }
    .wc.ev { flex:1.25 1 0; background:rgba(255,210,63,.12); }
    .whead .wc { background:transparent; padding:.2vh 1.1vw; }
    .whead .lbl { font-size:2.1vh; font-weight:800; letter-spacing:.03em; text-transform:uppercase; }
    .whead .p1 .lbl { color:var(--gold); }
    .whead .p2 .lbl { color:var(--silver); }
    .whead .p3 .lbl { color:var(--bronze); }
    .wc.p1 { box-shadow: inset 4px 0 0 var(--gold); }
    .wc.p2 { box-shadow: inset 4px 0 0 var(--silver); }
    .wc.p3 { box-shadow: inset 4px 0 0 var(--bronze); }
    .evn { font-size:2.6vh; font-weight:800; line-height:1.1; }
    .evsub { font-size:1.7vh; color:#cbd5e1; margin-top:.4vh; }
    .win { display:flex; align-items:center; gap:.8vw; min-width:0; }
    .win + .win { margin-top:.7vh; padding-top:.7vh; border-top:1px solid rgba(255,255,255,.10); }
    .win.empty { color:#94a3b8; font-size:2.4vh; font-weight:700; }
    .ph { flex:0 0 auto; width:8vh; height:9.5vh; border-radius:8px; overflow:hidden;
          background:rgba(255,255,255,.12); }
    .ph img { width:100%; height:100%; object-fit:cover; }
    .ph.team img { object-fit:contain; background:#fff; }
    .ph-ph { display:flex; align-items:center; justify-content:center; width:100%; height:100%;
             font-size:4vh; color:#94a3b8; }
    .wi { min-width:0; }
    .wn { font-size:2.2vh; font-weight:800; line-height:1.12; }
    .wn .ch { display:inline-block; background:#0b1f3a; color:#fff; border-radius:5px;
              padding:0 .5vw; font-size:1.8vh; font-family:ui-monospace,monospace; }
    .wu { font-size:1.7vh; color:#cbd5e1; margin-top:.2vh; }
    .wm { font-size:1.4vh; color:#93c5fd; margin-top:.2vh; overflow:hidden;
          display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }

    /* ── Unit-wise points slide (seamless circular scroll) ──── */
    .units-head { font-size:3vh; font-weight:800; margin-bottom:.8vh; display:flex; align-items:center; gap:.6vw; }
    .units-head .cnt { background:var(--accent); color:var(--ink); border-radius:999px;
                       padding:.1vh 1vw; font-size:2vh; }
    .ut-colhead { display:flex; align-items:center; padding:.5vh 1vw; color:#cbd5e1; font-size:1.8vh;
                  text-transform:uppercase; letter-spacing:.04em; background:#0a1c3d;
                  border-bottom:2px solid rgba(255,255,255,.18); flex:0 0 auto; }
    .units-scroll { flex:1 1 auto; overflow:hidden; position:relative; }
    .ut-row { display:flex; align-items:center; padding:.9vh 1vw; font-size:2.3vh;
              border-bottom:1px solid rgba(255,255,255,.08); }
    .ut-row.top .uc.rk { color:var(--accent); }
    .uc { min-width:0; }
    .uc.rk { width:7vh; text-align:center; font-weight:800; }
    .uc.nm { flex:1 1 auto; font-weight:700; display:flex; align-items:center; gap:.6vw;
             white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .uc.c  { width:9vh; text-align:center; }
    .uc.pts{ width:12vh; text-align:right; font-weight:800; color:var(--accent); }
    .uc .ulogo { width:5vh; height:5vh; object-fit:contain; background:#fff; border-radius:6px; flex:0 0 auto; }
    /* Divider between the last and first unit as the list loops around. */
    .ut-sep { display:flex; align-items:center; justify-content:center; gap:1vw; padding:.7vh 0; }
    .ut-sep::before, .ut-sep::after { content:""; flex:1 1 auto; height:0; border-top:2px dashed var(--accent); opacity:.7; }
    .ut-sep span { color:var(--accent); font-size:1.5vh; font-weight:700; letter-spacing:.08em; text-transform:uppercase; white-space:nowrap; }

    .empty-state { position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                   flex-direction:column; gap:1vh; color:#93a4c3; text-align:center; padding:0 6vw; }
    .empty-state i { font-size:9vh; opacity:.5; }
    .empty-state h2 { font-size:3.4vh; margin:0; }

    /* Bottom timer bar + countdown chip + brand footer (on every slide). */
    .led-timer { position:fixed; left:0; right:0; bottom:0; height:6px;
                 background:rgba(255,255,255,.10); z-index:50; }
    .led-timer .fill { height:100%; width:0; background:var(--accent); }
    .led-count { position:fixed; right:16px; bottom:10px; z-index:51;
                 font-size:1.6vh; font-weight:700; color:#0b1f3a; background:var(--accent);
                 padding:.3vh 1vw; border-radius:999px; box-shadow:0 2px 8px rgba(0,0,0,.35); }
    .led-foot { position:fixed; left:0; right:0; bottom:10px; z-index:49; text-align:center;
                font-size:1.5vh; font-weight:600; color:#9fb0cd; pointer-events:none; }
  </style>
</head>
<body>
<div class="stage">
  <div class="topbar">
    <?php if (!empty($event['logo'])): ?><img class="logo" src="<?= $h($event['logo']) ?>" alt=""><?php endif; ?>
    <div class="meta">
      <h1><?= $h($event['name']) ?></h1>
      <div class="sub">Published Results &middot; Event-wise Winners &amp; Unit-wise Points</div>
    </div>
    <div class="actions">
      <div class="clock">
        <div class="t" id="clkTime">--:--:--</div>
        <div class="d" id="clkDate">&nbsp;</div>
      </div>
      <button id="fsBtn" type="button" title="Fullscreen (F)"><i class="bi bi-arrows-fullscreen"></i></button>
    </div>
  </div>

  <div class="slide-host">
    <?php if (!$hasDeck): ?>
      <div class="empty-state">
        <i class="bi bi-hourglass-split"></i>
        <h2>Results will appear here as soon as they are published.</h2>
      </div>
    <?php else: ?>

      <?php foreach ($winnerSlides as $slideEvents): ?>
        <section class="slide winners">
          <div class="wtable">
            <div class="wrow whead">
              <div class="wc ev"><span class="lbl">Event</span></div>
              <div class="wc p1"><span class="lbl">🥇 First</span></div>
              <div class="wc p2"><span class="lbl">🥈 Second</span></div>
              <div class="wc p3"><span class="lbl">🥉 Third</span></div>
            </div>
            <?php foreach ($slideEvents as $e): ?>
              <div class="wrow">
                <div class="wc ev">
                  <div class="evn"><?= $h($e['sport_event'] ?? '') ?></div>
                  <?php $sub = $evSub($e); if ($sub !== ''): ?><div class="evsub"><?= $sub ?></div><?php endif; ?>
                </div>
                <div class="wc p1"><?= $renderPlace($e['places'][1] ?? []) ?></div>
                <div class="wc p2"><?= $renderPlace($e['places'][2] ?? []) ?></div>
                <div class="wc p3"><?= $renderPlace($e['places'][3] ?? []) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>

      <?php if (!empty($units)):
        // Build the rows once; render twice so the scroll wraps seamlessly
        // (a circular / continuous loop rather than a jump back to the top).
        $unitRowsHtml = '';
        $i = 0;
        foreach ($units as $u) { $i++;
          $unitRowsHtml .= '<div class="ut-row' . ($i <= 3 ? ' top' : '') . '">'
            . '<div class="uc rk">' . $i . '</div>'
            . '<div class="uc nm">'
            . (!empty($u['logo']) ? '<img class="ulogo" src="' . $h($u['logo']) . '" alt="">' : '')
            . '<span>' . $h($u['unit'] ?? '') . '</span></div>'
            . '<div class="uc c">' . (int)($u['g'] ?? 0) . '</div>'
            . '<div class="uc c">' . (int)($u['s'] ?? 0) . '</div>'
            . '<div class="uc c">' . (int)($u['b'] ?? 0) . '</div>'
            . '<div class="uc pts">' . (int)($u['points'] ?? 0) . '</div>'
            . '</div>';
        }
        // Separator marking the wrap point (last → first) in the circular loop.
        $unitRowsHtml .= '<div class="ut-sep"><span>End of list</span></div>';
      ?>
        <section class="slide units">
          <div class="units-head"><i class="bi bi-buildings"></i> Unit-wise Points
            <span class="cnt"><?= count($units) ?></span></div>
          <div class="ut-colhead">
            <div class="uc rk">#</div><div class="uc nm">Unit / Institution</div>
            <div class="uc c">🥇</div><div class="uc c">🥈</div><div class="uc c">🥉</div><div class="uc pts">Points</div>
          </div>
          <div class="units-scroll">
            <div class="units-inner">
              <div class="ut-list"><?= $unitRowsHtml ?></div>
              <div class="ut-list" aria-hidden="true"><?= $unitRowsHtml ?></div>
            </div>
          </div>
        </section>
      <?php endif; ?>

    <?php endif; ?>
  </div>

  <div class="led-foot">SportsMIS.com&reg; by SportsByA Tech Private Limited</div>
  <?php if ($hasDeck): ?>
    <div class="led-count" id="ledCount">Next in <?= $interval ?>s</div>
  <?php endif; ?>
  <div class="led-timer"><div class="fill" id="ledFill"></div></div>
</div>

<script>
// Always on: live clock (top-right) + fullscreen toggle (button / F key).
(function () {
  var t = document.getElementById('clkTime'), d = document.getElementById('clkDate');
  function two(n){ return (n < 10 ? '0' : '') + n; }
  function tick(){
    var now = new Date(), hh = now.getHours(), ap = hh >= 12 ? 'PM' : 'AM', h12 = hh % 12 || 12;
    if (t) t.textContent = h12 + ':' + two(now.getMinutes()) + ':' + two(now.getSeconds()) + ' ' + ap;
    if (d) d.textContent = now.toLocaleDateString(undefined,
             { weekday:'short', day:'2-digit', month:'short', year:'numeric' });
  }
  tick(); setInterval(tick, 1000);

  function toggleFullscreen(){
    var el = document.documentElement;
    if (!document.fullscreenElement && el.requestFullscreen) el.requestFullscreen();
    else if (document.exitFullscreen) document.exitFullscreen();
  }
  var fs = document.getElementById('fsBtn');
  if (fs) fs.addEventListener('click', toggleFullscreen);
  document.addEventListener('keydown', function (e) {
    if (e.key && e.key.toLowerCase() === 'f') toggleFullscreen();
  });
})();
</script>

<?php if ($hasDeck): ?>
<script>
(function () {
  const INTERVAL      = <?= $interval * 1000 ?>;
  const UNIT_LOOPS    = 3;                  // circular loops of the unit list
  const UNIT_SECS     = <?= $unitScroll ?>;  // seconds for ONE loop of the list (higher = slower)
  const FIRST_HOLD_MS = 4000;              // pause before the FIRST loop (read the top ranks)

  const winners = Array.from(document.querySelectorAll('.slide.winners'));
  const unitEl  = document.querySelector('.slide.units');

  // Sequence: after every 3 winner slides, insert the unit-points slide.
  const seq = [];
  winners.forEach((el, i) => {
    seq.push({ type: 'w', el });
    if ((i + 1) % 3 === 0 && unitEl) seq.push({ type: 'u', el: unitEl });
  });
  if (unitEl && (winners.length % 3 !== 0 || winners.length === 0)) seq.push({ type: 'u', el: unitEl });
  if (!seq.length && unitEl) seq.push({ type: 'u', el: unitEl });
  const unitPositions = seq.map((s, i) => s.type === 'u' ? i : -1).filter(i => i >= 0);

  const fill  = document.getElementById('ledFill');
  const count = document.getElementById('ledCount');
  let timer = null, rafId = null, timerRaf = null;

  // Resume immediately after a data-refresh reload: land back on the unit slide.
  const RESUME_KEY = 'ledResumeUnitOrd';
  let idx = 0, skipRefreshOnce = false;
  try {
    const raw = sessionStorage.getItem(RESUME_KEY);
    if (raw !== null) {
      sessionStorage.removeItem(RESUME_KEY);
      const ord = parseInt(raw, 10);
      if (!isNaN(ord) && unitPositions.length) {
        idx = unitPositions[Math.min(Math.max(ord, 0), unitPositions.length - 1)];
        skipRefreshOnce = true;
      }
    }
  } catch (e) {}

  function clearTimers() { clearTimeout(timer); cancelAnimationFrame(rafId); cancelAnimationFrame(timerRaf); }

  // Bottom progress bar + "Next in Ns" countdown, driven over totalMs.
  function startTimer(totalMs) {
    cancelAnimationFrame(timerRaf);
    const start = Date.now();
    (function t() {
      const el  = Date.now() - start;
      const pct = Math.min(1, el / totalMs);
      if (fill)  fill.style.width = (pct * 100) + '%';
      if (count) count.textContent = 'Next in ' + Math.max(0, Math.ceil((totalMs - el) / 1000)) + 's';
      if (pct < 1) timerRaf = requestAnimationFrame(t);
    })();
  }

  function unitOneCopy(el) { const l = el.querySelector('.ut-list'); return l ? l.offsetHeight : 0; }
  function unitScrollable(el) {
    const s = el.querySelector('.units-scroll');
    return unitOneCopy(el) > (s ? s.clientHeight : 0) + 4;
  }
  function unitTotalMs(el) {
    if (!unitScrollable(el)) return INTERVAL * 2;             // whole list fits — just hold
    return FIRST_HOLD_MS + UNIT_LOOPS * UNIT_SECS * 1000;
  }

  // Seamless circular scroll: two identical stacked copies; when we pass one
  // copy's height we subtract it, so the view never jumps (continuous loop).
  function runUnit(el, done) {
    const scroller = el.querySelector('.units-scroll');
    scroller.scrollTop = 0;
    if (!unitScrollable(el)) { timer = setTimeout(done, INTERVAL * 2); return; }
    const oneCopy = unitOneCopy(el);
    const pps = oneCopy / UNIT_SECS;   // one full loop of the list per UNIT_SECS
    let loops = 0, pos = 0, last = null, holding = FIRST_HOLD_MS;
    function step(ts) {
      if (last === null) last = ts;
      const dt = ts - last; last = ts;
      if (holding > 0) { holding -= dt; rafId = requestAnimationFrame(step); return; }
      pos += pps * (dt / 1000);
      while (pos >= oneCopy) { pos -= oneCopy; loops++; }
      if (loops >= UNIT_LOOPS) { done(); return; }
      scroller.scrollTop = pos;
      rafId = requestAnimationFrame(step);
    }
    rafId = requestAnimationFrame(step);
  }

  function showCurrent() {
    clearTimers();
    seq.forEach(s => s.el.classList.remove('active'));
    const cur = seq[idx];
    cur.el.classList.add('active');
    if (cur.type === 'u') {
      if (!skipRefreshOnce) {
        // Refresh the data (fresh winners + standings) just before the unit-wise
        // result: reload the page, then resume on this same unit slide.
        const ord = unitPositions.indexOf(idx);
        try { sessionStorage.setItem(RESUME_KEY, String(ord < 0 ? 0 : ord)); } catch (e) {}
        location.reload();
        return;
      }
      skipRefreshOnce = false;
      startTimer(unitTotalMs(cur.el));
      runUnit(cur.el, next);
    } else {
      startTimer(INTERVAL);
      timer = setTimeout(next, INTERVAL);
    }
  }
  function next() { idx = (idx + 1) % seq.length; showCurrent(); }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight' || e.key === ' ') { e.preventDefault(); next(); }
  });

  showCurrent();
})();
</script>
<?php endif; ?>
</body>
</html>
