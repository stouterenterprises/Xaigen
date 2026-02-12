<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

function ensure_schema_migrations_table(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) UNIQUE NOT NULL,
        checksum VARCHAR(64) NOT NULL,
        applied_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function list_migration_files(): array
{
    $dir = app_root() . '/migrations';
    $files = glob($dir . '/*.sql') ?: [];
    sort($files);
    return $files;
}

function migration_status(): array
{
    ensure_schema_migrations_table();
    $rows = db()->query('SELECT filename, checksum, applied_at FROM schema_migrations ORDER BY filename')->fetchAll();
    $applied = [];
    foreach ($rows as $row) {
        $applied[$row['filename']] = $row;
    }

    $status = [];
    foreach (list_migration_files() as $path) {
        $filename = basename($path);
        $checksum = hash_file('sha256', $path);
        $state = 'pending';
        if (isset($applied[$filename])) {
            if ($applied[$filename]['checksum'] !== $checksum) {
                $state = 'checksum_mismatch';
            } else {
                $state = 'applied';
            }
        }
        $status[] = compact('filename', 'checksum', 'state');
    }
    return $status;
}

function migrate_if_needed(): void
{
    ensure_schema_migrations_table();
    $pdo = db();
    $existing = $pdo->query('SELECT filename, checksum FROM schema_migrations')->fetchAll();
    $applied = [];
    foreach ($existing as $row) {
        $applied[$row['filename']] = $row['checksum'];
    }

    foreach (list_migration_files() as $path) {
        $filename = basename($path);
        $checksum = hash_file('sha256', $path);
        if (isset($applied[$filename])) {
            if ($applied[$filename] !== $checksum) {
                throw new RuntimeException("Migration checksum mismatch for {$filename}. Execution halted.");
            }
            continue;
        }

        $sql = file_get_contents($path);
        $pdo->beginTransaction();
        try {
            $pdo->exec($sql);
            $stmt = $pdo->prepare('INSERT INTO schema_migrations (filename, checksum, applied_at) VALUES (?, ?, ?)');
            $stmt->execute([$filename, $checksum, now_utc()]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
