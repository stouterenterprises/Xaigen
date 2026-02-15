<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_admin();

$errorMessage = '';
$rows = [];

/**
 * @param string $email
 */
function build_username_from_email(string $email): string
{
    $localPart = strtolower((string) strstr($email, '@', true));
    $base = preg_replace('/[^a-z0-9_]/', '_', $localPart);
    $base = trim((string) $base, '_');
    if ($base === '') {
        $base = 'user';
    }
    return substr($base, 0, 40);
}

function find_unique_username(string $email): string
{
    $base = build_username_from_email($email);
    $username = $base;

    $i = 1;
    while (true) {
        $stmt = db()->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        if (!$stmt->fetch()) {
            return $username;
        }

        $suffix = '_' . $i;
        $username = substr($base, 0, max(1, 40 - strlen($suffix))) . $suffix;
        $i++;
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');
        $id = (string) ($_POST['id'] ?? '');

        if ($id !== '' && in_array($action, ['approve', 'reject'], true)) {
            $status = $action === 'approve' ? 'active' : 'rejected';
            db()->prepare('UPDATE users SET status=?, reviewed_by_admin_id=?, reviewed_at=?, updated_at=? WHERE id=?')->execute([
                $status,
                $_SESSION['admin_user_id'] ?? null,
                now_utc(),
                now_utc(),
                $id,
            ]);
        }

        if ($id !== '' && $action === 'update_credentials') {
            $email = trim(strtolower((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Please provide a valid email address.');
            }

            if ($password !== '' && strlen($password) < 8) {
                throw new RuntimeException('Password must be at least 8 characters.');
            }

            $currentStmt = db()->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
            $currentStmt->execute([$id]);
            $current = $currentStmt->fetch();

            if (!$current) {
                throw new RuntimeException('User record not found.');
            }

            if ($email === '') {
                $email = (string) ($current['email'] ?? '');
            }

            $dupStmt = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $dupStmt->execute([$email, $id]);
            if ($dupStmt->fetch()) {
                throw new RuntimeException('Email already exists on another account.');
            }

            $params = [$email, now_utc(), $id];
            $sql = 'UPDATE users SET email=?, updated_at=?';
            if ($password !== '') {
                $sql .= ', password_hash=?';
                array_splice($params, 2, 0, [password_hash($password, PASSWORD_DEFAULT)]);
            }
            $sql .= ' WHERE id=?';

            db()->prepare($sql)->execute($params);
        }

        if ($action === 'create_user') {
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $email = trim(strtolower((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');

            if ($fullName === '' || $email === '' || $password === '') {
                throw new RuntimeException('Name, email, and password are required.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Please provide a valid email address.');
            }

            if (strlen($password) < 8) {
                throw new RuntimeException('Password must be at least 8 characters.');
            }

            $exists = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $exists->execute([$email]);
            if ($exists->fetch()) {
                throw new RuntimeException('Email already exists.');
            }

            db()->prepare('INSERT INTO users (id, full_name, username, email, purpose, password_hash, role, status, reviewed_by_admin_id, reviewed_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([
                    uuidv4(),
                    $fullName,
                    find_unique_username($email),
                    $email,
                    'Created by admin user management',
                    password_hash($password, PASSWORD_DEFAULT),
                    'user',
                    'active',
                    $_SESSION['admin_user_id'] ?? null,
                    now_utc(),
                    now_utc(),
                    now_utc(),
                ]);
        }

        header('Location: /admin/users.php');
        exit;
    }

    $rows = db()->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();
} catch (Throwable $e) {
    $errorMessage = 'Could not load users admin page: ' . $e->getMessage();
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
  <title>Users</title>
</head>
<body>
<nav class="site-nav"><div class="container nav-inner"><a class="brand" href="/"><img class="brand-logo" src="/app/assets/img/logo-glow.svg" alt="" aria-hidden="true"><span>GetYourPics.com</span></a><button class="menu-toggle" aria-expanded="false" aria-controls="nav-links">Menu</button><div id="nav-links" class="nav-links"><a href="/">Home</a><a href="/app/create.php">Generator</a><a href="/app/gallery.php">Gallery</a><a href="/admin/index.php">Admin</a></div></div></nav>
<div class="container">
<div class="users-page-head">
  <h1>Users</h1>
</div>
<p><a href="/admin/settings.php">Settings</a> | <a href="/admin/keys.php">API Keys</a> | <a href="/admin/models.php">Models</a> | <a href="/admin/users.php">Users</a> | <a href="/admin/migrations.php">Migrations</a></p>
<?php if ($errorMessage): ?><div class="banner"><?=htmlspecialchars($errorMessage)?></div><?php endif; ?>

<?php
$requests = array_values(array_filter($rows, static fn($u) => ($u['status'] ?? '') === 'pending'));
$members = array_values(array_filter($rows, static fn($u) => ($u['status'] ?? '') !== 'pending'));
?>

<div class="models-toolbar">
  <button type="button" class="form-btn" data-open-new-user-dialog>New User</button>
</div>

<div class="card users-table-card">
  <h2>User Requests</h2>
  <div class="users-table-wrap">
    <table class="users-table">
      <thead>
      <tr>
        <th>Name</th>
        <th>Email (press Enter to save)</th>
        <th>Password (press Enter to save)</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
      </thead>
      <tbody>
      <?php if (!$requests): ?>
      <tr><td colspan="5" class="muted">No pending user requests.</td></tr>
      <?php endif; ?>
      <?php foreach($requests as $u): ?>
      <tr>
        <td>
          <strong><?=htmlspecialchars((string)$u['full_name'])?></strong><br>
          <small class="muted">@<?=htmlspecialchars((string)$u['username'])?></small>
        </td>
        <td>
          <form method="post" class="users-inline-form">
            <input type="hidden" name="id" value="<?=htmlspecialchars((string)$u['id'])?>">
            <input type="hidden" name="action" value="update_credentials">
            <input type="email" name="email" value="<?=htmlspecialchars((string)$u['email'])?>" autocomplete="email" required>
          </form>
        </td>
        <td>
          <form method="post" class="users-inline-form">
            <input type="hidden" name="id" value="<?=htmlspecialchars((string)$u['id'])?>">
            <input type="hidden" name="action" value="update_credentials">
            <input type="password" name="password" placeholder="New password" autocomplete="new-password" minlength="8">
          </form>
        </td>
        <td><small class="status-pill status-<?=htmlspecialchars((string)$u['status'])?>"><?=htmlspecialchars((string)$u['status'])?></small></td>
        <td>
          <div class="users-actions">
            <form method="post"><input type="hidden" name="id" value="<?=htmlspecialchars((string)$u['id'])?>"><input type="hidden" name="action" value="approve"><button class="btn" type="submit">Approve</button></form>
            <form method="post"><input type="hidden" name="id" value="<?=htmlspecialchars((string)$u['id'])?>"><input type="hidden" name="action" value="reject"><button class="btn btn-danger" type="submit">Reject</button></form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card users-table-card">
  <h2>All Users</h2>
  <div class="users-table-wrap">
    <table class="users-table">
      <thead>
      <tr>
        <th>Name</th>
        <th>Email (press Enter to save)</th>
        <th>Password (press Enter to save)</th>
        <th>Status</th>
      </tr>
      </thead>
      <tbody>
      <?php if (!$members): ?>
      <tr><td colspan="4" class="muted">No users found.</td></tr>
      <?php endif; ?>
      <?php foreach($members as $u): ?>
      <tr>
        <td>
          <strong><?=htmlspecialchars((string)$u['full_name'])?></strong><br>
          <small class="muted">@<?=htmlspecialchars((string)$u['username'])?></small>
        </td>
        <td>
          <form method="post" class="users-inline-form">
            <input type="hidden" name="id" value="<?=htmlspecialchars((string)$u['id'])?>">
            <input type="hidden" name="action" value="update_credentials">
            <input type="email" name="email" value="<?=htmlspecialchars((string)$u['email'])?>" autocomplete="email" required>
          </form>
        </td>
        <td>
          <form method="post" class="users-inline-form">
            <input type="hidden" name="id" value="<?=htmlspecialchars((string)$u['id'])?>">
            <input type="hidden" name="action" value="update_credentials">
            <input type="password" name="password" placeholder="New password" autocomplete="new-password" minlength="8">
          </form>
        </td>
        <td><small class="status-pill status-<?=htmlspecialchars((string)$u['status'])?>"><?=htmlspecialchars((string)$u['status'])?></small></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<dialog class="model-dialog" id="newUserDialog">
  <form method="dialog" class="admin-model-form">
    <div class="model-dialog-head">
      <h3>Create New User</h3>
      <button type="button" class="btn btn-secondary" data-close-new-user-dialog>&times;</button>
    </div>
  </form>
  <form method="post" class="admin-model-form">
    <input type="hidden" name="action" value="create_user">
    <div class="row"><label>Name</label><input name="full_name" required></div>
    <div class="row"><label>Email</label><input type="email" name="email" autocomplete="email" required></div>
    <div class="row"><label>Password</label><input type="password" name="password" autocomplete="new-password" minlength="8" required></div>
    <button type="submit" class="btn">Create User</button>
  </form>
</dialog>
</div>
<script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script>
<script>
document.querySelectorAll('.users-inline-form input').forEach(function (input) {
  input.addEventListener('keydown', function (event) {
    if (event.key === 'Enter') {
      event.preventDefault();
      event.currentTarget.form.submit();
    }
  });
});

var newUserDialog = document.getElementById('newUserDialog');
var openNewUserButton = document.querySelector('[data-open-new-user-dialog]');
var closeNewUserButton = document.querySelector('[data-close-new-user-dialog]');
if (newUserDialog && openNewUserButton) {
  openNewUserButton.addEventListener('click', function () {
    newUserDialog.showModal();
  });
}
if (newUserDialog && closeNewUserButton) {
  closeNewUserButton.addEventListener('click', function () {
    newUserDialog.close();
  });
}
</script>
</body>
</html>
