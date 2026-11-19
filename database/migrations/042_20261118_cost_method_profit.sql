-- Perfect LPG - cost method (FIFO, LIFO, Average) and profit per sale
-- shop_settings.cost_method picks the cost price used when a sale is posted.
-- cost_layers holds the cost of every purchase or opening stock lot, cost_layer_usage records what each sale line consumed.
-- sale_items stores the cost that applied at the time of sale, so a later change of method never alters old sales.
-- Idempotent: safe to run more than once.
-- Note for editors: the migration runner splits on semicolons, so comments must not contain one.

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE shop_settings ADD COLUMN cost_method VARCHAR(10) NOT NULL DEFAULT 'average'",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shop_settings' AND COLUMN_NAME = 'cost_method'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE inventory_opening_balances ADD COLUMN gas_cost_per_kg DECIMAL(14,4) NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_opening_balances' AND COLUMN_NAME = 'gas_cost_per_kg'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE inventory_opening_balances ADD COLUMN cylinder_cost DECIMAL(14,4) NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_opening_balances' AND COLUMN_NAME = 'cylinder_cost'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE sale_items ADD COLUMN cost_gas DECIMAL(14,2) NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_items' AND COLUMN_NAME = 'cost_gas'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE sale_items ADD COLUMN cost_cylinder DECIMAL(14,2) NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_items' AND COLUMN_NAME = 'cost_cylinder'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE sale_items ADD COLUMN cost_amount DECIMAL(14,2) NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_items' AND COLUMN_NAME = 'cost_amount'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE sale_items ADD COLUMN cost_method VARCHAR(10) NULL",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_items' AND COLUMN_NAME = 'cost_method'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS cost_layers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location_id BIGINT UNSIGNED NOT NULL,
  kind ENUM('gas','cylinder') NOT NULL,
  cylinder_type_id BIGINT UNSIGNED NULL,
  source_type VARCHAR(20) NOT NULL,
  source_id BIGINT UNSIGNED NOT NULL,
  source_line_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  received_at DATETIME NOT NULL,
  qty_in DECIMAL(14,3) NOT NULL,
  qty_left DECIMAL(14,3) NOT NULL,
  unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
  status ENUM('active','voided') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cost_layer_source (source_type, source_id, source_line_id, kind),
  KEY idx_cost_layer_pick (location_id, kind, cylinder_type_id, status, received_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cost_layer_usage (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id BIGINT UNSIGNED NOT NULL,
  sale_item_id BIGINT UNSIGNED NOT NULL,
  layer_id BIGINT UNSIGNED NULL,
  kind ENUM('gas','cylinder') NOT NULL,
  qty DECIMAL(14,3) NOT NULL,
  unit_cost DECIMAL(14,4) NOT NULL,
  amount DECIMAL(14,4) NOT NULL,
  reversed TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_cost_usage_sale (sale_id),
  KEY idx_cost_usage_item (sale_item_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cost_layer_state (
  location_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  ready_at DATETIME NOT NULL
) ENGINE=InnoDB;
