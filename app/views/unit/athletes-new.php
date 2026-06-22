<?php
$pageTitle = 'Add Athlete';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$old       = $old    ?? [];
$errors    = $errors ?? [];
$activeUnit = $active_unit_id;
$err = function (string $key) use ($errors): string {
    return isset($errors[$key]) ? '<div class="invalid-feedback d-block">' . htmlspecialchars((string)$errors[$key]) . '</div>' : '';
};
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0 fw-bold"><i class="bi bi-person-plus me-2"></i>Add Athlete</h5>
    <div class="text-muted small mt-1">
      Event: <strong><?= e($event['name']) ?></strong>
      · Code: <code><?= e($event['event_code'] ?? '') ?></code>
    </div>
  </div>
  <a href="/unit/dashboard" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
  </a>
</div>

<?= flashBag() ?>

<div class="sms-card p-4">
  <p class="text-muted small">
    Create a new athlete on this event under your Unit. Email is optional &mdash;
    leave it blank for a managed athlete who won&rsquo;t log in. You can add
    sport-events and submit the registration from the dashboard once the
    athlete is created.
  </p>

  <form method="POST" action="/unit/athletes" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

    <div class="row g-3">
      <?php if (count($units) > 1): ?>
        <div class="col-md-6">
          <label class="form-label fw-medium">Unit / Club <span class="text-danger">*</span></label>
          <select name="unit_id" class="form-select form-select-sm" required>
            <?php foreach ($units as $u): ?>
              <option value="<?= (int)$u['id'] ?>"
                      <?= (int)($old['unit_id'] ?? $activeUnit) === (int)$u['id'] ? 'selected' : '' ?>>
                <?= e($u['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php else: ?>
        <input type="hidden" name="unit_id" value="<?= (int)($units[0]['id'] ?? 0) ?>">
      <?php endif; ?>

      <div class="col-md-6">
        <label class="form-label fw-medium">Passport Photo
          <small class="text-muted">(optional)</small></label>
        <input type="file" name="passport_photo" class="form-control form-control-sm"
               accept="image/jpeg,image/png,image/webp">
      </div>

      <div class="col-md-8">
        <label class="form-label fw-medium">Full Name <span class="text-danger">*</span></label>
        <input type="text" name="name" maxlength="255" required
               class="form-control form-control-sm <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
               value="<?= e($old['name'] ?? '') ?>" placeholder="As per identity document">
        <?= $err('name') ?>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-medium">Gender <span class="text-danger">*</span></label>
        <select name="gender" required
                class="form-select form-select-sm <?= isset($errors['gender']) ? 'is-invalid' : '' ?>">
          <option value="">— Select —</option>
          <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $g => $lbl): ?>
            <option value="<?= $g ?>" <?= ($old['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
        <?= $err('gender') ?>
      </div>

      <div class="col-md-4">
        <label class="form-label fw-medium">Date of Birth <span class="text-danger">*</span></label>
        <input type="date" name="date_of_birth" max="<?= date('Y-m-d') ?>" required
               class="form-control form-control-sm <?= isset($errors['date_of_birth']) ? 'is-invalid' : '' ?>"
               value="<?= e($old['date_of_birth'] ?? '') ?>">
        <?= $err('date_of_birth') ?>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-medium">Mobile <small class="text-muted">(optional)</small></label>
        <input type="tel" name="mobile" maxlength="10" inputmode="numeric" pattern="\d{10}"
               class="form-control form-control-sm <?= isset($errors['mobile']) ? 'is-invalid' : '' ?>"
               value="<?= e($old['mobile'] ?? '') ?>" placeholder="10-digit">
        <?= $err('mobile') ?>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-medium">Email <small class="text-muted">(optional &mdash; enables athlete login)</small></label>
        <input type="email" name="email" maxlength="255"
               class="form-control form-control-sm <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
               value="<?= e($old['email'] ?? '') ?>">
        <?= $err('email') ?>
      </div>

      <div class="col-md-4">
        <label class="form-label fw-medium">Aadhaar Number <small class="text-muted">(optional)</small></label>
        <input type="text" name="id_proof_number" inputmode="numeric" pattern="\d{12}" maxlength="12"
               class="form-control form-control-sm <?= isset($errors['id_proof_number']) ? 'is-invalid' : '' ?>"
               value="<?= e($old['id_proof_number'] ?? '') ?>" placeholder="12-digit">
        <?= $err('id_proof_number') ?>
      </div>
      <div class="col-md-8">
        <label class="form-label fw-medium">Aadhaar Proof File <small class="text-muted">(optional)</small></label>
        <input type="file" name="id_proof_file" class="form-control form-control-sm"
               accept="image/jpeg,image/png,image/webp,application/pdf">
      </div>

      <div class="col-12">
        <label class="form-label fw-medium">Address <small class="text-muted">(optional)</small></label>
        <textarea name="address" rows="2" maxlength="500"
                  class="form-control form-control-sm"><?= e($old['address'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="d-flex justify-content-end mt-3 gap-2">
      <a href="/unit/dashboard" class="btn btn-outline-secondary btn-sm">Cancel</a>
      <button type="submit" class="btn btn-primary btn-sm">
        <i class="bi bi-save me-1"></i>Create Athlete &amp; Start Registration
      </button>
    </div>
  </form>
</div>
