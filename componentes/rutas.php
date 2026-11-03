<?php

function sg_route_base_path(): string
{
    return trim((string) (parse_url(BASE_URL, PHP_URL_PATH) ?: '/'), '/');
}

function sg_current_clean_route(): string
{
    $requestPath = trim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/');
    $basePath = sg_route_base_path();

    if ($basePath !== '' && ($requestPath === $basePath || str_starts_with($requestPath, $basePath . '/'))) {
        $requestPath = ltrim(substr($requestPath, strlen($basePath)), '/');
    }

    return trim($requestPath, '/');
}

function sg_public_route_aliases(): array
{
    return [
        '' => 'inicio',
        'instalar' => 'instalar',
        'ingresar' => 'inicio_sesion',
        'crear-cuenta' => 'registro',
        'mi-perfil' => 'mi_perfil',
        'completar-perfil' => 'completar_perfil',
        'sendero' => 'senderos_detalle',
        'registro-sendero' => 'registro_sendero',
        'senderos-visitados' => 'senderos_visitados',
        'recuperar-contrasena' => 'recuperar_password',
        'restablecer-contrasena' => 'restablecer_password',
        'admin' => 'panel_administrativo',
        'admin/finanzas' => 'panel_financiero',
        'admin/reportes' => 'reportes',
        'admin/reportes/contactos' => 'reporte_contacto',
        'admin/reportes/usuarios' => 'reporte_estadisticas_usuarios',
        'admin/reportes/usuarios-por-sendero' => 'reporte_usuarios_sendero',
        'admin/reportes/rentabilidad-por-sendero' => 'reporte_rentabilidad_sendero',
        'admin/reportes/rentabilidad-por-fechas' => 'reporte_rentabilidad_fechas',
        'admin/reportes/encuestas' => 'reporte_encuestas',
        'admin/encuestas/resultados' => 'resultados_encuesta',
    ];
}

function sg_clean_route_for_screen(string $screen): string
{
    foreach (sg_public_route_aliases() as $route => $target) {
        if ($target === $screen) {
            return $route;
        }
    }

    return str_replace('_', '-', $screen);
}

function sg_clean_url(string $path): string
{
    $path = trim($path);
    if ($path === '' || str_starts_with($path, '#') || preg_match('/^(?:https?:)?\/\//i', $path)) {
        return $path === '' ? BASE_URL : $path;
    }

    $parts = parse_url($path);
    $routePath = ltrim((string) ($parts['path'] ?? ''), '/');
    $basePath = sg_route_base_path();
    if ($basePath !== '' && str_starts_with($routePath, $basePath . '/')) {
        $routePath = substr($routePath, strlen($basePath) + 1);
    }

    if (preg_match('#^pantallas/([a-z0-9_]+)\.php$#i', $routePath, $match)) {
        $routePath = sg_clean_route_for_screen(strtolower($match[1]));
    } elseif (preg_match('#^mantenimientos/mantenimiento_([a-z0-9_]+)\.php$#i', $routePath, $match)) {
        $routePath = 'admin/' . str_replace('_', '-', strtolower($match[1]));
    } elseif ($routePath === 'sesion/cerrar_sesion.php') {
        $routePath = 'salir';
    } elseif ($routePath === 'index.php') {
        $routePath = '';
    } else {
        return BASE_URL . ltrim($routePath, '/');
    }

    $url = BASE_URL . ltrim($routePath, '/');
    if (isset($parts['query']) && $parts['query'] !== '') {
        $url .= '?' . $parts['query'];
    }
    if (isset($parts['fragment']) && $parts['fragment'] !== '') {
        $url .= '#' . $parts['fragment'];
    }

    return $url;
}

function sg_resolve_clean_route(string $route): ?string
{
    $route = trim($route, '/');

    if ($route === 'instalar') {
        $file = __DIR__ . '/../instalar.php';
        return is_file($file) ? $file : null;
    }

    $aliases = sg_public_route_aliases();

    if (array_key_exists($route, $aliases)) {
        $file = __DIR__ . '/../pantallas/' . $aliases[$route] . '.php';
        return is_file($file) ? $file : null;
    }

    if ($route === 'salir') {
        $file = __DIR__ . '/../sesion/cerrar_sesion.php';
        return is_file($file) ? $file : null;
    }

    // Todas las areas de configuracion comparten esta misma pantalla y se
    // distinguen mediante el parametro ?seccion=identidad|seo|menu|pie|privacidad.
    if ($route === 'admin/configuracion') {
        $file = __DIR__ . '/../mantenimientos/mantenimiento_configuracion_sitio.php';
        return is_file($file) ? $file : null;
    }

    if (str_starts_with($route, 'admin/')) {
        $section = substr($route, strlen('admin/'));
        if ($section === '' || !preg_match('/^[a-z0-9-]+$/', $section)) {
            return null;
        }

        $file = __DIR__ . '/../mantenimientos/mantenimiento_' . str_replace('-', '_', $section) . '.php';
        return is_file($file) ? $file : null;
    }

    if (!preg_match('/^[a-z0-9-]+$/', $route)) {
        return null;
    }

    $file = __DIR__ . '/../pantallas/' . str_replace('-', '_', $route) . '.php';
    return is_file($file) ? $file : null;
}

function sg_canonical_route_for_legacy_script(): ?string
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return null;
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $screen = pathinfo($scriptName, PATHINFO_FILENAME);

    if (str_contains($scriptName, '/mantenimientos/') && str_starts_with($screen, 'mantenimiento_')) {
        return 'admin/' . str_replace('_', '-', substr($screen, strlen('mantenimiento_')));
    }

    if (str_contains($scriptName, '/pantallas/') && $screen !== 'index') {
        return sg_clean_route_for_screen($screen);
    }

    if (str_contains($scriptName, '/sesion/') && $screen === 'cerrar_sesion') {
        return 'salir';
    }

    return null;
}

function sg_redirect_legacy_route(): void
{
    if (($_SERVER['SG_CLEAN_ROUTE_DISPATCH'] ?? '') === '1') {
        return;
    }

    $route = sg_canonical_route_for_legacy_script();
    if ($route === null) {
        return;
    }

    $destination = BASE_URL . ltrim($route, '/');
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    if ($query !== '') {
        $destination .= '?' . $query;
    }

    header('Location: ' . $destination, true, 301);
    exit;
}
