<?php $pageTitle = 'Competitor List — ' . $event['name']; ?>

<div class="text-center" style="margin-bottom: 14px">
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
  <h3 class="mt-3" style="font-size:14pt">Competitor List (Sport-Event wise)</h3>
</div>

<?php if (empty($sections)): ?>
  <p class="text-center text-muted">No approved competitors yet — nothing to print.</p>
<?php else: ?>
  <?php $first = true; foreach ($sections as $sec): ?>
    <section class="<?= $first ? 'no-break' : 'page-break' ?>">
      <h4 class="mb-1" style="font-size:13pt">
        <?= e($sec['sport_name']) ?>
        <?php if (!empty($sec['event_code']) || !empty($sec['sport_event_name'])): ?>
          — <?= e(trim(($sec['event_code'] ? $sec['event_code'] . ' · ' : '') . ($sec['sport_event_name'] ?? ''))) ?>
        <?php endif; ?>
      </h4>
      <div class="small text-muted mb-2">
        <?php if (!empty($sec['category_name'])): ?>Category: <strong><?= e($sec['category_name']) ?></strong><?php endif; ?>
        <?php if (!empty($sec['age_category_name'])): ?> · Age: <strong><?= e($sec['age_category_name']) ?></strong><?php endif; ?>
        · <?= count($sec['athletes']) ?> competitor<?= count($sec['athletes']) === 1 ? '' : 's' ?>
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
