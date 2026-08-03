<?php
/**
 * LED-wall slide bodies (winner slides + unit-wise points slide) — the inner
 * content of .slide-host. Rendered both by led-wall/show.php and by the
 * background-refresh endpoint (LedWallController::slides) so the deck can be
 * refreshed in place without a page reload (which would drop out of fullscreen).
 * Expects: $events, $units. Self-contained (defines its own helpers).
 */
$h = $h ?? fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$events = $events ?? [];
$units  = $units ?? [];
$winnerSlides = array_chunk($events, 3);

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
?>
<?php if (empty($winnerSlides) && empty($units)): ?>
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
    // Build the rows once; render twice so the scroll wraps seamlessly.
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
