<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_installation();
start_session();

$currentUser = current_user();
$isAdmin = !empty($_SESSION['admin_user_id']);
$styleVersion = @filemtime(__DIR__ . '/assets/css/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/assets/js/app.js') ?: time();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"><link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Customize</title>
  <link rel="stylesheet" href="/app/assets/css/style.css?v=<?=urlencode((string)$styleVersion)?>">
</head>
<body>
<nav class="site-nav"><div class="container nav-inner"><a class="brand" href="/"><img class="brand-logo" src="/app/assets/img/logo-glow.svg" alt="" aria-hidden="true"><span>GetYourPics.com</span></a><button class="menu-toggle" aria-expanded="false" aria-controls="nav-links">Menu</button><div id="nav-links" class="nav-links"><a href="/">Home</a><a href="/app/create.php">Generator</a><a href="/app/customize.php">Customize</a><a href="/app/gallery.php">Gallery</a><?php if($currentUser): ?><a href="/app/logout.php">Logout (<?=htmlspecialchars((string)$currentUser['username'])?>)</a><?php elseif($isAdmin): ?><a href="/admin/index.php">Admin</a><a href="/admin/logout.php">Logout (Admin)</a><?php else: ?><a href="/app/login.php">Login</a><?php endif; ?></div></div></nav>
<div class="container">
  <h1>Customize</h1>
  <p><a href="/app/characters.php">Characters</a> | <a href="/app/parts.php">Parts</a> | <a href="/app/scenes.php">Scenes</a></p>
  <div class="card">
    <p class="muted">Manage your reusable character, part, and scene libraries here.</p>
  </div>
</div>
<script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script>
</body>
</html>
