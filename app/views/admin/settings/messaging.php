<?php
$pageTitle = 'Messaging Settings';
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];
?>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
  <div id="msgToast" class="toast align-items-center border-0" role="alert">
    <div class="d-flex"><div class="toast-body fw-medium" id="msgToastMsg"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div>
  </div>
</div>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/admin/settings" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Settings</a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-chat-dots me-2"></i>Messaging</h5>
  <span class="text-muted small ms-2">Email is the default channel; enable WhatsApp / SMS per message type.</span>
</div>

<?= flashBag() ?>

<div class="alert alert-light border small mb-3">
  <i class="bi bi-info-circle me-1"></i>
  WhatsApp uses the <strong>chatico.in</strong> payload: <code>apiKey</code>, <code>campaignName</code>,
  <code>destination</code> (recipient mobile), and up to 7 <code>templateParams</code>. The provider takes
  <code>userName</code> from <strong>param&nbsp;3</strong>. Map each param to a <em>software field</em>
  (resolved when the message is sent) or a <em>constant</em> value.
</div>

<!-- Field keys usable in template params -->
<datalist id="waFields">
  <?php foreach ($fields as $k => $label): ?>
    <option value="<?= e($k) ?>"><?= e($label) ?></option>
  <?php endforeach; ?>
</datalist>

<?php foreach ($types as $code => $t):
  $s = $t['settings'];
  $def = $t['def'];
  // Normalise the stored mapping to exactly 7 rows.
  $map = $s['wa_params'] ?? [];
  for ($i = 0; $i < 7; $i++) { if (!isset($map[$i])) $map[$i] = ['src' => '', 'val' => '']; }
