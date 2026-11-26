-- Perfect LPG - back-dated purchases (owner decision 2026-11-26)
-- purchases.backdate_reason is why a purchase was dated before the day it was entered.
-- NULL for purchases dated the day they were entered.
-- Idempotent: safe to run more than once.
-- Note for editors: the migration runner splits on semicolons and tracks quotes, so comments must not contain either.

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE purchases ADD COLUMN backdate_reason VARCHAR(255) NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchases' AND COLUMN_NAME = 'backdate_reason'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
