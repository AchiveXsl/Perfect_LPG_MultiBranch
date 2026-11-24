-- Perfect LPG - several cylinder types may share one capacity
-- Only cylinder_types.code stays unique. All stock, rates, costs and sales use cylinder_type_id, never the capacity.
-- Drops every single-column unique index on cylinder_types.capacity_kg.
-- Idempotent: safe to run more than once.
-- Note for editors: the migration runner splits on semicolons and tracks quotes, so comments must not contain either.

SET @idx = (
  SELECT MIN(s.INDEX_NAME) FROM INFORMATION_SCHEMA.STATISTICS s
  WHERE s.TABLE_SCHEMA = DATABASE() AND s.TABLE_NAME = 'cylinder_types' AND s.COLUMN_NAME = 'capacity_kg' AND s.NON_UNIQUE = 0
    AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS t WHERE t.TABLE_SCHEMA = s.TABLE_SCHEMA AND t.TABLE_NAME = s.TABLE_NAME AND t.INDEX_NAME = s.INDEX_NAME) = 1
);
SET @sql = IF(@idx IS NULL, 'SELECT 1', CONCAT('ALTER TABLE cylinder_types DROP INDEX `', @idx, '`'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
