CREATE DATABASE IF NOT EXISTS `jfgfmnue_kursantplus`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `jfgfmnue_kursantplus`;

CREATE TABLE IF NOT EXISTS `contact_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(40) NOT NULL,
  `email` VARCHAR(190) DEFAULT NULL,
  `topic` VARCHAR(120) NOT NULL,
  `message` TEXT NOT NULL,
  `privacy_consent` TINYINT(1) NOT NULL DEFAULT 0,
  `source_page` VARCHAR(255) DEFAULT NULL,
  `request_origin` VARCHAR(255) DEFAULT NULL,
  `referer` VARCHAR(255) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'new',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contact_requests_created_at` (`created_at`),
  KEY `idx_contact_requests_status` (`status`),
  KEY `idx_contact_requests_topic` (`topic`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
