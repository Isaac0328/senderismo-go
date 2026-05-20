-- Limpieza de datos para iniciar pruebas reales en local.
-- Conserva estructura, roles y permisos. Deja un unico usuario: admin / admin.

USE `sgbd`;

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM `sendero_anotaciones`;
DELETE FROM `sendero_elementos_incluidos`;
DELETE FROM `sendero_tipos_terreno`;
DELETE FROM `sendero_puntos_encuentro`;
DELETE FROM `sendero_imagenes`;
DELETE FROM `registros_senderos`;
DELETE FROM `detalles_usuarios`;
DELETE FROM `sesiones_usuario`;
DELETE FROM `intentos_inicio_sesion`;
DELETE FROM `mensajes_contacto`;
DELETE FROM `senderos`;
DELETE FROM `anotaciones_importantes`;
DELETE FROM `elementos_incluidos`;
DELETE FROM `niveles_dificultad`;
DELETE FROM `tipos_camino_vehiculo`;
DELETE FROM `tipos_terreno`;
DELETE FROM `configuracion_contacto`;
DELETE FROM `usuarios`;

ALTER TABLE `sendero_anotaciones` AUTO_INCREMENT = 1;
ALTER TABLE `sendero_elementos_incluidos` AUTO_INCREMENT = 1;
ALTER TABLE `sendero_tipos_terreno` AUTO_INCREMENT = 1;
ALTER TABLE `sendero_puntos_encuentro` AUTO_INCREMENT = 1;
ALTER TABLE `sendero_imagenes` AUTO_INCREMENT = 1;
ALTER TABLE `registros_senderos` AUTO_INCREMENT = 1;
ALTER TABLE `detalles_usuarios` AUTO_INCREMENT = 1;
ALTER TABLE `sesiones_usuario` AUTO_INCREMENT = 1;
ALTER TABLE `intentos_inicio_sesion` AUTO_INCREMENT = 1;
ALTER TABLE `mensajes_contacto` AUTO_INCREMENT = 1;
ALTER TABLE `senderos` AUTO_INCREMENT = 1;
ALTER TABLE `anotaciones_importantes` AUTO_INCREMENT = 1;
ALTER TABLE `elementos_incluidos` AUTO_INCREMENT = 1;
ALTER TABLE `niveles_dificultad` AUTO_INCREMENT = 1;
ALTER TABLE `tipos_camino_vehiculo` AUTO_INCREMENT = 1;
ALTER TABLE `tipos_terreno` AUTO_INCREMENT = 1;
ALTER TABLE `usuarios` AUTO_INCREMENT = 1;

INSERT INTO `usuarios`
    (`id`, `nombre`, `apellido`, `user`, `email`, `password`, `rol_id`, `estado`, `created_at`, `last_login`)
VALUES
    (1, 'Admin', 'Principal', 'admin', 'admin@senderismogo.local', '$2y$10$CVEyOPJBxAs9sG66tvZtNuNNWIn2JWU1Ub9HuVfBtzpBlP0mBcOvW', 1, 1, NOW(), NULL);

INSERT INTO `configuracion_contacto`
    (`id`, `hero_imagen`, `titulo`, `subtitulo`, `horario`, `ubicacion`, `telefono`, `whatsapp`, `email`, `instagram`, `instagram_url`, `texto_formulario`)
VALUES
    (
        1,
        'imagenes/paisajes/hero.jpg',
        'Hablemos de tu proxima ruta',
        'Reserva experiencias privadas, pregunta por nuestros proximos senderos o coordinemos una aventura para tu grupo.',
        'Lunes a viernes, 8:00 a.m. - 6:00 p.m.',
        'Republica Dominicana',
        '+1 (849) 472-1200',
        '18494721200',
        'info@senderismogo.com',
        '@senderismogo',
        'https://www.instagram.com/senderismogo',
        'Cuentanos que necesitas y te responderemos con orientacion clara para que puedas planificar con confianza.'
    );

SET FOREIGN_KEY_CHECKS = 1;
