-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 01, 2026 at 05:37 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hrms`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `action` varchar(150) NOT NULL,
  `details` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `details`, `created_at`) VALUES
(1, 1, 'login', 'User logged in', '2026-07-18 16:34:39'),
(2, 1, 'logout', 'User logged out', '2026-07-18 16:38:07'),
(3, 1, 'login', 'User logged in', '2026-07-19 00:25:23'),
(4, 1, 'logout', 'User logged out', '2026-07-19 00:28:26'),
(5, 1, 'login', 'User logged in', '2026-07-19 01:31:24'),
(6, 2, 'login', 'User logged in', '2026-07-19 02:23:50'),
(7, 1, 'login', 'User logged in', '2026-07-19 02:24:42'),
(8, 1, 'login', 'User logged in', '2026-07-19 04:29:18'),
(9, 1, 'login', 'User logged in', '2026-07-19 05:05:57'),
(10, 1, 'login', 'User logged in', '2026-07-19 13:42:43'),
(11, 1, 'login', 'User logged in', '2026-07-19 14:41:38'),
(12, 1, 'logout', 'User logged out', '2026-07-19 14:49:31'),
(13, 1, 'login', 'User logged in', '2026-07-19 14:49:43'),
(14, 1, 'login', 'User logged in', '2026-07-19 18:50:22'),
(15, 1, 'login', 'User logged in', '2026-07-19 20:34:24'),
(16, 3, 'login', 'User logged in', '2026-07-20 01:13:03'),
(17, 3, 'logout', 'User logged out', '2026-07-20 01:31:27'),
(18, 1, 'login', 'User logged in', '2026-07-20 01:31:37'),
(19, 1, 'logout', 'User logged out', '2026-07-20 01:31:51'),
(20, 3, 'login', 'User logged in', '2026-07-20 01:32:39'),
(21, 3, 'logout', 'User logged out', '2026-07-20 01:35:42'),
(22, 1, 'login', 'User logged in', '2026-07-20 01:37:32'),
(23, 1, 'logout', 'User logged out', '2026-07-20 01:52:28'),
(24, 27, 'login', 'User logged in', '2026-07-20 01:52:41'),
(25, 27, 'logout', 'User logged out', '2026-07-20 01:57:06'),
(26, 10, 'login', 'User logged in', '2026-07-20 01:58:21'),
(27, 10, 'logout', 'User logged out', '2026-07-20 01:58:32'),
(28, 27, 'login', 'User logged in', '2026-07-20 02:09:01'),
(34, 159, 'login', 'User logged in', '2026-07-20 03:12:01'),
(35, 159, 'logout', 'User logged out', '2026-07-20 03:12:41'),
(36, 159, 'login', 'User logged in', '2026-07-20 03:12:51'),
(37, 159, 'login', 'User logged in', '2026-07-20 04:21:43'),
(38, 159, 'logout', 'User logged out', '2026-07-20 05:00:26'),
(39, 159, 'login', 'User logged in', '2026-07-20 05:00:35'),
(40, 159, 'terminate_employee', 'Archived employee Andres Bonifacio due to Deployed to other store', '2026-07-20 05:03:52'),
(41, 159, 'login', 'User logged in', '2026-07-20 06:01:11'),
(42, 1, 'login', 'User logged in', '2026-07-20 06:02:14'),
(43, 1, 'login', 'User logged in', '2026-07-20 06:19:39'),
(44, 1, 'login', 'User logged in', '2026-07-20 06:22:55'),
(45, 145, 'login', 'User logged in', '2026-07-20 06:23:49'),
(46, 145, 'login', 'User logged in', '2026-07-20 06:55:30'),
(47, 159, 'login', 'User logged in', '2026-07-20 09:23:02'),
(48, 163, 'login', 'User logged in', '2026-07-20 09:48:02'),
(49, 159, 'login', 'User logged in', '2026-07-20 09:48:43'),
(50, 163, 'login', 'User logged in', '2026-07-20 09:53:49'),
(51, 159, 'login', 'User logged in', '2026-07-20 09:55:12'),
(52, 163, 'login', 'User logged in', '2026-07-20 09:58:05'),
(53, 152, 'kiosk_punch', 'Time punch via Kiosk', '2026-07-20 09:58:20'),
(54, 154, 'kiosk_punch', 'Time punch via Kiosk', '2026-07-20 09:59:22'),
(55, 146, 'kiosk_punch', 'Time punch via Kiosk', '2026-07-20 09:59:59'),
(56, 153, 'kiosk_punch', 'Time punch via Kiosk', '2026-07-20 10:00:29'),
(57, 159, 'login', 'User logged in', '2026-07-20 10:00:55'),
(58, 1, 'login', 'User logged in', '2026-07-20 10:04:56'),
(59, 142, 'login', 'User logged in', '2026-07-20 10:08:04'),
(60, 1, 'login', 'User logged in', '2026-07-20 10:13:46'),
(61, 163, 'login', 'User logged in', '2026-07-20 10:14:24'),
(62, 142, 'login', 'User logged in', '2026-07-20 10:18:53'),
(63, 159, 'login', 'User logged in', '2026-07-20 10:40:44'),
(64, 152, 'login', 'User logged in', '2026-07-20 10:43:25'),
(65, 159, 'login', 'User logged in', '2026-07-20 10:44:18'),
(66, 146, 'login', 'User logged in', '2026-07-20 10:45:38'),
(67, 148, 'login', 'User logged in', '2026-07-20 10:46:18'),
(68, 159, 'login', 'User logged in', '2026-07-20 10:47:35'),
(69, 146, 'login', 'User logged in', '2026-07-20 10:55:33'),
(70, 149, 'login', 'User logged in', '2026-07-20 11:13:45'),
(71, 155, 'login', 'User logged in', '2026-07-20 11:15:01'),
(72, 159, 'login', 'User logged in', '2026-07-20 11:16:02'),
(73, 147, 'login', 'User logged in', '2026-07-20 11:17:45'),
(74, 159, 'login', 'User logged in', '2026-07-20 11:18:26'),
(75, 147, 'login', 'User logged in', '2026-07-20 11:36:35'),
(76, 150, 'login', 'User logged in', '2026-07-20 11:40:10'),
(77, 163, 'login', 'User logged in', '2026-07-20 11:41:06'),
(78, 146, 'kiosk_punch', 'Time punch via Kiosk', '2026-07-20 11:42:04'),
(79, 159, 'login', 'User logged in', '2026-07-20 11:42:19'),
(80, 159, 'login', 'User logged in', '2026-07-20 11:47:12'),
(81, 147, 'login', 'User logged in', '2026-07-20 11:49:41'),
(82, 159, 'login', 'User logged in', '2026-07-20 11:51:51'),
(83, 147, 'login', 'User logged in', '2026-07-20 11:54:23'),
(84, 159, 'login', 'User logged in', '2026-07-20 14:09:53'),
(85, 1, 'login', 'User logged in', '2026-07-26 23:19:52'),
(86, 159, 'login', 'User logged in', '2026-07-26 23:23:17'),
(87, 143, 'login', 'User logged in', '2026-07-26 23:24:12'),
(88, 1, 'login', 'User logged in', '2026-07-27 14:30:20'),
(89, 1, 'login', 'User logged in', '2026-07-27 14:30:27'),
(90, 1, 'login', 'User logged in', '2026-07-27 18:21:23'),
(91, 1, 'login', 'User logged in', '2026-07-27 18:21:30'),
(92, 1, 'login', 'User logged in', '2026-07-27 18:24:50'),
(93, 1, 'login', 'User logged in', '2026-07-27 20:31:42'),
(94, 163, 'login', 'User logged in', '2026-07-27 23:04:53'),
(95, 148, 'kiosk_punch', 'Time punch via Kiosk', '2026-07-27 23:05:05'),
(96, 164, 'login', 'User logged in', '2026-07-27 23:05:53'),
(97, 165, 'login', 'User logged in', '2026-07-27 23:17:12'),
(98, 165, 'login', 'User logged in', '2026-07-27 23:28:20'),
(99, 1, 'login', 'User logged in', '2026-07-27 23:49:17'),
(100, 165, 'login', 'User logged in', '2026-07-28 00:22:57'),
(101, 164, 'login', 'User logged in', '2026-07-28 00:23:22'),
(102, 148, 'kiosk_punch', 'Time punch via Kiosk', '2026-07-28 00:23:34'),
(103, 163, 'login', 'User logged in', '2026-07-28 00:23:50'),
(104, 165, 'login', 'User logged in', '2026-07-28 00:24:04'),
(105, 1, 'login', 'User logged in', '2026-07-28 00:33:40'),
(106, 165, 'login', 'User logged in', '2026-07-28 00:47:00'),
(107, 1, 'login', 'User logged in', '2026-07-28 00:48:54'),
(108, 164, 'login', 'User logged in', '2026-07-28 00:55:56'),
(109, 163, 'login', 'User logged in', '2026-07-28 01:02:27'),
(110, 164, 'login', 'User logged in', '2026-07-28 01:24:29'),
(111, 164, 'login', 'User logged in', '2026-07-28 01:26:19'),
(112, 164, 'login', 'User logged in', '2026-07-28 01:32:27'),
(113, 164, 'login', 'User logged in', '2026-07-28 02:48:08'),
(114, 1, 'login', 'User logged in', '2026-07-28 02:52:59'),
(115, 1, 'login', 'User logged in', '2026-07-28 04:13:08'),
(116, 1, 'login', 'User logged in', '2026-07-28 08:06:13'),
(117, 165, 'login', 'User logged in', '2026-07-28 08:13:52'),
(118, 165, 'login', 'User logged in', '2026-07-28 08:19:46'),
(119, 1, 'login', 'User logged in', '2026-07-28 08:22:06'),
(120, 165, 'login', 'User logged in', '2026-07-28 08:31:50'),
(121, 164, 'login', 'User logged in', '2026-07-28 08:32:40'),
(122, 148, 'login', 'User logged in', '2026-07-28 08:34:51'),
(123, 165, 'login', 'User logged in', '2026-07-28 08:39:11'),
(124, 1, 'login', 'User logged in', '2026-07-28 08:40:14'),
(125, 1, 'login', 'User logged in', '2026-07-28 13:03:25'),
(126, 165, 'login', 'User logged in', '2026-07-28 13:20:23'),
(127, 1, 'login', 'User logged in', '2026-07-28 13:29:33'),
(128, 143, 'login', 'User logged in', '2026-07-28 13:30:56'),
(129, 1, 'login', 'User logged in', '2026-07-28 16:07:45'),
(130, 166, 'login', 'User logged in', '2026-07-28 16:35:05'),
(131, 159, 'login', 'User logged in', '2026-07-28 16:57:15'),
(132, 166, 'login', 'User logged in', '2026-07-28 17:00:08'),
(133, 165, 'login', 'User logged in', '2026-07-28 17:00:51'),
(134, 163, 'login', 'User logged in', '2026-07-28 17:01:21'),
(135, 166, 'kiosk_punch', 'Time punch via Kiosk', '2026-07-28 17:01:44'),
(136, 165, 'login', 'User logged in', '2026-07-28 17:02:22'),
(137, 164, 'login', 'User logged in', '2026-07-28 17:08:02'),
(138, 164, 'login', 'User logged in', '2026-07-28 17:10:04'),
(139, 165, 'login', 'User logged in', '2026-07-28 17:10:45'),
(140, 1, 'login', 'User logged in', '2026-07-28 19:02:29'),
(141, 164, 'login', 'User logged in', '2026-07-28 22:07:29'),
(142, 165, 'login', 'User logged in', '2026-07-28 22:40:32'),
(143, 1, 'login', 'User logged in', '2026-07-28 22:42:20'),
(144, 163, 'login', 'User logged in', '2026-07-28 22:42:59'),
(145, 148, 'kiosk_punch', 'Time punch via Kiosk', '2026-07-28 22:43:20'),
(146, 153, 'kiosk_punch', 'Time punch via Kiosk', '2026-07-28 22:43:38'),
(147, 154, 'kiosk_punch', 'Time punch via Kiosk', '2026-07-28 22:44:06'),
(148, 152, 'kiosk_punch', 'Time punch via Kiosk', '2026-07-28 22:44:16'),
(149, 166, 'kiosk_punch', 'Time punch via Kiosk', '2026-07-28 22:44:31'),
(150, 1, 'login', 'User logged in', '2026-07-28 22:44:47'),
(151, 163, 'login', 'User logged in', '2026-07-28 22:45:48'),
(152, 153, 'kiosk_punch', 'Time punch via Kiosk', '2026-07-28 22:46:03'),
(153, 154, 'kiosk_punch', 'Time punch via Kiosk', '2026-07-28 22:46:25'),
(154, 152, 'kiosk_punch', 'Time punch via Kiosk', '2026-07-28 22:46:38'),
(155, 1, 'login', 'User logged in', '2026-07-28 22:46:53'),
(156, 1, 'login', 'User logged in', '2026-07-28 22:47:33'),
(157, 164, 'login', 'User logged in', '2026-07-28 23:02:58'),
(158, 1, 'login', 'User logged in', '2026-07-28 23:13:06'),
(159, 1, 'login', 'User logged in', '2026-07-29 00:17:42'),
(160, 159, 'login', 'User logged in', '2026-07-29 12:16:02'),
(161, 1, 'login', 'User logged in', '2026-07-29 13:44:10'),
(162, 1, 'login', 'User logged in', '2026-07-29 13:46:19'),
(163, 165, 'login', 'User logged in', '2026-07-29 13:46:58'),
(164, 163, 'login', 'User logged in', '2026-07-29 13:47:43'),
(165, 141, 'kiosk_punch', 'Time punch via Kiosk', '2026-07-29 13:48:06'),
(166, 1, 'login', 'User logged in', '2026-07-29 13:48:19'),
(167, 163, 'login', 'User logged in', '2026-07-29 13:48:45'),
(168, 151, 'kiosk_punch', 'Time punch via Kiosk', '2026-07-29 13:48:52'),
(169, 153, 'kiosk_punch', 'Time punch via Kiosk', '2026-07-29 13:49:04'),
(170, 150, 'kiosk_punch', 'Time punch via Kiosk', '2026-07-29 13:49:24'),
(171, 165, 'login', 'User logged in', '2026-07-29 13:49:43'),
(172, 1, 'login', 'User logged in', '2026-07-29 13:51:52'),
(173, 159, 'login', 'User logged in', '2026-07-29 13:52:35'),
(174, 163, 'login', 'User logged in', '2026-07-29 13:58:07'),
(175, 1, 'login', 'User logged in', '2026-07-29 14:10:50'),
(176, 165, 'login', 'User logged in', '2026-07-29 14:11:52'),
(177, 1, 'login', 'User logged in', '2026-08-30 18:43:38'),
(178, 1, 'login', 'User logged in', '2026-08-30 20:46:03'),
(179, 1, 'login', 'User logged in', '2026-08-31 00:09:54'),
(180, 1, 'login', 'User logged in', '2026-08-31 04:11:03'),
(181, 1, 'login', 'User logged in', '2026-08-31 10:58:38'),
(182, 1, 'login', 'User logged in', '2026-08-31 12:40:26'),
(183, 1, 'login', 'User logged in', '2026-09-03 19:23:55'),
(184, 1, 'login', 'User logged in', '2026-09-03 20:42:01'),
(185, 165, 'login', 'User logged in', '2026-09-03 20:59:16'),
(186, 1, 'login', 'User logged in', '2026-09-03 23:33:06'),
(187, 1, 'login', 'User logged in', '2026-09-04 01:05:28'),
(188, 1, 'login', 'User logged in', '2026-09-06 18:55:38'),
(189, 1, 'login', 'User logged in', '2026-09-07 00:06:49'),
(190, 1, 'login', 'User logged in', '2026-09-07 04:41:08'),
(191, 165, 'login', 'User logged in', '2026-09-07 05:05:05'),
(192, 163, 'login', 'User logged in', '2026-09-07 05:08:10'),
(193, 141, 'kiosk_punch', 'Time punch via Kiosk', '2026-09-07 05:08:51'),
(194, 148, 'kiosk_punch', 'Time punch via Kiosk', '2026-09-07 05:09:23'),
(195, 152, 'kiosk_punch', 'Time punch via Kiosk', '2026-09-07 05:15:52'),
(196, 165, 'login', 'User logged in', '2026-09-07 05:32:59'),
(197, 165, 'login', 'User logged in', '2026-09-07 05:42:30'),
(198, 165, 'login', 'User logged in', '2026-09-07 05:45:32'),
(199, 167, 'login', 'User logged in', '2026-09-07 06:03:04'),
(200, 165, 'login', 'User logged in', '2026-09-07 06:34:26'),
(201, 1, 'login', 'User logged in', '2026-09-07 06:44:13'),
(202, 1, 'login', 'User logged in', '2026-09-08 13:27:47'),
(203, 165, 'login', 'User logged in', '2026-09-08 14:29:05'),
(204, 163, 'login', 'User logged in', '2026-09-08 14:34:14'),
(205, 148, 'kiosk_punch', 'Time punch via Kiosk', '2026-09-08 14:35:35'),
(206, 1, 'login', 'User logged in', '2026-09-08 15:27:06'),
(207, 165, 'login', 'User logged in', '2026-09-08 15:34:48'),
(208, 1, 'login', 'User logged in', '2026-09-08 18:21:43'),
(209, 1, 'login', 'User logged in', '2026-09-15 00:32:33'),
(210, 1, 'login', 'User logged in', '2026-09-15 01:17:14'),
(211, 1, 'login', 'User logged in', '2026-09-15 02:36:31'),
(212, 165, 'login', 'User logged in', '2026-09-15 02:51:09'),
(213, 163, 'login', 'User logged in', '2026-09-15 02:51:21'),
(214, 148, 'kiosk_punch', 'Time punch via Kiosk', '2026-09-15 02:51:39'),
(215, 165, 'login', 'User logged in', '2026-09-15 02:51:57'),
(216, 1, 'login', 'User logged in', '2026-09-15 14:04:29'),
(217, 165, 'login', 'User logged in', '2026-09-15 14:06:28'),
(218, 163, 'login', 'User logged in', '2026-09-15 14:08:32'),
(219, 164, 'login', 'User logged in', '2026-09-15 14:10:18'),
(220, 1, 'login', 'User logged in', '2026-09-15 14:23:11'),
(221, 1, 'login', 'User logged in', '2026-09-15 14:25:53'),
(222, 148, 'login', 'User logged in', '2026-09-15 14:29:11'),
(223, 1, 'login', 'User logged in', '2026-09-15 14:30:24'),
(224, 1, 'login', 'User logged in', '2026-09-15 14:34:13'),
(225, 165, 'login', 'User logged in', '2026-09-15 14:34:27'),
(226, 163, 'login', 'User logged in', '2026-09-15 14:34:40'),
(227, 164, 'login', 'User logged in', '2026-09-15 14:35:00'),
(228, 1, 'login', 'User logged in', '2026-09-15 14:39:42'),
(229, 1, 'login', 'User logged in', '2026-09-15 15:17:48'),
(230, 161, 'login', 'User logged in', '2026-09-15 16:23:26'),
(231, 1, 'login', 'User logged in', '2026-09-15 18:14:04'),
(232, 1, 'login', 'User logged in', '2026-09-20 19:54:39'),
(233, 1, 'login', 'User logged in', '2026-09-20 22:29:42'),
(234, 1, 'login', 'User logged in', '2026-09-21 06:17:19'),
(235, 1, 'login', 'User logged in', '2026-09-21 06:28:40'),
(236, 1, 'login', 'User logged in', '2026-09-21 06:37:04'),
(237, 1, 'login', 'User logged in', '2026-09-21 06:44:18'),
(238, 1, 'login', 'User logged in', '2026-09-21 06:50:49'),
(239, 1, 'login', 'User logged in', '2026-09-21 06:56:35'),
(240, 1, 'login', 'User logged in', '2026-09-29 16:05:12'),
(241, 1, 'login', 'User logged in', '2026-09-29 17:08:49'),
(242, 148, 'login', 'User logged in', '2026-09-29 17:47:55'),
(243, 159, 'login', 'User logged in', '2026-09-29 17:48:48'),
(244, 1, 'login', 'User logged in', '2026-09-29 17:50:54'),
(245, 151, 'login', 'User logged in', '2026-09-29 17:52:26'),
(246, 1, 'login', 'User logged in', '2026-09-29 17:53:01'),
(247, 163, 'login', 'User logged in', '2026-09-29 17:53:59'),
(248, 163, 'login', 'User logged in', '2026-09-29 17:54:57'),
(249, 164, 'login', 'User logged in', '2026-09-29 17:55:43'),
(250, 1, 'login', 'User logged in', '2026-09-30 05:59:18'),
(251, 1, 'login', 'User logged in', '2026-09-30 09:36:37'),
(252, 1, 'login', 'User logged in', '2026-10-01 11:53:43');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `image` varchar(500) DEFAULT NULL,
  `file_attachment` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `applicants`
--

CREATE TABLE `applicants` (
  `id` int NOT NULL,
  `first_name` varchar(150) DEFAULT NULL,
  `last_name` varchar(150) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `position_applied` varchar(120) NOT NULL,
  `stage` varchar(50) NOT NULL DEFAULT 'Initial Interview',
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `valid_id_photo` varchar(500) DEFAULT NULL,
  `resume` varchar(500) DEFAULT NULL,
  `reference_name` varchar(100) DEFAULT NULL,
  `reference_phone` varchar(50) DEFAULT NULL,
  `valid_id_back_photo` varchar(500) DEFAULT NULL,
  `expected_hourly_rate` decimal(12,2) DEFAULT NULL,
  `agreed_hourly_rate` decimal(12,2) DEFAULT NULL,
  `education_level` varchar(50) DEFAULT NULL,
  `school_name` varchar(150) DEFAULT NULL,
  `school_address` varchar(255) DEFAULT NULL,
  `year_graduated` varchar(20) DEFAULT NULL,
  `education_status` varchar(50) DEFAULT NULL,
  `experience_level` varchar(50) DEFAULT NULL,
  `course_diploma` varchar(150) DEFAULT NULL,
  `employment_category` varchar(50) DEFAULT 'Full-Time',
  `birthdate` date DEFAULT NULL,
  `address` text,
  `sex` varchar(20) DEFAULT '',
  `nationality` varchar(100) DEFAULT '',
  `preferred_schedule` varchar(50) DEFAULT 'Any',
  `branch_id` int DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `applicants`
--

INSERT INTO `applicants` (`id`, `first_name`, `last_name`, `email`, `phone`, `position_applied`, `stage`, `notes`, `created_at`, `valid_id_photo`, `resume`, `reference_name`, `reference_phone`, `valid_id_back_photo`, `expected_hourly_rate`, `agreed_hourly_rate`, `education_level`, `school_name`, `school_address`, `year_graduated`, `education_status`, `experience_level`, `course_diploma`, `employment_category`, `birthdate`, `address`, `sex`, `nationality`, `preferred_schedule`, `branch_id`, `rejection_reason`) VALUES
(1, 'Terence', 'Danlag', 'danlag.terence@ncst.edu.ph', '+63 9331001712', 'Barista', 'Hireable', '', '2026-07-20 09:36:09', 'id_1784511369_143.jpg', 'resume_1784511369_198.pdf', 'Renz Danlag', '+63 9942133053', 'id_back_1784511369_615.jpg', 0.68, 0.00, 'College', 'National College of Science and Technology', '', '2026', 'Enrolled', 'No Experience', 'BS Information Technology', 'Full-Time', '2006-03-04', 'B2, L39, Camachile Subdivision, Pasong Camachile I, General Trias, Cavite, 4017', 'Male', 'Filipino', 'Night', 1, NULL),
(2, 'Renz', 'Danlag', 'danlagterence231@gmail.com', '+63 9331001712', 'Head Barista', 'Hireable', '', '2026-07-28 02:58:02', 'id_1785178681_322.jpg', 'resume_1785178681_792.pdf', 'RenzTe Danlag', '+63 5465120222', 'id_back_1785178681_628.jpg', 0.68, 0.00, 'College', 'National College of Science and Technology', '', '2026', 'Enrolled', '<= 3 Years', 'BS Information Technology', 'Full-Time', '2006-03-04', '[REDACTED]', 'Male', 'Filipino', 'Night', 1, NULL),
(3, 'Kristan', 'Kyle Ante', 'kristankyleante@gmail.com', '+63 9260114881', 'Barista', 'Hired', 'Bading', '2026-07-28 16:18:05', 'id_1785226685_738.jpg', 'resume_1785226685_108.pdf', 'Shaine', '+63 5646645344', 'id_back_1785226685_777.jpg', 0.97, 0.00, 'College', 'National College of Science and Technology', '', '2026', 'Enrolled', '<= 1 Year', 'BS Information Technology', 'Full-Time', '2005-03-01', '[REDACTED]', 'Male', 'Chinese', 'Morning', 1, NULL),
(4, 'Renz', 'Dan', 'bentaforever43@gmail.com', '+63 9942133053', 'Head Barista', 'Hireable', NULL, '2026-07-29 00:17:26', 'id_1785255446_195.jpg', 'resume_1785255446_365.pdf', 'Terence', '+63 9331001712', 'id_back_1785255446_514.jpg', 0.81, NULL, 'College', 'Polytechnic University of the Philippines', NULL, '2024', 'Enrolled', '<= 3 Years', 'BSIT', 'Full-Time', '2006-03-04', '[REDACTED]', 'Male', 'Filipino', 'Any', 1, NULL),
(5, 'James', 'Frederick Cipriaso', 'felicespaulkenneth.ncst@gmail.com', '+63 0555521213', 'Barista', 'Rejected', NULL, '2026-07-29 14:23:41', 'id_1785306221_671.jpg', 'resume_1785306221_459.pdf', 'Kevin', '+63 0555555611', 'id_back_1785306221_194.bmp', 0.81, NULL, 'College', 'National', NULL, '2023', 'Graduated', '<= 1 Year', 'BSCS', 'Full-Time', '2000-03-07', '', '', '', 'Any', 1, 'No-Show'),
(6, 'Terence', 'Danlag', 'danlagterence@gmail.com', '+63 9331001712', 'Barista', 'Hireable', '', '2026-09-06 21:33:53', 'id_1788701633_233.jpg', 'resume_1788701633_748.pdf', 'Terence', '+63 9215161321', 'id_back_1788701633_974.jpg', NULL, NULL, 'College', 'National College of Science and Technology', '', '2026', 'Enrolled', 'No Experience', 'BS Information Technologies', 'Part-Time', '2006-03-04', '{REDACTED}', 'Male', 'Filipino', 'Night', 1, NULL),
(7, 'Terence', 'Danalg', 'danlagterence01@gmail.com', '+63 9331001712', 'Barista', 'Final Interview', '', '2026-09-06 21:50:02', 'id_1788702602_696.jpg', 'resume_1788702602_867.pdf', 'Terence', '+63 5151212121', 'id_back_1788702602_333.jpg', NULL, NULL, 'College', 'NNNNN', '', '2025', 'Graduated', '<= 1 Year', 'BS Bullshit', 'Full-Time', '2006-03-04', '{REDACTED}', 'Male', 'Filipino', 'Morning', 1, NULL),
(8, 'Terence', 'Danalg', 'danlagterence231@gmail.com', '+63 9331001412', 'Barista', 'New', '', '2026-09-06 22:08:56', 'id_1788703736_295.jpg', 'resume_1788703736_629.pdf', 'John Kevs', '+63 1541512323', 'id_back_1788703736_598.jpg', NULL, NULL, 'College', '48515', '', '2025', 'Undergraduate', 'No Experience', 'BS Shitbull', 'Part-Time', '2004-10-05', '12121', 'Male', 'Filipino', 'Any', 1, NULL),
(9, 'Justine Kyle', 'Tu', 'kyletutri@gmail.com', '+63 5154541664', 'Barista', 'Initial Interview', '', '2026-09-06 22:25:09', 'id_1788704709_228.JPG', 'resume_1788704709_808.pdf', 'Kyle Wan', '+63 0546945415', 'id_back_1788704709_347.jpg', NULL, NULL, 'College', 'National College of Science and Technology', '', '2026', 'Enrolled', 'No Experience', 'BS in Bullshitery', 'Full-Time', '2000-12-08', 'Taga Imus, tabe taeb', 'Female', 'Bading', 'Mid', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `applicant_logs`
--

CREATE TABLE `applicant_logs` (
  `id` int NOT NULL,
  `applicant_id` int NOT NULL,
  `user_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applicant_logs`
--

INSERT INTO `applicant_logs` (`id`, `applicant_id`, `user_name`, `action`, `created_at`) VALUES
(1, 1, 'Manuel Quezon', 'Added Applicant', '2026-07-20 09:36:09'),
(2, 1, 'Manuel Quezon', 'Moved to Initial Interview (Scheduled)', '2026-07-20 14:13:02'),
(3, 1, 'System Admin', 'Moved to Final Interview (Scheduled)', '2026-07-26 23:22:10'),
(4, 2, 'System Admin', 'Added Applicant', '2026-07-28 02:58:02'),
(5, 2, 'System Admin', 'Moved to Initial Interview (Scheduled)', '2026-07-28 16:10:21'),
(6, 3, 'System Admin', 'Added Applicant', '2026-07-28 16:18:05'),
(7, 3, 'System Admin', 'Moved to Final Interview (Scheduled)', '2026-07-28 16:18:44'),
(8, 3, 'System Admin', 'Moved to Final Interview (Scheduled)', '2026-07-28 16:18:50'),
(9, 3, 'System Admin', 'Moved to Final Interview (Scheduled)', '2026-07-28 16:18:55'),
(10, 3, 'System Admin', 'Moved to Final Interview (Scheduled)', '2026-07-28 16:19:03'),
(11, 3, 'System Admin', 'Moved to Hireable', '2026-07-28 16:19:16'),
(12, 3, 'System Admin', 'Hired as Barista', '2026-07-28 16:20:13'),
(13, 4, 'System (Online)', 'Applicant submitted online application', '2026-07-29 00:17:26'),
(14, 5, 'System (Online)', 'Applicant submitted online application', '2026-07-29 14:23:41'),
(15, 5, 'Manuel Quezon', 'Moved to Initial Interview (Scheduled)', '2026-07-29 14:26:50'),
(16, 4, 'System Admin', 'Moved to Hireable', '2026-09-03 19:59:00'),
(17, 1, 'System Admin', 'Moved to Hireable', '2026-09-03 23:59:59'),
(18, 2, 'System Admin', 'Moved to Final Interview (Scheduled)', '2026-09-06 20:38:50'),
(19, 2, 'System Admin', 'Moved to Hireable', '2026-09-06 21:21:56'),
(20, 6, 'System Admin', 'Added Applicant', '2026-09-06 21:33:53'),
(21, 6, 'System Admin', 'Moved to Initial Interview (Scheduled)', '2026-09-06 21:47:09'),
(22, 7, 'System Admin', 'Added Applicant', '2026-09-06 21:50:02'),
(23, 7, 'System Admin', 'Moved to Initial Interview (Scheduled)', '2026-09-06 21:51:09'),
(24, 8, 'System Admin', 'Added Applicant', '2026-09-06 22:08:56'),
(25, 6, 'System', 'Interview evaluated. Recommended: Next Round. Moved to Final Interview.', '2026-09-06 22:13:54'),
(26, 9, 'System Admin', 'Added Applicant', '2026-09-06 22:25:09'),
(27, 9, 'System Admin', 'Moved to Initial Interview (Scheduled)', '2026-09-06 22:25:18'),
(28, 7, 'System', 'Interview evaluated. Recommended: Next Round. Moved to Final Interview.', '2026-09-06 22:54:19'),
(29, 5, 'System Admin', 'Moved to Rejected', '2026-09-07 00:59:25'),
(30, 8, 'System Admin', 'Moved to Initial Interview via Kanban Drag-and-Drop', '2026-09-20 20:44:27'),
(31, 8, 'System Admin', 'Moved to New via Kanban Drag-and-Drop', '2026-09-20 20:44:28'),
(32, 8, 'System Admin', 'Moved to Initial Interview via Kanban Drag-and-Drop', '2026-09-20 20:44:29'),
(33, 8, 'System Admin', 'Moved to New via Kanban Drag-and-Drop', '2026-09-20 20:44:30'),
(34, 8, 'System Admin', 'Moved to Initial Interview via Kanban Drag-and-Drop', '2026-09-20 20:44:31'),
(35, 8, 'System Admin', 'Moved to New via Kanban Drag-and-Drop', '2026-09-20 20:44:32'),
(36, 8, 'System Admin', 'Moved to Initial Interview via Kanban Drag-and-Drop', '2026-09-20 20:44:33'),
(37, 8, 'System Admin', 'Moved to New via Kanban Drag-and-Drop', '2026-09-20 20:44:34'),
(38, 8, 'System Admin', 'Moved to Initial Interview via Kanban Drag-and-Drop', '2026-09-20 20:44:34'),
(39, 8, 'System Admin', 'Moved to New via Kanban Drag-and-Drop', '2026-09-20 20:44:35'),
(40, 6, 'System Admin', 'Moved to Hireable via Kanban Drag-and-Drop', '2026-09-20 21:00:06');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `time_in` datetime NOT NULL,
  `time_out` datetime DEFAULT NULL,
  `break_in` datetime DEFAULT NULL,
  `break_out` datetime DEFAULT NULL,
  `is_late` tinyint(1) NOT NULL DEFAULT '0',
  `overtime_hours` decimal(6,2) NOT NULL DEFAULT '0.00',
  `undertime_hours` decimal(6,2) NOT NULL DEFAULT '0.00',
  `status` varchar(50) NOT NULL DEFAULT 'Present',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `employee_id`, `time_in`, `time_out`, `break_in`, `break_out`, `is_late`, `overtime_hours`, `undertime_hours`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-04-23 14:53:00', '2026-04-23 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(2, 1, '2026-04-24 10:46:00', '2026-04-24 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(3, 1, '2026-04-27 07:00:00', '2026-04-27 17:49:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(4, 1, '2026-04-29 10:52:00', '2026-04-29 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(5, 1, '2026-04-30 07:41:00', '2026-04-30 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:33', NULL),
(6, 1, '2026-05-01 14:46:00', '2026-05-01 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(7, 1, '2026-05-03 15:00:00', '2026-05-04 01:28:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(8, 1, '2026-05-05 06:53:00', '2026-05-05 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(9, 1, '2026-05-06 11:00:00', '2026-05-06 20:13:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(10, 1, '2026-05-07 06:53:00', '2026-05-07 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(11, 1, '2026-05-09 06:47:00', '2026-05-09 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(12, 1, '2026-05-10 14:45:00', '2026-05-10 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(13, 1, '2026-05-11 14:49:00', '2026-05-11 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(14, 1, '2026-05-12 10:55:00', '2026-05-12 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(15, 1, '2026-05-13 15:37:00', '2026-05-13 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:33', NULL),
(16, 1, '2026-05-14 10:52:00', '2026-05-14 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(17, 1, '2026-05-16 11:00:00', '2026-05-16 20:07:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(18, 1, '2026-05-17 06:46:00', '2026-05-17 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(19, 1, '2026-05-18 15:11:00', '2026-05-18 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:33', NULL),
(20, 1, '2026-05-19 15:13:00', '2026-05-19 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:33', NULL),
(21, 1, '2026-05-22 14:49:00', '2026-05-22 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(22, 1, '2026-05-23 14:50:00', '2026-05-23 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(23, 1, '2026-05-24 10:54:00', '2026-05-24 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(24, 1, '2026-05-26 10:55:00', '2026-05-26 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(25, 1, '2026-05-27 11:22:00', '2026-05-27 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:33', NULL),
(26, 1, '2026-05-28 06:51:00', '2026-05-28 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(27, 1, '2026-05-31 10:45:00', '2026-05-31 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(28, 1, '2026-06-02 14:48:00', '2026-06-02 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(29, 1, '2026-06-03 06:52:00', '2026-06-03 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(30, 1, '2026-06-05 06:51:00', '2026-06-05 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(31, 1, '2026-06-08 14:47:00', '2026-06-08 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(32, 1, '2026-06-09 10:51:00', '2026-06-09 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(33, 1, '2026-06-11 14:53:00', '2026-06-11 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(34, 1, '2026-06-12 06:45:00', '2026-06-12 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(35, 1, '2026-06-15 14:53:00', '2026-06-15 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(36, 1, '2026-06-16 10:54:00', '2026-06-16 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(37, 1, '2026-06-19 06:46:00', '2026-06-19 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(38, 1, '2026-06-20 14:55:00', '2026-06-20 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(39, 1, '2026-06-21 14:55:00', '2026-06-21 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(40, 1, '2026-06-22 10:48:00', '2026-06-22 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(41, 1, '2026-06-23 14:52:00', '2026-06-23 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(42, 1, '2026-06-27 06:45:00', '2026-06-27 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(43, 1, '2026-06-28 06:47:00', '2026-06-28 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(44, 1, '2026-06-29 14:51:00', '2026-06-29 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(45, 1, '2026-07-01 10:48:00', '2026-07-01 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(46, 1, '2026-07-02 06:48:00', '2026-07-02 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(47, 1, '2026-07-03 10:47:00', '2026-07-03 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(48, 1, '2026-07-05 06:55:00', '2026-07-05 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(49, 1, '2026-07-06 10:52:00', '2026-07-06 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(50, 1, '2026-07-07 06:52:00', '2026-07-07 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(51, 1, '2026-07-10 07:00:00', '2026-07-10 17:48:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(52, 1, '2026-07-12 10:50:00', '2026-07-12 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(53, 1, '2026-07-13 10:53:00', '2026-07-13 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(54, 1, '2026-07-16 07:00:00', '2026-07-16 16:29:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(55, 1, '2026-07-17 06:48:00', '2026-07-17 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(56, 1, '2026-07-18 14:53:00', '2026-07-18 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(57, 1, '2026-07-19 06:51:00', '2026-07-19 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(58, 2, '2026-04-17 10:51:00', '2026-04-17 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(59, 2, '2026-04-18 10:46:00', '2026-04-18 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(60, 2, '2026-04-19 06:49:00', '2026-04-19 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(61, 2, '2026-04-20 06:50:00', '2026-04-20 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(62, 2, '2026-04-21 07:00:00', '2026-04-21 17:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(63, 2, '2026-04-22 06:49:00', '2026-04-22 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(64, 2, '2026-04-23 14:51:00', '2026-04-23 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(65, 2, '2026-04-24 11:28:00', '2026-04-24 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:33', NULL),
(66, 2, '2026-04-25 07:34:00', '2026-04-25 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:33', NULL),
(67, 2, '2026-04-26 14:55:00', '2026-04-26 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(68, 2, '2026-04-27 14:50:00', '2026-04-27 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(69, 2, '2026-05-01 10:54:00', '2026-05-01 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(70, 2, '2026-05-02 11:00:00', '2026-05-02 20:22:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(71, 2, '2026-05-04 10:49:00', '2026-05-04 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(72, 2, '2026-05-05 10:51:00', '2026-05-05 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(73, 2, '2026-05-06 14:53:00', '2026-05-06 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(74, 2, '2026-05-07 06:46:00', '2026-05-07 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(75, 2, '2026-05-10 15:10:00', '2026-05-10 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:33', NULL),
(76, 2, '2026-05-12 06:50:00', '2026-05-12 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(77, 2, '2026-05-13 10:51:00', '2026-05-13 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(78, 2, '2026-05-14 15:00:00', '2026-05-15 00:31:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(79, 2, '2026-05-15 11:27:00', '2026-05-15 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:33', NULL),
(80, 2, '2026-05-16 11:00:00', '2026-05-16 20:29:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(81, 2, '2026-05-17 10:53:00', '2026-05-17 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(82, 2, '2026-05-19 10:46:00', '2026-05-19 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(83, 2, '2026-05-20 10:46:00', '2026-05-20 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(84, 2, '2026-05-21 15:00:00', '2026-05-22 00:39:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(85, 2, '2026-05-22 15:00:00', '2026-05-23 01:08:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(86, 2, '2026-05-23 06:49:00', '2026-05-23 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(87, 2, '2026-05-24 10:53:00', '2026-05-24 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(88, 2, '2026-05-25 10:45:00', '2026-05-25 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(89, 2, '2026-05-27 06:48:00', '2026-05-27 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(90, 2, '2026-05-29 06:49:00', '2026-05-29 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(91, 2, '2026-05-30 06:47:00', '2026-05-30 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(92, 2, '2026-05-31 06:46:00', '2026-05-31 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(93, 2, '2026-06-01 15:00:00', '2026-06-02 00:57:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(94, 2, '2026-06-02 14:53:00', '2026-06-02 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(95, 2, '2026-06-03 07:30:00', '2026-06-03 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:33', NULL),
(96, 2, '2026-06-04 06:45:00', '2026-06-04 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(97, 2, '2026-06-05 15:00:00', '2026-06-06 00:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(98, 2, '2026-06-06 14:45:00', '2026-06-06 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(99, 2, '2026-06-07 14:48:00', '2026-06-07 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(100, 2, '2026-06-10 06:51:00', '2026-06-10 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(101, 2, '2026-06-13 06:51:00', '2026-06-13 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(102, 2, '2026-06-15 06:45:00', '2026-06-15 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(103, 2, '2026-06-16 14:50:00', '2026-06-16 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(104, 2, '2026-06-17 14:53:00', '2026-06-17 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(105, 2, '2026-06-18 06:54:00', '2026-06-18 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(106, 2, '2026-06-19 07:00:00', '2026-06-19 16:30:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(107, 2, '2026-06-21 06:54:00', '2026-06-21 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(108, 2, '2026-06-22 10:51:00', '2026-06-22 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(109, 2, '2026-06-25 14:46:00', '2026-06-25 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(110, 2, '2026-06-26 06:53:00', '2026-06-26 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(111, 2, '2026-06-27 14:47:00', '2026-06-27 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(112, 2, '2026-06-28 10:49:00', '2026-06-28 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(113, 2, '2026-06-29 10:49:00', '2026-06-29 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(114, 2, '2026-06-30 14:52:00', '2026-06-30 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(115, 2, '2026-07-01 10:55:00', '2026-07-01 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(116, 2, '2026-07-02 06:51:00', '2026-07-02 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(117, 2, '2026-07-03 06:54:00', '2026-07-03 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(118, 2, '2026-07-04 06:53:00', '2026-07-04 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(119, 2, '2026-07-05 14:49:00', '2026-07-05 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(120, 2, '2026-07-06 07:00:00', '2026-07-06 16:56:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(121, 2, '2026-07-07 11:00:00', '2026-07-07 20:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(122, 2, '2026-07-08 07:16:00', '2026-07-08 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:33', NULL),
(123, 2, '2026-07-10 14:47:00', '2026-07-10 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(124, 2, '2026-07-11 14:51:00', '2026-07-11 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(125, 2, '2026-07-13 10:54:00', '2026-07-13 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(126, 2, '2026-07-14 10:53:00', '2026-07-14 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(127, 2, '2026-07-15 14:48:00', '2026-07-15 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(128, 2, '2026-07-17 14:48:00', '2026-07-17 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(129, 3, '2026-04-20 06:51:00', '2026-04-20 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(130, 3, '2026-04-21 10:48:00', '2026-04-21 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(131, 3, '2026-04-24 10:50:00', '2026-04-24 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(132, 3, '2026-04-25 06:54:00', '2026-04-25 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(133, 3, '2026-04-26 14:46:00', '2026-04-26 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(134, 3, '2026-04-27 06:48:00', '2026-04-27 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(135, 3, '2026-04-28 06:45:00', '2026-04-28 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(136, 3, '2026-04-29 10:48:00', '2026-04-29 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(137, 3, '2026-04-30 14:55:00', '2026-04-30 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(138, 3, '2026-05-01 14:49:00', '2026-05-01 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(139, 3, '2026-05-02 10:54:00', '2026-05-02 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(140, 3, '2026-05-03 14:45:00', '2026-05-03 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(141, 3, '2026-05-05 06:54:00', '2026-05-05 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(142, 3, '2026-05-06 14:54:00', '2026-05-06 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(143, 3, '2026-05-07 06:48:00', '2026-05-07 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(144, 3, '2026-05-09 06:52:00', '2026-05-09 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(145, 3, '2026-05-10 06:45:00', '2026-05-10 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(146, 3, '2026-05-11 15:00:00', '2026-05-12 00:58:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(147, 3, '2026-05-13 06:46:00', '2026-05-13 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(148, 3, '2026-05-14 10:46:00', '2026-05-14 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(149, 3, '2026-05-16 11:00:00', '2026-05-16 20:41:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(150, 3, '2026-05-17 15:00:00', '2026-05-18 00:22:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(151, 3, '2026-05-18 07:00:00', '2026-05-18 17:57:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(152, 3, '2026-05-19 10:50:00', '2026-05-19 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(153, 3, '2026-05-20 10:46:00', '2026-05-20 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(154, 3, '2026-05-21 10:46:00', '2026-05-21 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(155, 3, '2026-05-22 10:45:00', '2026-05-22 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(156, 3, '2026-05-23 06:55:00', '2026-05-23 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(157, 3, '2026-05-24 14:54:00', '2026-05-24 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(158, 3, '2026-05-25 10:45:00', '2026-05-25 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(159, 3, '2026-05-26 07:12:00', '2026-05-26 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:33', NULL),
(160, 3, '2026-05-27 06:53:00', '2026-05-27 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(161, 3, '2026-05-29 14:48:00', '2026-05-29 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(162, 3, '2026-05-31 14:49:00', '2026-05-31 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(163, 3, '2026-06-01 06:48:00', '2026-06-01 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(164, 3, '2026-06-02 10:51:00', '2026-06-02 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(165, 3, '2026-06-04 06:54:00', '2026-06-04 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(166, 3, '2026-06-05 14:53:00', '2026-06-05 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(167, 3, '2026-06-06 06:54:00', '2026-06-06 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(168, 3, '2026-06-07 14:54:00', '2026-06-07 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(169, 3, '2026-06-08 14:54:00', '2026-06-08 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(170, 3, '2026-06-09 10:46:00', '2026-06-09 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(171, 3, '2026-06-10 10:52:00', '2026-06-10 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(172, 3, '2026-06-11 11:00:00', '2026-06-11 20:45:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(173, 3, '2026-06-13 06:47:00', '2026-06-13 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(174, 3, '2026-06-15 10:47:00', '2026-06-15 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(175, 3, '2026-06-17 10:49:00', '2026-06-17 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(176, 3, '2026-06-18 10:53:00', '2026-06-18 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(177, 3, '2026-06-19 14:50:00', '2026-06-19 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(178, 3, '2026-06-20 10:45:00', '2026-06-20 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(179, 3, '2026-06-21 10:53:00', '2026-06-21 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(180, 3, '2026-06-22 10:51:00', '2026-06-22 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(181, 3, '2026-06-23 10:50:00', '2026-06-23 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(182, 3, '2026-06-24 15:30:00', '2026-06-24 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:33', NULL),
(183, 3, '2026-06-26 15:44:00', '2026-06-26 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:33', NULL),
(184, 3, '2026-06-29 10:51:00', '2026-06-29 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(185, 3, '2026-06-30 14:46:00', '2026-06-30 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(186, 3, '2026-07-01 14:45:00', '2026-07-01 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(187, 3, '2026-07-02 14:53:00', '2026-07-02 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(188, 3, '2026-07-05 06:47:00', '2026-07-05 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(189, 3, '2026-07-06 15:30:00', '2026-07-06 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:33', NULL),
(190, 3, '2026-07-07 14:53:00', '2026-07-07 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(191, 3, '2026-07-08 06:55:00', '2026-07-08 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(192, 3, '2026-07-11 14:52:00', '2026-07-11 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(193, 3, '2026-07-12 10:50:00', '2026-07-12 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(194, 3, '2026-07-14 10:45:00', '2026-07-14 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(195, 3, '2026-07-15 15:14:00', '2026-07-15 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:33', NULL),
(196, 3, '2026-07-17 06:55:00', '2026-07-17 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(197, 3, '2026-07-18 10:48:00', '2026-07-18 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(198, 3, '2026-07-19 14:52:00', '2026-07-19 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(199, 4, '2026-04-19 07:00:00', '2026-04-19 17:38:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(200, 4, '2026-04-21 10:54:00', '2026-04-21 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(201, 4, '2026-04-22 06:53:00', '2026-04-22 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(202, 4, '2026-04-23 10:55:00', '2026-04-23 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(203, 4, '2026-04-25 06:53:00', '2026-04-25 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(204, 4, '2026-04-27 10:47:00', '2026-04-27 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(205, 4, '2026-04-28 11:00:00', '2026-04-28 21:19:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(206, 4, '2026-04-29 06:47:00', '2026-04-29 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(207, 4, '2026-04-30 14:53:00', '2026-04-30 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(208, 4, '2026-05-03 10:53:00', '2026-05-03 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(209, 4, '2026-05-05 11:41:00', '2026-05-05 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:33', NULL),
(210, 4, '2026-05-07 06:46:00', '2026-05-07 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(211, 4, '2026-05-08 14:45:00', '2026-05-08 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(212, 4, '2026-05-09 14:47:00', '2026-05-09 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(213, 4, '2026-05-10 14:50:00', '2026-05-10 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(214, 4, '2026-05-12 10:51:00', '2026-05-12 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(215, 4, '2026-05-13 06:54:00', '2026-05-13 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(216, 4, '2026-05-14 10:47:00', '2026-05-14 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(217, 4, '2026-05-15 14:50:00', '2026-05-15 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(218, 4, '2026-05-16 06:45:00', '2026-05-16 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(219, 4, '2026-05-17 11:00:00', '2026-05-17 20:42:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(220, 4, '2026-05-19 06:52:00', '2026-05-19 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(221, 4, '2026-05-21 15:00:00', '2026-05-22 01:18:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(222, 4, '2026-05-22 07:23:00', '2026-05-22 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:33', NULL),
(223, 4, '2026-05-24 15:29:00', '2026-05-24 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:33', NULL),
(224, 4, '2026-05-25 10:45:00', '2026-05-25 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(225, 4, '2026-05-26 15:23:00', '2026-05-26 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:33', NULL),
(226, 4, '2026-05-28 15:41:00', '2026-05-28 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:33', NULL),
(227, 4, '2026-05-30 07:00:00', '2026-05-30 17:10:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(228, 4, '2026-06-01 06:48:00', '2026-06-01 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(229, 4, '2026-06-02 06:45:00', '2026-06-02 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(230, 4, '2026-06-03 06:53:00', '2026-06-03 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(231, 4, '2026-06-04 14:45:00', '2026-06-04 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(232, 4, '2026-06-05 14:49:00', '2026-06-05 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(233, 4, '2026-06-06 06:53:00', '2026-06-06 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(234, 4, '2026-06-08 06:53:00', '2026-06-08 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:33', NULL),
(235, 4, '2026-06-10 10:54:00', '2026-06-10 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(236, 4, '2026-06-11 14:50:00', '2026-06-11 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(237, 4, '2026-06-12 14:52:00', '2026-06-12 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(238, 4, '2026-06-13 06:51:00', '2026-06-13 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(239, 4, '2026-06-14 10:47:00', '2026-06-14 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(240, 4, '2026-06-16 11:13:00', '2026-06-16 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(241, 4, '2026-06-17 14:48:00', '2026-06-17 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(242, 4, '2026-06-18 14:50:00', '2026-06-18 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(243, 4, '2026-06-19 10:49:00', '2026-06-19 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(244, 4, '2026-06-20 10:54:00', '2026-06-20 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(245, 4, '2026-06-21 14:54:00', '2026-06-21 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(246, 4, '2026-06-22 14:52:00', '2026-06-22 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(247, 4, '2026-06-23 15:21:00', '2026-06-23 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(248, 4, '2026-06-25 15:00:00', '2026-06-26 01:54:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(249, 4, '2026-06-26 15:00:00', '2026-06-27 01:50:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(250, 4, '2026-06-28 11:00:00', '2026-06-28 20:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(251, 4, '2026-06-30 14:48:00', '2026-06-30 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(252, 4, '2026-07-02 14:49:00', '2026-07-02 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(253, 4, '2026-07-03 06:50:00', '2026-07-03 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(254, 4, '2026-07-05 06:52:00', '2026-07-05 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(255, 4, '2026-07-06 06:50:00', '2026-07-06 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(256, 4, '2026-07-07 10:51:00', '2026-07-07 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(257, 4, '2026-07-10 10:50:00', '2026-07-10 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(258, 4, '2026-07-11 10:49:00', '2026-07-11 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(259, 4, '2026-07-12 07:00:00', '2026-07-12 17:19:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(260, 4, '2026-07-13 14:51:00', '2026-07-13 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(261, 4, '2026-07-14 14:55:00', '2026-07-14 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(262, 4, '2026-07-16 06:52:00', '2026-07-16 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(263, 4, '2026-07-19 14:48:00', '2026-07-19 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(264, 5, '2026-04-23 07:00:00', '2026-04-23 16:40:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(265, 5, '2026-04-25 06:53:00', '2026-04-25 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(266, 5, '2026-04-26 11:24:00', '2026-04-26 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(267, 5, '2026-04-27 14:55:00', '2026-04-27 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(268, 5, '2026-04-28 06:54:00', '2026-04-28 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(269, 5, '2026-04-29 15:00:00', '2026-04-30 00:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(270, 5, '2026-05-01 11:18:00', '2026-05-01 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(271, 5, '2026-05-02 06:52:00', '2026-05-02 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(272, 5, '2026-05-05 06:48:00', '2026-05-05 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(273, 5, '2026-05-08 06:54:00', '2026-05-08 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(274, 5, '2026-05-09 06:48:00', '2026-05-09 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(275, 5, '2026-05-11 14:52:00', '2026-05-11 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(276, 5, '2026-05-12 06:45:00', '2026-05-12 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(277, 5, '2026-05-14 11:00:00', '2026-05-14 21:15:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(278, 5, '2026-05-15 14:48:00', '2026-05-15 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(279, 5, '2026-05-17 07:00:00', '2026-05-17 16:39:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(280, 5, '2026-05-18 10:45:00', '2026-05-18 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(281, 5, '2026-05-23 15:23:00', '2026-05-23 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(282, 5, '2026-05-24 06:51:00', '2026-05-24 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(283, 5, '2026-05-26 10:52:00', '2026-05-26 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(284, 5, '2026-05-28 06:55:00', '2026-05-28 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(285, 5, '2026-05-29 14:52:00', '2026-05-29 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(286, 5, '2026-05-30 14:52:00', '2026-05-30 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(287, 5, '2026-05-31 07:22:00', '2026-05-31 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(288, 5, '2026-06-02 10:54:00', '2026-06-02 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(289, 5, '2026-06-03 10:48:00', '2026-06-03 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(290, 5, '2026-06-04 06:46:00', '2026-06-04 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(291, 5, '2026-06-05 06:51:00', '2026-06-05 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(292, 5, '2026-06-09 10:53:00', '2026-06-09 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(293, 5, '2026-06-10 14:50:00', '2026-06-10 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(294, 5, '2026-06-11 14:53:00', '2026-06-11 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(295, 5, '2026-06-12 06:45:00', '2026-06-12 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(296, 5, '2026-06-13 06:55:00', '2026-06-13 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(297, 5, '2026-06-15 06:47:00', '2026-06-15 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(298, 5, '2026-06-17 14:52:00', '2026-06-17 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(299, 5, '2026-06-19 14:48:00', '2026-06-19 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(300, 5, '2026-06-21 14:53:00', '2026-06-21 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(301, 5, '2026-06-22 06:47:00', '2026-06-22 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(302, 5, '2026-06-23 11:00:00', '2026-06-23 20:15:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(303, 5, '2026-06-25 10:46:00', '2026-06-25 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(304, 5, '2026-06-27 14:50:00', '2026-06-27 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(305, 5, '2026-06-28 10:53:00', '2026-06-28 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(306, 5, '2026-06-30 10:55:00', '2026-06-30 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(307, 5, '2026-07-01 10:54:00', '2026-07-01 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(308, 5, '2026-07-02 14:48:00', '2026-07-02 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(309, 5, '2026-07-03 14:48:00', '2026-07-03 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(310, 5, '2026-07-05 07:00:00', '2026-07-05 16:43:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(311, 5, '2026-07-06 15:34:00', '2026-07-06 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(312, 5, '2026-07-07 06:52:00', '2026-07-07 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(313, 5, '2026-07-09 14:54:00', '2026-07-09 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(314, 5, '2026-07-10 10:55:00', '2026-07-10 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(315, 5, '2026-07-11 14:54:00', '2026-07-11 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(316, 5, '2026-07-13 06:47:00', '2026-07-13 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(317, 5, '2026-07-14 06:52:00', '2026-07-14 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(318, 5, '2026-07-15 10:45:00', '2026-07-15 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(319, 5, '2026-07-18 14:52:00', '2026-07-18 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(320, 6, '2026-04-21 15:00:00', '2026-04-22 01:16:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(321, 6, '2026-04-22 14:46:00', '2026-04-22 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(322, 6, '2026-04-23 06:52:00', '2026-04-23 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(323, 6, '2026-04-24 15:00:00', '2026-04-25 01:58:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(324, 6, '2026-04-25 06:49:00', '2026-04-25 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(325, 6, '2026-04-26 06:53:00', '2026-04-26 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(326, 6, '2026-04-27 10:50:00', '2026-04-27 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(327, 6, '2026-04-28 14:49:00', '2026-04-28 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(328, 6, '2026-04-29 06:52:00', '2026-04-29 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(329, 6, '2026-04-30 06:53:00', '2026-04-30 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(330, 6, '2026-05-02 06:50:00', '2026-05-02 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(331, 6, '2026-05-05 06:45:00', '2026-05-05 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(332, 6, '2026-05-06 11:00:00', '2026-05-06 20:34:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(333, 6, '2026-05-07 11:32:00', '2026-05-07 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(334, 6, '2026-05-08 06:53:00', '2026-05-08 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(335, 6, '2026-05-09 06:55:00', '2026-05-09 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(336, 6, '2026-05-10 06:46:00', '2026-05-10 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(337, 6, '2026-05-11 10:55:00', '2026-05-11 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(338, 6, '2026-05-12 11:00:00', '2026-05-12 21:30:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(339, 6, '2026-05-13 06:55:00', '2026-05-13 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(340, 6, '2026-05-14 14:53:00', '2026-05-14 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(341, 6, '2026-05-15 10:49:00', '2026-05-15 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(342, 6, '2026-05-17 06:54:00', '2026-05-17 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(343, 6, '2026-05-18 06:48:00', '2026-05-18 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(344, 6, '2026-05-19 10:51:00', '2026-05-19 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(345, 6, '2026-05-21 14:46:00', '2026-05-21 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(346, 6, '2026-05-23 14:51:00', '2026-05-23 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(347, 6, '2026-05-24 14:49:00', '2026-05-24 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(348, 6, '2026-05-25 06:55:00', '2026-05-25 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(349, 6, '2026-05-26 14:45:00', '2026-05-26 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(350, 6, '2026-05-27 14:50:00', '2026-05-27 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(351, 6, '2026-05-29 14:47:00', '2026-05-29 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(352, 6, '2026-05-31 10:54:00', '2026-05-31 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(353, 6, '2026-06-02 14:50:00', '2026-06-02 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(354, 6, '2026-06-04 06:45:00', '2026-06-04 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(355, 6, '2026-06-06 15:00:00', '2026-06-07 00:09:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(356, 6, '2026-06-09 14:46:00', '2026-06-09 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(357, 6, '2026-06-12 15:28:00', '2026-06-12 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(358, 6, '2026-06-13 10:55:00', '2026-06-13 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(359, 6, '2026-06-14 10:45:00', '2026-06-14 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(360, 6, '2026-06-15 14:55:00', '2026-06-15 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(361, 6, '2026-06-16 10:49:00', '2026-06-16 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(362, 6, '2026-06-17 14:53:00', '2026-06-17 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(363, 6, '2026-06-18 14:48:00', '2026-06-18 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(364, 6, '2026-06-20 06:50:00', '2026-06-20 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(365, 6, '2026-06-21 10:49:00', '2026-06-21 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(366, 6, '2026-06-22 14:51:00', '2026-06-22 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(367, 6, '2026-06-23 06:48:00', '2026-06-23 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(368, 6, '2026-06-24 06:45:00', '2026-06-24 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(369, 6, '2026-06-25 07:00:00', '2026-06-25 16:20:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(370, 6, '2026-06-27 06:48:00', '2026-06-27 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(371, 6, '2026-06-28 10:52:00', '2026-06-28 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(372, 6, '2026-06-29 10:51:00', '2026-06-29 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(373, 6, '2026-06-30 10:46:00', '2026-06-30 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(374, 6, '2026-07-02 15:00:00', '2026-07-03 00:08:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(375, 6, '2026-07-03 07:00:00', '2026-07-03 17:40:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(376, 6, '2026-07-04 10:54:00', '2026-07-04 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(377, 6, '2026-07-06 07:00:00', '2026-07-06 17:29:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(378, 6, '2026-07-08 14:55:00', '2026-07-08 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(379, 6, '2026-07-09 06:51:00', '2026-07-09 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(380, 6, '2026-07-10 10:45:00', '2026-07-10 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(381, 6, '2026-07-12 06:53:00', '2026-07-12 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(382, 6, '2026-07-13 10:45:00', '2026-07-13 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(383, 6, '2026-07-15 14:52:00', '2026-07-15 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(384, 6, '2026-07-17 10:51:00', '2026-07-17 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(385, 6, '2026-07-18 14:45:00', '2026-07-18 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(386, 6, '2026-07-19 14:48:00', '2026-07-19 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(387, 7, '2026-04-21 10:45:00', '2026-04-21 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(388, 7, '2026-04-22 11:00:00', '2026-04-22 21:40:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(389, 7, '2026-04-23 06:54:00', '2026-04-23 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(390, 7, '2026-04-24 10:45:00', '2026-04-24 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(391, 7, '2026-04-25 14:53:00', '2026-04-25 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(392, 7, '2026-04-26 14:49:00', '2026-04-26 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(393, 7, '2026-04-27 15:00:00', '2026-04-28 01:38:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(394, 7, '2026-04-28 14:46:00', '2026-04-28 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(395, 7, '2026-04-29 10:54:00', '2026-04-29 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(396, 7, '2026-04-30 14:45:00', '2026-04-30 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(397, 7, '2026-05-01 14:54:00', '2026-05-01 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(398, 7, '2026-05-04 06:48:00', '2026-05-04 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(399, 7, '2026-05-06 10:52:00', '2026-05-06 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(400, 7, '2026-05-07 06:52:00', '2026-05-07 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(401, 7, '2026-05-08 06:47:00', '2026-05-08 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(402, 7, '2026-05-09 14:50:00', '2026-05-09 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(403, 7, '2026-05-11 06:48:00', '2026-05-11 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(404, 7, '2026-05-13 10:55:00', '2026-05-13 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(405, 7, '2026-05-14 06:50:00', '2026-05-14 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(406, 7, '2026-05-15 06:50:00', '2026-05-15 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(407, 7, '2026-05-18 06:52:00', '2026-05-18 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL);
INSERT INTO `attendance` (`id`, `employee_id`, `time_in`, `time_out`, `break_in`, `break_out`, `is_late`, `overtime_hours`, `undertime_hours`, `status`, `created_at`, `updated_at`) VALUES
(408, 7, '2026-05-20 10:50:00', '2026-05-20 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(409, 7, '2026-05-21 15:15:00', '2026-05-21 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(410, 7, '2026-05-23 14:53:00', '2026-05-23 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(411, 7, '2026-05-24 14:53:00', '2026-05-24 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(412, 7, '2026-05-25 10:55:00', '2026-05-25 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(413, 7, '2026-05-26 15:26:00', '2026-05-26 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(414, 7, '2026-05-27 10:46:00', '2026-05-27 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(415, 7, '2026-05-29 14:48:00', '2026-05-29 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(416, 7, '2026-05-30 10:48:00', '2026-05-30 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(417, 7, '2026-05-31 06:52:00', '2026-05-31 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(418, 7, '2026-06-02 06:53:00', '2026-06-02 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(419, 7, '2026-06-03 10:47:00', '2026-06-03 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(420, 7, '2026-06-05 07:41:00', '2026-06-05 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(421, 7, '2026-06-06 10:49:00', '2026-06-06 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(422, 7, '2026-06-07 14:53:00', '2026-06-07 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(423, 7, '2026-06-08 06:47:00', '2026-06-08 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(424, 7, '2026-06-10 14:50:00', '2026-06-10 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(425, 7, '2026-06-11 10:46:00', '2026-06-11 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(426, 7, '2026-06-13 10:52:00', '2026-06-13 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(427, 7, '2026-06-14 10:47:00', '2026-06-14 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(428, 7, '2026-06-15 06:49:00', '2026-06-15 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(429, 7, '2026-06-16 14:48:00', '2026-06-16 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(430, 7, '2026-06-17 06:46:00', '2026-06-17 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(431, 7, '2026-06-18 14:49:00', '2026-06-18 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(432, 7, '2026-06-19 14:45:00', '2026-06-19 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(433, 7, '2026-06-20 11:00:00', '2026-06-20 20:26:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(434, 7, '2026-06-21 14:51:00', '2026-06-21 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(435, 7, '2026-06-22 06:53:00', '2026-06-22 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(436, 7, '2026-06-23 14:54:00', '2026-06-23 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(437, 7, '2026-06-24 11:38:00', '2026-06-24 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(438, 7, '2026-06-25 14:49:00', '2026-06-25 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(439, 7, '2026-06-26 06:47:00', '2026-06-26 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(440, 7, '2026-06-27 14:54:00', '2026-06-27 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(441, 7, '2026-06-28 14:55:00', '2026-06-28 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(442, 7, '2026-06-29 14:49:00', '2026-06-29 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(443, 7, '2026-06-30 06:51:00', '2026-06-30 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(444, 7, '2026-07-01 06:46:00', '2026-07-01 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(445, 7, '2026-07-02 14:48:00', '2026-07-02 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(446, 7, '2026-07-04 10:54:00', '2026-07-04 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(447, 7, '2026-07-05 15:00:00', '2026-07-06 00:58:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(448, 7, '2026-07-06 07:10:00', '2026-07-06 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(449, 7, '2026-07-10 06:47:00', '2026-07-10 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(450, 7, '2026-07-11 07:36:00', '2026-07-11 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(451, 7, '2026-07-12 14:53:00', '2026-07-12 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(452, 7, '2026-07-13 14:51:00', '2026-07-13 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(453, 7, '2026-07-15 15:42:00', '2026-07-15 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(454, 7, '2026-07-16 06:54:00', '2026-07-16 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(455, 7, '2026-07-17 10:52:00', '2026-07-17 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(456, 7, '2026-07-18 07:00:00', '2026-07-18 17:45:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(457, 8, '2026-04-21 10:54:00', '2026-04-21 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(458, 8, '2026-04-23 15:14:00', '2026-04-23 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(459, 8, '2026-04-24 14:50:00', '2026-04-24 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(460, 8, '2026-04-26 14:55:00', '2026-04-26 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(461, 8, '2026-04-27 06:55:00', '2026-04-27 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(462, 8, '2026-04-28 14:47:00', '2026-04-28 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(463, 8, '2026-04-30 06:49:00', '2026-04-30 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(464, 8, '2026-05-01 06:49:00', '2026-05-01 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(465, 8, '2026-05-05 10:45:00', '2026-05-05 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(466, 8, '2026-05-07 06:50:00', '2026-05-07 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(467, 8, '2026-05-11 06:53:00', '2026-05-11 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(468, 8, '2026-05-12 14:48:00', '2026-05-12 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(469, 8, '2026-05-13 14:51:00', '2026-05-13 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(470, 8, '2026-05-14 14:51:00', '2026-05-14 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(471, 8, '2026-05-15 14:49:00', '2026-05-15 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(472, 8, '2026-05-16 06:50:00', '2026-05-16 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(473, 8, '2026-05-17 11:00:00', '2026-05-17 21:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(474, 8, '2026-05-19 14:48:00', '2026-05-19 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(475, 8, '2026-05-21 06:46:00', '2026-05-21 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(476, 8, '2026-05-22 10:54:00', '2026-05-22 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(477, 8, '2026-05-23 10:52:00', '2026-05-23 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(478, 8, '2026-05-24 15:31:00', '2026-05-24 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(479, 8, '2026-05-25 06:54:00', '2026-05-25 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(480, 8, '2026-05-26 14:51:00', '2026-05-26 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(481, 8, '2026-05-27 07:27:00', '2026-05-27 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(482, 8, '2026-05-28 06:51:00', '2026-05-28 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(483, 8, '2026-05-29 14:52:00', '2026-05-29 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(484, 8, '2026-05-31 06:50:00', '2026-05-31 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(485, 8, '2026-06-01 10:52:00', '2026-06-01 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(486, 8, '2026-06-04 07:25:00', '2026-06-04 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(487, 8, '2026-06-05 07:00:00', '2026-06-05 17:13:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(488, 8, '2026-06-06 10:47:00', '2026-06-06 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(489, 8, '2026-06-07 10:47:00', '2026-06-07 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(490, 8, '2026-06-08 10:47:00', '2026-06-08 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(491, 8, '2026-06-10 15:00:00', '2026-06-11 00:17:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(492, 8, '2026-06-12 14:46:00', '2026-06-12 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(493, 8, '2026-06-13 14:49:00', '2026-06-13 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(494, 8, '2026-06-14 14:53:00', '2026-06-14 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(495, 8, '2026-06-15 06:46:00', '2026-06-15 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(496, 8, '2026-06-17 14:54:00', '2026-06-17 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(497, 8, '2026-06-19 06:51:00', '2026-06-19 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(498, 8, '2026-06-20 14:52:00', '2026-06-20 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(499, 8, '2026-06-21 06:53:00', '2026-06-21 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(500, 8, '2026-06-23 06:51:00', '2026-06-23 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(501, 8, '2026-06-24 10:48:00', '2026-06-24 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(502, 8, '2026-06-28 14:49:00', '2026-06-28 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(503, 8, '2026-06-29 06:50:00', '2026-06-29 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(504, 8, '2026-06-30 14:53:00', '2026-06-30 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(505, 8, '2026-07-02 14:53:00', '2026-07-02 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(506, 8, '2026-07-04 14:45:00', '2026-07-04 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(507, 8, '2026-07-05 10:52:00', '2026-07-05 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(508, 8, '2026-07-06 06:45:00', '2026-07-06 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(509, 8, '2026-07-07 14:47:00', '2026-07-07 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(510, 8, '2026-07-08 14:52:00', '2026-07-08 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(511, 8, '2026-07-09 10:53:00', '2026-07-09 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(512, 8, '2026-07-10 10:52:00', '2026-07-10 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(513, 8, '2026-07-11 06:51:00', '2026-07-11 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(514, 8, '2026-07-12 14:52:00', '2026-07-12 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(515, 8, '2026-07-13 06:48:00', '2026-07-13 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(516, 8, '2026-07-14 14:51:00', '2026-07-14 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(517, 8, '2026-07-15 14:47:00', '2026-07-15 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(518, 8, '2026-07-16 14:45:00', '2026-07-16 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(519, 8, '2026-07-17 10:53:00', '2026-07-17 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(520, 8, '2026-07-18 14:51:00', '2026-07-18 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(521, 8, '2026-07-19 14:49:00', '2026-07-19 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(522, 9, '2026-04-16 14:45:00', '2026-04-16 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(523, 9, '2026-04-17 10:48:00', '2026-04-17 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(524, 9, '2026-04-18 15:00:00', '2026-04-19 01:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(525, 9, '2026-04-19 10:53:00', '2026-04-19 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(526, 9, '2026-04-21 15:00:00', '2026-04-22 01:24:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(527, 9, '2026-04-22 14:49:00', '2026-04-22 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(528, 9, '2026-04-23 14:55:00', '2026-04-23 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(529, 9, '2026-04-24 06:50:00', '2026-04-24 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(530, 9, '2026-04-25 06:45:00', '2026-04-25 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(531, 9, '2026-04-27 14:55:00', '2026-04-27 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(532, 9, '2026-04-28 06:46:00', '2026-04-28 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(533, 9, '2026-04-29 06:52:00', '2026-04-29 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(534, 9, '2026-05-03 10:45:00', '2026-05-03 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(535, 9, '2026-05-05 15:00:00', '2026-05-06 01:30:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(536, 9, '2026-05-08 06:48:00', '2026-05-08 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(537, 9, '2026-05-09 06:55:00', '2026-05-09 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(538, 9, '2026-05-10 06:48:00', '2026-05-10 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(539, 9, '2026-05-11 15:00:00', '2026-05-12 00:07:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(540, 9, '2026-05-12 06:52:00', '2026-05-12 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(541, 9, '2026-05-13 14:47:00', '2026-05-13 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(542, 9, '2026-05-14 14:55:00', '2026-05-14 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(543, 9, '2026-05-15 06:55:00', '2026-05-15 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(544, 9, '2026-05-16 14:53:00', '2026-05-16 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(545, 9, '2026-05-17 15:11:00', '2026-05-17 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(546, 9, '2026-05-19 06:50:00', '2026-05-19 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(547, 9, '2026-05-20 10:54:00', '2026-05-20 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(548, 9, '2026-05-22 11:00:00', '2026-05-22 20:49:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(549, 9, '2026-05-23 15:24:00', '2026-05-23 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(550, 9, '2026-05-24 14:51:00', '2026-05-24 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(551, 9, '2026-05-25 14:46:00', '2026-05-25 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(552, 9, '2026-05-26 06:55:00', '2026-05-26 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(553, 9, '2026-05-28 15:00:00', '2026-05-29 00:30:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(554, 9, '2026-05-29 10:54:00', '2026-05-29 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(555, 9, '2026-05-31 15:00:00', '2026-06-01 01:55:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(556, 9, '2026-06-01 10:55:00', '2026-06-01 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(557, 9, '2026-06-02 07:19:00', '2026-06-02 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(558, 9, '2026-06-03 07:00:00', '2026-06-03 17:50:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(559, 9, '2026-06-04 10:51:00', '2026-06-04 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(560, 9, '2026-06-05 07:00:00', '2026-06-05 16:24:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(561, 9, '2026-06-06 10:48:00', '2026-06-06 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(562, 9, '2026-06-07 14:47:00', '2026-06-07 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(563, 9, '2026-06-08 06:46:00', '2026-06-08 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(564, 9, '2026-06-09 10:52:00', '2026-06-09 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(565, 9, '2026-06-10 10:48:00', '2026-06-10 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(566, 9, '2026-06-11 06:50:00', '2026-06-11 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(567, 9, '2026-06-12 14:46:00', '2026-06-12 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(568, 9, '2026-06-13 10:48:00', '2026-06-13 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(569, 9, '2026-06-14 14:50:00', '2026-06-14 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(570, 9, '2026-06-15 06:53:00', '2026-06-15 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(571, 9, '2026-06-16 06:50:00', '2026-06-16 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(572, 9, '2026-06-17 06:49:00', '2026-06-17 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(573, 9, '2026-06-18 11:00:00', '2026-06-18 20:34:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(574, 9, '2026-06-21 14:47:00', '2026-06-21 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(575, 9, '2026-06-22 10:49:00', '2026-06-22 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(576, 9, '2026-06-23 14:48:00', '2026-06-23 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(577, 9, '2026-06-24 11:00:00', '2026-06-24 20:34:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(578, 9, '2026-06-25 06:48:00', '2026-06-25 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(579, 9, '2026-06-26 06:52:00', '2026-06-26 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(580, 9, '2026-06-27 14:46:00', '2026-06-27 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(581, 9, '2026-06-28 15:00:00', '2026-06-29 00:17:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(582, 9, '2026-06-29 10:54:00', '2026-06-29 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(583, 9, '2026-06-30 14:51:00', '2026-06-30 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(584, 9, '2026-07-01 10:54:00', '2026-07-01 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(585, 9, '2026-07-02 10:47:00', '2026-07-02 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(586, 9, '2026-07-03 14:51:00', '2026-07-03 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(587, 9, '2026-07-05 14:50:00', '2026-07-05 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(588, 9, '2026-07-06 15:22:00', '2026-07-06 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(589, 9, '2026-07-07 10:52:00', '2026-07-07 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(590, 9, '2026-07-08 10:50:00', '2026-07-08 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(591, 9, '2026-07-09 06:55:00', '2026-07-09 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(592, 9, '2026-07-10 06:50:00', '2026-07-10 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(593, 9, '2026-07-12 14:52:00', '2026-07-12 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(594, 9, '2026-07-13 14:55:00', '2026-07-13 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(595, 9, '2026-07-14 10:49:00', '2026-07-14 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(596, 9, '2026-07-16 11:26:00', '2026-07-16 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(597, 9, '2026-07-17 10:45:00', '2026-07-17 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(598, 9, '2026-07-19 14:47:00', '2026-07-19 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(599, 10, '2026-04-16 10:51:00', '2026-04-16 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(600, 10, '2026-04-17 14:49:00', '2026-04-17 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(601, 10, '2026-04-18 10:46:00', '2026-04-18 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(602, 10, '2026-04-19 10:50:00', '2026-04-19 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(603, 10, '2026-04-20 15:20:00', '2026-04-20 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(604, 10, '2026-04-21 14:55:00', '2026-04-21 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(605, 10, '2026-04-22 14:45:00', '2026-04-22 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(606, 10, '2026-04-24 10:50:00', '2026-04-24 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(607, 10, '2026-04-27 10:55:00', '2026-04-27 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(608, 10, '2026-04-28 14:54:00', '2026-04-28 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(609, 10, '2026-04-29 15:40:00', '2026-04-29 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(610, 10, '2026-04-30 06:55:00', '2026-04-30 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(611, 10, '2026-05-03 06:46:00', '2026-05-03 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(612, 10, '2026-05-04 10:48:00', '2026-05-04 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(613, 10, '2026-05-05 10:51:00', '2026-05-05 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(614, 10, '2026-05-06 14:47:00', '2026-05-06 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(615, 10, '2026-05-09 11:00:00', '2026-05-09 21:16:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(616, 10, '2026-05-10 14:53:00', '2026-05-10 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(617, 10, '2026-05-12 10:45:00', '2026-05-12 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(618, 10, '2026-05-13 14:47:00', '2026-05-13 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(619, 10, '2026-05-15 07:00:00', '2026-05-15 16:55:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(620, 10, '2026-05-16 14:54:00', '2026-05-16 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(621, 10, '2026-05-17 11:11:00', '2026-05-17 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(622, 10, '2026-05-18 06:54:00', '2026-05-18 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(623, 10, '2026-05-19 10:48:00', '2026-05-19 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(624, 10, '2026-05-21 10:45:00', '2026-05-21 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(625, 10, '2026-05-22 10:46:00', '2026-05-22 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(626, 10, '2026-05-23 10:54:00', '2026-05-23 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(627, 10, '2026-05-24 14:55:00', '2026-05-24 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(628, 10, '2026-05-25 14:54:00', '2026-05-25 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(629, 10, '2026-05-26 06:55:00', '2026-05-26 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(630, 10, '2026-05-28 11:17:00', '2026-05-28 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:34', NULL),
(631, 10, '2026-05-30 06:52:00', '2026-05-30 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(632, 10, '2026-05-31 14:46:00', '2026-05-31 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(633, 10, '2026-06-01 06:45:00', '2026-06-01 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(634, 10, '2026-06-04 14:55:00', '2026-06-04 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(635, 10, '2026-06-05 10:49:00', '2026-06-05 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(636, 10, '2026-06-06 10:48:00', '2026-06-06 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(637, 10, '2026-06-07 06:48:00', '2026-06-07 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(638, 10, '2026-06-08 10:49:00', '2026-06-08 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(639, 10, '2026-06-09 06:51:00', '2026-06-09 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(640, 10, '2026-06-10 14:55:00', '2026-06-10 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(641, 10, '2026-06-12 10:55:00', '2026-06-12 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(642, 10, '2026-06-13 14:54:00', '2026-06-13 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(643, 10, '2026-06-15 10:46:00', '2026-06-15 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(644, 10, '2026-06-16 06:47:00', '2026-06-16 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(645, 10, '2026-06-18 10:48:00', '2026-06-18 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:34', NULL),
(646, 10, '2026-06-19 07:00:00', '2026-06-19 16:33:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(647, 10, '2026-06-20 06:48:00', '2026-06-20 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(648, 10, '2026-06-21 06:48:00', '2026-06-21 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(649, 10, '2026-06-22 10:53:00', '2026-06-22 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(650, 10, '2026-06-23 14:50:00', '2026-06-23 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(651, 10, '2026-06-24 06:52:00', '2026-06-24 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(652, 10, '2026-06-25 14:46:00', '2026-06-25 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(653, 10, '2026-06-27 15:00:00', '2026-06-28 01:35:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(654, 10, '2026-06-28 10:46:00', '2026-06-28 19:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(655, 10, '2026-06-29 10:47:00', '2026-06-29 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(656, 10, '2026-06-30 06:49:00', '2026-06-30 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(657, 10, '2026-07-03 10:55:00', '2026-07-03 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(658, 10, '2026-07-04 06:48:00', '2026-07-04 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(659, 10, '2026-07-05 14:50:00', '2026-07-05 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(660, 10, '2026-07-07 10:55:00', '2026-07-07 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(661, 10, '2026-07-08 10:54:00', '2026-07-08 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(662, 10, '2026-07-09 11:00:00', '2026-07-09 21:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(663, 10, '2026-07-11 14:55:00', '2026-07-11 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(664, 10, '2026-07-12 10:49:00', '2026-07-12 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(665, 10, '2026-07-14 10:50:00', '2026-07-14 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(666, 10, '2026-07-15 07:39:00', '2026-07-15 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:35', NULL),
(667, 10, '2026-07-16 15:24:00', '2026-07-16 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:35', NULL),
(668, 10, '2026-07-17 14:45:00', '2026-07-17 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(669, 10, '2026-07-19 10:46:00', '2026-07-19 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(670, 11, '2026-07-12 15:00:00', '2026-07-13 00:53:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(671, 11, '2026-07-13 06:45:00', '2026-07-13 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(672, 11, '2026-07-15 07:00:00', '2026-07-15 17:50:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(673, 11, '2026-07-16 14:47:00', '2026-07-16 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(674, 11, '2026-07-17 07:00:00', '2026-07-17 17:06:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(675, 11, '2026-07-19 07:00:00', '2026-07-19 16:31:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(676, 12, '2026-07-08 06:46:00', '2026-07-08 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(677, 12, '2026-07-09 14:53:00', '2026-07-09 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(678, 12, '2026-07-10 06:47:00', '2026-07-10 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(679, 12, '2026-07-11 14:45:00', '2026-07-11 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(680, 12, '2026-07-12 10:54:00', '2026-07-12 19:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(681, 12, '2026-07-14 10:45:00', '2026-07-14 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(682, 12, '2026-07-15 11:00:00', '2026-07-15 21:19:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(683, 12, '2026-07-17 10:53:00', '2026-07-17 19:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(684, 12, '2026-07-18 06:53:00', '2026-07-18 15:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(685, 12, '2026-07-19 14:50:00', '2026-07-19 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(686, 13, '2026-07-01 14:52:00', '2026-07-01 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(687, 13, '2026-07-02 11:29:00', '2026-07-02 19:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:35', NULL),
(688, 13, '2026-07-03 06:48:00', '2026-07-03 15:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(689, 13, '2026-07-04 14:54:00', '2026-07-04 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(690, 13, '2026-07-06 06:49:00', '2026-07-06 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(691, 13, '2026-07-07 14:46:00', '2026-07-07 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(692, 13, '2026-07-08 06:54:00', '2026-07-08 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(693, 13, '2026-07-09 06:55:00', '2026-07-09 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(694, 13, '2026-07-10 06:47:00', '2026-07-10 15:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(695, 13, '2026-07-11 07:00:00', '2026-07-11 16:57:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(696, 13, '2026-07-12 06:51:00', '2026-07-12 15:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(697, 13, '2026-07-13 10:47:00', '2026-07-13 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(698, 13, '2026-07-14 06:49:00', '2026-07-14 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(699, 13, '2026-07-15 10:55:00', '2026-07-15 19:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(700, 13, '2026-07-16 14:54:00', '2026-07-16 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(701, 13, '2026-07-18 10:50:00', '2026-07-18 19:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(702, 13, '2026-07-19 06:54:00', '2026-07-19 15:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(703, 14, '2026-04-24 10:51:00', '2026-04-24 17:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(704, 14, '2026-04-26 16:48:00', '2026-04-26 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(705, 14, '2026-04-27 17:11:00', '2026-04-27 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:35', NULL),
(706, 14, '2026-04-29 16:50:00', '2026-04-29 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(707, 14, '2026-04-30 10:51:00', '2026-04-30 17:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(708, 14, '2026-05-01 06:55:00', '2026-05-01 13:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(709, 14, '2026-05-02 06:55:00', '2026-05-02 13:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(710, 14, '2026-05-04 10:49:00', '2026-05-04 17:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(711, 14, '2026-05-05 16:45:00', '2026-05-05 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(712, 14, '2026-05-07 16:45:00', '2026-05-07 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(713, 14, '2026-05-12 11:17:00', '2026-05-12 17:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:35', NULL),
(714, 14, '2026-05-13 06:51:00', '2026-05-13 13:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(715, 14, '2026-05-18 11:15:00', '2026-05-18 17:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:35', NULL),
(716, 14, '2026-05-20 10:47:00', '2026-05-20 17:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(717, 14, '2026-05-25 16:53:00', '2026-05-25 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(718, 14, '2026-05-29 06:48:00', '2026-05-29 13:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(719, 14, '2026-06-01 06:54:00', '2026-06-01 13:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(720, 14, '2026-06-03 10:47:00', '2026-06-03 17:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(721, 14, '2026-06-05 06:46:00', '2026-06-05 13:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(722, 14, '2026-06-06 06:53:00', '2026-06-06 13:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(723, 14, '2026-06-07 06:52:00', '2026-06-07 13:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(724, 14, '2026-06-08 10:55:00', '2026-06-08 17:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(725, 14, '2026-06-10 06:55:00', '2026-06-10 13:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(726, 14, '2026-06-11 10:49:00', '2026-06-11 17:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(727, 14, '2026-06-12 10:49:00', '2026-06-12 17:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(728, 14, '2026-06-13 16:45:00', '2026-06-13 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(729, 14, '2026-06-20 06:53:00', '2026-06-20 13:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(730, 14, '2026-06-21 06:48:00', '2026-06-21 13:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(731, 14, '2026-06-22 10:52:00', '2026-06-22 17:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(732, 14, '2026-06-23 16:48:00', '2026-06-23 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(733, 14, '2026-06-29 16:46:00', '2026-06-29 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(734, 14, '2026-06-30 10:52:00', '2026-06-30 17:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(735, 14, '2026-07-01 16:53:00', '2026-07-01 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(736, 14, '2026-07-04 06:54:00', '2026-07-04 13:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(737, 14, '2026-07-05 16:53:00', '2026-07-05 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(738, 14, '2026-07-10 16:52:00', '2026-07-10 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(739, 14, '2026-07-11 06:55:00', '2026-07-11 13:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(740, 14, '2026-07-18 06:54:00', '2026-07-18 13:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(741, 15, '2026-04-18 11:00:00', '2026-04-18 18:40:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(742, 15, '2026-04-22 17:32:00', '2026-04-22 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:35', NULL),
(743, 15, '2026-04-29 06:45:00', '2026-04-29 13:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(744, 15, '2026-05-05 11:00:00', '2026-05-05 19:56:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(745, 15, '2026-05-06 10:47:00', '2026-05-06 17:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(746, 15, '2026-05-07 10:51:00', '2026-05-07 17:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(747, 15, '2026-05-12 16:52:00', '2026-05-12 23:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(748, 15, '2026-05-14 16:47:00', '2026-05-14 23:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(749, 15, '2026-05-15 06:47:00', '2026-05-15 13:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(750, 15, '2026-05-16 06:46:00', '2026-05-16 13:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(751, 15, '2026-05-19 06:45:00', '2026-05-19 13:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(752, 15, '2026-05-20 10:46:00', '2026-05-20 17:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(753, 15, '2026-05-22 10:45:00', '2026-05-22 17:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(754, 15, '2026-05-25 06:54:00', '2026-05-25 13:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(755, 15, '2026-05-27 06:53:00', '2026-05-27 13:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(756, 15, '2026-05-28 10:52:00', '2026-05-28 17:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(757, 15, '2026-05-30 16:51:00', '2026-05-30 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(758, 15, '2026-05-31 16:54:00', '2026-05-31 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(759, 15, '2026-06-03 06:47:00', '2026-06-03 13:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(760, 15, '2026-06-04 06:55:00', '2026-06-04 13:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(761, 15, '2026-06-06 06:50:00', '2026-06-06 13:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(762, 15, '2026-06-13 07:24:00', '2026-06-13 13:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:35', NULL),
(763, 15, '2026-06-16 06:48:00', '2026-06-16 13:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(764, 15, '2026-06-21 07:00:00', '2026-06-21 15:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(765, 15, '2026-06-26 10:47:00', '2026-06-26 17:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(766, 15, '2026-06-29 16:46:00', '2026-06-29 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(767, 15, '2026-06-30 16:46:00', '2026-06-30 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(768, 15, '2026-07-01 06:50:00', '2026-07-01 13:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(769, 15, '2026-07-02 10:53:00', '2026-07-02 17:05:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(770, 15, '2026-07-03 06:51:00', '2026-07-03 13:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(771, 15, '2026-07-08 10:50:00', '2026-07-08 17:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(772, 15, '2026-07-09 06:47:00', '2026-07-09 13:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(773, 15, '2026-07-11 11:10:00', '2026-07-11 17:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:35', NULL),
(774, 15, '2026-07-14 06:54:00', '2026-07-14 13:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(775, 15, '2026-07-16 11:26:00', '2026-07-16 17:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:35', NULL),
(776, 15, '2026-07-18 16:54:00', '2026-07-18 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(777, 16, '2026-07-04 16:47:00', '2026-07-04 23:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(778, 16, '2026-07-05 10:54:00', '2026-07-05 17:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(779, 16, '2026-07-06 06:55:00', '2026-07-06 13:01:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(780, 16, '2026-07-07 16:48:00', '2026-07-07 23:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(781, 16, '2026-07-10 06:47:00', '2026-07-10 13:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(782, 16, '2026-07-12 16:52:00', '2026-07-12 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(783, 17, '2026-07-16 10:55:00', '2026-07-16 17:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(784, 17, '2026-07-17 06:49:00', '2026-07-17 13:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(785, 17, '2026-07-19 10:45:00', '2026-07-19 17:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(786, 18, '2026-06-27 10:54:00', '2026-06-27 17:00:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(787, 18, '2026-06-28 06:52:00', '2026-06-28 13:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(788, 18, '2026-06-29 06:55:00', '2026-06-29 13:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(789, 18, '2026-07-01 17:42:00', '2026-07-01 23:00:00', NULL, NULL, 0, 0.00, 0.00, 'Late', '2026-07-20 03:08:35', NULL),
(790, 18, '2026-07-02 06:49:00', '2026-07-02 13:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(791, 18, '2026-07-04 10:45:00', '2026-07-04 17:02:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(792, 18, '2026-07-06 16:54:00', '2026-07-06 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(793, 18, '2026-07-07 16:54:00', '2026-07-07 23:03:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(794, 18, '2026-07-10 06:55:00', '2026-07-10 13:04:00', NULL, NULL, 0, 0.00, 0.00, 'Completed', '2026-07-20 03:08:35', NULL),
(795, 12, '2026-07-20 09:58:20', '2026-07-20 17:58:20', NULL, NULL, 0, 0.00, 0.00, 'Auto-Closed', '2026-07-20 09:58:20', NULL),
(796, 14, '2026-07-20 09:59:22', '2026-07-20 17:59:22', NULL, NULL, 0, 0.00, 0.00, 'Auto-Closed', '2026-07-20 09:59:22', NULL),
(797, 6, '2026-07-20 09:59:59', '2026-07-20 11:42:04', NULL, NULL, 0, 0.00, 0.00, 'Clocked Out', '2026-07-20 09:59:59', NULL),
(798, 13, '2026-07-20 10:00:29', '2026-07-20 18:00:29', NULL, NULL, 0, 0.00, 0.00, 'Auto-Closed', '2026-07-20 10:00:29', NULL),
(799, 8, '2026-07-27 23:05:05', '2026-07-28 07:05:05', NULL, NULL, 0, 0.00, 0.00, 'Auto-Closed', '2026-07-27 23:05:05', NULL),
(800, 8, '2026-07-28 00:23:34', '2026-07-28 22:43:20', NULL, NULL, 0, 0.00, 0.00, 'Clocked Out', '2026-07-28 00:23:34', NULL),
(801, 23, '2026-07-28 17:01:44', '2026-07-28 22:44:31', NULL, NULL, 0, 0.00, 0.00, 'Clocked Out', '2026-07-28 17:01:44', NULL),
(802, 13, '2026-07-28 22:43:38', '2026-07-28 22:46:03', NULL, NULL, 0, 0.00, 0.00, 'Clocked Out', '2026-07-28 22:43:38', NULL),
(803, 14, '2026-07-28 22:44:06', '2026-07-28 22:46:25', NULL, NULL, 0, 0.00, 0.00, 'Clocked Out', '2026-07-28 22:44:06', NULL),
(804, 12, '2026-07-28 22:44:16', '2026-07-28 22:46:38', NULL, NULL, 0, 0.00, 0.00, 'Clocked Out', '2026-07-28 22:44:16', NULL),
(805, 1, '2026-07-29 13:48:06', '2026-07-29 21:48:06', NULL, NULL, 0, 0.00, 0.00, 'Auto-Closed', '2026-07-29 13:48:06', NULL),
(806, 11, '2026-07-29 13:48:52', NULL, NULL, NULL, 0, 0.00, 0.00, 'Present', '2026-07-29 13:48:52', NULL),
(807, 13, '2026-07-29 13:49:04', NULL, NULL, NULL, 0, 0.00, 0.00, 'Present', '2026-07-29 13:49:04', NULL),
(808, 10, '2026-07-29 13:49:24', NULL, NULL, NULL, 0, 0.00, 0.00, 'Present', '2026-07-29 13:49:24', NULL),
(809, 1, '2026-09-07 05:08:51', NULL, NULL, NULL, 0, 0.00, 0.00, 'Present', '2026-09-07 05:08:51', NULL),
(810, 8, '2026-09-07 05:09:23', '2026-09-07 13:09:23', NULL, NULL, 0, 0.00, 0.00, 'Auto-Closed', '2026-09-07 05:09:23', NULL),
(811, 12, '2026-09-07 05:15:52', NULL, NULL, NULL, 0, 0.00, 0.00, 'Present', '2026-09-07 05:15:52', NULL),
(812, 8, '2026-09-08 14:35:35', '2026-09-08 22:35:35', NULL, NULL, 0, 0.00, 0.00, 'Auto-Closed', '2026-09-08 14:35:35', NULL);
INSERT INTO `attendance` (`id`, `employee_id`, `time_in`, `time_out`, `break_in`, `break_out`, `is_late`, `overtime_hours`, `undertime_hours`, `status`, `created_at`, `updated_at`) VALUES
(813, 8, '2026-09-15 02:51:39', NULL, NULL, NULL, 0, 0.00, 0.00, 'Present', '2026-09-15 02:51:39', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int NOT NULL,
  `name` varchar(150) NOT NULL,
  `address` text,
  `contact_number` varchar(50) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `name`, `address`, `contact_number`, `status`, `created_at`) VALUES
(1, 'Main Branch', '123 Coffee Boulevard, Cityville', NULL, 'Active', '2026-09-04 00:46:05');

-- --------------------------------------------------------

--
-- Table structure for table `branch_budgets`
--

CREATE TABLE `branch_budgets` (
  `id` int NOT NULL,
  `branch_id` int NOT NULL,
  `budget_month` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'YYYY-MM format',
  `allocated_labor_budget` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branch_budgets`
