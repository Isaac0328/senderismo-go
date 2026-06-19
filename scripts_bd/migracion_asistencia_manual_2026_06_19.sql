ALTER TABLE registros_senderos
    ADD COLUMN IF NOT EXISTS registro_origen ENUM('publico','admin_manual') NOT NULL DEFAULT 'publico' AFTER estado;

UPDATE registros_senderos
SET registro_origen = 'admin_manual'
WHERE registro_origen = 'publico'
  AND (
      consentimiento_texto LIKE '%Registro administrativo de participante%'
      OR asistencia_notas LIKE '%Agregado desde Usuarios por sendero%'
  );

ALTER TABLE registros_senderos
    ADD INDEX IF NOT EXISTS idx_registros_senderos_origen (sendero_id, registro_origen, estado);
