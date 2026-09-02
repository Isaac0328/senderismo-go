-- Comprobantes de pago adjuntos al registro de senderos.
-- Ejecutar en produccion antes de publicar los archivos de esta funcionalidad.

SET @db_name := DATABASE();

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
