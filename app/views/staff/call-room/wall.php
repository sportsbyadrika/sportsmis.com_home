<?php
/**
 * Call Room LED Wall — full-screen display page (opened on the second monitor).
 * Polls /event-staff/call-room/state.json and renders the current heat over the
 * selected background. Rendered standalone (no app chrome).
 * Expects: $event.
 */
$evName = trim((string)($event['name'] ?? ''));
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Call Room LED Wall — <?= htmlspecialchars($evName, ENT_QUOTES) ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  [hidden] { display: none !important; }
  html, body { height: 100%; background: #05070d; overflow: hidden; font-family: "Segoe UI", Arial, sans-serif; }
  #bg { position: fixed; inset: 0; background: #05070d center/cover no-repeat;
        background-image: radial-gradient(circle at 50% 30%, #16264d, #05070d 70%); }
  #stage { position: fixed; inset: 0; }
  /* Heading — positioned from the top by --head-top. */
  #head { position: absolute; top: var(--head-top, 3.2vh); left: 0; right: 0;
          padding: 0 3vw; text-align: center; text-shadow: 0 2px 10px rgba(0,0,0,.7); }
  #evt { font-size: 3.4vw; font-weight: 800; color: #ffe08a; letter-spacing: .5px; line-height: 1.05; }
  #sub { font-size: 2.3vw; font-weight: 700; color: #fff; margin-top: .6vh; }
  #sub .heat { color: #7ee0ff; }
  /* Cards viewport (the athlete-cards box): two rows visible, four columns.
     Positioned by --table-top and the --mleft/--mright/--mbottom margins. */
  #vp { position: absolute; top: var(--table-top, 16vh); left: var(--mleft, 3vw);
        right: var(--mright, 3vw); bottom: var(--mbottom, 4vh); overflow: hidden; }
  /* Equal columns: minmax(0,1fr) stops a long name/photo from widening a track.
     Equal rows: grid-auto-rows pins every row to --cardh (set from JS so two
     rows exactly fill the box). */
  #cards { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));
           grid-auto-rows: var(--cardh, 40vh); gap: var(--cardgap, 1.6vh) 1.4vw;
           position: absolute; left: 0; right: 0; top: 0; will-change: transform; }
  .card { background: rgba(6, 16, 34, .62); border: 1px solid rgba(126,224,255,.45);
          border-radius: 1.1vh; padding: 1.2vh 1vw; display: flex; align-items: center; gap: 1vw;
          height: 100%; min-width: 0; overflow: hidden;
          box-shadow: 0 3px 16px rgba(0,0,0,.45); backdrop-filter: blur(2px); }
  .lane { flex: 0 0 auto; width: 5.6vh; height: 5.6vh; border-radius: 50%;
          background: linear-gradient(160deg,#1e5bd6,#0b2a6b); color: #fff; font-weight: 800;
          display: flex; align-items: center; justify-content: center; font-size: 2.6vh;
          border: 2px solid rgba(255,255,255,.35); }
  .photo { flex: 0 0 auto; width: 7vh; height: 8.6vh; object-fit: cover; border-radius: .8vh;
           border: 2px solid rgba(255,255,255,.4); background: #223; }
  .photo.ph { display: flex; align-items: center; justify-content: center; color: #6a86b6; font-size: 4vh; }
  .who { min-width: 0; flex: 1; }
  .nm { color: #fff; font-weight: 700; font-size: 2.2vh; line-height: 1.15;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .meta { color: #bcd2f5; font-size: 1.7vh; margin-top: .5vh; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .bib { color: #ffe08a; font-weight: 800; }
  #idle { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
          color: #9fb6df; font-size: 2.4vw; text-align: center; text-shadow: 0 2px 10px rgba(0,0,0,.7); }
  #fs { position: fixed; top: 10px; right: 12px; z-index: 5; background: rgba(0,0,0,.45); color: #fff;
        border: 1px solid rgba(255,255,255,.35); border-radius: 6px; padding: 6px 10px; cursor: pointer;
        font-size: 13px; opacity: .5; transition: opacity .2s; }
  #fs:hover { opacity: 1; }
  body.fs #fs { display: none; }
</style>
</head>
<body>
  <div id="bg"></div>
  <div id="stage">
    <div id="head" hidden>
      <div id="evt"></div>
      <div id="sub"></div>
    </div>
    <div id="vp">
      <div id="idle"><div><div style="font-size:3vw;font-weight:800;color:#ffe08a"><?= htmlspecialchars($evName, ENT_QUOTES) ?></div><div style="margin-top:1vh">Call Room — waiting for the next heat…</div></div></div>
      <div id="cards" hidden></div>
    </div>
  </div>
  <button id="fs" type="button">⛶ Full screen</button>

<script>
(function () {
  const bg = document.getElementById('bg');
  const stage = document.getElementById('stage');
  const head = document.getElementById('head'), evt = document.getElementById('evt'), sub = document.getElementById('sub');
  const vp = document.getElementById('vp'), cards = document.getElementById('cards'), idle = document.getElementById('idle');
  const esc = s => (s == null ? '' : String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])));
  let sig = '', curBg = '', raf = 0, scrollY = 0, lastT = 0, pauseUntil = 0;

  document.getElementById('fs').addEventListener('click', () => {
    if (!document.fullscreenElement) document.documentElement.requestFullscreen && document.documentElement.requestFullscreen();
    else document.exitFullscreen && document.exitFullscreen();
  });
  document.addEventListener('fullscreenchange', () => document.body.classList.toggle('fs', !!document.fullscreenElement));

  function setBackground(url) {
    if (url === curBg) return; curBg = url;
    bg.style.backgroundImage = url
      ? "url('" + url.replace(/'/g, "%27") + "')"
      : "radial-gradient(circle at 50% 30%, #16264d, #05070d 70%)";
  }

  function render(d) {
    // Layout controls from the control page (blank = CSS default via var()).
    const S = stage.style;
    S.setProperty('--head-top',  d.head_top      ? d.head_top + 'px'      : '');
    S.setProperty('--table-top', d.table_top     ? d.table_top + 'px'     : '');
    S.setProperty('--mleft',     d.margin_left   ? d.margin_left + 'px'   : '');
    S.setProperty('--mright',    d.margin_right  ? d.margin_right + 'px'  : '');
    S.setProperty('--mbottom',   d.margin_bottom ? d.margin_bottom + 'px' : '');
    evt.style.fontSize = d.head_font ? d.head_font + 'px' : '';
    // Two rows + one row-gap exactly fill the cards box, so all cards are equal
    // and the whole 4×2 table fits the box (extra rows keep the same height and
    // scroll). Read the box height AFTER the layout vars above are applied.
    const rowGap = Math.round(window.innerHeight * 0.016);
    const rowH = Math.max(40, (vp.clientHeight - rowGap) / 2);
    cards.style.setProperty('--cardgap', rowGap + 'px');
    cards.style.setProperty('--cardh', rowH + 'px');
    evt.textContent = d.event || '';
    sub.innerHTML = esc(d.round || '') + ' &nbsp;·&nbsp; <span class="heat">Heat ' + (d.heat || '') + '</span>' +
      (d.num_heats > 1 ? ' <span style="opacity:.7;font-size:.8em">of ' + d.num_heats + '</span>' : '');
    head.hidden = false; idle.hidden = true; cards.hidden = false;
    cards.innerHTML = (d.athletes || []).map(a => `
      <div class="card">
        <div class="lane">${a.lane || '-'}</div>
        ${a.photo ? '<img class="photo" src="' + esc(a.photo) + '">' : '<div class="photo ph">\u{1F464}</div>'}
        <div class="who">
          <div class="nm">${esc(a.name)}</div>
          <div class="meta"><span class="bib">${a.bib ? '#' + a.bib : ''}</span>${a.unit ? ' &nbsp; ' + esc(a.unit) : ''}</div>
        </div>
      </div>`).join('');
    scrollY = 0; cards.style.transform = 'translateY(0)'; pauseUntil = performance.now() + 2500;
    startScroll();
  }

  function showIdle() { head.hidden = true; cards.hidden = true; idle.hidden = false; cards.innerHTML = ''; stage.style.paddingTop = ''; stopScroll(); }

  function startScroll() {
    stopScroll();
    const step = (t) => {
      if (!lastT) lastT = t;
      const dt = t - lastT; lastT = t;
      const max = cards.scrollHeight - vp.clientHeight;
      if (max > 4 && t > pauseUntil) {
        scrollY += dt * 0.03;            // ~30px/sec
        if (scrollY >= max + 40) { scrollY = 0; pauseUntil = t + 1500; }  // loop with a pause
        cards.style.transform = 'translateY(' + (-Math.min(scrollY, max)) + 'px)';
      }
      raf = requestAnimationFrame(step);
    };
    lastT = 0; raf = requestAnimationFrame(step);
  }
  function stopScroll() { if (raf) cancelAnimationFrame(raf); raf = 0; lastT = 0; }

  async function poll() {
    try {
      const res = await fetch('/event-staff/call-room/state.json', { cache: 'no-store' });
      const d = await res.json();
      setBackground(d.background || '');
      if (d.live && d.ok) {
        const s = [d.round_id, d.round, d.heat, (d.athletes || []).length, d.updated_at].join('|');
        if (s !== sig) { sig = s; render(d); }
      } else {
        if (sig !== 'idle') { sig = 'idle'; showIdle(); }
      }
    } catch (e) { /* keep last frame on transient errors */ }
  }
  poll(); setInterval(poll, 2500);
  window.addEventListener('resize', () => { if (!cards.hidden && sig && sig !== 'idle') { const y = scrollY; sig = ''; poll(); } });
})();
</script>
</body>
</html>
