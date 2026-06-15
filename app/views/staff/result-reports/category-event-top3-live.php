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
             padding: 2cqh 2cqw; }
    .slide.active { display:flex; }

    /* Event header */
    .event-strip { text-align:center; margin-top: 1cqh; margin-bottom: 0.5cqh; }
    .event-strip .ev-code { display:inline-block; background:rgba(0,0,0,.18);
                            color:#fff; font-weight:700; padding:.3cqh 1.2cqw;
                            border-radius:999px; font-size: 1.7cqh; letter-spacing:.03em; }
    .event-strip h1 { font-size: 5cqh; font-weight: 900; margin: .6cqh 0 0;
                      line-height:1.1; text-shadow: 2px 2px 0 rgba(0,0,0,.18); }
    .event-strip .ev-meta { font-size: 1.8cqh; opacity:.92; margin-top: .4cqh; font-weight:500; }
    .event-strip .cat-pill { display:inline-block; background:#FFD23F; color:#0b1f3a;
                             font-weight:800; padding:.2cqh 1cqw; border-radius:999px;
                             font-size: 1.6cqh; margin-left:8px; vertical-align: middle; }

    /* Podium — three columns. Silver | Gold | Bronze. */
    .podium { display:grid; grid-template-columns: 1fr 1.15fr 1fr; gap: 2cqw;
              width: 100%; flex: 1 1 auto; align-items: end;
              padding: 1cqh 1cqw 4cqh; }
    .step { position:relative; display:flex; flex-direction:column; align-items:center;
            color:#fff; }
    .step.gold   { transform: translateY(0); }
    .step.silver { transform: translateY(3cqh); }
    .step.bronze { transform: translateY(6cqh); }

    /* Photo frames — circular with medal-tinted border. */
    .photo-wrap { position:relative; width: 26cqh; height: 26cqh; max-width: 240px; max-height: 240px;
                  border-radius: 50%; overflow:hidden; box-shadow: 0 10px 30px rgba(0,0,0,.25);
                  background:#0b1f3a; display:flex; align-items:center; justify-content:center; }
    .photo-wrap img { width:100%; height:100%; object-fit:cover; display:block; }
    .photo-fallback { color:#fff; font-size: 7cqh; font-weight: 800; }
    .gold   .photo-wrap { border: 5px solid #FFD23F; width: 30cqh; height: 30cqh; max-width: 270px; max-height: 270px; }
    .silver .photo-wrap { border: 5px solid #D6DBE0; }
    .bronze .photo-wrap { border: 5px solid #CD7F32; }

    /* Medal disc — bottom-right of the photo */
    .medal-disc { position:absolute; right: -4px; bottom: -4px;
                  width: 7cqh; height: 7cqh; max-width: 64px; max-height: 64px;
                  border-radius:50%; display:flex; align-items:center; justify-content:center;
                  font-weight:800; font-size: 2.4cqh; color:#0b1f3a;
                  border: 3px solid #fff; box-shadow: 0 4px 14px rgba(0,0,0,.3);
                  letter-spacing: .03em; }
    .gold   .medal-disc { background: linear-gradient(135deg, #FFE17B, #E8A93C); }
    .silver .medal-disc { background: linear-gradient(135deg, #F1F4F7, #BCC4CC); }
    .bronze .medal-disc { background: linear-gradient(135deg, #E8B485, #A26834); color:#fff; }

    /* Name + meta below the photo */
    .name { margin-top: 1.2cqh; text-align:center; }
    .name .pos { display:inline-block; background:#0b1f3a; color:#fff; font-weight:800;
                 padding:.2cqh 1cqw; border-radius:999px; font-size: 1.6cqh; margin-bottom: .4cqh; }
    .name h2 { font-size: 2.8cqh; font-weight: 900; margin: 0 0 .2cqh;
               text-shadow: 1px 1px 0 rgba(0,0,0,.18); line-height:1.15; }
    .gold   .name h2 { font-size: 3.2cqh; }
    .name .unit { font-size: 1.8cqh; font-weight: 600; opacity: .95; }
    .name .addr { font-size: 1.4cqh; opacity: .8; margin-top: .2cqh; }
    .name .comp { font-size: 1.5cqh; opacity: .85; margin-top: .3cqh; font-family: ui-monospace, monospace; }

    /* Score plate */
    .score { margin-top: .8cqh; background: rgba(0,0,0,.22); border-radius:14px;
             padding: .6cqh 1.2cqw; text-align:center; font-weight: 900;
             font-size: 3.2cqh; letter-spacing:.02em; line-height:1; }
    .gold   .score { background: rgba(255,210,63,.32); }
    .score .label { display:block; font-size: 1.2cqh; font-weight:700; opacity: .85;
                    text-transform: uppercase; letter-spacing: .12em; margin-bottom: .15cqh; }

    /* Empty state placeholder for missing medalists */
    .step.empty { opacity: .55; }
    .step.empty .name h2 { font-style: italic; font-weight: 700; }

    /* SportsMIS logo — circle at the bottom-left of the work area. */
    .sms-logo {
      position: absolute; left: 0; bottom: 0;
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

    /* White footer bar with the controls. Sits outside the work area
       so it stays visible (and on stream — operator controls are
       intentionally NOT chroma-keyed away). */
    .footer-bar { flex: 0 0 auto; background: #fff;
                  height: var(--footer-h);
                  display:flex; align-items:center; justify-content:center;
                  gap: 14px; padding: 0 18px;
                  border-top: 3px solid rgba(0,0,0,.08);
                  box-shadow: 0 -6px 20px rgba(0,0,0,.18); z-index: 50; }
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

  <?php foreach ($sport_events as $idx => $ev):
    $top3 = $ev['top3'] ?? [];
    // Build a positional map so silver-left / gold-middle / bronze-right
    // always render in the same slot, even when fewer than 3 medalists.
    $byRank = [];
    foreach ($top3 as $i => $a) $byRank[$i + 1] = $a;
    $renderStep = function (string $cls, int $rank, ?array $a, string $medalLetter) use ($h, $compNo) {
        $pos = $rank === 1 ? '1st Place' : ($rank === 2 ? '2nd Place' : '3rd Place');
        $img = $a && !empty($a['athlete_photo']) ? $h($a['athlete_photo']) : '';
        $initial = $a ? strtoupper(substr((string)$a['athlete_name'], 0, 1)) : '';
        ob_start(); ?>
        <div class="step <?= $cls ?> <?= $a ? '' : 'empty' ?>">
          <div class="photo-wrap">
            <?php if ($img): ?>
              <img src="<?= $img ?>" alt="">
            <?php else: ?>
              <div class="photo-fallback"><?= $a ? $h($initial) : '—' ?></div>
            <?php endif; ?>
            <div class="medal-disc"><?= $medalLetter ?></div>
          </div>
          <div class="name">
            <div class="pos"><?= $pos ?></div>
            <h2><?= $a ? $h($a['athlete_name']) : 'No medalist' ?></h2>
            <?php if ($a): ?>
              <div class="unit"><?= $h($a['unit_name'] ?: '—') ?></div>
              <?php if (!empty($a['unit_address'])): ?>
                <div class="addr"><?= $h($a['unit_address']) ?></div>
              <?php endif; ?>
              <?php if (!empty($a['competitor_number'])): ?>
                <div class="comp"><?= $h($compNo($a['competitor_number'])) ?></div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          <?php if ($a): ?>
            <div class="score">
              <span class="label">Total Score</span>
              <?= (int)round((float)$a['grand_total']) ?>
            </div>
          <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    };
  ?>
  <section class="slide <?= $idx === 0 ? 'active' : '' ?>" data-slide="<?= $idx ?>">

    <div class="event-strip">
      <?php if (!empty($ev['event_code'])): ?>
        <div class="ev-code"><?= $h($ev['event_code']) ?></div>
      <?php endif; ?>
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
  <button class="ctrl-btn" id="prevBtn" type="button" title="Previous (←)">
    <i class="bi bi-chevron-left"></i>Back
  </button>
  <span class="pos-indicator">
    <span id="curIdx">1</span> / <span id="totalIdx"><?= count($sport_events) ?></span>
  </span>
  <button class="ctrl-btn" id="nextBtn" type="button" title="Next (→)">
    Next<i class="bi bi-chevron-right"></i>
  </button>
  <button class="ctrl-btn close" type="button" onclick="window.close()" title="Close (Esc)">
    <i class="bi bi-x-lg"></i>Close
  </button>
</div>

  <script>
    (function () {
      const slides = Array.from(document.querySelectorAll('.slide'));
      let cur = 0;
      const prev = document.getElementById('prevBtn');
      const next = document.getElementById('nextBtn');
      const curIdx = document.getElementById('curIdx');

      function show(i) {
        if (i < 0 || i >= slides.length) return;
        slides[cur].classList.remove('active');
        cur = i;
        slides[cur].classList.add('active');
        curIdx.textContent = String(cur + 1);
        prev.disabled = cur === 0;
        next.disabled = cur === slides.length - 1;
      }
      prev.addEventListener('click', () => show(cur - 1));
      next.addEventListener('click', () => show(cur + 1));
      document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft'  || e.key === 'PageUp')   { show(cur - 1); }
        if (e.key === 'ArrowRight' || e.key === 'PageDown' || e.key === ' ') {
          e.preventDefault(); show(cur + 1);
        }
        if (e.key === 'Escape') window.close();
        if (e.key === 'Home')   show(0);
        if (e.key === 'End')    show(slides.length - 1);
      });
      show(0);
    })();
  </script>
<?php endif; ?>
</body>
</html>
