-- Actualizacion: descuento autorizado en ingresos por sendero
-- Ejecutar en la base productiva antes de usar la nueva columna.

SET @db_name := DATABASE();

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'contabilidad_registro_pagos'
      AND COLUMN_NAME = 'descuento_autorizado'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE contabilidad_registro_pagos ADD COLUMN descuento_autorizado DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER credito_aplicado',
    'SELECT ''descuento_autorizado ya existe'' AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE contabilidad_registro_pagos
    MODIFY COLUMN estado_financiero ENUM(
        'pendiente',
        'pagado',
        'parcial',
        'credito_aplicado',
        'descuento',
        'deuda',
        'cortesia',
        'no_asistio_sin_pago'
    ) NOT NULL DEFAULT 'pendiente';
