-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 02-10-2025 a las 16:23:37
-- Versión del servidor: 10.4.28-MariaDB
-- Versión de PHP: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `payroll_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `cedula` varchar(20) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `fecha_ingreso` date NOT NULL,
  `cargo` varchar(100) NOT NULL,
  `salario_base_mensual_usd` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `employees`
--

INSERT INTO `employees` (`id`, `cedula`, `full_name`, `fecha_ingreso`, `cargo`, `salario_base_mensual_usd`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '30236536', 'Alex', '2024-01-24', 'administrador', 200.00, 1, '2025-06-24 14:28:40', '2025-06-24 14:28:40');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `employee_payroll_details`
--

CREATE TABLE `employee_payroll_details` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `payroll_period_id` int(11) NOT NULL,
  `concept_id` int(11) NOT NULL,
  `amount_usd` decimal(10,2) DEFAULT 0.00,
  `amount_bs` decimal(12,2) DEFAULT 0.00,
  `days_applied` decimal(4,1) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `employee_payroll_details`
--

INSERT INTO `employee_payroll_details` (`id`, `employee_id`, `payroll_period_id`, `concept_id`, `amount_usd`, `amount_bs`, `days_applied`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 100.00, 10800.00, NULL, NULL, '2025-07-07 21:19:53', '2025-07-07 21:19:53'),
(2, 1, 1, 5, 4.00, 432.00, NULL, NULL, '2025-07-07 21:19:53', '2025-07-07 21:19:53'),
(3, 1, 1, 6, 1.00, 108.00, NULL, NULL, '2025-07-07 21:19:53', '2025-07-07 21:19:53'),
(4, 1, 1, 7, 1.00, 108.00, NULL, NULL, '2025-07-07 21:19:53', '2025-07-07 21:19:53'),
(5, 1, 1, 14, 20.00, 2160.00, 15.0, NULL, '2025-07-07 21:19:53', '2025-07-07 21:19:53'),
(6, 1, 2, 1, 100.00, 10800.00, NULL, NULL, '2025-07-07 22:54:24', '2025-07-07 22:54:24'),
(7, 1, 2, 5, 4.00, 432.00, NULL, NULL, '2025-07-07 22:54:24', '2025-07-07 22:54:24'),
(8, 1, 2, 6, 1.00, 108.00, NULL, NULL, '2025-07-07 22:54:24', '2025-07-07 22:54:24'),
(9, 1, 2, 7, 1.00, 108.00, NULL, NULL, '2025-07-07 22:54:24', '2025-07-07 22:54:24'),
(10, 1, 2, 14, 20.00, 2160.00, 15.0, NULL, '2025-07-07 22:54:24', '2025-07-07 22:54:24');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `payroll_concepts`
--

CREATE TABLE `payroll_concepts` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('ingreso','deduccion_legal','deduccion_personal','beneficio') NOT NULL,
  `calculation_type` enum('fixed_value','percentage_of_salary','per_day_value','manual_input') NOT NULL,
  `default_value` decimal(10,2) DEFAULT NULL,
  `applies_to_all` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `payroll_concepts`
--

INSERT INTO `payroll_concepts` (`id`, `name`, `type`, `calculation_type`, `default_value`, `applies_to_all`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Salario Base (Quincenal)', 'ingreso', 'manual_input', NULL, 1, 1, '2025-06-24 13:21:48', '2025-06-24 13:21:48'),
(2, 'Horas Extras', 'ingreso', 'manual_input', NULL, 0, 1, '2025-06-24 13:21:48', '2025-06-24 13:21:48'),
(3, 'Retroactivos', 'ingreso', 'manual_input', NULL, 0, 1, '2025-06-24 13:21:48', '2025-06-24 13:21:48'),
(4, 'Otros Ingresos', 'ingreso', 'manual_input', NULL, 0, 1, '2025-06-24 13:21:48', '2025-06-24 13:21:48'),
(5, 'SSO', 'deduccion_legal', 'percentage_of_salary', 0.04, 1, 1, '2025-06-24 13:21:48', '2025-06-24 13:21:48'),
(6, 'SPF', 'deduccion_legal', 'percentage_of_salary', 0.01, 1, 1, '2025-06-24 13:21:48', '2025-06-24 13:21:48'),
(7, 'FAOV', 'deduccion_legal', 'percentage_of_salary', 0.01, 1, 1, '2025-06-24 13:21:48', '2025-06-24 13:21:48'),
(8, 'ISLR', 'deduccion_legal', 'manual_input', NULL, 0, 1, '2025-06-24 13:21:48', '2025-06-24 13:21:48'),
(9, 'Descuento Préstamo', 'deduccion_personal', 'manual_input', NULL, 0, 1, '2025-06-24 13:21:48', '2025-06-24 13:21:48'),
(10, 'Descuento Adelanto', 'deduccion_personal', 'manual_input', NULL, 0, 1, '2025-06-24 13:21:48', '2025-06-24 13:21:48'),
(11, 'Descuento Farmacia', 'deduccion_personal', 'manual_input', NULL, 0, 1, '2025-06-24 13:21:48', '2025-06-24 13:21:48'),
(12, 'Descuento Días Faltantes', 'deduccion_personal', 'per_day_value', NULL, 0, 1, '2025-06-24 13:21:48', '2025-06-24 13:21:48'),
(13, 'Subsidio Alimentación', 'beneficio', 'manual_input', NULL, 1, 1, '2025-06-24 13:21:48', '2025-06-24 13:21:48'),
(14, 'Cesta Ticket', 'beneficio', 'per_day_value', NULL, 1, 1, '2025-06-24 13:21:48', '2025-06-24 13:21:48');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `payroll_periods`
--

CREATE TABLE `payroll_periods` (
  `id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `bcv_rate` decimal(10,4) NOT NULL,
  `days_in_period` decimal(4,1) NOT NULL,
  `status` enum('pending','calculated','paid','closed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `payroll_periods`
--

INSERT INTO `payroll_periods` (`id`, `start_date`, `end_date`, `bcv_rate`, `days_in_period`, `status`, `created_at`, `updated_at`) VALUES
(1, '2025-07-08', '2025-07-26', 108.0000, 15.0, 'paid', '2025-07-07 21:19:53', '2025-07-07 22:15:54'),
(2, '2025-07-01', '2025-07-23', 108.0000, 15.0, 'paid', '2025-07-07 22:54:24', '2025-07-07 22:55:38');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `payroll_summaries`
--

CREATE TABLE `payroll_summaries` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','assistant','read_only') NOT NULL DEFAULT 'read_only',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$tKMgVT9wFf/l7mYATBAZ0.lZ/UMqBtte3Lk0fiX9L3zvr67M.FaWy', 'admin', '2025-06-24 13:21:48', '2025-06-24 14:16:30'),
(2, 'alex', '$2y$10$u9T2nmJ3a3NCRUdj/tkIyu6JdVoi2GuTKSj6QI6LJVwwyjMow7Qqa', 'assistant', '2025-07-07 22:13:31', '2025-07-07 22:13:31');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cedula` (`cedula`);

--
-- Indices de la tabla `employee_payroll_details`
--
ALTER TABLE `employee_payroll_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`,`payroll_period_id`,`concept_id`),
  ADD KEY `payroll_period_id` (`payroll_period_id`),
  ADD KEY `concept_id` (`concept_id`);

--
-- Indices de la tabla `payroll_concepts`
--
ALTER TABLE `payroll_concepts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indices de la tabla `payroll_periods`
--
ALTER TABLE `payroll_periods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `start_date` (`start_date`,`end_date`);

--
-- Indices de la tabla `payroll_summaries`
--
ALTER TABLE `payroll_summaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payroll_period_id` (`payroll_period_id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `employee_payroll_details`
--
ALTER TABLE `employee_payroll_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `payroll_concepts`
--
ALTER TABLE `payroll_concepts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `payroll_periods`
--
ALTER TABLE `payroll_periods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `payroll_summaries`
--
ALTER TABLE `payroll_summaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `employee_payroll_details`
--
ALTER TABLE `employee_payroll_details`
  ADD CONSTRAINT `employee_payroll_details_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_payroll_details_ibfk_2` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_payroll_details_ibfk_3` FOREIGN KEY (`concept_id`) REFERENCES `payroll_concepts` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `payroll_summaries`
--
ALTER TABLE `payroll_summaries`
  ADD CONSTRAINT `payroll_summaries_ibfk_1` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
