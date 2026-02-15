UPDATE models
SET is_active = 0,
    updated_at = UTC_TIMESTAMP()
WHERE api_provider = 'openrouter'
  AND type IN ('image', 'video')
  AND (
    LOWER(model_key) LIKE '%dolphin%'
    OR LOWER(model_key) LIKE '%venice%'
    OR LOWER(model_key) LIKE '%hermes%'
    OR LOWER(model_key) LIKE '%qwen%'
    OR LOWER(model_key) LIKE '%llama%'
    OR LOWER(model_key) LIKE '%mixtral%'
  );
