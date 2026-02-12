<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
header('Content-Type: application/json');
require_installation();
start_session();
$user = current_user();
if (!$user) { http_response_code(403); echo json_encode(['error'=>'Login required']); exit; }
$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
$id = trim((string)($payload['id'] ?? ''));
if ($id === '') { http_response_code(400); echo json_encode(['error'=>'id required']); exit; }
$stmt = db()->prepare('UPDATE generations SET is_public = CASE WHEN is_public=1 THEN 0 ELSE 1 END WHERE id=? AND user_id=?');
$stmt->execute([$id, $user['id']]);
$read = db()->prepare('SELECT is_public FROM generations WHERE id=? AND user_id=? LIMIT 1');
$read->execute([$id, $user['id']]);
$row = $read->fetch();
echo json_encode(['ok'=>true,'is_public'=>(int)($row['is_public'] ?? 0)]);
