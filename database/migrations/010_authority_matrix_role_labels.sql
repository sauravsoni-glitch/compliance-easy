-- Display labels for workflow cards (e.g. Compliance Head, CFO)
ALTER TABLE `authority_matrix`
  ADD COLUMN `maker_role_label` varchar(100) DEFAULT NULL AFTER `maker_id`,
  ADD COLUMN `reviewer_role_label` varchar(100) DEFAULT NULL AFTER `reviewer_id`,
  ADD COLUMN `approver_role_label` varchar(100) DEFAULT NULL AFTER `approver_id`;
