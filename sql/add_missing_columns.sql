-- Add missing columns to students table (if they don't exist)

-- Add Offcampus_selection column
ALTER TABLE `students`
ADD COLUMN IF NOT EXISTS `Offcampus_selection` ENUM('unknown', 'not_placed', 'placed') DEFAULT 'unknown'
COMMENT 'Off-campus placement status';

-- Add editable_comment column
ALTER TABLE `students`
ADD COLUMN IF NOT EXISTS `editable_comment` TEXT DEFAULT NULL
COMMENT 'Off-campus placement comments/notes';
