<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$PERMISO_REQUERIDO = 'interfaz.inicio';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';

function volver_inicio_admin(): void
{
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_inicio.php");
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $_SESSION['inicio_admin_error'] = "Metodo no permitido.";
    volver_inicio_admin();
}
csrf_validate_post(BASE_URL . "mantenimientos/mantenimiento_inicio.php", 'inicio_admin_error');

require_once __DIR__ . '/../bd/conexion.php';

function inicio_admin_text(string $key, int $max, bool $required = true): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    $value = preg_replace('/\s+/', ' ', $value);
    if ($required && $value === '') {
        throw new RuntimeException("Completa todos los campos obligatorios.");
    }
    return substr($value, 0, $max);
}

function inicio_admin_upload(string $field, ?string $actual = null): ?string
{
    if (empty($_FILES[$field]['name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return $actual;
    }

    $permitidos = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $mime = mime_content_type($_FILES[$field]['tmp_name']);
    if (!isset($permitidos[$mime])) {
        throw new RuntimeException("Las imagenes deben ser JPG, PNG o WEBP.");
    }
    if ((int) ($_FILES[$field]['size'] ?? 0) > 4 * 1024 * 1024) {
        throw new RuntimeException("Cada imagen no debe superar 4 MB.");
    }

    $dir = __DIR__ . '/../imagenes/inicio';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $name = $field . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $permitidos[$mime];
    $destino = $dir . '/' . $name;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $destino)) {
        throw new RuntimeException("No se pudo guardar la imagen.");
    }

    return 'imagenes/inicio/' . $name;
}

function inicio_admin_crear_tablas(mysqli $conn): void
{
    // La estructura se gestiona desde scripts_bd/migracion_estructura_configuracion_2026_06_17.sql.
}

