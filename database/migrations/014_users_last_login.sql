-- Last login tracking for Roles & Permissions table
ALTER TABLE `users`
  ADD COLUMN `last_login_at` datetime DEFAULT NULL AFTER `email_verified_at`;
