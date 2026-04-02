-- Drop legacy phone columns on compliances if they exist (safe re-run)
SET @col_phone_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'compliances'
      AND COLUMN_NAME = 'phone'
);

SET @sql_phone := IF(
    @col_phone_exists > 0,
    'ALTER TABLE compliances DROP COLUMN phone',
    'SELECT ''compliances.phone does not exist'' AS message'
);

PREPARE stmt_phone FROM @sql_phone;
EXECUTE stmt_phone;
DEALLOCATE PREPARE stmt_phone;

SET @col_phone_number_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'compliances'
      AND COLUMN_NAME = 'phone_number'
);

SET @sql_phone_number := IF(
    @col_phone_number_exists > 0,
    'ALTER TABLE compliances DROP COLUMN phone_number',
    'SELECT ''compliances.phone_number does not exist'' AS message'
);

PREPARE stmt_phone_number FROM @sql_phone_number;
EXECUTE stmt_phone_number;
DEALLOCATE PREPARE stmt_phone_number;
