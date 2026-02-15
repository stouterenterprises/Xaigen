UPDATE models
SET model_key = 'josiefied/qwen3-8b', updated_at = UTC_TIMESTAMP()
WHERE api_provider = 'openrouter'
  AND LOWER(model_key) = 'josiefied-qwen3-8b';
