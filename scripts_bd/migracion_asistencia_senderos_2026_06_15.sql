-- Migracion: asistencia real de usuarios por sendero
-- Ejecutar en la base local y luego en produccion.

SET @db_name := DATABASE();

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE registros_senderos ADD COLUMN asistio TINYINT(1) NOT NULL DEFAULT 0 AFTER estado',
        'SELECT "asistio ya existe"'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'registros_senderos'
      AND COLUMN_NAME = 'asistio'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE registros_senderos ADD COLUMN fecha_asistencia DATETIME NULL AFTER asistio',
        'SELECT "fecha_asistencia ya existe"'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'registros_senderos'
      AND COLUMN_NAME = 'fecha_asistencia'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE registros_senderos ADD COLUMN asistencia_marcada_por INT NULL AFTER fecha_asistencia',
        'SELECT "asistencia_marcada_por ya existe"'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'registros_senderos'
      AND COLUMN_NAME = 'asistencia_marcada_por'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE registros_senderos ADD COLUMN asistencia_notas VARCHAR(255) NULL AFTER asistencia_marcada_por',
        'SELECT "asistencia_notas ya existe"'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'registros_senderos'
      AND COLUMN_NAME = 'asistencia_notas'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE registros_senderos ADD INDEX idx_registros_senderos_asistencia (sendero_id, asistio, estado)',
        'SELECT "idx_registros_senderos_asistencia ya existe"'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'registros_senderos'
      AND INDEX_NAME = 'idx_registros_senderos_asistencia'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE registros_senderos ADD INDEX idx_registros_senderos_usuario_asistencia (usuario_id, asistio, fecha_asistencia)',
        'SELECT "idx_registros_senderos_usuario_asistencia ya existe"'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'registros_senderos'
      AND INDEX_NAME = 'idx_registros_senderos_usuario_asistencia'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
