-- Circular Intelligence AI / review / audit (run once on existing DBs)
ALTER TABLE `circulars`
  ADD COLUMN `ai_secondary_dept` varchar(100) DEFAULT NULL AFTER `ai_department`,
  ADD COLUMN `document_raw_text` mediumtext NULL AFTER `document_name`,
  ADD COLUMN `ai_approver_tags` varchar(500) DEFAULT NULL AFTER `ai_workflow`,
  ADD COLUMN `review_department` varchar(100) DEFAULT NULL AFTER `ai_approver_tags`,
  ADD COLUMN `review_secondary_dept` varchar(100) DEFAULT NULL,
  ADD COLUMN `review_owner_id` int unsigned DEFAULT NULL,
  ADD COLUMN `review_workflow` varchar(50) DEFAULT NULL,
  ADD COLUMN `review_frequency` varchar(50) DEFAULT NULL,
  ADD COLUMN `review_risk` varchar(20) DEFAULT NULL,
  ADD COLUMN `review_priority` varchar(20) DEFAULT NULL,
  ADD COLUMN `review_due_date` date DEFAULT NULL,
  ADD COLUMN `review_expected_date` date DEFAULT NULL,
  ADD COLUMN `review_penalty` text NULL,
  ADD COLUMN `review_remarks` text NULL,
  ADD COLUMN `final_department` varchar(100) DEFAULT NULL,
  ADD COLUMN `final_risk_level` varchar(20) DEFAULT NULL,
  ADD COLUMN `final_priority` varchar(20) DEFAULT NULL,
  ADD COLUMN `final_owner_label` varchar(150) DEFAULT NULL;

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
