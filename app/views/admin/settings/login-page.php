<?php $pageTitle = 'Login Page Settings'; ?>

<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
  <a href="/admin/settings" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Settings
  </a>
  <h5 class="mb-0 fw-bold"><i class="bi bi-box-arrow-in-right me-2"></i>Login Page</h5>
</div>

<?= flashBag() ?>

<div class="row">
  <div class="col-lg-7">
    <div class="sms-card p-4">
      <p class="text-muted small mb-3">
        Control which buttons appear on the public login page. Turning a switch
        off hides that button from the <strong>Athletes</strong> card and blocks
        its panel — existing accounts are unaffected and can still be reached
        directly once re-enabled.
      </p>

      <form method="POST" action="/admin/settings/login-page/save">
        <?= csrf() ?>

        <div class="border rounded-3 p-3 mb-3">
          <div class="fw-semibold mb-2"><i class="bi bi-person-arms-up me-1"></i>Athletes card</div>

          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch"
                   id="athlete_login_visible" name="athlete_login_visible" value="1"
                   <?= !empty($athlete_login_visible) ? 'checked' : '' ?>>
            <label class="form-check-label" for="athlete_login_visible">
              Show <strong>Login</strong> button
            </label>
          </div>

          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch"
                   id="athlete_register_visible" name="athlete_register_visible" value="1"
                   <?= !empty($athlete_register_visible) ? 'checked' : '' ?>>
            <label class="form-check-label" for="athlete_register_visible">
              Show <strong>Register</strong> button
            </label>
          </div>
        </div>

        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Settings</button>
      </form>
    </div>
  </div>
</div>
