<?php

require_once __DIR__ . '/configuracion_sitio.php';
require_once __DIR__ . '/menu_panel.php';
require_once __DIR__ . '/migraciones.php';

function sg_instalacion_tabla_existe(mysqli $conn, string $tabla): bool
{
    $tabla = mysqli_real_escape_string($conn, $tabla);
    $resultado = mysqli_query($conn, "SHOW TABLES LIKE '{$tabla}'");
    $existe = $resultado && mysqli_num_rows($resultado) === 1;
    if ($resultado) {
        mysqli_free_result($resultado);
    }
    return $existe;
}

function sg_instalacion_total(mysqli $conn, string $tabla): int
{
    if (!sg_instalacion_tabla_existe($conn, $tabla)) {
        return 0;
    }

    $resultado = mysqli_query($conn, "SELECT COUNT(*) AS total FROM `{$tabla}`");
    $fila = $resultado ? mysqli_fetch_assoc($resultado) : null;
    if ($resultado) {
        mysqli_free_result($resultado);
    }
    return (int) ($fila['total'] ?? 0);
}

function sg_instalacion_esta_completa(mysqli $conn): bool
{
    if (!sg_instalacion_tabla_existe($conn, 'sistema_instalacion')) {
        return false;
    }

    $resultado = mysqli_query($conn, 'SELECT id FROM sistema_instalacion WHERE id = 1 LIMIT 1');
    $completa = $resultado && mysqli_num_rows($resultado) === 1;
    if ($resultado) {
        mysqli_free_result($resultado);
    }
    return $completa;
}

function sg_instalacion_estado(mysqli $conn): array
{
    $requeridas = [
        'roles', 'usuarios', 'permisos', 'rol_permiso', 'configuracion_sitio',
        'configuracion_contacto', 'configuracion_inicio', 'configuracion_menu',
        'configuracion_nosotros', 'admin_menu_secciones', 'admin_menu_subsecciones',
        'admin_menu_modulos', 'configuracion_tema', 'tarjeta_pago', 'niveles_dificultad',
        'tipos_terreno', 'tipos_camino_vehiculo', 'anotaciones_importantes',
        'elementos_incluidos', 'contabilidad_metodo_pago', 'contabilidad_categoria_gasto',
        'fin_tipos', 'notificacion_tipos',
    ];
    $faltantes = array_values(array_filter($requeridas, static fn(string $tabla): bool => !sg_instalacion_tabla_existe($conn, $tabla)));

    return [
        'completa' => sg_instalacion_esta_completa($conn),
        'faltantes' => $faltantes,
        'usuarios' => sg_instalacion_total($conn, 'usuarios'),
        'roles' => sg_instalacion_total($conn, 'roles'),
    ];
}

function sg_instalacion_validar(array $datos): array
{
    $limpiar = static fn($valor, int $max): string => mb_substr(trim(preg_replace('/\s+/', ' ', (string) $valor)), 0, $max);
    $datos = [
        'organizacion' => $limpiar($datos['organizacion'] ?? '', 120),
        'nombre_corto' => $limpiar($datos['nombre_corto'] ?? '', 80),
        'eslogan' => $limpiar($datos['eslogan'] ?? '', 180),
        'admin_nombre' => $limpiar($datos['admin_nombre'] ?? '', 100),
        'admin_apellido' => $limpiar($datos['admin_apellido'] ?? '', 100),
        'admin_usuario' => $limpiar($datos['admin_usuario'] ?? '', 50),
        'admin_email' => mb_strtolower($limpiar($datos['admin_email'] ?? '', 100), 'UTF-8'),
        'admin_password' => (string) ($datos['admin_password'] ?? ''),
        'admin_password_confirmacion' => (string) ($datos['admin_password_confirmacion'] ?? ''),
    ];

    $errores = [];
    foreach (['organizacion' => 'nombre de la organizacion', 'nombre_corto' => 'nombre corto', 'admin_nombre' => 'nombre del administrador', 'admin_apellido' => 'apellido del administrador', 'admin_usuario' => 'usuario administrador'] as $campo => $etiqueta) {
        if ($datos[$campo] === '') {
            $errores[] = 'Completa el campo: ' . $etiqueta . '.';
        }
    }
    if (!filter_var($datos['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'Indica un correo valido para el administrador.';
    }
    if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $datos['admin_usuario'])) {
        $errores[] = 'El usuario debe tener de 3 a 50 caracteres y usar solo letras, numeros, punto, guion o guion bajo.';
    }
    if (mb_strlen($datos['admin_password']) < 10) {
        $errores[] = 'La contrasena debe tener al menos 10 caracteres.';
    }
    if (!hash_equals($datos['admin_password'], $datos['admin_password_confirmacion'])) {
        $errores[] = 'La confirmacion de contrasena no coincide.';
    }

    return [$datos, $errores];
}

