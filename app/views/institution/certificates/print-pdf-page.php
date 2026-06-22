<?php
/**
 * One certificate page's body fragment, rendered by the controller
 * for each (registration × Part B chunk) pair.
 *
 * The controller calls $mpdf->AddPage() + $mpdf->Image(bg, 0,0,210,297)
 * itself before each include, so this template stops worrying about
 * page boundaries or backgrounds — it just emits the absolute-positioned
 * blocks (meta strip, body / name-band, Part B table) for the current
 * page. Drops every <html>/<head>/<body>/<pagebreak> tag since mPDF
 * receives this via WriteHTML($html, 2) in body-fragment mode.
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
?>
<div class="cert-meta" style="position:absolute;top:<?= (int)$meta_top_mm ?>mm;left:22mm;right:22mm;font-size:10.5pt;color:#333">
  <table style="width:100%;border-collapse:collapse"><tr>
    <td style="vertical-align:top">
      <div><span style="color:#666">Certificate No:</span> <span style="font-weight:700"><?= $h($vars['certificate_no']) ?></span></div>
      <?php if ($vars['competitor_no'] !== ''): ?>
        <div><span style="color:#666">Competitor No:</span> <span style="font-weight:700"><?= $h($vars['competitor_no']) ?></span></div>
      <?php endif; ?>
    </td>
    <td style="vertical-align:top;text-align:right">
      <span style="color:#666">Date:</span> <span style="font-weight:700"><?= $h($vars['date']) ?></span>
    </td>
  </tr></table>
</div>

<?php if ($isFirst): ?>
  <div style="position:absolute;top:<?= (int)$body_top_mm ?>mm;left:22mm;right:22mm;text-align:center;color:#1a1a2e">
    <div style="font-style:italic;font-size:12.5pt;color:#444;margin-bottom:4mm">This is to certify that</div>
    <?php if (!empty($photo)): ?>
      <img src="<?= $h($photo) ?>" style="width:32mm;height:38mm;border:1px solid #b7bec5;background:#fff" alt="">
    <?php else: ?>
      <div style="display:inline-block;width:32mm;height:38mm;border:1px solid #b7bec5;background:#e9ecef;color:#6c757d;line-height:38mm;text-align:center;font-size:22pt;font-weight:700"><?= $initial ?></div>
    <?php endif; ?>
    <div style="margin-top:6mm;font-size:12.5pt;line-height:1.6"><?= $bodyHtml ?></div>
  </div>
<?php else: ?>
  <div style="position:absolute;top:<?= (int)$nameBandTop ?>mm;left:22mm;right:22mm;text-align:center;font-size:<?= (int)$cont_name_size_pt ?>pt;<?= !empty($cont_name_bold) ? 'font-weight:700;' : '' ?><?= !empty($cont_name_uppercase) ? 'text-transform:uppercase;' : '' ?>color:#1a1a2e">
    <?= $h($vars['name']) ?>
  </div>
<?php endif; ?>

<div style="position:absolute;left:22mm;right:22mm;top:<?= $isFirst ? (int)$partb_top_mm : (int)$partb_cont_top_mm ?>mm;font-size:10pt">
  <?php if (!$isFirst): ?>
    <div style="font-size:10pt;font-style:italic;color:#555;margin-bottom:4mm;text-align:right">Continued — page <?= $pageNo ?> of <?= $totalPages ?></div>
  <?php endif; ?>
  <table style="width:100%;border-collapse:collapse">
    <thead>
      <tr>
        <th style="border:none;border-bottom:2px solid #b08d57;color:#5a3a18;font-weight:700;text-align:left;padding:3px 6px;font-size:9.5pt;text-transform:uppercase;letter-spacing:0.04em;width:14mm;text-align:center">#</th>
        <th style="border:none;border-bottom:2px solid #b08d57;color:#5a3a18;font-weight:700;text-align:left;padding:3px 6px;font-size:9.5pt;text-transform:uppercase;letter-spacing:0.04em">Event</th>
        <th style="border:none;border-bottom:2px solid #b08d57;color:#5a3a18;font-weight:700;padding:3px 6px;font-size:9.5pt;text-transform:uppercase;letter-spacing:0.04em;width:22mm;text-align:center">Score</th>
        <th style="border:none;border-bottom:2px solid #b08d57;color:#5a3a18;font-weight:700;padding:3px 6px;font-size:9.5pt;text-transform:uppercase;letter-spacing:0.04em;width:16mm;text-align:center">Position</th>
        <?php if ($showMqs): ?>
          <th style="border:none;border-bottom:2px solid #b08d57;color:#5a3a18;font-weight:700;padding:3px 6px;font-size:9.5pt;text-transform:uppercase;letter-spacing:0.04em;width:18mm;text-align:center">MQS</th>
        <?php endif; ?>
        <th style="border:none;border-bottom:2px solid #b08d57;color:#5a3a18;font-weight:700;padding:3px 6px;font-size:9.5pt;text-transform:uppercase;letter-spacing:0.04em;width:26mm;text-align:center">Remarks</th>
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
        if ($rem === 'GOLD')   $trStyle = 'background:#fff4c8';
        if ($rem === 'SILVER') $trStyle = 'background:#ececec';
        if ($rem === 'BRONZE') $trStyle = 'background:#f2dcc0';
      ?>
        <tr<?= $trStyle ? ' style="' . $trStyle . '"' : '' ?>>
          <td style="border:none;border-top:1px solid #d4b482;padding:4px 6px;text-align:center;width:14mm"><?= $globalNo ?></td>
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
  <?php if ($isFirst && $totalPages > 1): ?>
    <div style="font-size:9.5pt;font-style:italic;color:#555;margin-top:3mm;text-align:right">Page <?= $pageNo ?> of <?= $totalPages ?></div>
  <?php endif; ?>
</div>
