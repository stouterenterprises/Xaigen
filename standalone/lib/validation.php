<?php

declare(strict_types=1);

function json_input(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function validate_generation_payload(array $payload): array
{
    $type = $payload['type'] ?? 'image';
    $modelKey = trim((string) ($payload['model_key'] ?? ''));
    $prompt = trim((string) ($payload['prompt'] ?? ''));
    $negative = trim((string) ($payload['negative_prompt'] ?? ''));

    if (!in_array($type, ['image', 'video'], true)) {
        throw new InvalidArgumentException('Invalid type.');
    }
    if ($modelKey === '') {
        throw new InvalidArgumentException('model_key is required.');
    }
    if ($prompt === '') {
        throw new InvalidArgumentException('prompt is required.');
    }

    return [
        'type' => $type,
        'model_key' => $modelKey,
        'prompt' => $prompt,
        'negative_prompt' => $negative,
        'seed' => isset($payload['seed']) ? (int) $payload['seed'] : null,
        'aspect_ratio' => (string) ($payload['aspect_ratio'] ?? '16:9'),
        'resolution' => (string) ($payload['resolution'] ?? '1024x1024'),
        'duration_seconds' => isset($payload['duration_seconds']) ? (float) $payload['duration_seconds'] : 5.0,
        'fps' => isset($payload['fps']) ? (int) $payload['fps'] : 24,
    ];
}
