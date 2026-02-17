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

function normalize_provider_base_url(string $provider, string $baseUrl): string
{
    $provider = strtolower(trim($provider));
    $baseUrl = rtrim(trim($baseUrl), '/');
    if ($baseUrl === '') {
        return provider_default_base_url($provider);
    }

    if (!preg_match('#^https?://#i', $baseUrl)) {
        $baseUrl = 'https://' . ltrim($baseUrl, '/');
    }

    $parts = parse_url($baseUrl);
    if (!is_array($parts)) {
        return $baseUrl;
    }

    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = rtrim((string) ($parts['path'] ?? ''), '/');
    $isOpenRouterHost = $host === 'openrouter.ai' || str_ends_with($host, '.openrouter.ai');
    if ($isOpenRouterHost) {
        if ($path === '' || $path === '/' || $path === '/api') {
            return 'https://openrouter.ai/api/v1';
        }

        if ($path === '/v1' || str_starts_with($path, '/v1/')) {
            return 'https://openrouter.ai/api/v1';
        }

        if (!str_starts_with($path, '/api/v1')) {
            return 'https://openrouter.ai/api/v1';
        }

        return 'https://openrouter.ai/api/v1';
    }

    if ($host === 'api.x.ai') {
        if ($path === '' || $path === '/') {
            return 'https://api.x.ai/v1';
        }

        if ($path === '/api') {
            return 'https://api.x.ai/v1';
        }

        if ($path === '/v1' || str_starts_with($path, '/v1/')) {
            return 'https://api.x.ai/v1';
        }
    }

    if ($provider !== 'openrouter') {
        return $baseUrl;
    }

    return $baseUrl;
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
            return normalize_provider_base_url($provider, $val);
        }
    }

    return normalize_provider_base_url($provider, provider_default_base_url($provider));
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
        'base_url' => normalize_provider_base_url($provider, $baseUrl),
        'api_key' => $apiKey,
    ];
}

