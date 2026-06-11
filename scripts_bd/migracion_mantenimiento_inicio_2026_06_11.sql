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

INSERT INTO inicio_tarjetas (icono, titulo, descripcion, orden, activo)
SELECT 'map', 'Rutas Exclusivas y Seguras', 'Explora senderos cuidadosamente seleccionados para ofrecerte las mejores vistas y experiencias, con guias expertos que garantizan tu seguridad en todo momento.', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM inicio_tarjetas);

INSERT INTO inicio_tarjetas (icono, titulo, descripcion, orden, activo)
SELECT 'users', 'Experiencia para Todos los Niveles', 'Ofrecemos rutas adaptadas a principiantes y expertos, asegurando que cada aventura se ajuste a tu nivel y disfrutes sin preocupaciones.', 2, 1
WHERE (SELECT COUNT(*) FROM inicio_tarjetas) = 1;

INSERT INTO inicio_tarjetas (icono, titulo, descripcion, orden, activo)
SELECT 'image', 'Conexion Autentica con la Naturaleza', 'Mas que un recorrido, nuestras experiencias te sumergen en la naturaleza con actividades de observacion, fotografia y momentos de relajacion en entornos unicos.', 3, 1
WHERE (SELECT COUNT(*) FROM inicio_tarjetas) = 2;

INSERT INTO inicio_galeria (imagen, titulo, orden, activo)
SELECT 'imagenes/paisajes/img1.jpg', 'Paisaje 1', 1, 1 WHERE NOT EXISTS (SELECT 1 FROM inicio_galeria);
INSERT INTO inicio_galeria (imagen, titulo, orden, activo)
SELECT 'imagenes/paisajes/img2.jpg', 'Paisaje 2', 2, 1 WHERE (SELECT COUNT(*) FROM inicio_galeria) = 1;
INSERT INTO inicio_galeria (imagen, titulo, orden, activo)
SELECT 'imagenes/paisajes/img3.jpg', 'Paisaje 3', 3, 1 WHERE (SELECT COUNT(*) FROM inicio_galeria) = 2;
INSERT INTO inicio_galeria (imagen, titulo, orden, activo)
SELECT 'imagenes/paisajes/img4.jpg', 'Paisaje 4', 4, 1 WHERE (SELECT COUNT(*) FROM inicio_galeria) = 3;
INSERT INTO inicio_galeria (imagen, titulo, orden, activo)
SELECT 'imagenes/paisajes/img5.jpg', 'Paisaje 5', 5, 1 WHERE (SELECT COUNT(*) FROM inicio_galeria) = 4;
INSERT INTO inicio_galeria (imagen, titulo, orden, activo)
SELECT 'imagenes/paisajes/img6.jpg', 'Paisaje 6', 6, 1 WHERE (SELECT COUNT(*) FROM inicio_galeria) = 5;
INSERT INTO inicio_galeria (imagen, titulo, orden, activo)
SELECT 'imagenes/paisajes/img7.jpg', 'Paisaje 7', 7, 1 WHERE (SELECT COUNT(*) FROM inicio_galeria) = 6;
INSERT INTO inicio_galeria (imagen, titulo, orden, activo)
SELECT 'imagenes/paisajes/img8.jpg', 'Paisaje 8', 8, 1 WHERE (SELECT COUNT(*) FROM inicio_galeria) = 7;
INSERT INTO inicio_galeria (imagen, titulo, orden, activo)
SELECT 'imagenes/paisajes/img9.jpg', 'Paisaje 9', 9, 1 WHERE (SELECT COUNT(*) FROM inicio_galeria) = 8;
INSERT INTO inicio_galeria (imagen, titulo, orden, activo)
SELECT 'imagenes/paisajes/img10.jpg', 'Paisaje 10', 10, 1 WHERE (SELECT COUNT(*) FROM inicio_galeria) = 9;
