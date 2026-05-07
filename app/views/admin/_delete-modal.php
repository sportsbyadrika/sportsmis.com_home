<?php /* Shared confirmation modal for super-admin cascade deletes.
       Pages that include this partial put a button like
         <button type="button" class="btn btn-sm btn-outline-danger"
                 data-bs-toggle="modal" data-bs-target="#smsDeleteModal"
                 data-action="/admin/events/123/delete"
                 data-kind="event"
                 data-name="Annual Sports Meet 2026"
                 data-warning="Removes the event ONLY if no athletes have registered.">
            <i class="bi bi-trash"></i>
         </button>
       on each row. The single modal below picks up the data attributes,
       fills in the heading and warning, and POSTs the hidden form when
       the admin clicks Delete. */ ?>
<div class="modal fade" id="smsDeleteModal" tabindex="-1" aria-labelledby="smsDeleteModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" id="smsDeleteForm">
        <?= csrf() ?>
        <div class="modal-header bg-danger-subtle">
          <h5 class="modal-title text-danger" id="smsDeleteModalTitle">
            <i class="bi bi-exclamation-triangle me-2"></i>Confirm Delete
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2">You are about to delete the following <strong id="smsDeleteKind">item</strong>:</p>
          <p class="fw-semibold fs-5 mb-3" id="smsDeleteName">—</p>
          <div class="alert alert-warning small mb-3" id="smsDeleteWarning">This action cannot be undone.</div>
          <label class="form-label small">Type <code>DELETE</code> to confirm:</label>
          <input type="text" class="form-control" id="smsDeleteTyping" autocomplete="off" placeholder="DELETE">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger" id="smsDeleteConfirm" disabled>
            <i class="bi bi-trash me-1"></i>Delete
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
(function () {
  const modal   = document.getElementById('smsDeleteModal');
  const form    = document.getElementById('smsDeleteForm');
  const titleEl = document.getElementById('smsDeleteModalTitle');
  const kindEl  = document.getElementById('smsDeleteKind');
  const nameEl  = document.getElementById('smsDeleteName');
  const warnEl  = document.getElementById('smsDeleteWarning');
  const typeEl  = document.getElementById('smsDeleteTyping');
  const okBtn   = document.getElementById('smsDeleteConfirm');
  if (!modal || !form) return;
  modal.addEventListener('show.bs.modal', function (ev) {
    const t = ev.relatedTarget;
    if (!t) return;
    form.setAttribute('action', t.dataset.action || '#');
    const kind = t.dataset.kind || 'item';
    kindEl.textContent  = kind;
    titleEl.innerHTML   = '<i class="bi bi-exclamation-triangle me-2"></i>Confirm Delete ' + kind.charAt(0).toUpperCase() + kind.slice(1);
    nameEl.textContent  = t.dataset.name || '—';
    warnEl.innerHTML    = (t.dataset.warning || '') + '<br><strong>This action cannot be undone.</strong>';
    typeEl.value = '';
    okBtn.disabled = true;
  });
  typeEl.addEventListener('input', function () {
    okBtn.disabled = (typeEl.value.trim() !== 'DELETE');
  });
})();
</script>
