<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function encryption_key_bytes(): string
{
    $raw = (string) cfg('ENCRYPTION_KEY', '');
    if (str_starts_with($raw, 'base64:')) {
        $decoded = base64_decode(substr($raw, 7), true);
        if ($decoded !== false && strlen($decoded) === 32) {
            return $decoded;
        }
    }
    if (strlen($raw) === 32) {
        return $raw;
    }
    throw new RuntimeException('Invalid ENCRYPTION_KEY. Must be 32 bytes or base64: encoded 32 bytes.');
}

function encrypt_secret(string $plaintext): string
{
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', encryption_key_bytes(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Encryption failed.');
    }

    return base64_encode(json_encode([
        'iv' => bin2hex($iv),
        'tag' => bin2hex($tag),
        'ct' => bin2hex($ciphertext),
    ], JSON_THROW_ON_ERROR));
}

function decrypt_secret(string $payload): string
{
    $decoded = base64_decode($payload, true);
    if ($decoded === false) {
        return '';
    }
    $json = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);

    $iv = hex2bin((string) ($json['iv'] ?? '')) ?: '';
    $tag = hex2bin((string) ($json['tag'] ?? '')) ?: '';
    $ct = hex2bin((string) ($json['ct'] ?? '')) ?: '';

    $plaintext = openssl_decrypt($ct, 'aes-256-gcm', encryption_key_bytes(), OPENSSL_RAW_DATA, $iv, $tag);
    return $plaintext === false ? '' : $plaintext;
}
