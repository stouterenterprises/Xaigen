<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/app_settings.php';
require_once __DIR__ . '/../lib/migrations.php';
require_installation();
start_session();

$models = [];
$hasApi = false;
$currentUser = null;
$hasActiveAccount = false;
$defaults = get_generation_defaults();
$characters = [];
$scenes = [];
$parts = [];
$pageError = '';

try {
if ((bool) cfg('AUTO_MIGRATE', true)) { migrate_if_needed(); }

$models = db()->query("SELECT * FROM models WHERE is_active = 1 ORDER BY type, display_name")->fetchAll();
$apiRows = db()->query("SELECT COUNT(*) AS c FROM api_keys WHERE key_name LIKE '%_API_KEY' AND is_active=1")->fetch();
$modelApiRows = db()->query("SELECT COUNT(*) AS c FROM models WHERE is_active=1 AND api_key_encrypted IS NOT NULL AND api_key_encrypted <> ''")->fetch();
$hasApi = (int)($apiRows['c'] ?? 0) > 0 || (int)($modelApiRows['c'] ?? 0) > 0;
$currentUser = current_user();
$hasActiveAccount = !empty($_SESSION['admin_user_id']) || (($currentUser['status'] ?? '') === 'active');
$visibilityWhere = !empty($_SESSION['admin_user_id']) ? '1=1' : '(user_id = :user_id OR is_public = 1)';
$characterStmt = db()->prepare("SELECT c.id, c.name, (SELECT cm.media_path FROM character_media cm WHERE cm.character_id = c.id ORDER BY cm.created_at DESC, cm.id DESC LIMIT 1) AS thumbnail_path FROM characters c WHERE {$visibilityWhere} ORDER BY c.created_at DESC");
if (!empty($_SESSION['admin_user_id'])) { $characterStmt->execute(); } else { $characterStmt->execute(['user_id'=>$currentUser['id'] ?? '']); }
$characters = $characterStmt->fetchAll();
$sceneStmt = db()->prepare("SELECT s.id, s.name, s.type, (SELECT sm.media_path FROM scene_media sm WHERE sm.scene_id = s.id ORDER BY sm.created_at DESC, sm.id DESC LIMIT 1) AS thumbnail_path FROM scenes s WHERE {$visibilityWhere} ORDER BY s.created_at DESC");
if (!empty($_SESSION['admin_user_id'])) { $sceneStmt->execute(); } else { $sceneStmt->execute(['user_id'=>$currentUser['id'] ?? '']); }
$scenes = $sceneStmt->fetchAll();
$partStmt = db()->prepare("SELECT p.id, p.name, (SELECT pm.media_path FROM part_media pm WHERE pm.part_id = p.id ORDER BY pm.created_at DESC, pm.id DESC LIMIT 1) AS thumbnail_path FROM parts p WHERE {$visibilityWhere} ORDER BY p.created_at DESC");
if (!empty($_SESSION['admin_user_id'])) { $partStmt->execute(); } else { $partStmt->execute(['user_id'=>$currentUser['id'] ?? '']); }
$parts = $partStmt->fetchAll();
} catch (Throwable $e) {
  $pageError = $e->getMessage();
}
$styleVersion = @filemtime(__DIR__ . '/assets/css/style.css') ?: time();
$scriptVersion = @filemtime(__DIR__ . '/assets/js/app.js') ?: time();
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><link rel="icon" type="image/svg+xml" href="/favicon.svg"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Ai Generation Studio</title><link rel="stylesheet" href="/app/assets/css/style.css?v=<?=urlencode((string)$styleVersion)?>"></head>
<body>
<nav class="site-nav"><div class="container nav-inner"><a class="brand" href="/"><img class="brand-logo" src="/app/assets/img/logo-glow.svg" alt="" aria-hidden="true"><span>GetYourPics.com</span></a><button class="menu-toggle" aria-expanded="false" aria-controls="nav-links">Menu</button><div id="nav-links" class="nav-links"><a href="/">Home</a><a href="/app/create.php">Generator</a><a href="/app/gallery.php">Gallery</a><a href="/app/customize.php">Customize</a><?php if($currentUser): ?><a href="/app/logout.php">Logout (<?=htmlspecialchars((string)$currentUser['username'])?>)</a><?php elseif(!empty($_SESSION['admin_user_id'])): ?><a href="/admin/index.php">Admin</a><a href="/admin/logout.php">Logout (Admin)</a><?php else: ?><a href="/app/login.php">Login</a><?php endif; ?></div></div></nav>
<div class="container">
<h1>Ai Generation Studio</h1>
<?php if($pageError): ?><div class="banner">Unable to load generator data: <?=htmlspecialchars($pageError)?></div><?php endif; ?>
<?php if(!$hasApi): ?><div class="banner">Admin must configure API keys.</div><?php endif; ?>
<?php if(!$hasActiveAccount): ?><div class="banner">You need an active account to generate. Please login or request access.</div><?php endif; ?>
<div class="grid"><div class="card"><h3>Create</h3><form id="generateForm">
<div class="generator-tabs" role="tablist" aria-label="Generation type"><button class="generator-tab is-active" type="button" role="tab" aria-selected="true" data-generator-tab="image">Photo</button><button class="generator-tab" type="button" role="tab" aria-selected="false" data-generator-tab="video">Video</button><button class="generator-tab" type="button" role="tab" aria-selected="false" data-generator-tab="extend">Extend</button></div>
<input type="hidden" name="generation_mode" value="create">
<input type="hidden" name="type" value="image">
<div class="row"><label>Model</label><select name="model_key"><?php foreach($models as $m): ?><option value="<?=htmlspecialchars((string)$m['model_key'])?>" data-model-type="<?=htmlspecialchars((string)$m['type'])?>" data-provider="<?=htmlspecialchars((string)($m['api_provider'] ?? 'xai'))?>"><?=htmlspecialchars((string)$m['display_name'])?></option><?php endforeach; ?></select></div>
<div class="row"><label>Prompt</label><textarea name="prompt" required></textarea></div>
<div class="row"><label>Negative Prompt</label><textarea name="negative_prompt"></textarea></div>
<div class="row row-standard-media"><label>Reference Photo (Photo + Video)</label><input type="file" name="reference_media" accept="image/*"></div>
<div class="row row-extend-only is-hidden"><label>Extend Reference (Photo or Video)</label><input type="file" name="extend_media" accept="image/*,video/*"></div>
<div class="row row-extend-only is-hidden"><label>Extend to provider max duration</label><input type="checkbox" name="extend_to_provider_max" value="1" checked></div>
<div class="row row-image-only"><label>Seed</label><input name="seed" value="<?=htmlspecialchars((string)$defaults['seed'])?>"></div>
<div class="row row-image-only"><label>Aspect ratio</label><input name="aspect_ratio" value="<?=htmlspecialchars((string)$defaults['aspect_ratio'])?>"></div>
<div class="row"><label>Resolution</label><input name="resolution" value="<?=htmlspecialchars((string)$defaults['resolution'])?>"></div>
<div class="row row-video-only is-hidden"><label>Video duration</label><input name="duration_seconds" value="<?=htmlspecialchars((string)$defaults['duration_seconds'])?>"></div>
<div class="row row-video-only is-hidden"><label>FPS</label><input name="fps" value="<?=htmlspecialchars((string)$defaults['fps'])?>"></div>
<div class="row"><label>Characters (up to 3)</label><select name="character_ids[]" id="characterSelect" multiple data-max-select="3"><?php foreach($characters as $c): ?><option value="<?=htmlspecialchars((string)$c['id'])?>" data-thumb="<?=htmlspecialchars((string)($c['thumbnail_path'] ?? ''))?>"><?=htmlspecialchars((string)$c['name'])?></option><?php endforeach; ?></select><small class="muted">Pick up to three characters.</small></div>
<div class="row"><label>Scene</label><select name="scene_id" id="sceneSelect"><option value="">None</option><?php foreach($scenes as $scene): ?><option value="<?=htmlspecialchars((string)$scene['id'])?>" data-scene-type="<?=htmlspecialchars((string)$scene['type'])?>" data-thumb="<?=htmlspecialchars((string)($scene['thumbnail_path'] ?? ''))?>">[<?=htmlspecialchars((string)$scene['type'])?>] <?=htmlspecialchars((string)$scene['name'])?></option><?php endforeach; ?></select></div>
<div class="row"><label>Parts (multi-select)</label><select name="part_ids[]" id="partSelect" multiple><?php foreach($parts as $part): ?><option value="<?=htmlspecialchars((string)$part['id'])?>" data-thumb="<?=htmlspecialchars((string)($part['thumbnail_path'] ?? ''))?>"><?=htmlspecialchars((string)$part['name'])?></option><?php endforeach; ?></select></div>
<button class="form-btn" type="submit" <?=$hasActiveAccount ? '' : 'disabled'?>>Generate</button></form></div><div><div class="card"><h3>Preview + Status</h3><pre id="statusBox" class="muted">Submit a generation request.</pre></div><div id="historyBox"></div></div></div>
</div>
<script src="/app/assets/js/app.js?v=<?=urlencode((string)$scriptVersion)?>"></script>
</body>
</html>
