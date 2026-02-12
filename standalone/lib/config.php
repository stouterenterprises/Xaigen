<?php

declare(strict_types=1);

function app_root(): string
{
    return dirname(__DIR__);
}

function load_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $samplePath = app_root() . '/config.sample.php';
    $localPath = app_root() . '/config.local.php';

    $sample = file_exists($samplePath) ? require $samplePath : [];
    $local = file_exists($localPath) ? require $localPath : [];

    $config = array_merge($sample, $local);
    return $config;
}

function cfg(string $key, mixed $default = null): mixed
{
    $config = load_config();
    return $config[$key] ?? $default;
}

function require_installation(): void
{
    $lock = app_root() . '/installed.lock';
    if (!file_exists($lock)) {
        header('Location: /installer/index.php');
        exit;
    }
}

function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}