try {
    inicio_admin_crear_tablas($conn);
    $accion = trim((string) ($_POST['accion'] ?? ''));

    if ($accion === 'guardar_config') {
        $actual = mysqli_fetch_assoc(mysqli_query($conn, "SELECT hero_imagen, logo_imagen FROM configuracion_inicio WHERE id = 1 LIMIT 1")) ?: [
            'hero_imagen' => 'imagenes/paisajes/hero.jpg',
            'logo_imagen' => 'imagenes/logo/logo_sg.png',
        ];

        $hero = inicio_admin_upload('hero_imagen', $actual['hero_imagen']);
        $logo = inicio_admin_upload('logo_imagen', $actual['logo_imagen']);
        $datos = [
            'hero_titulo' => inicio_admin_text('hero_titulo', 160),
            'hero_subtitulo' => inicio_admin_text('hero_subtitulo', 255),
            'boton_texto' => inicio_admin_text('boton_texto', 80),
            'boton_url' => inicio_admin_text('boton_url', 255),
            'acceso_rapido_texto' => inicio_admin_text('acceso_rapido_texto', 120),
            'acceso_rapido_badge' => inicio_admin_text('acceso_rapido_badge', 40),
            'acceso_rapido_url' => inicio_admin_text('acceso_rapido_url', 255),
            'porque_titulo' => inicio_admin_text('porque_titulo', 160),
            'galeria_titulo' => inicio_admin_text('galeria_titulo', 180),
            'galeria_subtitulo' => inicio_admin_text('galeria_subtitulo', 255),
            'cta_titulo' => inicio_admin_text('cta_titulo', 180),
            'cta_texto' => trim((string) ($_POST['cta_texto'] ?? '')),
            'cta_boton_texto' => inicio_admin_text('cta_boton_texto', 80),
            'cta_boton_url' => inicio_admin_text('cta_boton_url', 255),
        ];
        if ($datos['cta_texto'] === '') {
            throw new RuntimeException("Completa todos los campos obligatorios.");
        }
        $datos['cta_texto'] = substr($datos['cta_texto'], 0, 1200);

        $stmt = mysqli_prepare($conn, "
            INSERT INTO configuracion_inicio
                (id, hero_imagen, logo_imagen, hero_titulo, hero_subtitulo, boton_texto, boton_url, acceso_rapido_texto, acceso_rapido_badge, acceso_rapido_url, porque_titulo, galeria_titulo, galeria_subtitulo, cta_titulo, cta_texto, cta_boton_texto, cta_boton_url)
            VALUES
                (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                hero_imagen = VALUES(hero_imagen),
                logo_imagen = VALUES(logo_imagen),
                hero_titulo = VALUES(hero_titulo),
                hero_subtitulo = VALUES(hero_subtitulo),
                boton_texto = VALUES(boton_texto),
                boton_url = VALUES(boton_url),
                acceso_rapido_texto = VALUES(acceso_rapido_texto),
                acceso_rapido_badge = VALUES(acceso_rapido_badge),
                acceso_rapido_url = VALUES(acceso_rapido_url),
                porque_titulo = VALUES(porque_titulo),
                galeria_titulo = VALUES(galeria_titulo),
                galeria_subtitulo = VALUES(galeria_subtitulo),
                cta_titulo = VALUES(cta_titulo),
                cta_texto = VALUES(cta_texto),
                cta_boton_texto = VALUES(cta_boton_texto),
                cta_boton_url = VALUES(cta_boton_url)
        ");
        mysqli_stmt_bind_param($stmt, 'ssssssssssssssss', $hero, $logo, $datos['hero_titulo'], $datos['hero_subtitulo'], $datos['boton_texto'], $datos['boton_url'], $datos['acceso_rapido_texto'], $datos['acceso_rapido_badge'], $datos['acceso_rapido_url'], $datos['porque_titulo'], $datos['galeria_titulo'], $datos['galeria_subtitulo'], $datos['cta_titulo'], $datos['cta_texto'], $datos['cta_boton_texto'], $datos['cta_boton_url']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['inicio_admin_success'] = "Contenido principal actualizado.";
    } elseif ($accion === 'guardar_tarjeta') {
        $id = (int) ($_POST['id'] ?? 0);
        $icono = preg_replace('/[^a-z0-9-]/i', '', inicio_admin_text('icono', 60));
        $titulo = inicio_admin_text('titulo', 160);
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        if ($descripcion === '') {
            throw new RuntimeException("Completa la descripcion de la tarjeta.");
        }
        $orden = (int) ($_POST['orden'] ?? 0);
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE inicio_tarjetas SET icono = ?, titulo = ?, descripcion = ?, orden = ?, activo = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'sssiii', $icono, $titulo, $descripcion, $orden, $activo, $id);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO inicio_tarjetas (icono, titulo, descripcion, orden, activo) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sssii', $icono, $titulo, $descripcion, $orden, $activo);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['inicio_admin_success'] = "Tarjeta guardada correctamente.";
    } elseif ($accion === 'toggle_tarjeta') {
        $id = (int) ($_POST['id'] ?? 0);
        mysqli_query($conn, "UPDATE inicio_tarjetas SET activo = IF(activo = 1, 0, 1) WHERE id = " . $id);
        $_SESSION['inicio_admin_success'] = "Estado de tarjeta actualizado.";
    } elseif ($accion === 'guardar_imagen') {
        $id = (int) ($_POST['id'] ?? 0);
        $actual = null;
        if ($id > 0) {
            $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT imagen FROM inicio_galeria WHERE id = " . $id . " LIMIT 1"));
            $actual = $row['imagen'] ?? null;
        }
        $imagen = inicio_admin_upload('imagen', $actual);
        if (!$imagen) {
            throw new RuntimeException("Selecciona una imagen.");
        }
        $titulo = inicio_admin_text('titulo', 160, false);
        $orden = (int) ($_POST['orden'] ?? 0);
        $activo = isset($_POST['activo']) ? 1 : 0;
        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE inicio_galeria SET imagen = ?, titulo = ?, orden = ?, activo = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'ssiii', $imagen, $titulo, $orden, $activo, $id);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO inicio_galeria (imagen, titulo, orden, activo) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssii', $imagen, $titulo, $orden, $activo);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['inicio_admin_success'] = "Imagen guardada correctamente.";
    } elseif ($accion === 'toggle_imagen') {
        $id = (int) ($_POST['id'] ?? 0);
        mysqli_query($conn, "UPDATE inicio_galeria SET activo = IF(activo = 1, 0, 1) WHERE id = " . $id);
        $_SESSION['inicio_admin_success'] = "Estado de imagen actualizado.";
    } else {
        throw new RuntimeException("Accion no valida.");
    }
} catch (Throwable $e) {
    $_SESSION['inicio_admin_error'] = APP_DEBUG ? $e->getMessage() : "No se pudo completar la accion.";
}

mysqli_close($conn);
volver_inicio_admin();

