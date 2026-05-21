<?php $pageTitle = 'Relay-wise Participant List — ' . $event['name']; ?>

<style>
  @page {
    size: A4 landscape;
    margin: 12mm 10mm 16mm 10mm;
    @bottom-right {
      content: "Page " counter(page) " of " counter(pages);
      font-size: 9pt;
      color: #666;
    }
  }
  .event-head {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 10px;
    border-bottom: 2px solid #333;
    padding-bottom: 8px;
  }
  .event-head .event-logo {
    width: 64px;
    height: 64px;
    object-fit: contain;
    flex-shrink: 0;
  }
  .event-head .event-head-text { flex: 1; min-width: 0; }
  .relay-block { page-break-inside: avoid; margin-bottom: 18px; }
  .relay-block + .relay-block { page-break-before: always; }
  .relay-meta {
    display: grid;
    grid-template-columns: 110px 110px 130px 1fr;
    gap: 4px 14px;
    font-size: 10pt;
    margin: 6px 0 8px;
    padding: 6px 8px;
    background: #f5f7fa;
    border: 1px solid #d0d6dd;
  }
  .relay-meta .lbl { color: #666; font-size: 9pt; }
  .relay-meta .val { font-weight: 600; }
  table.lane-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  table.lane-table th, table.lane-table td {
    border: 1px solid #555;
    padding: 3px 5px;
    font-size: 9.5pt;
    vertical-align: middle;
    word-wrap: break-word;
  }
  table.lane-table thead th { background: #e9ecef; font-size: 9pt; text-align: center; }
  .athlete-photo, .athlete-photo-fallback {
    width: 36px; height: 36px;
    object-fit: cover;
    border: 1px solid #b7bec5;
    border-radius: 3px;
    display: block;
    margin: 0 auto;
  }
  .athlete-photo-fallback {
    background: #e9ecef;
    color: #6c757d;
    text-align: center;
    line-height: 36px;
    font-weight: 600;
    font-size: 9.5pt;
  }
  .status-pill {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 8px;
    font-size: 8.5pt;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .02em;
  }
  .status-not_use      { background: #f1f3f5; color: #6c757d; }
  .status-reserved     { background: #e7f5ff; color: #1971c2; }
  .status-unit_assigned{ background: #fff3cd; color: #8a6d3b; }
  .status-allotted     { background: #d4edda; color: #1f6e3b; }
  .empty-lane td       { color: #888; font-style: italic; }
  .empty-lane td.lane-num,
  .empty-lane td.status-col { font-style: normal; color: #333; }
  @media screen {
    body { padding: 18px; max-width: 297mm; margin: 0 auto; box-shadow: 0 0 12px rgba(0,0,0,.1); background:#fff; }
  }
</style>

<?php
  $statusLabel = [
    'allotted'      => 'Allotted',
    'unit_assigned' => 'Unit Asgn',
    'reserved'      => 'Reserved',
    'not_use'       => 'Not in Use',
  ];
?>

<header class="event-head no-break">
  <?php if (!empty($event['logo'])): ?>
    <img src="<?= e($event['logo']) ?>" alt="" class="event-logo">
  <?php endif; ?>
  <div class="event-head-text">
    <h2 class="fw-bold mb-1" style="font-size:16pt;margin:0"><?= e($event['name']) ?></h2>
    <div class="small text-muted">
      <?= e($event['institution_name'] ?? '') ?>
      <?php if (!empty($event['location'])): ?> · <?= e($event['location']) ?><?php endif; ?>
      <?php if (!empty($event['event_date_from'])): ?>
        · <?= formatDate($event['event_date_from']) ?>
        <?php if (!empty($event['event_date_to']) && $event['event_date_to'] !== $event['event_date_from']): ?>
          – <?= formatDate($event['event_date_to']) ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <h3 class="mt-1 mb-0" style="font-size:12pt">Relay-wise Participant List</h3>
  </div>
</header>

<?php if (empty($relays)): ?>
  <p class="text-center text-muted mt-3">No relays configured for this event.</p>
<?php else: ?>
  <?php foreach ($relays as $r): ?>
    <section class="relay-block">
      <h4 style="font-size:13pt;margin:0 0 4px 0">
        Relay <?= e($r['relay_number']) ?>
      </h4>
      <div class="relay-meta">
        <div><div class="lbl">Date</div><div class="val"><?= e(formatDate($r['relay_date'])) ?: '—' ?></div></div>
        <div><div class="lbl">Match Time</div><div class="val"><?= e($r['match_time']) ?: '—' ?></div></div>
        <div><div class="lbl">Reporting Time</div><div class="val"><?= e($r['reporting_time']) ?: '—' ?></div></div>
        <div>
          <div class="lbl">Venue</div>
          <div class="val">
            <?= e($r['venue_name']) ?>
            <?php if (!empty($r['venue_location'])): ?> — <?= e($r['venue_location']) ?><?php endif; ?>
          </div>
        </div>
      </div>

      <?php if (empty($r['lanes'])): ?>
        <p class="text-muted small">No lanes configured.</p>
      <?php else: ?>
        <table class="lane-table">
          <colgroup>
            <col style="width:42px">  <!-- Lane -->
            <col style="width:70px">  <!-- Status -->
            <col style="width:50px">  <!-- Photo -->
            <col style="width:62px">  <!-- Comp No -->
            <col>                     <!-- Name of Athlete -->
            <col>                     <!-- Unit -->
            <col style="width:60px">  <!-- Category -->
            <col>                     <!-- Events -->
            <col style="width:70px">  <!-- Team Entries -->
            <col style="width:54px">  <!-- Target From -->
            <col style="width:54px">  <!-- Target To -->
            <col style="width:88px">  <!-- Signature -->
          </colgroup>
          <thead>
            <tr>
              <th>Lane</th>
              <th>Status</th>
              <th>Photo</th>
              <th>Comp. No.</th>
              <th>Name of Athlete</th>
              <th>Unit</th>
              <th>Cat.</th>
              <th>Events</th>
              <th>Team</th>
              <th>Target From</th>
              <th>Target To</th>
              <th>Signature</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($r['lanes'] as $ln):
              $hasAthlete = !empty($ln['athlete_name']);
              $st         = $ln['lane_status'] ?? 'not_use';
              $stLabel    = $statusLabel[$st] ?? '—';
              $catShort   = trim((string)($ln['category_abbr'] ?? ''))
                             ?: trim((string)($ln['category']      ?? ''));
              $isBlank    = ($st === 'not_use');
              $photoUrl   = $ln['athlete_photo'] ?? '';
              $athleteInitial = $hasAthlete ? strtoupper(substr((string)$ln['athlete_name'], 0, 1)) : '';
            ?>
              <tr class="<?= !$hasAthlete ? 'empty-lane' : '' ?>">
                <td class="lane-num text-center fw-bold">Lane <?= e($ln['lane_number']) ?></td>
                <td class="status-col text-center">
                  <span class="status-pill status-<?= e($st) ?>"><?= e($stLabel) ?></span>
                </td>
                <td class="text-center">
                  <?php if ($photoUrl !== ''): ?>
                    <img src="<?= e($photoUrl) ?>" class="athlete-photo" alt="">
                  <?php elseif ($hasAthlete): ?>
                    <div class="athlete-photo-fallback"><?= e($athleteInitial) ?></div>
                  <?php else: ?>
                    <div class="athlete-photo-fallback">&nbsp;</div>
                  <?php endif; ?>
                </td>
                <td class="text-center fw-bold">
                  <?= $ln['competitor_number']
                        ? '#' . str_pad((string)(int)$ln['competitor_number'], 4, '0', STR_PAD_LEFT)
                        : '' ?>
                </td>
                <td><?= e($ln['athlete_name']) ?></td>
                <td><?= e($ln['unit_name']) ?></td>
                <td class="text-center"><?= e($catShort) ?></td>
                <td><?= !empty($ln['event_codes']) ? e(implode(', ', $ln['event_codes'])) : '' ?></td>
                <td><?= !empty($ln['team_codes']) ? e(implode(', ', $ln['team_codes'])) : '' ?></td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
<?php endif; ?>
