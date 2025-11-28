-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 12, 2025 at 05:30 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `smart_ndvi_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_officer_actions`
--

CREATE TABLE `admin_officer_actions` (
  `admin_action_id` int(11) NOT NULL,
  `action_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `admin_name` varchar(100) NOT NULL,
  `officer_name` varchar(100) NOT NULL,
  `report_id` int(11) NOT NULL,
  `feedback` varchar(255) DEFAULT NULL,
  `verification_status` enum('Verified','Pending','Rejected') DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `reviewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `admin_officer_actions`
--

INSERT INTO `admin_officer_actions` (`admin_action_id`, `action_id`, `admin_id`, `admin_name`, `officer_name`, `report_id`, `feedback`, `verification_status`, `remarks`, `reviewed_at`) VALUES
(1, 1, 1, 'Ravi Kumar', 'Priya Sharma', 1, 'Good validation, data consistent.', 'Verified', 'No anomalies found in Nilgiri NDVI data.', '2025-11-12 14:39:50'),
(2, 2, 1, 'Ravi Kumar', 'Arun Singh', 3, 'Needs follow-up site visit.', 'Pending', 'NDVI critical; please reassess next week.', '2025-11-12 14:39:50');

-- --------------------------------------------------------

--
-- Table structure for table `forest_reports`
--

CREATE TABLE `forest_reports` (
  `report_id` int(11) NOT NULL,
  `forest_name` varchar(150) NOT NULL,
  `old_image` varchar(255) NOT NULL,
  `new_image` varchar(255) NOT NULL,
  `ndvi_percentage` decimal(5,2) NOT NULL,
  `change_detection_percentage` decimal(5,2) NOT NULL,
  `tree_loss_percentage` decimal(5,2) NOT NULL,
  `report_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Stable','Moderately Stable','Unstable','Critical') NOT NULL,
  `analyzed_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `forest_reports`
--

INSERT INTO `forest_reports` (`report_id`, `forest_name`, `old_image`, `new_image`, `ndvi_percentage`, `change_detection_percentage`, `tree_loss_percentage`, `report_date`, `status`, `analyzed_by`) VALUES
(1, 'Nilgiri Forest', 'nilgiri_old.jpg', 'nilgiri_new.jpg', 72.50, 12.30, 5.60, '2025-11-12 14:39:50', 'Stable', 1),
(2, 'Western Ghats', 'ghats_old.jpg', 'ghats_new.jpg', 58.20, 23.40, 10.10, '2025-11-12 14:39:50', 'Moderately Stable', 1),
(3, 'Sundarbans', 'sundar_old.jpg', 'sundar_new.jpg', 42.80, 38.90, 25.60, '2025-11-12 14:39:50', 'Critical', 1);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `sender_name` varchar(100) NOT NULL,
  `receiver_name` varchar(100) NOT NULL,
  `report_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `sender_name`, `receiver_name`, `report_id`, `message`, `sent_at`) VALUES
(1, 1, 2, 'Ravi Kumar', 'Priya Sharma', 1, 'Please verify the Nilgiri report and confirm NDVI values.', '2025-11-12 14:39:50'),
(2, 2, 1, 'Priya Sharma', 'Ravi Kumar', 1, 'Field check done. NDVI seems stable in Nilgiri region.', '2025-11-12 14:39:50'),
(3, 1, 3, 'Ravi Kumar', 'Arun Singh', 3, 'Urgent attention required in Sundarbans — critical condition!', '2025-11-12 14:39:50');

-- --------------------------------------------------------

--
-- Table structure for table `officer_actions`
--

CREATE TABLE `officer_actions` (
  `action_id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `officer_id` int(11) NOT NULL,
  `officer_name` varchar(100) NOT NULL,
  `location` varchar(255) NOT NULL,
  `action_type` varchar(255) NOT NULL,
  `completion_date` date NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `officer_actions`
--

INSERT INTO `officer_actions` (`action_id`, `report_id`, `officer_id`, `officer_name`, `location`, `action_type`, `completion_date`, `submitted_at`) VALUES
(1, 1, 2, 'Priya Sharma', 'Nilgiri Range, Tamil Nadu', 'NDVI Revalidation Survey', '2025-11-10', '2025-11-12 14:39:50'),
(2, 3, 3, 'Arun Singh', 'Sundarbans, West Bengal', 'Tree Loss Inspection', '2025-11-11', '2025-11-12 14:39:50');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','officer') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `username`, `password`, `email`, `role`, `created_at`) VALUES
(1, 'Ravi Kumar', 'admin_ravi', 'admin123', 'admin_ravi@example.com', 'admin', '2025-11-12 14:39:50'),
(2, 'Priya Sharma', 'officer_priya', 'officer123', 'officer_priya@example.com', 'officer', '2025-11-12 14:39:50'),
(3, 'Arun Singh', 'officer_arun', 'officer456', 'officer_arun@example.com', 'officer', '2025-11-12 14:39:50');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_officer_actions`
--
ALTER TABLE `admin_officer_actions`
  ADD PRIMARY KEY (`admin_action_id`),
  ADD KEY `action_id` (`action_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `forest_reports`
--
ALTER TABLE `forest_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `analyzed_by` (`analyzed_by`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `officer_actions`
--
ALTER TABLE `officer_actions`
  ADD PRIMARY KEY (`action_id`),
  ADD KEY `report_id` (`report_id`),
  ADD KEY `officer_id` (`officer_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_officer_actions`
--
ALTER TABLE `admin_officer_actions`
  MODIFY `admin_action_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `forest_reports`
--
ALTER TABLE `forest_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `officer_actions`
--
ALTER TABLE `officer_actions`
  MODIFY `action_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_officer_actions`
--
ALTER TABLE `admin_officer_actions`
  ADD CONSTRAINT `admin_officer_actions_ibfk_1` FOREIGN KEY (`action_id`) REFERENCES `officer_actions` (`action_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_officer_actions_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_officer_actions_ibfk_3` FOREIGN KEY (`report_id`) REFERENCES `forest_reports` (`report_id`) ON DELETE CASCADE;

--
-- Constraints for table `forest_reports`
--
ALTER TABLE `forest_reports`
  ADD CONSTRAINT `forest_reports_ibfk_1` FOREIGN KEY (`analyzed_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`report_id`) REFERENCES `forest_reports` (`report_id`) ON DELETE SET NULL;

--
-- Constraints for table `officer_actions`
--
ALTER TABLE `officer_actions`
  ADD CONSTRAINT `officer_actions_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `forest_reports` (`report_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `officer_actions_ibfk_2` FOREIGN KEY (`officer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
