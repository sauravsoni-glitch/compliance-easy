-- Password reset tokens (hashed; raw token only in email / job payload briefly)
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `prt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Simple queue for outbound work (e.g. password reset emails). Process with: php scripts/queue_worker.php
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(64) NOT NULL DEFAULT 'default',
  `payload` text NOT NULL,
  `attempts` tinyint unsigned NOT NULL DEFAULT 0,
  `available_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `queue_available` (`queue`, `available_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
