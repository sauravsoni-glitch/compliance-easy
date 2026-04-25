ALTER TABLE `compliances`
    ADD COLUMN IF NOT EXISTS `objective_text` text NULL AFTER `description`,
    ADD COLUMN IF NOT EXISTS `expected_outcome` text NULL AFTER `objective_text`,
    ADD COLUMN IF NOT EXISTS `final_debrief_comment` text NULL AFTER `expected_outcome`,
    ADD COLUMN IF NOT EXISTS `final_debrief_lessons` text NULL AFTER `final_debrief_comment`,
    ADD COLUMN IF NOT EXISTS `final_debrief_by` int unsigned NULL AFTER `final_debrief_lessons`,
    ADD COLUMN IF NOT EXISTS `final_debrief_at` datetime NULL AFTER `final_debrief_by`;

CREATE TABLE IF NOT EXISTS `compliance_discussions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `compliance_id` int unsigned NOT NULL,
  `parent_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `org_cmp_created` (`organization_id`,`compliance_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `compliance_checkpoints` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `compliance_id` int unsigned NOT NULL,
  `step_order` int unsigned NOT NULL DEFAULT 1,
  `title` varchar(255) NOT NULL,
  `status` enum('pending','completed','rework') NOT NULL DEFAULT 'pending',
  `comment` text NULL,
  `proof_document_id` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `org_cmp_order` (`organization_id`,`compliance_id`,`step_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
