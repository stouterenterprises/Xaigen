<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function get_generation_defaults(): array
{
    $defaults = [
        'custom_prompt' => '',
        'custom_negative_prompt' => '',
        'seed' => '',
        'aspect_ratio' => '16:9',
        'resolution' => '1k',
        'duration_seconds' => '5',
        'fps' => '24',
    ];

    $row = db()->query("SELECT setting_value FROM app_settings WHERE setting_key='defaults_json' LIMIT 1")->fetch();
    $decoded = [];
    if (!empty($row['setting_value'])) {
        $decoded = json_decode((string) $row['setting_value'], true);
    }

    if (!is_array($decoded)) {
        return $defaults;
    }

    foreach ($defaults as $key => $value) {
        if (array_key_exists($key, $decoded) && $decoded[$key] !== null && $decoded[$key] !== '') {
            $defaults[$key] = (string) $decoded[$key];
        }
    }

    return $defaults;
}

function save_generation_defaults(array $input): void
{
    $payload = [
        'custom_prompt' => trim((string) ($input['custom_prompt'] ?? '')),
        'custom_negative_prompt' => trim((string) ($input['custom_negative_prompt'] ?? '')),
        'seed' => trim((string) ($input['seed'] ?? '')),
        'aspect_ratio' => trim((string) ($input['aspect_ratio'] ?? '16:9')),
        'resolution' => trim((string) ($input['resolution'] ?? '1k')),
        'duration_seconds' => trim((string) ($input['duration_seconds'] ?? '5')),
        'fps' => trim((string) ($input['fps'] ?? '24')),
    ];

    $stmt = db()->prepare("INSERT INTO app_settings (setting_key,setting_value,updated_at) VALUES ('defaults_json',?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=VALUES(updated_at)");
    $stmt->execute([json_encode($payload), now_utc()]);
}
