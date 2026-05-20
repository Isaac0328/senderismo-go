<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['usuario_id']) || empty($_SESSION['logged_in'])) {
    header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
    exit;
}
if ((int) ($_SESSION['usuario_rol_id'] ?? 0) !== 1) {
    header("Location: " . BASE_URL . "pantallas/inicio.php");
    exit;
}

$pageTitle = "Mantenimiento Senderos | Senderismo Go!";

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

function img_admin_src(?string $ruta): string
{
    $ruta = trim((string) $ruta);
    if ($ruta !== '' && file_exists(__DIR__ . '/../' . $ruta)) {
        return BASE_URL . htmlspecialchars($ruta);
    }
    return '';
}

function fecha_admin_visual(?string $fecha): string
{
    $fecha = trim((string) $fecha);
    if ($fecha === '') {
        return '';
    }

    $dt = DateTime::createFromFormat('Y-m-d', $fecha);
    return $dt ? $dt->format('d/m/Y') : '';
}

function minutos_horas(int|string|null $minutos): array
{
    $total = max(0, (int) ($minutos ?? 0));
    return [intdiv($total, 60), $total % 60];
}

function tiempo_legible(int|string|null $minutos): string
{
    if ($minutos === null || $minutos === '') {
        return 'Tiempo pendiente';
    }

    $total = max(0, (int) $minutos);
    $horas = intdiv($total, 60);
    $mins = $total % 60;

    if ($horas > 0 && $mins > 0) {
        return $horas . ' h ' . $mins . ' min';
    }

    if ($horas > 0) {
        return $horas . ' h';
    }

    return $mins . ' min';
}

$niveles = [];
$resNiveles = mysqli_query($conn, "SELECT id, nombre FROM niveles_dificultad WHERE activo = 1 ORDER BY id ASC");
if ($resNiveles) {
    while ($row = mysqli_fetch_assoc($resNiveles)) {
        $niveles[] = $row;
    }
}

$terrenos = [];
$resTerrenos = mysqli_query($conn, "SELECT id, nombre FROM tipos_terreno WHERE activo = 1 ORDER BY nombre ASC");
if ($resTerrenos) {
    while ($row = mysqli_fetch_assoc($resTerrenos)) {
        $terrenos[] = $row;
    }
}

$caminosVehiculo = [];
$resCaminos = mysqli_query($conn, "SELECT id, nombre FROM tipos_camino_vehiculo WHERE activo = 1 ORDER BY nombre ASC");
if ($resCaminos) {
    while ($row = mysqli_fetch_assoc($resCaminos)) {
        $caminosVehiculo[] = $row;
    }
}

$anotaciones = [];
$resAnotaciones = mysqli_query($conn, "SELECT id, nombre, descripcion FROM anotaciones_importantes WHERE activo = 1 ORDER BY nombre ASC");
if ($resAnotaciones) {
    while ($row = mysqli_fetch_assoc($resAnotaciones)) {
        $anotaciones[] = $row;
    }
}

$incluyeItems = [];
$resIncluye = mysqli_query($conn, "SELECT id, nombre, descripcion FROM elementos_incluidos WHERE activo = 1 ORDER BY nombre ASC");
if ($resIncluye) {
    while ($row = mysqli_fetch_assoc($resIncluye)) {
        $incluyeItems[] = $row;
    }
}

$senderos = [];
$sqlSenderos = "
    SELECT s.*, nd.nombre AS nivel_nombre,
           tc.nombre AS camino_nombre,
           (SELECT COUNT(*) FROM sendero_imagenes si WHERE si.sendero_id = s.id AND si.activo = 1) AS total_imagenes,
           (SELECT COUNT(*) FROM sendero_puntos_encuentro sp WHERE sp.sendero_id = s.id AND sp.activo = 1) AS total_puntos
    FROM senderos s
    INNER JOIN niveles_dificultad nd ON nd.id = s.nivel_dificultad_id
    LEFT JOIN tipos_camino_vehiculo tc ON tc.id = s.tipo_camino_vehiculo_id
    ORDER BY s.fecha_sendero IS NULL ASC, s.fecha_sendero DESC, s.id DESC
";
$resSenderos = mysqli_query($conn, $sqlSenderos);
if ($resSenderos) {
    while ($row = mysqli_fetch_assoc($resSenderos)) {
        $senderos[] = $row;
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
$editTerrenos = [];
$editAnotaciones = [];
$editIncluye = [];
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

        $stmt = mysqli_prepare($conn, "SELECT anotacion_id FROM sendero_anotaciones WHERE sendero_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $editId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $editAnotaciones[] = (int) $row['anotacion_id'];
        }
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "SELECT incluye_id FROM sendero_elementos_incluidos WHERE sendero_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $editId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $editIncluye[] = (int) $row['incluye_id'];
        }
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "SELECT * FROM sendero_puntos_encuentro WHERE sendero_id = ? AND activo = 1 ORDER BY orden ASC, id ASC LIMIT 2");
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

