-- IT Governance modules: assets, vulnerabilities, access reviews, vendor assurance, policy exceptions
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `it_asset_register` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `asset_code` varchar(24) NOT NULL,
  `item_name` varchar(500) NOT NULL,
  `category` varchar(120) NOT NULL DEFAULT 'General',
  `criticality` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` enum('open','in_progress','closed') NOT NULL DEFAULT 'open',
  `owner` varchar(255) NOT NULL DEFAULT '',
  `last_reviewed` date DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `it_asset_org_code` (`organization_id`,`asset_code`),
  KEY `organization_id` (`organization_id`),
  CONSTRAINT `it_asset_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `it_vulnerability_register` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `vuln_code` varchar(24) NOT NULL,
  `item_name` varchar(500) NOT NULL,
  `category` varchar(120) NOT NULL DEFAULT 'General',
  `criticality` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` enum('open','in_progress','closed') NOT NULL DEFAULT 'open',
  `owner` varchar(255) NOT NULL DEFAULT '',
  `last_reviewed` date DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `it_vuln_org_code` (`organization_id`,`vuln_code`),
  KEY `organization_id` (`organization_id`),
  CONSTRAINT `it_vuln_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `it_access_reviews` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `review_code` varchar(24) NOT NULL,
  `item_name` varchar(500) NOT NULL,
  `category` varchar(120) NOT NULL DEFAULT 'General',
  `criticality` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` enum('open','in_progress','closed') NOT NULL DEFAULT 'open',
  `owner` varchar(255) NOT NULL DEFAULT '',
  `last_reviewed` date DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `it_access_org_code` (`organization_id`,`review_code`),
  KEY `organization_id` (`organization_id`),
  CONSTRAINT `it_access_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `it_vendor_assurance` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `vendor_code` varchar(24) NOT NULL,
  `item_name` varchar(500) NOT NULL,
  `category` varchar(120) NOT NULL DEFAULT 'General',
  `criticality` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` enum('open','in_progress','closed') NOT NULL DEFAULT 'open',
  `owner` varchar(255) NOT NULL DEFAULT '',
  `last_reviewed` date DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `it_vendor_org_code` (`organization_id`,`vendor_code`),
  KEY `organization_id` (`organization_id`),
  CONSTRAINT `it_vendor_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `it_policy_exceptions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int unsigned NOT NULL,
  `exception_code` varchar(24) NOT NULL,
  `item_name` varchar(500) NOT NULL,
  `category` varchar(120) NOT NULL DEFAULT 'General',
  `criticality` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` enum('open','in_progress','closed') NOT NULL DEFAULT 'open',
  `owner` varchar(255) NOT NULL DEFAULT '',
  `last_reviewed` date DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `it_exc_org_code` (`organization_id`,`exception_code`),
  KEY `organization_id` (`organization_id`),
  CONSTRAINT `it_exc_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
