<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/crypto.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = uuidv4();
    $apiProvider = strtolower(trim((string) ($_POST['api_provider'] ?? 'xai')));
    if ($apiProvider === '') {
        $apiProvider = 'xai';
    }
    $apiBaseUrl = trim((string) ($_POST['api_base_url'] ?? ''));
    $apiKeyPlain = trim((string) ($_POST['api_key_plain'] ?? ''));

    db()->prepare('INSERT INTO models (type,model_key,display_name,api_provider,api_base_url,api_key_encrypted,supports_negative_prompt,is_active,created_at,updated_at,id) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute([
        (string) ($_POST['type'] ?? 'image'),
        trim((string) ($_POST['model_key'] ?? '')),
        trim((string) ($_POST['display_name'] ?? '')),
        $apiProvider,
        $apiBaseUrl !== '' ? $apiBaseUrl : null,
        $apiKeyPlain !== '' ? encrypt_secret($apiKeyPlain) : null,
        (int) !empty($_POST['supports_negative_prompt']),
        (int) !empty($_POST['is_active']),
        now_utc(),
        now_utc(),
        $id,
    ]);

    header('Location: /admin/models.php');
    exit;
}

$rows = db()->query('SELECT * FROM models ORDER BY type, display_name')->fetchAll();
$styleVersion = @filemtime(__DIR__ . '/../app/assets/css/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/../app/assets/js/app.js') ?: time();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"><link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/app/assets/css/style.css?v=<?=urlencode((string)$styleVersion)?>">
  <title>Models</title>
</head>
<body>
  <nav class="site-nav"><div class="container nav-inner"><a class="brand" href="/"><img class="brand-logo" src="/app/assets/img/logo-glow.svg" alt="" aria-hidden="true"><span>GetYourPics.com</span></a><button class="menu-toggle" aria-expanded="false" aria-controls="nav-links">Menu</button><div id="nav-links" class="nav-links"><a href="/">Home</a><a href="/app/create.php">Generator</a><a href="/app/gallery.php">Gallery</a><a href="/admin/index.php">Admin</a></div></div></nav>

  <div class="container">
    <h1>Models</h1>
    <p><a href="/admin/settings.php">Settings</a> | <a href="/admin/keys.php">API Keys</a> | <a href="/admin/models.php">Models</a> | <a href="/admin/users.php">Users</a> | <a href="/admin/migrations.php">Migrations</a></p>

    <div class="models-toolbar">
      <button class="form-btn" type="button" id="openAddModel">New Model</button>
    </div>

    <dialog id="addModelDialog" class="model-dialog">
      <form method="post" class="admin-model-form" id="addModelForm">
        <div class="model-dialog-head">
          <h3>Add model</h3>
          <button class="icon-btn btn-secondary" type="button" id="closeAddModel" aria-label="Close add model dialog">✕</button>
        </div>
        <div class="row"><label>Type</label><select name="type" required><option value="image">Image</option><option value="video">Video</option></select></div>
        <div class="row"><label>Model key</label><input name="model_key" required></div>
        <div class="row"><label>Display name</label><input name="display_name" required></div>
        <div class="row"><label>Provider</label><input name="api_provider" value="xai" placeholder="xai or openrouter"></div>
        <div class="row"><label>Model API base URL (optional override)</label><input name="api_base_url" placeholder="Leave blank to use provider shared base URL from API Keys"></div>
        <div class="row"><label>Model API key (optional override)</label><input name="api_key_plain" placeholder="Leave blank to use provider shared API key from API Keys" autocomplete="off"></div>
        <label><input type="checkbox" name="supports_negative_prompt" checked> Supports negative prompt</label>
        <label><input type="checkbox" name="is_active" checked> Active</label>
        <div class="gallery-actions"><button class="btn" type="button" id="cancelAddModel">Cancel</button><button class="form-btn" type="submit">Save</button></div>
      </form>
    </dialog>

    <div class="model-list">
    <?php foreach ($rows as $r): ?>
      <a class="card model-link-card" href="/admin/model_edit.php?id=<?=urlencode((string)$r['id'])?>">
        <strong><?=htmlspecialchars((string)$r['display_name'])?></strong>
        <span class="muted"><?=htmlspecialchars((string)$r['model_key'])?> • <?=htmlspecialchars((string)$r['type'])?> • <?=htmlspecialchars((string)($r['api_provider'] ?? 'xai'))?></span>
      </a>
    <?php endforeach; ?>
    </div>
  </div>

  <script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script>
  <script>
    const addDialog = document.getElementById('addModelDialog');
    const openBtn = document.getElementById('openAddModel');
    const closeBtn = document.getElementById('closeAddModel');
    const cancelBtn = document.getElementById('cancelAddModel');
    openBtn?.addEventListener('click', () => addDialog?.showModal());
    closeBtn?.addEventListener('click', () => addDialog?.close());
    cancelBtn?.addEventListener('click', () => addDialog?.close());
    addDialog?.addEventListener('click', (event) => {
      if (event.target === addDialog) {
        addDialog.close();
      }
    });
  </script>
</body>
</html>
