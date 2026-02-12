-- Create table for dynamic course management
-- This allows admins to add/edit/delete courses without editing PHP files

CREATE TABLE IF NOT EXISTS `courses` (
  `course_id` INT(11) NOT NULL AUTO_INCREMENT,
  `course_name` VARCHAR(255) NOT NULL,
  `program_type` ENUM('UG', 'PG') NOT NULL,
  `school` VARCHAR(255) NOT NULL COMMENT 'e.g., SCHOOL OF HUMANITIES(UG)',
  `program_level` VARCHAR(100) NOT NULL COMMENT 'e.g., Undergraduate Programs',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `display_order` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`course_id`),
  UNIQUE KEY `unique_course` (`course_name`, `program_type`, `school`),
  KEY `program_type` (`program_type`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Stores courses that can be managed by admins';

-- Insert existing courses from course_groups.php
-- SCHOOL OF HUMANITIES (UG)
INSERT INTO `courses` (`course_name`, `program_type`, `school`, `program_level`, `display_order`) VALUES
('BA-Communicative English_Psychology', 'UG', 'SCHOOL OF HUMANITIES(UG)', 'Undergraduate Programs', 1),
('BA-History_Political Science', 'UG', 'SCHOOL OF HUMANITIES(UG)', 'Undergraduate Programs', 2),
('BA-History_Travel Tourism', 'UG', 'SCHOOL OF HUMANITIES(UG)', 'Undergraduate Programs', 3),
('BA-Political Science_Economics', 'UG', 'SCHOOL OF HUMANITIES(UG)', 'Undergraduate Programs', 4),
('BA-Political Science_Sociology', 'UG', 'SCHOOL OF HUMANITIES(UG)', 'Undergraduate Programs', 5),
('BA-Psychology_Economics', 'UG', 'SCHOOL OF HUMANITIES(UG)', 'Undergraduate Programs', 6),
('BA-Psychology_English Literature', 'UG', 'SCHOOL OF HUMANITIES(UG)', 'Undergraduate Programs', 7),
('BA-Psychology_Journalism', 'UG', 'SCHOOL OF HUMANITIES(UG)', 'Undergraduate Programs', 8),
('BA-Psychology_Sociology', 'UG', 'SCHOOL OF HUMANITIES(UG)', 'Undergraduate Programs', 9),
('BA-Travel & Tourism_Journalism', 'UG', 'SCHOOL OF HUMANITIES(UG)', 'Undergraduate Programs', 10),
('BVoc-Hospitality and Tourism', 'UG', 'SCHOOL OF HUMANITIES(UG)', 'Undergraduate Programs', 11),
('BA-Communication Studies', 'UG', 'SCHOOL OF HUMANITIES(UG)', 'Undergraduate Programs', 12),
('BA-Economics', 'UG', 'SCHOOL OF HUMANITIES(UG)', 'Undergraduate Programs', 13),
('BA-Journalism & Mass Communication', 'UG', 'SCHOOL OF HUMANITIES(UG)', 'Undergraduate Programs', 14),
('BA-Psychology', 'UG', 'SCHOOL OF HUMANITIES(UG)', 'Undergraduate Programs', 15);

-- SCHOOL OF MANAGEMENT (UG)
INSERT INTO `courses` (`course_name`, `program_type`, `school`, `program_level`, `display_order`) VALUES
('BBA-Branding & Advertising', 'UG', 'SCHOOL OF MANAGEMENT(UG)', 'Undergraduate Programs', 1),
('BBA-Business Analytics', 'UG', 'SCHOOL OF MANAGEMENT(UG)', 'Undergraduate Programs', 2),
('BBA-Regular', 'UG', 'SCHOOL OF MANAGEMENT(UG)', 'Undergraduate Programs', 3);

-- SCHOOL OF COMMERCE (UG)
INSERT INTO `courses` (`course_name`, `program_type`, `school`, `program_level`, `display_order`) VALUES
('BCom-Business Process Services', 'UG', 'SCHOOL OF COMMERCE(UG)', 'Undergraduate Programs', 1),
('BCom-Corporate Finance', 'UG', 'SCHOOL OF COMMERCE(UG)', 'Undergraduate Programs', 2),
('BCom-General', 'UG', 'SCHOOL OF COMMERCE(UG)', 'Undergraduate Programs', 3),
('BCom-Industry Integrated', 'UG', 'SCHOOL OF COMMERCE(UG)', 'Undergraduate Programs', 4),
('BCom-International Accounting and Finance', 'UG', 'SCHOOL OF COMMERCE(UG)', 'Undergraduate Programs', 5),
('BCom-Professional', 'UG', 'SCHOOL OF COMMERCE(UG)', 'Undergraduate Programs', 6),
('BCom-Strategic Finance', 'UG', 'SCHOOL OF COMMERCE(UG)', 'Undergraduate Programs', 7),
('BCom-Tourism and Travel Management', 'UG', 'SCHOOL OF COMMERCE(UG)', 'Undergraduate Programs', 8);

-- SCHOOL OF NATURAL AND APPLIED SCIENCES (UG)
INSERT INTO `courses` (`course_name`, `program_type`, `school`, `program_level`, `display_order`) VALUES
('BSc-Biochemistry', 'UG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(UG)', 'Undergraduate Programs', 1),
('BSc-Botany_Microbiology', 'UG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(UG)', 'Undergraduate Programs', 2),
('BSc-Botany_Zoology', 'UG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(UG)', 'Undergraduate Programs', 3),
('BSc-Chemistry_Biotechnology', 'UG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(UG)', 'Undergraduate Programs', 4),
('BSc-Chemistry_Microbiology', 'UG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(UG)', 'Undergraduate Programs', 5),
('BSc-COMPOSITE HOME SCIENCE', 'UG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(UG)', 'Undergraduate Programs', 6),
('BSc-Computer Science_Mathematics', 'UG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(UG)', 'Undergraduate Programs', 7),
('BSc-Economics_Statistics', 'UG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(UG)', 'Undergraduate Programs', 8),
('BSc-Environmental Science & Sustainability_Life Sciences', 'UG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(UG)', 'Undergraduate Programs', 9),
('BSc-Mathematics_Physics', 'UG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(UG)', 'Undergraduate Programs', 10),
('BSc-Microbiology_Zoology', 'UG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(UG)', 'Undergraduate Programs', 11),
('BSc-Nutrition & Dietetics_Human Development', 'UG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(UG)', 'Undergraduate Programs', 12),
('BSc-Zoology_Biotechnology', 'UG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(UG)', 'Undergraduate Programs', 13),
('BSc-Biotechnology', 'UG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(UG)', 'Undergraduate Programs', 14),
('BSc-Data Science', 'UG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(UG)', 'Undergraduate Programs', 15),
('BSc-Fashion and Apparel Design', 'UG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(UG)', 'Undergraduate Programs', 16),
('BSc-Food Science & Nutrition', 'UG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(UG)', 'Undergraduate Programs', 17),
('BSc-Interior Design & Management', 'UG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(UG)', 'Undergraduate Programs', 18),
('Bachelor of Computer Applications', 'UG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(UG)', 'Undergraduate Programs', 19);

-- SCHOOL OF HUMANITIES (PG)
INSERT INTO `courses` (`course_name`, `program_type`, `school`, `program_level`, `display_order`) VALUES
('MA-Economics', 'PG', 'SCHOOL OF HUMANITIES(PG)', 'Postgraduate Programs', 1),
('MA-English', 'PG', 'SCHOOL OF HUMANITIES(PG)', 'Postgraduate Programs', 2),
('MA-Public Policy', 'PG', 'SCHOOL OF HUMANITIES(PG)', 'Postgraduate Programs', 3);

-- SCHOOL OF MANAGEMENT (PG)
INSERT INTO `courses` (`course_name`, `program_type`, `school`, `program_level`, `display_order`) VALUES
('PG Diploma in Business Applications', 'PG', 'SCHOOL OF MANAGEMENT(PG)', 'Postgraduate Programs', 1),
('PG Diploma in Business Intelligence and Analytics', 'PG', 'SCHOOL OF MANAGEMENT(PG)', 'Postgraduate Programs', 2),
('Master of Business Administration', 'PG', 'SCHOOL OF MANAGEMENT(PG)', 'Postgraduate Programs', 3),
('PG Diploma in Management Analytics', 'PG', 'SCHOOL OF MANAGEMENT(PG)', 'Postgraduate Programs', 4);

-- SCHOOL OF COMMERCE (PG)
INSERT INTO `courses` (`course_name`, `program_type`, `school`, `program_level`, `display_order`) VALUES
('MCom-Financial Analysis', 'PG', 'SCHOOL OF COMMERCE(PG)', 'Postgraduate Programs', 1),
('MCom-General', 'PG', 'SCHOOL OF COMMERCE(PG)', 'Postgraduate Programs', 2),
('MCom-International Business', 'PG', 'SCHOOL OF COMMERCE(PG)', 'Postgraduate Programs', 3),
('One Year Masters Degree In Commerce', 'PG', 'SCHOOL OF COMMERCE(PG)', 'Postgraduate Programs', 4);

-- SCHOOL OF NATURAL AND APPLIED SCIENCES (PG)
INSERT INTO `courses` (`course_name`, `program_type`, `school`, `program_level`, `display_order`) VALUES
('MSc-Biochemistry', 'PG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(PG)', 'Postgraduate Programs', 1),
('MSc-Biotechnology', 'PG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(PG)', 'Postgraduate Programs', 2),
('MSc-Botany', 'PG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(PG)', 'Postgraduate Programs', 3),
('MSc-Chemistry', 'PG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(PG)', 'Postgraduate Programs', 4),
('MSc-Computer Science (Data Science Specialization)', 'PG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(PG)', 'Postgraduate Programs', 5),
('MSc-Electronics', 'PG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(PG)', 'Postgraduate Programs', 6),
('MSc-Food Science & Nutrition', 'PG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(PG)', 'Postgraduate Programs', 7),
('MSc-Life Science', 'PG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(PG)', 'Postgraduate Programs', 8),
('MSc-Mathematics', 'PG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(PG)', 'Postgraduate Programs', 9),
('MSc-Psychology', 'PG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(PG)', 'Postgraduate Programs', 10),
('MSc-Human Development', 'PG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(PG)', 'Postgraduate Programs', 11),
('Master of Computer Applications', 'PG', 'SCHOOL OF NATURAL AND APPLIED SCIENCES(PG)', 'Postgraduate Programs', 12);
