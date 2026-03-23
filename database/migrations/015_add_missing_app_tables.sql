-- Adds tables that exist in database/schema.sql but are often missing on older DBs
-- (e.g. only 20 tables after an early import). Safe to run multiple times (IF NOT EXISTS).
-- Run: mysql -u root -p compliance_saas < database/migrations/015_add_missing_app_tables.sql

SET NAMES utf8mb4;

-- Circular Intelligence – activity audit trail
CREATE TABLE IF NOT EXISTS `circular_activity` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `circular_id` int unsigned NOT NULL,
  `action` varchar(80) NOT NULL,
  `detail` text,
  `user_id` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `circular_id` (`circular_id`),
  CONSTRAINT `circ_act_circ` FOREIGN KEY (`circular_id`) REFERENCES `circulars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bulk Upload – per-upload log (history tab)
CREATE TABLE IF NOT EXISTS `bulk_upload_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `upload_kind` varchar(40) NOT NULL DEFAULT 'compliance',
  `file_name` varchar(255) NOT NULL,
  `uploaded_by` int unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `records_total` int unsigned NOT NULL DEFAULT 0,
  `records_ok` int unsigned NOT NULL DEFAULT 0,
  `records_fail` int unsigned NOT NULL DEFAULT 0,
  `status` enum('completed','failed','partial') NOT NULL DEFAULT 'completed',
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `organization_id` (`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Financial Ratios – upload history per ratio row
CREATE TABLE IF NOT EXISTS `financial_ratio_upload_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ratio_id` int unsigned NOT NULL,
  `value` varchar(50) NOT NULL,
  `status` enum('compliant','watch','non_compliant') NOT NULL,
  `uploaded_at` date NOT NULL,
  `uploaded_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ratio_id` (`ratio_id`),
  CONSTRAINT `fr_history_ratio` FOREIGN KEY (`ratio_id`) REFERENCES `financial_ratios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fr_history_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Financial Ratios – per-category reminder
CREATE TABLE IF NOT EXISTS `financial_ratio_category_reminders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `category_id` int unsigned NOT NULL,
  `reminder_date` date NOT NULL,
  `note` text,
  `repeat_monthly` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `org_category` (`organization_id`,`category_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `frr_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `frr_cat` FOREIGN KEY (`category_id`) REFERENCES `financial_ratio_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `frr_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
