-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 03, 2026 at 03:02 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `leave_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `accruals`
--

CREATE TABLE `accruals` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `amount` decimal(6,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `accruals`
--

INSERT INTO `accruals` (`id`, `employee_id`, `amount`, `created_at`) VALUES
(1, 1, 1.25, '2026-02-24 14:13:37'),
(2, 2, 1.25, '2026-02-24 14:13:37'),
(3, 3, 1.25, '2026-02-24 14:13:37'),
(4, 1, 1.25, '2026-02-24 14:23:44'),
(5, 2, 1.25, '2026-02-24 14:23:44'),
(6, 3, 1.25, '2026-02-24 14:23:44'),
(7, 3, 1.75, '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `accrual_history`
--

CREATE TABLE `accrual_history` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `amount` decimal(6,2) NOT NULL,
  `date_accrued` date NOT NULL,
  `month_reference` varchar(7) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `accrual_history`
--

INSERT INTO `accrual_history` (`id`, `employee_id`, `amount`, `date_accrued`, `month_reference`, `created_at`) VALUES
(1, 1, 1.25, '2026-02-25', '2026-02', '2026-02-25 09:20:50'),
(2, 2, 1.25, '2026-02-25', '2026-02', '2026-02-25 09:20:50'),
(3, 3, 1.25, '2026-02-25', '2026-02', '2026-02-25 09:20:50'),
(4, 1, 1.25, '2026-02-25', '2026-02', '2026-02-25 09:21:18'),
(5, 2, 1.25, '2026-02-25', '2026-02', '2026-02-25 09:21:18'),
(6, 3, 1.25, '2026-02-25', '2026-02', '2026-02-25 09:21:18'),
(7, 3, 1.25, '2026-02-25', '2026-02', '2026-02-25 16:12:04'),
(8, 1, 1.25, '2026-03-31', '2026-03', '2026-03-02 09:10:29'),
(9, 2, 1.25, '2026-03-31', '2026-03', '2026-03-02 09:10:29'),
(10, 3, 1.25, '2026-03-31', '2026-03', '2026-03-02 09:10:29'),
(11, 4, 1.25, '2026-03-31', '2026-03', '2026-03-02 09:10:29');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `budget_history`
--

CREATE TABLE `budget_history` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `trans_date` date DEFAULT NULL,
  `leave_type` varchar(50) NOT NULL,
  `old_balance` decimal(6,2) NOT NULL,
  `new_balance` decimal(6,2) NOT NULL,
  `action` varchar(50) NOT NULL,
  `leave_request_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `budget_history`
--

