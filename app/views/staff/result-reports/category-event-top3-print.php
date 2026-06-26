<?php
$pageTitle  = 'Category Top 3 — ' . $event['name'];
// Delegate to the global helper so the label honours this event's
// gender_label_set switch.
$genderLbl  = fn(string $g): string => genderLabel($g, $event);
$fmtPadNo = fn($n): string => $n ? '#' . str_pad((string)(int)$n, 4, '0', STR_PAD_LEFT) : '—';
?>

<style>
  @page { size: A4 portrait; margin: 14mm 12mm 18mm 12mm;
          @bottom-right { content: "Page " counter(page) " of " counter(pages);
                          font-size: 9pt; color:#666; } }
  html, body { background:#fff !important; color:#111; }
  .et3-hero { display:flex; align-items:center; gap:14px; border-bottom:2px solid #333;
              padding-bottom:8px; margin-bottom:12px; page-break-after:avoid; }
  .et3-hero img.evt-logo { width:54px; height:54px; object-fit:contain; flex-shrink:0; }
  .et3-hero h1 { font-size:14pt; margin:0; }
  .et3-hero .meta { font-size:10pt; color:#444; margin-top:2px; }
  .et3-event { page-break-after: always; page-break-inside: avoid; }
  .et3-event:last-child { page-break-after: auto; }
  .et3-event-head { border-bottom:1.5px solid #333; padding-bottom:6px; margin-bottom:8px;
                    page-break-after:avoid; }
  .et3-event-head h2 { font-size:13pt; margin:0 0 2px; }
  .et3-event-head .meta { font-size:10pt; color:#555; }
  .et3-event-head code { background:#f3f4f6; padding:1px 6px; border-radius:4px;
                         font-size:10pt; margin-right:6px; }
  table.top3-table { width:100%; border-collapse:collapse; font-size:10.5pt; }
  table.top3-table th, table.top3-table td {
    border:1px solid #555; padding:6px 8px; vertical-align:middle;
  }
  table.top3-table thead th { background:#f1f3f5 !important; font-weight:700;
                               text-align:center; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  table.top3-table td.cnum, table.top3-table td.rank, table.top3-table td.score,
  table.top3-table td.tens, table.top3-table td.medal { text-align:center; }
  table.top3-table td.score { font-weight:700; text-align:right; }
  table.top3-table td.rank { font-weight:700; }
  /* Medal row tints — Gold / Silver / Bronze. */
  tr.medal-gold   td { background:#fff4c8 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  tr.medal-silver td { background:#ececec !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  tr.medal-bronze td { background:#f2dcc0 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  .medal-pill { display:inline-block; padding:1px 8px; border-radius:10px; font-weight:700;
                font-size:9.5pt; letter-spacing:.02em; border:1px solid transparent; }
  .medal-pill.gold   { background:#fde68a; color:#7c4a00; border-color:#facc15; }
  .medal-pill.silver { background:#e5e7eb; color:#4b5563; border-color:#cbd5e1; }
  .medal-pill.bronze { background:#fed7aa; color:#7c2d12; border-color:#fb923c; }
  .empty-note { color:#666; font-style:italic; margin:0; }
  .auth-sig { margin-top: 14mm; padding-top:6px; border-top:1px solid #555;
              text-align:right; font-size:9.5pt; color:#333; }
</style>

<!-- One hero header per printed event, so each sheet is self-identifying. -->
<?php if (empty($sport_events)): ?>
  <p class="empty-note">No sport-events with scored athletes were found for this category.</p>
<?php else: foreach ($sport_events as $ev): ?>
  <section class="et3-event">

    <header class="et3-hero">
      <?php if (!empty($event['logo'])): ?>
        <img src="<?= e($event['logo']) ?>" alt="" class="evt-logo">
      <?php endif; ?>
      <div>
        <h1><?= e($event['name']) ?></h1>
        <div class="meta">
          Category &mdash; Event Top 3 &middot;
          <strong><?= e($category_name) ?></strong>
          <?php if (!empty($event['event_date_from'])): ?>
            &middot; <?= e(formatDate($event['event_date_from'])) ?>
          <?php endif; ?>
        </div>
      </div>
    </header>

    <div class="et3-event-head">
      <h2>
        <?php if (!empty($ev['event_code'])): ?><code><?= e($ev['event_code']) ?></code><?php endif; ?>
        <?= e($ev['sport_event'] ?: '—') ?>
      </h2>
      <div class="meta">
        <?php if (!empty($ev['age_category'])): ?>Age Category: <strong><?= e($ev['age_category']) ?></strong><?php endif; ?>
        <?php if (!empty($ev['gender'])): ?>
          <?= !empty($ev['age_category']) ? ' &middot; ' : '' ?>
          Gender: <strong><?= e($genderLbl($ev['gender'])) ?></strong>
        <?php endif; ?>
      </div>
    </div>

    <?php if (empty($ev['top3'])): ?>
      <p class="empty-note">No scored athletes recorded yet for this sport-event.</p>
    <?php else: ?>
      <table class="top3-table">
        <thead>
          <tr>
            <th style="width:14mm">Rank</th>
            <th style="width:24mm">Comp. No.</th>
            <th>Name of Athlete</th>
            <th>Unit / Club</th>
            <th style="width:20mm">Score</th>
            <th style="width:18mm">No. of 10x</th>
            <th style="width:22mm">Medal</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ev['top3'] as $rk => $a):
            $rank   = $rk + 1;
            $tint   = $rank === 1 ? 'medal-gold' : ($rank === 2 ? 'medal-silver' : ($rank === 3 ? 'medal-bronze' : ''));
            $pill   = $rank === 1 ? 'gold'       : ($rank === 2 ? 'silver'       : ($rank === 3 ? 'bronze'       : ''));
            $medal  = $rank === 1 ? 'Gold'       : ($rank === 2 ? 'Silver'       : ($rank === 3 ? 'Bronze'       : ''));
          ?>
            <tr class="<?= $tint ?>">
              <td class="rank"><?= $rank ?></td>
              <td class="cnum"><?= e($fmtPadNo($a['competitor_number'] ?? 0)) ?></td>
              <td><?= e($a['athlete_name']) ?></td>
              <td>
                <?= e($a['unit_name'] ?: '—') ?>
                <?php if (!empty($a['unit_address'])): ?>
                  <div style="color:#555;font-size:9.5pt"><?= e($a['unit_address']) ?></div>
                <?php endif; ?>
              </td>
              <td class="score"><?= (int)round((float)$a['grand_total']) ?></td>
              <td class="tens"><?= (int)$a['tens_count'] ?></td>
              <td class="medal"><span class="medal-pill <?= $pill ?>"><?= $medal ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <div class="auth-sig">Authorised Signature</div>
  </section>
<?php endforeach; endif; ?>

<script>
  /* Auto-fire the browser print dialog once the page settles. The
     "print" layout already hides Print/Close buttons via @media print
     and provides a small action bar on screen. */
  window.addEventListener('load', () => { setTimeout(() => window.print(), 200); });
</script>
