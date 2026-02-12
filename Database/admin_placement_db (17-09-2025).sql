-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 16, 2025 at 01:34 PM
-- Server version: 8.0.40
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `admin_placement_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `admin_id` int NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`admin_id`, `username`, `password_hash`, `email`, `created_at`) VALUES
(2, 'Asgar Ahmed', '$2y$10$9QsS7u2trWuWrR2/DVTMmudswub1ggk6GcxRcYTzVb83mwiXQN2SC', 'asgarahmedf@gmail.com', '2025-07-13 17:31:41'),
(6, 'Annie Shruthi', '$2y$10$1eaisMl/g16kHTITUvwl3.VIn4e3TEZV97AcqhuRuf9bdRShy23mS', 'annieshruthi1@gmail.com', '2025-07-13 17:31:41'),
(7, 'Madiha S', '$2y$10$6tCeQ3GmHIu./9tM43xi.ufDEuXyE3u9zS.4gAfCpfl0a3H8Nstfa', 'madihasyeda794@gmail.com', '2025-07-13 17:31:41'),
(8, 'Jagriti Sarda', '$2y$10$/JS7Kv6w7A2gR23Mj4uolOuuh0S4GSeVCXTQP/dO1DNtauUnaA5ny', 'jagritisarda02@gmail.com', '2025-07-13 17:31:41'),
(9, 'Kaunain Kauser', '$2y$10$.LnxGrCuNOWhTbfnEmpg7.Hh6JjUO1bkJgb5GFlBaVL1rbzRV6vPS', 'kaunainkauser.work@gmail.com', '2025-07-13 17:31:41'),
(14, 'adminuser', '$2y$10$3gaRmT6VMnyB0o5oQtKe3.iNaBT1zllfy00VhXtG2e75dRtDGQN1q', 'preethamkumari391@gmail.com', '2025-09-06 12:01:41'),
(15, 'Zahraa Ilyas', '$2y$10$5jvbL1jDE5SGOdLvtxG6JuvK9sHqDSbUANuCCqEV0QwdnfFvNgqQW', 'zahraailyas23@gmail.com', '2025-09-06 12:01:41');

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `application_id` int NOT NULL,
  `student_id` int DEFAULT NULL,
  `drive_id` int DEFAULT NULL,
  `role_id` int DEFAULT NULL,
  `resume_file` varchar(255) DEFAULT NULL,
  `status` enum('not_placed','applied','placed','blocked','rejected','pending') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'applied',
  `comments` text,
  `applied_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `student_data` longtext,
  `percentage` float DEFAULT NULL,
  `course` varchar(255) DEFAULT NULL,
  `priority` int DEFAULT NULL,
  `upid` varchar(50) DEFAULT NULL,
  `reg_no` varchar(50) DEFAULT NULL,
  `student_name` varchar(100) DEFAULT NULL,
  `placement_batch` varchar(20) DEFAULT NULL,
  `status_changed` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `applications`
--
DELIMITER $$
CREATE TRIGGER `set_status_timestamp_on_insert` BEFORE INSERT ON `applications` FOR EACH ROW BEGIN
    SET NEW.status_changed = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_status_timestamp_on_update` BEFORE UPDATE ON `applications` FOR EACH ROW BEGIN
    IF NEW.status <> OLD.status THEN
        SET NEW.status_changed = NOW();
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `drives`
--

CREATE TABLE `drives` (
  `drive_id` int NOT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `drive_no` varchar(50) DEFAULT NULL,
  `open_date` datetime NOT NULL,
  `close_date` datetime NOT NULL,
  `jd_file` longtext,
  `form_link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `extra_details` longtext,
  `jd_link` text,
  `created_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drive_data`
--

