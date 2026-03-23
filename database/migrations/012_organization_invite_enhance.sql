ALTER TABLE `organization_invites`
  ADD COLUMN `full_name` varchar(255) DEFAULT NULL AFTER `organization_id`,
  ADD COLUMN `department` varchar(100) DEFAULT NULL AFTER `full_name`;
