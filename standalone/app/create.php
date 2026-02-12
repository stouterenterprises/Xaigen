<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_installation();

$models = db()->query("SELECT * FROM models WHERE is_active = 1 ORDER BY type, display_name")->fetchAll();
$apiRows = db()->query("SELECT COUNT(*) AS c FROM api_keys WHERE provider='xai' AND key_name='XAI_API_KEY' AND is_active=1")->fetch();
$hasApi = (int)($apiRows['c'] ?? 0) > 0;
?>
<!doctype html><html><head><meta charset="utf-8"><title>Generation Studio</title><link rel="stylesheet" href="/app/assets/css/style.css"></head>
<body><div class="container"><h1>Image + Video Generation Studio</h1>
<?php if(!$hasApi): ?><div class="banner">Admin must configure API keys.</div><?php endif; ?>
<div class="grid">
<div class="card"><h3>Create</h3><form id="generateForm">
<div class="row"><label>Type</label><select name="type"><option value="image">Image</option><option value="video">Video</option></select></div>
<div class="row"><label>Model</label><select name="model_key"><?php foreach($models as $m): ?><option value="<?=htmlspecialchars($m['model_key'])?>"><?=htmlspecialchars($m['display_name'])?> (<?=htmlspecialchars($m['type'])?>)</option><?php endforeach; ?></select></div>
<div class="row"><label>Prompt</label><textarea name="prompt" required></textarea></div>
<div class="row"><label>Negative Prompt</label><textarea name="negative_prompt"></textarea></div>
<div class="row"><label>Seed</label><input name="seed"></div>
<div class="row"><label>Aspect ratio</label><input name="aspect_ratio" value="16:9"></div>
<div class="row"><label>Resolution</label><input name="resolution" value="1024x1024"></div>
<div class="row"><label>Video duration</label><input name="duration_seconds" value="5"></div>
<div class="row"><label>FPS</label><input name="fps" value="24"></div>
<button type="submit">Generate</button></form></div>
<div><div class="card"><h3>Preview + Status</h3><pre id="statusBox" class="muted">Submit a generation request.</pre></div><div id="historyBox"></div></div>
</div>
<p><a href="/app/gallery.php">Gallery</a> | <a href="/admin/index.php">Admin</a></p>
</div><script src="/app/assets/js/app.js"></script></body></html>
