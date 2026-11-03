<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../componentes/recordar_sesion.php';
sg_restaurar_sesion_recordada();

$PERMISO_REQUERIDO = 'operaciones.senderos';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';

$pageTitle = "Mantenimiento Senderos";

$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/senderos_admin.css"
];

$jsFiles = [
    "js/barra_navegacion.js",
    "js/senderos_admin.js"
];

require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/actualizar_estado_senderos.php';
require_once __DIR__ . '/../componentes/helpers.php';
require_once __DIR__ . '/../componentes/senderos_admin_data.php';
require_once __DIR__ . '/../componentes/provincias.php';
require_once __DIR__ . '/../componentes/menores_sendero.php';

sg_actualizar_senderos_vencidos($conn);

function img_admin_src(?string $ruta): string
{
    $ruta = trim((string) $ruta);
    if ($ruta !== '' && file_exists(__DIR__ . '/../' . $ruta)) {
        return BASE_URL . sg_h($ruta);
    }
    return '';
}

// Boton para reutilizar una imagen que el sendero ya tiene cargada (sin subirla otra vez).
function sendero_imagen_elegir(string $campo, string $titulo): void
{
    ?>
    <div class="image-pick" data-image-pick="<?= $campo ?>">
        <input type="hidden" name="<?= $campo ?>_existente" value="">
        <button type="button" class="image-pick-button" data-open-image-picker="<?= $campo ?>" data-slot-label="<?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?>"><i data-feather="image"></i>Elegir de las im&aacute;genes ya cargadas</button>
        <span class="image-pick-chosen" data-image-pick-chosen hidden><img src="" alt=""><span>Se usar&aacute; esta imagen al guardar</span><button type="button" data-image-pick-clear>Quitar</button></span>
    </div>
    <?php
}

function fecha_admin_visual(?string $fecha): string
{
    return sg_fecha($fecha, false, '');
}

function minutos_horas(int|string|null $minutos): array
{
    return sg_minutes_parts($minutos);
}

function tiempo_legible(int|string|null $minutos): string
{
    return sg_time_label($minutos);
}

$catalogosSenderos = sg_senderos_catalogos_mantenimiento($conn);
$niveles = $catalogosSenderos['niveles'];
$terrenos = $catalogosSenderos['terrenos'];
$caminosVehiculo = $catalogosSenderos['caminosVehiculo'];
$anotaciones = $catalogosSenderos['anotaciones'];
$incluyeItems = $catalogosSenderos['incluyeItems'];
$puntosCatalogo = $catalogosSenderos['puntosCatalogo'];
$provinciasCatalogo = sg_provincias_activas($conn);
$senderos = sg_senderos_listado_mantenimiento($conn);

$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
$editTerrenos = [];
$editAnotaciones = [];
$editAnotacionesOrden = [];
$editInversiones = [];
$editInversionesMenores = [];
$investmentUsageById = [];
$editPuntos = [];
$editGaleria = [];

