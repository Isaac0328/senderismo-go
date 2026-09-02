-- Agrega el estado Exento a los ingresos por sendero.
-- Los registros exentos no generan ingreso esperado, cobro, credito ni saldo.

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
