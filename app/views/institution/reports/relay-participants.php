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
  /* Each relay starts on a fresh page; the table itself is allowed to
     overflow with the thead repeating on continuation pages. */
  .relay-block { margin-bottom: 18px; }
  .relay-block + .relay-block { page-break-before: always; }
  .relay-block h4, .relay-meta { page-break-after: avoid; }
  /* Repeat the thead on every print page when the table overflows.
     We put the relay heading + meta INSIDE the thead so that block
     repeats together with the column headers on continuation pages. */
  table.lane-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  table.lane-table thead { display: table-header-group; }
  table.lane-table tfoot { display: table-footer-group; }
  /* Uniform row height — every body row renders at the same height
     regardless of content, so the printed sheet looks like a grid.
     17mm gives room for the athlete-name + age/gender/category meta
     sub-line under it without clipping. */
  table.lane-table tbody tr { height: 17mm; page-break-inside: avoid; }
  table.lane-table th, table.lane-table td {
    border: 1px solid #555;
    padding: 3px 5px;
    font-size: 9.5pt;
    vertical-align: middle;
    word-wrap: break-word;
    overflow: hidden;
  }
  /* Name cell keeps the multi-line meta line visible even if other
     cells would otherwise clip. */
  table.lane-table td.athlete-name { overflow: visible; }
  table.lane-table thead th { background: #e9ecef; font-size: 9pt; text-align: center; }
  /* Relay heading + meta strip inside the thead. */
  td.relay-strip {
    background: #f5f7fa;
    border: 1px solid #d0d6dd;
    padding: 6px 8px;
    text-align: left;
  }
  td.relay-strip .relay-title {
    font-size: 12pt; font-weight: 700; margin-bottom: 4px;
  }
  td.relay-strip .relay-meta-grid {
    display: grid;
    grid-template-columns: 110px 110px 130px 1fr;
    gap: 4px 14px;
    font-size: 10pt;
  }
  td.relay-strip .lbl { color: #666; font-size: 9pt; }
  td.relay-strip .val { font-weight: 600; }
  /* Capitalise the athlete name on screen + print. */
  td.athlete-name { text-transform: uppercase; font-weight: 600; }
  td.athlete-name .athlete-meta {
    display: block;
    margin-top: 2px;
    font-size: 8pt;
    font-weight: 400;
    color: #555;
    text-transform: none;
    letter-spacing: 0;
  }
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
      <?php if (empty($r['lanes'])): ?>
        <h4 style="font-size:13pt;margin:0 0 4px 0">Relay <?= e($r['relay_number']) ?></h4>
        <p class="text-muted small">No lanes configured.</p>
      <?php else: ?>
        <table class="lane-table">
          <!-- Column widths (mm): tuned for A4 landscape. Events and
               Team are widened; Name of Athlete and the two Target
               columns are narrowed. -->
          <colgroup>
            <col style="width:12mm">  <!-- Lane -->
            <col style="width:14mm">  <!-- Photo -->
            <col style="width:16mm">  <!-- Comp No -->
            <col style="width:45mm">  <!-- Name of Athlete (incl. age | gender | category sub-line) -->
            <col style="width:16mm">  <!-- Unit -->
            <col style="width:16mm">  <!-- Cat -->
            <col style="width:32mm">  <!-- Events -->
            <col style="width:32mm">  <!-- Team -->
            <col style="width:16mm">  <!-- Target From -->
            <col style="width:16mm">  <!-- Target To -->
            <col style="width:32mm">  <!-- Signature -->
          </colgroup>
          <thead>
            <!-- Relay heading + meta strip — sits inside the thead so
                 it repeats together with the column headers on every
                 continuation page when the table overflows. -->
            <tr>
              <td class="relay-strip" colspan="11">
                <div class="relay-title">Relay <?= e($r['relay_number']) ?></div>
                <div class="relay-meta-grid">
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
              </td>
            </tr>
            <tr>
              <th>Lane</th>
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
              $catShort   = trim((string)($ln['category_abbr'] ?? ''))
                             ?: trim((string)($ln['category']      ?? ''));
              $photoUrl   = $ln['athlete_photo'] ?? '';
              $athleteInitial = $hasAthlete ? strtoupper(substr((string)$ln['athlete_name'], 0, 1)) : '';
            ?>
              <tr>
                <td class="text-center fw-bold"><?= e($ln['lane_number']) ?></td>
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
                        ? str_pad((string)(int)$ln['competitor_number'], 4, '0', STR_PAD_LEFT)
                        : '' ?>
                </td>
                <td class="athlete-name">
                  <?= e($ln['athlete_name']) ?>
                  <?php
                    $metaBits = [];
                    if (!empty($ln['athlete_age']))   $metaBits[] = (int)$ln['athlete_age'] . ' yrs';
                    if (!empty($ln['athlete_gender'])) $metaBits[] = ucfirst(strtolower((string)$ln['athlete_gender']));
                    if (!empty($ln['age_category_label'])) $metaBits[] = $ln['age_category_label'];
                  ?>
                  <?php if ($metaBits): ?>
                    <span class="athlete-meta"><?= e(implode(' | ', $metaBits)) ?></span>
                  <?php endif; ?>
                </td>
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
