CREATE TABLE IF NOT EXISTS api_keys (
  id CHAR(36) PRIMARY KEY,
  provider VARCHAR(64) NOT NULL,
  key_name VARCHAR(128) NOT NULL,
  key_value_encrypted TEXT NOT NULL,
  is_active TINYINT(1) DEFAULT 1,
  notes TEXT,
  created_at DATETIME,
  updated_at DATETIME,
  INDEX idx_provider_name_active (provider, key_name, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
