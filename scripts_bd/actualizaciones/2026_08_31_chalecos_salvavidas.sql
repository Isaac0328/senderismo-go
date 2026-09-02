-- Configuracion y seleccion de tallas de chalecos salvavidas.
-- Ejecutar en produccion antes de publicar los archivos de esta funcionalidad.

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

SET @db_name := DATABASE();

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
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
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
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
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
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
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
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
