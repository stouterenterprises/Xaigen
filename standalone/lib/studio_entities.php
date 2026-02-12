<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function ensure_active_user_for_pages(): array
{
    $user = current_user();
    if (!$user || ($user['status'] ?? '') !== 'active') {
        header('Location: /app/login.php');
        exit;
    }
    return $user;
}

function visibility_bool_from_post(string $key = 'is_public'): int
{
    return !empty($_POST[$key]) ? 1 : 0;
}

function store_uploaded_media(array $file, string $group, array $allowedExtensions): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions, true)) {
        throw new InvalidArgumentException('Unsupported upload type.');
    }

    $baseDir = __DIR__ . '/../storage/uploads/' . $group;
    if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
        throw new RuntimeException('Could not create upload directory.');
    }

    $filename = uuidv4() . '.' . $ext;
    $target = $baseDir . '/' . $filename;
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
        throw new RuntimeException('Failed to save uploaded file.');
    }

    return '/storage/uploads/' . $group . '/' . $filename;
}

function extract_files_array(string $key): array
{
    if (empty($_FILES[$key]) || !isset($_FILES[$key]['name'])) {
        return [];
    }

    $files = [];
    $names = (array)$_FILES[$key]['name'];
    foreach (array_keys($names) as $idx) {
        $files[] = [
            'name' => $_FILES[$key]['name'][$idx] ?? '',
            'type' => $_FILES[$key]['type'][$idx] ?? '',
            'tmp_name' => $_FILES[$key]['tmp_name'][$idx] ?? '',
            'error' => $_FILES[$key]['error'][$idx] ?? UPLOAD_ERR_NO_FILE,
            'size' => $_FILES[$key]['size'][$idx] ?? 0,
        ];
    }

    return $files;
}
