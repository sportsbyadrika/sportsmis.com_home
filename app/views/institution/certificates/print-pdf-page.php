<?php
/**
 * One certificate page's body fragments, in chunks the controller
 * writes individually via $mpdf->WriteHTMLCell($w, $h, $x, $y, $html)
 * so each block lands at the exact mm coordinate without relying on
 * mPDF's flaky position:absolute handling. The HTML in each chunk has
 * no positioning of its own — just inline styling for the content.
 *
 * Sets $pdfPageChunks = [
 *   ['html' => '<...>', 'y' => 15],
 *   ['html' => '<...>', 'y' => 60],
 *   ...
 * ] for the controller to consume.
 *
 * Locals expected:
 *   $cert, $reg, $athlete, $rows, $vars (composed with name etc.),
 *   $bodyHtml (rendered body template),
 *   $chunk, $isFirst, $pageNo, $totalPages,
 *   $meta_top_mm, $body_top_mm, $partb_top_mm, $partb_cont_top_mm,
 *   $cont_name_size_pt, $cont_name_bold, $cont_name_uppercase,
 *   $showMqs, $h (escape helper).
 */
$nameBandTop = max((int)$meta_top_mm + 20,
                   min((int)$partb_cont_top_mm - 10,
                       (int)(((int)$meta_top_mm + (int)$partb_cont_top_mm) / 2)));
$photo   = $reg['passport_photo'] ?? ($athlete['passport_photo'] ?? '');
$initial = $h(strtoupper(substr((string)($reg['athlete_name'] ?? '?'), 0, 1)));
$globalNo = (int)($global_no_offset ?? 0);

$pdfPageChunks = [];

// ── Chunk 1: Meta strip (Certificate No / Competitor No / Date) ──
ob_start();
?>
<table style="width:100%;border-collapse:collapse;font-size:10.5pt;color:#333"><tr>
  <td style="vertical-align:top">
    <div><span style="color:#666"><?= $h($cert_no_label ?? 'Certificate No:') ?></span> <span style="font-weight:700"><?= $h($vars['certificate_no']) ?></span></div>
    <?php if (!empty($show_competitor_no) && $vars['competitor_no'] !== ''): ?>
      <div><span style="color:#666"><?= $h($competitor_no_label ?? 'Competitor No:') ?></span> <span style="font-weight:700"><?= $h($vars['competitor_no']) ?></span></div>
    <?php endif; ?>
  </td>
  <td style="vertical-align:top;text-align:right">
    <span style="color:#666">Date:</span> <span style="font-weight:700"><?= $h($vars['date']) ?></span>
  </td>
</tr></table>
<?php
$pdfPageChunks[] = ['html' => (string)ob_get_clean(), 'y' => (int)$meta_top_mm];

// ── Chunk 2: First-page body block OR continuation name band ──
ob_start();
if ($isFirst):
    $photoW   = max(10, (int)($photo_width_mm    ?? 32));
    $photoH   = max(10, (int)($photo_height_mm   ?? 38));
    $photoGap = max(0,  (int)($photo_name_gap_mm ?? 6));
    $renderPhoto = !empty($show_photo);
?>
  <div style="text-align:center;color:#1a1a2e">
    <div style="font-style:italic;font-size:12.5pt;color:#444;margin-bottom:4mm">This is to certify that</div>
    <?php if ($renderPhoto): ?>
      <?php if (!empty($photo)): ?>
        <img src="<?= $h($photo) ?>" style="width:<?= $photoW ?>mm;height:<?= $photoH ?>mm;border:1px solid #b7bec5;background:#fff" alt="">
      <?php else: ?>
        <div style="display:inline-block;width:<?= $photoW ?>mm;height:<?= $photoH ?>mm;border:1px solid #b7bec5;background:#e9ecef;color:#6c757d;line-height:<?= $photoH ?>mm;text-align:center;font-size:22pt;font-weight:700"><?= $initial ?></div>
      <?php endif; ?>
      <div style="margin-top:<?= $photoGap ?>mm;font-size:12.5pt;line-height:1.6"><?= $bodyHtml ?></div>
    <?php else: ?>
      <div style="font-size:12.5pt;line-height:1.6"><?= $bodyHtml ?></div>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div style="text-align:center;font-size:<?= (int)$cont_name_size_pt ?>pt;<?= !empty($cont_name_bold) ? 'font-weight:700;' : '' ?><?= !empty($cont_name_uppercase) ? 'text-transform:uppercase;' : '' ?>color:#1a1a2e">
    <?= $h($vars['name']) ?>
  </div>
  <?php if (($contBodyHtml ?? '') !== ''): ?>
    <div style="margin-top:<?= (int)($cont_name_gap_mm ?? 6) ?>mm;font-size:12.5pt;line-height:1.6;text-align:center;color:#1a1a2e"><?= $contBodyHtml ?></div>
  <?php endif; ?>
<?php endif;
$pdfPageChunks[] = [
    'html' => (string)ob_get_clean(),
    'y'    => $isFirst ? (int)$body_top_mm : (int)$nameBandTop,
];

