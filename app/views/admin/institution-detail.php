<?php $pageTitle = 'Review Institution'; ?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="/admin/institutions" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold">Review Institution Registration</h5>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="sms-card p-4 mb-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3">Registration Details</h6>
      <table class="table table-sm mb-0">
        <tbody>
          <tr><th>Institution Name</th><td><?= e($reg['institution_name']) ?></td></tr>
          <tr><th>SPOC Name</th><td><?= e($reg['spoc_name']) ?></td></tr>
          <tr><th>SPOC Mobile</th><td><?= e($reg['spoc_mobile']) ?></td></tr>
          <tr><th>Email</th><td><?= e($reg['email']) ?></td></tr>
          <tr><th>Address</th><td><?= nl2br(e($reg['address'])) ?></td></tr>
          <tr><th>Submitted</th><td><?= formatDate($reg['created_at'], 'd M Y H:i') ?></td></tr>
          <tr><th>Status</th><td><?= statusBadge($reg['status']) ?></td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-lg-5">
    <?php if ($reg['status'] === 'pending'): ?>
    <div class="sms-card p-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3">Actions</h6>

      <form method="POST" action="/admin/institutions/<?= $reg['id'] ?>/verify" class="mb-3">
        <?= csrf() ?>
        <p class="text-muted small mb-3">
          Verifying will create a user account and send login credentials to
          <strong><?= e($reg['email']) ?></strong>.
        </p>
        <button type="submit" class="btn btn-success w-100 fw-semibold"
                onclick="return confirm('Verify and send credentials?')">
          <i class="bi bi-check-circle me-2"></i>Verify & Send Credentials
        </button>
      </form>

      <form method="POST" action="/admin/institutions/<?= $reg['id'] ?>/reject">
        <?= csrf() ?>
        <button type="submit" class="btn btn-outline-danger w-100"
                onclick="return confirm('Reject this registration?')">
          <i class="bi bi-x-circle me-2"></i>Reject Registration
        </button>
      </form>
    </div>

    <?php elseif ($reg['status'] === 'verified' && $institution && !$institution['approved_at']): ?>
    <!-- Approve with validity dates -->
    <div class="sms-card p-4">
      <h6 class="fw-semibold border-bottom pb-2 mb-3">Approve Institution</h6>
      <p class="text-muted small mb-3">Set validity period for this institution.</p>
      <form method="POST" action="/admin/institutions/<?= $institution['id'] ?>/approve">
        <?= csrf() ?>
        <div class="mb-3">
          <label class="form-label fw-medium">Valid From</label>
          <input type="date" name="validity_from" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-medium">Valid To</label>
          <input type="date" name="validity_to" class="form-control" value="<?= date('Y-m-d', strtotime('+1 year')) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 fw-semibold">
          <i class="bi bi-shield-check me-2"></i>Approve Institution
        </button>
      </form>
    </div>

    <?php elseif ($institution && $institution['approved_at']): ?>
    <div class="sms-card p-4 border-start border-4 border-success">
      <div class="d-flex align-items-center gap-3 mb-3">
        <i class="bi bi-shield-check fs-4 text-success"></i>
        <div>
          <div class="fw-semibold">Institution Approved</div>
          <small class="text-muted">On <?= formatDate($institution['approved_at'], 'd M Y') ?></small>
        </div>
      </div>
      <div class="text-muted small">
        <strong>Validity:</strong>
        <?= formatDate($institution['validity_from']) ?> – <?= formatDate($institution['validity_to']) ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
