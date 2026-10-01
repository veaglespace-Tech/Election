-- ============================================================
-- Election Database Setup
-- Run this FIRST in your MySQL/phpMyAdmin
-- ============================================================

CREATE DATABASE IF NOT EXISTS `election_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `election_db`;

-- ============================================================
-- Main electors table (supports all 104 parts)
-- ============================================================
CREATE TABLE IF NOT EXISTS `electors` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `part_no`       SMALLINT UNSIGNED NOT NULL,
  `sr_no`         SMALLINT UNSIGNED NOT NULL,
  `elector_name`  VARCHAR(300) NOT NULL,
  `relative_name` VARCHAR(300) DEFAULT '',
  `address`       TEXT,
  `institute`     VARCHAR(500) DEFAULT '',
  `age`           TINYINT UNSIGNED DEFAULT NULL,
  `gender`        CHAR(1) DEFAULT '',
  `epic_no`       VARCHAR(20) DEFAULT '',
  UNIQUE KEY `uq_part_sr`  (`part_no`, `sr_no`),
  INDEX `idx_name`    (`elector_name`(80)),
  INDEX `idx_epic`    (`epic_no`),
  INDEX `idx_age`     (`age`),
  INDEX `idx_gender`  (`gender`),
  INDEX `idx_part`    (`part_no`),
  INDEX `idx_addr`    (`address`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AFTER creating the table, import Part 5 data:
-- Run electors_part5.sql
-- Or use phpMyAdmin → Import → select electors_part5.sql
-- ============================================================
