-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 07-12-2025 a las 03:19:34
-- Versión del servidor: 10.11.13-MariaDB-cll-lve
-- Versión de PHP: 8.4.15

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `juegosd2_alcohol`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`wepanel_juegosd2`@`localhost` PROCEDURE `SolicitarRetest` (IN `p_prueba_original_id` INT, IN `p_solicitado_por` INT, IN `p_motivo` TEXT)   BEGIN
    DECLARE v_intentos_permisibles INT$$

CREATE DEFINER=`wepanel_juegosd2`@`localhost` PROCEDURE `VerificarLimitesPlan` (IN `p_cliente_id` INT, IN `p_tipo_limite` VARCHAR(50))   BEGIN
    DECLARE v_plan_id INT$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `actas_consentimiento`
--

CREATE TABLE `actas_consentimiento` (
  `id` int(11) NOT NULL,
  `operacion_id` int(11) DEFAULT NULL,
  `conductor_id` int(11) DEFAULT NULL,
  `objetivo_prueba` enum('preventivo','descarte','estudio_positivo','confirmatorio','otros') DEFAULT NULL,
  `firma_conductor` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `actas_consentimiento`
--

INSERT INTO `actas_consentimiento` (`id`, `operacion_id`, `conductor_id`, `objetivo_prueba`, `firma_conductor`) VALUES
(1, 1, 6, 'preventivo', 'fernando'),
(2, 1, 6, 'confirmatorio', 'Pedro Ramirez'),
(3, 1, 6, 'descarte', 'Pedro Ramirez'),
(4, 1, 6, 'estudio_positivo', 'Pedro Ramirez'),
(5, 1, 6, 'estudio_positivo', 'Pedro Ramirez'),
(6, 1, 6, 'confirmatorio', 'Pedro Ramirez'),
(7, 1, 6, 'descarte', 'Pedro Ramirez'),
(8, 1, 6, 'preventivo', 'Pedro Ramirez'),
(9, 1, 6, 'preventivo', 'Pedro Ramirez'),
(10, 1, 6, 'preventivo', 'Pedro Ramirez'),
(11, 1, 6, 'estudio_positivo', 'Pedro Ramirez'),
(12, 1, 6, 'preventivo', 'Pedro Ramirez'),
(13, 3, 6, 'preventivo', 'Pedro Ramirez'),
(14, 3, 6, 'preventivo', 'Pedro Ramirez'),
(15, 3, 6, 'preventivo', 'Pedro Ramirez'),
(16, 2, 6, 'descarte', 'Pedro Ramirez'),
(17, 2, 6, 'preventivo', 'Pedro Ramirez'),
(18, 2, 6, 'preventivo', 'Pedro Ramirez'),
(19, 2, 6, 'descarte', 'Pedro Ramirez'),
(20, 2, 6, 'descarte', 'Pedro Ramirez'),
(21, 2, 6, 'descarte', 'Pedro Ramirez'),
(22, 4, 6, 'preventivo', 'Pedro Ramirez'),
(23, 4, 6, 'preventivo', 'Pedro Ramirez'),
(24, 3, 6, 'preventivo', 'Pedro Ramirez'),
(25, 4, 6, 'preventivo', 'Pedro Ramirez'),
(26, 8, 6, 'estudio_positivo', 'Pedro Ramirez'),
(27, 9, 7, 'preventivo', 'Jeykos Aguilar'),
(28, 10, 7, 'preventivo', 'Jeykos Aguilar');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alcoholimetros`
--

CREATE TABLE `alcoholimetros` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `numero_serie` varchar(50) NOT NULL,
  `nombre_activo` varchar(100) NOT NULL,
  `modelo` varchar(50) DEFAULT NULL,
  `marca` varchar(50) DEFAULT NULL,
  `fecha_calibracion` date DEFAULT NULL,
  `proxima_calibracion` date DEFAULT NULL,
  `estado` enum('activo','inactivo','mantenimiento','calibracion') DEFAULT 'activo',
  `qr_code` varchar(255) DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `alcoholimetros`
--

INSERT INTO `alcoholimetros` (`id`, `cliente_id`, `numero_serie`, `nombre_activo`, `modelo`, `marca`, `fecha_calibracion`, `proxima_calibracion`, `estado`, `qr_code`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 1, 'ALC-001', 'Alcoholímetro Principal', 'AL-3000', 'AlcoTest', '2024-01-15', '2025-01-15', 'activo', 'qr_ALC-001_1764393745.png', '2025-11-25 12:10:30', '2025-11-29 05:22:25'),
(2, 1, 'ALC-002', 'Alcoholímetro Secundario', 'AL-2500', 'AlcoTest', '2024-02-20', '2025-02-20', 'activo', NULL, '2025-11-25 12:10:30', '2025-11-25 12:10:30'),
(3, 1, 'ALC-003', 'Alcoholímetro manual', 'AL-3001', 'AlcoTest', '2025-11-01', '2026-11-30', 'activo', NULL, '2025-11-29 04:47:21', '2025-11-29 04:47:21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `almacenes_inventario`
--

CREATE TABLE `almacenes_inventario` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `direccion` text DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `responsable_id` int(11) DEFAULT NULL,
  `capacidad_total` decimal(10,2) DEFAULT NULL COMMENT 'en m3, kg, etc.',
  `capacidad_utilizada` decimal(10,2) DEFAULT 0.00,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria`
--

CREATE TABLE `auditoria` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `tabla_afectada` varchar(50) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `valores_anteriores` text DEFAULT NULL,
  `valores_nuevos` text DEFAULT NULL,
  `detalles` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `fecha_accion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `auditoria`
--

INSERT INTO `auditoria` (`id`, `cliente_id`, `usuario_id`, `accion`, `tabla_afectada`, `registro_id`, `valores_anteriores`, `valores_nuevos`, `detalles`, `ip_address`, `user_agent`, `fecha_accion`) VALUES
(1, 1, 2, 'LOGIN', 'usuarios', 2, NULL, NULL, 'Inicio de sesión exitoso', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-25 12:15:31'),
(2, 1, 2, 'LOGIN', 'usuarios', 2, NULL, NULL, 'Inicio de sesión exitoso', '132.191.2.150', 'Mozilla/5.0 (Android 13; Mobile; rv:145.0) Gecko/145.0 Firefox/145.0', '2025-11-25 13:10:14'),
(3, 1, 2, 'LOGIN', 'usuarios', 2, NULL, NULL, 'Inicio de sesión exitoso', '132.191.2.150', 'Mozilla/5.0 (X11; Linux x86_64; rv:145.0) Gecko/20100101 Firefox/145.0', '2025-11-25 13:11:47'),
(4, 1, 2, 'LOGIN', 'usuarios', 2, NULL, NULL, 'Inicio de sesión exitoso', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-25 22:20:28'),
(5, 1, 2, 'LOGIN', 'usuarios', 2, NULL, NULL, 'Inicio de sesión exitoso', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-25 22:34:31'),
(6, 1, 2, 'CONFIG_ALCOHOL', 'configuraciones', 1, NULL, NULL, 'Actualización de niveles de alcohol', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-25 22:50:44'),
(7, 1, 2, 'CONFIG_RETEST', 'configuraciones', 1, NULL, NULL, 'Actualización de protocolo re-test', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-25 22:50:51'),
(8, 1, 2, 'CONFIG_RETEST', 'configuraciones', 1, NULL, NULL, 'Actualización de protocolo re-test', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-25 22:50:59'),
(9, 1, 2, 'LOGIN', 'usuarios', 2, NULL, NULL, 'Inicio de sesión exitoso', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 02:09:22'),
(10, 1, 2, 'CAMBIO_PLAN', 'clientes', 1, NULL, NULL, 'Cambio de plan a ID: 2', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 03:35:21'),
(11, 1, 2, 'CAMBIO_PLAN', 'clientes', 1, NULL, NULL, 'Cambio de plan a ID: 3', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 03:35:26'),
(12, 1, 2, 'CAMBIO_PLAN', 'clientes', 1, NULL, NULL, 'Cambio de plan a ID: 4', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 03:35:31'),
(13, 1, 2, 'CAMBIO_PLAN', 'clientes', 1, NULL, NULL, 'Cambio de plan a ID: 1', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 03:35:53'),
(14, 1, 2, 'CONFIG_GENERAL', 'clientes', 1, NULL, NULL, 'Actualización de datos generales', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 03:47:16'),
(15, 1, 2, 'CONFIG_GENERAL', 'clientes', 1, NULL, NULL, 'Actualización de datos generales', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 03:47:30'),
(16, 1, 2, 'CONFIG_GENERAL', 'clientes', 1, NULL, NULL, 'Actualización de datos generales', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 03:47:36'),
(17, 1, 2, 'CONFIG_RETEST', 'configuraciones', 1, NULL, NULL, 'Actualización de protocolo re-test', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 03:47:50'),
(18, 1, 2, 'CONFIG_RETEST', 'configuraciones', 1, NULL, NULL, 'Actualización de protocolo re-test', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 03:48:21'),
(19, 1, 2, 'LOGIN', 'usuarios', 2, NULL, NULL, 'Inicio de sesión exitoso', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 03:49:04'),
(20, 1, 2, 'BACKUP', NULL, NULL, NULL, NULL, 'Backup manual realizado', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 03:57:04'),
(21, 1, 2, 'BACKUP', NULL, NULL, NULL, NULL, 'Backup manual realizado', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 03:57:34'),
(22, 1, 1, 'LOGOUT', 'usuarios', 1, NULL, NULL, 'Cierre de sesión', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 05:36:07'),
(23, 1, 2, 'LOGOUT', 'usuarios', 2, NULL, NULL, 'Cierre de sesión', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 05:36:16'),
(24, 1, 2, 'LOGOUT', 'usuarios', 2, NULL, NULL, 'Cierre de sesión', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 05:41:00'),
(25, 1, 2, 'LOGOUT', 'usuarios', 2, NULL, NULL, 'Cierre de sesión', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 05:59:03'),
(26, 1, 2, 'LOGOUT', 'usuarios', 2, NULL, NULL, 'Cierre de sesión', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 06:34:58'),
(27, 1, 2, 'LOGOUT', 'usuarios', 2, NULL, NULL, 'Cierre de sesión', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 06:42:13'),
(28, 1, 2, 'LOGOUT', 'usuarios', 2, NULL, NULL, 'Cierre de sesión', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 06:45:10'),
(29, 1, 2, 'LOGIN', 'usuarios', 2, NULL, NULL, 'Inicio de sesión exitoso', '179.6.2.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 20:05:40'),
(30, 1, 2, 'CONFIG_EMPRESA', 'clientes', 1, NULL, NULL, 'Actualización de información de la empresa', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-28 03:28:35'),
(31, 1, 2, 'CONFIG_EMPRESA', 'clientes', 1, NULL, NULL, 'Actualización de información de la empresa', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-28 03:28:42'),
(32, 1, 2, 'CONFIG_EMPRESA', 'clientes', 1, NULL, NULL, 'Actualización de información de la empresa', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-28 03:28:49'),
(33, 1, 2, 'ACTUALIZAR_ALCOHOLIMETRO', 'alcoholimetros', 1, NULL, NULL, 'Alcoholímetro actualizado', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 04:35:07'),
(34, 1, 2, 'ACTUALIZAR_ALCOHOLIMETRO', 'alcoholimetros', 1, NULL, NULL, 'Alcoholímetro actualizado', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 04:35:12'),
(35, 1, 2, 'ACTUALIZAR_ALCOHOLIMETRO', 'alcoholimetros', 1, NULL, NULL, 'Alcoholímetro actualizado', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 04:35:17'),
(36, 1, 2, 'ACTUALIZAR_ALCOHOLIMETRO', 'alcoholimetros', 1, NULL, NULL, 'Alcoholímetro actualizado', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 04:35:29'),
(37, 1, 2, 'ACTUALIZAR_ALCOHOLIMETRO', 'alcoholimetros', 1, NULL, NULL, 'Alcoholímetro actualizado', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 04:46:19'),
(38, 1, 2, 'ACTUALIZAR_ALCOHOLIMETRO', 'alcoholimetros', 1, NULL, NULL, 'Alcoholímetro actualizado', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 04:46:34'),
(39, 1, 2, 'ACTUALIZAR_ALCOHOLIMETRO', 'alcoholimetros', 1, NULL, NULL, 'Alcoholímetro actualizado', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 04:46:46'),
(40, 1, 2, 'CREAR_ALCOHOLIMETRO', 'alcoholimetros', 3, NULL, NULL, 'Alcoholímetro creado', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 04:47:21'),
(41, 1, 2, 'GENERAR_QR_INDIVIDUAL', 'alcoholimetros', 1, NULL, NULL, 'Código QR generado: qr_ALC-001_1764393745.png', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 05:22:25'),
(42, 1, 2, 'DESCARGAR_QR', 'alcoholimetros', 1, NULL, NULL, 'Descarga de código QR: qr_ALC-001_1764393745.png', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 05:22:31'),
(43, 1, 2, 'DESCARGAR_QR', 'alcoholimetros', 1, NULL, NULL, 'Descarga de código QR: qr_ALC-001_1764393745.png', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 05:22:56'),
(44, 1, 2, 'DESCARGAR_QR', 'alcoholimetros', 1, NULL, NULL, 'Descarga de código QR: qr_ALC-001_1764393745.png', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 05:30:34'),
(45, 1, 2, 'CREAR_VEHICULO', 'vehiculos', 3, NULL, NULL, 'Vehículo DEF-456777 - Nissan Frontier - Estado: activo', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 06:49:50'),
(46, 1, 2, 'CREAR_CONDUCTOR', 'usuarios', 0, NULL, NULL, 'Conductor Pedro Ramirez - DNI: 57845478 - Estado: Activo', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 06:57:56'),
(47, 1, 2, 'CREAR_CONDUCTOR', 'usuarios', 0, NULL, NULL, 'Conductor Pedro Ramirez - DNI: 57845478 - Estado: Activo', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 06:58:04'),
(48, 1, 2, 'CREAR_CONDUCTOR', 'usuarios', 6, NULL, NULL, 'Conductor Pedro Ramirez - DNI: 40766447 - Estado: Activo', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 07:08:47'),
(49, 1, 2, 'CREAR_PRUEBA', 'pruebas', 1, NULL, NULL, 'Prueba creada - Nivel: 0.1 g/L', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 04:05:37'),
(50, 1, 2, 'LOGOUT', 'usuarios', 2, NULL, NULL, 'Cierre de sesión del sistema', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 01:50:25'),
(51, 1, 2, 'LOGOUT', 'usuarios', 2, NULL, NULL, 'Cierre de sesión del sistema', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 01:50:39'),
(52, 1, 2, 'LOGOUT', 'usuarios', 2, NULL, NULL, 'Cierre de sesión del sistema', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 01:52:44'),
(53, 1, 2, 'LOGOUT', 'usuarios', 2, NULL, NULL, 'Cierre de sesión del sistema', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 03:10:29'),
(54, 1, 2, 'LOGOUT', 'usuarios', 2, NULL, NULL, 'Cierre de sesión del sistema', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 03:11:04'),
(55, 1, 2, 'LOGOUT', 'usuarios', 2, NULL, NULL, 'Cierre de sesión del sistema', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 03:13:45'),
(56, 1, 2, 'CONFIG_EMPRESA', 'clientes', 1, NULL, NULL, 'Actualización de información de la empresa', '38.253.182.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 17:15:18'),
(57, 1, 2, 'GUARDAR_OPERACION', 'operaciones', 1, NULL, NULL, 'Operación fecha: 2025-12-03', '38.253.182.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 19:21:42'),
(58, 1, 2, 'CREAR_PRUEBA', 'pruebas', 2, NULL, NULL, 'Prueba - Nivel: 0.1 g/L - Resultado: reprobado', '38.253.182.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 19:26:38'),
(59, 1, 2, 'CREAR_PRUEBA', 'pruebas', 3, NULL, NULL, 'Prueba - Nivel: 0.05 g/L - Resultado: reprobado', '38.253.182.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 19:29:47'),
(60, 1, 2, 'CREAR_PRUEBA', 'pruebas', 4, NULL, NULL, 'Prueba - Nivel: 0 g/L - Resultado: aprobado', '38.253.182.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 19:31:03'),
(61, 1, 2, 'CREAR_PRUEBA', 'pruebas', 5, NULL, NULL, 'Prueba - Nivel: 0 g/L - Resultado: aprobado', '38.253.182.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 19:38:28'),
(62, 1, 2, 'GUARDAR_OPERACION', 'operaciones', 2, NULL, NULL, 'Operación fecha: 2025-12-03', '38.253.182.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 19:42:19'),
(63, 1, 2, 'CREAR_PRUEBA', 'pruebas', 6, NULL, NULL, 'Prueba - Nivel: 0.1 g/L - Resultado: reprobado', '38.253.182.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 19:44:15'),
(64, 1, 2, 'GUARDAR_OPERACION', 'operaciones', 3, NULL, NULL, 'Operación fecha: 2025-12-03', '38.253.182.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 19:47:15'),
(65, 1, 2, 'CREAR_CONDUCTOR', 'usuarios', 7, NULL, NULL, 'Conductor Jeyko Aguilar - DNI: 41001016 - Estado: Activo', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 04:41:14'),
(66, 1, 2, 'CREAR_VEHICULO', 'vehiculos', 4, NULL, NULL, 'Vehículo ABC-777 - Mahndra Guerrero - Estado: activo', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 04:42:09'),
(67, 1, 2, 'LOGOUT', 'usuarios', 2, NULL, NULL, 'Cierre de sesión del sistema', '38.253.182.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-04 15:38:12'),
(68, 0, 1, 'LOGOUT', 'usuarios', 1, NULL, NULL, 'Cierre de sesión del sistema', '38.253.182.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-04 15:45:58'),
(69, 1, 2, 'LOGOUT', 'usuarios', 2, NULL, NULL, 'Cierre de sesión del sistema', '38.253.182.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-04 16:38:02'),
(70, 0, 1, 'LOGOUT', 'usuarios', 1, NULL, NULL, 'Cierre de sesión del sistema', '38.253.182.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-04 16:48:05'),
(71, 1, 2, 'LOGOUT', 'usuarios', 2, NULL, NULL, 'Cierre de sesión del sistema', '38.253.182.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-04 17:25:19'),
(72, 0, 1, 'LOGOUT', 'usuarios', 1, NULL, NULL, 'Cierre de sesión del sistema', '38.253.182.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-04 17:29:41'),
(73, 1, 2, 'LOGOUT', 'usuarios', 2, NULL, NULL, 'Cierre de sesión del sistema', '38.253.182.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-04 18:10:40'),
(74, 0, 1, 'LOGOUT', 'usuarios', 1, NULL, NULL, 'Cierre de sesión del sistema', '38.253.182.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-04 18:16:36'),
(75, 1, 2, 'LOGOUT', 'usuarios', 2, NULL, NULL, 'Cierre de sesión del sistema', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 02:56:10'),
(76, 0, 1, 'LOGOUT', 'usuarios', 1, NULL, NULL, 'Cierre de sesión del sistema', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 03:18:56'),
(77, 1, 2, 'LOGOUT', 'usuarios', 2, NULL, NULL, 'Cierre de sesión del sistema', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 03:20:36'),
(78, 0, 1, 'LOGOUT', 'usuarios', 1, NULL, NULL, 'Cierre de sesión del sistema', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 18:15:20'),
(79, 1, 2, 'LOGOUT', 'usuarios', 2, NULL, NULL, 'Cierre de sesión del sistema', '179.6.3.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 20:33:36');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `backups`
--

CREATE TABLE `backups` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(500) DEFAULT NULL,
  `tamanio` bigint(20) DEFAULT NULL,
  `hash_verificacion` varchar(100) DEFAULT NULL,
  `incluye_archivos` tinyint(1) DEFAULT 0,
  `tipo` enum('manual','automatico') DEFAULT 'manual',
  `estado` enum('completado','error','en_proceso') DEFAULT 'completado',
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `observaciones` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `backups`
--

INSERT INTO `backups` (`id`, `cliente_id`, `archivo`, `ruta_archivo`, `tamanio`, `hash_verificacion`, `incluye_archivos`, `tipo`, `estado`, `fecha_creacion`, `observaciones`) VALUES
(1, 1, 'backup_1_2024-11-25_03-00-00.sql', NULL, 2621440, NULL, 0, 'automatico', 'completado', '2025-11-26 03:25:45', 'Backup diario automático'),
(2, 1, 'backup_1_2024-11-24_03-00-00.sql', NULL, 2521340, NULL, 0, 'automatico', 'completado', '2025-11-26 03:25:45', 'Backup diario automático'),
(3, 1, 'backup_1_2024-11-23_15-30-00.sql', NULL, 2421240, NULL, 0, 'manual', 'completado', '2025-11-26 03:25:45', 'Backup manual solicitado por usuario');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checklists_operacion`
--

CREATE TABLE `checklists_operacion` (
  `id` int(11) NOT NULL,
  `operacion_id` int(11) DEFAULT NULL,
  `alcoholimetro_id` int(11) DEFAULT NULL,
  `estado_alcoholimetro` enum('conforme','no_conforme') DEFAULT NULL,
  `fecha_hora_actualizada` tinyint(1) DEFAULT NULL,
  `bateria_cargada` tinyint(1) DEFAULT NULL,
  `enciende_condiciones` tinyint(1) DEFAULT NULL,
  `impresora_operativa` tinyint(1) DEFAULT NULL,
  `boquillas` tinyint(1) DEFAULT NULL,
  `documentacion_disponible` tinyint(1) DEFAULT NULL,
  `huellero` tinyint(1) DEFAULT NULL,
  `lapicero` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `checklists_operacion`
--

INSERT INTO `checklists_operacion` (`id`, `operacion_id`, `alcoholimetro_id`, `estado_alcoholimetro`, `fecha_hora_actualizada`, `bateria_cargada`, `enciende_condiciones`, `impresora_operativa`, `boquillas`, `documentacion_disponible`, `huellero`, `lapicero`) VALUES
(1, 1, 3, 'conforme', 1, 0, 0, 0, 1, 0, 1, 0),
(2, 2, 3, 'conforme', 1, 1, 1, 1, 1, 1, 1, 1),
(3, 3, 3, 'conforme', 1, 1, 1, 1, 1, 1, 1, 1),
(4, 4, 3, 'conforme', 1, 1, 1, 1, 1, 1, 1, 1),
(5, 8, 3, 'conforme', 1, 1, 1, 1, 1, 1, 1, 1),
(6, 9, 3, 'conforme', 1, 1, 1, 1, 1, 1, 1, 1),
(7, 10, 3, 'conforme', 1, 1, 1, 1, 1, 1, 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nombre_empresa` varchar(255) NOT NULL,
  `ruc` varchar(20) NOT NULL,
  `direccion` text DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email_contacto` varchar(255) DEFAULT NULL,
  `plan_id` int(11) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `color_primario` varchar(7) DEFAULT '#2196F3',
  `color_secundario` varchar(7) DEFAULT '#1976D2',
  `fecha_registro` timestamp NULL DEFAULT current_timestamp(),
  `fecha_vencimiento` date DEFAULT NULL,
  `estado` enum('activo','inactivo','suspendido','prueba') DEFAULT 'prueba',
  `token_api` varchar(100) DEFAULT NULL,
  `modo_demo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `nombre_empresa`, `ruc`, `direccion`, `telefono`, `email_contacto`, `plan_id`, `logo`, `color_primario`, `color_secundario`, `fecha_registro`, `fecha_vencimiento`, `estado`, `token_api`, `modo_demo`) VALUES
(1, 'Empresa Demo S.A.C.', '20123456789', 'Av. Demo 123, Lima', '01-234-5678', 'admin@demo.com', 1, NULL, '#009dff', '#1d4fb4', '2025-11-25 12:10:30', '2025-12-25', 'prueba', '8bd23693c01825696ee136aee8eae333', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuraciones`
--

CREATE TABLE `configuraciones` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `limite_alcohol_permisible` decimal(5,3) DEFAULT 0.000,
  `intervalo_retest_minutos` int(11) DEFAULT 15,
  `intentos_retest` int(11) DEFAULT 3,
  `requerir_geolocalizacion` tinyint(1) DEFAULT 1,
  `requerir_foto_evidencia` tinyint(1) DEFAULT 0,
  `requerir_firma_digital` tinyint(1) DEFAULT 1,
  `notificaciones_email` tinyint(1) DEFAULT 1,
  `notificaciones_sms` tinyint(1) DEFAULT 0,
  `notificaciones_push` tinyint(1) DEFAULT 1,
  `timezone` varchar(50) DEFAULT 'America/Lima',
  `idioma` enum('es','en','pt') DEFAULT 'es',
  `formato_fecha` varchar(20) DEFAULT 'd/m/Y',
  `fecha_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `nivel_advertencia` decimal(5,3) DEFAULT 0.025,
  `nivel_critico` decimal(5,3) DEFAULT 0.080,
  `unidad_medida` varchar(10) DEFAULT 'g/L',
  `bloqueo_conductor_horas` int(11) DEFAULT 24,
  `notificar_supervisor_retest` tinyint(1) DEFAULT 1,
  `requerir_aprobacion_supervisor` tinyint(1) DEFAULT 0,
  `requerir_observaciones` tinyint(1) DEFAULT 0,
  `tiempo_maximo_prueba_minutos` int(11) DEFAULT 10,
  `distancia_maxima_metros` int(11) DEFAULT 500,
  `notificaciones_whatsapp` tinyint(1) DEFAULT 0,
  `email_notificacion` varchar(255) DEFAULT NULL,
  `telefono_notificacion` varchar(20) DEFAULT NULL,
  `emails_adicionales` text DEFAULT NULL,
  `backup_diario` tinyint(1) DEFAULT 1,
  `backup_semanal` tinyint(1) DEFAULT 1,
  `backup_mensual` tinyint(1) DEFAULT 0,
  `retencion_dias` int(11) DEFAULT 30,
  `observaciones` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `configuraciones`
--

INSERT INTO `configuraciones` (`id`, `cliente_id`, `limite_alcohol_permisible`, `intervalo_retest_minutos`, `intentos_retest`, `requerir_geolocalizacion`, `requerir_foto_evidencia`, `requerir_firma_digital`, `notificaciones_email`, `notificaciones_sms`, `notificaciones_push`, `timezone`, `idioma`, `formato_fecha`, `fecha_actualizacion`, `nivel_advertencia`, `nivel_critico`, `unidad_medida`, `bloqueo_conductor_horas`, `notificar_supervisor_retest`, `requerir_aprobacion_supervisor`, `requerir_observaciones`, `tiempo_maximo_prueba_minutos`, `distancia_maxima_metros`, `notificaciones_whatsapp`, `email_notificacion`, `telefono_notificacion`, `emails_adicionales`, `backup_diario`, `backup_semanal`, `backup_mensual`, `retencion_dias`, `observaciones`) VALUES
(1, 1, 0.000, 15, 5, 1, 0, 1, 1, 0, 1, 'America/Lima', 'es', 'd/m/Y', '2025-11-26 03:47:50', 0.025, 0.080, 'g/L', 24, 1, 0, 0, 10, 500, 0, NULL, NULL, NULL, 1, 1, 0, 30, '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_notificaciones`
--

CREATE TABLE `configuracion_notificaciones` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `notificaciones_email` tinyint(1) DEFAULT 1,
  `notificaciones_sms` tinyint(1) DEFAULT 0,
  `notificaciones_push` tinyint(1) DEFAULT 1,
  `notificaciones_whatsapp` tinyint(1) DEFAULT 0,
  `alerta_nivel_alto` tinyint(1) DEFAULT 1,
  `alerta_nivel_medio` tinyint(1) DEFAULT 1,
  `alerta_nivel_bajo` tinyint(1) DEFAULT 1,
  `notificar_supervisor` tinyint(1) DEFAULT 1,
  `notificar_admin` tinyint(1) DEFAULT 1,
  `notificar_conductores` tinyint(1) DEFAULT 0,
  `umbral_alto` decimal(5,3) DEFAULT 0.800,
  `umbral_medio` decimal(5,3) DEFAULT 0.500,
  `umbral_bajo` decimal(5,3) DEFAULT 0.300,
  `intervalo_notificaciones` int(11) DEFAULT 60,
  `horario_inicio` time DEFAULT '08:00:00',
  `horario_fin` time DEFAULT '18:00:00',
  `dias_activos` varchar(50) DEFAULT '1,2,3,4,5',
  `plantilla_email` text DEFAULT NULL,
  `plantilla_sms` text DEFAULT NULL,
  `configuracion_avanzada` text DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `configuracion_notificaciones`
--

INSERT INTO `configuracion_notificaciones` (`id`, `cliente_id`, `notificaciones_email`, `notificaciones_sms`, `notificaciones_push`, `notificaciones_whatsapp`, `alerta_nivel_alto`, `alerta_nivel_medio`, `alerta_nivel_bajo`, `notificar_supervisor`, `notificar_admin`, `notificar_conductores`, `umbral_alto`, `umbral_medio`, `umbral_bajo`, `intervalo_notificaciones`, `horario_inicio`, `horario_fin`, `dias_activos`, `plantilla_email`, `plantilla_sms`, `configuracion_avanzada`, `estado`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 1, 1, 0, 1, 0, 1, 1, 1, 1, 1, 0, 0.800, 0.500, 0.300, 60, '08:00:00', '18:00:00', '1,2,3,4,5', 'Estimado usuario,\n\nSe ha registrado una prueba de alcohol con resultado: {resultado}.\nNivel: {nivel_alcohol} {unidad_medida}\nConductor: {conductor_nombre}\nFecha: {fecha_prueba}\n\nSaludos,\nSistema de Control de Alcohol', 'Alerta: Prueba {resultado}. Nivel: {nivel_alcohol}. Conductor: {conductor_nombre}', NULL, 1, '2025-11-28 05:18:02', '2025-11-28 05:18:02');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_sistema`
--

CREATE TABLE `configuracion_sistema` (
  `id` int(11) NOT NULL,
  `clave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `configuracion_sistema`
--

INSERT INTO `configuracion_sistema` (`id`, `clave`, `valor`, `descripcion`, `fecha_actualizacion`) VALUES
(1, 'moneda_default', 'USD', 'Moneda por defecto para precios', '2025-12-04 16:31:37'),
(2, 'impuesto_default', '0', 'Impuesto por defecto (%)', '2025-12-04 16:31:37'),
(3, 'limite_pruebas_free', '1000', 'Límite de pruebas para plan free', '2025-12-04 16:31:37'),
(4, 'limite_usuarios_free', '5', 'Límite de usuarios para plan free', '2025-12-04 16:31:37'),
(5, 'tasa_retencion_meta', '85', 'Tasa de retención meta (%)', '2025-12-04 16:31:37'),
(6, 'email_soporte', 'soporte@sistema.com', 'Email de soporte', '2025-12-04 16:31:37'),
(7, 'telefono_soporte', '+51 123 456 789', 'Teléfono de soporte', '2025-12-04 16:31:37'),
(8, 'dias_prueba_gratis', '30', 'Días de prueba gratis', '2025-12-04 16:31:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `config_notificaciones_eventos`
--

CREATE TABLE `config_notificaciones_eventos` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `evento` varchar(50) NOT NULL,
  `notificar_email` tinyint(1) DEFAULT 1,
  `notificar_sms` tinyint(1) DEFAULT 0,
  `notificar_push` tinyint(1) DEFAULT 1,
  `notificar_whatsapp` tinyint(1) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `config_notificaciones_eventos`
--

INSERT INTO `config_notificaciones_eventos` (`id`, `cliente_id`, `evento`, `notificar_email`, `notificar_sms`, `notificar_push`, `notificar_whatsapp`, `activo`) VALUES
(1, 1, 'prueba_positiva', 1, 1, 1, 1, 1),
(2, 1, 'retest_fallido', 1, 1, 1, 1, 1),
(3, 1, 'conductor_bloqueado', 1, 1, 1, 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_toma_inventario`
--

CREATE TABLE `detalle_toma_inventario` (
  `id` int(11) NOT NULL,
  `toma_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad_sistema` decimal(10,2) NOT NULL COMMENT 'Cantidad que el sistema espera',
  `cantidad_contada` decimal(10,2) DEFAULT NULL,
  `diferencia` decimal(10,2) DEFAULT 0.00,
  `estado` enum('pendiente','contado','discrepancia','verificado','no_encontrado') DEFAULT 'pendiente',
  `observaciones` text DEFAULT NULL,
  `intentos_conteo` int(11) DEFAULT 0,
  `necesita_reconteo` tinyint(1) DEFAULT 0,
  `motivo_reconteo` text DEFAULT NULL,
  `fecha_conteo` datetime DEFAULT NULL,
  `fecha_verificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `encuestas_preliminares`
--

CREATE TABLE `encuestas_preliminares` (
  `id` int(11) NOT NULL,
  `acta_id` int(11) DEFAULT NULL,
  `enfermedades` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`enfermedades`)),
  `elementos_boca` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`elementos_boca`)),
  `actividades_recientes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`actividades_recientes`)),
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `encuestas_preliminares`
--

INSERT INTO `encuestas_preliminares` (`id`, `acta_id`, `enfermedades`, `elementos_boca`, `actividades_recientes`, `observaciones`) VALUES
(1, 3, '{\"diabetes\":true,\"hipertension\":false,\"otros\":\"\"}', '{\"chiclets\":true,\"caramelos\":false,\"pastillas_mentoladas\":false,\"piercing_brackets\":false,\"otros\":\"\"}', '{\"fumado\":true,\"eructado\":false,\"splash_bucal\":false,\"vomitado\":false,\"enjuague_bucal\":false,\"otros\":\"\"}', 'demo'),
(2, 4, '{\"diabetes\":true,\"hipertension\":false,\"otros\":\"\"}', '{\"chiclets\":false,\"caramelos\":false,\"pastillas_mentoladas\":true,\"piercing_brackets\":false,\"otros\":\"\"}', '{\"fumado\":false,\"eructado\":false,\"splash_bucal\":false,\"vomitado\":false,\"enjuague_bucal\":true,\"otros\":\"\"}', 'no se'),
(3, 8, '{\"diabetes\":true,\"hipertension\":false,\"otros\":\"\"}', '{\"chiclets\":false,\"caramelos\":true,\"pastillas_mentoladas\":false,\"piercing_brackets\":false,\"otros\":\"\"}', '{\"fumado\":false,\"eructado\":true,\"splash_bucal\":false,\"vomitado\":false,\"enjuague_bucal\":false,\"otros\":\"\"}', ''),
(4, 9, '{\"diabetes\":true,\"hipertension\":false,\"otros\":\"\"}', '{\"chiclets\":false,\"caramelos\":true,\"pastillas_mentoladas\":false,\"piercing_brackets\":false,\"otros\":\"\"}', '{\"fumado\":false,\"eructado\":true,\"splash_bucal\":false,\"vomitado\":false,\"enjuague_bucal\":false,\"otros\":\"\"}', 'demo'),
(5, 11, '{\"diabetes\":true,\"hipertension\":false,\"otros\":\"\"}', '{\"chiclets\":false,\"caramelos\":true,\"pastillas_mentoladas\":false,\"piercing_brackets\":false,\"otros\":\"\"}', '{\"fumado\":true,\"eructado\":false,\"splash_bucal\":false,\"vomitado\":false,\"enjuague_bucal\":false,\"otros\":\"\"}', 'cansado'),
(6, 14, '{\"diabetes\":true,\"hipertension\":false,\"otros\":\"\"}', '{\"chiclets\":false,\"caramelos\":true,\"pastillas_mentoladas\":false,\"piercing_brackets\":false,\"otros\":\"\"}', '{\"fumado\":true,\"eructado\":false,\"splash_bucal\":false,\"vomitado\":false,\"enjuague_bucal\":false,\"otros\":\"\"}', ''),
(7, 16, '{\"diabetes\":true,\"hipertension\":false,\"otros\":\"\"}', '{\"chiclets\":false,\"caramelos\":true,\"pastillas_mentoladas\":false,\"piercing_brackets\":false,\"otros\":\"\"}', '{\"fumado\":false,\"eructado\":true,\"splash_bucal\":false,\"vomitado\":false,\"enjuague_bucal\":true,\"otros\":\"\"}', ''),
(8, 17, '{\"diabetes\":false,\"hipertension\":true,\"otros\":\"\"}', '{\"chiclets\":false,\"caramelos\":false,\"pastillas_mentoladas\":false,\"piercing_brackets\":false,\"otros\":\"\"}', '{\"fumado\":false,\"eructado\":false,\"splash_bucal\":false,\"vomitado\":true,\"enjuague_bucal\":false,\"otros\":\"\"}', ''),
(9, 18, '{\"diabetes\":true,\"hipertension\":false,\"otros\":\"\"}', '{\"chiclets\":false,\"caramelos\":true,\"pastillas_mentoladas\":false,\"piercing_brackets\":false,\"otros\":\"\"}', '{\"fumado\":true,\"eructado\":false,\"splash_bucal\":false,\"vomitado\":false,\"enjuague_bucal\":false,\"otros\":\"\"}', ''),
(10, 19, '{\"diabetes\":false,\"hipertension\":true,\"otros\":\"\"}', '{\"chiclets\":false,\"caramelos\":true,\"pastillas_mentoladas\":false,\"piercing_brackets\":false,\"otros\":\"\"}', '{\"fumado\":false,\"eructado\":true,\"splash_bucal\":false,\"vomitado\":false,\"enjuague_bucal\":false,\"otros\":\"\"}', ''),
(11, 20, '{\"diabetes\":false,\"hipertension\":true,\"otros\":\"\"}', '{\"chiclets\":false,\"caramelos\":true,\"pastillas_mentoladas\":false,\"piercing_brackets\":false,\"otros\":\"\"}', '{\"fumado\":false,\"eructado\":true,\"splash_bucal\":false,\"vomitado\":false,\"enjuague_bucal\":false,\"otros\":\"\"}', ''),
(12, 21, '{\"diabetes\":false,\"hipertension\":true,\"otros\":\"\"}', '{\"chiclets\":false,\"caramelos\":true,\"pastillas_mentoladas\":false,\"piercing_brackets\":false,\"otros\":\"\"}', '{\"fumado\":false,\"eructado\":true,\"splash_bucal\":false,\"vomitado\":false,\"enjuague_bucal\":false,\"otros\":\"\"}', ''),
(13, 22, '{\"diabetes\":true,\"hipertension\":false,\"otros\":\"\"}', '{\"chiclets\":false,\"caramelos\":true,\"pastillas_mentoladas\":false,\"piercing_brackets\":false,\"otros\":\"\"}', '{\"fumado\":false,\"eructado\":true,\"splash_bucal\":false,\"vomitado\":false,\"enjuague_bucal\":false,\"otros\":\"\"}', ''),
(14, 24, '{\"diabetes\":false,\"hipertension\":true,\"otros\":\"\"}', '{\"chiclets\":false,\"caramelos\":true,\"pastillas_mentoladas\":false,\"piercing_brackets\":false,\"otros\":\"\"}', '{\"fumado\":false,\"eructado\":true,\"splash_bucal\":false,\"vomitado\":false,\"enjuague_bucal\":false,\"otros\":\"\"}', ''),
(15, 25, '{\"diabetes\":false,\"hipertension\":true,\"otros\":\"\"}', '{\"chiclets\":false,\"caramelos\":true,\"pastillas_mentoladas\":false,\"piercing_brackets\":false,\"otros\":\"\"}', '{\"fumado\":false,\"eructado\":true,\"splash_bucal\":false,\"vomitado\":false,\"enjuague_bucal\":false,\"otros\":\"\"}', ''),
(16, 26, '{\"diabetes\":true,\"hipertension\":false,\"otros\":\"\"}', '{\"chiclets\":true,\"caramelos\":false,\"pastillas_mentoladas\":false,\"piercing_brackets\":false,\"otros\":\"\"}', '{\"fumado\":true,\"eructado\":false,\"splash_bucal\":false,\"vomitado\":false,\"enjuague_bucal\":false,\"otros\":\"\"}', ''),
(17, 27, '{\"diabetes\":true,\"hipertension\":false,\"otros\":\"\"}', '{\"chiclets\":true,\"caramelos\":false,\"pastillas_mentoladas\":false,\"piercing_brackets\":false,\"otros\":\"\"}', '{\"fumado\":false,\"eructado\":true,\"splash_bucal\":false,\"vomitado\":true,\"enjuague_bucal\":false,\"otros\":\"\"}', ''),
(18, 28, '{\"diabetes\":true,\"hipertension\":false,\"otros\":\"\"}', '{\"chiclets\":false,\"caramelos\":true,\"pastillas_mentoladas\":false,\"piercing_brackets\":false,\"otros\":\"\"}', '{\"fumado\":false,\"eructado\":true,\"splash_bucal\":false,\"vomitado\":false,\"enjuague_bucal\":false,\"otros\":\"\"}', '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_niveles_alcohol`
--

CREATE TABLE `historial_niveles_alcohol` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `limite_anterior` decimal(5,3) NOT NULL,
  `limite_nuevo` decimal(5,3) NOT NULL,
  `nivel_advertencia_anterior` decimal(5,3) NOT NULL,
  `nivel_advertencia_nuevo` decimal(5,3) NOT NULL,
  `nivel_critico_anterior` decimal(5,3) NOT NULL,
  `nivel_critico_nuevo` decimal(5,3) NOT NULL,
  `motivo_cambio` text DEFAULT NULL,
  `fecha_cambio` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_planes`
--

CREATE TABLE `historial_planes` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `plan_anterior_id` int(11) DEFAULT NULL,
  `plan_nuevo_id` int(11) NOT NULL,
  `fecha_cambio` timestamp NULL DEFAULT current_timestamp(),
  `motivo_cambio` varchar(255) DEFAULT NULL,
  `cambio_por` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `informes_positivos`
--

CREATE TABLE `informes_positivos` (
  `id` int(11) NOT NULL,
  `prueba_id` int(11) DEFAULT NULL,
  `condicion_ambiental` enum('controlado','libre_alcohol') DEFAULT NULL,
  `observaciones_examinado` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`observaciones_examinado`)),
  `comentarios_accion` text DEFAULT NULL,
  `firma_supervisor` varchar(255) DEFAULT NULL,
  `firma_evaluado` varchar(255) DEFAULT NULL,
  `conclusion` text DEFAULT NULL,
  `adjuntos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`adjuntos`))
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `informes_positivos`
--

