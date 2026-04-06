-- Compliance Management SaaS - MySQL Schema
-- Easy Home Finance

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Roles
-- ----------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `roles` (`name`, `slug`) VALUES
('Admin', 'admin'),
('Maker', 'maker'),
('Reviewer', 'reviewer'),
('Approver', 'approver');

-- ----------------------------
-- Organizations (multi-tenant)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `organizations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `registration_number` varchar(100) DEFAULT NULL,
  `address` text,
  `contact_email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `company_size` varchar(80) DEFAULT NULL,
  `timezone` varchar(80) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `logo_path` varchar(500) DEFAULT NULL,
  `onboarding_step` tinyint DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Users
-- ----------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `role_id` int unsigned NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','pending') DEFAULT 'active',
  `email_verified_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_org` (`email`,`organization_id`),
  KEY `organization_id` (`organization_id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Subscriptions & Billing
-- ----------------------------
CREATE TABLE IF NOT EXISTS `plans` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `amount_monthly` decimal(12,2) NOT NULL,
  `amount_display` varchar(50) DEFAULT NULL,
  `is_custom` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `plans` (`name`, `slug`, `amount_monthly`, `amount_display`, `is_custom`) VALUES
('Starter', 'starter', 6499.00, '₹6,499/month', 0),
('Professional', 'professional', 20499.00, '₹20,499/month', 0),
('Enterprise', 'enterprise', 0.00, 'Custom', 1);

CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `plan_id` int unsigned NOT NULL,
  `status` enum('trial','active','cancelled','expired') DEFAULT 'trial',
  `trial_ends_at` datetime DEFAULT NULL,
  `current_period_start` datetime DEFAULT NULL,
  `current_period_end` datetime DEFAULT NULL,
  `card_last4` varchar(4) DEFAULT NULL,
  `card_verified` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `organization_id` (`organization_id`),
  KEY `plan_id` (`plan_id`),
  CONSTRAINT `subs_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subs_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `billing_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `invoice_id` varchar(50) NOT NULL,
  `plan_name` varchar(50) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('paid','pending','failed','refunded') DEFAULT 'pending',
  `billing_date` date NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `organization_id` (`organization_id`),
  CONSTRAINT `billing_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Compliances
-- ----------------------------
CREATE TABLE IF NOT EXISTS `authorities` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `authorities` (`name`) VALUES ('RBI'), ('NHB'), ('SEBI'), ('Internal Policy'), ('Audit');

CREATE TABLE IF NOT EXISTS `compliances` (
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
  UNIQUE KEY `org_code` (`organization_id`,`compliance_code`),
  KEY `organization_id` (`organization_id`),
  KEY `owner_id` (`owner_id`),
  KEY `authority_id` (`authority_id`),
  CONSTRAINT `compliances_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `compliances_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`),
  CONSTRAINT `compliances_authority` FOREIGN KEY (`authority_id`) REFERENCES `authorities` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Compliance submissions (per period / instance)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `compliance_submissions` (
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
  CONSTRAINT `submissions_compliance` FOREIGN KEY (`compliance_id`) REFERENCES `compliances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `submissions_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Compliance documents (evidence)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `compliance_documents` (
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
  CONSTRAINT `documents_compliance` FOREIGN KEY (`compliance_id`) REFERENCES `compliances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `documents_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Compliance workflow steps (process checklist)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `compliance_workflow_steps` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `compliance_id` int unsigned NOT NULL,
  `submission_id` int unsigned DEFAULT NULL,
  `step_order` tinyint NOT NULL,
  `step_name` varchar(100) NOT NULL,
  `status` enum('pending','completed') DEFAULT 'pending',
  `completed_by` int unsigned DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `comment` text,
  PRIMARY KEY (`id`),
  KEY `compliance_id` (`compliance_id`),
  KEY `submission_id` (`submission_id`),
  CONSTRAINT `workflow_compliance` FOREIGN KEY (`compliance_id`) REFERENCES `compliances` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Compliance history / activity
-- ----------------------------
CREATE TABLE IF NOT EXISTS `compliance_history` (
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
  CONSTRAINT `history_compliance` FOREIGN KEY (`compliance_id`) REFERENCES `compliances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Circulars (Circular Intelligence)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `circulars` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `circular_code` varchar(20) NOT NULL,
  `title` varchar(500) NOT NULL,
  `authority` varchar(100) NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `circular_date` date DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `impact` enum('low','medium','high') DEFAULT 'medium',
  `status` enum('uploaded','ai_analyzed','pending_approval','approved','rejected') DEFAULT 'uploaded',
  `document_path` varchar(500) DEFAULT NULL,
  `document_name` varchar(255) DEFAULT NULL,
  `document_raw_text` mediumtext,
  `content_summary` text,
  `ai_executive_summary` text,
  `ai_department` varchar(100) DEFAULT NULL,
  `ai_secondary_dept` varchar(100) DEFAULT NULL,
  `ai_frequency` varchar(50) DEFAULT NULL,
  `ai_due_date` varchar(100) DEFAULT NULL,
  `ai_risk_level` varchar(20) DEFAULT NULL,
  `ai_priority` varchar(20) DEFAULT NULL,
  `ai_owner` varchar(100) DEFAULT NULL,
  `ai_workflow` varchar(50) DEFAULT NULL,
  `ai_approver_tags` varchar(500) DEFAULT NULL,
  `ai_penalty` text,
  `review_department` varchar(100) DEFAULT NULL,
  `review_secondary_dept` varchar(100) DEFAULT NULL,
  `review_owner_id` int unsigned DEFAULT NULL,
  `review_workflow` varchar(50) DEFAULT NULL,
  `review_frequency` varchar(50) DEFAULT NULL,
  `review_risk` varchar(20) DEFAULT NULL,
  `review_priority` varchar(20) DEFAULT NULL,
  `review_due_date` date DEFAULT NULL,
  `review_expected_date` date DEFAULT NULL,
  `review_penalty` text,
  `review_remarks` text,
  `final_department` varchar(100) DEFAULT NULL,
  `final_risk_level` varchar(20) DEFAULT NULL,
  `final_priority` varchar(20) DEFAULT NULL,
  `final_owner_label` varchar(150) DEFAULT NULL,
  `linked_compliance_id` int unsigned DEFAULT NULL,
  `approved_by` int unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `uploaded_by` int unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `org_circular_code` (`organization_id`,`circular_code`),
  KEY `organization_id` (`organization_id`),
  KEY `linked_compliance_id` (`linked_compliance_id`),
  CONSTRAINT `circulars_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- ----------------------------
-- Delegation of Authority
-- ----------------------------
CREATE TABLE IF NOT EXISTS `delegation_authority` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `rule_code` varchar(20) DEFAULT NULL,
  `organization_id` int unsigned NOT NULL,
  `department` varchar(100) NOT NULL,
  `level_order` tinyint NOT NULL,
  `designation` varchar(150) NOT NULL,
  `approval_type` varchar(100) NOT NULL DEFAULT 'Expense Approval',
  `approval_limit` decimal(15,2) NOT NULL COMMENT 'in INR max at this level',
  `min_amount` decimal(15,2) NOT NULL DEFAULT 0,
  `conditions` text,
  `is_unlimited` tinyint(1) NOT NULL DEFAULT 0,
  `limit_display` varchar(50) DEFAULT NULL,
  `status` enum('active','temporary','inactive') DEFAULT 'active',
  `expires_at` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `organization_id` (`organization_id`),
  CONSTRAINT `doa_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Authority Matrix (workflow responsibility)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `authority_matrix` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `compliance_area` varchar(255) NOT NULL,
  `department` varchar(100) NOT NULL,
  `frequency` varchar(50) NOT NULL,
  `maker_id` int unsigned DEFAULT NULL,
  `maker_role_label` varchar(100) DEFAULT NULL,
  `reviewer_id` int unsigned DEFAULT NULL,
  `reviewer_role_label` varchar(100) DEFAULT NULL,
  `approver_id` int unsigned DEFAULT NULL,
  `approver_role_label` varchar(100) DEFAULT NULL,
  `workflow_level` varchar(50) DEFAULT NULL,
  `risk_level` enum('low','medium','high') DEFAULT 'medium',
  `escalation_days_before` int DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `organization_id` (`organization_id`),
  KEY `maker_id` (`maker_id`),
  KEY `reviewer_id` (`reviewer_id`),
  KEY `approver_id` (`approver_id`),
  CONSTRAINT `matrix_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `matrix_maker` FOREIGN KEY (`maker_id`) REFERENCES `users` (`id`),
  CONSTRAINT `matrix_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `matrix_approver` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- ----------------------------
-- Organization invites
-- ----------------------------
CREATE TABLE IF NOT EXISTS `organization_invites` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `role_id` int unsigned NOT NULL,
  `expires_at` datetime NOT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `created_by` int unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `organization_id` (`organization_id`),
  KEY `token` (`token`),
  CONSTRAINT `invites_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invites_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Activity logs (global)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int unsigned DEFAULT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `organization_id` (`organization_id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `activity_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Settings (per organization)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `key_name` varchar(100) NOT NULL,
  `value` text,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `org_key` (`organization_id`,`key_name`),
  CONSTRAINT `settings_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Financial ratios (for Financial Ratios module)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `financial_ratio_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `financial_ratio_categories` (`name`, `slug`) VALUES
('Capital Adequacy', 'capital-adequacy'),
('Leverage Ratio', 'leverage-ratio'),
('Exposure Limits', 'exposure-limits'),
('Provisioning', 'provisioning');

CREATE TABLE IF NOT EXISTS `financial_ratios` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `category_id` int unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `regulatory_limit` varchar(100) NOT NULL,
  `current_value` varchar(50) NOT NULL,
  `status` enum('compliant','watch','non_compliant') DEFAULT 'compliant',
  `updated_at` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `organization_id` (`organization_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `ratios_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ratios_category` FOREIGN KEY (`category_id`) REFERENCES `financial_ratio_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- Seed default organization and demo users (run database/seed_demo_users.php to set passwords: admin123, maker123, Reviewer@123, Approver@123)
INSERT INTO `organizations` (`id`, `name`, `industry`, `registration_number`, `address`, `contact_email`, `onboarding_step`) VALUES
(1, 'Easy Home Finance', 'NBFC', 'REG001', 'Sample Address', 'admin@easyhome.com', 3);

INSERT INTO `users` (`organization_id`, `role_id`, `full_name`, `email`, `password`, `department`, `status`) VALUES
(1, 1, 'Admin User', 'admin@easyhome.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'active'),
(1, 2, 'Maker User', 'maker@easyhome.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Operations', 'active'),
(1, 3, 'Reviewer User', 'reviewer@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Compliance', 'active'),
(1, 4, 'Approver User', 'approver@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Management', 'active')
;

INSERT INTO `subscriptions` (`organization_id`, `plan_id`, `status`, `trial_ends_at`, `current_period_start`, `current_period_end`, `card_verified`) VALUES
(1, 2, 'active', DATE_ADD(NOW(), INTERVAL 14 DAY), NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH), 1);

SET FOREIGN_KEY_CHECKS = 1;
