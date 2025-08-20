-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 20-08-2025 a las 04:43:45
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `testbdpa`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categoria`
--

CREATE TABLE `categoria` (
  `codigo` int(11) NOT NULL,
  `nombre` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categoria`
--

INSERT INTO `categoria` (`codigo`, `nombre`) VALUES
(2323255, 'Congelados para freir'),
(2323256, 'Frutas Congeladas'),
(2323257, 'Pulpas'),
(2323258, 'Vegetales Congelados'),
(2323259, 'Otros');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ingreso`
--

CREATE TABLE `ingreso` (
  `codigo` int(11) NOT NULL,
  `producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `fechaDeIngreso` date NOT NULL,
  `horaDeIngreso` time NOT NULL,
  `fechaFabricacion` date NOT NULL,
  `fechaVencimiento` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Disparadores `ingreso`
--
DELIMITER $$
CREATE TRIGGER `after_ingreso_delete` AFTER DELETE ON `ingreso` FOR EACH ROW BEGIN
    UPDATE producto
    SET stock = stock - OLD.cantidad
    WHERE codigo = OLD.producto;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_ingreso_insert` AFTER INSERT ON `ingreso` FOR EACH ROW BEGIN
    UPDATE producto
    SET stock = stock + NEW.cantidad
    WHERE codigo = NEW.producto;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_ingreso_delete` BEFORE DELETE ON `ingreso` FOR EACH ROW BEGIN
    DECLARE current_stock INT;

    -- Obtener el stock actual del producto
    SELECT stock INTO current_stock FROM producto WHERE codigo = OLD.producto;

    -- Verificar si el stock se volvería negativo
    IF current_stock - OLD.cantidad < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede eliminar el ingreso, el stock se volvería negativo';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_ingreso_update` BEFORE UPDATE ON `ingreso` FOR EACH ROW BEGIN
    DECLARE current_stock INT;

    -- Obtener el stock actual del producto
    SELECT stock INTO current_stock FROM producto WHERE codigo = NEW.producto;

    -- Verificar si la cantidad ha cambiado
    IF OLD.cantidad <> NEW.cantidad THEN
        -- Calcular el nuevo stock
        SET current_stock = current_stock - OLD.cantidad + NEW.cantidad;

        -- Verificar si el nuevo stock sería negativo
        IF current_stock < 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede actualizar el ingreso, el stock se volvería negativo';
        ELSE
            -- Actualizar el stock en la tabla producto
            UPDATE producto
            SET stock = current_stock
            WHERE codigo = NEW.producto;
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `marca`
--

