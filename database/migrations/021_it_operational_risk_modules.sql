-- Operational risk modules under IT compliance: Identification, Assessment, Controls, KRIs
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `it_risk_org_settings` (
  `organization_id` int unsigned NOT NULL,
  `risk_appetite_score` int unsigned NOT NULL DEFAULT 50,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`organization_id`),
  CONSTRAINT `it_risk_org_settings_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `it_risk_identifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `risk_code` varchar(20) NOT NULL,
  `risk_name` varchar(500) NOT NULL,
  `category` varchar(100) NOT NULL,
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` enum('identified','assessed','mitigated','monitored','closed') NOT NULL DEFAULT 'identified',
  `owner` varchar(255) NOT NULL DEFAULT '',
  `date_identified` date NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `it_ri_org_code` (`organization_id`,`risk_code`),
  KEY `organization_id` (`organization_id`),
  CONSTRAINT `it_ri_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `it_risk_assessments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `assessment_code` varchar(20) NOT NULL,
  `risk_name` varchar(500) NOT NULL,
  `category` varchar(100) NOT NULL,
  `inherent_risk` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `residual_risk` enum('low','medium','high','critical') NOT NULL DEFAULT 'low',
  `owner` varchar(255) NOT NULL DEFAULT '',
  `last_assessment_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `it_ra_org_code` (`organization_id`,`assessment_code`),
  KEY `organization_id` (`organization_id`),
  CONSTRAINT `it_ra_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `it_risk_controls` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `control_code` varchar(20) NOT NULL,
  `control_name` varchar(500) NOT NULL,
  `description` text,
  `risk_category` varchar(100) NOT NULL,
  `control_type` enum('preventive','detective','corrective') NOT NULL DEFAULT 'preventive',
  `frequency` varchar(80) NOT NULL DEFAULT 'monthly',
  `effectiveness` enum('effective','partially_effective','ineffective') NOT NULL DEFAULT 'effective',
  `status` enum('active','under_review','improvement_required') NOT NULL DEFAULT 'active',
  `control_owner` varchar(255) NOT NULL DEFAULT '',
  `documentation` text,
  `testing_procedure` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `it_ctl_org_code` (`organization_id`,`control_code`),
  KEY `organization_id` (`organization_id`),
  CONSTRAINT `it_ctl_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `it_kris` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `kri_code` varchar(20) NOT NULL,
  `kri_name` varchar(500) NOT NULL,
  `description` text,
  `measurement_unit` varchar(80) DEFAULT NULL,
  `frequency` varchar(50) NOT NULL DEFAULT 'monthly',
  `current_value` decimal(16,6) DEFAULT NULL,
  `threshold_value` decimal(16,6) DEFAULT NULL,
  `status` enum('active','inactive','under_review') NOT NULL DEFAULT 'active',
  `owner` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `it_kri_org_code` (`organization_id`,`kri_code`),
  KEY `organization_id` (`organization_id`),
  CONSTRAINT `it_kri_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `it_kri_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `kri_id` int unsigned NOT NULL,
  `period_month` date NOT NULL,
  `actual_value` decimal(16,6) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `it_kri_hist_uq` (`kri_id`,`period_month`),
  KEY `organization_id` (`organization_id`),
  KEY `kri_id` (`kri_id`),
  CONSTRAINT `it_kri_hist_kri` FOREIGN KEY (`kri_id`) REFERENCES `it_kris` (`id`) ON DELETE CASCADE,
  CONSTRAINT `it_kri_hist_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
