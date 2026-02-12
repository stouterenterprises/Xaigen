<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_installation();
start_session();
$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (admin_login((string)$_POST['username'], (string)$_POST['password'])) {
        header('Location: /admin/keys.php');exit;
    }
    $msg='Invalid credentials';
}
if (!empty($_SESSION['admin_user_id'])) { header('Location: /admin/keys.php'); exit; }
?><!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/app/assets/css/style.css"><title>Admin Login</title></head><body><div class="container"><div class="card"><h1>Admin Login</h1><?php if($msg): ?><p class="banner"><?=$msg?></p><?php endif; ?><form method="post"><div class="row"><input name="username" placeholder="Username" required></div><div class="row"><input type="password" name="password" placeholder="Password" required></div><button>Login</button></form><p><a href="/app/create.php">Back to app</a></p></div></div></body></html>
