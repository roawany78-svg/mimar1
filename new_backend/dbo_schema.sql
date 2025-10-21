-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 17, 2025 at 06:02 PM
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
-- Database: `dbo_schema`
--

-- --------------------------------------------------------

--
-- Table structure for table `attachment`
--

CREATE TABLE `attachment` (
  `attachment_id` int(11) NOT NULL,
  `file_name` varchar(400) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_path` varchar(600) DEFAULT NULL,
  `file_category` enum('contract','attachment') DEFAULT 'attachment',
  `uploaded_at` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  `project_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attachment`
--

INSERT INTO `attachment` (`attachment_id`, `file_name`, `file_type`, `file_path`, `file_category`, `uploaded_at`, `project_id`) VALUES
(1, 'renad_Ass1.pdf', 'application/pdf', 'contract_68eed5e549fd8.pdf', 'contract', '2025-10-15 01:59:49.314', 2),
(2, 'image_2.png', 'image/png', 'attachment_68eed5e5518fd_0.png', 'attachment', '2025-10-15 01:59:49.337', 2),
(3, 'image_3.png', 'image/png', 'attachment_68eed5e552aa6_1.png', 'attachment', '2025-10-15 01:59:49.340', 2),
(4, 'proposal.pdf', 'application/pdf', 'contract_68efadfe4fec1.pdf', 'contract', '2025-10-15 17:21:50.336', 3),
(5, 'project-1-DG-Grjnd.jpg', 'image/jpeg', 'attachment_68efadfe5885a_0.jpg', 'attachment', '2025-10-15 17:21:50.366', 3);

-- --------------------------------------------------------

--
-- Table structure for table `clientprofile`
--

CREATE TABLE `clientprofile` (
  `client_id` int(11) NOT NULL,
  `location` varchar(300) DEFAULT NULL,
  `profile_image` varchar(600) DEFAULT NULL,
  `member_since` date NOT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT current_timestamp(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clientprofile`
--

INSERT INTO `clientprofile` (`client_id`, `location`, `profile_image`, `member_since`, `created_at`) VALUES
(9, NULL, NULL, '2025-10-14', '2025-10-14 18:51:27.274'),
(10, NULL, NULL, '2025-10-14', '2025-10-14 18:52:57.356'),
(11, NULL, NULL, '2025-10-14', '2025-10-14 18:54:45.864'),
(13, NULL, NULL, '2025-10-17', '2025-10-17 16:31:40.822');

-- --------------------------------------------------------

--
-- Table structure for table `contractorasset`
--

CREATE TABLE `contractorasset` (
  `asset_id` int(11) NOT NULL,
  `contractor_id` int(11) NOT NULL,
  `asset_type` enum('profile','portfolio','cert') NOT NULL DEFAULT 'profile',
  `file_path` varchar(800) NOT NULL,
  `caption` varchar(500) DEFAULT NULL,
  `uploaded_at` datetime(3) NOT NULL DEFAULT current_timestamp(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contractorcertification`
--

CREATE TABLE `contractorcertification` (
  `cert_id` int(11) NOT NULL,
  `contractor_id` int(11) NOT NULL,
  `cert_name` varchar(300) NOT NULL,
  `issuer` varchar(300) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `credential_url` varchar(600) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT current_timestamp(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contractorcertification`
--

INSERT INTO `contractorcertification` (`cert_id`, `contractor_id`, `cert_name`, `issuer`, `issue_date`, `credential_url`, `created_at`) VALUES
(3, 6, 'Saudi Building Code Certified', NULL, NULL, NULL, '2025-10-12 18:46:10.516'),
(4, 6, 'hhhhhhhhhhhhhhhhhh', NULL, NULL, NULL, '2025-10-12 18:46:10.517'),
(5, 7, 'Saudi Building Code Certified', NULL, NULL, NULL, '2025-10-12 22:29:41.901'),
(6, 7, 'Green Building Certified', NULL, NULL, NULL, '2025-10-12 22:29:41.902'),
(7, 8, 'Project Management Professional (PMP)', NULL, NULL, NULL, '2025-10-13 02:20:43.812'),
(8, 8, 'Project Management Professional (PMP)2', NULL, NULL, NULL, '2025-10-13 02:20:43.812');

-- --------------------------------------------------------

--
-- Table structure for table `contractorprofile`
--

CREATE TABLE `contractorprofile` (
  `contractor_id` int(11) NOT NULL,
  `specialization` varchar(200) DEFAULT NULL,
  `license_number` varchar(200) DEFAULT NULL,
  `location` varchar(300) DEFAULT NULL,
  `about` longtext DEFAULT NULL,
  `experience_years` tinyint(3) UNSIGNED DEFAULT NULL,
  `profile_image` varchar(600) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT current_timestamp(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contractorprofile`
--

INSERT INTO `contractorprofile` (`contractor_id`, `specialization`, `license_number`, `location`, `about`, `experience_years`, `profile_image`, `created_at`) VALUES
(6, 'Residential Construction', 'RC-2024-001', 'Riyadh, Saudi Arabia', 'Experienced residential contractor with over 8 years in the Saudi construction industry. Specialized in luxury villa construction, home renovations, and modern architectural projects. Known for attention to detail, quality craftsmanship, and timely project completion.', 15, '/uploads/contractors/6_profile_264f2d658f52.jpg', '2025-10-12 18:29:57.000'),
(7, 'Interior Design', 'ID-2024-003', 'Dammam', 'Experienced residential contractor with over 8 years in the Saudi construction industry. Specialized in luxury villa construction, home renovations, and modern architectural projects. Known for attention to detail, quality craftsmanship, and timely project completion.', 8, '/uploads/contractors/7_profile_1ee4128f1f50.jpg', '2025-10-12 22:29:41.000'),
(8, 'programmer', 'cv144552441', 'jadah', 'about me amalii  hhhhhhhhhhhhhhhhhhhhh', 8, '/uploads/contractors/8_profile_e9a904b0df0c.jpg', '2025-10-13 02:20:43.000');

-- --------------------------------------------------------

--
-- Table structure for table `contractorproject`
--

CREATE TABLE `contractorproject` (
  `portfolio_id` int(11) NOT NULL,
  `contractor_id` int(11) NOT NULL,
  `title` varchar(300) NOT NULL,
  `description` longtext DEFAULT NULL,
  `year_completed` smallint(5) UNSIGNED DEFAULT NULL,
  `project_url` varchar(600) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT current_timestamp(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contractorproject`
--

INSERT INTO `contractorproject` (`portfolio_id`, `contractor_id`, `title`, `description`, `year_completed`, `project_url`, `created_at`) VALUES
(3, 6, 'project1', 'project', 2020, NULL, '2025-10-12 18:46:10.517'),
(4, 6, 'pro2', 'hhhhhhhh', 1995, NULL, '2025-10-12 18:46:10.522'),
(5, 7, 'project1', 'description', 2022, NULL, '2025-10-12 22:29:41.907'),
(6, 7, 'project2', 'project2', 2020, NULL, '2025-10-12 22:29:41.907'),
(7, 8, 'project1', 'projectdesc', 2022, NULL, '2025-10-13 02:20:43.818'),
(8, 8, 'project2', 'desc', 2024, NULL, '2025-10-13 02:20:43.818');

-- --------------------------------------------------------

--
-- Table structure for table `contractorservice`
--

CREATE TABLE `contractorservice` (
  `service_id` int(11) NOT NULL,
  `contractor_id` int(11) NOT NULL,
  `service_name` varchar(200) NOT NULL,
  `details` longtext DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT current_timestamp(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contractorservice`
--

INSERT INTO `contractorservice` (`service_id`, `contractor_id`, `service_name`, `details`, `created_at`) VALUES
(3, 6, 'Residential Construction', NULL, '2025-10-12 18:46:10.511'),
(4, 6, 'hhhhhhhhhhhhhhhhhhhhh', NULL, '2025-10-12 18:46:10.515'),
(5, 6, 'hhhhhhhhhhhh', NULL, '2025-10-12 18:46:10.515'),
(6, 7, 'Residential Construction', NULL, '2025-10-12 22:29:41.894'),
(7, 7, 'Home Renovation', NULL, '2025-10-12 22:29:41.895'),
(8, 8, 'Residential Construction', NULL, '2025-10-13 02:20:43.803'),
(9, 8, 'Residential 2', NULL, '2025-10-13 02:20:43.804'),
(10, 8, 'Residential Construction3', NULL, '2025-10-13 02:20:43.804');

-- --------------------------------------------------------

--
-- Table structure for table `material`
--

CREATE TABLE `material` (
  `material_id` int(11) NOT NULL,
  `name` varchar(300) NOT NULL,
  `price` decimal(12,2) DEFAULT NULL,
  `unit` varchar(80) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  `added_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message`
--

CREATE TABLE `message` (
  `message_id` int(11) NOT NULL,
  `content` longtext NOT NULL,
  `sent_at` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification`
--

CREATE TABLE `notification` (
  `notification_id` int(11) NOT NULL,
  `content` longtext NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `offer`
--

CREATE TABLE `offer` (
  `offer_id` int(11) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `notes` longtext DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  `project_id` int(11) NOT NULL,
  `contractor_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project`
--

CREATE TABLE `project` (
  `project_id` int(11) NOT NULL,
  `order_id` varchar(100) DEFAULT NULL,
  `title` varchar(300) NOT NULL,
  `description` longtext DEFAULT NULL,
  `location` varchar(300) DEFAULT NULL,
  `status` varchar(60) NOT NULL DEFAULT 'open',
  `estimated_cost` decimal(12,2) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `project_contract` varchar(600) DEFAULT NULL,
  `client_id` int(11) NOT NULL,
  `accepted_contractor_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `project`
--

INSERT INTO `project` (`project_id`, `order_id`, `title`, `description`, `location`, `status`, `estimated_cost`, `created_at`, `start_date`, `end_date`, `project_contract`, `client_id`, `accepted_contractor_id`) VALUES
(1, '1111111', ' e.g., Modern Villa Construction Project Descri', '\r\ne.g., Modern Villa Construction\r\nProject Descri', 'Riyad', 'open', 777.00, '2025-10-14 19:07:53.763', '2025-10-14', '2025-10-05', '', 11, 8),
(2, '1111', 'Project Title', 'Project Title\r\n', 'riyath', 'in progress', 8500000.00, '2025-10-15 01:59:49.308', '2025-10-16', '2025-10-31', 'contract_68eed5e549fd8.pdf', 11, 7),
(3, '1211111111', 'Office Building Renovation', 'project_test descriptio Office Building Renovation\r\n', 'Jeddah', 'in progress', 58000000.00, '2025-10-15 17:21:50.332', '2025-10-15', '2027-02-09', 'contract_68efadfe4fec1.pdf', 11, 6);

-- --------------------------------------------------------

--
-- Table structure for table `projectspecification`
--

CREATE TABLE `projectspecification` (
  `spec_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `specification` text NOT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT current_timestamp(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `projectspecification`
--

INSERT INTO `projectspecification` (`spec_id`, `project_id`, `specification`, `created_at`) VALUES
(9, 1, 'ggggggggggg', '2025-10-14 19:07:53.772'),
(10, 1, 'jjjjjjjjjjjj', '2025-10-14 19:07:53.775'),
(11, 1, 'jjjjjjjjjjjjjjj', '2025-10-14 19:07:53.777'),
(12, 2, 'Project Specifications (one per line)', '2025-10-15 01:59:49.320'),
(13, 2, '?? Reinforced concrete foundation', '2025-10-15 01:59:49.323'),
(14, 2, '?? Steel frame construction', '2025-10-15 01:59:49.330'),
(15, 2, '?? Premium ceramic tiles', '2025-10-15 01:59:49.332'),
(16, 3, 'Reinforced concrete foundation', '2025-10-15 17:21:50.339'),
(17, 3, 'Steel frame construction', '2025-10-15 17:21:50.341'),
(18, 3, 'Premium ceramic tiles', '2025-10-15 17:21:50.342'),
(19, 3, 'Central air conditioning', '2025-10-15 17:21:50.346'),
(20, 3, 'Smart home automation', '2025-10-15 17:21:50.348'),
(21, 3, 'Security system integration', '2025-10-15 17:21:50.350'),
(22, 3, 'Landscape design included', '2025-10-15 17:21:50.357'),
(23, 3, 'Two-year warranty', '2025-10-15 17:21:50.360');

-- --------------------------------------------------------

--
-- Table structure for table `rating`
--

CREATE TABLE `rating` (
  `rating_id` int(11) NOT NULL,
  `stars` tinyint(3) UNSIGNED NOT NULL,
  `comment` longtext DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  `rated_by` int(11) NOT NULL,
  `contractor_id` int(11) NOT NULL
) ;

--
-- Dumping data for table `rating`
--

INSERT INTO `rating` (`rating_id`, `stars`, `comment`, `created_at`, `rated_by`, `contractor_id`) VALUES
(1, 5, 'hhhhh', '2025-10-14 19:09:05.593', 11, 3);

-- --------------------------------------------------------

--
-- Table structure for table `savedcontractor`
--

CREATE TABLE `savedcontractor` (
  `saved_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `contractor_id` int(11) NOT NULL,
  `saved_at` datetime(3) NOT NULL DEFAULT current_timestamp(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `savedcontractor`
--

INSERT INTO `savedcontractor` (`saved_id`, `client_id`, `contractor_id`, `saved_at`) VALUES
(3, 11, 2, '2025-10-14 19:09:15.692'),
(4, 11, 3, '2025-10-14 19:09:49.909');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `email` varchar(320) NOT NULL,
  `password` varchar(512) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `role` enum('client','contractor','admin') NOT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  `status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `name`, `email`, `password`, `phone`, `role`, `created_at`, `status`) VALUES
(2, 'hhhhhh', 'aa@gmail.com', '$2y$10$7aCimVWlGC9QAeH8luQXM.vjGi2MfGo0Kg4iZxOMwZLvcCKJFdL0m', NULL, 'client', '2025-10-08 02:50:20.000', NULL),
(3, 'sham', 'adaa@gmail.com', '$2y$10$1mjTMpFByuvYnxbYsr3i7.x3xtWCUugKvM55hIkROYtinTGSDYbw.', NULL, 'client', '2025-10-08 03:52:00.000', NULL),
(4, 'ali', 'affa@gmail.coma', '$2y$10$eRc7QtpFCn5M.f6MCjhZkOa1GX5nGjjWoRm6gAE7AN7W.jCJyC3ue', NULL, 'admin', '2025-10-08 03:57:38.000', NULL),
(5, 'newwwwwwwwww', 'new@gmail.com', '$2y$10$mX.xb0P3rzTg6Fbffginb.blz4WRq42ZJj8E6GdPO3TG3XI6cC0m6', '', 'contractor', '2025-10-12 18:20:10.000', NULL),
(6, 'Ahmed Al-Saud', 'new_b@gmail.com', '$2y$10$dlM7Eos4kzYgeiRQL0Mu8un1MbVa3Kl9z1YsEj4RvqlixnbkUrlCS', '+966 11 234 5678', 'contractor', '2025-10-12 18:25:13.000', NULL),
(7, 'mona', 'mona@gmail.com', '$2y$10$JBqFYfFpgK3LyUmxLsb0NOTr1yhekGgGqkJFQ9IsaZ6hSv5JKlMPC', '+9665022552', 'contractor', '2025-10-12 22:26:56.000', NULL),
(8, 'amal', 'amal@gmail.com', '$2y$10$.R1yeT8hfbin4CFwp8A3g.7nLsEO9O35gWZoztKXQje7vMJhIMKxO', '+966 74552252', 'contractor', '2025-10-13 02:17:05.000', NULL),
(9, 'clien ali', 'client@gmail.com', '$2y$10$8aKhiybJzMWdDH2zJa7PJuUs2gqv28sboBfkoCwZ3S0JRaolWYuYa', '', 'client', '2025-10-14 18:51:27.000', NULL),
(10, 'clien ali', 'client2@gmail.com', '$2y$10$eLSjB5uHhy9Z13EIEkz/Te3ZOrRoTxb09bh.SfBEEBMQ/1.1mFJh.', '', 'client', '2025-10-14 18:52:57.000', NULL),
(11, 'client mokaram', 'client3@gmail.com', '$2y$10$kMRBN2Qq2gLblG9y9mFoqO1ujV0fCXz.rGffxFAeNTM1RMzQ3IKjC', '', 'client', '2025-10-14 18:54:45.000', NULL),
(12, 'aki', 'aki@gmail.com', '$2y$10$g7gNjx1MUtpFAdm1nQMALeIZQyn/X1rRhHR4XecQrXe0bJRHXKRES', '', 'admin', '2025-10-17 16:30:30.000', NULL),
(13, 'clientaki', 'aclientki@gmail.com', '$2y$10$k2JRAzdjnbn3LqtyaCgZ.O.rrDXhu0lB15p6YWwz/rPUmpwV6yc.e', '', 'client', '2025-10-17 16:31:40.000', NULL),
(14, 'yaml', 'yamaladmin@gmail.com', '$2y$10$O1Q/n5P/9PBDPk.WU7BxvuFb631x.e2S6m6ILjkXJpSUS2OOP5rrm', '', 'admin', '2025-10-17 16:40:37.000', NULL),
(15, 'admin', 'admin@gmail.com', '$2y$10$5hrBXhKeb3DttQ3bHgGlneGLjF3LS4H0AxA2H1kC6DRxP6.0BxOHC', '', 'admin', '2025-10-17 16:41:30.000', NULL),
(16, 'radmin', 'radmin@fmain.com', '$2y$10$df3Q0WV2K6RrAqO233T5N.IleOszw8Q8RtaY/bGQHjJF2Lx2Rqwba', '', 'admin', '2025-10-17 16:43:45.000', NULL),
(17, 'adminthree', 'adminthree@gmain.com', '$2y$10$CuRe/5F1QizX5iAfMSE1ReO4pSYI.MJJlGv2Zr9z7N7ctGVbugrfS', '', 'admin', '2025-10-17 16:58:22.000', NULL),
(18, 'TA', 'TA@gmail.com', '$2y$10$FV87uo16RbDYxO6cl1W4XOg1Q6Qt1FUWodLR8dNe2AtQgRm.DOeeO', '', 'admin', '2025-10-17 19:00:31.000', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attachment`
--
ALTER TABLE `attachment`
  ADD PRIMARY KEY (`attachment_id`),
  ADD KEY `IX_Attachment_project` (`project_id`);

--
-- Indexes for table `clientprofile`
--
ALTER TABLE `clientprofile`
  ADD PRIMARY KEY (`client_id`);

--
-- Indexes for table `contractorasset`
--
ALTER TABLE `contractorasset`
  ADD PRIMARY KEY (`asset_id`),
  ADD KEY `IX_ContractorAsset_contractor` (`contractor_id`);

--
-- Indexes for table `contractorcertification`
--
ALTER TABLE `contractorcertification`
  ADD PRIMARY KEY (`cert_id`),
  ADD KEY `IX_ContractorCert_contractor` (`contractor_id`),
  ADD KEY `IX_ContractorCert_name` (`cert_name`);

--
-- Indexes for table `contractorprofile`
--
ALTER TABLE `contractorprofile`
  ADD PRIMARY KEY (`contractor_id`),
  ADD KEY `IX_ContractorProfile_specialization` (`specialization`),
  ADD KEY `IX_ContractorProfile_location` (`location`);

--
-- Indexes for table `contractorproject`
--
ALTER TABLE `contractorproject`
  ADD PRIMARY KEY (`portfolio_id`),
  ADD KEY `IX_ContractorProject_contractor` (`contractor_id`),
  ADD KEY `IX_ContractorProject_year` (`year_completed`);

--
-- Indexes for table `contractorservice`
--
ALTER TABLE `contractorservice`
  ADD PRIMARY KEY (`service_id`),
  ADD KEY `IX_ContractorService_contractor` (`contractor_id`),
  ADD KEY `IX_ContractorService_name` (`service_name`);

--
-- Indexes for table `material`
--
ALTER TABLE `material`
  ADD PRIMARY KEY (`material_id`),
  ADD KEY `IX_Material_added_by` (`added_by`);

--
-- Indexes for table `message`
--
ALTER TABLE `message`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `IX_Message_sender` (`sender_id`),
  ADD KEY `IX_Message_receiver` (`receiver_id`);

--
-- Indexes for table `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `IX_Notification_user` (`user_id`);

--
-- Indexes for table `offer`
--
ALTER TABLE `offer`
  ADD PRIMARY KEY (`offer_id`),
  ADD KEY `IX_Offer_project` (`project_id`),
  ADD KEY `IX_Offer_contractor` (`contractor_id`);

--
-- Indexes for table `project`
--
ALTER TABLE `project`
  ADD PRIMARY KEY (`project_id`),
  ADD KEY `IX_Project_client` (`client_id`),
  ADD KEY `IX_Project_status` (`status`),
  ADD KEY `IX_Project_order_id` (`order_id`),
  ADD KEY `IX_Project_accepted_contractor` (`accepted_contractor_id`);

--
-- Indexes for table `projectspecification`
--
ALTER TABLE `projectspecification`
  ADD PRIMARY KEY (`spec_id`),
  ADD KEY `IX_ProjectSpecification_project` (`project_id`);

--
-- Indexes for table `rating`
--
ALTER TABLE `rating`
  ADD PRIMARY KEY (`rating_id`),
  ADD KEY `FK_Rating_RatedBy` (`rated_by`),
  ADD KEY `IX_Rating_contractor` (`contractor_id`);

--
-- Indexes for table `savedcontractor`
--
ALTER TABLE `savedcontractor`
  ADD PRIMARY KEY (`saved_id`),
  ADD UNIQUE KEY `UK_SavedContractor_Client_Contractor` (`client_id`,`contractor_id`),
  ADD KEY `IX_SavedContractor_client` (`client_id`),
  ADD KEY `IX_SavedContractor_contractor` (`contractor_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `IX_User_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attachment`
--
ALTER TABLE `attachment`
  MODIFY `attachment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `contractorasset`
--
ALTER TABLE `contractorasset`
  MODIFY `asset_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contractorcertification`
--
ALTER TABLE `contractorcertification`
  MODIFY `cert_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `contractorproject`
--
ALTER TABLE `contractorproject`
  MODIFY `portfolio_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `contractorservice`
--
ALTER TABLE `contractorservice`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `material`
--
ALTER TABLE `material`
  MODIFY `material_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `message`
--
ALTER TABLE `message`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification`
--
ALTER TABLE `notification`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offer`
--
ALTER TABLE `offer`
  MODIFY `offer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project`
--
ALTER TABLE `project`
  MODIFY `project_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `projectspecification`
--
ALTER TABLE `projectspecification`
  MODIFY `spec_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `rating`
--
ALTER TABLE `rating`
  MODIFY `rating_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `savedcontractor`
--
ALTER TABLE `savedcontractor`
  MODIFY `saved_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attachment`
--
ALTER TABLE `attachment`
  ADD CONSTRAINT `FK_Attachment_Project` FOREIGN KEY (`project_id`) REFERENCES `project` (`project_id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table `clientprofile`
--
ALTER TABLE `clientprofile`
  ADD CONSTRAINT `FK_ClientProfile_User` FOREIGN KEY (`client_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table `contractorasset`
--
ALTER TABLE `contractorasset`
  ADD CONSTRAINT `FK_ContractorAsset_Contractor` FOREIGN KEY (`contractor_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table `contractorcertification`
--
ALTER TABLE `contractorcertification`
  ADD CONSTRAINT `FK_ContractorCert_Contractor` FOREIGN KEY (`contractor_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table `contractorprofile`
--
ALTER TABLE `contractorprofile`
  ADD CONSTRAINT `FK_ContractorProfile_User` FOREIGN KEY (`contractor_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table `contractorproject`
--
ALTER TABLE `contractorproject`
  ADD CONSTRAINT `FK_ContractorProject_Contractor` FOREIGN KEY (`contractor_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table `contractorservice`
--
ALTER TABLE `contractorservice`
  ADD CONSTRAINT `FK_ContractorService_Contractor` FOREIGN KEY (`contractor_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table `material`
--
ALTER TABLE `material`
  ADD CONSTRAINT `FK_Material_AddedBy` FOREIGN KEY (`added_by`) REFERENCES `user` (`user_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `message`
--
ALTER TABLE `message`
  ADD CONSTRAINT `FK_Message_Receiver` FOREIGN KEY (`receiver_id`) REFERENCES `user` (`user_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `FK_Message_Sender` FOREIGN KEY (`sender_id`) REFERENCES `user` (`user_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `notification`
--
ALTER TABLE `notification`
  ADD CONSTRAINT `FK_Notification_User` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table `offer`
--
ALTER TABLE `offer`
  ADD CONSTRAINT `FK_Offer_Contractor` FOREIGN KEY (`contractor_id`) REFERENCES `user` (`user_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `FK_Offer_Project` FOREIGN KEY (`project_id`) REFERENCES `project` (`project_id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table `project`
--
ALTER TABLE `project`
  ADD CONSTRAINT `FK_Project_AcceptedContractor` FOREIGN KEY (`accepted_contractor_id`) REFERENCES `user` (`user_id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  ADD CONSTRAINT `FK_Project_Client` FOREIGN KEY (`client_id`) REFERENCES `user` (`user_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `projectspecification`
--
ALTER TABLE `projectspecification`
  ADD CONSTRAINT `FK_ProjectSpecification_Project` FOREIGN KEY (`project_id`) REFERENCES `project` (`project_id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table `rating`
--
ALTER TABLE `rating`
  ADD CONSTRAINT `FK_Rating_Contractor` FOREIGN KEY (`contractor_id`) REFERENCES `user` (`user_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `FK_Rating_RatedBy` FOREIGN KEY (`rated_by`) REFERENCES `user` (`user_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `savedcontractor`
--
ALTER TABLE `savedcontractor`
  ADD CONSTRAINT `FK_SavedContractor_Client` FOREIGN KEY (`client_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `FK_SavedContractor_Contractor` FOREIGN KEY (`contractor_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE NO ACTION;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
