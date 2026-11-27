-- Perfect LPG - counter cash session per day as a branch setting (owner decision 2026-11-27)
-- shop_settings.cash_session_per_day = 1 makes every counter session belong to one date.
-- 0 (default) keeps a session open until a user closes it, for a day, a week or a month,
-- and every cash entry goes into the open session with its own transaction date.
-- Idempotent: safe to run more than once.
-- Note for editors: the migration runner splits on semicolons and tracks quotes, so comments must not contain either.

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE shop_settings ADD COLUMN cash_session_per_day TINYINT(1) NOT NULL DEFAULT 0",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shop_settings' AND COLUMN_NAME = 'cash_session_per_day'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
