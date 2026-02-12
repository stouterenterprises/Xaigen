ALTER TABLE models
  ADD COLUMN IF NOT EXISTS custom_prompt TEXT NULL AFTER display_name,
  ADD COLUMN IF NOT EXISTS custom_negative_prompt TEXT NULL AFTER custom_prompt;
