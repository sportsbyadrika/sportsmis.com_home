<?php
/**
 * mPDF-targeted variant of print.php — same data + same CSS, but
 * structured so mPDF doesn't choke on the .cert-page wrapper.
 *
 * mPDF positions `position: absolute` elements relative to the
 * CURRENT page, not the containing block. The browser-print template
 * wraps each cert in <section class="cert-page" style="height:297mm">
 * which mPDF then tries to flow as content — exploding the page count
 * into hundreds of mostly-blank pages. Here we drop the wrapper and
 * insert mPDF's native <pagebreak/> tag between certs / continuation
 * pages so each cert renders on exactly one A4 page (or N pages when
 * Part B overflows).
 *
 * Everything else (the meta strip, body, photo, Part B table, etc.)
 * stays identical so the visual output matches the browser print
 * preview.
 */
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
$render = function (string $tpl, array $vars) {
    $h = fn($s) => htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($vars, $h) {
        return $h($vars[$m[1]] ?? '');
    }, $tpl);
};
$h = fn($s) => htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$firstMax = (int)($rows_first ?? 0);
$contMax  = (int)($rows_cont  ?? 0);
if ($firstMax <= 0) $firstMax = max(1, (int)floor(((int)$partb_max_mm      - 10) / 8.5));
if ($contMax  <= 0) $contMax  = max(1, (int)floor(((int)$partb_cont_max_mm - 10) / 8.5));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  @page { size: A4 portrait; margin: 0; }
  body { margin: 0; padding: 0; color: #222;
         font-family: dejavusans, "DejaVu Sans", sans-serif; }
  /* Full-bleed background — placed at top:0 left:0, sized to A4. */
  .cert-bg {
    position: absolute; top: 0; left: 0;
    width: 210mm; height: 297mm;
  }
  .cert-meta {
    position: absolute;
    top: <?= (int)$meta_top_mm ?>mm;
    left: 22mm; right: 22mm;
    font-size: 10.5pt; color: #333;
  }
  .cert-meta table { width: 100%; border-collapse: collapse; }
  .cert-meta td.right { text-align: right; }
  .cert-meta .label { color:#666; letter-spacing:.02em; }
  .cert-meta .val   { font-weight: 700; }

  .cert-body-block {
    position: absolute;
    top: <?= (int)$body_top_mm ?>mm;
    left: 22mm; right: 22mm;
    text-align: center;
    color: #1a1a2e;
  }
  .cert-label { font-style: italic; font-size: 12.5pt; color: #444; margin-bottom: 4mm; }
  .cert-photo, .cert-photo-fallback {
    width: 32mm; height: 38mm; object-fit: cover;
    border: 1px solid #b7bec5; background:#fff;
  }
  .cert-photo-fallback {
    background:#e9ecef; color:#6c757d; line-height: 38mm;
    text-align: center; font-size: 22pt; font-weight: 700;
  }
  .cert-body { margin-top: 6mm; font-size: 12.5pt; line-height: 1.6; }

  .partb {
    position: absolute;
    left: 22mm; right: 22mm;
    top: <?= (int)$partb_top_mm ?>mm;
    font-size: 10pt;
  }
  .partb.partb-cont { top: <?= (int)$partb_cont_top_mm ?>mm; }
  .partb table { width: 100%; border-collapse: collapse; }
  .partb thead th {
    border: none;
    border-bottom: 2px solid #b08d57;
    color: #5a3a18; font-weight: 700;
    text-align: left;
    padding: 3px 6px;
    font-size: 9.5pt;
    text-transform: uppercase;
    letter-spacing: .04em;
  }
  .partb tbody td {
    border: none;
    border-top: 1px solid #d4b482;
    padding: 4px 6px;
    vertical-align: middle;
  }
  .partb tbody tr.medal-gold   td { background: #fff4c8; }
  .partb tbody tr.medal-silver td { background: #ececec; }
  .partb tbody tr.medal-bronze td { background: #f2dcc0; }
  .partb .no    { width:14mm; text-align:center; }
  .partb .score { width:22mm; text-align:center; }
  .partb .pos   { width:16mm; text-align:center; }
  .partb .mqs   { width:18mm; text-align:center; }
  .partb .rem   { width:26mm; text-align:center; font-weight: 600; }
  .partb-cont-hint { font-size: 10pt; font-style: italic; color: #555;
                     margin-bottom: 4mm; text-align: right; }
  .partb-page-count { font-size: 9.5pt; font-style: italic; color: #555;
                      margin-top: 3mm; text-align: right; }
  .cert-name-band {
    position: absolute;
    left: 22mm; right: 22mm;
    text-align: center;
    font-size: <?= (int)$cont_name_size_pt ?>pt;
    <?= !empty($cont_name_bold)      ? 'font-weight: 700;'      : '' ?>
    <?= !empty($cont_name_uppercase) ? 'text-transform: uppercase;' : '' ?>
    color: #1a1a2e;
  }
</style>
</head>
<body>
<?php
$wroteOnePage = false;
foreach ($registrations as $r):
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
        'unit_name'       => $reg['unit_name']    ?? ($reg['unit_name_other'] ?? ''),
        'unit_address'    => $reg['unit_address'] ?? '',
        'event_name'      => $event['name']       ?? '',
        'event_dates'     => $fmtDates($event['event_date_from'] ?? null, $event['event_date_to'] ?? null),
        'event_location'  => $event['location']   ?? '',
        'age'             => $ageYears($reg['date_of_birth'] ?? null),
        'gender'          => ucfirst((string)($reg['gender'] ?? '')),
    ];
    $bodyHtml = $render($body_template, $vars);
    $photo = $reg['passport_photo'] ?? ($athlete['passport_photo'] ?? '');
    $initial = $h(strtoupper(substr((string)($reg['athlete_name'] ?? '?'), 0, 1)));

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
    foreach ($rowChunks as $pi => $chunk):
        $isFirst = ($pi === 0);
        $pageNo  = $pi + 1;
        $nameBandTop = max((int)$meta_top_mm + 20,
                           min((int)$partb_cont_top_mm - 10,
                               (int)(((int)$meta_top_mm + (int)$partb_cont_top_mm) / 2)));
        if ($wroteOnePage): ?>
<pagebreak />
<?php endif; $wroteOnePage = true; ?>

<?php if (!empty($bg_image)): ?>
<img class="cert-bg" src="<?= $h($bg_image) ?>" alt="">
<?php endif; ?>

<div class="cert-meta">
  <table><tr>
    <td>
      <span class="label">Certificate No:</span> <span class="val"><?= $h($vars['certificate_no']) ?></span>
      <?php if ($vars['competitor_no'] !== ''): ?>
        &nbsp;&middot;&nbsp;
        <span class="label">Competitor No:</span> <span class="val"><?= $h($vars['competitor_no']) ?></span>
      <?php endif; ?>
    </td>
    <td class="right">
      <span class="label">Date:</span> <span class="val"><?= $h($vars['date']) ?></span>
    </td>
  </tr></table>
</div>

<?php if ($isFirst): ?>
  <div class="cert-body-block">
    <div class="cert-label">This is to certify that</div>
    <?php if (!empty($photo)): ?>
      <img src="<?= $h($photo) ?>" class="cert-photo" alt="">
    <?php else: ?>
      <div class="cert-photo-fallback"><?= $initial ?></div>
    <?php endif; ?>
    <div class="cert-body"><?= $bodyHtml ?></div>
  </div>
<?php else: ?>
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
      <?php else: foreach ($chunk as $row):
          $globalNo++;
          $pos = $row['position'] ?? null;
          $rem = strtoupper((string)($row['remarks'] ?? ''));
          $mqs = $row['mqs'] ?? null;
          $cls = '';
          if (in_array($rem, ['GOLD','SILVER','BRONZE'], true)) {
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

<?php
    endforeach; // rowChunks
endforeach; // registrations
?>
</body>
</html>
