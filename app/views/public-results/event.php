<?php $pageTitle = ($event['name'] ?? 'Results') . ' — Results'; $base = $base ?? ''; ?>
<div class="container">
  <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a href="<?= e($base) ?: '/' ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Events</a>
    <?php if (!empty($event['logo'])): ?><img src="<?= e($event['logo']) ?>" alt="" style="height:40px" class="rounded"><?php endif; ?>
    <h4 class="fw-bold mb-0"><?= e($event['name']) ?></h4>
    <span class="badge bg-success-subtle text-success-emphasis">Published Results</span>
    <div class="ms-auto d-flex gap-2">
      <button type="button" class="btn btn-sm btn-outline-primary" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
      <a href="<?= e($base) ?>/search" class="btn btn-sm btn-warning"><i class="bi bi-search me-1"></i>Search by Athlete</a>
    </div>
  </div>

  <div class="pr-card p-3 pr-cardify">
    <?php
      // Shared Medal Tally tabs (Unit-wise Points + Event-wise Winners),
      // published-only. No print buttons and no event filter on the public page.
      $showPrint = false;
      $isPublicView = true;
      $auto_refresh = false;   // manual Refresh button instead of a 60s auto-reload
      require APP_ROOT . '/views/partials/track-medal-tabs.php';
    ?>
  </div>

  <?php
    // Optional event video (YouTube) configured by the super admin — shown
    // under the results panel. Accept full URLs (watch / youtu.be / shorts /
    // live / embed) or a bare 11-character video id.
    $videoUrl = trim((string)($event['result_video_url'] ?? ''));
    $ytId = '';
    if ($videoUrl !== '') {
        if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/))([A-Za-z0-9_-]{11})~', $videoUrl, $m)) {
            $ytId = $m[1];
        } elseif (preg_match('~^[A-Za-z0-9_-]{11}$~', $videoUrl)) {
            $ytId = $videoUrl;
        }
    }
  ?>
  <?php if ($ytId !== ''): ?>
    <div class="pr-card p-3 pr-cardify mt-3">
      <h6 class="fw-bold mb-3"><i class="bi bi-youtube text-danger me-2"></i>Event Video</h6>
      <div style="position:relative;width:100%;max-width:900px;margin:0 auto;aspect-ratio:16/9;">
        <iframe style="position:absolute;inset:0;width:100%;height:100%;border:0;border-radius:10px"
                src="https://www.youtube-nocookie.com/embed/<?= e($ytId) ?>?rel=0"
                title="Event video" loading="lazy" allowfullscreen
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
      </div>
    </div>
  <?php endif; ?>
</div>
