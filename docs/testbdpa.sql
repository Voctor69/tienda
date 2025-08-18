-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 11-06-2025 a las 05:48:52
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
(21, 'Licores'),
(2323221, 'Cervezas'),
(2323223, 'Ron'),
(2323224, 'Aguardiente'),
(2323225, 'Whisky'),
(2323226, 'Vino'),
(2323227, 'Tequila'),
(2323228, 'Ginebra'),
(2323229, 'Vodka'),
(2323230, 'Brandy'),
(2323231, 'Cocteles'),
(2323232, 'Consumibles'),
(2323233, 'Gaseosas'),
(2323234, 'Energizantes'),
(2323235, 'Agua'),
(2323236, 'Dulces'),
(2323237, 'Cigarrillos'),
(2323238, 'Vapeadores'),
(2323239, 'Mecheras'),
(2323240, 'Jugos'),
(2323241, 'Aguas Saborizadas'),
(2323242, 'Hielo');

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
-- Volcado de datos para la tabla `ingreso`
--

INSERT INTO `ingreso` (`codigo`, `producto`, `cantidad`, `fechaDeIngreso`, `horaDeIngreso`, `fechaFabricacion`, `fechaVencimiento`) VALUES
(2323285, 2371, 151, '2024-10-09', '23:58:00', '2024-10-15', '2024-10-10');

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
(1, 'Absolut'),
(2, 'Aguila'),
(4, 'Poker'),
(5, 'Club Colombia'),
(6, 'Aguardiente Antioqueño'),
(7, 'Ron Medellin '),
(8, 'Ron Caldas'),
(9, 'Ron viejo de caldas'),
(10, 'Gato Negro'),
(11, 'Black and White'),
(12, 'Jhonny Walker'),
(13, 'Pilsen '),
(14, 'Corona'),
(15, 'Costeñita'),
(16, 'tequila 1800'),
(17, 'Buchannas'),
(18, 'Old Parr'),
(19, 'Jack Daniels'),
(20, 'Maria Mulata'),
(21, 'Coca Cola'),
(22, 'Pepsi'),
(23, 'Vive 100'),
(24, 'Speed max'),
(25, 'Spartan'),
(26, 'Redbull'),
(27, 'Amper'),
(28, 'Colombiana'),
(29, 'Uva'),
(30, 'Naranja'),
(31, 'Manzana'),
(32, 'H2O'),
(33, 'Gatorade'),
(34, 'Electrolit'),
(35, 'Powerade'),
(36, 'Mr Tee'),
(37, 'Fuze Tee'),
(38, 'Del valle'),
(39, 'Kola Roman'),
(40, 'Pony Malta'),
(41, 'Margarita'),
(42, 'Doritos'),
(43, 'Choclitos'),
(44, 'Rizadas'),
(45, 'Yupi'),
(46, 'Cheese trese'),
(47, 'Malboro'),
(48, 'Blackwood'),
(49, 'Lucky Strikes'),
(50, 'Boston'),
(51, 'LyM'),
(52, 'Candelas'),
(53, 'Bic2'),
(54, 'Baileys'),
(55, 'Nectar'),
(56, 'Cristal'),
(57, 'Saviloe'),
(58, 'Brisa'),
(59, 'Colombina'),
(60, 'Trululu'),
(62, 'gfe'),
(63, '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto`
--

CREATE TABLE `producto` (
  `codigo` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `valor` int(11) NOT NULL,
  `proveedor` int(11) NOT NULL,
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

INSERT INTO `producto` (`codigo`, `nombre`, `valor`, `proveedor`, `marca`, `categoria`, `presentacion`, `tamañoUnidad`, `unidad`, `stock`, `imagen`) VALUES
(2370, 'Botella de Baileyss34343', 10, 3, 54, 21, 'Botella de Vidrio', 1.25, 'Kg', 0, NULL),
(2371, 'fefe', 434423, 1, 21, 2323227, 'Botella de Vidrio', 2.32, 'Kg', 101, NULL),
(2393, 'Paquete de gomitas ', 2800, 3, 60, 2323236, 'Paquete', 100, 'Gr', 0, 'gomitas.webp'),
(2399, 'Cajetilla de Cigarrillos', 5000, 3, 47, 2323237, 'Paquete', 10, 'Cm3', 0, NULL),
(2400, 'Botella de Baileyss34343', 10, 3, 54, 21, 'Botella de Vidrio', 1.25, 'Kg', 0, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedor`
--

CREATE TABLE `proveedor` (
  `codigo` int(11) NOT NULL,
  `tipoPersona` varchar(50) NOT NULL,
  `tipoDoc` varchar(50) NOT NULL,
  `documento` varchar(11) NOT NULL,
  `nombres` varchar(50) NOT NULL,
  `apellidos` text NOT NULL,
  `direccion` varchar(50) NOT NULL,
  `correo` varchar(50) NOT NULL,
  `telefono` varchar(30) NOT NULL,
  `nit` varchar(12) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `proveedor`
--

INSERT INTO `proveedor` (`codigo`, `tipoPersona`, `tipoDoc`, `documento`, `nombres`, `apellidos`, `direccion`, `correo`, `telefono`, `nit`) VALUES
(1, 'Natural', 'PA', '1982', 'efe', 'ere', 'bogota', 'cordoba@gmail.com', '3506037128', ''),
(3, 'Juridica', 'C.C', '1232591140', 'El vaso', 'Cof', 'titiribi', 'cordoba@gmail.com', '350-6037128', '78'),
(46, 'Juridica', 'C.E', '1232591139', 'Sara', 'Avellaneda', 'el sol', 'hecto@gmail', '3506037128', '69');

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
-- Volcado de datos para la tabla `salida`
--

INSERT INTO `salida` (`codigo`, `producto`, `cantidad`, `fechaDeSalida`, `horaDeSalida`, `fechaFabricacion`, `fechaVencimiento`) VALUES
(6583, 2371, 50, '2024-10-11', '23:01:00', '2024-10-11', '2024-10-11');

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
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `documento` varchar(15) NOT NULL,
  `nombres` text NOT NULL,
  `apellidos` text NOT NULL,
  `clave` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`documento`, `nombres`, `apellidos`, `clave`) VALUES
('1232591140', 'Victor', 'Cabra', '12');

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
  ADD KEY `proveedor` (`proveedor`),
  ADD KEY `categoria` (`categoria`),
  ADD KEY `marca` (`marca`);

--
-- Indices de la tabla `proveedor`
--
ALTER TABLE `proveedor`
  ADD PRIMARY KEY (`codigo`);

--
-- Indices de la tabla `salida`
--
ALTER TABLE `salida`
  ADD PRIMARY KEY (`codigo`),
  ADD KEY `producto` (`producto`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`documento`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `categoria`
--
ALTER TABLE `categoria`
  MODIFY `codigo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2323255;

--
-- AUTO_INCREMENT de la tabla `ingreso`
--
ALTER TABLE `ingreso`
  MODIFY `codigo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2323292;

--
-- AUTO_INCREMENT de la tabla `marca`
--
ALTER TABLE `marca`
  MODIFY `codigo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT de la tabla `producto`
--
ALTER TABLE `producto`
  MODIFY `codigo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2401;

--
-- AUTO_INCREMENT de la tabla `proveedor`
--
ALTER TABLE `proveedor`
  MODIFY `codigo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT de la tabla `salida`
--
ALTER TABLE `salida`
  MODIFY `codigo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6587;

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
  ADD CONSTRAINT `producto_ibfk_1` FOREIGN KEY (`proveedor`) REFERENCES `proveedor` (`codigo`),
  ADD CONSTRAINT `producto_ibfk_2` FOREIGN KEY (`categoria`) REFERENCES `categoria` (`codigo`),
  ADD CONSTRAINT `producto_ibfk_3` FOREIGN KEY (`marca`) REFERENCES `marca` (`codigo`);

--
-- Filtros para la tabla `salida`
--
ALTER TABLE `salida`
  ADD CONSTRAINT `salida_ibfk_1` FOREIGN KEY (`producto`) REFERENCES `producto` (`codigo`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
