--
-- Base de datos: `agenda_hospital`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `agenda_paquetes`
--
-- Esta tabla almacena la información principal de los paquetes.
--

CREATE TABLE `agenda_paquetes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `precio` decimal(10,2) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `agenda_paquete_servicios`
--
-- Esta tabla de unión relaciona los paquetes con los servicios que incluyen.
--

CREATE TABLE `agenda_paquete_servicios` (
  `paquete_id` int(11) NOT NULL,
  `servicio_id` int(11) NOT NULL,
  PRIMARY KEY (`paquete_id`,`servicio_id`),
  KEY `servicio_id` (`servicio_id`),
  CONSTRAINT `agenda_paquete_servicios_ibfk_1` FOREIGN KEY (`paquete_id`) REFERENCES `agenda_paquetes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `agenda_paquete_servicios_ibfk_2` FOREIGN KEY (`servicio_id`) REFERENCES `portal_servicios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Nota: Asegúrate de que la tabla `portal_servicios` exista y tenga una columna `id` como clave primaria.
--
