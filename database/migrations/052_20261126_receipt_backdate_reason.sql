-- Perfect LPG - back-dated customer receipts (owner decision 2026-11-26)
-- customer_receipts.backdate_reason is why a receipt was dated before the day it was entered.
-- NULL for receipts dated the day they were entered.
-- Idempotent: safe to run more than once.
-- Note for editors: the migration runner splits on semicolons and tracks quotes, so comments must not contain either.

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE customer_receipts ADD COLUMN backdate_reason VARCHAR(255) NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_receipts' AND COLUMN_NAME = 'backdate_reason'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
