<?php

if (!function_exists('sg_site_defaults')) {
    function sg_site_defaults(): array
    {
        return [
            'id' => 1,
            'nombre_sitio' => 'Plataforma',
            'nombre_corto' => 'Plataforma',
            'eslogan' => 'Experiencias al aire libre para compartir.',
            'logo_header' => '',
            'logo_footer' => '',
            'favicon' => '',
            'imagen_compartir' => 'imagenes/paisajes/hero.jpg',
            'meta_descripcion' => 'Actividades, rutas y experiencias organizadas.',
            'login_texto' => 'Iniciar Sesion',
            'footer_descripcion' => 'Creamos experiencias que conectan personas, naturaleza y comunidades.',
            'footer_enlaces_titulo' => 'Explora',
            'footer_contacto_titulo' => 'Contacto',
            'footer_copyright' => 'Todos los derechos reservados.',
            'footer_mostrar_identidad' => 1,
            'footer_mostrar_logo' => 1,
            'footer_mostrar_descripcion' => 1,
            'footer_mostrar_enlaces' => 1,
            'footer_mostrar_contacto' => 1,
            'footer_mostrar_ubicacion' => 1,
            'footer_mostrar_telefono' => 1,
            'footer_mostrar_email' => 1,
            'footer_mostrar_whatsapp' => 1,
            'footer_mostrar_instagram' => 0,
            'footer_mostrar_copyright' => 1,
            'consentimiento_informado_texto' => 'Estoy de acuerdo que: Yo, siendo mayor de edad, en pleno uso de mis facultades y con total capacidad para comprender el contenido de este documento, declaro que he leido y entiendo completamente la informacion proporcionada en esta pagina sobre la actividad en la que participare. Reconozco que se trata de una actividad fisicamente exigente y que conlleva riesgos inherentes a su naturaleza. Estoy consciente de los posibles desafios y riesgos involucrados, incluyendo aquellos relacionados con el esfuerzo fisico, las condiciones del terreno y cualquier otro factor mencionado en la informacion suministrada. Asimismo, acepto que, en caso de emergencia medica, el acceso a asistencia puede estar sujeto a condiciones y tiempos de respuesta variables. Acepto y doy mi consentimiento a recibir asistencia de primeros auxilios por el personal de la directiva de ser necesario. Comprendo que la organizacion y su personal no son responsables de los riesgos que pudiera enfrentar debido a mi participacion o al incumplimiento de las recomendaciones y medidas de seguridad indicadas. Declaro que participo de manera voluntaria, asumiendo plena responsabilidad por mi bienestar y cualquier consecuencia derivada de mi participacion. Finalmente, autorizo el uso y publicacion de imagenes en las que pueda aparecer durante la actividad, siempre que se respete mi integridad y dignidad.',
            'rgpd_texto' => 'Doy mi consentimiento para que esta web almacene la informacion que envio para gestionar mi solicitud y mi participacion en las actividades.',
            'politica_privacidad_texto' => 'La informacion suministrada sera utilizada para gestionar registros, comunicaciones, seguridad y participacion en las actividades. Los datos no se compartiran con terceros salvo obligacion legal o necesidad operativa debidamente justificada.',
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
        mysqli_query($conn, "SET time_zone = '" . APP_DB_TIMEZONE . "'");
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

if (!function_exists('sg_site_identity')) {
    function sg_site_identity(?mysqli $conn = null): array
    {
        $ownConnection = false;
        if (!$conn instanceof mysqli) {
            $conn = sg_site_open_connection();
            $ownConnection = $conn instanceof mysqli;
        }

        $config = sg_site_config($conn);
        if ($ownConnection && $conn instanceof mysqli) {
            mysqli_close($conn);
        }

        $name = trim((string) ($config['nombre_sitio'] ?? '')) ?: 'Plataforma';
        return [
            'nombre' => $name,
            'logo' => sg_site_asset((string) ($config['logo_header'] ?? '')),
        ];
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
        $routeTarget = (string) ($_SERVER['SG_ROUTE_TARGET'] ?? '');
        $script = $routeTarget !== '' ? $routeTarget : (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        return in_array(basename($script), $allowed, true);
    }
}

if (!function_exists('sg_site_admin_sections')) {
    // Cada area: [titulo, descripcion, icono, nombre corto para las pestanas].
    function sg_site_admin_sections(): array
    {
        return [
            'identidad' => ['Identidad de la plataforma', 'Nombre, eslogan, logos e icono del sitio.', 'flag', 'Identidad'],
            'seo' => ['SEO y presentacion externa', 'Informacion para buscadores y enlaces compartidos.', 'search', 'SEO'],
            'menu' => ['Encabezado y menu', 'Etiquetas, orden y visibilidad de la navegacion publica.', 'menu', 'Encabezado y menu'],
            'pie' => ['Pie de pagina', 'Descripcion, bloques visibles y derechos.', 'columns', 'Pie de pagina'],
            'privacidad' => ['Consentimientos y privacidad', 'Textos legales que acepta cada participante.', 'file-text', 'Privacidad'],
        ];
    }
}

if (!function_exists('sg_site_admin_subnav')) {
    // Barra para cruzar entre las areas de configuracion y el panel lateral.
    // $actual es el codigo del area abierta, o 'panel' para el panel lateral.
    function sg_site_admin_subnav(string $actual, bool $puedeConfiguracion, bool $puedePanelLateral): void
    {
        $h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $tabs = [];
        if ($puedeConfiguracion) {
            foreach (sg_site_admin_sections() as $codigo => [, , $icono, $nombre]) {
                $tabs[] = [$codigo, BASE_URL . 'admin/configuracion?seccion=' . rawurlencode($codigo), $icono, $nombre, ''];
            }
        }
        if ($puedePanelLateral) {
            // El panel lateral es otro mantenimiento: se separa del grupo de areas.
            $tabs[] = ['panel', BASE_URL . 'admin/menu-panel', 'sidebar', 'Panel lateral', $puedeConfiguracion ? ' is-separate' : ''];
        }

        echo '<div class="site-config-subnav" role="navigation" aria-label="Navegacion de configuracion">';
        if ($puedeConfiguracion) {
            echo '<a class="site-config-subnav-back" title="Todas las areas" href="' . $h(BASE_URL . 'admin/configuracion') . '">'
                . '<span class="site-config-subnav-back-icon"><i data-feather="grid"></i></span>'
                . '<span class="site-config-subnav-back-text"><small>Volver a</small><strong>Todas las areas</strong></span></a>';
        }
        echo '<div class="site-config-subnav-tabs">';
        foreach ($tabs as [$codigo, $url, $icono, $nombre, $clase]) {
            echo '<a class="site-config-subnav-tab' . $clase . '" href="' . $h($url) . '"'
                . ($codigo === $actual ? ' aria-current="page"' : '') . '>'
                . '<i data-feather="' . $h($icono) . '"></i><span>' . $h($nombre) . '</span></a>';
        }
        echo '</div></div>';
    }
}
