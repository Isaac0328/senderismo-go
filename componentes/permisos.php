<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/rutas.php';
require_once __DIR__ . '/cargos.php';

if (!function_exists('sg_permission_catalog')) {
    function sg_permission_catalog(): array
    {
        return [
            [
                'label' => 'Panel',
                'title' => 'Acceso general',
                'icon' => 'layout',
                'items' => [
                    ['panel.dashboard', 'Panel general', 'Ver indicadores, agenda y accesos rapidos del panel.', 'layout', 'pantallas/panel_administrativo.php'],
                    ['panel.menu_panel', 'Configuracion del panel', 'Organizar secciones, subsecciones y modulos visibles del panel administrativo.', 'sidebar', 'mantenimientos/mantenimiento_menu_panel.php'],
                    ['panel.notificaciones', 'Configuracion de avisos', 'Activar avisos y definir su nivel de importancia.', 'bell', 'mantenimientos/mantenimiento_notificaciones.php'],
                    ['panel.tarjetas', 'Tarjetas del panel', 'Elegir que tarjetas del panel se muestran, su orden y para que roles.', 'layout', 'mantenimientos/mantenimiento_panel_tarjetas.php'],
                    ['sistema.actualizaciones', 'Actualizaciones del sistema', 'Revisar y aplicar cambios tecnicos pendientes de forma controlada.', 'refresh-cw', 'mantenimientos/mantenimiento_actualizaciones.php'],
                ],
            ],
            [
                'label' => 'Contenido',
                'title' => 'Interfaz publica',
                'icon' => 'globe',
                'items' => [
                    ['interfaz.configuracion_general', 'Configuracion general', 'Administrar identidad, menu, SEO, consentimientos y pie de pagina.', 'settings', 'mantenimientos/mantenimiento_configuracion_sitio.php'],
                    ['interfaz.inicio', 'Inicio', 'Editar portada, tarjetas, galeria y llamados.', 'home', 'mantenimientos/mantenimiento_inicio.php'],
                    ['interfaz.nosotros', 'Nosotros', 'Editar historia, valores, pasos, indicadores y equipo.', 'users', 'mantenimientos/mantenimiento_nosotros.php'],
                    ['interfaz.contacto', 'Contacto', 'Editar redes, telefonos, ubicacion y bloques de contacto.', 'phone-call', 'mantenimientos/mantenimiento_contacto.php'],
                    ['interfaz.tema', 'Tema visual', 'Editar paletas, colores globales y personalizacion.', 'sliders', 'mantenimientos/mantenimiento_tema.php'],
                ],
            ],
            [
                'label' => 'Usuarios',
                'title' => 'Seguridad y accesos',
                'icon' => 'shield',
                'items' => [
                    ['usuarios.usuarios', 'Usuarios', 'Crear, editar y administrar datos de usuarios.', 'user-check', 'mantenimientos/mantenimiento_usuarios.php'],
                    ['usuarios.sin_cuenta', 'Usuarios sin cuenta', 'Personas inscritas a mano: crear, editar, unir y reconciliar con su cuenta.', 'user-x', 'mantenimientos/mantenimiento_usuarios_sin_cuenta.php'],
                    ['usuarios.pasaporte', 'Pasaporte senderista', 'Administrar niveles, insignias y progreso.', 'award', 'mantenimientos/mantenimiento_pasaporte.php'],
                    ['usuarios.roles', 'Roles', 'Crear y editar perfiles administrativos.', 'key', 'mantenimientos/mantenimiento_roles.php'],
                    ['usuarios.permisos_roles', 'Permisos por rol', 'Distribuir por rol las ventanas visibles del panel.', 'lock', 'mantenimientos/mantenimiento_permisos_roles.php'],
                ],
            ],
            [
                'label' => 'Operaciones',
                'title' => 'Senderos y logistica',
                'icon' => 'map-pin',
                'items' => [
                    ['operaciones.senderos', 'Senderos', 'Crear y editar rutas, fechas, inversiones e imagenes.', 'map', 'mantenimientos/mantenimiento_senderos.php'],
                    ['operaciones.usuarios_senderos', 'Usuarios por sendero', 'Administrar reservas y participantes por ruta.', 'users', 'mantenimientos/mantenimiento_usuarios_senderos.php'],
                    ['operaciones.asistencia', 'Asistencia', 'Marcar quienes asistieron realmente.', 'check-square', 'mantenimientos/mantenimiento_asistencia_senderos.php'],
                    ['operaciones.encuestas', 'Encuestas', 'Crear, enviar y analizar encuestas de satisfaccion.', 'message-square', 'mantenimientos/mantenimiento_encuestas.php'],
                    ['operaciones.puntos_encuentro', 'Puntos de encuentro', 'Administrar puntos reutilizables y ubicaciones.', 'map', 'mantenimientos/mantenimiento_puntos_encuentro.php'],
                    ['operaciones.detalles', 'Catálogos del sendero', 'Administrar terrenos, anotaciones, incluye y dificultad.', 'file-text', 'mantenimientos/mantenimiento_detalles.php'],
                    ['operaciones.provincias', 'Provincias', 'Administrar las provincias de Republica Dominicana.', 'map-pin', 'mantenimientos/mantenimiento_provincias.php'],
                ],
            ],
            [
                'label' => 'Finanzas',
                'title' => 'Contabilidad y pagos',
                'icon' => 'dollar-sign',
                'items' => [
                    ['finanzas.panel', 'Panel financiero', 'Consultar indicadores, tendencias, alertas y rentabilidad consolidada.', 'bar-chart-2', 'pantallas/panel_financiero.php'],
                    ['finanzas.cuentas_usuarios', 'Estado de cuenta por usuario', 'Consultar cobros, descuentos, balances y creditos por participante.', 'users', 'mantenimientos/mantenimiento_cuentas_usuarios.php'],
                    ['finanzas.balances', 'Balances y cuentas', 'Gestionar cuentas, movimientos, pagos programados, cuadres y catalogos.', 'briefcase', 'mantenimientos/mantenimiento_balances.php'],
                    ['finanzas.cierres', 'Cerrar cuentas', 'Autorizar cierres de periodos conciliados en balances.', 'lock', 'mantenimientos/mantenimiento_balances.php?tab=cuadres'],
                    ['finanzas.tarjeta_pago', 'Tarjeta de pago', 'Editar datos bancarios visibles en el detalle.', 'credit-card', 'mantenimientos/mantenimiento_tarjeta_pago.php'],
                    ['finanzas.categorias_gasto', 'Categorias de gasto', 'Clasificar costos operativos.', 'folder', 'mantenimientos/mantenimiento_categoria_gasto.php'],
                    ['finanzas.gastos_catalogo', 'Gastos catalogo', 'Administrar costos frecuentes.', 'tag', 'mantenimientos/mantenimiento_gastos.php'],
                    ['finanzas.gastos_sendero', 'Gastos por sendero', 'Registrar costos reales por ruta.', 'shopping-bag', 'mantenimientos/mantenimiento_gastos_sendero.php'],
                    ['finanzas.metodos_pago', 'Metodos de pago', 'Administrar formas de cobro.', 'briefcase', 'mantenimientos/mantenimiento_metodo_pago.php'],
                    ['finanzas.estados_ingresos', 'Estados de ingresos', 'Personalizar la presentacion de estados financieros sin alterar sus reglas.', 'tag', 'mantenimientos/mantenimiento_estados_ingreso.php'],
                    ['finanzas.ingresos_sendero', 'Ingresos por sendero', 'Registrar pagos, creditos, cortesias, exentos y saldos.', 'trending-up', 'mantenimientos/mantenimiento_ingresos_sendero.php'],
                ],
            ],
            [
                'label' => 'Informes',
                'title' => 'Reportes y analisis',
                'icon' => 'bar-chart-2',
                'items' => [
                    ['reportes.usuarios', 'Reporte de Usuarios', 'Consultar ranking, kilometraje y perfil de usuarios.', 'users', 'pantallas/reporte_estadisticas_usuarios.php'],
                    ['reportes.actividad', 'Reporte de Actividad', 'Ver movimiento reciente de la plataforma.', 'activity', 'pantallas/reportes.php#actividad'],
                    ['reportes.contactos', 'Contactos Recibidos', 'Consultar solicitudes enviadas desde la web.', 'mail', 'pantallas/reporte_contacto.php'],
                    ['reportes.senderos_galeria', 'Senderos y Galeria', 'Consultar rutas, imagenes y comportamiento general.', 'git-branch', 'pantallas/reportes.php#senderos'],
                    ['reportes.usuarios_sendero', 'Usuarios por Sendero', 'Consultar participantes, salud, emergencia y menores.', 'user', 'pantallas/reporte_usuarios_sendero.php'],
                    ['reportes.rentabilidad_sendero', 'Rentabilidad por Sendero', 'Consultar ingresos, gastos, utilidad y margen por ruta.', 'trending-up', 'pantallas/reporte_rentabilidad_sendero.php'],
                    ['reportes.rentabilidad_fechas', 'Rentabilidad por Fechas', 'Consultar resumen financiero por periodo.', 'calendar', 'pantallas/reporte_rentabilidad_fechas.php'],
                    ['reportes.encuestas', 'Reporte de Encuestas', 'Consultar envios, respuestas y participacion por sendero, fecha y participante.', 'message-square', 'pantallas/reporte_encuestas.php'],
                ],
            ],
        ];
    }
}

