<?php
$pageTitle = 'Messaging Settings';
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];

$providers   = $providers ?? [];
$waProviders = array_values(array_filter($providers, fn($p) => ($p['channel'] ?? 'whatsapp') === 'whatsapp'));
$mask = function ($s) { $s = (string)$s; return $s === '' ? '' : (mb_strlen($s) <= 4 ? '••••' : '••••' . mb_substr($s, -4)); };
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

<!-- ── API Providers ─────────────────────────────────────────────── -->
<div class="sms-card p-3 mb-3">
  <h6 class="fw-bold mb-1"><i class="bi bi-hdd-network me-1"></i>API Providers</h6>
  <p class="small text-muted mb-3">Configure a provider once — name, channel, API URL &amp; key — then pick it per message type. Changing the provider or rotating a key here updates every message type that uses it.</p>

  <div class="table-responsive mb-3">
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light"><tr>
        <th>Name</th><th style="width:110px">Channel</th><th>API URL</th>
        <th style="width:120px">API Key</th><th class="text-end" style="width:110px">Action</th>
      </tr></thead>
      <tbody>
        <?php if (empty($providers)): ?>
          <tr><td colspan="5" class="text-muted text-center py-3">No providers yet — add one below.</td></tr>
        <?php else: foreach ($providers as $p): ?>
          <tr>
            <td class="fw-medium"><?= e($p['name']) ?></td>
            <td><span class="badge bg-secondary-subtle text-secondary-emphasis"><?= e($channels[$p['channel']] ?? $p['channel']) ?></span></td>
            <td class="small text-truncate" style="max-width:280px"><?= e($p['api_url'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
            <td class="small font-monospace"><?= e($mask($p['api_key'] ?? '')) ?: '<span class="text-muted">—</span>' ?></td>
            <td class="text-end text-nowrap">
              <button class="btn btn-sm btn-outline-secondary" type="button"
                      onclick='editProvider(<?= json_encode($p, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' title="Edit"><i class="bi bi-pencil"></i></button>
              <button class="btn btn-sm btn-outline-danger" type="button"
                      onclick="deleteProvider(<?= (int)$p['id'] ?>, '<?= e($p['name']) ?>')" title="Delete"><i class="bi bi-trash"></i></button>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <form class="row g-2 align-items-end" onsubmit="return saveProvider(event, this)">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="id" id="prov_id" value="">
    <div class="col-md-3">
      <label class="form-label small mb-1">Provider Name</label>
      <input type="text" name="name" id="prov_name" class="form-control form-control-sm" placeholder="e.g. Chatico" required maxlength="120">
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1">Channel</label>
      <select name="channel" id="prov_channel" class="form-select form-select-sm">
        <?php foreach ($channels as $k => $label): ?>
          <option value="<?= e($k) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label small mb-1">API URL (POST)</label>
      <input type="url" name="api_url" id="prov_url" class="form-control form-control-sm" placeholder="https://api.chatico.in/...">
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1">API Key</label>
      <input type="text" name="api_key" id="prov_key" class="form-control form-control-sm" placeholder="apiKey">
    </div>
    <div class="col-md-1 d-grid gap-1">
      <button type="submit" class="btn btn-sm btn-primary" id="prov_save"><i class="bi bi-save"></i></button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetProvider()" id="prov_reset" style="display:none">New</button>
    </div>
  </form>
</div>

<div class="alert alert-light border small mb-3">
  <i class="bi bi-info-circle me-1"></i>
  WhatsApp uses the <strong>chatico.in</strong> payload: <code>apiKey</code> &amp; API URL come from the selected
  <strong>provider</strong>; <code>campaignName</code> and up to 7 <code>templateParams</code> are set per message type.
  The provider takes <code>userName</code> from <strong>param&nbsp;3</strong>. Map each param to a <em>software field</em>
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
          <label class="form-label small mb-1">WhatsApp Provider</label>
          <select name="wa_provider_id" class="form-select form-select-sm">
            <option value="0">— Select provider —</option>
            <?php foreach ($waProviders as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= (int)$s['wa_provider_id'] === (int)$p['id'] ? 'selected' : '' ?>>
                <?= e($p['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($waProviders)): ?>
            <div class="form-text text-danger">No WhatsApp providers yet — add one above.</div>
          <?php endif; ?>
        </div>
        <div class="col-md-6">
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

/* ── Providers ─────────────────────────────────────────────── */
function editProvider(p) {
  document.getElementById('prov_id').value      = p.id;
  document.getElementById('prov_name').value    = p.name || '';
  document.getElementById('prov_channel').value = p.channel || 'whatsapp';
  document.getElementById('prov_url').value     = p.api_url || '';
  document.getElementById('prov_key').value     = p.api_key || '';
  document.getElementById('prov_reset').style.display = '';
  document.getElementById('prov_name').focus();
}
function resetProvider() {
  document.getElementById('prov_id').value = '';
  document.getElementById('prov_name').value = '';
  document.getElementById('prov_url').value = '';
  document.getElementById('prov_key').value = '';
  document.getElementById('prov_channel').value = 'whatsapp';
  document.getElementById('prov_reset').style.display = 'none';
}
async function saveProvider(ev, form) {
  ev.preventDefault();
  const fd = new FormData(form);
  try {
    const res = await fetch('/admin/settings/messaging/providers/save', { method: 'POST', body: fd });
    const d = await res.json();
    if (!d.success) { msgToast(d.message || 'Save failed.', 'danger'); return false; }
    msgToast('Provider saved.', 'success');
    setTimeout(() => location.reload(), 500);
  } catch (e) { msgToast('Save failed — please retry.', 'danger'); }
  return false;
}
async function deleteProvider(id, name) {
  if (!confirm('Delete provider "' + name + '"? Message types using it will lose their WhatsApp credentials until reassigned.')) return;
  const fd = new FormData();
  fd.append('_token', CSRF);
  fd.append('id', id);
  const res = await fetch('/admin/settings/messaging/providers/delete', { method: 'POST', body: fd });
  const d = await res.json();
  msgToast(d.message || (d.success ? 'Removed.' : 'Delete failed.'), d.success ? 'success' : 'danger');
  if (d.success) setTimeout(() => location.reload(), 500);
}

/* ── Message types ─────────────────────────────────────────── */
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
