<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_installation();
$items = db()->query("SELECT * FROM generations WHERE status='succeeded' ORDER BY created_at DESC LIMIT 50")->fetchAll();
?><!doctype html><html><head><meta charset="utf-8"><title>Gallery</title><link rel="stylesheet" href="/app/assets/css/style.css"></head><body><div class="container"><h1>Gallery</h1><?php foreach($items as $g): ?><div class="card"><strong><?=htmlspecialchars($g['model_key'])?></strong><br><?=htmlspecialchars($g['prompt'])?><br><?php if($g['output_path']): ?><a href="/api/download.php?id=<?=urlencode($g['id'])?>">Download output</a><?php endif; ?></div><?php endforeach; ?><p><a href="/app/create.php">Back</a></p></div></body></html>
