<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_admin();

$errorMessage = '';
$rows = [];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (string) ($_POST['id'] ?? '');
        $action = (string) ($_POST['action'] ?? '');
        if ($id !== '' && in_array($action, ['approve', 'reject'], true)) {
            $status = $action === 'approve' ? 'active' : 'rejected';
            db()->prepare('UPDATE users SET status=?, reviewed_by_admin_id=?, reviewed_at=?, updated_at=? WHERE id=?')->execute([
                $status,
                $_SESSION['admin_user_id'] ?? null,
                now_utc(),
                now_utc(),
                $id,
            ]);
        }
        header('Location: /admin/users.php');
        exit;
    }

    $rows = db()->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();
} catch (Throwable $e) {
    $errorMessage = 'Could not load users admin page: ' . $e->getMessage();
}
$styleVersion = @filemtime(__DIR__ . '/../app/assets/css/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/../app/assets/js/app.js') ?: time();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"><link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/app/assets/css/style.css?v=<?=urlencode((string)$styleVersion)?>">
  <title>Users</title>
</head>
<body>
<nav class="site-nav"><div class="container nav-inner"><a class="brand" href="/"><img class="brand-logo" src="/app/assets/img/logo-glow.svg" alt="" aria-hidden="true"><span>GetYourPics.com</span></a><button class="menu-toggle" aria-expanded="false" aria-controls="nav-links">Menu</button><div id="nav-links" class="nav-links"><a href="/">Home</a><a href="/app/create.php">Generator</a><a href="/app/gallery.php">Gallery</a><a href="/admin/index.php">Admin</a></div></div></nav>
<div class="container">
<h1>Users</h1>
<p><a href="/admin/settings.php">Settings</a> | <a href="/admin/keys.php">API Keys</a> | <a href="/admin/models.php">Models</a> | <a href="/admin/users.php">Users</a> | <a href="/admin/migrations.php">Migrations</a></p>
<?php if ($errorMessage): ?><div class="banner"><?=htmlspecialchars($errorMessage)?></div><?php endif; ?>
<?php foreach($rows as $u): ?>
<div class="card">
  <strong><?=htmlspecialchars((string)$u['full_name'])?> (<?=htmlspecialchars((string)$u['username'])?>)</strong>
  <p class="muted"><?=htmlspecialchars((string)$u['email'])?></p>
  <p><?=htmlspecialchars((string)$u['purpose'])?></p>
  <small class="status-pill status-<?=htmlspecialchars((string)$u['status'])?>"><?=htmlspecialchars((string)$u['status'])?></small>
  <?php if (($u['status'] ?? '') === 'pending'): ?>
  <div class="gallery-actions">
    <form method="post"><input type="hidden" name="id" value="<?=htmlspecialchars((string)$u['id'])?>"><input type="hidden" name="action" value="approve"><button class="btn" type="submit">Approve</button></form>
    <form method="post"><input type="hidden" name="id" value="<?=htmlspecialchars((string)$u['id'])?>"><input type="hidden" name="action" value="reject"><button class="btn btn-danger" type="submit">Reject</button></form>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script>
</body>
</html>
