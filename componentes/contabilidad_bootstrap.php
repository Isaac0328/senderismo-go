<?php

if (!function_exists('contabilidad_bootstrap')) {
    function contabilidad_bootstrap(mysqli $conn): void
    {
        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS contabilidad_categoria_gasto (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(120) NOT NULL,
                descripcion VARCHAR(255) DEFAULT NULL,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_cont_categoria_gasto_nombre (nombre),
                INDEX idx_cont_categoria_gasto_activo (activo, nombre)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS contabilidad_gastos_catalogo (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(160) NOT NULL,
                descripcion VARCHAR(255) DEFAULT NULL,
                categoria VARCHAR(80) DEFAULT NULL,
                categoria_gasto_id INT DEFAULT NULL,
                unidad VARCHAR(40) NOT NULL DEFAULT 'unidad',
                costo_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_cont_gastos_activo (activo, nombre),
                INDEX idx_cont_gastos_categoria_gasto (categoria_gasto_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $dbName = mysqli_real_escape_string($conn, DB_NAME);
        $resColumn = mysqli_query($conn, "
            SELECT COUNT(*) AS total
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = '{$dbName}'
              AND TABLE_NAME = 'contabilidad_gastos_catalogo'
              AND COLUMN_NAME = 'categoria_gasto_id'
        ");
        $hasCategoriaGastoId = $resColumn ? (int) (mysqli_fetch_assoc($resColumn)['total'] ?? 0) : 0;
        if ($hasCategoriaGastoId === 0) {
            mysqli_query($conn, "ALTER TABLE contabilidad_gastos_catalogo ADD COLUMN categoria_gasto_id INT DEFAULT NULL AFTER categoria");
            mysqli_query($conn, "ALTER TABLE contabilidad_gastos_catalogo ADD INDEX idx_cont_gastos_categoria_gasto (categoria_gasto_id)");
        }

        $resFk = mysqli_query($conn, "
            SELECT COUNT(*) AS total
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = '{$dbName}'
              AND TABLE_NAME = 'contabilidad_gastos_catalogo'
              AND CONSTRAINT_NAME = 'fk_cont_gastos_categoria_gasto'
        ");
        $hasFk = $resFk ? (int) (mysqli_fetch_assoc($resFk)['total'] ?? 0) : 0;
        if ($hasFk === 0) {
            mysqli_query($conn, "
                ALTER TABLE contabilidad_gastos_catalogo
                ADD CONSTRAINT fk_cont_gastos_categoria_gasto
                FOREIGN KEY (categoria_gasto_id) REFERENCES contabilidad_categoria_gasto(id)
                ON DELETE SET NULL ON UPDATE CASCADE
            ");
        }

        mysqli_query($conn, "
            INSERT IGNORE INTO contabilidad_categoria_gasto (nombre, descripcion, activo)
            SELECT DISTINCT TRIM(categoria), NULL, 1
            FROM contabilidad_gastos_catalogo
            WHERE categoria IS NOT NULL
              AND TRIM(categoria) <> ''
        ");

        mysqli_query($conn, "
            UPDATE contabilidad_gastos_catalogo cg
            INNER JOIN contabilidad_categoria_gasto ccg ON ccg.nombre = TRIM(cg.categoria)
            SET cg.categoria_gasto_id = ccg.id
            WHERE cg.categoria_gasto_id IS NULL
              AND cg.categoria IS NOT NULL
              AND TRIM(cg.categoria) <> ''
        ");

        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS contabilidad_sendero_gastos (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                sendero_id INT NOT NULL,
                gasto_id INT NOT NULL,
                cantidad DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                costo_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                nota VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_cont_sendero_gasto (sendero_id, gasto_id),
                INDEX idx_cont_gastos_sendero (sendero_id),
                CONSTRAINT fk_cont_gastos_sendero FOREIGN KEY (sendero_id) REFERENCES senderos(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_cont_gastos_catalogo FOREIGN KEY (gasto_id) REFERENCES contabilidad_gastos_catalogo(id) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS contabilidad_metodo_pago (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(120) NOT NULL,
                descripcion VARCHAR(255) DEFAULT NULL,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_cont_metodo_pago_nombre (nombre),
                INDEX idx_cont_metodo_pago_activo (activo, nombre)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS contabilidad_registro_pagos (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                registro_id INT NOT NULL,
                sendero_id INT NOT NULL,
                pagado TINYINT(1) NOT NULL DEFAULT 0,
                monto_pagado DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                fecha_pago DATE DEFAULT NULL,
                metodo_pago VARCHAR(60) DEFAULT NULL,
                metodo_pago_id INT DEFAULT NULL,
                nota VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_cont_pago_registro (registro_id),
                INDEX idx_cont_pagos_sendero (sendero_id, pagado),
                INDEX idx_cont_pagos_metodo_pago (metodo_pago_id),
                CONSTRAINT fk_cont_pagos_registro FOREIGN KEY (registro_id) REFERENCES registros_senderos(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_cont_pagos_sendero FOREIGN KEY (sendero_id) REFERENCES senderos(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $resPagoColumn = mysqli_query($conn, "
            SELECT COUNT(*) AS total
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = '{$dbName}'
              AND TABLE_NAME = 'contabilidad_registro_pagos'
              AND COLUMN_NAME = 'metodo_pago_id'
        ");
        $hasMetodoPagoId = $resPagoColumn ? (int) (mysqli_fetch_assoc($resPagoColumn)['total'] ?? 0) : 0;
        if ($hasMetodoPagoId === 0) {
            mysqli_query($conn, "ALTER TABLE contabilidad_registro_pagos ADD COLUMN metodo_pago_id INT DEFAULT NULL AFTER metodo_pago");
            mysqli_query($conn, "ALTER TABLE contabilidad_registro_pagos ADD INDEX idx_cont_pagos_metodo_pago (metodo_pago_id)");
        }

        $resMetodoFk = mysqli_query($conn, "
            SELECT COUNT(*) AS total
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = '{$dbName}'
              AND TABLE_NAME = 'contabilidad_registro_pagos'
              AND CONSTRAINT_NAME = 'fk_cont_pagos_metodo_pago'
        ");
        $hasMetodoFk = $resMetodoFk ? (int) (mysqli_fetch_assoc($resMetodoFk)['total'] ?? 0) : 0;
        if ($hasMetodoFk === 0) {
            mysqli_query($conn, "
                ALTER TABLE contabilidad_registro_pagos
                ADD CONSTRAINT fk_cont_pagos_metodo_pago
                FOREIGN KEY (metodo_pago_id) REFERENCES contabilidad_metodo_pago(id)
                ON DELETE SET NULL ON UPDATE CASCADE
            ");
        }

        mysqli_query($conn, "
            INSERT IGNORE INTO contabilidad_metodo_pago (nombre, descripcion, activo)
            SELECT DISTINCT TRIM(metodo_pago), NULL, 1
            FROM contabilidad_registro_pagos
            WHERE metodo_pago IS NOT NULL
              AND TRIM(metodo_pago) <> ''
        ");

        mysqli_query($conn, "
            UPDATE contabilidad_registro_pagos crp
            INNER JOIN contabilidad_metodo_pago cmp ON cmp.nombre = TRIM(crp.metodo_pago)
            SET crp.metodo_pago_id = cmp.id
            WHERE crp.metodo_pago_id IS NULL
              AND crp.metodo_pago IS NOT NULL
              AND TRIM(crp.metodo_pago) <> ''
        ");
    }
}
