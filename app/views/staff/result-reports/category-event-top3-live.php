<?php
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$genderLbl = fn(string $g): string => match (strtolower($g)) {
    'male'   => 'Men',
    'female' => 'Women',
    'mixed'  => 'Mixed',
    default  => $g,
};
$compNo = fn($n): string => $n
    ? '#' . str_pad((string)(int)$n, 4, '0', STR_PAD_LEFT) : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Live — <?= $h($category_name) ?> · <?= $h($event['name']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    /* Pure SMPTE chroma-key green background so streaming software
       can replace it cleanly. The work area + footer sit ON TOP and
       stay opaque so they're not keyed out. */
    :root { --green: #00B140; --footer-h: 80px; }
    * { box-sizing: border-box; }
    html, body { margin:0; height:100%; background: var(--green); color:#fff;
                 font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
                 overflow:hidden; -webkit-font-smoothing: antialiased; }
    body { display:flex; flex-direction:column; }

    /* Stage occupies the space above the footer; the work area sits
       inside it as a centered 2/3-sized container that holds the
       slides, the medal podium and the SportsMIS logo. */
    .stage { flex: 1 1 auto; display:flex; align-items:center; justify-content:center;
             position: relative; }
    .work-area { position:relative;
                 width:  min(86vw, 1500px);
                 height: min(66vh, calc(100vh - var(--footer-h) - 40px));
                 container-type: size; }

    /* Slides cover the full work area. Sized via container query units
       so everything scales with the work area, not the viewport. */
    .slide { position:absolute; inset:0; display:none; flex-direction:column;
             align-items:center; justify-content:flex-start;
             padding: 1.5cqh 1.5cqw; }
    .slide.active { display:flex; }

    /* Header row — event details panel (left-aligned, light shade) sits
       beside the event logo which lives OUTSIDE the panel on the right. */
    .header-row { display:flex; align-items:center; gap: 2cqw; width:100%; }
    .event-strip { flex: 1 1 auto; text-align:left; margin: 0;
                   background: linear-gradient(180deg, rgba(255,255,255,.94), rgba(232,238,247,.94));
                   border: 1.5px solid rgba(11,31,58,.18);
                   border-radius: 14px;
                   padding: 1.4cqh 2cqw 1.6cqh;
                   box-shadow: 0 8px 24px rgba(0,0,0,.22);
                   color: #0b1f3a; }
    .event-logo-side { flex: 0 0 auto;
                       width: 14cqh; height: 14cqh; max-width: 160px; max-height: 160px;
                       min-width: 80px; min-height: 80px;
                       border-radius: 50%; background:#fff;
                       display:flex; align-items:center; justify-content:center;
                       box-shadow: 0 6px 18px rgba(0,0,0,.35); padding: 6px; }
    .event-logo-side img { max-width:100%; max-height:100%; object-fit:contain; }
    .event-strip h1 { font-size: 7cqh; font-weight: 900; margin: 0;
                      line-height:1.05; color:#0b1f3a;
                      text-shadow: 1px 1px 0 rgba(11,31,58,.08);
                      text-transform: uppercase; letter-spacing: .01em; }
    .event-strip .ev-meta { font-size: 4cqh; color:#1e293b; opacity:.95;
                             margin-top: .6cqh; font-weight:700; }
    .event-strip .cat-pill { display:inline-block; background:#FFD23F; color:#0b1f3a;
                             font-weight:800; padding:.3cqh 1.4cqw; border-radius:999px;
                             font-size: 3.6cqh; margin-left:10px; vertical-align: middle; }

    /* Podium — three columns. Silver | Gold | Bronze. Silver and bronze
       share the same translateY so they sit on a single baseline; gold
       stays raised in the middle. */
    .podium { display:grid; grid-template-columns: 1fr 1.15fr 1fr; gap: 0.3cm;
              width: 100%; flex: 0 0 auto; align-items: end;
              padding: 0 1cqw 1cqh; margin-top: 1.5cm; }
    /* Each step is transparent — only the name chip carries a medal-tinted
       background so the broadcast reads cleanly on the keyed-out stage. */
    .step { position:relative; display:flex; flex-direction:column; align-items:center;
            color:#fff; }
    .step.gold   { transform: translateY(0); }
    .step.silver { transform: translateY(3cqh); }
    .step.bronze { transform: translateY(3cqh); }

    /* Photo frames — circular with medal-tinted border. */
    .photo-wrap { position:relative; width: 28cqh; height: 28cqh; max-width: 240px; max-height: 240px;
                  border-radius: 50%; overflow:hidden; box-shadow: 0 10px 30px rgba(0,0,0,.25);
                  background:#0b1f3a; display:flex; align-items:center; justify-content:center; }
    .photo-wrap img { width:100%; height:100%; object-fit:cover; display:block; }
    .photo-fallback { color:#fff; font-size: 9cqh; font-weight: 800; }
    .gold   .photo-wrap { border: 6px solid #FFD23F; width: 33cqh; height: 33cqh; max-width: 280px; max-height: 280px; }
    .silver .photo-wrap { border: 6px solid #D6DBE0; }
    .bronze .photo-wrap { border: 6px solid #CD7F32; }

    /* Medal disc — bottom-right of the photo */
    .medal-disc { position:absolute; right: -4px; bottom: -4px;
                  width: 8.5cqh; height: 8.5cqh; max-width: 70px; max-height: 70px;
                  border-radius:50%; display:flex; align-items:center; justify-content:center;
                  font-weight:800; font-size: 3.2cqh; color:#0b1f3a;
                  border: 3px solid #fff; box-shadow: 0 4px 14px rgba(0,0,0,.3);
                  letter-spacing: .03em; }
    .gold   .medal-disc { background: linear-gradient(135deg, #FFE17B, #E8A93C); }
    .silver .medal-disc { background: linear-gradient(135deg, #F1F4F7, #BCC4CC); }
    .bronze .medal-disc { background: linear-gradient(135deg, #E8B485, #A26834); color:#fff; }

    /* Medal label — sits at the top of each step, above the photo, with
       the same gradient as the name chip below. */
    .pos { display:inline-block; color:#fff; font-weight:900;
           padding:.5cqh 1.6cqw; border-radius:999px; font-size: 2.6cqh;
           margin-bottom: .9cqh;
           text-transform: uppercase; letter-spacing: .06em;
           box-shadow: 0 4px 12px rgba(0,0,0,.25); }
    .gold   .pos { background: linear-gradient(135deg, #FFE17B, #E8A93C); color:#5b3700; }
    .silver .pos { background: linear-gradient(135deg, #F1F4F7, #BCC4CC); color:#1a2231; }
    .bronze .pos { background: linear-gradient(135deg, #E8B485, #A26834); color:#fff; }

    /* Name chip below the photo — the only element that carries a medal
       background. Gold / silver / bronze gradients tint the chip per rank. */
    .name { margin-top: 1.4cqh; text-align:center; width:100%; }
    .name h2 { font-size: 4.5cqh; font-weight: 900; margin: 0;
               line-height:1.15;
               text-transform: uppercase; letter-spacing: .015em;
               border-radius: 12px;
               padding: .6cqh 1.2cqw;
               display: inline-block; max-width: 100%;
               box-shadow: 0 6px 16px rgba(0,0,0,.25); }
    .gold   .name h2 { font-size: 5.4cqh;
                       background: linear-gradient(135deg, #FFE17B, #E8A93C);
                       color:#5b3700; }
    .silver .name h2 { background: linear-gradient(135deg, #F1F4F7, #BCC4CC);
                       color:#1a2231; }
    .bronze .name h2 { background: linear-gradient(135deg, #E8B485, #A26834);
                       color:#fff; }

    /* Score plate — label now carries the unit / club name; score number
       below stays bold and prominent. Solid dark fill keeps it readable on stream. */
    .score { margin-top: 1cqh; background: rgba(11,31,58,.92); border-radius:14px;
             padding: .7cqh 1.6cqw; text-align:center; font-weight: 900;
             font-size: 5cqh; letter-spacing:.02em; line-height:1; color:#fff;
             box-shadow: 0 6px 16px rgba(0,0,0,.25); }
    .gold   .score { background: linear-gradient(135deg, rgba(199,140,30,.95), rgba(124,74,0,.95)); }
    .score .label { display:block; font-size: 2.4cqh; font-weight:800; opacity: .98;
                    text-transform: uppercase; letter-spacing: .04em; margin-bottom: .35cqh;
                    line-height:1.15; white-space: normal; }

    /* Empty medalist cell — keeps the grid slot but draws nothing. */
    .step.empty-hidden { visibility: hidden; }

    /* SportsMIS logo — circle at the bottom-left, nudged 2cm outside
       the work area so it sits cleanly on the green stage. */
    .sms-logo {
      position: absolute; left: -2cm; bottom: 0;
      width: 8cqh; height: 8cqh; max-width: 90px; max-height: 90px;
      min-width: 56px; min-height: 56px;
      border-radius: 50%;
      background: #fff;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 6px 18px rgba(0,0,0,.35);
      z-index: 10;
      padding: 6px;
    }
    .sms-logo img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .sms-logo .reg { position:absolute; top: 4px; right: 6px;
                     font-size: 12px; color: #0b1f3a; font-weight: 700; }

    /* Auto-rotate timer ring — mirrors sms-logo on the right, nudged
       2cm outside the work area so it stays clear of the bronze step. */
    .auto-timer {
      position: absolute; right: -2cm; bottom: 0;
      width: 8cqh; height: 8cqh; max-width: 90px; max-height: 90px;
      min-width: 56px; min-height: 56px;
      border-radius: 50%;
      background: rgba(11,31,58,.55);
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 6px 18px rgba(0,0,0,.35);
      z-index: 10;
      transition: opacity .2s, background .2s;
    }
    .auto-timer .ring { position: absolute; inset: 0; width: 100%; height: 100%;
                        transform: rotate(-90deg); }
    .auto-timer .ring .track { fill: none; stroke: rgba(255,255,255,.22); stroke-width: 7; }
    .auto-timer .ring .progress { fill: none; stroke: #FFD23F; stroke-width: 7;
                                  stroke-linecap: round;
                                  stroke-dasharray: 276.46;
                                  stroke-dashoffset: 276.46;
                                  transition: stroke-dashoffset .12s linear; }
    .auto-timer .time-text { color:#fff; font-weight: 800; font-size: 3.2cqh;
                             text-shadow: 1px 1px 2px rgba(0,0,0,.55);
                             font-variant-numeric: tabular-nums;
                             font-family: ui-monospace, "SFMono-Regular", Menlo, monospace;
                             pointer-events: none; z-index: 1; line-height:1; }
    .auto-timer:not(.on) { background: rgba(11,31,58,.32); }
    .auto-timer:not(.on) .ring .progress { stroke: rgba(255,255,255,.35); }
    .auto-timer:not(.on) .time-text { opacity: .65; }

    /* White footer bar with the controls. Sits outside the work area
       so it stays visible (and on stream — operator controls are
       intentionally NOT chroma-keyed away). */
    .footer-bar { flex: 0 0 auto; background: #fff;
                  height: var(--footer-h);
                  display:grid; grid-template-columns: 1fr auto 1fr;
                  align-items:center; gap: 14px; padding: 0 18px;
                  border-top: 3px solid rgba(0,0,0,.08);
                  box-shadow: 0 -6px 20px rgba(0,0,0,.18); z-index: 50; }
    .footer-left   { display:flex; align-items:center; gap:8px; justify-self: start; }
    .footer-center { display:flex; align-items:center; gap:14px; }
    .footer-right  { display:flex; align-items:center; gap:14px; justify-self: end; }
    .footer-bar .lbl { color:#475569; font-weight:700; font-size:.85rem;
                       text-transform:uppercase; letter-spacing:.05em; }
    .cat-select { background:#fff; border:2px solid #0b1f3a; color:#0b1f3a;
                  font-weight:700; padding: 8px 14px; border-radius: 999px;
                  font-size: .98rem; cursor:pointer; max-width: 280px;
                  appearance: none; -webkit-appearance: none;
                  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' width='14' height='14' fill='%230b1f3a'%3E%3Cpath d='M4 6l4 4 4-4'/%3E%3C/svg%3E");
                  background-repeat: no-repeat; background-position: right 14px center;
                  padding-right: 36px; }
    .ctrl-btn { background:#0b1f3a; color:#fff; border:0;
                padding: 10px 22px; border-radius: 999px;
                font-weight:700; font-size: 1rem; cursor: pointer;
                transition: background .15s; display:inline-flex; align-items:center; gap:6px; }
    .ctrl-btn:hover    { background:#1f3b7a; }
    .ctrl-btn:disabled { opacity:.4; cursor:not-allowed; }
    .ctrl-btn.close    { background:#dc2626; }
    .ctrl-btn.close:hover { background:#b91c1c; }
    .pos-indicator { color:#0b1f3a; font-weight:800; padding: 0 14px; font-size: 1.05rem;
                     min-width: 90px; text-align:center; }

    /* Auto-rotate switch — labelled toggle that lights up green when on. */
    .auto-toggle { display:inline-flex; align-items:center; gap:8px; cursor:pointer;
                   user-select:none; }
    .auto-toggle .slider { position:relative; width: 46px; height: 24px;
                           background:#cbd5e1; border-radius: 999px;
                           transition: background .2s; flex-shrink: 0; }
    .auto-toggle .slider::after { content:""; position:absolute; top:2px; left:2px;
                                   width:20px; height:20px; background:#fff;
                                   border-radius:50%; transition: left .2s;
                                   box-shadow: 0 2px 4px rgba(0,0,0,.2); }
    .auto-toggle.on .slider { background:#16a34a; }
    .auto-toggle.on .slider::after { left: 24px; }
    .auto-toggle .label { color:#0b1f3a; font-weight:700; font-size: .95rem; }
    .auto-toggle.on .label { color:#16a34a; }

    /* No-medalists fallback */
    .empty-screen { color:#fff; text-align:center; padding-top: 12vh; font-size: 2vw; opacity:.9; }
  </style>
</head>
<body>

<?php if (empty($sport_events)): ?>
  <div class="empty-screen">
    <i class="bi bi-info-circle me-2"></i>
    No sport-events with medalists found for <strong><?= $h($category_name) ?></strong>.
  </div>
<?php else: ?>

<div class="stage">
  <div class="work-area">

    <!-- SportsMIS logo — circle at the bottom-left of the work area. -->
    <div class="sms-logo" title="SportsMIS&reg;">
      <img src="/assets/img/sba-logo.png" alt="SportsMIS">
    </div>

    <!-- Auto-rotate timer — animated ring at the bottom-right of the work area. -->
    <div class="auto-timer" id="autoTimer" title="Auto-rotate countdown">
      <svg class="ring" viewBox="0 0 100 100" aria-hidden="true">
        <circle class="track"    cx="50" cy="50" r="44" />
        <circle class="progress" cx="50" cy="50" r="44" />
      </svg>
      <div class="time-text" id="timeText">7</div>
    </div>

  <?php foreach ($sport_events as $idx => $ev):
    $top3 = $ev['top3'] ?? [];
    // Build a positional map so silver-left / gold-middle / bronze-right
    // always render in the same slot, even when fewer than 3 medalists.
    $byRank = [];
    foreach ($top3 as $i => $a) $byRank[$i + 1] = $a;
    $renderStep = function (string $cls, int $rank, ?array $a, string $medalLetter) use ($h) {
        // No medalist for this rank — render an invisible placeholder so the
        // grid keeps gold-middle / silver-left / bronze-right alignment.
        if (!$a) return '<div class="step ' . $cls . ' empty-hidden"></div>';
        $medalName = $rank === 1 ? 'Gold' : ($rank === 2 ? 'Silver' : 'Bronze');
        $img = !empty($a['athlete_photo']) ? $h($a['athlete_photo']) : '';
        $initial = strtoupper(substr((string)$a['athlete_name'], 0, 1));
        ob_start(); ?>
        <div class="step <?= $cls ?>">
          <div class="pos"><?= $medalName ?></div>
          <div class="photo-wrap">
            <?php if ($img): ?>
              <img src="<?= $img ?>" alt="">
            <?php else: ?>
              <div class="photo-fallback"><?= $h($initial) ?></div>
            <?php endif; ?>
            <div class="medal-disc"><?= $medalLetter ?></div>
          </div>
          <div class="name">
            <h2><?= $h($a['athlete_name']) ?></h2>
          </div>
          <div class="score">
            <span class="label"><?= $h($a['unit_name'] ?: '—') ?></span>
            <?= (int)round((float)$a['grand_total']) ?>
          </div>
        </div>
        <?php
        return ob_get_clean();
    };
  ?>
  <section class="slide <?= $idx === 0 ? 'active' : '' ?>" data-slide="<?= $idx ?>">

    <div class="header-row">
      <div class="event-strip">
        <h1><?= $h($ev['sport_event'] ?: '—') ?></h1>
        <div class="ev-meta">
          <?= $h($event['name']) ?>
          <span class="cat-pill"><?= $h($category_name) ?></span>
          <?php if (!empty($ev['age_category'])): ?>
            &middot; <?= $h($ev['age_category']) ?>
          <?php endif; ?>
          <?php if (!empty($ev['gender'])): ?>
            &middot; <?= $h($genderLbl($ev['gender'])) ?>
          <?php endif; ?>
        </div>
      </div>
      <?php if (!empty($event['logo'])): ?>
        <div class="event-logo-side">
          <img src="<?= $h($event['logo']) ?>" alt="">
        </div>
      <?php endif; ?>
    </div>

    <div class="podium">
      <!-- Silver (left) -->
      <?= $renderStep('silver', 2, $byRank[2] ?? null, 'S') ?>
      <!-- Gold (middle) -->
      <?= $renderStep('gold',   1, $byRank[1] ?? null, 'G') ?>
      <!-- Bronze (right) -->
      <?= $renderStep('bronze', 3, $byRank[3] ?? null, 'B') ?>
    </div>
  </section>
  <?php endforeach; ?>

  </div><!-- /.work-area -->
</div><!-- /.stage -->

<div class="footer-bar">

  <!-- Left: Category dropdown — change filter without leaving the live screen. -->
  <div class="footer-left">
    <span class="lbl">Category</span>
    <select id="catSelect" class="cat-select" title="Switch to a different Event Category">
      <?php foreach ($categories as $c): ?>
        <option value="<?= (int)$c['id'] ?>"
                <?= (int)$selected_category === (int)$c['id'] ? 'selected' : '' ?>>
          <?= $h($c['name']) ?>
          <?php if (!empty($c['abbreviation'])): ?> (<?= $h($c['abbreviation']) ?>)<?php endif; ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- Centre: Back / counter / Next. -->
  <div class="footer-center">
    <button class="ctrl-btn" id="prevBtn" type="button" title="Previous (←)">
      <i class="bi bi-chevron-left"></i>Back
    </button>
    <span class="pos-indicator">
      <span id="curIdx">1</span> / <span id="totalIdx"><?= count($sport_events) ?></span>
    </span>
    <button class="ctrl-btn" id="nextBtn" type="button" title="Next (→)">
      Next<i class="bi bi-chevron-right"></i>
    </button>
  </div>

  <!-- Right: Auto-rotate toggle + Close. -->
  <div class="footer-right">
    <label class="auto-toggle" id="autoToggle" title="Cycle through slides every 7 seconds (R)">
      <span class="slider"></span>
      <span class="label">Auto rotate</span>
    </label>
    <button class="ctrl-btn close" type="button" onclick="window.close()" title="Close (Esc)">
      <i class="bi bi-x-lg"></i>Close
    </button>
  </div>
</div>

  <script>
    (function () {
      const slides = Array.from(document.querySelectorAll('.slide'));
      let cur = 0;
      const prev    = document.getElementById('prevBtn');
      const next    = document.getElementById('nextBtn');
      const curIdx  = document.getElementById('curIdx');
      const catSel  = document.getElementById('catSelect');
      const auto    = document.getElementById('autoToggle');
      const timer   = document.getElementById('autoTimer');
      const ring    = timer.querySelector('.progress');
      const timeTxt = document.getElementById('timeText');

      const AUTO_INTERVAL_MS = 7000;
      const RING_CIRC = 2 * Math.PI * 44; // ≈ 276.46
      let autoTimer  = null;
      let rafId      = null;
      let cycleStart = 0;

      ring.style.strokeDasharray = String(RING_CIRC);
      ring.style.strokeDashoffset = String(RING_CIRC);

      function tickRing() {
        if (!auto.classList.contains('on')) return;
        const elapsed = Date.now() - cycleStart;
        const pct     = Math.min(1, elapsed / AUTO_INTERVAL_MS);
        const remain  = Math.max(0, AUTO_INTERVAL_MS - elapsed);
        ring.style.strokeDashoffset = String(RING_CIRC * (1 - pct));
        timeTxt.textContent = String(Math.ceil(remain / 1000));
        rafId = requestAnimationFrame(tickRing);
      }
      function startRingCycle() {
        cancelAnimationFrame(rafId);
        cycleStart = Date.now();
        ring.style.strokeDashoffset = String(RING_CIRC);
        timeTxt.textContent = String(Math.ceil(AUTO_INTERVAL_MS / 1000));
        rafId = requestAnimationFrame(tickRing);
      }
      function stopRing() {
        cancelAnimationFrame(rafId); rafId = null;
        ring.style.strokeDashoffset = String(RING_CIRC);
        timeTxt.textContent = String(Math.ceil(AUTO_INTERVAL_MS / 1000));
      }
      function restartAutoCycle() {
        clearInterval(autoTimer);
        autoTimer = setInterval(stepNext, AUTO_INTERVAL_MS);
        startRingCycle();
      }

      function show(i, wrap) {
        if (i < 0 || i >= slides.length) {
          if (wrap) i = (i + slides.length) % slides.length;
          else return;
        }
        slides[cur].classList.remove('active');
        cur = i;
        slides[cur].classList.add('active');
        curIdx.textContent = String(cur + 1);
        // In auto-rotate mode we cycle, so the Back / Next buttons stay
        // enabled regardless of position. Manual mode pins them at the
        // ends as before.
        const autoOn = auto.classList.contains('on');
        prev.disabled = !autoOn && cur === 0;
        next.disabled = !autoOn && cur === slides.length - 1;
      }
      function stepNext() {
        show(cur + 1, true);
        if (auto.classList.contains('on')) restartAutoCycle();
      }
      function stepPrev() {
        show(cur - 1, true);
        if (auto.classList.contains('on')) restartAutoCycle();
      }

      function setAuto(on) {
        if (on) {
          auto.classList.add('on');
          timer.classList.add('on');
          restartAutoCycle();
          try { localStorage.setItem('et3_auto', '1'); } catch (e) {}
        } else {
          auto.classList.remove('on');
          timer.classList.remove('on');
          clearInterval(autoTimer); autoTimer = null;
          stopRing();
          try { localStorage.setItem('et3_auto', '0'); } catch (e) {}
        }
        // Reset the disabled-state for prev/next under the new mode.
        show(cur, false);
      }

      prev.addEventListener('click', () => stepPrev());
      next.addEventListener('click', () => stepNext());
      auto.addEventListener('click', () => setAuto(!auto.classList.contains('on')));
      catSel.addEventListener('change', () => {
        const id = parseInt(catSel.value, 10);
        if (!id) return;
        // Carry the auto-rotate preference forward via localStorage; the
        // new page rehydrates it on load.
        window.location.href = '/event-staff/result-reports/category-event-top3/live?category_id=' + encodeURIComponent(id);
      });

      document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft'  || e.key === 'PageUp')   { stepPrev(); }
        if (e.key === 'ArrowRight' || e.key === 'PageDown' || e.key === ' ') {
          e.preventDefault(); stepNext();
        }
        if (e.key.toLowerCase() === 'r') setAuto(!auto.classList.contains('on'));
        if (e.key === 'Escape') window.close();
        if (e.key === 'Home')   show(0);
        if (e.key === 'End')    show(slides.length - 1);
      });
      show(0);
      // Rehydrate auto-rotate from the previous live screen (or earlier
      // session) so toggling the category dropdown doesn't reset it.
      try {
        if (localStorage.getItem('et3_auto') === '1') setAuto(true);
      } catch (e) {}
    })();
  </script>
<?php endif; ?>
</body>
</html>
