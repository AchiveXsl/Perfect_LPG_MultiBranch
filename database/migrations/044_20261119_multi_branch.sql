-- Perfect LPG - multi branch
-- One Head Office branch. Existing data stays on the first branch, which becomes the Head Office.
-- user_branches gives a user access to a branch with a role for that branch. users.all_branches gives owners and admins every branch.
-- customers.branch_scope is shared or branch. A branch customer is used only at customers.home_location_id.
-- customers.credit_scope picks the balance the credit limit is checked against, this branch or all branches combined.
-- The customer opening balance belongs to the home branch.
-- purchase_items.receive_location_id is the branch whose stock receives the line. Purchases are entered at the Head Office.
-- stock_transfers move physical cylinders between branches. A cylinder on the way has status in_transit.
-- Idempotent: safe to run more than once.
-- Note for editors: the migration runner splits on semicolons and tracks quotes, so comments must not contain either.

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE locations ADD COLUMN is_head_office TINYINT(1) NOT NULL DEFAULT 0 AFTER phone",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'locations' AND COLUMN_NAME = 'is_head_office'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ho = (SELECT id FROM locations WHERE is_head_office = 1 ORDER BY id LIMIT 1);
SET @ho = COALESCE(@ho, (SELECT id FROM locations ORDER BY id LIMIT 1));
UPDATE locations SET is_head_office = IF(id = @ho, 1, 0);

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE users ADD COLUMN all_branches TINYINT(1) NOT NULL DEFAULT 0 AFTER role_id",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'all_branches'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE users u JOIN roles r ON r.id = u.role_id SET u.all_branches = 1 WHERE r.code = 'ADMIN';

CREATE TABLE IF NOT EXISTS user_branches(
  user_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(user_id, location_id),
  KEY idx_user_branches_location(location_id),
  CONSTRAINT fk_user_branches_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_branches_location FOREIGN KEY(location_id) REFERENCES locations(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_branches_role FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE RESTRICT
)ENGINE=InnoDB;

INSERT IGNORE INTO user_branches(user_id, location_id, role_id, is_default)
SELECT u.id, COALESCE(u.location_id, @ho), u.role_id, 1 FROM users u WHERE @ho IS NOT NULL;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE customers ADD COLUMN branch_scope ENUM('shared','branch') NOT NULL DEFAULT 'shared' AFTER customer_type, ADD COLUMN home_location_id BIGINT UNSIGNED NULL AFTER branch_scope, ADD COLUMN credit_scope ENUM('branch','combined') NOT NULL DEFAULT 'branch' AFTER home_location_id, ADD KEY idx_customer_home_location(home_location_id), ADD CONSTRAINT fk_customer_home_location FOREIGN KEY(home_location_id) REFERENCES locations(id) ON DELETE RESTRICT",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'branch_scope'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE customers SET home_location_id = @ho WHERE home_location_id IS NULL AND @ho IS NOT NULL;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    "ALTER TABLE purchase_items ADD COLUMN receive_location_id BIGINT UNSIGNED NULL AFTER purchase_id, ADD KEY idx_purchase_item_receive_location(receive_location_id), ADD CONSTRAINT fk_purchase_item_receive_location FOREIGN KEY(receive_location_id) REFERENCES locations(id) ON DELETE RESTRICT",
    "SELECT 1")
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_items' AND COLUMN_NAME = 'receive_location_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE purchase_items pi JOIN purchases p ON p.id = pi.purchase_id SET pi.receive_location_id = p.location_id WHERE pi.receive_location_id IS NULL;

ALTER TABLE cylinder_units MODIFY COLUMN status ENUM('filled','empty','custody','sold','voided','in_transit') NOT NULL DEFAULT 'empty';

CREATE TABLE IF NOT EXISTS stock_transfers(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transfer_no VARCHAR(40) NOT NULL,
  from_location_id BIGINT UNSIGNED NOT NULL,
  to_location_id BIGINT UNSIGNED NOT NULL,
  status ENUM('in_transit','received','cancelled') NOT NULL DEFAULT 'in_transit',
  dispatched_at DATETIME NOT NULL,
  dispatched_by BIGINT UNSIGNED NULL,
  received_at DATETIME NULL,
  received_by BIGINT UNSIGNED NULL,
  cancelled_at DATETIME NULL,
  cancelled_by BIGINT UNSIGNED NULL,
  cancel_reason VARCHAR(255) NULL,
  notes VARCHAR(500) NULL,
  receive_notes VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_stock_transfer_no(transfer_no),
  KEY idx_stock_transfer_from(from_location_id, status, dispatched_at),
  KEY idx_stock_transfer_to(to_location_id, status, dispatched_at),
  CONSTRAINT fk_stock_transfer_from FOREIGN KEY(from_location_id) REFERENCES locations(id) ON DELETE RESTRICT,
  CONSTRAINT fk_stock_transfer_to FOREIGN KEY(to_location_id) REFERENCES locations(id) ON DELETE RESTRICT,
  CONSTRAINT fk_stock_transfer_dispatched_by FOREIGN KEY(dispatched_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_stock_transfer_received_by FOREIGN KEY(received_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_stock_transfer_cancelled_by FOREIGN KEY(cancelled_by) REFERENCES users(id) ON DELETE SET NULL
)ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_transfer_items(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transfer_id BIGINT UNSIGNED NOT NULL,
  cylinder_unit_id BIGINT UNSIGNED NOT NULL,
  cylinder_type_id BIGINT UNSIGNED NOT NULL,
  sent_status ENUM('filled','empty') NOT NULL,
  sent_gas_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
  gas_unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
  cylinder_unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
  receive_status ENUM('pending','received','missing','returned') NOT NULL DEFAULT 'pending',
  received_gas_weight_kg DECIMAL(14,3) NULL,
  UNIQUE KEY uq_stock_transfer_unit(transfer_id, cylinder_unit_id),
  KEY idx_stock_transfer_item_unit(cylinder_unit_id),
  CONSTRAINT fk_stock_transfer_item_transfer FOREIGN KEY(transfer_id) REFERENCES stock_transfers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_stock_transfer_item_unit FOREIGN KEY(cylinder_unit_id) REFERENCES cylinder_units(id) ON DELETE RESTRICT,
  CONSTRAINT fk_stock_transfer_item_type FOREIGN KEY(cylinder_type_id) REFERENCES cylinder_types(id) ON DELETE RESTRICT
)ENGINE=InnoDB;

INSERT IGNORE INTO permissions(code, name) VALUES
  ('BRANCH_MANAGE', 'Manage branches'),
  ('STOCK_TRANSFER', 'Transfer stock between branches'),
  ('REPORT_ALL_BRANCHES', 'View reports for all branches combined');

INSERT IGNORE INTO role_permissions(role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('BRANCH_MANAGE','STOCK_TRANSFER','REPORT_ALL_BRANCHES') WHERE r.code = 'ADMIN';

INSERT IGNORE INTO role_permissions(role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'STOCK_TRANSFER' WHERE r.code = 'MANAGER';
