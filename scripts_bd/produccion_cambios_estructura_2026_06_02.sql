-- Senderismo Go - Cambios de estructura para produccion
-- Fecha: 2026-06-02
-- Ejecutar en phpMyAdmin sobre la base de datos productiva.

CREATE TABLE IF NOT EXISTS `sendero_inversiones` (
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
  CONSTRAINT `fk_sendero_inversiones_sendero`
    FOREIGN KEY (`sendero_id`) REFERENCES `senderos` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `sendero_inversion_incluye` (
  `inversion_id` int(11) NOT NULL,
  `incluye_id` int(11) NOT NULL,
  PRIMARY KEY (`inversion_id`, `incluye_id`),
  KEY `idx_sendero_inversion_incluye_item` (`incluye_id`),
  CONSTRAINT `fk_inversion_incluye_inversion`
    FOREIGN KEY (`inversion_id`) REFERENCES `sendero_inversiones` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_inversion_incluye_catalogo`
    FOREIGN KEY (`incluye_id`) REFERENCES `elementos_incluidos` (`id`)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `puntos_encuentro` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `direccion_referencia` varchar(255) DEFAULT NULL,
  `url_mapa` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_puntos_encuentro_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP PROCEDURE IF EXISTS `sg_cambios_estructura_2026_06_02`;
DELIMITER //
CREATE PROCEDURE `sg_cambios_estructura_2026_06_02`()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'niveles_dificultad'
      AND COLUMN_NAME = 'nivel_numero'
  ) THEN
    ALTER TABLE `niveles_dificultad`
      ADD COLUMN `nivel_numero` tinyint unsigned NOT NULL DEFAULT 50 AFTER `descripcion`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'registros_senderos'
      AND COLUMN_NAME = 'inversion_id'
  ) THEN
    ALTER TABLE `registros_senderos`
      ADD COLUMN `inversion_id` int(11) DEFAULT NULL AFTER `detalle_usuario_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'registros_senderos'
      AND INDEX_NAME = 'fk_registros_senderos_inversion'
  ) THEN
    ALTER TABLE `registros_senderos`
      ADD KEY `fk_registros_senderos_inversion` (`inversion_id`);
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'registros_senderos'
      AND CONSTRAINT_NAME = 'fk_registros_senderos_inversion'
  ) THEN
    ALTER TABLE `registros_senderos`
      ADD CONSTRAINT `fk_registros_senderos_inversion`
      FOREIGN KEY (`inversion_id`) REFERENCES `sendero_inversiones` (`id`)
      ON DELETE SET NULL ON UPDATE CASCADE;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sendero_puntos_encuentro'
      AND COLUMN_NAME = 'punto_encuentro_id'
  ) THEN
    ALTER TABLE `sendero_puntos_encuentro`
      ADD COLUMN `punto_encuentro_id` int(11) DEFAULT NULL AFTER `sendero_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sendero_puntos_encuentro'
      AND INDEX_NAME = 'idx_sendero_puntos_catalogo'
  ) THEN
    ALTER TABLE `sendero_puntos_encuentro`
      ADD KEY `idx_sendero_puntos_catalogo` (`punto_encuentro_id`);
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sendero_puntos_encuentro'
      AND CONSTRAINT_NAME = 'fk_sendero_puntos_catalogo'
  ) THEN
    ALTER TABLE `sendero_puntos_encuentro`
      ADD CONSTRAINT `fk_sendero_puntos_catalogo`
      FOREIGN KEY (`punto_encuentro_id`) REFERENCES `puntos_encuentro` (`id`)
      ON DELETE SET NULL ON UPDATE CASCADE;
  END IF;
END//
DELIMITER ;

CALL `sg_cambios_estructura_2026_06_02`();
DROP PROCEDURE IF EXISTS `sg_cambios_estructura_2026_06_02`;

INSERT INTO `sendero_inversiones` (`sendero_id`, `nombre`, `descripcion`, `monto`, `fecha_limite_pago`, `orden`, `activo`)
SELECT
  s.`id`,
  'Inversion general',
  'Plan creado desde la inversion anterior del sendero.',
  COALESCE(s.`inversion_total`, 0.00),
  s.`fecha_limite_pago`,
  1,
  1
FROM `senderos` s
WHERE NOT EXISTS (
  SELECT 1
  FROM `sendero_inversiones` si
  WHERE si.`sendero_id` = s.`id`
);

INSERT IGNORE INTO `sendero_inversion_incluye` (`inversion_id`, `incluye_id`)
SELECT si.`id`, sei.`incluye_id`
FROM `sendero_inversiones` si
INNER JOIN `sendero_elementos_incluidos` sei ON sei.`sendero_id` = si.`sendero_id`
WHERE si.`orden` = 1;

INSERT IGNORE INTO `puntos_encuentro` (`nombre`, `direccion_referencia`, `url_mapa`, `activo`)
SELECT DISTINCT
  TRIM(`nombre_punto`) AS `nombre`,
  NULLIF(TRIM(COALESCE(`direccion_referencia`, '')), '') AS `direccion_referencia`,
  NULLIF(TRIM(COALESCE(`url_mapa`, '')), '') AS `url_mapa`,
  1 AS `activo`
FROM `sendero_puntos_encuentro`
WHERE TRIM(`nombre_punto`) <> '';

UPDATE `sendero_puntos_encuentro` spe
INNER JOIN `puntos_encuentro` pe ON pe.`nombre` = spe.`nombre_punto`
SET spe.`punto_encuentro_id` = pe.`id`
WHERE spe.`punto_encuentro_id` IS NULL;
