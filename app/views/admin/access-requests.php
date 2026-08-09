<?php
/**
 * Super-admin queue for organiser-access requests.
 * Expects: $pending (array), $recent (array).
 */
$pageTitle = 'Access Requests';
$fmt = fn($ts) => $ts ? formatDate($ts, 'd M Y, h:i A') : '';
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <h5 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2"></i>Organiser Access Requests</h5>
  <span class="badge bg-danger-subtle text-danger-emphasis"><?= count($pending) ?> pending</span>
</div>

<?= flashBag() ?>

<div class="alert alert-info small d-flex gap-2">
  <i class="bi bi-info-circle"></i>
  <div>Approving <strong>provisions the organiser workspace automatically</strong> (the account keeps
  its existing role and gains organiser access) and emails the requester.</div>
</div>

<div class="sms-card p-3 mb-4">
  <h6 class="fw-semibold mb-3">Pending</h6>
  <?php if (empty($pending)): ?>
    <p class="text-muted small mb-0"><i class="bi bi-check2-circle me-1"></i>No pending requests.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Requested</th>
            <th>Organisation / Event</th>
            <th>Requester email</th>
            <th>Message</th>
            <th style="width:320px">Decision</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pending as $r): ?>
            <tr>
              <td class="small text-muted"><?= e($fmt($r['created_at'] ?? '')) ?></td>
              <td class="fw-medium"><?= e($r['org_name'] ?? '') ?></td>
              <td class="small"><?= e($r['email'] ?? '') ?></td>
              <td class="small text-muted" style="max-width:260px"><?= nl2br(e($r['message'] ?? '')) ?: '<span class="text-muted">—</span>' ?></td>
              <td>
                <form method="POST" action="/admin/access-requests/<?= (int)$r['id'] ?>/decide" class="d-flex flex-column gap-2">
                  <?= csrf() ?>
                  <input type="text" name="admin_note" class="form-control form-control-sm" placeholder="Note to requester (optional)">
                  <div class="d-flex gap-2">
                    <button name="action" value="approve" class="btn btn-sm btn-success flex-fill"
                            onclick="return confirm('Approve this organiser request?')">
                      <i class="bi bi-check-lg me-1"></i>Approve
                    </button>
                    <button name="action" value="reject" class="btn btn-sm btn-outline-danger flex-fill"
                            onclick="return confirm('Reject this organiser request?')">
                      <i class="bi bi-x-lg me-1"></i>Reject
                    </button>
                  </div>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if (!empty($recent)): ?>
<div class="sms-card p-3">
  <h6 class="fw-semibold mb-3">Recently decided</h6>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light">
        <tr><th>Decided</th><th>Organisation / Event</th><th>Requester email</th><th>Status</th><th>Note</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $r): $ap = ($r['status'] ?? '') === 'approved'; ?>
          <tr>
            <td class="small text-muted"><?= e($fmt($r['decided_at'] ?? '')) ?></td>
            <td class="fw-medium"><?= e($r['org_name'] ?? '') ?></td>
            <td class="small"><?= e($r['email'] ?? '') ?></td>
            <td><span class="badge bg-<?= $ap ? 'success' : 'secondary' ?>-subtle text-<?= $ap ? 'success' : 'secondary' ?>-emphasis"><?= e(ucfirst((string)$r['status'])) ?></span></td>
            <td class="small text-muted"><?= e($r['admin_note'] ?? '') ?: '—' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
