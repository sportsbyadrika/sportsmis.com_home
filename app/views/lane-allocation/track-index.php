<?php
$pageTitle = 'Lane Allocation — ' . ($event['name'] ?? '');
$trackEvents = $track_events ?? [];
$totalApproved = 0;
foreach ($trackEvents as $te) { $totalApproved += (int)$te['approved']; }
?>

<!-- ── Top Bar ───────────────────────────────────────────────── -->
<div class="sms-card p-3 mb-3">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h5 class="mb-0 fw-bold"><i class="bi bi-flag me-2"></i>Lane Allocation
        <span class="badge bg-info-subtle text-info-emphasis ms-1"><?= e($sport ?? 'Athletics') ?></span>
      </h5>
      <div class="text-muted small mt-1">
        <span class="badge bg-primary-subtle text-primary-emphasis"><i class="bi bi-hash"></i> <?= e($event['event_code'] ?? '') ?></span>
        <span class="ms-1"><?= e($event['name'] ?? '') ?></span>
      </div>
    </div>
  </div>
</div>

<?= flashBag() ?>

<!-- ── Tabs ──────────────────────────────────────────────────── -->
<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="tab-events-btn" data-bs-toggle="tab" data-bs-target="#tab-events"
            type="button" role="tab">
      <i class="bi bi-list-ol me-1"></i>Events
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link disabled" type="button" role="tab" tabindex="-1" aria-disabled="true"
            title="Coming soon">
      <i class="bi bi-diagram-3 me-1"></i>Heats &amp; Lane Draw
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link disabled" type="button" role="tab" tabindex="-1" aria-disabled="true"
            title="Coming soon">
      <i class="bi bi-trophy me-1"></i>Results
    </button>
  </li>
</ul>

<div class="tab-content">
  <!-- Tab 1: Events with approved-athlete counts -->
  <div class="tab-pane fade show active" id="tab-events" role="tabpanel">
    <div class="sms-card p-3">
      <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-2 flex-wrap">
        <strong><i class="bi bi-list-ol me-1"></i>Events with Approved Athletes</strong>
        <span class="badge bg-secondary-subtle text-secondary-emphasis ms-auto">
          <?= count($trackEvents) ?> event<?= count($trackEvents) === 1 ? '' : 's' ?> · <?= $totalApproved ?> approved
        </span>
      </div>

      <?php if (empty($trackEvents)): ?>
        <p class="text-muted small text-center py-3 mb-0">
          No sport events have approved athletes yet.
        </p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:70px">Sl. No</th>
              <th>Name of Sport Event</th>
              <th class="text-end" style="width:180px">Approved Athletes</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($trackEvents as $i => $te): ?>
              <tr>
                <td class="text-center"><?= $i + 1 ?></td>
                <td>
                  <div class="fw-medium"><?= e($te['sport_event']) ?></div>
                  <?php if ($te['category'] !== '' || $te['event_code'] !== ''): ?>
                    <div class="small text-muted">
                      <?= e($te['category']) ?><?php if ($te['category'] !== '' && $te['event_code'] !== ''): ?> · <?php endif; ?>
                      <?php if ($te['event_code'] !== ''): ?><code><?= e($te['event_code']) ?></code><?php endif; ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="text-end fw-bold"><?= (int)$te['approved'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="table-light">
              <th colspan="2" class="text-end">Total</th>
              <th class="text-end"><?= $totalApproved ?></th>
            </tr>
          </tfoot>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
