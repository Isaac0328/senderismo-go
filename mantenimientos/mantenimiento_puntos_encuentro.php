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

$pageTitle = "Mantenimiento Puntos de Encuentro | Senderismo Go!";

$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/detalles_admin.css",
    "css/puntos_encuentro_admin.css"
];

$jsFiles = [
    "js/barra_navegacion.js",
    "js/puntos_encuentro_admin.js"
];

require_once __DIR__ . '/../bd/conexion.php';

$puntos = [];
$resPuntos = mysqli_query($conn, "SELECT * FROM puntos_encuentro ORDER BY activo DESC, nombre ASC");
if ($resPuntos) {
    while ($row = mysqli_fetch_assoc($resPuntos)) {
        $puntos[] = $row;
    }
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<div class="detalles-page puntos-page">
    <div class="detalles-container">
        <div class="detalles-header">
            <div>
                <span class="detalles-kicker">Catalogo operativo</span>
                <h1 class="detalles-title">Mantenimiento Puntos de Encuentro</h1>
                <p class="detalles-subtitle">Registra puntos reutilizables con referencia y enlace de mapa para asignarlos a cada sendero.</p>
            </div>
            <div class="puntos-header-actions">
                <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_senderos.php" class="detalles-link">Volver a senderos</a>
                <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="detalles-link">Volver al panel</a>
            </div>
        </div>

        <?php if (!empty($_SESSION['puntos_success'])): ?>
            <div class="detalles-alert success"><?= htmlspecialchars($_SESSION['puntos_success']) ?></div>
            <?php unset($_SESSION['puntos_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['puntos_error'])): ?>
            <div class="detalles-alert error"><?= htmlspecialchars($_SESSION['puntos_error']) ?></div>
            <?php unset($_SESSION['puntos_error']); ?>
        <?php endif; ?>

        <section class="catalog-card puntos-card">
            <div class="catalog-head">
                <div>
                    <h2>Puntos registrados</h2>
                    <p>Estos puntos apareceran como lista desplegable en mantenimiento de senderos.</p>
                </div>
                <div class="catalog-head-actions">
                    <span><?= count($puntos) ?> registros</span>
                </div>
            </div>

            <div class="puntos-layout">
                <form class="catalog-form puntos-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_puntos_encuentro.php">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="0" data-point-id>

                    <div class="field">
                        <label>Nombre del punto *</label>
                        <input type="text" name="nombre" maxlength="120" required placeholder="Ej: Gurabo, en casa de Marcos." data-point-name>
                    </div>

                    <div class="field">
                        <label>Referencia o direccion</label>
                        <input type="text" name="direccion_referencia" maxlength="255" placeholder="Ej: Frente al parque, calle principal..." data-point-address>
                    </div>

                    <div class="field">
                        <label>URL de Google Maps</label>
                        <input type="url" name="url_mapa" maxlength="255" placeholder="https://maps.app.goo.gl/..." data-point-map>
                    </div>

                    <label class="active-row">
                        <input type="checkbox" name="activo" value="1" checked data-point-active>
                        <span>Activo</span>
                    </label>

                    <div class="catalog-actions">
                        <button type="submit" class="btn-primary" data-point-submit>Guardar punto</button>
                        <button type="button" class="btn-secondary" data-point-reset>Limpiar</button>
                    </div>
                </form>

                <div class="catalog-list puntos-list">
                    <?php if (empty($puntos)): ?>
                        <p class="empty">No hay puntos registrados.</p>
                    <?php else: ?>
                        <?php foreach ($puntos as $punto): ?>
                            <article class="catalog-item punto-item">
                                <div>
                                    <strong><?= htmlspecialchars($punto['nombre']) ?></strong>
                                    <?php if (!empty($punto['direccion_referencia'])): ?>
                                        <p><?= htmlspecialchars($punto['direccion_referencia']) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($punto['url_mapa'])): ?>
                                        <a href="<?= htmlspecialchars($punto['url_mapa']) ?>" target="_blank" rel="noopener">Abrir ubicacion</a>
                                    <?php endif; ?>
                                    <span class="<?= (int) $punto['activo'] === 1 ? 'pill active' : 'pill inactive' ?>">
                                        <?= (int) $punto['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </div>
                                <div class="catalog-item-actions">
                                    <button type="button"
                                        class="btn-mini edit-point"
                                        data-id="<?= (int) $punto['id'] ?>"
                                        data-nombre="<?= htmlspecialchars($punto['nombre']) ?>"
                                        data-direccion="<?= htmlspecialchars($punto['direccion_referencia'] ?? '') ?>"
                                        data-url="<?= htmlspecialchars($punto['url_mapa'] ?? '') ?>"
                                        data-activo="<?= (int) $punto['activo'] ?>">
                                        Editar
                                    </button>
                                    <form method="POST" action="<?= BASE_URL ?>procesos/proceso_puntos_encuentro.php" class="inline-form">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $punto['id'] ?>">
                                        <input type="hidden" name="activo" value="<?= (int) $punto['activo'] === 1 ? 0 : 1 ?>">
                                        <button type="submit" class="btn-mini <?= (int) $punto['activo'] === 1 ? 'warn' : 'ok' ?>">
                                            <?= (int) $punto['activo'] === 1 ? 'Inactivar' : 'Activar' ?>
                                        </button>
                                    </form>
                                    <?php if ((int) $punto['activo'] === 0): ?>
                                        <form method="POST" action="<?= BASE_URL ?>procesos/proceso_puntos_encuentro.php" class="inline-form" onsubmit="return confirm('Seguro que deseas eliminar este punto inactivo?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $punto['id'] ?>">
                                            <button type="submit" class="btn-mini danger">Eliminar</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</div>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
