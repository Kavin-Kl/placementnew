-- Create admin notifications table for deadline reminders and system notifications
CREATE TABLE IF NOT EXISTS `admin_notifications` (
  `notification_id` INT(11) NOT NULL AUTO_INCREMENT,
  `admin_id` INT(11) DEFAULT NULL,
  `drive_id` INT(11) DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('deadline', 'application', 'system', 'reminder') NOT NULL DEFAULT 'system',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `action_url` VARCHAR(500) DEFAULT NULL,
  PRIMARY KEY (`notification_id`),
  KEY `admin_id` (`admin_id`),
  KEY `drive_id` (`drive_id`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- Add index for efficient querying of unread notifications
CREATE INDEX idx_admin_unread ON admin_notifications(admin_id, is_read, created_at DESC);
-- Create table to track deadline notifications to avoid duplicates
CREATE TABLE IF NOT EXISTS `deadline_notifications_sent` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `drive_id` INT(11) NOT NULL,
  `notification_type` VARCHAR(50) NOT NULL,
  `sent_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_drive_notification` (`drive_id`, `notification_type`),
  KEY `drive_id` (`drive_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- Add form_fields column to drives table if it doesn't exist
ALTER TABLE `drives`
ADD COLUMN IF NOT EXISTS `form_fields` JSON DEFAULT NULL COMMENT 'Custom form fields configuration for this drive';