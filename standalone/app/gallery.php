<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/migrations.php';
require_installation();
start_session();
$currentUser = null;
$isLoggedIn = false;
$items = [];
$pageError = '';

try {
  if ((bool) cfg('AUTO_MIGRATE', true)) { migrate_if_needed(); }
  $currentUser = current_user();
  $isAdmin = !empty($_SESSION['admin_user_id']);
  $isLoggedIn = $isAdmin || $currentUser;

  if ($isAdmin && !$currentUser) {
    $items = db()->query("SELECT g.*, u.username FROM generations g LEFT JOIN users u ON g.user_id=u.id ORDER BY g.created_at DESC LIMIT 50")->fetchAll();
  } elseif ($currentUser) {
    $stmt = db()->prepare('SELECT g.*, u.username FROM generations g LEFT JOIN users u ON g.user_id=u.id WHERE g.user_id = ? ORDER BY g.created_at DESC LIMIT 50');
    $stmt->execute([$currentUser['id']]);
    $items = $stmt->fetchAll();
  } else {
    $items = db()->query("SELECT g.*, u.username FROM generations g LEFT JOIN users u ON g.user_id=u.id WHERE g.is_public = 1 AND g.status = 'succeeded' ORDER BY g.created_at DESC LIMIT 50")->fetchAll();
  }
} catch (Throwable $e) {
  $pageError = $e->getMessage();
}

$styleVersion = @filemtime(__DIR__ . '/assets/css/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/assets/js/app.js') ?: time();

function status_label(string $status): string { $s=strtolower(trim($status)); return $s==='queued'||$s==='running'?'Generating':($s==='succeeded'?'Generated':($s==='failed'?'Failed':($s===''?'Unknown':ucfirst($s)))); }
?>
<!doctype html><html><head><meta charset="utf-8"><link rel="icon" type="image/svg+xml" href="/favicon.svg"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Gallery</title><link rel="stylesheet" href="/app/assets/css/style.css?v=<?=urlencode((string)$styleVersion)?>"></head><body>
<nav class="site-nav"><div class="container nav-inner"><a class="brand" href="/"><img class="brand-logo" src="/app/assets/img/logo-glow.svg" alt="" aria-hidden="true"><span>GetYourPics.com</span></a><button class="menu-toggle" aria-expanded="false" aria-controls="nav-links">Menu</button><div id="nav-links" class="nav-links"><a href="/">Home</a><a href="/app/create.php">Generator</a><a href="/app/gallery.php">Gallery</a><a href="/app/customize.php">Customize</a><?php if($currentUser): ?><a href="/app/logout.php">Logout (<?=htmlspecialchars((string)$currentUser['username'])?>)</a><?php elseif(!empty($_SESSION['admin_user_id'])): ?><a href="/admin/index.php">Admin</a><a href="/admin/logout.php">Logout (Admin)</a><?php else: ?><a href="/app/login.php">Login</a><?php endif; ?></div></div></nav>
<div class="container"><h1><?=$isAdmin ? 'Admin Gallery' : ($isLoggedIn ? 'My Gallery' : 'Public Gallery')?></h1><?php if($pageError): ?><div class="banner">Unable to load gallery: <?=htmlspecialchars($pageError)?></div><?php endif; ?><div class="gallery-list"><?php foreach($items as $g): $isSucceeded=strtolower((string)$g['status'])==='succeeded'; $hasOutput=!empty($g['output_path']); $mediaViewHref='/app/media.php?id='.urlencode((string)$g['id']); $statusClass='status-'.preg_replace('/[^a-z0-9_-]/','',strtolower((string)($g['status'] ?? 'unknown'))); ?>
<article class="gallery-item card"><?php if($hasOutput): ?><a class="gallery-preview" href="<?=htmlspecialchars($mediaViewHref)?>"><?php if($g['type']==='video'): ?><video src="<?=htmlspecialchars((string)$g['output_path'])?>" muted playsinline preload="metadata"></video><?php else: ?><img src="<?=htmlspecialchars((string)$g['output_path'])?>" alt="Generated output preview"><?php endif; ?></a><?php else: ?><div class="gallery-preview gallery-preview-empty"><span>No preview</span></div><?php endif; ?>
<div class="gallery-content"><?php if($hasOutput): ?><a class="gallery-main-link" href="<?=htmlspecialchars($mediaViewHref)?>"><?php endif; ?><strong><?=htmlspecialchars((string)$g['type'])?> • <?=htmlspecialchars((string)$g['model_key'])?></strong><small class="status-pill <?=htmlspecialchars($statusClass)?>"><?=htmlspecialchars(status_label((string)($g['status'] ?? '')))?></small><span><?=htmlspecialchars((string)$g['prompt'])?></span><?php if(!$isLoggedIn): ?><small class="muted">By @<?=htmlspecialchars((string)($g['username'] ?? 'unknown'))?></small><?php endif; ?><?php if($hasOutput): ?></a><?php endif; ?>
<div class="gallery-actions"><?php if($hasOutput && $isSucceeded): ?><a class="btn btn-secondary" href="/api/download.php?id=<?=urlencode((string)$g['id'])?>">Download</a><?php endif; ?><?php if($isLoggedIn): ?><button class="btn js-toggle-visibility" data-id="<?=htmlspecialchars((string)$g['id'])?>" data-public="<?=!empty($g['is_public']) ? '1':'0'?>" type="button"><?=!empty($g['is_public']) ? '🔗 Public' : '🔒 Private'?></button><button class="btn btn-danger js-delete-generation" data-id="<?=htmlspecialchars((string)$g['id'])?>" type="button">Delete</button><?php endif; ?></div>
</div></article><?php endforeach; ?></div></div>
<script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script></body></html>
