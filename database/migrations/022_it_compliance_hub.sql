-- IT Compliance Hub: incidents, anomalies, resilience, lessons, evidence register
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `it_incidents` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `incident_code` varchar(24) NOT NULL,
  `title` varchar(500) NOT NULL,
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` enum('open','investigating','contained','closed') NOT NULL DEFAULT 'open',
  `reported_at` date DEFAULT NULL,
  `owner` varchar(255) NOT NULL DEFAULT '',
  `description` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `it_inc_org_code` (`organization_id`,`incident_code`),
  KEY `organization_id` (`organization_id`),
  CONSTRAINT `it_inc_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `it_anomalies` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `anomaly_code` varchar(24) NOT NULL,
  `title` varchar(500) NOT NULL,
  `source` varchar(255) NOT NULL DEFAULT '',
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` enum('new','triaged','false_positive','resolved') NOT NULL DEFAULT 'new',
  `detected_at` date DEFAULT NULL,
  `owner` varchar(255) NOT NULL DEFAULT '',
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `it_anom_org_code` (`organization_id`,`anomaly_code`),
  KEY `organization_id` (`organization_id`),
  CONSTRAINT `it_anom_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `it_resilience_checks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `check_code` varchar(24) NOT NULL,
  `name` varchar(500) NOT NULL,
  `check_type` enum('bcp','dr','backup','test','other') NOT NULL DEFAULT 'other',
  `frequency` varchar(80) NOT NULL DEFAULT 'Annual',
  `last_tested` date DEFAULT NULL,
  `next_due` date DEFAULT NULL,
  `status` enum('planned','scheduled','overdue','passed','failed') NOT NULL DEFAULT 'planned',
  `owner` varchar(255) NOT NULL DEFAULT '',
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `it_res_org_code` (`organization_id`,`check_code`),
  KEY `organization_id` (`organization_id`),
  CONSTRAINT `it_res_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `it_lessons_learned` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `lesson_code` varchar(24) NOT NULL,
  `title` varchar(500) NOT NULL,
  `category` varchar(120) NOT NULL DEFAULT 'General',
  `related_reference` varchar(255) NOT NULL DEFAULT '',
  `summary` text,
  `action_items` text,
  `owner` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `it_ll_org_code` (`organization_id`,`lesson_code`),
  KEY `organization_id` (`organization_id`),
  CONSTRAINT `it_ll_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `it_evidence_registry` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `evidence_code` varchar(24) NOT NULL,
  `title` varchar(500) NOT NULL,
  `category` varchar(120) NOT NULL DEFAULT 'General',
  `storage_location` varchar(500) NOT NULL DEFAULT '',
  `classification` enum('internal','confidential','restricted') NOT NULL DEFAULT 'internal',
  `last_reviewed` date DEFAULT NULL,
  `owner` varchar(255) NOT NULL DEFAULT '',
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `it_evd_org_code` (`organization_id`,`evidence_code`),
  KEY `organization_id` (`organization_id`),
  CONSTRAINT `it_evd_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
