<?php
$pageTitle = 'Competitor Card #' . (int)$registration['competitor_number'];
$photo = $athlete['passport_photo'] ?? '';
$cfg   = require CONFIG_ROOT . '/app.php';
$verifyUrl = rtrim($cfg['url'], '/') . '/athlete/registrations/' . hid_reg((int)$registration['id']) . '/card';
// QR content per the event's Card Settings — default encodes the
// padded 4-digit competitor number; the 'url' mode encodes whatever
// URL the admin configured (e.g. a venue map link).
$compNoPadded = str_pad((string)(int)$registration['competitor_number'], 4, '0', STR_PAD_LEFT);
$qrMode       = (string)($event['competitor_card_qr_mode'] ?? 'competitor_no');
$qrCustomUrl  = trim((string)($event['competitor_card_qr_url'] ?? ''));
$qrData       = ($qrMode === 'url' && $qrCustomUrl !== '') ? $qrCustomUrl : $compNoPadded;
$qrSrc        = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&margin=4&data=' . rawurlencode($qrData);
$qrFallbackLabel = $qrData;
$qrCaption       = trim((string)($event['competitor_card_qr_label'] ?? '')) ?: 'Scan to verify';
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
  .cc-actions { max-width:840px; margin:0 auto 16px; padding:0 12px; display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap; }
  .cc-card { max-width:840px; margin:0 12px; background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 8px 24px rgba(2,8,23,.08); border:1px solid #e2e8f0; }
  @media (min-width:864px){ .cc-card { margin:0 auto; } }
  .cc-header { background:linear-gradient(135deg,#0b1f3a,#1f3b7a); color:#fff; padding:20px 28px; display:flex; align-items:center; gap:18px; flex-wrap:wrap; }
  .cc-header .inst-logo { width:56px; height:56px; object-fit:contain; background:#fff; border-radius:10px; padding:4px; flex-shrink:0; }
  .cc-header .inst-logo-fallback { width:56px; height:56px; border-radius:10px; background:rgba(255,255,255,.12); display:grid; place-items:center; color:#fff; font-weight:700; font-size:22px; flex-shrink:0; }
  .cc-header h1 { font-size:18px; margin:0 0 4px; font-weight:700; word-wrap:break-word; }
  .cc-header h2 { font-size:14px; margin:0; opacity:.85; font-weight:500; word-wrap:break-word; }
  .cc-body { display:grid; grid-template-columns:1fr 240px; gap:24px; padding:24px 28px; }
  .cc-section-title { font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:#64748b; margin-bottom:6px; }
  .cc-row { display:flex; gap:8px; padding:6px 0; border-bottom:1px dashed #e2e8f0; }
  .cc-row:last-child { border-bottom:none; }
  .cc-row .lbl { width:140px; color:#64748b; font-size:13px; flex-shrink:0; }
  .cc-row .val { flex:1; font-weight:600; font-size:14px; color:#0f172a; word-wrap:break-word; min-width:0; }
  .cc-photo-pane { text-align:center; }
  .cc-photo { width:160px; height:160px; object-fit:cover; border-radius:12px; border:3px solid #0b1f3a; max-width:100%; }
  .cc-photo-fallback { width:160px; height:160px; border-radius:12px; background:#e2e8f0; display:grid; place-items:center; font-size:48px; font-weight:700; color:#475569; margin:0 auto; }
  .cc-num { margin-top:12px; }
  .cc-num .lbl { font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:#64748b; }
  .cc-num .val { font-size:36px; font-weight:800; color:#0b1f3a; line-height:1; letter-spacing:1px; }
  .cc-qr { margin-top:14px; padding-top:12px; border-top:1px dashed #e2e8f0; }
  .cc-qr img { display:block; margin:0 auto; max-width:100%; height:auto; }
  .cc-qr-cap { font-size:10px; letter-spacing:.06em; text-transform:uppercase; color:#94a3b8; margin-top:4px; }
  .cc-events { padding:0 28px 24px; }
  .cc-events-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
  .cc-events table { width:100%; border-collapse:collapse; font-size:13px; }
  .cc-events th, .cc-events td { padding:8px 10px; border-bottom:1px solid #e2e8f0; text-align:left; vertical-align:top; }
  .cc-events th { background:#f8fafc; color:#475569; text-transform:uppercase; font-size:11px; letter-spacing:.05em; }
  .cc-events td.text-end, .cc-events th.text-end { text-align:right; }
  .cc-cell-label { display:none; font-size:10px; letter-spacing:.05em; text-transform:uppercase; color:#94a3b8; margin-bottom:2px; }
  .cc-message { margin:14px 28px 0; padding:8px 14px 12px; background:#fff7ed; border:1px solid #fed7aa; border-radius:8px; color:#7c2d12; font-size:12.5px; line-height:1.45; white-space:pre-line; font-weight:700; }
  .cc-message-title { font-size:10.5px; letter-spacing:.06em; text-transform:uppercase; color:#9a3412; margin-bottom:1px; font-weight:700; line-height:1.2; }
  .cc-footer { background:#f8fafc; padding:14px 28px; font-size:11px; color:#64748b; display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; }

  /* ── Tablet ──────────────────────────────────────────────── */
  @media (max-width:720px){
    .cc-events { padding:0 16px 16px; }
    .cc-body   { padding:16px;       }
  }

  /* ── Mobile: stack the photo pane under details, convert
       the events table into a card-stack layout ─────────── */
  @media (max-width:600px){
    body { padding:12px 0; }
    .cc-header { padding:16px 18px; gap:12px; }
    .cc-header h1 { font-size:16px; }
    .cc-header h2 { font-size:12px; }
    .cc-body { grid-template-columns:1fr; padding:16px 18px; gap:18px; }
    .cc-row .lbl { width:110px; font-size:12px; }
    .cc-row .val { font-size:13px; }
    .cc-num .val { font-size:30px; }
    .cc-events { padding:0 14px 14px; }
    .cc-events thead { display:none; }
    .cc-events tbody, .cc-events tr, .cc-events td { display:block; width:100%; }
    .cc-events tr { border:1px solid #e2e8f0; border-radius:10px; padding:8px 10px; margin-bottom:10px; }
    .cc-events td { border:none; padding:6px 0; }
    .cc-events td.text-end { text-align:left; }
    .cc-cell-label { display:block; }
    .cc-footer { padding:12px 16px; flex-direction:column; gap:4px; }
  }

  @media print {
    body { background:#fff; padding:0; }
    .cc-actions { display:none; }
    .cc-card { box-shadow:none; border:1px solid #cbd5e1; margin:0; }
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
    <?php if (!empty($event['logo'])): ?>
      <img src="<?= e($event['logo']) ?>" alt="" class="inst-logo">
    <?php else: ?>
      <div class="inst-logo-fallback"><?= strtoupper(substr($event['name'] ?? 'E', 0, 1)) ?></div>
    <?php endif; ?>
    <div>
      <h1><?= e($event['name']) ?></h1>
      <h2><?= e($institution['name'] ?? '') ?> · <?= formatDate($event['event_date_from']) ?> – <?= formatDate($event['event_date_to']) ?></h2>
    </div>
  </div>

  <div class="cc-body">
    <div>
      <div class="cc-section-title">Competitor</div>
      <div class="cc-row"><div class="lbl">Name</div><div class="val"><?= e($athlete['name']) ?></div></div>
      <div class="cc-row"><div class="lbl">Gender / Age / Category</div><div class="val">
        <?= e(genderLabel((string)($athlete['gender'] ?? ''), $event)) ?>
        <?php if (!empty($athlete['date_of_birth'])): ?> / <?= (int)ageFromDob($athlete['date_of_birth']) ?> yrs<?php endif; ?>
        <?php if (!empty($age_category_label)): ?> / <?= e($age_category_label) ?><?php endif; ?>
      </div></div>
      <div class="cc-row"><div class="lbl">Mobile</div><div class="val"><?= e($athlete['mobile'] ?? '') ?></div></div>
      <?php $unitLabel = $registration['unit_name'] ?? ($registration['unit_name_other'] ?? ''); ?>
      <?php if ($unitLabel !== ''): ?>
        <div class="cc-row"><div class="lbl">Unit</div><div class="val">
          <?= e($unitLabel) ?>
          <?php if (!empty($registration['unit_address'])): ?>
            <div class="text-muted" style="font-weight:400;font-size:12px;margin-top:2px">
              <?= e($registration['unit_address']) ?>
            </div>
          <?php endif; ?>
        </div></div>
      <?php endif; ?>

      <div class="cc-section-title" style="margin-top:14px">Event</div>
      <div class="cc-row"><div class="lbl">Venue</div><div class="val"><?= e($event['location']) ?></div></div>
      <div class="cc-row"><div class="lbl">Approved On</div><div class="val">
        <?= !empty($registration['admin_reviewed_at']) ? formatDate($registration['admin_reviewed_at'], 'd M Y') : '—' ?>
      </div></div>

      <div class="cc-section-title" style="margin-top:14px">Institution</div>
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
          <?= e($qrFallbackLabel) ?>
        </div>
        <div class="cc-qr-cap"><?= e($qrCaption) ?></div>
      </div>
    </div>
  </div>

  <div class="cc-events">
    <div class="cc-section-title">Registered Events</div>
    <?php if (empty($category_rows ?? [])): ?>
      <p style="color:#64748b">No events registered.</p>
    <?php else: ?>
    <div class="cc-events-scroll">
    <table>
      <thead>
        <tr>
          <th style="width:36px">#</th>
          <th>Event Category</th>
          <th>Events</th>
          <th>Team Entries</th>
          <th>Relay &amp; Lane</th>
          <th class="text-end">Fee</th>
        </tr>
      </thead>
      <tbody>
        <?php $i = 0; foreach ($category_rows as $catName => $row): $i++; ?>
          <tr>
            <td><span class="cc-cell-label">#</span><?= $i ?></td>
            <td>
              <span class="cc-cell-label">Event Category</span>
              <?= e($catName) ?>
            </td>
            <td>
              <span class="cc-cell-label">Events</span>
              <?php if (!empty($row['events'])): ?>
                <?php foreach ($row['events'] as $code): ?>
                  <code style="display:inline-block;margin:1px 2px"><?= e($code) ?></code>
                <?php endforeach; ?>
              <?php else: ?>
                <span style="color:#94a3b8">—</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="cc-cell-label">Team Entries</span>
              <?php if (!empty($row['team_events'])): ?>
                <?php foreach ($row['team_events'] as $code): ?>
                  <code style="display:inline-block;margin:1px 2px;background:#fff3cd"><?= e($code) ?></code>
                <?php endforeach; ?>
              <?php else: ?>
                <span style="color:#94a3b8">—</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="cc-cell-label">Relay &amp; Lane</span>
              <?php if (!empty($row['relays'])): ?>
                <?php foreach ($row['relays'] as $rl): ?>
                  <div style="line-height:1.3;margin-bottom:4px">
                    <strong>Relay <?= e($rl['relay_number']) ?></strong>
                    <?php if (!empty($rl['relay_date'])): ?> · <?= e(formatDate($rl['relay_date'])) ?><?php endif; ?>
                    <?php if (!empty($rl['match_time'])): ?> · <?= e(substr((string)$rl['match_time'], 0, 5)) ?><?php endif; ?>
                    · Lane <?= e($rl['lane_number']) ?>
                    <?php if (!empty($rl['range_name']) || !empty($rl['range_address'])): ?>
                      <div style="color:#475569;font-weight:400;font-size:11px">
                        <?php if (!empty($rl['range_name'])): ?>
                          <i class="bi bi-geo-alt"></i> <?= e($rl['range_name']) ?>
                        <?php endif; ?>
                        <?php if (!empty($rl['range_address'])): ?>
                          <span style="color:#64748b"> — <?= e($rl['range_address']) ?></span>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <span style="color:#94a3b8">— not yet —</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <span class="cc-cell-label">Fee</span>
              ₹<?= number_format((float)$row['fee'], 2) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </div>

  <?php if (!empty($event['competitor_card_message'])): ?>
    <div class="cc-message">
      <div class="cc-message-title"><i class="bi bi-info-circle me-1"></i>Important Note</div>
      <?= e($event['competitor_card_message']) ?>
    </div>
  <?php endif; ?>

  <div class="cc-footer">
    <div>Issued by <strong><?= e($institution['name'] ?? '') ?></strong> · <?= e($event['name']) ?></div>
    <div>Powered by SportsMIS · <?= date('d M Y') ?></div>
  </div>
</div>

</body>
</html>
