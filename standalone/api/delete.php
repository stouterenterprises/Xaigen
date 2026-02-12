<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json');
require_installation();

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing id']);
    exit;
}

$stmt = db()->prepare('DELETE FROM generations WHERE id = ? LIMIT 1');
$stmt->execute([$id]);

echo json_encode(['ok' => true, 'deleted' => $stmt->rowCount() > 0]);
