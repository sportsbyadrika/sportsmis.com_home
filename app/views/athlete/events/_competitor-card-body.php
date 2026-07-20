<?php
/**
 * Competitor Card body — the printable `.cc-card` block. Shared by the
 * single-card view (athlete/events/competitor-card.php) and the multi-card
 * print sheet (institution/reports/competitor-cards-print.php).
 *
 * Expects in scope: $athlete, $event, $institution, $registration,
 *                   $category_rows, $age_category_label
 * Everything else (photo, QR, competitor label) is derived here so each
 * card in a loop computes its own values.
 */
$photo        = $athlete['passport_photo'] ?? '';
$compNoPadded = str_pad((string)(int)$registration['competitor_number'], 4, '0', STR_PAD_LEFT);
$qrMode       = (string)($event['competitor_card_qr_mode'] ?? 'competitor_no');
$qrCustomUrl  = trim((string)($event['competitor_card_qr_url'] ?? ''));
$qrData       = ($qrMode === 'url' && $qrCustomUrl !== '') ? $qrCustomUrl : $compNoPadded;
$qrSrc        = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&margin=4&data=' . rawurlencode($qrData);
$qrFallbackLabel = $qrData;
$qrCaption       = trim((string)($event['competitor_card_qr_label'] ?? '')) ?: 'Scan to verify';
$compLabel       = \Models\Event::competitorLabel($event);   // e.g. "Chest Number"
$qrFallbackId    = 'cc-qr-fallback-' . (int)$registration['id'];
?>
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
        <div class="lbl"><?= e($compLabel) ?></div>
        <div class="val"><?= $compNoPadded ?></div>
      </div>
      <div class="cc-qr">
        <img src="<?= e($qrSrc) ?>" alt="QR" width="120" height="120"
             onerror="this.style.display='none';var f=document.getElementById('<?= e($qrFallbackId) ?>');if(f)f.style.display='block'">
        <div id="<?= e($qrFallbackId) ?>" style="display:none;font-size:11px;color:#64748b;word-break:break-all;max-width:140px">
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
