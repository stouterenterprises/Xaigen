<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json');
require_installation();
$items = db()->query('SELECT * FROM generations ORDER BY created_at DESC LIMIT 100')->fetchAll();
echo json_encode(['items'=>$items]);
