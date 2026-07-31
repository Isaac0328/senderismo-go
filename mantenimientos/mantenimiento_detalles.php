<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../componentes/recordar_sesion.php';
sg_restaurar_sesion_recordada();

$PERMISO_REQUERIDO = 'operaciones.detalles';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';

$pageTitle = "Mantenimiento Detalles | Senderismo Go!";

$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/detalles_admin.css"
];

$jsFiles = [
    "js/barra_navegacion.js",
    "js/detalles_admin.js"
];

require_once __DIR__ . '/../bd/conexion.php';

function fetch_catalog(mysqli $conn, string $table, bool $withLevel = false): array
{
    $items = [];
    $fields = $withLevel
        ? "id, nombre, descripcion, nivel_numero, activo, created_at"
        : "id, nombre, descripcion, activo, created_at";
    $order = $withLevel ? "activo DESC, nivel_numero ASC, nombre ASC" : "activo DESC, nombre ASC";
    $res = mysqli_query($conn, "SELECT {$fields} FROM {$table} ORDER BY {$order}");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $items[] = $row;
        }
    }
    return $items;
}

$catalogs = [
    'dificultad' => [
        'title' => 'Niveles de dificultad',
        'subtitle' => 'Escalas usadas para clasificar cada sendero.',
        'table' => 'niveles_dificultad',
        'placeholder' => 'Ej: Basico, Intermedio, Avanzado',
        'with_level' => true,
    ],
    'terreno' => [
        'title' => 'Tipos de terreno',
        'subtitle' => 'Terrenos que se asignan a cada sendero.',
        'table' => 'tipos_terreno',
        'placeholder' => 'Ej: Pedregoso, Humedo, Alta vegetacion',
    ],
    'camino' => [
        'title' => 'Camino vehicular',
        'subtitle' => 'Condicion del trayecto en vehiculo.',
        'table' => 'tipos_camino_vehiculo',
        'placeholder' => 'Ej: Carretera asfaltada, tierra, mixto',
    ],
    'anotacion' => [
        'title' => 'Anotaciones importantes',
        'subtitle' => 'Recomendaciones que el cliente vera en el detalle.',
        'table' => 'anotaciones_importantes',
        'placeholder' => 'Ej: Debe llevar repelente',
    ],
    'incluye' => [
        'title' => 'Este sendero incluye',
        'subtitle' => 'Servicios o articulos incluidos en la actividad.',
        'table' => 'elementos_incluidos',
        'placeholder' => 'Ej: Staff y guias certificados',
    ],
];

