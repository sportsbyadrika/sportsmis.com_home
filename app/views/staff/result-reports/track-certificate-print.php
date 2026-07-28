<?php
/**
 * Athletics / Skating — Certificate overlay print. Prints ONLY the variable
 * fields at their configured X/Y (mm) positions, one certificate per page, for
 * printing onto pre-printed certificate paper. Blank background.
 * Expects: $event, $type ('merit'|'appreciation'), $config, $certs.
 */
$isLandscape = ($config['orientation'] ?? 'landscape') === 'landscape';
$fields = $config['fields'];
// Certificate font (user-typed name) with a serif fallback.
$fontName = trim((string)($config['font'] ?? '')) ?: 'Georgia';
$fontStack = '"' . str_replace('"', '', $fontName) . '", "Georgia", "Times New Roman", serif';
// Certificate date, formatted per the configured format. When no date is set
// in the layout, fall back to today so the Date field still prints (turn the
// field "Off" in the layout to hide it entirely).
$dateFmt = (string)($config['date_format'] ?? 'd M Y');
if (!in_array($dateFmt, ['d M Y', 'd F Y', 'd-m-Y', 'd/m/Y'], true)) $dateFmt = 'd M Y';
$cd = trim((string)($config['cert_date'] ?? ''));
$ts = ($cd !== '' && ($t = strtotime($cd))) ? $t : time();
$certDate = date($dateFmt, $ts);

// Resolve a field's printed value for a given certificate.
$valueFor = function (string $key, array $cert) use ($config, $certDate): string {
    switch ($key) {
        // Athlete name is printed in capitals on the certificate.
        case 'name':        return mb_strtoupper((string)($cert['name'] ?? ''), 'UTF-8');
        case 'school':      return (string)($cert['school'] ?? '');
        case 'prize':       return (string)($cert['prize'] ?? '');
        case 'event':       return (string)($cert['event'] ?? '');
        case 'event_label': return (string)($cert['event_label'] ?? '');
        case 'cert_no':     return (string)($cert['cert_no'] ?? '');
        case 'date':        return $certDate;
        case 'const1':      return (string)($config['const1_text'] ?? '');
        case 'const2':      return (string)($config['const2_text'] ?? '');
        case 'const3':      return (string)($config['const3_text'] ?? '');
    }
    return '';
};
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Certificates — <?= e($event['name'] ?? '') ?></title>
<style>
  @page { size: A4 <?= $isLandscape ? 'landscape' : 'portrait' ?>; margin: 0; }
  * { font-family: <?= $fontStack ?>; }
  html, body { margin:0; padding:0; background:#fff; color:#111; }
  .cert {
    position: relative;
    width: <?= $isLandscape ? '297mm' : '210mm' ?>;
    height: <?= $isLandscape ? '210mm' : '297mm' ?>;
    page-break-after: always;
    overflow: hidden;
  }
  .cert:last-child { page-break-after: auto; }
  .fld { position:absolute; transform: translateY(-50%); white-space: nowrap; }
  /* On-screen guide only — never prints. */
  @media screen {
    body { background:#e9ecef; }
    .cert { background:#fff; margin:10px auto; box-shadow:0 0 8px rgba(0,0,0,.25); outline:1px dashed #bbb; }
    .hint { position:fixed; top:0; left:0; right:0; background:#111; color:#fff; font-family:sans-serif;
            font-size:12px; padding:6px 10px; z-index:10; }
    .cert { margin-top:0; }
    body { padding-top:34px; }
  }
  @media print { .hint { display:none; } }
</style>
</head>
<body>
  <div class="hint">
    Preview — prints onto your pre-printed certificate paper (<?= $isLandscape ? 'landscape' : 'portrait' ?>).
    <?= count($certs) ?> certificate<?= count($certs) === 1 ? '' : 's' ?> generated. Use your browser Print dialog; set margins to “None”.
    <button onclick="certPrint()" style="margin-left:8px">Print &amp; mark printed</button>
  </div>

  <?php if (empty($certs)): ?>
    <div style="font-family:sans-serif;padding:40px;color:#666">No certificates to generate for this selection.</div>
  <?php else: foreach ($certs as $cert): ?>
    <div class="cert">
      <?php foreach ($fields as $key => $fv):
        if (empty($fv['enabled'])) continue;
        $val = $valueFor($key, $cert);
        if ($val === '') continue;
        $wt = !empty($fv['bold'])   ? 'font-weight:bold;'  : '';
        $st = !empty($fv['italic']) ? 'font-style:italic;' : ''; ?>
        <div class="fld" style="left:<?= (float)$fv['x'] ?>mm; top:<?= (float)$fv['y'] ?>mm; font-size:<?= (float)$fv['size'] ?>pt;<?= $wt . $st ?>"><?= e($val) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; endif; ?>

<script>
  // These certificates are already recorded as "generated". Clicking Print
  // also flags them "printed" in the issue register.
  var CERT_IDS  = <?= json_encode(array_values(array_map('intval', $issued_ids ?? []))) ?>;
  var CERT_CSRF = <?= json_encode($csrf ?? '') ?>;
  function certPrint() {
    if (CERT_IDS.length && CERT_CSRF) {
      try {
        var fd = new FormData();
        fd.append('_token', CERT_CSRF);
        CERT_IDS.forEach(function (id) { fd.append('ids[]', id); });
        fetch('/event-staff/result-reports/certificate/mark-printed', {
          method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).catch(function () {});
      } catch (e) {}
    }
    window.print();
  }
</script>
</body>
</html>
