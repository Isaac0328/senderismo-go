<?php
require_once __DIR__ . '/permisos.php';
require_once __DIR__ . '/configuracion_sitio.php';

function sg_admin_menu_tables_ready(mysqli $conn): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    $ready = true;
    foreach (['admin_menu_secciones', 'admin_menu_subsecciones', 'admin_menu_modulos'] as $table) {
        $result = mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");
        if (!$result || mysqli_num_rows($result) !== 1) {
            $ready = false;
        }
        if ($result) {
            mysqli_free_result($result);
        }
    }
    return $ready;
}

function sg_admin_menu_config_modules(): array
{
    $modules = [];
    foreach (sg_site_admin_sections() as $section => [$name, $description, $icon]) {
        $modules[] = [$name, $description, $icon, $section, 'interfaz.configuracion_' . $section];
    }
    return $modules;
}

function sg_admin_menu_default_groups(array $stats = []): array
{
    $users = (int) ($stats['totalUsuarios'] ?? 0);
    $messages = (int) ($stats['mensajesNuevos'] ?? 0);
    $url = BASE_URL;

    return [
        ['label' => 'Contenido', 'title' => 'Interfaz publica', 'icon' => 'globe', 'items' => [
            ['Configuracion general', 'Identidad, menu, SEO, consentimientos y pie de pagina', 'settings', $url.'mantenimientos/mantenimiento_configuracion_sitio.php', 'permission' => 'interfaz.configuracion_general'],
            ...array_map(static fn (array $module): array => [
                $module[0], $module[1], $module[2], $url.'admin/configuracion?seccion='.$module[3],
                'permission' => 'interfaz.configuracion_general', 'module_code' => $module[4],
            ], sg_admin_menu_config_modules()),
            ['Inicio', 'Portada, tarjetas, galeria y llamados', 'home', $url.'mantenimientos/mantenimiento_inicio.php', 'permission' => 'interfaz.inicio'],
            ['Nosotros', 'Historia, valores, pasos, indicadores y equipo', 'users', $url.'mantenimientos/mantenimiento_nosotros.php', 'permission' => 'interfaz.nosotros'],
            ['Contacto', 'Redes, telefonos, ubicacion y mensajes', 'phone-call', $url.'mantenimientos/mantenimiento_contacto.php', 'permission' => 'interfaz.contacto'],
            ['Tema visual', 'Paletas, colores globales y personalizacion', 'sliders', $url.'mantenimientos/mantenimiento_tema.php', 'permission' => 'interfaz.tema'],
        ]],
        ['label' => 'Usuarios', 'title' => 'Seguridad y accesos', 'icon' => 'shield', 'items' => [
            ['Usuarios', 'Datos, salud, menores, perfil y estado', 'user-check', $url.'mantenimientos/mantenimiento_usuarios.php', $users, 'permission' => 'usuarios.usuarios'],
            ['Usuarios sin cuenta', 'Personas inscritas a mano y su reconciliacion', 'user-x', $url.'mantenimientos/mantenimiento_usuarios_sin_cuenta.php', 'permission' => 'usuarios.sin_cuenta'],
            ['Pasaporte senderista', 'Niveles, insignias y progreso del usuario', 'award', $url.'mantenimientos/mantenimiento_pasaporte.php', 'permission' => 'usuarios.pasaporte'],
            ['Roles', 'Perfiles administrativos de usuarios', 'key', $url.'mantenimientos/mantenimiento_roles.php', 'permission' => 'usuarios.roles'],
            ['Permisos por rol', 'Distribucion de accesos por ventana', 'lock', $url.'mantenimientos/mantenimiento_permisos_roles.php', 'permission' => 'usuarios.permisos_roles'],
            ['Configuracion del panel', 'Organizar secciones, subsecciones y modulos visibles', 'sidebar', $url.'mantenimientos/mantenimiento_menu_panel.php', 'permission' => 'panel.menu_panel'],
            ['Configuracion de avisos', 'Activar avisos y definir su importancia', 'bell', $url.'mantenimientos/mantenimiento_notificaciones.php', 'permission' => 'panel.notificaciones'],
            ['Tarjetas del panel', 'Elegir que se muestra en el panel, su orden y para que roles', 'layout', $url.'mantenimientos/mantenimiento_panel_tarjetas.php', 'permission' => 'panel.tarjetas'],
            ['Actualizaciones del sistema', 'Revisar y aplicar cambios tecnicos pendientes', 'refresh-cw', $url.'mantenimientos/mantenimiento_actualizaciones.php', 'permission' => 'sistema.actualizaciones'],
        ]],
        ['label' => 'Operaciones', 'title' => 'Senderos y logistica', 'icon' => 'map-pin', 'items' => [
            ['Senderos', 'Rutas, fechas, inversiones, imagenes y detalle', 'map', $url.'mantenimientos/mantenimiento_senderos.php', (int) ($stats['senderos'] ?? 0), 'permission' => 'operaciones.senderos'],
            ['Usuarios por sendero', 'Acciones sobre reservas y participantes', 'users', $url.'mantenimientos/mantenimiento_usuarios_senderos.php', 'permission' => 'operaciones.usuarios_senderos'],
            ['Asistencia', 'Marcar quienes fueron realmente', 'check-square', $url.'mantenimientos/mantenimiento_asistencia_senderos.php', 'permission' => 'operaciones.asistencia'],
            ['Encuestas', 'Formularios, envios y satisfaccion por ruta', 'message-square', $url.'mantenimientos/mantenimiento_encuestas.php', 'permission' => 'operaciones.encuestas'],
            ['Puntos de encuentro', 'Ubicaciones reutilizables para salidas', 'map', $url.'mantenimientos/mantenimiento_puntos_encuentro.php', 'permission' => 'operaciones.puntos_encuentro'],
            ['Catálogos del sendero', 'Terrenos, anotaciones, incluye y dificultad', 'file-text', $url.'mantenimientos/mantenimiento_detalles.php', 'permission' => 'operaciones.detalles'],
            ['Provincias', 'Catalogo de provincias de Republica Dominicana', 'map-pin', $url.'mantenimientos/mantenimiento_provincias.php', 'permission' => 'operaciones.provincias'],
        ]],
        ['label' => 'Finanzas', 'title' => 'Contabilidad y pagos', 'icon' => 'dollar-sign', 'items' => [
            ['Balances y cuentas', 'Cuentas, ingresos, salidas, pagos y cuadres', 'briefcase', $url.'mantenimientos/mantenimiento_balances.php', 'permission' => 'finanzas.balances'],
            ['Panel financiero', 'Indicadores, tendencias, alertas y rentabilidad', 'bar-chart-2', $url.'pantallas/panel_financiero.php', 'permission' => 'finanzas.panel'],
            ['Estado de cuenta por usuario', 'Cobros, descuentos, creditos e historial por participante', 'users', $url.'mantenimientos/mantenimiento_cuentas_usuarios.php', 'permission' => 'finanzas.cuentas_usuarios'],
            ['Tarjeta de pago', 'Datos bancarios visibles en el detalle', 'credit-card', $url.'mantenimientos/mantenimiento_tarjeta_pago.php', 'permission' => 'finanzas.tarjeta_pago'],
            ['Categorias de gasto', 'Clasificacion financiera de costos', 'folder', $url.'mantenimientos/mantenimiento_categoria_gasto.php', 'permission' => 'finanzas.categorias_gasto'],
            ['Gastos catalogo', 'Costos frecuentes por alimento, equipo o servicio', 'tag', $url.'mantenimientos/mantenimiento_gastos.php', 'permission' => 'finanzas.gastos_catalogo'],
            ['Gastos por sendero', 'Costos reales por ruta', 'shopping-bag', $url.'mantenimientos/mantenimiento_gastos_sendero.php', 'permission' => 'finanzas.gastos_sendero'],
            ['Metodos de pago', 'Formas de cobro para los ingresos', 'briefcase', $url.'mantenimientos/mantenimiento_metodo_pago.php', 'permission' => 'finanzas.metodos_pago'],
            ['Estados de ingresos', 'Nombres, colores y orden de estados financieros', 'tag', $url.'mantenimientos/mantenimiento_estados_ingreso.php', 'permission' => 'finanzas.estados_ingresos'],
            ['Ingresos por sendero', 'Pagos, creditos, cortesias, exentos y asistencia financiera', 'trending-up', $url.'mantenimientos/mantenimiento_ingresos_sendero.php', 'permission' => 'finanzas.ingresos_sendero'],
        ]],
        ['label' => 'Informes', 'title' => 'Reportes y analisis', 'icon' => 'bar-chart-2', 'sections' => [
            ['label' => 'General', 'items' => [
                ['Reporte de Usuarios', 'Ranking, kilometraje y perfil de usuarios', 'users', $url.'pantallas/reporte_estadisticas_usuarios.php', 'permission' => 'reportes.usuarios'],
                ['Reporte de Actividad', 'Movimiento reciente de la plataforma', 'activity', $url.'pantallas/reportes.php#actividad', 'permission' => 'reportes.actividad'],
                ['Contactos Recibidos', 'Solicitudes enviadas desde la web', 'mail', $url.'pantallas/reporte_contacto.php', $messages, 'permission' => 'reportes.contactos'],
            ]],
            ['label' => 'Operaciones', 'items' => [
                ['Senderos y Galeria', 'Rutas, imagenes y comportamiento general', 'git-branch', $url.'pantallas/reportes.php#senderos', 'permission' => 'reportes.senderos_galeria'],
                ['Usuarios por Sendero', 'Participantes, salud, emergencia y menores', 'user', $url.'pantallas/reporte_usuarios_sendero.php', 'permission' => 'reportes.usuarios_sendero'],
            ]],
            ['label' => 'Finanzas', 'items' => [
                ['Rentabilidad por Sendero', 'Ingresos, gastos, utilidad y margen', 'trending-up', $url.'pantallas/reporte_rentabilidad_sendero.php', 'permission' => 'reportes.rentabilidad_sendero'],
                ['Rentabilidad por Fechas', 'Resumen financiero por periodo', 'calendar', $url.'pantallas/reporte_rentabilidad_fechas.php', 'permission' => 'reportes.rentabilidad_fechas'],
                ['Reporte de Encuestas', 'Envios, respuestas y participacion por periodo', 'message-square', $url.'pantallas/reporte_encuestas.php', 'permission' => 'reportes.encuestas'],
            ]],
        ]],
    ];
}

