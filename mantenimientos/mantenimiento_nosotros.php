<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';

$pageTitle = "Mantenimiento Nosotros | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/nosotros_admin.css"
];
$jsFiles = [
    "js/barra_navegacion.js"
];

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
mysqli_set_charset($conn, 'utf8mb4');

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS configuracion_nosotros (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
        hero_imagen VARCHAR(255) NOT NULL DEFAULT 'imagenes/paisajes/hero.jpg',
        hero_kicker VARCHAR(80) NOT NULL DEFAULT 'Nosotros',
        hero_titulo VARCHAR(180) NOT NULL,
        hero_subtitulo TEXT NOT NULL,
        boton_principal_texto VARCHAR(80) NOT NULL DEFAULT 'Ver senderos',
        boton_principal_url VARCHAR(255) NOT NULL DEFAULT 'pantallas/senderos.php',
        boton_secundario_texto VARCHAR(80) NOT NULL DEFAULT 'Coordinar una ruta',
        boton_secundario_url VARCHAR(255) NOT NULL DEFAULT 'pantallas/contacto.php',
        historia_imagen VARCHAR(255) NOT NULL DEFAULT 'imagenes/paisajes/img4.jpg',
        historia_badge_titulo VARCHAR(80) NOT NULL DEFAULT 'Desde 2015',
        historia_badge_texto VARCHAR(120) NOT NULL DEFAULT 'Creando comunidad outdoor',
        historia_kicker VARCHAR(80) NOT NULL DEFAULT 'Nuestra historia',
        historia_titulo VARCHAR(180) NOT NULL,
        historia_texto_1 TEXT NOT NULL,
        historia_texto_2 TEXT NOT NULL,
        valores_kicker VARCHAR(80) NOT NULL DEFAULT 'Nuestro compromiso',
        valores_titulo VARCHAR(180) NOT NULL,
        valores_texto TEXT NOT NULL,
        proceso_kicker VARCHAR(80) NOT NULL DEFAULT 'Como trabajamos',
        proceso_titulo VARCHAR(180) NOT NULL,
        proceso_texto TEXT NOT NULL,
        equipo_kicker VARCHAR(80) NOT NULL DEFAULT 'Equipo',
        equipo_titulo VARCHAR(180) NOT NULL,
        equipo_texto TEXT NOT NULL,
        cta_kicker VARCHAR(80) NOT NULL DEFAULT 'Proxima aventura',
        cta_titulo VARCHAR(180) NOT NULL,
        cta_texto TEXT NOT NULL,
        cta_boton_principal_texto VARCHAR(80) NOT NULL DEFAULT 'Ver proximos',
        cta_boton_principal_url VARCHAR(255) NOT NULL DEFAULT 'pantallas/senderos.php',
        cta_boton_secundario_texto VARCHAR(80) NOT NULL DEFAULT 'Contactar',
        cta_boton_secundario_url VARCHAR(255) NOT NULL DEFAULT 'pantallas/contacto.php',
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS nosotros_indicadores (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, valor VARCHAR(40) NOT NULL, etiqueta VARCHAR(120) NOT NULL, orden INT NOT NULL DEFAULT 0, activo TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS nosotros_valores (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, icono VARCHAR(60) NOT NULL DEFAULT 'leaf', titulo VARCHAR(120) NOT NULL, texto TEXT NOT NULL, orden INT NOT NULL DEFAULT 0, activo TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS nosotros_pasos (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, numero VARCHAR(10) NOT NULL, titulo VARCHAR(140) NOT NULL, texto TEXT NOT NULL, orden INT NOT NULL DEFAULT 0, activo TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS nosotros_equipo (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(120) NOT NULL, rol VARCHAR(160) NOT NULL, imagen VARCHAR(255) NOT NULL, orden INT NOT NULL DEFAULT 0, activo TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$resCfg = mysqli_query($conn, "SELECT COUNT(*) AS total FROM configuracion_nosotros");
$totalCfg = ($resCfg && ($rowCfg = mysqli_fetch_assoc($resCfg))) ? (int) $rowCfg['total'] : 0;
if ($totalCfg === 0) {
    mysqli_query($conn, "
        INSERT INTO configuracion_nosotros (
            id, hero_titulo, hero_subtitulo, historia_titulo, historia_texto_1, historia_texto_2,
            valores_titulo, valores_texto, proceso_titulo, proceso_texto, equipo_titulo, equipo_texto,
            cta_titulo, cta_texto
        ) VALUES (
            1,
            'Guiamos experiencias que conectan personas con la naturaleza.',
            'Senderismo Go nace para que cada persona pueda descubrir rutas, paisajes y comunidades con una experiencia organizada, cercana y responsable.',
            'De una caminata entre amigos a una comunidad de aventura.',
            'Lo que comenzo como recorridos entre personas amantes de la montana se convirtio en una forma de compartir naturaleza, bienestar y companerismo.',
            'Trabajamos con planificacion, guias preparados, comunicacion clara y respeto por cada espacio natural.',
            'Lo que cuidamos en cada salida',
            'La aventura debe sentirse emocionante, pero tambien organizada, clara y humana.',
            'Una ruta bien vivida empieza antes de caminar.',
            'Cada experiencia se prepara con informacion practica: dificultad, distancia, tiempos, puntos de encuentro, terreno y recomendaciones.',
            'Personas detras de cada experiencia',
            'Un equipo enfocado en seguridad, logistica, orientacion y buen trato.',
            'Quieres caminar con nosotros?',
            'Explora los proximos senderos o escribenos para coordinar una experiencia privada.'
        )
    ");
}

$config = [];
$res = mysqli_query($conn, "SELECT * FROM configuracion_nosotros WHERE id = 1 LIMIT 1");
if ($res) {
    $config = mysqli_fetch_assoc($res) ?: [];
}

function cargar_items_nosotros(mysqli $conn, string $tabla): array
{
    $items = [];
    $res = mysqli_query($conn, "SELECT * FROM {$tabla} ORDER BY orden ASC, id ASC");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $items[] = $row;
    }
    return $items;
}

$indicadores = cargar_items_nosotros($conn, 'nosotros_indicadores');
$valores = cargar_items_nosotros($conn, 'nosotros_valores');
$pasos = cargar_items_nosotros($conn, 'nosotros_pasos');
$equipo = cargar_items_nosotros($conn, 'nosotros_equipo');

$editTipo = (string) ($_GET['tipo'] ?? '');
$editId = max(0, (int) ($_GET['item_id'] ?? 0));

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="nosotros-admin-page">
    <div class="nosotros-admin-container">
        <header class="nosotros-admin-header">
            <div>
                <span class="nosotros-admin-kicker">Contenido publico</span>
                <h1>Mantenimiento Nosotros</h1>
                <p>Personaliza la historia, indicadores, valores, pasos de trabajo y equipo que se muestran al visitante.</p>
            </div>
            <div class="nosotros-admin-actions">
                <a href="<?= BASE_URL ?>pantallas/nosotros.php" target="_blank" class="nosotros-admin-link">Ver pagina publica</a>
                <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="nosotros-admin-link soft">Volver al panel</a>
            </div>
        </header>

        <?php if (!empty($_SESSION['nosotros_admin_success'])): ?>
            <div class="nosotros-alert success"><?= h($_SESSION['nosotros_admin_success']) ?></div>
            <?php unset($_SESSION['nosotros_admin_success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['nosotros_admin_error'])): ?>
            <div class="nosotros-alert error"><?= h($_SESSION['nosotros_admin_error']) ?></div>
            <?php unset($_SESSION['nosotros_admin_error']); ?>
        <?php endif; ?>

        <form class="nosotros-card nosotros-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_nosotros.php" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="guardar_config">
            <div class="nosotros-card-head">
                <div>
                    <h2>Contenido principal</h2>
                    <p>Textos e imagenes generales de la pantalla Nosotros.</p>
                </div>
                <span>Editable</span>
            </div>

            <div class="admin-section-line">Hero</div>
            <div class="form-grid two">
                <label><span>Etiqueta *</span><input name="hero_kicker" required maxlength="80" value="<?= h($config['hero_kicker'] ?? '') ?>"></label>
                <label><span>Imagen hero</span><input type="file" name="hero_imagen" accept="image/jpeg,image/png,image/webp"><small>Actual: <?= h($config['hero_imagen'] ?? '') ?></small></label>
            </div>
            <label><span>Titulo *</span><textarea name="hero_titulo" required maxlength="180" rows="2"><?= h($config['hero_titulo'] ?? '') ?></textarea></label>
            <label><span>Subtitulo *</span><textarea name="hero_subtitulo" required maxlength="1200" rows="3"><?= h($config['hero_subtitulo'] ?? '') ?></textarea></label>
            <div class="form-grid two">
                <label><span>Boton principal *</span><input name="boton_principal_texto" required maxlength="80" value="<?= h($config['boton_principal_texto'] ?? '') ?>"></label>
                <label><span>URL principal *</span><input name="boton_principal_url" required maxlength="255" value="<?= h($config['boton_principal_url'] ?? '') ?>"></label>
                <label><span>Boton secundario *</span><input name="boton_secundario_texto" required maxlength="80" value="<?= h($config['boton_secundario_texto'] ?? '') ?>"></label>
                <label><span>URL secundaria *</span><input name="boton_secundario_url" required maxlength="255" value="<?= h($config['boton_secundario_url'] ?? '') ?>"></label>
            </div>

            <div class="admin-section-line">Historia</div>
            <div class="form-grid two">
                <label><span>Imagen historia</span><input type="file" name="historia_imagen" accept="image/jpeg,image/png,image/webp"><small>Actual: <?= h($config['historia_imagen'] ?? '') ?></small></label>
                <label><span>Etiqueta historia *</span><input name="historia_kicker" required maxlength="80" value="<?= h($config['historia_kicker'] ?? '') ?>"></label>
                <label><span>Badge titulo *</span><input name="historia_badge_titulo" required maxlength="80" value="<?= h($config['historia_badge_titulo'] ?? '') ?>"></label>
                <label><span>Badge texto *</span><input name="historia_badge_texto" required maxlength="120" value="<?= h($config['historia_badge_texto'] ?? '') ?>"></label>
            </div>
            <label><span>Titulo historia *</span><textarea name="historia_titulo" required maxlength="180" rows="2"><?= h($config['historia_titulo'] ?? '') ?></textarea></label>
            <label><span>Texto 1 *</span><textarea name="historia_texto_1" required maxlength="1600" rows="4"><?= h($config['historia_texto_1'] ?? '') ?></textarea></label>
            <label><span>Texto 2 *</span><textarea name="historia_texto_2" required maxlength="1600" rows="4"><?= h($config['historia_texto_2'] ?? '') ?></textarea></label>

            <div class="admin-section-line">Secciones</div>
            <div class="form-grid two">
                <label><span>Valores etiqueta *</span><input name="valores_kicker" required maxlength="80" value="<?= h($config['valores_kicker'] ?? '') ?>"></label>
                <label><span>Valores titulo *</span><input name="valores_titulo" required maxlength="180" value="<?= h($config['valores_titulo'] ?? '') ?>"></label>
                <label class="span-2"><span>Valores texto *</span><input name="valores_texto" required maxlength="1200" value="<?= h($config['valores_texto'] ?? '') ?>"></label>
                <label><span>Proceso etiqueta *</span><input name="proceso_kicker" required maxlength="80" value="<?= h($config['proceso_kicker'] ?? '') ?>"></label>
                <label><span>Proceso titulo *</span><input name="proceso_titulo" required maxlength="180" value="<?= h($config['proceso_titulo'] ?? '') ?>"></label>
                <label class="span-2"><span>Proceso texto *</span><input name="proceso_texto" required maxlength="1200" value="<?= h($config['proceso_texto'] ?? '') ?>"></label>
                <label><span>Equipo etiqueta *</span><input name="equipo_kicker" required maxlength="80" value="<?= h($config['equipo_kicker'] ?? '') ?>"></label>
                <label><span>Equipo titulo *</span><input name="equipo_titulo" required maxlength="180" value="<?= h($config['equipo_titulo'] ?? '') ?>"></label>
                <label class="span-2"><span>Equipo texto *</span><input name="equipo_texto" required maxlength="1200" value="<?= h($config['equipo_texto'] ?? '') ?>"></label>
            </div>

            <div class="admin-section-line">Llamado final</div>
            <div class="form-grid two">
                <label><span>CTA etiqueta *</span><input name="cta_kicker" required maxlength="80" value="<?= h($config['cta_kicker'] ?? '') ?>"></label>
                <label><span>CTA titulo *</span><input name="cta_titulo" required maxlength="180" value="<?= h($config['cta_titulo'] ?? '') ?>"></label>
                <label class="span-2"><span>CTA texto *</span><input name="cta_texto" required maxlength="1200" value="<?= h($config['cta_texto'] ?? '') ?>"></label>
                <label><span>CTA boton principal *</span><input name="cta_boton_principal_texto" required maxlength="80" value="<?= h($config['cta_boton_principal_texto'] ?? '') ?>"></label>
                <label><span>CTA URL principal *</span><input name="cta_boton_principal_url" required maxlength="255" value="<?= h($config['cta_boton_principal_url'] ?? '') ?>"></label>
                <label><span>CTA boton secundario *</span><input name="cta_boton_secundario_texto" required maxlength="80" value="<?= h($config['cta_boton_secundario_texto'] ?? '') ?>"></label>
                <label><span>CTA URL secundaria *</span><input name="cta_boton_secundario_url" required maxlength="255" value="<?= h($config['cta_boton_secundario_url'] ?? '') ?>"></label>
            </div>

            <button type="submit" class="nosotros-submit"><i data-feather="save"></i> Guardar contenido</button>
        </form>

        <section class="nosotros-admin-grid">
            <?php
            $bloques = [
                ['tipo' => 'indicador', 'titulo' => 'Indicadores', 'items' => $indicadores, 'campos' => ['valor', 'etiqueta']],
                ['tipo' => 'valor', 'titulo' => 'Valores', 'items' => $valores, 'campos' => ['icono', 'titulo', 'texto']],
                ['tipo' => 'paso', 'titulo' => 'Pasos', 'items' => $pasos, 'campos' => ['numero', 'titulo', 'texto']],
                ['tipo' => 'equipo', 'titulo' => 'Equipo', 'items' => $equipo, 'campos' => ['nombre', 'rol']],
            ];
            foreach ($bloques as $bloque):
                $editItem = null;
                if ($editTipo === $bloque['tipo'] && $editId > 0) {
                    foreach ($bloque['items'] as $item) {
                        if ((int) $item['id'] === $editId) {
                            $editItem = $item;
                            break;
                        }
                    }
                }
            ?>
                <details class="nosotros-card nosotros-collapse" <?= $editItem ? 'open' : '' ?>>
                    <summary class="nosotros-card-head">
                        <div>
                            <h2><?= h($bloque['titulo']) ?></h2>
                            <p><?= $editItem ? 'Editando registro seleccionado.' : 'Agregar, editar, ordenar, activar o inactivar registros.' ?></p>
                        </div>
                        <span><?= count($bloque['items']) ?> registros</span>
                        <i data-feather="chevron-down"></i>
                    </summary>

                    <form class="nosotros-mini-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_nosotros.php" enctype="multipart/form-data">
                        <input type="hidden" name="accion" value="guardar_item">
                        <input type="hidden" name="tipo" value="<?= h($bloque['tipo']) ?>">
                        <input type="hidden" name="item_id" value="<?= (int) ($editItem['id'] ?? 0) ?>">
                        <div class="mini-grid">
                            <?php foreach ($bloque['campos'] as $campo): ?>
                                <label>
                                    <span><?= h(ucfirst($campo)) ?> *</span>
                                    <?php if ($campo === 'texto'): ?>
                                        <textarea name="texto" required maxlength="1600" rows="3"><?= h($editItem['texto'] ?? '') ?></textarea>
                                    <?php else: ?>
                                        <input name="<?= h($campo === 'etiqueta' ? 'etiqueta' : $campo) ?>" required maxlength="160" value="<?= h($editItem[$campo] ?? '') ?>">
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                            <?php if ($bloque['tipo'] === 'equipo'): ?>
                                <label>
                                    <span>Imagen</span>
                                    <input type="file" name="imagen_equipo" accept="image/jpeg,image/png,image/webp">
                                    <?php if (!empty($editItem['imagen'])): ?>
                                        <small>Actual: <?= h($editItem['imagen']) ?></small>
                                    <?php endif; ?>
                                </label>
                            <?php endif; ?>
                            <label><span>Orden</span><input type="number" name="orden" value="<?= (int) ($editItem['orden'] ?? 0) ?>"></label>
                        </div>
                        <label class="check-line"><input type="checkbox" name="activo" <?= (int) ($editItem['activo'] ?? 1) === 1 ? 'checked' : '' ?>> Activo</label>
                        <div class="nosotros-form-actions">
                            <button class="nosotros-submit" type="submit"><?= $editItem ? 'Actualizar' : 'Agregar' ?></button>
                            <?php if ($editItem): ?>
                                <a class="nosotros-admin-link soft" href="<?= BASE_URL ?>mantenimientos/mantenimiento_nosotros.php">Nuevo</a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <div class="nosotros-item-list">
                        <?php foreach ($bloque['items'] as $item): ?>
                            <div class="nosotros-item <?= (int) $item['activo'] === 1 ? '' : 'inactive' ?>">
                                <div>
                                    <strong><?= h($item['titulo'] ?? $item['nombre'] ?? $item['valor'] ?? '') ?></strong>
                                    <small><?= h($item['etiqueta'] ?? $item['rol'] ?? $item['texto'] ?? '') ?></small>
                                </div>
                                <div class="nosotros-item-actions">
                                    <a class="small-action" href="<?= BASE_URL ?>mantenimientos/mantenimiento_nosotros.php?tipo=<?= h($bloque['tipo']) ?>&item_id=<?= (int) $item['id'] ?>">Editar</a>
                                    <form method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_nosotros.php">
                                        <input type="hidden" name="accion" value="toggle_item">
                                        <input type="hidden" name="tipo" value="<?= h($bloque['tipo']) ?>">
                                        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                        <?php if ((int) $item['activo'] === 1): ?>
                                            <button class="small-action warn" type="submit">Inactivar</button>
                                        <?php else: ?>
                                            <input type="hidden" name="activo" value="1">
                                            <button class="small-action ok" type="submit">Activar</button>
                                        <?php endif; ?>
                                    </form>
                                    <?php if ((int) $item['activo'] === 0): ?>
                                        <form method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_nosotros.php" onsubmit="return confirm('Deseas eliminar este registro?');">
                                            <input type="hidden" name="accion" value="eliminar_item">
                                            <input type="hidden" name="tipo" value="<?= h($bloque['tipo']) ?>">
                                            <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                            <button class="small-action danger" type="submit">Eliminar</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </section>
    </div>
</main>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
