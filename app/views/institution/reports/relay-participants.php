<?php $pageTitle = 'Relay-wise Participant List — ' . $event['name']; ?>

<style>
  @page {
    size: A4 landscape;
    margin: 14mm 12mm 18mm 12mm;
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
    grid-template-columns: repeat(4, 1fr);
    gap: 4px 14px;
    font-size: 10pt;
    margin: 6px 0 8px;
    padding: 6px 8px;
    background: #f5f7fa;
    border: 1px solid #d0d6dd;
  }
  .relay-meta .lbl { color: #666; font-size: 9pt; }
  .relay-meta .val { font-weight: 600; }
  table.lane-table th, table.lane-table td {
    border: 1px solid #555;
    padding: 4px 6px;
    font-size: 10pt;
    vertical-align: middle;
  }
  table.lane-table thead th { background: #e9ecef; }
  .empty-lane td { color: #888; font-style: italic; }
  @media screen {
    body { padding: 18px; max-width: 297mm; margin: 0 auto; box-shadow: 0 0 12px rgba(0,0,0,.1); background:#fff; }
  }
</style>

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
        <div><div class="lbl">Range / Distance</div>
          <div class="val">
            <?= e($r['range_name']) ?>
            <?php if (!empty($r['distance_meters'])): ?> · <?= (int)$r['distance_meters'] ?>m<?php endif; ?>
          </div>
        </div>
        <div style="grid-column: span 4">
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
        <table class="lane-table" style="width:100%;border-collapse:collapse">
          <thead>
            <tr>
              <th style="width:60px">Lane</th>
              <th style="width:90px">Lane Type</th>
              <th>Unit</th>
              <th>Event Category</th>
              <th style="width:100px">Comp. No.</th>
              <th>Competitor</th>
              <th>Registered Events</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($r['lanes'] as $ln):
              $hasAthlete = !empty($ln['athlete_name']);
              $hasUnit    = !empty($ln['unit_name']);
            ?>
              <tr class="<?= !$hasAthlete ? 'empty-lane' : '' ?>">
                <td class="text-center fw-bold">Lane <?= e($ln['lane_number']) ?></td>
                <td><?= e(ucfirst((string)($ln['lane_type'] ?? ''))) ?></td>
                <td>
                  <?php if ($hasUnit): ?>
                    <div class="fw-medium"><?= e($ln['unit_name']) ?></div>
                    <?php if (!empty($ln['unit_address'])): ?>
                      <div class="small text-muted"><?= e($ln['unit_address']) ?></div>
                    <?php endif; ?>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td><?= e($ln['category']) ?: '—' ?></td>
                <td class="text-center fw-bold">
                  <?= $ln['competitor_number']
                        ? '#' . str_pad((string)(int)$ln['competitor_number'], 4, '0', STR_PAD_LEFT)
                        : '—' ?>
                </td>
                <td><?= e($ln['athlete_name']) ?: '—' ?></td>
                <td><?= !empty($ln['events']) ? e(implode(', ', $ln['events'])) : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
<?php endif; ?>