function sg_admin_menu_catalog(): array
{
    $catalog = sg_permission_flat_catalog();
    unset($catalog['panel.dashboard'], $catalog['finanzas.cierres']);
    foreach (sg_admin_menu_config_modules() as $module) {
        $catalog[$module[4]] = [
            'codigo' => $module[4], 'nombre' => $module[0], 'descripcion' => $module[1],
            'icono' => $module[2], 'ruta' => 'admin/configuracion?seccion='.$module[3],
            'grupo' => 'Interfaz publica', 'label' => 'Contenido',
            'acceso' => 'interfaz.configuracion_general',
        ];
    }
    return $catalog;
}

function sg_admin_menu_icon_choices(): array
{
    return [
        'grid' => 'Cuadricula', 'globe' => 'Sitio web', 'shield' => 'Seguridad',
        'map-pin' => 'Ubicacion', 'map' => 'Mapa', 'dollar-sign' => 'Finanzas',
        'bar-chart-2' => 'Graficos', 'users' => 'Personas', 'settings' => 'Configuracion',
        'activity' => 'Actividad', 'award' => 'Reconocimientos', 'briefcase' => 'Trabajo',
        'file-text' => 'Documentos', 'home' => 'Inicio', 'key' => 'Llave',
        'layout' => 'Panel', 'lock' => 'Candado', 'message-square' => 'Mensajes',
        'sidebar' => 'Panel lateral', 'sliders' => 'Controles',
    ];
}