?>
<div class="sms-card p-3 mb-3" data-type="<?= e($code) ?>">
  <form onsubmit="return saveMsg(event, this)">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="message_type" value="<?= e($code) ?>">

    <div class="d-flex flex-wrap align-items-start gap-2 mb-2">
      <div class="flex-grow-1">
        <h6 class="fw-bold mb-1"><?= e($def['label']) ?></h6>
        <div class="small text-muted"><?= e($def['desc']) ?></div>
        <div class="small text-muted mt-1">
          <i class="bi bi-person-check me-1"></i>Recipient: <strong><?= e($def['recipient']) ?></strong>
          &middot; Available fields:
          <?php foreach (($def['fields'] ?? []) as $f): ?><code class="me-1"><?= e($f) ?></code><?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Channels -->
    <div class="d-flex flex-wrap gap-4 mb-2">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="email_enabled" value="1" id="em_<?= e($code) ?>" <?= $s['email_enabled'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="em_<?= e($code) ?>"><i class="bi bi-envelope me-1"></i>Email <span class="text-muted small">(default)</span></label>
      </div>
      <div class="form-check form-switch">
        <input class="form-check-input wa-toggle" type="checkbox" name="whatsapp_enabled" value="1" id="wa_<?= e($code) ?>" <?= $s['whatsapp_enabled'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="wa_<?= e($code) ?>"><i class="bi bi-whatsapp me-1 text-success"></i>WhatsApp</label>
      </div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="sms_enabled" value="1" id="sm_<?= e($code) ?>" <?= $s['sms_enabled'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="sm_<?= e($code) ?>"><i class="bi bi-phone me-1"></i>SMS <span class="text-muted small">(coming soon)</span></label>
      </div>
    </div>

    <!-- WhatsApp config -->
    <div class="border rounded-3 p-3 bg-light-subtle wa-config">
      <div class="row g-2 mb-2">
        <div class="col-md-6">
          <label class="form-label small mb-1">WhatsApp API URL (POST)</label>
          <input type="url" name="wa_api_url" class="form-control form-control-sm" value="<?= e($s['wa_api_url']) ?>" placeholder="https://api.chatico.in/...">
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">API Key</label>
          <input type="text" name="wa_api_key" class="form-control form-control-sm" value="<?= e($s['wa_api_key']) ?>" placeholder="apiKey">
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">Campaign Name</label>
          <input type="text" name="wa_campaign_name" class="form-control form-control-sm" value="<?= e($s['wa_campaign_name']) ?>" placeholder="campaignName">
        </div>
      </div>

      <label class="form-label small mb-1">Template Parameters <span class="text-muted">(param 1 → 7; param 3 is used as <code>userName</code>)</span></label>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light"><tr>
            <th style="width:70px">Param</th>
            <th style="width:160px">Source</th>
            <th>Value <span class="text-muted small">(field key or constant text)</span></th>
          </tr></thead>
          <tbody>
            <?php for ($i = 0; $i < 7; $i++): $m = $map[$i]; ?>
              <tr>
                <td class="text-muted">param<?= $i + 1 ?><?= $i === 2 ? ' <span class="badge bg-secondary-subtle text-secondary">userName</span>' : '' ?></td>
                <td>
                  <select name="param_src[]" class="form-select form-select-sm p-src">
                    <option value="" <?= $m['src'] === '' ? 'selected' : '' ?>>—</option>
                    <option value="field" <?= $m['src'] === 'field' ? 'selected' : '' ?>>Software field</option>
                    <option value="const" <?= $m['src'] === 'const' ? 'selected' : '' ?>>Constant</option>
                  </select>
                </td>
                <td>
                  <input type="text" name="param_val[]" class="form-control form-control-sm p-val"
                         list="waFields" value="<?= e($m['val']) ?>"
                         placeholder="<?= $m['src'] === 'const' ? 'literal text' : 'e.g. name_of_user' ?>"
                         <?= $m['src'] === '' ? 'disabled' : '' ?>>
                </td>
              </tr>
            <?php endfor; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="text-end mt-2">
      <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Save</button>
    </div>
  </form>
</div>
<?php endforeach; ?>

<script>
const CSRF = '<?= e($csrfToken) ?>';
function msgToast(msg, type) {
  const el = document.getElementById('msgToast');
  el.className = 'toast align-items-center border-0 text-bg-' + (type || 'primary');
  document.getElementById('msgToastMsg').textContent = msg;
  if (window.bootstrap && bootstrap.Toast) bootstrap.Toast.getOrCreateInstance(el, {delay:2200}).show();
}

// Enable/disable a param value input based on its source; enable/disable the
// whole WhatsApp config block based on the WhatsApp toggle.
function wireCard(card) {
  const waToggle = card.querySelector('.wa-toggle');
  const waCfg    = card.querySelector('.wa-config');
  const syncWa = () => { waCfg.style.opacity = waToggle.checked ? '1' : '.5'; };
  waToggle.addEventListener('change', syncWa); syncWa();

  card.querySelectorAll('tr').forEach(tr => {
    const src = tr.querySelector('.p-src');
    const val = tr.querySelector('.p-val');
    if (!src || !val) return;
    const sync = () => {
      val.disabled = (src.value === '');
      val.placeholder = src.value === 'const' ? 'literal text' : 'e.g. name_of_user';
    };
    src.addEventListener('change', sync);
  });
}
document.querySelectorAll('.sms-card[data-type]').forEach(wireCard);

async function saveMsg(ev, form) {
  ev.preventDefault();
  const btn = form.querySelector('button[type=submit]');
  const orig = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
  // Re-enable disabled param inputs momentarily so their (empty) values post in order.
  const disabled = Array.from(form.querySelectorAll('.p-val[disabled]'));
  disabled.forEach(el => el.disabled = false);
  const fd = new FormData(form);
  disabled.forEach(el => el.disabled = true);
  try {
    const res = await fetch('/admin/settings/messaging/save', { method: 'POST', body: fd });
    const d = await res.json();
    msgToast(d.message || (d.success ? 'Saved.' : 'Save failed.'), d.success ? 'success' : 'danger');
  } catch (e) {
    msgToast('Save failed — please retry.', 'danger');
  }
  btn.disabled = false; btn.innerHTML = orig;
  return false;
}
</script>
