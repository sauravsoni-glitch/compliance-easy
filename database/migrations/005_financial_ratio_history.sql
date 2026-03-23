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
