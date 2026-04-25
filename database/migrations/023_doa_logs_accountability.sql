-- DOA accountability log (application also creates / migrates via DoaEngine::ensureSchema).

CREATE TABLE IF NOT EXISTS `doa_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `compliance_id` int unsigned NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `role` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `comment` text,
  `level` int NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `org_compliance` (`organization_id`,`compliance_id`),
  KEY `compliance_created` (`compliance_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
