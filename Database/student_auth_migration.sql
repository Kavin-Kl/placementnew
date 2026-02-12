-- Migration to add authentication fields to students table
-- Run this SQL in your phpMyAdmin or MySQL console

-- Add password_hash field for student authentication
ALTER TABLE `students`
ADD COLUMN `password_hash` VARCHAR(255) DEFAULT NULL AFTER `email`,
ADD COLUMN `is_active` TINYINT(1) DEFAULT 1 AFTER `password_hash`,
ADD COLUMN `last_login` TIMESTAMP NULL DEFAULT NULL AFTER `is_active`,
ADD COLUMN `email_verified` TINYINT(1) DEFAULT 0 AFTER `last_login`;

-- Create notifications table for students
CREATE TABLE IF NOT EXISTS `student_notifications` (
  `notification_id` INT NOT NULL AUTO_INCREMENT,
  `student_id` INT DEFAULT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `message` TEXT DEFAULT NULL,
  `type` ENUM('drive', 'application', 'placement', 'general') DEFAULT 'general',
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `student_id` (`student_id`),
  FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create student password resets table
CREATE TABLE IF NOT EXISTS `student_password_resets` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(100) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for faster student email lookups during login
ALTER TABLE `students`
ADD INDEX `idx_student_email` (`email`);