INSERT INTO `budget_history` (`id`, `employee_id`, `trans_date`, `leave_type`, `old_balance`, `new_balance`, `action`, `leave_request_id`, `notes`, `created_at`) VALUES
(1, 1, NULL, 'Annual', 23.00, 24.00, 'adjustment', NULL, 'Admin manual adjustment', '2026-02-24 14:27:17'),
(2, 3, NULL, 'Annual', 3.75, 5.50, 'accrual', NULL, 'Manual accrual recorded for 2026-01', '2026-02-24 14:40:26'),
(3, 2, NULL, 'Annual', 25.00, 24.00, 'deduction', 3, 'Historical leave entry added by admin', '2026-02-24 16:02:14'),
(4, 3, NULL, 'Force', 5.00, 4.00, 'deduction', 4, 'Leave approved', '2026-02-24 16:29:47'),
(5, 3, NULL, 'Sick', 0.00, 5.00, 'adjustment', NULL, 'Admin manual adjustment', '2026-02-24 16:34:28'),
(6, 3, NULL, 'Sick', 5.00, 4.00, 'deduction', 5, 'Leave approved', '2026-02-24 16:36:05'),
(7, 2, NULL, 'Force', 5.00, 4.00, 'adjustment', NULL, 'Admin manual adjustment', '2026-02-25 09:19:20'),
(8, 3, NULL, 'Annual', 8.00, 9.25, 'accrual', NULL, 'Manual accrual recorded for 2026-02', '2026-02-25 16:12:04'),
(9, 3, NULL, 'Annual', 9.25, 8.65, 'undertime_paid', NULL, 'Undertime 300 mins', '2026-02-26 10:16:41'),
(10, 4, NULL, 'Annual', 0.00, 9.51, 'adjustment', NULL, 'Admin manual adjustment', '2026-02-26 13:20:13'),
(11, 4, NULL, 'Sick', 0.00, 6.71, 'adjustment', NULL, 'Admin manual adjustment', '2026-02-26 13:20:13'),
(12, 4, NULL, 'Force', 5.00, 3.00, 'adjustment', NULL, 'Admin manual adjustment', '2026-02-26 13:20:13'),
(13, 4, NULL, 'Sick', 6.71, 5.71, 'deduction', 6, 'Historical leave entry added by admin', '2026-02-26 13:22:04'),
(14, 4, NULL, 'Sick', 5.71, 4.71, 'deduction', 10, 'Leave approved', '2026-02-26 14:16:42'),
(15, 4, NULL, 'Vacational', 9.51, 8.71, 'undertime_paid', NULL, 'Undertime 400 mins', '2026-02-26 14:22:11'),
(16, 4, NULL, 'Sick', 5.96, 4.96, 'deduction', 9, 'Leave approved', '2026-03-02 09:11:10'),
(17, 4, NULL, 'Sick', 4.96, 3.96, 'deduction', 11, 'Historical leave entry added by admin', '2026-03-02 14:52:30'),
(18, 4, NULL, 'Vacational', 9.96, 8.96, 'deduction', 12, 'Historical leave entry added by admin', '2026-03-02 14:54:47'),
(19, 4, NULL, 'Vacational', 8.96, 8.21, 'undertime_paid', NULL, 'Historical undertime: 6h 15m', '2026-03-02 14:54:47'),
(20, 4, NULL, 'Sick', 3.96, 2.96, 'deduction', 13, 'Historical leave entry added by admin', '2026-03-02 14:56:23'),
(21, 4, NULL, 'Vacational', 8.21, 7.21, 'deduction', 14, 'Leave approved', '2026-03-02 14:59:46'),
(22, 3, NULL, 'Vacational', 9.90, 7.90, 'deduction', 17, 'Leave approved', '2026-03-02 15:21:01'),
(23, 3, NULL, 'Vacational', 8.91, 7.91, 'deduction', 18, 'Historical leave entry (no current balance affected)', '2026-03-03 08:24:12'),
(24, 3, NULL, 'Vacational', 7.09, 6.09, 'deduction', 19, 'Historical leave entry (no current balance affected)', '2026-03-03 08:43:02'),
(25, 3, NULL, 'Vacational', 9.05, 10.30, 'earning', NULL, 'Historical earning (no current balance affected)', '2026-03-03 08:44:48'),
(26, 3, NULL, 'Vacational', 10.30, 9.30, 'deduction', 20, 'Historical leave entry (no current balance affected)', '2026-03-03 08:44:48'),
(27, 3, '2024-01-10', 'Vacational', 6.10, 5.10, 'deduction', 21, 'Historical leave entry (no current balance affected)', '2026-03-03 09:32:25'),
(28, 3, '2025-02-28', 'Vacational', 8.10, 9.35, 'earning', NULL, 'Historical earning (no current balance affected)', '2026-03-03 09:35:07'),
(29, 3, '2025-02-28', 'Vacational', 9.35, 8.35, 'deduction', 22, 'Historical leave entry (no current balance affected)', '2026-03-03 09:35:07'),
(30, 3, '2025-03-31', 'Vacational', 7.09, 8.34, 'earning', NULL, 'Historical earning (no current balance affected)', '2026-03-03 09:57:47'),
(31, 3, '2025-03-31', 'Vacational', 8.34, 7.34, 'deduction', 23, 'Historical leave entry (no current balance affected)', '2026-03-03 09:57:48');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `leave_balance` decimal(5,2) DEFAULT 20.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `annual_balance` decimal(6,2) NOT NULL DEFAULT 0.00,
  `sick_balance` decimal(6,2) NOT NULL DEFAULT 0.00,
  `force_balance` int(11) NOT NULL DEFAULT 0,
  `profile_pic` varchar(255) DEFAULT NULL,
  `position` varchar(128) DEFAULT NULL,
  `status` varchar(64) DEFAULT NULL,
  `civil_status` varchar(64) DEFAULT NULL,
  `entrance_to_duty` date DEFAULT NULL,
  `unit` varchar(128) DEFAULT NULL,
  `gsis_policy_no` varchar(128) DEFAULT NULL,
  `national_reference_card_no` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `user_id`, `first_name`, `last_name`, `department`, `manager_id`, `leave_balance`, `created_at`, `annual_balance`, `sick_balance`, `force_balance`, `profile_pic`, `position`, `status`, `civil_status`, `entrance_to_duty`, `unit`, `gsis_policy_no`, `national_reference_card_no`) VALUES
