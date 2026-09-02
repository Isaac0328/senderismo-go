<?php

if (!function_exists('encuestas_bootstrap')) {
    function encuestas_bootstrap(mysqli $conn): void
    {
        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS encuestas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                titulo VARCHAR(180) NOT NULL,
                descripcion TEXT NULL,
                sendero_id INT NULL,
                destinatarios ENUM('sendero_asistentes','sendero_registrados','todos_usuarios') NOT NULL DEFAULT 'sendero_asistentes',
                estado ENUM('borrador','enviada','cancelada','cerrada') NOT NULL DEFAULT 'borrador',
                anonima TINYINT(1) NOT NULL DEFAULT 0,
                permite_editar_respuesta TINYINT(1) NOT NULL DEFAULT 0,
                fecha_envio DATETIME NULL,
                fecha_cierre DATETIME NULL,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                creado_por INT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_encuestas_sendero (sendero_id),
                KEY idx_encuestas_estado (estado, activo),
                CONSTRAINT fk_encuestas_sendero FOREIGN KEY (sendero_id) REFERENCES senderos(id) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_encuestas_creador FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS encuesta_preguntas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                encuesta_id INT NOT NULL,
                pregunta VARCHAR(255) NOT NULL,
                ayuda VARCHAR(255) NULL,
                tipo ENUM('texto','textarea','radio','checkbox','select','escala','numero') NOT NULL DEFAULT 'texto',
                requerido TINYINT(1) NOT NULL DEFAULT 1,
                puntaje_max DECIMAL(8,2) NOT NULL DEFAULT 0,
                orden INT NOT NULL DEFAULT 0,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_encuesta_preguntas_encuesta (encuesta_id, orden),
                CONSTRAINT fk_encuesta_preguntas_encuesta FOREIGN KEY (encuesta_id) REFERENCES encuestas(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS encuesta_opciones (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pregunta_id INT NOT NULL,
                texto VARCHAR(255) NOT NULL,
                valor VARCHAR(120) NULL,
                puntuacion DECIMAL(8,2) NOT NULL DEFAULT 0,
                orden INT NOT NULL DEFAULT 0,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_encuesta_opciones_pregunta (pregunta_id, orden),
                CONSTRAINT fk_encuesta_opciones_pregunta FOREIGN KEY (pregunta_id) REFERENCES encuesta_preguntas(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS encuesta_envios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                encuesta_id INT NOT NULL,
                usuario_id INT NOT NULL,
                sendero_id INT NULL,
                estado ENUM('pendiente','respondida','cancelada') NOT NULL DEFAULT 'pendiente',
                enviado_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                respondido_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_encuesta_envio_usuario (encuesta_id, usuario_id),
                KEY idx_encuesta_envios_usuario (usuario_id, estado),
                KEY idx_encuesta_envios_sendero (sendero_id),
                CONSTRAINT fk_encuesta_envios_encuesta FOREIGN KEY (encuesta_id) REFERENCES encuestas(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_encuesta_envios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_encuesta_envios_sendero FOREIGN KEY (sendero_id) REFERENCES senderos(id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS encuesta_respuestas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                envio_id INT NOT NULL,
                pregunta_id INT NOT NULL,
                opcion_id INT NULL,
                respuesta_texto TEXT NULL,
                respuesta_numero DECIMAL(10,2) NULL,
                puntuacion DECIMAL(10,2) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_encuesta_respuestas_envio (envio_id),
                KEY idx_encuesta_respuestas_pregunta (pregunta_id),
                CONSTRAINT fk_encuesta_respuestas_envio FOREIGN KEY (envio_id) REFERENCES encuesta_envios(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_encuesta_respuestas_pregunta FOREIGN KEY (pregunta_id) REFERENCES encuesta_preguntas(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_encuesta_respuestas_opcion FOREIGN KEY (opcion_id) REFERENCES encuesta_opciones(id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
}