[$idaHoras, $idaMinutos] = minutos_horas($edit['tiempo_ida_vehiculo_min'] ?? null);
[$regresoHoras, $regresoMinutos] = minutos_horas($edit['tiempo_regreso_vehiculo_min'] ?? null);
[$senderoHoras, $senderoMinutos] = minutos_horas($edit['tiempo_sendero_min'] ?? null);

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<div class="senderos-admin-page">
    <div class="senderos-admin-container">
        <div class="senderos-admin-header">
            <div>
                <span class="admin-kicker">Gestion de rutas</span>
                <h1 class="senderos-admin-title">Mantenimiento de Senderos</h1>
                <p class="senderos-admin-subtitle">Crea rutas, publica proximas salidas y carga las imagenes que vera el cliente.</p>
            </div>
            <div class="senderos-header-actions">
                <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="view-public-link">Volver al panel</a>
                <a href="<?= BASE_URL ?>pantallas/senderos.php" class="view-public-link">Ver pantalla publica</a>
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

        <section class="senderos-form-card">
            <div class="senderos-card-head">
                <div>
                    <h2 id="formTitle"><?= $edit ? 'Editar sendero' : 'Nuevo sendero' ?></h2>
                    <p>Los campos con * son obligatorios. Las imagenes se guardan en <strong>imagenes/senderos</strong>.</p>
                </div>
                <?php if ($edit): ?>
                    <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_senderos.php" class="clear-edit-link">Nuevo sendero</a>
                <?php endif; ?>
            </div>

            <form class="senderos-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_senderos.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

                <div class="form-grid">
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
                        <input type="text" id="provincia" name="provincia" maxlength="100" value="<?= htmlspecialchars($edit['provincia'] ?? '') ?>" placeholder="Ej: Duarte">
                    </div>

                    <div class="field span-3">
                        <label for="descripcion_corta">Descripcion corta</label>
                        <input type="text" id="descripcion_corta" name="descripcion_corta" maxlength="255" value="<?= htmlspecialchars($edit['descripcion_corta'] ?? '') ?>" placeholder="Texto breve para las tarjetas publicas">
                    </div>

                    <div class="field span-3">
                        <label for="imagen_principal">Imagen principal</label>
                        <input type="file" id="imagen_principal" name="imagen_principal" accept="image/png,image/jpeg,image/webp">
                        <small><?= $edit && !empty($edit['imagen_principal']) ? 'Deja vacio para conservar la imagen principal actual.' : 'Se muestra grande al abrir el detalle del sendero.' ?></small>
                    </div>

                    <div class="field span-3">
                        <label for="imagen_flyer">Imagen flyer</label>
                        <input type="file" id="imagen_flyer" name="imagen_flyer" accept="image/png,image/jpeg,image/webp">
                        <small><?= $edit && !empty($edit['imagen_flyer']) ? 'Deja vacio para conservar el flyer actual.' : 'Se muestra en proximos senderos.' ?></small>
                    </div>

                    <div class="field span-3">
                        <label for="imagen_catalogo">Imagen visitados/catalogo</label>
                        <input type="file" id="imagen_catalogo" name="imagen_catalogo" accept="image/png,image/jpeg,image/webp">
                        <small><?= $edit && !empty($edit['imagen_catalogo']) ? 'Deja vacio para conservar la imagen de catalogo actual.' : 'Se muestra en senderos visitados.' ?></small>
                    </div>

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

                    <div class="field span-3">
                        <label for="galeria">Galeria de imagenes</label>
                        <input type="file" id="galeria" name="galeria[]" accept="image/png,image/jpeg,image/webp" multiple>
                        <small id="galleryHelp">Puedes cargar varias imagenes a la vez.</small>
                    </div>

                    <div class="field span-6">
                        <label class="section-label">Detalles del sendero</label>
                        <div class="detail-buttons-row">
                            <button type="button" class="detail-modal-trigger" data-modal-target="modalAnotaciones">
                                Agregar anotaciones
                                <span id="anotacionesCount"><?= count($editAnotaciones) ?></span>
                            </button>
                            <button type="button" class="detail-modal-trigger" data-modal-target="modalIncluye">
                                Agregar que incluye
                                <span id="incluyeCount"><?= count($editIncluye) ?></span>
                            </button>
                            <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_detalles.php" class="detail-admin-link">
                                Mantener catalogos
                            </a>
                        </div>
                    </div>

                    <div class="field span-6">
                        <label class="section-label">Puntos de encuentro</label>
                        <div class="meeting-grid">
                            <?php for ($i = 0; $i < 2; $i++): ?>
                                <?php $punto = $editPuntos[$i] ?? []; ?>
                                <div class="meeting-card">
                                    <strong>Punto <?= $i + 1 ?></strong>
                                    <input type="text" name="puntos[<?= $i ?>][nombre_punto]" value="<?= htmlspecialchars($punto['nombre_punto'] ?? '') ?>" placeholder="Nombre del punto">
                                    <input type="text" name="puntos[<?= $i ?>][direccion_referencia]" value="<?= htmlspecialchars($punto['direccion_referencia'] ?? '') ?>" placeholder="Referencia o direccion">
                                    <div class="time-row">
                                        <input type="time" name="puntos[<?= $i ?>][hora_encuentro]" value="<?= htmlspecialchars(isset($punto['hora_encuentro']) ? substr($punto['hora_encuentro'], 0, 5) : '') ?>">
                                        <input type="time" name="puntos[<?= $i ?>][hora_salida]" value="<?= htmlspecialchars(isset($punto['hora_salida']) ? substr($punto['hora_salida'], 0, 5) : '') ?>">
                                    </div>
                                    <input type="url" name="puntos[<?= $i ?>][url_mapa]" value="<?= htmlspecialchars($punto['url_mapa'] ?? '') ?>" placeholder="URL de Google Maps">
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <label class="active-toggle">
                        <input type="checkbox" name="activo" value="1" <?= (int) ($edit['activo'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <span>Visible en la pagina publica</span>
                    </label>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary"><?= $edit ? 'Actualizar sendero' : 'Guardar sendero' ?></button>
                        <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_senderos.php" class="btn-secondary">Limpiar</a>
                    </div>
                </div>

                <div class="detail-modal" id="modalAnotaciones" aria-hidden="true">
                    <div class="detail-modal-backdrop" data-modal-close></div>
                    <div class="detail-modal-panel">
                        <div class="detail-modal-head">
                            <div>
                                <h3>Anotaciones importantes</h3>
                                <p>Selecciona las recomendaciones que aplican para este sendero.</p>
                            </div>
                            <button type="button" class="detail-modal-close" data-modal-close>&times;</button>
                        </div>
                        <div class="detail-modal-list" data-count-target="anotacionesCount">
                            <?php foreach ($anotaciones as $item): ?>
                                <label class="modal-check-item">
                                    <input type="checkbox" name="anotaciones[]" value="<?= (int) $item['id'] ?>" <?= in_array((int) $item['id'], $editAnotaciones, true) ? 'checked' : '' ?>>
                                    <span>
                                        <strong><?= htmlspecialchars($item['nombre']) ?></strong>
                                        <?php if (!empty($item['descripcion'])): ?>
                                            <small><?= htmlspecialchars($item['descripcion']) ?></small>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="detail-modal-actions">
                            <button type="button" class="btn-primary" data-modal-close>Listo</button>
                        </div>
                    </div>
                </div>

                <div class="detail-modal" id="modalIncluye" aria-hidden="true">
                    <div class="detail-modal-backdrop" data-modal-close></div>
                    <div class="detail-modal-panel">
                        <div class="detail-modal-head">
                            <div>
                                <h3>Este sendero incluye</h3>
                                <p>Selecciona los servicios o articulos incluidos en esta actividad.</p>
                            </div>
                            <button type="button" class="detail-modal-close" data-modal-close>&times;</button>
                        </div>
                        <div class="detail-modal-list" data-count-target="incluyeCount">
                            <?php foreach ($incluyeItems as $item): ?>
                                <label class="modal-check-item">
                                    <input type="checkbox" name="incluye[]" value="<?= (int) $item['id'] ?>" <?= in_array((int) $item['id'], $editIncluye, true) ? 'checked' : '' ?>>
                                    <span>
                                        <strong><?= htmlspecialchars($item['nombre']) ?></strong>
                                        <?php if (!empty($item['descripcion'])): ?>
                                            <small><?= htmlspecialchars($item['descripcion']) ?></small>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="detail-modal-actions">
                            <button type="button" class="btn-primary" data-modal-close>Listo</button>
                        </div>
                    </div>
                </div>
            </form>
        </section>

        <?php if ($edit): ?>
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

        <?php if ($edit && !empty($editGaleria)): ?>
            <section class="senderos-form-card gallery-admin-card">
                <div class="senderos-card-head">
                    <div>
                        <h2>Galeria actual</h2>
                        <p>Estas imagenes se muestran en el detalle del sendero.</p>
                    </div>
                </div>
                <div class="admin-gallery">
                    <?php foreach ($editGaleria as $img): ?>
                        <div class="admin-gallery-item">
                            <img src="<?= img_admin_src($img['ruta_imagen']) ?>" alt="<?= htmlspecialchars($img['titulo'] ?? 'Imagen del sendero') ?>">
                            <form method="POST" action="<?= BASE_URL ?>procesos/proceso_senderos.php">
                                <input type="hidden" name="action" value="delete_image">
                                <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
                                <input type="hidden" name="image_id" value="<?= (int) $img['id'] ?>">
                                <button type="submit">Quitar</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="senderos-list-card">
            <div class="senderos-card-head">
                <div>
                    <h2>Senderos registrados</h2>
                    <p>Busca, edita o activa/inactiva rutas.</p>
                </div>
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
                                    <td><?= !empty($s['fecha_sendero']) ? date('d/m/Y', strtotime($s['fecha_sendero'])) : 'Sin fecha' ?></td>
                                    <td><?= htmlspecialchars($s['nivel_nombre']) ?></td>
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
    </div>
</div>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
