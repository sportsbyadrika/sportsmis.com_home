<?php
$pageTitle = 'Certificate — ' . ($event['name'] ?? '');
$showMqs   = !empty($event['cert_show_mqs']);
$fmtDate = function ($s) {
    if (!$s) return '';
    try { return (new DateTimeImmutable($s))->format('d M Y'); }
    catch (\Throwable $e) { return (string)$s; }
};
$fmtDates = function ($from, $to) use ($fmtDate) {
    if (!$from) return '';
    if (!$to || $from === $to) return $fmtDate($from);
    return $fmtDate($from) . ' – ' . $fmtDate($to);
};
$ageYears = function ($dob) {
    if (!$dob) return '';
    try {
        $d = new DateTimeImmutable($dob);
        return (int)$d->diff(new DateTimeImmutable('today'))->y;
    } catch (\Throwable $e) { return ''; }
};
/* Substitute {{vars}} into the (HTML) template. Values are escaped
   but the surrounding template HTML is intentionally preserved so
   admins can add <strong>, <br>, font-size spans, etc. */
$render = function (string $tpl, array $vars) {
    $h = fn($s) => htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($vars, $h) {
        return $h($vars[$m[1]] ?? '');
    }, $tpl);
};
$h = fn($s) => htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// Explicit row counts from settings — deterministic, no row-height
// guesswork. Fall back to the older mm-based estimate only if the
// admin hasn't picked one yet.
$firstMax = (int)($rows_first ?? 0);
$contMax  = (int)($rows_cont  ?? 0);
if ($firstMax <= 0) $firstMax = max(1, (int)floor(((int)$partb_max_mm      - 10) / 8.5));
if ($contMax  <= 0) $contMax  = max(1, (int)floor(((int)$partb_cont_max_mm - 10) / 8.5));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $h($pageTitle) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  @page { size: A4 portrait; margin: 0; }
  html, body { background:#eef2f7; margin:0; padding:0;
               font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
               color:#222; }
  .actions { max-width:210mm; margin:12px auto; display:flex; gap:8px; justify-content:flex-end; padding:0 12px; }
  @media print { .actions { display: none !important; } }
  .cert-page {
    position: relative;
    width: 210mm; height: 297mm;
    margin: 0 auto 18px;
    background: #fff;
    box-shadow: 0 6px 18px rgba(0,0,0,.12);
    overflow: hidden;
    page-break-after: always;
  }
  .cert-page:last-child { page-break-after: auto; }
  .cert-bg {
    position:absolute; inset:0; width:100%; height:100%;
    object-fit: cover;
  }

  /* Meta strip — position from top is configurable. */
  .cert-meta {
    position: absolute;
    top: <?= (int)$meta_top_mm ?>mm;
    left: 22mm; right: 22mm;
    display: flex; justify-content: space-between; align-items: flex-start;
    font-size: 10.5pt; color: #333;
  }
  .cert-meta .label { color:#666; letter-spacing:.02em; }
  .cert-meta .val   { font-weight: 700; }

  /* Body block — its top is the user-configured body_top_mm. */
  .cert-body-block {
    position: absolute;
    top: <?= (int)$body_top_mm ?>mm;
    left: 22mm; right: 22mm;
    text-align: center;
    color: #1a1a2e;
  }
  .cert-label {
    font-style: italic; font-size: 12.5pt; color: #444;
    margin-bottom: 4mm;
  }
  .cert-photo, .cert-photo-fallback {
    width: 32mm; height: 38mm; object-fit: cover;
    border: 1px solid #b7bec5; background:#fff;
    display: inline-block;
  }
  .cert-photo-fallback {
    background:#e9ecef; color:#6c757d; line-height: 38mm;
    text-align: center; font-size: 22pt; font-weight: 700;
  }
  .cert-body {
    margin-top: 6mm;
    font-size: 12.5pt; line-height: 1.6;
  }

  /* Part B box — same .partb selector for first page AND
     continuation, so all the table styling below is shared. The
     .partb-cont modifier just shifts the top offset. */
  .partb {
    position: absolute;
    left: 22mm; right: 22mm;
    top: <?= (int)$partb_top_mm ?>mm;
    font-size: 10pt;
  }
  .partb.partb-cont {
    top: <?= (int)$partb_cont_top_mm ?>mm;
  }
  .partb table { width: 100%; border-collapse: collapse; }
  .partb thead th {
    background: transparent !important;
    border: none;
    border-bottom: 2px solid #b08d57;        /* a single underline beneath the header */
    color: #5a3a18; font-weight: 700;
    text-align: left;
    padding: 3px 6px;
    font-size: 9.5pt;
    text-transform: uppercase;
    letter-spacing: .04em;
  }
  .partb tbody td {
    border: none;
    border-top: 1px solid #d4b482;           /* the one line per row the spec asks for */
    padding: 4px 6px;
    vertical-align: middle;
  }
  /* Medal row tints — Gold / Silver / Bronze. */
  .partb tbody tr.medal-gold   td { background: #fff4c8; }
  .partb tbody tr.medal-silver td { background: #ececec; }
  .partb tbody tr.medal-bronze td { background: #f2dcc0; }
  .partb .no    { width:14mm; text-align:center; }
  .partb .score { width:22mm; text-align:center; }
  .partb .pos   { width:16mm; text-align:center; }
  .partb .mqs   { width:18mm; text-align:center; }
  .partb .rem   { width:26mm; text-align:center; font-weight: 600; }
  /* "Continued — page X of Y" hint above the overflow table. Named
     distinctly from the .partb-cont wrapper class so its
     right-aligned text-align doesn't cascade into the table cells. */
  .partb-cont-hint { font-size: 10pt; font-style: italic; color: #555;
                     margin-bottom: 4mm; text-align: right; }
  /* "Page X of N" footer printed below the table on the first page
     when the participation list overflows onto continuation sheets. */
  .partb-page-count { font-size: 9.5pt; font-style: italic; color: #555;
                      margin-top: 3mm; text-align: right; }
  /* Athlete name banner shown on continuation pages so the reader
     can tell whose Part B continues here. Font size / weight /
     case are admin-configurable from Certificate Settings. */
  .cert-name-band {
    position: absolute;
    left: 22mm; right: 22mm;
    text-align: center;
    font-size: <?= (int)$cont_name_size_pt ?>pt;
    font-weight: <?= !empty($cont_name_bold) ? 700 : 400 ?>;
    color: #1a1a2e;
    text-transform: <?= !empty($cont_name_uppercase) ? 'uppercase' : 'none' ?>;
    letter-spacing: .03em;
  }

  @media print {
    body { background:#fff; }
    .actions { display: none !important; }
    .cert-page { box-shadow: none; margin: 0 auto; }
  }
</style>
</head>
<body>

<div class="actions">
  <button type="button" onclick="window.print()"
          style="padding:6px 14px;border:1px solid #cbd5e1;background:#fff;border-radius:6px;cursor:pointer">
    Print / Save as PDF
  </button>
  <button type="button" onclick="window.close()"
          style="padding:6px 14px;border:1px solid #cbd5e1;background:#fff;border-radius:6px;cursor:pointer">
    Close
  </button>
</div>

<?php foreach ($registrations as $r):
    $cert     = $r['cert'];
    $reg      = $r['reg'];
    $athlete  = $r['athlete'] ?? [];
    $rows     = $r['rows'] ?? [];
    $vars = [
        'certificate_no'  => $cert['certificate_no'] ?? '',
        'date'            => $fmtDate($cert['generated_at'] ?? null),
        'competitor_no'   => $reg['competitor_number']
                              ? str_pad((string)(int)$reg['competitor_number'], 4, '0', STR_PAD_LEFT)
                              : '',
        'name'            => $reg['athlete_name'] ?? '',
        'unit_name'       => $reg['unit_name']   ?? ($reg['unit_name_other'] ?? ''),
        'unit_address'    => $reg['unit_address'] ?? '',
        'event_name'      => $event['name']      ?? '',
        'event_dates'     => $fmtDates($event['event_date_from'] ?? null, $event['event_date_to'] ?? null),
        'event_location'  => $event['location']  ?? '',
        'age'             => $ageYears($reg['date_of_birth'] ?? null),
        'gender'          => ucfirst((string)($reg['gender'] ?? '')),
    ];
    $bodyHtml = $render($body_template, $vars);
    $photo = $reg['passport_photo'] ?? ($athlete['passport_photo'] ?? '');
    $initial = $h(strtoupper(substr((string)($reg['athlete_name'] ?? '?'), 0, 1)));

    // Split rows: first chunk uses the body-page Part B budget;
    // subsequent chunks use the continuation-page budget.
    $rowChunks = [];
    if ($rows) {
        $first = array_slice($rows, 0, $firstMax);
        $rowChunks[] = $first;
        $rest = array_slice($rows, $firstMax);
        if (!empty($rest)) {
            foreach (array_chunk($rest, $contMax) as $c) $rowChunks[] = $c;
        }
    } else {
        $rowChunks = [[]];
    }
    $totalPages = count($rowChunks);
    $globalNo   = 0;
?>
  <?php foreach ($rowChunks as $pi => $chunk):
        $isFirst = ($pi === 0);
        $pageNo  = $pi + 1;
        // Name-band sits halfway between the meta strip and the
        // continuation Part B box, so it reads naturally as a
        // "for: <name>" header.
        $nameBandTop = max((int)$meta_top_mm + 20,
                           min((int)$partb_cont_top_mm - 10,
                               (int)(((int)$meta_top_mm + (int)$partb_cont_top_mm) / 2)));
  ?>
  <section class="cert-page">
    <?php if (!empty($bg_image)): ?>
      <img class="cert-bg" src="<?= $h($bg_image) ?>" alt="">
    <?php endif; ?>

    <!-- Meta strip (Cert No / Comp No / Date) on every page so the
         continuation sheet is identifiable on its own. -->
    <?php
      $certNoLabel    = (string)($event['cert_no_label']            ?? 'Certificate No:');
      $showCompNoCert = (int)($event['cert_show_competitor_no']   ?? 1);
      $compNoLabel    = (string)($event['cert_competitor_no_label'] ?? 'Competitor No:');
    ?>
    <div class="cert-meta">
      <div>
        <div><span class="label"><?= $h($certNoLabel) ?></span> <span class="val"><?= $h($vars['certificate_no']) ?></span></div>
        <?php if ($showCompNoCert && $vars['competitor_no'] !== ''): ?>
          <div><span class="label"><?= $h($compNoLabel) ?></span> <span class="val"><?= $h($vars['competitor_no']) ?></span></div>
        <?php endif; ?>
      </div>
      <div>
        <span class="label">Date:</span> <span class="val"><?= $h($vars['date']) ?></span>
      </div>
    </div>

    <?php if ($isFirst):
      $photoW   = max(10, (int)($event['cert_photo_width_mm']    ?? 32));
      $photoH   = max(10, (int)($event['cert_photo_height_mm']   ?? 38));
      $photoGap = max(0,  (int)($event['cert_photo_name_gap_mm'] ?? 6));
      $renderPhoto = (int)($event['cert_show_photo'] ?? 1);
    ?>
      <div class="cert-body-block">
        <div class="cert-label">This is to certify that</div>
        <?php if ($renderPhoto): ?>
          <?php if (!empty($photo)): ?>
            <img src="<?= $h($photo) ?>" class="cert-photo" style="width:<?= $photoW ?>mm;height:<?= $photoH ?>mm" alt="">
          <?php else: ?>
            <div class="cert-photo-fallback" style="width:<?= $photoW ?>mm;height:<?= $photoH ?>mm;line-height:<?= $photoH ?>mm"><?= $initial ?></div>
          <?php endif; ?>
          <div class="cert-body" style="margin-top:<?= $photoGap ?>mm"><?= $bodyHtml ?></div>
        <?php else: ?>
          <div class="cert-body"><?= $bodyHtml ?></div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <!-- Sub-header on continuation pages: the athlete's name so
           the reader can tell which cert this sheet continues. -->
      <div class="cert-name-band" style="top:<?= (int)$nameBandTop ?>mm">
        <?= $h($vars['name']) ?>
      </div>
    <?php endif; ?>

    <div class="partb<?= $isFirst ? '' : ' partb-cont' ?>">
      <?php if (!$isFirst): ?>
        <div class="partb-cont-hint">Continued — page <?= $pageNo ?> of <?= $totalPages ?></div>
      <?php endif; ?>
      <table>
        <thead>
          <tr>
            <th class="no">#</th>
            <th>Event</th>
            <th class="score">Score</th>
            <th class="pos">Position</th>
            <?php if ($showMqs): ?><th class="mqs">MQS</th><?php endif; ?>
            <th class="rem">Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($chunk) && $isFirst): ?>
            <tr><td colspan="<?= $showMqs ? 6 : 5 ?>" style="text-align:center;color:#777;border-top:1px solid #d4b482">
              No event participation recorded.
            </td></tr>
          <?php else:
            $tintMedalBg = (int)($event['cert_show_medal_row_bg'] ?? 1);
            foreach ($chunk as $row):
              $globalNo++;
              $pos = $row['position'] ?? null;
              $rem = strtoupper((string)($row['remarks'] ?? ''));
              $mqs = $row['mqs'] ?? null;
              $cls = '';
              if ($tintMedalBg && in_array($rem, ['GOLD','SILVER','BRONZE'], true)) {
                  $cls = 'medal-' . strtolower($rem);
              }
          ?>
            <tr<?= $cls ? ' class="' . $cls . '"' : '' ?>>
              <td class="no"><?= $globalNo ?></td>
              <td class="event"><?= $h($row['event']) ?></td>
              <td class="score">
                <?= $row['score'] !== null ? $h((int)round((float)$row['score'])) : '—' ?>
              </td>
              <td class="pos"><?= $pos ? (int)$pos : '—' ?></td>
              <?php if ($showMqs): ?>
                <td class="mqs">
                  <?= ($mqs !== null && $mqs !== '') ? $h((int)round((float)$mqs)) : '' ?>
                </td>
              <?php endif; ?>
              <td class="rem"><?= $h($rem) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
      <?php if ($isFirst && $totalPages > 1): ?>
        <div class="partb-page-count">Page <?= $pageNo ?> of <?= $totalPages ?></div>
      <?php endif; ?>
    </div>
  </section>
  <?php endforeach; ?>
<?php endforeach; ?>
</body>
</html>
