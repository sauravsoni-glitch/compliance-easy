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
