<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/config.php';

function get_active_api_key_rows(string $provider, string $keyName): array
{
    $stmt = db()->prepare('SELECT * FROM api_keys WHERE provider = ? AND key_name = ? AND is_active = 1 ORDER BY created_at ASC');
    $stmt->execute([$provider, $keyName]);
    return $stmt->fetchAll();
}

function round_robin_pick_api_key(string $provider = 'xai', string $keyName = 'XAI_API_KEY'): ?string
{
    $rows = get_active_api_key_rows($provider, $keyName);
    if (!$rows) {
        return null;
    }

    $key = 'rr_' . $provider . '_' . $keyName;
    $counterFile = app_root() . '/storage/logs/' . $key . '.counter';
    if (!is_dir(dirname($counterFile))) { mkdir(dirname($counterFile), 0775, true); }
    $current = file_exists($counterFile) ? (int) file_get_contents($counterFile) : 0;
    $index = $current % count($rows);
    file_put_contents($counterFile, (string) ($current + 1));

    $enc = (string) $rows[$index]['key_value_encrypted'];
    return decrypt_secret($enc);
}

function xai_base_url(): string
{
    $rows = get_active_api_key_rows('xai', 'XAI_BASE_URL');
    if ($rows) {
        $val = trim(decrypt_secret((string) $rows[0]['key_value_encrypted']));
        if ($val !== '') {
            return rtrim($val, '/');
        }
    }
    return 'https://api.x.ai/v1';
}

function xai_request(string $method, string $endpoint, array $payload): array
{
    $apiKey = round_robin_pick_api_key();
    if (!$apiKey) {
        throw new RuntimeException('No active XAI_API_KEY configured.');
    }

    $ch = curl_init(xai_base_url() . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => (int) cfg('XAI_TIMEOUT_SECONDS', 60),
    ]);

    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) {
        throw new RuntimeException('xAI request failed: ' . curl_error($ch));
    }
    curl_close($ch);

    $json = json_decode($response, true);
    return ['status' => $code, 'body' => is_array($json) ? $json : ['raw' => $response]];
}

function generate_image(array $job, bool $supportsNegativePrompt): array
{
    $prompt = $job['prompt'];
    $params = json_decode((string) $job['params_json'], true) ?: [];

    if (!$supportsNegativePrompt && !empty($job['negative_prompt'])) {
        $prompt .= "\nAvoid: " . $job['negative_prompt'];
        $params['negative_prompt_fallback'] = true;
    }

    $payload = [
        'model' => $job['model_key'],
        'prompt' => $prompt,
        'negative_prompt' => $supportsNegativePrompt ? $job['negative_prompt'] : null,
        'seed' => $params['seed'] ?? null,
        'size' => $params['resolution'] ?? '1024x1024',
    ];

    return xai_request('POST', '/images/generations', $payload);
}

function generate_video(array $job, bool $supportsNegativePrompt): array
{
    $prompt = $job['prompt'];
    $params = json_decode((string) $job['params_json'], true) ?: [];

    if (!$supportsNegativePrompt && !empty($job['negative_prompt'])) {
        $prompt .= "\nAvoid: " . $job['negative_prompt'];
        $params['negative_prompt_fallback'] = true;
    }

    $payload = [
        'model' => $job['model_key'],
        'prompt' => $prompt,
        'negative_prompt' => $supportsNegativePrompt ? $job['negative_prompt'] : null,
        'duration' => $params['duration_seconds'] ?? 5,
        'fps' => $params['fps'] ?? 24,
        'resolution' => $params['resolution'] ?? '1280x720',
    ];

    return xai_request('POST', '/videos/generations', $payload);
}

function poll_job(string $externalJobId): array
{
    return xai_request('GET', '/jobs/' . rawurlencode($externalJobId), []);
}
