-- IT Compliance module: parallel tables + it_admin role + optional IT authority labels
SET NAMES utf8mb4;

INSERT INTO `roles` (`name`, `slug`) VALUES ('IT Admin', 'it_admin')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `authorities` (`name`)
SELECT v.name FROM (
  SELECT 'ISO 27001' AS name
  UNION ALL SELECT 'RBI Cyber Security'
  UNION ALL SELECT 'DPDP / Data Protection'
  UNION ALL SELECT 'Internal IT Policy'
) AS v
WHERE NOT EXISTS (SELECT 1 FROM `authorities` a WHERE a.name = v.name LIMIT 1);

CREATE TABLE IF NOT EXISTS `it_compliances` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `compliance_code` varchar(20) NOT NULL,
  `title` varchar(500) NOT NULL,
  `authority_id` int unsigned NOT NULL,
  `circular_reference` varchar(100) DEFAULT NULL,
  `department` varchar(100) NOT NULL,
  `risk_level` enum('low','medium','high','critical') NOT NULL,
  `priority` enum('low','medium','high','critical') NOT NULL,
  `frequency` varchar(32) NOT NULL DEFAULT 'monthly',
  `description` text,
  `penalty_impact` text,
  `owner_id` int unsigned NOT NULL,
  `reviewer_id` int unsigned DEFAULT NULL,
  `approver_id` int unsigned DEFAULT NULL,
  `workflow_type` varchar(50) DEFAULT 'two-level',
  `evidence_required` tinyint(1) DEFAULT 1,
  `evidence_type` varchar(100) DEFAULT NULL,
  `checklist_items` json DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `expected_date` date DEFAULT NULL,
  `reminder_date` date DEFAULT NULL,
  `status` enum('draft','pending','submitted','under_review','rework','approved','rejected','completed','overdue') DEFAULT 'draft',
  `created_by` int unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `it_org_code` (`organization_id`,`compliance_code`),
  KEY `organization_id` (`organization_id`),
  KEY `owner_id` (`owner_id`),
  KEY `authority_id` (`authority_id`),
  CONSTRAINT `it_compliances_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `it_compliances_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`),
  CONSTRAINT `it_compliances_authority` FOREIGN KEY (`authority_id`) REFERENCES `authorities` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `it_compliance_submissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `compliance_id` int unsigned NOT NULL,
  `submit_for_month` date NOT NULL,
  `submission_date` datetime DEFAULT NULL,
  `uploaded_by` int unsigned NOT NULL,
  `maker_created_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `maker_completion_date` date DEFAULT NULL,
  `document_path` varchar(500) DEFAULT NULL,
  `document_name` varchar(255) DEFAULT NULL,
  `status` enum('draft','submitted','approved','rejected','rework') DEFAULT 'draft',
  `checker_id` int unsigned DEFAULT NULL,
  `checker_remark` text,
  `checker_date` datetime DEFAULT NULL,
  `escalation_level` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `compliance_id` (`compliance_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `it_submissions_compliance` FOREIGN KEY (`compliance_id`) REFERENCES `it_compliances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `it_submissions_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `it_compliance_documents` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `compliance_id` int unsigned NOT NULL,
  `submission_id` int unsigned DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `document_kind` varchar(80) DEFAULT NULL,
  `upload_notes` text,
  `file_size` int unsigned DEFAULT NULL,
  `uploaded_by` int unsigned NOT NULL,
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `compliance_id` (`compliance_id`),
  KEY `submission_id` (`submission_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `it_documents_compliance` FOREIGN KEY (`compliance_id`) REFERENCES `it_compliances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `it_documents_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `it_compliance_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `compliance_id` int unsigned NOT NULL,
  `submission_id` int unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text,
  `comment` text DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `compliance_id` (`compliance_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `it_history_compliance` FOREIGN KEY (`compliance_id`) REFERENCES `it_compliances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `it_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
