<?php
/**
 * Public LED-wall slideshow — published medal-tally view.
 * Winner slides show three events per slide (event · 1st · 2nd · 3rd) with
 * athlete photos / unit logos. After every three winner slides the unit-wise
 * points table is shown, auto-scrolling top-to-bottom three times.
 * Expects: $event, $events (published event winners), $units (unit tally),
 *          $interval (slide seconds).
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
    .topbar .actions button { background:#0ea5e9; color:#fff; border:0; padding:8px 14px;
                  border-radius:999px; font-weight:700; cursor:pointer; display:inline-flex;
                  align-items:center; gap:6px; font-size:14px; }

    .slide-host { flex:1 1 auto; position:relative; overflow:hidden; padding:1.6vh 2vw 1vh; }
    .slide { position:absolute; inset:1.6vh 2vw 1vh; display:none; flex-direction:column; }
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

    /* ── Unit-wise points slide ─────────────────────────────── */
    .units-head { font-size:3vh; font-weight:800; margin-bottom:1vh; display:flex; align-items:center; gap:.6vw; }
    .units-head .cnt { background:var(--accent); color:var(--ink); border-radius:999px;
                       padding:.1vh 1vw; font-size:2vh; }
    .units-scroll { flex:1 1 auto; overflow:hidden; }
    .units-inner { }
    table.ut { width:100%; border-collapse:collapse; font-size:2.3vh; }
    table.ut th { text-align:left; color:#cbd5e1; font-size:1.8vh; text-transform:uppercase;
                  letter-spacing:.04em; padding:.6vh 1vw; border-bottom:2px solid rgba(255,255,255,.18); position:sticky; top:0;
                  background:#0a1c3d; }
    table.ut td { padding:.9vh 1vw; border-bottom:1px solid rgba(255,255,255,.08); }
    table.ut .rk { width:6vh; text-align:center; font-weight:800; }
    table.ut tr.top .rk { color:var(--accent); }
    table.ut .ulogo { width:5vh; height:5vh; object-fit:contain; background:#fff; border-radius:6px; vertical-align:middle; }
    table.ut .uname { font-weight:700; }
    table.ut .c { text-align:center; width:8vh; }
    table.ut .pts { text-align:right; width:10vh; font-weight:800; color:var(--accent); }

    .empty-state { position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                   flex-direction:column; gap:1vh; color:#93a4c3; text-align:center; padding:0 6vw; }
    .empty-state i { font-size:9vh; opacity:.5; }
    .empty-state h2 { font-size:3.4vh; margin:0; }

    /* Bottom timer bar + countdown chip (shown on every slide). */
    .led-timer { position:fixed; left:0; right:0; bottom:0; height:7px;
                 background:rgba(255,255,255,.10); z-index:50; }
    .led-timer .fill { height:100%; width:0; background:var(--accent); }
    .led-count { position:fixed; right:16px; bottom:14px; z-index:51;
                 font-size:1.7vh; font-weight:700; color:#0b1f3a; background:var(--accent);
                 padding:.3vh 1vw; border-radius:999px; box-shadow:0 2px 8px rgba(0,0,0,.35); }
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
      <button id="fsBtn" type="button"><i class="bi bi-arrows-fullscreen"></i> Fullscreen</button>
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

      <?php if (!empty($units)): ?>
        <section class="slide units">
          <div class="units-head"><i class="bi bi-buildings"></i> Unit-wise Points
            <span class="cnt"><?= count($units) ?></span></div>
          <div class="units-scroll">
            <div class="units-inner">
              <table class="ut">
                <thead>
                  <tr><th class="rk">#</th><th>Unit / Institution</th>
                      <th class="c">🥇</th><th class="c">🥈</th><th class="c">🥉</th><th class="pts">Points</th></tr>
                </thead>
                <tbody>
                  <?php $i = 0; foreach ($units as $u): $i++; ?>
                    <tr class="<?= $i <= 3 ? 'top' : '' ?>">
                      <td class="rk"><?= $i ?></td>
                      <td class="uname">
                        <?php if (!empty($u['logo'])): ?><img class="ulogo" src="<?= $h($u['logo']) ?>" alt=""> <?php endif; ?>
                        <?= $h($u['unit'] ?? '') ?>
                      </td>
                      <td class="c"><?= (int)($u['g'] ?? 0) ?></td>
                      <td class="c"><?= (int)($u['s'] ?? 0) ?></td>
                      <td class="c"><?= (int)($u['b'] ?? 0) ?></td>
                      <td class="pts"><?= (int)($u['points'] ?? 0) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      <?php endif; ?>

    <?php endif; ?>
  </div>

  <?php if ($hasDeck): ?>
    <div class="led-count" id="ledCount">Next in <?= $interval ?>s</div>
    <div class="led-timer"><div class="fill" id="ledFill"></div></div>
  <?php endif; ?>
</div>

<?php if ($hasDeck): ?>
<script>
(function () {
  const INTERVAL       = <?= $interval * 1000 ?>;
  const UNIT_LOOPS     = 3;                 // scroll the unit table top→bottom this many times
  const UNIT_SECS      = <?= $unitScroll ?>; // seconds for ONE full top→bottom pass (higher = slower)
  const FIRST_HOLD_MS  = 4000;             // longer pause before the FIRST pass (read the top ranks)
  const HOLD_MS        = 1500;             // pause at the top before each later pass

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

  let idx = 0, timer = null, rafId = null, timerRaf = null;
  const fill  = document.getElementById('ledFill');
  const count = document.getElementById('ledCount');

  function clearTimers() { clearTimeout(timer); cancelAnimationFrame(rafId); cancelAnimationFrame(timerRaf); }

  // Bottom progress bar + "Next in Ns" countdown, driven over totalMs.
  function startTimer(totalMs) {
    cancelAnimationFrame(timerRaf);
    const start = Date.now();
    (function t() {
      const el  = Date.now() - start;
      const pct = Math.min(1, el / totalMs);
      fill.style.width = (pct * 100) + '%';
      count.textContent = 'Next in ' + Math.max(0, Math.ceil((totalMs - el) / 1000)) + 's';
      if (pct < 1) timerRaf = requestAnimationFrame(t);
    })();
  }

  // Scrollable height of the unit table (only valid once the slide is visible).
  function unitScrollMax(el) {
    const scroller = el.querySelector('.units-scroll');
    const inner    = el.querySelector('.units-inner');
    return Math.max(0, inner.scrollHeight - scroller.clientHeight);
  }
  function unitTotalMs(el) {
    const max = unitScrollMax(el);
    if (max < 6) return INTERVAL * 2;                 // fits — just hold
    const passMs = UNIT_SECS * 1000;
    return FIRST_HOLD_MS + passMs + (UNIT_LOOPS - 1) * (HOLD_MS + passMs);
  }

  function runUnit(el, done) {
    const scroller = el.querySelector('.units-scroll');
    scroller.scrollTop = 0;
    const max = unitScrollMax(el);
    if (max < 6) { timer = setTimeout(done, INTERVAL * 2); return; }   // fits — just hold
    const pps = max / UNIT_SECS;   // px/sec so one full pass takes UNIT_SECS seconds
    let loops = 0, pos = 0, last = null, holding = FIRST_HOLD_MS;      // longer first wait
    function step(ts) {
      if (last === null) last = ts;
      const dt = ts - last; last = ts;
      if (holding > 0) { holding -= dt; rafId = requestAnimationFrame(step); return; }
      pos += pps * (dt / 1000);
      if (pos >= max) {
        loops++;
        if (loops >= UNIT_LOOPS) { done(); return; }
        pos = 0; holding = HOLD_MS; scroller.scrollTop = 0;
        rafId = requestAnimationFrame(step); return;
      }
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
    if (cur.type === 'u') { startTimer(unitTotalMs(cur.el)); runUnit(cur.el, next); }
    else { startTimer(INTERVAL); timer = setTimeout(next, INTERVAL); }
  }
  function next() { idx = (idx + 1) % seq.length; showCurrent(); }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight' || e.key === ' ') { e.preventDefault(); next(); }
    if (e.key && e.key.toLowerCase() === 'f') toggleFullscreen();
  });
  function toggleFullscreen() {
    const el = document.documentElement;
    if (!document.fullscreenElement && el.requestFullscreen) el.requestFullscreen();
    else if (document.exitFullscreen) document.exitFullscreen();
  }
  document.getElementById('fsBtn').addEventListener('click', toggleFullscreen);

  showCurrent();
})();
</script>
<?php endif; ?>
</body>
</html>