CREATE TABLE `marca` (
  `codigo` int(11) NOT NULL,
  `nombre` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `marca`
--

INSERT INTO `marca` (`codigo`, `nombre`) VALUES
(64, 'Canoa'),
(65, 'Otra (borr)');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto`
--

CREATE TABLE `producto` (
  `codigo` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `valor` int(11) NOT NULL,
  `marca` int(11) NOT NULL,
  `categoria` int(11) NOT NULL,
  `presentacion` text NOT NULL,
  `tamañoUnidad` float NOT NULL,
  `unidad` varchar(10) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `imagen` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `producto`
--

INSERT INTO `producto` (`codigo`, `nombre`, `valor`, `marca`, `categoria`, `presentacion`, `tamañoUnidad`, `unidad`, `stock`, `imagen`) VALUES
(2401, 'Pulpa Piña Colada Congelada (cambio', 1, 64, 2323257, 'Bolsa', 1100, 'Gr', 0, 'Durazno-1000g-300x300.png'),
(2402, 'Pulpa Mixta Congelada', 25000, 64, 2323257, 'Bolsa', 90, 'Gr', 20, 'cf9a7b6b-5261-4c45-a041-472946ba6676.jpg'),
(2403, 'Pulpa de Fresa Congelada', 1600, 64, 2323257, 'Bolsa', 1000, 'Gr', 2, '275847-500x500-ON-58.webp'),
(2404, 'Pulpa de Fresa Congelada', 1, 64, 2323257, 'Bolsa', 90, 'Gr', 0, 'pulpa-fresa-canoa-90-gr.png'),
(2405, 'Pulpa de Guanabana Congelada', 1, 64, 2323257, 'Caja', 1000, 'Gr', 0, '276419-500x500-ON-58.webp'),
(2406, 'Pulpa de Guanabana Congelada', 1, 64, 2323257, 'Bolsa', 90, 'Gr', 0, 'pulpa-de-guanabana-x100.jpg'),
(2407, 'Pulpa de Guayaba Congelada', 1, 64, 2323257, 'Bolsa', 1000, 'Gr', 0, 'Guayaba-1000g.png'),
(2408, 'Pulpa de Limon Congelada', 1, 64, 2323257, 'Bolsa', 1000, 'Gr', 0, 'Limon-1000g-768x576.png'),
(2409, 'Pulpa de Lulo Congelada', 1, 64, 2323257, 'Bolsa', 1000, 'Gr', 0, 'Lulo-1000g-768x576.png'),
(2410, 'Pulpa de Lulo Congelada', 1, 64, 2323257, 'Bolsa', 90, 'Gr', 0, 'pulpa-de-lulo-100gr.jpg'),
(2411, 'Pulpa de Mango Congelada', 1, 64, 2323257, 'Bolsa', 1000, 'Gr', 0, 'mango-1000g-768x576.png'),
(2412, 'Pulpa de Mango Congelada', 1, 64, 2323257, 'Bolsa', 90, 'Gr', 0, 'pulpa-de-mango-100gr.jpg'),
(2413, 'Pulpa de Mandarina Congelada', 1, 64, 2323257, 'Bolsa', 1000, 'Gr', 0, 'Mandarina-1000g-768x576.png'),
(2414, 'Pulpa de Mandarina Congelada', 1, 64, 2323257, 'Bolsa', 90, 'Gr', 0, 'category-thumb-3.jpg'),
(2415, 'Pulpa de Maracuya Congelada', 1, 64, 2323257, 'Bolsa', 1000, 'Gr', 0, 'maracuya-1000g-768x576.png'),
(2416, 'Pulpa de Maracuya Congelada', 1, 64, 2323257, 'Bolsa', 90, 'Gr', 0, 'pulpa-de-maracuya-de-marchi-100g.jpg'),
(2417, 'Pulpa de Mora Congelada', 1, 64, 2323257, 'Bolsa', 1000, 'Gr', 0, 'Mora-1000g-768x576.png'),
(2418, 'Pulpa de Mora Congelada', 1, 64, 2323257, 'Bolsa', 90, 'Gr', 0, 'pulpa-de-mora-100g.jpg'),
(2419, 'Pulpa de Pina Congelada', 1, 64, 2323257, 'Bolsa', 1000, 'Gr', 0, 'Pina-1000g-768x576.png'),
(2420, 'Pulpa de Tomate Congelada', 1, 64, 2323257, 'Bolsa', 1000, 'Gr', 0, 'Tomate-1000g-768x576.png'),
(2421, 'Pulpa de Tamarindo Congelada', 1, 64, 2323257, 'Bolsa', 1000, 'Gr', 0, 'Tamarindo-1000g-768x576.png'),
(2422, 'Pulpa de Uva Congelada', 1, 64, 2323257, 'Bolsa', 1000, 'Gr', 0, 'Uva-1000g-768x576.png');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reserva_carrito`
--

CREATE TABLE `reserva_carrito` (
  `id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `usuario_id` varchar(15) DEFAULT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `fecha_reserva` datetime NOT NULL DEFAULT current_timestamp(),
  `expiracion` datetime NOT NULL,
  `estado` enum('reservado','liberado','comprado') DEFAULT 'reservado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `reserva_carrito`
--

INSERT INTO `reserva_carrito` (`id`, `session_id`, `usuario_id`, `producto_id`, `cantidad`, `fecha_reserva`, `expiracion`, `estado`) VALUES
(1, 'jffkne7gen4nhm6dqu9n7m80rh', NULL, 2402, 1, '2025-07-21 01:17:27', '2025-07-21 01:47:27', 'liberado'),
(2, 'vlc85ikbm5lp6ium3u7jio05ri', NULL, 2402, 1, '2025-07-21 01:18:24', '2025-07-21 01:48:24', 'liberado'),
(3, 'jffkne7gen4nhm6dqu9n7m80rh', NULL, 2402, 1, '2025-07-21 01:18:45', '2025-07-21 01:48:45', 'liberado'),
(4, 'kt10id9ks1t42n9momsqjm84mv', NULL, 2402, 1, '2025-07-21 01:21:06', '2025-07-21 01:51:06', 'liberado'),
(5, 'vlc85ikbm5lp6ium3u7jio05ri', NULL, 2402, 2, '2025-07-21 01:21:46', '2025-07-21 01:51:46', 'liberado'),
(6, 'kt10id9ks1t42n9momsqjm84mv', NULL, 2402, 2, '2025-07-21 01:21:58', '2025-07-21 01:51:58', 'liberado'),
(7, 'kt10id9ks1t42n9momsqjm84mv', NULL, 2402, 1, '2025-07-21 01:22:12', '2025-07-21 01:52:12', 'liberado'),
(8, 'jffkne7gen4nhm6dqu9n7m80rh', NULL, 2402, 1, '2025-07-21 01:22:17', '2025-07-21 01:52:17', 'liberado'),
(9, 'vlc85ikbm5lp6ium3u7jio05ri', NULL, 2402, 1, '2025-07-21 01:22:58', '2025-07-21 01:52:58', 'liberado'),
(10, '461uv2md8bgedasmb0ghplg4aa', NULL, 2402, 1, '2025-07-21 01:34:19', '2025-07-21 02:04:19', 'liberado'),
(11, 'kt10id9ks1t42n9momsqjm84mv', NULL, 2402, 1, '2025-07-21 01:40:55', '2025-07-21 02:10:55', 'liberado'),
(12, 'jffkne7gen4nhm6dqu9n7m80rh', NULL, 2402, 1, '2025-07-21 01:41:02', '2025-07-21 02:11:02', 'liberado'),
(13, 'jffkne7gen4nhm6dqu9n7m80rh', NULL, 2402, 1, '2025-07-21 01:49:44', '2025-07-21 02:19:44', 'liberado'),
(14, 'kt10id9ks1t42n9momsqjm84mv', NULL, 2402, 1, '2025-07-21 01:49:56', '2025-07-21 02:19:56', 'liberado'),
(15, 'jffkne7gen4nhm6dqu9n7m80rh', NULL, 2402, 1, '2025-07-21 01:50:16', '2025-07-21 02:20:16', 'liberado'),
(16, 'vlc85ikbm5lp6ium3u7jio05ri', NULL, 2402, 1, '2025-07-21 02:05:12', '2025-07-21 02:35:12', 'liberado'),
(17, 'kt10id9ks1t42n9momsqjm84mv', NULL, 2402, 1, '2025-07-21 02:16:36', '2025-07-21 02:46:36', 'liberado'),
(18, 'jffkne7gen4nhm6dqu9n7m80rh', NULL, 2402, 1, '2025-07-21 02:30:37', '2025-07-21 03:00:37', 'liberado'),
(19, 'kt10id9ks1t42n9momsqjm84mv', NULL, 2402, 1, '2025-07-21 02:35:54', '2025-07-21 03:05:54', 'liberado'),
(20, 'jffkne7gen4nhm6dqu9n7m80rh', NULL, 2402, 1, '2025-07-21 02:36:38', '2025-07-21 03:06:38', 'liberado'),
(21, 'kt10id9ks1t42n9momsqjm84mv', NULL, 2402, 1, '2025-07-21 03:19:55', '2025-07-21 03:49:55', 'liberado'),
(22, 'n4pa6tmtj0ahghlr64t0kq3lp2', NULL, 2402, 1, '2025-07-21 03:20:35', '2025-07-21 03:50:35', 'liberado'),
(23, '6mbhhq6rciqqtev7ika9pvsr1f', NULL, 2402, 1, '2025-07-21 03:21:47', '2025-07-21 03:51:47', 'liberado'),
(24, 'jffkne7gen4nhm6dqu9n7m80rh', NULL, 2402, 1, '2025-07-21 03:37:11', '2025-07-21 04:07:11', 'liberado'),
(25, 'jffkne7gen4nhm6dqu9n7m80rh', NULL, 2402, 1, '2025-07-21 04:11:44', '2025-07-21 04:41:44', 'liberado'),
(26, 'jffkne7gen4nhm6dqu9n7m80rh', NULL, 2402, 1, '2025-07-21 04:52:50', '2025-07-21 05:22:50', 'liberado'),
(27, 'jffkne7gen4nhm6dqu9n7m80rh', NULL, 2402, 20, '2025-07-21 05:32:25', '2025-07-21 06:02:25', 'liberado'),
(28, 'jffkne7gen4nhm6dqu9n7m80rh', NULL, 2402, 1, '2025-07-21 05:47:51', '2025-07-21 06:17:51', 'liberado'),
(29, 'rgb6vu4pt902p93h2v3k6raevh', NULL, 2402, 1, '2025-07-22 00:39:48', '2025-07-22 01:09:48', 'liberado'),
(30, 'rgb6vu4pt902p93h2v3k6raevh', NULL, 2402, 1, '2025-07-22 01:13:37', '2025-07-22 01:43:37', 'liberado'),
(31, '89uddcg4f7g6h4iehc3g83ahde', NULL, 2402, 1, '2025-07-22 03:32:20', '2025-07-22 04:02:20', 'liberado'),
(32, '89uddcg4f7g6h4iehc3g83ahde', NULL, 2402, 1, '2025-07-22 03:45:31', '2025-07-22 04:15:31', 'liberado'),
(33, '89uddcg4f7g6h4iehc3g83ahde', NULL, 2402, 1, '2025-07-22 03:45:38', '2025-07-22 04:15:38', 'liberado'),
(34, 'nojij1k95m07e3mi63m2q8suv6', NULL, 2403, 1, '2025-08-02 00:38:47', '2025-08-02 01:08:47', 'liberado'),
(35, 'nojij1k95m07e3mi63m2q8suv6', NULL, 2402, 1, '2025-08-02 00:43:56', '2025-08-02 01:13:56', 'liberado'),
(36, 'thrbjdgbd7mitjm8tdi8d0mdlk', NULL, 2403, 1, '2025-08-02 01:49:41', '2025-08-02 02:19:41', 'liberado'),
(37, 'thrbjdgbd7mitjm8tdi8d0mdlk', NULL, 2403, 1, '2025-08-02 02:41:28', '2025-08-02 03:11:28', 'liberado'),
(38, 'thrbjdgbd7mitjm8tdi8d0mdlk', NULL, 2403, 1, '2025-08-02 03:02:58', '2025-08-02 03:32:58', 'liberado'),
(39, 'thrbjdgbd7mitjm8tdi8d0mdlk', NULL, 2402, 1, '2025-08-02 03:27:26', '2025-08-02 03:57:26', 'liberado'),
(40, 'thrbjdgbd7mitjm8tdi8d0mdlk', NULL, 2403, 1, '2025-08-02 06:18:19', '2025-08-02 06:48:19', 'liberado'),
(41, 'thrbjdgbd7mitjm8tdi8d0mdlk', NULL, 2402, 1, '2025-08-02 06:18:35', '2025-08-02 06:48:35', 'liberado'),
(42, 'v6aab1on7g9788pqe8r1095ifg', NULL, 2402, 1, '2025-08-09 23:52:27', '2025-08-10 00:22:27', 'liberado'),
(43, 'v6aab1on7g9788pqe8r1095ifg', NULL, 2402, 1, '2025-08-10 01:57:35', '2025-08-10 02:27:35', 'liberado'),
(44, 'v6aab1on7g9788pqe8r1095ifg', NULL, 2403, 1, '2025-08-10 01:57:43', '2025-08-10 02:27:43', 'liberado'),
(45, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2402, 1, '2025-08-09 19:05:30', '2025-08-10 02:35:30', 'liberado'),
(46, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2402, 1, '2025-08-09 19:05:30', '2025-08-10 02:35:30', 'liberado'),
(47, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2403, 1, '2025-08-09 19:05:50', '2025-08-10 02:35:50', 'liberado'),
(48, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2402, 1, '2025-08-09 19:05:50', '2025-08-10 02:35:50', 'liberado'),
(49, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2402, 1, '2025-08-09 19:05:50', '2025-08-10 02:35:50', 'liberado'),
(50, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2402, 2, '2025-08-09 19:05:50', '2025-08-10 02:35:50', 'liberado'),
(51, 'v6aab1on7g9788pqe8r1095ifg', NULL, 2402, 1, '2025-08-10 02:06:02', '2025-08-10 02:36:02', 'liberado'),
(52, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2402, 1, '2025-08-09 19:06:51', '2025-08-10 02:36:51', 'liberado'),
(53, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2403, 1, '2025-08-09 19:06:51', '2025-08-10 02:36:51', 'liberado'),
(54, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2402, 1, '2025-08-09 19:06:51', '2025-08-10 02:36:51', 'liberado'),
(55, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2402, 1, '2025-08-09 19:06:51', '2025-08-10 02:36:51', 'liberado'),
(56, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2402, 4, '2025-08-09 19:06:51', '2025-08-10 02:36:51', 'liberado'),
(57, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2403, 1, '2025-08-09 19:06:51', '2025-08-10 02:36:51', 'liberado'),
(58, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2402, 2, '2025-08-09 19:06:51', '2025-08-10 02:36:51', 'liberado'),
(59, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2402, 1, '2025-08-09 19:07:34', '2025-08-10 02:37:34', 'liberado'),
(60, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2403, 1, '2025-08-09 19:07:34', '2025-08-10 02:37:34', 'liberado'),
(61, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2402, 1, '2025-08-09 19:07:34', '2025-08-10 02:37:34', 'liberado'),
(62, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2402, 1, '2025-08-09 19:07:34', '2025-08-10 02:37:34', 'liberado'),
(63, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2403, 1, '2025-08-09 19:07:34', '2025-08-10 02:37:34', 'liberado'),
(64, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2402, 9, '2025-08-09 19:07:34', '2025-08-10 02:37:34', 'liberado'),
(65, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2402, 4, '2025-08-09 19:07:34', '2025-08-10 02:37:34', 'liberado'),
(66, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2403, 1, '2025-08-09 19:07:34', '2025-08-10 02:37:34', 'liberado'),
(67, 'v6aab1on7g9788pqe8r1095ifg', '1232591140', 2402, 2, '2025-08-09 19:07:34', '2025-08-10 02:37:34', 'liberado'),
(68, 'v6aab1on7g9788pqe8r1095ifg', NULL, 2403, 1, '2025-08-10 02:16:40', '2025-08-10 02:46:40', 'liberado'),
(69, 'cr6ed4bmjto1k15g60vddiauk1', NULL, 2403, 1, '2025-08-10 02:19:07', '2025-08-10 03:31:22', 'liberado'),
(70, 'vtrburomb66i0ed612ki6dger5', NULL, 2402, 1, '2025-08-10 02:21:31', '2025-08-10 03:29:59', 'liberado'),
(71, 'cr6ed4bmjto1k15g60vddiauk1', NULL, 2402, 1, '2025-08-10 02:31:16', '2025-08-10 03:31:22', 'liberado'),
(72, 'vtrburomb66i0ed612ki6dger5', NULL, 2402, 1, '2025-08-10 02:37:03', '2025-08-10 03:37:44', 'liberado'),
(73, 'vtrburomb66i0ed612ki6dger5', NULL, 2402, 2, '2025-08-10 02:37:33', '2025-08-10 03:37:44', 'liberado'),
(74, 'rk599k0f4de01so4dms3qhtp03', NULL, 2402, 1, '2025-08-10 02:45:00', '2025-08-10 03:50:44', 'liberado'),
(75, 'vtrburomb66i0ed612ki6dger5', NULL, 2403, 1, '2025-08-10 02:58:20', '2025-08-10 03:58:26', 'liberado'),
(76, 'es97cevuu0277i8f4prfcb4umm', NULL, 2402, 1, '2025-08-10 03:02:45', '2025-08-10 04:07:28', 'liberado'),
(77, 'ogd41tl7h5m0ietak1mrve9jb7', NULL, 2402, 1, '2025-08-10 03:12:20', '2025-08-10 04:13:51', 'liberado'),
(78, 'ong12akiofd47ft0i6b3bj9kno', NULL, 2402, 1, '2025-08-18 02:09:02', '2025-08-18 02:39:02', 'liberado'),
(79, 'ong12akiofd47ft0i6b3bj9kno', NULL, 2403, 1, '2025-08-18 03:46:51', '2025-08-18 04:16:51', 'liberado'),
(80, 'ong12akiofd47ft0i6b3bj9kno', NULL, 2402, 1, '2025-08-18 04:10:46', '2025-08-18 04:40:46', 'liberado'),
(81, 'eum5tscnt0kbqn2k9kgi2qi47t', NULL, 2403, 1, '2025-08-18 04:13:49', '2025-08-18 04:43:49', 'liberado'),
(82, 'ong12akiofd47ft0i6b3bj9kno', NULL, 2402, 1, '2025-08-18 04:54:42', '2025-08-18 05:24:42', 'liberado'),
(83, 'eum5tscnt0kbqn2k9kgi2qi47t', NULL, 2402, 1, '2025-08-18 04:57:49', '2025-08-18 05:27:49', 'liberado'),
(84, 'ong12akiofd47ft0i6b3bj9kno', NULL, 2402, 1, '2025-08-18 05:26:53', '2025-08-18 05:56:53', 'liberado'),
(85, 'ong12akiofd47ft0i6b3bj9kno', NULL, 2402, 1, '2025-08-18 06:22:33', '2025-08-18 06:52:33', 'liberado'),
(86, 'ong12akiofd47ft0i6b3bj9kno', NULL, 2402, 1, '2025-08-18 06:57:18', '2025-08-18 07:27:18', 'liberado'),
(87, 'oikk52t05rb194g14v3iqrg66u', NULL, 2403, 1, '2025-08-20 02:50:41', '2025-08-20 04:35:44', 'liberado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `salida`
--

CREATE TABLE `salida` (
  `codigo` int(11) NOT NULL,
  `producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `fechaDeSalida` date NOT NULL,
  `horaDeSalida` time NOT NULL,
  `fechaFabricacion` date NOT NULL,
  `fechaVencimiento` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Disparadores `salida`
--
DELIMITER $$
CREATE TRIGGER `after_salida_delete` AFTER DELETE ON `salida` FOR EACH ROW BEGIN
    UPDATE producto
    SET stock = stock + OLD.cantidad
    WHERE codigo = OLD.producto;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_salida_insert` AFTER INSERT ON `salida` FOR EACH ROW BEGIN
    UPDATE producto
    SET stock = stock - NEW.cantidad
    WHERE codigo = NEW.producto;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_salida_insert` BEFORE INSERT ON `salida` FOR EACH ROW BEGIN
    DECLARE current_stock INT;

    SELECT stock INTO current_stock FROM producto WHERE codigo = NEW.producto;

    IF current_stock < NEW.cantidad THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No hay suficiente stock para realizar la salida';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_salida_update` BEFORE UPDATE ON `salida` FOR EACH ROW BEGIN
    DECLARE current_stock INT;

    -- Obtener el stock actual del producto
    SELECT stock INTO current_stock FROM producto WHERE codigo = NEW.producto;

    -- Verificar si la cantidad ha cambiado
    IF OLD.cantidad <> NEW.cantidad THEN
        -- Calcular el nuevo stock
        SET current_stock = current_stock + OLD.cantidad - NEW.cantidad;

        -- Verificar si el nuevo stock sería negativo
        IF current_stock < 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede actualizar la salida, el stock se volvería negativo';
        ELSE
            -- Actualizar el stock en la tabla producto
            UPDATE producto
            SET stock = current_stock
            WHERE codigo = NEW.producto;
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transacciones`
--

CREATE TABLE `transacciones` (
  `id` int(11) NOT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `payment_link_id` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `transacciones`
--

INSERT INTO `transacciones` (`id`, `session_id`, `reference`, `payment_link_id`, `amount`, `status`, `created_at`) VALUES
(1, 'nojij1k95m07e3mi63m2q8suv6', 'ORDER-688d437824553', 'm8S5Qt', 25001.00, 'pending', '2025-08-01 22:45:12'),
(2, 'nojij1k95m07e3mi63m2q8suv6', 'ORDER-688d440e2ac8c', 'E2efuH', 25001.00, 'pending', '2025-08-01 22:47:42'),
(3, 'nojij1k95m07e3mi63m2q8suv6', 'ORDER-688d447859220', 'YTmtJB', 25001.00, 'pending', '2025-08-01 22:49:28'),
(4, 'nojij1k95m07e3mi63m2q8suv6', 'ORDER-688d448e4ccbc', 'OPge43', 25001.00, 'pending', '2025-08-01 22:49:50'),
(5, 'nojij1k95m07e3mi63m2q8suv6', 'ORDER-688d44d3d9ea2', 'AoXRgY', 1600.00, 'pending', '2025-08-01 22:51:00'),
(6, 'thrbjdgbd7mitjm8tdi8d0mdlk', 'ORDER-688d52fbdba4c', 'jLEefv', 1600.00, 'pending', '2025-08-01 23:51:24'),
(7, 'thrbjdgbd7mitjm8tdi8d0mdlk', 'ORDER-688d5ecbc6a7b', 'nvK0Oz', 1600.00, 'pending', '2025-08-02 00:41:48'),
(8, 'vtrburomb66i0ed612ki6dger5', 'ORDER-6897e807830b2', 'vltWa9', 25000.00, 'pending', '2025-08-10 00:29:59'),
(9, 'cr6ed4bmjto1k15g60vddiauk1', 'ORDER-6897e83b40f1a', 'jl01HB', 1600.00, 'pending', '2025-08-10 00:30:51'),
(10, 'cr6ed4bmjto1k15g60vddiauk1', 'ORDER-6897e843f0596', 'fiXtBX', 1600.00, 'pending', '2025-08-10 00:31:00'),
(11, 'cr6ed4bmjto1k15g60vddiauk1', 'ORDER-6897e859966bb', 'MjOh0h', 26600.00, 'pending', '2025-08-10 00:31:22'),
(12, 'vtrburomb66i0ed612ki6dger5', 'ORDER-6897e9b2f25d8', 'sDuWZ5', 25000.00, 'pending', '2025-08-10 00:37:08'),
(13, 'vtrburomb66i0ed612ki6dger5', 'ORDER-6897e9d0e10bb', 'u1K2Jx', 75000.00, 'pending', '2025-08-10 00:37:37'),
(14, 'vtrburomb66i0ed612ki6dger5', 'ORDER-6897e9d8941e1', '2AdoM0', 75000.00, 'pending', '2025-08-10 00:37:44'),
(15, 'rk599k0f4de01so4dms3qhtp03', 'ORDER-6897ec0b9b898', 'o2agws', 25000.00, 'pending', '2025-08-10 00:47:08'),
(16, 'rk599k0f4de01so4dms3qhtp03', 'ORDER-6897ece433ff2', 'HBnLD4', 25000.00, 'pending', '2025-08-10 00:50:44'),
(17, 'vtrburomb66i0ed612ki6dger5', 'ORDER-6897eeb1c9f95', '1JEWK3', 1600.00, 'pending', '2025-08-10 00:58:26'),
(18, 'es97cevuu0277i8f4prfcb4umm', 'ORDER-6897efd34373b', 'ZW5uX4', 25000.00, 'pending', '2025-08-10 01:03:15'),
(19, 'es97cevuu0277i8f4prfcb4umm', 'ORDER-6897f0a9e2c74', 'Koyah3', 25000.00, 'pending', '2025-08-10 01:06:50'),
(20, 'es97cevuu0277i8f4prfcb4umm', 'ORDER-6897f0cfb3d40', 'qo27hG', 25000.00, 'pending', '2025-08-10 01:07:28'),
(21, 'ogd41tl7h5m0ietak1mrve9jb7', 'ORDER-6897f23fcf6ca', '15ZRhZ', 25000.00, 'pending', '2025-08-10 01:13:36'),
(22, 'ogd41tl7h5m0ietak1mrve9jb7', 'ORDER-6897f244ecceb', 'Gdnpcc', 25000.00, 'pending', '2025-08-10 01:13:41'),
(23, 'ogd41tl7h5m0ietak1mrve9jb7', 'ORDER-6897f24f2c520', 'U40qKA', 25000.00, 'pending', '2025-08-10 01:13:51'),
(24, 'oikk52t05rb194g14v3iqrg66u', 'ORDER-68a5219dea8c4', 'NlV8ZV', 1600.00, 'pending', '2025-08-20 01:15:10'),
(25, 'oikk52t05rb194g14v3iqrg66u', 'ORDER-68a5266fef74b', 'sbFDqF', 1600.00, 'pending', '2025-08-20 01:35:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `documento` varchar(15) NOT NULL,
  `nombres` text NOT NULL,
  `apellidos` text NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `clave` varchar(15) NOT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  `rol` enum('admin','empleado','cliente') NOT NULL DEFAULT 'cliente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`documento`, `nombres`, `apellidos`, `email`, `telefono`, `direccion`, `clave`, `fecha_registro`, `rol`) VALUES
('123', 'pepe', 'gil', 'correocordobavictorml@gmail.com', '3506037169', 'jausdnda', '123', '2025-08-01 23:17:58', 'cliente'),
('1232591140', 'Victor', 'Cordoba', 'cordobavictorml@gmail.com', '3506037128', NULL, '123', '2025-08-01 19:56:12', 'admin'),
('321', 'PEpe', 'CRUZ', 'hugovhce@gmail.com', '3506039456', 'Cl. 87 Sur #65a-371', '123', '2025-08-09 19:47:07', 'cliente');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `categoria`
--
ALTER TABLE `categoria`
  ADD PRIMARY KEY (`codigo`);

--
-- Indices de la tabla `ingreso`
--
ALTER TABLE `ingreso`
  ADD PRIMARY KEY (`codigo`),
  ADD KEY `producto` (`producto`);

--
-- Indices de la tabla `marca`
--
ALTER TABLE `marca`
  ADD PRIMARY KEY (`codigo`);

--
-- Indices de la tabla `producto`
--
ALTER TABLE `producto`
  ADD PRIMARY KEY (`codigo`),
  ADD KEY `categoria` (`categoria`),
  ADD KEY `marca` (`marca`);

--
-- Indices de la tabla `reserva_carrito`
--
ALTER TABLE `reserva_carrito`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reserva_carrito_prod_estado` (`producto_id`,`estado`),
  ADD KEY `idx_reserva_carrito_sess_estado` (`session_id`,`estado`),
  ADD KEY `reserva_carrito_ibfk_2` (`usuario_id`);

--
-- Indices de la tabla `salida`
--
ALTER TABLE `salida`
  ADD PRIMARY KEY (`codigo`),
  ADD KEY `producto` (`producto`);

--
-- Indices de la tabla `transacciones`
--
ALTER TABLE `transacciones`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`documento`),
  ADD UNIQUE KEY `email_UNIQUE` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `categoria`
--
ALTER TABLE `categoria`
  MODIFY `codigo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2323265;

--
-- AUTO_INCREMENT de la tabla `ingreso`
--
ALTER TABLE `ingreso`
  MODIFY `codigo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2323292;

--
-- AUTO_INCREMENT de la tabla `marca`
--
ALTER TABLE `marca`
  MODIFY `codigo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT de la tabla `producto`
--
ALTER TABLE `producto`
  MODIFY `codigo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2424;

--
-- AUTO_INCREMENT de la tabla `reserva_carrito`
--
ALTER TABLE `reserva_carrito`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT de la tabla `salida`
--
ALTER TABLE `salida`
  MODIFY `codigo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6587;

--
-- AUTO_INCREMENT de la tabla `transacciones`
--
ALTER TABLE `transacciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `ingreso`
--
ALTER TABLE `ingreso`
  ADD CONSTRAINT `ingreso_ibfk_1` FOREIGN KEY (`producto`) REFERENCES `producto` (`codigo`);

--
-- Filtros para la tabla `producto`
--
ALTER TABLE `producto`
  ADD CONSTRAINT `producto_ibfk_2` FOREIGN KEY (`categoria`) REFERENCES `categoria` (`codigo`),
  ADD CONSTRAINT `producto_ibfk_3` FOREIGN KEY (`marca`) REFERENCES `marca` (`codigo`);

--
-- Filtros para la tabla `reserva_carrito`
--
ALTER TABLE `reserva_carrito`
  ADD CONSTRAINT `reserva_carrito_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `producto` (`codigo`),
  ADD CONSTRAINT `reserva_carrito_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`documento`);

--
-- Filtros para la tabla `salida`
--
ALTER TABLE `salida`
  ADD CONSTRAINT `salida_ibfk_1` FOREIGN KEY (`producto`) REFERENCES `producto` (`codigo`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
