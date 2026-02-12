<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_admin();
if($_SERVER['REQUEST_METHOD']==='POST'){
  $json=(string)($_POST['defaults_json']??'{}');
  json_decode($json, true, 512, JSON_THROW_ON_ERROR);
  $stmt=db()->prepare("INSERT INTO app_settings (setting_key,setting_value,updated_at) VALUES ('defaults_json',?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=VALUES(updated_at)");
  $stmt->execute([$json, now_utc()]);
}
$row = db()->query("SELECT setting_value FROM app_settings WHERE setting_key='defaults_json'")->fetch();
$val = $row['setting_value'] ?? '{"resolution":"1024x1024"}';
?><!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/app/assets/css/style.css"><title>Settings</title></head><body><div class="container"><h1>Settings</h1><p><a href="/admin/keys.php">Keys</a> | <a href="/admin/models.php">Models</a> | <a href="/admin/migrations.php">Migrations</a></p><div class="card"><form method="post"><textarea name="defaults_json" rows="12"><?=htmlspecialchars($val)?></textarea><button>Save</button></form></div></div></body></html>