if ($editId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM senderos WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $editId);
    mysqli_stmt_execute($stmt);
    $resEdit = mysqli_stmt_get_result($stmt);
    $edit = $resEdit ? mysqli_fetch_assoc($resEdit) : null;
    mysqli_stmt_close($stmt);

    if ($edit) {
        $stmt = mysqli_prepare($conn, "SELECT tipo_terreno_id FROM sendero_tipos_terreno WHERE sendero_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $editId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $editTerrenos[] = (int) $row['tipo_terreno_id'];
        }
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "SELECT anotacion_id, orden FROM sendero_anotaciones WHERE sendero_id = ? ORDER BY orden ASC, id ASC");
        mysqli_stmt_bind_param($stmt, "i", $editId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $editAnotaciones[] = (int) $row['anotacion_id'];
            $editAnotacionesOrden[(int) $row['anotacion_id']] = (int) $row['orden'];
        }
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare(
            $conn,
            "SELECT si.*, GROUP_CONCAT(sii.incluye_id ORDER BY sii.orden ASC, sii.incluye_id ASC) AS incluye_ids,
                    ((SELECT COUNT(*) FROM registros_senderos rs WHERE rs.inversion_id = si.id) +
                     (SELECT COUNT(*) FROM registro_sendero_menores rsm WHERE rsm.inversion_id = si.id)) AS uso_count
             FROM sendero_inversiones si
             LEFT JOIN sendero_inversion_incluye sii ON sii.inversion_id = si.id
             WHERE si.sendero_id = ?
             GROUP BY si.id
             ORDER BY si.orden ASC, si.id ASC"
        );
        mysqli_stmt_bind_param($stmt, "i", $editId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $row['incluye_ids_array'] = array_values(array_filter(array_map('intval', explode(',', (string) ($row['incluye_ids'] ?? '')))));
            $investmentUsageById[(int) $row['id']] = (int) ($row['uso_count'] ?? 0);
            if ((int) ($row['es_menor'] ?? 0) === 1) {
                $editInversionesMenores[] = $row;
            } else {
                $editInversiones[] = $row;
            }
        }
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "SELECT * FROM sendero_puntos_encuentro WHERE sendero_id = ? AND activo = 1 ORDER BY orden ASC, id ASC");
        mysqli_stmt_bind_param($stmt, "i", $editId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $editPuntos[] = $row;
        }
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "SELECT * FROM sendero_imagenes WHERE sendero_id = ? AND activo = 1 ORDER BY orden ASC, id ASC");
        mysqli_stmt_bind_param($stmt, "i", $editId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $editGaleria[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

$oldSenderoPayload = $_SESSION['senderos_old_input'] ?? null;
unset($_SESSION['senderos_old_input']);
$oldSenderoInput = [];
if (is_array($oldSenderoPayload)
    && (int) ($oldSenderoPayload['id'] ?? -1) === $editId
    && is_array($oldSenderoPayload['data'] ?? null)) {
    $oldSenderoInput = $oldSenderoPayload['data'];
}

if (!empty($oldSenderoInput)) {
    $edit = is_array($edit) ? $edit : [];
    $oldScalarFields = [
        'nombre', 'fecha_sendero', 'lugar', 'provincia', 'descripcion_corta', 'descripcion',
        'nivel_dificultad_id', 'tiempo_ida_vehiculo_min', 'tiempo_regreso_vehiculo_min',
        'tipo_camino_vehiculo_id', 'tiempo_sendero_min', 'distancia_km', 'desnivel_mts',
        'cobertura_senal_pct', 'estado',
    ];
    foreach ($oldScalarFields as $field) {
        if (array_key_exists($field, $oldSenderoInput)) {
            $edit[$field] = $oldSenderoInput[$field];
        }
    }
    $edit['id'] = $editId;
    $edit['activo'] = isset($oldSenderoInput['activo']) ? 1 : 0;
    $edit['incluye_chaleco_salvavidas'] = isset($oldSenderoInput['incluye_chaleco_salvavidas']) ? 1 : 0;
    $edit['menores_misma_inversion'] = array_key_exists('menores_misma_inversion', $oldSenderoInput)
        ? (isset($oldSenderoInput['menores_misma_inversion']) ? 1 : 0)
        : (int) ($edit['menores_misma_inversion'] ?? 1);
    if (isset($oldSenderoInput['menores_modo'])) {
        $edit['menores_modo'] = (string) $oldSenderoInput['menores_modo'];
    }

    $editTerrenos = array_values(array_unique(array_filter(array_map('intval', (array) ($oldSenderoInput['tipos_terreno'] ?? [])))));
    $editAnotaciones = array_values(array_unique(array_filter(array_map('intval', (array) ($oldSenderoInput['anotaciones'] ?? [])))));
    $editAnotacionesOrden = [];
    foreach ($editAnotaciones as $position => $anotacionId) {
        $editAnotacionesOrden[$anotacionId] = $position + 1;
    }

    $restoreInvestmentGroup = static function (array $items, bool $minor) use ($investmentUsageById): array {
        $restored = [];
        foreach ($items as $index => $oldInvestment) {
            if (!is_array($oldInvestment)) {
                continue;
            }
            $oldIncludes = array_values(array_unique(array_filter(array_map('intval', (array) ($oldInvestment['incluye'] ?? [])))));
            $restored[] = [
                'id' => (int) ($oldInvestment['id'] ?? 0),
                'nombre' => (string) ($oldInvestment['nombre'] ?? ''),
                'descripcion' => (string) ($oldInvestment['descripcion'] ?? ''),
                'monto' => (string) ($oldInvestment['monto'] ?? ''),
                'monto_menor' => $minor ? '' : (string) ($oldInvestment['monto_menor'] ?? ''),
                'fecha_limite_pago' => (string) ($oldInvestment['fecha_limite_pago'] ?? ''),
                'orden' => max(1, (int) ($oldInvestment['orden'] ?? ((int) $index + 1))),
                'activo' => isset($oldInvestment['activo']) ? 1 : 0,
                'es_menor' => $minor ? 1 : 0,
                'incluye_ids_array' => $oldIncludes,
                'uso_count' => $investmentUsageById[(int) ($oldInvestment['id'] ?? 0)] ?? 0,
            ];
        }
        return $restored;
    };
    $editInversiones = $restoreInvestmentGroup((array) ($oldSenderoInput['inversiones'] ?? []), false);
    $editInversionesMenores = $restoreInvestmentGroup((array) ($oldSenderoInput['inversiones_menores'] ?? []), true);

    $editPuntos = [];
    foreach (array_slice((array) ($oldSenderoInput['puntos'] ?? []), 0, 2) as $oldPoint) {
        if (is_array($oldPoint)) {
            $editPuntos[] = $oldPoint;
        }
    }
}

[$idaHoras, $idaMinutos] = minutos_horas($edit['tiempo_ida_vehiculo_min'] ?? null);
[$regresoHoras, $regresoMinutos] = minutos_horas($edit['tiempo_regreso_vehiculo_min'] ?? null);
[$senderoHoras, $senderoMinutos] = minutos_horas($edit['tiempo_sendero_min'] ?? null);

if (empty($editInversiones)) {
    $editInversiones[] = [
        'id' => 0,
        'nombre' => '',
        'descripcion' => '',
        'monto' => $edit['inversion_total'] ?? '',
        'monto_menor' => '',
        'fecha_limite_pago' => $edit['fecha_limite_pago'] ?? '',
        'orden' => 1,
        'activo' => 1,
        'es_menor' => 0,
        'incluye_ids_array' => [],
    ];
}

$menoresMismaInversion = (int) ($edit['menores_misma_inversion'] ?? 1) === 1;
// Un sendero nuevo empieza con "pagan igual que el adulto".
$menoresModo = !empty($edit['id']) || isset($edit['menores_modo']) ? sg_menores_modo($edit) : 'igual';
// Sin la columna menores_modo (actualizacion 010 pendiente) se deduce como lo hace esa
// actualizacion: si el precio de menor es igual al del adulto, "igual"; si todos son 0, "gratis".
if (!isset($edit['menores_modo']) && $menoresModo === 'precio') {
    $preciosMenor = array_map(
        static fn(array $i): ?float => ($i['monto_menor'] ?? '') === '' || $i['monto_menor'] === null ? null : (float) $i['monto_menor'],
        $editInversiones
    );
    $todosIguales = true;
    $todosCero = true;
    foreach ($editInversiones as $pos => $inversionModo) {
        $menor = $preciosMenor[$pos];
        if ($menor !== null && abs($menor - (float) ($inversionModo['monto'] ?? 0)) > 0.004) {
            $todosIguales = false;
        }
        if ($menor === null || $menor > 0.004) {
            $todosCero = false;
        }
    }
    $menoresModo = $todosIguales ? 'igual' : ($todosCero ? 'gratis' : 'precio');
}

function ordenar_items_modal(array $items, array $seleccionados): array
{
    if (empty($seleccionados)) {
        return $items;
    }

    $posiciones = array_flip(array_values(array_map('intval', $seleccionados)));
    usort($items, static function (array $a, array $b) use ($posiciones): int {
        $idA = (int) ($a['id'] ?? 0);
        $idB = (int) ($b['id'] ?? 0);
        $aSeleccionado = array_key_exists($idA, $posiciones);
        $bSeleccionado = array_key_exists($idB, $posiciones);

        if ($aSeleccionado && $bSeleccionado) {
            return $posiciones[$idA] <=> $posiciones[$idB];
        }

        if ($aSeleccionado !== $bSeleccionado) {
            return $aSeleccionado ? -1 : 1;
        }

        return strcasecmp((string) ($a['nombre'] ?? ''), (string) ($b['nombre'] ?? ''));
    });

    return $items;
}

$anotacionesOrdenadas = ordenar_items_modal($anotaciones, $editAnotaciones);
$isEditingSendero = $editId > 0;
$imagenesExistentes = [];
if ($isEditingSendero) {
    $registrarImagen = static function (string $ruta, string $etiqueta) use (&$imagenesExistentes): void {
        $ruta = trim($ruta);
        if ($ruta === '' || img_admin_src($ruta) === '') {
            return;
        }
        $imagenesExistentes[$ruta]['ruta'] = $ruta;
        $imagenesExistentes[$ruta]['etiquetas'][] = $etiqueta;
    };
    $registrarImagen((string) ($edit['imagen_principal'] ?? ''), 'Principal');
    $registrarImagen((string) ($edit['imagen_flyer'] ?? ''), 'Flyer');
    $registrarImagen((string) ($edit['imagen_catalogo'] ?? ''), 'Catalogo');
    foreach ($editGaleria as $posicionGaleria => $imagenGaleria) {
        $registrarImagen((string) $imagenGaleria['ruta_imagen'], 'Galeria ' . ($posicionGaleria + 1));
    }
    $imagenesExistentes = array_values($imagenesExistentes);
}
// La entrada es la consulta; el formulario se abre con ?nuevo=1 o con ?edit=ID.
$mostrarFormulario = $isEditingSendero || isset($_GET['nuevo']);
$urlSenderos = BASE_URL . 'mantenimientos/mantenimiento_senderos.php';

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<div class="senderos-admin-page">
    <div class="senderos-admin-container">
        <div class="senderos-admin-header">
            <div>
                <span class="admin-kicker">Gestion de rutas</span>
                <?php if (!$mostrarFormulario): ?>
                    <h1 class="senderos-admin-title">Senderos</h1>
                    <p class="senderos-admin-subtitle">Consulta las rutas registradas, editalas o activa e inactiva su publicacion.</p>
                <?php else: ?>
                    <h1 class="senderos-admin-title"><?= $isEditingSendero ? 'Editar sendero' : 'Nuevo sendero' ?></h1>
                    <p class="senderos-admin-subtitle">Completa los datos de la ruta y carga las imagenes que vera el cliente.</p>
                <?php endif; ?>
            </div>
            <div class="senderos-header-actions">
                <?php if (!$mostrarFormulario): ?>
                    <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="view-public-link">Volver al panel</a>
                    <a href="<?= BASE_URL ?>pantallas/senderos.php" class="view-public-link">Ver pantalla publica</a>
                    <a href="<?= $urlSenderos ?>?nuevo=1" class="senderos-new-link"><i data-feather="plus"></i>Nuevo sendero</a>
                <?php else: ?>
                    <a href="<?= $urlSenderos ?>" class="view-public-link senderos-back-link"><i data-feather="arrow-left"></i>Volver a la lista</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($_SESSION['senderos_success'])): ?>
            <div class="senderos-alert success"><?= htmlspecialchars($_SESSION['senderos_success']) ?></div>
            <?php unset($_SESSION['senderos_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['senderos_error'])): ?>
            <div class="senderos-alert error"><?= htmlspecialchars($_SESSION['senderos_error']) ?></div>
            <?php unset($_SESSION['senderos_error']); ?>
        <?php endif; ?>

        <?php if ($mostrarFormulario): ?>
        <section class="senderos-form-card">
            <div class="senderos-card-head">
                <div>
                    <h2 id="formTitle"><?= $isEditingSendero ? 'Datos del sendero' : 'Datos del nuevo sendero' ?></h2>
                    <p>Los campos con * son obligatorios. Las imagenes se guardan en <strong>imagenes/senderos</strong>.</p>
                </div>
                <?php if ($isEditingSendero): ?>
                    <a href="<?= $urlSenderos ?>?nuevo=1" class="clear-edit-link">Nuevo sendero</a>
                <?php endif; ?>
            </div>

            <form class="senderos-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_senderos.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

                <div class="form-grid">
                    <section class="sendero-collapsible span-6" data-sendero-section>
                        <button type="button" class="sendero-section-toggle" data-sendero-section-toggle aria-expanded="true">
                            <span class="sendero-section-heading">
                                <span class="sendero-section-kicker">Contenido base</span>
                                <strong>Datos generales</strong>
                                <small>Nombre, fecha, ubicacion y estado publico del sendero.</small>
                            </span>
                            <span class="sendero-section-arrow" aria-hidden="true"></span>
                        </button>
                        <div class="sendero-section-body">

                    <div class="field span-3">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" maxlength="150" required value="<?= htmlspecialchars($edit['nombre'] ?? '') ?>" placeholder="Ej: Reserva Cientifica Loma Quita Espuela">
                    </div>

                    <div class="field">
                        <label for="fecha_sendero">Fecha</label>
                        <input type="date" id="fecha_sendero" name="fecha_sendero" value="<?= htmlspecialchars($edit['fecha_sendero'] ?? '') ?>">
                        <small id="fechaSenderoPreview" class="date-preview">Obligatoria solo para proximos senderos.</small>
                    </div>

                    <div class="field">
                        <label for="nivel_dificultad_id">Dificultad *</label>
                        <select id="nivel_dificultad_id" name="nivel_dificultad_id" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($niveles as $nivel): ?>
                                <option value="<?= (int) $nivel['id'] ?>" <?= (int) ($edit['nivel_dificultad_id'] ?? 0) === (int) $nivel['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($nivel['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado">
                            <option value="pendiente" <?= ($edit['estado'] ?? 'pendiente') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="visitado" <?= ($edit['estado'] ?? '') === 'visitado' ? 'selected' : '' ?>>Visitado</option>
                        </select>
                    </div>

                    <div class="field span-2">
                        <label for="lugar">Lugar *</label>
                        <input type="text" id="lugar" name="lugar" maxlength="150" required value="<?= htmlspecialchars($edit['lugar'] ?? '') ?>" placeholder="Ej: San Francisco de Macoris">
                    </div>

                    <div class="field span-2">
                        <label for="provincia">Provincia</label>
                        <input type="text" id="provincia" name="provincia" maxlength="100" list="provinciasCatalogo" autocomplete="off" value="<?= sg_h($edit['provincia'] ?? '') ?>" placeholder="Buscar provincia...">
                        <datalist id="provinciasCatalogo">
                            <?php foreach ($provinciasCatalogo as $provinciaItem): ?>
                                <option value="<?= sg_h($provinciaItem['nombre']) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="field span-3">
                        <label for="descripcion_corta">Descripcion corta</label>
                        <input type="text" id="descripcion_corta" name="descripcion_corta" maxlength="255" value="<?= htmlspecialchars($edit['descripcion_corta'] ?? '') ?>" placeholder="Texto breve para las tarjetas publicas">
                    </div>

                    <div class="field span-3">
                        <label>Equipamiento incluido</label>
                        <div class="checks-grid compact">
                            <label class="check-pill">
                                <input type="checkbox" name="incluye_chaleco_salvavidas" value="1" <?= (int) ($edit['incluye_chaleco_salvavidas'] ?? 0) === 1 ? 'checked' : '' ?>>
                                <span>Incluye chalecos salvavidas</span>
                            </label>
                        </div>
                        <small>Al activarlo, cada participante debera elegir una talla al registrarse.</small>
                    </div>

                        </div>
                    </section>

                    <section class="sendero-collapsible span-6 is-collapsed" data-sendero-section>
                        <button type="button" class="sendero-section-toggle" data-sendero-section-toggle aria-expanded="false">
                            <span class="sendero-section-heading">
                                <span class="sendero-section-kicker">Material visual</span>
                                <strong>Imagenes del sendero</strong>
                                <small>Principal para el detalle, flyer para proximos, catalogo para visitados y galeria adicional.</small>
                            </span>
                            <span class="sendero-section-arrow" aria-hidden="true"></span>
                        </button>
                        <div class="sendero-section-body">

                    <div class="field span-3">
                        <label for="imagen_principal">Imagen principal</label>
                        <input type="file" id="imagen_principal" name="imagen_principal" accept="image/png,image/jpeg,image/webp">
                        <small><?= $edit && !empty($edit['imagen_principal']) ? 'Deja vacio para conservar la imagen principal actual.' : 'Se muestra grande al abrir el detalle del sendero.' ?></small>
                        <?php if ($isEditingSendero && $imagenesExistentes) { sendero_imagen_elegir('imagen_principal', 'Imagen principal'); } ?>
                    </div>

                    <div class="field span-3">
                        <label for="imagen_flyer">Imagen flyer</label>
                        <input type="file" id="imagen_flyer" name="imagen_flyer" accept="image/png,image/jpeg,image/webp">
                        <small><?= $edit && !empty($edit['imagen_flyer']) ? 'Deja vacio para conservar el flyer actual.' : 'Se muestra en proximos senderos.' ?></small>
                        <?php if ($isEditingSendero && $imagenesExistentes) { sendero_imagen_elegir('imagen_flyer', 'Imagen flyer'); } ?>
                    </div>

                    <div class="field span-3">
                        <label for="imagen_catalogo">Imagen visitados/catalogo</label>
                        <input type="file" id="imagen_catalogo" name="imagen_catalogo" accept="image/png,image/jpeg,image/webp">
                        <small><?= $edit && !empty($edit['imagen_catalogo']) ? 'Deja vacio para conservar la imagen de catalogo actual.' : 'Se muestra en senderos visitados.' ?></small>
                        <?php if ($isEditingSendero && $imagenesExistentes) { sendero_imagen_elegir('imagen_catalogo', 'Imagen visitados/catalogo'); } ?>
                    </div>

                    <div class="field span-3">
                        <label for="galeria">Galeria de imagenes</label>
                        <input type="file" id="galeria" name="galeria[]" accept="image/png,image/jpeg,image/webp" multiple>
                        <small id="galleryHelp">Puedes cargar varias imagenes a la vez.</small>
                    </div>

                        </div>
                    </section>

                    <section class="sendero-collapsible span-6 is-collapsed" data-sendero-section>
                        <button type="button" class="sendero-section-toggle" data-sendero-section-toggle aria-expanded="false">
                            <span class="sendero-section-heading">
                                <span class="sendero-section-kicker">Datos tecnicos</span>
                                <strong>Caracteristicas de la ruta</strong>
                                <small>Tiempos, trayecto, distancia, desnivel, senal y descripcion completa.</small>
                            </span>
                            <span class="sendero-section-arrow" aria-hidden="true"></span>
                        </button>
                        <div class="sendero-section-body">

                    <div class="field">
                        <label>Ida vehiculo</label>
                        <div class="time-combo" data-duration-group>
                            <input type="number" min="0" max="99" value="<?= $idaHoras ?>" aria-label="Horas ida vehiculo" data-duration-hours>
                            <span>h</span>
                            <input type="number" min="0" max="59" value="<?= $idaMinutos ?>" aria-label="Minutos ida vehiculo" data-duration-minutes>
                            <span>min</span>
                            <input type="hidden" id="tiempo_ida_vehiculo_min" name="tiempo_ida_vehiculo_min" value="<?= htmlspecialchars($edit['tiempo_ida_vehiculo_min'] ?? '') ?>" data-duration-total>
                        </div>
                    </div>

                    <div class="field">
                        <label>Regreso vehiculo</label>
                        <div class="time-combo" data-duration-group>
                            <input type="number" min="0" max="99" value="<?= $regresoHoras ?>" aria-label="Horas regreso vehiculo" data-duration-hours>
                            <span>h</span>
                            <input type="number" min="0" max="59" value="<?= $regresoMinutos ?>" aria-label="Minutos regreso vehiculo" data-duration-minutes>
                            <span>min</span>
                            <input type="hidden" id="tiempo_regreso_vehiculo_min" name="tiempo_regreso_vehiculo_min" value="<?= htmlspecialchars($edit['tiempo_regreso_vehiculo_min'] ?? '') ?>" data-duration-total>
                        </div>
                    </div>

                    <div class="field">
                        <label for="tipo_camino_vehiculo_id">Trayecto vehiculo</label>
                        <select id="tipo_camino_vehiculo_id" name="tipo_camino_vehiculo_id">
                            <option value="">Seleccione...</option>
                            <?php foreach ($caminosVehiculo as $camino): ?>
                                <option value="<?= (int) $camino['id'] ?>" <?= (int) ($edit['tipo_camino_vehiculo_id'] ?? 0) === (int) $camino['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($camino['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label>Tiempo sendero</label>
                        <div class="time-combo" data-duration-group>
                            <input type="number" min="0" max="99" value="<?= $senderoHoras ?>" aria-label="Horas del sendero" data-duration-hours>
                            <span>h</span>
                            <input type="number" min="0" max="59" value="<?= $senderoMinutos ?>" aria-label="Minutos del sendero" data-duration-minutes>
                            <span>min</span>
                            <input type="hidden" id="tiempo_sendero_min" name="tiempo_sendero_min" value="<?= htmlspecialchars($edit['tiempo_sendero_min'] ?? '') ?>" data-duration-total>
                        </div>
                    </div>

                    <div class="field">
                        <label for="distancia_km">Distancia (km)</label>
                        <input type="number" id="distancia_km" name="distancia_km" min="0" max="999" step="0.01" value="<?= htmlspecialchars($edit['distancia_km'] ?? '') ?>" placeholder="6.50">
                    </div>

                    <div class="field">
                        <label for="desnivel_mts">Desnivel (+ - mts)</label>
                        <input type="number" id="desnivel_mts" name="desnivel_mts" min="0" max="9999" value="<?= htmlspecialchars($edit['desnivel_mts'] ?? '') ?>" placeholder="450">
                    </div>

                    <div class="field">
                        <label for="cobertura_senal_pct">Cobertura senal (%)</label>
                        <input type="number" id="cobertura_senal_pct" name="cobertura_senal_pct" min="0" max="100" value="<?= htmlspecialchars($edit['cobertura_senal_pct'] ?? '') ?>" placeholder="50">
                    </div>

                    <div class="field span-6">
                        <label for="descripcion">Descripcion completa</label>
                        <textarea id="descripcion" name="descripcion" rows="4" placeholder="Describe la experiencia, nivel fisico, ambiente y recomendaciones generales."><?= htmlspecialchars($edit['descripcion'] ?? '') ?></textarea>
                    </div>

                    <div class="field span-3">
                        <label>Tipos de terreno</label>
                        <div class="checks-grid">
                            <?php foreach ($terrenos as $terreno): ?>
                                <label class="check-pill">
                                    <input type="checkbox" name="tipos_terreno[]" value="<?= (int) $terreno['id'] ?>" <?= in_array((int) $terreno['id'], $editTerrenos, true) ? 'checked' : '' ?>>
                                    <span><?= htmlspecialchars($terreno['nombre']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                        </div>
                    </section>

                    <section class="sendero-collapsible span-6 is-collapsed" data-sendero-section>
                        <button type="button" class="sendero-section-toggle" data-sendero-section-toggle aria-expanded="false">
                            <span class="sendero-section-heading">
                                <span class="sendero-section-kicker">Informacion al visitante</span>
                                <strong>Recomendaciones del sendero</strong>
                                <small>Anotaciones importantes y catalogos auxiliares que aplican a la ruta.</small>
                            </span>
                            <span class="sendero-section-arrow" aria-hidden="true"></span>
                        </button>
                        <div class="sendero-section-body">

                    <div class="field span-6">
                        <label class="section-label">Detalles del sendero</label>
                        <div class="detail-buttons-row">
                            <button type="button" class="detail-modal-trigger" data-modal-target="modalAnotaciones">
                                Agregar anotaciones
                                <span id="anotacionesCount"><?= count($editAnotaciones) ?></span>
                            </button>
                            <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_detalles.php" class="detail-admin-link">
                                Mantener catalogos
                            </a>
                        </div>
                    </div>

                        </div>
                    </section>

                    <section class="sendero-collapsible span-6 is-collapsed" data-sendero-section>
                        <button type="button" class="sendero-section-toggle" data-sendero-section-toggle aria-expanded="false">
                            <span class="sendero-section-heading">
                                <span class="sendero-section-kicker">Reservas y pagos</span>
                                <strong>Inversiones</strong>
                                <small>Opciones de pago con monto, fecha limite y elementos incluidos por cada opcion.</small>
                            </span>
                            <span class="sendero-section-arrow" aria-hidden="true"></span>
                        </button>
                        <div class="sendero-section-body">

                    <fieldset class="field span-6 menores-modo" data-menores-modo>
                        <legend class="section-label">Menores de edad</legend>
                        <p class="menores-modo-ayuda">Elige como participan los menores en este sendero.</p>
                        <div class="menores-modo-opciones">
                            <?php foreach (sg_menores_modos() as $codigoModo => [$tituloModo, $detalleModo]): ?>
                                <label class="menores-modo-opcion">
                                    <input type="radio" name="menores_modo" value="<?= $codigoModo ?>" <?= $menoresModo === $codigoModo ? 'checked' : '' ?>>
                                    <span>
                                        <strong><?= htmlspecialchars($tituloModo) ?></strong>
                                        <small><?= htmlspecialchars($detalleModo) ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <div class="field span-6">
                        <div class="investment-admin-head">
                            <div>
                                <label class="section-label">Inversiones para adultos</label>
                                <p>Agrega una o varias opciones. Cada inversion puede tener su propio monto, fecha limite y elementos incluidos.</p>
                            </div>
                            <button type="button" class="investment-add-btn" id="addInvestmentOption">Agregar inversion</button>
                        </div>
                        <div class="investment-options-admin" id="investmentOptionsAdmin" data-investment-group="adult">
                            <?php foreach ($editInversiones as $index => $inversion): ?>
                                <?php
                                $investmentNumber = $index + 1;
                                $investmentCount = count($inversion['incluye_ids_array'] ?? []);
                                ?>
                                <?php $investmentInUse = (int) ($inversion['uso_count'] ?? 0) > 0; ?>
                                <article class="investment-admin-card" data-investment-card data-investment-index="<?= $index ?>">
                                    <div class="investment-admin-card-head">
                                        <strong data-investment-title>Inversion <?= $investmentNumber ?></strong>
                                        <div class="investment-admin-card-actions">
                                            <label>
                                                <input type="checkbox" name="inversiones[<?= $index ?>][activo]" value="1" <?= (int) ($inversion['activo'] ?? 1) === 1 ? 'checked' : '' ?>>
                                                Activa
                                            </label>
                                            <button type="button"
                                                    class="investment-remove-btn"
                                                    data-remove-investment
                                                    aria-label="Eliminar inversion <?= $investmentNumber ?>"
                                                    title="<?= $investmentInUse ? 'Esta inversion tiene participantes; desactivala para conservar el historial.' : 'Eliminar inversion' ?>"
                                                    <?= $investmentInUse ? 'disabled' : '' ?>>
                                                <i data-feather="trash-2" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <input type="hidden" name="inversiones[<?= $index ?>][id]" value="<?= (int) ($inversion['id'] ?? 0) ?>">
                                    <input type="hidden" name="inversiones[<?= $index ?>][orden]" value="<?= (int) ($inversion['orden'] ?? ($index + 1)) ?>">
                                    <div class="investment-admin-grid">
                                        <label class="field">
                                            <span>Nombre opcional</span>
                                            <input type="text" name="inversiones[<?= $index ?>][nombre]" maxlength="120" value="<?= htmlspecialchars($inversion['nombre'] ?? '') ?>" placeholder="Ej: Con suplementos">
                                        </label>
                                        <div class="investment-amount-stack">
                                            <label class="field">
                                    <span data-adult-amount-label>Monto adulto (RD$) *</span>
                                                <input type="number" name="inversiones[<?= $index ?>][monto]" min="0" max="999999.99" step="0.01" value="<?= htmlspecialchars($inversion['monto'] ?? '') ?>" placeholder="1500.00">
                                            </label>
                                            <label class="field shared-minor-amount" data-shared-minor-amount <?= $menoresModo !== 'precio' ? 'hidden' : '' ?>>
                                                <span>Precio menor (RD$)</span>
                                                <input type="number" name="inversiones[<?= $index ?>][monto_menor]" min="0" max="999999.99" step="0.01" value="<?= htmlspecialchars($inversion['monto_menor'] ?? '') ?>" placeholder="Igual" title="Vacio = paga igual que el adulto; 0 = gratis">
                                            </label>
                                        </div>
                                        <label class="field">
                                            <span>Fecha limite de pago</span>
                                            <input type="date" name="inversiones[<?= $index ?>][fecha_limite_pago]" value="<?= htmlspecialchars($inversion['fecha_limite_pago'] ?? '') ?>">
                                        </label>
                                        <label class="field span-investment-full">
                                            <span>Descripcion</span>
                                            <input type="text" name="inversiones[<?= $index ?>][descripcion]" maxlength="255" value="<?= htmlspecialchars($inversion['descripcion'] ?? '') ?>" placeholder="Ej: Incluye hidratacion y merienda.">
                                        </label>
                                    </div>
                                    <button type="button" class="investment-includes-button detail-modal-trigger" data-modal-target="modalInversionIncluye<?= $index ?>">
                                        Agregar que incluye
                                        <span id="inversionIncluyeCount<?= $index ?>"><?= $investmentCount ?></span>
                                    </button>

                                    <div class="detail-modal investment-include-modal" id="modalInversionIncluye<?= $index ?>" aria-hidden="true">
                                        <div class="detail-modal-backdrop" data-modal-close></div>
                                        <div class="detail-modal-panel">
                                            <div class="detail-modal-head">
                                                <div>
                                                    <h3 data-investment-modal-title>Incluye - Inversion <?= $investmentNumber ?></h3>
                                                    <p>Selecciona los servicios o suplementos incluidos en esta opcion.</p>
                                                </div>
                                                <button type="button" class="detail-modal-close" data-modal-close>&times;</button>
                                            </div>
                                            <div class="detail-modal-list" data-count-target="inversionIncluyeCount<?= $index ?>" data-sortable-modal-list>
                                                <?php foreach (ordenar_items_modal($incluyeItems, $inversion['incluye_ids_array'] ?? []) as $item): ?>
                                                    <?php $inputId = 'incluye_' . $index . '_' . (int) $item['id']; ?>
                                                    <div class="modal-check-item">
                                                        <span class="modal-check-controls">
                                                            <span class="modal-drag-handle" aria-hidden="true">::</span>
                                                        </span>
                                                        <label class="modal-check-main" for="<?= $inputId ?>">
                                                            <input id="<?= $inputId ?>" type="checkbox" name="inversiones[<?= $index ?>][incluye][]" value="<?= (int) $item['id'] ?>" <?= in_array((int) $item['id'], $inversion['incluye_ids_array'] ?? [], true) ? 'checked' : '' ?>>
                                                            <span class="modal-check-copy">
                                                                <strong><?= htmlspecialchars($item['nombre']) ?></strong>
                                                                <?php if (!empty($item['descripcion'])): ?>
                                                                    <small><?= htmlspecialchars($item['descripcion']) ?></small>
                                                                <?php endif; ?>
                                                            </span>
                                                        </label>
                                                        <span class="modal-order-actions" aria-label="Cambiar orden">
                                                            <button type="button" class="modal-order-btn" data-modal-move="up" aria-label="Subir">&uarr;</button>
                                                            <button type="button" class="modal-order-btn" data-modal-move="down" aria-label="Bajar">&darr;</button>
                                                        </span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="detail-modal-actions">
                                                <button type="button" class="btn-primary" data-modal-close>Listo</button>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="field span-6 minor-investment-config" data-minor-investment-config <?= $menoresModo !== 'propias' ? 'hidden' : '' ?>>
                        <div class="investment-admin-head">
                            <div>
                                <label class="section-label">Inversiones para menores de edad</label>
                                <p>Estas opciones apareceran al registrar cada menor. Puedes usar montos y beneficios diferentes.</p>
                            </div>
                            <button type="button" class="investment-add-btn" id="addMinorInvestmentOption">Agregar inversion para menores</button>
                        </div>
                        <div class="investment-options-admin" id="minorInvestmentOptionsAdmin" data-investment-group="minor">
                            <?php foreach ($editInversionesMenores as $index => $inversion): ?>
                                <?php
                                $investmentNumber = $index + 1;
                                $investmentCount = count($inversion['incluye_ids_array'] ?? []);
                                $investmentInUse = (int) ($inversion['uso_count'] ?? 0) > 0;
                                ?>
                                <article class="investment-admin-card" data-investment-card data-investment-index="<?= $index ?>" data-investment-kind="minor">
                                    <div class="investment-admin-card-head">
                                        <strong data-investment-title>Inversion menor <?= $investmentNumber ?></strong>
                                        <div class="investment-admin-card-actions">
                                            <label>
                                                <input type="checkbox" name="inversiones_menores[<?= $index ?>][activo]" value="1" <?= (int) ($inversion['activo'] ?? 1) === 1 ? 'checked' : '' ?>>
                                                Activa
                                            </label>
                                            <button type="button" class="investment-remove-btn" data-remove-investment aria-label="Eliminar inversion menor <?= $investmentNumber ?>" title="<?= $investmentInUse ? 'Esta inversion tiene participantes; desactivala para conservar el historial.' : 'Eliminar inversion' ?>" <?= $investmentInUse ? 'disabled' : '' ?>>
                                                <i data-feather="trash-2" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <input type="hidden" name="inversiones_menores[<?= $index ?>][id]" value="<?= (int) ($inversion['id'] ?? 0) ?>">
                                    <input type="hidden" name="inversiones_menores[<?= $index ?>][orden]" value="<?= (int) ($inversion['orden'] ?? ($index + 1)) ?>">
                                    <div class="investment-admin-grid">
                                        <label class="field">
                                            <span>Nombre opcional</span>
                                            <input type="text" name="inversiones_menores[<?= $index ?>][nombre]" maxlength="120" value="<?= htmlspecialchars($inversion['nombre'] ?? '') ?>" placeholder="Ej: Menor con suplementos">
                                        </label>
                                        <label class="field">
                                            <span>Monto (RD$) *</span>
                                            <input type="number" name="inversiones_menores[<?= $index ?>][monto]" min="0" max="999999.99" step="0.01" value="<?= htmlspecialchars($inversion['monto'] ?? '') ?>" placeholder="850.00">
                                        </label>
                                        <label class="field">
                                            <span>Fecha limite de pago</span>
                                            <input type="date" name="inversiones_menores[<?= $index ?>][fecha_limite_pago]" value="<?= htmlspecialchars($inversion['fecha_limite_pago'] ?? '') ?>">
                                        </label>
                                        <label class="field span-investment-full">
                                            <span>Descripcion</span>
                                            <input type="text" name="inversiones_menores[<?= $index ?>][descripcion]" maxlength="255" value="<?= htmlspecialchars($inversion['descripcion'] ?? '') ?>" placeholder="Ej: Incluye hidratacion y merienda.">
                                        </label>
                                    </div>
                                    <button type="button" class="investment-includes-button detail-modal-trigger" data-modal-target="modalInversionMenorIncluye<?= $index ?>">
                                        Agregar que incluye
                                        <span id="inversionMenorIncluyeCount<?= $index ?>"><?= $investmentCount ?></span>
                                    </button>
                                    <div class="detail-modal investment-include-modal" id="modalInversionMenorIncluye<?= $index ?>" aria-hidden="true">
                                        <div class="detail-modal-backdrop" data-modal-close></div>
                                        <div class="detail-modal-panel">
                                            <div class="detail-modal-head">
                                                <div>
                                                    <h3 data-investment-modal-title>Incluye - Inversion menor <?= $investmentNumber ?></h3>
                                                    <p>Selecciona los servicios o suplementos incluidos en esta opcion.</p>
                                                </div>
                                                <button type="button" class="detail-modal-close" data-modal-close>&times;</button>
                                            </div>
                                            <div class="detail-modal-list" data-count-target="inversionMenorIncluyeCount<?= $index ?>" data-sortable-modal-list>
                                                <?php foreach (ordenar_items_modal($incluyeItems, $inversion['incluye_ids_array'] ?? []) as $item): ?>
                                                    <?php $inputId = 'incluye_menor_' . $index . '_' . (int) $item['id']; ?>
                                                    <div class="modal-check-item">
                                                        <span class="modal-check-controls"><span class="modal-drag-handle" aria-hidden="true">::</span></span>
                                                        <label class="modal-check-main" for="<?= $inputId ?>">
                                                            <input id="<?= $inputId ?>" type="checkbox" name="inversiones_menores[<?= $index ?>][incluye][]" value="<?= (int) $item['id'] ?>" <?= in_array((int) $item['id'], $inversion['incluye_ids_array'] ?? [], true) ? 'checked' : '' ?>>
                                                            <span class="modal-check-copy"><strong><?= htmlspecialchars($item['nombre']) ?></strong><?php if (!empty($item['descripcion'])): ?><small><?= htmlspecialchars($item['descripcion']) ?></small><?php endif; ?></span>
                                                        </label>
                                                        <span class="modal-order-actions" aria-label="Cambiar orden"><button type="button" class="modal-order-btn" data-modal-move="up" aria-label="Subir">&uarr;</button><button type="button" class="modal-order-btn" data-modal-move="down" aria-label="Bajar">&darr;</button></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="detail-modal-actions"><button type="button" class="btn-primary" data-modal-close>Listo</button></div>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>

                        </div>
                    </section>

                    <section class="sendero-collapsible span-6 is-collapsed" data-sendero-section>
                        <button type="button" class="sendero-section-toggle" data-sendero-section-toggle aria-expanded="false">
                            <span class="sendero-section-heading">
                                <span class="sendero-section-kicker">Logistica</span>
                                <strong>Puntos de encuentro</strong>
                                <small>Agrega las ubicaciones necesarias con sus horas y enlaces de mapa.</small>
                            </span>
                            <span class="sendero-section-arrow" aria-hidden="true"></span>
                        </button>
                        <div class="sendero-section-body">

                    <div class="field span-6">
                        <div class="meeting-admin-head">
                            <div>
                                <label class="section-label">Puntos de encuentro</label>
                                <p>Selecciona uno o varios lugares para que cada senderista indique donde lo recogeran.</p>
                            </div>
                            <div class="meeting-admin-actions">
                                <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_puntos_encuentro.php" class="detail-admin-link">
                                    Mantener catalogo
                                </a>
                                <button type="button" class="meeting-add-btn" id="addMeetingPoint">
                                    <i data-feather="plus" aria-hidden="true"></i>
                                    Agregar punto
                                </button>
                            </div>
                        </div>
                        <div class="meeting-grid" id="meetingPointsAdmin">
                            <?php foreach (($editPuntos ?: [[]]) as $i => $punto): ?>
                                <?php
                                $selectedPuntoId = (int) ($punto['punto_encuentro_id'] ?? 0);
                                if ($selectedPuntoId <= 0 && !empty($punto['nombre_punto'])) {
                                    foreach ($puntosCatalogo as $catalogoPunto) {
                                        if (strcasecmp(trim($catalogoPunto['nombre']), trim((string) $punto['nombre_punto'])) === 0) {
                                            $selectedPuntoId = (int) $catalogoPunto['id'];
                                            break;
                                        }
                                    }
                                }
                                ?>
                                <article class="meeting-card" data-meeting-card data-meeting-index="<?= $i ?>">
                                    <div class="meeting-card-head">
                                        <strong data-meeting-title>Punto <?= $i + 1 ?></strong>
                                        <button type="button" class="meeting-remove-btn" data-remove-meeting aria-label="Eliminar punto <?= $i + 1 ?>" title="Eliminar punto">
                                            <i data-feather="trash-2" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <input type="hidden" name="puntos[<?= $i ?>][id]" value="<?= (int) ($punto['id'] ?? 0) ?>">
                                    <label>
                                        <span>Titulo del punto</span>
                                        <select name="puntos[<?= $i ?>][punto_encuentro_id]" class="meeting-point-select">
                                            <option value="">Seleccione un punto...</option>
                                            <?php foreach ($puntosCatalogo as $catalogoPunto): ?>
                                                <option
                                                    value="<?= (int) $catalogoPunto['id'] ?>"
                                                    data-nombre="<?= htmlspecialchars($catalogoPunto['nombre']) ?>"
                                                    data-direccion="<?= htmlspecialchars($catalogoPunto['direccion_referencia'] ?? '') ?>"
                                                    data-url="<?= htmlspecialchars($catalogoPunto['url_mapa'] ?? '') ?>"
                                                    <?= $selectedPuntoId === (int) $catalogoPunto['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($catalogoPunto['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>
                                        <span>Referencia o direccion</span>
                                        <input type="text" name="puntos[<?= $i ?>][direccion_referencia]" value="<?= htmlspecialchars($punto['direccion_referencia'] ?? '') ?>" placeholder="Se completa al elegir el punto" data-meeting-address readonly>
                                    </label>
                                    <div class="time-row">
                                        <label>
                                            <span>Hora de llegada</span>
                                            <input type="time" name="puntos[<?= $i ?>][hora_encuentro]" value="<?= htmlspecialchars(isset($punto['hora_encuentro']) ? substr($punto['hora_encuentro'], 0, 5) : '') ?>">
                                        </label>
                                        <label>
                                            <span>Hora de salida</span>
                                            <input type="time" name="puntos[<?= $i ?>][hora_salida]" value="<?= htmlspecialchars(isset($punto['hora_salida']) ? substr($punto['hora_salida'], 0, 5) : '') ?>">
                                        </label>
                                    </div>
                                    <label>
                                        <span>URL de Google Maps</span>
                                        <input type="url" name="puntos[<?= $i ?>][url_mapa]" value="<?= htmlspecialchars($punto['url_mapa'] ?? '') ?>" placeholder="Se completa al elegir el punto" data-meeting-map readonly>
                                    </label>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>

                        </div>
                    </section>

                    <label class="active-toggle">
                        <input type="checkbox" name="activo" value="1" <?= (int) ($edit['activo'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <span>Visible en la pagina publica</span>
                    </label>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary"><?= $isEditingSendero ? 'Actualizar sendero' : 'Guardar sendero' ?></button>
                        <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_senderos.php" class="btn-secondary">Limpiar</a>
                    </div>
                </div>

                <div class="detail-modal investment-include-modal annotations-modal" id="modalAnotaciones" aria-hidden="true">
                    <div class="detail-modal-backdrop" data-modal-close></div>
                    <div class="detail-modal-panel">
                        <div class="detail-modal-head">
                            <div>
                                <h3>Anotaciones importantes</h3>
                                <p>Selecciona las recomendaciones que aplican para este sendero.</p>
                            </div>
                            <button type="button" class="detail-modal-close" data-modal-close>&times;</button>
                        </div>
                        <div class="detail-modal-list" data-count-target="anotacionesCount" data-sortable-modal-list>
                            <?php foreach ($anotacionesOrdenadas as $item): ?>
                                <?php $inputId = 'anotacion_' . (int) $item['id']; ?>
                                <div class="modal-check-item">
                                    <span class="modal-check-controls">
                                        <span class="modal-drag-handle" aria-hidden="true">::</span>
                                    </span>
                                    <label class="modal-check-main" for="<?= $inputId ?>">
                                        <input id="<?= $inputId ?>" type="checkbox" name="anotaciones[]" value="<?= (int) $item['id'] ?>" <?= in_array((int) $item['id'], $editAnotaciones, true) ? 'checked' : '' ?>>
                                        <span class="modal-check-copy">
                                            <strong><?= htmlspecialchars($item['nombre']) ?></strong>
                                            <?php if (!empty($item['descripcion'])): ?>
                                                <small><?= htmlspecialchars($item['descripcion']) ?></small>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                    <span class="modal-order-actions" aria-label="Cambiar orden">
                                        <button type="button" class="modal-order-btn" data-modal-move="up" aria-label="Subir">&uarr;</button>
                                        <button type="button" class="modal-order-btn" data-modal-move="down" aria-label="Bajar">&darr;</button>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="detail-modal-actions">
                            <button type="button" class="btn-primary" data-modal-close>Listo</button>
                        </div>
                    </div>
                </div>

                <template id="meetingPointTemplate">
                    <article class="meeting-card" data-meeting-card>
                        <div class="meeting-card-head">
                            <strong data-meeting-title>Punto __ORDER__</strong>
                            <button type="button" class="meeting-remove-btn" data-remove-meeting aria-label="Eliminar punto __ORDER__" title="Eliminar punto">
                                <i data-feather="trash-2" aria-hidden="true"></i>
                            </button>
                        </div>
                        <input type="hidden" data-name-template="puntos[__INDEX__][id]" value="0">
                        <label>
                            <span>Titulo del punto</span>
                            <select data-name-template="puntos[__INDEX__][punto_encuentro_id]" class="meeting-point-select">
                                <option value="">Seleccione un punto...</option>
                                <?php foreach ($puntosCatalogo as $catalogoPunto): ?>
                                    <option value="<?= (int) $catalogoPunto['id'] ?>"
                                            data-nombre="<?= htmlspecialchars($catalogoPunto['nombre']) ?>"
                                            data-direccion="<?= htmlspecialchars($catalogoPunto['direccion_referencia'] ?? '') ?>"
                                            data-url="<?= htmlspecialchars($catalogoPunto['url_mapa'] ?? '') ?>">
                                        <?= htmlspecialchars($catalogoPunto['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Referencia o direccion</span>
                            <input type="text" data-name-template="puntos[__INDEX__][direccion_referencia]" placeholder="Se completa al elegir el punto" data-meeting-address readonly>
                        </label>
                        <div class="time-row">
                            <label>
                                <span>Hora de llegada</span>
                                <input type="time" data-name-template="puntos[__INDEX__][hora_encuentro]">
                            </label>
                            <label>
                                <span>Hora de salida</span>
                                <input type="time" data-name-template="puntos[__INDEX__][hora_salida]">
                            </label>
                        </div>
                        <label>
                            <span>URL de Google Maps</span>
                            <input type="url" data-name-template="puntos[__INDEX__][url_mapa]" placeholder="Se completa al elegir el punto" data-meeting-map readonly>
                        </label>
                    </article>
                </template>

                <template id="investmentOptionTemplate">
                    <article class="investment-admin-card" data-investment-card>
                        <div class="investment-admin-card-head">
                            <strong data-investment-title>Inversion __ORDER__</strong>
                            <div class="investment-admin-card-actions">
                                <label>
                                    <input type="checkbox" data-name-template="__PREFIX__[__INDEX__][activo]" value="1" checked>
                                    Activa
                                </label>
                                <button type="button" class="investment-remove-btn" data-remove-investment aria-label="Eliminar inversion __ORDER__" title="Eliminar inversion">
                                    <i data-feather="trash-2" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <input type="hidden" data-name-template="__PREFIX__[__INDEX__][id]" value="0">
                        <input type="hidden" data-name-template="__PREFIX__[__INDEX__][orden]" value="__ORDER__">
                        <div class="investment-admin-grid">
                            <label class="field">
                                <span>Nombre opcional</span>
                                <input type="text" data-name-template="__PREFIX__[__INDEX__][nombre]" maxlength="120" placeholder="Ej: Con suplementos">
                            </label>
                            <div class="investment-amount-stack">
                                <label class="field">
                                    <span data-adult-amount-label>Monto adulto (RD$) *</span>
                                    <input type="number" data-name-template="__PREFIX__[__INDEX__][monto]" min="0" max="999999.99" step="0.01" placeholder="1500.00">
                                </label>
                                <label class="field shared-minor-amount" data-shared-minor-amount>
                                    <span>Precio menor (RD$)</span>
                                    <input type="number" data-name-template="__PREFIX__[__INDEX__][monto_menor]" min="0" max="999999.99" step="0.01" placeholder="Igual" title="Vacio = paga igual que el adulto; 0 = gratis">
                                </label>
                            </div>
                            <label class="field">
                                <span>Fecha limite de pago</span>
                                <input type="date" data-name-template="__PREFIX__[__INDEX__][fecha_limite_pago]">
                            </label>
                            <label class="field span-investment-full">
                                <span>Descripcion</span>
                                <input type="text" data-name-template="__PREFIX__[__INDEX__][descripcion]" maxlength="255" placeholder="Ej: Incluye hidratacion y merienda.">
                            </label>
                        </div>
                        <button type="button" class="investment-includes-button detail-modal-trigger" data-modal-target="modalInversionIncluye__PREFIX____INDEX__">
                            Agregar que incluye
                            <span id="inversionIncluyeCount__PREFIX____INDEX__">0</span>
                        </button>

                        <div class="detail-modal investment-include-modal" id="modalInversionIncluye__PREFIX____INDEX__" aria-hidden="true">
                            <div class="detail-modal-backdrop" data-modal-close></div>
                            <div class="detail-modal-panel">
                                <div class="detail-modal-head">
                                    <div>
                                        <h3 data-investment-modal-title>Incluye - Inversion __ORDER__</h3>
                                        <p>Selecciona los servicios o suplementos incluidos en esta opcion.</p>
                                    </div>
                                    <button type="button" class="detail-modal-close" data-modal-close>&times;</button>
                                </div>
                                <div class="detail-modal-list" data-count-target="inversionIncluyeCount__PREFIX____INDEX__" data-sortable-modal-list>
                                    <?php foreach ($incluyeItems as $item): ?>
                                        <?php $inputId = 'incluye___PREFIX____INDEX___' . (int) $item['id']; ?>
                                        <div class="modal-check-item">
                                            <span class="modal-check-controls">
                                                <span class="modal-drag-handle" aria-hidden="true">::</span>
                                            </span>
                                            <label class="modal-check-main" for="<?= $inputId ?>">
                                                <input id="<?= $inputId ?>" type="checkbox" data-name-template="__PREFIX__[__INDEX__][incluye][]" value="<?= (int) $item['id'] ?>">
                                                <span class="modal-check-copy">
                                                    <strong><?= htmlspecialchars($item['nombre']) ?></strong>
                                                    <?php if (!empty($item['descripcion'])): ?>
                                                        <small><?= htmlspecialchars($item['descripcion']) ?></small>
                                                    <?php endif; ?>
                                                </span>
                                            </label>
                                            <span class="modal-order-actions" aria-label="Cambiar orden">
                                                <button type="button" class="modal-order-btn" data-modal-move="up" aria-label="Subir">&uarr;</button>
                                                <button type="button" class="modal-order-btn" data-modal-move="down" aria-label="Bajar">&darr;</button>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="detail-modal-actions">
                                    <button type="button" class="btn-primary" data-modal-close>Listo</button>
                                </div>
                            </div>
                        </div>
                    </article>
                </template>

                <dialog class="investment-delete-dialog" data-meeting-delete-dialog>
                    <div class="investment-delete-dialog-icon" aria-hidden="true">
                        <i data-feather="trash-2"></i>
                    </div>
                    <div class="investment-delete-dialog-copy">
                        <h3>Eliminar punto</h3>
                        <p data-meeting-delete-message>Este punto se retirara cuando guardes el sendero.</p>
                    </div>
                    <div class="investment-delete-dialog-actions">
                        <button type="button" class="btn-secondary" data-cancel-meeting-delete>Cancelar</button>
                        <button type="button" class="investment-delete-confirm" data-confirm-meeting-delete>Eliminar</button>
                    </div>
                </dialog>

                <dialog class="investment-delete-dialog" data-investment-delete-dialog>
                    <div class="investment-delete-dialog-icon" aria-hidden="true">
                        <i data-feather="trash-2"></i>
                    </div>
                    <div class="investment-delete-dialog-copy">
                        <h3>Eliminar inversion</h3>
                        <p data-investment-delete-message>Esta inversion se eliminara cuando guardes el sendero.</p>
                    </div>
                    <div class="investment-delete-dialog-actions">
                        <button type="button" class="btn-secondary" data-cancel-investment-delete>Cancelar</button>
                        <button type="button" class="investment-delete-confirm" data-confirm-investment-delete>Eliminar</button>
                    </div>
                </dialog>
            </form>
        </section>

        <?php if ($isEditingSendero && $imagenesExistentes): ?>
            <dialog class="image-picker-dialog" data-image-picker-dialog>
                <div class="image-picker-head">
                    <div><strong data-image-picker-title>Elegir imagen</strong>
                    <small>Elige una de las im&aacute;genes que ya cargaste en este sendero. No se vuelve a subir y la original se queda donde est&aacute;.</small></div>
                    <button type="button" class="image-picker-close" data-close-image-picker aria-label="Cerrar"><i data-feather="x"></i></button>
                </div>
                <div class="image-picker-grid">
                    <?php foreach ($imagenesExistentes as $imagenExistente): ?>
                        <button type="button" class="image-picker-item" data-pick-image data-ruta="<?= htmlspecialchars($imagenExistente['ruta'], ENT_QUOTES, 'UTF-8') ?>" data-src="<?= img_admin_src($imagenExistente['ruta']) ?>">
                            <img src="<?= img_admin_src($imagenExistente['ruta']) ?>" alt="" loading="lazy">
                            <span><?= htmlspecialchars(implode(' · ', $imagenExistente['etiquetas']), ENT_QUOTES, 'UTF-8') ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </dialog>
        <?php endif; ?>

        <?php if ($isEditingSendero): ?>
            <?php
            $imagenesBase = [
                [
                    'titulo' => 'Imagen principal',
                    'uso' => 'Detalle grande del sendero',
                    'ruta' => $edit['imagen_principal'] ?? '',
                ],
                [
                    'titulo' => 'Imagen flyer',
                    'uso' => 'Tarjeta de proximos senderos',
                    'ruta' => $edit['imagen_flyer'] ?? '',
                ],
                [
                    'titulo' => 'Imagen visitados/catalogo',
                    'uso' => 'Tarjeta de senderos visitados',
                    'ruta' => $edit['imagen_catalogo'] ?? '',
                ],
            ];
            ?>
            <section class="senderos-form-card image-summary-card">
                <div class="senderos-card-head">
                    <div>
                        <h2>Imagenes principales</h2>
                        <p>Vista rapida de las imagenes asignadas a cada pantalla publica.</p>
                    </div>
                </div>
                <div class="image-summary-grid">
                    <?php foreach ($imagenesBase as $imagenBase): ?>
                        <?php $src = img_admin_src($imagenBase['ruta']); ?>
                        <article class="image-summary-item">
                            <div class="image-summary-preview">
                                <?php if ($src !== ''): ?>
                                    <img src="<?= $src ?>" alt="<?= htmlspecialchars($imagenBase['titulo']) ?>">
                                <?php else: ?>
                                    <span>Sin imagen cargada</span>
                                <?php endif; ?>
                            </div>
                            <div class="image-summary-copy">
                                <strong><?= htmlspecialchars($imagenBase['titulo']) ?></strong>
                                <span><?= htmlspecialchars($imagenBase['uso']) ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($isEditingSendero && !empty($editGaleria)): ?>
            <section class="senderos-form-card gallery-admin-card">
                <div class="senderos-card-head">
                    <div>
                        <h2>Galeria actual</h2>
                        <p>Arrastra las imagenes o usa las flechas para definir el orden en que se mostraran.</p>
                    </div>
                </div>
                <form method="POST" action="<?= BASE_URL ?>procesos/proceso_senderos.php" class="gallery-order-form" data-gallery-order-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reorder_images">
                    <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
                    <div class="admin-gallery" data-sortable-gallery>
                        <?php foreach ($editGaleria as $index => $img): ?>
                            <article class="admin-gallery-item" draggable="true" data-gallery-item data-image-id="<?= (int) $img['id'] ?>">
                                <input type="hidden" name="gallery_order[]" value="<?= (int) $img['id'] ?>">
                                <img src="<?= img_admin_src($img['ruta_imagen']) ?>" alt="<?= htmlspecialchars($img['titulo'] ?? 'Imagen del sendero') ?>">
                                <span class="gallery-order-number" data-gallery-order-number><?= $index + 1 ?></span>
                                <span class="gallery-drag-handle" title="Arrastrar para ordenar" aria-hidden="true"><i data-feather="move"></i></span>
                                <div class="gallery-item-actions">
                                    <button type="button" class="gallery-move-button" data-gallery-move="left" aria-label="Mover imagen a la izquierda"><i data-feather="arrow-left"></i></button>
                                    <button type="button" class="gallery-move-button" data-gallery-move="right" aria-label="Mover imagen a la derecha"><i data-feather="arrow-right"></i></button>
                                    <button type="button" class="gallery-remove-button" data-gallery-delete-form="deleteGalleryImage<?= (int) $img['id'] ?>">Quitar</button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <div class="gallery-order-toolbar">
                        <span data-gallery-order-status>El orden actual esta guardado.</span>
                        <button type="submit" class="btn-primary" data-gallery-save-order disabled>Guardar orden</button>
                    </div>
                </form>
                <?php foreach ($editGaleria as $img): ?>
                    <form id="deleteGalleryImage<?= (int) $img['id'] ?>" method="POST" action="<?= BASE_URL ?>procesos/proceso_senderos.php" class="gallery-delete-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_image">
                        <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
                        <input type="hidden" name="image_id" value="<?= (int) $img['id'] ?>">
                    </form>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php else: ?>
        <section class="senderos-list-card">
            <div class="senderos-card-head">
                <div>
                    <h2>Senderos registrados</h2>
                    <p>Busca, edita o activa/inactiva rutas.</p>
                </div>
                <span class="senderos-list-count"><?= count($senderos) ?></span>
            </div>

            <div class="table-tools">
                <input type="text" id="searchInput" placeholder="Buscar por nombre, lugar, fecha, estado...">
            </div>

            <div class="senderos-table-wrap">
                <table class="senderos-admin-table" id="senderosTable">
                    <thead>
                        <tr>
                            <th>Imagen</th>
                            <th>Sendero</th>
                            <th>Fecha</th>
                            <th>Dificultad</th>
                            <th>Estado</th>
                            <th>Contenido</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($senderos)): ?>
                            <tr>
                                <td colspan="7" class="empty">No hay senderos registrados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($senderos as $s): ?>
                                <tr>
                                    <td>
                                        <?php $thumb = img_admin_src(($s['estado'] === 'visitado' ? $s['imagen_catalogo'] : $s['imagen_flyer']) ?: $s['imagen_principal']); ?>
                                        <?php if ($thumb !== ''): ?>
                                            <img class="row-thumb" src="<?= $thumb ?>" alt="<?= htmlspecialchars($s['nombre']) ?>">
                                        <?php else: ?>
                                            <span class="row-thumb-placeholder">Sin imagen</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($s['nombre']) ?></strong>
                                        <span><?= htmlspecialchars($s['lugar']) ?><?= !empty($s['provincia']) ? ', ' . htmlspecialchars($s['provincia']) : '' ?></span>
                                    </td>
                                    <td data-label="Fecha"><?= !empty($s['fecha_sendero']) ? date('d/m/Y', strtotime($s['fecha_sendero'])) : 'Sin fecha' ?></td>
                                    <td data-label="Dificultad"><?= htmlspecialchars($s['nivel_nombre']) ?></td>
                                    <td>
                                        <span class="state-pill <?= $s['estado'] === 'pendiente' ? 'pending' : 'visited' ?>"><?= htmlspecialchars($s['estado']) ?></span>
                                        <span class="state-pill <?= (int) $s['activo'] === 1 ? 'active' : 'inactive' ?>"><?= (int) $s['activo'] === 1 ? 'activo' : 'inactivo' ?></span>
                                    </td>
                                    <td>
                                        <?= $s['distancia_km'] !== null ? number_format((float) $s['distancia_km'], 2) . ' km' : 'Distancia pendiente' ?>
                                        <span><?= $s['tiempo_sendero_min'] !== null ? tiempo_legible($s['tiempo_sendero_min']) . ' sendero' : 'Tiempo pendiente' ?></span>
                                    </td>
                                    <td>
                                        <a class="btn-mini" href="<?= BASE_URL ?>mantenimientos/mantenimiento_senderos.php?edit=<?= (int) $s['id'] ?>">Editar</a>
                                        <form method="POST" action="<?= BASE_URL ?>procesos/proceso_senderos.php" class="inline-form">
                                            <input type="hidden" name="action" value="toggle_activo">
                                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                            <input type="hidden" name="activo" value="<?= (int) $s['activo'] === 1 ? 0 : 1 ?>">
                                            <button type="submit" class="btn-mini <?= (int) $s['activo'] === 1 ? 'warn' : 'ok' ?>">
                                                <?= (int) $s['activo'] === 1 ? 'Inactivar' : 'Activar' ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
