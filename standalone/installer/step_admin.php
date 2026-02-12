<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/migrations.php';
require_once __DIR__ . '/../lib/crypto.php';
$lock = app_root() . '/installed.lock';
if (file_exists($lock)) { header('Location: /installer/step_finish.php?mode=upgrade'); exit; }
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  try {
    migrate_if_needed();
    $username = trim((string)$_POST['username']);
    $password = (string)$_POST['password'];
    if($username===''||$password===''){throw new RuntimeException('Username and password are required.');}
    $id = uuidv4();
    db()->prepare('INSERT INTO admin_users (id,username,password_hash,created_at,updated_at) VALUES (?,?,?,?,?)')->execute([$id,$username,password_hash($password,PASSWORD_DEFAULT),now_utc(),now_utc()]);

    $defaults = [
      ['xai','XAI_API_KEY',''],
      ['xai','XAI_BASE_URL',''],
    ];
    foreach($defaults as $d){
      db()->prepare('INSERT INTO api_keys (id,provider,key_name,key_value_encrypted,is_active,notes,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)')
      ->execute([uuidv4(),$d[0],$d[1],encrypt_secret($d[2]),1,'seeded',now_utc(),now_utc()]);
    }

    $models = [
      ['image','grok-2-image','Grok 2 Image',1,1],
      ['video','grok-2-video','Grok 2 Video',1,1],
    ];
    foreach($models as $m){
      db()->prepare('INSERT INTO models (id,type,model_key,display_name,supports_negative_prompt,is_active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)')
      ->execute([uuidv4(),$m[0],$m[1],$m[2],$m[3],$m[4],now_utc(),now_utc()]);
    }

    db()->prepare('INSERT INTO app_settings (setting_key,setting_value,updated_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=VALUES(updated_at)')
      ->execute(['defaults_json', json_encode(['resolution'=>'1024x1024','aspect_ratio'=>'16:9','fps'=>24], JSON_UNESCAPED_SLASHES), now_utc()]);

    file_put_contents($lock, "installed_at=" . now_utc());
    header('Location: /installer/step_finish.php?mode=fresh'); exit;
  } catch (Throwable $e) {
    $rawMessage = trim((string) $e->getMessage());
    $msg = $rawMessage;

    if (stripos($rawMessage, 'no active transaction') !== false) {
      $msg = 'A migration triggered an implicit MySQL commit and the installer lost transaction context. '
        . 'Please retry. If the error persists, run installer/step_finish.php?mode=upgrade and click "Run migrations" to see the exact migration failure.';
    } elseif (stripos($rawMessage, 'Failed applying migration') === 0) {
      $msg = $rawMessage . ' Please check DB permissions and SQL compatibility for this server version.';
    }
  }
}
?><!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/app/assets/css/style.css"><title>Installer Admin</title></head><body><div class="container"><div class="card"><h1>Step 2: Admin Account</h1><?php if($msg): ?><div class="banner"><?=htmlspecialchars($msg)?></div><?php endif; ?><form method="post"><input name="username" placeholder="Admin username" required><input type="password" name="password" placeholder="Admin password" required><button>Run migrations + finalize install</button></form></div></div></body></html>
