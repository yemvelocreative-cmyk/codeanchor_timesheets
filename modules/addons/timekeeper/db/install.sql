-- timekeeper install.sql
-- Engine/charset normalized; tables created only if not exists
-- MySQL 5.7+ / 8.0 compatible

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- Assigned users (e.g., for cron timesheet creation targets)
CREATE TABLE IF NOT EXISTS `mod_timekeeper_assigned_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Departments
CREATE TABLE IF NOT EXISTS `mod_timekeeper_departments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_department_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hidden tabs per admin role
CREATE TABLE IF NOT EXISTS `mod_timekeeper_hidden_tabs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` INT UNSIGNED NOT NULL,
  `tab_name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_tab` (`role_id`, `tab_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions / settings
-- Stores both role-scoped and global settings (role_id optional).
CREATE TABLE IF NOT EXISTS `mod_timekeeper_permissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` INT UNSIGNED NULL,
  `setting_key` VARCHAR(50) NOT NULL,
  `setting_value` TEXT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_key` (`setting_key`),
  KEY `idx_role_key` (`role_id`, `setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Task categories (subtasks)
CREATE TABLE IF NOT EXISTS `mod_timekeeper_task_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_department` (`department_id`),
  UNIQUE KEY `uq_dept_name` (`department_id`, `name`),
  CONSTRAINT `fk_taskcat_department`
    FOREIGN KEY (`department_id`) REFERENCES `mod_timekeeper_departments` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Timesheets (one per admin per date)
CREATE TABLE IF NOT EXISTS `mod_timekeeper_timesheets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT UNSIGNED NOT NULL,
  `timesheet_date` DATE NOT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_rejection_note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at` TIMESTAMP NULL DEFAULT NULL,
  `approved_by` INT UNSIGNED DEFAULT NULL,
  `rejected_at` DATETIME DEFAULT NULL,
  `rejected_by` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_date` (`admin_id`, `timesheet_date`),
  KEY `idx_status_date` (`status`, `timesheet_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Timesheet entries
CREATE TABLE IF NOT EXISTS `mod_timekeeper_timesheet_entries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `timesheet_id` INT UNSIGNED NOT NULL,
  `client_id` INT UNSIGNED NOT NULL,
  `department_id` INT UNSIGNED NOT NULL,
  `task_category_id` INT UNSIGNED NOT NULL,
  `ticket_id` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `time_spent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `billable` TINYINT(1) NOT NULL DEFAULT 0,
  `billable_time` DECIMAL(5,2) DEFAULT 0.00,
  `sla` TINYINT(1) NOT NULL DEFAULT 0,
  `sla_time` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `no_billing_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `no_billing_verified_at` DATETIME DEFAULT NULL,
  `no_billing_verified_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_timesheet` (`timesheet_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_department` (`department_id`),
  KEY `idx_subtask` (`task_category_id`),
  CONSTRAINT `fk_entry_timesheet`
    FOREIGN KEY (`timesheet_id`) REFERENCES `mod_timekeeper_timesheets` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
