<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function rate_limit_or_fail(string $bucket): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $hash = sha1($bucket . '|' . $ip . '|' . gmdate('Y-m-d-H-i'));

    $dir = app_root() . '/storage/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $file = $dir . '/ratelimit_' . $hash . '.log';
    $count = file_exists($file) ? (int) file_get_contents($file) : 0;
    $count++;
    file_put_contents($file, (string) $count);

    $max = (int) cfg('RATE_LIMIT_PER_MINUTE', 20);
    if ($count > $max) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Rate limit exceeded']);
        exit;
    }
}
