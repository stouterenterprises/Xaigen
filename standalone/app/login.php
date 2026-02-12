<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_installation();
start_session();

$message = '';
$messageType = '';
$view = (string) ($_GET['view'] ?? 'login');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'login');
    if ($action === 'register') {
        $view = 'register';
        $result = register_user_request(
            (string) ($_POST['full_name'] ?? ''),
            (string) ($_POST['username'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['purpose'] ?? ''),
            (string) ($_POST['password'] ?? '')
        );

        if (!empty($result['ok'])) {
            $message = 'Allow 24-48 hours for review and try to login. We will attempt to notify you when your account is ready.';
            $messageType = 'success';
        } else {
            $message = (string) ($result['error'] ?? 'Could not submit request.');
            $messageType = 'error';
        }
    } else {
        $identifier = (string) ($_POST['username_or_email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if (admin_login($identifier, $password)) {
            header('Location: /admin/users.php');
            exit;
        }

        $result = user_login($identifier, $password);
        if (!empty($result['ok'])) {
            header('Location: /app/create.php');
            exit;
        }
        $message = (string) ($result['error'] ?? 'Login failed.');
        $messageType = 'error';
    }
}

$styleVersion = @filemtime(__DIR__ . '/assets/css/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/assets/js/app.js') ?: time();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login</title>
  <link rel="stylesheet" href="/app/assets/css/style.css?v=<?=urlencode((string)$styleVersion)?>">
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
        <a href="/app/login.php">Login</a>
        <a href="/admin/index.php">Admin</a>
      </div>
    </div>
  </nav>

  <div class="container">
    <h1>Login</h1>
    <?php if ($message): ?>
      <div class="banner <?=$messageType === 'success' ? 'banner-success' : ''?>"><?=htmlspecialchars($message)?></div>
    <?php endif; ?>
    <div class="card">
      <?php if ($view === 'register'): ?>
        <h3>Create User Request</h3>
        <form method="post" action="/app/login.php?view=register">
          <input type="hidden" name="action" value="register">
          <div class="row"><label>Name</label><input name="full_name" required></div>
          <div class="row"><label>Username</label><input name="username" required></div>
          <div class="row"><label>Email</label><input type="email" name="email" required></div>
          <div class="row"><label>Purpose</label><textarea name="purpose" required></textarea></div>
          <div class="row"><label>Password</label><input type="password" name="password" minlength="8" required></div>
          <button class="form-btn" type="submit">Request Account</button>
        </form>
        <p><a href="/app/login.php">Back to Login</a></p>
      <?php else: ?>
        <h3>User + Admin Login</h3>
        <form method="post">
          <input type="hidden" name="action" value="login">
          <div class="row"><label>Username or Email</label><input name="username_or_email" required></div>
          <div class="row"><label>Password</label><input type="password" name="password" required></div>
          <button class="form-btn" type="submit">Login</button>
        </form>
        <p><a href="/app/login.php?view=register">Create Account</a></p>
      <?php endif; ?>
    </div>
  </div>

  <script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script>
</body>
</html>
