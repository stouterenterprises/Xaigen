<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/app_settings.php';
require_admin();

$defaults = get_generation_defaults();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = uuidv4();

    db()->prepare('INSERT INTO models (type,model_key,display_name,custom_prompt,custom_negative_prompt,default_seed,default_aspect_ratio,default_resolution,default_duration_seconds,default_fps,supports_negative_prompt,is_active,created_at,updated_at,id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
        (string) ($_POST['type'] ?? 'image'),
        trim((string) ($_POST['model_key'] ?? '')),
        trim((string) ($_POST['display_name'] ?? '')),
        trim((string) ($_POST['custom_prompt'] ?? '')),
        trim((string) ($_POST['custom_negative_prompt'] ?? '')),
        ($_POST['default_seed'] ?? '') === '' ? null : (int) $_POST['default_seed'],
        trim((string) ($_POST['default_aspect_ratio'] ?? '')),
        trim((string) ($_POST['default_resolution'] ?? '')),
        ($_POST['default_duration_seconds'] ?? '') === '' ? null : (float) $_POST['default_duration_seconds'],
        ($_POST['default_fps'] ?? '') === '' ? null : (int) $_POST['default_fps'],
        (int) !empty($_POST['supports_negative_prompt']),
        (int) !empty($_POST['is_active']),
        now_utc(),
        now_utc(),
        $id,
    ]);

    header('Location: /admin/models.php');
    exit;
}

$rows = db()->query('SELECT * FROM models ORDER BY type, display_name')->fetchAll();
$styleVersion = @filemtime(__DIR__ . '/../app/assets/css/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/../app/assets/js/app.js') ?: time();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"><link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/app/assets/css/style.css?v=<?=urlencode((string)$styleVersion)?>">
  <title>Models</title>
</head>
<body>
  <nav class="site-nav"><div class="container nav-inner"><a class="brand" href="/"><img class="brand-logo" src="/app/assets/img/logo-glow.svg" alt="" aria-hidden="true"><span>GetYourPics.com</span></a><button class="menu-toggle" aria-expanded="false" aria-controls="nav-links">Menu</button><div id="nav-links" class="nav-links"><a href="/">Home</a><a href="/app/create.php">Generator</a><a href="/app/gallery.php">Gallery</a><a href="/admin/index.php">Admin</a></div></div></nav>

  <div class="container">
    <h1>Models</h1>
    <p><a href="/admin/settings.php">Settings</a> | <a href="/admin/keys.php">API Keys</a> | <a href="/admin/models.php">Models</a> | <a href="/admin/users.php">Users</a> | <a href="/admin/migrations.php">Migrations</a></p>

    <button class="form-btn" type="button" id="openAddModel">New Model</button>

    <dialog id="addModelDialog">
      <form method="post" class="admin-model-form">
        <h3>Add model</h3>
        <div class="row"><label>Type</label><select name="type" required><option value="image">Image</option><option value="video">Video</option></select></div>
        <div class="row"><label>Model key</label><input name="model_key" required></div>
        <div class="row"><label>Display name</label><input name="display_name" required></div>
        <div class="row"><label>Custom prompt</label><textarea name="custom_prompt"></textarea></div>
        <div class="row"><label>Custom negative prompt</label><textarea name="custom_negative_prompt"></textarea></div>
        <div class="row"><label>Default seed</label><input name="default_seed" value="<?=htmlspecialchars((string)$defaults['seed'])?>"></div>
        <div class="row"><label>Default aspect ratio</label><input name="default_aspect_ratio" value="<?=htmlspecialchars((string)$defaults['aspect_ratio'])?>"></div>
        <div class="row"><label>Default resolution</label><input name="default_resolution" value="<?=htmlspecialchars((string)$defaults['resolution'])?>"></div>
        <div class="row"><label>Default duration seconds</label><input name="default_duration_seconds" value="<?=htmlspecialchars((string)$defaults['duration_seconds'])?>"></div>
        <div class="row"><label>Default fps</label><input name="default_fps" value="<?=htmlspecialchars((string)$defaults['fps'])?>"></div>
        <label><input type="checkbox" name="supports_negative_prompt" checked> Supports negative prompt</label>
        <label><input type="checkbox" name="is_active" checked> Active</label>
        <div class="gallery-actions"><button class="btn" type="button" id="closeAddModel">Cancel</button><button class="form-btn" type="submit">Save</button></div>
      </form>
    </dialog>

    <?php foreach ($rows as $r): ?>
      <a class="card model-link-card" href="/admin/model_edit.php?id=<?=urlencode((string)$r['id'])?>">
        <strong><?=htmlspecialchars((string)$r['display_name'])?></strong>
        <span class="muted"><?=htmlspecialchars((string)$r['model_key'])?> • <?=htmlspecialchars((string)$r['type'])?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script>
  <script>
    const addDialog = document.getElementById('addModelDialog');
    document.getElementById('openAddModel')?.addEventListener('click', ()=>addDialog?.showModal());
    document.getElementById('closeAddModel')?.addEventListener('click', ()=>addDialog?.close());
  </script>
</body>
</html>