INSERT INTO `informes_positivos` (`id`, `prueba_id`, `condicion_ambiental`, `observaciones_examinado`, `comentarios_accion`, `firma_supervisor`, `firma_evaluado`, `conclusion`, `adjuntos`) VALUES
(1, 6, 'controlado', '{\"seguridad_si_mismo\":true,\"euforia\":false,\"trastornos_equilibrio\":false,\"disminucion_autocritica\":false,\"perturbaciones_psicosensoriales\":false,\"otros\":\"\"}', 'tomar', NULL, NULL, 'Resultado POSITIVO en prueba de alcoholemia. Se recomienda seguir el protocolo interno de seguridad y salud ocupacional para casos positivos.', '{\"ticket_alcoholimetro\":true,\"registro_digital\":true,\"grabacion\":true}'),
(2, 9, 'controlado', '{\"seguridad_si_mismo\":true,\"euforia\":false,\"trastornos_equilibrio\":false,\"disminucion_autocritica\":true,\"perturbaciones_psicosensoriales\":false,\"otros\":\"\"}', 'dadadad', NULL, NULL, 'Resultado POSITIVO. Se recomienda seguir protocolo interno de seguridad.', '{\"ticket_alcoholimetro\":true,\"registro_digital\":true,\"grabacion\":true}'),
(3, 14, 'controlado', '{\"seguridad_si_mismo\":true,\"euforia\":true,\"trastornos_equilibrio\":false,\"disminucion_autocritica\":true,\"perturbaciones_psicosensoriales\":false,\"otros\":\"\"}', 'dsadasdasdas', NULL, NULL, 'Resultado POSITIVO. Se recomienda seguir protocolo interno de seguridad.', '{\"ticket_alcoholimetro\":true,\"registro_digital\":true,\"grabacion\":true}'),
(4, 16, 'controlado', '{\"seguridad_si_mismo\":true,\"euforia\":false,\"trastornos_equilibrio\":false,\"disminucion_autocritica\":false,\"perturbaciones_psicosensoriales\":false,\"otros\":\"\"}', 'wwwww', NULL, NULL, 'Resultado POSITIVO. Se recomienda seguir protocolo interno de seguridad.', '{\"ticket_alcoholimetro\":true,\"registro_digital\":true,\"grabacion\":true}'),
(5, 17, 'controlado', '{\"seguridad_si_mismo\":true,\"euforia\":true,\"trastornos_equilibrio\":false,\"disminucion_autocritica\":false,\"perturbaciones_psicosensoriales\":false,\"otros\":\"\"}', '', NULL, NULL, 'Resultado POSITIVO. Se recomienda seguir protocolo interno de seguridad.', '{\"ticket_alcoholimetro\":true,\"registro_digital\":true,\"grabacion\":true}'),
(6, 18, 'controlado', '{\"seguridad_si_mismo\":false,\"euforia\":false,\"trastornos_equilibrio\":false,\"disminucion_autocritica\":false,\"perturbaciones_psicosensoriales\":true,\"otros\":\"\"}', 'xfdffsdfdsfsd', NULL, NULL, 'Resultado POSITIVO. Se recomienda seguir protocolo interno de seguridad.', '{\"ticket_alcoholimetro\":true,\"registro_digital\":true,\"grabacion\":true}');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `jornadas_prueba`
--