(1, 2, '', '', '', NULL, 20.00, '2026-02-23 07:32:18', 27.75, 1.25, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 3, 'Jp', 'Daquis', 'Payroll', NULL, 20.00, '2026-02-23 07:41:34', 27.75, 1.25, 5, '../uploads/699e5de99193d_640153593_911638038127568_5031301312851920220_n.jpg', '', '', '', '0000-00-00', '', '', ''),
(3, 4, 'Hans', 'Ferrer', 'ICT', NULL, 20.00, '2026-02-24 02:18:53', 7.90, 5.25, 5, NULL, '', '', '', '0000-00-00', '', '', ''),
(4, 5, 'Jermaine', 'Jermaine', 'Personnel', NULL, 20.00, '2026-02-26 05:16:36', 7.21, 2.96, 5, NULL, '', '', 'Married to the Game', '2025-03-21', '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` int(11) NOT NULL,
  `holiday_date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'Other'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `holidays`
--

INSERT INTO `holidays` (`id`, `holiday_date`, `description`, `type`) VALUES
(1, '2026-03-20', 'Eid al-Fitr', 'Non-working Holiday'),
(2, '2026-12-25', 'Christmas', 'Non-working Holiday'),
(3, '2026-11-01', 'All Saints Day', 'Non-working Holiday'),
(4, '2026-04-02', 'Maundy Thursday', 'Non-working Holiday'),
(5, '2026-04-03', 'Good Friday', 'Non-working Holiday'),
(6, '2026-05-01', 'Labor Day', 'Non-working Holiday'),
(7, '2026-06-12', 'Independece Day', 'Non-working Holiday'),
(8, '2026-08-31', 'National Heroes Day', 'Non-working Holiday'),
(9, '2026-11-30', 'Andres Bonifacio Day', 'Non-working Holiday'),
(10, '2026-12-30', 'Rizal Day', 'Non-working Holiday');

-- --------------------------------------------------------

--
-- Table structure for table `leave_balance_logs`
--

CREATE TABLE `leave_balance_logs` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `change_amount` decimal(6,2) NOT NULL,
  `reason` varchar(50) NOT NULL,
  `leave_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `leave_balance_logs`
--

INSERT INTO `leave_balance_logs` (`id`, `employee_id`, `change_amount`, `reason`, `leave_id`, `created_at`) VALUES
(1, 3, -0.60, 'undertime_paid', NULL, '2026-02-26 10:16:41'),
(2, 4, -1.00, 'deduction', 6, '2026-02-26 13:22:04'),
(3, 4, -1.00, 'deduction', 10, '2026-02-26 14:16:42'),
(4, 4, -0.80, 'undertime_paid', NULL, '2026-02-26 14:22:11'),
(5, 4, -1.00, 'deduction', 9, '2026-03-02 09:11:10'),
(6, 4, -1.00, 'deduction', 11, '2026-03-02 14:52:30'),
(7, 4, -1.00, 'deduction', 12, '2026-03-02 14:54:47'),
(8, 4, -0.75, 'undertime_paid', NULL, '2026-03-02 14:54:47'),
(9, 4, -1.00, 'deduction', 13, '2026-03-02 14:56:23'),
(10, 4, -1.00, 'deduction', 14, '2026-03-02 14:59:46'),
(11, 3, -2.00, 'deduction', 17, '2026-03-02 15:21:01'),
(12, 3, -1.00, 'historical_deduction', 18, '2026-03-03 08:24:12'),
(13, 3, -1.00, 'historical_deduction', 19, '2026-03-03 08:43:02'),
(14, 3, -1.00, 'historical_deduction', 20, '2026-03-03 08:44:48'),
(15, 3, -1.00, 'historical_deduction', 21, '2026-03-03 09:32:25'),
(16, 3, -1.00, 'historical_deduction', 22, '2026-03-03 09:35:07'),
(17, 3, -1.00, 'historical_deduction', 23, '2026-03-03 09:57:48');

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `leave_type` varchar(50) DEFAULT NULL,
  `leave_type_id` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `total_days` decimal(4,1) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `manager_comments` text DEFAULT NULL,
  `snapshot_annual_balance` decimal(6,2) DEFAULT NULL,
  `snapshot_sick_balance` decimal(6,2) DEFAULT NULL,
  `snapshot_force_balance` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`id`, `employee_id`, `leave_type`, `leave_type_id`, `start_date`, `end_date`, `total_days`, `reason`, `status`, `approved_by`, `created_at`, `manager_comments`, `snapshot_annual_balance`, `snapshot_sick_balance`, `snapshot_force_balance`) VALUES
