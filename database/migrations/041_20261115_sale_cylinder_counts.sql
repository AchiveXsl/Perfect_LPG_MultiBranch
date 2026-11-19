-- Perfect LPG - cylinder counts on a sale
-- A Security Deposit / Issue Cylinder invoice can also take back already issued cylinders.
-- cylinders_issued = cylinders issued on this invoice
-- cylinders_returned = earlier issued cylinders returned (empty) on this invoice
-- cylinders_with_customer = cylinders the customer holds after this invoice
-- Idempotent: safe to run more than once.
-- Note for editors: the migration runner splits on semicolons, so comments must not contain one.

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE sales ADD COLUMN cylinders_issued INT UNSIGNED NOT NULL DEFAULT 0 AFTER return_gas_ledger_amount",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'cylinders_issued'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE sales ADD COLUMN cylinders_returned INT UNSIGNED NOT NULL DEFAULT 0 AFTER cylinders_issued",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'cylinders_returned'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE sales ADD COLUMN cylinders_with_customer INT UNSIGNED NOT NULL DEFAULT 0 AFTER cylinders_returned",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'cylinders_with_customer'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
