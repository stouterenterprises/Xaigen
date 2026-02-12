<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = (string)($_POST['defaults_json'] ?? '{}');
    json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    $stmt = db()->prepare("INSERT INTO app_settings (setting_key,setting_value,updated_at) VALUES ('defaults_json',?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=VALUES(updated_at)");
    $stmt->execute([$json, now_utc()]);
}
$row = db()->query("SELECT setting_value FROM app_settings WHERE setting_key='defaults_json'")->fetch();
$val = $row['setting_value'] ?? '{"resolution":"1024x1024"}';
$styleVersion = @filemtime(__DIR__ . '/../app/assets/css/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/../app/assets/js/app.js') ?: time();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/app/assets/css/style.css?v=<?=urlencode((string)$styleVersion)?>">
  <title>Settings</title>
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
    <h1>Settings</h1>
    <p><a href="/admin/settings.php">Settings</a> | <a href="/admin/keys.php">API Keys</a> | <a href="/admin/models.php">Models</a> | <a href="/admin/migrations.php">Migrations</a></p>
    <div class="card"><form method="post"><textarea name="defaults_json" rows="12"><?=htmlspecialchars($val)?></textarea><button type="submit">Save</button></form></div>
  </div>

  <script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script>
</body>
</html>
