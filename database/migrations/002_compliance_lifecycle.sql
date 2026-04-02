-- Compliance lifecycle: frequency values, history comments, Audit authority
SET NAMES utf8mb4;

-- Widen frequency for Daily / Weekly / Yearly
ALTER TABLE `compliances`
  MODIFY COLUMN `frequency` VARCHAR(32) NOT NULL DEFAULT 'monthly';

-- Optional comment on each history row
ALTER TABLE `compliance_history`
  ADD COLUMN `comment` text DEFAULT NULL AFTER `description`;

INSERT INTO `authorities` (`name`)
SELECT tmp.name FROM (SELECT 'Audit' AS name) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `authorities` a WHERE a.name = tmp.name LIMIT 1);
