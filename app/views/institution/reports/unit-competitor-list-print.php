<?php $pageTitle = 'Unit-wise Competitor List — ' . $event['name']; ?>

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
  html, body { background: #fff !important; color: #111; }
  .unit-block { margin-bottom: 14px; }
  /* Every unit after the first starts on a fresh landscape sheet. */
  .unit-block + .unit-block { page-break-before: always; }
  .unit-head {
    display: flex; align-items: center; gap: 14px;
    border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 10px;
    page-break-after: avoid;
  }
  .unit-head .unit-logo {
    width: 56px; height: 56px; object-fit: contain; flex-shrink: 0;
    border: 1px solid #ccc; background: #fff;
  }
  .unit-head-text h2 { font-size: 14pt; margin: 0; }
  .unit-head-text .unit-address { font-size: 9.5pt; color: #555; }
  .event-bar {
    font-size: 11pt; margin-bottom: 8px; color: #333;
    display: flex; align-items: center; gap: 10px;
  }
  .event-bar .event-logo { width: 36px; height: 36px; object-fit: contain; }
  table.uc-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 9.5pt; }
  table.uc-table thead { display: table-header-group; }
  table.uc-table th, table.uc-table td {
    border: 1px solid #444; padding: 4px 6px; vertical-align: top;
    word-wrap: break-word; overflow-wrap: anywhere;
  }
  table.uc-table thead th { background: #f1f3f5 !important; font-weight: 600; text-align: center; }
  table.uc-table tr { page-break-inside: avoid; }
  .photo-thumb { width: 32px; height: 32px; object-fit: cover; border: 1px solid #ccc; }
  .photo-fallback {
    width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;
    border: 1px solid #ccc; background: #f6f7f8; color: #999; font-size: 10pt;
  }
  /* Numbered, line-broken events list — 1pt smaller than the table
     base font so a packed row stays one sheet. */
  td.events-cell { font-size: 8.5pt; line-height: 1.35; }
  td.events-cell ol { margin: 0; padding-left: 18px; }
  td.events-cell ol li { margin: 0; padding: 0; }
  /* Column widths tuned for A4 landscape (~277mm usable). */
  col.c-sl    { width: 5%; }
  col.c-photo { width: 5%; }
  col.c-comp  { width: 7%; }
  col.c-name  { width: 16%; }
  col.c-age   { width: 5%; }
  col.c-gen   { width: 6%; }
  col.c-cat   { width: 14%; }
  col.c-evt   { width: 22%; }
  col.c-team  { width: 12%; }
  col.c-relay { width: 8%; }
</style>

<div class="event-bar">
  <?php if (!empty($event['logo'])): ?>
    <img src="<?= e($event['logo']) ?>" alt="" class="event-logo">
  <?php endif; ?>
  <div>
    <strong><?= e($event['name']) ?></strong> &middot;
    Unit-wise Competitor List
    <?php if (!empty($event['event_date_from'])): ?>
      &middot; <span class="text-muted"><?= e(formatDate($event['event_date_from'])) ?></span>
    <?php endif; ?>
  </div>
</div>

<?php if (empty($units)): ?>
  <p class="text-muted">No approved competitors yet.</p>
<?php else: ?>
  <?php foreach ($units as $u): ?>
    <div class="unit-block">
      <div class="unit-head">
        <?php if (!empty($u['unit_logo'])): ?>
          <img src="<?= e($u['unit_logo']) ?>" alt="" class="unit-logo">
        <?php else: ?>
          <div class="unit-logo d-inline-flex align-items-center justify-content-center text-muted">
            <i class="bi bi-building"></i>
          </div>
        <?php endif; ?>
        <div class="unit-head-text">
          <h2><?= e($u['unit_name']) ?></h2>
          <?php if (!empty($u['unit_address'])): ?>
            <div class="unit-address"><?= e($u['unit_address']) ?></div>
          <?php endif; ?>
          <div class="small text-muted">
            <?= count($u['rows']) ?> row<?= count($u['rows']) === 1 ? '' : 's' ?>
          </div>
        </div>
      </div>

      <table class="uc-table">
        <colgroup>
          <col class="c-sl"><col class="c-photo"><col class="c-comp"><col class="c-name">
          <col class="c-age"><col class="c-gen"><col class="c-cat"><col class="c-evt">
          <col class="c-team"><col class="c-relay">
        </colgroup>
        <thead>
          <tr>
            <th>Sl.</th><th>Photo</th><th>Comp. No.</th><th>Athlete Name</th>
            <th>Age</th><th>Gender</th><th>Event Category</th><th>Events</th>
            <th>Team Events</th><th>Relay &amp; Lane</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($u['rows'] as $i => $r): ?>
            <tr>
              <td class="text-center"><?= $i + 1 ?></td>
              <td class="text-center">
                <?php if (!empty($r['photo'])): ?>
                  <img src="<?= e($r['photo']) ?>" alt="" class="photo-thumb">
                <?php else: ?>
                  <span class="photo-fallback"><i class="bi bi-person"></i></span>
                <?php endif; ?>
              </td>
              <td class="text-center fw-bold">
                <?= $r['competitor_number']
                      ? '#' . str_pad((string)(int)$r['competitor_number'], 4, '0', STR_PAD_LEFT)
                      : '—' ?>
              </td>
              <td><?= e($r['athlete_name']) ?></td>
              <td class="text-center"><?= e($r['age']) ?></td>
              <td class="text-center"><?= e($r['gender']) ?></td>
              <td><?= e($r['category_name']) ?></td>
              <td class="events-cell">
                <?php if (empty($r['events'])): ?>
                  —
                <?php else: ?>
                  <ol>
                    <?php foreach ($r['events'] as $ev): ?>
                      <li><?= e($ev) ?></li>
                    <?php endforeach; ?>
                  </ol>
                <?php endif; ?>
              </td>
              <td><?= !empty($r['team_events']) ? e(implode(', ', $r['team_events'])) : '—' ?></td>
              <td>
                <?php if (empty($r['relays'])): ?>
                  —
                <?php else: foreach ($r['relays'] as $rl): ?>
                  <div>
                    R<?= e($rl['relay_number']) ?>
                    <?php if (!empty($rl['relay_date'])): ?> · <?= e(formatDate($rl['relay_date'])) ?><?php endif; ?>
                    <?php if (!empty($rl['match_time'])): ?> · <?= e(substr((string)$rl['match_time'], 0, 5)) ?><?php endif; ?>
                    · L<?= e($rl['lane_number']) ?>
                  </div>
                <?php endforeach; endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