foreach ($catalogs as $key => $config) {
    $catalogs[$key]['items'] = fetch_catalog($conn, $config['table'], !empty($config['with_level']));
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<div class="detalles-page">
    <div class="detalles-container">
        <div class="detalles-header">
            <div>
                <span class="detalles-kicker">Catalogos del sendero</span>
                <h1 class="detalles-title">Mantenimiento Detalles</h1>
                <p class="detalles-subtitle">Administra terrenos, anotaciones importantes y elementos incluidos.</p>
            </div>
            <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="detalles-link">Volver al panel</a>
        </div>

        <?php if (!empty($_SESSION['detalles_success'])): ?>
            <div class="detalles-alert success"><?= htmlspecialchars($_SESSION['detalles_success']) ?></div>
            <?php unset($_SESSION['detalles_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['detalles_error'])): ?>
            <div class="detalles-alert error"><?= htmlspecialchars($_SESSION['detalles_error']) ?></div>
            <?php unset($_SESSION['detalles_error']); ?>
        <?php endif; ?>

        <div class="detalles-tabs">
            <?php foreach ($catalogs as $key => $config): ?>
                <a href="#<?= $key ?>"><?= htmlspecialchars($config['title']) ?></a>
            <?php endforeach; ?>
        </div>

        <div class="catalog-grid">
            <?php foreach ($catalogs as $key => $config): ?>
                <section class="catalog-card is-collapsed" id="<?= $key ?>" data-catalog-card="<?= $key ?>">
                    <div class="catalog-head">
                        <div>
                            <h2><?= htmlspecialchars($config['title']) ?></h2>
                            <p><?= htmlspecialchars($config['subtitle']) ?></p>
                        </div>
                        <div class="catalog-head-actions">
                            <span><?= count($config['items']) ?> registros</span>
                            <button type="button" class="catalog-toggle" data-toggle-catalog="<?= $key ?>" aria-label="Plegar o desplegar <?= htmlspecialchars($config['title']) ?>" aria-expanded="false">
                                <i data-feather="chevron-up"></i>
                            </button>
                        </div>
                    </div>

                    <div class="catalog-body">
                        <form class="catalog-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_detalles.php">
                            <input type="hidden" name="action" value="save">
                            <input type="hidden" name="catalog" value="<?= $key ?>">
                            <input type="hidden" name="id" value="0" data-id-field="<?= $key ?>">

                            <div class="field">
                                <label>Nombre *</label>
                                <input type="text" name="nombre" maxlength="120" required placeholder="<?= htmlspecialchars($config['placeholder']) ?>" data-name-field="<?= $key ?>">
                            </div>

                            <div class="field">
                                <label>Descripcion</label>
                                <textarea name="descripcion" maxlength="255" rows="3" placeholder="Detalle opcional que ayuda a explicar este item." data-desc-field="<?= $key ?>"></textarea>
                            </div>

                            <?php if (!empty($config['with_level'])): ?>
                                <div class="field">
                                    <label>Nivel de dificultad (0 a 100) *</label>
                                    <input type="number" name="nivel_numero" min="0" max="100" required value="50" data-level-field="<?= $key ?>">
                                    <small class="field-note">Este numero define el color de la carita en el detalle del sendero.</small>
                                </div>
                            <?php endif; ?>

                            <label class="active-row">
                                <input type="checkbox" name="activo" value="1" checked data-active-field="<?= $key ?>">
                                <span>Activo</span>
                            </label>

                            <div class="catalog-actions">
                                <button type="submit" class="btn-primary" data-submit-field="<?= $key ?>">Guardar</button>
                                <button type="button" class="btn-secondary reset-catalog" data-catalog="<?= $key ?>">Limpiar</button>
                            </div>
                        </form>

                        <div class="catalog-list">
                            <?php if (empty($config['items'])): ?>
                                <p class="empty">No hay registros.</p>
                            <?php else: ?>
                                <?php foreach ($config['items'] as $item): ?>
                                    <article class="catalog-item">
                                        <div>
                                            <strong><?= htmlspecialchars($item['nombre']) ?></strong>
                                            <?php if (!empty($item['descripcion'])): ?>
                                                <p><?= htmlspecialchars($item['descripcion']) ?></p>
                                            <?php endif; ?>
                                            <span class="<?= (int) $item['activo'] === 1 ? 'pill active' : 'pill inactive' ?>">
                                                <?= (int) $item['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                            <?php if (!empty($config['with_level'])): ?>
                                                <span class="pill level">Nivel <?= (int) ($item['nivel_numero'] ?? 50) ?>/100</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="catalog-item-actions">
                                            <button type="button"
                                                class="btn-mini edit-detail"
                                                data-catalog="<?= $key ?>"
                                                data-id="<?= (int) $item['id'] ?>"
                                                data-nombre="<?= htmlspecialchars($item['nombre']) ?>"
                                                data-descripcion="<?= htmlspecialchars($item['descripcion'] ?? '') ?>"
                                                data-nivel="<?= (int) ($item['nivel_numero'] ?? 50) ?>"
                                                data-activo="<?= (int) $item['activo'] ?>">
                                                Editar
                                            </button>
                                            <form method="POST" action="<?= BASE_URL ?>procesos/proceso_detalles.php" class="inline-form">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="catalog" value="<?= $key ?>">
                                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                                <input type="hidden" name="activo" value="<?= (int) $item['activo'] === 1 ? 0 : 1 ?>">
                                                <button type="submit" class="btn-mini <?= (int) $item['activo'] === 1 ? 'warn' : 'ok' ?>">
                                                    <?= (int) $item['activo'] === 1 ? 'Inactivar' : 'Activar' ?>
                                                </button>
                                            </form>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
