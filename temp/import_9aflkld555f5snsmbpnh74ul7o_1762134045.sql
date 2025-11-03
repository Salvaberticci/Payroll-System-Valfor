-- Payroll System Database Backup
-- Generated on: 2025-11-03 01:27:17
-- Software Version: 1.0.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Table structure for table `employee_payroll_details`
--

CREATE TABLE `employee_payroll_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `payroll_period_id` int(11) NOT NULL,
  `concept_id` int(11) NOT NULL,
  `amount_usd` decimal(10,2) DEFAULT 0.00,
  `amount_bs` decimal(12,2) DEFAULT 0.00,
  `days_applied` decimal(4,1) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_id` (`employee_id`,`payroll_period_id`,`concept_id`),
  KEY `payroll_period_id` (`payroll_period_id`),
  KEY `concept_id` (`concept_id`),
  CONSTRAINT `employee_payroll_details_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_payroll_details_ibfk_2` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_payroll_details_ibfk_3` FOREIGN KEY (`concept_id`) REFERENCES `payroll_concepts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employee_payroll_details`
--

INSERT INTO `employee_payroll_details` VALUES
('123', '1', '26', '1', '100.00', '20000.00', NULL, NULL, '2025-10-28 20:37:16', '2025-10-28 20:37:16'),
('124', '1', '26', '5', '4.00', '800.00', NULL, NULL, '2025-10-28 20:37:16', '2025-10-28 20:37:16'),
('125', '1', '26', '6', '1.00', '200.00', NULL, NULL, '2025-10-28 20:37:16', '2025-10-28 20:37:16'),
('126', '7', '26', '1', '100.00', '20000.00', NULL, NULL, '2025-10-28 20:37:16', '2025-10-28 20:37:16'),
('127', '7', '26', '5', '4.00', '800.00', NULL, NULL, '2025-10-28 20:37:16', '2025-10-28 20:37:16'),
('128', '7', '26', '6', '1.00', '200.00', NULL, NULL, '2025-10-28 20:37:16', '2025-10-28 20:37:16'),
('129', '1', '27', '1', '100.00', '22396.22', NULL, NULL, '2025-11-01 13:03:31', '2025-11-01 13:03:31'),
('130', '1', '27', '5', '4.00', '895.85', NULL, NULL, '2025-11-01 13:03:31', '2025-11-01 13:03:31'),
('131', '1', '27', '6', '1.00', '223.96', NULL, NULL, '2025-11-01 13:03:31', '2025-11-01 13:03:31'),
('132', '7', '27', '1', '100.00', '22396.22', NULL, NULL, '2025-11-01 13:03:31', '2025-11-01 13:03:31'),
('133', '7', '27', '5', '4.00', '895.85', NULL, NULL, '2025-11-01 13:03:31', '2025-11-01 13:03:31'),
('134', '7', '27', '6', '1.00', '223.96', NULL, NULL, '2025-11-01 13:03:31', '2025-11-01 13:03:31');

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cedula` varchar(20) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `fecha_ingreso` date NOT NULL,
  `cargo` varchar(100) NOT NULL,
  `salario_base_mensual_usd` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `photo_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cedula` (`cedula`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` VALUES
('1', '30236536', 'Alex', '2024-01-24', 'administrador', '200.00', '1', '2025-06-24 10:28:40', '2025-10-24 21:09:52', 'uploads/employees/employee_68fc23607beb97.00552707.png'),
('7', '12345678', 'Juan', '2025-10-25', 'empleado', '200.00', '1', '2025-10-26 18:20:02', '2025-10-26 18:20:02', '');

--
-- Table structure for table `payroll_concepts`
--

CREATE TABLE `payroll_concepts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('ingreso','deduccion_legal','deduccion_personal','beneficio') NOT NULL,
  `calculation_type` enum('fixed_value','percentage_of_salary','per_day_value','manual_input') NOT NULL,
  `default_value` decimal(10,2) DEFAULT NULL,
  `applies_to_all` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payroll_concepts`
--

INSERT INTO `payroll_concepts` VALUES
('1', 'Salario Base (Quincenal)', 'ingreso', 'manual_input', NULL, '1', '1', '2025-06-24 09:21:48', '2025-06-24 09:21:48'),
('3', 'Retroactivos', 'ingreso', 'manual_input', NULL, '0', '1', '2025-06-24 09:21:48', '2025-06-24 09:21:48'),
('4', 'Otros Ingresos', 'ingreso', 'manual_input', NULL, '0', '1', '2025-06-24 09:21:48', '2025-06-24 09:21:48'),
('5', 'SSO', 'deduccion_legal', 'percentage_of_salary', '0.04', '1', '1', '2025-06-24 09:21:48', '2025-06-24 09:21:48'),
('6', 'SPF', 'deduccion_legal', 'percentage_of_salary', '0.01', '1', '1', '2025-06-24 09:21:48', '2025-06-24 09:21:48');

--
-- Table structure for table `payroll_periods`
--

CREATE TABLE `payroll_periods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `bcv_rate` decimal(10,4) NOT NULL,
  `days_in_period` decimal(4,1) NOT NULL,
  `status` enum('pendiente','calculado','pagado','cerrado') NOT NULL DEFAULT 'pendiente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `start_date` (`start_date`,`end_date`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payroll_periods`
--

INSERT INTO `payroll_periods` VALUES
('26', '2025-10-01', '2025-10-30', '200.0000', '15.0', 'calculado', '2025-10-28 20:37:16', '2025-10-28 20:37:16'),
('27', '2025-10-31', '2025-11-30', '223.9622', '15.0', 'calculado', '2025-11-01 13:03:31', '2025-11-01 13:03:31');

--
-- Table structure for table `payroll_summaries`
--

CREATE TABLE `payroll_summaries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payroll_period_id` int(11) NOT NULL,
  `total_ingresos_usd` decimal(12,2) DEFAULT 0.00,
  `total_deducciones_usd` decimal(12,2) DEFAULT 0.00,
  `total_beneficios_usd` decimal(12,2) DEFAULT 0.00,
  `neto_a_pagar_usd` decimal(12,2) DEFAULT 0.00,
  `total_ingresos_bs` decimal(15,2) DEFAULT 0.00,
  `total_deducciones_bs` decimal(15,2) DEFAULT 0.00,
  `total_beneficios_bs` decimal(15,2) DEFAULT 0.00,
  `neto_a_pagar_bs` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_period_id` (`payroll_period_id`),
  CONSTRAINT `payroll_summaries_ibfk_1` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','asistente','solo lectura') NOT NULL DEFAULT 'solo lectura',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` VALUES
('1', 'admin', '$2y$10$tKMgVT9wFf/l7mYATBAZ0.lZ/UMqBtte3Lk0fiX9L3zvr67M.FaWy', 'admin', '2025-06-24 09:21:48', '2025-06-24 10:16:30'),
('2', 'alex', '$2y$10$u9T2nmJ3a3NCRUdj/tkIyu6JdVoi2GuTKSj6QI6LJVwwyjMow7Qqa', 'asistente', '2025-07-07 18:13:31', '2025-10-26 11:41:36'),
('6', 'prueba', '$2y$10$Ef7QxbnuOvUHUMC2O5l2g.7pemOi7/NpNfiKO.mK27vc972ydUuEq', 'solo lectura', '2025-10-26 11:47:40', '2025-10-26 11:47:40');

COMMIT;
