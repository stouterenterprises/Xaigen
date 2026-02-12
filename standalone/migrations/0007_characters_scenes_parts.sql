CREATE TABLE IF NOT EXISTS characters (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  name VARCHAR(120) NOT NULL,
  description TEXT NULL,
  age INT NOT NULL,
  gender VARCHAR(40) NULL,
  penis_size VARCHAR(40) NULL,
  boob_size VARCHAR(40) NULL,
  height_cm INT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME,
  updated_at DATETIME,
  INDEX idx_characters_user_created (user_id, created_at),
  INDEX idx_characters_public_created (is_public, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS character_media (
  id CHAR(36) PRIMARY KEY,
  character_id CHAR(36) NOT NULL,
  media_path TEXT NOT NULL,
  media_type ENUM('image') NOT NULL DEFAULT 'image',
  created_at DATETIME,
  INDEX idx_character_media_character (character_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS scenes (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  name VARCHAR(120) NOT NULL,
  description TEXT NULL,
  type ENUM('image','video') NOT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME,
  updated_at DATETIME,
  INDEX idx_scenes_user_created (user_id, created_at),
  INDEX idx_scenes_public_created (is_public, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS scene_media (
  id CHAR(36) PRIMARY KEY,
  scene_id CHAR(36) NOT NULL,
  media_path TEXT NOT NULL,
  media_type ENUM('image','video') NOT NULL,
  created_at DATETIME,
  INDEX idx_scene_media_scene (scene_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS parts (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  name VARCHAR(120) NOT NULL,
  description TEXT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME,
  updated_at DATETIME,
  INDEX idx_parts_user_created (user_id, created_at),
  INDEX idx_parts_public_created (is_public, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS part_media (
  id CHAR(36) PRIMARY KEY,
  part_id CHAR(36) NOT NULL,
  media_path TEXT NOT NULL,
  media_type ENUM('image','video') NOT NULL,
  created_at DATETIME,
  INDEX idx_part_media_part (part_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
