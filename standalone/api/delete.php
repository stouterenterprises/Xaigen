<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';

header('Content-Type: application/json');
require_installation();
start_session();
$user = current_user();
if (!$user) { http_response_code(403); echo json_encode(['error'=>'Login required']); exit; }

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing id']);
    exit;
}

$stmt = db()->prepare('DELETE FROM generations WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$id, $user['id']]);

echo json_encode(['ok' => true, 'deleted' => $stmt->rowCount() > 0]);
