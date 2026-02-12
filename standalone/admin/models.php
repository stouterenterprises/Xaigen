<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = trim((string) ($_POST['id'] ?? ''));
    if ($id === '') {
        $id = uuidv4();
    }

    $exists = db()->prepare('SELECT id FROM models WHERE id=?');
    $exists->execute([$id]);

    $type = (string) ($_POST['type'] ?? 'image');
    $modelKey = trim((string) ($_POST['model_key'] ?? ''));
    $displayName = trim((string) ($_POST['display_name'] ?? ''));
    $customPrompt = trim((string) ($_POST['custom_prompt'] ?? ''));
    $customNegativePrompt = trim((string) ($_POST['custom_negative_prompt'] ?? ''));
    $supportsNegativePrompt = (int) !empty($_POST['supports_negative_prompt']);
    $isActive = (int) !empty($_POST['is_active']);

    if ($exists->fetch()) {
        db()->prepare('UPDATE models SET type=?, model_key=?, display_name=?, custom_prompt=?, custom_negative_prompt=?, supports_negative_prompt=?, is_active=?, updated_at=? WHERE id=?')->execute([
            $type,
            $modelKey,
            $displayName,
            $customPrompt,
            $customNegativePrompt,
            $supportsNegativePrompt,
            $isActive,
            now_utc(),
            $id,
        ]);
    } else {
        db()->prepare('INSERT INTO models (type,model_key,display_name,custom_prompt,custom_negative_prompt,supports_negative_prompt,is_active,created_at,updated_at,id) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([
            $type,
            $modelKey,
            $displayName,
            $customPrompt,
            $customNegativePrompt,
            $supportsNegativePrompt,
            $isActive,
            now_utc(),
            now_utc(),
            $id,
        ]);
    }

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
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/app/assets/css/style.css?v=<?=urlencode((string)$styleVersion)?>">
  <title>Models</title>
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
    <h1>Models</h1>
    <p><a href="/admin/settings.php">Settings</a> | <a href="/admin/keys.php">API Keys</a> | <a href="/admin/models.php">Models</a> | <a href="/admin/migrations.php">Migrations</a></p>

    <div class="card">
      <h3>Add model</h3>
      <form method="post" class="admin-model-form">
        <div class="row"><label>Type</label><select name="type" required><option value="image">Image</option><option value="video">Video</option></select></div>
        <div class="row"><label>Model key</label><input name="model_key" required></div>
        <div class="row"><label>Display name</label><input name="display_name" required></div>
        <div class="row"><label>Custom prompt (always prepended)</label><textarea name="custom_prompt"></textarea></div>
        <div class="row"><label>Custom negative prompt (always appended)</label><textarea name="custom_negative_prompt"></textarea></div>
        <label><input type="checkbox" name="supports_negative_prompt" checked> Supports negative prompt</label>
        <label><input type="checkbox" name="is_active" checked> Active</label>
        <button class="form-btn" type="submit">Save</button>
      </form>
    </div>

    <?php foreach ($rows as $r): ?>
      <a class="card model-link-card" href="/admin/model_edit.php?id=<?=urlencode((string)$r['id'])?>">
        <strong><?=htmlspecialchars($r['display_name'])?></strong>
        <span class="muted"><?=htmlspecialchars($r['model_key'])?> • <?=htmlspecialchars($r['type'])?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script>
</body>
</html>
