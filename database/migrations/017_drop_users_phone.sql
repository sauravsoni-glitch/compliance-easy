-- Drop users.phone if it exists (safe re-run)
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'phone'
);

SET @sql := IF(
    @col_exists > 0,
    'ALTER TABLE users DROP COLUMN phone',
    'SELECT ''users.phone does not exist'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
