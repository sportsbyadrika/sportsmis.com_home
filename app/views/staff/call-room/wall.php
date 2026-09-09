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
  /* Vertical card: Unit (top) · [lane · photo · BIB] (centered) · Name (bottom). */
  .card { background: rgba(6, 16, 34, .62); border: 1px solid rgba(126,224,255,.45);
          border-radius: 1.1vh; padding: 1vh 1vw; display: flex; flex-direction: column;
          align-items: center; justify-content: space-between; gap: .6vh; text-align: center;
          height: 100%; min-width: 0; overflow: hidden;
          box-shadow: 0 3px 16px rgba(0,0,0,.45); backdrop-filter: blur(2px); }
  .unit { max-width: 100%; color: #bcd2f5; font-weight: 600; font-size: 1.9vh; line-height: 1.15;
          white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .unit.lanelabel { color: #7ee0ff; font-weight: 800; font-size: 2.6vh; letter-spacing: .04em; }
  .mid  { display: flex; align-items: center; justify-content: center; gap: 1.2vw; width: 100%; }
  .lane { flex: 0 0 auto; width: 5.4vh; height: 5.4vh; border-radius: 50%;
          background: linear-gradient(160deg,#1e5bd6,#0b2a6b); color: #fff; font-weight: 800;
          display: flex; align-items: center; justify-content: center; font-size: 2.6vh;
          border: 2px solid rgba(255,255,255,.35); }
  /* Results: rank badge (same footprint as .lane) with medal colours for 1/2/3. */
  .rank { flex: 0 0 auto; width: 5.4vh; height: 5.4vh; border-radius: 50%;
          background: linear-gradient(160deg,#1e5bd6,#0b2a6b); color: #fff; font-weight: 800;
          display: flex; align-items: center; justify-content: center; font-size: 2.8vh;
          border: 2px solid rgba(255,255,255,.35); }
  .rank.r1 { background: linear-gradient(160deg,#f6d365,#b8860b); color: #3a2c00; }
  .rank.r2 { background: linear-gradient(160deg,#e8edf3,#94a3b8); color: #1f2937; }
  .rank.r3 { background: linear-gradient(160deg,#e0a878,#a0522d); color: #2a1500; }
  .time { color: #ffe08a; font-weight: 700; font-size: 2.1vh; line-height: 1; }
  /* Medal tally table — dark theme, gold accents, aligned with the cards. */
  #cards.tallywrap { display: block; grid-auto-rows: initial; }
  table.tally { width: 100%; border-collapse: collapse; table-layout: fixed; color: #eaf1ff;
                text-shadow: 0 2px 8px rgba(0,0,0,.55); }
  table.tally th { font-size: 2.1vh; color: #7ee0ff; text-transform: uppercase; letter-spacing: .4px;
                   padding: 1.1vh .8vw; border-bottom: 2px solid rgba(126,224,255,.45); text-align: center; }
  table.tally th.u { text-align: left; }
  table.tally td { font-size: 2.7vh; padding: 1.05vh .8vw; text-align: center;
                   border-bottom: 1px solid rgba(126,224,255,.16); }
  table.tally td.u { text-align: left; font-weight: 700; white-space: nowrap;
                     overflow: hidden; text-overflow: ellipsis; }
  table.tally td.pos { color: #ffe08a; font-weight: 800; }
  table.tally td.pos.p1 { color: #f6d365; } table.tally td.pos.p2 { color: #e8edf3; } table.tally td.pos.p3 { color: #e0a878; }
  table.tally td.pts { color: #ffe08a; font-weight: 800; font-size: 3vh; }
  table.tally tr:nth-child(even) td { background: rgba(6,16,34,.35); }
  table.tally .ulogo { width: 3.6vh; height: 3.6vh; object-fit: contain; vertical-align: middle;
                       margin-right: .6vw; border-radius: .4vh; background: rgba(255,255,255,.12); }
  /* New Meet Record — one big centered card with OLD vs NEW. */
  #cards.nmrwrap { display: flex; align-items: center; justify-content: center; grid-auto-rows: initial; }
  .nmrcard { background: rgba(6,16,34,.68); border: 2px solid rgba(255,224,138,.6); border-radius: 1.6vh;
             padding: 4vh 4vw; box-shadow: 0 6px 30px rgba(0,0,0,.5);
             backdrop-filter: blur(2px); max-width: 92%;
             display: flex; align-items: stretch; gap: 3.5vw; }
  .nmrcard .nmrphoto { flex: 0 0 auto; width: 24vh; align-self: stretch; object-fit: cover;
                       border-radius: 1.2vh; border: 2px solid rgba(255,224,138,.55); background: #223; }
  .nmrcard .nmrbody { flex: 1 1 auto; text-align: center; display: flex; flex-direction: column;
                      align-items: center; justify-content: center; }
  .nmrcard .tag { display: inline-block; background: #b02a37; color: #fff; font-weight: 800;
                  font-size: 3vh; letter-spacing: .15em; padding: .5vh 2vw; border-radius: .8vh; margin-bottom: 2.4vh; }
  .nmrcard .ath { color: #fff; font-weight: 800; font-size: 4.2vh; line-height: 1.1; text-transform: uppercase; }
  .nmrcard .un  { color: #bcd2f5; font-weight: 600; font-size: 2.6vh; margin-top: .6vh; }
  .nmrcard .vals { display: flex; align-items: center; justify-content: center; gap: 3vw; margin-top: 3vh; }
  .nmrcard .old .lab, .nmrcard .new .lab { color: #7ee0ff; font-size: 2vh; letter-spacing: .1em; }
  .nmrcard .old .v { color: #9fb6df; font-size: 4.4vh; font-weight: 700; text-decoration: line-through; }
  .nmrcard .old .m { color: #7f93b8; font-size: 1.9vh; margin-top: .4vh; }
  .nmrcard .arrow { color: #ffe08a; font-size: 5vh; font-weight: 800; }
  .nmrcard .new .v { color: #ffe08a; font-size: 6.5vh; font-weight: 900; line-height: 1; }
  .photo { flex: 0 0 auto; width: 7vh; height: 8.6vh; object-fit: cover; border-radius: .8vh;
           border: 2px solid rgba(255,255,255,.4); background: #223; }
  .photo.ph { display: flex; align-items: center; justify-content: center; color: #6a86b6; font-size: 4vh; }
  .bib { flex: 0 0 auto; color: #ffe08a; font-weight: 800; font-size: 4vh; line-height: 1; }
  .relay { color: #ffe08a; font-weight: 900; font-size: 7vh; line-height: 1; text-transform: uppercase; }
  .nm { max-width: 100%; color: #fff; font-weight: 700; font-size: 3vh; line-height: 1.15;
        text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  #idle { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
          color: #9fb6df; font-size: 2.4vw; text-align: center; text-shadow: 0 2px 10px rgba(0,0,0,.7); }
  #fs { position: fixed; top: 10px; right: 12px; z-index: 25; background: rgba(0,0,0,.45); color: #fff;
        border: 1px solid rgba(255,255,255,.35); border-radius: 6px; padding: 6px 10px; cursor: pointer;
        font-size: 13px; opacity: .5; transition: opacity .2s; }
  #fs:hover { opacity: 1; }
  #flowersBtn { position: fixed; top: 10px; right: 128px; z-index: 25; background: rgba(0,0,0,.45); color: #fff;
        border: 1px solid rgba(255,255,255,.35); border-radius: 6px; padding: 6px 10px; cursor: pointer;
        font-size: 13px; opacity: .5; transition: opacity .2s; }
  #flowersBtn:hover { opacity: 1; }
  body.fs #fs, body.fs #flowersBtn { display: none; }
  /* Flower shower — pure-CSS falling petals overlay. */
  #petals { position: fixed; inset: 0; overflow: hidden; pointer-events: none; z-index: 30; }
  #petals .petal { position: absolute; top: -12vh; will-change: transform, opacity;
                   animation-name: petal-fall; animation-timing-function: linear;
                   animation-iteration-count: 1; animation-fill-mode: forwards;
                   text-shadow: 0 2px 6px rgba(0,0,0,.35); }
  @keyframes petal-fall {
    0%   { transform: translate(0, -12vh) rotate(0deg);   opacity: 0; }
    8%   { opacity: 1; }
    100% { transform: translate(calc(var(--sway, 0) * 22vw), 114vh) rotate(680deg); opacity: .85; }
  }
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
  <button id="flowersBtn" type="button">🌸 Flowers</button>
  <button id="fs" type="button">⛶ Full screen</button>
  <div id="petals" hidden></div>

<script>
(function () {
  const bg = document.getElementById('bg');
  const stage = document.getElementById('stage');
  const head = document.getElementById('head'), evt = document.getElementById('evt'), sub = document.getElementById('sub');
  const vp = document.getElementById('vp'), cards = document.getElementById('cards'), idle = document.getElementById('idle');
  const esc = s => (s == null ? '' : String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])));
  let sig = '', curBg = '', raf = 0, scrollY = 0, lastT = 0, pauseUntil = 0, lastFlowers = null;

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

  // Flower shower — spawn falling petals for a few seconds (pure CSS animation).
  let petalTimer = 0;
  function playFlowers() {
    const layer = document.getElementById('petals');
    if (!layer) return;
    const glyphs = ['🌸', '🌺', '🌼', '🌷', '💮', '🏵️'];
    var html = '';
    for (var i = 0; i < 70; i++) {
      var dur = (4 + Math.random() * 4).toFixed(2);
      var delay = (Math.random() * 2.5).toFixed(2);
      var left = (Math.random() * 100).toFixed(2);
      var size = (2.4 + Math.random() * 3.4).toFixed(2);
      var sway = (Math.random() * 2 - 1).toFixed(2);
      html += '<div class="petal" style="left:' + left + 'vw;font-size:' + size + 'vh;'
            + 'animation-duration:' + dur + 's;animation-delay:' + delay + 's;--sway:' + sway + '">'
            + glyphs[i % glyphs.length] + '</div>';
    }
    layer.innerHTML = html;
    layer.hidden = false;
    clearTimeout(petalTimer);
    petalTimer = setTimeout(function () { layer.hidden = true; layer.innerHTML = ''; }, 11000);
  }
  document.getElementById('flowersBtn').addEventListener('click', playFlowers);

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
    const isResults = d.mode === 'results';
    const isMedal   = d.mode === 'medal';
    const isNmr     = d.mode === 'nmr';
    // Medal tally renders as a full-width scrolling table; NMR as one centered
    // card; heat/results use the card grid (4 cols for a heat, 3 for top-6).
    cards.classList.toggle('tallywrap', isMedal);
    cards.classList.toggle('nmrwrap', isNmr);
    evt.textContent = d.event || '';
    if (isNmr) {
      sub.innerHTML = '<span class="heat">NEW MEET RECORD</span>';
      head.hidden = false; idle.hidden = true; cards.hidden = false;
      cards.innerHTML =
        '<div class="nmrcard">' +
          (d.photo ? '<img class="nmrphoto" src="' + esc(d.photo) + '">' : '') +
          '<div class="nmrbody">' +
            '<div class="tag">NEW MEET RECORD</div>' +
            '<div class="ath">' + esc(d.athlete || '') + (d.bib ? ' <span style="color:#ffe08a">#' + d.bib + '</span>' : '') + '</div>' +
            (d.unit ? '<div class="un">' + esc(d.unit) + '</div>' : '') +
            '<div class="vals">' +
              '<div class="old"><div class="lab">OLD RECORD</div><div class="v">' + esc(d.old || '') + '</div>' +
                (d.old_meta ? '<div class="m">' + esc(d.old_meta) + '</div>' : '') + '</div>' +
              '<div class="arrow">&rarr;</div>' +
              '<div class="new"><div class="lab">NEW RECORD</div><div class="v">' + esc(d.new || '') + '</div></div>' +
            '</div>' +
          '</div>' +
        '</div>';
      stopScroll();
      return;
    }
    if (isMedal) {
      sub.innerHTML = '<span class="heat">UNIT-WISE MEDAL TALLY</span>';
      head.hidden = false; idle.hidden = true; cards.hidden = false;
      const mp = d.max_position || 3;
      let extraH = '', colg = '<col style="width:8%"><col>';
      let mCols = 3;                          // gold/silver/bronze
      for (let p = 4; p <= mp; p++) { extraH += '<th>' + p + '</th>'; mCols++; }
      // medal columns + points share the remaining width evenly-ish
      colg += '<col style="width:9%"><col style="width:9%"><col style="width:9%">';
      for (let p = 4; p <= mp; p++) colg += '<col style="width:8%">';
      colg += '<col style="width:12%">';
      const body = (d.units || []).map(u => {
        let ex = '';
        for (let p = 4; p <= mp; p++) ex += '<td>' + (u['p' + p] || 0) + '</td>';
        const pc = u.pos <= 3 ? ' p' + u.pos : '';
        return '<tr><td class="pos' + pc + '">' + u.pos + '</td>' +
          '<td class="u">' + (u.logo ? '<img class="ulogo" src="' + esc(u.logo) + '">' : '') + esc(u.unit) + '</td>' +
          '<td>' + u.g + '</td><td>' + u.s + '</td><td>' + u.b + '</td>' + ex +
          '<td class="pts">' + u.points + '</td></tr>';
      }).join('');
      cards.innerHTML = '<table class="tally"><colgroup>' + colg + '</colgroup>' +
        '<thead><tr><th>#</th><th class="u">Unit / Institution</th>' +
        '<th>Gold</th><th>Silver</th><th>Bronze</th>' + extraH + '<th>Points</th></tr></thead>' +
        '<tbody>' + body + '</tbody></table>';
      scrollY = 0; cards.style.transform = 'translateY(0)'; pauseUntil = performance.now() + 2500;
      startScroll();
      return;
    }
    // Card modes (heat / results).
    cards.style.gridTemplateColumns = 'repeat(' + (isResults ? 3 : 4) + ', minmax(0, 1fr))';
    if (isResults) {
      // Finals drop the Heat label; otherwise show it as in the heat view.
      const heatPart = d.is_final ? '' :
        (' &nbsp;·&nbsp; <span class="heat">Heat ' + (d.heat || '') + '</span>' +
         (d.num_heats > 1 ? ' <span style="opacity:.7;font-size:.8em">of ' + d.num_heats + '</span>' : ''));
      sub.innerHTML = esc(d.round || '') + heatPart + ' &nbsp;·&nbsp; <span class="heat">RESULTS</span>';
    } else {
      sub.innerHTML = esc(d.round || '') + ' &nbsp;·&nbsp; <span class="heat">Heat ' + (d.heat || '') + '</span>' +
        (d.num_heats > 1 ? ' <span style="opacity:.7;font-size:.8em">of ' + d.num_heats + '</span>' : '');
    }
    head.hidden = false; idle.hidden = true; cards.hidden = false;
    if (isResults) {
      cards.innerHTML = (d.athletes || []).map(a => `
        <div class="card">
          <div class="unit">${esc(a.unit || '')}</div>
          <div class="mid">
            <div class="rank${a.rank && a.rank <= 3 ? ' r' + a.rank : ''}">${a.rank || '-'}</div>
            ${a.photo ? '<img class="photo" src="' + esc(a.photo) + '">' : '<div class="photo ph">\u{1F464}</div>'}
            <div class="bib">${a.bib ? a.bib : ''}</div>
          </div>
          <div class="nm">${esc(a.name)}</div>
          ${a.time ? '<div class="time">' + esc(a.time) + '</div>' : ''}
        </div>`).join('');
    } else {
      // Call Room — Heat: the lane is shown as the top label ("Lane - N");
      // the blue lane circle is dropped, leaving photo + BIB centered. Team /
      // relay lanes instead show the relay letter and the members' BIBs.
      cards.innerHTML = (d.athletes || []).map(a => {
        if (a.is_team) {
          return '<div class="card">' +
            '<div class="unit lanelabel">Lane - ' + (a.lane || '-') + '</div>' +
            '<div class="mid"><div class="relay">' + (esc(a.relay) || '&mdash;') + '</div></div>' +
            '<div class="nm">' + (a.bibs ? esc(a.bibs) : '') + '</div>' +
            (a.unit || a.name ? '<div class="unit">' + esc(a.unit || a.name) + '</div>' : '') +
          '</div>';
        }
        return '<div class="card">' +
          '<div class="unit lanelabel">Lane - ' + (a.lane || '-') + '</div>' +
          '<div class="mid">' +
            (a.photo ? '<img class="photo" src="' + esc(a.photo) + '">' : '<div class="photo ph">\u{1F464}</div>') +
            '<div class="bib">' + (a.bib ? a.bib : '') + '</div>' +
          '</div>' +
          '<div class="nm">' + esc(a.name) + '</div>' +
        '</div>';
      }).join('');
    }
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
      // Flower-shower trigger: play once when flowers_at changes (skip the very
      // first poll so it doesn't replay an old trigger on load).
      if (d.flowers_at) {
        if (lastFlowers !== null && d.flowers_at !== lastFlowers) playFlowers();
        lastFlowers = d.flowers_at;
      }
      if (d.live && d.ok) {
        const s = [d.mode, d.round_id, d.round, d.heat, d.is_final ? 1 : 0,
                   (d.athletes || []).map(a => a.rank + ':' + a.time).join(','),
                   (d.units || []).map(u => u.pos + ':' + u.unit + ':' + u.points + ':' + u.g + '/' + u.s + '/' + u.b).join(','),
                   d.event, d.athlete, d.old, d.new,
                   (d.athletes || []).length, d.updated_at].join('|');
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
