<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/migrations.php';
require_once __DIR__ . '/../lib/xai.php';

function extract_output_url(array $body): ?string
{
    $candidates = [
        $body['data'][0]['url'] ?? null,
        $body['data'][0]['output_url'] ?? null,
        $body['data'][0]['image_url'] ?? null,
        $body['data'][0]['video_url'] ?? null,
        $body['output_url'] ?? null,
        $body['image_url'] ?? null,
        $body['video_url'] ?? null,
        $body['result']['url'] ?? null,
        $body['response']['url'] ?? null,
        $body['result']['output_url'] ?? null,
        $body['result']['video_url'] ?? null,
        $body['result']['image_url'] ?? null,
        $body['result']['media_url'] ?? null,
        $body['output'][0]['url'] ?? null,
        $body['output'][0]['output_url'] ?? null,
        $body['artifacts'][0]['url'] ?? null,
        $body['artifacts'][0]['output_url'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            return trim($candidate);
        }
    }

    return null;
}

function extract_base64_output(array $body): ?string
{
    $candidates = [
        $body['data'][0]['b64_json'] ?? null,
        $body['result']['b64_json'] ?? null,
        $body['output'][0]['b64_json'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            return trim($candidate);
        }
    }

    return null;
}

function persist_base64_image(array $job, string $base64): ?array
{
    $binary = base64_decode($base64, true);
    if ($binary === false || $binary === '') {
        return null;
    }

    $storageDir = app_root() . '/storage/generated';
    if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
        return null;
    }

    $filename = $job['id'] . '_' . bin2hex(random_bytes(4)) . '.png';
    $fullPath = $storageDir . '/' . $filename;
    if (file_put_contents($fullPath, $binary) === false) {
        return null;
    }

    return ['path' => $filename, 'mime' => 'image/png'];
}

function extract_preview_url(array $body): ?string
{
    $candidates = [
        $body['preview_url'] ?? null,
        $body['preview_image_url'] ?? null,
        $body['thumbnail_url'] ?? null,
        $body['poster_url'] ?? null,
        $body['result']['preview_url'] ?? null,
        $body['result']['preview_image_url'] ?? null,
        $body['result']['thumbnail_url'] ?? null,
        $body['data'][0]['preview_url'] ?? null,
        $body['data'][0]['thumbnail_url'] ?? null,
        $body['artifacts'][0]['preview_url'] ?? null,
        $body['artifacts'][0]['thumbnail_url'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            return trim($candidate);
        }
    }

    return null;
}

function update_running_preview(array $job, string $previewUrl): void
{
    db()->prepare('UPDATE generations SET output_path=?, output_mime=? WHERE id=? AND status=\'running\'')
        ->execute([$previewUrl, 'image/png', $job['id']]);
}

function extract_external_job_id(array $body): ?string
{
    $candidates = [
        $body['id'] ?? null,
        $body['job_id'] ?? null,
        $body['jobId'] ?? null,
        $body['external_job_id'] ?? null,
        $body['externalJobId'] ?? null,
        $body['request_id'] ?? null,
        $body['requestId'] ?? null,
        $body['job']['id'] ?? null,
        $body['job']['job_id'] ?? null,
        $body['job']['jobId'] ?? null,
        $body['data'][0]['id'] ?? null,
        $body['data'][0]['job_id'] ?? null,
        $body['data'][0]['jobId'] ?? null,
        $body['result']['id'] ?? null,
        $body['result']['job_id'] ?? null,
        $body['result']['jobId'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            return trim($candidate);
        }
    }

    return null;
}

function mark_succeeded(array $job, ?string $external, string $output): void
{
    $mime = $job['type'] === 'video' ? 'video/mp4' : 'image/png';
    db()->prepare("UPDATE generations SET status='succeeded', external_job_id=?, output_path=?, output_mime=?, error_message=NULL, finished_at=? WHERE id=?")
        ->execute([$external, $output, $mime, now_utc(), $job['id']]);
}

function mark_failed(array $job, string $message): void
{
    db()->prepare("UPDATE generations SET status='failed', error_message=?, finished_at=? WHERE id=?")
        ->execute([$message, now_utc(), $job['id']]);
}

function elapsed_seconds(array $job): ?int
{
    $startedAt = (string) ($job['started_at'] ?? $job['created_at'] ?? '');
    if ($startedAt === '') {
        return null;
    }

    $startedTs = strtotime($startedAt);
    if ($startedTs === false) {
        return null;
    }

    return max(0, time() - $startedTs);
}

