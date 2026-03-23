ALTER TABLE `organizations`
  ADD COLUMN `company_size` varchar(80) DEFAULT NULL,
  ADD COLUMN `timezone` varchar(80) DEFAULT NULL,
  ADD COLUMN `city` varchar(100) DEFAULT NULL,
  ADD COLUMN `country` varchar(100) DEFAULT NULL,
  ADD COLUMN `logo_path` varchar(500) DEFAULT NULL;