if (!function_exists('sg_permission_flat_catalog')) {
    function sg_permission_flat_catalog(): array
    {
        $flat = [];
        foreach (sg_permission_catalog() as $group) {
            foreach ($group['items'] as $item) {
                $flat[$item[0]] = [
                    'codigo' => $item[0],
                    'nombre' => $item[1],
                    'descripcion' => $item[2],
                    'icono' => $item[3],
                    'ruta' => $item[4],
                    'grupo' => $group['title'],
                    'label' => $group['label'],
                ];
            }
        }

        return $flat;
    }
}

if (!function_exists('sg_permission_actions')) {
    function sg_permission_actions(): array
    {
        return [
            'ver' => 'Ver',
            'agregar' => 'Agregar',
            'editar' => 'Editar',
            'eliminar' => 'Eliminar',
        ];
    }
}

if (!function_exists('sg_permission_actions_for_code')) {
    function sg_permission_actions_for_code(string $code): array
    {
        if ($code === 'panel.dashboard' || $code === 'finanzas.panel' || $code === 'finanzas.cierres' || $code === 'finanzas.cuentas_usuarios') {
            return ['ver'];
        }

        if (str_starts_with($code, 'reportes.')) {
            return $code === 'reportes.contactos' ? ['ver', 'editar', 'eliminar'] : ['ver'];
        }

        if (in_array($code, [
            'interfaz.configuracion_general',
            'interfaz.tema',
            'usuarios.permisos_roles',
            'operaciones.asistencia',
            'operaciones.encuestas',
            'finanzas.tarjeta_pago',
            'finanzas.gastos_sendero',
            'finanzas.ingresos_sendero',
        ], true)) {
            return ['ver', 'editar'];
        }

        if ($code === 'operaciones.provincias') {
            return ['ver', 'agregar', 'editar'];
        }

        return array_keys(sg_permission_actions());
    }
}