CREATE TABLE `drive_data` (
  `id` int NOT NULL,
  `company_status` varchar(100) DEFAULT NULL,
  `offer_type` varchar(100) DEFAULT NULL,
  `sector` varchar(100) DEFAULT NULL,
  `eligible_courses` text,
  `company_name` varchar(100) DEFAULT NULL,
  `drive_no` varchar(100) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `no_of_applied` int DEFAULT '0',
  `no_of_hired` int DEFAULT '0',
  `drive_id` int DEFAULT NULL,
  `role_id` int DEFAULT NULL,
  `spo_name` varchar(255) DEFAULT NULL,
  `contact_no` varchar(50) DEFAULT NULL,
  `follow_status` varchar(255) DEFAULT NULL,
  `final_status` varchar(255) DEFAULT NULL,
  `hired_count` int DEFAULT NULL,
  `follow_up_person` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drive_roles`
--

CREATE TABLE `drive_roles` (
  `role_id` int NOT NULL,
  `drive_id` int DEFAULT NULL,
  `designation_name` varchar(100) DEFAULT NULL,
  `eligible_courses` text,
  `min_percentage` decimal(5,2) DEFAULT NULL,
  `ctc` varchar(50) DEFAULT NULL,
  `stipend` text,
  `form_fields` text,
  `offer_type` varchar(50) DEFAULT NULL,
  `sector` varchar(100) DEFAULT NULL,
  `is_finished` tinyint(1) DEFAULT '0',
  `close_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `form_links`
--

CREATE TABLE `form_links` (
  `id` int NOT NULL,
  `shortcode` varchar(100) DEFAULT NULL,
  `fields` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `custom_field_meta` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `on_off_campus_students`
--

CREATE TABLE `on_off_campus_students` (
  `external_id` int NOT NULL,
  `company_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `full_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reg_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `upid` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `course_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `offer_letter_received` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'no',
  `intent_letter_received` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'no',
  `onboarding_date` date DEFAULT NULL,
  `campus_type` enum('on','off') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'off',
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `photo_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `offer_letter_file` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `passing_year` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `intent_letter_file` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `register_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `created_at`) VALUES
(20, 'preethamkumari391@gmail.com', '2ed767b4ac22269b34a7a80043df04639ff2965c11d2d8fd92b2bc39a1c48791', '2025-09-11 04:10:28');

-- --------------------------------------------------------

--
-- Table structure for table `placed_students`
--

CREATE TABLE `placed_students` (
  `place_id` int NOT NULL,
  `student_id` int DEFAULT NULL,
  `drive_id` int DEFAULT NULL,
  `role_id` int DEFAULT NULL,
  `comment` text,
  `offer_letter_accepted` enum('yes','no','unknown') DEFAULT 'unknown',
  `offer_letter_received` enum('yes','no','unknown') DEFAULT 'unknown',
  `joining_status` enum('joined','not_joined','unknown') DEFAULT 'unknown',
  `joining_reason` text,
  `upid` varchar(50) DEFAULT NULL,
  `program_type` varchar(50) DEFAULT NULL,
  `program` varchar(50) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `class` varchar(50) DEFAULT NULL,
  `reg_no` varchar(50) DEFAULT NULL,
  `student_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone_no` varchar(20) DEFAULT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `ctc` varchar(50) DEFAULT NULL,
  `filled_on_off_form` varchar(20) DEFAULT 'not filled',
  `placement_batch` enum('original','reapplied') DEFAULT 'original',
  `placed_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `stipend` varchar(50) DEFAULT NULL,
  `drive_no` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int NOT NULL,
  `upid` varchar(50) DEFAULT NULL,
  `program_type` varchar(50) DEFAULT NULL,
  `program` varchar(50) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `class` varchar(50) DEFAULT NULL,
  `reg_no` varchar(50) DEFAULT NULL,
  `student_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone_no` varchar(20) DEFAULT NULL,
  `applied_date` date DEFAULT NULL,
  `batch` varchar(20) DEFAULT NULL,
  `placed_status` enum('placed','not_placed','blocked','rejected','pending') DEFAULT 'not_placed',
  `allow_reapply` enum('yes','no') DEFAULT 'no',
  `comment` text,
  `company_name` varchar(100) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `ctc` varchar(50) DEFAULT NULL,
  `percentage` float DEFAULT NULL,
  `comments` text,
  `year_of_passing` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username_2` (`username`),
  ADD UNIQUE KEY `email_2` (`email`),
  ADD UNIQUE KEY `username_3` (`username`),
  ADD UNIQUE KEY `email_3` (`email`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`application_id`),
  ADD UNIQUE KEY `unique_application` (`student_id`,`drive_id`,`role_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `drive_id` (`drive_id`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `idx_applications_student_status` (`student_id`,`status`,`placement_batch`,`status_changed`);

--
-- Indexes for table `drives`
--
ALTER TABLE `drives`
  ADD PRIMARY KEY (`drive_id`),
  ADD KEY `idx_drives_id` (`drive_id`);

--
-- Indexes for table `drive_data`
--
ALTER TABLE `drive_data`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `drive_roles`
--
ALTER TABLE `drive_roles`
  ADD PRIMARY KEY (`role_id`),
  ADD KEY `drive_id` (`drive_id`),
  ADD KEY `idx_roles_id` (`role_id`);

--
-- Indexes for table `form_links`
--
ALTER TABLE `form_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shortcode` (`shortcode`);

--
-- Indexes for table `on_off_campus_students`
--
ALTER TABLE `on_off_campus_students`
  ADD PRIMARY KEY (`external_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `placed_students`
--
ALTER TABLE `placed_students`
  ADD PRIMARY KEY (`place_id`),
  ADD UNIQUE KEY `unique_placed` (`student_id`,`drive_id`,`role_id`,`placement_batch`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `drive_id` (`drive_id`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `upid` (`upid`),
  ADD UNIQUE KEY `reg_no` (`reg_no`),
  ADD KEY `idx_students_batch` (`batch`),
  ADD KEY `idx_students_regno` (`reg_no`),
  ADD KEY `idx_students_year` (`year_of_passing`),
  ADD KEY `idx_students_upid` (`upid`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `admin_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `application_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=506;

--
-- AUTO_INCREMENT for table `drives`
--
ALTER TABLE `drives`
  MODIFY `drive_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=407;

--
-- AUTO_INCREMENT for table `drive_data`
--
ALTER TABLE `drive_data`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=666;

--
-- AUTO_INCREMENT for table `drive_roles`
--
ALTER TABLE `drive_roles`
  MODIFY `role_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=645;

--
-- AUTO_INCREMENT for table `form_links`
--
ALTER TABLE `form_links`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=534;

--
-- AUTO_INCREMENT for table `on_off_campus_students`
--
ALTER TABLE `on_off_campus_students`
  MODIFY `external_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=527;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `placed_students`
--
ALTER TABLE `placed_students`
  MODIFY `place_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=270;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=972;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`drive_id`) REFERENCES `drives` (`drive_id`),
  ADD CONSTRAINT `applications_ibfk_3` FOREIGN KEY (`role_id`) REFERENCES `drive_roles` (`role_id`);

--
-- Constraints for table `drive_roles`
--
ALTER TABLE `drive_roles`
  ADD CONSTRAINT `drive_roles_ibfk_1` FOREIGN KEY (`drive_id`) REFERENCES `drives` (`drive_id`) ON DELETE CASCADE;

--
-- Constraints for table `placed_students`
--
ALTER TABLE `placed_students`
  ADD CONSTRAINT `placed_students_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `placed_students_ibfk_2` FOREIGN KEY (`drive_id`) REFERENCES `drives` (`drive_id`),
  ADD CONSTRAINT `placed_students_ibfk_3` FOREIGN KEY (`role_id`) REFERENCES `drive_roles` (`role_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
