<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app_settings.php';

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
    return provider_base_url('xai');
}

function provider_default_base_url(string $provider): string
{
    return match (strtolower(trim($provider))) {
        'openrouter' => 'https://openrouter.ai/api/v1',
        default => 'https://api.x.ai/v1',
    };
}

function provider_base_url(string $provider): string
{
    $provider = strtolower(trim($provider));
    if ($provider === '') {
        $provider = 'xai';
    }

    $baseUrlKeyName = strtoupper($provider) . '_BASE_URL';
    $rows = get_active_api_key_rows($provider, $baseUrlKeyName);
    if ($rows) {
        $val = trim(decrypt_secret((string) $rows[0]['key_value_encrypted']));
        if ($val !== '') {
            return rtrim($val, '/');
        }
    }

    return provider_default_base_url($provider);
}

function resolve_model_api_settings(array $model): array
{
    $provider = strtolower(trim((string) ($model['api_provider'] ?? 'xai')));
    if ($provider === '') {
        $provider = 'xai';
    }

    $baseUrl = trim((string) ($model['api_base_url'] ?? ''));
    if ($baseUrl === '') {
        $baseUrl = provider_base_url($provider);
    }

    $apiKey = trim((string) ($model['api_key_plain'] ?? ''));
    if ($apiKey === '' && !empty($model['api_key_encrypted'])) {
        $apiKey = trim((string) decrypt_secret((string) $model['api_key_encrypted']));
    }
    if ($apiKey === '') {
        $fallbackKeyName = strtoupper($provider) . '_API_KEY';
        $apiKey = trim((string) (round_robin_pick_api_key($provider, $fallbackKeyName) ?? ''));
    }
    return [
        'provider' => $provider,
        'base_url' => rtrim($baseUrl, '/'),
        'api_key' => $apiKey,
    ];
}

function describe_xai_error(array $body): string
{
    $candidates = [
        $body['error']['message'] ?? null,
        $body['error_description'] ?? null,
        $body['message'] ?? null,
        $body['detail'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            return trim($candidate);
        }
    }

    $encoded = json_encode($body, JSON_UNESCAPED_SLASHES);
    return is_string($encoded) && $encoded !== '' ? substr($encoded, 0, 600) : 'No additional details returned by provider.';
}

function xai_request(string $method, string $endpoint, array $payload, array $apiSettings): array
{
    $apiKey = trim((string) ($apiSettings['api_key'] ?? ''));
    if (!$apiKey) {
        throw new RuntimeException('No active API key configured for this model/provider.');
    }

    $baseUrl = trim((string) ($apiSettings['base_url'] ?? ''));
    $provider = (string) ($apiSettings['provider'] ?? 'xai');
    if ($baseUrl === '') {
        $baseUrl = xai_base_url();
    }

    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ];
    if ($provider === 'openrouter') {
        $headers[] = 'HTTP-Referer: ' . (string) cfg('APP_URL', 'http://localhost');
        $headers[] = 'X-Title: GetYourPics.com';
    }

    $ch = curl_init(rtrim($baseUrl, '/') . $endpoint);
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => (int) cfg('XAI_TIMEOUT_SECONDS', 60),
    ];

    if (strtoupper($method) !== 'GET') {
        $curlOptions[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    curl_setopt_array($ch, $curlOptions);

    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) {
        throw new RuntimeException('xAI request failed (' . $method . ' ' . $endpoint . '): ' . curl_error($ch));
    }
    curl_close($ch);

    $json = json_decode($response, true);
    $body = is_array($json) ? $json : ['raw' => $response];

    if ($code >= 400) {
        throw new RuntimeException(sprintf(
            'xAI %s %s returned HTTP %d: %s',
            strtoupper($method),
            $endpoint,
            $code,
            describe_xai_error($body)
        ));
    }

    return ['status' => $code, 'body' => $body];
}

function merge_default_prompt(string $defaultPrompt, string $userPrompt): string
{
    $defaultPrompt = trim($defaultPrompt);
    $userPrompt = trim($userPrompt);
    if ($defaultPrompt === '') {
        return $userPrompt;
    }
    if ($userPrompt === '') {
        return $defaultPrompt;
    }
    return $defaultPrompt . "\n\n" . $userPrompt;
}

