<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_installation();
$items = db()->query("SELECT * FROM generations WHERE status='succeeded' ORDER BY created_at DESC LIMIT 50")->fetchAll();
$styleVersion = @filemtime(__DIR__ . '/assets/css/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/assets/js/app.js') ?: time();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Gallery</title>
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
    <h1>Gallery</h1>
    <div class="gallery-list">
      <?php foreach($items as $g): ?>
        <?php $hasOutput = !empty($g['output_path']); ?>
        <article class="gallery-item card">
          <?php if ($hasOutput): ?>
            <a class="gallery-preview" href="<?=htmlspecialchars($g['output_path'])?>" target="_blank" rel="noopener">
              <?php if ($g['type'] === 'video'): ?>
                <video src="<?=htmlspecialchars($g['output_path'])?>" muted playsinline preload="metadata"></video>
              <?php else: ?>
                <img src="<?=htmlspecialchars($g['output_path'])?>" alt="Generated output preview">
              <?php endif; ?>
            </a>
          <?php else: ?>
            <div class="gallery-preview gallery-preview-empty"><span>No preview</span></div>
          <?php endif; ?>

          <div class="gallery-content">
            <?php if ($hasOutput): ?>
              <a class="gallery-main-link" href="<?=htmlspecialchars($g['output_path'])?>" target="_blank" rel="noopener">
                <strong><?=htmlspecialchars($g['type'])?> • <?=htmlspecialchars($g['model_key'])?></strong>
                <span><?=htmlspecialchars($g['prompt'])?></span>
              </a>
            <?php else: ?>
              <strong><?=htmlspecialchars($g['type'])?> • <?=htmlspecialchars($g['model_key'])?></strong>
              <span><?=htmlspecialchars($g['prompt'])?></span>
            <?php endif; ?>

            <div class="gallery-actions">
              <?php if($hasOutput): ?>
                <a class="btn btn-secondary" href="/api/download.php?id=<?=urlencode($g['id'])?>">Download</a>
              <?php endif; ?>
              <button class="btn btn-danger js-delete-generation" data-id="<?=htmlspecialchars($g['id'])?>" type="button">Delete</button>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
  <script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script>
</body>
</html>
