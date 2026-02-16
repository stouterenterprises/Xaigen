-- Fix deprecated OpenRouter Dolphin model.
-- cognitivecomputations/dolphin-mixtral-8x7b has no active endpoints on
-- OpenRouter and has been superseded by dolphin3.0-mistral-24b.

UPDATE models
SET model_key    = 'cognitivecomputations/dolphin3.0-mistral-24b',
    display_name = 'Dolphin 3.0 Mistral 24B',
    updated_at   = UTC_TIMESTAMP()
WHERE api_provider = 'openrouter'
  AND LOWER(model_key) IN (
      'cognitivecomputations/dolphin-mixtral-8x7b',
      'dolphin-2.5-mixtral',
      'dolphin/2.5-mixtral'
  );
