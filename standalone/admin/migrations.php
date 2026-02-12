<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/migrations.php';
require_admin();
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  try{migrate_if_needed();$msg='Migrations applied successfully.';}catch(Throwable $e){$msg='Error: '.$e->getMessage();}
}
$status = migration_status();
?><!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/app/assets/css/style.css"><title>Migrations</title></head><body><div class="container"><h1>Migrations</h1><p><a href="/admin/keys.php">Keys</a> | <a href="/admin/settings.php">Settings</a> | <a href="/admin/models.php">Models</a></p><?php if($msg): ?><div class="card"><?=$msg?></div><?php endif; ?><div class="card"><form method="post"><button>Run Migrations Now</button></form></div><?php foreach($status as $m): ?><div class="card"><?=htmlspecialchars($m['filename'])?> - <?=htmlspecialchars($m['state'])?></div><?php endforeach; ?></div></body></html>
