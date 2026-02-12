<?php
?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Xaigen - AI Image & Video Generator</title>
  <link rel="stylesheet" href="/app/assets/css/style.css">
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
        <h3>Why creators pick Xaigen</h3>
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

  <script src="/app/assets/js/app.js"></script>
</body>
</html>
