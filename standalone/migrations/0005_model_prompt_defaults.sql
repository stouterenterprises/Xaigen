SET @schema_name := DATABASE();

SET @has_custom_prompt := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'models'
    AND COLUMN_NAME = 'custom_prompt'
);
SET @sql := IF(
  @has_custom_prompt = 0,
  'ALTER TABLE models ADD COLUMN custom_prompt TEXT NULL AFTER display_name',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_custom_negative_prompt := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'models'
    AND COLUMN_NAME = 'custom_negative_prompt'
);
SET @sql := IF(
  @has_custom_negative_prompt = 0,
  'ALTER TABLE models ADD COLUMN custom_negative_prompt TEXT NULL AFTER custom_prompt',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
