<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../componentes/recordar_sesion.php';
sg_restaurar_sesion_recordada();

$PERMISO_REQUERIDO = 'usuarios.pasaporte';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';

$pageTitle = "Mantenimiento Pasaporte | Senderismo Go!";

$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/pasaporte_admin.css"
];

$jsFiles = [
    "js/barra_navegacion.js",
    "js/pasaporte_admin.js"
];

require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/helpers.php';
require_once __DIR__ . '/../componentes/pasaporte_bootstrap.php';

pasaporte_bootstrap($conn);

$niveles = [];
$res = mysqli_query($conn, "SELECT * FROM pasaporte_niveles ORDER BY activo DESC, min_senderos ASC, min_km ASC, orden ASC, id ASC");
while ($res && $row = mysqli_fetch_assoc($res)) {
    $niveles[] = $row;
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<div class="passport-admin-page">
    <div class="passport-admin-container">
        <header class="passport-admin-header">
            <div>
                <span class="passport-kicker">Motivacion y fidelizacion</span>
                <h1>Pasaporte senderista</h1>
                <p>Define las clasificaciones e insignias que tendra cada usuario segun sus rutas asistidas y kilometros acumulados.</p>
            </div>
            <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="passport-back">Volver al panel</a>
        </header>

        <?php if (!empty($_SESSION['pasaporte_success'])): ?>
            <div class="passport-alert success"><?= sg_h($_SESSION['pasaporte_success']) ?></div>
            <?php unset($_SESSION['pasaporte_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['pasaporte_error'])): ?>
            <div class="passport-alert error"><?= sg_h($_SESSION['pasaporte_error']) ?></div>
            <?php unset($_SESSION['pasaporte_error']); ?>
        <?php endif; ?>

        <section class="passport-logic-card">
            <div>
                <span>Como sube de nivel</span>
                <h2>El sistema toma el nivel activo mas alto que el usuario cumpla.</h2>
                <p>Ejemplo: si una insignia requiere 6 senderos y 35 km, el usuario debe cumplir ambos valores. Si solo cumple uno, conserva el nivel anterior.</p>
            </div>
            <div class="passport-logic-grid">
                <article>
                    <strong>1</strong>
                    <small>Asistencia confirmada</small>
                </article>
                <article>
                    <strong>2</strong>
                    <small>Kilometros acumulados</small>
                </article>
                <article>
                    <strong>3</strong>
                    <small>Insignia asignada</small>
                </article>
            </div>
        </section>

        <div class="passport-layout">
            <section class="passport-form-card">
                <div class="passport-card-head">
                    <span>Nivel</span>
                    <h2 id="passportFormTitle">Crear clasificacion</h2>
                </div>

                <form method="POST" action="<?= BASE_URL ?>procesos/proceso_pasaporte.php" id="passportForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="0" data-passport-id>

                    <div class="passport-field">
                        <label>Nombre *</label>
                        <input type="text" name="nombre" required maxlength="120" placeholder="Ej: Senderista constante" data-passport-nombre>
                    </div>

                    <div class="passport-field">
                        <label>Descripcion</label>
                        <textarea name="descripcion" maxlength="255" rows="4" placeholder="Mensaje motivador que vera el usuario." data-passport-descripcion></textarea>
                    </div>

                    <div class="passport-grid">
                        <div class="passport-field">
                            <label>Senderos minimos *</label>
                            <input type="number" name="min_senderos" min="0" required value="0" data-passport-senderos>
                        </div>
                        <div class="passport-field">
                            <label>Kilometros minimos *</label>
                            <input type="number" name="min_km" min="0" step="0.01" required value="0" data-passport-km>
                        </div>
                    </div>

                    <div class="passport-grid">
                        <div class="passport-field">
                            <label>Icono</label>
                            <select name="icono" data-passport-icono>
                                <option value="compass">Compas</option>
                                <option value="map">Mapa</option>
                                <option value="trending-up">Progreso</option>
                                <option value="activity">Actividad</option>
                                <option value="award">Insignia</option>
                                <option value="star">Estrella</option>
                                <option value="flag">Meta</option>
                                <option value="navigation">Ruta</option>
                            </select>
                        </div>
                        <div class="passport-field">
                            <label>Color</label>
                            <input type="color" name="color" value="#0f7a3f" data-passport-color>
                        </div>
                    </div>

                    <div class="passport-grid">
                        <div class="passport-field">
                            <label>Orden</label>
                            <input type="number" name="orden" value="0" data-passport-orden>
                        </div>
                        <label class="passport-active">
                            <input type="checkbox" name="activo" value="1" checked data-passport-activo>
                            <span>Activo</span>
                        </label>
                    </div>

                    <div class="passport-actions">
                        <button type="submit" class="passport-btn primary" data-passport-submit>Guardar nivel</button>
                        <button type="button" class="passport-btn secondary" data-passport-reset>Limpiar</button>
                    </div>
                </form>
            </section>

            <section class="passport-list-card">
                <div class="passport-card-head horizontal">
                    <div>
                        <span>Catalogo</span>
                        <h2>Clasificaciones registradas</h2>
                    </div>
                    <strong><?= count($niveles) ?></strong>
                </div>

                <div class="passport-table-wrap">
                    <table class="passport-table">
                        <thead>
                            <tr>
                                <th>Nivel</th>
                                <th>Condicion</th>
                                <th>Vista</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($niveles)): ?>
                                <tr><td colspan="5">No hay niveles registrados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($niveles as $nivel): ?>
                                    <tr>
                                        <td>
                                            <strong><?= sg_h($nivel['nombre']) ?></strong>
                                            <?php if (!empty($nivel['descripcion'])): ?>
                                                <small><?= sg_h($nivel['descripcion']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span><?= (int) $nivel['min_senderos'] ?> senderos</span>
                                            <small><?= number_format((float) $nivel['min_km'], 1) ?> km</small>
                                        </td>
                                        <td>
                                            <span class="passport-preview" style="--badge-color: <?= sg_h($nivel['color']) ?>;">
                                                <i data-feather="<?= sg_h($nivel['icono']) ?>"></i>
                                                <?= sg_h($nivel['nombre']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="<?= (int) $nivel['activo'] === 1 ? 'passport-pill active' : 'passport-pill inactive' ?>">
                                                <?= (int) $nivel['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="passport-row-actions">
                                                <button type="button"
                                                    class="passport-mini edit"
                                                    data-passport-edit
                                                    data-id="<?= (int) $nivel['id'] ?>"
                                                    data-nombre="<?= sg_h($nivel['nombre']) ?>"
                                                    data-descripcion="<?= sg_h($nivel['descripcion']) ?>"
                                                    data-icono="<?= sg_h($nivel['icono']) ?>"
                                                    data-color="<?= sg_h($nivel['color']) ?>"
                                                    data-min-senderos="<?= (int) $nivel['min_senderos'] ?>"
                                                    data-min-km="<?= sg_h($nivel['min_km']) ?>"
                                                    data-orden="<?= (int) $nivel['orden'] ?>"
                                                    data-activo="<?= (int) $nivel['activo'] ?>">
                                                    Editar
                                                </button>
                                                <form method="POST" action="<?= BASE_URL ?>procesos/proceso_pasaporte.php">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="id" value="<?= (int) $nivel['id'] ?>">
                                                    <input type="hidden" name="activo" value="<?= (int) $nivel['activo'] === 1 ? 0 : 1 ?>">
                                                    <button class="passport-mini <?= (int) $nivel['activo'] === 1 ? 'warn' : 'ok' ?>" type="submit">
                                                        <?= (int) $nivel['activo'] === 1 ? 'Inactivar' : 'Activar' ?>
                                                    </button>
                                                </form>
                                            </div>
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
</div>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
