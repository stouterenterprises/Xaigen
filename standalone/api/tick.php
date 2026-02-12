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
    db()->prepare("UPDATE generations SET status='succeeded', external_job_id=?, output_path=?, output_mime=?, finished_at=? WHERE id=?")
        ->execute([$external, $output, $job['type'] === 'video' ? 'video/mp4' : 'image/png', now_utc(), $job['id']]);
}

function process_running_job(array $job): array
{
    if (empty($job['external_job_id'])) {
        return ['ok' => false, 'error' => 'Running job is missing external_job_id', 'id' => $job['id']];
    }

    try {
        $response = poll_job((string) $job['external_job_id']);
        $body = $response['body'];
        $state = strtolower((string) ($body['status'] ?? $body['state'] ?? ''));
        $output = extract_output_url($body);

        if ($output !== null && ($state === '' || $state === 'succeeded' || $state === 'completed' || $state === 'done')) {
            mark_succeeded($job, (string) $job['external_job_id'], $output);
            return ['ok' => true, 'id' => $job['id'], 'status' => 'succeeded'];
        }

        if ($state === 'failed' || $state === 'error' || $state === 'cancelled') {
            $message = (string) ($body['error']['message'] ?? $body['message'] ?? 'Generation failed while polling.');
            db()->prepare("UPDATE generations SET status='failed', error_message=?, finished_at=? WHERE id=?")
                ->execute([$message, now_utc(), $job['id']]);
            return ['ok' => false, 'id' => $job['id'], 'status' => 'failed', 'error' => $message];
        }

        return ['ok' => true, 'id' => $job['id'], 'status' => 'running'];
    } catch (Throwable $e) {
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

    db()->prepare("UPDATE generations SET status='running', started_at=? WHERE id=?")
        ->execute([now_utc(), $job['id']]);

    $modelStmt = db()->prepare('SELECT * FROM models WHERE model_key=? AND type=? LIMIT 1');
    $modelStmt->execute([$job['model_key'], $job['type']]);
    $model = $modelStmt->fetch() ?: ['supports_negative_prompt' => 1];

    try {
        $response = $job['type'] === 'video'
            ? generate_video($job, (bool) $model['supports_negative_prompt'])
            : generate_image($job, (bool) $model['supports_negative_prompt']);

        $body = $response['body'];
        $external = $body['id'] ?? $body['job_id'] ?? null;
        $output = extract_output_url($body);

        if ($output !== null) {
            mark_succeeded($job, is_string($external) ? $external : null, $output);
            return ['ok' => true, 'id' => $job['id'], 'status' => 'succeeded'];
        }

        db()->prepare("UPDATE generations SET external_job_id=? WHERE id=?")
            ->execute([is_string($external) ? $external : null, $job['id']]);

        return ['ok' => true, 'id' => $job['id'], 'status' => 'running'];
    } catch (Throwable $e) {
        db()->prepare("UPDATE generations SET status='failed', error_message=?, finished_at=? WHERE id=?")
            ->execute([$e->getMessage(), now_utc(), $job['id']]);
        return ['ok' => false, 'error' => $e->getMessage(), 'id' => $job['id']];
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    header('Content-Type: application/json');
    require_installation();
    echo json_encode(process_one_queued_job());
}
