-- Fix para Hostinger: recrea procedimientos con collation explicita.
-- Ejecutar en phpMyAdmin sobre la base u591234109_senderismo.

SET NAMES utf8mb4 COLLATE utf8mb4_general_ci;

DROP PROCEDURE IF EXISTS sp_iniciar_sesion;
DROP PROCEDURE IF EXISTS sp_registrar_usuario;
DROP PROCEDURE IF EXISTS sp_roles_eliminar;
DROP PROCEDURE IF EXISTS sp_roles_guardar;
DROP PROCEDURE IF EXISTS sp_roles_listar;
DROP PROCEDURE IF EXISTS sp_usuarios_cambiar_estado;
DROP PROCEDURE IF EXISTS sp_usuarios_eliminar;
DROP PROCEDURE IF EXISTS sp_usuarios_guardar;
DROP PROCEDURE IF EXISTS sp_usuarios_listar;

DELIMITER $$

CREATE PROCEDURE sp_iniciar_sesion(
    IN  p_user    VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    OUT p_mensaje VARCHAR(600) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    OUT p_codigo  INT
)
BEGIN
    DECLARE v_id INT DEFAULT NULL;
    DECLARE v_nombre VARCHAR(220) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '';
    DECLARE v_hash VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '';
    DECLARE v_rol_id INT DEFAULT NULL;
    DECLARE v_rol_nombre VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '';
    DECLARE v_estado TINYINT DEFAULT 0;

    SET p_codigo = 0;
    SET p_mensaje = '';

    SELECT u.id,
           CONCAT(u.nombre, ' ', u.apellido),
           u.password,
           u.rol_id,
           u.estado,
           r.nombre
      INTO v_id, v_nombre, v_hash, v_rol_id, v_estado, v_rol_nombre
      FROM usuarios u
      JOIN roles r ON r.id = u.rol_id
     WHERE (
        u.user COLLATE utf8mb4_general_ci = p_user COLLATE utf8mb4_general_ci
        OR u.email COLLATE utf8mb4_general_ci = p_user COLLATE utf8mb4_general_ci
     )
     LIMIT 1;

    IF v_id IS NULL THEN
        SET p_codigo = 1;
        SET p_mensaje = 'Usuario o correo no existe';
    ELSEIF v_estado = 0 THEN
        SET p_codigo = 2;
        SET p_mensaje = 'Usuario inactivo';
    ELSE
        SET p_codigo = 0;
        SET p_mensaje = CONCAT(v_hash, '|', v_id, '|', v_nombre, '|', v_rol_id, '|', v_rol_nombre);
    END IF;
END $$

CREATE PROCEDURE sp_registrar_usuario(
    IN  p_nombre VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    IN  p_apellido VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    IN  p_user VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    IN  p_email VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    IN  p_password_hash VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    OUT p_mensaje VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    OUT p_codigo INT
)
proc: BEGIN
    DECLARE v_rol_id INT DEFAULT NULL;

    SET p_codigo = 0;
    SET p_mensaje = '';

    IF p_nombre IS NULL OR TRIM(p_nombre) = ''
       OR p_apellido IS NULL OR TRIM(p_apellido) = ''
       OR p_user IS NULL OR TRIM(p_user) = ''
       OR p_email IS NULL OR TRIM(p_email) = ''
       OR p_password_hash IS NULL OR TRIM(p_password_hash) = '' THEN
        SET p_codigo = 10;
        SET p_mensaje = 'Debe completar todos los campos';
        LEAVE proc;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM usuarios
        WHERE email COLLATE utf8mb4_general_ci = TRIM(p_email) COLLATE utf8mb4_general_ci
        LIMIT 1
    ) THEN
        SET p_codigo = 11;
        SET p_mensaje = 'Este email ya esta registrado';
        LEAVE proc;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM usuarios
        WHERE user COLLATE utf8mb4_general_ci = TRIM(p_user) COLLATE utf8mb4_general_ci
        LIMIT 1
    ) THEN
        SET p_codigo = 12;
        SET p_mensaje = 'Este usuario ya existe';
        LEAVE proc;
    END IF;

    SELECT id INTO v_rol_id
      FROM roles
     WHERE nombre COLLATE utf8mb4_general_ci = 'Invitado' COLLATE utf8mb4_general_ci
     LIMIT 1;

    IF v_rol_id IS NULL THEN
        SET p_codigo = 13;
        SET p_mensaje = 'No existe el rol Invitado. Crealo primero.';
        LEAVE proc;
    END IF;

    INSERT INTO usuarios (nombre, apellido, user, email, password, rol_id, estado)
    VALUES (TRIM(p_nombre), TRIM(p_apellido), TRIM(p_user), TRIM(p_email), p_password_hash, v_rol_id, 1);

    SET p_codigo = 0;
    SET p_mensaje = 'Usuario registrado correctamente';
