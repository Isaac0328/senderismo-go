USE `sgbd`;

CREATE TABLE IF NOT EXISTS `configuracion_contacto` (
  `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `hero_imagen` VARCHAR(255) NOT NULL DEFAULT 'imagenes/paisajes/hero.jpg',
  `titulo` VARCHAR(160) NOT NULL,
  `subtitulo` VARCHAR(255) NOT NULL,
  `hero_boton_texto` VARCHAR(80) NOT NULL DEFAULT 'Escribir mensaje',
  `hero_whatsapp_texto` VARCHAR(80) NOT NULL DEFAULT 'WhatsApp',
  `horario` VARCHAR(160) NOT NULL,
  `ubicacion` VARCHAR(160) NOT NULL,
  `telefono` VARCHAR(40) NOT NULL,
  `whatsapp` VARCHAR(40) NOT NULL,
  `email` VARCHAR(160) NOT NULL,
  `instagram` VARCHAR(80) NOT NULL,
  `instagram_url` VARCHAR(255) NOT NULL,
  `seccion_kicker` VARCHAR(80) NOT NULL DEFAULT 'Atencion personalizada',
  `seccion_titulo` VARCHAR(160) NOT NULL DEFAULT 'Estamos listos para orientarte.',
  `texto_formulario` TEXT NOT NULL,
  `nota_contacto` TEXT NULL,
  `form_kicker` VARCHAR(80) NOT NULL DEFAULT 'Mensaje rapido',
  `form_titulo` VARCHAR(120) NOT NULL DEFAULT 'Escribenos',
  `form_subtitulo` VARCHAR(255) NOT NULL DEFAULT 'Completa estos datos y nos pondremos en contacto contigo.',
  `form_privacidad` VARCHAR(255) NOT NULL DEFAULT 'Usaremos tu informacion solo para responder esta solicitud.',
  `boton_formulario` VARCHAR(80) NOT NULL DEFAULT 'Enviar mensaje',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELIMITER $$
CREATE PROCEDURE `sg_contacto_add_column_if_missing`(
  IN p_table VARCHAR(64),
  IN p_column VARCHAR(64),
  IN p_definition TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND COLUMN_NAME = p_column
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN ', p_definition);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END$$
DELIMITER ;

CALL `sg_contacto_add_column_if_missing`('configuracion_contacto', 'hero_boton_texto', "`hero_boton_texto` VARCHAR(80) NOT NULL DEFAULT 'Escribir mensaje'");
CALL `sg_contacto_add_column_if_missing`('configuracion_contacto', 'hero_whatsapp_texto', "`hero_whatsapp_texto` VARCHAR(80) NOT NULL DEFAULT 'WhatsApp'");
CALL `sg_contacto_add_column_if_missing`('configuracion_contacto', 'seccion_kicker', "`seccion_kicker` VARCHAR(80) NOT NULL DEFAULT 'Atencion personalizada'");
CALL `sg_contacto_add_column_if_missing`('configuracion_contacto', 'seccion_titulo', "`seccion_titulo` VARCHAR(160) NOT NULL DEFAULT 'Estamos listos para orientarte.'");
CALL `sg_contacto_add_column_if_missing`('configuracion_contacto', 'nota_contacto', "`nota_contacto` TEXT NULL");
CALL `sg_contacto_add_column_if_missing`('configuracion_contacto', 'form_kicker', "`form_kicker` VARCHAR(80) NOT NULL DEFAULT 'Mensaje rapido'");
CALL `sg_contacto_add_column_if_missing`('configuracion_contacto', 'form_titulo', "`form_titulo` VARCHAR(120) NOT NULL DEFAULT 'Escribenos'");
CALL `sg_contacto_add_column_if_missing`('configuracion_contacto', 'form_subtitulo', "`form_subtitulo` VARCHAR(255) NOT NULL DEFAULT 'Completa estos datos y nos pondremos en contacto contigo.'");
CALL `sg_contacto_add_column_if_missing`('configuracion_contacto', 'form_privacidad', "`form_privacidad` VARCHAR(255) NOT NULL DEFAULT 'Usaremos tu informacion solo para responder esta solicitud.'");
CALL `sg_contacto_add_column_if_missing`('configuracion_contacto', 'boton_formulario', "`boton_formulario` VARCHAR(80) NOT NULL DEFAULT 'Enviar mensaje'");

DROP PROCEDURE `sg_contacto_add_column_if_missing`;

CREATE TABLE IF NOT EXISTS `contacto_bloques` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `grupo` VARCHAR(30) NOT NULL,
  `icono` VARCHAR(60) NOT NULL DEFAULT 'circle',
  `titulo` VARCHAR(120) NOT NULL,
  `texto` VARCHAR(255) NOT NULL,
  `url` VARCHAR(255) DEFAULT NULL,
  `orden` INT NOT NULL DEFAULT 0,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contacto_bloques_grupo` (`grupo`, `activo`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `contacto_bloques` (`grupo`, `icono`, `titulo`, `texto`, `url`, `orden`, `activo`)
SELECT 'resumen', 'clock', 'Horario de respuesta', 'Lunes a viernes, 8:00 a.m. - 6:00 p.m.', '', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM `contacto_bloques` WHERE `grupo` = 'resumen' AND `titulo` = 'Horario de respuesta');

INSERT INTO `contacto_bloques` (`grupo`, `icono`, `titulo`, `texto`, `url`, `orden`, `activo`)
SELECT 'resumen', 'map-pin', 'Ubicacion', 'Republica Dominicana', '', 2, 1
WHERE NOT EXISTS (SELECT 1 FROM `contacto_bloques` WHERE `grupo` = 'resumen' AND `titulo` = 'Ubicacion');

INSERT INTO `contacto_bloques` (`grupo`, `icono`, `titulo`, `texto`, `url`, `orden`, `activo`)
SELECT 'resumen', 'mail', 'Correo', 'info@senderismogo.com', '', 3, 1
WHERE NOT EXISTS (SELECT 1 FROM `contacto_bloques` WHERE `grupo` = 'resumen' AND `titulo` = 'Correo');

INSERT INTO `contacto_bloques` (`grupo`, `icono`, `titulo`, `texto`, `url`, `orden`, `activo`)
SELECT 'canal', 'phone', 'WhatsApp directo', '+1 (849) 472-1200', 'https://wa.me/18494721200?text=Hola%20Senderismo%20Go,%20quiero%20coordinar%20una%20ruta.', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM `contacto_bloques` WHERE `grupo` = 'canal' AND `titulo` = 'WhatsApp directo');

INSERT INTO `contacto_bloques` (`grupo`, `icono`, `titulo`, `texto`, `url`, `orden`, `activo`)
SELECT 'canal', 'mail', 'Correo electronico', 'info@senderismogo.com', 'mailto:info@senderismogo.com', 2, 1
WHERE NOT EXISTS (SELECT 1 FROM `contacto_bloques` WHERE `grupo` = 'canal' AND `titulo` = 'Correo electronico');

INSERT INTO `contacto_bloques` (`grupo`, `icono`, `titulo`, `texto`, `url`, `orden`, `activo`)
SELECT 'canal', 'instagram', 'Instagram', '@senderismogo', 'https://www.instagram.com/senderismogo', 3, 1
WHERE NOT EXISTS (SELECT 1 FROM `contacto_bloques` WHERE `grupo` = 'canal' AND `titulo` = 'Instagram');
