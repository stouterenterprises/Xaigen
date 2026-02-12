<?php
require_once __DIR__ . '/../lib/config.php';
$lock = app_root() . '/installed.lock';
if (file_exists($lock)) { header('Location: /installer/step_finish.php?mode=upgrade'); exit; }
if($_SERVER['REQUEST_METHOD']==='POST'){
  $enc = trim((string)($_POST['ENCRYPTION_KEY'] ?? ''));
  if ($enc === '') { $enc = 'base64:' . base64_encode(random_bytes(32)); }
  $cfg = "<?php\nreturn " . var_export([
    'APP_ENV' => 'production',
    'APP_URL' => (string)($_POST['APP_URL'] ?? ''),
    'AUTO_MIGRATE' => true,
    'DB_HOST' => (string)$_POST['DB_HOST'],
    'DB_PORT' => (int)$_POST['DB_PORT'],
    'DB_NAME' => (string)$_POST['DB_NAME'],
    'DB_USER' => (string)$_POST['DB_USER'],
    'DB_PASS' => (string)$_POST['DB_PASS'],
    'ENCRYPTION_KEY' => $enc,
    'RATE_LIMIT_PER_MINUTE' => 20,
    'XAI_TIMEOUT_SECONDS' => 60,
  ], true) . ";\n";
  file_put_contents(app_root().'/config.local.php', $cfg);
  header('Location: /installer/step_admin.php');exit;
}
?><!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/app/assets/css/style.css"><title>Installer DB</title></head><body><div class="container"><div class="card"><h1>Step 1: DB + App Config</h1><form method="post"><input name="APP_URL" placeholder="https://your-domain.com" required><input name="DB_HOST" placeholder="DB Host" required><input name="DB_PORT" value="3306" required><input name="DB_NAME" placeholder="DB Name" required><input name="DB_USER" placeholder="DB User" required><input type="password" name="DB_PASS" placeholder="DB Password" required><input name="ENCRYPTION_KEY" placeholder="Leave blank to auto-generate"><button>Save and continue</button></form></div></div></body></html>
