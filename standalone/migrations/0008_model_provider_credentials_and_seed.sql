SET @schema_name := DATABASE();

SET @has_api_provider := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'models'
    AND COLUMN_NAME = 'api_provider'
);
SET @sql := IF(
  @has_api_provider = 0,
  "ALTER TABLE models ADD COLUMN api_provider VARCHAR(32) NOT NULL DEFAULT 'xai' AFTER display_name",
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_api_base_url := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'models'
    AND COLUMN_NAME = 'api_base_url'
);
SET @sql := IF(
  @has_api_base_url = 0,
  'ALTER TABLE models ADD COLUMN api_base_url VARCHAR(255) NULL AFTER api_provider',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_api_key_encrypted := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'models'
    AND COLUMN_NAME = 'api_key_encrypted'
);
SET @sql := IF(
  @has_api_key_encrypted = 0,
  'ALTER TABLE models ADD COLUMN api_key_encrypted TEXT NULL AFTER api_base_url',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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
SELECT UUID(), 'image', 'nous-hermes-2-mixtral-8x7b', 'Nous-Hermes 2 – Mixtral 8x7B', 'openrouter', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
  SELECT 1 FROM models WHERE model_key = 'nous-hermes-2-mixtral-8x7b' AND type = 'image'
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
SELECT UUID(), 'video', 'dolphin-2.5-mixtral', 'Dolphin 2.5 – Mixtral', 'openrouter', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
  SELECT 1 FROM models WHERE model_key = 'dolphin-2.5-mixtral' AND type = 'video'
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
SELECT UUID(), 'image', 'josiefied-qwen3-8b', 'JOSIEFIED-Qwen3:8b', 'openrouter', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
  SELECT 1 FROM models WHERE model_key = 'josiefied-qwen3-8b' AND type = 'image'
);
