-- Perfect LPG - purchasing mode
-- shop_settings.purchase_mode is read from the Head Office row and applies to the whole company.
-- central = the Head Office purchases for every branch and owns the supplier payables.
-- split = every branch purchases only its own stock, owes and pays its own supplier balances.
-- Idempotent: safe to run more than once.
-- Note for editors: the migration runner splits on semicolons and tracks quotes, so comments must not contain either.

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE shop_settings ADD COLUMN purchase_mode VARCHAR(10) NOT NULL DEFAULT 'central'",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shop_settings' AND COLUMN_NAME = 'purchase_mode'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
