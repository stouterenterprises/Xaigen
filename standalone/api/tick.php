<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/migrations.php';
require_once __DIR__ . '/../lib/xai.php';

function process_one_queued_job(): array
{
    if ((bool) cfg('AUTO_MIGRATE', true)) { migrate_if_needed(); }

    $job = db()->query("SELECT * FROM generations WHERE status='queued' ORDER BY created_at ASC LIMIT 1")->fetch();
    if (!$job) {
        return ['ok' => true, 'message' => 'No queued jobs'];
    }

    db()->prepare("UPDATE generations SET status='running', started_at=? WHERE id=?")->execute([now_utc(), $job['id']]);
    $modelStmt = db()->prepare('SELECT * FROM models WHERE model_key=? AND type=? LIMIT 1');
    $modelStmt->execute([$job['model_key'], $job['type']]);
    $model = $modelStmt->fetch() ?: ['supports_negative_prompt' => 1];

    try {
        $response = $job['type'] === 'video'
            ? generate_video($job, (bool)$model['supports_negative_prompt'])
            : generate_image($job, (bool)$model['supports_negative_prompt']);

        $body = $response['body'];
        $external = $body['id'] ?? $body['job_id'] ?? null;
        $output = $body['data'][0]['url'] ?? $body['output_url'] ?? null;

        db()->prepare("UPDATE generations SET status='succeeded', external_job_id=?, output_path=?, output_mime=?, finished_at=? WHERE id=?")
            ->execute([$external, $output, $job['type']==='video'?'video/mp4':'image/png', now_utc(), $job['id']]);

        return ['ok' => true, 'id' => $job['id']];
    } catch (Throwable $e) {
        db()->prepare("UPDATE generations SET status='failed', error_message=?, finished_at=? WHERE id=?")
            ->execute([$e->getMessage(), now_utc(), $job['id']]);
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    header('Content-Type: application/json');
    require_installation();
    echo json_encode(process_one_queued_job());
}
