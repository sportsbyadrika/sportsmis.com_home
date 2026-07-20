<?php
$pageTitle = 'Competitor Cards — ' . $event['name'];
$compLabel = \Models\Event::competitorLabel($event);   // e.g. "Chest Number"
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eventHash) ?>/reports" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Reports
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-card-heading me-2"></i>Competitor Cards</h5>
  <span class="text-muted small ms-2"><?= e($event['name']) ?></span>
  <a href="/institution/events/<?= e($eventHash) ?>/reports/competitor-cards.json"
     class="btn btn-sm btn-outline-success ms-auto"
     title="Download competitor details (current filter) as a JSON file">
    <i class="bi bi-filetype-json me-1"></i>Download JSON
  </a>
</div>

<?= flashBag() ?>

<p class="small text-muted mb-3">
  <i class="bi bi-info-circle me-1"></i>
  Approving a registration no longer issues a Competitor Card automatically. Tick the
  registrations below and click <strong>Generate</strong> to allocate <?= e($compLabel) ?>s
  (if not already assigned), <strong>Email</strong> to send the card to each athlete
  (allocating a number first if needed), or <strong>Print</strong> to open a print sheet with
  one card per page. Already-issued cards can be re-sent the same way.
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
      <label class="form-label small mb-1"><?= e($compLabel) ?></label>
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

<?php
  $qrMode  = (string)($event['competitor_card_qr_mode']  ?? 'competitor_no');
  $qrUrl   = (string)($event['competitor_card_qr_url']   ?? '');
  $qrLabel = (string)($event['competitor_card_qr_label'] ?? '');