END $$

CREATE PROCEDURE sp_roles_eliminar(
  IN  p_id INT,
  OUT p_mensaje VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  OUT p_codigo INT
)
proc: BEGIN
  DECLARE v_uso INT DEFAULT 0;

  SET p_codigo = 0;
  SET p_mensaje = '';

  IF p_id IS NULL OR p_id <= 0 THEN
    SET p_codigo = 10;
    SET p_mensaje = 'ID invalido';
    LEAVE proc;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM roles WHERE id = p_id) THEN
    SET p_codigo = 11;
    SET p_mensaje = 'Rol no existe';
    LEAVE proc;
  END IF;

  SELECT COUNT(*) INTO v_uso FROM usuarios WHERE rol_id = p_id;

  IF v_uso > 0 THEN
    SET p_codigo = 12;
    SET p_mensaje = 'No se puede eliminar: hay usuarios asignados a este rol';
    LEAVE proc;
  END IF;

  DELETE FROM roles WHERE id = p_id;
  SET p_mensaje = 'Rol eliminado correctamente';
END $$

CREATE PROCEDURE sp_roles_guardar(
  IN  p_id INT,
  IN  p_nombre VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  IN  p_descripcion VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  OUT p_mensaje VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  OUT p_codigo INT
)
proc: BEGIN
  DECLARE v_exists INT DEFAULT 0;

  SET p_codigo = 0;
  SET p_mensaje = '';

  IF p_nombre IS NULL OR TRIM(p_nombre) = '' THEN
    SET p_codigo = 10;
    SET p_mensaje = 'El nombre del rol es obligatorio';
    LEAVE proc;
  END IF;

  SELECT COUNT(*) INTO v_exists
  FROM roles
  WHERE nombre COLLATE utf8mb4_general_ci = TRIM(p_nombre) COLLATE utf8mb4_general_ci
    AND (p_id = 0 OR id <> p_id);

  IF v_exists > 0 THEN
    SET p_codigo = 11;
    SET p_mensaje = 'Ya existe un rol con ese nombre';
    LEAVE proc;
  END IF;

  IF p_id IS NULL OR p_id = 0 THEN
    INSERT INTO roles (nombre, descripcion)
    VALUES (TRIM(p_nombre), NULLIF(TRIM(p_descripcion), ''));
    SET p_mensaje = 'Rol creado correctamente';
  ELSE
    IF NOT EXISTS (SELECT 1 FROM roles WHERE id = p_id) THEN
      SET p_codigo = 12;
      SET p_mensaje = 'Rol no encontrado';
      LEAVE proc;
    END IF;

    UPDATE roles
    SET nombre = TRIM(p_nombre),
        descripcion = NULLIF(TRIM(p_descripcion), '')
    WHERE id = p_id;

    SET p_mensaje = 'Rol actualizado correctamente';
  END IF;
END $$

CREATE PROCEDURE sp_roles_listar()
BEGIN
  SELECT id, nombre, descripcion, created_at
  FROM roles
  ORDER BY id DESC;
END $$

CREATE PROCEDURE sp_usuarios_cambiar_estado(
  IN  p_id INT,
  IN  p_estado TINYINT,
  OUT p_mensaje VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  OUT p_codigo INT
)
proc: BEGIN
  SET p_codigo = 0;
  SET p_mensaje = '';

  IF p_id IS NULL OR p_id <= 0 THEN
    SET p_codigo = 10;
    SET p_mensaje = 'ID invalido';
    LEAVE proc;
  END IF;

  IF p_estado NOT IN (0,1) THEN
    SET p_codigo = 11;
    SET p_mensaje = 'Estado invalido';
    LEAVE proc;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM usuarios WHERE id = p_id) THEN
    SET p_codigo = 12;
    SET p_mensaje = 'Usuario no encontrado';
    LEAVE proc;
  END IF;

  UPDATE usuarios SET estado = p_estado WHERE id = p_id;
  SET p_mensaje = IF(p_estado = 1, 'Usuario activado', 'Usuario inactivado');
END $$

