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

    $characterIds = array_slice(array_values(array_unique($payload['character_ids'] ?? [])), 0, 3);
    $partIds = array_values(array_unique($payload['part_ids'] ?? []));
    $sceneId = $payload['scene_id'] ?? '';
    $visibilityWhere = !empty($_SESSION['admin_user_id']) ? '1=1' : '(user_id = :user_id OR is_public = 1)';
    $selectionNotes = [];

    if ($characterIds) {
        $ph = implode(',', array_fill(0, count($characterIds), '?'));
        $sql = "SELECT id, name FROM characters WHERE id IN ($ph) AND {$visibilityWhere}";
        $stmtChars = db()->prepare($sql);
        if (!empty($_SESSION['admin_user_id'])) {
            $stmtChars->execute($characterIds);
        } else {
            $stmtChars->execute(array_merge($characterIds, [$user['id'] ?? '']));
        }
        $rows = $stmtChars->fetchAll();
        if (count($rows) !== count($characterIds)) {
            throw new InvalidArgumentException('One or more selected characters are unavailable.');
        }
        $selectionNotes[] = 'Characters: ' . implode(', ', array_map(fn($r) => (string)$r['name'], $rows));
    }

    if ($sceneId !== '') {
        $sceneSql = "SELECT id, name, type FROM scenes WHERE id = ? AND {$visibilityWhere} LIMIT 1";
        $stmtScene = db()->prepare($sceneSql);
        if (!empty($_SESSION['admin_user_id'])) {
            $stmtScene->execute([$sceneId]);
        } else {
            $stmtScene->execute([$sceneId, $user['id'] ?? '']);
        }
        $scene = $stmtScene->fetch();
        if (!$scene) {
            throw new InvalidArgumentException('Selected scene is unavailable.');
        }
        if (($scene['type'] ?? '') !== $payload['type']) {
            throw new InvalidArgumentException('Scene type must match generation type.');
        }
        $selectionNotes[] = 'Scene: ' . $scene['name'];
    }

    if ($partIds) {
        $ph = implode(',', array_fill(0, count($partIds), '?'));
        $partSql = "SELECT id, name FROM parts WHERE id IN ($ph) AND {$visibilityWhere}";
        $stmtParts = db()->prepare($partSql);
        if (!empty($_SESSION['admin_user_id'])) {
            $stmtParts->execute($partIds);
        } else {
            $stmtParts->execute(array_merge($partIds, [$user['id'] ?? '']));
        }
        $rows = $stmtParts->fetchAll();
        if (count($rows) !== count($partIds)) {
            throw new InvalidArgumentException('One or more selected parts are unavailable.');
        }
        $selectionNotes[] = 'Parts: ' . implode(', ', array_map(fn($r) => (string)$r['name'], $rows));
    }

    $id = uuidv4();
    $params = [
        'character_ids' => $characterIds,
        'scene_id' => $sceneId ?: null,
        'part_ids' => $partIds,
        'seed' => $payload['seed'] ?? $model['default_seed'],
        'aspect_ratio' => $payload['aspect_ratio'] ?: ($model['default_aspect_ratio'] ?: '16:9'),
        'resolution' => $payload['resolution'] ?: ($model['default_resolution'] ?: '1024x1024'),
        'duration_seconds' => $payload['duration_seconds'] ?: (float) ($model['default_duration_seconds'] ?: 5),
        'fps' => $payload['fps'] ?: (int) ($model['default_fps'] ?: 24),
    ];

    $finalPrompt = $payload['prompt'] . (empty($selectionNotes) ? '' : "\n\nCreative context: " . implode(' | ', $selectionNotes));

    $stmt = db()->prepare('INSERT INTO generations (id,user_id,type,model_key,prompt,negative_prompt,params_json,status,created_at,fps,duration_seconds,is_public) VALUES (?,?,?,?,?,?,?,?,?,?,?,0)');
    $stmt->execute([$id,$user['id'] ?? null,$payload['type'],$payload['model_key'],$finalPrompt,$payload['negative_prompt'],json_encode($params),'queued',now_utc(),(int)$params['fps'],(float)$params['duration_seconds']]);

    require __DIR__ . '/tick.php';
    process_one_queued_job();

    echo json_encode(['ok' => true, 'id' => $id]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
