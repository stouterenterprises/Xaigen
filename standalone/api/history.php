<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
header('Content-Type: application/json');
require_installation();
start_session();
$user = current_user();
$isAdmin = !empty($_SESSION['admin_user_id']);

if (!$user && !$isAdmin) { echo json_encode(['items'=>[]]); exit; }

if ($isAdmin && !$user) {
    $items = db()->query('SELECT * FROM generations ORDER BY created_at DESC LIMIT 100')->fetchAll();
    echo json_encode(['items'=>$items]);
    exit;
}

$stmt = db()->prepare('SELECT * FROM generations WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
$stmt->execute([$user['id']]);
$items = $stmt->fetchAll();
echo json_encode(['items'=>$items]);