function sg_admin_menu_can_delete_section(mysqli $conn, int $id): bool
{
    $stmt = mysqli_prepare($conn, 'SELECT (SELECT COUNT(*) FROM admin_menu_subsecciones WHERE seccion_id=?) + (SELECT COUNT(*) FROM admin_menu_modulos WHERE seccion_id=?) total');
    mysqli_stmt_bind_param($stmt, 'ii', $id, $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (int) ($row['total'] ?? 0) === 0;
}

function sg_admin_menu_can_delete_subsection(mysqli $conn, int $id): bool
{
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) total FROM admin_menu_modulos WHERE subseccion_id=?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (int) ($row['total'] ?? 0) === 0;
}

function sg_admin_menu_section_exists(mysqli $conn, int $id): bool
{
    $stmt = mysqli_prepare($conn, 'SELECT id FROM admin_menu_secciones WHERE id=? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $found = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) !== null;
    mysqli_stmt_close($stmt);
    return $found;
}

function sg_admin_menu_place_module(mysqli $conn, string $code, int $sectionId, ?int $subsectionId, int $order, int $active): void
{
    $stmt = mysqli_prepare($conn, 'INSERT INTO admin_menu_modulos (permiso_codigo,seccion_id,subseccion_id,orden,activo) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE seccion_id=VALUES(seccion_id),subseccion_id=VALUES(subseccion_id),orden=VALUES(orden),activo=VALUES(activo)');
    mysqli_stmt_bind_param($stmt, 'siiii', $code, $sectionId, $subsectionId, $order, $active);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Envia un modulo a otra seccion: queda al final de sus modulos sin subseccion.
function sg_admin_menu_send_module(mysqli $conn, string $code, int $sectionId, int $active): void
{
    if (!sg_admin_menu_section_exists($conn, $sectionId)) {
        throw new RuntimeException('La seccion de destino ya no existe. Recarga la pagina.');
    }
    $stmt = mysqli_prepare($conn, 'SELECT COALESCE(MAX(orden),0)+10 siguiente FROM admin_menu_modulos WHERE seccion_id=? AND subseccion_id IS NULL');
    mysqli_stmt_bind_param($stmt, 'i', $sectionId);
    mysqli_stmt_execute($stmt);
    $order = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['siguiente'] ?? 10);
    mysqli_stmt_close($stmt);
    sg_admin_menu_place_module($conn, $code, $sectionId, null, $order, $active);
}

// Guarda de una vez una seccion completa: sus datos, sus subsecciones y sus modulos.
// El orden no se escribe a mano: sale de la posicion en que llega cada elemento.
// Devuelve las subsecciones creadas como [clave del formulario => id real].
function sg_admin_menu_save_layout(mysqli $conn, int $sectionId, array $data, bool $canAdd, bool $canDelete): array
{
    if (!sg_admin_menu_section_exists($conn, $sectionId)) {
        throw new RuntimeException('La seccion ya no existe. Recarga la pagina.');
    }
    $catalog = sg_admin_menu_catalog();
    $created = [];
    mysqli_begin_transaction($conn);
    try {
        $active = !empty($data['activo']) ? 1 : 0;
        $stmt = mysqli_prepare($conn, 'UPDATE admin_menu_secciones SET etiqueta=?,nombre=?,icono=?,activo=? WHERE id=?');
        mysqli_stmt_bind_param($stmt, 'sssii', $data['etiqueta'], $data['nombre'], $data['icono'], $active, $sectionId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $owned = [];
        $stmt = mysqli_prepare($conn, 'SELECT id FROM admin_menu_subsecciones WHERE seccion_id=? FOR UPDATE');
        mysqli_stmt_bind_param($stmt, 'i', $sectionId);
        mysqli_stmt_execute($stmt);
        foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $row) { $owned[(int) $row['id']] = true; }
        mysqli_stmt_close($stmt);

        $subsectionIds = [];
        $toDelete = [];
        $position = 0;
        foreach ((array) ($data['subs'] ?? []) as $key => $subsection) {
            $key = (string) $key;
            $isNew = str_starts_with($key, 'new-');
            if (!$isNew && !isset($owned[(int) $key])) {
                throw new RuntimeException('Una subseccion ya no pertenece a esta seccion. Recarga la pagina.');
            }
            if (!empty($subsection['eliminar'])) {
                if (!$isNew) {
                    if (!$canDelete) { throw new RuntimeException('No tienes permiso para eliminar subsecciones.'); }
                    $toDelete[] = (int) $key;
                }
                continue;
            }
            $name = mb_substr(trim((string) preg_replace('/\s+/', ' ', (string) ($subsection['nombre'] ?? ''))), 0, 120);
            if ($name === '') { throw new RuntimeException('Cada subseccion necesita un nombre.'); }
            $subActive = !empty($subsection['activo']) ? 1 : 0;
            $order = ++$position * 10;
            if ($isNew) {
                if (!$canAdd) { throw new RuntimeException('No tienes permiso para crear subsecciones.'); }
                $stmt = mysqli_prepare($conn, 'INSERT INTO admin_menu_subsecciones (seccion_id,nombre,orden,activo) VALUES (?,?,?,?)');
                mysqli_stmt_bind_param($stmt, 'isii', $sectionId, $name, $order, $subActive);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $subsectionIds[$key] = $created[$key] = (int) mysqli_insert_id($conn);
            } else {
                $subsectionId = (int) $key;
                $stmt = mysqli_prepare($conn, 'UPDATE admin_menu_subsecciones SET nombre=?,orden=?,activo=? WHERE id=?');
                mysqli_stmt_bind_param($stmt, 'siii', $name, $order, $subActive, $subsectionId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $subsectionIds[$key] = $subsectionId;
            }
        }

        $positions = [];
        foreach ((array) ($data['modulos'] ?? []) as $code => $module) {
            $code = (string) $code;
            if (!isset($catalog[$code])) { throw new RuntimeException('El modulo seleccionado no es valido.'); }
            $moduleActive = !empty($module['activo']) ? 1 : 0;
            $destination = (int) ($module['destino'] ?? 0);
            if ($destination > 0 && $destination !== $sectionId) {
                sg_admin_menu_send_module($conn, $code, $destination, $moduleActive);
                continue;
            }
            $group = (string) ($module['grupo'] ?? 'direct');
            if ($group !== 'direct' && !isset($subsectionIds[$group])) {
                throw new RuntimeException('Un modulo quedo dentro de una subseccion que se va a eliminar. Muevelo antes de guardar.');
            }
            $positions[$group] = ($positions[$group] ?? 0) + 10;
            sg_admin_menu_place_module($conn, $code, $sectionId, $group === 'direct' ? null : $subsectionIds[$group], $positions[$group], $moduleActive);
        }

        foreach ($toDelete as $subsectionId) {
            if (!sg_admin_menu_can_delete_subsection($conn, $subsectionId)) {
                throw new RuntimeException('No puedes eliminar una subseccion que aun contiene modulos.');
            }
            $stmt = mysqli_prepare($conn, 'DELETE FROM admin_menu_subsecciones WHERE id=?');
            mysqli_stmt_bind_param($stmt, 'i', $subsectionId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        mysqli_commit($conn);
        return $created;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

// Reordena las secciones segun la lista de ids recibida, de arriba hacia abajo.
function sg_admin_menu_reorder_sections(mysqli $conn, array $ids): void
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $known = array_map('intval', array_column(mysqli_fetch_all(mysqli_query($conn, 'SELECT id FROM admin_menu_secciones'), MYSQLI_ASSOC), 'id'));
    if (count($ids) !== count($known) || array_diff($known, $ids)) {
        throw new RuntimeException('La lista de secciones cambio. Recarga la pagina.');
    }
    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare($conn, 'UPDATE admin_menu_secciones SET orden=? WHERE id=?');
        foreach ($ids as $index => $id) {
            $order = ($index + 1) * 10;
            mysqli_stmt_bind_param($stmt, 'ii', $order, $id);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

// Ubica modulos que aun no tienen seccion. Devuelve cuantos se ubicaron.
function sg_admin_menu_assign_modules(mysqli $conn, array $modules): int
{
    $catalog = sg_admin_menu_catalog();
    $assigned = 0;
    mysqli_begin_transaction($conn);
    try {
        foreach ($modules as $code => $module) {
            $destination = (int) ($module['destino'] ?? 0);
            if ($destination <= 0) { continue; }
            if (!isset($catalog[(string) $code])) { throw new RuntimeException('El modulo seleccionado no es valido.'); }
            sg_admin_menu_send_module($conn, (string) $code, $destination, 1);
            $assigned++;
        }
        mysqli_commit($conn);
        return $assigned;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

function sg_admin_menu_seed(mysqli $conn, array $defaults, bool $manageTransaction = true): void
{
    if (!sg_admin_menu_tables_ready($conn)) {
        return;
    }
    $existing = mysqli_query($conn, 'SELECT id FROM admin_menu_secciones LIMIT 1');
    if ($existing && mysqli_num_rows($existing) > 0) {
        mysqli_free_result($existing);
        return;
    }
    if ($existing) { mysqli_free_result($existing); }
    $lockResult = mysqli_query($conn, "SELECT GET_LOCK('sg_admin_menu_seed', 5) acquired");
    $lock = $lockResult ? mysqli_fetch_assoc($lockResult) : null;
    if ($lockResult) { mysqli_free_result($lockResult); }
    if ((int) ($lock['acquired'] ?? 0) !== 1) {
        throw new RuntimeException('No se pudo preparar el menu administrativo. Intenta nuevamente.');
    }
    try {
        $countResult = mysqli_query($conn, 'SELECT COUNT(*) total FROM admin_menu_secciones');
        $count = $countResult ? mysqli_fetch_assoc($countResult) : null;
        if ($countResult) { mysqli_free_result($countResult); }
        if ((int) ($count['total'] ?? 0) > 0) { return; }

        if ($manageTransaction) {
            mysqli_begin_transaction($conn);
        }
        try {
        $seeded = [];
        foreach ($defaults as $sectionOrder => $group) {
            $order = ($sectionOrder + 1) * 10;
            $stmt = mysqli_prepare($conn, 'INSERT INTO admin_menu_secciones (etiqueta,nombre,icono,orden,activo) VALUES (?,?,?,?,1)');
            mysqli_stmt_bind_param($stmt, 'sssi', $group['label'], $group['title'], $group['icon'], $order);
            if (!mysqli_stmt_execute($stmt)) { throw new RuntimeException('No se pudo crear la seccion inicial.'); }
            $sectionId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            $children = $group['sections'] ?? [['label' => '', 'items' => $group['items'] ?? []]];
            foreach ($children as $subOrder => $subsection) {
                $subsectionId = null;
                if ($subsection['label'] !== '') {
                    $subOrderValue = ($subOrder + 1) * 10;
                    $stmt = mysqli_prepare($conn, 'INSERT INTO admin_menu_subsecciones (seccion_id,nombre,orden,activo) VALUES (?,?,?,1)');
                    mysqli_stmt_bind_param($stmt, 'isi', $sectionId, $subsection['label'], $subOrderValue);
                    if (!mysqli_stmt_execute($stmt)) { throw new RuntimeException('No se pudo crear la subseccion inicial.'); }
                    $subsectionId = mysqli_insert_id($conn);
                    mysqli_stmt_close($stmt);
                }
                foreach ($subsection['items'] as $itemOrder => $item) {
                    $permission = (string) ($item['module_code'] ?? $item['permission'] ?? '');
                    if ($permission === '' || isset($seeded[$permission]) || !isset(sg_admin_menu_catalog()[$permission])) {
                        continue;
                    }
                    $seeded[$permission] = true;
                    $moduleOrder = ($itemOrder + 1) * 10;
                    $stmt = mysqli_prepare($conn, 'INSERT INTO admin_menu_modulos (permiso_codigo,seccion_id,subseccion_id,orden,activo) VALUES (?,?,?,?,1)');
                    mysqli_stmt_bind_param($stmt, 'siii', $permission, $sectionId, $subsectionId, $moduleOrder);
                    if (!mysqli_stmt_execute($stmt)) { throw new RuntimeException('No se pudo ubicar el modulo inicial.'); }
                    mysqli_stmt_close($stmt);
                }
            }
        }
        if ($manageTransaction) {
            mysqli_commit($conn);
        }
        } catch (Throwable $e) {
            if ($manageTransaction) {
                mysqli_rollback($conn);
            }
            throw $e;
        }
    } finally {
        mysqli_query($conn, "SELECT RELEASE_LOCK('sg_admin_menu_seed')");
    }
}

function sg_admin_menu_ensure_config_modules(mysqli $conn): void
{
    if (!sg_admin_menu_tables_ready($conn)) return;
    $parent = $conn->query("SELECT seccion_id,subseccion_id,orden,activo FROM admin_menu_modulos WHERE permiso_codigo='interfaz.configuracion_general' LIMIT 1")->fetch_assoc();
    if (!$parent) return;

    $stmt = $conn->prepare('INSERT IGNORE INTO admin_menu_modulos (permiso_codigo,seccion_id,subseccion_id,orden,activo) VALUES (?,?,?,?,?)');
    $sectionId = (int) $parent['seccion_id'];
    $subsectionId = $parent['subseccion_id'] === null ? null : (int) $parent['subseccion_id'];
    $active = (int) $parent['activo'];
    foreach (sg_admin_menu_config_modules() as $index => $module) {
        $code = $module[4];
        $order = (int) $parent['orden'] + $index + 1;
        $stmt->bind_param('siiii', $code, $sectionId, $subsectionId, $order, $active);
        $stmt->execute();
    }
    $stmt->close();
}

function sg_admin_menu_groups(mysqli $conn, array $stats = []): array
{
    $defaults = sg_admin_menu_default_groups($stats);
    if (!sg_admin_menu_tables_ready($conn)) {
        return $defaults;
    }
    sg_admin_menu_seed($conn, $defaults);
    sg_admin_menu_ensure_config_modules($conn);

    $catalog = sg_admin_menu_catalog();
    $badges = ['usuarios.usuarios' => (int) ($stats['totalUsuarios'] ?? 0), 'operaciones.senderos' => (int) ($stats['senderos'] ?? 0), 'reportes.contactos' => (int) ($stats['mensajesNuevos'] ?? 0)];
    $rows = mysqli_query($conn, 'SELECT * FROM admin_menu_secciones WHERE activo=1 ORDER BY orden,id');
    if (!$rows) { return $defaults; }
    $groups = [];
    while ($rows && $row = mysqli_fetch_assoc($rows)) {
        $groups[(int) $row['id']] = ['label' => $row['etiqueta'], 'title' => $row['nombre'], 'icon' => $row['icono'], 'sections' => ['direct' => ['label' => null, 'items' => []]]];
    }
    if ($rows) { mysqli_free_result($rows); }
    $rows = mysqli_query($conn, 'SELECT id,seccion_id,nombre FROM admin_menu_subsecciones WHERE activo=1 ORDER BY seccion_id,orden,id');
    if (!$rows) { return $defaults; }
    while ($rows && $row = mysqli_fetch_assoc($rows)) {
        $sectionId = (int) $row['seccion_id'];
        if (isset($groups[$sectionId])) {
            $groups[$sectionId]['sections']['sub-'.$row['id']] = ['label' => $row['nombre'], 'items' => []];
        }
    }
    if ($rows) { mysqli_free_result($rows); }
    $rows = mysqli_query($conn, 'SELECT permiso_codigo,seccion_id,subseccion_id FROM admin_menu_modulos WHERE activo=1 ORDER BY seccion_id,COALESCE(subseccion_id,0),orden,permiso_codigo');
    if (!$rows) { return $defaults; }
    while ($rows && $row = mysqli_fetch_assoc($rows)) {
        $sectionId = (int) $row['seccion_id'];
        $subKey = $row['subseccion_id'] === null ? 'direct' : 'sub-'.$row['subseccion_id'];
        $code = (string) $row['permiso_codigo'];
        if (!isset($groups[$sectionId]['sections'][$subKey])) { continue; }
        if ($code !== '' && isset($catalog[$code])) {
            $module = $catalog[$code];
            $item = [$module['nombre'], $module['descripcion'], $module['icono'], BASE_URL.$module['ruta'], 'permission' => $module['acceso'] ?? $code];
            if (isset($badges[$code])) {
                array_splice($item, 4, 0, [$badges[$code]]);
            }
            $groups[$sectionId]['sections'][$subKey]['items'][] = $item;
        }
    }
    if ($rows) {
        mysqli_free_result($rows);
    }
    foreach ($groups as &$group) {
        $group['sections'] = array_values(array_filter($group['sections'], static fn ($section) => !empty($section['items'])));
    }
    unset($group);
    return array_values(array_filter($groups, static fn ($group) => !empty($group['sections'])));
}
