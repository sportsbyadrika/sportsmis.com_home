<?php $pageTitle = 'Competitor Cards — ' . $event['name']; ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-card-heading me-2"></i>Competitor Cards</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
</div>

<?= flashBag() ?>

<p class="small text-muted mb-3">
  <i class="bi bi-info-circle me-1"></i>
  Approving a registration no longer issues a Competitor Card automatically. Tick the
  registrations below and click <strong>Generate</strong> to allocate competitor numbers
  (if not already assigned) and email the card to each athlete. Already-issued cards can
  be re-sent the same way.
</p>

<form method="POST" action="/institution/events/<?= e($eventHash) ?>/reports/competitor-cards/generate">
  <?= csrf() ?>
  <div class="sms-card p-3">
    <?php if (empty($rows)): ?>
      <p class="text-muted small mb-0 text-center py-3">No approved registrations on this event yet.</p>
    <?php else: ?>
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <div class="form-check m-0">
          <input class="form-check-input" type="checkbox" id="selAll" onchange="toggleAll(this)">
          <label class="form-check-label small" for="selAll">Select all</label>
        </div>
        <span class="text-muted small" id="selCount">0 selected</span>
      </div>
      <button type="submit" class="btn btn-success" id="genBtn" disabled
              onclick="return confirm('Generate Competitor Cards for the selected athletes and email them?')">
        <i class="bi bi-send-check me-1"></i>Generate &amp; Email
      </button>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:40px"></th>
            <th>Athlete</th>
            <th>Unit</th>
            <th>Events</th>
            <th>Competitor No.</th>
            <th>NOC Status</th>
            <th>Card Status</th>
            <th>Email</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $compNum  = (int)($r['competitor_number'] ?? 0);
            $issuedAt = $r['card_issued_at'] ?? null;
            $hasEmail = !empty($r['athlete_email']);
            $unit     = $r['unit_name'] ?: ($r['unit_name_other'] ? $r['unit_name_other'] . ' (Other)' : '—');
          ?>
            <tr>
              <td>
                <input class="form-check-input row-check" type="checkbox" name="ids[]"
                       value="<?= (int)$r['id'] ?>" onchange="updateSelCount()"
                       <?= $hasEmail ? '' : 'disabled title="No email on file"' ?>>
              </td>
              <td>
                <div class="fw-medium"><?= e($r['athlete_name']) ?></div>
                <small class="text-muted">
                  <?php if (!empty($r['athlete_mobile'])): ?>
                    <i class="bi bi-phone me-1"></i><?= e($r['athlete_mobile']) ?>
                  <?php endif; ?>
                </small>
              </td>
              <td class="small"><?= e($unit) ?></td>
              <td class="small text-muted"><?= (int)$r['items_count'] ?></td>
              <td>
                <?php if ($compNum): ?>
                  <code class="fw-bold">#<?= str_pad((string)$compNum, 4, '0', STR_PAD_LEFT) ?></code>
                <?php else: ?>
                  <span class="text-muted">— not yet —</span>
                <?php endif; ?>
              </td>
              <td>
                <?php
                  $noc = $r['noc_status'] ?: 'pending';
                  $nocMap = [
                    'accepted' => ['Accepted', 'bg-success'],
                    'rejected' => ['Rejected', 'bg-danger'],
                    'pending'  => ['Pending',  'bg-warning text-dark'],
                  ];
                  $nb = $nocMap[$noc] ?? $nocMap['pending'];
                ?>
                <span class="badge <?= $nb[1] ?>"><?= $nb[0] ?></span>
              </td>
              <td>
                <?php if ($issuedAt): ?>
                  <span class="badge bg-success-subtle text-success">
                    <i class="bi bi-check-circle me-1"></i>Issued
                  </span>
                  <small class="text-muted d-block"><?= formatDate($issuedAt, 'd M Y H:i') ?></small>
                <?php elseif ($compNum): ?>
                  <span class="badge bg-info-subtle text-info">Number Allocated</span>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary">Pending</span>
                <?php endif; ?>
              </td>
              <td class="small text-muted">
                <?php if ($hasEmail): ?>
                  <?= e($r['athlete_email']) ?>
                <?php else: ?>
                  <span class="text-danger">— missing —</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</form>

<script>
function toggleAll(cb) {
  document.querySelectorAll('.row-check:not(:disabled)').forEach(c => { c.checked = cb.checked; });
  updateSelCount();
}
function updateSelCount() {
  const n = document.querySelectorAll('.row-check:checked').length;
  document.getElementById('selCount').textContent = n + ' selected';
  document.getElementById('genBtn').disabled = n === 0;
}
</script>