function merge_default_negative_prompt(string $defaultNegativePrompt, string $userNegativePrompt): string
{
    $defaultNegativePrompt = trim($defaultNegativePrompt);
    $userNegativePrompt = trim($userNegativePrompt);
    if ($defaultNegativePrompt === '') {
        return $userNegativePrompt;
    }
    if ($userNegativePrompt === '') {
        return $defaultNegativePrompt;
    }
    return $userNegativePrompt . "\n" . $defaultNegativePrompt;
}

function generate_image(array $job, bool $supportsNegativePrompt, array $model): array
{
    $params = json_decode((string) $job['params_json'], true) ?: [];
    $defaults = get_generation_defaults();
    $prompt = merge_default_prompt((string) ($defaults['custom_prompt'] ?? ''), (string) $job['prompt']);
    $negativePrompt = merge_default_negative_prompt((string) ($defaults['custom_negative_prompt'] ?? ''), (string) ($job['negative_prompt'] ?? ''));

    if (!$supportsNegativePrompt && $negativePrompt !== '') {
        $prompt .= "\nAvoid: " . $negativePrompt;
        $params['negative_prompt_fallback'] = true;
    }

    $payload = [
        'model' => $job['model_key'],
        'prompt' => $prompt,
        'negative_prompt' => $supportsNegativePrompt ? $negativePrompt : null,
        'seed' => $params['seed'] ?? null,
        'resolution' => normalize_xai_image_resolution((string) ($params['resolution'] ?? '1k')),
        'aspect_ratio' => $params['aspect_ratio'] ?? '1:1',
    ];

    $apiSettings = resolve_model_api_settings($model);
    return xai_request('POST', '/images/generations', $payload, $apiSettings);
}

function normalize_xai_image_resolution(string $resolution): string
{
    $normalized = strtolower(trim($resolution));
    if ($normalized === '') {
        return '1k';
    }

    $map = [
        '1k' => '1k',
        '1024' => '1k',
        '1024x1024' => '1k',
        '2k' => '2k',
        '2048' => '2k',
        '2048x2048' => '2k',
    ];

    if (isset($map[$normalized])) {
        return $map[$normalized];
    }

    return str_contains($normalized, '2048') || str_contains($normalized, '2k') ? '2k' : '1k';
}

function generate_video(array $job, bool $supportsNegativePrompt, array $model): array
{
    $params = json_decode((string) $job['params_json'], true) ?: [];
    $defaults = get_generation_defaults();
    $prompt = merge_default_prompt((string) ($defaults['custom_prompt'] ?? ''), (string) $job['prompt']);
    $negativePrompt = merge_default_negative_prompt((string) ($defaults['custom_negative_prompt'] ?? ''), (string) ($job['negative_prompt'] ?? ''));

    if (!$supportsNegativePrompt && $negativePrompt !== '') {
        $prompt .= "\nAvoid: " . $negativePrompt;
        $params['negative_prompt_fallback'] = true;
    }

    $payload = [
        'model' => $job['model_key'],
        'prompt' => $prompt,
        'negative_prompt' => $supportsNegativePrompt ? $negativePrompt : null,
        'duration' => $params['duration_seconds'] ?? 5,
        'fps' => $params['fps'] ?? 24,
        'resolution' => normalize_xai_video_resolution((string) ($params['resolution'] ?? '720p')),
    ];

    $apiSettings = resolve_model_api_settings($model);
    return xai_request('POST', '/videos/generations', $payload, $apiSettings);
}

function normalize_xai_video_resolution(string $resolution): string
{
    $normalized = strtolower(trim($resolution));
    if ($normalized === '') {
        return '720p';
    }

    $map = [
        '480p' => '480p',
        '854x480' => '480p',
        '640x480' => '480p',
        '720p' => '720p',
        '1280x720' => '720p',
        '1024x1024' => '720p',
        '1k' => '720p',
        '2k' => '720p',
    ];

    if (isset($map[$normalized])) {
        return $map[$normalized];
    }

    return str_contains($normalized, '480') ? '480p' : '720p';
}

function poll_job(string $externalJobId, array $model = []): array
{
    $apiSettings = resolve_model_api_settings($model);
    return xai_request('GET', '/jobs/' . rawurlencode($externalJobId), [], $apiSettings);
}