CREATE PROCEDURE sp_usuarios_eliminar(
  IN  p_id INT,
  OUT p_mensaje VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  OUT p_codigo INT
)
proc: BEGIN
  DECLARE v_estado TINYINT;

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

  SELECT estado INTO v_estado FROM usuarios WHERE id = p_id;

  IF v_estado = 1 THEN
    SET p_codigo = 12;
    SET p_mensaje = 'No se puede eliminar un usuario activo. Debe inactivarlo primero.';
    LEAVE proc;
  END IF;

  DELETE FROM usuarios WHERE id = p_id;
  SET p_mensaje = 'Usuario eliminado correctamente';
END $$

CREATE PROCEDURE sp_usuarios_guardar(
  IN  p_id INT,
  IN  p_nombre VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  IN  p_apellido VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  IN  p_user VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  IN  p_email VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  IN  p_password_hash VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  IN  p_rol_id INT,
  OUT p_mensaje VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  OUT p_codigo INT
)
proc: BEGIN
  DECLARE v_exists INT DEFAULT 0;

  SET p_codigo = 0;
  SET p_mensaje = '';

  IF p_nombre IS NULL OR TRIM(p_nombre) = ''
     OR p_apellido IS NULL OR TRIM(p_apellido) = ''
     OR p_user IS NULL OR TRIM(p_user) = ''
     OR p_email IS NULL OR TRIM(p_email) = ''
     OR p_rol_id IS NULL OR p_rol_id <= 0 THEN
    SET p_codigo = 10;
    SET p_mensaje = 'Debe completar todos los campos obligatorios';
    LEAVE proc;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM roles WHERE id = p_rol_id) THEN
    SET p_codigo = 11;
    SET p_mensaje = 'El rol seleccionado no existe';
    LEAVE proc;
  END IF;

  SELECT COUNT(*) INTO v_exists
  FROM usuarios
  WHERE email COLLATE utf8mb4_general_ci = TRIM(p_email) COLLATE utf8mb4_general_ci
    AND (p_id = 0 OR id <> p_id);

  IF v_exists > 0 THEN
    SET p_codigo = 12;
    SET p_mensaje = 'Este email ya esta registrado';
    LEAVE proc;
  END IF;

  SELECT COUNT(*) INTO v_exists
  FROM usuarios
  WHERE user COLLATE utf8mb4_general_ci = TRIM(p_user) COLLATE utf8mb4_general_ci
    AND (p_id = 0 OR id <> p_id);

  IF v_exists > 0 THEN
    SET p_codigo = 13;
    SET p_mensaje = 'Este usuario ya existe';
    LEAVE proc;
  END IF;

  IF p_id IS NULL OR p_id = 0 THEN
    IF p_password_hash IS NULL OR TRIM(p_password_hash) = '' THEN
      SET p_codigo = 14;
      SET p_mensaje = 'La contrasena es obligatoria para crear el usuario';
      LEAVE proc;
    END IF;

    INSERT INTO usuarios (nombre, apellido, user, email, password, rol_id, estado)
    VALUES (TRIM(p_nombre), TRIM(p_apellido), TRIM(p_user), TRIM(p_email), p_password_hash, p_rol_id, 1);

    SET p_mensaje = 'Usuario creado correctamente';
  ELSE
    IF NOT EXISTS (SELECT 1 FROM usuarios WHERE id = p_id) THEN
      SET p_codigo = 15;
      SET p_mensaje = 'Usuario no encontrado';
      LEAVE proc;
    END IF;

    IF p_password_hash IS NULL OR TRIM(p_password_hash) = '' THEN
      UPDATE usuarios
      SET nombre = TRIM(p_nombre),
          apellido = TRIM(p_apellido),
          user = TRIM(p_user),
          email = TRIM(p_email),
          rol_id = p_rol_id
      WHERE id = p_id;
    ELSE
      UPDATE usuarios
      SET nombre = TRIM(p_nombre),
          apellido = TRIM(p_apellido),
          user = TRIM(p_user),
          email = TRIM(p_email),
          password = p_password_hash,
          rol_id = p_rol_id
      WHERE id = p_id;
    END IF;

    SET p_mensaje = 'Usuario actualizado correctamente';
  END IF;
END $$

CREATE PROCEDURE sp_usuarios_listar()
BEGIN
  SELECT
    u.id,
    u.nombre,
    u.apellido,
    u.user,
    u.email,
    u.rol_id,
    r.nombre AS rol_nombre,
    u.estado,
    u.created_at,
    u.last_login
  FROM usuarios u
  INNER JOIN roles r ON r.id = u.rol_id
  ORDER BY u.id DESC;
END $$

DELIMITER ;
