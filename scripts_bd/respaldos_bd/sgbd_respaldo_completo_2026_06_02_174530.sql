-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: sgbd
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `sgbd`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `sgbd` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `sgbd`;

--
-- Table structure for table `anotaciones_importantes`
--

DROP TABLE IF EXISTS `anotaciones_importantes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `anotaciones_importantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `anotaciones_importantes`
--

LOCK TABLES `anotaciones_importantes` WRITE;
/*!40000 ALTER TABLE `anotaciones_importantes` DISABLE KEYS */;
INSERT INTO `anotaciones_importantes` VALUES (1,'Llevar Protector Solar Biodegradable y Resistente al Agua.','',1,'2026-05-20 16:21:16'),(2,'Llevar Repelente.','',1,'2026-05-20 16:21:24'),(3,'Usar Ropa Cómoda y que Puedas Mojar, preferiblemente de realizar deporte.','',1,'2026-05-20 16:21:50'),(4,'Calzados Adecuados para la Caminata y que Puedas Mojar. Recuerda Revisar tus Calzados antes de Usarlo.','',1,'2026-05-20 16:22:06'),(5,'Capa para la Lluvia si no Gustas Mojarte.','',1,'2026-05-20 16:22:19'),(6,'Agua y/o Bebida Hidratante. Recuerden: la Cantidad de Líquido que su Cuerpo Requiera.','',1,'2026-05-20 16:22:26'),(7,'Desayuno que te Sostenga y Alimentos que les Den Energía, Frutas, Granola, etc.','',1,'2026-05-20 16:22:31'),(8,'Preferible que Lleves tu Vaso para Tomar Café; Así Aportas al Cuidado del Medio Ambiente.','',1,'2026-05-20 16:22:43'),(9,'Si gusta puede llevar traje de baño debajo de su ropa.','',1,'2026-05-20 16:22:50'),(10,'Es obligatorio mojarse los pies.','',1,'2026-05-20 16:22:58');
/*!40000 ALTER TABLE `anotaciones_importantes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `configuracion_contacto`
--

DROP TABLE IF EXISTS `configuracion_contacto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuracion_contacto` (
  `id` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `hero_imagen` varchar(255) NOT NULL DEFAULT 'imagenes/paisajes/hero.jpg',
  `titulo` varchar(160) NOT NULL,
  `subtitulo` varchar(255) NOT NULL,
  `horario` varchar(160) NOT NULL,
  `ubicacion` varchar(160) NOT NULL,
  `telefono` varchar(40) NOT NULL,
  `whatsapp` varchar(40) NOT NULL,
  `email` varchar(160) NOT NULL,
  `instagram` varchar(80) NOT NULL,
  `instagram_url` varchar(255) NOT NULL,
  `texto_formulario` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuracion_contacto`
--

LOCK TABLES `configuracion_contacto` WRITE;
/*!40000 ALTER TABLE `configuracion_contacto` DISABLE KEYS */;
INSERT INTO `configuracion_contacto` VALUES (1,'imagenes/paisajes/hero.jpg','Hablemos de tu proxima ruta','Reserva experiencias privadas, pregunta por nuestros proximos senderos o coordinemos una aventura para tu grupo.','Lunes a viernes, 8:00 a.m. - 6:00 p.m.','Republica Dominicana','+1 (849) 472-1200','18494721200','info@senderismogo.com','@senderismogo','https://www.instagram.com/senderismogo','Cuentanos que necesitas y te responderemos con orientacion clara para que puedas planificar con confianza.','2026-05-20 16:11:39');
/*!40000 ALTER TABLE `configuracion_contacto` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalles_usuarios`
--

DROP TABLE IF EXISTS `detalles_usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalles_usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `rango_edad` varchar(20) NOT NULL,
  `identificacion` varchar(50) NOT NULL,
  `es_alergico` tinyint(1) NOT NULL DEFAULT 0,
  `alergias_detalle` varchar(255) DEFAULT NULL,
  `grupo_sanguineo` varchar(5) NOT NULL,
  `enfermedad` varchar(255) NOT NULL,
  `seguro_medico` varchar(255) NOT NULL,
  `experiencia_senderismo` varchar(80) NOT NULL,
  `via_entero` varchar(80) NOT NULL,
  `referido_nombre` varchar(150) DEFAULT NULL,
  `emergencia_nombre` varchar(150) NOT NULL,
  `emergencia_parentesco` varchar(80) NOT NULL,
  `emergencia_telefono` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_detalles_usuarios_usuario` (`usuario_id`),
  CONSTRAINT `fk_detalles_usuarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalles_usuarios`
--

LOCK TABLES `detalles_usuarios` WRITE;
/*!40000 ALTER TABLE `detalles_usuarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `detalles_usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `elementos_incluidos`
--

DROP TABLE IF EXISTS `elementos_incluidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `elementos_incluidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `elementos_incluidos`
--

LOCK TABLES `elementos_incluidos` WRITE;
/*!40000 ALTER TABLE `elementos_incluidos` DISABLE KEYS */;
INSERT INTO `elementos_incluidos` VALUES (1,'Staff y Guías Certificados en Primeros Auxilios.','',1,'2026-05-20 16:23:06'),(2,'Bienvenida con Café en Casa de Marcos.','',1,'2026-05-20 16:23:12'),(3,'Agua y Banana al Inicio del Sendero. Recuerda que Debes Llevar tu Recipiente para Abastecimiento de Agua.','',1,'2026-05-20 16:23:25'),(4,'1 Sándwich Jamón y Queso.','',1,'2026-05-20 16:24:00'),(5,'Jugo Refrescante.','',1,'2026-05-20 16:24:06'),(6,'Almuerzo típico Dominicano.','',1,'2026-05-20 16:24:12'),(7,'1 Sándwich Italiano al Pesto.','',1,'2026-05-20 16:24:27'),(8,'Café en el destino. Preferible que lleves tu vaso así aportas al cuidado del medio ambiente.','',1,'2026-05-20 16:25:08'),(9,'Chaleco Salvavidas.','',1,'2026-05-20 16:25:16');
/*!40000 ALTER TABLE `elementos_incluidos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `intentos_inicio_sesion`
--

DROP TABLE IF EXISTS `intentos_inicio_sesion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `intentos_inicio_sesion` (
  `ip_address` varchar(45) NOT NULL,
  `attempts` int(11) DEFAULT 1,
  `last_attempt` int(11) NOT NULL,
  PRIMARY KEY (`ip_address`),
  KEY `idx_last_attempt` (`last_attempt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `intentos_inicio_sesion`
--

LOCK TABLES `intentos_inicio_sesion` WRITE;
/*!40000 ALTER TABLE `intentos_inicio_sesion` DISABLE KEYS */;
/*!40000 ALTER TABLE `intentos_inicio_sesion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mensajes_contacto`
--

DROP TABLE IF EXISTS `mensajes_contacto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mensajes_contacto` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `asunto` varchar(80) NOT NULL,
  `mensaje` text NOT NULL,
  `estado` enum('nuevo','leido','respondido','archivado') NOT NULL DEFAULT 'nuevo',
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mensajes_contacto_estado` (`estado`),
  KEY `idx_mensajes_contacto_fecha` (`fecha_creacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mensajes_contacto`
--

LOCK TABLES `mensajes_contacto` WRITE;
/*!40000 ALTER TABLE `mensajes_contacto` DISABLE KEYS */;
/*!40000 ALTER TABLE `mensajes_contacto` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `niveles_dificultad`
--

DROP TABLE IF EXISTS `niveles_dificultad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `niveles_dificultad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `nivel_numero` tinyint(3) unsigned NOT NULL DEFAULT 50,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `niveles_dificultad`
--

LOCK TABLES `niveles_dificultad` WRITE;
/*!40000 ALTER TABLE `niveles_dificultad` DISABLE KEYS */;
INSERT INTO `niveles_dificultad` VALUES (1,'Bajo','Para principiantes',0,1,'2026-05-20 16:18:25'),(2,'Bajo - Intermedio','',25,1,'2026-05-20 16:18:36'),(3,'Intermedio','',50,1,'2026-05-20 16:18:42'),(4,'Intermedio - Alto','',75,1,'2026-05-20 16:18:51'),(5,'Alto','',100,1,'2026-05-20 16:18:56');
/*!40000 ALTER TABLE `niveles_dificultad` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permisos`
--

DROP TABLE IF EXISTS `permisos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permisos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permisos`
--

LOCK TABLES `permisos` WRITE;
/*!40000 ALTER TABLE `permisos` DISABLE KEYS */;
INSERT INTO `permisos` VALUES (1,'crear_usuario','Puede crear nuevos usuarios'),(2,'editar_usuario','Puede editar usuarios'),(3,'eliminar_usuario','Puede eliminar usuarios'),(4,'ver_usuarios','Puede ver lista de usuarios'),(5,'ver_reportes','Puede ver reportes'),(6,'gestionar_finanzas','Puede gestionar finanzas'),(7,'ver_dashboard','Puede ver el dashboard'),(8,'gestionar_rutas','Puede gestionar rutas de senderismo');
/*!40000 ALTER TABLE `permisos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `puntos_encuentro`
--

DROP TABLE IF EXISTS `puntos_encuentro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `puntos_encuentro` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `direccion_referencia` varchar(255) DEFAULT NULL,
  `url_mapa` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_puntos_encuentro_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `puntos_encuentro`
--

LOCK TABLES `puntos_encuentro` WRITE;
/*!40000 ALTER TABLE `puntos_encuentro` DISABLE KEYS */;
INSERT INTO `puntos_encuentro` VALUES (2,'Estación Texaco Canabacoa.',NULL,'https://maps.app.goo.gl/wR3JRh7kyKzkRqwa7',1,'2026-06-02 21:35:45',NULL),(3,'Estación Next Licey.',NULL,'https://maps.app.goo.gl/ZnJRQ1SDtBBoFdT17',1,'2026-06-02 21:35:45',NULL),(4,'Gurabo - Casa de Marcos',NULL,'https://maps.app.goo.gl/LfXPwyUzBLjEn8gJ8',1,'2026-06-02 21:35:45',NULL);
/*!40000 ALTER TABLE `puntos_encuentro` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `registros_senderos`
--

DROP TABLE IF EXISTS `registros_senderos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `registros_senderos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sendero_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `detalle_usuario_id` int(11) NOT NULL,
  `inversion_id` int(11) DEFAULT NULL,
  `estado` enum('registrado','cancelado') NOT NULL DEFAULT 'registrado',
  `consentimiento_aceptado` tinyint(1) NOT NULL DEFAULT 0,
  `rgpd_aceptado` tinyint(1) NOT NULL DEFAULT 0,
  `consentimiento_texto` mediumtext NOT NULL,
  `rgpd_texto` varchar(255) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_registros_senderos_usuario_sendero` (`usuario_id`,`sendero_id`),
  KEY `fk_registros_senderos_sendero` (`sendero_id`),
  KEY `fk_registros_senderos_detalle` (`detalle_usuario_id`),
  KEY `fk_registros_senderos_inversion` (`inversion_id`),
  CONSTRAINT `fk_registros_senderos_detalle` FOREIGN KEY (`detalle_usuario_id`) REFERENCES `detalles_usuarios` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_registros_senderos_inversion` FOREIGN KEY (`inversion_id`) REFERENCES `sendero_inversiones` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_registros_senderos_sendero` FOREIGN KEY (`sendero_id`) REFERENCES `senderos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_registros_senderos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `registros_senderos`
--

LOCK TABLES `registros_senderos` WRITE;
/*!40000 ALTER TABLE `registros_senderos` DISABLE KEYS */;
/*!40000 ALTER TABLE `registros_senderos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rol_permiso`
--

DROP TABLE IF EXISTS `rol_permiso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rol_permiso` (
  `rol_id` int(11) NOT NULL,
  `permiso_id` int(11) NOT NULL,
  PRIMARY KEY (`rol_id`,`permiso_id`),
  KEY `fk_rp_permiso` (`permiso_id`),
  CONSTRAINT `fk_rp_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rol_permiso`
--

LOCK TABLES `rol_permiso` WRITE;
/*!40000 ALTER TABLE `rol_permiso` DISABLE KEYS */;
INSERT INTO `rol_permiso` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(2,5),(2,6),(2,7),(3,7),(3,8);
/*!40000 ALTER TABLE `rol_permiso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Administrador','Acceso total al sistema','2026-02-02 16:54:32'),(2,'Contable','Acceso a módulos financieros','2026-02-02 16:54:32'),(3,'Usuario','Usuario estándar del sistema','2026-02-02 16:54:32'),(4,'Invitado','Usuario registrado (pendiente / acceso básico)','2026-02-06 20:31:57');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sendero_anotaciones`
--

DROP TABLE IF EXISTS `sendero_anotaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sendero_anotaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sendero_id` int(11) NOT NULL,
  `anotacion_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sendero_anotacion` (`sendero_id`,`anotacion_id`),
  KEY `fk_sendero_anotaciones_anotacion` (`anotacion_id`),
  CONSTRAINT `fk_sendero_anotaciones_anotacion` FOREIGN KEY (`anotacion_id`) REFERENCES `anotaciones_importantes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_sendero_anotaciones_sendero` FOREIGN KEY (`sendero_id`) REFERENCES `senderos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=129 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sendero_anotaciones`
--

LOCK TABLES `sendero_anotaciones` WRITE;
/*!40000 ALTER TABLE `sendero_anotaciones` DISABLE KEYS */;
INSERT INTO `sendero_anotaciones` VALUES (106,1,1),(107,1,2),(110,1,3),(102,1,4),(103,1,5),(101,1,6),(104,1,7),(108,1,8),(109,1,9),(105,1,10),(97,2,1),(98,2,2),(100,2,3),(94,2,4),(95,2,5),(93,2,6),(96,2,7),(99,2,8),(124,3,1),(125,3,2),(128,3,3),(121,3,4),(120,3,6),(122,3,7),(126,3,8),(127,3,9),(123,3,10);
/*!40000 ALTER TABLE `sendero_anotaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sendero_elementos_incluidos`
--

DROP TABLE IF EXISTS `sendero_elementos_incluidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sendero_elementos_incluidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sendero_id` int(11) NOT NULL,
  `incluye_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sendero_incluye` (`sendero_id`,`incluye_id`),
  KEY `fk_sendero_incluye_catalogo` (`incluye_id`),
  CONSTRAINT `fk_sendero_incluye_catalogo` FOREIGN KEY (`incluye_id`) REFERENCES `elementos_incluidos` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_sendero_incluye_sendero` FOREIGN KEY (`sendero_id`) REFERENCES `senderos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sendero_elementos_incluidos`
--

LOCK TABLES `sendero_elementos_incluidos` WRITE;
/*!40000 ALTER TABLE `sendero_elementos_incluidos` DISABLE KEYS */;
INSERT INTO `sendero_elementos_incluidos` VALUES (78,1,1),(74,1,2),(72,1,3),(71,1,4),(77,1,5),(73,1,6),(75,1,8),(76,1,9),(70,2,1),(67,2,2),(66,2,3),(69,2,5),(65,2,7),(68,2,8),(90,3,1),(87,3,2),(86,3,3),(89,3,5),(85,3,7),(88,3,8);
/*!40000 ALTER TABLE `sendero_elementos_incluidos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sendero_imagenes`
--

DROP TABLE IF EXISTS `sendero_imagenes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sendero_imagenes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sendero_id` int(11) NOT NULL,
  `ruta_imagen` varchar(255) NOT NULL,
  `titulo` varchar(150) DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 1,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_sendero_imagenes_sendero` (`sendero_id`),
  CONSTRAINT `fk_sendero_imagenes_sendero` FOREIGN KEY (`sendero_id`) REFERENCES `senderos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sendero_imagenes`
--

LOCK TABLES `sendero_imagenes` WRITE;
/*!40000 ALTER TABLE `sendero_imagenes` DISABLE KEYS */;
INSERT INTO `sendero_imagenes` VALUES (1,1,'imagenes/senderos/sendero-reserva-cientifica-la-salcedoa-cascada-velo-de-novia/galeria-20260520183915-b1261f9a.jpg','14fe9f30-150b-43ad-a9d9-0521086ec5d8',1,1,'2026-05-20 16:39:15'),(2,1,'imagenes/senderos/sendero-reserva-cientifica-la-salcedoa-cascada-velo-de-novia/galeria-20260520183915-e5c1e4dd.jpg','0478bb02-021a-4d56-baba-7769ee0ec0df',2,1,'2026-05-20 16:39:15'),(3,1,'imagenes/senderos/sendero-reserva-cientifica-la-salcedoa-cascada-velo-de-novia/galeria-20260520183915-e819a420.jpg','ac0a73b8-e276-49a5-bee1-b74291e68649',3,1,'2026-05-20 16:39:15'),(4,1,'imagenes/senderos/sendero-reserva-cientifica-la-salcedoa-cascada-velo-de-novia/galeria-20260520183915-3d94a447.jpg','b6bfedc0-586e-4873-9268-f46f38a8cfe9',4,1,'2026-05-20 16:39:15'),(5,1,'imagenes/senderos/sendero-reserva-cientifica-la-salcedoa-cascada-velo-de-novia/galeria-20260520183915-df2c3ca5.jpg','b7770a29-ed7c-4ed8-822e-0e6560927d03',5,1,'2026-05-20 16:39:15'),(6,1,'imagenes/senderos/sendero-reserva-cientifica-la-salcedoa-cascada-velo-de-novia/galeria-20260520183915-f66dadaf.jpg','ce58a8eb-cf28-472a-805e-a7d63f97bbad',6,1,'2026-05-20 16:39:15'),(7,2,'imagenes/senderos/reserva-cientifica-loma-quita-espuela-sendero-las-nubes/galeria-20260520213326-2f2299dc.jpg','2cb6295c-3e2c-4788-8a0a-c1c1526ee332',1,1,'2026-05-20 19:33:26'),(8,2,'imagenes/senderos/reserva-cientifica-loma-quita-espuela-sendero-las-nubes/galeria-20260520213326-de6c851a.jpg','5ac887e5-dd15-471f-a945-8251ed2bae29',2,0,'2026-05-20 19:33:26'),(9,2,'imagenes/senderos/reserva-cientifica-loma-quita-espuela-sendero-las-nubes/galeria-20260520213326-5d05f09a.jpg','22d63321-7681-47c3-bfe8-17d4d5d66861',3,1,'2026-05-20 19:33:26'),(10,2,'imagenes/senderos/reserva-cientifica-loma-quita-espuela-sendero-las-nubes/galeria-20260520213326-c8115a23.jpg','632b319b-b514-4717-96c6-a01fd5b13749',4,0,'2026-05-20 19:33:26'),(11,2,'imagenes/senderos/reserva-cientifica-loma-quita-espuela-sendero-las-nubes/galeria-20260520213326-17f9e2c6.jpg','12569a44-5dae-4b1f-9de1-567209c1a7d6',5,0,'2026-05-20 19:33:26'),(12,2,'imagenes/senderos/reserva-cientifica-loma-quita-espuela-sendero-las-nubes/galeria-20260520213326-8737ead8.jpg','d397db6a-c19e-4b52-b752-67f1dc0d0ce2',6,1,'2026-05-20 19:33:26'),(13,2,'imagenes/senderos/reserva-cientifica-loma-quita-espuela-sendero-las-nubes/galeria-20260520215821-0f7ed0a6.jpg','2cb6295c-3e2c-4788-8a0a-c1c1526ee332',7,0,'2026-05-20 19:58:21'),(14,2,'imagenes/senderos/reserva-cientifica-loma-quita-espuela-sendero-las-nubes/galeria-20260520215821-5890ae60.jpg','2ff59276-5ac4-41b8-9c87-87cfa762b21d',8,1,'2026-05-20 19:58:21'),(15,2,'imagenes/senderos/reserva-cientifica-loma-quita-espuela-sendero-las-nubes/galeria-20260520215821-57042b2c.jpg','3c6d8a23-1a5d-4589-bb00-0206e878b833',9,1,'2026-05-20 19:58:21'),(16,2,'imagenes/senderos/reserva-cientifica-loma-quita-espuela-sendero-las-nubes/galeria-20260520215821-5d5979bd.jpg','5ac887e5-dd15-471f-a945-8251ed2bae29',10,1,'2026-05-20 19:58:21'),(17,2,'imagenes/senderos/reserva-cientifica-loma-quita-espuela-sendero-las-nubes/galeria-20260520215821-a675f51f.jpg','22d63321-7681-47c3-bfe8-17d4d5d66861',11,0,'2026-05-20 19:58:21'),(18,2,'imagenes/senderos/reserva-cientifica-loma-quita-espuela-sendero-las-nubes/galeria-20260520215821-3d4c12aa.jpg','80af083a-a430-4b5c-9715-3ea22374865a',12,1,'2026-05-20 19:58:21'),(19,2,'imagenes/senderos/reserva-cientifica-loma-quita-espuela-sendero-las-nubes/galeria-20260520215821-e4313e7b.jpg','265f55bf-d343-4fbd-bc4a-3998783f77f3',13,1,'2026-05-20 19:58:21'),(20,2,'imagenes/senderos/reserva-cientifica-loma-quita-espuela-sendero-las-nubes/galeria-20260520215821-51cb7486.jpg','632b319b-b514-4717-96c6-a01fd5b13749',14,1,'2026-05-20 19:58:21'),(21,2,'imagenes/senderos/reserva-cientifica-loma-quita-espuela-sendero-las-nubes/galeria-20260520215821-d22ce00c.jpg','12569a44-5dae-4b1f-9de1-567209c1a7d6',15,1,'2026-05-20 19:58:21'),(22,2,'imagenes/senderos/reserva-cientifica-loma-quita-espuela-sendero-las-nubes/galeria-20260520215821-40f9f276.jpg','bb197ebb-164a-4331-9da3-d4b62eef192b',16,1,'2026-05-20 19:58:21'),(23,2,'imagenes/senderos/reserva-cientifica-loma-quita-espuela-sendero-las-nubes/galeria-20260520215821-d6760d0a.jpg','d397db6a-c19e-4b52-b752-67f1dc0d0ce2',17,0,'2026-05-20 19:58:21'),(24,3,'imagenes/senderos/sendero-salto-los-monjes/galeria-20260602232147-1047ce49.jpg','0c1e4aee-79e1-4470-bb36-3c7626ff34c5',1,1,'2026-06-02 21:21:47'),(25,3,'imagenes/senderos/sendero-salto-los-monjes/galeria-20260602232147-79e17ed8.jpg','3f905983-814d-4adc-9a77-de40135c5e8d',2,1,'2026-06-02 21:21:47'),(26,3,'imagenes/senderos/sendero-salto-los-monjes/galeria-20260602232147-7391fc90.jpg','12be0447-4295-470a-a7a2-c89d7c25459c',3,1,'2026-06-02 21:21:47'),(27,3,'imagenes/senderos/sendero-salto-los-monjes/galeria-20260602232147-2220505a.jpg','959b3b49-f6d5-4c1f-a7d9-19f8d8467dd4',4,1,'2026-06-02 21:21:47'),(28,3,'imagenes/senderos/sendero-salto-los-monjes/galeria-20260602232147-f4b60b96.jpg','645691da-d0e9-4795-963f-0fe99f451697',5,1,'2026-06-02 21:21:47'),(29,3,'imagenes/senderos/sendero-salto-los-monjes/galeria-20260602232147-43a21517.jpg','a5b0594a-2f7d-4de5-a406-4d18b43798a4',6,1,'2026-06-02 21:21:47'),(30,3,'imagenes/senderos/sendero-salto-los-monjes/galeria-20260602232147-6bb00380.jpg','d5ea4db6-c904-4e20-bb17-96d78fe3ef2e',7,1,'2026-06-02 21:21:47'),(31,3,'imagenes/senderos/sendero-salto-los-monjes/galeria-20260602232147-fcb6548f.jpg','face5526-6c28-4045-8b6f-49381e9b8245',8,1,'2026-06-02 21:21:47');
/*!40000 ALTER TABLE `sendero_imagenes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sendero_inversion_incluye`
--

DROP TABLE IF EXISTS `sendero_inversion_incluye`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sendero_inversion_incluye` (
  `inversion_id` int(11) NOT NULL,
  `incluye_id` int(11) NOT NULL,
  PRIMARY KEY (`inversion_id`,`incluye_id`),
  KEY `idx_sendero_inversion_incluye_item` (`incluye_id`),
  CONSTRAINT `fk_inversion_incluye_catalogo` FOREIGN KEY (`incluye_id`) REFERENCES `elementos_incluidos` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_inversion_incluye_inversion` FOREIGN KEY (`inversion_id`) REFERENCES `sendero_inversiones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sendero_inversion_incluye`
--

LOCK TABLES `sendero_inversion_incluye` WRITE;
/*!40000 ALTER TABLE `sendero_inversion_incluye` DISABLE KEYS */;
INSERT INTO `sendero_inversion_incluye` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,8),(1,9),(2,1),(2,2),(2,3),(2,5),(2,7),(2,8),(4,1),(4,2),(4,3),(4,5),(4,7),(4,8),(5,1),(5,2),(5,3),(5,8);
/*!40000 ALTER TABLE `sendero_inversion_incluye` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sendero_inversiones`
--

DROP TABLE IF EXISTS `sendero_inversiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sendero_inversiones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sendero_id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fecha_limite_pago` date DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 1,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sendero_inversiones_sendero` (`sendero_id`),
  CONSTRAINT `fk_sendero_inversiones_sendero` FOREIGN KEY (`sendero_id`) REFERENCES `senderos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sendero_inversiones`
--

LOCK TABLES `sendero_inversiones` WRITE;
/*!40000 ALTER TABLE `sendero_inversiones` DISABLE KEYS */;
INSERT INTO `sendero_inversiones` VALUES (1,1,'Inversion general','Plan creado desde la inversion anterior del sendero.',1500.00,'2026-05-20',1,1,'2026-06-02 20:17:44','2026-06-02 20:17:44'),(2,2,'Inversion general','Plan creado desde la inversion anterior del sendero.',1100.00,'0000-00-00',1,1,'2026-06-02 20:17:44','2026-06-02 20:17:44'),(4,3,'Inversion 1','',1000.00,'2026-06-04',1,1,'2026-06-02 21:21:47','2026-06-02 21:21:47'),(5,3,'Inversion 2','',800.00,'2026-06-04',2,1,'2026-06-02 21:21:47','2026-06-02 21:21:47');
/*!40000 ALTER TABLE `sendero_inversiones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sendero_puntos_encuentro`
--

DROP TABLE IF EXISTS `sendero_puntos_encuentro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sendero_puntos_encuentro` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sendero_id` int(11) NOT NULL,
  `punto_encuentro_id` int(11) DEFAULT NULL,
  `nombre_punto` varchar(120) NOT NULL,
  `direccion_referencia` varchar(255) DEFAULT NULL,
  `hora_encuentro` time NOT NULL,
  `hora_salida` time NOT NULL,
  `url_mapa` varchar(255) DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 1,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_puntos_encuentro_sendero` (`sendero_id`),
  KEY `idx_sendero_puntos_catalogo` (`punto_encuentro_id`),
  CONSTRAINT `fk_puntos_encuentro_sendero` FOREIGN KEY (`sendero_id`) REFERENCES `senderos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sendero_puntos_catalogo` FOREIGN KEY (`punto_encuentro_id`) REFERENCES `puntos_encuentro` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sendero_puntos_encuentro`
--

LOCK TABLES `sendero_puntos_encuentro` WRITE;
/*!40000 ALTER TABLE `sendero_puntos_encuentro` DISABLE KEYS */;
INSERT INTO `sendero_puntos_encuentro` VALUES (19,2,NULL,'Gurabo, en casa de Marcos.','','05:30:00','05:45:00','https://maps.app.goo.gl/X45hfvpXuFH24pNs9',1,1,'2026-05-20 20:00:59'),(20,2,2,'Estación Texaco Canabacoa.','','05:55:00','06:00:00','https://maps.app.goo.gl/wR3JRh7kyKzkRqwa7',2,1,'2026-05-20 20:00:59'),(21,1,NULL,'Gurabo, en casa de Marcos.','','05:30:00','05:40:00','https://maps.app.goo.gl/ZPeBVS5bg8D1gojt6',1,1,'2026-06-02 20:10:52'),(22,1,3,'Estación Next Licey.','','05:45:00','05:55:00','https://maps.app.goo.gl/ZnJRQ1SDtBBoFdT17',2,1,'2026-06-02 20:10:52'),(24,3,4,'Gurabo - Casa de Marcos','','05:45:00','05:55:00','https://maps.app.goo.gl/LfXPwyUzBLjEn8gJ8',1,1,'2026-06-02 21:35:23');
/*!40000 ALTER TABLE `sendero_puntos_encuentro` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sendero_tipos_terreno`
--

DROP TABLE IF EXISTS `sendero_tipos_terreno`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sendero_tipos_terreno` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sendero_id` int(11) NOT NULL,
  `tipo_terreno_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sendero_tipo_terreno` (`sendero_id`,`tipo_terreno_id`),
  KEY `fk_sendero_tipos_terreno_tipo` (`tipo_terreno_id`),
  CONSTRAINT `fk_sendero_tipos_terreno_sendero` FOREIGN KEY (`sendero_id`) REFERENCES `senderos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sendero_tipos_terreno_tipo` FOREIGN KEY (`tipo_terreno_id`) REFERENCES `tipos_terreno` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sendero_tipos_terreno`
--

LOCK TABLES `sendero_tipos_terreno` WRITE;
/*!40000 ALTER TABLE `sendero_tipos_terreno` DISABLE KEYS */;
INSERT INTO `sendero_tipos_terreno` VALUES (43,1,1),(42,1,2),(44,1,4),(40,2,1),(39,2,2),(38,2,3),(41,2,4),(48,3,1),(47,3,2);
/*!40000 ALTER TABLE `sendero_tipos_terreno` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `senderos`
--

DROP TABLE IF EXISTS `senderos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `senderos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `fecha_sendero` date DEFAULT NULL,
  `lugar` varchar(150) NOT NULL,
  `provincia` varchar(100) DEFAULT NULL,
  `descripcion_corta` varchar(255) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen_principal` varchar(255) DEFAULT NULL,
  `imagen_flyer` varchar(255) DEFAULT NULL,
  `imagen_catalogo` varchar(255) DEFAULT NULL,
  `nivel_dificultad_id` int(11) NOT NULL,
  `tiempo_ida_vehiculo_min` int(11) DEFAULT NULL,
  `tiempo_regreso_vehiculo_min` int(11) DEFAULT NULL,
  `tipo_camino_vehiculo_id` int(11) DEFAULT NULL,
  `tiempo_sendero_min` int(11) DEFAULT NULL,
  `distancia_km` decimal(6,2) DEFAULT NULL,
  `desnivel_mts` int(11) DEFAULT NULL,
  `cobertura_senal_pct` tinyint(3) unsigned DEFAULT NULL,
  `inversion_total` decimal(10,2) DEFAULT NULL,
  `fecha_limite_pago` date DEFAULT NULL,
  `estado` enum('pendiente','visitado') NOT NULL DEFAULT 'pendiente',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_senderos_slug` (`slug`),
  KEY `fk_senderos_nivel_dificultad` (`nivel_dificultad_id`),
  KEY `fk_senderos_tipo_camino_vehiculo` (`tipo_camino_vehiculo_id`),
  CONSTRAINT `fk_senderos_nivel_dificultad` FOREIGN KEY (`nivel_dificultad_id`) REFERENCES `niveles_dificultad` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_senderos_tipo_camino_vehiculo` FOREIGN KEY (`tipo_camino_vehiculo_id`) REFERENCES `tipos_camino_vehiculo` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `senderos`
--

LOCK TABLES `senderos` WRITE;
/*!40000 ALTER TABLE `senderos` DISABLE KEYS */;
INSERT INTO `senderos` VALUES (1,'Sendero Reserva Científica La Salcedoa – Cascada Velo De Novia','sendero-reserva-cientifica-la-salcedoa-cascada-velo-de-novia','2026-06-03','Salcedo','Maria Trinidad Sanchez','¿Sabías que existe un lugar en RD que desaparece si dejas de visitarlo? El sendero Velo de Novia en Salcedo es pura magia efímera. Esta imponente cascada solo nace cuando las nubes lloran, ocultándose semanas después en una selva prehistórica.','Olvida los caminos trillados y los balnearios repletos de turistas. Si tus piernas buscan un verdadero reto y tus ojos exigen paisajes que parezcan de otro planeta, el sendero hacia la cascada Velo de Novia en la Reserva Científica La Salcedoa es la medalla que le falta a tu historial de explorador.','imagenes/senderos/sendero-reserva-cientifica-la-salcedoa-cascada-velo-de-novia/principal-20260520183915-af724d7b.jpg','imagenes/senderos/sendero-reserva-cientifica-la-salcedoa-cascada-velo-de-novia/flyer-20260520183915-907d24a8.jpg','imagenes/senderos/sendero-reserva-cientifica-la-salcedoa-cascada-velo-de-novia/catalogo-20260520183915-ba8378aa.jpg',4,90,90,3,420,10.50,450,30,1500.00,'2026-05-20','pendiente',1,'2026-05-20 16:39:15','2026-06-02 20:10:52'),(2,'Reserva Científica Loma Quita Espuela - Sendero Las nubes','reserva-cientifica-loma-quita-espuela-sendero-las-nubes','2026-05-31','San Francisco de Macorís','Duarte','Ruta Loma Quita Espuela: El pico más alto de la Cordillera Septentrional','Adéntrate en el corazón verde del Cibao y vive una aventura ecoturística inigualable en la provincia Duarte. El sendero hacia la cima de Loma Quita Espuela te invita a desafiar tus límites cruzando ríos cristalinos, caminando entre helechos gigantes y descubriendo especies que solo habitan en este rincón del planeta. Tras una emocionante caminata, romperás la niebla para coronar el pico más alto de la Cordillera Septentrional, donde te espera una panorámica impresionante de todo el valle. Es el escape perfecto para los amantes del senderismo puro, la fotografía de naturaleza y el aire libre. ¡Prepara tus botas y atrévete a descubrir el bosque nublado más espectacular de la República Dominicana!','imagenes/senderos/reserva-cientifica-loma-quita-espuela-sendero-las-nubes/principal-20260520213326-5262398c.jpg','imagenes/senderos/reserva-cientifica-loma-quita-espuela-sendero-las-nubes/flyer-20260520213326-4e4f9401.jpg','imagenes/senderos/reserva-cientifica-loma-quita-espuela-sendero-las-nubes/catalogo-20260520213326-6aa1bd7a.jpg',4,80,80,1,300,6.50,650,50,1100.00,'0000-00-00','visitado',1,'2026-05-20 19:33:26','2026-05-20 19:45:55'),(3,'Sendero Salto los Monjes','sendero-salto-los-monjes','2026-06-07','Jarabacoa','La Vega','¡Descubre el secreto mejor guardado de Jarabacoa! Un emocionante trekking te llevará hasta una joya oculta de aguas cristalinas, con una impresionante cascada virgen.','¿Listo para una experiencia fuera de lo común? Te invitamos a explorar el Salto Los Monjes, un paraíso escondido en las montañas de Jarabacoa que pocos tienen el privilegio de conocer. Lejos del turismo de masa, esta ruta está diseñada especialmente para los amantes de la aventura auténtica y la naturaleza virgen.\r\n\r\nNuestro recorrido comienza con un emocionante senderismo de aproximadamente una hora. Caminaremos rodeados de una exuberante vegetación tropical y aire puro de montaña, superando divertidos retos naturales en el camino que harán latir tu corazón.','imagenes/senderos/sendero-salto-los-monjes/principal-20260602232147-3a24d752.jpg','imagenes/senderos/sendero-salto-los-monjes/flyer-20260602232147-d6ef8b8f.jpg','imagenes/senderos/sendero-salto-los-monjes/catalogo-20260602232147-61291170.jpg',1,75,75,3,240,5.00,200,50,1000.00,'2026-06-04','pendiente',1,'2026-06-02 21:21:47','2026-06-02 21:21:47');
/*!40000 ALTER TABLE `senderos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sesiones_usuario`
--

DROP TABLE IF EXISTS `sesiones_usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sesiones_usuario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_token` (`token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`),
  CONSTRAINT `sesiones_usuario_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sesiones_usuario`
--

LOCK TABLES `sesiones_usuario` WRITE;
/*!40000 ALTER TABLE `sesiones_usuario` DISABLE KEYS */;
/*!40000 ALTER TABLE `sesiones_usuario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tarjeta_pago`
--

DROP TABLE IF EXISTS `tarjeta_pago`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tarjeta_pago` (
  `id` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `banco` varchar(120) NOT NULL,
  `cuenta` varchar(80) NOT NULL,
  `tipo_cuenta` varchar(80) NOT NULL,
  `cedula` varchar(40) NOT NULL,
  `correo` varchar(160) NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `telefono_comprobante` varchar(40) NOT NULL,
  `nota_importante` text NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tarjeta_pago`
--

LOCK TABLES `tarjeta_pago` WRITE;
/*!40000 ALTER TABLE `tarjeta_pago` DISABLE KEYS */;
INSERT INTO `tarjeta_pago` VALUES (1,'Banco Popular','846542835','Corriente','032-0039961-0','senderismogopro@gmail.com','Yomary Infante','809-323-1888','Al momento de realizar el pago debe enviar el comprobante al numero indicado. El deposito por reservacion no es reembolsable ni transferible. No se realizan reembolsos del pago total, pero puede ceder su lugar a otra persona que cuente con la capacidad fisica necesaria para realizar el sendero.',1,'2026-05-20 17:12:39');
/*!40000 ALTER TABLE `tarjeta_pago` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tipos_camino_vehiculo`
--

DROP TABLE IF EXISTS `tipos_camino_vehiculo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipos_camino_vehiculo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tipos_camino_vehiculo_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipos_camino_vehiculo`
--

LOCK TABLES `tipos_camino_vehiculo` WRITE;
/*!40000 ALTER TABLE `tipos_camino_vehiculo` DISABLE KEYS */;
INSERT INTO `tipos_camino_vehiculo` VALUES (1,'Carretera Asfaltada','',1,'2026-05-20 16:19:04'),(2,'Carretera No Asfaltada','',1,'2026-05-20 16:19:16'),(3,'Carretera Asfaltada y No Asfaltada','',1,'2026-05-20 16:19:28');
/*!40000 ALTER TABLE `tipos_camino_vehiculo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tipos_terreno`
--

DROP TABLE IF EXISTS `tipos_terreno`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipos_terreno` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipos_terreno`
--

LOCK TABLES `tipos_terreno` WRITE;
/*!40000 ALTER TABLE `tipos_terreno` DISABLE KEYS */;
INSERT INTO `tipos_terreno` VALUES (1,'Pedregoso','',1,'2026-05-20 16:19:54'),(2,'Húmedo','',1,'2026-05-20 16:20:05'),(3,'Arcilloso','',1,'2026-05-20 16:20:15'),(4,'Vegetación Alta','',1,'2026-05-20 16:20:29'),(5,'Tramos Soleados','',1,'2026-05-20 16:20:41');
/*!40000 ALTER TABLE `tipos_terreno` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `user` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol_id` int(11) NOT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `user` (`user`),
  KEY `fk_usuario_rol` (`rol_id`),
  CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'Admin','Principal','admin','admin@senderismogo.local','$2y$10$CVEyOPJBxAs9sG66tvZtNuNNWIn2JWU1Ub9HuVfBtzpBlP0mBcOvW',1,1,'2026-05-20 16:11:39','2026-05-20 12:13:28'),(2,'Isaac','Espinal','iespinal','espinalespinalisaac@gmail.com','$2y$10$Lt8eAG9W7p.AD4HIsAUMleOhKfvtoZ3mw0pfxwE/.0DRAoTXBmBxi',1,1,'2026-05-20 16:14:40','2026-06-02 17:26:32');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'sgbd'
--

--
-- Dumping routines for database 'sgbd'
--
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_iniciar_sesion` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_iniciar_sesion`(
    IN  p_user    VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    OUT p_mensaje VARCHAR(600) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    OUT p_codigo  INT
)
BEGIN
    DECLARE v_id INT DEFAULT NULL;
    DECLARE v_nombre VARCHAR(220) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '';
    DECLARE v_hash VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '';
    DECLARE v_rol_id INT DEFAULT NULL;
    DECLARE v_rol_nombre VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '';
    DECLARE v_estado TINYINT DEFAULT 0;

    SET p_codigo = 0;
    SET p_mensaje = '';

    SELECT u.id,
           CONCAT(u.nombre, ' ', u.apellido),
           u.password,
           u.rol_id,
           u.estado,
           r.nombre
      INTO v_id, v_nombre, v_hash, v_rol_id, v_estado, v_rol_nombre
      FROM usuarios u
      JOIN roles r ON r.id = u.rol_id
     WHERE (
        u.user COLLATE utf8mb4_general_ci = p_user COLLATE utf8mb4_general_ci
        OR u.email COLLATE utf8mb4_general_ci = p_user COLLATE utf8mb4_general_ci
     )
     LIMIT 1;

    IF v_id IS NULL THEN
        SET p_codigo = 1;
        SET p_mensaje = 'Usuario o correo no existe';
    ELSEIF v_estado = 0 THEN
        SET p_codigo = 2;
        SET p_mensaje = 'Usuario inactivo';
    ELSE
        SET p_codigo = 0;
        SET p_mensaje = CONCAT(v_hash, '|', v_id, '|', v_nombre, '|', v_rol_id, '|', v_rol_nombre);
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_registrar_usuario` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_usuario`(
    IN  p_nombre VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    IN  p_apellido VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    IN  p_user VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    IN  p_email VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    IN  p_password_hash VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    OUT p_mensaje VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    OUT p_codigo INT
)
proc: BEGIN
    DECLARE v_rol_id INT DEFAULT NULL;

    SET p_codigo = 0;
    SET p_mensaje = '';

    IF p_nombre IS NULL OR TRIM(p_nombre) = ''
       OR p_apellido IS NULL OR TRIM(p_apellido) = ''
       OR p_user IS NULL OR TRIM(p_user) = ''
       OR p_email IS NULL OR TRIM(p_email) = ''
       OR p_password_hash IS NULL OR TRIM(p_password_hash) = '' THEN
        SET p_codigo = 10;
        SET p_mensaje = 'Debe completar todos los campos';
        LEAVE proc;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM usuarios
        WHERE email COLLATE utf8mb4_general_ci = TRIM(p_email) COLLATE utf8mb4_general_ci
        LIMIT 1
    ) THEN
        SET p_codigo = 11;
        SET p_mensaje = 'Este email ya esta registrado';
        LEAVE proc;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM usuarios
        WHERE user COLLATE utf8mb4_general_ci = TRIM(p_user) COLLATE utf8mb4_general_ci
        LIMIT 1
    ) THEN
        SET p_codigo = 12;
        SET p_mensaje = 'Este usuario ya existe';
        LEAVE proc;
    END IF;

    SELECT id INTO v_rol_id
      FROM roles
     WHERE nombre COLLATE utf8mb4_general_ci = 'Invitado' COLLATE utf8mb4_general_ci
     LIMIT 1;

    IF v_rol_id IS NULL THEN
        SET p_codigo = 13;
        SET p_mensaje = 'No existe el rol Invitado. Crealo primero.';
        LEAVE proc;
    END IF;

    INSERT INTO usuarios (nombre, apellido, user, email, password, rol_id, estado)
    VALUES (TRIM(p_nombre), TRIM(p_apellido), TRIM(p_user), TRIM(p_email), p_password_hash, v_rol_id, 1);

    SET p_codigo = 0;
    SET p_mensaje = 'Usuario registrado correctamente';
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_roles_eliminar` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_roles_eliminar`(
  IN  p_id INT,
  OUT p_mensaje VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  OUT p_codigo INT
)
proc: BEGIN
  DECLARE v_uso INT DEFAULT 0;

  SET p_codigo = 0;
  SET p_mensaje = '';

  IF p_id IS NULL OR p_id <= 0 THEN
    SET p_codigo = 10;
    SET p_mensaje = 'ID invalido';
    LEAVE proc;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM roles WHERE id = p_id) THEN
    SET p_codigo = 11;
    SET p_mensaje = 'Rol no existe';
    LEAVE proc;
  END IF;

  SELECT COUNT(*) INTO v_uso FROM usuarios WHERE rol_id = p_id;

  IF v_uso > 0 THEN
    SET p_codigo = 12;
    SET p_mensaje = 'No se puede eliminar: hay usuarios asignados a este rol';
    LEAVE proc;
  END IF;

  DELETE FROM roles WHERE id = p_id;
  SET p_mensaje = 'Rol eliminado correctamente';
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_roles_guardar` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_roles_guardar`(
  IN  p_id INT,
  IN  p_nombre VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  IN  p_descripcion VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  OUT p_mensaje VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  OUT p_codigo INT
)
proc: BEGIN
  DECLARE v_exists INT DEFAULT 0;

  SET p_codigo = 0;
  SET p_mensaje = '';

  IF p_nombre IS NULL OR TRIM(p_nombre) = '' THEN
    SET p_codigo = 10;
    SET p_mensaje = 'El nombre del rol es obligatorio';
    LEAVE proc;
  END IF;

  SELECT COUNT(*) INTO v_exists
  FROM roles
  WHERE nombre COLLATE utf8mb4_general_ci = TRIM(p_nombre) COLLATE utf8mb4_general_ci
    AND (p_id = 0 OR id <> p_id);

  IF v_exists > 0 THEN
    SET p_codigo = 11;
    SET p_mensaje = 'Ya existe un rol con ese nombre';
    LEAVE proc;
  END IF;

  IF p_id IS NULL OR p_id = 0 THEN
    INSERT INTO roles (nombre, descripcion)
    VALUES (TRIM(p_nombre), NULLIF(TRIM(p_descripcion), ''));
    SET p_mensaje = 'Rol creado correctamente';
  ELSE
    IF NOT EXISTS (SELECT 1 FROM roles WHERE id = p_id) THEN
      SET p_codigo = 12;
      SET p_mensaje = 'Rol no encontrado';
      LEAVE proc;
    END IF;

    UPDATE roles
    SET nombre = TRIM(p_nombre),
        descripcion = NULLIF(TRIM(p_descripcion), '')
    WHERE id = p_id;

    SET p_mensaje = 'Rol actualizado correctamente';
  END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_roles_listar` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_roles_listar`()
BEGIN
  SELECT id, nombre, descripcion, created_at
  FROM roles
  ORDER BY id DESC;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_usuarios_cambiar_estado` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_usuarios_cambiar_estado`(
  IN  p_id INT,
  IN  p_estado TINYINT,
  OUT p_mensaje VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  OUT p_codigo INT
)
proc: BEGIN
  SET p_codigo = 0;
  SET p_mensaje = '';

  IF p_id IS NULL OR p_id <= 0 THEN
    SET p_codigo = 10;
    SET p_mensaje = 'ID invalido';
    LEAVE proc;
  END IF;

  IF p_estado NOT IN (0,1) THEN
    SET p_codigo = 11;
    SET p_mensaje = 'Estado invalido';
    LEAVE proc;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM usuarios WHERE id = p_id) THEN
    SET p_codigo = 12;
    SET p_mensaje = 'Usuario no encontrado';
    LEAVE proc;
  END IF;

  UPDATE usuarios SET estado = p_estado WHERE id = p_id;
  SET p_mensaje = IF(p_estado = 1, 'Usuario activado', 'Usuario inactivado');
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_usuarios_eliminar` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_usuarios_eliminar`(
  IN  p_id INT,
  OUT p_mensaje VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  OUT p_codigo INT
)
proc: BEGIN
  DECLARE v_estado TINYINT;

  SET p_codigo = 0;
  SET p_mensaje = '';

  IF p_id IS NULL OR p_id <= 0 THEN
    SET p_codigo = 10;
    SET p_mensaje = 'ID invalido';
    LEAVE proc;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM usuarios WHERE id = p_id) THEN
    SET p_codigo = 11;
    SET p_mensaje = 'Usuario no existe';
    LEAVE proc;
  END IF;

  SELECT estado INTO v_estado FROM usuarios WHERE id = p_id;

  IF v_estado = 1 THEN
    SET p_codigo = 12;
    SET p_mensaje = 'No se puede eliminar un usuario activo. Debe inactivarlo primero.';
    LEAVE proc;
  END IF;

  DELETE FROM usuarios WHERE id = p_id;
  SET p_mensaje = 'Usuario eliminado correctamente';
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_usuarios_guardar` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_usuarios_guardar`(
  IN  p_id INT,
  IN  p_nombre VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  IN  p_apellido VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  IN  p_user VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  IN  p_email VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  IN  p_password_hash VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  IN  p_rol_id INT,
  OUT p_mensaje VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  OUT p_codigo INT
)
proc: BEGIN
  DECLARE v_exists INT DEFAULT 0;

  SET p_codigo = 0;
  SET p_mensaje = '';

  IF p_nombre IS NULL OR TRIM(p_nombre) = ''
     OR p_apellido IS NULL OR TRIM(p_apellido) = ''
     OR p_user IS NULL OR TRIM(p_user) = ''
     OR p_email IS NULL OR TRIM(p_email) = ''
     OR p_rol_id IS NULL OR p_rol_id <= 0 THEN
    SET p_codigo = 10;
    SET p_mensaje = 'Debe completar todos los campos obligatorios';
    LEAVE proc;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM roles WHERE id = p_rol_id) THEN
    SET p_codigo = 11;
    SET p_mensaje = 'El rol seleccionado no existe';
    LEAVE proc;
  END IF;

  SELECT COUNT(*) INTO v_exists
  FROM usuarios
  WHERE email COLLATE utf8mb4_general_ci = TRIM(p_email) COLLATE utf8mb4_general_ci
    AND (p_id = 0 OR id <> p_id);

  IF v_exists > 0 THEN
    SET p_codigo = 12;
    SET p_mensaje = 'Este email ya esta registrado';
    LEAVE proc;
  END IF;

  SELECT COUNT(*) INTO v_exists
  FROM usuarios
  WHERE user COLLATE utf8mb4_general_ci = TRIM(p_user) COLLATE utf8mb4_general_ci
    AND (p_id = 0 OR id <> p_id);

  IF v_exists > 0 THEN
    SET p_codigo = 13;
    SET p_mensaje = 'Este usuario ya existe';
    LEAVE proc;
  END IF;

  IF p_id IS NULL OR p_id = 0 THEN
    IF p_password_hash IS NULL OR TRIM(p_password_hash) = '' THEN
      SET p_codigo = 14;
      SET p_mensaje = 'La contrasena es obligatoria para crear el usuario';
      LEAVE proc;
    END IF;

    INSERT INTO usuarios (nombre, apellido, user, email, password, rol_id, estado)
    VALUES (TRIM(p_nombre), TRIM(p_apellido), TRIM(p_user), TRIM(p_email), p_password_hash, p_rol_id, 1);

    SET p_mensaje = 'Usuario creado correctamente';
  ELSE
    IF NOT EXISTS (SELECT 1 FROM usuarios WHERE id = p_id) THEN
      SET p_codigo = 15;
      SET p_mensaje = 'Usuario no encontrado';
      LEAVE proc;
    END IF;

    IF p_password_hash IS NULL OR TRIM(p_password_hash) = '' THEN
      UPDATE usuarios
      SET nombre = TRIM(p_nombre),
          apellido = TRIM(p_apellido),
          user = TRIM(p_user),
          email = TRIM(p_email),
          rol_id = p_rol_id
      WHERE id = p_id;
    ELSE
      UPDATE usuarios
      SET nombre = TRIM(p_nombre),
          apellido = TRIM(p_apellido),
          user = TRIM(p_user),
          email = TRIM(p_email),
          password = p_password_hash,
          rol_id = p_rol_id
      WHERE id = p_id;
    END IF;

    SET p_mensaje = 'Usuario actualizado correctamente';
  END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_usuarios_listar` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_usuarios_listar`()
BEGIN
  SELECT
    u.id,
    u.nombre,
    u.apellido,
    u.user,
    u.email,
    u.rol_id,
    r.nombre AS rol_nombre,
    u.estado,
    u.created_at,
    u.last_login
  FROM usuarios u
  INNER JOIN roles r ON r.id = u.rol_id
  ORDER BY u.id DESC;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-02 17:46:21
