INSERT INTO models (
  id,
  type,
  model_key,
  display_name,
  api_provider,
  supports_negative_prompt,
  is_active,
  created_at,
  updated_at
)
SELECT UUID(), 'video', 'nous-hermes-2-mixtral-8x7b', 'Nous-Hermes 2 – Mixtral 8x7B', 'openrouter', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
  SELECT 1 FROM models WHERE model_key = 'nous-hermes-2-mixtral-8x7b' AND type = 'video'
);

INSERT INTO models (
  id,
  type,
  model_key,
  display_name,
  api_provider,
  supports_negative_prompt,
  is_active,
  created_at,
  updated_at
)
SELECT UUID(), 'image', 'dolphin-2.5-mixtral', 'Dolphin 2.5 – Mixtral', 'openrouter', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
  SELECT 1 FROM models WHERE model_key = 'dolphin-2.5-mixtral' AND type = 'image'
);

INSERT INTO models (
  id,
  type,
  model_key,
  display_name,
  api_provider,
  supports_negative_prompt,
  is_active,
  created_at,
  updated_at
)
SELECT UUID(), 'video', 'josiefied-qwen3-8b', 'JOSIEFIED-Qwen3:8b', 'openrouter', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
  SELECT 1 FROM models WHERE model_key = 'josiefied-qwen3-8b' AND type = 'video'
);
