<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_installation();
start_session();

if (!empty($_SESSION['admin_user_id'])) {
    header('Location: /admin/users.php');
    exit;
}

header('Location: /app/login.php');
exit;
