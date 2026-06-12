USE `sgbd`;

CREATE TABLE IF NOT EXISTS `registro_sendero_menores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `registro_id` INT NOT NULL,
  `inversion_id` INT DEFAULT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `apellido` VARCHAR(100) NOT NULL,
  `telefono` VARCHAR(30) DEFAULT NULL,
  `rango_edad` VARCHAR(20) NOT NULL,
  `es_alergico` TINYINT(1) NOT NULL DEFAULT 0,
  `alergias_detalle` VARCHAR(255) DEFAULT NULL,
  `grupo_sanguineo` VARCHAR(10) NOT NULL,
  `enfermedad` VARCHAR(255) NOT NULL,
  `seguro_medico` VARCHAR(255) NOT NULL,
  `experiencia_senderismo` VARCHAR(80) NOT NULL,
  `emergencia_nombre` VARCHAR(150) NOT NULL,
  `emergencia_parentesco` VARCHAR(80) NOT NULL,
  `emergencia_telefono` VARCHAR(30) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_registro_menores_registro` (`registro_id`),
  KEY `idx_registro_menores_inversion` (`inversion_id`),
  CONSTRAINT `fk_registro_menores_registro`
    FOREIGN KEY (`registro_id`) REFERENCES `registros_senderos` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELIMITER $$
CREATE PROCEDURE `sg_add_inversion_menores_if_missing`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'registro_sendero_menores'
      AND COLUMN_NAME = 'inversion_id'
  ) THEN
    ALTER TABLE `registro_sendero_menores`
      ADD COLUMN `inversion_id` INT DEFAULT NULL AFTER `registro_id`,
      ADD KEY `idx_registro_menores_inversion` (`inversion_id`);
  END IF;
END$$
DELIMITER ;

CALL `sg_add_inversion_menores_if_missing`();
DROP PROCEDURE `sg_add_inversion_menores_if_missing`;
