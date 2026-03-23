-- Maker-reported completion date on submission
ALTER TABLE `compliance_submissions`
  ADD COLUMN `maker_completion_date` date DEFAULT NULL AFTER `maker_created_date`;
