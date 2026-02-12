CREATE TABLE IF NOT EXISTS users (
  id CHAR(36) PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  username VARCHAR(64) UNIQUE NOT NULL,
  email VARCHAR(190) UNIQUE NOT NULL,
  purpose TEXT NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  status ENUM('pending','active','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by_admin_id CHAR(36) NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME,
  updated_at DATETIME,
  INDEX idx_users_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE generations
  ADD COLUMN user_id CHAR(36) NULL AFTER id,
  ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER output_mime,
  ADD INDEX idx_generations_user_created (user_id, created_at),
  ADD INDEX idx_generations_public_created (is_public, created_at);

ALTER TABLE models
  ADD COLUMN default_seed INT NULL AFTER custom_negative_prompt,
  ADD COLUMN default_aspect_ratio VARCHAR(32) NULL AFTER default_seed,
  ADD COLUMN default_resolution VARCHAR(32) NULL AFTER default_aspect_ratio,
  ADD COLUMN default_duration_seconds DECIMAL(6,2) NULL AFTER default_resolution,
  ADD COLUMN default_fps INT NULL AFTER default_duration_seconds;
