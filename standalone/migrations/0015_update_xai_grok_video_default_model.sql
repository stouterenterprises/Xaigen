UPDATE models
SET
  model_key = 'grok-video-latest',
  display_name = 'Grok Video (Latest)',
  updated_at = UTC_TIMESTAMP()
WHERE type = 'video'
  AND COALESCE(api_provider, 'xai') = 'xai'
  AND model_key = 'grok-2-video';