function describe_xai_error(array $body): string
{
    $candidates = [
        $body['error']['message'] ?? null,
        $body['error'] ?? null,
        $body['code'] ?? null,
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
    $baseUrl = normalize_provider_base_url($provider, $baseUrl);

    $providerLabel = strtoupper($provider === '' ? 'xai' : $provider);

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
        throw new RuntimeException($providerLabel . ' request failed (' . $method . ' ' . $endpoint . '): ' . curl_error($ch));
    }
    curl_close($ch);

    $json = json_decode($response, true);
    $body = is_array($json) ? $json : ['raw' => $response];

    if ($code >= 400) {
        $extraHint = '';
        if (
            $provider === 'openrouter'
            && strtoupper($method) === 'POST'
            && ($endpoint === '/videos/generations' || $endpoint === '/images/generations')
            && $code === 404
        ) {
            $extraHint = ' OpenRouter returned 404 for ' . $endpoint . '. Confirm OPENROUTER_BASE_URL resolves to https://openrouter.ai/api/v1 and the selected model supports this generation endpoint.';
        }

        if (
            $provider === 'xai'
            && strtoupper($method) === 'POST'
            && ($endpoint === '/video/generations' || $endpoint === '/videos/generations')
            && $code === 403
        ) {
            $extraHint = ' xAI denied video generation for this key/account. Confirm your team has Grok video access enabled and sufficient credits.';
        }

        if (
            $provider === 'xai'
            && strtoupper($method) === 'POST'
            && ($endpoint === '/video/generations' || $endpoint === '/videos/generations')
            && $code === 404
        ) {
            $extraHint = ' xAI could not find this video model for your account. Use an accessible model id (for example grok-video-latest) or update Admin → Models.';
        }

        throw new RuntimeException(sprintf(
            '%s %s %s returned HTTP %d: %s%s',
            $providerLabel,
            strtoupper($method),
            $endpoint,
            $code,
            describe_xai_error($body),
            $extraHint
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

function openrouter_model_looks_text_only_for_media(string $modelKey): bool
{
    $normalized = strtolower(trim($modelKey));
    if ($normalized === '') {
        return false;
    }

    $textOnlyTokens = [
        'dolphin',
        'venice',
        'hermes',
        'qwen',
        'llama',
        'mixtral',
        'mistral',
        'gpt',
        'claude',
    ];

    foreach ($textOnlyTokens as $token) {
        if (str_contains($normalized, $token)) {
            return true;
        }
    }

    return false;
}

function openrouter_model_key_candidates(string $modelKey): array
{
    $original = trim($modelKey);
    if ($original === '') {
        return [];
    }

    $candidates = [$original];
    $knownMappings = [
        'nous-hermes-2-mixtral-8x7b'  => 'nousresearch/nous-hermes-2-mixtral-8x7b-dpo',
        'nous/hermes-2-mixtral-8x7b'  => 'nousresearch/nous-hermes-2-mixtral-8x7b-dpo',
        // dolphin3.0-mistral-24b has no active endpoints on OpenRouter; do not fall back to it
        'josiefied-qwen3-8b'         => 'qwen/qwen3-8b',
        'josiefied/qwen3-8b'         => 'qwen/qwen3-8b',
    ];

    $normalized = strtolower($original);
    if (isset($knownMappings[$normalized])) {
        $candidates[] = $knownMappings[$normalized];
    }

    if (!str_contains($original, '/') && str_contains($original, '-')) {
        $candidates[] = preg_replace('/-/', '/', $original, 1) ?: $original;
    }

    $seen = [];
    $unique = [];
    foreach ($candidates as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate === '') {
            continue;
        }
        $key = strtolower($candidate);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $unique[] = $candidate;
    }

    return $unique;
}

function openrouter_is_invalid_model_id_error(Throwable $error): bool
{
    $message = strtolower($error->getMessage());

    // HTTP 400: "not a valid model ID" or "invalid model"
    if (str_contains($message, 'returned http 400')
        && (str_contains($message, 'not a valid model id') || str_contains($message, 'invalid model'))
    ) {
        return true;
    }

    // HTTP 404: "no endpoints found" — model exists but has no active providers
    if (str_contains($message, 'returned http 404')
        && str_contains($message, 'no endpoints found')
    ) {
        return true;
    }

    return false;
}

function xai_request_with_openrouter_model_fallback(
    string $method,
    string $endpoint,
    array $payload,
    array $apiSettings,
    array $modelCandidates
): array {
    $provider = strtolower(trim((string) ($apiSettings['provider'] ?? 'xai')));
    if ($provider !== 'openrouter' || !$modelCandidates) {
        return xai_request($method, $endpoint, $payload, $apiSettings);
    }

    $lastError = null;
    foreach ($modelCandidates as $candidate) {
        $payload['model'] = $candidate;
        try {
            return xai_request($method, $endpoint, $payload, $apiSettings);
        } catch (RuntimeException $error) {
            $lastError = $error;
            if (!openrouter_is_invalid_model_id_error($error)) {
                throw $error;
            }
        }
    }

    if ($lastError instanceof RuntimeException) {
        throw new RuntimeException(
            $lastError->getMessage()
            . ' Attempted OpenRouter model id fallbacks: '
            . implode(', ', $modelCandidates)
            . '.'
        );
    }

    return xai_request($method, $endpoint, $payload, $apiSettings);
}

function assert_openrouter_media_model_supported(array $apiSettings, array $job, string $endpoint): void
{
    $provider = strtolower(trim((string) ($apiSettings['provider'] ?? 'xai')));
    if ($provider !== 'openrouter') {
        return;
    }

    $modelKey = trim((string) ($job['model_key'] ?? ''));
    if (!openrouter_model_looks_text_only_for_media($modelKey)) {
        return;
    }

    throw new RuntimeException(
        'Selected OpenRouter model "' . $modelKey . '" appears to be text/chat-only and does not support '
        . $endpoint
        . '. Dolphin/Venice-style free models can work for chat completion, but image/video generation requires a model that explicitly supports OpenRouter media endpoints.'
    );
}

function openrouter_media_model_requires_chat_bridge(array $apiSettings, array $job): bool
{
    $provider = strtolower(trim((string) ($apiSettings['provider'] ?? 'xai')));
    if ($provider !== 'openrouter') {
        return false;
    }

    $modelKey = trim((string) ($job['model_key'] ?? ''));
    if ($modelKey === '') {
        return false;
    }

    return openrouter_model_looks_text_only_for_media($modelKey);
}

function generate_openrouter_chat_bridge(
    array $job,
    array $apiSettings,
    string $prompt,
    string $negativePrompt,
    string $mediaType
): array {
    $modelKey = trim((string) ($job['model_key'] ?? ''));
    $instructions = 'The user attempted a ' . $mediaType . ' generation request using OpenRouter model "' . $modelKey . '". '
        . 'This model is typically chat/text-first. Rewrite the user input into the best possible prompt for that model and explain that no media file will be produced from chat-only endpoints.';

    if ($negativePrompt !== '') {
        $instructions .= ' Include the negative prompt constraints when relevant.';
    }

    $payload = [
        'model' => $modelKey,
        'messages' => [
            ['role' => 'system', 'content' => $instructions],
            ['role' => 'user', 'content' => "Prompt:\n" . $prompt . ($negativePrompt !== '' ? "\n\nNegative prompt:\n" . $negativePrompt : '')],
        ],
        'temperature' => 0.7,
    ];

    $response = xai_request_with_openrouter_model_fallback(
        'POST',
        '/chat/completions',
        $payload,
        $apiSettings,
        openrouter_model_key_candidates($modelKey)
    );
    $content = trim((string) ($response['body']['choices'][0]['message']['content'] ?? ''));
    $response['body']['_chat_bridge'] = true;
    $response['body']['_chat_bridge_media_type'] = $mediaType;
    $response['body']['_chat_bridge_content'] = $content;
    $response['body']['_chat_bridge_message'] = 'Selected OpenRouter model "' . $modelKey . '" appears chat-only, so the generator routed the request to /chat/completions instead of media endpoints.';

    return $response;
}

function openrouter_image_size_from_params(array $params): string
{
    // Map aspect_ratio + resolution to a standard OpenAI-style size string.
    $aspectRatio = trim((string) ($params['aspect_ratio'] ?? '1:1'));
    $resolution = strtolower(trim((string) ($params['resolution'] ?? '')));
    $is2k = ($resolution === '2k' || $resolution === '2048' || str_contains($resolution, '2048'));

    $base = $is2k ? 2048 : 1024;

    // Common aspect ratio → size mappings
    $ratioMap = [
        '1:1'  => [$base, $base],
        '16:9' => [$is2k ? 2048 : 1792, $is2k ? 1152 : 1024],
        '9:16' => [$is2k ? 1152 : 1024, $is2k ? 2048 : 1792],
        '4:3'  => [$is2k ? 2048 : 1024, $is2k ? 1536 : 768],
        '3:4'  => [$is2k ? 1536 : 768, $is2k ? 2048 : 1024],
        '3:2'  => [$is2k ? 2048 : 1216, $is2k ? 1365 : 832],
        '2:3'  => [$is2k ? 1365 : 832, $is2k ? 2048 : 1216],
    ];

    [$w, $h] = $ratioMap[$aspectRatio] ?? [$base, $base];
    return $w . 'x' . $h;
}

function openrouter_image_payload(array $xaiPayload, array $params): array
{
    // Build a payload that OpenRouter image models understand.
    // OpenRouter proxies to providers that use OpenAI-compatible parameters;
    // xAI-specific fields like resolution ('1k'/'2k') are not understood there.
    $payload = array_filter([
        'model'           => $xaiPayload['model'] ?? null,
        'prompt'          => $xaiPayload['prompt'] ?? null,
        'negative_prompt' => ($xaiPayload['negative_prompt'] ?? '') !== '' ? $xaiPayload['negative_prompt'] : null,
        'n'               => 1,
        'size'            => openrouter_image_size_from_params($params),
        'seed'            => isset($xaiPayload['seed']) ? (int) $xaiPayload['seed'] : null,
    ], static fn($v) => $v !== null && $v !== '');

    // Pass through input image reference if present (image-to-image); field name varies by model.
    if (!empty($xaiPayload['image_url'])) {
        $payload['image_url'] = $xaiPayload['image_url'];
    }

    return $payload;
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
        'image_url' => $params['input_image'] ?? null,
    ];

    $payload = array_filter($payload, static fn($value) => $value !== null && $value !== '');

    $apiSettings = resolve_model_api_settings($model);
    if (openrouter_media_model_requires_chat_bridge($apiSettings, $job)) {
        return generate_openrouter_chat_bridge($job, $apiSettings, $prompt, $negativePrompt, 'image');
    }

    assert_openrouter_media_model_supported($apiSettings, $job, '/images/generations');

    // OpenRouter image models use standard OpenAI image parameters; strip xAI-specific fields.
    if ($apiSettings['provider'] === 'openrouter') {
        $payload = openrouter_image_payload($payload, $params);
    }

    return xai_request_with_openrouter_model_fallback(
        'POST',
        '/images/generations',
        $payload,
        $apiSettings,
        openrouter_model_key_candidates((string) $payload['model'])
    );
}

function max_video_duration_for_provider(string $provider): float
{
    $provider = strtolower(trim($provider));
    return match ($provider) {
        'openrouter' => 10.0,
        default => 10.0,
    };
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

    $requestedModelKey = trim((string) ($job['model_key'] ?? ''));

    $payload = [
        'model' => $requestedModelKey,
        'prompt' => $prompt,
        'negative_prompt' => $supportsNegativePrompt ? $negativePrompt : null,
        'duration' => $params['duration_seconds'] ?? 5,
        'fps' => $params['fps'] ?? 24,
        'resolution' => normalize_xai_video_resolution((string) ($params['resolution'] ?? '720p')),
        'image_url' => $params['input_image'] ?? null,
        'video_url' => $params['input_video'] ?? null,
    ];

    $payload = array_filter($payload, static fn($value) => $value !== null && $value !== '');

    $apiSettings = resolve_model_api_settings($model);
    if (openrouter_media_model_requires_chat_bridge($apiSettings, $job)) {
        return generate_openrouter_chat_bridge($job, $apiSettings, $prompt, $negativePrompt, 'video');
    }

    // xAI uses /video/generations (singular); OpenRouter uses /videos/generations (plural)
    $videoPostEndpoint = $apiSettings['provider'] === 'openrouter' ? '/videos/generations' : '/video/generations';
    assert_openrouter_media_model_supported($apiSettings, $job, $videoPostEndpoint);
    try {
        return xai_request_with_openrouter_model_fallback(
            'POST',
            $videoPostEndpoint,
            $payload,
            $apiSettings,
            openrouter_model_key_candidates((string) $payload['model'])
        );
    } catch (RuntimeException $e) {
        if (!should_retry_video_model_with_fallback($e, $apiSettings, $requestedModelKey)) {
            throw $e;
        }

        $fallbackCandidates = resolve_video_model_fallback_candidates($requestedModelKey, $apiSettings);
        if (!$fallbackCandidates) {
            throw new RuntimeException(
                $e->getMessage()
                . ' No accessible xAI video model was discovered from GET /models. '
                . 'Update the model key in Admin → Models to a video model your team can access.'
            );
        }

        $attemptedFallbacks = [];
        $lastRetryError = $e;
        foreach ($fallbackCandidates as $fallback) {
            $payload['model'] = $fallback;
            $attemptedFallbacks[] = $fallback;
            try {
                return xai_request('POST', $videoPostEndpoint, $payload, $apiSettings);
            } catch (RuntimeException $retryError) {
                $lastRetryError = $retryError;
                if (!should_retry_video_model_with_fallback($retryError, $apiSettings, $fallback)) {
                    throw $retryError;
                }
            }
        }

        throw new RuntimeException(
            $lastRetryError->getMessage()
            . ' Automatic fallback was attempted after model '
            . $requestedModelKey
            . ' failed. Tried: '
            . implode(', ', $attemptedFallbacks)
            . '.'
        );
    }
}

function should_retry_video_model_with_fallback(RuntimeException $error, array $apiSettings, string $requestedModelKey): bool
{
    $provider = strtolower(trim((string) ($apiSettings['provider'] ?? 'xai')));
    if ($provider !== 'xai') {
        return false;
    }

    if ($requestedModelKey === '') {
        return false;
    }

    $message = strtolower(trim($error->getMessage()));
    if (!str_contains($message, 'returned http 404')) {
        return false;
    }

    return str_contains($message, 'does not exist or your team')
        || str_contains($message, 'model does not exist')
        || str_contains($message, 'model_not_found')
        // xAI returns "The requested resource was not found" for inaccessible video models
        || str_contains($message, 'requested resource was not found')
        || str_contains($message, 'resource was not found');
}

function resolve_video_model_fallback_candidates(string $requestedModelKey, array $apiSettings): array
{
    $requestedModelKey = trim($requestedModelKey);
    if ($requestedModelKey === '') {
        return [];
    }

    $candidates = [];
    foreach (list_accessible_video_models($apiSettings) as $candidate) {
        $candidate = trim($candidate);
        if ($candidate !== '') {
            $candidates[] = $candidate;
        }
    }

    foreach (video_model_alias_candidates($requestedModelKey) as $candidate) {
        $candidate = trim($candidate);
        if ($candidate !== '') {
            $candidates[] = $candidate;
        }
    }

    $seen = [];
    $fallbacks = [];
    foreach ($candidates as $candidate) {
        $normalized = strtolower($candidate);
        if (isset($seen[$normalized])) {
            continue;
        }
        $seen[$normalized] = true;
        if ($normalized === strtolower($requestedModelKey)) {
            continue;
        }
        $fallbacks[] = $candidate;
    }

    return $fallbacks;
}

function video_model_alias_candidates(string $requestedModelKey): array
{
    $requestedModelKey = strtolower(trim($requestedModelKey));
    $aliases = [
        'grok-2-video' => ['grok-video', 'grok-video-latest'],
        'grok-video' => ['grok-2-video', 'grok-video-latest'],
        'grok-video-latest' => ['grok-video', 'grok-2-video'],
    ];

    return $aliases[$requestedModelKey] ?? [];
}

function list_accessible_video_models(array $apiSettings): array
{
    try {
        $response = xai_request('GET', '/models', [], $apiSettings);
    } catch (Throwable) {
        return [];
    }

    $models = $response['body']['data'] ?? null;
    if (!is_array($models)) {
        return [];
    }

    $videoModels = [];
    foreach ($models as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $modelId = trim((string) ($entry['id'] ?? ''));
        if ($modelId === '') {
            continue;
        }

        $hasVideoModality = false;
        $modalities = $entry['modalities'] ?? null;
        if (is_array($modalities)) {
            foreach ($modalities as $modality) {
                if (is_string($modality) && strtolower(trim($modality)) === 'video') {
                    $hasVideoModality = true;
                    break;
                }
            }
        }

        if ($hasVideoModality || str_contains(strtolower($modelId), 'video')) {
            $videoModels[] = $modelId;
        }
    }

    return $videoModels;
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
    $jobId = rawurlencode($externalJobId);
    $provider = strtolower(trim((string) ($apiSettings['provider'] ?? 'xai')));
    $modelType = strtolower(trim((string) ($model['type'] ?? '')));

    $fallbackEndpoints = [];
    if ($modelType === 'video') {
        // xAI uses /video/generations/{id} (singular); try it first, then the plural form
        $fallbackEndpoints[] = '/video/generations/' . $jobId;
        $fallbackEndpoints[] = '/videos/generations/' . $jobId;
        $fallbackEndpoints[] = '/generations/' . $jobId;
    } elseif ($modelType === 'image') {
        $fallbackEndpoints[] = '/images/generations/' . $jobId;
        $fallbackEndpoints[] = '/image/generations/' . $jobId;
        $fallbackEndpoints[] = '/generations/' . $jobId;
    } else {
        $fallbackEndpoints[] = '/video/generations/' . $jobId;
        $fallbackEndpoints[] = '/videos/generations/' . $jobId;
        $fallbackEndpoints[] = '/images/generations/' . $jobId;
        $fallbackEndpoints[] = '/image/generations/' . $jobId;
        $fallbackEndpoints[] = '/generations/' . $jobId;
    }

    if ($provider === 'openrouter') {
        $primaryEndpoints = $fallbackEndpoints;
        $primaryEndpoints[] = '/jobs/' . $jobId;
    } else {
        $primaryEndpoints = ['/jobs/' . $jobId, ...$fallbackEndpoints];
    }

    $attempted = [];
    $lastNotFound = null;
    foreach ($primaryEndpoints as $endpoint) {
        if (isset($attempted[$endpoint])) {
            continue;
        }
        $attempted[$endpoint] = true;
        try {
            return xai_request('GET', $endpoint, [], $apiSettings);
        } catch (RuntimeException $error) {
            if (!str_contains(strtolower($error->getMessage()), 'returned http 404')) {
                throw $error;
            }
            $lastNotFound = $error;
        }
    }

    if ($lastNotFound instanceof RuntimeException) {
        throw $lastNotFound;
    }

    throw new RuntimeException('Unable to poll provider job status. No valid polling endpoint responded.');
}
