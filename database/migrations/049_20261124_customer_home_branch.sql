-- Perfect LPG - every customer gets a home branch (owner decision 2026-11-24)
-- Customers saved before branches existed have no home_location_id. They are mapped to the Head Office (default branch).
-- When purchasing mode is split (each branch buys its own stock) every customer belongs to its home branch only,
-- so their scope and credit scope are set to branch here too. Switching to split in Shop Settings does the same.
-- Idempotent: safe to run more than once.
-- Note for editors: the migration runner splits on semicolons and tracks quotes, so comments must not contain either.

SET @ho = (SELECT id FROM locations ORDER BY is_head_office DESC, id LIMIT 1);

UPDATE customers SET home_location_id = @ho WHERE home_location_id IS NULL AND @ho IS NOT NULL;

UPDATE customers SET branch_scope = 'branch', credit_scope = 'branch'
WHERE (SELECT purchase_mode FROM shop_settings WHERE location_id = @ho) = 'split';
