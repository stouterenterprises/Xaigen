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

function require_admin(): void
{
    start_session();
    if (empty($_SESSION['admin_user_id'])) {
        header('Location: /admin/index.php');
        exit;
    }
}

function admin_logout(): void
{
    start_session();
    $_SESSION = [];
    session_destroy();
}

function current_admin_username(): ?string
{
    start_session();
    return $_SESSION['admin_username'] ?? null;
}
