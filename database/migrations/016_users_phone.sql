-- Add phone column on users if missing (safe re-run)
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'phone'
);

SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE users ADD COLUMN phone VARCHAR(50) DEFAULT NULL AFTER email',
    'SELECT ''users.phone already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
