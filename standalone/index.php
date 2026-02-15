<?php
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';
start_session();

$styleVersion = @filemtime(__DIR__ . '/app/assets/css/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/app/assets/js/app.js') ?: time();
?><!doctype html>
<html>
<head>
  <meta charset="utf-8"><link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>GetYourPics.com - AI Image & Video Generator</title>
  <link rel="stylesheet" href="/app/assets/css/style.css?v=<?=urlencode((string)$styleVersion)?>">
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
        <?php if(!empty($_SESSION['admin_user_id'])): ?><a href="/admin/index.php">Admin</a><a href="/admin/logout.php">Logout (Admin)</a><?php else: ?><a href="/app/login.php">Login</a><?php endif; ?>
      </div>
    </div>
  </nav>

  <section class="hero">
    <div class="container hero-grid">
      <div>
        <p class="pill">AI content studio</p>
        <h1>Create stunning AI images and videos in seconds.</h1>
        <p class="muted hero-copy">Generate polished, social-ready visual content from simple prompts. Switch between image and video workflows, track status, and download results instantly.</p>
        <div class="hero-actions">
          <a class="btn btn-primary" href="/app/create.php">Start Generating</a>
          <a class="btn btn-secondary" href="/app/gallery.php">View Gallery</a>
        </div>
      </div>
      <div class="card showcase-card">
        <h3>Why creators pick GetYourPics.com</h3>
        <ul class="feature-list">
          <li>Image + video generation in one dashboard</li>
          <li>Flexible prompt + negative prompt controls</li>
          <li>Fast output history and direct downloads</li>
          <li>Mobile-friendly studio and navigation</li>
        </ul>
      </div>
    </div>
  </section>

  <section class="container cards-three">
    <article class="card">
      <h3>1. Describe your idea</h3>
      <p class="muted">Write a prompt with the style, mood, and details you need.</p>
    </article>
    <article class="card">
      <h3>2. Pick a model</h3>
      <p class="muted">Choose image or video models optimized for your workflow.</p>
    </article>
    <article class="card">
      <h3>3. Export and share</h3>
      <p class="muted">Download successful generations and keep your creative momentum.</p>
    </article>
  </section>

  <script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script>
</body>
</html>
