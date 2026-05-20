-- Script de estructura para Senderismo Go
-- Generado desde respaldo local sin datos personales ni registros reales.
-- Crear primero la base de datos sgbd y luego ejecutar este archivo.

CREATE DATABASE IF NOT EXISTS `sgbd` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `sgbd`;

-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Estructura segura de base de datos: sgbd
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
/*!40000 ALTER TABLE `anotaciones_importantes` ENABLE KEYS */;
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
/*!40000 ALTER TABLE `configuracion_contacto` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `elementos_incluidos`
--

LOCK TABLES `elementos_incluidos` WRITE;
/*!40000 ALTER TABLE `elementos_incluidos` DISABLE KEYS */;
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
-- Table structure for table `niveles_dificultad`
--

DROP TABLE IF EXISTS `niveles_dificultad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `niveles_dificultad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `niveles_dificultad`
--

LOCK TABLES `niveles_dificultad` WRITE;
/*!40000 ALTER TABLE `niveles_dificultad` DISABLE KEYS */;
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
/*!40000 ALTER TABLE `permisos` ENABLE KEYS */;
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
  CONSTRAINT `fk_registros_senderos_detalle` FOREIGN KEY (`detalle_usuario_id`) REFERENCES `detalles_usuarios` (`id`) ON UPDATE CASCADE,
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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sendero_anotaciones`
--

LOCK TABLES `sendero_anotaciones` WRITE;
/*!40000 ALTER TABLE `sendero_anotaciones` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sendero_elementos_incluidos`
--

LOCK TABLES `sendero_elementos_incluidos` WRITE;
/*!40000 ALTER TABLE `sendero_elementos_incluidos` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sendero_imagenes`
--

LOCK TABLES `sendero_imagenes` WRITE;
/*!40000 ALTER TABLE `sendero_imagenes` DISABLE KEYS */;
/*!40000 ALTER TABLE `sendero_imagenes` ENABLE KEYS */;
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
  CONSTRAINT `fk_puntos_encuentro_sendero` FOREIGN KEY (`sendero_id`) REFERENCES `senderos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sendero_puntos_encuentro`
--

LOCK TABLES `sendero_puntos_encuentro` WRITE;
/*!40000 ALTER TABLE `sendero_puntos_encuentro` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sendero_tipos_terreno`
--

LOCK TABLES `sendero_tipos_terreno` WRITE;
/*!40000 ALTER TABLE `sendero_tipos_terreno` DISABLE KEYS */;
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
  `cobertura_senal_pct` tinyint(3) unsigned DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `senderos`
--