(1, 3, 'Force', NULL, '2026-02-25', '2026-02-26', 2.0, 'I wanna cry for a bit', 'approved', 1, '2026-02-24 02:19:41', '', NULL, NULL, NULL),
(2, 1, 'Annual', NULL, '2026-02-18', '2026-02-18', 2.0, '', 'approved', 1, '2026-02-24 06:10:23', NULL, NULL, NULL, NULL),
(3, 2, 'Annual', NULL, '2026-01-07', '2026-01-07', 1.0, '', 'approved', 1, '2026-02-24 08:02:14', NULL, 23.75, 7.89, 5),
(4, 3, 'Force', NULL, '2026-12-12', '2026-12-12', 1.0, 'ktamad lng', 'approved', 1, '2026-02-24 08:29:15', '', 5.50, 0.00, 4),
(5, 3, 'Sick', 2, '2026-02-27', '2026-02-27', 1.0, 'kalagnat lng doi .,.', 'approved', 1, '2026-02-24 08:35:39', '', 5.50, 4.00, 4),
(6, 4, 'Sick', 2, '2026-11-07', '2026-11-07', 1.0, '', 'approved', 1, '2026-02-26 05:22:04', NULL, 8.27, 5.46, 3),
(7, 4, 'Sick', 2, '2026-02-28', '2026-02-28', 0.0, 'sICK', 'approved', NULL, '2026-02-26 05:24:07', NULL, 9.51, 5.71, 3),
(8, 4, 'Sick', 2, '2026-03-01', '2026-03-02', 1.0, 'sickkk', 'approved', NULL, '2026-02-26 05:25:35', NULL, 9.51, 5.71, 3),
(9, 4, 'Sick', 2, '2026-01-03', '2026-01-03', 1.0, 'lagnat', 'approved', 1, '2026-02-26 05:57:26', '', 9.96, 4.96, 5),
(10, 4, 'Sick', 2, '2026-03-04', '2026-03-04', 1.0, 'lovenat', 'approved', 1, '2026-02-26 06:16:12', '', 9.51, 4.71, 3),
(11, 4, 'Sick', 2, '2025-03-07', '2025-03-07', 1.0, '', 'approved', 1, '2026-03-02 06:52:30', NULL, 1.21, 0.21, 5),
(12, 4, 'Vacational', 14, '2025-04-04', '2025-04-04', 1.0, '', 'approved', 1, '2026-03-02 06:54:47', NULL, 0.52, 0.21, 5),
(13, 4, 'Sick', 2, '2025-03-03', '2025-03-03', 1.0, '', 'approved', 1, '2026-03-02 06:56:23', NULL, 0.52, 0.21, 5),
(14, 4, 'Vacational', 14, '2026-03-12', '2026-03-12', 1.0, 'i just want to', 'approved', 1, '2026-03-02 06:58:28', '', 7.21, 2.96, 5),
(15, 3, 'Vacational', 14, '2026-03-14', '2026-03-14', 1.0, 'nah', 'rejected', 1, '2026-03-02 07:08:58', 'NAHH', 9.90, 5.25, 5),
(17, 3, 'Vacational', 14, '2026-03-23', '2026-03-24', 2.0, 'jsjsjsjs', 'approved', 1, '2026-03-02 07:20:40', '', 7.90, 5.25, 5),
(18, 3, 'Vacational', 14, '2025-12-02', '2025-12-02', 1.0, '', 'approved', 1, '2026-03-03 00:24:12', NULL, 8.91, 5.41, 2),
(19, 3, 'Vacational', 14, '2025-06-01', '2025-06-01', 1.0, '', 'approved', 1, '2026-03-03 00:43:02', NULL, 7.09, 3.91, 3),
(20, 3, 'Vacational', 14, '2025-01-31', '2025-01-31', 1.0, '', 'approved', 1, '2026-03-03 00:44:48', NULL, 9.05, 5.06, 5),
(21, 3, 'Vacational', 14, '2024-01-10', '2024-01-10', 1.0, '', 'approved', 1, '2026-03-03 01:32:25', NULL, 6.10, 6.10, 5),
(22, 3, 'Vacational', 14, '2025-02-28', '2026-02-28', 1.0, '', 'approved', 1, '2026-03-03 01:35:07', NULL, 8.10, 7.09, 4),
(23, 3, 'Vacational', 14, '2025-03-31', '2025-03-31', 1.0, '', 'approved', 1, '2026-03-03 01:57:47', NULL, 7.09, 9.10, 3);

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `deduct_balance` tinyint(1) NOT NULL DEFAULT 1,
  `requires_approval` tinyint(1) NOT NULL DEFAULT 1,
  `max_days_per_year` decimal(6,2) DEFAULT NULL,
  `auto_approve` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`id`, `name`, `deduct_balance`, `requires_approval`, `max_days_per_year`, `auto_approve`) VALUES
