<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_installation();
$id = $_GET['id'] ?? '';
$stmt = db()->prepare('SELECT * FROM generations WHERE id=? LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch();
if(!$row || empty($row['output_path'])){http_response_code(404);echo 'Not found';exit;}
$path = $row['output_path'];
if (preg_match('#^https?://#', $path)) { header('Location: '.$path); exit; }
$full = realpath(app_root() . '/storage/generated/' . basename($path));
if (!$full || !file_exists($full)) { http_response_code(404); echo 'Missing file'; exit; }
header('Content-Type: '.($row['output_mime'] ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="output_'.basename($full).'"');
readfile($full);