if (!function_exists('sg_permission_action_table_exists')) {
    function sg_permission_action_table_exists(mysqli $conn): bool
    {
        static $cache = [];
        $key = spl_object_id($conn);
        if (!array_key_exists($key, $cache)) {
            $res = @mysqli_query($conn, "SHOW TABLES LIKE 'rol_permiso_accion'");
            $cache[$key] = $res && mysqli_num_rows($res) > 0;
            if ($res) {
                mysqli_free_result($res);
            }
        }
        return $cache[$key];
    }
}

if (!function_exists('sg_role_permission_actions')) {
    function sg_role_permission_actions(mysqli $conn, int $rolId): array
    {
        $catalog = sg_permission_flat_catalog();
        if ($rolId <= 0) {
            return [];
        }

        if (sg_is_admin_role($rolId)) {
            $all = [];
            foreach ($catalog as $code => $_item) {
                $all[$code] = sg_permission_actions_for_code($code);
            }
            return $all;
        }

        $basePermissions = sg_role_permissions($conn, $rolId);
        if (!sg_permission_action_table_exists($conn)) {
            $legacy = [];
            foreach ($basePermissions as $code) {
                if (isset($catalog[$code])) {
                    $legacy[$code] = sg_permission_actions_for_code($code);
                }
            }
            return $legacy;
        }

        $stmt = mysqli_prepare($conn, "
            SELECT p.nombre AS codigo, rpa.accion, rpa.permitido
            FROM rol_permiso_accion rpa
            INNER JOIN rol_permiso rp
                ON rp.rol_id = rpa.rol_id
               AND rp.permiso_id = rpa.permiso_id
            INNER JOIN permisos p ON p.id = rpa.permiso_id
            WHERE rpa.rol_id = ?
        ");
        $actions = [];
        $configured = [];
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $rolId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($res && $row = mysqli_fetch_assoc($res)) {
                $code = (string) $row['codigo'];
                $action = (string) $row['accion'];
                $configured[$code] = true;
                if ((int) $row['permitido'] === 1
                    && isset($catalog[$code])
                    && in_array($action, sg_permission_actions_for_code($code), true)) {
                    $actions[$code][] = $action;
                }
            }
            mysqli_stmt_close($stmt);
        }

        foreach ($basePermissions as $code) {
            if (isset($catalog[$code]) && !isset($configured[$code])) {
                $actions[$code] = sg_permission_actions_for_code($code);
            }
        }

        foreach ($actions as $code => $values) {
            $actions[$code] = array_values(array_unique($values));
        }
        return $actions;
    }
}

