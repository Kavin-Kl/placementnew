-- Add SPOC Email column to drive_data table
-- This allows admins to store SPOC email addresses in the Company Progress Tracker

ALTER TABLE `drive_data`
ADD COLUMN `spoc_email` VARCHAR(255) DEFAULT NULL AFTER `spo_name`;
