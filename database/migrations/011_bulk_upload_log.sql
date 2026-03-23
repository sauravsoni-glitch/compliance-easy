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
