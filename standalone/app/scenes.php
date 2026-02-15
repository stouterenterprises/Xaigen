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
    if ($isAdminSession) throw new InvalidArgumentException('Switch to a user account to create scenes. Admin sessions can browse this page without re-login.');
    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $type = ($_POST['type'] ?? 'image') === 'video' ? 'video' : 'image';
    if ($name === '') throw new InvalidArgumentException('Name is required.');

    $files = array_values(array_filter(extract_files_array('assets'), fn($f) => ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE));
    if (!$files) throw new InvalidArgumentException('Upload at least one scene asset.');

    $sceneId = uuidv4();
    db()->prepare('INSERT INTO scenes (id,user_id,name,description,type,is_public,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)')
      ->execute([$sceneId,$currentUser['id'],$name,$description,$type,visibility_bool_from_post(),now_utc(),now_utc()]);

    $allowed = $type === 'image' ? ['jpg','jpeg','png','webp'] : ['mp4','mov','webm'];
    foreach ($files as $file) {
      $path = store_uploaded_media($file, 'scenes', $allowed);
      if ($path) {
        db()->prepare('INSERT INTO scene_media (id,scene_id,media_path,media_type,created_at) VALUES (?,?,?,?,?)')
          ->execute([uuidv4(),$sceneId,$path,$type,now_utc()]);
      }
    }

    $success = 'Scene created.';
  } catch (Throwable $e) {
    $error = $e->getMessage();
  }
}

$where = $isAdminSession ? '1=1' : 's.user_id = ? OR s.is_public = 1';
$stmt = db()->prepare("SELECT s.*, (SELECT sm.media_path FROM scene_media sm WHERE sm.scene_id = s.id ORDER BY sm.created_at DESC, sm.id DESC LIMIT 1) AS thumbnail_path FROM scenes s WHERE {$where} ORDER BY s.created_at DESC");
if ($isAdminSession) { $stmt->execute(); } else { $stmt->execute([$currentUser['id']]); }
$items = $stmt->fetchAll();
$styleVersion = @filemtime(__DIR__ . '/assets/css/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/assets/js/app.js') ?: time();
?>
<!doctype html><html><head><meta charset="utf-8"><link rel="icon" type="image/svg+xml" href="/favicon.svg"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Scenes</title><link rel="stylesheet" href="/app/assets/css/style.css?v=<?=urlencode((string)$styleVersion)?>"></head><body>
<nav class="site-nav"><div class="container nav-inner"><a class="brand" href="/"><img class="brand-logo" src="/app/assets/img/logo-glow.svg" alt="" aria-hidden="true"><span>GetYourPics.com</span></a><button class="menu-toggle" aria-expanded="false" aria-controls="nav-links">Menu</button><div id="nav-links" class="nav-links"><a href="/app/create.php">Generator</a><a href="/app/customize.php">Customize</a><a href="/app/gallery.php">Gallery</a><?php if($isAdminSession): ?><a href="/admin/index.php">Admin</a><a href="/admin/logout.php">Logout (Admin)</a><?php else: ?><a href="/app/logout.php">Logout (<?=htmlspecialchars((string)$currentUser['username'])?>)</a><?php endif; ?></div></div></nav>
<div class="container"><h1>Scenes</h1><?php if($error): ?><div class="banner"><?=htmlspecialchars($error)?></div><?php endif; ?><?php if($success): ?><div class="banner banner-success"><?=htmlspecialchars($success)?></div><?php endif; ?>
<div class="grid"><div class="card"><h3>Create Scene</h3><form method="post" enctype="multipart/form-data">
<div class="row"><label>Name</label><input name="name" required></div>
<div class="row"><label>Type</label><select name="type"><option value="image">Photo Scene (images only)</option><option value="video">Video Scene (videos only)</option></select></div>
<div class="row"><label>Description</label><textarea name="description"></textarea></div>
<div class="row"><label>Assets</label><input type="file" name="assets[]" multiple required></div>
<div class="row"><label><input type="checkbox" name="is_public" value="1"> Public</label></div>
<button class="form-btn" type="submit">Save Scene</button></form></div>
<div class="card"><h3>Available Scenes</h3><div class="gallery-list"><?php foreach($items as $item): ?><article class="gallery-item card"><div class="gallery-preview"><?php if(!empty($item['thumbnail_path'])): ?><?php if(($item['type'] ?? 'image') === 'video'): ?><video src="<?=htmlspecialchars((string)$item['thumbnail_path'])?>" muted playsinline preload="metadata"></video><?php else: ?><img src="<?=htmlspecialchars((string)$item['thumbnail_path'])?>" alt="Scene thumbnail"><?php endif; ?><?php endif; ?></div><div class="gallery-content"><strong><?=htmlspecialchars((string)$item['name'])?></strong><small class="muted"><?=htmlspecialchars((string)$item['type'])?> • <?=!empty($item['is_public']) ? 'Public' : 'Private'?></small><span><?=htmlspecialchars((string)($item['description'] ?? ''))?></span></div></article><?php endforeach; ?></div></div></div></div>
<script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script></body></html>
