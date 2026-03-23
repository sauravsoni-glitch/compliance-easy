-- Optional: document kind & notes for Reports Quick Upload
ALTER TABLE `compliance_documents`
  ADD COLUMN `document_kind` varchar(80) DEFAULT NULL AFTER `file_path`,
  ADD COLUMN `upload_notes` text NULL AFTER `document_kind`;
