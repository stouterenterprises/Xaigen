CREATE TABLE IF NOT EXISTS models (
  id CHAR(36) PRIMARY KEY,
  type ENUM('image','video'),
  model_key VARCHAR(128),
  display_name VARCHAR(128),
  supports_negative_prompt TINYINT(1) DEFAULT 1,
  is_active TINYINT(1) DEFAULT 1,
  created_at DATETIME,
  updated_at DATETIME,
  UNIQUE KEY uniq_model_key_type (model_key, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
