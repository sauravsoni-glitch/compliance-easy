ALTER TABLE `compliances`
  ADD COLUMN `evidence_type` varchar(100) DEFAULT NULL AFTER `evidence_required`;
