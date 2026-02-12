<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/migrations.php';
require_once __DIR__ . '/../lib/validation.php';
require_once __DIR__ . '/../lib/rate_limit.php';
require_once __DIR__ . '/../lib/xai.php';

header('Content-Type: application/json');
require_installation();
rate_limit_or_fail('generate');
if ((bool) cfg('AUTO_MIGRATE', true)) { migrate_if_needed(); }

try {
    $payload = validate_generation_payload(json_input());

    $modelStmt = db()->prepare('SELECT id FROM models WHERE model_key=? AND type=? AND is_active=1 LIMIT 1');
    $modelStmt->execute([$payload['model_key'], $payload['type']]);
    if (!$modelStmt->fetch()) {
        throw new InvalidArgumentException('The selected model is not active for the requested generation type.');
    }

    $id = uuidv4();
    $params = [
        'seed' => $payload['seed'],
        'aspect_ratio' => $payload['aspect_ratio'],
        'resolution' => $payload['resolution'],
        'duration_seconds' => $payload['duration_seconds'],
        'fps' => $payload['fps'],
    ];

    $stmt = db()->prepare('INSERT INTO generations (id,type,model_key,prompt,negative_prompt,params_json,status,created_at,fps,duration_seconds) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$id,$payload['type'],$payload['model_key'],$payload['prompt'],$payload['negative_prompt'],json_encode($params),'queued',now_utc(),$payload['fps'],$payload['duration_seconds']]);

    require __DIR__ . '/tick.php';
    process_one_queued_job();

    echo json_encode(['ok' => true, 'id' => $id]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
