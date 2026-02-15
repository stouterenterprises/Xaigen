<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_admin();

function shared_provider_value(string $provider, string $keyName): string
{
    $stmt = db()->prepare('SELECT key_value_encrypted FROM api_keys WHERE provider=? AND key_name=? AND is_active=1 ORDER BY created_at ASC LIMIT 1');
    $stmt->execute([$provider, $keyName]);
    $row = $stmt->fetch();
    if (!$row || empty($row['key_value_encrypted'])) {
        return '';
    }

    return trim((string) decrypt_secret((string) $row['key_value_encrypted']));
}

function shared_provider_default_base_url(string $provider): string
{
    return $provider === 'openrouter' ? 'https://openrouter.ai/api/v1' : 'https://api.x.ai/v1';
}

$id = trim((string) ($_GET['id'] ?? $_POST['id'] ?? ''));
if ($id === '') {
    http_response_code(404);
    exit('Model not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiProvider = strtolower(trim((string) ($_POST['api_provider'] ?? 'xai')));
    if ($apiProvider === '') {
        $apiProvider = 'xai';
    }

    db()->prepare('UPDATE models SET type=?, model_key=?, display_name=?, api_provider=?, api_base_url=?, api_key_encrypted=?, supports_negative_prompt=?, is_active=?, updated_at=? WHERE id=?')->execute([
        (string) ($_POST['type'] ?? 'image'),
        trim((string) ($_POST['model_key'] ?? '')),
        trim((string) ($_POST['display_name'] ?? '')),
        $apiProvider,
        null,
        null,
        (int) !empty($_POST['supports_negative_prompt']),
        (int) !empty($_POST['is_active']),
        now_utc(),
        $id,
    ]);

    header('Location: /admin/model_edit.php?id=' . rawurlencode($id) . '&saved=1');
    exit;
}

$stmt = db()->prepare('SELECT * FROM models WHERE id=? LIMIT 1');
$stmt->execute([$id]);
$model = $stmt->fetch();
if (!$model) {
    http_response_code(404);
    exit('Model not found.');
}

$provider = strtolower(trim((string) ($model['api_provider'] ?? 'xai')));
if ($provider === '') {
    $provider = 'xai';
}
$sharedBaseUrl = shared_provider_value($provider, strtoupper($provider) . '_BASE_URL');
if ($sharedBaseUrl === '') {
    $sharedBaseUrl = shared_provider_default_base_url($provider);
}
$sharedApiKeyExists = shared_provider_value($provider, strtoupper($provider) . '_API_KEY') !== '';
$styleVersion = @filemtime(__DIR__ . '/../app/assets/css/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/../app/assets/js/app.js') ?: time();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"><link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/app/assets/css/style.css?v=<?=urlencode((string)$styleVersion)?>">
  <title>Edit Model</title>
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
      <a href="/admin/index.php">Admin</a>
    </div>
  </div>
</nav>

<div class="container">
  <h1>Edit model</h1>
  <p><a href="/admin/models.php">← Back to models</a> | <a href="/admin/users.php">Users</a></p>
  <?php if (!empty($_GET['saved'])): ?><div class="banner banner-success">Saved.</div><?php endif; ?>

  <div class="card">
    <form method="post">
      <input type="hidden" name="id" value="<?=htmlspecialchars((string)$model['id'])?>">
      <div class="row"><label>Type</label><select name="type" required><option value="image" <?=$model['type']==='image'?'selected':''?>>Image</option><option value="video" <?=$model['type']==='video'?'selected':''?>>Video</option></select></div>
      <div class="row"><label>Model key</label><input name="model_key" value="<?=htmlspecialchars((string)$model['model_key'])?>" required></div>
      <div class="row"><label>Display name</label><input name="display_name" value="<?=htmlspecialchars((string)$model['display_name'])?>" required></div>
      <div class="row"><label>Provider</label><input name="api_provider" value="<?=htmlspecialchars((string)$provider)?>" placeholder="xai or openrouter"></div>
      <p class="muted">This model uses shared provider URL + key from <a href="/admin/keys.php">API Keys</a>.</p>
      <p class="muted">Shared provider base URL: <code><?=htmlspecialchars($sharedBaseUrl)?></code></p>
      <p class="muted">Shared provider API key: <?=$sharedApiKeyExists ? 'Configured' : 'Not configured yet'?> (key name: <code><?=htmlspecialchars(strtoupper($provider) . '_API_KEY')?></code>)</p>
      <label><input type="checkbox" name="supports_negative_prompt" <?=!empty($model['supports_negative_prompt'])?'checked':''?>> Supports negative prompt</label>
      <label><input type="checkbox" name="is_active" <?=!empty($model['is_active'])?'checked':''?>> Active</label>
      <button class="form-btn" type="submit">Save changes</button>
    </form>
  </div>
</div>
<script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script>
</body>
</html>
