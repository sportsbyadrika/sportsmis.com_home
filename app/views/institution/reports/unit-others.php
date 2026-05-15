<?php $pageTitle = 'Unit = Other — ' . $event['name']; ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-buildings me-2"></i>Registrations with Unit = Other</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <button class="btn btn-sm btn-outline-secondary ms-auto" type="button" onclick="window.print()">
    <i class="bi bi-printer me-1"></i>Print
  </button>
</div>

<p class="small text-muted mb-3">
  <i class="bi bi-info-circle me-1"></i>
  Athletes who picked <strong>Other</strong> for Unit / Club / Institution and typed a name instead of choosing one from the per-event Units list. Use this list to verify free-text entries before approval.
</p>

<div class="sms-card p-3">
  <?php if (empty($rows)): ?>
    <p class="text-muted small mb-0 text-center py-3">No registrations have used the "Other" unit option for this event.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:60px">#</th>
          <th>Athlete</th>
          <th>Unit (Other) — typed name</th>
          <th>Unit Reg. No.</th>
          <th>Application Status</th>
          <th>Submitted</th>
          <th class="text-end"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $i => $r): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td>
              <div class="fw-medium"><?= e($r['athlete_name']) ?></div>
              <?php if (!empty($r['athlete_mobile'])): ?>
                <small class="text-muted"><i class="bi bi-phone me-1"></i><?= e($r['athlete_mobile']) ?></small>
              <?php endif; ?>
            </td>
            <td><?= e($r['unit_name_other']) ?></td>
            <td class="small text-muted"><?= !empty($r['unit_reg_no']) ? e($r['unit_reg_no']) : '—' ?></td>
            <td><?= appStatusBadge($r['admin_review_status'] ?? null, $r['submitted_at'] ?? null) ?></td>
            <td class="small text-muted">
              <?= !empty($r['submitted_at'])
                    ? formatDate($r['submitted_at'], 'd M Y H:i')
                    : '<em>—</em>' ?>
            </td>
            <td class="text-end">
              <a href="/institution/registrations/<?= (int)$r['id'] ?>"
                 class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-eye me-1"></i>View
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light">
        <tr>
          <th colspan="6" class="text-end">Total</th>
          <th class="text-end"><?= count($rows) ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>
</div>