?>
<form method="POST" action="/institution/events/<?= e($eventHash) ?>/reports/competitor-cards/settings"
      class="sms-card p-3 mb-3">
  <?= csrf() ?>
  <div class="d-flex align-items-center mb-3">
    <h6 class="fw-semibold mb-0"><i class="bi bi-gear me-2"></i>Card Settings</h6>
    <small class="text-muted ms-2">Applies to both the printed card and the card email.</small>
    <button class="btn btn-sm btn-primary ms-auto">
      <i class="bi bi-save me-1"></i>Save Settings
    </button>
  </div>

  <?php $eventsMode = (string)($event['competitor_card_events_mode'] ?? 'category'); ?>
  <div class="row g-3 mb-1">
    <div class="col-lg-4">
      <label class="form-label small mb-1 fw-semibold">
        <i class="bi bi-tag me-1"></i>Competitor Number Label
      </label>
      <select name="competitor_number_label" class="form-select form-select-sm">
        <?php foreach (\Models\Event::COMPETITOR_LABELS as $lbl): ?>
          <option value="<?= e($lbl) ?>" <?= $compLabel === $lbl ? 'selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="text-muted">Used on the card, its email and the report table. e.g. Chest Number for athletics/skating.</small>
    </div>
    <div class="col-lg-4">
      <label class="form-label small mb-1 fw-semibold">
        <i class="bi bi-list-nested me-1"></i>Registered Events Table
      </label>
      <select name="competitor_card_events_mode" class="form-select form-select-sm">
        <option value="category"    <?= $eventsMode !== 'sport_event' ? 'selected' : '' ?>>Event Category wise (default)</option>
        <option value="sport_event" <?= $eventsMode === 'sport_event' ? 'selected' : '' ?>>Sport Event wise</option>
      </select>
      <small class="text-muted">How the card's Registered Events table is grouped. Sport Event wise shows one row per sport event with a Team Entry flag.</small>
    </div>
  </div>

  <div class="row g-3">
    <!-- Card message -->
    <div class="col-lg-7">
      <label class="form-label small mb-1 fw-semibold">
        <i class="bi bi-chat-square-text me-1"></i>Card Message
      </label>
      <textarea name="competitor_card_message" rows="4" class="form-control"
                placeholder="e.g. Bring this card to the reporting desk along with a valid photo ID. Reporting time is 30 minutes before the relay."><?= e($event['competitor_card_message'] ?? '') ?></textarea>
      <small class="text-muted">Shown between the Registered Events table and the footer. Plain text — line breaks are preserved.</small>
    </div>

    <!-- QR content -->
    <div class="col-lg-5">
      <label class="form-label small mb-1 fw-semibold">
        <i class="bi bi-qr-code me-1"></i>QR Code Content
      </label>
      <div class="form-check">
        <input class="form-check-input" type="radio"
               name="competitor_card_qr_mode" id="qrModeCompNo" value="competitor_no"
               <?= $qrMode !== 'url' ? 'checked' : '' ?>
               onchange="document.getElementById('qrUrlInput').disabled = true">
        <label class="form-check-label" for="qrModeCompNo">
          Competitor Number <small class="text-muted">(default)</small>
        </label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="radio"
               name="competitor_card_qr_mode" id="qrModeUrl" value="url"
               <?= $qrMode === 'url' ? 'checked' : '' ?>
               onchange="document.getElementById('qrUrlInput').disabled = false; document.getElementById('qrUrlInput').focus()">
        <label class="form-check-label" for="qrModeUrl">
          Custom URL (e.g. venue map link)
        </label>
      </div>
      <input type="url" id="qrUrlInput" name="competitor_card_qr_url"
             class="form-control form-control-sm mt-2"
             value="<?= e($qrUrl) ?>"
             placeholder="https://maps.google.com/?q=…"
             <?= $qrMode === 'url' ? '' : 'disabled' ?>>
      <small class="text-muted">Used when "Custom URL" is selected above. Falls back to Competitor Number if blank or invalid.</small>

      <label class="form-label small mb-1 fw-semibold mt-3">QR Caption</label>
      <input type="text" name="competitor_card_qr_label" maxlength="100"
             class="form-control form-control-sm"
             value="<?= e($qrLabel) ?>"
             placeholder="Scan to verify">
      <small class="text-muted">Shown directly under the QR. Defaults to <em>Scan to verify</em> when left blank.</small>
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
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary" id="genBtn" disabled
                formaction="/institution/events/<?= e($eventHash) ?>/reports/competitor-cards/generate"
                onclick="return confirm('Allocate <?= e($compLabel) ?>s for the selected athletes? (No email is sent.)')">
          <i class="bi bi-hash me-1"></i>Generate
        </button>
        <button type="submit" class="btn btn-success" id="emailBtn" disabled
                formaction="/institution/events/<?= e($eventHash) ?>/reports/competitor-cards/email"
                onclick="return emailConfirm()">
          <i class="bi bi-envelope me-1"></i>Email
        </button>
        <button type="submit" class="btn btn-outline-dark" id="printBtn" disabled
                formaction="/institution/events/<?= e($eventHash) ?>/reports/competitor-cards/print"
                formtarget="_blank"
                title="Open a print sheet with one card per page (already-allocated numbers only)">
          <i class="bi bi-printer me-1"></i>Print
        </button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:40px"></th>
            <th>Athlete</th>
            <th>Unit</th>
            <th>Events</th>
            <th><?= e($compLabel) ?></th>
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
                       data-has-email="<?= $hasEmail ? '1' : '0' ?>"
                       <?= $hasEmail ? '' : 'title="No email on file — can be Generated, but Email will skip this athlete"' ?>>
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
  document.getElementById('genBtn').disabled   = n === 0;
  document.getElementById('emailBtn').disabled = n === 0;
  document.getElementById('printBtn').disabled = n === 0;
}
function emailConfirm() {
  const checked = Array.from(document.querySelectorAll('.row-check:checked'));
  if (!checked.length) return false;
  const noEmail = checked.filter(c => c.dataset.hasEmail === '0').length;
  let msg = 'Email the Competitor Card to the selected athletes? '
          + 'Any without a <?= e($compLabel) ?> will be allocated one first.';
  if (noEmail) {
    msg = noEmail + ' of the selected athlete(s) have no email on file and will be '
        + 'skipped (no card sent). ' + msg;
  }
  return confirm(msg);
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
