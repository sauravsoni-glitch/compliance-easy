-- DOA rule-engine enhancements (lean, reusable across modules)
ALTER TABLE `delegation_authority`
  ADD COLUMN IF NOT EXISTS `rule_name` varchar(255) NULL AFTER `rule_code`,
  ADD COLUMN IF NOT EXISTS `role_slug` varchar(50) NULL AFTER `designation`,
  ADD COLUMN IF NOT EXISTS `user_id` int unsigned NULL AFTER `role_slug`,
  ADD COLUMN IF NOT EXISTS `is_amount_based` tinyint(1) NOT NULL DEFAULT 0 AFTER `approval_type`,
  ADD COLUMN IF NOT EXISTS `effective_from` date NULL AFTER `is_amount_based`,
  ADD COLUMN IF NOT EXISTS `effective_to` date NULL AFTER `effective_from`;

CREATE TABLE IF NOT EXISTS `doa_approval_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `module_name` varchar(50) NOT NULL,
  `record_id` int unsigned NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `level` int NOT NULL DEFAULT 1,
  `approved_by` int unsigned DEFAULT NULL,
  `status` enum('Approved','Rejected','Pending') NOT NULL DEFAULT 'Pending',
  `comment` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `org_idx` (`organization_id`),
  KEY `module_record_idx` (`module_name`,`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
