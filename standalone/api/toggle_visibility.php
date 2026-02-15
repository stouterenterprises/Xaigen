<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';

header('Content-Type: application/json');
require_installation();
start_session();
$isAdmin = !empty($_SESSION['admin_user_id']);
$user = current_user();
if (!$isAdmin && !$user) { http_response_code(403); echo json_encode(['error'=>'Login required']); exit; }

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
$id = trim((string)($payload['id'] ?? ''));
if ($id === '') { http_response_code(400); echo json_encode(['error'=>'id required']); exit; }

if ($isAdmin) {
    $stmt = db()->prepare('UPDATE generations SET is_public = CASE WHEN is_public=1 THEN 0 ELSE 1 END WHERE id=?');
    $stmt->execute([$id]);
    $read = db()->prepare('SELECT is_public FROM generations WHERE id=? LIMIT 1');
    $read->execute([$id]);
} else {
    $stmt = db()->prepare('UPDATE generations SET is_public = CASE WHEN is_public=1 THEN 0 ELSE 1 END WHERE id=? AND user_id=?');
    $stmt->execute([$id, $user['id']]);
    $read = db()->prepare('SELECT is_public FROM generations WHERE id=? AND user_id=? LIMIT 1');
    $read->execute([$id, $user['id']]);
}

$row = $read->fetch();

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Generation not found']);
    exit;
}

echo json_encode(['ok'=>true,'is_public'=>(int)($row['is_public'] ?? 0)]);