function sg_instalacion_guardar_unica(mysqli $conn, string $tabla, array $valores): void
{
    $tablas = ['configuracion_sitio', 'configuracion_contacto', 'configuracion_inicio', 'configuracion_nosotros', 'configuracion_tema'];
    if (!in_array($tabla, $tablas, true) || empty($valores)) {
        throw new InvalidArgumentException('Configuracion inicial no valida.');
    }

    $columnas = array_keys($valores);
    $columnasSql = implode(', ', array_map(static fn(string $campo): string => '`' . $campo . '`', $columnas));
    $valoresSql = implode(', ', array_map(static fn($valor): string => "'" . mysqli_real_escape_string($conn, (string) $valor) . "'", array_values($valores)));
    $actualizaciones = implode(', ', array_map(static fn(string $campo): string => "`{$campo}` = VALUES(`{$campo}`)", array_filter($columnas, static fn(string $campo): bool => $campo !== 'id')));
    $sql = "INSERT INTO `{$tabla}` ({$columnasSql}) VALUES ({$valoresSql}) ON DUPLICATE KEY UPDATE {$actualizaciones}";

    if (!mysqli_query($conn, $sql)) {
        throw new RuntimeException('No se pudo guardar la configuracion inicial: ' . mysqli_error($conn));
    }
}