// ── Chunk 3: Part B table (+ "Continued —" / "Page X of N" hints) ──
ob_start();
?>
<?php if (!$isFirst && ($page_num_position ?? 'current') === 'current'): ?>
  <div style="font-size:10pt;font-style:italic;color:#555;margin-bottom:4mm;text-align:right">Continued — page <?= $pageNo ?> of <?= $totalPages ?></div>
<?php endif; ?>
<table style="width:100%;border-collapse:collapse;table-layout:fixed;font-size:10pt">
  <colgroup>
    <col style="width:14mm">
    <col><!-- Event takes remaining space -->
    <col style="width:22mm">
    <col style="width:16mm">
    <?php if ($showMqs): ?><col style="width:18mm"><?php endif; ?>
    <col style="width:26mm">
  </colgroup>
  <thead>
    <tr>
      <th style="border:none;border-bottom:2px solid #b08d57;color:#5a3a18;font-weight:700;padding:3px 6px;font-size:9.5pt;text-transform:uppercase;letter-spacing:0.04em;text-align:center">#</th>
      <th style="border:none;border-bottom:2px solid #b08d57;color:#5a3a18;font-weight:700;text-align:left;padding:3px 6px;font-size:9.5pt;text-transform:uppercase;letter-spacing:0.04em">Event</th>
      <th style="border:none;border-bottom:2px solid #b08d57;color:#5a3a18;font-weight:700;padding:3px 6px;font-size:9.5pt;text-transform:uppercase;letter-spacing:0.04em;text-align:center">Score</th>
      <th style="border:none;border-bottom:2px solid #b08d57;color:#5a3a18;font-weight:700;padding:3px 6px;font-size:9.5pt;text-transform:uppercase;letter-spacing:0.04em;text-align:center">Position</th>
      <?php if ($showMqs): ?>
        <th style="border:none;border-bottom:2px solid #b08d57;color:#5a3a18;font-weight:700;padding:3px 6px;font-size:9.5pt;text-transform:uppercase;letter-spacing:0.04em;text-align:center">MQS</th>
      <?php endif; ?>
      <th style="border:none;border-bottom:2px solid #b08d57;color:#5a3a18;font-weight:700;padding:3px 6px;font-size:9.5pt;text-transform:uppercase;letter-spacing:0.04em;text-align:center">Remarks</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($chunk) && $isFirst): ?>
      <tr><td colspan="<?= $showMqs ? 6 : 5 ?>" style="text-align:center;color:#777;border-top:1px solid #d4b482;padding:4px 6px">No event participation recorded.</td></tr>
    <?php else: foreach ($chunk as $row):
      $globalNo++;
      $pos = $row['position'] ?? null;
      $rem = strtoupper((string)($row['remarks'] ?? ''));
      $mqs = $row['mqs'] ?? null;
      $trStyle = '';
      if (!empty($show_medal_row_bg)) {
        if ($rem === 'GOLD')   $trStyle = 'background:#fff4c8';
        if ($rem === 'SILVER') $trStyle = 'background:#ececec';
        if ($rem === 'BRONZE') $trStyle = 'background:#f2dcc0';
      }
    ?>
      <tr<?= $trStyle ? ' style="' . $trStyle . '"' : '' ?>>
        <td style="border:none;border-top:1px solid #d4b482;padding:4px 6px;text-align:center"><?= $globalNo ?></td>
        <td style="border:none;border-top:1px solid #d4b482;padding:4px 6px"><?= $h($row['event']) ?></td>
        <td style="border:none;border-top:1px solid #d4b482;padding:4px 6px;text-align:center"><?= $row['score'] !== null ? $h((int)round((float)$row['score'])) : '—' ?></td>
        <td style="border:none;border-top:1px solid #d4b482;padding:4px 6px;text-align:center"><?= $pos ? (int)$pos : '—' ?></td>
        <?php if ($showMqs): ?>
          <td style="border:none;border-top:1px solid #d4b482;padding:4px 6px;text-align:center"><?= ($mqs !== null && $mqs !== '') ? $h((int)round((float)$mqs)) : '' ?></td>
        <?php endif; ?>
        <td style="border:none;border-top:1px solid #d4b482;padding:4px 6px;text-align:center;font-weight:600"><?= $h($rem) ?></td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>
<?php if ($isFirst && $totalPages > 1 && ($page_num_position ?? 'current') === 'current'): ?>
  <div style="font-size:9.5pt;font-style:italic;color:#555;margin-top:3mm;text-align:right">Page <?= $pageNo ?> of <?= $totalPages ?></div>
<?php endif; ?>
<?php
$pdfPageChunks[] = [
    'html' => (string)ob_get_clean(),
    'y'    => $isFirst ? (int)$partb_top_mm : (int)$partb_cont_top_mm,
];

// ── Chunk 4 (optional): centred footer page number "Page X of Y" ──
if (($page_num_position ?? 'current') === 'footer_center' && (int)$totalPages > 1) {
    ob_start(); ?>
    <div style="text-align:center;font-size:9.5pt;color:#555">Page <?= $pageNo ?> of <?= $totalPages ?></div>
    <?php
    $pdfPageChunks[] = ['html' => (string)ob_get_clean(), 'y' => (int)($page_num_footer_mm ?? 287)];
}