CREATE TABLE `jornadas_prueba` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL COMMENT 'Cliente al que pertenece la jornada',
  `operador_id` int(11) NOT NULL COMMENT 'Usuario que abre la jornada',
  `ubicacion_id` int(11) DEFAULT NULL COMMENT 'Sede/ubicación de la jornada',
  `fecha` date NOT NULL COMMENT 'Fecha de la jornada',
  `hora_inicio` time NOT NULL COMMENT 'Hora de apertura',
  `hora_fin` time DEFAULT NULL COMMENT 'Hora de cierre',
  `numero_prueba_inicio` int(11) NOT NULL DEFAULT 1 COMMENT 'Número de prueba inicial del día',
  `numero_prueba_fin` int(11) DEFAULT NULL COMMENT 'Número de prueba final del día',
  `total_pruebas` int(11) NOT NULL DEFAULT 0 COMMENT 'Total de pruebas realizadas',
  `pruebas_negativas` int(11) NOT NULL DEFAULT 0 COMMENT 'Cantidad de pruebas negativas',
  `pruebas_positivas` int(11) NOT NULL DEFAULT 0 COMMENT 'Cantidad de pruebas positivas',
  `protocolos_pendientes` int(11) NOT NULL DEFAULT 0 COMMENT 'Protocolos positivos sin completar',
  `observaciones` text DEFAULT NULL COMMENT 'Observaciones de la jornada',
  `estado` enum('abierta','pendiente_cierre','cerrada','cancelada') NOT NULL DEFAULT 'abierta' COMMENT 'Estado de la jornada',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `kardex_inventario`
