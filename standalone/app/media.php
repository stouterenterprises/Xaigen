<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_installation();

$id = (string) ($_GET['id'] ?? '');
$stmt = db()->prepare('SELECT * FROM generations WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
}

$styleVersion = @filemtime(__DIR__ . '/assets/css/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/assets/js/app.js') ?: time();

function status_label(string $status): string {
    $normalized = strtolower(trim($status));
    if ($normalized === 'queued' || $normalized === 'running') {
        return 'Generating';
    }
    if ($normalized === 'succeeded') {
        return 'Generated';
    }
    if ($normalized === 'failed') {
        return 'Failed';
    }
    return $normalized === '' ? 'Unknown' : ucfirst($normalized);
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Media Viewer</title>
  <link rel="stylesheet" href="/app/assets/css/style.css?v=<?=urlencode((string)$styleVersion)?>">
</head>
<body>
  <nav class="site-nav">
    <div class="container nav-inner">
      <a class="brand" href="/">Xaigen</a>
      <button class="menu-toggle" aria-expanded="false" aria-controls="nav-links">Menu</button>
      <div id="nav-links" class="nav-links">
        <a href="/">Home</a>
        <a href="/app/create.php">Generator</a>
        <a href="/app/gallery.php">Gallery</a>
        <a href="/admin/index.php">Admin</a>
      </div>
    </div>
  </nav>

  <div class="container">
    <?php if (!$item): ?>
      <div class="card">
        <h1>Media not found</h1>
        <p class="muted">This generation no longer exists or the link is invalid.</p>
        <a class="btn" href="/app/gallery.php">Back to Gallery</a>
      </div>
    <?php else: ?>
      <?php $statusClass = 'status-' . preg_replace('/[^a-z0-9_-]/', '', strtolower((string)($item['status'] ?? 'unknown'))); ?>
      <article class="card media-viewer-card">
        <div class="media-viewer-head">
          <h1><?=htmlspecialchars($item['type'])?> output</h1>
          <small class="status-pill <?=htmlspecialchars($statusClass)?>"><?=htmlspecialchars(status_label((string)($item['status'] ?? '')))?></small>
        </div>

        <p class="muted media-viewer-prompt"><?=htmlspecialchars((string)($item['prompt'] ?? ''))?></p>

        <?php if (!empty($item['output_path'])): ?>
          <div class="media-viewer-stage">
            <?php if (($item['type'] ?? '') === 'video'): ?>
              <video controls autoplay muted playsinline src="<?=htmlspecialchars((string)$item['output_path'])?>"></video>
            <?php else: ?>
              <img src="<?=htmlspecialchars((string)$item['output_path'])?>" alt="Generated media output">
            <?php endif; ?>
          </div>
          <div class="gallery-actions media-viewer-actions">
            <a class="btn btn-secondary" href="/api/download.php?id=<?=urlencode((string)$item['id'])?>">Download</a>
            <a class="btn" href="/app/gallery.php">Back to Gallery</a>
          </div>
        <?php else: ?>
          <p class="muted">Output is not available yet. Please check again in a moment.</p>
          <a class="btn" href="/app/gallery.php">Back to Gallery</a>
        <?php endif; ?>
      </article>
    <?php endif; ?>
  </div>

  <script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script>
</body>
</html>
