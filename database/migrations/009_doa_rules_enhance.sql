ALTER TABLE `delegation_authority`
  ADD COLUMN `approval_type` varchar(100) NOT NULL DEFAULT 'Expense Approval' AFTER `designation`,
  ADD COLUMN `min_amount` decimal(15,2) NOT NULL DEFAULT 0 AFTER `approval_limit`,
  ADD COLUMN `conditions` text NULL AFTER `min_amount`,
  ADD COLUMN `is_unlimited` tinyint(1) NOT NULL DEFAULT 0 AFTER `conditions`,
  ADD COLUMN `rule_code` varchar(20) DEFAULT NULL AFTER `id`;
