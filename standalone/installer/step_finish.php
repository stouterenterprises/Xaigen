<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/migrations.php';
$mode = $_GET['mode'] ?? 'upgrade';
$message='';
if($mode==='upgrade' && $_SERVER['REQUEST_METHOD']==='POST'){
  try {
    if(isset($_POST['run_migrations'])){migrate_if_needed();$message='Migrations completed.';}
    if(isset($_POST['repair_storage'])){ @mkdir(app_root().'/storage/generated',0775,true); @mkdir(app_root().'/storage/logs',0775,true); $message='Storage repaired.'; }
    if(isset($_POST['validate_db'])){ db()->query('SELECT 1'); $message='Database connection OK.'; }
  } catch(Throwable $e){ $message='Error: '.$e->getMessage(); }
}
?><!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/app/assets/css/style.css"><title>Installer Finish</title></head><body><div class="container"><div class="card"><h1><?= $mode==='fresh'?'Installation Complete':'Upgrade Utilities' ?></h1><?php if($message): ?><p><?=htmlspecialchars($message)?></p><?php endif; ?><?php if($mode==='upgrade'): ?><p>Full install is locked because installed.lock exists.</p><form method="post"><button name="run_migrations" value="1">Run migrations</button></form><form method="post"><button name="repair_storage" value="1">Repair storage</button></form><form method="post"><button name="validate_db" value="1">Validate DB</button></form><?php else: ?><p>installed.lock created and app is ready.</p><?php endif; ?><p><a href="/app/create.php">Open App</a> | <a href="/admin/index.php">Admin Login</a></p></div></div></body></html>
