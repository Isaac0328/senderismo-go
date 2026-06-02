-- Migracion: multiples inversiones por sendero y nivel numerico de dificultad.
-- Ejecutar sobre la base de datos actual de Senderismo Go.

ALTER TABLE niveles_dificultad
    ADD COLUMN IF NOT EXISTS nivel_numero TINYINT UNSIGNED NOT NULL DEFAULT 50 AFTER descripcion;

CREATE TABLE IF NOT EXISTS sendero_inversiones (
    id INT NOT NULL AUTO_INCREMENT,
    sendero_id INT NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL,
    monto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    fecha_limite_pago DATE DEFAULT NULL,
    orden INT NOT NULL DEFAULT 1,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sendero_inversiones_sendero (sendero_id),
    CONSTRAINT fk_sendero_inversiones_sendero
        FOREIGN KEY (sendero_id) REFERENCES senderos (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS sendero_inversion_incluye (
    inversion_id INT NOT NULL,
    incluye_id INT NOT NULL,
    PRIMARY KEY (inversion_id, incluye_id),
    KEY idx_sendero_inversion_incluye_item (incluye_id),
    CONSTRAINT fk_inversion_incluye_inversion
        FOREIGN KEY (inversion_id) REFERENCES sendero_inversiones (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_inversion_incluye_catalogo
        FOREIGN KEY (incluye_id) REFERENCES elementos_incluidos (id)
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE registros_senderos
    ADD COLUMN IF NOT EXISTS inversion_id INT NULL AFTER detalle_usuario_id,
    ADD KEY IF NOT EXISTS fk_registros_senderos_inversion (inversion_id),
    ADD CONSTRAINT fk_registros_senderos_inversion
        FOREIGN KEY (inversion_id) REFERENCES sendero_inversiones (id)
        ON DELETE SET NULL ON UPDATE CASCADE;

INSERT INTO sendero_inversiones (sendero_id, nombre, descripcion, monto, fecha_limite_pago, orden, activo)
SELECT
    s.id,
    'Inversion general',
    'Plan creado desde la inversion anterior del sendero.',
    COALESCE(s.inversion_total, 0.00),
    s.fecha_limite_pago,
    1,
    1
FROM senderos s
WHERE NOT EXISTS (
    SELECT 1
    FROM sendero_inversiones si
    WHERE si.sendero_id = s.id
);

INSERT IGNORE INTO sendero_inversion_incluye (inversion_id, incluye_id)
SELECT si.id, sei.incluye_id
FROM sendero_inversiones si
INNER JOIN sendero_elementos_incluidos sei ON sei.sendero_id = si.sendero_id
WHERE si.orden = 1;
