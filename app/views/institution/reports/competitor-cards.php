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

<form method="GET" class="sms-card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small mb-1">Unit / Club / Institution</label>
      <select name="unit_id" class="form-select form-select-sm">
        <option value="0">All units</option>
        <?php foreach (($units ?? []) as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= (int)($unit_filter ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
            <?= e($u['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1">Competitor No.</label>
      <select name="comp" class="form-select form-select-sm">
        <option value=""    <?= ($comp_filter ?? '') === ''    ? 'selected' : '' ?>>All</option>
        <option value="yes" <?= ($comp_filter ?? '') === 'yes' ? 'selected' : '' ?>>Generated</option>
        <option value="no"  <?= ($comp_filter ?? '') === 'no'  ? 'selected' : '' ?>>Not generated</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1">NOC Status</label>
      <select name="noc" class="form-select form-select-sm">
        <option value=""         <?= ($noc_filter ?? '') === ''         ? 'selected' : '' ?>>All</option>
        <option value="accepted" <?= ($noc_filter ?? '') === 'accepted' ? 'selected' : '' ?>>Accepted</option>
        <option value="pending"  <?= ($noc_filter ?? '') === 'pending'  ? 'selected' : '' ?>>Pending</option>
        <option value="rejected" <?= ($noc_filter ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1">Card Status</label>
      <select name="card" class="form-select form-select-sm">
        <option value=""           <?= ($card_filter ?? '') === ''           ? 'selected' : '' ?>>All</option>
        <option value="issued"    <?= ($card_filter ?? '') === 'issued'    ? 'selected' : '' ?>>Issued</option>
        <option value="allocated" <?= ($card_filter ?? '') === 'allocated' ? 'selected' : '' ?>>Number Allocated</option>
        <option value="pending"   <?= ($card_filter ?? '') === 'pending'   ? 'selected' : '' ?>>Pending</option>
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Apply</button>
      <a href="/institution/events/<?= e($eventHash) ?>/reports/competitor-cards?reset=1" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i> Reset</a>
    </div>
  </div>
</form>

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
            <th class="text-end" style="width:160px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $compNum  = (int)($r['competitor_number'] ?? 0);
            $issuedAt = $r['card_issued_at'] ?? null;
            $hasEmail = !empty($r['athlete_email']);
            $unit     = $r['unit_name'] ?: ($r['unit_name_other'] ? $r['unit_name_other'] . ' (Other)' : '—');
            $cardReady = $compNum > 0;
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
              <td class="text-end">
                <div class="d-inline-flex gap-1">
                  <?php if ($cardReady): ?>
                    <a href="/athlete/registrations/<?= e(hid_reg((int)$r['id'])) ?>/card"
                       target="_blank" rel="noopener"
                       class="btn btn-sm btn-outline-success" title="View Competitor Card">
                      <i class="bi bi-card-heading"></i>
                    </a>
                    <button type="button"
                            class="btn btn-sm btn-outline-primary"
                            onclick="resendCard(<?= (int)$r['id'] ?>)"
                            <?= $hasEmail ? '' : 'disabled title="No email on file"' ?>
                            title="Resend Card Email">
                      <i class="bi bi-envelope"></i>
                    </button>
                  <?php else: ?>
                    <span class="text-muted small">— issue card first —</span>
                  <?php endif; ?>
                </div>
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
const CC_CSRF = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
function toggleAll(cb) {
  document.querySelectorAll('.row-check:not(:disabled)').forEach(c => { c.checked = cb.checked; });
  updateSelCount();
}
function updateSelCount() {
  const n = document.querySelectorAll('.row-check:checked').length;
  document.getElementById('selCount').textContent = n + ' selected';
  document.getElementById('genBtn').disabled = n === 0;
}
function resendCard(regId) {
  if (!confirm('Resend the competitor card to this athlete via email?')) return;
  const f = document.createElement('form');
  f.method = 'POST';
  f.action = '/institution/registrations/' + regId + '/resend-card';
  const t = document.createElement('input');
  t.type = 'hidden'; t.name = '_token'; t.value = CC_CSRF;
  f.appendChild(t);
  document.body.appendChild(f);
  f.submit();
}
</script>
