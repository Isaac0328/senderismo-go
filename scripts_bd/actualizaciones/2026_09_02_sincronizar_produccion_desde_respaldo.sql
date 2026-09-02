-- Sincronizacion de estructura local hacia produccion.
-- Comparado contra el respaldo productivo generado el 2026-09-02 17:27:17 UTC.
-- SHA256 del respaldo: 5C7DCED1FA0DD1AEF2EA0BF264CADBB6AAE043AAC7ADFA47AC19625AEFE08F88
--
-- Alcance:
--   1. Modulo de encuestas.
--   2. Catalogo y seleccion de tallas de chalecos salvavidas.
--   3. Comprobantes de pago en registros de senderos.
--   4. Estado financiero Exento.
--   5. Permiso administrativo para encuestas.
--
-- No copia datos de encuestas ni respuestas del ambiente local.
-- Ejecutar despues de respaldar la base productiva completa.

SET NAMES utf8mb4;
SET @db_name := DATABASE();

-- =========================================================
-- 1. ENCUESTAS
-- =========================================================

CREATE TABLE IF NOT EXISTS encuestas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(180) NOT NULL,
    descripcion TEXT NULL,
    sendero_id INT NULL,
    destinatarios ENUM('sendero_asistentes','sendero_registrados','todos_usuarios') NOT NULL DEFAULT 'sendero_asistentes',
    estado ENUM('borrador','enviada','cancelada','cerrada') NOT NULL DEFAULT 'borrador',
    anonima TINYINT(1) NOT NULL DEFAULT 0,
    permite_editar_respuesta TINYINT(1) NOT NULL DEFAULT 0,
    fecha_envio DATETIME NULL,
    fecha_cierre DATETIME NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_por INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_encuestas_sendero (sendero_id),
    KEY idx_encuestas_estado (estado, activo),
    CONSTRAINT fk_encuestas_sendero FOREIGN KEY (sendero_id) REFERENCES senderos(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_encuestas_creador FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS encuesta_preguntas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    encuesta_id INT NOT NULL,
    pregunta VARCHAR(255) NOT NULL,
    ayuda VARCHAR(255) NULL,
    tipo ENUM('texto','textarea','radio','checkbox','select','escala','numero') NOT NULL DEFAULT 'texto',
    requerido TINYINT(1) NOT NULL DEFAULT 1,
    puntaje_max DECIMAL(8,2) NOT NULL DEFAULT 0,
    orden INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_encuesta_preguntas_encuesta (encuesta_id, orden),
    CONSTRAINT fk_encuesta_preguntas_encuesta FOREIGN KEY (encuesta_id) REFERENCES encuestas(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS encuesta_opciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pregunta_id INT NOT NULL,
    texto VARCHAR(255) NOT NULL,
    valor VARCHAR(120) NULL,
    puntuacion DECIMAL(8,2) NOT NULL DEFAULT 0,
    orden INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_encuesta_opciones_pregunta (pregunta_id, orden),
    CONSTRAINT fk_encuesta_opciones_pregunta FOREIGN KEY (pregunta_id) REFERENCES encuesta_preguntas(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS encuesta_envios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    encuesta_id INT NOT NULL,
    usuario_id INT NOT NULL,
    sendero_id INT NULL,
    estado ENUM('pendiente','respondida','cancelada') NOT NULL DEFAULT 'pendiente',
    enviado_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    respondido_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_encuesta_envio_usuario (encuesta_id, usuario_id),
    KEY idx_encuesta_envios_usuario (usuario_id, estado),
    KEY idx_encuesta_envios_sendero (sendero_id),
    CONSTRAINT fk_encuesta_envios_encuesta FOREIGN KEY (encuesta_id) REFERENCES encuestas(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_encuesta_envios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_encuesta_envios_sendero FOREIGN KEY (sendero_id) REFERENCES senderos(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS encuesta_respuestas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    envio_id INT NOT NULL,
    pregunta_id INT NOT NULL,
    opcion_id INT NULL,
    respuesta_texto TEXT NULL,
    respuesta_numero DECIMAL(10,2) NULL,
    puntuacion DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_encuesta_respuestas_envio (envio_id),
    KEY idx_encuesta_respuestas_pregunta (pregunta_id),
    CONSTRAINT fk_encuesta_respuestas_envio FOREIGN KEY (envio_id) REFERENCES encuesta_envios(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_encuesta_respuestas_pregunta FOREIGN KEY (pregunta_id) REFERENCES encuesta_preguntas(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_encuesta_respuestas_opcion FOREIGN KEY (opcion_id) REFERENCES encuesta_opciones(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =========================================================
-- 2. CHALECOS SALVAVIDAS
-- =========================================================

CREATE TABLE IF NOT EXISTS tallas_chalecos_salvavidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255) NULL,
    orden INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_talla_chaleco_nombre (nombre),
    KEY idx_tallas_chalecos_orden (activo, orden, nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO tallas_chalecos_salvavidas (nombre, descripcion, orden, activo) VALUES
    ('Infantil', 'Talla para participantes infantiles.', 10, 1),
    ('XS', 'Extra pequena.', 20, 1),
    ('S', 'Pequena.', 30, 1),
    ('M', 'Mediana.', 40, 1),
    ('L', 'Grande.', 50, 1),
    ('XL', 'Extra grande.', 60, 1),
    ('XXL', 'Doble extra grande.', 70, 1);

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'senderos'
      AND COLUMN_NAME = 'incluye_chaleco_salvavidas'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE senderos ADD COLUMN incluye_chaleco_salvavidas TINYINT(1) NOT NULL DEFAULT 0 AFTER activo',
    'SELECT ''incluye_chaleco_salvavidas ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'registros_senderos'
      AND COLUMN_NAME = 'chaleco_talla_id'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE registros_senderos ADD COLUMN chaleco_talla_id INT NULL AFTER inversion_id',
    'SELECT ''chaleco_talla_id ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'registros_senderos'
      AND INDEX_NAME = 'idx_registros_chaleco_talla'
);
SET @sql := IF(
    @index_exists = 0,
    'ALTER TABLE registros_senderos ADD KEY idx_registros_chaleco_talla (chaleco_talla_id)',
    'SELECT ''idx_registros_chaleco_talla ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @db_name
      AND TABLE_NAME = 'registros_senderos'
      AND CONSTRAINT_NAME = 'fk_registros_chaleco_talla'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE registros_senderos ADD CONSTRAINT fk_registros_chaleco_talla FOREIGN KEY (chaleco_talla_id) REFERENCES tallas_chalecos_salvavidas(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT ''fk_registros_chaleco_talla ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 3. COMPROBANTES DE PAGO
-- =========================================================

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'registros_senderos'
      AND COLUMN_NAME = 'comprobante_pago_ruta'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE registros_senderos ADD COLUMN comprobante_pago_ruta VARCHAR(255) NULL AFTER chaleco_talla_id',
    'SELECT ''comprobante_pago_ruta ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'registros_senderos'
      AND COLUMN_NAME = 'comprobante_pago_nombre'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE registros_senderos ADD COLUMN comprobante_pago_nombre VARCHAR(180) NULL AFTER comprobante_pago_ruta',
    'SELECT ''comprobante_pago_nombre ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'registros_senderos'
      AND COLUMN_NAME = 'comprobante_pago_mime'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE registros_senderos ADD COLUMN comprobante_pago_mime VARCHAR(80) NULL AFTER comprobante_pago_nombre',
    'SELECT ''comprobante_pago_mime ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'registros_senderos'
      AND COLUMN_NAME = 'comprobante_pago_fecha'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE registros_senderos ADD COLUMN comprobante_pago_fecha DATETIME NULL AFTER comprobante_pago_mime',
    'SELECT ''comprobante_pago_fecha ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 4. ESTADO FINANCIERO EXENTO
-- =========================================================

ALTER TABLE contabilidad_registro_pagos
    MODIFY COLUMN estado_financiero ENUM(
        'pendiente',
        'pagado',
        'parcial',
        'credito_aplicado',
        'descuento',
        'deuda',
        'cortesia',
        'exento',
        'no_asistio_sin_pago'
    ) NOT NULL DEFAULT 'pendiente';

-- =========================================================
-- 5. PERMISOS
-- =========================================================

INSERT INTO permisos (nombre, descripcion)
VALUES (
    'operaciones.encuestas',
    'Senderos y logistica - Crear, enviar y analizar encuestas de satisfaccion.'
)
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

INSERT INTO rol_permiso (rol_id, permiso_id)
SELECT 1, id
FROM permisos
WHERE nombre = 'operaciones.encuestas'
ON DUPLICATE KEY UPDATE permiso_id = VALUES(permiso_id);

UPDATE permisos
SET descripcion = 'Contabilidad y pagos - Registrar pagos, creditos, cortesias, exentos y saldos.'
WHERE nombre = 'finanzas.ingresos_sendero';

-- =========================================================
-- VERIFICACION FINAL
-- Debe devolver 6 tablas, 6 columnas, estado Exento y 1 permiso.
-- =========================================================

SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME IN (
      'encuestas',
      'encuesta_preguntas',
      'encuesta_opciones',
      'encuesta_envios',
      'encuesta_respuestas',
      'tallas_chalecos_salvavidas'
  )
ORDER BY TABLE_NAME;

SELECT TABLE_NAME, COLUMN_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND (
      (TABLE_NAME = 'senderos' AND COLUMN_NAME = 'incluye_chaleco_salvavidas')
      OR
      (TABLE_NAME = 'registros_senderos' AND COLUMN_NAME IN (
          'chaleco_talla_id',
          'comprobante_pago_ruta',
          'comprobante_pago_nombre',
          'comprobante_pago_mime',
          'comprobante_pago_fecha'
      ))
  )
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'contabilidad_registro_pagos'
  AND COLUMN_NAME = 'estado_financiero';

SELECT p.id, p.nombre, rp.rol_id
FROM permisos p
LEFT JOIN rol_permiso rp
  ON rp.permiso_id = p.id
 AND rp.rol_id = 1
WHERE p.nombre = 'operaciones.encuestas';
