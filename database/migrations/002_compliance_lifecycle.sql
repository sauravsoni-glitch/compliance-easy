-- Compliance lifecycle: frequency values, history comments, Audit authority
SET NAMES utf8mb4;

-- Widen frequency for Daily / Weekly / Yearly
ALTER TABLE `compliances`
  MODIFY COLUMN `frequency` VARCHAR(32) NOT NULL DEFAULT 'monthly';

-- Optional comment on each history row (skip if schema.sql already added it)
SET @ch_comment := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'compliance_history'
      AND COLUMN_NAME = 'comment'
);
SET @sql_ch := IF(
    @ch_comment = 0,
    'ALTER TABLE `compliance_history` ADD COLUMN `comment` text DEFAULT NULL AFTER `description`',
    'SELECT ''compliance_history.comment already exists'' AS message'
);
PREPARE stmt_ch FROM @sql_ch;
EXECUTE stmt_ch;
DEALLOCATE PREPARE stmt_ch;

INSERT INTO `authorities` (`name`)
SELECT tmp.name FROM (SELECT 'Audit' AS name) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `authorities` a WHERE a.name = tmp.name LIMIT 1);
