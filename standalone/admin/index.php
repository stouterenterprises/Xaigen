<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_installation();
start_session();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (admin_login((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''))) {
        header('Location: /admin/users.php');
        exit;
    }
    $msg = 'Invalid credentials';
}

if (!empty($_SESSION['admin_user_id'])) {
    header('Location: /admin/users.php');
    exit;
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
  <title>Admin Login</title>
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
    <div class="card">
      <h1>Admin Login</h1>
      <?php if ($msg): ?><p class="banner"><?=htmlspecialchars($msg)?></p><?php endif; ?>
      <form method="post">
        <div class="row"><input name="username" placeholder="Username" required></div>
        <div class="row"><input type="password" name="password" placeholder="Password" required></div>
        <button type="submit">Login</button>
      </form>
      <p><a href="/app/create.php">Back to app</a> | <a href="/app/login.php">User login / create user request</a></p>
    </div>
  </div>

  <script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script>
</body>
</html>
