<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['usuario_id']) || empty($_SESSION['logged_in'])) {
    $senderoIdRetorno = isset($_GET['sendero_id']) ? (int) $_GET['sendero_id'] : 0;
    if ($senderoIdRetorno > 0) {
        $_SESSION['redirect_after_login'] = BASE_URL . "pantallas/completar_perfil.php?sendero_id=" . $senderoIdRetorno;
    }
    $_SESSION['error_message'] = "Inicia sesion para completar tus datos.";
    header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
    exit;
}

require_once __DIR__ . '/../bd/conexion.php';

$usuarioId = (int) $_SESSION['usuario_id'];
$senderoId = isset($_GET['sendero_id']) ? (int) $_GET['sendero_id'] : 0;
$esMiPerfil = ($perfilModo ?? '') === 'mi_perfil';

function perfil_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function perfil_selected(array $data, string $key, string $value): string
{
    return (string) ($data[$key] ?? '') === $value ? 'selected' : '';
}

function perfil_checked(array $data, string $key, string $value): string
{
    return (string) ($data[$key] ?? '') === $value ? 'checked' : '';
}

$stmt = mysqli_prepare($conn, "SELECT nombre, apellido, user, email, created_at, last_login FROM usuarios WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $usuarioId);
mysqli_stmt_execute($stmt);
$usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM registros_senderos WHERE usuario_id = ? AND estado = 'registrado'");
mysqli_stmt_bind_param($stmt, "i", $usuarioId);
mysqli_stmt_execute($stmt);
$registroStats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: ['total' => 0];
mysqli_stmt_close($stmt);

$detalle = [];
$stmt = mysqli_prepare($conn, "SELECT * FROM detalles_usuarios WHERE usuario_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $usuarioId);
mysqli_stmt_execute($stmt);
$detalle = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
mysqli_stmt_close($stmt);

$old = $_SESSION['perfil_senderista_old'] ?? [];
if (is_array($old)) {
    $detalle = array_merge($detalle, $old);
}
unset($_SESSION['perfil_senderista_old']);

function perfil_completo_vista(array $detalle): bool
{
    $requeridos = [
        'telefono',
        'rango_edad',
        'identificacion',
        'grupo_sanguineo',
        'enfermedad',
        'seguro_medico',
        'experiencia_senderismo',
        'via_entero',
        'emergencia_nombre',
        'emergencia_parentesco',
        'emergencia_telefono',
    ];

    foreach ($requeridos as $campo) {
        if (trim((string) ($detalle[$campo] ?? '')) === '') {
            return false;
        }
    }

    return (int) ($detalle['es_alergico'] ?? 0) !== 1 || trim((string) ($detalle['alergias_detalle'] ?? '')) !== '';
}

$perfilCompleto = perfil_completo_vista($detalle);

$rangosEdad = ['0-18', '19-30', '31-40', '41-50', '51-60', '61+'];
$gruposSanguineos = ['O+', 'O-', 'A+', 'A-', 'AB+', 'AB-', 'B+', 'B-'];
$experiencias = ['Primera vez', 'Principiante', 'Intermedio', 'Avanzado'];
$vias = ['Instagram', 'Facebook', 'TikTok', 'WhatsApp', 'Google', 'Amigos', 'Otro'];

$pageTitle = ($esMiPerfil ? "Mi perfil" : "Completar perfil") . " | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/perfil_senderista.css"
];
$jsFiles = [
    "js/barra_navegacion.js"
];

include_once __DIR__ . "/../componentes/encabezado.php";
include_once __DIR__ . "/../componentes/barra_navegacion.php";
?>

<main class="perfil-page">
    <section class="perfil-shell">
        <div class="perfil-hero">
            <span class="perfil-kicker"><?= $esMiPerfil ? 'Mi cuenta' : 'Datos del senderista' ?></span>
            <h1><?= $esMiPerfil ? 'Mi perfil' : 'Completa tu perfil' ?></h1>
            <p><?= $esMiPerfil ? 'Consulta y actualiza tus datos personales, de salud y contacto de emergencia.' : 'Estos datos se guardan una sola vez y se usaran para tus proximas reservas.' ?></p>
        </div>

        <?php if (!empty($_SESSION['perfil_senderista_success'])): ?>
            <div class="perfil-alert success"><?= perfil_h($_SESSION['perfil_senderista_success']) ?></div>
            <?php unset($_SESSION['perfil_senderista_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['perfil_senderista_info'])): ?>
            <div class="perfil-alert info"><?= perfil_h($_SESSION['perfil_senderista_info']) ?></div>
            <?php unset($_SESSION['perfil_senderista_info']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['perfil_senderista_error'])): ?>
            <div class="perfil-alert error"><?= perfil_h($_SESSION['perfil_senderista_error']) ?></div>
            <?php unset($_SESSION['perfil_senderista_error']); ?>
        <?php endif; ?>

        <form class="perfil-card" method="POST" action="<?= BASE_URL ?>procesos/proceso_completar_perfil.php" novalidate>
            <input type="hidden" name="sendero_id" value="<?= (int) $senderoId ?>">
            <input type="hidden" name="origen" value="<?= $esMiPerfil ? 'mi_perfil' : 'completar_perfil' ?>">

            <div class="perfil-user-summary">
                <div>
                    <span>Nombre</span>
                    <strong><?= perfil_h(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? '')) ?></strong>
                </div>
                <div>
                    <span>Usuario</span>
                    <strong>@<?= perfil_h($usuario['user'] ?? '') ?></strong>
                </div>
                <div>
                    <span>Correo</span>
                    <strong><?= perfil_h($usuario['email'] ?? '') ?></strong>
                </div>
                <div>
                    <span>Estado del perfil</span>
                    <strong class="<?= $perfilCompleto ? 'perfil-status-ok' : 'perfil-status-pending' ?>"><?= $perfilCompleto ? 'Completo' : 'Pendiente' ?></strong>
                </div>
                <div>
                    <span>Reservas activas</span>
                    <strong><?= (int) ($registroStats['total'] ?? 0) ?></strong>
                </div>
                <div>
                    <span>Miembro desde</span>
                    <strong><?= !empty($usuario['created_at']) ? date('d/m/Y', strtotime($usuario['created_at'])) : 'No disponible' ?></strong>
                </div>
            </div>

            <div class="perfil-section-title">
                <span>Informacion personal y de salud</span>
                <small>Los campos con * son obligatorios.</small>
            </div>

            <div class="perfil-grid">
                <div class="perfil-field">
                    <label for="telefono">Telefono *</label>
                    <input type="tel" name="telefono" id="telefono" required inputmode="numeric" pattern="[0-9]{10,15}" placeholder="8090000000" value="<?= perfil_h($detalle['telefono'] ?? '') ?>">
                </div>
                <div class="perfil-field">
                    <label for="rango_edad">Edad *</label>
                    <select name="rango_edad" id="rango_edad" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($rangosEdad as $rango): ?>
                            <option value="<?= perfil_h($rango) ?>" <?= perfil_selected($detalle, 'rango_edad', $rango) ?>><?= perfil_h($rango) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="perfil-field">
                    <label for="identificacion">Identificacion *</label>
                    <input type="text" name="identificacion" id="identificacion" required maxlength="50" value="<?= perfil_h($detalle['identificacion'] ?? '') ?>">
                </div>
                <div class="perfil-field">
                    <label for="grupo_sanguineo">Grupo sanguineo *</label>
                    <select name="grupo_sanguineo" id="grupo_sanguineo" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($gruposSanguineos as $grupo): ?>
                            <option value="<?= perfil_h($grupo) ?>" <?= perfil_selected($detalle, 'grupo_sanguineo', $grupo) ?>><?= perfil_h($grupo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="perfil-grid">
                <div class="perfil-field">
                    <label>Es alergico? *</label>
                    <div class="perfil-radio-row">
                        <label><input type="radio" name="es_alergico" value="1" <?= perfil_checked($detalle, 'es_alergico', '1') ?> required> Si</label>
                        <label><input type="radio" name="es_alergico" value="0" <?= $detalle ? perfil_checked($detalle, 'es_alergico', '0') : 'checked' ?> required> No</label>
                    </div>
                </div>
                <div class="perfil-field">
                    <label for="alergias_detalle">Detalle de alergia</label>
                    <input type="text" name="alergias_detalle" id="alergias_detalle" maxlength="255" placeholder="Solo si aplica" value="<?= perfil_h($detalle['alergias_detalle'] ?? '') ?>">
                </div>
            </div>

            <div class="perfil-field">
                <label for="enfermedad">Padece alguna enfermedad? *</label>
                <input type="text" name="enfermedad" id="enfermedad" required maxlength="255" placeholder="Si no aplica, escribe No" value="<?= perfil_h($detalle['enfermedad'] ?? '') ?>">
            </div>

            <div class="perfil-field">
                <label for="seguro_medico">Tiene seguro medico? *</label>
                <input type="text" name="seguro_medico" id="seguro_medico" required maxlength="255" placeholder="Si no aplica, escribe No" value="<?= perfil_h($detalle['seguro_medico'] ?? '') ?>">
            </div>

            <div class="perfil-grid">
                <div class="perfil-field">
                    <label for="experiencia_senderismo">Experiencia haciendo senderismo *</label>
                    <select name="experiencia_senderismo" id="experiencia_senderismo" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($experiencias as $experiencia): ?>
                            <option value="<?= perfil_h($experiencia) ?>" <?= perfil_selected($detalle, 'experiencia_senderismo', $experiencia) ?>><?= perfil_h($experiencia) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="perfil-field">
                    <label for="via_entero">Por cual via te enteraste? *</label>
                    <select name="via_entero" id="via_entero" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($vias as $via): ?>
                            <option value="<?= perfil_h($via) ?>" <?= perfil_selected($detalle, 'via_entero', $via) ?>><?= perfil_h($via) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="perfil-field">
                <label for="referido_nombre">Si fue por amigos, escribe su nombre</label>
                <input type="text" name="referido_nombre" id="referido_nombre" maxlength="150" value="<?= perfil_h($detalle['referido_nombre'] ?? '') ?>">
            </div>

            <div class="perfil-section-title">
                <span>Contacto de emergencia</span>
            </div>

            <div class="perfil-grid">
                <div class="perfil-field">
                    <label for="emergencia_nombre">Nombre *</label>
                    <input type="text" name="emergencia_nombre" id="emergencia_nombre" required maxlength="150" value="<?= perfil_h($detalle['emergencia_nombre'] ?? '') ?>">
                </div>
                <div class="perfil-field">
                    <label for="emergencia_parentesco">Parentesco *</label>
                    <input type="text" name="emergencia_parentesco" id="emergencia_parentesco" required maxlength="80" value="<?= perfil_h($detalle['emergencia_parentesco'] ?? '') ?>">
                </div>
            </div>

            <div class="perfil-field">
                <label for="emergencia_telefono">Telefono de emergencia *</label>
                <input type="tel" name="emergencia_telefono" id="emergencia_telefono" required inputmode="numeric" pattern="[0-9]{10,15}" placeholder="8090000000" value="<?= perfil_h($detalle['emergencia_telefono'] ?? '') ?>">
            </div>

            <div class="perfil-actions">
                <a class="perfil-btn secondary" href="<?= $senderoId > 0 ? BASE_URL . 'pantallas/senderos_detalle.php?id=' . (int) $senderoId : BASE_URL . 'pantallas/inicio.php' ?>">Cancelar</a>
                <button class="perfil-btn primary" type="submit"><?= $esMiPerfil ? 'Guardar cambios' : 'Guardar y continuar' ?></button>
            </div>
        </form>
    </section>
</main>

<?php
mysqli_close($conn);
include_once __DIR__ . "/../componentes/pie_pagina.php";
?>