function process_running_job(array $job): array
{
    $timeoutSeconds = (int) cfg('GENERATION_TIMEOUT_SECONDS', 3600);
    if ($timeoutSeconds > 0) {
        $elapsed = elapsed_seconds($job);
        if ($elapsed !== null && $elapsed >= $timeoutSeconds) {
            $message = sprintf(
                'Generation timed out after %d minutes without a final result.',
                (int) floor($timeoutSeconds / 60)
            );
            mark_failed($job, $message);
            return ['ok' => false, 'id' => $job['id'], 'status' => 'failed', 'error' => $message];
        }
    }

    if (empty($job['external_job_id'])) {
        $message = 'Running job is missing external_job_id. The provider accepted the request but did not return a trackable job id.';
        mark_failed($job, $message);
        return ['ok' => false, 'error' => $message, 'id' => $job['id'], 'status' => 'failed'];
    }

    try {
        $response = poll_job((string) $job['external_job_id']);
        $body = $response['body'];
        db()->prepare("UPDATE generations SET error_message=NULL WHERE id=? AND status='running'")
            ->execute([$job['id']]);
        $state = strtolower((string) ($body['status'] ?? $body['state'] ?? ''));
        $output = extract_output_url($body);
        $preview = extract_preview_url($body);

        if ($preview !== null) {
            update_running_preview($job, $preview);
        }

        if ($output !== null && ($state === '' || $state === 'succeeded' || $state === 'completed' || $state === 'done')) {
            mark_succeeded($job, (string) $job['external_job_id'], $output);
            return ['ok' => true, 'id' => $job['id'], 'status' => 'succeeded'];
        }

        if ($state === 'failed' || $state === 'error' || $state === 'cancelled') {
            $message = (string) ($body['error']['message'] ?? $body['message'] ?? 'Generation failed while polling.');
            $message = 'Provider polling returned a failed state: ' . $message;
            mark_failed($job, $message);
            return ['ok' => false, 'id' => $job['id'], 'status' => 'failed', 'error' => $message];
        }

        return ['ok' => true, 'id' => $job['id'], 'status' => 'running'];
    } catch (Throwable $e) {
        db()->prepare("UPDATE generations SET error_message=? WHERE id=? AND status='running'")
            ->execute([$e->getMessage(), $job['id']]);
        return ['ok' => false, 'id' => $job['id'], 'status' => 'running', 'error' => $e->getMessage()];
    }
}

function process_one_queued_job(): array
{
    if ((bool) cfg('AUTO_MIGRATE', true)) { migrate_if_needed(); }

    $running = db()->query("SELECT * FROM generations WHERE status='running' ORDER BY started_at ASC LIMIT 1")->fetch();
    if ($running) {
        return process_running_job($running);
    }

    $job = db()->query("SELECT * FROM generations WHERE status='queued' ORDER BY created_at ASC LIMIT 1")->fetch();
    if (!$job) {
        return ['ok' => true, 'message' => 'No queued jobs'];
    }

    db()->prepare("UPDATE generations SET status='running', started_at=?, error_message=NULL WHERE id=?")
        ->execute([now_utc(), $job['id']]);

    $modelStmt = db()->prepare('SELECT * FROM models WHERE model_key=? AND type=? LIMIT 1');
    $modelStmt->execute([$job['model_key'], $job['type']]);
    $model = $modelStmt->fetch() ?: ['supports_negative_prompt' => 1];

    try {
        $response = $job['type'] === 'video'
            ? generate_video($job, (bool) $model['supports_negative_prompt'], $model)
            : generate_image($job, (bool) $model['supports_negative_prompt'], $model);

        $body = $response['body'];
        $external = extract_external_job_id($body);
        $output = extract_output_url($body);
        $base64Output = extract_base64_output($body);
        $preview = extract_preview_url($body);

        if ($output === null && $base64Output !== null) {
            $persisted = persist_base64_image($job, $base64Output);
            if ($persisted !== null) {
                $output = $persisted['path'];
            }
        }

        if ($output !== null) {
            mark_succeeded($job, is_string($external) ? $external : null, $output);
            return ['ok' => true, 'id' => $job['id'], 'status' => 'succeeded'];
        }

        if ($external === null && $output === null) {
            $message = 'Provider response did not include output media or a job id to poll. Check model endpoint configuration and provider payload shape.';
            mark_failed($job, $message);
            return ['ok' => false, 'id' => $job['id'], 'status' => 'failed', 'error' => $message];
        }

        db()->prepare("UPDATE generations SET external_job_id=?, output_path=?, output_mime=? WHERE id=?")
            ->execute([$external, $preview, $preview !== null ? 'image/png' : null, $job['id']]);

        return ['ok' => true, 'id' => $job['id'], 'status' => 'running'];
    } catch (Throwable $e) {
        mark_failed($job, $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage(), 'id' => $job['id']];
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    header('Content-Type: application/json');
    require_installation();
    echo json_encode(process_one_queued_job());
}
