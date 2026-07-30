<?php
$pageTitle = 'Search by Athlete'; $old = $old ?? []; $base = $base ?? '';
// Repopulate the dd/mm/yyyy text box even if the stored value is yyyy-mm-dd.
$oldDob = trim((string)($old['dob'] ?? ''));
if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $oldDob, $m)) $oldDob = $m[3] . '/' . $m[2] . '/' . $m[1];
?>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
      <div class="d-flex align-items-center gap-2 mb-3">
        <a href="<?= e($base) ?: '/' ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Events</a>
        <h4 class="fw-bold mb-0"><i class="bi bi-person-badge me-2"></i>Search by Athlete</h4>
      </div>

      <div class="pr-card p-4">
        <?php if (!empty($error)): ?>
          <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle me-1"></i><?= e($error) ?></div>
        <?php endif; ?>
        <p class="text-muted small mb-3">Enter the athlete's Chest number and date of birth to view their results.</p>
        <form method="POST" action="<?= e($base) ?>/search" autocomplete="off">
          <!-- Honeypot: must stay empty (hidden from humans). -->
          <input type="text" name="website" tabindex="-1" autocomplete="off"
                 style="position:absolute;left:-9999px;width:1px;height:1px" aria-hidden="true">
          <div class="mb-3">
            <label class="form-label fw-medium">BIB / Chest Number</label>
            <input type="text" name="chest" class="form-control" maxlength="20" required
                   value="<?= e($old['chest'] ?? '') ?>" placeholder="e.g. 1024">
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Date of Birth <span class="text-muted">(dd/mm/yyyy)</span></label>
            <div class="input-group">
              <input type="text" name="dob" id="dobText" class="form-control" required inputmode="numeric" autocomplete="off"
                     pattern="\d{1,2}[/-]\d{1,2}[/-]\d{4}" placeholder="dd/mm/yyyy" value="<?= e($oldDob) ?>">
              <button type="button" class="btn btn-outline-secondary" id="dobPickBtn" title="Pick from calendar"><i class="bi bi-calendar3"></i></button>
              <input type="date" id="dobPick" max="<?= date('Y-m-d') ?>" tabindex="-1"
                     style="position:absolute;width:1px;height:1px;opacity:0;pointer-events:none">
            </div>
            <div class="form-text">Type as dd/mm/yyyy, or use the calendar button.</div>
          </div>
          <button type="submit" class="btn btn-warning w-100"><i class="bi bi-search me-1"></i>Find Athlete</button>
        </form>
        <script>
          (function () {
            var t = document.getElementById('dobText'),
                p = document.getElementById('dobPick'),
                b = document.getElementById('dobPickBtn');
            if (!t || !p || !b) return;
            b.addEventListener('click', function () {
              try { p.showPicker(); } catch (e) { p.focus(); p.click(); }
            });
            p.addEventListener('change', function () {
              if (!p.value) return;
              var s = p.value.split('-');           // yyyy-mm-dd
              t.value = s[2] + '/' + s[1] + '/' + s[0];
            });
          })();
        </script>
      </div>
    </div>
  </div>
</div>
