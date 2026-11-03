<?php
require_once __DIR__ . '/configuracion.php';
require_once __DIR__ . '/componentes/csrf.php';
require_once __DIR__ . '/bd/conexion.php';
require_once __DIR__ . '/componentes/permisos.php';
require_once __DIR__ . '/componentes/instalacion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function instalacion_h($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$valores = [
    'organizacion' => '', 'nombre_corto' => '', 'eslogan' => '', 'admin_nombre' => '',
    'admin_apellido' => '', 'admin_usuario' => '', 'admin_email' => '',
];
$errores = [];
$instaladaAhora = isset($_GET['completa']);

try {
    $estado = sg_instalacion_estado($conn);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_validate_post(BASE_URL . 'instalar.php');
        [$datos, $errores] = sg_instalacion_validar($_POST);
        foreach ($valores as $campo => $_valor) {
            $valores[$campo] = $datos[$campo] ?? '';
        }
        if (!$errores) {
            sg_instalacion_ejecutar($conn, $datos);
            header('Location: ' . BASE_URL . 'instalar.php?completa=1');
            exit;
        }
    }
} catch (Throwable $error) {
    $errores[] = APP_DEBUG ? $error->getMessage() : 'No se pudo completar la instalacion. Verifica la estructura y vuelve a intentarlo.';
}

$bloqueada = !empty($estado['completa']) || (int) ($estado['usuarios'] ?? 0) > 0;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Instalar plataforma</title>
    <link rel="stylesheet" href="<?= instalacion_h(BASE_URL) ?>css/instalacion.css">
</head>
<body>
<main class="install-shell">
    <section class="install-intro">
        <span class="install-kicker">CONFIGURACION INICIAL</span>
        <h1>Prepara la plataforma para tu organizacion.</h1>
        <p>Esta pantalla solo funciona una vez, antes de crear usuarios. Define la identidad inicial y el primer administrador.</p>
        <ol>
            <li>La base de datos se conecta con los datos privados del hosting.</li>
            <li>La estructura y catalogos base se preparan automaticamente.</li>
            <li>Al finalizar, el acceso de instalacion queda bloqueado.</li>
        </ol>
    </section>

    <section class="install-card">
        <?php if ($instaladaAhora && $bloqueada): ?>
            <span class="install-kicker success">INSTALACION COMPLETA</span>
            <h2>La plataforma esta lista.</h2>
            <p>Ya puedes iniciar sesion con el administrador que acabas de crear. Desde el panel podras completar logos, contacto, contenido y configuracion visual.</p>
            <a class="install-submit" href="<?= instalacion_h(BASE_URL) ?>pantallas/inicio_sesion.php">Ir a iniciar sesion</a>
        <?php elseif ($bloqueada): ?>
            <span class="install-kicker locked">INSTALADOR BLOQUEADO</span>
            <h2>Esta instalacion ya esta protegida.</h2>
            <p>La plataforma ya tiene usuarios o fue configurada anteriormente. Esta pantalla no puede modificarla.</p>
            <a class="install-secondary" href="<?= instalacion_h(BASE_URL) ?>pantallas/inicio_sesion.php">Ir a iniciar sesion</a>
        <?php else: ?>
            <span class="install-kicker">DATOS DEL CLIENTE</span>
            <h2>Identidad y primer administrador</h2>
            <p class="install-note">Usa un correo real. Podra utilizarse para el contacto publico inicial y para recuperar el acceso del administrador.</p>

            <?php if ($errores): ?>
                <div class="install-alert" role="alert"><strong>Revisa lo siguiente:</strong><ul><?php foreach ($errores as $error): ?><li><?= instalacion_h($error) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>

            <form method="post" class="install-form" novalidate>
                <?= csrf_field() ?>
                <fieldset>
                    <legend>Organizacion</legend>
                    <label>Nombre de la organizacion<input name="organizacion" maxlength="120" required value="<?= instalacion_h($valores['organizacion']) ?>" placeholder="Ej.: Aventuras del Norte"></label>
                    <label>Nombre corto<input name="nombre_corto" maxlength="80" required value="<?= instalacion_h($valores['nombre_corto']) ?>" placeholder="Ej.: Aventuras Norte"></label>
                    <label>Eslogan <span>Opcional</span><input name="eslogan" maxlength="180" value="<?= instalacion_h($valores['eslogan']) ?>" placeholder="Ej.: Naturaleza, comunidad y aventura"></label>
                </fieldset>
                <fieldset>
                    <legend>Administrador inicial</legend>
                    <div class="install-grid">
                        <label>Nombre<input name="admin_nombre" maxlength="100" required value="<?= instalacion_h($valores['admin_nombre']) ?>" autocomplete="given-name"></label>
                        <label>Apellido<input name="admin_apellido" maxlength="100" required value="<?= instalacion_h($valores['admin_apellido']) ?>" autocomplete="family-name"></label>
                    </div>
                    <div class="install-grid">
                        <label>Usuario<input name="admin_usuario" maxlength="50" required value="<?= instalacion_h($valores['admin_usuario']) ?>" autocomplete="username" placeholder="administrador"></label>
                        <label>Correo<input type="email" name="admin_email" maxlength="100" required value="<?= instalacion_h($valores['admin_email']) ?>" autocomplete="email" placeholder="admin@organizacion.com"></label>
                    </div>
                    <div class="install-grid">
                        <label>Contrasena<input type="password" name="admin_password" minlength="10" required autocomplete="new-password"></label>
                        <label>Confirmar contrasena<input type="password" name="admin_password_confirmacion" minlength="10" required autocomplete="new-password"></label>
                    </div>
                </fieldset>
                <button type="submit" class="install-submit">Completar instalacion</button>
            </form>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
