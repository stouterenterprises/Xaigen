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

    $generationMode = strtolower(trim((string) ($payload['generation_mode'] ?? 'create')));
    if (!in_array($generationMode, ['create', 'extend'], true)) {
        throw new InvalidArgumentException('Invalid generation_mode.');
    }
    if ($modelKey === '') {
        throw new InvalidArgumentException('model_key is required.');
    }
    if ($prompt === '') {
        throw new InvalidArgumentException('prompt is required.');
    }

    return [
        'generation_mode' => $generationMode,
        'type' => $type,
        'model_key' => $modelKey,
        'prompt' => $prompt,
        'negative_prompt' => $negative,
        'seed' => isset($payload['seed']) ? (int) $payload['seed'] : null,
        'aspect_ratio' => (string) ($payload['aspect_ratio'] ?? '16:9'),
        'resolution' => (string) ($payload['resolution'] ?? '1k'),
        'duration_seconds' => isset($payload['duration_seconds']) ? (float) $payload['duration_seconds'] : 5.0,
        'fps' => isset($payload['fps']) ? (int) $payload['fps'] : 24,
        'character_ids' => array_values(array_filter(array_map('strval', (array)($payload['character_ids'] ?? [])))),
        'scene_id' => trim((string)($payload['scene_id'] ?? '')),
        'part_ids' => array_values(array_filter(array_map('strval', (array)($payload['part_ids'] ?? [])))),
        'input_image' => trim((string) ($payload['input_image'] ?? '')),
        'input_video' => trim((string) ($payload['input_video'] ?? '')),
        'extend_video' => trim((string) ($payload['extend_video'] ?? '')),
        'extend_to_provider_max' => !empty($payload['extend_to_provider_max']),
    ];
}