(2, 'Sick', 1, 1, NULL, 0),
(3, 'Emergency', 0, 1, NULL, 1),
(4, 'Special', 0, 0, NULL, 0),
(14, 'Vacational', 1, 1, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager','employee') NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `activation_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `role`, `is_active`, `activation_token`, `created_at`) VALUES
(1, 'admin@company.com', '$2y$10$F.tcknFl.wn1gMuK4XpuSuKgT8X/Fvcahbkdg5zpjkFkuXloJ9scG', 'admin', 1, NULL, '2026-02-23 07:20:19'),
(2, 'tapia.carlamae27@gmail.com', '$2y$10$8ovXTfIg.XzThNOE15AFAut2pgE27wSoOb/FTr.ViBAQjKKlKCXVe', 'employee', 0, '7ed4f53843dd09b63c906d7cc65241c562516a47927c139238904b10c14ff061', '2026-02-23 07:32:18'),
(3, 'jpdaquis@gmail.com', '$2y$10$z7b3QSDkvX5zbT5.K3f4F.EFeizIcu0d57AUdynhavPuvPTIYZG3.', 'employee', 0, '7207d1a3bac97786b00fd3827644769687aa7f9a2f9be48dfaf91b4a5aee4045', '2026-02-23 07:41:34'),
(4, 'hansferrer@gmail.com', '$2y$10$0DxGcIhDxVPbIE./slA8sO9DiBEKVag1zfEBjRrCYvoWDNGDQ1CRW', 'employee', 1, NULL, '2026-02-24 02:18:53'),
(5, 'jermaine@gmail.com', '$2y$10$EYkDEEhORkAEb09JMDuTeucrT09U52EVZFtI61LKG82dsN1oxO6Mq', 'employee', 1, NULL, '2026-02-26 05:16:36');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accruals`
--
ALTER TABLE `accruals`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `accrual_history`
--
ALTER TABLE `accrual_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `budget_history`
--
ALTER TABLE `budget_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_budget_history_emp_transdate` (`employee_id`,`trans_date`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `holiday_date` (`holiday_date`);

--
-- Indexes for table `leave_balance_logs`
--
ALTER TABLE `leave_balance_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accruals`
--
ALTER TABLE `accruals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `accrual_history`
--
ALTER TABLE `accrual_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `budget_history`
--
ALTER TABLE `budget_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `leave_balance_logs`
--
ALTER TABLE `leave_balance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
