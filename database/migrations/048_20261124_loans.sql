-- Perfect LPG - loans taken by the business (owner decision 2026-11-24)
-- A lender is a person who lends money to a branch. Not a customer and not a supplier.
-- Each branch keeps its own lenders and loans and repays them itself. No interest or profit share.
-- loan_entries holds every loan received and every repayment. Posted entries are voided, never deleted.
-- A loan is a liability. It is never sales, profit or expense.
-- Cash entries use the cash types loan_received and loan_repayment. Permission LOAN_MANAGE.
-- Idempotent: safe to run more than once.
-- Note for editors: the migration runner splits on semicolons and tracks quotes, so comments must not contain either.

CREATE TABLE IF NOT EXISTS lenders(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(40) NULL,
  cnic VARCHAR(40) NULL,
  address VARCHAR(255) NULL,
  notes VARCHAR(500) NULL,
  opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
  is_active BOOLEAN NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_lender_location(location_id),
  CONSTRAINT fk_lender_location FOREIGN KEY(location_id) REFERENCES locations(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS loan_entries(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entry_no VARCHAR(40) NOT NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  lender_id BIGINT UNSIGNED NOT NULL,
  entry_type ENUM('received','repayment') NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  payment_mode ENUM('cash','cheque','online') NOT NULL DEFAULT 'cash',
  reference_no VARCHAR(100) NULL,
  entry_at DATETIME NOT NULL,
  due_date DATE NULL,
  notes VARCHAR(500) NULL,
  status ENUM('posted','voided') NOT NULL DEFAULT 'posted',
  void_reason VARCHAR(500) NULL,
  voided_by BIGINT UNSIGNED NULL,
  voided_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_loan_entry_no(entry_no),
  KEY idx_loan_lender(lender_id, status),
  KEY idx_loan_location_date(location_id, entry_at),
  CONSTRAINT fk_loan_lender FOREIGN KEY(lender_id) REFERENCES lenders(id),
  CONSTRAINT fk_loan_location FOREIGN KEY(location_id) REFERENCES locations(id)
) ENGINE=InnoDB;

ALTER TABLE cash_transactions MODIFY transaction_type ENUM('opening_float','sale_cash','customer_receipt','purchase_cash','supplier_payment','expense','manual_in','manual_out','cash_handover','security_deposit','security_deposit_refund','loan_received','loan_repayment') NOT NULL;

INSERT IGNORE INTO permissions(code, name) VALUES ('LOAN_MANAGE', 'Manage loans taken from lenders');
INSERT IGNORE INTO role_permissions(role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'LOAN_MANAGE' WHERE r.code = 'ADMIN';