LOCK TABLES `senderos` WRITE;
/*!40000 ALTER TABLE `senderos` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipos_camino_vehiculo`
--

LOCK TABLES `tipos_camino_vehiculo` WRITE;
/*!40000 ALTER TABLE `tipos_camino_vehiculo` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipos_terreno`
--

LOCK TABLES `tipos_terreno` WRITE;
/*!40000 ALTER TABLE `tipos_terreno` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
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
CREATE PROCEDURE `sp_iniciar_sesion`(
    IN  p_user    VARCHAR(100),
    OUT p_mensaje VARCHAR(600),
    OUT p_codigo  INT
)
BEGIN
    DECLARE v_id INT DEFAULT NULL;
    DECLARE v_nombre VARCHAR(220) DEFAULT '';
    DECLARE v_hash VARCHAR(255) DEFAULT '';
    DECLARE v_rol_id INT DEFAULT NULL;
    DECLARE v_rol_nombre VARCHAR(50) DEFAULT '';
    DECLARE v_estado TINYINT DEFAULT 0;

    SET p_codigo = 0;
    SET p_mensaje = '';

    SELECT u.id,
           CONCAT(u.nombre, ' ', u.apellido) AS nombre_completo,
           u.password,
           u.rol_id,
           u.estado,
           r.nombre AS rol_nombre
      INTO v_id, v_nombre, v_hash, v_rol_id, v_estado, v_rol_nombre
      FROM usuarios u
      JOIN roles r ON r.id = u.rol_id
     WHERE (u.user = p_user OR u.email = p_user)
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
CREATE PROCEDURE `sp_registrar_usuario`(
    IN  p_nombre    VARCHAR(100),
    IN  p_apellido  VARCHAR(100),
    IN  p_user      VARCHAR(50),
    IN  p_email     VARCHAR(100),
    IN  p_password_hash  VARCHAR(255),
    OUT p_mensaje   VARCHAR(255),
    OUT p_codigo    INT
)
proc: BEGIN
    DECLARE v_rol_id INT DEFAULT NULL;

    SET p_codigo = 0;
    SET p_mensaje = '';

    -- Validaciones básicas
    IF p_nombre IS NULL OR TRIM(p_nombre) = ''
       OR p_apellido IS NULL OR TRIM(p_apellido) = ''
       OR p_user IS NULL OR TRIM(p_user) = ''
       OR p_email IS NULL OR TRIM(p_email) = ''
       OR p_password_hash IS NULL OR TRIM(p_password_hash) = '' THEN
        SET p_codigo = 10;
        SET p_mensaje = 'Debe completar todos los campos';
        LEAVE proc;
    END IF;

    -- Email repetido
    IF EXISTS (SELECT 1 FROM usuarios WHERE email = TRIM(p_email) LIMIT 1) THEN
        SET p_codigo = 11;
        SET p_mensaje = 'Este email ya está registrado';
        LEAVE proc;
    END IF;

    -- User repetido
    IF EXISTS (SELECT 1 FROM usuarios WHERE user = TRIM(p_user) LIMIT 1) THEN
        SET p_codigo = 12;
        SET p_mensaje = 'Este usuario ya existe';
        LEAVE proc;
    END IF;

    -- Obtener rol invitado
    SELECT id INTO v_rol_id
      FROM roles
     WHERE nombre = 'Invitado'
     LIMIT 1;

    IF v_rol_id IS NULL THEN
        SET p_codigo = 13;
        SET p_mensaje = 'No existe el rol Invitado. Créalo primero.';
        LEAVE proc;
    END IF;

    -- Insertar usuario (guardando HASH generado por PHP)
    INSERT INTO usuarios (nombre, apellido, user, email, password, rol_id, estado)
    VALUES (
        TRIM(p_nombre),
        TRIM(p_apellido),
        TRIM(p_user),
        TRIM(p_email),
        p_password_hash,
        v_rol_id,
        1
    );

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
CREATE PROCEDURE `sp_roles_eliminar`(
  IN  p_id INT,
  OUT p_mensaje VARCHAR(255),
  OUT p_codigo INT
)
proc: BEGIN
  DECLARE v_uso INT DEFAULT 0;

  SET p_codigo = 0;
  SET p_mensaje = '';

  IF p_id IS NULL OR p_id <= 0 THEN
    SET p_codigo = 10;
    SET p_mensaje = 'ID inválido';
    LEAVE proc;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM roles WHERE id = p_id) THEN
    SET p_codigo = 11;
    SET p_mensaje = 'Rol no existe';
    LEAVE proc;
  END IF;

  SELECT COUNT(*) INTO v_uso
  FROM usuarios
  WHERE rol_id = p_id;

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
CREATE PROCEDURE `sp_roles_guardar`(
  IN  p_id INT,
  IN  p_nombre VARCHAR(50),
  IN  p_descripcion VARCHAR(150),
  OUT p_mensaje VARCHAR(255),
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

  -- Duplicado (mismo nombre, distinto id)
  SELECT COUNT(*) INTO v_exists
  FROM roles
  WHERE nombre = TRIM(p_nombre)
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
CREATE PROCEDURE `sp_roles_listar`()
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
CREATE PROCEDURE `sp_usuarios_cambiar_estado`(
  IN  p_id INT,
  IN  p_estado TINYINT,
  OUT p_mensaje VARCHAR(255),
  OUT p_codigo INT
)
proc: BEGIN
  SET p_codigo = 0;
  SET p_mensaje = '';

  IF p_id IS NULL OR p_id <= 0 THEN
    SET p_codigo = 10;
    SET p_mensaje = 'ID inválido';
    LEAVE proc;
  END IF;

  IF p_estado NOT IN (0,1) THEN
    SET p_codigo = 11;
    SET p_mensaje = 'Estado inválido';
    LEAVE proc;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM usuarios WHERE id = p_id) THEN
    SET p_codigo = 12;
    SET p_mensaje = 'Usuario no encontrado';
    LEAVE proc;
  END IF;

  UPDATE usuarios
  SET estado = p_estado
  WHERE id = p_id;

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
CREATE PROCEDURE `sp_usuarios_eliminar`(
  IN  p_id INT,
  OUT p_mensaje VARCHAR(255),
  OUT p_codigo INT
)
proc: BEGIN

  DECLARE v_estado TINYINT;

  SET p_codigo = 0;
  SET p_mensaje = '';

  -- Validar ID
  IF p_id IS NULL OR p_id <= 0 THEN
    SET p_codigo = 10;
    SET p_mensaje = 'ID inválido';
    LEAVE proc;
  END IF;

  -- Validar existencia
  IF NOT EXISTS (SELECT 1 FROM usuarios WHERE id = p_id) THEN
    SET p_codigo = 11;
    SET p_mensaje = 'Usuario no existe';
    LEAVE proc;
  END IF;

  -- Obtener estado
  SELECT estado INTO v_estado
  FROM usuarios
  WHERE id = p_id;

  -- No permitir eliminar activos
  IF v_estado = 1 THEN
    SET p_codigo = 12;
    SET p_mensaje = 'No se puede eliminar un usuario activo. Debe inactivarlo primero.';
    LEAVE proc;
  END IF;

  -- Eliminar solo si está inactivo
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
CREATE PROCEDURE `sp_usuarios_guardar`(
  IN  p_id INT,
  IN  p_nombre VARCHAR(100),
  IN  p_apellido VARCHAR(100),
  IN  p_user VARCHAR(50),
  IN  p_email VARCHAR(100),
  IN  p_password_hash VARCHAR(255),  -- hash ya generado en PHP
  IN  p_rol_id INT,
  OUT p_mensaje VARCHAR(255),
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

  -- rol existe
  IF NOT EXISTS (SELECT 1 FROM roles WHERE id = p_rol_id) THEN
    SET p_codigo = 11;
    SET p_mensaje = 'El rol seleccionado no existe';
    LEAVE proc;
  END IF;

  -- email duplicado
  SELECT COUNT(*) INTO v_exists
  FROM usuarios
  WHERE email = TRIM(p_email)
    AND (p_id = 0 OR id <> p_id);

  IF v_exists > 0 THEN
    SET p_codigo = 12;
    SET p_mensaje = 'Este email ya está registrado';
    LEAVE proc;
  END IF;

  -- user duplicado
  SELECT COUNT(*) INTO v_exists
  FROM usuarios
  WHERE user = TRIM(p_user)
    AND (p_id = 0 OR id <> p_id);

  IF v_exists > 0 THEN
    SET p_codigo = 13;
    SET p_mensaje = 'Este usuario ya existe';
    LEAVE proc;
  END IF;

  IF p_id IS NULL OR p_id = 0 THEN
    -- Crear: password obligatorio
    IF p_password_hash IS NULL OR TRIM(p_password_hash) = '' THEN
      SET p_codigo = 14;
      SET p_mensaje = 'La contraseña es obligatoria para crear el usuario';
      LEAVE proc;
    END IF;

    INSERT INTO usuarios (nombre, apellido, user, email, password, rol_id, estado)
    VALUES (TRIM(p_nombre), TRIM(p_apellido), TRIM(p_user), TRIM(p_email), p_password_hash, p_rol_id, 1);

    SET p_mensaje = 'Usuario creado correctamente';
  ELSE
    -- Editar: usuario existe
    IF NOT EXISTS (SELECT 1 FROM usuarios WHERE id = p_id) THEN
      SET p_codigo = 15;
      SET p_mensaje = 'Usuario no encontrado';
      LEAVE proc;
    END IF;

    -- Si viene password, la actualiza. Si no, la deja igual.
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
CREATE PROCEDURE `sp_usuarios_listar`()
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

-- Dump completed on 2026-05-13 16:03:50