--

INSERT INTO `branch_budgets` (`id`, `branch_id`, `budget_month`, `allocated_labor_budget`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-09', 100000.00, '2026-09-07 04:46:20', '2026-09-07 04:46:20'),
(2, 1, '2026-07', 100000.00, '2026-09-07 04:51:39', '2026-09-07 04:51:39');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `document_type` varchar(120) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_verifications`
--

CREATE TABLE `email_verifications` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `token` varchar(120) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int NOT NULL,
  `employee_id` varchar(80) NOT NULL,
  `first_name` varchar(150) DEFAULT NULL,
  `last_name` varchar(150) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `position` varchar(120) DEFAULT NULL,
  `department` varchar(120) DEFAULT NULL,
  `employment_type` varchar(50) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Active',
  `sick_leave_balance` int NOT NULL DEFAULT '14',
  `termination_reason` varchar(100) DEFAULT NULL,
  `termination_details` text,
  `termination_date` date DEFAULT NULL,
  `date_hired` date DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `hourly_rate` decimal(12,2) NOT NULL DEFAULT '0.00',
  `last_raise_date` date DEFAULT NULL,
  `last_bonus_date` date DEFAULT NULL,
  `address` text,
  `emergency_contact` varchar(200) DEFAULT NULL,
  `bank_account` varchar(120) DEFAULT NULL,
  `tin` varchar(50) DEFAULT NULL,
  `sss` varchar(50) DEFAULT NULL,
  `philhealth` varchar(50) DEFAULT NULL,
  `pagibig` varchar(50) DEFAULT NULL,
  `government_ids` varchar(255) DEFAULT NULL,
  `photo` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_phone` varchar(50) DEFAULT NULL,
  `valid_id_back_photo` varchar(500) DEFAULT NULL,
  `valid_id_photo` varchar(500) DEFAULT NULL,
  `education_level` varchar(50) DEFAULT NULL,
  `school_name` varchar(150) DEFAULT NULL,
  `school_address` varchar(255) DEFAULT NULL,
  `year_graduated` varchar(20) DEFAULT NULL,
  `education_status` varchar(50) DEFAULT NULL,
  `course_diploma` varchar(150) DEFAULT NULL,
  `employment_category` varchar(50) DEFAULT 'Full-Time',
  `birthdate` date DEFAULT NULL,
  `sex` varchar(20) DEFAULT '',
  `nationality` varchar(100) DEFAULT '',
  `preferred_schedule` varchar(50) DEFAULT 'Any',
  `branch_id` int DEFAULT NULL,
  `employment_status` enum('Trainee','Probationary','Regular') NOT NULL DEFAULT 'Regular'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_id`, `first_name`, `last_name`, `email`, `phone`, `position`, `department`, `employment_type`, `status`, `sick_leave_balance`, `termination_reason`, `termination_details`, `termination_date`, `date_hired`, `birthday`, `hourly_rate`, `last_raise_date`, `last_bonus_date`, `address`, `emergency_contact`, `bank_account`, `tin`, `sss`, `philhealth`, `pagibig`, `government_ids`, `photo`, `created_at`, `emergency_contact_name`, `emergency_contact_phone`, `valid_id_back_photo`, `valid_id_photo`, `education_level`, `school_name`, `school_address`, `year_graduated`, `education_status`, `course_diploma`, `employment_category`, `birthdate`, `sex`, `nationality`, `preferred_schedule`, `branch_id`, `employment_status`) VALUES
(1, 'EMP-001', 'Juan', 'Dela Cruz', 'juandelacruz@cafehrms.local', '09123456789', 'Head Barista', NULL, NULL, 'Active', 14, NULL, NULL, NULL, '2026-04-22', NULL, 114.40, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-20 03:05:42', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Full-Time', NULL, '', '', 'Any', 1, 'Regular'),
(2, 'EMP-002', 'Maria', 'Clara', 'mariaclara@cafehrms.local', '09123456789', 'Head Barista', NULL, NULL, 'Active', 14, NULL, NULL, NULL, '2026-04-17', NULL, 114.40, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-20 03:05:42', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Full-Time', NULL, '', '', 'Any', 1, 'Regular'),
(3, 'EMP-003', 'Jose', 'Rizal', 'joserizal@cafehrms.local', '09123456789', 'Head Barista', NULL, NULL, 'Active', 14, NULL, NULL, NULL, '2026-04-19', NULL, 114.40, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-20 03:05:42', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Full-Time', NULL, '', '', 'Any', 1, 'Regular'),
(4, 'EMP-004', 'Andres', 'Bonifacio', 'andresbonifacio@cafehrms.local', '09123456789', 'Head Barista', NULL, NULL, 'Archived', 0, 'Deployed to other store', 'Asked to be deployed to the other city for better time and work environment ', '2026-07-20', '2026-04-17', NULL, 114.40, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-20 03:05:42', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Full-Time', NULL, '', '', 'Any', 1, 'Regular'),
(5, 'EMP-005', 'Emilio', 'Aguinaldo', 'emilioaguinaldo@cafehrms.local', '09123456789', 'Head Barista', NULL, NULL, 'Active', 14, NULL, NULL, NULL, '2026-04-21', NULL, 114.40, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-20 03:05:42', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Full-Time', NULL, '', '', 'Any', 1, 'Regular'),
(6, 'EMP-006', 'Apolinario', 'Mabini', 'apolinariomabini@cafehrms.local', '09123456789', 'Barista', NULL, NULL, 'Active', 14, NULL, NULL, NULL, '2026-04-21', NULL, 114.40, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-20 03:05:42', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Full-Time', NULL, '', '', 'Any', 1, 'Regular'),
(7, 'EMP-007', 'Marcelo', 'Del Pilar', 'marcelodelpilar@cafehrms.local', '09123456789', 'Barista', NULL, NULL, 'Active', 14, NULL, NULL, NULL, '2026-04-20', NULL, 114.40, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-20 03:05:43', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Full-Time', NULL, '', '', 'Any', 1, 'Regular'),
(8, 'EMP-008', 'Gabriela', 'Silang', 'gabrielasilang@cafehrms.local', '09123456789', 'Head Barista', NULL, NULL, 'Active', 14, NULL, NULL, NULL, '2026-04-21', NULL, 114.40, NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-20 03:05:43', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, 'Full-Time', NULL, '', '', 'Any', 1, 'Regular'),
(9, 'EMP-009', 'Melchora', 'Aquino', 'melchoraaquino@cafehrms.local', '09123456789', 'Barista', NULL, NULL, 'Active', 14, NULL, NULL, NULL, '2026-04-16', NULL, 114.40, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-20 03:05:43', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Full-Time', NULL, '', '', 'Any', 1, 'Regular'),
(10, 'EMP-010', 'Antonio', 'Luna', 'antonioluna@cafehrms.local', '09123456789', 'Head Barista', NULL, NULL, 'Active', 14, NULL, NULL, NULL, '2026-04-16', NULL, 114.40, NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-20 03:05:43', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, 'Full-Time', NULL, '', '', 'Any', 1, 'Regular'),
(11, 'EMP-011', 'Lapu', 'Lapu', 'lapulapu@cafehrms.local', '09123456789', 'Barista', NULL, NULL, 'Active', 3, NULL, NULL, NULL, '2026-07-10', NULL, 114.40, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-20 03:05:43', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Full-Time', NULL, '', '', 'Any', 1, 'Trainee'),
(12, 'EMP-012', 'Diego', 'Silang', 'diegosilang@cafehrms.local', '09123456789', 'Head Barista', NULL, NULL, 'Active', 3, NULL, NULL, NULL, '2026-07-08', NULL, 114.40, NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-20 03:05:43', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, 'Full-Time', NULL, '', '', 'Any', 1, 'Trainee'),
(13, 'EMP-013', 'Gregorio', 'DelPilar', 'gregoriodelpilar@cafehrms.local', '09123456789', 'Barista', NULL, NULL, 'Active', 3, NULL, NULL, NULL, '2026-07-01', NULL, 114.40, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-20 03:05:43', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Full-Time', NULL, '', '', 'Any', 1, 'Trainee'),
(14, 'EMP-014', 'Emilio', 'Jacinto', 'emiliojacinto@cafehrms.local', '09123456789', 'Barista', NULL, NULL, 'Active', 14, NULL, NULL, NULL, '2026-04-22', NULL, 100.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-20 03:05:43', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Part-Time', NULL, '', '', 'Any', 1, 'Regular'),
(15, 'EMP-015', 'Miguel', 'Malvar', 'miguelmalvar@cafehrms.local', '09123456789', 'Barista', NULL, NULL, 'Active', 19, NULL, NULL, NULL, '2026-04-16', NULL, 100.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-20 03:05:43', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Part-Time', NULL, '', '', 'Any', 1, 'Regular'),
(16, 'EMP-016', 'Macario', 'Sakay', 'macariosakay@cafehrms.local', '09123456789', 'Barista', NULL, NULL, 'Active', 3, NULL, NULL, NULL, '2026-07-04', NULL, 100.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-20 03:05:43', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Part-Time', NULL, '', '', 'Any', 1, 'Trainee'),
(17, 'EMP-017', 'Teresa', 'Magbanua', 'teresamagbanua@cafehrms.local', '09123456789', 'Barista', NULL, NULL, 'Active', 3, NULL, NULL, NULL, '2026-07-14', NULL, 100.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-20 03:05:43', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Part-Time', NULL, '', '', 'Any', 1, 'Trainee'),
(18, 'EMP-018', 'Trinidad', 'Tecson', 'trinidadtecson@cafehrms.local', '09123456789', 'Barista', NULL, NULL, 'Active', 3, NULL, NULL, NULL, '2026-06-25', NULL, 100.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-20 03:05:43', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Part-Time', NULL, '', '', 'Any', 1, 'Trainee'),
(19, 'EMP-019', 'Manuel', 'Quezon', 'manuelquezon@cafehrms.local', '09123456789', 'HR', NULL, NULL, 'Active', 14, NULL, NULL, NULL, '2026-04-18', NULL, 114.40, NULL, NULL, '', NULL, NULL, '', '', '', '', NULL, NULL, '2026-07-20 03:05:43', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Full-Time', NULL, '', '', 'Any', 1, 'Regular'),
(20, 'EMP-020', 'Sergio', 'Osmena', 'sergioosmena@cafehrms.local', '09123456789', 'HR', NULL, NULL, 'Active', 14, NULL, NULL, NULL, '2026-04-24', NULL, 114.40, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-20 03:05:43', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Full-Time', NULL, '', '', 'Any', 1, 'Regular'),
(21, 'EMP-021', 'Jose', 'Laurel', 'joselaurel@cafehrms.local', '09123456789', 'HR', NULL, NULL, 'Active', 14, NULL, NULL, NULL, '2026-04-22', NULL, 114.40, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-20 03:05:43', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Full-Time', NULL, '', '', 'Any', 1, 'Regular'),
(22, 'EMP-022', 'Ramon', 'Magsaysay', 'ramonmagsaysay@cafehrms.local', '09123456789', 'HR', NULL, NULL, 'Active', 14, NULL, NULL, NULL, '2026-04-16', NULL, 114.40, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-20 03:05:43', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Full-Time', NULL, '', '', 'Any', 1, 'Regular'),
(23, 'BAR-8561', 'Kristan', 'Kyle Ante', 'kristankyleante@gmail.com', '+63 9260114881', 'Barista', NULL, NULL, 'Active', 14, NULL, NULL, NULL, '2026-07-28', NULL, 114.40, NULL, NULL, '[REDACTED]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-28 16:20:13', 'Shaine', '+63 5646645344', 'id_back_1785226685_777.jpg', 'id_1785226685_738.jpg', 'College', 'National College of Science and Technology', '', '2026', 'Enrolled', 'BS Information Technology', 'Full-Time', '2005-03-01', 'Male', 'Chinese', 'Morning', 1, 'Trainee');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_assignments`
--

CREATE TABLE `equipment_assignments` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Released',
  `assigned_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `returned_at` datetime DEFAULT NULL,
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `global_notifications`
--

CREATE TABLE `global_notifications` (
  `id` int NOT NULL,
  `message` text NOT NULL,
  `icon` varchar(50) DEFAULT 'fa-bell',
  `color` varchar(50) DEFAULT 'primary',
  `link` varchar(255) DEFAULT NULL,
  `target_roles` varchar(255) NOT NULL,
  `target_user_id` int DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `read_by` int DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `global_notifications`
--

INSERT INTO `global_notifications` (`id`, `message`, `icon`, `color`, `link`, `target_roles`, `target_user_id`, `is_read`, `read_by`, `read_at`, `created_at`) VALUES
(1, 'Maria Clara requested Sick Leave (Jul 21 - Jul 24). 2 scheduled shift(s) were posted to the Shift Board!', 'fa-plane-departure', 'primary', 'leave.php', 'Admin,Super Admin,HR,HR Admin', NULL, 0, NULL, NULL, '2026-07-20 10:40:11'),
(2, 'Melchora Aquino requested Sick Leave (Jul 28 - Jul 29). 1 scheduled shift(s) were posted to the Shift Board!', 'fa-plane-departure', 'primary', 'leave.php', 'Admin,Super Admin,HR,HR Admin', NULL, 1, 1, '2026-07-28 02:54:09', '2026-07-20 11:14:36'),
(3, 'Miguel Malvar requested Sick Leave (Jul 20 - Jul 24). 3 scheduled shift(s) were posted to the Shift Board!', 'fa-plane-departure', 'primary', 'leave.php', 'Admin,Super Admin,HR,HR Admin', NULL, 0, NULL, NULL, '2026-07-20 11:15:49'),
(4, 'Marcelo Del Pilar requested Vacation (Jul 28 - Jul 31). 2 scheduled shift(s) were posted to the Shift Board!', 'fa-plane-departure', 'primary', 'leave.php', 'Admin,Super Admin,HR,HR Admin', NULL, 1, 159, '2026-07-20 11:35:54', '2026-07-20 11:18:13');

-- --------------------------------------------------------

--
-- Table structure for table `interviews`
--

CREATE TABLE `interviews` (
  `id` int NOT NULL,
  `applicant_id` int NOT NULL,
  `interview_date` date NOT NULL,
  `interview_time` time NOT NULL,
  `end_time` time DEFAULT NULL,
  `interviewer_id` int DEFAULT NULL,
  `stage` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Scheduled',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `interviews`
--

INSERT INTO `interviews` (`id`, `applicant_id`, `interview_date`, `interview_time`, `end_time`, `interviewer_id`, `stage`, `status`, `created_at`) VALUES
(1, 15, '2026-07-20', '10:00:00', NULL, 7, 'Initial Interview', 'Scheduled', '2026-07-19 20:56:28'),
(2, 16, '2026-07-20', '13:30:00', NULL, 9, 'Initial Interview', 'Scheduled', '2026-07-19 21:22:38'),
(3, 1, '2026-07-20', '16:00:00', NULL, 21, 'Initial Interview', 'Scheduled', '2026-07-20 14:13:02'),
(4, 1, '2026-07-27', '12:30:00', NULL, 14, 'Final Interview', 'Scheduled', '2026-07-26 23:22:10'),
(5, 2, '2026-07-29', '17:00:00', NULL, 19, 'Initial Interview', 'Scheduled', '2026-07-28 16:10:21'),
(6, 3, '2026-07-28', '18:20:00', NULL, 15, 'Final Interview', 'Scheduled', '2026-07-28 16:18:44'),
(7, 3, '2026-07-28', '18:20:00', NULL, 15, 'Final Interview', 'Scheduled', '2026-07-28 16:18:50'),
(8, 3, '2026-07-28', '18:20:00', NULL, 15, 'Final Interview', 'Scheduled', '2026-07-28 16:18:55'),
(9, 3, '2026-07-28', '18:20:00', NULL, 15, 'Final Interview', 'Scheduled', '2026-07-28 16:19:03'),
(10, 5, '2026-07-30', '15:00:00', NULL, 8, 'Initial Interview', 'AWOL', '2026-07-29 14:26:50'),
(11, 2, '2026-09-07', '09:00:00', '10:40:00', 21, 'Final Interview', 'Scheduled', '2026-09-06 20:38:50'),
(12, 6, '2026-09-06', '22:00:00', '22:30:00', 21, 'Initial Interview', 'Completed', '2026-09-06 21:47:09'),
(13, 7, '2026-09-06', '22:31:00', '23:00:00', 21, 'Initial Interview', 'Completed', '2026-09-06 21:51:09'),
(14, 9, '2026-09-07', '08:00:00', '08:30:00', 21, 'Initial Interview', 'Scheduled', '2026-09-06 22:25:18'),
(15, 7, '2026-09-08', '08:00:00', '08:30:00', 21, 'Final Interview', 'Scheduled', '2026-09-06 22:54:19');

-- --------------------------------------------------------

--
-- Table structure for table `interview_scorecards`
--

CREATE TABLE `interview_scorecards` (
  `id` int NOT NULL,
  `interview_id` int NOT NULL,
  `technical_score` int NOT NULL,
  `communication_score` int DEFAULT NULL,
  `reliability_score` int DEFAULT NULL,
  `culture_score` int NOT NULL,
  `problem_solving_score` int DEFAULT NULL,
  `strengths` text,
  `concerns` text,
  `recommendation` enum('Hire','Reject','Next Round','Global Pool') NOT NULL,
  `created_by` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `hourly_wage` decimal(10,2) DEFAULT NULL,
  `monthly_wage` decimal(10,2) DEFAULT NULL,
  `global_pool_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `interview_scorecards`
--

INSERT INTO `interview_scorecards` (`id`, `interview_id`, `technical_score`, `communication_score`, `reliability_score`, `culture_score`, `problem_solving_score`, `strengths`, `concerns`, `recommendation`, `created_by`, `created_at`, `hourly_wage`, `monthly_wage`, `global_pool_reason`) VALUES
(1, 12, 4, NULL, NULL, 5, NULL, 'igop', 'angat', 'Next Round', 'System Admin', '2026-09-06 22:13:54', NULL, NULL, NULL),
(2, 13, 4, NULL, NULL, 4, NULL, 'Igop', 'Angat', 'Next Round', 'System Admin', '2026-09-06 22:54:19', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int NOT NULL,
  `branch_id` int NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT 'General',
  `unit` varchar(50) NOT NULL DEFAULT 'pcs',
  `stock_level` decimal(10,2) NOT NULL DEFAULT '0.00',
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `reorder_level` decimal(10,2) NOT NULL DEFAULT '10.00',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `id` int NOT NULL,
  `branch_id` int NOT NULL,
  `inventory_id` int DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `type` enum('Restock','Write-off','Usage') NOT NULL,
  `quantity` int NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `status` varchar(50) DEFAULT 'Completed',
  `logged_by` int NOT NULL,
  `transaction_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leaves`
--

CREATE TABLE `leaves` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `leave_type` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text,
  `medical_certificate` varchar(500) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `med_cert` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `leaves`
--

INSERT INTO `leaves` (`id`, `employee_id`, `leave_type`, `start_date`, `end_date`, `reason`, `medical_certificate`, `status`, `med_cert`, `created_at`) VALUES
(1, 1, 'Sick', '2026-07-20', '2026-07-22', 'Fever', NULL, 'Approved', NULL, '2026-07-20 03:08:35'),
(2, 6, 'Vacation', '2026-07-23', '2026-07-25', 'Family Trip', NULL, 'Approved', NULL, '2026-07-20 03:08:35'),
(3, 2, 'Sick', '2026-07-21', '2026-07-24', 'Rashes', NULL, 'Approved', NULL, '2026-07-20 10:40:11'),
(4, 9, 'Sick', '2026-07-28', '2026-07-29', 'Mandatory Check-up', NULL, 'Approved', NULL, '2026-07-20 11:14:36'),
(5, 15, 'Sick', '2026-07-20', '2026-07-24', 'Sudden Chronic Internal Pain', NULL, 'Voided (AWOL)', NULL, '2026-07-20 11:15:49'),
(6, 7, 'Vacation', '2026-07-28', '2026-07-31', 'Family Out', NULL, 'Rejected', NULL, '2026-07-20 11:18:13');

-- --------------------------------------------------------

--
-- Table structure for table `leave_approvals`
--

CREATE TABLE `leave_approvals` (
  `id` int NOT NULL,
  `leave_id` int NOT NULL,
  `approver_id` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `target_role` varchar(80) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `title`, `message`, `target_role`, `is_read`, `created_at`) VALUES
(1, 'New Hire Account Created', 'A system account was automatically created for new hire: Kristan Kyle Ante (Username: kristan.ante).', 'Super Admin', 0, '2026-07-28 16:20:13');

-- --------------------------------------------------------

--
-- Table structure for table `open_shifts`
--

CREATE TABLE `open_shifts` (
  `id` int NOT NULL,
  `original_employee_id` int NOT NULL,
  `shift_date` date NOT NULL,
  `shift_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Open',
  `claimed_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `open_shifts`
--

INSERT INTO `open_shifts` (`id`, `original_employee_id`, `shift_date`, `shift_type`, `reason`, `status`, `claimed_by`, `created_at`) VALUES
(1, 1, '2026-07-20', 'Mid', 'Leave: Sick Leave', 'Open', NULL, '2026-07-20 03:08:35'),
(2, 1, '2026-07-21', 'Night', 'Leave: Sick Leave', 'Open', NULL, '2026-07-20 03:08:35'),
(3, 1, '2026-07-22', 'Morning', 'Leave: Sick Leave', 'Open', NULL, '2026-07-20 03:08:35'),
(4, 6, '2026-07-23', 'Mid', 'Leave: Vacation Leave', 'Open', NULL, '2026-07-20 03:08:35'),
(5, 6, '2026-07-24', 'Night', 'Leave: Vacation Leave', 'Open', NULL, '2026-07-20 03:08:35');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `token` varchar(120) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `created_at`) VALUES
(3, 166, '348b943a20873d6cd784755163ff4302edd3a4bc', '2026-07-28 16:33:24');

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `status` varchar(20) DEFAULT 'Draft',
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `hourly_rate` decimal(12,2) NOT NULL DEFAULT '0.00',
  `overtime_hours` decimal(6,2) NOT NULL DEFAULT '0.00',
  `overtime_pay` decimal(12,2) NOT NULL DEFAULT '0.00',
  `holiday_pay` decimal(12,2) NOT NULL DEFAULT '0.00',
  `night_differential` decimal(12,2) NOT NULL DEFAULT '0.00',
  `late_deduction` decimal(12,2) NOT NULL DEFAULT '0.00',
  `absent_deduction` decimal(12,2) NOT NULL DEFAULT '0.00',
  `bonus_amount` decimal(10,2) DEFAULT '0.00',
  `tax` decimal(12,2) NOT NULL DEFAULT '0.00',
  `sss` decimal(12,2) NOT NULL DEFAULT '0.00',
  `philhealth` decimal(12,2) NOT NULL DEFAULT '0.00',
  `pagibig` decimal(12,2) NOT NULL DEFAULT '0.00',
  `gross_pay` decimal(14,2) NOT NULL DEFAULT '0.00',
  `deductions` decimal(14,2) NOT NULL DEFAULT '0.00',
  `net_pay` decimal(14,2) NOT NULL DEFAULT '0.00',
  `payment_method` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `payroll`
--

INSERT INTO `payroll` (`id`, `employee_id`, `status`, `period_start`, `period_end`, `hourly_rate`, `overtime_hours`, `overtime_pay`, `holiday_pay`, `night_differential`, `late_deduction`, `absent_deduction`, `bonus_amount`, `tax`, `sss`, `philhealth`, `pagibig`, `gross_pay`, `deductions`, `net_pay`, `payment_method`, `created_at`) VALUES
(46, 3, 'Released', '2026-07-01', '2026-07-15', 113.64, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 450.00, 250.00, 100.00, 10000.00, 800.00, 9200.00, 'Cash', '2026-07-26 23:20:37'),
(50, 8, 'Released', '2026-07-01', '2026-07-15', 113.64, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 450.00, 250.00, 100.00, 10000.00, 800.00, 9200.00, 'Cash', '2026-07-26 23:20:38'),
(58, 16, 'Released', '2026-07-01', '2026-07-15', 100.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 167.48, 93.04, 100.00, 3721.67, 360.52, 3361.15, 'Cash', '2026-07-26 23:20:38'),
(65, 1, 'Released', '2026-07-16', '2026-07-31', 113.64, 0.00, 0.00, 0.00, 0.00, 0.00, 4545.45, 0.00, 0.00, 245.45, 136.36, 100.00, 10000.00, 5027.27, 4972.73, 'Cash', '2026-07-27 14:45:48'),
(88, 5, 'Released', '2026-07-16', '2026-07-31', 113.64, 0.00, 0.00, 0.00, 0.00, 0.00, 10000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 10000.00, 10000.00, 0.00, 'Cash', '2026-07-28 16:27:42'),
(149, 1, 'Draft', '2026-09-16', '2026-09-30', 114.40, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15'),
(150, 2, 'Draft', '2026-09-16', '2026-09-30', 114.40, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15'),
(151, 3, 'Draft', '2026-09-16', '2026-09-30', 114.40, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15'),
(152, 5, 'Draft', '2026-09-16', '2026-09-30', 114.40, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15'),
(153, 6, 'Draft', '2026-09-16', '2026-09-30', 114.40, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15'),
(154, 7, 'Draft', '2026-09-16', '2026-09-30', 114.40, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15'),
(155, 8, 'Draft', '2026-09-16', '2026-09-30', 114.40, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15'),
(156, 9, 'Draft', '2026-09-16', '2026-09-30', 114.40, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15'),
(157, 10, 'Draft', '2026-09-16', '2026-09-30', 114.40, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15'),
(158, 11, 'Draft', '2026-09-16', '2026-09-30', 114.40, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15'),
(159, 12, 'Draft', '2026-09-16', '2026-09-30', 114.40, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15'),
(160, 13, 'Draft', '2026-09-16', '2026-09-30', 114.40, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15'),
(161, 14, 'Draft', '2026-09-16', '2026-09-30', 100.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15'),
(162, 15, 'Draft', '2026-09-16', '2026-09-30', 100.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15'),
(163, 16, 'Draft', '2026-09-16', '2026-09-30', 100.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15'),
(164, 17, 'Draft', '2026-09-16', '2026-09-30', 100.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15'),
(165, 18, 'Draft', '2026-09-16', '2026-09-30', 100.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15'),
(166, 19, 'Draft', '2026-09-16', '2026-09-30', 114.40, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15'),
(167, 20, 'Draft', '2026-09-16', '2026-09-30', 114.40, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15'),
(168, 21, 'Draft', '2026-09-16', '2026-09-30', 114.40, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15'),
(169, 22, 'Draft', '2026-09-16', '2026-09-30', 114.40, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15'),
(170, 23, 'Draft', '2026-09-16', '2026-09-30', 114.40, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-29 17:33:15');

-- --------------------------------------------------------

--
-- Table structure for table `pending_bonuses`
--

CREATE TABLE `pending_bonuses` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `performance_reviews`
--

CREATE TABLE `performance_reviews` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `evaluator_id` int NOT NULL,
  `review_date` date NOT NULL,
  `is_leadership` tinyint(1) NOT NULL DEFAULT '0',
  `evaluation_type` varchar(50) NOT NULL DEFAULT 'Official',
  `score_kitchen` int DEFAULT NULL,
  `score_cashier` int DEFAULT NULL,
  `score_cleaning` int DEFAULT NULL,
  `score_inventory` int DEFAULT NULL,
  `score_floor_mgmt` int DEFAULT NULL,
  `score_staff_training` int DEFAULT NULL,
  `score_quality_control` int DEFAULT NULL,
  `score_reliability` int DEFAULT NULL,
  `score_setup` int DEFAULT NULL,
  `score_scheduling` int DEFAULT NULL,
  `score_recruitment` int DEFAULT NULL,
  `score_compliance` int DEFAULT NULL,
  `total_score` decimal(3,2) NOT NULL,
  `comments` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ;

--
-- Dumping data for table `performance_reviews`
--

INSERT INTO `performance_reviews` (`id`, `employee_id`, `evaluator_id`, `review_date`, `is_leadership`, `evaluation_type`, `score_kitchen`, `score_cashier`, `score_cleaning`, `score_inventory`, `score_floor_mgmt`, `score_staff_training`, `score_quality_control`, `score_reliability`, `score_setup`, `score_scheduling`, `score_recruitment`, `score_compliance`, `total_score`, `comments`, `created_at`) VALUES
(1, 12, 1, '2026-07-19', 0, 'Official', 4, 4, 4, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4.25, '', '2026-07-19 05:48:22'),
(2, 8, 1, '2026-07-19', 0, 'Official', 5, 4, 4, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4.50, '', '2026-07-19 05:48:33'),
(3, 11, 1, '2026-07-19', 0, 'Official', 1, 1, 2, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1.50, '', '2026-07-19 06:00:53'),
(4, 11, 1, '2026-07-19', 0, 'Official', 4, 4, 3, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3.25, '', '2026-07-19 06:01:01'),
(5, 12, 1, '2026-07-19', 0, 'Official', 3, 3, 2, 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2.75, '', '2026-07-19 06:01:51'),
(6, 13, 1, '2026-07-19', 0, 'Official', 4, 3, 3, 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3.25, '', '2026-07-19 14:52:04'),
(7, 13, 1, '2026-07-19', 0, 'Official', 2, 1, 2, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1.50, '', '2026-07-19 14:52:11'),
(8, 12, 1, '2026-07-19', 0, 'Official', 3, 3, 3, 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3.00, '', '2026-07-19 14:52:22'),
(9, 13, 1, '2026-07-19', 0, 'Official', 3, 3, 3, 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3.00, '', '2026-07-19 14:52:30'),
(10, 2, 159, '2026-07-20', 1, 'Official', NULL, NULL, NULL, NULL, 4, 3, 4, 5, NULL, NULL, NULL, NULL, 4.00, '', '2026-07-20 04:26:47'),
(11, 10, 142, '2026-07-20', 0, 'HB_Weekly', 4, 3, 4, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4.00, '', '2026-07-20 10:20:34'),
(12, 19, 1, '2026-07-27', 1, 'Official', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, 4, 3, 4, 3.75, '', '2026-07-27 19:31:05');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requests`
--

CREATE TABLE `purchase_requests` (
  `id` int NOT NULL,
  `branch_id` int NOT NULL,
  `inventory_id` int DEFAULT NULL,
  `requested_by` int NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity` int NOT NULL,
  `estimated_cost` decimal(10,2) NOT NULL,
  `status` enum('Pending','Approved','Rejected','Completed') DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`) VALUES
(8, 'Barista'),
(2, 'Branch Admin'),
(9, 'Cashier'),
(7, 'Central HR'),
(12, 'Clock'),
(11, 'Executive'),
(10, 'Global Accountant'),
(5, 'Head Barista'),
(4, 'Kiosk'),
(1, 'Super Admin');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int NOT NULL,
  `employee_id` int DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Assigned',
  `shift_type` varchar(120) NOT NULL,
  `shift_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `assigned_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `branch_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `employee_id`, `status`, `shift_type`, `shift_date`, `start_time`, `end_time`, `assigned_by`, `created_at`, `branch_id`) VALUES
(1, 1, 'Assigned', 'Night', '2026-04-23', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(2, 1, 'Assigned', 'Mid', '2026-04-24', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(3, 1, 'Assigned', 'Morning', '2026-04-27', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:33', 1),
(4, 1, 'Assigned', 'Mid', '2026-04-29', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:33', 1),
(5, 1, 'Assigned', 'Morning', '2026-04-30', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:33', 1),
(6, 1, 'Assigned', 'Night', '2026-05-01', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:33', 1),
(7, 1, 'Assigned', 'Night', '2026-05-03', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:33', 1),
(8, 1, 'Assigned', 'Morning', '2026-05-05', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:33', 1),
(9, 1, 'Assigned', 'Mid', '2026-05-06', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(10, 1, 'Assigned', 'Morning', '2026-05-07', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(11, 1, 'Assigned', 'Morning', '2026-05-09', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:33', 1),
(12, 1, 'Assigned', 'Night', '2026-05-10', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(13, 1, 'Assigned', 'Night', '2026-05-11', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:33', 1),
(14, 1, 'Assigned', 'Mid', '2026-05-12', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:33', 1),
(15, 1, 'Assigned', 'Night', '2026-05-13', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(16, 1, 'Assigned', 'Mid', '2026-05-14', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(17, 1, 'Assigned', 'Mid', '2026-05-16', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(18, 1, 'Assigned', 'Morning', '2026-05-17', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(19, 1, 'Assigned', 'Night', '2026-05-18', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:33', 1),
(20, 1, 'Assigned', 'Night', '2026-05-19', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(21, 1, 'Assigned', 'Night', '2026-05-22', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(22, 1, 'Assigned', 'Night', '2026-05-23', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(23, 1, 'Assigned', 'Mid', '2026-05-24', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(24, 1, 'Assigned', 'Mid', '2026-05-26', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:33', 1),
(25, 1, 'Assigned', 'Mid', '2026-05-27', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(26, 1, 'Assigned', 'Morning', '2026-05-28', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:33', 1),
(27, 1, 'Assigned', 'Mid', '2026-05-31', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:33', 1),
(28, 1, 'Assigned', 'Night', '2026-06-02', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(29, 1, 'Assigned', 'Morning', '2026-06-03', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(30, 1, 'Assigned', 'Morning', '2026-06-05', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:33', 1),
(31, 1, 'Assigned', 'Night', '2026-06-08', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:33', 1),
(32, 1, 'Assigned', 'Mid', '2026-06-09', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(33, 1, 'Assigned', 'Night', '2026-06-11', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(34, 1, 'Assigned', 'Morning', '2026-06-12', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:33', 1),
(35, 1, 'Assigned', 'Night', '2026-06-15', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(36, 1, 'Assigned', 'Mid', '2026-06-16', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(37, 1, 'Assigned', 'Morning', '2026-06-19', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:33', 1),
(38, 1, 'Assigned', 'Night', '2026-06-20', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(39, 1, 'Assigned', 'Night', '2026-06-21', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:33', 1),
(40, 1, 'Assigned', 'Mid', '2026-06-22', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(41, 1, 'Assigned', 'Night', '2026-06-23', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(42, 1, 'Assigned', 'Morning', '2026-06-27', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(43, 1, 'Assigned', 'Morning', '2026-06-28', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:33', 1),
(44, 1, 'Assigned', 'Night', '2026-06-29', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(45, 1, 'Assigned', 'Mid', '2026-07-01', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(46, 1, 'Assigned', 'Morning', '2026-07-02', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(47, 1, 'Assigned', 'Mid', '2026-07-03', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:33', 1),
(48, 1, 'Assigned', 'Morning', '2026-07-05', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(49, 1, 'Assigned', 'Mid', '2026-07-06', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:33', 1),
(50, 1, 'Assigned', 'Morning', '2026-07-07', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(51, 1, 'Assigned', 'Morning', '2026-07-10', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:33', 1),
(52, 1, 'Assigned', 'Mid', '2026-07-12', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:33', 1),
(53, 1, 'Assigned', 'Mid', '2026-07-13', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(54, 1, 'Assigned', 'Morning', '2026-07-16', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(55, 1, 'Assigned', 'Morning', '2026-07-17', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(56, 1, 'Assigned', 'Night', '2026-07-18', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:33', 1),
(57, 1, 'Assigned', 'Morning', '2026-07-19', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(58, 1, 'Assigned', 'Mid', '2026-07-20', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:33', 1),
(59, 1, 'Assigned', 'Night', '2026-07-21', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(60, 1, 'Assigned', 'Morning', '2026-07-22', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:33', 1),
(61, 1, 'Assigned', 'Morning', '2026-07-23', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(62, 1, 'Assigned', 'Mid', '2026-07-26', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:33', 1),
(63, 1, 'Assigned', 'Mid', '2026-07-29', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(64, 1, 'Assigned', 'Night', '2026-07-30', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(65, 1, 'Assigned', 'Mid', '2026-07-31', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:33', 1),
(66, 2, 'Assigned', 'Mid', '2026-04-17', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:33', 1),
(67, 2, 'Assigned', 'Mid', '2026-04-18', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(68, 2, 'Assigned', 'Morning', '2026-04-19', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:33', 1),
(69, 2, 'Assigned', 'Morning', '2026-04-20', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(70, 2, 'Assigned', 'Morning', '2026-04-21', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:33', 1),
(71, 2, 'Assigned', 'Morning', '2026-04-22', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(72, 2, 'Assigned', 'Night', '2026-04-23', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(73, 2, 'Assigned', 'Mid', '2026-04-24', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:33', 1),
(74, 2, 'Assigned', 'Morning', '2026-04-25', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(75, 2, 'Assigned', 'Night', '2026-04-26', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(76, 2, 'Assigned', 'Night', '2026-04-27', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:33', 1),
(77, 2, 'Assigned', 'Mid', '2026-05-01', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(78, 2, 'Assigned', 'Mid', '2026-05-02', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(79, 2, 'Assigned', 'Mid', '2026-05-04', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:33', 1),
(80, 2, 'Assigned', 'Mid', '2026-05-05', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:33', 1),
(81, 2, 'Assigned', 'Night', '2026-05-06', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:33', 1),
(82, 2, 'Assigned', 'Morning', '2026-05-07', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:33', 1),
(83, 2, 'Assigned', 'Night', '2026-05-10', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(84, 2, 'Assigned', 'Morning', '2026-05-12', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:33', 1),
(85, 2, 'Assigned', 'Mid', '2026-05-13', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:33', 1),
(86, 2, 'Assigned', 'Night', '2026-05-14', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:33', 1),
(87, 2, 'Assigned', 'Mid', '2026-05-15', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:33', 1),
(88, 2, 'Assigned', 'Mid', '2026-05-16', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(89, 2, 'Assigned', 'Mid', '2026-05-17', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(90, 2, 'Assigned', 'Mid', '2026-05-19', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(91, 2, 'Assigned', 'Mid', '2026-05-20', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:33', 1),
(92, 2, 'Assigned', 'Night', '2026-05-21', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(93, 2, 'Assigned', 'Night', '2026-05-22', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(94, 2, 'Assigned', 'Morning', '2026-05-23', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:33', 1),
(95, 2, 'Assigned', 'Mid', '2026-05-24', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:33', 1),
(96, 2, 'Assigned', 'Mid', '2026-05-25', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(97, 2, 'Assigned', 'Morning', '2026-05-27', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:33', 1),
(98, 2, 'Assigned', 'Morning', '2026-05-29', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:33', 1),
(99, 2, 'Assigned', 'Morning', '2026-05-30', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:33', 1),
(100, 2, 'Assigned', 'Morning', '2026-05-31', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:33', 1),
(101, 2, 'Assigned', 'Night', '2026-06-01', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(102, 2, 'Assigned', 'Night', '2026-06-02', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:33', 1),
(103, 2, 'Assigned', 'Morning', '2026-06-03', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(104, 2, 'Assigned', 'Morning', '2026-06-04', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:33', 1),
(105, 2, 'Assigned', 'Night', '2026-06-05', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(106, 2, 'Assigned', 'Night', '2026-06-06', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:33', 1),
(107, 2, 'Assigned', 'Night', '2026-06-07', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(108, 2, 'Assigned', 'Morning', '2026-06-10', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:33', 1),
(109, 2, 'Assigned', 'Morning', '2026-06-13', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:33', 1),
(110, 2, 'Assigned', 'Morning', '2026-06-15', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(111, 2, 'Assigned', 'Night', '2026-06-16', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(112, 2, 'Assigned', 'Night', '2026-06-17', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:33', 1),
(113, 2, 'Assigned', 'Morning', '2026-06-18', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:33', 1),
(114, 2, 'Assigned', 'Morning', '2026-06-19', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:33', 1),
(115, 2, 'Assigned', 'Morning', '2026-06-21', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(116, 2, 'Assigned', 'Mid', '2026-06-22', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:33', 1),
(117, 2, 'Assigned', 'Night', '2026-06-25', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:33', 1),
(118, 2, 'Assigned', 'Morning', '2026-06-26', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(119, 2, 'Assigned', 'Night', '2026-06-27', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(120, 2, 'Assigned', 'Mid', '2026-06-28', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:33', 1),
(121, 2, 'Assigned', 'Mid', '2026-06-29', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:33', 1),
(122, 2, 'Assigned', 'Night', '2026-06-30', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:33', 1),
(123, 2, 'Assigned', 'Mid', '2026-07-01', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:33', 1),
(124, 2, 'Assigned', 'Morning', '2026-07-02', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(125, 2, 'Assigned', 'Morning', '2026-07-03', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:33', 1),
(126, 2, 'Assigned', 'Morning', '2026-07-04', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:33', 1),
(127, 2, 'Assigned', 'Night', '2026-07-05', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(128, 2, 'Assigned', 'Morning', '2026-07-06', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:33', 1),
(129, 2, 'Assigned', 'Mid', '2026-07-07', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(130, 2, 'Assigned', 'Morning', '2026-07-08', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:33', 1),
(131, 2, 'Assigned', 'Night', '2026-07-10', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(132, 2, 'Assigned', 'Night', '2026-07-11', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(133, 2, 'Assigned', 'Mid', '2026-07-13', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(134, 2, 'Assigned', 'Mid', '2026-07-14', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(135, 2, 'Assigned', 'Night', '2026-07-15', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:33', 1),
(136, 2, 'Assigned', 'Night', '2026-07-17', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(137, 2, 'Assigned', 'Mid', '2026-07-20', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(138, NULL, 'Open', 'Morning', '2026-07-21', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:33', 1),
(139, NULL, 'Open', 'Night', '2026-07-22', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(140, 2, 'Assigned', 'Night', '2026-07-25', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(141, 2, 'Assigned', 'Mid', '2026-07-27', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:33', 1),
(142, 2, 'Assigned', 'Night', '2026-07-28', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:33', 1),
(143, 2, 'Assigned', 'Night', '2026-07-29', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(144, 2, 'Assigned', 'Morning', '2026-07-30', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:33', 1),
(145, 2, 'Assigned', 'Mid', '2026-07-31', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(146, 3, 'Assigned', 'Morning', '2026-04-20', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:33', 1),
(147, 3, 'Assigned', 'Mid', '2026-04-21', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:33', 1),
(148, 3, 'Assigned', 'Mid', '2026-04-24', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:33', 1),
(149, 3, 'Assigned', 'Morning', '2026-04-25', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(150, 3, 'Assigned', 'Night', '2026-04-26', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:33', 1),
(151, 3, 'Assigned', 'Morning', '2026-04-27', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:33', 1),
(152, 3, 'Assigned', 'Morning', '2026-04-28', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(153, 3, 'Assigned', 'Mid', '2026-04-29', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(154, 3, 'Assigned', 'Night', '2026-04-30', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(155, 3, 'Assigned', 'Night', '2026-05-01', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:33', 1),
(156, 3, 'Assigned', 'Mid', '2026-05-02', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:33', 1),
(157, 3, 'Assigned', 'Night', '2026-05-03', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:33', 1),
(158, 3, 'Assigned', 'Morning', '2026-05-05', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(159, 3, 'Assigned', 'Night', '2026-05-06', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:33', 1),
(160, 3, 'Assigned', 'Morning', '2026-05-07', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:33', 1),
(161, 3, 'Assigned', 'Morning', '2026-05-09', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:33', 1),
(162, 3, 'Assigned', 'Morning', '2026-05-10', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(163, 3, 'Assigned', 'Night', '2026-05-11', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(164, 3, 'Assigned', 'Morning', '2026-05-13', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:33', 1),
(165, 3, 'Assigned', 'Mid', '2026-05-14', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:33', 1),
(166, 3, 'Assigned', 'Mid', '2026-05-16', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(167, 3, 'Assigned', 'Night', '2026-05-17', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(168, 3, 'Assigned', 'Morning', '2026-05-18', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:33', 1),
(169, 3, 'Assigned', 'Mid', '2026-05-19', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(170, 3, 'Assigned', 'Mid', '2026-05-20', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(171, 3, 'Assigned', 'Mid', '2026-05-21', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(172, 3, 'Assigned', 'Mid', '2026-05-22', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(173, 3, 'Assigned', 'Morning', '2026-05-23', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(174, 3, 'Assigned', 'Night', '2026-05-24', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:33', 1),
(175, 3, 'Assigned', 'Mid', '2026-05-25', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:33', 1),
(176, 3, 'Assigned', 'Morning', '2026-05-26', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:33', 1),
(177, 3, 'Assigned', 'Morning', '2026-05-27', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(178, 3, 'Assigned', 'Night', '2026-05-29', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:33', 1),
(179, 3, 'Assigned', 'Night', '2026-05-31', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(180, 3, 'Assigned', 'Morning', '2026-06-01', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:33', 1),
(181, 3, 'Assigned', 'Mid', '2026-06-02', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(182, 3, 'Assigned', 'Morning', '2026-06-04', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:33', 1),
(183, 3, 'Assigned', 'Night', '2026-06-05', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:33', 1),
(184, 3, 'Assigned', 'Morning', '2026-06-06', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:33', 1),
(185, 3, 'Assigned', 'Night', '2026-06-07', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:33', 1),
(186, 3, 'Assigned', 'Night', '2026-06-08', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(187, 3, 'Assigned', 'Mid', '2026-06-09', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(188, 3, 'Assigned', 'Mid', '2026-06-10', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:33', 1),
(189, 3, 'Assigned', 'Mid', '2026-06-11', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:33', 1),
(190, 3, 'Assigned', 'Morning', '2026-06-13', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(191, 3, 'Assigned', 'Mid', '2026-06-15', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:33', 1),
(192, 3, 'Assigned', 'Mid', '2026-06-17', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:33', 1),
(193, 3, 'Assigned', 'Mid', '2026-06-18', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:33', 1),
(194, 3, 'Assigned', 'Night', '2026-06-19', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(195, 3, 'Assigned', 'Mid', '2026-06-20', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(196, 3, 'Assigned', 'Mid', '2026-06-21', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(197, 3, 'Assigned', 'Mid', '2026-06-22', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(198, 3, 'Assigned', 'Mid', '2026-06-23', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(199, 3, 'Assigned', 'Night', '2026-06-24', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(200, 3, 'Assigned', 'Night', '2026-06-26', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:33', 1),
(201, 3, 'Assigned', 'Mid', '2026-06-29', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(202, 3, 'Assigned', 'Night', '2026-06-30', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(203, 3, 'Assigned', 'Night', '2026-07-01', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:33', 1),
(204, 3, 'Assigned', 'Night', '2026-07-02', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:33', 1),
(205, 3, 'Assigned', 'Morning', '2026-07-05', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(206, 3, 'Assigned', 'Night', '2026-07-06', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(207, 3, 'Assigned', 'Night', '2026-07-07', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(208, 3, 'Assigned', 'Morning', '2026-07-08', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:33', 1),
(209, 3, 'Assigned', 'Night', '2026-07-11', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:33', 1),
(210, 3, 'Assigned', 'Mid', '2026-07-12', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(211, 3, 'Assigned', 'Mid', '2026-07-14', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:33', 1),
(212, 3, 'Assigned', 'Night', '2026-07-15', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(213, 3, 'Assigned', 'Morning', '2026-07-17', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:33', 1),
(214, 3, 'Assigned', 'Mid', '2026-07-18', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(215, 3, 'Assigned', 'Night', '2026-07-19', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(216, 3, 'Assigned', 'Mid', '2026-07-20', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(217, 3, 'Assigned', 'Mid', '2026-07-23', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(218, 3, 'Assigned', 'Mid', '2026-07-24', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(219, 3, 'Assigned', 'Morning', '2026-07-25', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(220, 3, 'Assigned', 'Night', '2026-07-27', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(221, 3, 'Assigned', 'Night', '2026-07-28', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(222, 3, 'Assigned', 'Mid', '2026-07-30', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(223, 3, 'Assigned', 'Night', '2026-07-31', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:33', 1),
(224, 4, 'Assigned', 'Morning', '2026-04-19', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:33', 1),
(225, 4, 'Assigned', 'Mid', '2026-04-21', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(226, 4, 'Assigned', 'Morning', '2026-04-22', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:33', 1),
(227, 4, 'Assigned', 'Mid', '2026-04-23', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:33', 1),
(228, 4, 'Assigned', 'Morning', '2026-04-25', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:33', 1),
(229, 4, 'Assigned', 'Mid', '2026-04-27', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:33', 1),
(230, 4, 'Assigned', 'Mid', '2026-04-28', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(231, 4, 'Assigned', 'Morning', '2026-04-29', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:33', 1),
(232, 4, 'Assigned', 'Night', '2026-04-30', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:33', 1),
(233, 4, 'Assigned', 'Mid', '2026-05-03', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:33', 1),
(234, 4, 'Assigned', 'Mid', '2026-05-05', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:33', 1),
(235, 4, 'Assigned', 'Morning', '2026-05-07', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:33', 1),
(236, 4, 'Assigned', 'Night', '2026-05-08', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(237, 4, 'Assigned', 'Night', '2026-05-09', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(238, 4, 'Assigned', 'Night', '2026-05-10', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(239, 4, 'Assigned', 'Mid', '2026-05-12', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:33', 1),
(240, 4, 'Assigned', 'Morning', '2026-05-13', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:33', 1),
(241, 4, 'Assigned', 'Mid', '2026-05-14', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(242, 4, 'Assigned', 'Night', '2026-05-15', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:33', 1),
(243, 4, 'Assigned', 'Morning', '2026-05-16', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(244, 4, 'Assigned', 'Mid', '2026-05-17', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:33', 1),
(245, 4, 'Assigned', 'Morning', '2026-05-19', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:33', 1),
(246, 4, 'Assigned', 'Night', '2026-05-21', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:33', 1),
(247, 4, 'Assigned', 'Morning', '2026-05-22', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:33', 1),
(248, 4, 'Assigned', 'Night', '2026-05-24', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(249, 4, 'Assigned', 'Mid', '2026-05-25', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:33', 1),
(250, 4, 'Assigned', 'Night', '2026-05-26', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:33', 1),
(251, 4, 'Assigned', 'Night', '2026-05-28', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:33', 1),
(252, 4, 'Assigned', 'Morning', '2026-05-30', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:33', 1),
(253, 4, 'Assigned', 'Morning', '2026-06-01', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:33', 1),
(254, 4, 'Assigned', 'Morning', '2026-06-02', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:33', 1),
(255, 4, 'Assigned', 'Morning', '2026-06-03', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:33', 1),
(256, 4, 'Assigned', 'Night', '2026-06-04', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:33', 1),
(257, 4, 'Assigned', 'Night', '2026-06-05', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:33', 1),
(258, 4, 'Assigned', 'Morning', '2026-06-06', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:33', 1),
(259, 4, 'Assigned', 'Morning', '2026-06-08', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:33', 1),
(260, 4, 'Assigned', 'Mid', '2026-06-10', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(261, 4, 'Assigned', 'Night', '2026-06-11', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(262, 4, 'Assigned', 'Night', '2026-06-12', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(263, 4, 'Assigned', 'Morning', '2026-06-13', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(264, 4, 'Assigned', 'Mid', '2026-06-14', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(265, 4, 'Assigned', 'Mid', '2026-06-16', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(266, 4, 'Assigned', 'Night', '2026-06-17', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(267, 4, 'Assigned', 'Night', '2026-06-18', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(268, 4, 'Assigned', 'Mid', '2026-06-19', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(269, 4, 'Assigned', 'Mid', '2026-06-20', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(270, 4, 'Assigned', 'Night', '2026-06-21', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(271, 4, 'Assigned', 'Night', '2026-06-22', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(272, 4, 'Assigned', 'Night', '2026-06-23', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(273, 4, 'Assigned', 'Night', '2026-06-25', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(274, 4, 'Assigned', 'Night', '2026-06-26', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(275, 4, 'Assigned', 'Mid', '2026-06-28', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(276, 4, 'Assigned', 'Night', '2026-06-30', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(277, 4, 'Assigned', 'Night', '2026-07-02', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(278, 4, 'Assigned', 'Morning', '2026-07-03', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(279, 4, 'Assigned', 'Morning', '2026-07-05', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(280, 4, 'Assigned', 'Morning', '2026-07-06', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(281, 4, 'Assigned', 'Mid', '2026-07-07', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(282, 4, 'Assigned', 'Mid', '2026-07-10', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(283, 4, 'Assigned', 'Mid', '2026-07-11', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(284, 4, 'Assigned', 'Morning', '2026-07-12', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(285, 4, 'Assigned', 'Night', '2026-07-13', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(286, 4, 'Assigned', 'Night', '2026-07-14', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(287, 4, 'Assigned', 'Morning', '2026-07-16', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(288, 4, 'Assigned', 'Night', '2026-07-19', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(289, 4, 'Assigned', 'Morning', '2026-07-20', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(290, 4, 'Assigned', 'Morning', '2026-07-23', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(291, 4, 'Assigned', 'Mid', '2026-07-24', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(292, 4, 'Assigned', 'Morning', '2026-07-27', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(293, 4, 'Assigned', 'Mid', '2026-07-28', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(294, 4, 'Assigned', 'Mid', '2026-07-30', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(295, 4, 'Assigned', 'Night', '2026-07-31', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(296, 5, 'Assigned', 'Morning', '2026-04-23', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(297, 5, 'Assigned', 'Morning', '2026-04-25', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(298, 5, 'Assigned', 'Mid', '2026-04-26', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(299, 5, 'Assigned', 'Night', '2026-04-27', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(300, 5, 'Assigned', 'Morning', '2026-04-28', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(301, 5, 'Assigned', 'Night', '2026-04-29', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(302, 5, 'Assigned', 'Mid', '2026-05-01', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(303, 5, 'Assigned', 'Morning', '2026-05-02', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(304, 5, 'Assigned', 'Morning', '2026-05-05', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(305, 5, 'Assigned', 'Morning', '2026-05-08', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(306, 5, 'Assigned', 'Morning', '2026-05-09', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(307, 5, 'Assigned', 'Night', '2026-05-11', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(308, 5, 'Assigned', 'Morning', '2026-05-12', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(309, 5, 'Assigned', 'Mid', '2026-05-14', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(310, 5, 'Assigned', 'Night', '2026-05-15', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(311, 5, 'Assigned', 'Morning', '2026-05-17', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(312, 5, 'Assigned', 'Mid', '2026-05-18', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(313, 5, 'Assigned', 'Night', '2026-05-23', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(314, 5, 'Assigned', 'Morning', '2026-05-24', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(315, 5, 'Assigned', 'Mid', '2026-05-26', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(316, 5, 'Assigned', 'Morning', '2026-05-28', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(317, 5, 'Assigned', 'Night', '2026-05-29', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(318, 5, 'Assigned', 'Night', '2026-05-30', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(319, 5, 'Assigned', 'Morning', '2026-05-31', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(320, 5, 'Assigned', 'Mid', '2026-06-02', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(321, 5, 'Assigned', 'Mid', '2026-06-03', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(322, 5, 'Assigned', 'Morning', '2026-06-04', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(323, 5, 'Assigned', 'Morning', '2026-06-05', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(324, 5, 'Assigned', 'Mid', '2026-06-09', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(325, 5, 'Assigned', 'Night', '2026-06-10', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(326, 5, 'Assigned', 'Night', '2026-06-11', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(327, 5, 'Assigned', 'Morning', '2026-06-12', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(328, 5, 'Assigned', 'Morning', '2026-06-13', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(329, 5, 'Assigned', 'Morning', '2026-06-15', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(330, 5, 'Assigned', 'Night', '2026-06-17', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(331, 5, 'Assigned', 'Night', '2026-06-19', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(332, 5, 'Assigned', 'Night', '2026-06-21', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(333, 5, 'Assigned', 'Morning', '2026-06-22', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(334, 5, 'Assigned', 'Mid', '2026-06-23', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(335, 5, 'Assigned', 'Mid', '2026-06-25', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(336, 5, 'Assigned', 'Night', '2026-06-27', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(337, 5, 'Assigned', 'Mid', '2026-06-28', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(338, 5, 'Assigned', 'Mid', '2026-06-30', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(339, 5, 'Assigned', 'Mid', '2026-07-01', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(340, 5, 'Assigned', 'Night', '2026-07-02', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(341, 5, 'Assigned', 'Night', '2026-07-03', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(342, 5, 'Assigned', 'Morning', '2026-07-05', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(343, 5, 'Assigned', 'Night', '2026-07-06', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(344, 5, 'Assigned', 'Morning', '2026-07-07', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(345, 5, 'Assigned', 'Night', '2026-07-09', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(346, 5, 'Assigned', 'Mid', '2026-07-10', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(347, 5, 'Assigned', 'Night', '2026-07-11', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(348, 5, 'Assigned', 'Morning', '2026-07-13', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(349, 5, 'Assigned', 'Morning', '2026-07-14', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(350, 5, 'Assigned', 'Mid', '2026-07-15', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(351, 5, 'Assigned', 'Night', '2026-07-18', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(352, 5, 'Assigned', 'Night', '2026-07-20', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(353, 5, 'Assigned', 'Morning', '2026-07-21', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(354, 5, 'Assigned', 'Mid', '2026-07-23', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(355, 5, 'Assigned', 'Mid', '2026-07-24', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(356, 5, 'Assigned', 'Mid', '2026-07-25', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(357, 5, 'Assigned', 'Mid', '2026-07-26', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(358, 5, 'Assigned', 'Morning', '2026-07-27', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(359, 5, 'Assigned', 'Mid', '2026-07-28', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(360, 5, 'Assigned', 'Night', '2026-07-29', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(361, 5, 'Assigned', 'Mid', '2026-07-30', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(362, 5, 'Assigned', 'Night', '2026-07-31', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(363, 6, 'Assigned', 'Night', '2026-04-21', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(364, 6, 'Assigned', 'Night', '2026-04-22', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(365, 6, 'Assigned', 'Morning', '2026-04-23', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(366, 6, 'Assigned', 'Night', '2026-04-24', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(367, 6, 'Assigned', 'Morning', '2026-04-25', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(368, 6, 'Assigned', 'Morning', '2026-04-26', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(369, 6, 'Assigned', 'Mid', '2026-04-27', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(370, 6, 'Assigned', 'Night', '2026-04-28', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(371, 6, 'Assigned', 'Morning', '2026-04-29', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(372, 6, 'Assigned', 'Morning', '2026-04-30', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(373, 6, 'Assigned', 'Morning', '2026-05-02', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(374, 6, 'Assigned', 'Morning', '2026-05-05', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(375, 6, 'Assigned', 'Mid', '2026-05-06', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(376, 6, 'Assigned', 'Mid', '2026-05-07', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(377, 6, 'Assigned', 'Morning', '2026-05-08', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(378, 6, 'Assigned', 'Morning', '2026-05-09', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(379, 6, 'Assigned', 'Morning', '2026-05-10', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(380, 6, 'Assigned', 'Mid', '2026-05-11', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(381, 6, 'Assigned', 'Mid', '2026-05-12', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(382, 6, 'Assigned', 'Morning', '2026-05-13', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(383, 6, 'Assigned', 'Night', '2026-05-14', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(384, 6, 'Assigned', 'Mid', '2026-05-15', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(385, 6, 'Assigned', 'Morning', '2026-05-17', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(386, 6, 'Assigned', 'Morning', '2026-05-18', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(387, 6, 'Assigned', 'Mid', '2026-05-19', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(388, 6, 'Assigned', 'Night', '2026-05-21', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(389, 6, 'Assigned', 'Night', '2026-05-23', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(390, 6, 'Assigned', 'Night', '2026-05-24', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(391, 6, 'Assigned', 'Morning', '2026-05-25', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(392, 6, 'Assigned', 'Night', '2026-05-26', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(393, 6, 'Assigned', 'Night', '2026-05-27', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(394, 6, 'Assigned', 'Night', '2026-05-29', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(395, 6, 'Assigned', 'Mid', '2026-05-31', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(396, 6, 'Assigned', 'Night', '2026-06-02', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(397, 6, 'Assigned', 'Morning', '2026-06-04', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(398, 6, 'Assigned', 'Night', '2026-06-06', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(399, 6, 'Assigned', 'Night', '2026-06-09', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(400, 6, 'Assigned', 'Night', '2026-06-12', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(401, 6, 'Assigned', 'Mid', '2026-06-13', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(402, 6, 'Assigned', 'Mid', '2026-06-14', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(403, 6, 'Assigned', 'Night', '2026-06-15', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(404, 6, 'Assigned', 'Mid', '2026-06-16', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(405, 6, 'Assigned', 'Night', '2026-06-17', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(406, 6, 'Assigned', 'Night', '2026-06-18', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(407, 6, 'Assigned', 'Morning', '2026-06-20', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(408, 6, 'Assigned', 'Mid', '2026-06-21', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(409, 6, 'Assigned', 'Night', '2026-06-22', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(410, 6, 'Assigned', 'Morning', '2026-06-23', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(411, 6, 'Assigned', 'Morning', '2026-06-24', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(412, 6, 'Assigned', 'Morning', '2026-06-25', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(413, 6, 'Assigned', 'Morning', '2026-06-27', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(414, 6, 'Assigned', 'Mid', '2026-06-28', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(415, 6, 'Assigned', 'Mid', '2026-06-29', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(416, 6, 'Assigned', 'Mid', '2026-06-30', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(417, 6, 'Assigned', 'Night', '2026-07-02', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(418, 6, 'Assigned', 'Morning', '2026-07-03', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(419, 6, 'Assigned', 'Mid', '2026-07-04', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(420, 6, 'Assigned', 'Morning', '2026-07-06', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(421, 6, 'Assigned', 'Night', '2026-07-08', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(422, 6, 'Assigned', 'Morning', '2026-07-09', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(423, 6, 'Assigned', 'Mid', '2026-07-10', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(424, 6, 'Assigned', 'Morning', '2026-07-12', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(425, 6, 'Assigned', 'Mid', '2026-07-13', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(426, 6, 'Assigned', 'Night', '2026-07-15', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(427, 6, 'Assigned', 'Mid', '2026-07-17', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(428, 6, 'Assigned', 'Night', '2026-07-18', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(429, 6, 'Assigned', 'Night', '2026-07-19', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(430, 6, 'Assigned', 'Mid', '2026-07-20', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(431, 6, 'Assigned', 'Mid', '2026-07-23', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(432, 6, 'Assigned', 'Night', '2026-07-24', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(433, 6, 'Assigned', 'Mid', '2026-07-28', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(434, 6, 'Assigned', 'Morning', '2026-07-29', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(435, 6, 'Assigned', 'Morning', '2026-07-31', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(436, 7, 'Assigned', 'Mid', '2026-04-21', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(437, 7, 'Assigned', 'Mid', '2026-04-22', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(438, 7, 'Assigned', 'Morning', '2026-04-23', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(439, 7, 'Assigned', 'Mid', '2026-04-24', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(440, 7, 'Assigned', 'Night', '2026-04-25', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(441, 7, 'Assigned', 'Night', '2026-04-26', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(442, 7, 'Assigned', 'Night', '2026-04-27', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(443, 7, 'Assigned', 'Night', '2026-04-28', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(444, 7, 'Assigned', 'Mid', '2026-04-29', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(445, 7, 'Assigned', 'Night', '2026-04-30', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(446, 7, 'Assigned', 'Night', '2026-05-01', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(447, 7, 'Assigned', 'Morning', '2026-05-04', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(448, 7, 'Assigned', 'Mid', '2026-05-06', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(449, 7, 'Assigned', 'Morning', '2026-05-07', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(450, 7, 'Assigned', 'Morning', '2026-05-08', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(451, 7, 'Assigned', 'Night', '2026-05-09', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(452, 7, 'Assigned', 'Morning', '2026-05-11', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(453, 7, 'Assigned', 'Mid', '2026-05-13', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(454, 7, 'Assigned', 'Morning', '2026-05-14', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(455, 7, 'Assigned', 'Morning', '2026-05-15', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(456, 7, 'Assigned', 'Morning', '2026-05-18', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(457, 7, 'Assigned', 'Mid', '2026-05-20', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(458, 7, 'Assigned', 'Night', '2026-05-21', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(459, 7, 'Assigned', 'Night', '2026-05-23', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(460, 7, 'Assigned', 'Night', '2026-05-24', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(461, 7, 'Assigned', 'Mid', '2026-05-25', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(462, 7, 'Assigned', 'Night', '2026-05-26', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(463, 7, 'Assigned', 'Mid', '2026-05-27', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(464, 7, 'Assigned', 'Night', '2026-05-29', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(465, 7, 'Assigned', 'Mid', '2026-05-30', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(466, 7, 'Assigned', 'Morning', '2026-05-31', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(467, 7, 'Assigned', 'Morning', '2026-06-02', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(468, 7, 'Assigned', 'Mid', '2026-06-03', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(469, 7, 'Assigned', 'Morning', '2026-06-05', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(470, 7, 'Assigned', 'Mid', '2026-06-06', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(471, 7, 'Assigned', 'Night', '2026-06-07', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(472, 7, 'Assigned', 'Morning', '2026-06-08', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(473, 7, 'Assigned', 'Night', '2026-06-10', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(474, 7, 'Assigned', 'Mid', '2026-06-11', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(475, 7, 'Assigned', 'Mid', '2026-06-13', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(476, 7, 'Assigned', 'Mid', '2026-06-14', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(477, 7, 'Assigned', 'Morning', '2026-06-15', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(478, 7, 'Assigned', 'Night', '2026-06-16', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(479, 7, 'Assigned', 'Morning', '2026-06-17', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(480, 7, 'Assigned', 'Night', '2026-06-18', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(481, 7, 'Assigned', 'Night', '2026-06-19', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(482, 7, 'Assigned', 'Mid', '2026-06-20', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(483, 7, 'Assigned', 'Night', '2026-06-21', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(484, 7, 'Assigned', 'Morning', '2026-06-22', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(485, 7, 'Assigned', 'Night', '2026-06-23', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(486, 7, 'Assigned', 'Mid', '2026-06-24', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(487, 7, 'Assigned', 'Night', '2026-06-25', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(488, 7, 'Assigned', 'Morning', '2026-06-26', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(489, 7, 'Assigned', 'Night', '2026-06-27', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(490, 7, 'Assigned', 'Night', '2026-06-28', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(491, 7, 'Assigned', 'Night', '2026-06-29', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(492, 7, 'Assigned', 'Morning', '2026-06-30', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(493, 7, 'Assigned', 'Morning', '2026-07-01', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(494, 7, 'Assigned', 'Night', '2026-07-02', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(495, 7, 'Assigned', 'Mid', '2026-07-04', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(496, 7, 'Assigned', 'Night', '2026-07-05', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(497, 7, 'Assigned', 'Morning', '2026-07-06', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(498, 7, 'Assigned', 'Morning', '2026-07-10', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(499, 7, 'Assigned', 'Morning', '2026-07-11', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(500, 7, 'Assigned', 'Night', '2026-07-12', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(501, 7, 'Assigned', 'Night', '2026-07-13', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(502, 7, 'Assigned', 'Night', '2026-07-15', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(503, 7, 'Assigned', 'Morning', '2026-07-16', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(504, 7, 'Assigned', 'Mid', '2026-07-17', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(505, 7, 'Assigned', 'Morning', '2026-07-18', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(506, 7, 'Assigned', 'Night', '2026-07-20', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(507, 7, 'Assigned', 'Night', '2026-07-21', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(508, 7, 'Assigned', 'Night', '2026-07-22', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(509, 7, 'Assigned', 'Night', '2026-07-24', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1);
INSERT INTO `schedules` (`id`, `employee_id`, `status`, `shift_type`, `shift_date`, `start_time`, `end_time`, `assigned_by`, `created_at`, `branch_id`) VALUES
(510, 7, 'Assigned', 'Night', '2026-07-26', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(511, 7, 'Assigned', 'Night', '2026-07-27', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(512, 14, 'Assigned', 'Night', '2026-07-28', '15:00:00', '23:00:00', 1, '2026-07-20 03:08:34', 1),
(513, NULL, 'Open', 'Morning', '2026-07-29', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(514, 8, 'Assigned', 'Mid', '2026-04-21', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(515, 8, 'Assigned', 'Night', '2026-04-23', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(516, 8, 'Assigned', 'Night', '2026-04-24', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(517, 8, 'Assigned', 'Night', '2026-04-26', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(518, 8, 'Assigned', 'Morning', '2026-04-27', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(519, 8, 'Assigned', 'Night', '2026-04-28', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(520, 8, 'Assigned', 'Morning', '2026-04-30', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(521, 8, 'Assigned', 'Morning', '2026-05-01', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(522, 8, 'Assigned', 'Mid', '2026-05-05', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(523, 8, 'Assigned', 'Morning', '2026-05-07', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(524, 8, 'Assigned', 'Morning', '2026-05-11', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(525, 8, 'Assigned', 'Night', '2026-05-12', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(526, 8, 'Assigned', 'Night', '2026-05-13', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(527, 8, 'Assigned', 'Night', '2026-05-14', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(528, 8, 'Assigned', 'Night', '2026-05-15', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(529, 8, 'Assigned', 'Morning', '2026-05-16', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(530, 8, 'Assigned', 'Mid', '2026-05-17', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(531, 8, 'Assigned', 'Night', '2026-05-19', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(532, 8, 'Assigned', 'Morning', '2026-05-21', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(533, 8, 'Assigned', 'Mid', '2026-05-22', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(534, 8, 'Assigned', 'Mid', '2026-05-23', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(535, 8, 'Assigned', 'Night', '2026-05-24', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(536, 8, 'Assigned', 'Morning', '2026-05-25', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(537, 8, 'Assigned', 'Night', '2026-05-26', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(538, 8, 'Assigned', 'Morning', '2026-05-27', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(539, 8, 'Assigned', 'Morning', '2026-05-28', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(540, 8, 'Assigned', 'Night', '2026-05-29', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(541, 8, 'Assigned', 'Morning', '2026-05-31', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(542, 8, 'Assigned', 'Mid', '2026-06-01', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(543, 8, 'Assigned', 'Morning', '2026-06-04', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(544, 8, 'Assigned', 'Morning', '2026-06-05', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(545, 8, 'Assigned', 'Mid', '2026-06-06', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(546, 8, 'Assigned', 'Mid', '2026-06-07', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(547, 8, 'Assigned', 'Mid', '2026-06-08', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(548, 8, 'Assigned', 'Night', '2026-06-10', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(549, 8, 'Assigned', 'Night', '2026-06-12', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(550, 8, 'Assigned', 'Night', '2026-06-13', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(551, 8, 'Assigned', 'Night', '2026-06-14', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(552, 8, 'Assigned', 'Morning', '2026-06-15', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(553, 8, 'Assigned', 'Night', '2026-06-17', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(554, 8, 'Assigned', 'Morning', '2026-06-19', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(555, 8, 'Assigned', 'Night', '2026-06-20', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(556, 8, 'Assigned', 'Morning', '2026-06-21', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(557, 8, 'Assigned', 'Morning', '2026-06-23', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(558, 8, 'Assigned', 'Mid', '2026-06-24', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(559, 8, 'Assigned', 'Night', '2026-06-28', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(560, 8, 'Assigned', 'Morning', '2026-06-29', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(561, 8, 'Assigned', 'Night', '2026-06-30', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(562, 8, 'Assigned', 'Night', '2026-07-02', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(563, 8, 'Assigned', 'Night', '2026-07-04', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(564, 8, 'Assigned', 'Mid', '2026-07-05', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(565, 8, 'Assigned', 'Morning', '2026-07-06', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(566, 8, 'Assigned', 'Night', '2026-07-07', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(567, 8, 'Assigned', 'Night', '2026-07-08', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(568, 8, 'Assigned', 'Mid', '2026-07-09', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(569, 8, 'Assigned', 'Mid', '2026-07-10', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(570, 8, 'Assigned', 'Morning', '2026-07-11', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(571, 8, 'Assigned', 'Night', '2026-07-12', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(572, 8, 'Assigned', 'Morning', '2026-07-13', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(573, 8, 'Assigned', 'Night', '2026-07-14', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(574, 8, 'Assigned', 'Night', '2026-07-15', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(575, 8, 'Assigned', 'Night', '2026-07-16', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(576, 8, 'Assigned', 'Mid', '2026-07-17', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(577, 8, 'Assigned', 'Night', '2026-07-18', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(578, 8, 'Assigned', 'Night', '2026-07-19', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(579, 8, 'Assigned', 'Mid', '2026-07-20', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(580, 8, 'Assigned', 'Night', '2026-07-22', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(581, 8, 'Assigned', 'Mid', '2026-07-23', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(582, 8, 'Assigned', 'Mid', '2026-07-24', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(583, 8, 'Assigned', 'Morning', '2026-07-25', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(584, 8, 'Assigned', 'Night', '2026-07-26', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(585, 8, 'Assigned', 'Morning', '2026-07-27', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(586, 8, 'Assigned', 'Night', '2026-07-29', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(587, 8, 'Assigned', 'Mid', '2026-07-30', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(588, 9, 'Assigned', 'Night', '2026-04-16', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(589, 9, 'Assigned', 'Mid', '2026-04-17', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(590, 9, 'Assigned', 'Night', '2026-04-18', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(591, 9, 'Assigned', 'Mid', '2026-04-19', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(592, 9, 'Assigned', 'Night', '2026-04-21', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(593, 9, 'Assigned', 'Night', '2026-04-22', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(594, 9, 'Assigned', 'Night', '2026-04-23', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(595, 9, 'Assigned', 'Morning', '2026-04-24', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(596, 9, 'Assigned', 'Morning', '2026-04-25', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(597, 9, 'Assigned', 'Night', '2026-04-27', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(598, 9, 'Assigned', 'Morning', '2026-04-28', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(599, 9, 'Assigned', 'Morning', '2026-04-29', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(600, 9, 'Assigned', 'Mid', '2026-05-03', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(601, 9, 'Assigned', 'Night', '2026-05-05', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(602, 9, 'Assigned', 'Morning', '2026-05-08', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(603, 9, 'Assigned', 'Morning', '2026-05-09', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(604, 9, 'Assigned', 'Morning', '2026-05-10', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(605, 9, 'Assigned', 'Night', '2026-05-11', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(606, 9, 'Assigned', 'Morning', '2026-05-12', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(607, 9, 'Assigned', 'Night', '2026-05-13', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(608, 9, 'Assigned', 'Night', '2026-05-14', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(609, 9, 'Assigned', 'Morning', '2026-05-15', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(610, 9, 'Assigned', 'Night', '2026-05-16', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(611, 9, 'Assigned', 'Night', '2026-05-17', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(612, 9, 'Assigned', 'Morning', '2026-05-19', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(613, 9, 'Assigned', 'Mid', '2026-05-20', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(614, 9, 'Assigned', 'Mid', '2026-05-22', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(615, 9, 'Assigned', 'Night', '2026-05-23', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(616, 9, 'Assigned', 'Night', '2026-05-24', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(617, 9, 'Assigned', 'Night', '2026-05-25', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(618, 9, 'Assigned', 'Morning', '2026-05-26', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(619, 9, 'Assigned', 'Night', '2026-05-28', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(620, 9, 'Assigned', 'Mid', '2026-05-29', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(621, 9, 'Assigned', 'Night', '2026-05-31', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(622, 9, 'Assigned', 'Mid', '2026-06-01', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(623, 9, 'Assigned', 'Morning', '2026-06-02', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(624, 9, 'Assigned', 'Morning', '2026-06-03', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(625, 9, 'Assigned', 'Mid', '2026-06-04', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(626, 9, 'Assigned', 'Morning', '2026-06-05', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(627, 9, 'Assigned', 'Mid', '2026-06-06', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(628, 9, 'Assigned', 'Night', '2026-06-07', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(629, 9, 'Assigned', 'Morning', '2026-06-08', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(630, 9, 'Assigned', 'Mid', '2026-06-09', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(631, 9, 'Assigned', 'Mid', '2026-06-10', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(632, 9, 'Assigned', 'Morning', '2026-06-11', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(633, 9, 'Assigned', 'Night', '2026-06-12', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(634, 9, 'Assigned', 'Mid', '2026-06-13', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(635, 9, 'Assigned', 'Night', '2026-06-14', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(636, 9, 'Assigned', 'Morning', '2026-06-15', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(637, 9, 'Assigned', 'Morning', '2026-06-16', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(638, 9, 'Assigned', 'Morning', '2026-06-17', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(639, 9, 'Assigned', 'Mid', '2026-06-18', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(640, 9, 'Assigned', 'Night', '2026-06-21', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(641, 9, 'Assigned', 'Mid', '2026-06-22', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(642, 9, 'Assigned', 'Night', '2026-06-23', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(643, 9, 'Assigned', 'Mid', '2026-06-24', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(644, 9, 'Assigned', 'Morning', '2026-06-25', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(645, 9, 'Assigned', 'Morning', '2026-06-26', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(646, 9, 'Assigned', 'Night', '2026-06-27', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(647, 9, 'Assigned', 'Night', '2026-06-28', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(648, 9, 'Assigned', 'Mid', '2026-06-29', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(649, 9, 'Assigned', 'Night', '2026-06-30', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(650, 9, 'Assigned', 'Mid', '2026-07-01', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(651, 9, 'Assigned', 'Mid', '2026-07-02', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(652, 9, 'Assigned', 'Night', '2026-07-03', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(653, 9, 'Assigned', 'Night', '2026-07-05', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(654, 9, 'Assigned', 'Night', '2026-07-06', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(655, 9, 'Assigned', 'Mid', '2026-07-07', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(656, 9, 'Assigned', 'Mid', '2026-07-08', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(657, 9, 'Assigned', 'Morning', '2026-07-09', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(658, 9, 'Assigned', 'Morning', '2026-07-10', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:34', 1),
(659, 9, 'Assigned', 'Night', '2026-07-12', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(660, 9, 'Assigned', 'Night', '2026-07-13', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(661, 9, 'Assigned', 'Mid', '2026-07-14', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(662, 9, 'Assigned', 'Mid', '2026-07-16', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(663, 9, 'Assigned', 'Mid', '2026-07-17', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(664, 9, 'Assigned', 'Night', '2026-07-19', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(665, 9, 'Assigned', 'Morning', '2026-07-21', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(666, 9, 'Assigned', 'Night', '2026-07-22', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(667, 9, 'Assigned', 'Mid', '2026-07-23', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(668, 9, 'Assigned', 'Mid', '2026-07-24', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(669, 9, 'Assigned', 'Mid', '2026-07-25', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(670, 9, 'Assigned', 'Mid', '2026-07-26', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(671, 9, 'Assigned', 'Morning', '2026-07-27', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(672, 3, 'Assigned', 'Morning', '2026-07-29', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(673, 9, 'Assigned', 'Mid', '2026-07-30', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(674, 9, 'Assigned', 'Mid', '2026-07-31', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(675, 10, 'Assigned', 'Mid', '2026-04-16', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(676, 10, 'Assigned', 'Night', '2026-04-17', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(677, 10, 'Assigned', 'Mid', '2026-04-18', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(678, 10, 'Assigned', 'Mid', '2026-04-19', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(679, 10, 'Assigned', 'Night', '2026-04-20', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(680, 10, 'Assigned', 'Night', '2026-04-21', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(681, 10, 'Assigned', 'Night', '2026-04-22', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(682, 10, 'Assigned', 'Mid', '2026-04-24', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(683, 10, 'Assigned', 'Mid', '2026-04-27', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(684, 10, 'Assigned', 'Night', '2026-04-28', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(685, 10, 'Assigned', 'Night', '2026-04-29', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(686, 10, 'Assigned', 'Morning', '2026-04-30', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(687, 10, 'Assigned', 'Morning', '2026-05-03', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(688, 10, 'Assigned', 'Mid', '2026-05-04', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(689, 10, 'Assigned', 'Mid', '2026-05-05', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(690, 10, 'Assigned', 'Night', '2026-05-06', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(691, 10, 'Assigned', 'Mid', '2026-05-09', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(692, 10, 'Assigned', 'Night', '2026-05-10', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(693, 10, 'Assigned', 'Mid', '2026-05-12', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(694, 10, 'Assigned', 'Night', '2026-05-13', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(695, 10, 'Assigned', 'Morning', '2026-05-15', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:34', 1),
(696, 10, 'Assigned', 'Night', '2026-05-16', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:34', 1),
(697, 10, 'Assigned', 'Mid', '2026-05-17', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(698, 10, 'Assigned', 'Morning', '2026-05-18', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(699, 10, 'Assigned', 'Mid', '2026-05-19', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(700, 10, 'Assigned', 'Mid', '2026-05-21', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(701, 10, 'Assigned', 'Mid', '2026-05-22', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(702, 10, 'Assigned', 'Mid', '2026-05-23', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(703, 10, 'Assigned', 'Night', '2026-05-24', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(704, 10, 'Assigned', 'Night', '2026-05-25', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(705, 10, 'Assigned', 'Morning', '2026-05-26', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(706, 10, 'Assigned', 'Mid', '2026-05-28', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(707, 10, 'Assigned', 'Morning', '2026-05-30', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(708, 10, 'Assigned', 'Night', '2026-05-31', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(709, 10, 'Assigned', 'Morning', '2026-06-01', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(710, 10, 'Assigned', 'Night', '2026-06-04', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:34', 1),
(711, 10, 'Assigned', 'Mid', '2026-06-05', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(712, 10, 'Assigned', 'Mid', '2026-06-06', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(713, 10, 'Assigned', 'Morning', '2026-06-07', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(714, 10, 'Assigned', 'Mid', '2026-06-08', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:34', 1),
(715, 10, 'Assigned', 'Morning', '2026-06-09', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(716, 10, 'Assigned', 'Night', '2026-06-10', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:34', 1),
(717, 10, 'Assigned', 'Mid', '2026-06-12', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:34', 1),
(718, 10, 'Assigned', 'Night', '2026-06-13', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:34', 1),
(719, 10, 'Assigned', 'Mid', '2026-06-15', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:34', 1),
(720, 10, 'Assigned', 'Morning', '2026-06-16', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:34', 1),
(721, 10, 'Assigned', 'Mid', '2026-06-18', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:34', 1),
(722, 10, 'Assigned', 'Morning', '2026-06-19', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:34', 1),
(723, 10, 'Assigned', 'Morning', '2026-06-20', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:35', 1),
(724, 10, 'Assigned', 'Morning', '2026-06-21', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:35', 1),
(725, 10, 'Assigned', 'Mid', '2026-06-22', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:35', 1),
(726, 10, 'Assigned', 'Night', '2026-06-23', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:35', 1),
(727, 10, 'Assigned', 'Morning', '2026-06-24', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:35', 1),
(728, 10, 'Assigned', 'Night', '2026-06-25', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:35', 1),
(729, 10, 'Assigned', 'Night', '2026-06-27', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:35', 1),
(730, 10, 'Assigned', 'Mid', '2026-06-28', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:35', 1),
(731, 10, 'Assigned', 'Mid', '2026-06-29', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:35', 1),
(732, 10, 'Assigned', 'Morning', '2026-06-30', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:35', 1),
(733, 10, 'Assigned', 'Mid', '2026-07-03', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:35', 1),
(734, 10, 'Assigned', 'Morning', '2026-07-04', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:35', 1),
(735, 10, 'Assigned', 'Night', '2026-07-05', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:35', 1),
(736, 10, 'Assigned', 'Mid', '2026-07-07', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:35', 1),
(737, 10, 'Assigned', 'Mid', '2026-07-08', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:35', 1),
(738, 10, 'Assigned', 'Mid', '2026-07-09', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:35', 1),
(739, 10, 'Assigned', 'Night', '2026-07-11', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:35', 1),
(740, 10, 'Assigned', 'Mid', '2026-07-12', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:35', 1),
(741, 10, 'Assigned', 'Mid', '2026-07-14', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:35', 1),
(742, 10, 'Assigned', 'Morning', '2026-07-15', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:35', 1),
(743, 10, 'Assigned', 'Night', '2026-07-16', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:35', 1),
(744, 10, 'Assigned', 'Night', '2026-07-17', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:35', 1),
(745, 10, 'Assigned', 'Mid', '2026-07-19', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:35', 1),
(746, 10, 'Assigned', 'Night', '2026-07-21', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:35', 1),
(747, 10, 'Assigned', 'Night', '2026-07-23', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:35', 1),
(748, 10, 'Assigned', 'Morning', '2026-07-24', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:35', 1),
(749, 10, 'Assigned', 'Morning', '2026-07-25', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:35', 1),
(750, 10, 'Assigned', 'Mid', '2026-07-26', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:35', 1),
(751, 10, 'Assigned', 'Morning', '2026-07-27', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:35', 1),
(752, 10, 'Assigned', 'Mid', '2026-07-29', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:35', 1),
(753, 10, 'Assigned', 'Mid', '2026-07-30', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:35', 1),
(754, 11, 'Assigned', 'Night', '2026-07-12', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:35', 1),
(755, 11, 'Assigned', 'Morning', '2026-07-13', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:35', 1),
(756, 11, 'Assigned', 'Morning', '2026-07-15', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:35', 1),
(757, 11, 'Assigned', 'Night', '2026-07-16', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:35', 1),
(758, 11, 'Assigned', 'Morning', '2026-07-17', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:35', 1),
(759, 11, 'Assigned', 'Morning', '2026-07-19', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:35', 1),
(760, 11, 'Assigned', 'Night', '2026-07-21', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:35', 1),
(761, 11, 'Assigned', 'Mid', '2026-07-22', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:35', 1),
(762, 11, 'Assigned', 'Mid', '2026-07-23', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:35', 1),
(763, 11, 'Assigned', 'Night', '2026-07-25', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:35', 1),
(764, 11, 'Assigned', 'Mid', '2026-07-29', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:35', 1),
(765, 11, 'Assigned', 'Night', '2026-07-30', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:35', 1),
(766, 12, 'Assigned', 'Morning', '2026-07-08', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:35', 1),
(767, 12, 'Assigned', 'Night', '2026-07-09', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:35', 1),
(768, 12, 'Assigned', 'Morning', '2026-07-10', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:35', 1),
(769, 12, 'Assigned', 'Night', '2026-07-11', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:35', 1),
(770, 12, 'Assigned', 'Mid', '2026-07-12', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:35', 1),
(771, 12, 'Assigned', 'Mid', '2026-07-14', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:35', 1),
(772, 12, 'Assigned', 'Mid', '2026-07-15', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:35', 1),
(773, 12, 'Assigned', 'Mid', '2026-07-17', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:35', 1),
(774, 12, 'Assigned', 'Morning', '2026-07-18', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:35', 1),
(775, 12, 'Assigned', 'Night', '2026-07-19', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:35', 1),
(776, 12, 'Assigned', 'Morning', '2026-07-20', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:35', 1),
(777, 12, 'Assigned', 'Morning', '2026-07-21', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:35', 1),
(778, 12, 'Assigned', 'Night', '2026-07-23', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:35', 1),
(779, 12, 'Assigned', 'Mid', '2026-07-24', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:35', 1),
(780, 12, 'Assigned', 'Night', '2026-07-25', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:35', 1),
(781, 12, 'Assigned', 'Mid', '2026-07-27', '11:00:00', '19:00:00', 159, '2026-07-20 03:08:35', 1),
(782, 12, 'Assigned', 'Morning', '2026-07-28', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:35', 1),
(783, 12, 'Assigned', 'Night', '2026-07-31', '15:00:00', '23:00:00', 162, '2026-07-20 03:08:35', 1),
(784, 13, 'Assigned', 'Night', '2026-07-01', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:35', 1),
(785, 13, 'Assigned', 'Mid', '2026-07-02', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:35', 1),
(786, 13, 'Assigned', 'Morning', '2026-07-03', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:35', 1),
(787, 13, 'Assigned', 'Night', '2026-07-04', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:35', 1),
(788, 13, 'Assigned', 'Morning', '2026-07-06', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:35', 1),
(789, 13, 'Assigned', 'Night', '2026-07-07', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:35', 1),
(790, 13, 'Assigned', 'Morning', '2026-07-08', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:35', 1),
(791, 13, 'Assigned', 'Morning', '2026-07-09', '07:00:00', '15:00:00', 159, '2026-07-20 03:08:35', 1),
(792, 13, 'Assigned', 'Morning', '2026-07-10', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:35', 1),
(793, 13, 'Assigned', 'Morning', '2026-07-11', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:35', 1),
(794, 13, 'Assigned', 'Morning', '2026-07-12', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:35', 1),
(795, 13, 'Assigned', 'Mid', '2026-07-13', '11:00:00', '19:00:00', 160, '2026-07-20 03:08:35', 1),
(796, 13, 'Assigned', 'Morning', '2026-07-14', '07:00:00', '15:00:00', 162, '2026-07-20 03:08:35', 1),
(797, 13, 'Assigned', 'Mid', '2026-07-15', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:35', 1),
(798, 13, 'Assigned', 'Night', '2026-07-16', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:35', 1),
(799, 13, 'Assigned', 'Mid', '2026-07-18', '11:00:00', '19:00:00', 161, '2026-07-20 03:08:35', 1),
(800, 13, 'Assigned', 'Morning', '2026-07-19', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:35', 1),
(801, 13, 'Assigned', 'Night', '2026-07-20', '15:00:00', '23:00:00', 159, '2026-07-20 03:08:35', 1),
(802, 13, 'Assigned', 'Night', '2026-07-22', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:35', 1),
(803, 13, 'Assigned', 'Morning', '2026-07-24', '07:00:00', '15:00:00', 160, '2026-07-20 03:08:35', 1),
(804, 13, 'Assigned', 'Night', '2026-07-25', '15:00:00', '23:00:00', 160, '2026-07-20 03:08:35', 1),
(805, 13, 'Assigned', 'Mid', '2026-07-27', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:35', 1),
(806, 13, 'Assigned', 'Morning', '2026-07-28', '07:00:00', '15:00:00', 161, '2026-07-20 03:08:35', 1),
(807, 13, 'Assigned', 'Mid', '2026-07-29', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:35', 1),
(808, 13, 'Assigned', 'Night', '2026-07-30', '15:00:00', '23:00:00', 161, '2026-07-20 03:08:35', 1),
(809, 13, 'Assigned', 'Mid', '2026-07-31', '11:00:00', '19:00:00', 162, '2026-07-20 03:08:35', 1),
(810, 14, 'Assigned', 'Mid', '2026-04-24', '11:00:00', '17:00:00', 161, '2026-07-20 03:08:35', 1),
(811, 14, 'Assigned', 'Night', '2026-04-26', '17:00:00', '23:00:00', 160, '2026-07-20 03:08:35', 1),
(812, 14, 'Assigned', 'Night', '2026-04-27', '17:00:00', '23:00:00', 161, '2026-07-20 03:08:35', 1),
(813, 14, 'Assigned', 'Night', '2026-04-29', '17:00:00', '23:00:00', 162, '2026-07-20 03:08:35', 1),
(814, 14, 'Assigned', 'Mid', '2026-04-30', '11:00:00', '17:00:00', 161, '2026-07-20 03:08:35', 1),
(815, 14, 'Assigned', 'Morning', '2026-05-01', '07:00:00', '13:00:00', 159, '2026-07-20 03:08:35', 1),
(816, 14, 'Assigned', 'Morning', '2026-05-02', '07:00:00', '13:00:00', 159, '2026-07-20 03:08:35', 1),
(817, 14, 'Assigned', 'Mid', '2026-05-04', '11:00:00', '17:00:00', 160, '2026-07-20 03:08:35', 1),
(818, 14, 'Assigned', 'Night', '2026-05-05', '17:00:00', '23:00:00', 159, '2026-07-20 03:08:35', 1),
(819, 14, 'Assigned', 'Night', '2026-05-07', '17:00:00', '23:00:00', 162, '2026-07-20 03:08:35', 1),
(820, 14, 'Assigned', 'Mid', '2026-05-12', '11:00:00', '17:00:00', 161, '2026-07-20 03:08:35', 1),
(821, 14, 'Assigned', 'Morning', '2026-05-13', '07:00:00', '13:00:00', 161, '2026-07-20 03:08:35', 1),
(822, 14, 'Assigned', 'Mid', '2026-05-18', '11:00:00', '17:00:00', 161, '2026-07-20 03:08:35', 1),
(823, 14, 'Assigned', 'Mid', '2026-05-20', '11:00:00', '17:00:00', 161, '2026-07-20 03:08:35', 1),
(824, 14, 'Assigned', 'Night', '2026-05-25', '17:00:00', '23:00:00', 159, '2026-07-20 03:08:35', 1),
(825, 14, 'Assigned', 'Morning', '2026-05-29', '07:00:00', '13:00:00', 161, '2026-07-20 03:08:35', 1),
(826, 14, 'Assigned', 'Morning', '2026-06-01', '07:00:00', '13:00:00', 160, '2026-07-20 03:08:35', 1),
(827, 14, 'Assigned', 'Mid', '2026-06-03', '11:00:00', '17:00:00', 162, '2026-07-20 03:08:35', 1),
(828, 14, 'Assigned', 'Morning', '2026-06-05', '07:00:00', '13:00:00', 159, '2026-07-20 03:08:35', 1),
(829, 14, 'Assigned', 'Morning', '2026-06-06', '07:00:00', '13:00:00', 159, '2026-07-20 03:08:35', 1),
(830, 14, 'Assigned', 'Morning', '2026-06-07', '07:00:00', '13:00:00', 162, '2026-07-20 03:08:35', 1),
(831, 14, 'Assigned', 'Mid', '2026-06-08', '11:00:00', '17:00:00', 159, '2026-07-20 03:08:35', 1),
(832, 14, 'Assigned', 'Morning', '2026-06-10', '07:00:00', '13:00:00', 161, '2026-07-20 03:08:35', 1),
(833, 14, 'Assigned', 'Mid', '2026-06-11', '11:00:00', '17:00:00', 160, '2026-07-20 03:08:35', 1),
(834, 14, 'Assigned', 'Mid', '2026-06-12', '11:00:00', '17:00:00', 159, '2026-07-20 03:08:35', 1),
(835, 14, 'Assigned', 'Night', '2026-06-13', '17:00:00', '23:00:00', 159, '2026-07-20 03:08:35', 1),
(836, 14, 'Assigned', 'Morning', '2026-06-20', '07:00:00', '13:00:00', 159, '2026-07-20 03:08:35', 1),
(837, 14, 'Assigned', 'Morning', '2026-06-21', '07:00:00', '13:00:00', 161, '2026-07-20 03:08:35', 1),
(838, 14, 'Assigned', 'Mid', '2026-06-22', '11:00:00', '17:00:00', 159, '2026-07-20 03:08:35', 1),
(839, 14, 'Assigned', 'Night', '2026-06-23', '17:00:00', '23:00:00', 161, '2026-07-20 03:08:35', 1),
(840, 14, 'Assigned', 'Night', '2026-06-29', '17:00:00', '23:00:00', 160, '2026-07-20 03:08:35', 1),
(841, 14, 'Assigned', 'Mid', '2026-06-30', '11:00:00', '17:00:00', 162, '2026-07-20 03:08:35', 1),
(842, 14, 'Assigned', 'Night', '2026-07-01', '17:00:00', '23:00:00', 159, '2026-07-20 03:08:35', 1),
(843, 14, 'Assigned', 'Morning', '2026-07-04', '07:00:00', '13:00:00', 161, '2026-07-20 03:08:35', 1),
(844, 14, 'Assigned', 'Night', '2026-07-05', '17:00:00', '23:00:00', 162, '2026-07-20 03:08:35', 1),
(845, 14, 'Assigned', 'Night', '2026-07-10', '17:00:00', '23:00:00', 160, '2026-07-20 03:08:35', 1),
(846, 14, 'Assigned', 'Morning', '2026-07-11', '07:00:00', '13:00:00', 160, '2026-07-20 03:08:35', 1),
(847, 14, 'Assigned', 'Morning', '2026-07-18', '07:00:00', '13:00:00', 162, '2026-07-20 03:08:35', 1),
(848, 14, 'Assigned', 'Mid', '2026-07-21', '11:00:00', '17:00:00', 161, '2026-07-20 03:08:35', 1),
(849, 14, 'Assigned', 'Mid', '2026-07-23', '11:00:00', '17:00:00', 159, '2026-07-20 03:08:35', 1),
(850, 14, 'Assigned', 'Morning', '2026-07-25', '07:00:00', '13:00:00', 162, '2026-07-20 03:08:35', 1),
(851, 14, 'Assigned', 'Morning', '2026-07-26', '07:00:00', '13:00:00', 161, '2026-07-20 03:08:35', 1),
(852, 14, 'Assigned', 'Night', '2026-07-29', '17:00:00', '23:00:00', 160, '2026-07-20 03:08:35', 1),
(853, 14, 'Assigned', 'Morning', '2026-07-30', '07:00:00', '13:00:00', 162, '2026-07-20 03:08:35', 1),
(854, 15, 'Assigned', 'Mid', '2026-04-18', '11:00:00', '17:00:00', 161, '2026-07-20 03:08:35', 1),
(855, 15, 'Assigned', 'Night', '2026-04-22', '17:00:00', '23:00:00', 159, '2026-07-20 03:08:35', 1),
(856, 15, 'Assigned', 'Morning', '2026-04-29', '07:00:00', '13:00:00', 159, '2026-07-20 03:08:35', 1),
(857, 15, 'Assigned', 'Mid', '2026-05-05', '11:00:00', '17:00:00', 161, '2026-07-20 03:08:35', 1),
(858, 15, 'Assigned', 'Mid', '2026-05-06', '11:00:00', '17:00:00', 161, '2026-07-20 03:08:35', 1),
(859, 15, 'Assigned', 'Mid', '2026-05-07', '11:00:00', '17:00:00', 159, '2026-07-20 03:08:35', 1),
(860, 15, 'Assigned', 'Night', '2026-05-12', '17:00:00', '23:00:00', 159, '2026-07-20 03:08:35', 1),
(861, 15, 'Assigned', 'Night', '2026-05-14', '17:00:00', '23:00:00', 162, '2026-07-20 03:08:35', 1),
(862, 15, 'Assigned', 'Morning', '2026-05-15', '07:00:00', '13:00:00', 161, '2026-07-20 03:08:35', 1),
(863, 15, 'Assigned', 'Morning', '2026-05-16', '07:00:00', '13:00:00', 160, '2026-07-20 03:08:35', 1),
(864, 15, 'Assigned', 'Morning', '2026-05-19', '07:00:00', '13:00:00', 162, '2026-07-20 03:08:35', 1),
(865, 15, 'Assigned', 'Mid', '2026-05-20', '11:00:00', '17:00:00', 161, '2026-07-20 03:08:35', 1),
(866, 15, 'Assigned', 'Mid', '2026-05-22', '11:00:00', '17:00:00', 161, '2026-07-20 03:08:35', 1),
(867, 15, 'Assigned', 'Morning', '2026-05-25', '07:00:00', '13:00:00', 161, '2026-07-20 03:08:35', 1),
(868, 15, 'Assigned', 'Morning', '2026-05-27', '07:00:00', '13:00:00', 161, '2026-07-20 03:08:35', 1),
(869, 15, 'Assigned', 'Mid', '2026-05-28', '11:00:00', '17:00:00', 162, '2026-07-20 03:08:35', 1),
(870, 15, 'Assigned', 'Night', '2026-05-30', '17:00:00', '23:00:00', 159, '2026-07-20 03:08:35', 1),
(871, 15, 'Assigned', 'Night', '2026-05-31', '17:00:00', '23:00:00', 161, '2026-07-20 03:08:35', 1),
(872, 15, 'Assigned', 'Morning', '2026-06-03', '07:00:00', '13:00:00', 162, '2026-07-20 03:08:35', 1),
(873, 15, 'Assigned', 'Morning', '2026-06-04', '07:00:00', '13:00:00', 160, '2026-07-20 03:08:35', 1),
(874, 15, 'Assigned', 'Morning', '2026-06-06', '07:00:00', '13:00:00', 159, '2026-07-20 03:08:35', 1),
(875, 15, 'Assigned', 'Morning', '2026-06-13', '07:00:00', '13:00:00', 160, '2026-07-20 03:08:35', 1),
(876, 15, 'Assigned', 'Morning', '2026-06-16', '07:00:00', '13:00:00', 161, '2026-07-20 03:08:35', 1),
(877, 15, 'Assigned', 'Morning', '2026-06-21', '07:00:00', '13:00:00', 159, '2026-07-20 03:08:35', 1),
(878, 15, 'Assigned', 'Mid', '2026-06-26', '11:00:00', '17:00:00', 162, '2026-07-20 03:08:35', 1),
(879, 15, 'Assigned', 'Night', '2026-06-29', '17:00:00', '23:00:00', 162, '2026-07-20 03:08:35', 1),
(880, 15, 'Assigned', 'Night', '2026-06-30', '17:00:00', '23:00:00', 161, '2026-07-20 03:08:35', 1),
(881, 15, 'Assigned', 'Morning', '2026-07-01', '07:00:00', '13:00:00', 159, '2026-07-20 03:08:35', 1),
(882, 15, 'Assigned', 'Mid', '2026-07-02', '11:00:00', '17:00:00', 159, '2026-07-20 03:08:35', 1),
(883, 15, 'Assigned', 'Morning', '2026-07-03', '07:00:00', '13:00:00', 159, '2026-07-20 03:08:35', 1),
(884, 15, 'Assigned', 'Mid', '2026-07-08', '11:00:00', '17:00:00', 160, '2026-07-20 03:08:35', 1),
(885, 15, 'Assigned', 'Morning', '2026-07-09', '07:00:00', '13:00:00', 161, '2026-07-20 03:08:35', 1),
(886, 15, 'Assigned', 'Mid', '2026-07-11', '11:00:00', '17:00:00', 160, '2026-07-20 03:08:35', 1),
(887, 15, 'Assigned', 'Morning', '2026-07-14', '07:00:00', '13:00:00', 162, '2026-07-20 03:08:35', 1),
(888, 15, 'Assigned', 'Mid', '2026-07-16', '11:00:00', '17:00:00', 159, '2026-07-20 03:08:35', 1),
(889, 15, 'Assigned', 'Night', '2026-07-18', '17:00:00', '23:00:00', 159, '2026-07-20 03:08:35', 1),
(890, NULL, 'Open', 'Night', '2026-07-21', '17:00:00', '23:00:00', 159, '2026-07-20 03:08:35', 1),
(891, NULL, 'Open', 'Morning', '2026-07-23', '07:00:00', '13:00:00', 159, '2026-07-20 03:08:35', 1),
(892, NULL, 'Open', 'Mid', '2026-07-24', '11:00:00', '17:00:00', 159, '2026-07-20 03:08:35', 1),
(893, 15, 'Assigned', 'Morning', '2026-07-29', '07:00:00', '13:00:00', 159, '2026-07-20 03:08:35', 1),
(894, 15, 'Assigned', 'Mid', '2026-07-31', '11:00:00', '17:00:00', 161, '2026-07-20 03:08:35', 1),
(895, 16, 'Assigned', 'Night', '2026-07-04', '17:00:00', '23:00:00', 161, '2026-07-20 03:08:35', 1),
(896, 16, 'Assigned', 'Mid', '2026-07-05', '11:00:00', '17:00:00', 162, '2026-07-20 03:08:35', 1),
(897, 16, 'Assigned', 'Morning', '2026-07-06', '07:00:00', '13:00:00', 159, '2026-07-20 03:08:35', 1),
(898, 16, 'Assigned', 'Night', '2026-07-07', '17:00:00', '23:00:00', 159, '2026-07-20 03:08:35', 1),
(899, 16, 'Assigned', 'Morning', '2026-07-10', '07:00:00', '13:00:00', 159, '2026-07-20 03:08:35', 1),
(900, 16, 'Assigned', 'Night', '2026-07-12', '17:00:00', '23:00:00', 159, '2026-07-20 03:08:35', 1),
(901, 16, 'Assigned', 'Night', '2026-07-20', '17:00:00', '23:00:00', 162, '2026-07-20 03:08:35', 1),
(902, 16, 'Assigned', 'Night', '2026-07-22', '17:00:00', '23:00:00', 159, '2026-07-20 03:08:35', 1),
(903, 16, 'Assigned', 'Morning', '2026-07-23', '07:00:00', '13:00:00', 161, '2026-07-20 03:08:35', 1),
(904, 16, 'Assigned', 'Morning', '2026-07-24', '07:00:00', '13:00:00', 159, '2026-07-20 03:08:35', 1),
(905, 16, 'Assigned', 'Night', '2026-07-25', '17:00:00', '23:00:00', 162, '2026-07-20 03:08:35', 1),
(906, 16, 'Assigned', 'Morning', '2026-07-26', '07:00:00', '13:00:00', 162, '2026-07-20 03:08:35', 1),
(907, 16, 'Assigned', 'Morning', '2026-07-27', '07:00:00', '13:00:00', 161, '2026-07-20 03:08:35', 1),
(908, 16, 'Assigned', 'Mid', '2026-07-28', '11:00:00', '17:00:00', 160, '2026-07-20 03:08:35', 1),
(909, 17, 'Assigned', 'Mid', '2026-07-16', '11:00:00', '17:00:00', 159, '2026-07-20 03:08:35', 1),
(910, 17, 'Assigned', 'Morning', '2026-07-17', '07:00:00', '13:00:00', 162, '2026-07-20 03:08:35', 1),
(911, 17, 'Assigned', 'Mid', '2026-07-19', '11:00:00', '17:00:00', 159, '2026-07-20 03:08:35', 1),
(912, 17, 'Assigned', 'Night', '2026-07-20', '17:00:00', '23:00:00', 161, '2026-07-20 03:08:35', 1),
(913, 17, 'Assigned', 'Night', '2026-07-23', '17:00:00', '23:00:00', 162, '2026-07-20 03:08:35', 1),
(914, 17, 'Assigned', 'Night', '2026-07-24', '17:00:00', '23:00:00', 159, '2026-07-20 03:08:35', 1),
(915, 17, 'Assigned', 'Night', '2026-07-26', '17:00:00', '23:00:00', 160, '2026-07-20 03:08:35', 1),
(916, 17, 'Assigned', 'Night', '2026-07-27', '17:00:00', '23:00:00', 162, '2026-07-20 03:08:35', 1),
(917, 17, 'Assigned', 'Morning', '2026-07-28', '07:00:00', '13:00:00', 162, '2026-07-20 03:08:35', 1),
(918, 17, 'Assigned', 'Morning', '2026-07-29', '07:00:00', '13:00:00', 159, '2026-07-20 03:08:35', 1),
(919, 17, 'Assigned', 'Morning', '2026-07-31', '07:00:00', '13:00:00', 160, '2026-07-20 03:08:35', 1),
(920, 18, 'Assigned', 'Mid', '2026-06-27', '11:00:00', '17:00:00', 162, '2026-07-20 03:08:35', 1),
(921, 18, 'Assigned', 'Morning', '2026-06-28', '07:00:00', '13:00:00', 160, '2026-07-20 03:08:35', 1),
(922, 18, 'Assigned', 'Morning', '2026-06-29', '07:00:00', '13:00:00', 160, '2026-07-20 03:08:35', 1),
(923, 18, 'Assigned', 'Night', '2026-07-01', '17:00:00', '23:00:00', 160, '2026-07-20 03:08:35', 1),
(924, 18, 'Assigned', 'Morning', '2026-07-02', '07:00:00', '13:00:00', 160, '2026-07-20 03:08:35', 1),
(925, 18, 'Assigned', 'Mid', '2026-07-04', '11:00:00', '17:00:00', 159, '2026-07-20 03:08:35', 1),
(926, 18, 'Assigned', 'Night', '2026-07-06', '17:00:00', '23:00:00', 160, '2026-07-20 03:08:35', 1),
(927, 18, 'Assigned', 'Night', '2026-07-07', '17:00:00', '23:00:00', 162, '2026-07-20 03:08:35', 1),
(928, 18, 'Assigned', 'Morning', '2026-07-10', '07:00:00', '13:00:00', 162, '2026-07-20 03:08:35', 1),
(929, 18, 'Assigned', 'Mid', '2026-07-23', '11:00:00', '17:00:00', 161, '2026-07-20 03:08:35', 1),
(930, 18, 'Assigned', 'Mid', '2026-07-25', '11:00:00', '17:00:00', 159, '2026-07-20 03:08:35', 1),
(931, 18, 'Assigned', 'Night', '2026-07-26', '17:00:00', '23:00:00', 159, '2026-07-20 03:08:35', 1),
(932, 18, 'Assigned', 'Night', '2026-07-27', '17:00:00', '23:00:00', 161, '2026-07-20 03:08:35', 1),
(933, 18, 'Assigned', 'Morning', '2026-07-30', '07:00:00', '13:00:00', 159, '2026-07-20 03:08:35', 1),
(934, 18, 'Assigned', 'Night', '2026-07-31', '17:00:00', '23:00:00', 161, '2026-07-20 03:08:35', 1),
(937, 6, 'Assigned', 'Morning', '2026-07-27', '07:00:00', '15:00:00', 159, '2026-07-20 03:12:13', 1),
(938, 3, 'Assigned', 'Mid', '2026-08-01', '11:00:00', '19:00:00', 159, '2026-07-26 23:23:43', 1),
(939, 10, 'Assigned', 'Morning', '2026-08-03', '07:00:00', '15:00:00', 1, '2026-07-28 16:25:56', 1),
(940, 23, 'Assigned', 'Morning', '2026-08-03', '07:00:00', '15:00:00', 159, '2026-07-28 16:59:10', 1),
(942, 10, 'Assigned', 'Mid', '2026-09-07', '11:00:00', '19:00:00', 1, '2026-09-03 20:46:07', 1);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `value` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `name`, `value`) VALUES
(1, 'company_name', 'Slow Hours'),
(2, 'company_address', 'Bayan, Dasmarinas City, Cavite'),
(3, 'app_url', 'http://localhost/again'),
(4, 'email_from', 'noreply@cafehrms.local'),
(5, 'theme_mode', 'light'),
(6, 'kiosk_pin', '1234'),
(7, 'smtp_host', 'smtp.gmail.com'),
(8, 'smtp_port', '587'),
(9, 'smtp_user', 'danlagterence01@gmail.com'),
(10, 'smtp_pass', 'dzbo diuy rzna fapa'),
(11, 'smtp_from_email', 'noreply@slow.hours'),
(12, 'smtp_from_name', 'Slow Hours Cafe');

-- --------------------------------------------------------

--
-- Table structure for table `training`
--

CREATE TABLE `training` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Scheduled',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `name` varchar(200) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int NOT NULL,
  `employee_id` int DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL,
  `branch_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `email`, `password`, `role_id`, `employee_id`, `verified`, `created_at`, `last_login`, `branch_id`) VALUES
(1, 'System Admin', 'admin', 'danlagterence01@gmail.com', '$2y$10$VYNJ5jSn/mdsGEdoyTDqqOfn09pExUx0C912BrW/2ODH904A9r1AG', 1, NULL, 1, '2026-07-18 16:32:49', NULL, NULL),
(141, 'Juan Dela Cruz', 'juan.dela.cruz', 'juandelacruz@cafehrms.local', '$2y$10$mbHZwxA2mur7BVt19fjduOfK8D3n7Z/8rCHYFWteuLdsmpKFj1Hdm', 5, 1, 1, '2026-07-20 03:05:42', NULL, 1),
(142, 'Maria Clara', 'maria.clara', 'mariaclara@cafehrms.local', '$2y$10$kOQ.whjNWleQvwv4m7eeWOZJSbcEi5Wu/UrdSzReZ0EhjFx4Ga4ui', 8, 2, 1, '2026-07-20 03:05:42', NULL, 1),
(143, 'Jose Rizal', 'jose.rizal', 'joserizal@cafehrms.local', '$2y$10$kOQ.whjNWleQvwv4m7eeWOZJSbcEi5Wu/UrdSzReZ0EhjFx4Ga4ui', 8, 3, 1, '2026-07-20 03:05:42', NULL, 1),
(144, 'Andres Bonifacio', 'andres.bonifacio', 'andresbonifacio@cafehrms.local', '$2y$10$kOQ.whjNWleQvwv4m7eeWOZJSbcEi5Wu/UrdSzReZ0EhjFx4Ga4ui', 8, 4, 1, '2026-07-20 03:05:42', NULL, 1),
(145, 'Emilio Aguinaldo', 'emilio.aguinaldo', 'emilioaguinaldo@cafehrms.local', '$2y$10$kOQ.whjNWleQvwv4m7eeWOZJSbcEi5Wu/UrdSzReZ0EhjFx4Ga4ui', 8, 5, 1, '2026-07-20 03:05:42', NULL, 1),
(146, 'Apolinario Mabini', 'apolinario.mabini', 'apolinariomabini@cafehrms.local', '$2y$10$kOQ.whjNWleQvwv4m7eeWOZJSbcEi5Wu/UrdSzReZ0EhjFx4Ga4ui', 8, 6, 1, '2026-07-20 03:05:43', NULL, 1),
(147, 'Marcelo Del Pilar', 'marcelo.del.pilar', 'marcelodelpilar@cafehrms.local', '$2y$10$kOQ.whjNWleQvwv4m7eeWOZJSbcEi5Wu/UrdSzReZ0EhjFx4Ga4ui', 8, 7, 1, '2026-07-20 03:05:43', NULL, 1),
(148, 'Gabriela Silang', 'gabriela.silang', 'gabrielasilang@cafehrms.local', '$2y$10$kOQ.whjNWleQvwv4m7eeWOZJSbcEi5Wu/UrdSzReZ0EhjFx4Ga4ui', 5, 8, 1, '2026-07-20 03:05:43', NULL, 1),
(149, 'Melchora Aquino', 'melchora.aquino', 'melchoraaquino@cafehrms.local', '$2y$10$kOQ.whjNWleQvwv4m7eeWOZJSbcEi5Wu/UrdSzReZ0EhjFx4Ga4ui', 8, 9, 1, '2026-07-20 03:05:43', NULL, 1),
(150, 'Antonio Luna', 'antonio.luna', 'antonioluna@cafehrms.local', '$2y$10$kOQ.whjNWleQvwv4m7eeWOZJSbcEi5Wu/UrdSzReZ0EhjFx4Ga4ui', 8, 10, 1, '2026-07-20 03:05:43', NULL, 1),
(151, 'Lapu Lapu', 'lapu.lapu', 'lapulapu@cafehrms.local', '$2y$10$kOQ.whjNWleQvwv4m7eeWOZJSbcEi5Wu/UrdSzReZ0EhjFx4Ga4ui', 8, 11, 1, '2026-07-20 03:05:43', NULL, 1),
(152, 'Diego Silang', 'diego.silang', 'diegosilang@cafehrms.local', '$2y$10$kOQ.whjNWleQvwv4m7eeWOZJSbcEi5Wu/UrdSzReZ0EhjFx4Ga4ui', 5, 12, 1, '2026-07-20 03:05:43', NULL, 1),
(153, 'Gregorio DelPilar', 'gregorio.delpilar', 'gregoriodelpilar@cafehrms.local', '$2y$10$kOQ.whjNWleQvwv4m7eeWOZJSbcEi5Wu/UrdSzReZ0EhjFx4Ga4ui', 8, 13, 1, '2026-07-20 03:05:43', NULL, 1),
(154, 'Emilio Jacinto', 'emilio.jacinto', 'emiliojacinto@cafehrms.local', '$2y$10$kOQ.whjNWleQvwv4m7eeWOZJSbcEi5Wu/UrdSzReZ0EhjFx4Ga4ui', 8, 14, 1, '2026-07-20 03:05:43', NULL, 1),
(155, 'Miguel Malvar', 'miguel.malvar', 'miguelmalvar@cafehrms.local', '$2y$10$kOQ.whjNWleQvwv4m7eeWOZJSbcEi5Wu/UrdSzReZ0EhjFx4Ga4ui', 8, 15, 1, '2026-07-20 03:05:43', NULL, 1),
(156, 'Macario Sakay', 'macario.sakay', 'macariosakay@cafehrms.local', '$2y$10$kOQ.whjNWleQvwv4m7eeWOZJSbcEi5Wu/UrdSzReZ0EhjFx4Ga4ui', 8, 16, 1, '2026-07-20 03:05:43', NULL, 1),
(157, 'Teresa Magbanua', 'teresa.magbanua', 'teresamagbanua@cafehrms.local', '$2y$10$kOQ.whjNWleQvwv4m7eeWOZJSbcEi5Wu/UrdSzReZ0EhjFx4Ga4ui', 8, 17, 1, '2026-07-20 03:05:43', NULL, 1),
(158, 'Trinidad Tecson', 'trinidad.tecson', 'trinidadtecson@cafehrms.local', '$2y$10$kOQ.whjNWleQvwv4m7eeWOZJSbcEi5Wu/UrdSzReZ0EhjFx4Ga4ui', 8, 18, 1, '2026-07-20 03:05:43', NULL, 1),
(159, 'Manuel Quezon', 'manuel.quezon', 'manuelquezon@cafehrms.local', '$2y$10$kOQ.whjNWleQvwv4m7eeWOZJSbcEi5Wu/UrdSzReZ0EhjFx4Ga4ui', 7, 19, 1, '2026-07-20 03:05:43', NULL, NULL),
(160, 'Sergio Osmena', 'sergio.osmena', 'sergioosmena@cafehrms.local', '$2y$10$kOQ.whjNWleQvwv4m7eeWOZJSbcEi5Wu/UrdSzReZ0EhjFx4Ga4ui', 7, 20, 1, '2026-07-20 03:05:43', NULL, NULL),
(161, 'Jose Laurel', 'jose.laurel', 'joselaurel@cafehrms.local', '$2y$10$kOQ.whjNWleQvwv4m7eeWOZJSbcEi5Wu/UrdSzReZ0EhjFx4Ga4ui', 7, 21, 1, '2026-07-20 03:05:43', NULL, NULL),
(162, 'Ramon Magsaysay', 'ramon.magsaysay', 'ramonmagsaysay@cafehrms.local', '$2y$10$kOQ.whjNWleQvwv4m7eeWOZJSbcEi5Wu/UrdSzReZ0EhjFx4Ga4ui', 7, 22, 1, '2026-07-20 03:05:43', NULL, NULL),
(163, 'System Kiosk', 'kiosk', 'kiosk@cafehrms.local', '$2y$10$JvaOen0Va6/1UDTGO/1aIe6y4k8eNFIVvGH2N5W60c/VXN6KmhkB2', 12, NULL, 1, '2026-07-20 09:47:05', NULL, 1),
(164, 'Tablet Kiosk', 'tablet-kiosk', 'tabletkiosk@hrms.cafe', '$2y$10$VYNJ5jSn/mdsGEdoyTDqqOfn09pExUx0C912BrW/2ODH904A9r1AG', 4, NULL, 1, '2026-07-27 23:02:02', NULL, 1),
(165, 'Cashier Terminal 1', 'cashier_term1', 'cashier@hrms.cafe', '$2y$10$2hOgOwXQS7JGdG3wGD66w.7vIj9BoQkXSss9.uiBHQ4UNzs7H3qL6', 9, NULL, 1, '2026-07-27 23:10:47', NULL, 1),
(166, 'Kristan Kyle Ante', 'kristan.ante', 'kristankyleante@gmail.com', '$2y$10$Ns.BVQLLhcBhIu/c28csouURLOtSC92GcaiiXKUhR0QmKXUGLXq9W', 8, 23, 1, '2026-07-28 16:20:13', NULL, 1),
(167, 'Cashier Terminal 2', 'main_cash_term2', 'main_cash_term2_1788732170@system.local', '$2y$10$Ms30DlMkdZj/66j6vABP9ej4Dxp30g2vViYqTfD54NVMy1thhKKAG', 9, NULL, 1, '2026-09-07 06:02:50', NULL, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `applicants`
--
ALTER TABLE `applicants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_applicants_branch` (`branch_id`);

--
-- Indexes for table `applicant_logs`
--
ALTER TABLE `applicant_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `applicant_id` (`applicant_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `branch_budgets`
--
ALTER TABLE `branch_budgets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_branch_month` (`branch_id`,`budget_month`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_employees_branch` (`branch_id`);

--
-- Indexes for table `equipment_assignments`
--
ALTER TABLE `equipment_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `global_notifications`
--
ALTER TABLE `global_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `read_by` (`read_by`);

--
-- Indexes for table `interviews`
--
ALTER TABLE `interviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `applicant_id` (`applicant_id`);

--
-- Indexes for table `interview_scorecards`
--
ALTER TABLE `interview_scorecards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `interview_id` (`interview_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leaves`
--
ALTER TABLE `leaves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `leave_approvals`
--
ALTER TABLE `leave_approvals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_approval` (`leave_id`,`approver_id`),
  ADD KEY `approver_id` (`approver_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `open_shifts`
--
ALTER TABLE `open_shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `original_employee_id` (`original_employee_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `pending_bonuses`
--
ALTER TABLE `pending_bonuses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `evaluator_id` (`evaluator_id`);

--
-- Indexes for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `emp_date` (`employee_id`,`shift_date`),
  ADD KEY `fk_sched_assign_by` (`assigned_by`),
  ADD KEY `fk_schedules_branch` (`branch_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `training`
--
ALTER TABLE `training`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `fk_users_branch` (`branch_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=253;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `applicants`
--
ALTER TABLE `applicants`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `applicant_logs`
--
ALTER TABLE `applicant_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=814;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `branch_budgets`
--
ALTER TABLE `branch_budgets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `equipment_assignments`
--
ALTER TABLE `equipment_assignments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `global_notifications`
--
ALTER TABLE `global_notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `interviews`
--
ALTER TABLE `interviews`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `interview_scorecards`
--
ALTER TABLE `interview_scorecards`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leaves`
--
ALTER TABLE `leaves`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `leave_approvals`
--
ALTER TABLE `leave_approvals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `open_shifts`
--
ALTER TABLE `open_shifts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=171;

--
-- AUTO_INCREMENT for table `pending_bonuses`
--
ALTER TABLE `pending_bonuses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=944;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `training`
--
ALTER TABLE `training`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=168;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `applicants`
--
ALTER TABLE `applicants`
  ADD CONSTRAINT `fk_applicants_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `applicant_logs`
--
ALTER TABLE `applicant_logs`
  ADD CONSTRAINT `applicant_logs_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `branch_budgets`
--
ALTER TABLE `branch_budgets`
  ADD CONSTRAINT `branch_budgets_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD CONSTRAINT `email_verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `fk_employees_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `equipment_assignments`
--
ALTER TABLE `equipment_assignments`
  ADD CONSTRAINT `equipment_assignments_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `global_notifications`
--
ALTER TABLE `global_notifications`
  ADD CONSTRAINT `global_notifications_ibfk_1` FOREIGN KEY (`read_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `interviews`
--
ALTER TABLE `interviews`
  ADD CONSTRAINT `interviews_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `interview_scorecards`
--
ALTER TABLE `interview_scorecards`
  ADD CONSTRAINT `interview_scorecards_ibfk_1` FOREIGN KEY (`interview_id`) REFERENCES `interviews` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leaves`
--
ALTER TABLE `leaves`
  ADD CONSTRAINT `leaves_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_approvals`
--
ALTER TABLE `leave_approvals`
  ADD CONSTRAINT `leave_approvals_ibfk_1` FOREIGN KEY (`leave_id`) REFERENCES `leaves` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_approvals_ibfk_2` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `open_shifts`
--
ALTER TABLE `open_shifts`
  ADD CONSTRAINT `open_shifts_ibfk_1` FOREIGN KEY (`original_employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll`
--
ALTER TABLE `payroll`
  ADD CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pending_bonuses`
--
ALTER TABLE `pending_bonuses`
  ADD CONSTRAINT `pending_bonuses_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  ADD CONSTRAINT `performance_reviews_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `performance_reviews_ibfk_2` FOREIGN KEY (`evaluator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `fk_sched_assign_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_schedules_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
