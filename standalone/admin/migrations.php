<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/migrations.php';
require_admin();
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        migrate_if_needed();
        $msg = 'Migrations applied successfully.';
    } catch (Throwable $e) {
        $msg = 'Error: ' . $e->getMessage();
    }
}
$status = migration_status();
$styleVersion = @filemtime(__DIR__ . '/../app/assets/css/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/../app/assets/js/app.js') ?: time();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/app/assets/css/style.css?v=<?=urlencode((string)$styleVersion)?>">
  <title>Migrations</title>
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
    <h1>Migrations</h1>
    <p><a href="/admin/settings.php">Settings</a> | <a href="/admin/keys.php">API Keys</a> | <a href="/admin/models.php">Models</a> | <a href="/admin/migrations.php">Migrations</a></p>
    <?php if ($msg): ?><div class="card"><?=htmlspecialchars($msg)?></div><?php endif; ?>
    <div class="card"><form method="post"><button type="submit">Run Migrations Now</button></form></div>
    <?php foreach ($status as $m): ?><div class="card"><?=htmlspecialchars($m['filename'])?> - <?=htmlspecialchars($m['state'])?></div><?php endforeach; ?>
  </div>

  <script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script>
</body>
</html>
