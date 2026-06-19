<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';

$pageTitle = "Mantenimiento Tarjeta de Pago | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/senderos_admin.css"
];
$jsFiles = [
    "js/barra_navegacion.js"
];

$pago = [
    'banco' => 'Banco Popular',
    'cuenta' => '846542835',
    'tipo_cuenta' => 'Corriente',
    'cedula' => '032-0039961-0',
    'correo' => 'senderismogopro@gmail.com',
    'nombre' => 'Yomary Infante',
    'telefono_comprobante' => '809-323-1888',
    'nota_importante' => 'Al momento de realizar el pago debe enviar el comprobante al numero indicado. El deposito por reservacion no es reembolsable ni transferible. No se realizan reembolsos del pago total, pero puede ceder su lugar a otra persona que cuente con la capacidad fisica necesaria para realizar el sendero.',
    'activo' => 1,
];

$res = mysqli_query($conn, "SELECT * FROM tarjeta_pago WHERE id = 1 LIMIT 1");
if ($res && ($row = mysqli_fetch_assoc($res))) {
    foreach ($pago as $campo => $valor) {
        if (array_key_exists($campo, $row)) {
            $pago[$campo] = $row[$campo];
        }
    }
}

function hp($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<div class="senderos-admin-page">
    <div class="senderos-admin-container">
        <div class="senderos-admin-header">
            <div>
                <span class="admin-kicker">Informacion financiera</span>
                <h1 class="senderos-admin-title">Mantenimiento Tarjeta de Pago</h1>
                <p class="senderos-admin-subtitle">Actualiza los datos bancarios que se muestran en el detalle de los senderos.</p>
            </div>
            <div class="senderos-header-actions">
                <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="view-public-link">Volver al panel</a>
            </div>
        </div>

        <?php if (!empty($_SESSION['pago_success'])): ?>
            <div class="senderos-alert success"><?= hp($_SESSION['pago_success']) ?></div>
            <?php unset($_SESSION['pago_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['pago_error'])): ?>
            <div class="senderos-alert error"><?= hp($_SESSION['pago_error']) ?></div>
            <?php unset($_SESSION['pago_error']); ?>
        <?php endif; ?>

        <section class="senderos-form-card">
            <div class="senderos-card-head">
                <div>
                    <h2>Datos para recibir pagos</h2>
                    <p>Estos datos se mostraran antes del boton de registro en el detalle publico.</p>
                </div>
            </div>

            <form class="senderos-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_tarjeta_pago.php">
                <div class="form-grid">
                    <div class="field span-2">
                        <label for="banco">Banco *</label>
                        <input type="text" id="banco" name="banco" maxlength="120" required value="<?= hp($pago['banco']) ?>">
                    </div>
                    <div class="field span-2">
                        <label for="cuenta">Cuenta No. *</label>
                        <input type="text" id="cuenta" name="cuenta" maxlength="80" required value="<?= hp($pago['cuenta']) ?>">
                    </div>
                    <div class="field span-2">
                        <label for="tipo_cuenta">Tipo de cuenta *</label>
                        <input type="text" id="tipo_cuenta" name="tipo_cuenta" maxlength="80" required value="<?= hp($pago['tipo_cuenta']) ?>">
                    </div>
                    <div class="field span-2">
                        <label for="cedula">Cedula *</label>
                        <input type="text" id="cedula" name="cedula" maxlength="40" required value="<?= hp($pago['cedula']) ?>">
                    </div>
                    <div class="field span-2">
                        <label for="correo">Correo *</label>
                        <input type="email" id="correo" name="correo" maxlength="160" required value="<?= hp($pago['correo']) ?>">
                    </div>
                    <div class="field span-2">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" maxlength="160" required value="<?= hp($pago['nombre']) ?>">
                    </div>
                    <div class="field span-2">
                        <label for="telefono_comprobante">Telefono para comprobante *</label>
                        <input type="text" id="telefono_comprobante" name="telefono_comprobante" maxlength="40" required value="<?= hp($pago['telefono_comprobante']) ?>">
                    </div>
                    <div class="field span-6">
                        <label for="nota_importante">Nota importante *</label>
                        <textarea id="nota_importante" name="nota_importante" rows="6" required><?= hp($pago['nota_importante']) ?></textarea>
                    </div>
                    <label class="active-toggle">
                        <input type="checkbox" name="activo" value="1" <?= (int) $pago['activo'] === 1 ? 'checked' : '' ?>>
                        <span>Mostrar tarjeta de pago en la pagina publica</span>
                    </label>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Guardar datos de pago</button>
                    </div>
                </div>
            </form>
        </section>
    </div>
</div>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
