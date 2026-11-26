-- Perfect LPG - per-branch System Date (owner decision 2026-11-26)
-- locations.system_date is the working date of a branch. Every transaction of the branch is dated with it
-- plus the clock time. NULL means the branch follows the real date.
-- entered_at keeps the real time a row was entered, whatever date the transaction carries.
-- Permission SYSTEM_DATE_CHANGE, Admin by default.
-- Idempotent: safe to run more than once.
-- Note for editors: the migration runner splits on semicolons and tracks quotes, so comments must not contain either.
-- Rows entered before this migration keep entered_at NULL.

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE locations ADD COLUMN system_date DATE NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'locations' AND COLUMN_NAME = 'system_date'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO permissions(code, name) VALUES ('SYSTEM_DATE_CHANGE', 'Change the branch System Date');
INSERT IGNORE INTO role_permissions(role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'SYSTEM_DATE_CHANGE' WHERE r.code = 'ADMIN';

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE inventory_movements ADD COLUMN entered_at DATETIME NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_movements' AND COLUMN_NAME = 'entered_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE cash_transactions ADD COLUMN entered_at DATETIME NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_transactions' AND COLUMN_NAME = 'entered_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE cash_sessions ADD COLUMN entered_at DATETIME NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'entered_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE sale_payments ADD COLUMN entered_at DATETIME NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_payments' AND COLUMN_NAME = 'entered_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE purchase_payments ADD COLUMN entered_at DATETIME NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_payments' AND COLUMN_NAME = 'entered_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE customer_receipts ADD COLUMN entered_at DATETIME NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_receipts' AND COLUMN_NAME = 'entered_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE supplier_payments ADD COLUMN entered_at DATETIME NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'supplier_payments' AND COLUMN_NAME = 'entered_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE expenses ADD COLUMN entered_at DATETIME NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expenses' AND COLUMN_NAME = 'entered_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE customer_security_deposits ADD COLUMN entered_at DATETIME NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_security_deposits' AND COLUMN_NAME = 'entered_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE cylinder_custody ADD COLUMN entered_at DATETIME NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cylinder_custody' AND COLUMN_NAME = 'entered_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE loan_entries ADD COLUMN entered_at DATETIME NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loan_entries' AND COLUMN_NAME = 'entered_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE stock_transfers ADD COLUMN entered_at DATETIME NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_transfers' AND COLUMN_NAME = 'entered_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE gas_decants ADD COLUMN entered_at DATETIME NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gas_decants' AND COLUMN_NAME = 'entered_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE inventory_adjustments ADD COLUMN entered_at DATETIME NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_adjustments' AND COLUMN_NAME = 'entered_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE cylinder_fills ADD COLUMN entered_at DATETIME NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cylinder_fills' AND COLUMN_NAME = 'entered_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE inventory_movements MODIFY COLUMN entered_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE cash_transactions MODIFY COLUMN entered_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE cash_sessions MODIFY COLUMN entered_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE sale_payments MODIFY COLUMN entered_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE purchase_payments MODIFY COLUMN entered_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE customer_receipts MODIFY COLUMN entered_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE supplier_payments MODIFY COLUMN entered_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE expenses MODIFY COLUMN entered_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE customer_security_deposits MODIFY COLUMN entered_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE cylinder_custody MODIFY COLUMN entered_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE loan_entries MODIFY COLUMN entered_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE stock_transfers MODIFY COLUMN entered_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE gas_decants MODIFY COLUMN entered_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE inventory_adjustments MODIFY COLUMN entered_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE cylinder_fills MODIFY COLUMN entered_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
