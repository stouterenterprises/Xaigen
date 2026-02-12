<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/crypto.php';
require_admin();
start_session();
$revealValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = $_POST['id'] ?: uuidv4();
        $exists = db()->prepare('SELECT id FROM api_keys WHERE id=?');
        $exists->execute([$id]);
        $enc = encrypt_secret((string)($_POST['key_value'] ?? ''));
        if ($exists->fetch()) {
            db()->prepare('UPDATE api_keys SET provider=?, key_name=?, key_value_encrypted=?, is_active=?, notes=?, updated_at=? WHERE id=?')->execute([
                $_POST['provider'],
                $_POST['key_name'],
                $enc,
                (int)!empty($_POST['is_active']),
                $_POST['notes'],
                now_utc(),
                $id,
            ]);
        } else {
            db()->prepare('INSERT INTO api_keys (id,provider,key_name,key_value_encrypted,is_active,notes,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)')->execute([
                $id,
                $_POST['provider'],
                $_POST['key_name'],
                $enc,
                (int)!empty($_POST['is_active']),
                $_POST['notes'],
                now_utc(),
                now_utc(),
            ]);
        }
    }
    if ($action === 'delete') {
        db()->prepare('DELETE FROM api_keys WHERE id=?')->execute([$_POST['id']]);
    }
    if ($action === 'reveal') {
        $u = db()->prepare('SELECT password_hash FROM admin_users WHERE id=? LIMIT 1');
        $u->execute([$_SESSION['admin_user_id']]);
        $row = $u->fetch();
        if ($row && password_verify((string)($_POST['admin_password'] ?? ''), $row['password_hash'])) {
            $k = db()->prepare('SELECT key_value_encrypted FROM api_keys WHERE id=?');
            $k->execute([$_POST['id']]);
            $kr = $k->fetch();
            if ($kr) {
                $revealValue = decrypt_secret((string)$kr['key_value_encrypted']);
            }
        }
    }
    if ($action !== 'reveal') {
        header('Location: /admin/keys.php');
        exit;
    }
}
$rows = db()->query('SELECT * FROM api_keys ORDER BY created_at DESC')->fetchAll();
$styleVersion = @filemtime(__DIR__ . '/../app/assets/css/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/../app/assets/js/app.js') ?: time();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/app/assets/css/style.css?v=<?=urlencode((string)$styleVersion)?>">
  <title>Keys</title>
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
    <h1>API Keys</h1>
    <p><a href="/admin/settings.php">Settings</a> | <a href="/admin/keys.php">API Keys</a> | <a href="/admin/models.php">Models</a> | <a href="/admin/migrations.php">Migrations</a></p>
    <?php if ($revealValue !== ''): ?><div class="card">Revealed key value: <code><?=htmlspecialchars($revealValue)?></code></div><?php endif; ?>
    <div class="card"><h3>Add / Edit Key</h3><form method="post"><input type="hidden" name="action" value="save"><div class="row"><input name="id" placeholder="Leave blank for new"></div><div class="row"><input name="provider" value="xai" required></div><div class="row"><input name="key_name" placeholder="XAI_API_KEY" required></div><div class="row"><input type="password" name="key_value" placeholder="Secret value" required></div><div class="row"><label><input type="checkbox" name="is_active" value="1" checked> Active</label></div><div class="row"><textarea name="notes" placeholder="Notes"></textarea></div><button type="submit">Save</button></form></div>
    <?php foreach ($rows as $r): ?><div class="card"><strong><?=htmlspecialchars($r['provider'])?> / <?=htmlspecialchars($r['key_name'])?></strong> <?=((int)$r['is_active']) ? '(active)' : '(disabled)'?><br>Value: ************<br><small><?=htmlspecialchars((string)$r['notes'])?></small><form method="post"><input type="hidden" name="action" value="reveal"><input type="hidden" name="id" value="<?=htmlspecialchars($r['id'])?>"><input type="password" name="admin_password" placeholder="Re-enter admin password" required><button type="submit">Reveal</button></form><form method="post" onsubmit="return confirm('Delete key?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=htmlspecialchars($r['id'])?>"><button type="submit">Delete</button></form></div><?php endforeach; ?>
  </div>

  <script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script>
</body>
</html>
