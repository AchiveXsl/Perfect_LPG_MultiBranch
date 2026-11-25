-- Perfect LPG - purchase rate changes after posting (owner decision 2026-11-24)
-- One row per changed purchase line: old and new rate and line total, quantity already sold, whether the
-- cost and profit of linked sales were restated, the reason and who changed it. Rates are never overwritten silently.
-- Idempotent: safe to run more than once.
-- Note for editors: the migration runner splits on semicolons and tracks quotes, so comments must not contain either.

CREATE TABLE IF NOT EXISTS purchase_rate_changes(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purchase_id BIGINT UNSIGNED NOT NULL,
  purchase_item_id BIGINT UNSIGNED NOT NULL,
  old_rate DECIMAL(14,4) NOT NULL,
  new_rate DECIMAL(14,4) NOT NULL,
  old_line_total DECIMAL(14,2) NOT NULL,
  new_line_total DECIMAL(14,2) NOT NULL,
  sold_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  sales_restated TINYINT(1) NOT NULL DEFAULT 0,
  reason VARCHAR(500) NOT NULL,
  changed_by BIGINT UNSIGNED NULL,
  changed_at DATETIME NOT NULL,
  KEY idx_prc_purchase(purchase_id),
  CONSTRAINT fk_prc_purchase FOREIGN KEY(purchase_id) REFERENCES purchases(id)
) ENGINE=InnoDB;
