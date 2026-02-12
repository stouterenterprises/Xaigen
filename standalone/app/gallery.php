<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_installation();
$items = db()->query("SELECT * FROM generations WHERE status='succeeded' ORDER BY created_at DESC LIMIT 50")->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Gallery</title>
  <link rel="stylesheet" href="/app/assets/css/style.css">
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
    <?php foreach($items as $g): ?>
      <div class="card"><strong><?=htmlspecialchars($g['model_key'])?></strong><br><?=htmlspecialchars($g['prompt'])?><br><?php if($g['output_path']): ?><a href="/api/download.php?id=<?=urlencode($g['id'])?>">Download output</a><?php endif; ?></div>
    <?php endforeach; ?>
  </div>
  <script src="/app/assets/js/app.js"></script>
</body>
</html>
