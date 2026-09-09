<?php
$pageTitle = 'Search — ' . $event['name'];
$eh = $eventHash ?? hid_event((int)$event['id']);
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/institution/events/<?= e($eh) ?>/view" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Event
  </a>
  <div>
    <h5 class="mb-0 fw-bold"><i class="bi bi-search me-2"></i>Search Competitors</h5>
    <div class="text-muted small mt-1">
      Event: <strong><?= e($event['name']) ?></strong> · Code: <code><?= e($event['event_code'] ?? '') ?></code>
    </div>
  </div>
</div>

<?php
  $searchAction = '/institution/events/' . e($eh) . '/search';
  $showQr       = false;
  $viewUrlFn    = fn(int $regId) => '/institution/registrations/' . $regId;
  require APP_ROOT . '/views/partials/registration-search.php';
?>