function sg_instalacion_sembrar_configuracion(mysqli $conn, array $datos): void
{
    $organizacion = $datos['organizacion'];
    $nombreCorto = $datos['nombre_corto'];
    $eslogan = $datos['eslogan'] !== '' ? $datos['eslogan'] : 'Experiencias al aire libre para compartir.';

    sg_instalacion_guardar_unica($conn, 'configuracion_sitio', [
        'id' => 1,
        'nombre_sitio' => $organizacion,
        'nombre_corto' => $nombreCorto,
        'eslogan' => $eslogan,
        'logo_header' => '',
        'logo_footer' => '',
        'favicon' => '',
        'imagen_compartir' => 'imagenes/paisajes/hero.jpg',
        'meta_descripcion' => 'Actividades, rutas y experiencias organizadas por ' . $organizacion . '.',
        'login_texto' => 'Iniciar sesion',
        'footer_descripcion' => 'Organizamos experiencias que conectan personas, naturaleza y comunidad.',
        'footer_enlaces_titulo' => 'Explora',
        'footer_contacto_titulo' => 'Contacto',
        'footer_copyright' => $organizacion . '. Todos los derechos reservados.',
        'consentimiento_informado_texto' => 'Confirmo que participo voluntariamente y que conozco las indicaciones de seguridad de la actividad.',
        'rgpd_texto' => 'Autorizo el uso de mis datos para gestionar mi solicitud y participacion en las actividades.',
        'politica_privacidad_texto' => 'La informacion suministrada se utiliza para gestionar registros, comunicaciones y seguridad de las actividades.',
    ]);

    sg_instalacion_guardar_unica($conn, 'configuracion_contacto', [
        'id' => 1,
        'hero_imagen' => 'imagenes/paisajes/hero.jpg',
        'titulo' => 'Hablemos de tu proxima experiencia',
        'subtitulo' => 'Estamos disponibles para responder tus consultas.',
        'horario' => '',
        'ubicacion' => '',
        'telefono' => '',
        'whatsapp' => '',
        'email' => $datos['admin_email'],
        'instagram' => '',
        'instagram_url' => '',
        'texto_formulario' => 'Completa el formulario y nos pondremos en contacto contigo.',
    ]);

    sg_instalacion_guardar_unica($conn, 'configuracion_inicio', [
        'id' => 1,
        'hero_imagen' => 'imagenes/paisajes/hero.jpg',
        'logo_imagen' => '',
        'hero_titulo' => $organizacion,
        'hero_subtitulo' => $eslogan,
        'porque_titulo' => 'Experiencias pensadas para disfrutar y compartir',
        'galeria_titulo' => 'Momentos para recordar',
        'galeria_subtitulo' => 'Descubre las actividades que tenemos preparadas para la comunidad.',
        'cta_titulo' => 'Conoce mas sobre nosotros',
        'cta_texto' => 'Somos una comunidad que crea experiencias al aire libre con organizacion, cuidado y entusiasmo.',
        'cta_boton_texto' => 'Conocenos',
        'cta_boton_url' => 'pantallas/nosotros.php',
    ]);

    sg_instalacion_guardar_unica($conn, 'configuracion_nosotros', [
        'id' => 1,
        'hero_imagen' => 'imagenes/paisajes/hero.jpg',
        'hero_titulo' => 'Una comunidad para vivir nuevas experiencias',
        'hero_subtitulo' => 'Creamos actividades que acercan a las personas, la naturaleza y la aventura.',
        'historia_titulo' => 'Nuestra historia comienza aqui',
        'historia_texto_1' => 'Comparte aqui como nacio la organizacion y que la hace especial.',
        'historia_texto_2' => 'Personaliza esta seccion con el proposito, equipo y valores del cliente.',
        'valores_titulo' => 'Lo que nos mueve',
        'valores_texto' => 'Define los valores que guian cada actividad y cada encuentro.',
        'proceso_titulo' => 'Como organizamos cada experiencia',
        'proceso_texto' => 'Explica de forma clara como las personas pueden participar.',
        'equipo_titulo' => 'Las personas detras de la experiencia',
        'equipo_texto' => 'Presenta al equipo responsable cuando este listo.',
        'cta_titulo' => 'Tu proxima experiencia puede empezar hoy',
        'cta_texto' => 'Conoce las proximas actividades o ponte en contacto con nosotros.',
    ]);

    sg_instalacion_guardar_unica($conn, 'configuracion_tema', [
        'id' => 1,
        'tema' => 'predeterminado',
        'primary_color' => '#1f6b45',
        'primary_dark_color' => '#123d27',
        'accent_color' => '#c2410c',
        'accent_dark_color' => '#9a3412',
        'background_color' => '#f3f6ef',
        'surface_color' => '#ffffff',
        'text_color' => '#17221b',
        'muted_color' => '#617067',
    ]);

    if (!mysqli_query($conn, "
        INSERT INTO tarjeta_pago (
            id, banco, cuenta, tipo_cuenta, cedula, correo, nombre,
            telefono_comprobante, nota_importante, activo
        ) VALUES (1, '', '', '', '', '', '', '', '', 0)
        ON DUPLICATE KEY UPDATE
            banco = VALUES(banco), cuenta = VALUES(cuenta), tipo_cuenta = VALUES(tipo_cuenta),
            cedula = VALUES(cedula), correo = VALUES(correo), nombre = VALUES(nombre),
            telefono_comprobante = VALUES(telefono_comprobante), nota_importante = VALUES(nota_importante),
            activo = VALUES(activo)
    ")) {
        throw new RuntimeException('No se pudo limpiar la tarjeta de pago inicial: ' . mysqli_error($conn));
    }

    $menu = sg_site_menu_defaults();
    $stmt = mysqli_prepare($conn, '
        INSERT INTO configuracion_menu (codigo, etiqueta, ruta, parent_codigo, icono, orden, activo)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE etiqueta = VALUES(etiqueta), ruta = VALUES(ruta), parent_codigo = VALUES(parent_codigo), icono = VALUES(icono), orden = VALUES(orden), activo = VALUES(activo)
    ');
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar el menu inicial.');
    }
    foreach ($menu as $item) {
        $codigo = $item['codigo'];
        $etiqueta = $item['etiqueta'];
        $ruta = $item['ruta'];
        $parent = $item['parent_codigo'];
        $icono = $item['icono'];
        $orden = (int) $item['orden'];
        $activo = (int) $item['activo'];
        mysqli_stmt_bind_param($stmt, 'sssssii', $codigo, $etiqueta, $ruta, $parent, $icono, $orden, $activo);
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new RuntimeException('No se pudo crear el menu inicial: ' . $error);
        }
    }
    mysqli_stmt_close($stmt);
}

