-- Compliance lifecycle: frequency values, history comments, Audit authority
SET NAMES utf8mb4;

-- Widen frequency for Daily / Weekly / Yearly
ALTER TABLE `compliances`
  MODIFY COLUMN `frequency` VARCHAR(32) NOT NULL DEFAULT 'monthly';

-- Optional comment on each history row
ALTER TABLE `compliance_history`
  ADD COLUMN `comment` text DEFAULT NULL AFTER `description`;

INSERT IGNORE INTO `authorities` (`name`) VALUES ('Audit');
