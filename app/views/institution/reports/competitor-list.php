<?php $pageTitle = 'Competitor List — ' . $event['name']; ?>

<header class="event-head no-break">
  <?php if (!empty($event['logo'])): ?>
    <img src="<?= e($event['logo']) ?>" alt="" class="event-logo">
  <?php endif; ?>
  <div class="event-head-text">
    <h2 class="fw-bold mb-1" style="font-size:18pt"><?= e($event['name']) ?></h2>
    <div class="small text-muted">
      <?= e($event['institution_name'] ?? '') ?>
      <?php if (!empty($event['location'])): ?> · <i class="bi bi-geo-alt"></i> <?= e($event['location']) ?><?php endif; ?>
      <?php if (!empty($event['event_date_from'])): ?>
        · <?= formatDate($event['event_date_from']) ?>
        <?php if (!empty($event['event_date_to']) && $event['event_date_to'] !== $event['event_date_from']): ?>
          – <?= formatDate($event['event_date_to']) ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <h3 class="mt-2 mb-0" style="font-size:14pt">Competitor List (Sport-Event wise)</h3>
  </div>
</header>

<style>
  .event-head {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 14px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
  }
  .event-head .event-logo {
    width: 72px;
    height: 72px;
    object-fit: contain;
    flex-shrink: 0;
  }
  .event-head .event-head-text { flex: 1; min-width: 0; }
</style>

<?php if (empty($sections)): ?>
  <p class="text-center text-muted">No approved competitors yet — nothing to print.</p>
<?php else: ?>
  <?php $first = true; foreach ($sections as $sec): ?>
    <section class="<?= $first ? 'no-break' : 'page-break' ?>">
      <h4 class="mb-1" style="font-size:13pt">
        <?php if (!empty($sec['event_code'])): ?><?= e($sec['event_code']) ?> · <?php endif; ?>
        <?= e($sec['sport_event_name'] ?? '') ?>
      </h4>
      <div class="small text-muted mb-2">
        <?= count($sec['athletes']) ?> competitor<?= count($sec['athletes']) === 1 ? '' : 's' ?>
      </div>

      <?php if (empty($sec['athletes'])): ?>
        <p class="text-muted small">No approved competitors for this sport-event.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th style="width: 7%"  class="text-center">Sl. No</th>
              <th style="width: 13%" class="text-center">Comp. No.</th>
              <th>Athlete Name</th>
              <th style="width: 8%"  class="text-center">Age</th>
              <th style="width: 10%" class="text-center">Gender</th>
              <th>Unit / Club / Institution</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sec['athletes'] as $i => $a): ?>
              <tr>
                <td class="text-center"><?= $i + 1 ?></td>
                <td class="text-center fw-bold">
                  <?= $a['competitor_number']
                        ? '#' . str_pad((string)(int)$a['competitor_number'], 4, '0', STR_PAD_LEFT)
                        : '—' ?>
                </td>
                <td><?= e($a['athlete_name']) ?></td>
                <td class="text-center"><?= e($a['age']) ?></td>
                <td class="text-center"><?= e($a['gender']) ?></td>
                <td><?= e($a['unit']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  <?php $first = false; endforeach; ?>
<?php endif; ?>
