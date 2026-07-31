<?php

if (!function_exists('sg_site_defaults')) {
    function sg_site_defaults(): array
    {
        return [
            'id' => 1,
            'nombre_sitio' => 'Senderismo Go',
            'nombre_corto' => 'Senderismo Go',
            'eslogan' => 'Apasionados por la naturaleza',
            'logo_header' => 'imagenes/logo/logo_sg.png',
            'logo_footer' => 'imagenes/logo/logo_sg.png',
            'favicon' => 'imagenes/logo/logo_sg.png',
            'imagen_compartir' => 'imagenes/paisajes/hero.jpg',
            'meta_descripcion' => 'Senderos, excursiones y experiencias de naturaleza en Republica Dominicana.',
            'login_texto' => 'Iniciar Sesion',
            'footer_descripcion' => 'Creamos experiencias de senderismo que conectan personas, naturaleza y comunidades.',
            'footer_enlaces_titulo' => 'Explora',
            'footer_contacto_titulo' => 'Contacto',
            'footer_copyright' => 'Senderismo Go. Todos los derechos reservados.',
        ];
    }
}

if (!function_exists('sg_site_menu_defaults')) {
    function sg_site_menu_defaults(): array
    {
        return [
            ['codigo' => 'inicio', 'etiqueta' => 'Inicio', 'ruta' => 'pantallas/inicio.php', 'parent_codigo' => null, 'icono' => 'home', 'orden' => 10, 'activo' => 1],
            ['codigo' => 'nosotros', 'etiqueta' => 'Nosotros', 'ruta' => 'pantallas/nosotros.php', 'parent_codigo' => null, 'icono' => 'users', 'orden' => 20, 'activo' => 1],
            ['codigo' => 'senderos', 'etiqueta' => 'Senderos', 'ruta' => 'pantallas/senderos.php', 'parent_codigo' => null, 'icono' => 'map', 'orden' => 30, 'activo' => 1],
            ['codigo' => 'senderos_proximos', 'etiqueta' => 'Proximos senderos', 'ruta' => 'pantallas/senderos.php', 'parent_codigo' => 'senderos', 'icono' => 'calendar', 'orden' => 10, 'activo' => 1],
            ['codigo' => 'senderos_visitados', 'etiqueta' => 'Senderos visitados', 'ruta' => 'pantallas/senderos_visitados.php', 'parent_codigo' => 'senderos', 'icono' => 'check-circle', 'orden' => 20, 'activo' => 1],
            ['codigo' => 'contacto', 'etiqueta' => 'Contacto', 'ruta' => 'pantallas/contacto.php', 'parent_codigo' => null, 'icono' => 'mail', 'orden' => 40, 'activo' => 1],
        ];
    }
}

if (!function_exists('sg_site_table_exists')) {
    function sg_site_table_exists(mysqli $conn, string $table): bool
    {
        $safe = mysqli_real_escape_string($conn, $table);
        $result = mysqli_query($conn, "SHOW TABLES LIKE '{$safe}'");
        return $result && mysqli_num_rows($result) > 0;
    }
}

if (!function_exists('sg_site_open_connection')) {
    function sg_site_open_connection(): ?mysqli
    {
        $conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (!$conn) {
            return null;
        }
        mysqli_set_charset($conn, 'utf8mb4');
        return $conn;
    }
}

if (!function_exists('sg_site_config')) {
    function sg_site_config(?mysqli $conn): array
    {
        $defaults = sg_site_defaults();
        if (!$conn || !sg_site_table_exists($conn, 'configuracion_sitio')) {
            return $defaults;
        }
        $result = mysqli_query($conn, 'SELECT * FROM configuracion_sitio WHERE id = 1 LIMIT 1');
        $row = $result ? mysqli_fetch_assoc($result) : null;
        return $row ? array_merge($defaults, $row) : $defaults;
    }
}

if (!function_exists('sg_site_menu')) {
    function sg_site_menu(?mysqli $conn, bool $onlyActive = true): array
    {
        if (!$conn || !sg_site_table_exists($conn, 'configuracion_menu')) {
            return array_values(array_filter(sg_site_menu_defaults(), static fn(array $item): bool => !$onlyActive || (int) $item['activo'] === 1));
        }
        $where = $onlyActive ? 'WHERE activo = 1' : '';
        $result = mysqli_query($conn, "SELECT * FROM configuracion_menu {$where} ORDER BY parent_codigo IS NOT NULL, orden ASC, id ASC");
        $items = [];
        while ($result && $row = mysqli_fetch_assoc($result)) {
            $items[] = $row;
        }
        return $items ?: sg_site_menu_defaults();
    }
}

if (!function_exists('sg_site_contact')) {
    function sg_site_contact(?mysqli $conn): array
    {
        $defaults = ['ubicacion' => '', 'telefono' => '', 'whatsapp' => '', 'email' => '', 'instagram' => '', 'instagram_url' => ''];
        if (!$conn || !sg_site_table_exists($conn, 'configuracion_contacto')) {
            return $defaults;
        }
        $result = mysqli_query($conn, 'SELECT ubicacion, telefono, whatsapp, email, instagram, instagram_url FROM configuracion_contacto WHERE id = 1 LIMIT 1');
        $row = $result ? mysqli_fetch_assoc($result) : null;
        return $row ? array_merge($defaults, $row) : $defaults;
    }
}

if (!function_exists('sg_site_asset')) {
    function sg_site_asset(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }
        return preg_match('/^https?:\/\//i', $path) ? $path : BASE_URL . ltrim($path, '/');
    }
}

if (!function_exists('sg_site_public_footer_enabled')) {
    function sg_site_public_footer_enabled(): bool
    {
        $allowed = [
            'inicio.php', 'nosotros.php', 'senderos.php', 'senderos_visitados.php',
            'senderos_detalle.php', 'contacto.php', 'registro.php', 'inicio_sesion.php',
            'recuperar_password.php', 'restablecer_password.php', 'registro_sendero.php',
            'completar_perfil.php', 'mi_perfil.php',
        ];
        return in_array(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')), $allowed, true);
    }
}
