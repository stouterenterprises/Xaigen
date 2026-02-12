<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_admin();

$id = trim((string) ($_GET['id'] ?? $_POST['id'] ?? ''));
if ($id === '') {
    http_response_code(404);
    exit('Model not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    db()->prepare('UPDATE models SET type=?, model_key=?, display_name=?, custom_prompt=?, custom_negative_prompt=?, default_seed=?, default_aspect_ratio=?, default_resolution=?, default_duration_seconds=?, default_fps=?, supports_negative_prompt=?, is_active=?, updated_at=? WHERE id=?')->execute([
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
        $id,
    ]);

    header('Location: /admin/model_edit.php?id=' . rawurlencode($id) . '&saved=1');
    exit;
}

$stmt = db()->prepare('SELECT * FROM models WHERE id=? LIMIT 1');
$stmt->execute([$id]);
$model = $stmt->fetch();
if (!$model) {
    http_response_code(404);
    exit('Model not found.');
}

$styleVersion = @filemtime(__DIR__ . '/../app/assets/css/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/../app/assets/js/app.js') ?: time();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/app/assets/css/style.css?v=<?=urlencode((string)$styleVersion)?>">
  <title>Edit Model</title>
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
  <h1>Edit model</h1>
  <p><a href="/admin/models.php">← Back to models</a> | <a href="/admin/users.php">Users</a></p>
  <?php if (!empty($_GET['saved'])): ?><div class="banner">Saved.</div><?php endif; ?>

  <div class="card">
    <form method="post">
      <input type="hidden" name="id" value="<?=htmlspecialchars((string)$model['id'])?>">
      <div class="row"><label>Type</label><select name="type" required><option value="image" <?=$model['type']==='image'?'selected':''?>>Image</option><option value="video" <?=$model['type']==='video'?'selected':''?>>Video</option></select></div>
      <div class="row"><label>Model key</label><input name="model_key" value="<?=htmlspecialchars((string)$model['model_key'])?>" required></div>
      <div class="row"><label>Display name</label><input name="display_name" value="<?=htmlspecialchars((string)$model['display_name'])?>" required></div>
      <div class="row"><label>Custom prompt (always prepended)</label><textarea name="custom_prompt"><?=htmlspecialchars((string)($model['custom_prompt'] ?? ''))?></textarea></div>
      <div class="row"><label>Custom negative prompt (always appended)</label><textarea name="custom_negative_prompt"><?=htmlspecialchars((string)($model['custom_negative_prompt'] ?? ''))?></textarea></div>
      <div class="row"><label>Default seed</label><input name="default_seed" value="<?=htmlspecialchars((string)($model['default_seed'] ?? ''))?>"></div>
      <div class="row"><label>Default aspect ratio</label><input name="default_aspect_ratio" value="<?=htmlspecialchars((string)($model['default_aspect_ratio'] ?? ''))?>"></div>
      <div class="row"><label>Default resolution</label><input name="default_resolution" value="<?=htmlspecialchars((string)($model['default_resolution'] ?? ''))?>"></div>
      <div class="row"><label>Default duration seconds</label><input name="default_duration_seconds" value="<?=htmlspecialchars((string)($model['default_duration_seconds'] ?? ''))?>"></div>
      <div class="row"><label>Default fps</label><input name="default_fps" value="<?=htmlspecialchars((string)($model['default_fps'] ?? ''))?>"></div>
      <label><input type="checkbox" name="supports_negative_prompt" <?=!empty($model['supports_negative_prompt'])?'checked':''?>> Supports negative prompt</label>
      <label><input type="checkbox" name="is_active" <?=!empty($model['is_active'])?'checked':''?>> Active</label>
      <button class="form-btn" type="submit">Save changes</button>
    </form>
  </div>
</div>
<script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script>
</body>
</html>
