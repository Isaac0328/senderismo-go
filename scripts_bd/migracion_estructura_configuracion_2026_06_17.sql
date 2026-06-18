-- Migracion de estructura para configuraciones editables y modulos recientes.
-- Ejecutar una vez en cada base de datos antes de desplegar el codigo que ya no crea tablas en runtime.
-- Compatible con MariaDB/MySQL de Hostinger en uso para el proyecto.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS configuracion_inicio (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    hero_imagen VARCHAR(255) NOT NULL DEFAULT 'imagenes/paisajes/hero.jpg',
    logo_imagen VARCHAR(255) NOT NULL DEFAULT 'imagenes/logo/logo_sg.png',
    hero_titulo VARCHAR(160) NOT NULL,
    hero_subtitulo VARCHAR(255) NOT NULL,
    boton_texto VARCHAR(80) NOT NULL DEFAULT 'CONOCER MAS',
    boton_url VARCHAR(255) NOT NULL DEFAULT '#porque-elegirnos',
    acceso_rapido_texto VARCHAR(120) NOT NULL DEFAULT 'Ver proximos senderos',
    acceso_rapido_badge VARCHAR(40) NOT NULL DEFAULT 'Agenda',
    acceso_rapido_url VARCHAR(255) NOT NULL DEFAULT 'pantallas/senderos.php',
    porque_titulo VARCHAR(160) NOT NULL,
    galeria_titulo VARCHAR(180) NOT NULL,
    galeria_subtitulo VARCHAR(255) NOT NULL,
    cta_titulo VARCHAR(180) NOT NULL,
    cta_texto TEXT NOT NULL,
    cta_boton_texto VARCHAR(80) NOT NULL,
    cta_boton_url VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS inicio_tarjetas (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    icono VARCHAR(60) NOT NULL DEFAULT 'map',
    titulo VARCHAR(160) NOT NULL,
    descripcion TEXT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS inicio_galeria (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    imagen VARCHAR(255) NOT NULL,
    titulo VARCHAR(160) DEFAULT NULL,
    orden INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO configuracion_inicio
    (id, hero_imagen, logo_imagen, hero_titulo, hero_subtitulo, boton_texto, boton_url, acceso_rapido_texto, acceso_rapido_badge, acceso_rapido_url, porque_titulo, galeria_titulo, galeria_subtitulo, cta_titulo, cta_texto, cta_boton_texto, cta_boton_url)
VALUES
    (1, 'imagenes/paisajes/hero.jpg', 'imagenes/logo/logo_sg.png', 'Senderismo... Go!', 'Apasionados por la naturaleza!', 'CONOCER MAS', '#porque-elegirnos', 'Ver proximos senderos', 'Agenda', 'pantallas/senderos.php', 'Por que elegirnos?', 'Algunos de los paisajes que veras con nosotros', 'Descubre un vistazo de las experiencias que te esperan', 'Conoce un poco mas sobre nosotros', 'Somos una comunidad apasionada por la naturaleza, dedicada a crear experiencias unicas de senderismo que conectan a las personas con paisajes increibles y momentos inolvidables.', 'Saber mas', 'pantallas/nosotros.php');

INSERT IGNORE INTO inicio_tarjetas (id, icono, titulo, descripcion, orden, activo) VALUES
    (1, 'map', 'Rutas Exclusivas y Seguras', 'Explora senderos cuidadosamente seleccionados para ofrecerte las mejores vistas y experiencias, con guias expertos que garantizan tu seguridad en todo momento.', 1, 1),
    (2, 'users', 'Experiencia para Todos los Niveles', 'Ofrecemos rutas adaptadas a principiantes y expertos, asegurando que cada aventura se ajuste a tu nivel y disfrutes sin preocupaciones.', 2, 1),
    (3, 'image', 'Conexion Autentica con la Naturaleza', 'Mas que un recorrido, nuestras experiencias te sumergen en la naturaleza con actividades de observacion, fotografia y momentos de relajacion en entornos unicos.', 3, 1);

INSERT IGNORE INTO inicio_galeria (id, imagen, titulo, orden, activo) VALUES
    (1, 'imagenes/paisajes/img1.jpg', 'Paisaje 1', 1, 1),
    (2, 'imagenes/paisajes/img2.jpg', 'Paisaje 2', 2, 1),
    (3, 'imagenes/paisajes/img3.jpg', 'Paisaje 3', 3, 1),
    (4, 'imagenes/paisajes/img4.jpg', 'Paisaje 4', 4, 1),
    (5, 'imagenes/paisajes/img5.jpg', 'Paisaje 5', 5, 1),
    (6, 'imagenes/paisajes/img6.jpg', 'Paisaje 6', 6, 1),
    (7, 'imagenes/paisajes/img7.jpg', 'Paisaje 7', 7, 1),
    (8, 'imagenes/paisajes/img8.jpg', 'Paisaje 8', 8, 1),
    (9, 'imagenes/paisajes/img9.jpg', 'Paisaje 9', 9, 1),
    (10, 'imagenes/paisajes/img10.jpg', 'Paisaje 10', 10, 1);

CREATE TABLE IF NOT EXISTS configuracion_contacto (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    hero_imagen VARCHAR(255) NOT NULL DEFAULT 'imagenes/paisajes/hero.jpg',
    titulo VARCHAR(160) NOT NULL,
    subtitulo VARCHAR(255) NOT NULL,
    hero_boton_texto VARCHAR(80) NOT NULL DEFAULT 'Escribir mensaje',
    hero_whatsapp_texto VARCHAR(80) NOT NULL DEFAULT 'WhatsApp',
    horario VARCHAR(160) NOT NULL,
    ubicacion VARCHAR(160) NOT NULL,
    telefono VARCHAR(60) NOT NULL,
    whatsapp VARCHAR(30) NOT NULL,
    email VARCHAR(160) NOT NULL,
    instagram VARCHAR(120) NOT NULL,
    instagram_url VARCHAR(255) NOT NULL,
    seccion_kicker VARCHAR(80) NOT NULL DEFAULT 'Atencion personalizada',
    seccion_titulo VARCHAR(180) NOT NULL,
    texto_formulario TEXT NOT NULL,
    nota_contacto TEXT NOT NULL,
    form_kicker VARCHAR(80) NOT NULL DEFAULT 'Mensaje rapido',
    form_titulo VARCHAR(120) NOT NULL,
    form_subtitulo VARCHAR(255) NOT NULL,
    form_privacidad VARCHAR(255) NOT NULL,
    boton_formulario VARCHAR(80) NOT NULL DEFAULT 'Enviar mensaje',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS contacto_bloques (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    grupo VARCHAR(30) NOT NULL,
    icono VARCHAR(60) NOT NULL DEFAULT 'circle',
    titulo VARCHAR(120) NOT NULL,
    texto VARCHAR(255) NOT NULL,
    url VARCHAR(255) DEFAULT NULL,
    orden INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_contacto_bloques_grupo (grupo, activo, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS mensajes_contacto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NULL,
    email VARCHAR(150) NOT NULL,
    telefono VARCHAR(30) NULL,
    asunto VARCHAR(80) NOT NULL,
    mensaje TEXT NOT NULL,
    estado ENUM('nuevo','leido','respondido','archivado') NOT NULL DEFAULT 'nuevo',
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_mensajes_contacto_estado (estado),
    INDEX idx_mensajes_contacto_fecha (fecha_creacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO configuracion_contacto
    (id, hero_imagen, titulo, subtitulo, hero_boton_texto, hero_whatsapp_texto, horario, ubicacion, telefono, whatsapp, email, instagram, instagram_url, seccion_titulo, texto_formulario, nota_contacto, form_titulo, form_subtitulo, form_privacidad, boton_formulario)
VALUES
    (1, 'imagenes/paisajes/hero.jpg', 'Hablemos de tu proxima ruta', 'Reserva experiencias privadas, pregunta por nuestros proximos senderos o coordinemos una aventura para tu grupo.', 'Escribir mensaje', 'WhatsApp', 'Lunes a viernes, 8:00 a.m. - 6:00 p.m.', 'Republica Dominicana', '+1 (849) 472-1200', '18494721200', 'info@senderismogo.com', '@senderismogo', 'https://www.instagram.com/senderismogo', 'Estamos listos para orientarte.', 'Cuentanos que necesitas y te responderemos con orientacion clara para que puedas planificar con confianza.', 'Tambien puedes escribirnos para rutas privadas, actividades corporativas, grupos familiares o recomendaciones de dificultad.', 'Escribenos', 'Completa estos datos y nos pondremos en contacto contigo.', 'Usaremos tu informacion solo para responder esta solicitud.', 'Enviar mensaje');

CREATE TABLE IF NOT EXISTS configuracion_nosotros (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    hero_imagen VARCHAR(255) NOT NULL DEFAULT 'imagenes/paisajes/hero.jpg',
    hero_kicker VARCHAR(80) NOT NULL DEFAULT 'Nosotros',
    hero_titulo VARCHAR(180) NOT NULL,
    hero_subtitulo TEXT NOT NULL,
    boton_principal_texto VARCHAR(80) NOT NULL DEFAULT 'Ver senderos',
    boton_principal_url VARCHAR(255) NOT NULL DEFAULT 'pantallas/senderos.php',
    boton_secundario_texto VARCHAR(80) NOT NULL DEFAULT 'Coordinar una ruta',
    boton_secundario_url VARCHAR(255) NOT NULL DEFAULT 'pantallas/contacto.php',
    historia_imagen VARCHAR(255) NOT NULL DEFAULT 'imagenes/paisajes/img4.jpg',
    historia_badge_titulo VARCHAR(80) NOT NULL DEFAULT 'Desde 2015',
    historia_badge_texto VARCHAR(120) NOT NULL DEFAULT 'Creando comunidad outdoor',
    historia_kicker VARCHAR(80) NOT NULL DEFAULT 'Nuestra historia',
    historia_titulo VARCHAR(180) NOT NULL,
    historia_texto_1 TEXT NOT NULL,
    historia_texto_2 TEXT NOT NULL,
    valores_kicker VARCHAR(80) NOT NULL DEFAULT 'Nuestro compromiso',
    valores_titulo VARCHAR(180) NOT NULL,
    valores_texto TEXT NOT NULL,
    proceso_kicker VARCHAR(80) NOT NULL DEFAULT 'Como trabajamos',
    proceso_titulo VARCHAR(180) NOT NULL,
    proceso_texto TEXT NOT NULL,
    equipo_kicker VARCHAR(80) NOT NULL DEFAULT 'Equipo',
    equipo_titulo VARCHAR(180) NOT NULL,
    equipo_texto TEXT NOT NULL,
    cta_kicker VARCHAR(80) NOT NULL DEFAULT 'Proxima aventura',
    cta_titulo VARCHAR(180) NOT NULL,
    cta_texto TEXT NOT NULL,
    cta_boton_principal_texto VARCHAR(80) NOT NULL DEFAULT 'Ver proximos',
    cta_boton_principal_url VARCHAR(255) NOT NULL DEFAULT 'pantallas/senderos.php',
    cta_boton_secundario_texto VARCHAR(80) NOT NULL DEFAULT 'Contactar',
    cta_boton_secundario_url VARCHAR(255) NOT NULL DEFAULT 'pantallas/contacto.php',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS nosotros_indicadores (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    valor VARCHAR(40) NOT NULL,
    etiqueta VARCHAR(120) NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS nosotros_valores (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    icono VARCHAR(60) NOT NULL DEFAULT 'leaf',
    titulo VARCHAR(120) NOT NULL,
    texto TEXT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS nosotros_pasos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(10) NOT NULL,
    titulo VARCHAR(140) NOT NULL,
    texto TEXT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS nosotros_equipo (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    rol VARCHAR(160) NOT NULL,
    imagen VARCHAR(255) NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO configuracion_nosotros
    (id, hero_titulo, hero_subtitulo, historia_titulo, historia_texto_1, historia_texto_2, valores_titulo, valores_texto, proceso_titulo, proceso_texto, equipo_titulo, equipo_texto, cta_titulo, cta_texto)
VALUES
    (1, 'Guiamos experiencias que conectan personas con la naturaleza.', 'Senderismo Go nace para que cada persona pueda descubrir rutas, paisajes y comunidades con una experiencia organizada, cercana y responsable.', 'De una caminata entre amigos a una comunidad de aventura.', 'Lo que comenzo como recorridos entre personas amantes de la montana se convirtio en una forma de compartir naturaleza, bienestar y companerismo. Hoy organizamos experiencias para quienes desean caminar con mayor seguridad, aprender sobre cada ruta y vivir momentos memorables.', 'Trabajamos con planificacion, guias preparados, comunicacion clara y respeto por cada espacio natural. Nuestro objetivo es que el visitante se sienta acompanado desde que pregunta por una ruta hasta que termina la experiencia.', 'Lo que cuidamos en cada salida', 'La aventura debe sentirse emocionante, pero tambien organizada, clara y humana.', 'Una ruta bien vivida empieza antes de caminar.', 'Por eso cada experiencia se prepara con informacion practica: dificultad, distancia, tiempos, puntos de encuentro, terreno, recomendaciones y lo que cada participante debe llevar.', 'Personas detras de cada experiencia', 'Un equipo enfocado en seguridad, logistica, orientacion y buen trato.', 'Quieres caminar con nosotros?', 'Explora los proximos senderos o escribenos para coordinar una experiencia privada.');

CREATE TABLE IF NOT EXISTS configuracion_tema (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    tema VARCHAR(40) NOT NULL DEFAULT 'senderismo',
    primary_color VARCHAR(7) NOT NULL DEFAULT '#255f38',
    primary_dark_color VARCHAR(7) NOT NULL DEFAULT '#102617',
    accent_color VARCHAR(7) NOT NULL DEFAULT '#e10600',
    accent_dark_color VARCHAR(7) NOT NULL DEFAULT '#b90000',
    background_color VARCHAR(7) NOT NULL DEFAULT '#f3f6ef',
    surface_color VARCHAR(7) NOT NULL DEFAULT '#ffffff',
    text_color VARCHAR(7) NOT NULL DEFAULT '#111111',
    muted_color VARCHAR(7) NOT NULL DEFAULT '#5f6d64',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO configuracion_tema (id) VALUES (1);

CREATE TABLE IF NOT EXISTS menores_usuarios (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    telefono VARCHAR(30) DEFAULT NULL,
    rango_edad VARCHAR(20) NOT NULL,
    es_alergico TINYINT(1) NOT NULL DEFAULT 0,
    alergias_detalle VARCHAR(255) DEFAULT NULL,
    grupo_sanguineo VARCHAR(10) NOT NULL,
    enfermedad VARCHAR(255) NOT NULL,
    seguro_medico VARCHAR(255) NOT NULL,
    experiencia_senderismo VARCHAR(80) NOT NULL,
    emergencia_nombre VARCHAR(150) NOT NULL,
    emergencia_parentesco VARCHAR(80) NOT NULL,
    emergencia_telefono VARCHAR(30) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_menores_usuarios_usuario (usuario_id),
    CONSTRAINT fk_menores_usuarios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS registro_sendero_menores (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    registro_id INT NOT NULL,
    inversion_id INT DEFAULT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    telefono VARCHAR(30) DEFAULT NULL,
    rango_edad VARCHAR(20) NOT NULL,
    es_alergico TINYINT(1) NOT NULL DEFAULT 0,
    alergias_detalle VARCHAR(255) DEFAULT NULL,
    grupo_sanguineo VARCHAR(10) NOT NULL,
    enfermedad VARCHAR(255) NOT NULL,
    seguro_medico VARCHAR(255) NOT NULL,
    experiencia_senderismo VARCHAR(80) NOT NULL,
    emergencia_nombre VARCHAR(150) NOT NULL,
    emergencia_parentesco VARCHAR(80) NOT NULL,
    emergencia_telefono VARCHAR(30) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_registro_menores_registro (registro_id),
    INDEX idx_registro_menores_inversion (inversion_id),
    CONSTRAINT fk_registro_menores_registro FOREIGN KEY (registro_id) REFERENCES registros_senderos(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tarjeta_pago (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    banco VARCHAR(120) NOT NULL,
    cuenta VARCHAR(60) NOT NULL,
    tipo_cuenta VARCHAR(60) NOT NULL,
    cedula VARCHAR(40) NOT NULL,
    correo VARCHAR(160) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    telefono_comprobante VARCHAR(40) NOT NULL,
    nota_importante TEXT NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE registros_senderos ADD COLUMN IF NOT EXISTS asistio TINYINT(1) NOT NULL DEFAULT 0 AFTER estado;
ALTER TABLE registros_senderos ADD COLUMN IF NOT EXISTS fecha_asistencia DATETIME NULL AFTER asistio;
ALTER TABLE registros_senderos ADD COLUMN IF NOT EXISTS asistencia_marcada_por INT NULL AFTER fecha_asistencia;
ALTER TABLE registros_senderos ADD COLUMN IF NOT EXISTS asistencia_notas VARCHAR(255) NULL AFTER asistencia_marcada_por;
ALTER TABLE registros_senderos ADD INDEX IF NOT EXISTS idx_registros_senderos_asistencia (sendero_id, asistio, estado);
ALTER TABLE registros_senderos ADD INDEX IF NOT EXISTS idx_registros_senderos_usuario_asistencia (usuario_id, asistio, fecha_asistencia);

ALTER TABLE registro_sendero_menores ADD COLUMN IF NOT EXISTS inversion_id INT DEFAULT NULL AFTER registro_id;
ALTER TABLE registro_sendero_menores ADD INDEX IF NOT EXISTS idx_registro_menores_inversion (inversion_id);
