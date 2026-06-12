<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/tema_colores.php';
require_once __DIR__ . '/../bd/conexion.php';

$pageTitle = "Mantenimiento Tema | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/tema_admin.css"
];
$jsFiles = [
    "js/barra_navegacion.js",
    "js/tema_admin.js"
];

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS configuracion_tema (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
        tema VARCHAR(40) NOT NULL DEFAULT 'senderismo',
        primary_color VARCHAR(7) NOT NULL DEFAULT '#255f38',
        primary_dark_color VARCHAR(7) NOT NULL DEFAULT '#102617',
        accent_color VARCHAR(7) NOT NULL DEFAULT '#e10600',
        accent_dark_color VARCHAR(7) NOT NULL DEFAULT '#b90000',
        background_color VARCHAR(7) NOT NULL DEFAULT '#f3f6ef',
        surface_color VARCHAR(7) NOT NULL DEFAULT '#ffffff',
        text_color VARCHAR(7) NOT NULL DEFAULT '#111111',
        muted_color VARCHAR(7) NOT NULL DEFAULT '#5f6d64',
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");
$resCount = mysqli_query($conn, "SELECT COUNT(*) AS total FROM configuracion_tema");
$total = ($resCount && ($row = mysqli_fetch_assoc($resCount))) ? (int) $row['total'] : 0;
if ($total === 0) {
    mysqli_query($conn, "INSERT INTO configuracion_tema (id) VALUES (1)");
}

$config = [];
$res = mysqli_query($conn, "SELECT * FROM configuracion_tema WHERE id = 1 LIMIT 1");
if ($res) {
    $config = mysqli_fetch_assoc($res) ?: [];
}

$paletas = sg_paletas_colores();

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="tema-admin-page">
    <div class="tema-admin-container">
        <header class="tema-admin-header">
            <div>
                <span class="tema-admin-kicker">Configuracion visual</span>
                <h1>Paleta de colores</h1>
                <p>Selecciona un tema estandar o personaliza los colores principales de toda la plataforma.</p>
            </div>
            <div class="tema-admin-actions">
                <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="tema-admin-link soft">Volver al panel</a>
            </div>
        </header>

        <?php if (!empty($_SESSION['tema_admin_success'])): ?>
            <div class="tema-alert success"><?= h($_SESSION['tema_admin_success']) ?></div>
            <?php unset($_SESSION['tema_admin_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['tema_admin_error'])): ?>
            <div class="tema-alert error"><?= h($_SESSION['tema_admin_error']) ?></div>
            <?php unset($_SESSION['tema_admin_error']); ?>
        <?php endif; ?>

        <form class="tema-card tema-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_tema.php">
            <div class="tema-card-head">
                <div>
                    <h2>Temas estandares</h2>
                    <p>Estas opciones cambian rapidamente la identidad visual general.</p>
                </div>
                <span><?= h($config['tema'] ?? 'senderismo') ?></span>
            </div>

            <div class="theme-grid">
                <?php foreach ($paletas as $key => $paleta): ?>
                    <label class="theme-option">
                        <input
                            type="radio"
                            name="tema"
                            value="<?= h($key) ?>"
                            data-primary="<?= h($paleta['primary']) ?>"
                            data-primary-dark="<?= h($paleta['primary_dark']) ?>"
                            data-accent="<?= h($paleta['accent']) ?>"
                            data-accent-dark="<?= h($paleta['accent_dark']) ?>"
                            data-background="<?= h($paleta['background']) ?>"
                            data-surface="<?= h($paleta['surface']) ?>"
                            data-text="<?= h($paleta['text']) ?>"
                            data-muted="<?= h($paleta['muted']) ?>"
                            <?= (($config['tema'] ?? 'senderismo') === $key) ? 'checked' : '' ?>>
                        <span>
                            <strong><?= h($paleta['nombre']) ?></strong>
                            <small>Paleta predefinida</small>
                            <span class="swatches">
                                <i style="background: <?= h($paleta['primary']) ?>"></i>
                                <i style="background: <?= h($paleta['primary_dark']) ?>"></i>
                                <i style="background: <?= h($paleta['accent']) ?>"></i>
                                <i style="background: <?= h($paleta['background']) ?>"></i>
                            </span>
                        </span>
                    </label>
                <?php endforeach; ?>

                <label class="theme-option custom">
                    <input type="radio" name="tema" value="personalizado" <?= (($config['tema'] ?? '') === 'personalizado') ? 'checked' : '' ?>>
                    <span>
                        <strong>Personalizado</strong>
                        <small>Define cada color manualmente</small>
                        <span class="swatches">
                            <i style="background: <?= h($config['primary_color'] ?? '#255f38') ?>"></i>
                            <i style="background: <?= h($config['primary_dark_color'] ?? '#102617') ?>"></i>
                            <i style="background: <?= h($config['accent_color'] ?? '#e10600') ?>"></i>
                            <i style="background: <?= h($config['background_color'] ?? '#f3f6ef') ?>"></i>
                        </span>
                    </span>
                </label>
            </div>

            <div class="tema-section-line">Personalizar colores</div>
            <div class="color-grid">
                <label><span>Primario</span><input type="color" name="primary_color" value="<?= h($config['primary_color'] ?? '#255f38') ?>"></label>
                <label><span>Primario oscuro</span><input type="color" name="primary_dark_color" value="<?= h($config['primary_dark_color'] ?? '#102617') ?>"></label>
                <label><span>Acento</span><input type="color" name="accent_color" value="<?= h($config['accent_color'] ?? '#e10600') ?>"></label>
                <label><span>Acento oscuro</span><input type="color" name="accent_dark_color" value="<?= h($config['accent_dark_color'] ?? '#b90000') ?>"></label>
                <label><span>Fondo</span><input type="color" name="background_color" value="<?= h($config['background_color'] ?? '#f3f6ef') ?>"></label>
                <label><span>Superficie</span><input type="color" name="surface_color" value="<?= h($config['surface_color'] ?? '#ffffff') ?>"></label>
                <label><span>Texto</span><input type="color" name="text_color" value="<?= h($config['text_color'] ?? '#111111') ?>"></label>
                <label><span>Texto suave</span><input type="color" name="muted_color" value="<?= h($config['muted_color'] ?? '#5f6d64') ?>"></label>
            </div>

            <div class="theme-preview">
                <div>
                    <span>Vista previa</span>
                    <h3>Senderismo Go</h3>
                    <p>Asi se veran botones, fondos y textos principales.</p>
                </div>
                <button type="button">Accion principal</button>
            </div>

            <button type="submit" class="tema-submit">
                <i data-feather="save"></i>
                Guardar paleta
            </button>
        </form>
    </div>
</main>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
