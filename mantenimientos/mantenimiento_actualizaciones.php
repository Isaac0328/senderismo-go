<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/rutas.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$PERMISO_REQUERIDO = 'sistema.actualizaciones';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';

if (!sg_is_admin_role()) {
    http_response_code(403);
    exit('Solo el administrador principal puede gestionar actualizaciones.');
}

require_once __DIR__ . '/../componentes/migraciones.php';

function actualizaciones_h($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$pendientes = [];
$aplicadas = [];
$archivado = ['archivadas' => [], 'errores' => []];
$error = '';
try {
    $pendientes = sg_migraciones_pendientes($conn);
    $resultado = mysqli_query($conn, 'SELECT id, descripcion, aplicada_at FROM sistema_migraciones ORDER BY id DESC');
    while ($resultado && $fila = mysqli_fetch_assoc($resultado)) {
        $aplicadas[] = $fila;
    }
    if ($resultado) {
        mysqli_free_result($resultado);
    }
    $archivado = sg_migraciones_archivar_aplicadas($conn);
} catch (Throwable $exception) {
    $error = APP_DEBUG ? $exception->getMessage() : 'No se pudo leer el estado de actualizaciones.';
}

$pageTitle = 'Actualizaciones del sistema';
$cssFiles = ['css/global.css', 'css/barra_navegacion.css', 'css/actualizaciones_sistema.css'];
$jsFiles = ['js/barra_navegacion.js'];

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>
<main class="updates-page">
    <header class="updates-header">
        <div>
            <span>ADMINISTRACION TECNICA</span>
            <h1>Actualizaciones del sistema</h1>
            <p>Revisa los cambios pendientes antes de aplicarlos a esta plataforma.</p>
        </div>
        <a href="<?= actualizaciones_h(sg_clean_url('pantallas/panel_administrativo.php')) ?>" class="updates-back">Volver al panel</a>
    </header>

    <?php if (!empty($_SESSION['actualizaciones_success'])): ?>
        <div class="updates-alert success"><?= actualizaciones_h($_SESSION['actualizaciones_success']); unset($_SESSION['actualizaciones_success']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['actualizaciones_error'])): ?>
        <div class="updates-alert error"><?= actualizaciones_h($_SESSION['actualizaciones_error']); unset($_SESSION['actualizaciones_error']); ?></div>
    <?php endif; ?>
    <?php if ($archivado['archivadas']): ?>
        <div class="updates-alert success">Se archivaron <?= count($archivado['archivadas']) ?> migracion(es) en migraciones/CARGADAS.</div>
    <?php endif; ?>
    <?php if ($archivado['errores']): ?>
        <div class="updates-alert error">Las migraciones ya aplicadas siguen registradas, pero algunos archivos no pudieron moverse a CARGADAS: <?= actualizaciones_h(implode(' ', $archivado['errores'])) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="updates-alert error"><?= actualizaciones_h($error) ?></div>
    <?php elseif (!$pendientes): ?>
        <section class="updates-status ready"><strong>La plataforma esta al dia.</strong><p>No hay cambios tecnicos pendientes por aplicar.</p></section>
    <?php else: ?>
        <section class="updates-status warning"><strong>Hay <?= count($pendientes) ?> actualizacion<?= count($pendientes) === 1 ? '' : 'es' ?> pendiente<?= count($pendientes) === 1 ? '' : 's' ?>.</strong><p>Antes de continuar, crea un respaldo de la base de datos desde Hostinger o phpMyAdmin.</p></section>
        <section class="updates-card">
            <h2>Cambios pendientes</h2>
            <div class="updates-list">
                <?php foreach ($pendientes as $migracion): ?>
                    <article><code><?= actualizaciones_h($migracion['id']) ?></code><p><?= actualizaciones_h($migracion['descripcion']) ?></p></article>
                <?php endforeach; ?>
            </div>
            <form method="post" action="<?= actualizaciones_h(BASE_URL) ?>procesos/proceso_actualizaciones_sistema.php" class="updates-confirm-form">
                <?= csrf_field() ?>
                <label>Escribe <strong>ACTUALIZAR</strong> para confirmar<input name="confirmacion" autocomplete="off" required></label>
                <button type="submit">Aplicar actualizaciones</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($aplicadas): ?>
        <section class="updates-card history">
            <h2>Historial aplicado</h2>
            <div class="updates-list">
                <?php foreach ($aplicadas as $migracion): ?>
                    <article><code><?= actualizaciones_h($migracion['id']) ?></code><p><?= actualizaciones_h($migracion['descripcion']) ?><small>Aplicada: <?= actualizaciones_h($migracion['aplicada_at']) ?></small></p></article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
