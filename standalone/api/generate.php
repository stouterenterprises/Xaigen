<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/migrations.php';
require_once __DIR__ . '/../lib/validation.php';
require_once __DIR__ . '/../lib/rate_limit.php';
require_once __DIR__ . '/../lib/xai.php';
require_once __DIR__ . '/../lib/auth.php';

header('Content-Type: application/json');
require_installation();
require_active_account_or_admin_json();
rate_limit_or_fail('generate');
if ((bool) cfg('AUTO_MIGRATE', true)) { migrate_if_needed(); }

try {
    $payload = validate_generation_payload(json_input());
    $user = current_user();

    $modelStmt = db()->prepare('SELECT id, default_seed, default_aspect_ratio, default_resolution, default_duration_seconds, default_fps FROM models WHERE model_key=? AND type=? AND is_active=1 LIMIT 1');
    $modelStmt->execute([$payload['model_key'], $payload['type']]);
    $model = $modelStmt->fetch();
    if (!$model) {
        throw new InvalidArgumentException('The selected model is not active for the requested generation type.');
    }

    $id = uuidv4();
    $params = [
        'seed' => $payload['seed'] ?? $model['default_seed'],
        'aspect_ratio' => $payload['aspect_ratio'] ?: ($model['default_aspect_ratio'] ?: '16:9'),
        'resolution' => $payload['resolution'] ?: ($model['default_resolution'] ?: '1024x1024'),
        'duration_seconds' => $payload['duration_seconds'] ?: (float) ($model['default_duration_seconds'] ?: 5),
        'fps' => $payload['fps'] ?: (int) ($model['default_fps'] ?: 24),
    ];

    $stmt = db()->prepare('INSERT INTO generations (id,user_id,type,model_key,prompt,negative_prompt,params_json,status,created_at,fps,duration_seconds,is_public) VALUES (?,?,?,?,?,?,?,?,?,?,?,0)');
    $stmt->execute([$id,$user['id'] ?? null,$payload['type'],$payload['model_key'],$payload['prompt'],$payload['negative_prompt'],json_encode($params),'queued',now_utc(),(int)$params['fps'],(float)$params['duration_seconds']]);

    require __DIR__ . '/tick.php';
    process_one_queued_job();

    echo json_encode(['ok' => true, 'id' => $id]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
