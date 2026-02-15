<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/crypto.php';
require_admin();
start_session();
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = $_POST['id'] ?: uuidv4();
        $exists = db()->prepare('SELECT id FROM api_keys WHERE id=?');
        $exists->execute([$id]);
        $enc = encrypt_secret((string)($_POST['key_value'] ?? ''));
        if ($exists->fetch()) {
            db()->prepare('UPDATE api_keys SET provider=?, key_name=?, key_value_encrypted=?, is_active=?, notes=?, updated_at=? WHERE id=?')->execute([
                $_POST['provider'],
                $_POST['key_name'],
                $enc,
                (int)!empty($_POST['is_active']),
                $_POST['notes'],
                now_utc(),
                $id,
            ]);
        } else {
            db()->prepare('INSERT INTO api_keys (id,provider,key_name,key_value_encrypted,is_active,notes,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)')->execute([
                $id,
                $_POST['provider'],
                $_POST['key_name'],
                $enc,
                (int)!empty($_POST['is_active']),
                $_POST['notes'],
                now_utc(),
                now_utc(),
            ]);
        }
    }
    if ($action === 'delete') {
        db()->prepare('DELETE FROM api_keys WHERE id=?')->execute([$_POST['id']]);
        header('Location: /admin/keys.php?status=deleted');
        exit;
    }
    if ($action === 'save_value') {
        $enc = encrypt_secret((string)($_POST['key_value'] ?? ''));
        db()->prepare('UPDATE api_keys SET key_value_encrypted=?, updated_at=? WHERE id=?')->execute([
            $enc,
            now_utc(),
            $_POST['id'],
        ]);
        header('Location: /admin/keys.php?status=saved');
        exit;
    }

    if ($action !== 'save_value') {
        header('Location: /admin/keys.php');
        exit;
    }
}

$seedDefaults = [
    ['xai', 'XAI_API_KEY'],
    ['xai', 'XAI_BASE_URL'],
    ['openrouter', 'OPENROUTER_API_KEY'],
    ['openrouter', 'OPENROUTER_BASE_URL'],
];
foreach ($seedDefaults as [$provider, $keyName]) {
    $exists = db()->prepare('SELECT id FROM api_keys WHERE provider = ? AND key_name = ? LIMIT 1');
    $exists->execute([$provider, $keyName]);
    if (!$exists->fetch()) {
        db()->prepare('INSERT INTO api_keys (id,provider,key_name,key_value_encrypted,is_active,notes,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)')->execute([
            uuidv4(),
            $provider,
            $keyName,
            encrypt_secret(''),
            1,
            'seeded',
            now_utc(),
            now_utc(),
        ]);
    }
}

$rows = db()->query('SELECT * FROM api_keys ORDER BY created_at DESC')->fetchAll();
$status = $_GET['status'] ?? '';
if ($status === 'saved') {
    $flash = 'API key updated successfully.';
} elseif ($status === 'deleted') {
    $flash = 'API key removed.';
}
$styleVersion = @filemtime(__DIR__ . '/../app/assets/css/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/../app/assets/js/app.js') ?: time();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"><link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/app/assets/css/style.css?v=<?=urlencode((string)$styleVersion)?>">
  <title>Keys</title>
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
    <h1>API Keys</h1>
    <p><a href="/admin/settings.php">Settings</a> | <a href="/admin/keys.php">API Keys</a> | <a href="/admin/models.php">Models</a> | <a href="/admin/users.php">Users</a> | <a href="/admin/migrations.php">Migrations</a></p>
    <?php if ($flash !== ''): ?><div class="card api-keys-flash"><?=htmlspecialchars($flash)?></div><?php endif; ?>
    <div class="models-toolbar">
      <button type="button" class="form-btn" id="open-add-key-dialog">New Key</button>
    </div>

    <dialog id="add-key-dialog">
      <form method="post">
        <input type="hidden" name="action" value="save">
        <h3>Add Key</h3>
        <div class="row"><input name="provider" placeholder="Nickname" required></div>
        <div class="row"><input name="key_name" placeholder="KEY_NAME" required></div>
        <div class="row"><input type="password" name="key_value" placeholder="Secret value" required></div>
        <div class="row"><label><input type="checkbox" name="is_active" value="1" checked> Active</label></div>
        <div class="row"><textarea name="notes" placeholder="Notes"></textarea></div>
        <div class="row">
          <button type="submit">Save</button>
          <button type="button" id="close-add-key-dialog">Cancel</button>
        </div>
      </form>
    </dialog>
    <?php foreach ($rows as $r):
      $decryptedValue = decrypt_secret((string)$r['key_value_encrypted']);
      $isActive = (int)$r['is_active'] === 1;
    ?>
      <div class="card api-key-card">
        <div class="api-key-head">
          <strong><?=htmlspecialchars($r['provider'])?> / <?=htmlspecialchars($r['key_name'])?></strong>
          <span class="api-key-status <?= $isActive ? 'is-active' : 'is-disabled' ?>"><?= $isActive ? 'Active' : 'Disabled' ?></span>
        </div>
        <?php if (!empty($r['notes'])): ?><small class="muted"><?=htmlspecialchars((string)$r['notes'])?></small><?php endif; ?>
        <form method="post" class="api-key-edit-form">
          <input type="hidden" name="action" value="save_value">
          <input type="hidden" name="id" value="<?=htmlspecialchars($r['id'])?>">
          <label for="key-value-<?=htmlspecialchars($r['id'])?>" class="muted">Key value</label>
          <div class="api-key-value-row">
            <input
              id="key-value-<?=htmlspecialchars($r['id'])?>"
              name="key_value"
              class="api-key-value-input"
              value="<?=htmlspecialchars($decryptedValue)?>"
              autocomplete="off"
              required
            >
            <button type="submit" class="icon-btn" title="Save key" aria-label="Save key">
              💾
            </button>
            <button type="button" class="icon-btn btn-secondary copy-key-btn" title="Copy key" aria-label="Copy key">
              📋
            </button>
          </div>
        </form>
        <form method="post" onsubmit="return confirm('Delete key?')">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?=htmlspecialchars($r['id'])?>">
          <button type="submit" class="btn-danger">🗑 Delete</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>

  <script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script>
  <script>
    (function () {
      const dialog = document.getElementById('add-key-dialog');
      const openButton = document.getElementById('open-add-key-dialog');
      const closeButton = document.getElementById('close-add-key-dialog');

      if (!dialog || !openButton || !closeButton) {
        return;
      }

      openButton.addEventListener('click', function () {
        dialog.showModal();
      });

      closeButton.addEventListener('click', function () {
        dialog.close();
      });

      document.querySelectorAll('.copy-key-btn').forEach(function (button) {
        button.addEventListener('click', async function () {
          const container = button.closest('.api-key-value-row');
          const input = container ? container.querySelector('input') : null;
          if (!input) {
            return;
          }
          try {
            await navigator.clipboard.writeText(input.value);
            button.textContent = '✅';
            setTimeout(function () {
              button.textContent = '📋';
            }, 1200);
          } catch (error) {
            input.select();
            document.execCommand('copy');
          }
        });
      });
    })();
  </script>
</body>
</html>
