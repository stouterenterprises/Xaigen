-- Fix OpenRouter model keys to use correct provider/model-name format.
-- Previous keys used shorthand names that are not valid OpenRouter model IDs.

-- nous-hermes-2-mixtral-8x7b → nousresearch/nous-hermes-2-mixtral-8x7b-dpo
UPDATE models
SET model_key = 'nousresearch/nous-hermes-2-mixtral-8x7b-dpo', updated_at = UTC_TIMESTAMP()
WHERE api_provider = 'openrouter'
  AND LOWER(model_key) IN ('nous-hermes-2-mixtral-8x7b', 'nous/hermes-2-mixtral-8x7b');

-- dolphin-2.5-mixtral → cognitivecomputations/dolphin-mixtral-8x7b
UPDATE models
SET model_key = 'cognitivecomputations/dolphin-mixtral-8x7b', updated_at = UTC_TIMESTAMP()
WHERE api_provider = 'openrouter'
  AND LOWER(model_key) IN ('dolphin-2.5-mixtral', 'dolphin/2.5-mixtral');

-- josiefied/qwen3-8b is not on OpenRouter; remap to qwen/qwen3-8b
UPDATE models
SET model_key = 'qwen/qwen3-8b', display_name = 'Qwen3 8B', updated_at = UTC_TIMESTAMP()
WHERE api_provider = 'openrouter'
  AND LOWER(model_key) IN ('josiefied-qwen3-8b', 'josiefied/qwen3-8b');