--

CREATE TABLE `kardex_inventario` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `tipo_movimiento` enum('entrada','salida','ajuste_inventario','toma_fisica') DEFAULT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `cantidad_anterior` decimal(10,2) NOT NULL,
  `cantidad_nueva` decimal(10,2) NOT NULL,
  `referencia_id` int(11) DEFAULT NULL COMMENT 'ID de la toma de inventario o ajuste',
  `motivo` text DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha_movimiento` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `licencias`
--

CREATE TABLE `licencias` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `conductor_id` int(11) NOT NULL,
  `numero_licencia` varchar(20) NOT NULL,
  `categoria` varchar(10) NOT NULL,
  `fecha_emision` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `estado` enum('activa','vencida','suspendida','inactiva') DEFAULT 'activa',
  `restricciones` text DEFAULT NULL,
  `archivo_adjunto` varchar(255) DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logs_configuracion`
--

CREATE TABLE `logs_configuracion` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `seccion` varchar(50) DEFAULT NULL,
  `cambios` text DEFAULT NULL,
  `fecha` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logs_notificaciones`
--

CREATE TABLE `logs_notificaciones` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `tipo_notificacion` enum('email','sms','push','whatsapp') NOT NULL,
  `destinatario` varchar(255) DEFAULT NULL,
  `asunto` varchar(255) DEFAULT NULL,
  `mensaje` text DEFAULT NULL,
  `estado` enum('enviado','error','pendiente') DEFAULT 'pendiente',
  `error_mensaje` text DEFAULT NULL,
  `fecha_envio` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones_inventario`
--

CREATE TABLE `notificaciones_inventario` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo` enum('reconteo','discrepancia','completado','asignacion') DEFAULT NULL,
  `mensaje` text NOT NULL,
  `leido` tinyint(1) DEFAULT 0,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `operaciones`
--

CREATE TABLE `operaciones` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `ubicacion_id` int(11) DEFAULT NULL,
  `lugar_pruebas` varchar(255) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `plan_motivo` enum('diario','aleatorio','semanal','mensual','sospecha') DEFAULT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_cierre` time DEFAULT NULL,
  `operador_id` int(11) DEFAULT NULL,
  `firma_operador` varchar(255) DEFAULT NULL,
  `estado` enum('planificada','en_proceso','completada','cancelada') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `operaciones`
--

INSERT INTO `operaciones` (`id`, `cliente_id`, `ubicacion_id`, `lugar_pruebas`, `fecha`, `plan_motivo`, `hora_inicio`, `hora_cierre`, `operador_id`, `firma_operador`, `estado`) VALUES
(1, 1, 4, 'entrada principal', '2025-12-03', 'diario', '15:21:00', '17:21:00', 2, NULL, 'completada'),
(2, 1, 4, 'patio', '2025-12-03', 'diario', '16:42:00', '20:42:00', 3, NULL, 'en_proceso'),
(3, 1, 1, 'entrada principal', '2025-12-03', 'diario', '14:47:00', '17:47:00', 3, NULL, 'en_proceso'),
(4, 1, 5, 'entrada principal', '2025-12-03', 'diario', '15:38:00', '18:38:00', 2, NULL, 'en_proceso'),
(5, 1, 2, 'entrada', '2025-12-04', 'diario', '01:21:00', '04:21:00', 2, NULL, 'planificada'),
(6, 1, 2, 'entrada', '2025-12-04', 'diario', '01:21:00', '04:21:00', 2, NULL, 'planificada'),
(7, 1, 1, 'entrada', '2025-12-04', 'diario', '00:23:00', '04:23:00', 2, NULL, 'planificada'),
(8, 1, 4, 'entrada', '2025-12-04', 'diario', '23:35:00', '01:36:00', 3, NULL, 'en_proceso'),
(9, 1, 4, 'entrada principal', '2025-12-04', 'diario', '09:35:00', '10:35:00', 8, NULL, 'en_proceso'),
(10, 1, 1, '', '2025-12-04', 'diario', '10:46:00', '11:46:00', 8, NULL, 'en_proceso');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

