<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/migrations.php';
require_once __DIR__ . '/../lib/studio_entities.php';
require_installation();
start_session();
if ((bool) cfg('AUTO_MIGRATE', true)) { migrate_if_needed(); }
$currentUser = ensure_active_user_for_pages();
$isAdminSession = !empty($currentUser['is_admin']);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if ($isAdminSession) throw new InvalidArgumentException('Switch to a user account to create parts. Admin sessions can browse this page without re-login.');
    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    if ($name === '') throw new InvalidArgumentException('Name is required.');

    $files = array_values(array_filter(extract_files_array('assets'), fn($f) => ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE));
    if (!$files) throw new InvalidArgumentException('Upload at least one part asset.');
    if (count($files) > 40) throw new InvalidArgumentException('A part supports up to 40 assets.');

    $partId = uuidv4();
    db()->prepare('INSERT INTO parts (id,user_id,name,description,is_public,created_at,updated_at) VALUES (?,?,?,?,?,?,?)')
      ->execute([$partId,$currentUser['id'],$name,$description,visibility_bool_from_post(),now_utc(),now_utc()]);

    foreach ($files as $file) {
      $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
      $isVideo = in_array($ext, ['mp4','mov','webm'], true);
      $path = store_uploaded_media($file, 'parts', ['jpg','jpeg','png','webp','mp4','mov','webm']);
      if ($path) {
        db()->prepare('INSERT INTO part_media (id,part_id,media_path,media_type,created_at) VALUES (?,?,?,?,?)')
          ->execute([uuidv4(),$partId,$path,$isVideo ? 'video' : 'image',now_utc()]);
      }
    }

    $success = 'Part created.';
  } catch (Throwable $e) {
    $error = $e->getMessage();
  }
}

$items = [];
try {
$where = $isAdminSession ? '1=1' : 'p.user_id = ? OR p.is_public = 1';
$stmt = db()->prepare("SELECT p.*, (SELECT pm.media_path FROM part_media pm WHERE pm.part_id = p.id ORDER BY pm.created_at DESC, pm.id DESC LIMIT 1) AS thumbnail_path, (SELECT pm.media_type FROM part_media pm WHERE pm.part_id = p.id ORDER BY pm.created_at DESC, pm.id DESC LIMIT 1) AS thumbnail_type FROM parts p WHERE {$where} ORDER BY p.created_at DESC");
if ($isAdminSession) { $stmt->execute(); } else { $stmt->execute([$currentUser['id']]); }
$items = $stmt->fetchAll();
} catch (Throwable $e) {
  $error = $e->getMessage();
}

$styleVersion = @filemtime(__DIR__ . '/assets/css/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/assets/js/app.js') ?: time();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Parts</title><link rel="stylesheet" href="/app/assets/css/style.css?v=<?=urlencode((string)$styleVersion)?>"></head><body>
<nav class="site-nav"><div class="container nav-inner"><a class="brand" href="/">Xaigen</a><button class="menu-toggle" aria-expanded="false" aria-controls="nav-links">Menu</button><div id="nav-links" class="nav-links"><a href="/app/create.php">Generator</a><a href="/app/customize.php">Customize</a><a href="/app/gallery.php">Gallery</a><?php if($isAdminSession): ?><a href="/admin/index.php">Admin</a><a href="/admin/logout.php">Logout (Admin)</a><?php else: ?><a href="/app/logout.php">Logout (<?=htmlspecialchars((string)$currentUser['username'])?>)</a><?php endif; ?></div></div></nav>
<div class="container"><h1>Parts</h1><?php if($error): ?><div class="banner"><?=htmlspecialchars($error)?></div><?php endif; ?><?php if($success): ?><div class="banner banner-success"><?=htmlspecialchars($success)?></div><?php endif; ?>
<div class="grid"><div class="card"><h3>Create Part Variation</h3><form method="post" enctype="multipart/form-data">
<div class="row"><label>Name</label><input name="name" required></div>
<div class="row"><label>Description</label><textarea name="description"></textarea></div>
<div class="row"><label>Assets (up to 40 images/videos)</label><input type="file" name="assets[]" multiple required></div>
<div class="row"><label><input type="checkbox" name="is_public" value="1"> Public</label></div>
<button class="form-btn" type="submit">Save Part</button></form></div>
<div class="card"><h3>Available Parts</h3><div class="gallery-list"><?php foreach($items as $item): ?><article class="gallery-item card"><div class="gallery-preview"><?php if(!empty($item['thumbnail_path'])): ?><?php if(($item['thumbnail_type'] ?? 'image') === 'video'): ?><video src="<?=htmlspecialchars((string)$item['thumbnail_path'])?>" muted playsinline preload="metadata"></video><?php else: ?><img src="<?=htmlspecialchars((string)$item['thumbnail_path'])?>" alt="Part thumbnail"><?php endif; ?><?php endif; ?></div><div class="gallery-content"><strong><?=htmlspecialchars((string)$item['name'])?></strong><small class="muted"><?=!empty($item['is_public']) ? 'Public' : 'Private'?></small><span><?=htmlspecialchars((string)($item['description'] ?? ''))?></span></div></article><?php endforeach; ?></div></div></div></div>
<script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script></body></html>