if (!function_exists('sg_has_permission_action')) {
    function sg_has_permission_action(mysqli $conn, string|array $codes, string $action, ?int $rolId = null): bool
    {
        if ($rolId === null) {
            foreach (sg_session_role_ids($conn) as $id) {
                if (sg_has_permission_action($conn, $codes, $action, $id)) {
                    return true;
                }
            }
            return false;
        }
        if (sg_is_admin_role($rolId)) {
            return true;
        }

        $action = strtolower(trim($action));
        if (!isset(sg_permission_actions()[$action])) {
            return false;
        }

        $actions = sg_role_permission_actions($conn, $rolId);
        foreach (array_values(array_filter((array) $codes)) as $code) {
            if (sg_has_permission($conn, $code, $rolId)
                && in_array($action, $actions[$code] ?? [], true)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('sg_require_permission_action')) {
    function sg_require_permission_action(mysqli $conn, string|array $codes, string $action, string $redirect = ''): void
    {
        if (sg_has_permission_action($conn, $codes, $action)) {
            return;
        }

        $_SESSION['error_message'] = 'No tienes permiso para ' . $action . ' registros en este modulo.';
        header('Location: ' . ($redirect !== '' ? $redirect : sg_clean_url('pantallas/panel_administrativo.php')));
        exit;
    }
}

if (!function_exists('sg_permission_action_from_request')) {
    function sg_permission_action_from_request(array $data, string|array $codes = []): string
    {
        $codeList = (array) $codes;
        $raw = strtolower(trim((string) ($data['action'] ?? $data['accion'] ?? '')));
        if (preg_match('/delete|eliminar|borrar|remove/', $raw)) {
            return 'eliminar';
        }
        if (preg_match('/agregar|crear|nuevo|guardar_imagen/', $raw)) {
            return 'agregar';
        }
        if (array_intersect($codeList, [
            'interfaz.configuracion_general',
            'interfaz.tema',
            'operaciones.asistencia',
            'finanzas.tarjeta_pago',
            'finanzas.gastos_sendero',
            'finanzas.ingresos_sendero',
        ])) {
            return 'editar';
        }
        if (preg_match('/toggle|activar|inactivar|cancelar|reactivar|editar|update|reorder|guardar_config|save_permissions/', $raw)) {
            return 'editar';
        }

        $id = (int) ($data['id'] ?? $data['registro_id'] ?? 0);
        if ($raw === 'save' || str_starts_with($raw, 'guardar')) {
            return $id > 0 ? 'editar' : 'agregar';
        }

        return $id > 0 ? 'editar' : 'agregar';
    }
}

if (!function_exists('sg_permission_route_map')) {
    function sg_permission_route_map(): array
    {
        return [
            'mantenimientos/mantenimiento_cuentas_usuarios.php' => ['finanzas.cuentas_usuarios'],
            'procesos/proceso_exportar_cuentas_usuarios.php' => ['finanzas.cuentas_usuarios'],
            'mantenimientos/mantenimiento_provincias.php' => ['operaciones.provincias'],
            'procesos/proceso_provincias.php' => ['operaciones.provincias'],
            'pantallas/panel_administrativo.php' => ['panel.dashboard'],
            'mantenimientos/mantenimiento_menu_panel.php' => ['panel.menu_panel'],
            'procesos/proceso_menu_panel.php' => ['panel.menu_panel'],
            'mantenimientos/mantenimiento_actualizaciones.php' => ['sistema.actualizaciones'],
            'procesos/proceso_actualizaciones_sistema.php' => ['sistema.actualizaciones'],
            'mantenimientos/mantenimiento_notificaciones.php' => ['panel.notificaciones'],
            'procesos/proceso_mantenimiento_notificaciones.php' => ['panel.notificaciones'],
            'mantenimientos/mantenimiento_panel_tarjetas.php' => ['panel.tarjetas'],
            'procesos/proceso_panel_tarjetas.php' => ['panel.tarjetas'],
            'mantenimientos/mantenimiento_configuracion_sitio.php' => ['interfaz.configuracion_general'],
            'procesos/proceso_configuracion_sitio.php' => ['interfaz.configuracion_general'],
            'mantenimientos/mantenimiento_inicio.php' => ['interfaz.inicio'],
            'procesos/proceso_mantenimiento_inicio.php' => ['interfaz.inicio'],
            'mantenimientos/mantenimiento_nosotros.php' => ['interfaz.nosotros'],
            'procesos/proceso_mantenimiento_nosotros.php' => ['interfaz.nosotros'],
            'mantenimientos/mantenimiento_contacto.php' => ['interfaz.contacto'],
            'procesos/proceso_mantenimiento_contacto.php' => ['interfaz.contacto'],
            'mantenimientos/mantenimiento_tema.php' => ['interfaz.tema'],
            'procesos/proceso_mantenimiento_tema.php' => ['interfaz.tema'],
            'mantenimientos/mantenimiento_usuarios.php' => ['usuarios.usuarios'],
            'procesos/proceso_usuarios.php' => ['usuarios.usuarios'],
            'mantenimientos/mantenimiento_usuarios_sin_cuenta.php' => ['usuarios.sin_cuenta'],
            'procesos/proceso_usuarios_sin_cuenta.php' => ['usuarios.sin_cuenta'],
            'mantenimientos/mantenimiento_pasaporte.php' => ['usuarios.pasaporte'],
            'procesos/proceso_pasaporte.php' => ['usuarios.pasaporte'],
            'mantenimientos/mantenimiento_roles.php' => ['usuarios.roles'],
            'mantenimientos/mantenimiento_permisos_roles.php' => ['usuarios.permisos_roles'],
            'procesos/proceso_roles.php' => ['usuarios.roles', 'usuarios.permisos_roles'],
            'mantenimientos/mantenimiento_senderos.php' => ['operaciones.senderos'],
            'procesos/proceso_senderos.php' => ['operaciones.senderos'],
            'mantenimientos/mantenimiento_usuarios_senderos.php' => ['operaciones.usuarios_senderos'],
            'procesos/proceso_usuarios_senderos.php' => ['operaciones.usuarios_senderos', 'operaciones.asistencia'],
            'mantenimientos/mantenimiento_asistencia_senderos.php' => ['operaciones.asistencia'],
            'procesos/proceso_asistencia_senderos.php' => ['operaciones.asistencia'],
            'mantenimientos/mantenimiento_encuestas.php' => ['operaciones.encuestas'],
            'procesos/proceso_encuestas.php' => ['operaciones.encuestas'],
            'mantenimientos/mantenimiento_puntos_encuentro.php' => ['operaciones.puntos_encuentro'],
            'procesos/proceso_puntos_encuentro.php' => ['operaciones.puntos_encuentro'],
            'mantenimientos/mantenimiento_detalles.php' => ['operaciones.detalles'],
            'procesos/proceso_detalles.php' => ['operaciones.detalles'],
            'pantallas/panel_financiero.php' => ['finanzas.panel'],
            'mantenimientos/mantenimiento_tarjeta_pago.php' => ['finanzas.tarjeta_pago'],
            'procesos/proceso_tarjeta_pago.php' => ['finanzas.tarjeta_pago'],
            'mantenimientos/mantenimiento_categoria_gasto.php' => ['finanzas.categorias_gasto'],
            'procesos/proceso_categoria_gasto.php' => ['finanzas.categorias_gasto'],
            'mantenimientos/mantenimiento_gastos.php' => ['finanzas.gastos_catalogo'],
            'procesos/proceso_gastos.php' => ['finanzas.gastos_catalogo'],
            'mantenimientos/mantenimiento_gastos_sendero.php' => ['finanzas.gastos_sendero'],
            'procesos/proceso_gastos_sendero.php' => ['finanzas.gastos_sendero'],
            'mantenimientos/mantenimiento_metodo_pago.php' => ['finanzas.metodos_pago'],
            'procesos/proceso_metodo_pago.php' => ['finanzas.metodos_pago'],
            'mantenimientos/mantenimiento_estados_ingreso.php' => ['finanzas.estados_ingresos'],
            'procesos/proceso_estados_ingreso.php' => ['finanzas.estados_ingresos'],
            'mantenimientos/mantenimiento_ingresos_sendero.php' => ['finanzas.ingresos_sendero'],
            'procesos/proceso_ingresos_sendero.php' => ['finanzas.ingresos_sendero'],
            'pantallas/reportes.php' => ['reportes.usuarios', 'reportes.actividad', 'reportes.senderos_galeria'],
            'pantallas/reporte_estadisticas_usuarios.php' => ['reportes.usuarios'],
            'pantallas/reporte_contacto.php' => ['reportes.contactos'],
            'procesos/proceso_reporte_contacto.php' => ['reportes.contactos'],
            'pantallas/reporte_usuarios_sendero.php' => ['reportes.usuarios_sendero'],
            'procesos/proceso_exportar_usuarios_sendero.php' => ['reportes.usuarios_sendero'],
            'pantallas/reporte_rentabilidad_sendero.php' => ['reportes.rentabilidad_sendero'],
            'pantallas/reporte_rentabilidad_fechas.php' => ['reportes.rentabilidad_fechas'],
            'pantallas/reporte_encuestas.php' => ['reportes.encuestas'],
        ];
    }
}

if (!function_exists('sg_permission_current_path')) {
    function sg_permission_current_path(): string
    {
        $routeTarget = (string) ($_SERVER['SG_ROUTE_TARGET'] ?? '');
        if ($routeTarget !== '') {
            $projectRoot = str_replace('\\', '/', dirname(__DIR__)) . '/';
            $targetPath = str_replace('\\', '/', $routeTarget);
            if (str_starts_with($targetPath, $projectRoot)) {
                return strtolower(substr($targetPath, strlen($projectRoot)));
            }
        }

        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
        $base = (string) (parse_url(BASE_URL, PHP_URL_PATH) ?: '/');
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $base = trim(str_replace('\\', '/', $base), '/');

        if ($base !== '' && str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base) + 1);
        }

        return strtolower($path);
    }
}

if (!function_exists('sg_permission_codes_for_current_route')) {
    function sg_permission_codes_for_current_route(): array
    {
        $path = sg_permission_current_path();
        $map = sg_permission_route_map();
        return $map[$path] ?? [];
    }
}

if (!function_exists('sg_is_admin_role')) {
    function sg_is_admin_role(?int $rolId = null): bool
    {
        $rolId = $rolId ?? (int) ($_SESSION['usuario_rol_id'] ?? 0);
        return $rolId === 1;
    }
}

if (!function_exists('sg_role_permissions')) {
    function sg_role_permissions(mysqli $conn, int $rolId): array
    {
        if ($rolId <= 0) {
            return [];
        }

        if (sg_is_admin_role($rolId)) {
            return array_keys(sg_permission_flat_catalog());
        }

        $stmt = mysqli_prepare($conn, "
            SELECT p.nombre
            FROM rol_permiso rp
            INNER JOIN permisos p ON p.id = rp.permiso_id
            WHERE rp.rol_id = ?
        ");
        if (!$stmt) {
            return [];
        }

        mysqli_stmt_bind_param($stmt, 'i', $rolId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $codes = [];
        while ($res && $row = mysqli_fetch_assoc($res)) {
            $codes[] = (string) $row['nombre'];
        }
        mysqli_stmt_close($stmt);

        return array_values(array_unique($codes));
    }
}

// Cargos: ver componentes/cargos.php. El rol de la sesion y los cargos del usuario se suman.
// Rol de la sesion mas los cargos del usuario: con cualquiera de ellos que tenga el permiso, se tiene.
if (!function_exists('sg_session_role_ids')) {
    function sg_session_role_ids(mysqli $conn): array
    {
        $ids = [(int) ($_SESSION['usuario_rol_id'] ?? 0)];
        foreach (sg_cargos_de_usuario($conn, (int) ($_SESSION['usuario_id'] ?? 0)) as $cargoId) {
            $ids[] = $cargoId;
        }
        return array_values(array_unique(array_filter($ids)));
    }
}

if (!function_exists('sg_session_permissions')) {
    function sg_session_permissions(mysqli $conn): array
    {
        $codes = [];
        foreach (sg_session_role_ids($conn) as $id) {
            $codes = array_merge($codes, sg_role_permissions($conn, $id));
        }
        return array_values(array_unique($codes));
    }
}

if (!function_exists('sg_has_permission')) {
    function sg_has_permission(mysqli $conn, string|array $codes, ?int $rolId = null): bool
    {
        if ($rolId === null) {
            foreach (sg_session_role_ids($conn) as $id) {
                if (sg_has_permission($conn, $codes, $id)) {
                    return true;
                }
            }
            return false;
        }
        if (sg_is_admin_role($rolId)) {
            return true;
        }

        $codes = array_values(array_filter((array) $codes));
        if (empty($codes)) {
            return false;
        }

        $perms = sg_role_permissions($conn, $rolId);
        return count(array_intersect($codes, $perms)) > 0;
    }
}

if (!function_exists('sg_require_permission')) {
    function sg_require_permission(mysqli $conn, string|array $codes, string $redirect = ''): void
    {
        if (sg_has_permission($conn, $codes)) {
            return;
        }

        $_SESSION['error_message'] = 'No tienes permiso para acceder a esta seccion.';
        header('Location: ' . ($redirect !== '' ? $redirect : sg_clean_url('pantallas/inicio.php')));
        exit;
    }
}

if (!function_exists('sg_seed_permission_catalog')) {
    function sg_seed_permission_catalog(mysqli $conn): void
    {
        $flat = sg_permission_flat_catalog();
        $stmt = mysqli_prepare($conn, "
            INSERT INTO permisos (nombre, descripcion)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)
        ");
        if (!$stmt) {
            return;
        }

        foreach ($flat as $code => $permiso) {
            $description = $permiso['grupo'] . ' - ' . $permiso['descripcion'];
            mysqli_stmt_bind_param($stmt, 'ss', $code, $description);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);

        mysqli_query($conn, "
            INSERT IGNORE INTO rol_permiso (rol_id, permiso_id)
            SELECT 1, id
            FROM permisos
            WHERE nombre IN ('" . implode("','", array_map(static fn ($v) => mysqli_real_escape_string($conn, $v), array_keys($flat))) . "')
        ");

        mysqli_query($conn, "
            INSERT IGNORE INTO rol_permiso (rol_id, permiso_id)
            SELECT rp.rol_id, p2.id
            FROM rol_permiso rp
            INNER JOIN permisos p1 ON p1.id = rp.permiso_id
            INNER JOIN permisos p2 ON p2.nombre LIKE 'finanzas.%'
            WHERE p1.nombre = 'gestionar_finanzas'
              AND p2.nombre NOT IN ('finanzas.balances', 'finanzas.cierres')
        ");

        mysqli_query($conn, "
            INSERT IGNORE INTO rol_permiso (rol_id, permiso_id)
            SELECT rp.rol_id, p2.id
            FROM rol_permiso rp
            INNER JOIN permisos p1 ON p1.id = rp.permiso_id
            INNER JOIN permisos p2 ON p2.nombre LIKE 'reportes.%'
            WHERE p1.nombre = 'ver_reportes'
        ");

        mysqli_query($conn, "
            INSERT IGNORE INTO rol_permiso (rol_id, permiso_id)
            SELECT rp.rol_id, p2.id
            FROM rol_permiso rp
            INNER JOIN permisos p1 ON p1.id = rp.permiso_id
            INNER JOIN permisos p2 ON p2.nombre = 'panel.dashboard'
            WHERE p1.nombre = 'ver_dashboard'
        ");
    }
}