CREATE TABLE `permisos` (
  `id` int(11) NOT NULL,
  `modulo` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `permisos`
--

INSERT INTO `permisos` (`id`, `modulo`, `nombre`, `codigo`, `descripcion`, `estado`) VALUES
(1, 'pruebas', 'Ver Pruebas', 'pruebas.ver', 'Ver listado de pruebas', 1),
(2, 'pruebas', 'Crear Pruebas', 'pruebas.crear', 'Realizar nuevas pruebas', 1),
(3, 'pruebas', 'Editar Pruebas', 'pruebas.editar', 'Modificar pruebas existentes', 1),
(4, 'pruebas', 'Eliminar Pruebas', 'pruebas.eliminar', 'Eliminar pruebas', 1),
(5, 'pruebas', 'Aprobar Re-test', 'pruebas.aprobar_retest', 'Aprobar realización de re-test', 1),
(6, 'configuracion', 'Ver Configuración', 'config.ver', 'Ver configuración del sistema', 1),
(7, 'configuracion', 'Editar Configuración', 'config.editar', 'Modificar configuración', 1),
(8, 'configuracion', 'Gestionar Roles', 'config.roles', 'Administrar roles y permisos', 1),
(9, 'configuracion', 'Realizar Backups', 'config.backup', 'Realizar backups del sistema', 1),
(10, 'usuarios', 'Ver Usuarios', 'usuarios.ver', 'Ver listado de usuarios', 1),
(11, 'usuarios', 'Crear Usuarios', 'usuarios.crear', 'Crear nuevos usuarios', 1),
(12, 'usuarios', 'Editar Usuarios', 'usuarios.editar', 'Modificar usuarios', 1),
(13, 'usuarios', 'Eliminar Usuarios', 'usuarios.eliminar', 'Eliminar usuarios', 1),
(14, 'reportes', 'Ver Reportes', 'reportes.ver', 'Ver reportes', 1),
(15, 'reportes', 'Exportar Reportes', 'reportes.exportar', 'Exportar reportes', 1),
(16, 'reportes', 'Reportes Gerenciales', 'reportes.gerenciales', 'Acceso a reportes gerenciales', 1),
(17, 'vehiculos', 'Ver Vehículos', 'vehiculos.ver', 'Ver listado de vehículos', 1),
(18, 'vehiculos', 'Gestionar Vehículos', 'vehiculos.gestionar', 'Crear, editar y eliminar vehículos', 1),
(19, 'alcoholimetros', 'Ver Alcoholímetros', 'alcoholimetros.ver', 'Ver listado de alcoholímetros', 1),
(20, 'alcoholimetros', 'Gestionar Alcoholímetros', 'alcoholimetros.gestionar', 'Administrar alcoholímetros', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `planes`
--

CREATE TABLE `planes` (
  `id` int(11) NOT NULL,
  `nombre_plan` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio_mensual` decimal(10,2) NOT NULL,
  `limite_pruebas_mes` int(11) DEFAULT 1000,
  `limite_usuarios` int(11) DEFAULT 5,
  `limite_conductores` int(11) DEFAULT 50,
  `limite_vehiculos` int(11) DEFAULT 50,
  `limite_alcoholimetros` int(11) DEFAULT 10,
  `reportes_avanzados` tinyint(1) DEFAULT 0,
  `soporte_prioritario` tinyint(1) DEFAULT 0,
  `acceso_api` tinyint(1) DEFAULT 0,
  `almacenamiento_fotos` int(11) DEFAULT 100,
  `backup_automatico` tinyint(1) DEFAULT 1,
  `retencion_datos_meses` int(11) DEFAULT 12,
  `estado` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `integraciones` tinyint(1) DEFAULT 0,
  `multi_sede` tinyint(1) DEFAULT 0,
  `personalizacion` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `planes`
--

INSERT INTO `planes` (`id`, `nombre_plan`, `descripcion`, `precio_mensual`, `limite_pruebas_mes`, `limite_usuarios`, `limite_conductores`, `limite_vehiculos`, `limite_alcoholimetros`, `reportes_avanzados`, `soporte_prioritario`, `acceso_api`, `almacenamiento_fotos`, `backup_automatico`, `retencion_datos_meses`, `estado`, `fecha_creacion`, `fecha_actualizacion`, `integraciones`, `multi_sede`, `personalizacion`) VALUES
(1, 'Free', '', 0.00, 30, 1, 50, 50, 1, 0, 0, 0, 100, 1, 12, 1, '2025-11-25 12:10:30', '2025-12-06 05:30:45', 0, 0, 0),
(2, 'Starterrrrr', '', 49.00, 500, 5, 50, 50, 3, 0, 0, 0, 100, 1, 12, 1, '2025-11-25 12:10:30', '2025-12-05 17:36:08', 0, 0, 0),
(3, 'Professional', NULL, 149.00, 2000, 20, 50, 50, 10, 1, 1, 1, 100, 1, 12, 1, '2025-11-25 12:10:30', '2025-12-04 16:33:52', 1, 0, 1),
(4, 'Enterprise', NULL, 499.00, 99999, 99999, 50, 50, 99999, 1, 1, 1, 100, 1, 12, 1, '2025-11-25 12:10:30', '2025-12-04 16:33:52', 1, 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos_inventario`
--

CREATE TABLE `productos_inventario` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `almacen_id` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `unidad_medida` varchar(20) DEFAULT 'UNIDAD',
  `stock_actual` decimal(10,2) DEFAULT 0.00,
  `stock_minimo` decimal(10,2) DEFAULT 0.00,
  `stock_maximo` decimal(10,2) DEFAULT 0.00,
  `ubicacion` varchar(100) DEFAULT NULL COMMENT 'Estante, pasillo, fila, columna',
  `costo_promedio` decimal(10,2) DEFAULT 0.00,
  `precio_venta` decimal(10,2) DEFAULT 0.00,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `programacion_backups`
--

CREATE TABLE `programacion_backups` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `tipo_backup` enum('completo','incremental','diferencial') DEFAULT 'completo',
  `frecuencia` enum('diario','semanal','mensual') DEFAULT 'diario',
  `hora_ejecucion` time DEFAULT '02:00:00',
  `dias_semana` varchar(20) DEFAULT NULL,
  `dia_mes` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `ultima_ejecucion` timestamp NULL DEFAULT NULL,
  `proxima_ejecucion` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pruebas`
--

CREATE TABLE `pruebas` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `alcoholimetro_id` int(11) NOT NULL,
  `conductor_id` int(11) NOT NULL,
  `supervisor_id` int(11) NOT NULL,
  `vehiculo_id` int(11) DEFAULT NULL,
  `nivel_alcohol` decimal(5,3) NOT NULL,
  `limite_permisible` decimal(5,3) DEFAULT 0.000,
  `resultado` enum('aprobado','reprobado') NOT NULL,
  `es_retest` tinyint(1) DEFAULT 0,
  `prueba_padre_id` int(11) DEFAULT NULL,
  `intento_numero` int(11) DEFAULT 1,
  `motivo_retest` varchar(100) DEFAULT NULL,
  `aprobado_por_supervisor` tinyint(1) DEFAULT 0,
  `fecha_aprobacion_retest` timestamp NULL DEFAULT NULL,
  `latitud` decimal(10,8) DEFAULT NULL,
  `longitud` decimal(11,8) DEFAULT NULL,
  `direccion_geocodificada` text DEFAULT NULL,
  `foto_evidencia` varchar(255) DEFAULT NULL,
  `firma_conductor` varchar(255) DEFAULT NULL,
  `firma_supervisor` varchar(255) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_prueba` timestamp NULL DEFAULT current_timestamp(),
  `sync_movil` tinyint(1) DEFAULT 0,
  `dispositivo_movil` varchar(100) DEFAULT NULL,
  `hash_verificacion` varchar(100) DEFAULT NULL,
  `temperatura_ambiente` decimal(4,2) DEFAULT NULL,
  `humedad_ambiente` decimal(4,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `pruebas`
--

INSERT INTO `pruebas` (`id`, `cliente_id`, `alcoholimetro_id`, `conductor_id`, `supervisor_id`, `vehiculo_id`, `nivel_alcohol`, `limite_permisible`, `resultado`, `es_retest`, `prueba_padre_id`, `intento_numero`, `motivo_retest`, `aprobado_por_supervisor`, `fecha_aprobacion_retest`, `latitud`, `longitud`, `direccion_geocodificada`, `foto_evidencia`, `firma_conductor`, `firma_supervisor`, `observaciones`, `fecha_prueba`, `sync_movil`, `dispositivo_movil`, `hash_verificacion`, `temperatura_ambiente`, `humedad_ambiente`) VALUES
(1, 1, 3, 6, 3, 1, 0.100, 0.000, 'reprobado', 0, NULL, 1, NULL, 0, NULL, -12.15692800, -76.99169280, NULL, NULL, NULL, NULL, 'ninguna', '2025-11-30 04:05:37', 0, NULL, NULL, NULL, NULL),
(2, 1, 3, 6, 2, NULL, 0.100, 0.000, 'reprobado', 0, NULL, 1, NULL, 0, NULL, 0.00000000, 0.00000000, NULL, NULL, NULL, NULL, 'demo', '2025-12-03 19:26:38', 0, NULL, NULL, NULL, NULL),
(3, 1, 3, 6, 2, 1, 0.050, 0.000, 'reprobado', 0, NULL, 1, NULL, 0, NULL, 0.00000000, 0.00000000, NULL, NULL, NULL, NULL, '', '2025-12-03 19:29:47', 0, NULL, NULL, NULL, NULL),
(4, 1, 3, 6, 2, NULL, 0.000, 0.000, 'aprobado', 0, NULL, 1, NULL, 0, NULL, 0.00000000, 0.00000000, NULL, NULL, NULL, NULL, '', '2025-12-03 19:31:03', 0, NULL, NULL, NULL, NULL),
(5, 1, 3, 6, 2, NULL, 0.000, 0.000, 'aprobado', 0, NULL, 1, NULL, 0, NULL, 0.00000000, 0.00000000, NULL, NULL, NULL, NULL, '', '2025-12-03 19:38:28', 0, NULL, NULL, NULL, NULL),
(6, 1, 3, 6, 2, 1, 0.100, 0.000, 'reprobado', 0, NULL, 1, NULL, 0, NULL, 0.00000000, 0.00000000, NULL, NULL, NULL, NULL, '', '2025-12-03 19:44:15', 0, NULL, NULL, NULL, NULL),
(7, 1, 3, 6, 2, 1, 0.000, 0.000, 'aprobado', 0, NULL, 1, NULL, 0, NULL, 0.00000000, 0.00000000, NULL, NULL, NULL, NULL, 's', '2025-12-03 20:30:38', 0, NULL, NULL, NULL, NULL),
(8, 1, 3, 6, 2, 1, 0.080, 0.000, 'reprobado', 0, NULL, 1, NULL, 0, NULL, 0.00000000, 0.00000000, NULL, NULL, NULL, NULL, 'ddd', '2025-12-03 20:31:20', 0, NULL, NULL, NULL, NULL),
(9, 1, 3, 6, 2, 2, 0.080, 0.000, 'reprobado', 0, NULL, 1, NULL, 0, NULL, 0.00000000, 0.00000000, NULL, NULL, NULL, NULL, '', '2025-12-03 20:32:07', 0, NULL, NULL, NULL, NULL),
(10, 1, 3, 6, 2, 1, 0.020, 0.000, 'reprobado', 0, NULL, 1, NULL, 0, NULL, 0.00000000, 0.00000000, NULL, NULL, NULL, NULL, '', '2025-12-03 20:33:42', 0, NULL, NULL, NULL, NULL),
(11, 1, 3, 6, 2, 3, 0.080, 0.000, 'reprobado', 0, NULL, 1, NULL, 0, NULL, 0.00000000, 0.00000000, NULL, NULL, NULL, NULL, '', '2025-12-03 20:34:45', 0, NULL, NULL, NULL, NULL),
(12, 1, 3, 6, 2, 1, 0.080, 0.000, 'reprobado', 0, NULL, 1, NULL, 0, NULL, 0.00000000, 0.00000000, NULL, NULL, NULL, NULL, '', '2025-12-03 20:35:21', 0, NULL, NULL, NULL, NULL),
(14, 1, 3, 6, 2, 1, 0.080, 0.000, 'reprobado', 0, NULL, 1, NULL, 0, NULL, 0.00000000, 0.00000000, NULL, NULL, NULL, NULL, 'ddd', '2025-12-03 20:39:27', 0, NULL, NULL, NULL, NULL),
(15, 1, 3, 6, 2, 1, 0.080, 0.000, 'reprobado', 0, NULL, 1, NULL, 0, NULL, 0.00000000, 0.00000000, NULL, NULL, NULL, NULL, 'dasdad', '2025-12-04 04:02:15', 0, NULL, NULL, NULL, NULL),
(16, 1, 3, 6, 2, 1, 0.080, 0.000, 'reprobado', 0, NULL, 1, NULL, 0, NULL, 0.00000000, 0.00000000, NULL, NULL, NULL, NULL, '', '2025-12-04 04:37:01', 0, NULL, NULL, NULL, NULL),
(17, 1, 3, 7, 2, 3, 0.080, 0.000, 'reprobado', 0, NULL, 1, NULL, 0, NULL, 0.00000000, 0.00000000, NULL, NULL, NULL, NULL, '', '2025-12-04 14:36:01', 0, NULL, NULL, NULL, NULL),
(18, 1, 3, 7, 2, 3, 0.080, 0.000, 'reprobado', 0, NULL, 1, NULL, 0, NULL, 0.00000000, 0.00000000, NULL, NULL, NULL, NULL, '', '2025-12-04 15:47:45', 0, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pruebas_protocolo`
--

CREATE TABLE `pruebas_protocolo` (
  `id` int(11) NOT NULL,
  `operacion_id` int(11) NOT NULL,
  `conductor_id` int(11) NOT NULL,
  `acta_id` int(11) DEFAULT NULL,
  `encuesta_id` int(11) DEFAULT NULL,
  `prueba_alcohol_id` int(11) DEFAULT NULL,
  `widmark_id` int(11) DEFAULT NULL,
  `informe_positivo_id` int(11) DEFAULT NULL,
  `paso_actual` int(11) DEFAULT 1,
  `completada` tinyint(1) DEFAULT 0,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `pruebas_protocolo`
--

INSERT INTO `pruebas_protocolo` (`id`, `operacion_id`, `conductor_id`, `acta_id`, `encuesta_id`, `prueba_alcohol_id`, `widmark_id`, `informe_positivo_id`, `paso_actual`, `completada`, `fecha_creacion`) VALUES
(1, 1, 6, 1, NULL, NULL, NULL, NULL, 3, 0, '2025-12-03 19:22:26'),
(2, 1, 6, 2, NULL, NULL, NULL, NULL, 3, 0, '2025-12-03 19:23:38'),
(3, 1, 6, 3, 1, NULL, NULL, NULL, 4, 0, '2025-12-03 19:24:32'),
(4, 1, 6, 4, 2, NULL, NULL, NULL, 4, 0, '2025-12-03 19:25:20'),
(5, 1, 6, 5, NULL, 2, NULL, NULL, 5, 0, '2025-12-03 19:26:14'),
(6, 1, 6, 6, NULL, 3, NULL, NULL, 5, 0, '2025-12-03 19:29:30'),
(7, 1, 6, 7, NULL, 4, NULL, NULL, 7, 0, '2025-12-03 19:30:49'),
(8, 1, 6, 8, 3, NULL, NULL, NULL, 4, 0, '2025-12-03 19:31:16'),
(9, 1, 6, 9, 4, NULL, NULL, NULL, 4, 0, '2025-12-03 19:37:47'),
(10, 1, 6, 10, NULL, 5, NULL, NULL, 7, 0, '2025-12-03 19:38:16'),
(11, 1, 6, 11, 5, NULL, NULL, NULL, 4, 0, '2025-12-03 19:43:13'),
(12, 1, 6, 12, NULL, 6, NULL, NULL, 5, 0, '2025-12-03 19:43:48'),
(13, 3, 6, 13, NULL, NULL, NULL, NULL, 3, 0, '2025-12-03 19:49:19'),
(14, 3, 6, 14, 6, NULL, NULL, NULL, 4, 0, '2025-12-03 19:49:55'),
(15, 3, 6, 15, NULL, NULL, NULL, NULL, 3, 0, '2025-12-03 20:03:54'),
(16, 2, 6, 16, 7, 7, NULL, NULL, 5, 1, '2025-12-03 20:30:21'),
(17, 2, 6, 17, 8, 8, NULL, NULL, 5, 0, '2025-12-03 20:31:02'),
(18, 2, 6, 18, 9, 9, NULL, 2, 7, 1, '2025-12-03 20:31:54'),
(19, 2, 6, 19, 10, 10, NULL, NULL, 5, 0, '2025-12-03 20:33:21'),
(20, 2, 6, 20, 11, 11, NULL, NULL, 5, 0, '2025-12-03 20:34:33'),
(21, 2, 6, 21, 12, 12, NULL, NULL, 5, 0, '2025-12-03 20:35:10'),
(22, 4, 6, 22, 13, NULL, NULL, NULL, 4, 0, '2025-12-03 20:38:34'),
(23, 4, 6, 23, NULL, NULL, NULL, NULL, 3, 0, '2025-12-03 20:39:01'),
(24, 3, 6, 24, 14, 14, NULL, 3, 7, 1, '2025-12-03 20:39:17'),
(25, 4, 6, 25, 15, 15, NULL, NULL, 5, 0, '2025-12-04 04:01:54'),
(26, 8, 6, 26, 16, 16, NULL, 4, 7, 1, '2025-12-04 04:36:36'),
(27, 9, 7, 27, 17, 17, NULL, 5, 7, 1, '2025-12-04 14:35:41'),
(28, 10, 7, 28, 18, 18, NULL, 6, 7, 1, '2025-12-04 15:47:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `registros_widmark`
--

CREATE TABLE `registros_widmark` (
  `id` int(11) NOT NULL,
  `prueba_id` int(11) DEFAULT NULL,
  `hora` time DEFAULT NULL,
  `tiempo_minutos` int(11) DEFAULT NULL,
  `bac` decimal(5,3) DEFAULT NULL,
  `observacion` text DEFAULT NULL,
  `comportamiento_curva` enum('ascendente','descendente','estable') DEFAULT NULL,
  `foto_toma` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `registros_widmark`
--

INSERT INTO `registros_widmark` (`id`, `prueba_id`, `hora`, `tiempo_minutos`, `bac`, `observacion`, `comportamiento_curva`, `foto_toma`) VALUES
(1, 3, '20:29:00', 10, 0.100, '', 'descendente', NULL),
(2, 8, '21:31:00', 10, 0.080, '', 'descendente', NULL),
(3, 10, '21:33:00', 0, 0.080, '', 'descendente', NULL),
(4, 11, '21:34:00', 10, 0.800, '', 'ascendente', NULL),
(5, 16, '05:37:00', 10, 0.080, '', '', NULL),
(6, 16, '05:37:00', 15, 0.070, '', 'descendente', NULL),
(7, 16, '05:37:00', 15, 0.000, '', 'estable', NULL),
(8, 17, '15:36:00', 10, 0.080, '', '', NULL),
(9, 17, '15:36:00', 15, 0.075, '', 'descendente', NULL),
(10, 17, '15:36:00', 15, 0.000, '', 'estable', NULL),
(11, 18, '16:47:00', 10, 0.080, '', '', NULL),
(12, 18, '16:47:00', 10, 0.075, '', 'descendente', NULL),
(13, 18, '16:48:00', 10, 0.000, '', 'estable', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `regulaciones_alcohol`
--

CREATE TABLE `regulaciones_alcohol` (
  `id` int(11) NOT NULL,
  `pais` varchar(100) NOT NULL,
  `codigo_pais` varchar(5) NOT NULL,
  `limite_permisible` decimal(5,3) NOT NULL,
  `unidad_medida` varchar(10) DEFAULT 'g/L',
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `regulaciones_alcohol`
--

INSERT INTO `regulaciones_alcohol` (`id`, `pais`, `codigo_pais`, `limite_permisible`, `unidad_medida`, `descripcion`, `activo`) VALUES
(1, 'Perú', 'PE', 0.000, 'g/L', 'Límite cero alcohol para conductores', 1),
(2, 'Chile', 'CL', 0.030, 'g/L', 'Límite general para conductores', 1),
(3, 'Argentina', 'AR', 0.000, 'g/L', 'Límite cero alcohol para conductores', 1),
(4, 'Colombia', 'CO', 0.020, 'g/L', 'Límite general para conductores', 1),
(5, 'México', 'MX', 0.040, 'g/L', 'Límite general para conductores', 1),
(6, 'España', 'ES', 0.050, 'g/L', 'Límite general para conductores experimentados', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `nivel` int(11) DEFAULT 1,
  `estado` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`, `descripcion`, `nivel`, `estado`, `fecha_creacion`) VALUES
(1, 'Super Admin', 'Acceso total al sistema', 10, 1, '2025-11-25 12:47:37'),
(2, 'Admin Cliente', 'Administrador de la empresa', 8, 1, '2025-11-25 12:47:37'),
(3, 'Supervisor', 'Supervisor de operaciones', 6, 1, '2025-11-25 12:47:37'),
(4, 'Operador', 'Operador de pruebas', 4, 1, '2025-11-25 12:47:37'),
(5, 'Conductor', 'Conductor - solo consulta', 2, 1, '2025-11-25 12:47:37'),
(6, 'Auditor', 'Solo lectura y reportes', 3, 1, '2025-11-25 12:47:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol_permisos`
--

CREATE TABLE `rol_permisos` (
  `id` int(11) NOT NULL,
  `rol_id` int(11) NOT NULL,
  `permiso_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rol_permisos`
--

INSERT INTO `rol_permisos` (`id`, `rol_id`, `permiso_id`) VALUES
(11, 1, 1),
(8, 1, 2),
(9, 1, 3),
(10, 1, 4),
(7, 1, 5),
(6, 1, 6),
(4, 1, 7),
(5, 1, 8),
(3, 1, 9),
(18, 1, 10),
(15, 1, 11),
(16, 1, 12),
(17, 1, 13),
(14, 1, 14),
(12, 1, 15),
(13, 1, 16),
(20, 1, 17),
(19, 1, 18),
(2, 1, 19),
(1, 1, 20);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sesiones`
--

CREATE TABLE `sesiones` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `token_sesion` varchar(255) NOT NULL,
  `dispositivo` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `fecha_inicio` timestamp NULL DEFAULT current_timestamp(),
  `fecha_expiracion` timestamp NULL DEFAULT NULL,
  `activa` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes_retest`
--

CREATE TABLE `solicitudes_retest` (
  `id` int(11) NOT NULL,
  `prueba_original_id` int(11) NOT NULL,
  `solicitado_por` int(11) NOT NULL,
  `motivo` text DEFAULT NULL,
  `estado` enum('pendiente','aprobado','rechazado') DEFAULT 'pendiente',
  `aprobado_por` int(11) DEFAULT NULL,
  `fecha_solicitud` timestamp NULL DEFAULT current_timestamp(),
  `fecha_resolucion` timestamp NULL DEFAULT NULL,
  `observaciones_aprobacion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `temas_personalizados`
--

CREATE TABLE `temas_personalizados` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `nombre_tema` varchar(50) DEFAULT 'default',
  `color_primario` varchar(7) DEFAULT '#2c3e50',
  `color_secundario` varchar(7) DEFAULT '#3498db',
  `color_exito` varchar(7) DEFAULT '#27ae60',
  `color_error` varchar(7) DEFAULT '#e74c3c',
  `color_advertencia` varchar(7) DEFAULT '#f39c12',
  `fuente_principal` varchar(100) DEFAULT 'Roboto',
  `tamanio_fuente` int(11) DEFAULT 14,
  `border_radius` int(11) DEFAULT 4,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tomas_inventario`
--

CREATE TABLE `tomas_inventario` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `almacen_id` int(11) NOT NULL,
  `codigo_toma` varchar(50) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `estado` enum('planificada','en_proceso','completada','verificando','ajustada','cancelada') DEFAULT 'planificada',
  `responsable_id` int(11) NOT NULL COMMENT 'Empleado que realiza el conteo',
  `supervisor_id` int(11) NOT NULL COMMENT 'Supervisor que revisa',
  `fecha_planificada` date DEFAULT NULL,
  `hora_inicio_planificada` time DEFAULT NULL,
  `hora_fin_planificada` time DEFAULT NULL,
  `fecha_inicio_real` datetime DEFAULT NULL,
  `fecha_fin_real` datetime DEFAULT NULL,
  `total_productos` int(11) DEFAULT 0,
  `productos_contados` int(11) DEFAULT 0,
  `productos_pendientes` int(11) DEFAULT 0,
  `productos_discrepancia` int(11) DEFAULT 0,
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ubicaciones_cliente`
--

CREATE TABLE `ubicaciones_cliente` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `nombre_ubicacion` varchar(255) NOT NULL,
  `tipo` enum('sede','area','unidad') DEFAULT 'sede',
  `estado` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `ubicaciones_cliente`
--

INSERT INTO `ubicaciones_cliente` (`id`, `cliente_id`, `nombre_ubicacion`, `tipo`, `estado`, `fecha_creacion`) VALUES
(1, 1, 'Lima/Planta 1', 'sede', 1, '2025-12-01 01:53:35'),
(2, 1, 'Lima/Planta 2', 'sede', 1, '2025-12-01 01:53:35'),
(3, 1, 'Lima/Planta 3', 'sede', 1, '2025-12-01 01:53:35'),
(4, 1, 'Almacen Central', 'area', 1, '2025-12-01 01:53:35'),
(5, 1, 'Oficinas Administrativas', 'area', 1, '2025-12-01 01:53:35'),
(6, 1, 'Taller Mecánico', 'unidad', 1, '2025-12-01 01:53:35'),
(7, 1, 'Patio de Vehículos', 'unidad', 1, '2025-12-01 01:53:35');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `dni` varchar(15) DEFAULT NULL,
  `rol` enum('super_admin','admin','supervisor','operador','conductor','auditor') NOT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `ultimo_login` timestamp NULL DEFAULT NULL,
  `token_recuperacion` varchar(100) DEFAULT NULL,
  `fecha_expiracion_token` datetime DEFAULT NULL,
  `intentos_login` int(11) DEFAULT 0,
  `bloqueado_hasta` datetime DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `cliente_id`, `nombre`, `apellido`, `email`, `password`, `telefono`, `dni`, `rol`, `foto_perfil`, `estado`, `ultimo_login`, `token_recuperacion`, `fecha_expiracion_token`, `intentos_login`, `bloqueado_hasta`, `fecha_creacion`) VALUES
(1, NULL, 'Superrrrr', 'Administrador', 'superadmin@sistema.com', '$2y$10$XpD9WN6lvltcSnwOgu1Tou0LQ.UC81SW27bnXGOBCQoCnuSEEi5BO', '', '', 'super_admin', NULL, 1, NULL, NULL, NULL, 0, NULL, '2025-11-25 12:10:30'),
(2, 1, 'Admin', 'Demo', 'admin@demo.com', '$2y$10$DmnfSjHrWRCf5R6wplmz2edyS5KvcnAZrdisj4WYtbbYbWrjKNrTK', NULL, '12345678', 'admin', NULL, 1, '2025-11-26 20:05:40', NULL, NULL, 0, NULL, '2025-11-25 12:10:30'),
(3, 1, 'Jose', 'Aguilar', 'fernando_7@hotmail.com', '$2y$10$RepK.j0a9EuYuf58yYEd1uMg4bAHfTDPJYPuNEqB1HpyEZnKbrMhW', '987456321', '40766447', 'supervisor', NULL, 1, NULL, NULL, NULL, 0, NULL, '2025-11-28 03:36:42'),
(6, 1, 'Pedro', 'Ramirez', 'radiantcenter.com@gmail.com', '$2y$10$KirIVOUPjbVHAzwWEh83TuWByE8M2hUoNNuIKJjYLInOGZsz5zcA.', '987456321', '40766447', 'conductor', NULL, 1, NULL, NULL, NULL, 0, NULL, '2025-11-29 07:08:47'),
(7, 1, 'Jeyko', 'Aguilar', 'jeykos@rocotodigital.com', '$2y$10$H5cUUmergiW.NUqNvD8nyuxXH11oC1CjMXrhxGtoSSpFDeMTZ6Kva', '987456325', '41001016', 'conductor', NULL, 1, NULL, NULL, NULL, 0, NULL, '2025-12-04 04:41:14'),
(8, 1, 'Carlos', 'Mallaupoma', 'cursosenlogistica@gmail.com', '$2y$10$NtOMJOIB7MZDEUrwzmtnAOi52Xdjr0BpcQmtqgV3O2GNX1ejIKv2i', '923442123', '41001016', 'operador', NULL, 1, NULL, NULL, NULL, 0, NULL, '2025-12-04 14:34:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vehiculos`
--

CREATE TABLE `vehiculos` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `placa` varchar(20) NOT NULL,
  `marca` varchar(50) DEFAULT NULL,
  `modelo` varchar(50) DEFAULT NULL,
  `anio` int(11) DEFAULT NULL,
  `color` varchar(30) DEFAULT NULL,
  `kilometraje` int(11) DEFAULT NULL,
  `estado` enum('activo','inactivo','mantenimiento') DEFAULT 'activo',
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `vehiculos`
--

INSERT INTO `vehiculos` (`id`, `cliente_id`, `placa`, `marca`, `modelo`, `anio`, `color`, `kilometraje`, `estado`, `fecha_creacion`) VALUES
(1, 1, 'ABC-123', 'Toyota', 'Hilux', 2023, 'Blanco', 15000, 'activo', '2025-11-25 12:10:30'),
(2, 1, 'DEF-456', 'Nissan', 'Frontier', 2022, 'Negro', 25000, 'activo', '2025-11-25 12:10:30'),
(3, 1, 'DEF-456777', 'Nissan', 'Frontier', 2022, 'Negro', 25000, 'activo', '2025-11-29 06:49:50'),
(4, 1, 'ABC-777', 'Mahndra', 'Guerrero', 2013, 'Negro', 1500, 'activo', '2025-12-04 04:42:09');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_retests`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_retests` (
`cliente_id` int(11)
,`prueba_id` int(11)
,`nivel_alcohol` decimal(5,3)
,`resultado` enum('aprobado','reprobado')
,`es_retest` tinyint(1)
,`intento_numero` int(11)
,`nivel_original` decimal(5,3)
,`resultado_original` enum('aprobado','reprobado')
,`minutos_diferencia` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_uso_planes`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_uso_planes` (
`cliente_id` int(11)
,`nombre_empresa` varchar(255)
,`nombre_plan` varchar(100)
,`limite_pruebas_mes` int(11)
,`pruebas_este_mes` bigint(21)
,`limite_usuarios` int(11)
,`usuarios_activos` bigint(21)
,`limite_alcoholimetros` int(11)
,`alcoholimetros_activos` bigint(21)
,`estado_pruebas` varchar(16)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `webhooks`
--

CREATE TABLE `webhooks` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `url` varchar(500) NOT NULL,
  `evento` varchar(50) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `secret_key` varchar(100) DEFAULT NULL,
  `reintentos` int(11) DEFAULT 3,
  `ultimo_intento` datetime DEFAULT NULL,
  `ultimo_estado` varchar(50) DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_retests`
--
DROP TABLE IF EXISTS `vista_retests`;

CREATE ALGORITHM=UNDEFINED DEFINER=`wepanel_juegosd2`@`localhost` SQL SECURITY DEFINER VIEW `vista_retests`  AS SELECT `p`.`cliente_id` AS `cliente_id`, `p`.`id` AS `prueba_id`, `p`.`nivel_alcohol` AS `nivel_alcohol`, `p`.`resultado` AS `resultado`, `p`.`es_retest` AS `es_retest`, `p`.`intento_numero` AS `intento_numero`, `p_prueba_original`.`nivel_alcohol` AS `nivel_original`, `p_prueba_original`.`resultado` AS `resultado_original`, timestampdiff(MINUTE,`p_prueba_original`.`fecha_prueba`,`p`.`fecha_prueba`) AS `minutos_diferencia` FROM (`pruebas` `p` left join `pruebas` `p_prueba_original` on(`p`.`prueba_padre_id` = `p_prueba_original`.`id`)) WHERE `p`.`es_retest` = 1 OR `p`.`prueba_padre_id` is not null ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_uso_planes`
--
DROP TABLE IF EXISTS `vista_uso_planes`;

CREATE ALGORITHM=UNDEFINED DEFINER=`wepanel_juegosd2`@`localhost` SQL SECURITY DEFINER VIEW `vista_uso_planes`  AS SELECT `c`.`id` AS `cliente_id`, `c`.`nombre_empresa` AS `nombre_empresa`, `p`.`nombre_plan` AS `nombre_plan`, `p`.`limite_pruebas_mes` AS `limite_pruebas_mes`, (select count(0) from `pruebas` `pr` where `pr`.`cliente_id` = `c`.`id` and month(`pr`.`fecha_prueba`) = month(current_timestamp())) AS `pruebas_este_mes`, `p`.`limite_usuarios` AS `limite_usuarios`, (select count(0) from `usuarios` `u` where `u`.`cliente_id` = `c`.`id`) AS `usuarios_activos`, `p`.`limite_alcoholimetros` AS `limite_alcoholimetros`, (select count(0) from `alcoholimetros` `a` where `a`.`cliente_id` = `c`.`id`) AS `alcoholimetros_activos`, CASE WHEN (select count(0) from `pruebas` `pr` where `pr`.`cliente_id` = `c`.`id` AND month(`pr`.`fecha_prueba`) = month(current_timestamp())) >= `p`.`limite_pruebas_mes` THEN 'LIMITE_ALCANZADO' ELSE 'DENTRO_LIMITE' END AS `estado_pruebas` FROM (`clientes` `c` join `planes` `p` on(`c`.`plan_id` = `p`.`id`)) ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `actas_consentimiento`
--
ALTER TABLE `actas_consentimiento`
  ADD PRIMARY KEY (`id`),
  ADD KEY `operacion_id` (`operacion_id`),
  ADD KEY `conductor_id` (`conductor_id`);

--
-- Indices de la tabla `alcoholimetros`
--
ALTER TABLE `alcoholimetros`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Indices de la tabla `almacenes_inventario`
--
ALTER TABLE `almacenes_inventario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `responsable_id` (`responsable_id`);

--
-- Indices de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `backups`
--
ALTER TABLE `backups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_backups_cliente` (`cliente_id`,`fecha_creacion`);

--
-- Indices de la tabla `checklists_operacion`
--
ALTER TABLE `checklists_operacion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `operacion_id` (`operacion_id`),
  ADD KEY `alcoholimetro_id` (`alcoholimetro_id`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ruc` (`ruc`),
  ADD UNIQUE KEY `token_api` (`token_api`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Indices de la tabla `configuraciones`
--
ALTER TABLE `configuraciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cliente` (`cliente_id`),
  ADD KEY `idx_configuraciones_cliente` (`cliente_id`);

--
-- Indices de la tabla `configuracion_notificaciones`
--
ALTER TABLE `configuracion_notificaciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cliente` (`cliente_id`);

--
-- Indices de la tabla `configuracion_sistema`
--
ALTER TABLE `configuracion_sistema`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clave` (`clave`);

--
-- Indices de la tabla `config_notificaciones_eventos`
--
ALTER TABLE `config_notificaciones_eventos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cliente_evento` (`cliente_id`,`evento`);

--
-- Indices de la tabla `detalle_toma_inventario`
--
ALTER TABLE `detalle_toma_inventario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `toma_id` (`toma_id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `encuestas_preliminares`
--
ALTER TABLE `encuestas_preliminares`
  ADD PRIMARY KEY (`id`),
  ADD KEY `acta_id` (`acta_id`);

--
-- Indices de la tabla `historial_niveles_alcohol`
--
ALTER TABLE `historial_niveles_alcohol`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `historial_planes`
--
ALTER TABLE `historial_planes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `plan_anterior_id` (`plan_anterior_id`),
  ADD KEY `plan_nuevo_id` (`plan_nuevo_id`),
  ADD KEY `cambio_por` (`cambio_por`);

--
-- Indices de la tabla `informes_positivos`
--
ALTER TABLE `informes_positivos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prueba_id` (`prueba_id`);

--
-- Indices de la tabla `jornadas_prueba`
--
ALTER TABLE `jornadas_prueba`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cliente_fecha` (`cliente_id`,`fecha`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_operador` (`operador_id`),
  ADD KEY `idx_ubicacion` (`ubicacion_id`);

--
-- Indices de la tabla `kardex_inventario`
--
ALTER TABLE `kardex_inventario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `licencias`
--
ALTER TABLE `licencias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_numero_licencia` (`cliente_id`,`numero_licencia`),
  ADD KEY `conductor_id` (`conductor_id`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Indices de la tabla `logs_configuracion`
--
ALTER TABLE `logs_configuracion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `logs_notificaciones`
--
ALTER TABLE `logs_notificaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `idx_logs_notif_fecha` (`fecha_envio`);

--
-- Indices de la tabla `notificaciones_inventario`
--
ALTER TABLE `notificaciones_inventario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `operaciones`
--
ALTER TABLE `operaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `ubicacion_id` (`ubicacion_id`),
  ADD KEY `operador_id` (`operador_id`);

--
-- Indices de la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Indices de la tabla `planes`
--
ALTER TABLE `planes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `productos_inventario`
--
ALTER TABLE `productos_inventario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_producto_cliente` (`cliente_id`,`codigo`),
  ADD KEY `almacen_id` (`almacen_id`);

--
-- Indices de la tabla `programacion_backups`
--
ALTER TABLE `programacion_backups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Indices de la tabla `pruebas`
--
ALTER TABLE `pruebas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `alcoholimetro_id` (`alcoholimetro_id`),
  ADD KEY `conductor_id` (`conductor_id`),
  ADD KEY `supervisor_id` (`supervisor_id`),
  ADD KEY `vehiculo_id` (`vehiculo_id`),
  ADD KEY `prueba_padre_id` (`prueba_padre_id`);

--
-- Indices de la tabla `pruebas_protocolo`
--
ALTER TABLE `pruebas_protocolo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `operacion_id` (`operacion_id`),
  ADD KEY `conductor_id` (`conductor_id`),
  ADD KEY `prueba_alcohol_id` (`prueba_alcohol_id`);

--
-- Indices de la tabla `registros_widmark`
--
ALTER TABLE `registros_widmark`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prueba_id` (`prueba_id`);

--
-- Indices de la tabla `regulaciones_alcohol`
--
ALTER TABLE `regulaciones_alcohol`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `rol_permisos`
--
ALTER TABLE `rol_permisos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_rol_permiso` (`rol_id`,`permiso_id`),
  ADD KEY `permiso_id` (`permiso_id`);

--
-- Indices de la tabla `sesiones`
--
ALTER TABLE `sesiones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_sesion` (`token_sesion`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `solicitudes_retest`
--
ALTER TABLE `solicitudes_retest`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prueba_original_id` (`prueba_original_id`),
  ADD KEY `solicitado_por` (`solicitado_por`),
  ADD KEY `aprobado_por` (`aprobado_por`);

--
-- Indices de la tabla `temas_personalizados`
--
ALTER TABLE `temas_personalizados`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cliente_tema` (`cliente_id`,`nombre_tema`);

--
-- Indices de la tabla `tomas_inventario`
--
ALTER TABLE `tomas_inventario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `almacen_id` (`almacen_id`),
  ADD KEY `responsable_id` (`responsable_id`),
  ADD KEY `supervisor_id` (`supervisor_id`);

--
-- Indices de la tabla `ubicaciones_cliente`
--
ALTER TABLE `ubicaciones_cliente`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Indices de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Indices de la tabla `webhooks`
--
ALTER TABLE `webhooks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_webhooks_cliente_evento` (`cliente_id`,`evento`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `actas_consentimiento`
--
ALTER TABLE `actas_consentimiento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de la tabla `alcoholimetros`
--
ALTER TABLE `alcoholimetros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `almacenes_inventario`
--
ALTER TABLE `almacenes_inventario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT de la tabla `backups`
--
ALTER TABLE `backups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `checklists_operacion`
--
ALTER TABLE `checklists_operacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `configuraciones`
--
ALTER TABLE `configuraciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `configuracion_notificaciones`
--
ALTER TABLE `configuracion_notificaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `configuracion_sistema`
--
ALTER TABLE `configuracion_sistema`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `config_notificaciones_eventos`
--
ALTER TABLE `config_notificaciones_eventos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `detalle_toma_inventario`
--
ALTER TABLE `detalle_toma_inventario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `encuestas_preliminares`
--
ALTER TABLE `encuestas_preliminares`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `historial_niveles_alcohol`
--
ALTER TABLE `historial_niveles_alcohol`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_planes`
--
ALTER TABLE `historial_planes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `informes_positivos`
--
ALTER TABLE `informes_positivos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `jornadas_prueba`
--
ALTER TABLE `jornadas_prueba`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `kardex_inventario`
--
ALTER TABLE `kardex_inventario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `licencias`
--
ALTER TABLE `licencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `logs_configuracion`
--
ALTER TABLE `logs_configuracion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `logs_notificaciones`
--
ALTER TABLE `logs_notificaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `notificaciones_inventario`
--
ALTER TABLE `notificaciones_inventario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `operaciones`
--
ALTER TABLE `operaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `permisos`
--
ALTER TABLE `permisos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `planes`
--
ALTER TABLE `planes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `productos_inventario`
--
ALTER TABLE `productos_inventario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `programacion_backups`
--
ALTER TABLE `programacion_backups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pruebas`
--
ALTER TABLE `pruebas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `pruebas_protocolo`
--
ALTER TABLE `pruebas_protocolo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de la tabla `registros_widmark`
--
ALTER TABLE `registros_widmark`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `regulaciones_alcohol`
--
ALTER TABLE `regulaciones_alcohol`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `rol_permisos`
--
ALTER TABLE `rol_permisos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT de la tabla `sesiones`
--
ALTER TABLE `sesiones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `solicitudes_retest`
--
ALTER TABLE `solicitudes_retest`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `temas_personalizados`
--
ALTER TABLE `temas_personalizados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tomas_inventario`
--
ALTER TABLE `tomas_inventario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ubicaciones_cliente`
--
ALTER TABLE `ubicaciones_cliente`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `webhooks`
--
ALTER TABLE `webhooks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `actas_consentimiento`
--
ALTER TABLE `actas_consentimiento`
  ADD CONSTRAINT `actas_consentimiento_ibfk_1` FOREIGN KEY (`operacion_id`) REFERENCES `operaciones` (`id`),
  ADD CONSTRAINT `actas_consentimiento_ibfk_2` FOREIGN KEY (`conductor_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `alcoholimetros`
--
ALTER TABLE `alcoholimetros`
  ADD CONSTRAINT `alcoholimetros_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `almacenes_inventario`
--
ALTER TABLE `almacenes_inventario`
  ADD CONSTRAINT `almacenes_inventario_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `almacenes_inventario_ibfk_2` FOREIGN KEY (`responsable_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `backups`
--
ALTER TABLE `backups`
  ADD CONSTRAINT `backups_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `checklists_operacion`
--
ALTER TABLE `checklists_operacion`
  ADD CONSTRAINT `checklists_operacion_ibfk_1` FOREIGN KEY (`operacion_id`) REFERENCES `operaciones` (`id`),
  ADD CONSTRAINT `checklists_operacion_ibfk_2` FOREIGN KEY (`alcoholimetro_id`) REFERENCES `alcoholimetros` (`id`);

--
-- Filtros para la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `clientes_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `planes` (`id`);

--
-- Filtros para la tabla `configuraciones`
--
ALTER TABLE `configuraciones`
  ADD CONSTRAINT `configuraciones_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `configuracion_notificaciones`
--
ALTER TABLE `configuracion_notificaciones`
  ADD CONSTRAINT `config_notificaciones_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `config_notificaciones_eventos`
--
ALTER TABLE `config_notificaciones_eventos`
  ADD CONSTRAINT `config_notificaciones_eventos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `detalle_toma_inventario`
--
ALTER TABLE `detalle_toma_inventario`
  ADD CONSTRAINT `detalle_toma_inventario_ibfk_1` FOREIGN KEY (`toma_id`) REFERENCES `tomas_inventario` (`id`),
  ADD CONSTRAINT `detalle_toma_inventario_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos_inventario` (`id`);

--
-- Filtros para la tabla `encuestas_preliminares`
--
ALTER TABLE `encuestas_preliminares`
  ADD CONSTRAINT `encuestas_preliminares_ibfk_1` FOREIGN KEY (`acta_id`) REFERENCES `actas_consentimiento` (`id`);

--
-- Filtros para la tabla `historial_niveles_alcohol`
--
ALTER TABLE `historial_niveles_alcohol`
  ADD CONSTRAINT `historial_niveles_alcohol_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `historial_niveles_alcohol_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `historial_planes`
--
ALTER TABLE `historial_planes`
  ADD CONSTRAINT `historial_planes_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `historial_planes_ibfk_2` FOREIGN KEY (`plan_anterior_id`) REFERENCES `planes` (`id`),
  ADD CONSTRAINT `historial_planes_ibfk_3` FOREIGN KEY (`plan_nuevo_id`) REFERENCES `planes` (`id`),
  ADD CONSTRAINT `historial_planes_ibfk_4` FOREIGN KEY (`cambio_por`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `informes_positivos`
--
ALTER TABLE `informes_positivos`
  ADD CONSTRAINT `informes_positivos_ibfk_1` FOREIGN KEY (`prueba_id`) REFERENCES `pruebas` (`id`);

--
-- Filtros para la tabla `jornadas_prueba`
--
ALTER TABLE `jornadas_prueba`
  ADD CONSTRAINT `jornadas_prueba_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jornadas_prueba_ibfk_2` FOREIGN KEY (`operador_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `jornadas_prueba_ibfk_3` FOREIGN KEY (`ubicacion_id`) REFERENCES `ubicaciones_cliente` (`id`);

--
-- Filtros para la tabla `kardex_inventario`
--
ALTER TABLE `kardex_inventario`
  ADD CONSTRAINT `kardex_inventario_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `kardex_inventario_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos_inventario` (`id`),
  ADD CONSTRAINT `kardex_inventario_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `licencias`
--
ALTER TABLE `licencias`
  ADD CONSTRAINT `licencias_ibfk_1` FOREIGN KEY (`conductor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `licencias_ibfk_2` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `logs_configuracion`
--
ALTER TABLE `logs_configuracion`
  ADD CONSTRAINT `logs_configuracion_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `logs_configuracion_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `logs_notificaciones`
--
ALTER TABLE `logs_notificaciones`
  ADD CONSTRAINT `logs_notificaciones_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `notificaciones_inventario`
--
ALTER TABLE `notificaciones_inventario`
  ADD CONSTRAINT `notificaciones_inventario_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `notificaciones_inventario_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `operaciones`
--
ALTER TABLE `operaciones`
  ADD CONSTRAINT `operaciones_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `operaciones_ibfk_2` FOREIGN KEY (`ubicacion_id`) REFERENCES `ubicaciones_cliente` (`id`),
  ADD CONSTRAINT `operaciones_ibfk_3` FOREIGN KEY (`operador_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `productos_inventario`
--
ALTER TABLE `productos_inventario`
  ADD CONSTRAINT `productos_inventario_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `productos_inventario_ibfk_2` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes_inventario` (`id`);

--
-- Filtros para la tabla `programacion_backups`
--
ALTER TABLE `programacion_backups`
  ADD CONSTRAINT `programacion_backups_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`);

--
-- Filtros para la tabla `pruebas`
--
ALTER TABLE `pruebas`
  ADD CONSTRAINT `pruebas_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pruebas_ibfk_2` FOREIGN KEY (`alcoholimetro_id`) REFERENCES `alcoholimetros` (`id`),
  ADD CONSTRAINT `pruebas_ibfk_3` FOREIGN KEY (`conductor_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `pruebas_ibfk_4` FOREIGN KEY (`supervisor_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `pruebas_ibfk_5` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`),
  ADD CONSTRAINT `pruebas_ibfk_6` FOREIGN KEY (`prueba_padre_id`) REFERENCES `pruebas` (`id`);

--
-- Filtros para la tabla `pruebas_protocolo`
--
ALTER TABLE `pruebas_protocolo`
  ADD CONSTRAINT `pruebas_protocolo_ibfk_1` FOREIGN KEY (`operacion_id`) REFERENCES `operaciones` (`id`),
  ADD CONSTRAINT `pruebas_protocolo_ibfk_2` FOREIGN KEY (`conductor_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `pruebas_protocolo_ibfk_3` FOREIGN KEY (`prueba_alcohol_id`) REFERENCES `pruebas` (`id`);

--
-- Filtros para la tabla `registros_widmark`
--
ALTER TABLE `registros_widmark`
  ADD CONSTRAINT `registros_widmark_ibfk_1` FOREIGN KEY (`prueba_id`) REFERENCES `pruebas` (`id`);

--
-- Filtros para la tabla `rol_permisos`
--
ALTER TABLE `rol_permisos`
  ADD CONSTRAINT `rol_permisos_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rol_permisos_ibfk_2` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `sesiones`
--
ALTER TABLE `sesiones`
  ADD CONSTRAINT `sesiones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `solicitudes_retest`
--
ALTER TABLE `solicitudes_retest`
  ADD CONSTRAINT `solicitudes_retest_ibfk_1` FOREIGN KEY (`prueba_original_id`) REFERENCES `pruebas` (`id`),
  ADD CONSTRAINT `solicitudes_retest_ibfk_2` FOREIGN KEY (`solicitado_por`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `solicitudes_retest_ibfk_3` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `temas_personalizados`
--
ALTER TABLE `temas_personalizados`
  ADD CONSTRAINT `temas_personalizados_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `tomas_inventario`
--
ALTER TABLE `tomas_inventario`
  ADD CONSTRAINT `tomas_inventario_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `tomas_inventario_ibfk_2` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes_inventario` (`id`),
  ADD CONSTRAINT `tomas_inventario_ibfk_3` FOREIGN KEY (`responsable_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `tomas_inventario_ibfk_4` FOREIGN KEY (`supervisor_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD CONSTRAINT `vehiculos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `webhooks`
--
ALTER TABLE `webhooks`
  ADD CONSTRAINT `webhooks_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
