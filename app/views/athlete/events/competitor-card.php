<?php
$pageTitle = 'Competitor Card #' . (int)$registration['competitor_number'];
$photo = $athlete['passport_photo'] ?? '';
$cfg   = require CONFIG_ROOT . '/app.php';
$verifyUrl = rtrim($cfg['url'], '/') . '/athlete/registrations/' . (int)$registration['id'] . '/card';
$qrSrc     = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&margin=4&data=' . rawurlencode($verifyUrl);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Competitor Card — <?= e($athlete['name']) ?> · #<?= (int)$registration['competitor_number'] ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
  body { background:#eef2f7; font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; padding:24px 0; }
  .cc-actions { max-width:840px; margin:0 auto 16px; display:flex; gap:8px; justify-content:flex-end; }
  .cc-card { max-width:840px; margin:0 auto; background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 8px 24px rgba(2,8,23,.08); border:1px solid #e2e8f0; }
  .cc-header { background:linear-gradient(135deg,#0b1f3a,#1f3b7a); color:#fff; padding:20px 28px; display:flex; align-items:center; gap:18px; }
  .cc-header .inst-logo { width:56px; height:56px; object-fit:contain; background:#fff; border-radius:10px; padding:4px; }
  .cc-header .inst-logo-fallback { width:56px; height:56px; border-radius:10px; background:rgba(255,255,255,.12); display:grid; place-items:center; color:#fff; font-weight:700; font-size:22px; }
  .cc-header h1 { font-size:18px; margin:0 0 4px; font-weight:700; }
  .cc-header h2 { font-size:14px; margin:0; opacity:.85; font-weight:500; }
  .cc-body { display:grid; grid-template-columns:1fr 240px; gap:24px; padding:24px 28px; }
  @media (max-width:600px){ .cc-body { grid-template-columns:1fr; } }
  .cc-section-title { font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:#64748b; margin-bottom:6px; }
  .cc-row { display:flex; gap:8px; padding:6px 0; border-bottom:1px dashed #e2e8f0; }
  .cc-row:last-child { border-bottom:none; }
  .cc-row .lbl { width:140px; color:#64748b; font-size:13px; }
  .cc-row .val { flex:1; font-weight:600; font-size:14px; color:#0f172a; }
  .cc-photo-pane { text-align:center; }
  .cc-photo { width:160px; height:160px; object-fit:cover; border-radius:12px; border:3px solid #0b1f3a; }
  .cc-photo-fallback { width:160px; height:160px; border-radius:12px; background:#e2e8f0; display:grid; place-items:center; font-size:48px; font-weight:700; color:#475569; margin:0 auto; }
  .cc-num { margin-top:12px; }
  .cc-num .lbl { font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:#64748b; }
  .cc-num .val { font-size:36px; font-weight:800; color:#0b1f3a; line-height:1; letter-spacing:1px; }
  .cc-qr { margin-top:14px; padding-top:12px; border-top:1px dashed #e2e8f0; }
  .cc-qr img { display:block; margin:0 auto; }
  .cc-qr-cap { font-size:10px; letter-spacing:.06em; text-transform:uppercase; color:#94a3b8; margin-top:4px; }
  .cc-events { padding:0 28px 24px; }
  .cc-events table { width:100%; border-collapse:collapse; font-size:13px; }
  .cc-events th, .cc-events td { padding:8px 10px; border-bottom:1px solid #e2e8f0; text-align:left; }
  .cc-events th { background:#f8fafc; color:#475569; text-transform:uppercase; font-size:11px; letter-spacing:.05em; }
  .cc-events td.text-end, .cc-events th.text-end { text-align:right; }
  .cc-footer { background:#f8fafc; padding:14px 28px; font-size:11px; color:#64748b; display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; }
  @media print {
    body { background:#fff; padding:0; }
    .cc-actions { display:none; }
    .cc-card { box-shadow:none; border:1px solid #cbd5e1; }
  }
</style>
</head>
<body>

<div class="cc-actions">
  <a href="/athlete/my-registrations" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
  <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print / Save as PDF</button>
</div>

<div class="cc-card">
  <div class="cc-header">
    <?php if (!empty($institution['logo'])): ?>
      <img src="<?= e($institution['logo']) ?>" alt="" class="inst-logo">
    <?php else: ?>
      <div class="inst-logo-fallback"><?= strtoupper(substr($institution['name'] ?? 'I', 0, 1)) ?></div>
    <?php endif; ?>
    <div>
      <h1><?= e($institution['name'] ?? '') ?></h1>
      <h2><?= e($event['name']) ?> · <?= formatDate($event['event_date_from']) ?> – <?= formatDate($event['event_date_to']) ?></h2>
    </div>
  </div>

  <div class="cc-body">
    <div>
      <div class="cc-section-title">Competitor</div>
      <div class="cc-row"><div class="lbl">Name</div><div class="val"><?= e($athlete['name']) ?></div></div>
      <div class="cc-row"><div class="lbl">Gender / Age</div><div class="val">
        <?= ucfirst($athlete['gender'] ?? '') ?>
        <?php if (!empty($athlete['date_of_birth'])): ?> · <?= ageFromDob($athlete['date_of_birth']) ?> yrs<?php endif; ?>
      </div></div>
      <div class="cc-row"><div class="lbl">Mobile</div><div class="val"><?= e($athlete['mobile'] ?? '') ?></div></div>

      <div class="cc-section-title" style="margin-top:14px">Event</div>
      <div class="cc-row"><div class="lbl">Venue</div><div class="val"><?= e($event['location']) ?></div></div>
      <div class="cc-row"><div class="lbl">Registration ID</div><div class="val">#<?= (int)$registration['id'] ?></div></div>
      <div class="cc-row"><div class="lbl">Approved On</div><div class="val">
        <?= !empty($registration['admin_reviewed_at']) ? formatDate($registration['admin_reviewed_at'], 'd M Y') : '—' ?>
      </div></div>

      <div class="cc-section-title" style="margin-top:14px">Institution</div>
      <?php if (!empty($institution['address'])): ?>
        <div class="cc-row"><div class="lbl">Address</div><div class="val"><?= e($institution['address']) ?></div></div>
      <?php endif; ?>
      <?php if (!empty($institution['email'])): ?>
        <div class="cc-row"><div class="lbl">Email</div><div class="val"><?= e($institution['email']) ?></div></div>
      <?php endif; ?>
      <?php if (!empty($event['contact_mobile'])): ?>
        <div class="cc-row"><div class="lbl">Event SPOC</div><div class="val">
          <?= e($event['contact_name']) ?> · <?= e($event['contact_mobile']) ?>
        </div></div>
      <?php endif; ?>
    </div>

    <div class="cc-photo-pane">
      <?php if (!empty($photo)): ?>
        <img src="<?= e($photo) ?>" alt="" class="cc-photo">
      <?php else: ?>
        <div class="cc-photo-fallback"><?= strtoupper(substr($athlete['name'] ?? 'A', 0, 1)) ?></div>
      <?php endif; ?>
      <div class="cc-num">
        <div class="lbl">Competitor No.</div>
        <div class="val"><?= str_pad((string)(int)$registration['competitor_number'], 4, '0', STR_PAD_LEFT) ?></div>
      </div>
      <div class="cc-qr">
        <img src="<?= e($qrSrc) ?>" alt="QR" width="120" height="120"
             onerror="this.style.display='none';document.getElementById('cc-qr-fallback').style.display='block'">
        <div id="cc-qr-fallback" style="display:none;font-size:11px;color:#64748b;word-break:break-all;max-width:140px">
          <?= e($verifyUrl) ?>
        </div>
        <div class="cc-qr-cap">Scan to verify</div>
      </div>
    </div>
  </div>

  <div class="cc-events">
    <div class="cc-section-title">Registered Events</div>
    <?php if (empty($items)): ?>
      <p style="color:#64748b">No events registered.</p>
    <?php else: ?>
    <table>
      <thead>
        <tr><th style="width:36px">#</th><th>Sport</th><th>Event Code</th><th>Event</th><th class="text-end">Fee</th></tr>
      </thead>
      <tbody>
        <?php foreach ($items as $i => $it): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><?= e($it['sport_name'] ?? '') ?></td>
            <td><code><?= e($it['event_code'] ?? '') ?></code></td>
            <td><?= e($it['sport_event_name'] ?? $it['category'] ?? '') ?></td>
            <td class="text-end">₹<?= number_format((float)$it['fee'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <div class="cc-footer">
    <div>Issued by <strong><?= e($institution['name'] ?? '') ?></strong> · <?= e($event['name']) ?></div>
    <div>Powered by SportsMIS · <?= date('d M Y') ?></div>
  </div>
</div>

</body>
</html>
