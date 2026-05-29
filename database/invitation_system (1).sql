-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 26, 2026 at 06:16 AM
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
-- Database: `invitation_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `invite_token` varchar(255) DEFAULT NULL,
  `otp` varchar(10) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 0,
  `role` enum('admin','user') DEFAULT 'user',
  `inviter` varchar(255) DEFAULT NULL,
  `inviter_email` varchar(150) DEFAULT NULL,
  `invite_accepted` enum('yes','no') DEFAULT 'no',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `inviter_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `invite_token`, `otp`, `otp_expiry`, `is_verified`, `is_active`, `role`, `inviter`, `inviter_email`, `invite_accepted`, `created_at`, `inviter_name`) VALUES
(1, 'Drivault Admin', 'admin@drivault.com', '9999999999', '$2y$10$yU1qVzGY7RYUa/n.4tRD7OhSPHEW6jEE4erif0mN4D/2dZZLSG7Ma', NULL, NULL, NULL, 1, 1, 'admin', NULL, NULL, 'no', '2026-05-25 07:32:35', NULL),
(6, 'rajum', 'tikoro8177@nuitx.com', '9856236523', '$2y$10$QUSpqKyX8sMD92HyTKBD6.D2y8OWCz8O90LshIwRLe45MWaLRLC82', '0ea8481860a75a3e74d04b141d0ae2145613b37b5ecfd5c94b7e624fc84082be', '1234', '2026-05-25 17:23:09', 1, 1, 'user', 'Team Drivault', '7892660797', 'yes', '2026-05-25 10:37:21', NULL),
(7, 'maria', 'sarin56954@okcpress.com', '9898569123', '$2y$10$b0yu9DCQoM.o3i0pyCRBP.VRxach7a/YfYNcOfbvmXt1q4pthCYAG', '96af01855e14190bc2cc61d2baab05439cc0016a46cb87bcf2c8990ec094ed5e', '123456', '2026-05-25 17:24:15', 1, 1, 'user', 'Team Drivault', '7892660797', 'yes', '2026-05-25 11:40:02', NULL),
(10, 'vjiijij', 'sosesij460@nuitx.com', '9665322332', '$2y$10$7WBAJeNILzMBf4lYKk/aGOt9n.Qq4hixWs3MLoAwNRfQIuwp1C74y', '80e292038d540120def51e85eac2f610c671e868c72a253e54c7bad565654ef2', '123456', '2026-05-25 17:43:43', 1, 1, 'user', 'Team Drivault', '7892660797', 'yes', '2026-05-25 12:07:11', NULL),
(13, 'vishalgoa', 'vishalgouadr143@gmail.com', '8120220202', NULL, 'db858e0cd53790c4e2135c44598c64c5d6465102114b7eedf17235d288737ab5', NULL, NULL, 0, 0, 'user', 'Team Drivault', '7892660797', 'no', '2026-05-25 13:39:28', NULL),
(14, 'irri', 'Visjdsfj@kgkg.com', '7892662666', NULL, 'a49cd36f12e475f8fe2cc5151a7a347e492ff14d9d450da40df7b7fd8198ecdd', NULL, NULL, 0, 0, 'user', 'Team Drivault', 'haler19082@gzeos.com', 'no', '2026-05-26 04:00:02', NULL);

--
-- Indexes for dumped tables
--

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
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
