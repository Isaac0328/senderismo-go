<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/contabilidad_bootstrap.php';

contabilidad_bootstrap($conn);

$pageTitle = "Metodos de Pago | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/contabilidad.css"
];
$jsFiles = [
    "js/barra_navegacion.js"
];

function mp_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM contabilidad_metodo_pago WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $editId);
    mysqli_stmt_execute($stmt);
    $edit = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    mysqli_stmt_close($stmt);
}

$metodos = [];
$res = mysqli_query($conn, "
    SELECT cmp.*, COUNT(crp.id) AS pagos_usando
    FROM contabilidad_metodo_pago cmp
    LEFT JOIN contabilidad_registro_pagos crp ON crp.metodo_pago_id = cmp.id
    GROUP BY cmp.id
    ORDER BY cmp.activo DESC, cmp.nombre ASC
");
while ($res && $row = mysqli_fetch_assoc($res)) {
    $metodos[] = $row;
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="fin-page">
    <section class="fin-container">
        <header class="fin-header">
            <div>
                <span class="fin-kicker">Contabilidad</span>
                <h1>Metodos de pago</h1>
                <p>Administra los metodos usados para registrar ingresos por sendero: transferencia, efectivo, tarjeta u otros medios.</p>
            </div>
            <a class="fin-back" href="<?= BASE_URL ?>pantallas/panel_administrativo.php">
                <i data-feather="arrow-left"></i>
                Volver al panel
            </a>
        </header>

        <?php if (!empty($_SESSION['metodo_pago_success'])): ?>
            <div class="fin-alert success"><?= mp_h($_SESSION['metodo_pago_success']) ?></div>
            <?php unset($_SESSION['metodo_pago_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['metodo_pago_error'])): ?>
            <div class="fin-alert error"><?= mp_h($_SESSION['metodo_pago_error']) ?></div>
            <?php unset($_SESSION['metodo_pago_error']); ?>
        <?php endif; ?>

        <div class="fin-layout">
            <section class="fin-card">
                <div class="fin-card-head">
                    <div>
                        <span><?= $edit ? 'Editar' : 'Nuevo' ?></span>
                        <h2><?= $edit ? 'Actualizar metodo' : 'Crear metodo' ?></h2>
                    </div>
                    <i data-feather="credit-card"></i>
                </div>

                <form class="fin-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_metodo_pago.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

                    <label>
                        <span>Nombre *</span>
                        <input type="text" name="nombre" maxlength="120" required placeholder="Ej: Transferencia, efectivo, tarjeta" value="<?= mp_h($edit['nombre'] ?? '') ?>">
                    </label>

                    <label>
                        <span>Descripcion</span>
                        <textarea name="descripcion" maxlength="255" rows="3" placeholder="Detalle opcional."><?= mp_h($edit['descripcion'] ?? '') ?></textarea>
                    </label>

                    <label class="fin-check-row">
                        <input type="checkbox" name="activo" value="1" <?= (int) ($edit['activo'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <span>Activo</span>
                    </label>

                    <div class="fin-actions">
                        <button type="submit"><?= $edit ? 'Actualizar metodo' : 'Guardar metodo' ?></button>
                        <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_metodo_pago.php">Limpiar</a>
                    </div>
                </form>
            </section>

            <section class="fin-card fin-card-wide">
                <div class="fin-card-head">
                    <div>
                        <span>Catalogo</span>
                        <h2>Metodos registrados</h2>
                    </div>
                    <strong class="fin-count"><?= count($metodos) ?></strong>
                </div>

                <?php if (empty($metodos)): ?>
                    <div class="fin-empty">
                        <i data-feather="credit-card"></i>
                        <h2>Sin metodos</h2>
                        <p>Crea el primer metodo para registrar pagos.</p>
                    </div>
                <?php else: ?>
                    <div class="fin-table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Metodo</th>
                                    <th>Descripcion</th>
                                    <th>Pagos</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($metodos as $metodo): ?>
                                    <tr>
                                        <td><strong><?= mp_h($metodo['nombre']) ?></strong></td>
                                        <td><?= mp_h($metodo['descripcion'] ?: 'Sin descripcion') ?></td>
                                        <td><strong><?= (int) $metodo['pagos_usando'] ?></strong></td>
                                        <td>
                                            <span class="fin-pill <?= (int) $metodo['activo'] === 1 ? 'ok' : 'off' ?>">
                                                <?= (int) $metodo['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fin-row-actions">
                                                <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_metodo_pago.php?edit=<?= (int) $metodo['id'] ?>">Editar</a>
                                                <form method="POST" action="<?= BASE_URL ?>procesos/proceso_metodo_pago.php">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="id" value="<?= (int) $metodo['id'] ?>">
                                                    <input type="hidden" name="activo" value="<?= (int) $metodo['activo'] === 1 ? 0 : 1 ?>">
                                                    <button type="submit"><?= (int) $metodo['activo'] === 1 ? 'Inactivar' : 'Activar' ?></button>
                                                </form>
                                                <?php if ((int) $metodo['activo'] === 0): ?>
                                                    <form method="POST" action="<?= BASE_URL ?>procesos/proceso_metodo_pago.php" onsubmit="return confirm('Eliminar este metodo inactivo? Esta accion no se puede deshacer.');">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= (int) $metodo['id'] ?>">
                                                        <button type="submit" class="danger">Eliminar</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </section>
</main>

<?php
mysqli_close($conn);
include_once __DIR__ . '/../componentes/pie_pagina.php';
?>

