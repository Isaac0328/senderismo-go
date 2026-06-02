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

INSERT IGNORE INTO `puntos_encuentro` (`nombre`, `direccion_referencia`, `url_mapa`, `activo`)
SELECT DISTINCT
  TRIM(`nombre_punto`) AS `nombre`,
  NULLIF(TRIM(COALESCE(`direccion_referencia`, '')), '') AS `direccion_referencia`,
  NULLIF(TRIM(COALESCE(`url_mapa`, '')), '') AS `url_mapa`,
  1 AS `activo`
FROM `sendero_puntos_encuentro`
WHERE TRIM(`nombre_punto`) <> '';

DROP PROCEDURE IF EXISTS `sg_add_punto_encuentro_id`;
DELIMITER //
CREATE PROCEDURE `sg_add_punto_encuentro_id`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sendero_puntos_encuentro'
      AND COLUMN_NAME = 'punto_encuentro_id'
  ) THEN
    ALTER TABLE `sendero_puntos_encuentro`
      ADD COLUMN `punto_encuentro_id` int(11) DEFAULT NULL AFTER `sendero_id`;
  END IF;
END//
DELIMITER ;
CALL `sg_add_punto_encuentro_id`();
DROP PROCEDURE IF EXISTS `sg_add_punto_encuentro_id`;

UPDATE `sendero_puntos_encuentro` spe
INNER JOIN `puntos_encuentro` pe ON pe.`nombre` = spe.`nombre_punto`
SET spe.`punto_encuentro_id` = pe.`id`
WHERE spe.`punto_encuentro_id` IS NULL;

DROP PROCEDURE IF EXISTS `sg_add_fk_punto_encuentro`;
DELIMITER //
CREATE PROCEDURE `sg_add_fk_punto_encuentro`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sendero_puntos_encuentro'
      AND CONSTRAINT_NAME = 'fk_sendero_puntos_catalogo'
  ) THEN
    ALTER TABLE `sendero_puntos_encuentro`
      ADD KEY `idx_sendero_puntos_catalogo` (`punto_encuentro_id`),
      ADD CONSTRAINT `fk_sendero_puntos_catalogo`
        FOREIGN KEY (`punto_encuentro_id`) REFERENCES `puntos_encuentro` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;
  END IF;
END//
DELIMITER ;
CALL `sg_add_fk_punto_encuentro`();
DROP PROCEDURE IF EXISTS `sg_add_fk_punto_encuentro`;
