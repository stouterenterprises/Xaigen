<?php
require_once __DIR__ . '/../lib/config.php';
$lock = app_root() . '/installed.lock';
if (file_exists($lock)) {
    header('Location: /installer/step_finish.php?mode=upgrade');
    exit;
}
?><!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/app/assets/css/style.css"><title>Installer</title></head><body><div class="container"><div class="card"><h1>Install Studio</h1><p>Step 1: Configure database connection.</p><a href="/installer/step_db.php">Start installation</a></div></div></body></html>
