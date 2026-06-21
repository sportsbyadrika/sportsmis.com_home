<?php
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$fmtScore = function ($v): string {
    if ($v === null || $v === '') return '';
    $f = (float)$v;
    if ($f == (int)$f) return (string)(int)$f;
    return rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');
};
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
            --gold:#FFE17B; --silver:#E5E7EB; --bronze:#E8B485;
            --ok:#16a34a; --row:rgba(255,255,255,.06); }
    * { box-sizing: border-box; }
    html,body { height:100%; margin:0; overflow:hidden; color:#fff;
                background: radial-gradient(circle at 20% 10%, #1a3470, #04122a);
                font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }

    /* Stage layout — top bar, slide body, bottom timer. */
    .stage { position: fixed; inset: 0; display: flex; flex-direction: column; }
    .topbar { display:flex; align-items:center; gap: 14px; padding: 14px 22px;
              border-bottom: 1px solid rgba(255,255,255,.10); flex: 0 0 auto; }
    .topbar .logo { width: 44px; height: 44px; object-fit: contain; background:#fff;
                    border-radius:8px; padding: 4px; }
    .topbar .meta { flex: 1; min-width: 0; }
    .topbar h1 { margin: 0; font-size: 18px; font-weight: 800; letter-spacing: .01em;
                 text-overflow: ellipsis; overflow: hidden; white-space: nowrap; }
    .topbar .sub { font-size: 12px; color:#cbd5e1; margin-top: 2px; }
    .topbar .actions button { background:#dc2626; color:#fff; border:0;
                  padding: 8px 14px; border-radius: 999px; font-weight: 700; cursor: pointer;
                  display:inline-flex; align-items:center; gap: 6px; font-size: 14px; }
    .topbar .actions .fs { background:#0ea5e9; margin-right: 8px; }

    .slide-host { flex: 1 1 auto; position: relative; overflow: hidden; padding: 18px 28px 0; }
    .slide { position:absolute; inset: 18px 28px 0; display:none; flex-direction: column; }
    .slide.active { display: flex; }

    .slide-head { display:flex; align-items:flex-end; gap: 12px; flex-wrap: wrap;
                  padding-bottom: 12px; border-bottom: 2px solid rgba(255,255,255,.15);
                  margin-bottom: 14px; }
    .slide-head .cat-pill { background: var(--accent); color: var(--ink);
                  font-weight: 800; padding: 3px 12px; border-radius: 999px;
                  font-size: 18px; letter-spacing: .03em; text-transform: uppercase; }
    .slide-head .type-pill { background: rgba(255,255,255,.10);
                  color:#fff; font-weight: 700; padding: 3px 12px; border-radius: 999px;
                  font-size: 14px; letter-spacing: .04em; text-transform: uppercase; }
    .slide-head .type-pill.team { background: rgba(14,165,233,.28); color:#bae6fd; }
    .slide-head .ev-code { background: rgba(255,255,255,.10); color:#fff;
                  padding: 3px 10px; border-radius: 8px; font-family: ui-monospace, monospace;
                  font-size: 18px; font-weight: 700; letter-spacing: .03em; }
    .slide-head h2 { margin: 0; font-size: 34px; font-weight: 800;
                     line-height: 1.05; flex: 1; min-width: 0; }
    .slide-head .mqs { background: rgba(34,197,94,.18); color:#bbf7d0;
                  font-weight: 800; padding: 4px 14px; border-radius: 999px;
                  font-size: 18px; letter-spacing: .03em; }

    .table-wrap { flex: 1 1 auto; overflow: hidden; }
    table.results { width:100%; border-collapse: collapse; font-size: 22px;
                    table-layout: fixed; }
    table.results th { text-align: left; padding: 10px 14px; color:#94a3b8;
                       font-weight: 700; font-size: 14px; letter-spacing: .05em;
                       text-transform: uppercase;
                       border-bottom: 1px solid rgba(255,255,255,.12); }
    table.results td { padding: 12px 14px; border-bottom: 1px solid rgba(255,255,255,.06);
                       overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    table.results tbody tr:nth-child(odd) td { background: var(--row); }
    table.results td.rank { font-weight: 800; font-size: 26px; width: 90px; text-align:center; }
    table.results td.name { font-weight: 700; }
    table.results td.unit { color:#cbd5e1; }
    table.results td.score { text-align:right; font-weight: 800;
                             font-family: ui-monospace, monospace; }
    table.results td.mqs { text-align:right; color:#cbd5e1; font-weight: 600;
                           font-family: ui-monospace, monospace; }
    table.results td.tags { text-align:right; }
    /* Medal-tinted top three. */
    table.results tr.r1 td.rank { color: var(--gold); }
    table.results tr.r2 td.rank { color: var(--silver); }
    table.results tr.r3 td.rank { color: var(--bronze); }
    .qual-badge { display:inline-block; background: var(--ok);
                  color:#fff; font-weight: 800; padding: 3px 12px;
                  border-radius: 999px; font-size: 14px; letter-spacing: .04em; }
    .empty-row { color:#94a3b8; padding: 32px; text-align: center;
                 font-style: italic; font-size: 18px; }

    /* Team variant: wider member column, members listed inline. */
    table.results.team td.members { white-space: normal; color:#e5e7eb; }

    /* Bottom timer — circular ring + countdown number. */
    .timer-bar { flex: 0 0 auto; display:flex; align-items:center; justify-content:center;
                 gap: 14px; padding: 14px 18px;
                 border-top: 1px solid rgba(255,255,255,.10); }
    .timer { position:relative; width: 58px; height: 58px; }
    .timer svg { position:absolute; inset:0; transform: rotate(-90deg); }
    .timer .track { fill: none; stroke: rgba(255,255,255,.15); stroke-width: 6; }
    .timer .progress { fill: none; stroke: var(--accent); stroke-width: 6;
                       stroke-linecap: round;
                       stroke-dasharray: 276.46; stroke-dashoffset: 276.46;
                       transition: stroke-dashoffset .12s linear; }
    .timer .num { position:absolute; inset:0; display:flex; align-items:center;
                  justify-content:center; font-weight: 800; font-size: 22px;
                  color:#fff; font-family: ui-monospace, monospace; }
    .pos-indicator { color:#cbd5e1; font-size: 14px; font-weight: 700;
                     letter-spacing: .05em; }
    .empty-stage { display:flex; flex-direction:column; align-items:center;
                   justify-content:center; height: 100%; color:#cbd5e1;
                   text-align:center; gap: 12px; }
    .empty-stage i { font-size: 80px; opacity: .35; }
    .empty-stage h2 { font-size: 28px; margin: 0; }
  </style>
</head>
<body>

<div class="stage">

  <div class="topbar">
    <?php if (!empty($event['logo'])): ?>
      <img src="<?= $h($event['logo']) ?>" alt="" class="logo">
    <?php endif; ?>
    <div class="meta">
      <h1><?= $h($event['name']) ?></h1>
      <div class="sub">
        <i class="bi bi-broadcast me-1"></i>Live LED-Wall Slideshow
        &middot; <code class="text-light"><?= $h($event['event_code'] ?? '') ?></code>
      </div>
    </div>
    <div class="actions">
      <button type="button" class="fs" id="fsBtn" title="Toggle full-screen">
        <i class="bi bi-arrows-fullscreen"></i> Full Screen
      </button>
      <button type="button" onclick="window.close()" title="Close (Esc)">
        <i class="bi bi-x-lg"></i> Close
      </button>
    </div>
  </div>

  <div class="slide-host" id="slideHost">
    <?php if (empty($slides)): ?>
      <div class="empty-stage">
        <i class="bi bi-clock-history"></i>
        <h2>No results have been recorded for this event yet.</h2>
        <p>This page will refresh when you reopen it after the organisers post results.</p>
      </div>
    <?php else: foreach ($slides as $idx => $s): ?>
      <section class="slide <?= $idx === 0 ? 'active' : '' ?>" data-slide="<?= $idx ?>">
        <div class="slide-head">
          <span class="cat-pill"><?= $h($s['category'] ?: '—') ?></span>
          <span class="type-pill <?= $s['type'] === 'team' ? 'team' : '' ?>">
            <?= $s['type'] === 'team' ? 'Team' : 'Individual' ?>
          </span>
          <?php if (!empty($s['event_code'])): ?>
            <span class="ev-code"><?= $h($s['event_code']) ?></span>
          <?php endif; ?>
          <h2><?= $h($s['sport_event'] ?: '—') ?></h2>
          <?php if ($s['type'] === 'individual' && !empty($s['mqs'])): ?>
            <span class="mqs">MQS: <?= $h($fmtScore($s['mqs'])) ?></span>
          <?php endif; ?>
        </div>

        <div class="table-wrap">
          <?php if ($s['type'] === 'individual'): ?>
            <table class="results">
              <colgroup>
                <col style="width:90px">
                <col>
                <col style="width:30%">
                <col style="width:120px">
                <?php if (!empty($s['mqs'])): ?><col style="width:110px"><?php endif; ?>
                <col style="width:150px">
              </colgroup>
              <thead>
                <tr>
                  <th class="text-center">Rank</th>
                  <th>Name</th>
                  <th>Unit / Club</th>
                  <th class="text-end">Total Score</th>
                  <?php if (!empty($s['mqs'])): ?><th class="text-end">MQS</th><?php endif; ?>
                  <th class="text-end">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($s['entries'])): ?>
                  <tr><td colspan="<?= !empty($s['mqs']) ? 6 : 5 ?>" class="empty-row">No entries yet.</td></tr>
                <?php else: foreach ($s['entries'] as $row):
                  $rkCls = $row['rank'] === 1 ? 'r1' : ($row['rank'] === 2 ? 'r2' : ($row['rank'] === 3 ? 'r3' : ''));
                ?>
                  <tr class="<?= $rkCls ?>">
                    <td class="rank"><?= $row['rank'] !== null ? (int)$row['rank'] : '—' ?></td>
                    <td class="name"><?= $h($row['athlete_name']) ?></td>
                    <td class="unit"><?= $h($row['unit_name'] ?: '—') ?></td>
                    <td class="score">
                      <?= $row['grand_total'] !== null
                            ? $h((string)(int)round((float)$row['grand_total']))
                            : '—' ?>
                    </td>
                    <?php if (!empty($s['mqs'])): ?>
                      <td class="mqs"><?= $h($fmtScore($s['mqs'])) ?></td>
                    <?php endif; ?>
                    <td class="tags">
                      <?php if (!empty($row['qualified'])): ?>
                        <span class="qual-badge">Qualified</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          <?php else: /* team slide */ ?>
            <table class="results team">
              <colgroup>
                <col style="width:90px">
                <col style="width:24%">
                <col>
                <col style="width:22%">
                <col style="width:140px">
              </colgroup>
              <thead>
                <tr>
                  <th class="text-center">Rank</th>
                  <th>Team</th>
                  <th>Members</th>
                  <th>Unit / Club</th>
                  <th class="text-end">Team Score</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($s['entries'])): ?>
                  <tr><td colspan="5" class="empty-row">No teams yet.</td></tr>
                <?php else: foreach ($s['entries'] as $row):
                  $rkCls = $row['rank'] === 1 ? 'r1' : ($row['rank'] === 2 ? 'r2' : ($row['rank'] === 3 ? 'r3' : ''));
                ?>
                  <tr class="<?= $rkCls ?>">
                    <td class="rank"><?= $row['rank'] !== null ? (int)$row['rank'] : '—' ?></td>
                    <td class="name"><?= $h($row['team_name']) ?></td>
                    <td class="members"><?= $h($row['members'] ?: '—') ?></td>
                    <td class="unit"><?= $h($row['unit_name'] ?: '—') ?></td>
                    <td class="score">
                      <?= $row['grand_total'] !== null
                            ? $h((string)(int)round((float)$row['grand_total']))
                            : '—' ?>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </section>
    <?php endforeach; endif; ?>
  </div>

  <?php if (!empty($slides)): ?>
  <div class="timer-bar">
    <div class="timer" id="autoTimer" title="Auto-rotate countdown">
      <svg viewBox="0 0 100 100" aria-hidden="true">
        <circle class="track"    cx="50" cy="50" r="44" />
        <circle class="progress" cx="50" cy="50" r="44" />
      </svg>
      <div class="num" id="timerNum">5</div>
    </div>
    <div class="pos-indicator">
      Slide <span id="curIdx">1</span> / <span id="totalIdx"><?= count($slides) ?></span>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php if (!empty($slides)): ?>
<script>
(function () {
  const SLIDES    = Array.from(document.querySelectorAll('.slide'));
  const INTERVAL  = 5000;
  const RING_C    = 2 * Math.PI * 44;
  const ring      = document.querySelector('.timer .progress');
  const timerNum  = document.getElementById('timerNum');
  const curIdx    = document.getElementById('curIdx');
  const fsBtn     = document.getElementById('fsBtn');

  let cur = 0, cycleStart = 0, rafId = null, autoTimer = null;
  ring.style.strokeDasharray  = String(RING_C);
  ring.style.strokeDashoffset = String(RING_C);

  function show(i) {
    SLIDES[cur].classList.remove('active');
    cur = (i + SLIDES.length) % SLIDES.length;
    SLIDES[cur].classList.add('active');
    curIdx.textContent = String(cur + 1);
    restartCycle();
  }
  function tick() {
    const elapsed = Date.now() - cycleStart;
    const pct     = Math.min(1, elapsed / INTERVAL);
    const left    = Math.max(0, INTERVAL - elapsed);
    ring.style.strokeDashoffset = String(RING_C * (1 - pct));
    timerNum.textContent = String(Math.ceil(left / 1000));
    rafId = requestAnimationFrame(tick);
  }
  function restartCycle() {
    cancelAnimationFrame(rafId);
    cycleStart = Date.now();
    ring.style.strokeDashoffset = String(RING_C);
    timerNum.textContent = String(Math.ceil(INTERVAL / 1000));
    rafId = requestAnimationFrame(tick);
    clearInterval(autoTimer);
    autoTimer = setInterval(() => show(cur + 1), INTERVAL);
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight' || e.key === 'PageDown' || e.key === ' ') {
      e.preventDefault(); show(cur + 1);
    }
    if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
      show(cur - 1);
    }
    if (e.key === 'Escape') window.close();
    if (e.key && e.key.toLowerCase() === 'f') toggleFullscreen();
  });

  function toggleFullscreen() {
    const el = document.documentElement;
    if (!document.fullscreenElement && el.requestFullscreen) el.requestFullscreen();
    else if (document.exitFullscreen) document.exitFullscreen();
  }
  fsBtn.addEventListener('click', toggleFullscreen);

  // Kick things off.
  restartCycle();
})();
</script>
<?php endif; ?>

</body>
</html>
