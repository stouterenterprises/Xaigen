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
    if ($isAdminSession) {
      throw new InvalidArgumentException('Switch to a user account to create characters. Admin sessions can browse this page without re-login.');
    }
    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $age = (int)($_POST['age'] ?? 0);
    $gender = trim((string)($_POST['gender'] ?? ''));
    $penisSize = trim((string)($_POST['penis_size'] ?? ''));
    $boobSize = trim((string)($_POST['boob_size'] ?? ''));
    $heightCm = (int)($_POST['height_cm'] ?? 0);
    if ($name === '') throw new InvalidArgumentException('Name is required.');
    if ($age < 20) throw new InvalidArgumentException('Age must be 20 or older.');

    $files = array_values(array_filter(extract_files_array('photos'), fn($f) => ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE));
    if (!$files) throw new InvalidArgumentException('Upload at least 1 character photo.');
    if (count($files) > 20) throw new InvalidArgumentException('A character supports up to 20 photos.');

    $charId = uuidv4();
    db()->prepare('INSERT INTO characters (id,user_id,name,description,age,gender,penis_size,boob_size,height_cm,is_public,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
      ->execute([$charId,$currentUser['id'],$name,$description,$age,$gender ?: null,$penisSize ?: null,$boobSize ?: null,$heightCm ?: null,visibility_bool_from_post(),now_utc(),now_utc()]);

    foreach ($files as $file) {
      $path = store_uploaded_media($file, 'characters', ['jpg','jpeg','png','webp']);
      if ($path) {
        db()->prepare('INSERT INTO character_media (id,character_id,media_path,media_type,created_at) VALUES (?,?,?,?,?)')
          ->execute([uuidv4(),$charId,$path,'image',now_utc()]);
      }
    }

    $success = 'Character created.';
  } catch (Throwable $e) {
    $error = $e->getMessage();
  }
}

$items = [];
try {
$where = $isAdminSession ? '1=1' : 'c.user_id = ? OR c.is_public = 1';
$stmt = db()->prepare("SELECT c.*, (SELECT cm.media_path FROM character_media cm WHERE cm.character_id = c.id ORDER BY cm.created_at DESC, cm.id DESC LIMIT 1) AS thumbnail_path FROM characters c WHERE {$where} ORDER BY c.created_at DESC");
if ($isAdminSession) {
  $stmt->execute();
} else {
  $stmt->execute([$currentUser['id']]);
}
$items = $stmt->fetchAll();
} catch (Throwable $e) {
  $error = $e->getMessage();
}

$styleVersion = @filemtime(__DIR__ . '/assets/css/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/assets/js/app.js') ?: time();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"><link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Characters</title>
  <link rel="stylesheet" href="/app/assets/css/style.css?v=<?=urlencode((string)$styleVersion)?>">
</head>
<body>
<nav class="site-nav"><div class="container nav-inner"><a class="brand" href="/"><img class="brand-logo" src="/app/assets/img/logo-glow.svg" alt="" aria-hidden="true"><span>GetYourPics.com</span></a><button class="menu-toggle" aria-expanded="false" aria-controls="nav-links">Menu</button><div id="nav-links" class="nav-links"><a href="/app/create.php">Generator</a><a href="/app/customize.php">Customize</a><a href="/app/gallery.php">Gallery</a><?php if($isAdminSession): ?><a href="/admin/index.php">Admin</a><a href="/admin/logout.php">Logout (Admin)</a><?php else: ?><a href="/app/logout.php">Logout (<?=htmlspecialchars((string)$currentUser['username'])?>)</a><?php endif; ?></div></div></nav>
<div class="container">
  <div class="users-page-head">
    <div>
      <h1>Characters</h1>
      <p><a href="/app/characters.php">Characters</a> | <a href="/app/parts.php">Parts</a> | <a href="/app/scenes.php">Scenes</a></p>
    </div>
  </div>

  <?php if($error): ?><div class="banner"><?=htmlspecialchars($error)?></div><?php endif; ?>
  <?php if($success): ?><div class="banner banner-success"><?=htmlspecialchars($success)?></div><?php endif; ?>

  <?php if(!$isAdminSession): ?>
    <dialog id="createCharacterDialog" class="model-dialog">
      <form method="post" enctype="multipart/form-data" class="admin-model-form">
        <div class="model-dialog-head">
          <h3>Create Character</h3>
          <button class="icon-btn btn-secondary" type="button" id="closeCreateCharacterDialog" aria-label="Close character dialog">✕</button>
        </div>
        <div class="row"><label>Name</label><input name="name" required></div>
        <div class="row"><label>Age (20+)</label><input name="age" type="number" min="20" required></div>
        <div class="row"><label>Gender</label><input name="gender"></div>
        <div class="row"><label>Penis size</label><input name="penis_size"></div>
        <div class="row"><label>Boob size</label><input name="boob_size"></div>
        <div class="row"><label>Height (cm)</label><input name="height_cm" type="number" min="50" max="280"></div>
        <div class="row"><label>Description</label><textarea name="description"></textarea></div>
        <div class="row"><label>Photos (up to 20)</label><input type="file" name="photos[]" accept="image/*" multiple required></div>
        <div class="row"><label><input type="checkbox" name="is_public" value="1"> Shared (public)</label></div>
        <div class="gallery-actions"><button class="btn btn-secondary" type="button" id="cancelCreateCharacterDialog">Cancel</button><button class="form-btn" type="submit">Save Character</button></div>
      </form>
    </dialog>
  <?php endif; ?>

  <?php if(!$isAdminSession): ?>
    <div class="models-toolbar">
      <button class="form-btn" type="button" id="openCreateCharacterDialog">New Character</button>
    </div>
  <?php endif; ?>

  <div class="card">
    <h3>Available Characters</h3>
    <div class="gallery-list"><?php foreach($items as $item): ?><article class="gallery-item card"><div class="gallery-preview"><?php if(!empty($item['thumbnail_path'])): ?><img src="<?=htmlspecialchars((string)$item['thumbnail_path'])?>" alt="Character thumbnail"><?php endif; ?></div><div class="gallery-content"><strong><?=htmlspecialchars((string)$item['name'])?></strong><small class="muted"><?=!empty($item['is_public']) ? 'Shared' : 'Private'?> • Age <?=htmlspecialchars((string)$item['age'])?></small><span><?=htmlspecialchars((string)($item['description'] ?? ''))?></span></div></article><?php endforeach; ?></div>
  </div>
</div>
<script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script>
<?php if(!$isAdminSession): ?>
<script>
(() => {
  const dialog = document.getElementById('createCharacterDialog');
  const openButton = document.getElementById('openCreateCharacterDialog');
  const closeButton = document.getElementById('closeCreateCharacterDialog');
  const cancelButton = document.getElementById('cancelCreateCharacterDialog');
  if (!dialog || !openButton) return;
  openButton.addEventListener('click', () => dialog.showModal());
  closeButton?.addEventListener('click', () => dialog.close());
  cancelButton?.addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close();
  });
})();
</script>
<?php endif; ?>
</body>
</html>
