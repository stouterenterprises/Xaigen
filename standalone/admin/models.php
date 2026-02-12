<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?: uuidv4();
    $exists = db()->prepare('SELECT id FROM models WHERE id=?');
    $exists->execute([$id]);
    $data = [
        $_POST['type'],
        $_POST['model_key'],
        $_POST['display_name'],
        (int)!empty($_POST['supports_negative_prompt']),
        (int)!empty($_POST['is_active']),
        now_utc(),
        $id,
    ];
    if ($exists->fetch()) {
        db()->prepare('UPDATE models SET type=?, model_key=?, display_name=?, supports_negative_prompt=?, is_active=?, updated_at=? WHERE id=?')->execute($data);
    } else {
        db()->prepare('INSERT INTO models (type,model_key,display_name,supports_negative_prompt,is_active,created_at,updated_at,id) VALUES (?,?,?,?,?,?,?,?)')->execute([
            $_POST['type'],
            $_POST['model_key'],
            $_POST['display_name'],
            (int)!empty($_POST['supports_negative_prompt']),
            (int)!empty($_POST['is_active']),
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
    <p><a href="/admin/keys.php">Keys</a> | <a href="/admin/settings.php">Settings</a> | <a href="/admin/migrations.php">Migrations</a></p>
    <div class="card"><form method="post"><input name="id" placeholder="Leave blank for new"><input name="type" placeholder="image/video" required><input name="model_key" required><input name="display_name" required><label><input type="checkbox" name="supports_negative_prompt" checked> Supports negative prompt</label><label><input type="checkbox" name="is_active" checked> Active</label><button type="submit">Save</button></form></div>
    <?php foreach ($rows as $r): ?><div class="card"><?=htmlspecialchars($r['type'])?> - <?=htmlspecialchars($r['display_name'])?> (<?=htmlspecialchars($r['model_key'])?>)</div><?php endforeach; ?>
  </div>

  <script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script>
</body>
</html>