function sg_instalacion_crear_administrador(mysqli $conn, array $datos): int
{
    $rol = mysqli_query($conn, 'SELECT id FROM roles WHERE id = 1 LIMIT 1');
    $rolActual = $rol ? mysqli_fetch_assoc($rol) : null;
    if ($rol) {
        mysqli_free_result($rol);
    }
    if ($rolActual) {
        throw new RuntimeException('La base ya contiene el rol administrador. Usa una base limpia para iniciar una nueva instalacion.');
    }

    $stmtRol = mysqli_prepare($conn, 'INSERT INTO roles (id, nombre, descripcion) VALUES (1, ?, ?)');
    $rolNombre = 'Administrador';
    $rolDescripcion = 'Acceso total a la plataforma.';
    mysqli_stmt_bind_param($stmtRol, 'ss', $rolNombre, $rolDescripcion);
    if (!mysqli_stmt_execute($stmtRol)) {
        $error = mysqli_stmt_error($stmtRol);
        mysqli_stmt_close($stmtRol);
        throw new RuntimeException('No se pudo crear el rol administrador: ' . $error);
    }
    mysqli_stmt_close($stmtRol);

    sg_seed_permission_catalog($conn);

    $password = password_hash($datos['admin_password'], PASSWORD_DEFAULT);
    if ($password === false) {
        throw new RuntimeException('No se pudo proteger la contrasena inicial.');
    }
    $stmt = mysqli_prepare($conn, '
        INSERT INTO usuarios (nombre, apellido, user, email, password, rol_id, estado)
        VALUES (?, ?, ?, ?, ?, 1, 1)
    ');
    $nombre = $datos['admin_nombre'];
    $apellido = $datos['admin_apellido'];
    $usuario = $datos['admin_usuario'];
    $email = $datos['admin_email'];
    mysqli_stmt_bind_param($stmt, 'sssss', $nombre, $apellido, $usuario, $email, $password);
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('No se pudo crear el administrador: ' . $error);
    }
    $id = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

function sg_instalacion_asegurar_rol_invitado(mysqli $conn): void
{
    $nombre = 'Invitado';
    $descripcion = 'Acceso publico para personas registradas.';
    $stmt = mysqli_prepare($conn, '
        INSERT INTO roles (nombre, descripcion) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)
    ');
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar el rol Invitado.');
    }
    mysqli_stmt_bind_param($stmt, 'ss', $nombre, $descripcion);
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('No se pudo crear el rol Invitado: ' . $error);
    }
    mysqli_stmt_close($stmt);
}

function sg_instalacion_ejecutar(mysqli $conn, array $datos): void
{
    $estado = sg_instalacion_estado($conn);
    if ($estado['completa'] || $estado['usuarios'] > 0) {
        throw new RuntimeException('Esta plataforma ya tiene usuarios. El instalador permanece bloqueado.');
    }
    if (!empty($estado['faltantes'])) {
        throw new RuntimeException('Falta importar la estructura base. Tablas no encontradas: ' . implode(', ', $estado['faltantes']) . '.');
    }
    if ($estado['roles'] > 0) {
        throw new RuntimeException('La base contiene roles previos. Usa una base limpia para evitar alterar otra instalacion.');
    }

    sg_migraciones_aplicar($conn);

    if (!mysqli_query($conn, '
        CREATE TABLE IF NOT EXISTS sistema_instalacion (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            nombre_sitio VARCHAR(120) NOT NULL,
            usuario_admin_id INT NOT NULL,
            instalada_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ')) {
        throw new RuntimeException('No se pudo preparar el control de instalacion: ' . mysqli_error($conn));
    }

    mysqli_begin_transaction($conn);
    try {
        sg_instalacion_sembrar_configuracion($conn, $datos);
        $adminId = sg_instalacion_crear_administrador($conn, $datos);
        sg_instalacion_asegurar_rol_invitado($conn);
        sg_admin_menu_seed($conn, sg_admin_menu_default_groups(), false);

        $stmt = mysqli_prepare($conn, 'INSERT INTO sistema_instalacion (id, nombre_sitio, usuario_admin_id) VALUES (1, ?, ?)');
        $organizacion = $datos['organizacion'];
        mysqli_stmt_bind_param($stmt, 'si', $organizacion, $adminId);
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new RuntimeException('No se pudo cerrar la instalacion: ' . $error);
        }
        mysqli_stmt_close($stmt);
        mysqli_commit($conn);
    } catch (Throwable $error) {
        mysqli_rollback($conn);
        throw $error;
    }
}
