<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json');
require_installation();
$id = $_GET['id'] ?? '';
$stmt = db()->prepare('SELECT * FROM generations WHERE id=? LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch();
if(!$row){http_response_code(404);echo json_encode(['error'=>'Not found']);exit;}
echo json_encode(['item'=>$row]);
