<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/migrations.php';

function start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function admin_login(string $username, string $password): bool
{
    start_session();
    if ((bool) cfg('AUTO_MIGRATE', true)) {
        migrate_if_needed();
    }

    $stmt = db()->prepare('SELECT * FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    $_SESSION['admin_user_id'] = $user['id'];
    $_SESSION['admin_username'] = $user['username'];
    return true;
}

function user_login(string $usernameOrEmail, string $password): array
{
    start_session();
    if ((bool) cfg('AUTO_MIGRATE', true)) {
        migrate_if_needed();
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['ok' => false, 'error' => 'Invalid credentials.'];
    }

    if (($user['status'] ?? 'pending') !== 'active') {
        return ['ok' => false, 'error' => 'Your account is pending approval.'];
    }

    $_SESSION['app_user_id'] = $user['id'];
    $_SESSION['app_username'] = $user['username'];
    $_SESSION['app_role'] = $user['role'] ?? 'user';

    return ['ok' => true];
}

function register_user_request(string $fullName, string $username, string $email, string $purpose, string $password): array
{
    $fullName = trim($fullName);
    $username = trim($username);
    $email = trim(strtolower($email));
    $purpose = trim($purpose);

    if ($fullName === '' || $username === '' || $email === '' || $purpose === '' || $password === '') {
        return ['ok' => false, 'error' => 'All fields are required.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Please provide a valid email address.'];
    }

    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
    }

    $exists = db()->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
    $exists->execute([$username, $email]);
    if ($exists->fetch()) {
        return ['ok' => false, 'error' => 'Username or email already exists.'];
    }

    db()->prepare('INSERT INTO users (id, full_name, username, email, purpose, password_hash, role, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)')
      ->execute([
          uuidv4(),
          $fullName,
          $username,
          $email,
          $purpose,
          password_hash($password, PASSWORD_DEFAULT),
          'user',
          'pending',
          now_utc(),
          now_utc(),
      ]);

    return ['ok' => true];
}

function require_admin(): void
{
    start_session();
    if (empty($_SESSION['admin_user_id'])) {
        header('Location: /admin/index.php');
        exit;
    }
}

function require_active_account_or_admin_json(): void
{
    start_session();
    if (!empty($_SESSION['admin_user_id'])) {
        return;
    }

    $user = current_user();
    if ($user && ($user['status'] ?? '') === 'active') {
        return;
    }

    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'An active user account is required.']);
    exit;
}

function current_user(): ?array
{
    start_session();
    $id = $_SESSION['app_user_id'] ?? null;
    if (!$id) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function current_admin_username(): ?string
{
    start_session();
    return $_SESSION['admin_username'] ?? null;
}

function admin_logout(): void
{
    start_session();
    $_SESSION = [];
    session_destroy();
}

function app_logout(): void
{
    start_session();
    unset($_SESSION['app_user_id'], $_SESSION['app_username'], $_SESSION['app_role']);
}
