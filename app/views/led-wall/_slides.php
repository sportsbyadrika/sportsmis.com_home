<?php
/**
 * LED-wall slide bodies (winner slides + unit-wise points slide) — the inner
 * content of .slide-host. Rendered both by led-wall/show.php and by the
 * background-refresh endpoint (LedWallController::slides) so the deck can be
 * refreshed in place without a page reload (which would drop out of fullscreen).
 * Expects: $events, $age_top, $units. Self-contained (defines its own helpers).
 */
$h = $h ?? fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$events      = $events ?? [];
$ageTop      = $age_top ?? [];
$ageTopUnits = $age_top_units ?? [];
$units       = $units ?? [];
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
// Age-category card: one medal-place cell may hold several tied athletes.
$posLabel = [1 => '🥇 First', 2 => '🥈 Second', 3 => '🥉 Third'];
$renderAgePlace = function (array $list) use ($h): string {
    if (empty($list)) return '<div class="win empty">—</div>';
    $out = '';
    foreach ($list as $a) {
        $img   = (string)($a['photo'] ?? '');
        $chest = (string)($a['chest'] ?? '');
        $name  = (string)($a['name'] ?? '');
        $unit  = (string)($a['unit'] ?? '');
        $pts   = (int)($a['points'] ?? 0);
        $g = (int)($a['gold'] ?? 0); $s = (int)($a['silver'] ?? 0); $b = (int)($a['bronze'] ?? 0);
        $ph = $img !== ''
            ? '<img src="' . $h($img) . '" alt="">'
            : '<span class="ph-ph"><i class="bi bi-person"></i></span>';
        $out .= '<div class="win">'
              . '<div class="ph">' . $ph . '</div>'
              . '<div class="wi">'
              . '<div class="wn">' . ($chest !== '' ? '<span class="ch">' . $h($chest) . '</span> ' : '') . $h($name) . '</div>'
              . ($unit !== '' ? '<div class="wu">' . $h($unit) . '</div>' : '')
              . '<div class="wpts"><span class="ptsb">' . $pts . ' pts</span> '
              . '<span class="mdl">🥇' . $g . ' 🥈' . $s . ' 🥉' . $b . '</span></div>'
              . '</div></div>';
    }
    return $out;
};
?>
<?php if (empty($winnerSlides) && empty($ageTop) && empty($ageTopUnits) && empty($units)): ?>
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

  <?php foreach ($ageTop as $ag): ?>
    <section class="slide agetop">
      <div class="age-head"><i class="bi bi-tag"></i> <?= $h($ag['age'] ?? '') ?>
        <span class="age-tag">Top Athletes</span></div>
      <div class="wtable">
        <div class="wrow whead">
          <div class="wc ev"><span class="lbl">Category</span></div>
          <div class="wc p1"><span class="lbl">🥇 First</span></div>
          <div class="wc p2"><span class="lbl">🥈 Second</span></div>
          <div class="wc p3"><span class="lbl">🥉 Third</span></div>
        </div>
        <?php foreach (($ag['genders'] ?? []) as $gp):
          $byPos = [1 => [], 2 => [], 3 => []];
          foreach (($gp['athletes'] ?? []) as $a) { $p = (int)($a['pos'] ?? 0); if (isset($byPos[$p])) $byPos[$p][] = $a; }
        ?>
          <div class="wrow">
            <div class="wc ev"><div class="evn"><?= $h($gp['gender'] ?? '') ?></div></div>
            <div class="wc p1"><?= $renderAgePlace($byPos[1]) ?></div>
            <div class="wc p2"><?= $renderAgePlace($byPos[2]) ?></div>
            <div class="wc p3"><?= $renderAgePlace($byPos[3]) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <?php foreach ($ageTopUnits as $ag): $top3 = array_slice($ag['units'] ?? [], 0, 3); ?>
    <section class="slide ageunits">
      <div class="age-head"><i class="bi bi-buildings-fill"></i> <?= $h($ag['age'] ?? '') ?>
        <span class="age-tag">Top Institutions</span></div>
      <div class="ti-table">
        <div class="ti-row ti-head">
          <div class="tc rk">#</div>
          <div class="tc nm">Institution</div>
          <div class="tc c">🥇</div><div class="tc c">🥈</div><div class="tc c">🥉</div>
          <div class="tc pts">Points</div>
        </div>
        <?php foreach ($top3 as $i => $u): ?>
          <div class="ti-row<?= $i === 0 ? ' lead' : '' ?>">
            <div class="tc rk"><?= $i + 1 ?></div>
            <div class="tc nm">
              <?php if (!empty($u['logo'])): ?><img class="ulogo" src="<?= $h($u['logo']) ?>" alt=""><?php endif; ?>
              <span><?= $h($u['unit'] ?? '') ?></span>
            </div>
            <div class="tc c"><?= (int)($u['g'] ?? 0) ?></div>
            <div class="tc c"><?= (int)($u['s'] ?? 0) ?></div>
            <div class="tc c"><?= (int)($u['b'] ?? 0) ?></div>
            <div class="tc pts"><?= (int)($u['points'] ?? 0) ?></div>
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
