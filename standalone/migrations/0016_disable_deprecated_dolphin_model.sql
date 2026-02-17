-- Disable cognitivecomputations/dolphin3.0-mistral-24b which has no active endpoints on OpenRouter.
-- The model was previously the replacement for dolphin-mixtral-8x7b but is now also unavailable.

UPDATE models
SET is_active  = 0,
    updated_at = UTC_TIMESTAMP()
WHERE api_provider = 'openrouter'
  AND LOWER(model_key) IN (
      'cognitivecomputations/dolphin3.0-mistral-24b',
      'cognitivecomputations/dolphin-mixtral-8x7b',
      'dolphin-2.5-mixtral',
      'dolphin/2.5-mixtral'
  );
