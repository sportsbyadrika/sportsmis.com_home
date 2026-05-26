<?php
$pageTitle = 'Certificate — ' . ($event['name'] ?? '');
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
    return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($vars) {
        return $vars[$m[1]] ?? '';
    }, $tpl);
};
$h = fn($s) => htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
  .cert-overlay {
    position: absolute; inset: 0;
    padding: 90mm 22mm 30mm;   /* tuned for the TRA template — leaves the heading + signature areas visible */
    display: flex;
    flex-direction: column;
    gap: 8mm;
  }
  .cert-meta {
    display:flex; justify-content:space-between; font-size: 10pt;
    color:#444;
  }
  .cert-meta .cert-no { font-weight:700; letter-spacing:.04em; }
  .cert-row {
    display: flex; gap: 14mm; align-items: flex-start;
  }
  .cert-photo {
    width: 32mm; height: 38mm; object-fit: cover;
    border: 1px solid #b7bec5; background:#fff;
  }
  .cert-photo-fallback {
    width: 32mm; height: 38mm; background:#e9ecef; color:#6c757d;
    display:flex; align-items:center; justify-content:center;
    font-size:22pt; font-weight:700; border:1px solid #b7bec5;
  }
  .cert-body {
    flex: 1; font-size: 12.5pt; line-height: 1.55;
    text-align: justify;
  }
  .partb {
    margin-top: 2mm; font-size: 10pt;
  }
  .partb table { width: 100%; border-collapse: collapse; }
  .partb th, .partb td {
    border: 1px solid #555; padding: 3px 6px; vertical-align: middle;
  }
  .partb thead th { background: rgba(233, 236, 239, 0.85); font-size: 9.5pt;
                    text-align: center; }
  .partb tbody td.kind, .partb tbody td.pos, .partb tbody td.score { text-align: center; }
  .partb tbody td.no    { width:14mm; text-align:center; }
  .partb tbody td.event { font-weight: 500; }
  @media print {
    body { background:#fff; }
    .actions { display: none !important; }
    .cert-page { box-shadow: none; margin: 0 auto; }
  }
</style>
</head>
<body>

<div class="actions">
  <button type="button" onclick="window.print()" class="action-btn"
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
        'event_name'      => $event['name']      ?? '',
        'event_dates'     => $fmtDates($event['event_date_from'] ?? null, $event['event_date_to'] ?? null),
        'event_location'  => $event['location']  ?? '',
        'age'             => $ageYears($reg['date_of_birth'] ?? null),
        'gender'          => ucfirst((string)($reg['gender'] ?? '')),
    ];
    $body = nl2br($h($render($body_template, $vars)));
    $photo = $reg['passport_photo'] ?? ($athlete['passport_photo'] ?? '');
    $initial = $h(strtoupper(substr((string)($reg['athlete_name'] ?? '?'), 0, 1)));
?>
  <section class="cert-page">
    <?php if (!empty($bg_image)): ?>
      <img class="cert-bg" src="<?= $h($bg_image) ?>" alt="">
    <?php endif; ?>
    <div class="cert-overlay">
      <div class="cert-meta">
        <div>Cert. No. <span class="cert-no"><?= $h($vars['certificate_no']) ?></span></div>
        <div>Date: <strong><?= $h($vars['date']) ?></strong></div>
      </div>

      <div class="cert-row">
        <?php if (!empty($photo)): ?>
          <img src="<?= $h($photo) ?>" class="cert-photo" alt="">
        <?php else: ?>
          <div class="cert-photo-fallback"><?= $initial ?></div>
        <?php endif; ?>
        <div class="cert-body"><?= $body ?></div>
      </div>

      <div class="partb">
        <table>
          <thead>
            <tr>
              <th style="width:14mm">#</th>
              <th>Event</th>
              <th style="width:24mm">Score</th>
              <th style="width:18mm">Position</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="5" style="text-align:center;color:#777">No event participation recorded.</td></tr>
            <?php else: foreach ($rows as $i => $row): ?>
              <tr>
                <td class="no"><?= $i + 1 ?></td>
                <td class="event"><?= $h($row['event']) ?></td>
                <td class="score">
                  <?= $row['score'] !== null
                        ? $h((int)round((float)$row['score']))
                        : '—' ?>
                </td>
                <td class="pos">
                  <?= $row['position'] ? (int)$row['position'] : '—' ?>
                </td>
                <td class="remarks">
                  <?= $h(strtoupper((string)($row['remarks'] ?? ''))) ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
<?php endforeach; ?>
</body>
</html>
