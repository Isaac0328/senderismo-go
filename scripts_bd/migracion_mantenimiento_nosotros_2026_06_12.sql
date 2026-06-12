USE `sgbd`;

CREATE TABLE IF NOT EXISTS `configuracion_nosotros` (
  `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `hero_imagen` VARCHAR(255) NOT NULL DEFAULT 'imagenes/paisajes/hero.jpg',
  `hero_kicker` VARCHAR(80) NOT NULL DEFAULT 'Nosotros',
  `hero_titulo` VARCHAR(180) NOT NULL,
  `hero_subtitulo` TEXT NOT NULL,
  `boton_principal_texto` VARCHAR(80) NOT NULL DEFAULT 'Ver senderos',
  `boton_principal_url` VARCHAR(255) NOT NULL DEFAULT 'pantallas/senderos.php',
  `boton_secundario_texto` VARCHAR(80) NOT NULL DEFAULT 'Coordinar una ruta',
  `boton_secundario_url` VARCHAR(255) NOT NULL DEFAULT 'pantallas/contacto.php',
  `historia_imagen` VARCHAR(255) NOT NULL DEFAULT 'imagenes/paisajes/img4.jpg',
  `historia_badge_titulo` VARCHAR(80) NOT NULL DEFAULT 'Desde 2015',
  `historia_badge_texto` VARCHAR(120) NOT NULL DEFAULT 'Creando comunidad outdoor',
  `historia_kicker` VARCHAR(80) NOT NULL DEFAULT 'Nuestra historia',
  `historia_titulo` VARCHAR(180) NOT NULL,
  `historia_texto_1` TEXT NOT NULL,
  `historia_texto_2` TEXT NOT NULL,
  `valores_kicker` VARCHAR(80) NOT NULL DEFAULT 'Nuestro compromiso',
  `valores_titulo` VARCHAR(180) NOT NULL,
  `valores_texto` TEXT NOT NULL,
  `proceso_kicker` VARCHAR(80) NOT NULL DEFAULT 'Como trabajamos',
  `proceso_titulo` VARCHAR(180) NOT NULL,
  `proceso_texto` TEXT NOT NULL,
  `equipo_kicker` VARCHAR(80) NOT NULL DEFAULT 'Equipo',
  `equipo_titulo` VARCHAR(180) NOT NULL,
  `equipo_texto` TEXT NOT NULL,
  `cta_kicker` VARCHAR(80) NOT NULL DEFAULT 'Proxima aventura',
  `cta_titulo` VARCHAR(180) NOT NULL,
  `cta_texto` TEXT NOT NULL,
  `cta_boton_principal_texto` VARCHAR(80) NOT NULL DEFAULT 'Ver proximos',
  `cta_boton_principal_url` VARCHAR(255) NOT NULL DEFAULT 'pantallas/senderos.php',
  `cta_boton_secundario_texto` VARCHAR(80) NOT NULL DEFAULT 'Contactar',
  `cta_boton_secundario_url` VARCHAR(255) NOT NULL DEFAULT 'pantallas/contacto.php',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `nosotros_indicadores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `valor` VARCHAR(40) NOT NULL,
  `etiqueta` VARCHAR(120) NOT NULL,
  `orden` INT NOT NULL DEFAULT 0,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `nosotros_valores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `icono` VARCHAR(60) NOT NULL DEFAULT 'leaf',
  `titulo` VARCHAR(120) NOT NULL,
  `texto` TEXT NOT NULL,
  `orden` INT NOT NULL DEFAULT 0,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `nosotros_pasos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `numero` VARCHAR(10) NOT NULL,
  `titulo` VARCHAR(140) NOT NULL,
  `texto` TEXT NOT NULL,
  `orden` INT NOT NULL DEFAULT 0,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `nosotros_equipo` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(120) NOT NULL,
  `rol` VARCHAR(160) NOT NULL,
  `imagen` VARCHAR(255) NOT NULL,
  `orden` INT NOT NULL DEFAULT 0,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
