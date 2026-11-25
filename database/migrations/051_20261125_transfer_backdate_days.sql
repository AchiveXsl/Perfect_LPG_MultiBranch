-- Perfect LPG - stock transfer dates (owner decision 2026-11-25)
-- shop_settings.transfer_backdate_days is how many days back a branch may date a transfer dispatch or receipt.
-- NULL means back to the start of the current month (the default).
-- Idempotent: safe to run more than once.
-- Note for editors: the migration runner splits on semicolons and tracks quotes, so comments must not contain either.

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE shop_settings ADD COLUMN transfer_backdate_days INT NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shop_settings' AND COLUMN_NAME = 'transfer_backdate_days'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
