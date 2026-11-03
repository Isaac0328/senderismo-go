-- Impide eliminar usuarios que tengan cualquier registro enlazado a un sendero.
-- La eliminacion continua permitida unicamente para usuarios inactivos sin registros.

DROP PROCEDURE IF EXISTS sp_usuarios_eliminar;

DELIMITER $$

CREATE PROCEDURE sp_usuarios_eliminar (
    IN p_id INT,
    OUT p_mensaje VARCHAR(255),
    OUT p_codigo INT
)
proc: BEGIN
    DECLARE v_estado TINYINT;
    DECLARE v_registros INT DEFAULT 0;

    SET p_codigo = 0;
    SET p_mensaje = '';

    IF p_id IS NULL OR p_id <= 0 THEN
        SET p_codigo = 10;
        SET p_mensaje = 'ID invalido';
        LEAVE proc;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM usuarios WHERE id = p_id) THEN
        SET p_codigo = 11;
        SET p_mensaje = 'Usuario no existe';
        LEAVE proc;
    END IF;

    SELECT estado INTO v_estado
    FROM usuarios
    WHERE id = p_id;

    IF v_estado = 1 THEN
        SET p_codigo = 12;
        SET p_mensaje = 'No se puede eliminar un usuario activo. Debe inactivarlo primero.';
        LEAVE proc;
    END IF;

    SELECT COUNT(*) INTO v_registros
    FROM registros_senderos
    WHERE usuario_id = p_id;

    IF v_registros > 0 THEN
        SET p_codigo = 13;
        SET p_mensaje = 'No se puede eliminar un usuario con registros enlazados a senderos. Debe inactivarlo.';
        LEAVE proc;
    END IF;

    DELETE FROM usuarios WHERE id = p_id;
    SET p_mensaje = 'Usuario eliminado correctamente';
END$$

DELIMITER ;
