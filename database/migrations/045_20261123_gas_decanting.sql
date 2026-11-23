-- Perfect LPG - gas decanting
-- Moves gas from one filled shop cylinder into empty or part-filled shop cylinders of the same branch.
-- gas_decants is the header, gas_decant_items one row per target cylinder. Permission GAS_DECANT.
-- Idempotent: safe to run more than once.
-- Note for editors: the migration runner splits on semicolons and tracks quotes, so comments must not contain either.

CREATE TABLE IF NOT EXISTS gas_decants(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  decant_no VARCHAR(40) NOT NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  source_unit_id BIGINT UNSIGNED NOT NULL,
  source_before_kg DECIMAL(14,3) NOT NULL,
  source_after_kg DECIMAL(14,3) NOT NULL,
  total_kg DECIMAL(14,3) NOT NULL,
  notes VARCHAR(500) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_gas_decant_no(decant_no),
  KEY idx_gas_decant_location(location_id, created_at),
  CONSTRAINT fk_gas_decant_location FOREIGN KEY(location_id) REFERENCES locations(id) ON DELETE RESTRICT,
  CONSTRAINT fk_gas_decant_source FOREIGN KEY(source_unit_id) REFERENCES cylinder_units(id) ON DELETE RESTRICT,
  CONSTRAINT fk_gas_decant_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
  CHECK(total_kg > 0)
)ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS gas_decant_items(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  decant_id BIGINT UNSIGNED NOT NULL,
  target_unit_id BIGINT UNSIGNED NOT NULL,
  kg DECIMAL(14,3) NOT NULL,
  before_kg DECIMAL(14,3) NOT NULL,
  after_kg DECIMAL(14,3) NOT NULL,
  KEY idx_gas_decant_item_unit(target_unit_id),
  CONSTRAINT fk_gas_decant_item_decant FOREIGN KEY(decant_id) REFERENCES gas_decants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_gas_decant_item_unit FOREIGN KEY(target_unit_id) REFERENCES cylinder_units(id) ON DELETE RESTRICT,
  CHECK(kg > 0)
)ENGINE=InnoDB;

INSERT IGNORE INTO permissions(code, name) VALUES ('GAS_DECANT', 'Move gas between cylinders (decanting)');

INSERT IGNORE INTO role_permissions(role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'GAS_DECANT' WHERE r.code IN ('ADMIN', 'MANAGER');
