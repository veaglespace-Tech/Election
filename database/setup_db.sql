-- ============================================================
-- Election Database Setup
-- Run this FIRST in your MySQL/phpMyAdmin
-- ============================================================

CREATE DATABASE IF NOT EXISTS `election_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `election_db`;

-- ============================================================
-- Admins table
-- ============================================================
CREATE TABLE IF NOT EXISTS `admins` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(150) NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admins` (`name`, `email`, `password`)
VALUES ('admin', 'veaglespace@gmail.com', 'Veagle@123')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `password`=VALUES(`password`);

-- ============================================================
-- Main electors table
-- ============================================================
CREATE TABLE IF NOT EXISTS `electors` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `part_no`       SMALLINT UNSIGNED NOT NULL,
  `sr_no`         SMALLINT UNSIGNED NOT NULL,
  `elector_name`  VARCHAR(300) NOT NULL,
  `relative_name` VARCHAR(300) DEFAULT '',
  `address`       TEXT,
  `institute`     VARCHAR(500) DEFAULT '',
  `building`      TEXT DEFAULT '',
  `age`           TINYINT UNSIGNED DEFAULT NULL,
  `gender`        CHAR(1) DEFAULT '',
  `epic_no`       VARCHAR(20) DEFAULT '',
  `review`        VARCHAR(255) DEFAULT '',
  `status`        VARCHAR(10) NOT NULL DEFAULT 'ACTIVE',
  UNIQUE KEY `uq_part_sr`  (`part_no`, `sr_no`),
  INDEX `idx_name`    (`elector_name`(80)),
  INDEX `idx_epic`    (`epic_no`),
  INDEX `idx_age`     (`age`),
  INDEX `idx_gender`  (`gender`),
  INDEX `idx_part`    (`part_no`),
  INDEX `idx_status`  (`status`),
  INDEX `idx_addr`    (`address`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Districts table
-- ============================================================
CREATE TABLE IF NOT EXISTS `districts` (
  `id`   TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Polling Stations table
-- ============================================================
CREATE TABLE IF NOT EXISTS `polling_stations` (
  `station_no`   SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
  `district_id`  TINYINT UNSIGNED NOT NULL,
  `location`     VARCHAR(100) NOT NULL,
  `building`     TEXT NOT NULL,
  `area`         TEXT NULL,
  `voter_type`   ENUM('TEACHERS_ONLY','GRADUATES_ONLY','ALL_ELECTORS') NOT NULL DEFAULT 'TEACHERS_ONLY',
  `constituency` VARCHAR(80) NOT NULL DEFAULT '03 Pune Division Teachers'' Constituency',
  CONSTRAINT `fk_ps_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`),
  INDEX `idx_ps_district` (`district_id`),
  INDEX `idx_ps_location` (`location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Default Districts Seed
-- ============================================================
INSERT INTO `districts` (`id`, `name`) VALUES
  (1, 'Pune'),
  (2, 'Solapur'),
  (3, 'Satara'),
  (4, 'Sangli'),
  (5, 'Kolhapur')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- ============================================================
-- Site Settings & Carousel Slides table
-- ============================================================
CREATE TABLE IF NOT EXISTS `site_settings` (
  `skey`    VARCHAR(100) NOT NULL PRIMARY KEY,
  `sval`    MEDIUMTEXT   NOT NULL,
  `updated` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default Settings & Carousel
INSERT INTO `site_settings` (`skey`, `sval`) VALUES
  ('carousel_speed_ms', '2000'),
  ('carousel_slides', '[{"src":"images/slide1.jpg","alt":"Campaign Photo 1"},{"src":"images/slide2.jpg","alt":"Campaign Photo 2"},{"src":"images/slide3.jpg","alt":"Campaign Photo 3"}]')
ON DUPLICATE KEY UPDATE `sval`=VALUES(`sval`);

-- ============================================================
-- AFTER creating the tables, import data:
-- 1. Elector Data: Run electors_part5.sql
-- 2. Polling Stations Data: Run seed.sql
-- (Or use phpMyAdmin → Import)
-- ============================================================


