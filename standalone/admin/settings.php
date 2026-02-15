<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/app_settings.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    save_generation_defaults($_POST);
}
$defaults = get_generation_defaults();
$styleVersion = @filemtime(__DIR__ . '/../app/assets/css/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/../app/assets/js/app.js') ?: time();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"><link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/app/assets/css/style.css?v=<?=urlencode((string)$styleVersion)?>">
  <title>Settings</title>
</head>
<body>
  <nav class="site-nav">
    <div class="container nav-inner">
      <a class="brand" href="/"><img class="brand-logo" src="/app/assets/img/logo-glow.svg" alt="" aria-hidden="true"><span>GetYourPics.com</span></a>
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
    <p><a href="/admin/settings.php">Settings</a> | <a href="/admin/keys.php">API Keys</a> | <a href="/admin/models.php">Models</a> | <a href="/admin/users.php">Users</a> | <a href="/admin/migrations.php">Migrations</a></p>
    <div class="card">
      <form method="post">
        <div class="row"><label>Custom prompt (always prepended)</label><textarea name="custom_prompt"><?=htmlspecialchars((string)$defaults['custom_prompt'])?></textarea></div>
        <div class="row"><label>Custom negative prompt (always appended)</label><textarea name="custom_negative_prompt"><?=htmlspecialchars((string)$defaults['custom_negative_prompt'])?></textarea></div>
        <div class="row"><label>Default Seed</label><input name="seed" value="<?=htmlspecialchars((string)$defaults['seed'])?>"></div>
        <div class="row"><label>Default Aspect Ratio</label><input name="aspect_ratio" value="<?=htmlspecialchars((string)$defaults['aspect_ratio'])?>"></div>
        <div class="row"><label>Default Resolution</label><input name="resolution" value="<?=htmlspecialchars((string)$defaults['resolution'])?>"></div>
        <div class="row"><label>Default Video Duration (seconds)</label><input name="duration_seconds" value="<?=htmlspecialchars((string)$defaults['duration_seconds'])?>"></div>
        <div class="row"><label>Default FPS</label><input name="fps" value="<?=htmlspecialchars((string)$defaults['fps'])?>"></div>
        <button class="form-btn" type="submit">Save</button>
      </form>
    </div>
  </div>

  <script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script>
</body>
</html>
