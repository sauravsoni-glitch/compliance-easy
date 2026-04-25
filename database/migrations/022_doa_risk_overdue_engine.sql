-- DOA v2: risk / overdue / priority based rules + compliance audit log
-- Run on MySQL 5.7+ / 8+. Safe to re-run if tables already exist (adjust manually if needed).

CREATE TABLE IF NOT EXISTS `doa_rules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `rule_set_id` int unsigned NOT NULL,
  `rule_name` varchar(255) NOT NULL,
  `department` varchar(100) NOT NULL,
  `condition_type` enum('Normal','Overdue','Risk','Priority') NOT NULL,
  `condition_value` varchar(50) DEFAULT NULL,
  `level` tinyint unsigned NOT NULL,
  `role` varchar(50) NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `org_rule_set` (`organization_id`,`rule_set_id`),
  KEY `org_dept_cond` (`organization_id`,`department`,`condition_type`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `doa_approval_logs`;
CREATE TABLE `doa_approval_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `compliance_id` int unsigned NOT NULL,
  `level` int NOT NULL,
  `role` varchar(50) NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `action` enum('Approved','Rejected','Rework') NOT NULL,
  `comment` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `org_c` (`organization_id`,`compliance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `roles` (`name`, `slug`) VALUES
  ('Compliance Head','compliance_head'),
  ('Senior Reviewer','senior_reviewer'),
  ('Management','management');

-- Optional: add DOA state columns on compliances (also auto-added by app if missing)
-- ALTER TABLE `compliances` ADD COLUMN `doa_rule_set_id` int unsigned NULL AFTER `approver_id`;
-- ALTER TABLE `compliances` ADD COLUMN `doa_applied_condition` varchar(32) NULL AFTER `doa_rule_set_id`;
-- ALTER TABLE `compliances` ADD COLUMN `doa_current_level` tinyint unsigned NOT NULL DEFAULT 1 AFTER `doa_applied_condition`;
-- ALTER TABLE `compliances` ADD COLUMN `doa_total_levels` tinyint unsigned NOT NULL DEFAULT 1 AFTER `doa_current_level`;
-- ALTER TABLE `compliances` ADD COLUMN `doa_active_user_id` int unsigned NULL AFTER `doa_total_levels`;
