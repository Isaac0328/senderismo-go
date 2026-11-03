
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_menu_modulos` (
  `permiso_codigo` varchar(120) NOT NULL,
  `seccion_id` int(10) unsigned NOT NULL,
  `subseccion_id` int(10) unsigned DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`permiso_codigo`),
  KEY `fk_menu_modulo_subseccion` (`subseccion_id`),
  KEY `idx_menu_modulos_orden` (`seccion_id`,`subseccion_id`,`activo`,`orden`),
  CONSTRAINT `fk_menu_modulo_seccion` FOREIGN KEY (`seccion_id`) REFERENCES `admin_menu_secciones` (`id`),
  CONSTRAINT `fk_menu_modulo_subseccion` FOREIGN KEY (`subseccion_id`) REFERENCES `admin_menu_subsecciones` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_menu_secciones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `etiqueta` varchar(80) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `icono` varchar(50) NOT NULL DEFAULT 'grid',
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_menu_secciones_orden` (`activo`,`orden`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_menu_subsecciones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `seccion_id` int(10) unsigned NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_menu_subsecciones_orden` (`seccion_id`,`activo`,`orden`),
  CONSTRAINT `fk_menu_subseccion_seccion` FOREIGN KEY (`seccion_id`) REFERENCES `admin_menu_secciones` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `anotaciones_importantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `hero_boton_texto` varchar(80) NOT NULL DEFAULT 'Escribir mensaje',
  `hero_whatsapp_texto` varchar(80) NOT NULL DEFAULT 'WhatsApp',
  `seccion_kicker` varchar(80) NOT NULL DEFAULT 'Atencion personalizada',
  `seccion_titulo` varchar(160) NOT NULL DEFAULT 'Estamos listos para orientarte.',
  `nota_contacto` text DEFAULT NULL,
  `form_kicker` varchar(80) NOT NULL DEFAULT 'Mensaje rapido',
  `form_titulo` varchar(120) NOT NULL DEFAULT 'Escribenos',
  `form_subtitulo` varchar(255) NOT NULL DEFAULT 'Completa estos datos y nos pondremos en contacto contigo.',
  `form_privacidad` varchar(255) NOT NULL DEFAULT 'Usaremos tu informacion solo para responder esta solicitud.',
  `boton_formulario` varchar(80) NOT NULL DEFAULT 'Enviar mensaje',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuracion_inicio` (
  `id` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `hero_imagen` varchar(255) NOT NULL DEFAULT 'imagenes/paisajes/hero.jpg',
  `logo_imagen` varchar(255) NOT NULL DEFAULT 'imagenes/logo/logo_sg.png',
  `hero_titulo` varchar(160) NOT NULL,
  `hero_subtitulo` varchar(255) NOT NULL,
  `boton_texto` varchar(80) NOT NULL DEFAULT 'CONOCER MAS',
  `boton_url` varchar(255) NOT NULL DEFAULT '#porque-elegirnos',
  `acceso_rapido_texto` varchar(120) NOT NULL DEFAULT 'Ver proximos senderos',
  `acceso_rapido_badge` varchar(40) NOT NULL DEFAULT 'Agenda',
  `acceso_rapido_url` varchar(255) NOT NULL DEFAULT 'pantallas/senderos.php',
  `porque_titulo` varchar(160) NOT NULL,
  `galeria_titulo` varchar(180) NOT NULL,
  `galeria_subtitulo` varchar(255) NOT NULL,
  `cta_titulo` varchar(180) NOT NULL,
  `cta_texto` text NOT NULL,
  `cta_boton_texto` varchar(80) NOT NULL,
  `cta_boton_url` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuracion_menu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) NOT NULL,
  `etiqueta` varchar(80) NOT NULL,
  `ruta` varchar(255) NOT NULL,
  `parent_codigo` varchar(50) DEFAULT NULL,
  `icono` varchar(60) NOT NULL DEFAULT 'circle',
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_configuracion_menu_codigo` (`codigo`),
  KEY `idx_configuracion_menu_orden` (`parent_codigo`,`activo`,`orden`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuracion_nosotros` (
  `id` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `hero_imagen` varchar(255) NOT NULL DEFAULT 'imagenes/paisajes/hero.jpg',
  `hero_kicker` varchar(80) NOT NULL DEFAULT 'Nosotros',
  `hero_titulo` varchar(180) NOT NULL,
  `hero_subtitulo` text NOT NULL,
  `boton_principal_texto` varchar(80) NOT NULL DEFAULT 'Ver senderos',
  `boton_principal_url` varchar(255) NOT NULL DEFAULT 'pantallas/senderos.php',
  `boton_secundario_texto` varchar(80) NOT NULL DEFAULT 'Coordinar una ruta',
  `boton_secundario_url` varchar(255) NOT NULL DEFAULT 'pantallas/contacto.php',
  `historia_imagen` varchar(255) NOT NULL DEFAULT 'imagenes/paisajes/img4.jpg',
  `historia_badge_titulo` varchar(80) NOT NULL DEFAULT 'Desde 2015',
  `historia_badge_texto` varchar(120) NOT NULL DEFAULT 'Creando comunidad outdoor',
  `historia_kicker` varchar(80) NOT NULL DEFAULT 'Nuestra historia',
  `historia_titulo` varchar(180) NOT NULL,
  `historia_texto_1` text NOT NULL,
  `historia_texto_2` text NOT NULL,
  `valores_kicker` varchar(80) NOT NULL DEFAULT 'Nuestro compromiso',
  `valores_titulo` varchar(180) NOT NULL,
  `valores_texto` text NOT NULL,
  `proceso_kicker` varchar(80) NOT NULL DEFAULT 'Como trabajamos',
  `proceso_titulo` varchar(180) NOT NULL,
  `proceso_texto` text NOT NULL,
  `equipo_kicker` varchar(80) NOT NULL DEFAULT 'Equipo',
  `equipo_titulo` varchar(180) NOT NULL,
  `equipo_texto` text NOT NULL,
  `cta_kicker` varchar(80) NOT NULL DEFAULT 'Proxima aventura',
  `cta_titulo` varchar(180) NOT NULL,
  `cta_texto` text NOT NULL,
  `cta_boton_principal_texto` varchar(80) NOT NULL DEFAULT 'Ver proximos',
  `cta_boton_principal_url` varchar(255) NOT NULL DEFAULT 'pantallas/senderos.php',
  `cta_boton_secundario_texto` varchar(80) NOT NULL DEFAULT 'Contactar',
  `cta_boton_secundario_url` varchar(255) NOT NULL DEFAULT 'pantallas/contacto.php',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuracion_sitio` (
  `id` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `nombre_sitio` varchar(120) NOT NULL DEFAULT 'Plataforma',
  `nombre_corto` varchar(80) NOT NULL DEFAULT 'Plataforma',
  `eslogan` varchar(180) NOT NULL DEFAULT 'Experiencias para compartir',
  `logo_header` varchar(255) NOT NULL DEFAULT '',
  `logo_footer` varchar(255) NOT NULL DEFAULT '',
  `favicon` varchar(255) NOT NULL DEFAULT '',
  `imagen_compartir` varchar(255) NOT NULL DEFAULT 'imagenes/paisajes/hero.jpg',
  `meta_descripcion` varchar(320) NOT NULL DEFAULT 'Actividades y experiencias para compartir.',
  `login_texto` varchar(60) NOT NULL DEFAULT 'Iniciar Sesion',
  `footer_descripcion` text NOT NULL,
  `footer_enlaces_titulo` varchar(80) NOT NULL DEFAULT 'Explora',
  `footer_contacto_titulo` varchar(80) NOT NULL DEFAULT 'Contacto',
  `footer_copyright` varchar(255) NOT NULL DEFAULT 'Todos los derechos reservados.',
  `footer_mostrar_identidad` tinyint(1) NOT NULL DEFAULT 1,
  `footer_mostrar_logo` tinyint(1) NOT NULL DEFAULT 1,
  `footer_mostrar_descripcion` tinyint(1) NOT NULL DEFAULT 1,
  `footer_mostrar_enlaces` tinyint(1) NOT NULL DEFAULT 1,
  `footer_mostrar_contacto` tinyint(1) NOT NULL DEFAULT 1,
  `footer_mostrar_ubicacion` tinyint(1) NOT NULL DEFAULT 1,
  `footer_mostrar_telefono` tinyint(1) NOT NULL DEFAULT 1,
  `footer_mostrar_email` tinyint(1) NOT NULL DEFAULT 1,
  `footer_mostrar_whatsapp` tinyint(1) NOT NULL DEFAULT 1,
  `footer_mostrar_instagram` tinyint(1) NOT NULL DEFAULT 0,
  `footer_mostrar_copyright` tinyint(1) NOT NULL DEFAULT 1,
  `alerta_inversion_economica_activa` tinyint(1) NOT NULL DEFAULT 1,
  `alerta_inversion_economica_texto` varchar(500) NOT NULL DEFAULT 'Esta opcion de inversion no incluye algunos beneficios o suplementos de las demas opciones. Revisa sus detalles antes de continuar.',
  `consentimiento_informado_texto` mediumtext NOT NULL,
  `rgpd_texto` text NOT NULL,
  `politica_privacidad_texto` mediumtext NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuracion_tema` (
  `id` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `tema` varchar(40) NOT NULL DEFAULT 'senderismo',
  `primary_color` varchar(7) NOT NULL DEFAULT '#255f38',
  `primary_dark_color` varchar(7) NOT NULL DEFAULT '#102617',
  `accent_color` varchar(7) NOT NULL DEFAULT '#e10600',
  `accent_dark_color` varchar(7) NOT NULL DEFAULT '#b90000',
  `background_color` varchar(7) NOT NULL DEFAULT '#f3f6ef',
  `surface_color` varchar(7) NOT NULL DEFAULT '#ffffff',
  `text_color` varchar(7) NOT NULL DEFAULT '#111111',
  `muted_color` varchar(7) NOT NULL DEFAULT '#5f6d64',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contabilidad_categoria_gasto` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cont_categoria_gasto_nombre` (`nombre`),
  KEY `idx_cont_categoria_gasto_activo` (`activo`,`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contabilidad_estado_financiero` (
  `codigo` varchar(40) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#0F6B3B',
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`codigo`),
  KEY `idx_cont_estado_financiero_orden` (`activo`,`orden`,`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contabilidad_gastos_catalogo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(160) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `categoria` varchar(80) DEFAULT NULL,
  `categoria_gasto_id` int(11) DEFAULT NULL,
  `unidad` varchar(40) NOT NULL DEFAULT 'unidad',
  `costo_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cont_gastos_activo` (`activo`,`nombre`),
  KEY `idx_cont_gastos_categoria_gasto` (`categoria_gasto_id`),
  CONSTRAINT `fk_cont_gastos_categoria_gasto` FOREIGN KEY (`categoria_gasto_id`) REFERENCES `contabilidad_categoria_gasto` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contabilidad_metodo_pago` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cont_metodo_pago_nombre` (`nombre`),
  KEY `idx_cont_metodo_pago_activo` (`activo`,`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=561 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contabilidad_registro_pagos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registro_id` int(11) NOT NULL,
  `sendero_id` int(11) NOT NULL,
  `pagado` tinyint(1) NOT NULL DEFAULT 0,
  `estado_financiero` enum('pendiente','pagado','parcial','credito_aplicado','pagado_con_credito','descuento','deuda','cortesia','exento','no_asistio_sin_pago') NOT NULL DEFAULT 'pendiente',
  `monto_esperado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `monto_pagado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `credito_aplicado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `descuento_autorizado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saldo_pendiente` decimal(12,2) NOT NULL DEFAULT 0.00,
  `credito_id` int(11) DEFAULT NULL,
  `credito_generado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `monto_retenido` decimal(12,2) NOT NULL DEFAULT 0.00,
  `fecha_pago` date DEFAULT NULL,
  `metodo_pago` varchar(60) DEFAULT NULL,
  `metodo_pago_id` int(11) DEFAULT NULL,
  `nota` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cont_pago_registro` (`registro_id`),
  KEY `idx_cont_pagos_sendero` (`sendero_id`,`pagado`),
  KEY `idx_cont_pagos_metodo_pago` (`metodo_pago_id`),
  KEY `idx_cont_pagos_estado_financiero` (`sendero_id`,`estado_financiero`),
  KEY `idx_cont_pagos_credito` (`credito_id`),
  CONSTRAINT `fk_cont_pagos_metodo_pago` FOREIGN KEY (`metodo_pago_id`) REFERENCES `contabilidad_metodo_pago` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_cont_pagos_registro` FOREIGN KEY (`registro_id`) REFERENCES `registros_senderos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cont_pagos_sendero` FOREIGN KEY (`sendero_id`) REFERENCES `senderos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=754 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contabilidad_sendero_gastos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sendero_id` int(11) NOT NULL,
  `gasto_id` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL DEFAULT 0.00,
  `costo_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `nota` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cont_sendero_gasto` (`sendero_id`,`gasto_id`),
  KEY `idx_cont_gastos_sendero` (`sendero_id`),
  KEY `fk_cont_gastos_catalogo` (`gasto_id`),
  CONSTRAINT `fk_cont_gastos_catalogo` FOREIGN KEY (`gasto_id`) REFERENCES `contabilidad_gastos_catalogo` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_cont_gastos_sendero` FOREIGN KEY (`sendero_id`) REFERENCES `senderos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=214 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contacto_bloques` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `grupo` varchar(30) NOT NULL,
  `icono` varchar(60) NOT NULL DEFAULT 'circle',
  `titulo` varchar(120) NOT NULL,
  `texto` varchar(255) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contacto_bloques_grupo` (`grupo`,`activo`,`orden`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `imagen_perfil` varchar(255) DEFAULT NULL,
  `imagen_cabecera` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_detalles_usuarios_usuario` (`usuario_id`),
  CONSTRAINT `fk_detalles_usuarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `elementos_incluidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `encuesta_envios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `encuesta_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `sendero_id` int(11) DEFAULT NULL,
  `estado` enum('pendiente','respondida','cancelada') NOT NULL DEFAULT 'pendiente',
  `enviado_at` datetime NOT NULL DEFAULT current_timestamp(),
  `respondido_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_encuesta_envio_usuario` (`encuesta_id`,`usuario_id`),
  KEY `idx_encuesta_envios_usuario` (`usuario_id`,`estado`),
  KEY `idx_encuesta_envios_sendero` (`sendero_id`),
  CONSTRAINT `fk_encuesta_envios_encuesta` FOREIGN KEY (`encuesta_id`) REFERENCES `encuestas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_encuesta_envios_sendero` FOREIGN KEY (`sendero_id`) REFERENCES `senderos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_encuesta_envios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `encuesta_opciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pregunta_id` int(11) NOT NULL,
  `texto` varchar(255) NOT NULL,
  `valor` varchar(120) DEFAULT NULL,
  `puntuacion` decimal(8,2) NOT NULL DEFAULT 0.00,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_encuesta_opciones_pregunta` (`pregunta_id`,`orden`),
  CONSTRAINT `fk_encuesta_opciones_pregunta` FOREIGN KEY (`pregunta_id`) REFERENCES `encuesta_preguntas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `encuesta_plantillas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(180) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `preguntas_json` longtext NOT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_encuesta_plantillas_activo` (`activo`,`updated_at`),
  KEY `fk_encuesta_plantillas_creador` (`creado_por`),
  CONSTRAINT `fk_encuesta_plantillas_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `encuesta_preguntas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `encuesta_id` int(11) NOT NULL,
  `pregunta` varchar(255) NOT NULL,
  `ayuda` varchar(255) DEFAULT NULL,
  `tipo` enum('texto','textarea','radio','checkbox','select','escala','numero') NOT NULL DEFAULT 'texto',
  `requerido` tinyint(1) NOT NULL DEFAULT 1,
  `puntaje_max` decimal(8,2) NOT NULL DEFAULT 0.00,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_encuesta_preguntas_encuesta` (`encuesta_id`,`orden`),
  CONSTRAINT `fk_encuesta_preguntas_encuesta` FOREIGN KEY (`encuesta_id`) REFERENCES `encuestas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `encuesta_respuestas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `envio_id` int(11) NOT NULL,
  `pregunta_id` int(11) NOT NULL,
  `opcion_id` int(11) DEFAULT NULL,
  `respuesta_texto` text DEFAULT NULL,
  `respuesta_numero` decimal(10,2) DEFAULT NULL,
  `puntuacion` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_encuesta_respuestas_envio` (`envio_id`),
  KEY `idx_encuesta_respuestas_pregunta` (`pregunta_id`),
  KEY `fk_encuesta_respuestas_opcion` (`opcion_id`),
  CONSTRAINT `fk_encuesta_respuestas_envio` FOREIGN KEY (`envio_id`) REFERENCES `encuesta_envios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_encuesta_respuestas_opcion` FOREIGN KEY (`opcion_id`) REFERENCES `encuesta_opciones` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_encuesta_respuestas_pregunta` FOREIGN KEY (`pregunta_id`) REFERENCES `encuesta_preguntas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `encuestas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(180) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `sendero_id` int(11) DEFAULT NULL,
  `destinatarios` enum('sendero_asistentes','sendero_registrados','todos_usuarios') NOT NULL DEFAULT 'sendero_asistentes',
  `estado` enum('borrador','enviada','cancelada','cerrada') NOT NULL DEFAULT 'borrador',
  `anonima` tinyint(1) NOT NULL DEFAULT 0,
  `permite_editar_respuesta` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_envio` datetime DEFAULT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_por` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_encuestas_sendero` (`sendero_id`),
  KEY `idx_encuestas_estado` (`estado`,`activo`),
  KEY `fk_encuestas_creador` (`creado_por`),
  CONSTRAINT `fk_encuestas_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_encuestas_sendero` FOREIGN KEY (`sendero_id`) REFERENCES `senderos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fin_auditoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `accion` varchar(60) NOT NULL,
  `entidad` varchar(60) NOT NULL,
  `entidad_id` int(11) NOT NULL,
  `antes` longtext DEFAULT NULL,
  `despues` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `fin_auditoria_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fin_control` (
  `id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fin_cuadres` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cuenta_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `saldo_libro` decimal(14,2) NOT NULL,
  `saldo_real` decimal(14,2) NOT NULL,
  `diferencia` decimal(14,2) NOT NULL,
  `cerrado` tinyint(4) NOT NULL DEFAULT 0,
  `nota` varchar(255) NOT NULL,
  `creado_por` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `creado_por` (`creado_por`),
  KEY `idx_fin_cierre` (`cuenta_id`,`cerrado`,`fecha`),
  CONSTRAINT `fin_cuadres_ibfk_1` FOREIGN KEY (`cuenta_id`) REFERENCES `fin_cuentas` (`id`),
  CONSTRAINT `fin_cuadres_ibfk_2` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fin_cuentas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `tipo` enum('banco','efectivo','otra') NOT NULL,
  `referencia` varchar(120) NOT NULL DEFAULT '',
  `moneda` char(3) NOT NULL DEFAULT 'DOP',
  `fecha_apertura` date NOT NULL,
  `saldo_inicial` decimal(14,2) NOT NULL DEFAULT 0.00,
  `activo` tinyint(4) NOT NULL DEFAULT 1,
  `creado_por` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`),
  KEY `creado_por` (`creado_por`),
  CONSTRAINT `fin_cuentas_ibfk_1` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fin_frecuencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `unidad` enum('dias','meses') NOT NULL,
  `intervalo` int(11) NOT NULL,
  `activo` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fin_movimientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token` varchar(100) NOT NULL,
  `cuenta_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `naturaleza` enum('ingreso','salida','transferencia') NOT NULL,
  `importe` decimal(14,2) NOT NULL,
  `concepto` varchar(255) NOT NULL,
  `tipo_id` int(11) DEFAULT NULL,
  `tercero_id` int(11) DEFAULT NULL,
  `metodo_pago_id` int(11) DEFAULT NULL,
  `referencia` varchar(180) NOT NULL DEFAULT '',
  `sendero_id` int(11) DEFAULT NULL,
  `origen` enum('manual','cobro_sendero','gasto_sendero','obligacion','transferencia') NOT NULL,
  `origen_id` int(11) DEFAULT NULL,
  `obligacion_id` int(11) DEFAULT NULL,
  `reversa_id` int(11) DEFAULT NULL,
  `creado_por` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  UNIQUE KEY `reversa_id` (`reversa_id`),
  KEY `idx_fin_cuenta_fecha` (`cuenta_id`,`fecha`),
  KEY `idx_fin_origen` (`origen`,`origen_id`),
  KEY `tipo_id` (`tipo_id`),
  KEY `tercero_id` (`tercero_id`),
  KEY `obligacion_id` (`obligacion_id`),
  KEY `creado_por` (`creado_por`),
  CONSTRAINT `fin_movimientos_ibfk_1` FOREIGN KEY (`cuenta_id`) REFERENCES `fin_cuentas` (`id`),
  CONSTRAINT `fin_movimientos_ibfk_2` FOREIGN KEY (`tipo_id`) REFERENCES `fin_tipos` (`id`),
  CONSTRAINT `fin_movimientos_ibfk_3` FOREIGN KEY (`tercero_id`) REFERENCES `fin_terceros` (`id`),
  CONSTRAINT `fin_movimientos_ibfk_4` FOREIGN KEY (`obligacion_id`) REFERENCES `fin_obligaciones` (`id`),
  CONSTRAINT `fin_movimientos_ibfk_5` FOREIGN KEY (`reversa_id`) REFERENCES `fin_movimientos` (`id`),
  CONSTRAINT `fin_movimientos_ibfk_6` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fin_obligaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concepto` varchar(200) NOT NULL,
  `naturaleza` enum('ingreso','salida') NOT NULL,
  `tercero_id` int(11) NOT NULL,
  `tipo_id` int(11) NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  `vencimiento` date NOT NULL,
  `programacion_id` int(11) DEFAULT NULL,
  `anulada` tinyint(4) NOT NULL DEFAULT 0,
  `creado_por` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_cuota` (`programacion_id`,`vencimiento`),
  KEY `tercero_id` (`tercero_id`),
  KEY `tipo_id` (`tipo_id`),
  KEY `creado_por` (`creado_por`),
  CONSTRAINT `fin_obligaciones_ibfk_1` FOREIGN KEY (`tercero_id`) REFERENCES `fin_terceros` (`id`),
  CONSTRAINT `fin_obligaciones_ibfk_2` FOREIGN KEY (`tipo_id`) REFERENCES `fin_tipos` (`id`),
  CONSTRAINT `fin_obligaciones_ibfk_3` FOREIGN KEY (`programacion_id`) REFERENCES `fin_programaciones` (`id`),
  CONSTRAINT `fin_obligaciones_ibfk_4` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fin_programaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concepto` varchar(200) NOT NULL,
  `tercero_id` int(11) NOT NULL,
  `tipo_id` int(11) NOT NULL,
  `frecuencia_id` int(11) NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  `inicio` date NOT NULL,
  `fin` date DEFAULT NULL,
  `unidad` enum('dias','meses') NOT NULL,
  `intervalo` int(11) NOT NULL,
  `activo` tinyint(4) NOT NULL DEFAULT 1,
  `creado_por` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tercero_id` (`tercero_id`),
  KEY `tipo_id` (`tipo_id`),
  KEY `frecuencia_id` (`frecuencia_id`),
  KEY `creado_por` (`creado_por`),
  CONSTRAINT `fin_programaciones_ibfk_1` FOREIGN KEY (`tercero_id`) REFERENCES `fin_terceros` (`id`),
  CONSTRAINT `fin_programaciones_ibfk_2` FOREIGN KEY (`tipo_id`) REFERENCES `fin_tipos` (`id`),
  CONSTRAINT `fin_programaciones_ibfk_3` FOREIGN KEY (`frecuencia_id`) REFERENCES `fin_frecuencias` (`id`),
  CONSTRAINT `fin_programaciones_ibfk_4` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fin_solicitudes` (
  `token` char(32) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `resultado` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fin_terceros` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(160) NOT NULL,
  `tipo` enum('empleado','directivo','proveedor','cliente','otro') NOT NULL,
  `referencia` varchar(160) NOT NULL DEFAULT '',
  `activo` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fin_tipos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `naturaleza` enum('ingreso','salida') NOT NULL,
  `activo` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_tipo` (`naturaleza`,`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inicio_galeria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `imagen` varchar(255) NOT NULL,
  `titulo` varchar(160) DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inicio_tarjetas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `icono` varchar(60) NOT NULL DEFAULT 'map',
  `titulo` varchar(160) NOT NULL,
  `descripcion` text NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menores_usuarios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `rango_edad` varchar(20) NOT NULL,
  `es_alergico` tinyint(1) NOT NULL DEFAULT 0,
  `alergias_detalle` varchar(255) DEFAULT NULL,
  `grupo_sanguineo` varchar(10) NOT NULL,
  `enfermedad` varchar(255) NOT NULL,
  `seguro_medico` varchar(255) NOT NULL,
  `experiencia_senderismo` varchar(80) NOT NULL,
  `emergencia_nombre` varchar(150) NOT NULL,
  `emergencia_parentesco` varchar(80) NOT NULL,
  `emergencia_telefono` varchar(30) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_menores_usuarios_usuario` (`usuario_id`),
  CONSTRAINT `fk_menores_usuarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nosotros_equipo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `rol` varchar(160) NOT NULL,
  `imagen` varchar(255) NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nosotros_indicadores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `valor` varchar(40) NOT NULL,
  `etiqueta` varchar(120) NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nosotros_pasos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(10) NOT NULL,
  `titulo` varchar(140) NOT NULL,
  `texto` text NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nosotros_valores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `icono` varchar(60) NOT NULL DEFAULT 'leaf',
  `titulo` varchar(120) NOT NULL,
  `texto` text NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notificacion_tipos` (
  `codigo` varchar(50) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `importante` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pasaporte_niveles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(255) DEFAULT '',
  `icono` varchar(30) DEFAULT 'map',
  `color` varchar(20) DEFAULT '#0f7a3f',
  `min_senderos` int(11) NOT NULL DEFAULT 0,
  `min_km` decimal(10,2) NOT NULL DEFAULT 0.00,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pasaporte_activo` (`activo`,`min_senderos`,`min_km`),
  KEY `idx_pasaporte_orden` (`orden`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_password_resets_token` (`token_hash`),
  KEY `idx_password_resets_usuario` (`usuario_id`),
  KEY `idx_password_resets_expires` (`expires_at`),
  CONSTRAINT `fk_password_resets_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permisos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=51594 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `registro_sendero_menores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `registro_id` int(11) NOT NULL,
  `inversion_id` int(11) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `rango_edad` varchar(20) NOT NULL,
  `es_alergico` tinyint(1) NOT NULL DEFAULT 0,
  `alergias_detalle` varchar(255) DEFAULT NULL,
  `grupo_sanguineo` varchar(10) NOT NULL,
  `enfermedad` varchar(255) NOT NULL,
  `seguro_medico` varchar(255) NOT NULL,
  `experiencia_senderismo` varchar(80) NOT NULL,
  `emergencia_nombre` varchar(150) NOT NULL,
  `emergencia_parentesco` varchar(80) NOT NULL,
  `emergencia_telefono` varchar(30) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_registro_menores_registro` (`registro_id`),
  KEY `idx_registro_menores_inversion` (`inversion_id`),
  CONSTRAINT `fk_registro_menores_registro` FOREIGN KEY (`registro_id`) REFERENCES `registros_senderos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `registros_senderos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sendero_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `detalle_usuario_id` int(11) DEFAULT NULL,
  `manual_nombre` varchar(100) DEFAULT NULL,
  `manual_apellido` varchar(100) DEFAULT NULL,
  `manual_telefono` varchar(20) DEFAULT NULL,
  `manual_email` varchar(100) DEFAULT NULL,
  `inversion_id` int(11) DEFAULT NULL,
  `chaleco_talla_id` int(11) DEFAULT NULL,
  `sendero_punto_encuentro_id` int(11) DEFAULT NULL,
  `comprobante_pago_ruta` varchar(255) DEFAULT NULL,
  `comprobante_pago_nombre` varchar(180) DEFAULT NULL,
  `comprobante_pago_mime` varchar(80) DEFAULT NULL,
  `comprobante_pago_fecha` datetime DEFAULT NULL,
  `estado` enum('registrado','cancelado') NOT NULL DEFAULT 'registrado',
  `registro_origen` enum('publico','admin_manual') NOT NULL DEFAULT 'publico',
  `asistio` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_asistencia` datetime DEFAULT NULL,
  `asistencia_marcada_por` int(11) DEFAULT NULL,
  `asistencia_notas` varchar(255) DEFAULT NULL,
  `consentimiento_aceptado` tinyint(1) NOT NULL DEFAULT 0,
  `rgpd_aceptado` tinyint(1) NOT NULL DEFAULT 0,
  `consentimiento_texto` mediumtext NOT NULL,
  `rgpd_texto` text NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_registros_senderos_usuario_sendero` (`usuario_id`,`sendero_id`),
  KEY `fk_registros_senderos_sendero` (`sendero_id`),
  KEY `fk_registros_senderos_detalle` (`detalle_usuario_id`),
  KEY `fk_registros_senderos_inversion` (`inversion_id`),
  KEY `idx_registros_senderos_asistencia` (`sendero_id`,`asistio`,`estado`),
  KEY `idx_registros_senderos_usuario_asistencia` (`usuario_id`,`asistio`,`fecha_asistencia`),
  KEY `idx_registros_senderos_origen` (`sendero_id`,`registro_origen`,`estado`),
  KEY `idx_registros_senderos_manual_nombre` (`sendero_id`,`registro_origen`,`manual_nombre`,`manual_apellido`),
  KEY `idx_registros_chaleco_talla` (`chaleco_talla_id`),
  KEY `idx_registros_sendero_punto` (`sendero_punto_encuentro_id`),
  CONSTRAINT `fk_registros_chaleco_talla` FOREIGN KEY (`chaleco_talla_id`) REFERENCES `tallas_chalecos_salvavidas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_registros_sendero_punto` FOREIGN KEY (`sendero_punto_encuentro_id`) REFERENCES `sendero_puntos_encuentro` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_registros_senderos_detalle` FOREIGN KEY (`detalle_usuario_id`) REFERENCES `detalles_usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_registros_senderos_inversion` FOREIGN KEY (`inversion_id`) REFERENCES `sendero_inversiones` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_registros_senderos_sendero` FOREIGN KEY (`sendero_id`) REFERENCES `senderos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_registros_senderos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=117 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sendero_anotaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sendero_id` int(11) NOT NULL,
  `anotacion_id` int(11) NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sendero_anotacion` (`sendero_id`,`anotacion_id`),
  KEY `fk_sendero_anotaciones_anotacion` (`anotacion_id`),
  CONSTRAINT `fk_sendero_anotaciones_anotacion` FOREIGN KEY (`anotacion_id`) REFERENCES `anotaciones_importantes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_sendero_anotaciones_sendero` FOREIGN KEY (`sendero_id`) REFERENCES `senderos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1196 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=729 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=130 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sendero_inversion_incluye` (
  `inversion_id` int(11) NOT NULL,
  `incluye_id` int(11) NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`inversion_id`,`incluye_id`),
  KEY `idx_sendero_inversion_incluye_item` (`incluye_id`),
  CONSTRAINT `fk_inversion_incluye_catalogo` FOREIGN KEY (`incluye_id`) REFERENCES `elementos_incluidos` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_inversion_incluye_inversion` FOREIGN KEY (`inversion_id`) REFERENCES `sendero_inversiones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sendero_inversiones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sendero_id` int(11) NOT NULL,
  `es_menor` tinyint(1) NOT NULL DEFAULT 0,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `monto_menor` decimal(10,2) DEFAULT NULL,
  `fecha_limite_pago` date DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 1,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sendero_inversiones_sendero` (`sendero_id`),
  CONSTRAINT `fk_sendero_inversiones_sendero` FOREIGN KEY (`sendero_id`) REFERENCES `senderos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=178 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=413 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `incluye_chaleco_salvavidas` tinyint(1) NOT NULL DEFAULT 0,
  `menores_misma_inversion` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_senderos_slug` (`slug`),
  KEY `fk_senderos_nivel_dificultad` (`nivel_dificultad_id`),
  KEY `fk_senderos_tipo_camino_vehiculo` (`tipo_camino_vehiculo_id`),
  CONSTRAINT `fk_senderos_nivel_dificultad` FOREIGN KEY (`nivel_dificultad_id`) REFERENCES `niveles_dificultad` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_senderos_tipo_camino_vehiculo` FOREIGN KEY (`tipo_camino_vehiculo_id`) REFERENCES `tipos_camino_vehiculo` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tallas_chalecos_salvavidas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_talla_chaleco_nombre` (`nombre`),
  KEY `idx_tallas_chalecos_orden` (`activo`,`orden`,`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipos_terreno` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario_credito_movimientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `credito_id` int(11) NOT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `sendero_id` int(11) DEFAULT NULL,
  `tipo` enum('creacion','aplicacion','ajuste','anulacion') NOT NULL,
  `monto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `nota` varchar(255) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_credito_movimientos_credito` (`credito_id`,`tipo`),
  KEY `idx_credito_movimientos_registro` (`registro_id`),
  KEY `fk_credito_movimientos_sendero` (`sendero_id`),
  CONSTRAINT `fk_credito_movimientos_credito` FOREIGN KEY (`credito_id`) REFERENCES `usuario_creditos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_credito_movimientos_registro` FOREIGN KEY (`registro_id`) REFERENCES `registros_senderos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_credito_movimientos_sendero` FOREIGN KEY (`sendero_id`) REFERENCES `senderos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario_creditos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `registro_origen_id` int(11) DEFAULT NULL,
  `sendero_origen_id` int(11) DEFAULT NULL,
  `monto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saldo_disponible` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` enum('activo','usado','vencido','anulado') NOT NULL DEFAULT 'activo',
  `motivo` varchar(255) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_usuario_creditos_usuario` (`usuario_id`,`estado`),
  KEY `idx_usuario_creditos_registro` (`registro_origen_id`),
  KEY `idx_usuario_creditos_sendero` (`sendero_origen_id`),
  CONSTRAINT `fk_usuario_creditos_registro` FOREIGN KEY (`registro_origen_id`) REFERENCES `registros_senderos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_usuario_creditos_sendero` FOREIGN KEY (`sendero_origen_id`) REFERENCES `senderos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_usuario_creditos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario_notificacion_lecturas` (
  `usuario_id` int(11) NOT NULL,
  `clave` varchar(120) NOT NULL,
  `leida_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`usuario_id`,`clave`),
  CONSTRAINT `fk_notificacion_lectura_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=CURRENT_USER PROCEDURE `sp_iniciar_sesion`(IN `p_user` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci, OUT `p_mensaje` VARCHAR(600) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci, OUT `p_codigo` INT)
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
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=CURRENT_USER PROCEDURE `sp_registrar_usuario`(IN `p_nombre` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci, IN `p_apellido` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci, IN `p_user` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci, IN `p_email` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci, IN `p_password_hash` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci, OUT `p_mensaje` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci, OUT `p_codigo` INT)
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
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=CURRENT_USER PROCEDURE `sp_roles_eliminar`(IN `p_id` INT, OUT `p_mensaje` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci, OUT `p_codigo` INT)
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
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=CURRENT_USER PROCEDURE `sp_roles_guardar`(IN `p_id` INT, IN `p_nombre` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci, IN `p_descripcion` VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci, OUT `p_mensaje` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci, OUT `p_codigo` INT)
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
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=CURRENT_USER PROCEDURE `sp_roles_listar`()
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
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=CURRENT_USER PROCEDURE `sp_usuarios_cambiar_estado`(IN `p_id` INT, IN `p_estado` TINYINT, OUT `p_mensaje` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci, OUT `p_codigo` INT)
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
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=CURRENT_USER PROCEDURE `sp_usuarios_eliminar`(IN `p_id` INT, OUT `p_mensaje` VARCHAR(255), OUT `p_codigo` INT)
proc: BEGIN
    DECLARE v_estado TINYINT;
    DECLARE v_registros INT DEFAULT 0;

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

    SELECT estado INTO v_estado
    FROM usuarios
    WHERE id = p_id;

    IF v_estado = 1 THEN
        SET p_codigo = 12;
        SET p_mensaje = 'No se puede eliminar un usuario activo. Debe inactivarlo primero.';
        LEAVE proc;
    END IF;

    SELECT COUNT(*) INTO v_registros
    FROM registros_senderos
    WHERE usuario_id = p_id;

    IF v_registros > 0 THEN
        SET p_codigo = 13;
        SET p_mensaje = 'No se puede eliminar un usuario con registros enlazados a senderos. Debe inactivarlo.';
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
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=CURRENT_USER PROCEDURE `sp_usuarios_guardar`(IN `p_id` INT, IN `p_nombre` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci, IN `p_apellido` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci, IN `p_user` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci, IN `p_email` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci, IN `p_password_hash` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci, IN `p_rol_id` INT, OUT `p_mensaje` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci, OUT `p_codigo` INT)
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
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=CURRENT_USER PROCEDURE `sp_usuarios_listar`()
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
