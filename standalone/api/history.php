<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
header('Content-Type: application/json');
require_installation();
start_session();
$user = current_user();
if (!$user) { echo json_encode(['items'=>[]]); exit; }
$stmt = db()->prepare('SELECT * FROM generations WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
$stmt->execute([$user['id']]);
$items = $stmt->fetchAll();
echo json_encode(['items'=>$items]);
