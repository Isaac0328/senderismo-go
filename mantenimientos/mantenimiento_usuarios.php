<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../componentes/recordar_sesion.php';
sg_restaurar_sesion_recordada();

$PERMISO_REQUERIDO = 'usuarios.usuarios';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';

$pageTitle = "Usuarios";

$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/usuarios.css"
];

$jsFiles = [
    "js/barra_navegacion.js",
    "js/usuarios.js"
];

require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/usuarios_vinculos.php';

require_once __DIR__ . '/../componentes/cargos.php';

// Roles de acceso para el select y cargos (Contable, Directiva...) aparte. Sin la actualizacion de cargos, todo es rol.
$cargosListo = sg_cargos_listo($conn);
$roles = [];
$cargos = [];
$rRoles = mysqli_query($conn, $cargosListo ? "SELECT id, nombre, tipo FROM roles ORDER BY id ASC" : "SELECT id, nombre, 'acceso' AS tipo FROM roles ORDER BY id ASC");
if ($rRoles) {
    while ($row = mysqli_fetch_assoc($rRoles)) {
        if ($row['tipo'] === 'cargo') {
            $cargos[] = $row;
        } else {
            $roles[] = $row;
        }
    }
}
$cargosPorUsuario = [];
if ($cargosListo) {
    $rCargos = mysqli_query($conn, 'SELECT uc.usuario_id, uc.cargo_id, r.nombre FROM usuario_cargos uc JOIN roles r ON r.id = uc.cargo_id ORDER BY r.nombre');
    while ($rCargos && $row = mysqli_fetch_assoc($rCargos)) {
        $cargosPorUsuario[(int) $row['usuario_id']][(int) $row['cargo_id']] = $row['nombre'];
    }
}

// Usuarios con detalles de participante cuando existan.
$usuarios = [];
$res = mysqli_query($conn, "
    SELECT
        u.id,
        u.nombre,
        u.apellido,
        u.user,
        u.email,
        u.rol_id,
        r.nombre AS rol_nombre,
        " . (sg_directiva_columna_lista($conn) ? 'u.es_directiva' : '0 AS es_directiva_placeholder') . ",
        u.estado,
        (SELECT COUNT(*) FROM registros_senderos rs WHERE rs.usuario_id = u.id) AS registros_senderos_total,
        u.created_at,
        u.last_login,
        du.telefono,
        du.rango_edad,
        du.identificacion,
        du.es_alergico,
        du.alergias_detalle,
        du.grupo_sanguineo,
        du.enfermedad,
        du.seguro_medico,
        du.experiencia_senderismo,
        du.via_entero,
        du.referido_nombre,
        du.emergencia_nombre,
        du.emergencia_parentesco,
        du.emergencia_telefono
    FROM usuarios u
    INNER JOIN roles r ON r.id = u.rol_id
    LEFT JOIN detalles_usuarios du ON du.usuario_id = u.id
    ORDER BY u.id DESC
");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $usuarios[] = $row;
    }
    mysqli_free_result($res);
}

$menoresPorUsuario = [];
$resMenores = mysqli_query($conn, "SELECT * FROM menores_usuarios ORDER BY usuario_id ASC, activo DESC, nombre ASC, apellido ASC, id ASC");
if ($resMenores) {
    while ($row = mysqli_fetch_assoc($resMenores)) {
        $uid = (int) $row['usuario_id'];
        $row['menor_usuario_id'] = (int) $row['id'];
        $menoresPorUsuario[$uid][] = $row;
    }
    mysqli_free_result($resMenores);
}

// Registros que amarran a cada usuario: mientras existan, solo se puede inactivar.
$vinculos = usuarios_vinculos($conn);
// Crear y Consultar son dos vistas de la misma pagina; editar abre Crear con los datos cargados.
$vistaInicial = ($_GET['vista'] ?? '') === 'consultar' && !isset($_GET['edit']) ? 'consultar' : 'crear';

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<div class="usuarios-page">
    <div class="usuarios-container">

        <div class="usuarios-header">
            <div>
                <span class="usuarios-kicker">Seguridad</span>
                <h1 class="usuarios-title">Usuarios</h1>
                <p class="usuarios-subtitle">Crea, edita, asigna roles y activa/inactiva usuarios.</p>
            </div>
            <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="usuarios-panel-link">
                <i data-feather="arrow-left"></i>
                Volver al panel
            </a>
        </div>

        <?php if (!empty($_SESSION['usuarios_success'])): ?>
            <div class="alert success">
                <?= htmlspecialchars($_SESSION['usuarios_success']) ?>
            </div>
            <?php unset($_SESSION['usuarios_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['usuarios_error'])): ?>
            <div class="alert error">
                <?= htmlspecialchars($_SESSION['usuarios_error']) ?>
            </div>
            <?php unset($_SESSION['usuarios_error']); ?>
        <?php endif; ?>

        <div class="usuarios-tabs" role="tablist" aria-label="Crear o consultar usuarios" data-vista-inicial="<?= $vistaInicial ?>">
            <button type="button" role="tab" class="usuarios-tab" data-vista-tab="crear" aria-selected="<?= $vistaInicial === 'crear' ? 'true' : 'false' ?>"><i data-feather="user-plus"></i>Crear</button>
            <button type="button" role="tab" class="usuarios-tab" data-vista-tab="consultar" aria-selected="<?= $vistaInicial === 'consultar' ? 'true' : 'false' ?>"><i data-feather="search"></i>Consultar <span class="usuarios-tab-count"><?= count($usuarios) ?></span></button>
        </div>

        <div class="usuarios-grid">

            <!-- FORM -->
            <section class="card user-form-card" data-user-form-card data-vista-panel="crear" <?= $vistaInicial === 'crear' ? '' : 'hidden' ?>>
                <div class="card-head">
                    <div>
                        <h2 id="formTitle">Nuevo Usuario</h2>
                        <p>Los campos con * son obligatorios.</p>
                    </div>
                </div>

                <form id="userForm" class="user-form" method="POST"
                    action="<?= BASE_URL ?>procesos/proceso_usuarios.php">
                    <input type="hidden" name="action" id="action" value="save">
                    <input type="hidden" name="id" id="userId" value="0">
                    <input type="hidden" name="sync_menores" value="1">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="nombre">Nombre *</label>
                            <input type="text" name="nombre" id="nombre" required maxlength="100"
                                placeholder="Ej: Juan" pattern="[^0-9]*" oninput="this.value=this.value.replace(/[0-9]/g,'')">
                        </div>
                        <div class="form-group">
                            <label for="apellido">Apellido *</label>
                            <input type="text" name="apellido" id="apellido" required maxlength="100"
                                placeholder="Ej: Pérez" pattern="[^0-9]*" oninput="this.value=this.value.replace(/[0-9]/g,'')">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="user">Usuario *</label>
                            <input type="text" name="user" id="user" required maxlength="50" placeholder="Ej: jperez">
                        </div>
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" name="email" id="email" required maxlength="100"
                                placeholder="ejemplo@email.com">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="rol_id">Rol (acceso) *</label>
                            <select name="rol_id" id="rol_id" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= (int) $r['id'] ?>">
                                        <?= htmlspecialchars($r['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php if (sg_directiva_columna_lista($conn)): ?>
                        <div class="form-group">
                            <label>Directiva <span class="hint">(aparte de rol y cargos)</span></label>
                            <input type="hidden" name="directiva_enviada" value="1">
                            <div class="cargos-lista">
                                <label class="cargo-opcion"><input type="checkbox" name="es_directiva" id="es_directiva" value="1"><span>Miembro de la directiva</span></label>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($cargos): ?>
                        <div class="form-group">
                            <label>Cargos <span class="hint">(opcional, aparte del rol)</span></label>
                            <input type="hidden" name="cargos_enviados" value="1">
                            <div class="cargos-lista" data-cargos-lista>
                                <?php foreach ($cargos as $c): ?>
                                    <label class="cargo-opcion"><input type="checkbox" name="cargos[]" value="<?= (int) $c['id'] ?>"><span><?= htmlspecialchars($c['nombre']) ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="password">Contrase&ntilde;a <span class="hint">(obligatoria al crear, opcional al
                                    editar)</span></label>
                            <input type="password" name="password" id="password" minlength="6" maxlength="120"
                                placeholder="********">
                        </div>
                    </div>


                    <div class="form-section-title">Detalles del senderista</div>

                    <div class="form-group">
                        <label for="telefono">Telefono</label>
                        <input type="tel" name="telefono" id="telefono" maxlength="15" inputmode="numeric" pattern="[0-9]{10,15}" placeholder="8090000000" oninput="this.value=this.value.replace(/\D/g,'')">
                    </div>
                    <div class="form-group">
                        <label for="rango_edad">Edad</label>
                        <select name="rango_edad" id="rango_edad">
                            <option value="">Seleccione...</option>
                            <option value="0-18">0-18</option>
                            <option value="19-30">19-30</option>
                            <option value="31-40">31-40</option>
                            <option value="41-50">41-50</option>
                            <option value="51-60">51-60</option>
                            <option value="61+">61+</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="identificacion">Identificacion</label>
                        <input type="text" name="identificacion" id="identificacion" maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="grupo_sanguineo">Sangre</label>
                        <select name="grupo_sanguineo" id="grupo_sanguineo">
                            <option value="">Seleccione...</option>
                            <option value="O+">O+</option><option value="O-">O-</option>
                            <option value="A+">A+</option><option value="A-">A-</option>
                            <option value="AB+">AB+</option><option value="AB-">AB-</option>
                            <option value="B+">B+</option><option value="B-">B-</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="es_alergico">Alergico</label>
                        <select name="es_alergico" id="es_alergico">
                            <option value="0">No</option>
                            <option value="1">Si</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="alergias_detalle">Detalle alergia</label>
                        <input type="text" name="alergias_detalle" id="alergias_detalle" maxlength="255">
                    </div>
                    <div class="form-group span-4">
                        <label for="enfermedad">Enfermedad</label>
                        <input type="text" name="enfermedad" id="enfermedad" maxlength="255" placeholder="Si no aplica, No">
                    </div>
                    <div class="form-group span-4">
                        <label for="seguro_medico">Seguro medico</label>
                        <input type="text" name="seguro_medico" id="seguro_medico" maxlength="255" placeholder="Si no aplica, No">
                    </div>
                    <div class="form-group">
                        <label for="experiencia_senderismo">Experiencia</label>
                        <select name="experiencia_senderismo" id="experiencia_senderismo">
                            <option value="">Seleccione...</option>
                            <option value="Primera vez">Primera vez</option>
                            <option value="Principiante">Principiante</option>
                            <option value="Intermedio">Intermedio</option>
                            <option value="Avanzado">Avanzado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="via_entero">Via</label>
                        <select name="via_entero" id="via_entero">
                            <option value="">Seleccione...</option>
                            <option value="Instagram">Instagram</option><option value="Facebook">Facebook</option>
                            <option value="TikTok">TikTok</option><option value="WhatsApp">WhatsApp</option>
                            <option value="Google">Google</option><option value="Amigos">Amigos</option><option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="referido_nombre">Referido</label>
                        <input type="text" name="referido_nombre" id="referido_nombre" maxlength="150" pattern="[^0-9]*" oninput="this.value=this.value.replace(/[0-9]/g,'')">
                    </div>
                    <div class="form-group">
                        <label for="emergencia_nombre">Emergencia</label>
                        <input type="text" name="emergencia_nombre" id="emergencia_nombre" maxlength="150" pattern="[^0-9]*" oninput="this.value=this.value.replace(/[0-9]/g,'')">
                    </div>
                    <div class="form-group">
                        <label for="emergencia_parentesco">Parentesco</label>
                        <input type="text" name="emergencia_parentesco" id="emergencia_parentesco" maxlength="80">
                    </div>
                    <div class="form-group">
                        <label for="emergencia_telefono">Tel. emergencia</label>
                        <input type="tel" name="emergencia_telefono" id="emergencia_telefono" maxlength="15" inputmode="numeric" pattern="[0-9]{10,15}" oninput="this.value=this.value.replace(/\D/g,'')">
                    </div>

                    <div class="form-section-title">Menores asociados al usuario</div>
                    <div class="user-minors-box">
                        <div class="user-minors-head">
                            <div>
                                <strong data-user-minors-count>0 menores asociados</strong>
                                <small>Estos menores apareceran como seleccionables cuando el usuario se registre a un sendero.</small>
                            </div>
                            <button class="btn-secondary" type="button" data-add-user-minor>Agregar menor</button>
                        </div>
                        <div class="user-minors-editor" data-user-minors-editor></div>
                        <div data-user-minors-fields></div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary" id="submitBtn">Guardar</button>
                        <button type="button" class="btn-secondary" id="resetBtn">Limpiar</button>
                        <button type="button" class="btn-secondary" id="cancelEditBtn" data-vista-ir="consultar" hidden>Cancelar edici&oacute;n</button>
                    </div>
                </form>
            </section>

            <!-- TABLA -->
            <section class="card" data-vista-panel="consultar" <?= $vistaInicial === 'consultar' ? '' : 'hidden' ?>>
                <div class="card-head">
                    <h2>Usuarios existentes</h2>
                    <p>Busca, edita, inactiva o elimina. Un usuario solo se elimina si est&aacute; inactivo y no tiene registros asociados.</p>
                </div>

                <div class="table-tools">
                    <input type="search" id="searchInput" placeholder="Buscar usuario o menor por nombre, email, rol o estado...">
                    <select id="minorFilter" aria-label="Filtrar usuarios por menores asociados">
                        <option value="all">Todos los usuarios</option>
                        <option value="with">Con menores</option>
                        <option value="without">Sin menores</option>
                    </select>
                </div>

                <div class="table-wrap">
                    <table class="usuarios-table" id="usuariosTable">
                        <thead>
                            <tr>
                                <th style="width:70px;">ID</th>
                                <th>Nombre</th>
                                <th>User</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Detalles</th>
                                <th style="width:90px;">Estado</th>
                                <th style="width:150px;">Registro</th>
                                <th style="width:240px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($usuarios) === 0): ?>
                                <tr>
                                    <td colspan="9" class="empty">No hay usuarios para mostrar.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($usuarios as $u): ?>
                                    <?php
                                    $estadoTxt = ((int) $u['estado'] === 1) ? 'Activo' : 'Inactivo';
                                    $estadoClass = ((int) $u['estado'] === 1) ? 'pill ok' : 'pill off';
                                    $vinculosUsuario = $vinculos[(int) $u['id']] ?? [];
                                    $nombreCompleto = trim($u['nombre'] . ' ' . $u['apellido']);
                                    $puedeEliminar = (int) $u['estado'] === 0 && !$vinculosUsuario && (int) $u['id'] !== (int) ($_SESSION['usuario_id'] ?? 0);
                                    $tieneDetalles = !empty($u['telefono']) || !empty($u['grupo_sanguineo']) || !empty($u['emergencia_nombre']);
                                    $alergiaTxt = ((int) ($u['es_alergico'] ?? 0) === 1)
                                        ? 'Alergico: ' . ($u['alergias_detalle'] ?: 'Si')
                                        : 'No alergico';
                                    $menoresUsuario = $menoresPorUsuario[(int) $u['id']] ?? [];
                                    $menoresActivos = array_values(array_filter($menoresUsuario, static fn($m) => (int) ($m['activo'] ?? 1) === 1));
                                    $nombresMenores = array_map(static fn($m) => trim(($m['nombre'] ?? '') . ' ' . ($m['apellido'] ?? '')), $menoresActivos);
                                    $menoresJson = htmlspecialchars(json_encode(array_values($menoresUsuario), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                    $fechaRegistro = !empty($u['created_at']) ? date('d/m/Y h:i A', strtotime($u['created_at'])) : 'Sin fecha';
                                    ?>
                                    <tr data-id="<?= (int) $u['id'] ?>" data-nombre="<?= htmlspecialchars($u['nombre']) ?>"
                                        data-apellido="<?= htmlspecialchars($u['apellido']) ?>"
                                        data-user="<?= htmlspecialchars($u['user']) ?>"
                                        data-email="<?= htmlspecialchars($u['email']) ?>" data-rol_id="<?= (int) $u['rol_id'] ?>"
                                        data-estado="<?= (int) $u['estado'] ?>"
                                        data-rol_nombre="<?= htmlspecialchars($u['rol_nombre']) ?>"
                                        data-es_directiva="<?= (int) ($u['es_directiva'] ?? 0) ?>"
                                        data-cargos="<?= htmlspecialchars(implode(',', array_keys($cargosPorUsuario[(int) $u['id']] ?? []))) ?>"
                                        data-telefono="<?= htmlspecialchars($u['telefono'] ?? '') ?>"
                                        data-rango_edad="<?= htmlspecialchars($u['rango_edad'] ?? '') ?>"
                                        data-identificacion="<?= htmlspecialchars($u['identificacion'] ?? '') ?>"
                                        data-es_alergico="<?= (int) ($u['es_alergico'] ?? 0) ?>"
                                        data-alergias_detalle="<?= htmlspecialchars($u['alergias_detalle'] ?? '') ?>"
                                        data-grupo_sanguineo="<?= htmlspecialchars($u['grupo_sanguineo'] ?? '') ?>"
                                        data-enfermedad="<?= htmlspecialchars($u['enfermedad'] ?? '') ?>"
                                        data-seguro_medico="<?= htmlspecialchars($u['seguro_medico'] ?? '') ?>"
                                        data-experiencia_senderismo="<?= htmlspecialchars($u['experiencia_senderismo'] ?? '') ?>"
                                        data-via_entero="<?= htmlspecialchars($u['via_entero'] ?? '') ?>"
                                        data-referido_nombre="<?= htmlspecialchars($u['referido_nombre'] ?? '') ?>"
                                        data-emergencia_nombre="<?= htmlspecialchars($u['emergencia_nombre'] ?? '') ?>"
                                        data-emergencia_parentesco="<?= htmlspecialchars($u['emergencia_parentesco'] ?? '') ?>"
                                        data-emergencia_telefono="<?= htmlspecialchars($u['emergencia_telefono'] ?? '') ?>"
                                        data-menores='<?= $menoresJson ?>'
                                        data-minor-search="<?= htmlspecialchars(implode(' ', $nombresMenores), ENT_QUOTES, 'UTF-8') ?>"
                                        data-minor-count="<?= count($menoresActivos) ?>">
                                        <td>
                                            <?= (int) $u['id'] ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($u['user']) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($u['email']) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($u['rol_nombre']) ?>
                                            <?php if ((int) ($u['es_directiva'] ?? 0) === 1): ?><small class="cargo-chip">Directiva</small><?php endif; ?>
                                            <?php foreach ($cargosPorUsuario[(int) $u['id']] ?? [] as $nombreCargo): ?><small class="cargo-chip"><?= htmlspecialchars($nombreCargo) ?></small><?php endforeach; ?>
                                        </td>
                                        <td class="details-cell">
                                            <?php if ($tieneDetalles): ?>
                                                <div class="user-detail-summary">
                                                    <span><strong>Tel:</strong> <?= htmlspecialchars($u['telefono'] ?: 'N/A') ?></span>
                                                    <span><strong>Edad:</strong> <?= htmlspecialchars($u['rango_edad'] ?: 'N/A') ?></span>
                                                    <span><strong>Sangre:</strong> <?= htmlspecialchars($u['grupo_sanguineo'] ?: 'N/A') ?></span>
                                                    <span><strong><?= htmlspecialchars($alergiaTxt) ?></strong></span>
                                                    <span><strong>Emergencia:</strong> <?= htmlspecialchars($u['emergencia_nombre'] ?: 'N/A') ?><?= !empty($u['emergencia_telefono']) ? ' / ' . htmlspecialchars($u['emergencia_telefono']) : '' ?></span>
                                                </div>
                                            <?php elseif (!$menoresActivos): ?>
                                                <span class="details-empty">Sin detalles registrados</span>
                                            <?php endif; ?>
                                            <?php if ($menoresActivos): ?>
                                                <details class="user-minors-detail">
                                                    <summary><strong>Menores (<?= count($menoresActivos) ?>):</strong> <?= htmlspecialchars(implode(', ', $nombresMenores), ENT_QUOTES, 'UTF-8') ?></summary>
                                                    <div class="user-minors-list">
                                                        <?php foreach ($menoresActivos as $menor): ?>
                                                            <div class="user-minor-item">
                                                                <strong><?= htmlspecialchars(trim(($menor['nombre'] ?? '') . ' ' . ($menor['apellido'] ?? '')), ENT_QUOTES, 'UTF-8') ?></strong>
                                                                <span>Edad: <?= htmlspecialchars($menor['rango_edad'] ?: 'No indicada', ENT_QUOTES, 'UTF-8') ?> · Sangre: <?= htmlspecialchars($menor['grupo_sanguineo'] ?: 'No indicada', ENT_QUOTES, 'UTF-8') ?></span>
                                                                <span>Alergias: <?= (int) ($menor['es_alergico'] ?? 0) === 1 ? htmlspecialchars($menor['alergias_detalle'] ?: 'No especificadas', ENT_QUOTES, 'UTF-8') : 'No registradas' ?></span>
                                                                <?php if (!empty($menor['enfermedad'])): ?><span>Condicion: <?= htmlspecialchars($menor['enfermedad'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                                                <?php if (!empty($menor['emergencia_nombre']) || !empty($menor['emergencia_telefono'])): ?><span>Emergencia: <?= htmlspecialchars(trim(($menor['emergencia_nombre'] ?? '') . ' / ' . ($menor['emergencia_telefono'] ?? ''), ' /'), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </details>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="<?= $estadoClass ?>">
                                                <?= $estadoTxt ?>
                                            </span></td>
                                        <td class="registration-date"><?= htmlspecialchars($fechaRegistro) ?></td>
                                        <td>
                                            <button type="button" class="btn-mini edit-btn">Editar</button>

                                            <form method="POST" action="<?= BASE_URL ?>procesos/proceso_usuarios.php"
                                                class="inline-form"
                                                data-confirmar="<?= htmlspecialchars(((int) $u['estado'] === 1
                                                    ? '¿Inactivar a ' . $nombreCompleto . '? No podrá iniciar sesión. Su historial y sus registros se conservan y podrás activarlo de nuevo.'
                                                    : '¿Activar a ' . $nombreCompleto . '? Podrá volver a iniciar sesión.'), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="action" value="toggle_estado">
                                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                                <input type="hidden" name="estado"
                                                    value="<?= ((int) $u['estado'] === 1) ? 0 : 1 ?>">
                                                <button type="submit"
                                                    class="btn-mini <?= ((int) $u['estado'] === 1) ? 'warn' : 'ok' ?>">
                                                    <?= ((int) $u['estado'] === 1) ? 'Inactivar' : 'Activar' ?>
                                                </button>
                                            </form>

                                            <?php if ($puedeEliminar): ?>
                                                <form method="POST" action="<?= BASE_URL ?>procesos/proceso_usuarios.php"
                                                    class="inline-form"
                                                    data-confirmar="<?= htmlspecialchars('¿Eliminar definitivamente a ' . $nombreCompleto . '? No tiene registros asociados. Esta acción no se puede deshacer.', ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                                    <button type="submit" class="btn-mini danger">Eliminar</button>
                                                </form>
                                            <?php elseif ((int) $u['estado'] === 0 && $vinculosUsuario): ?>
                                                <small class="user-delete-note">No se elimina: tiene <?= htmlspecialchars(usuarios_vinculos_texto($vinculosUsuario)) ?>.</small>
                                            <?php endif; ?>
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

<template id="userMinorTemplate">
    <article class="user-minor-card" data-user-minor-card>
        <input type="hidden" data-minor-field="menor_usuario_id">
        <div class="user-minor-card-head">
            <strong data-user-minor-title>Menor</strong>
            <button type="button" class="btn-mini danger" data-remove-user-minor>Quitar</button>
        </div>
        <div class="user-minor-grid">
            <label>
                <span>Nombre *</span>
                <input type="text" data-minor-field="nombre" maxlength="100" placeholder="Nombre" pattern="[^0-9]*" oninput="this.value=this.value.replace(/[0-9]/g,'')">
            </label>
            <label>
                <span>Apellido *</span>
                <input type="text" data-minor-field="apellido" maxlength="100" placeholder="Apellido" pattern="[^0-9]*" oninput="this.value=this.value.replace(/[0-9]/g,'')">
            </label>
            <label>
                <span>Telefono</span>
                <input type="tel" data-minor-field="telefono" maxlength="15" inputmode="numeric" pattern="[0-9]{10,15}" placeholder="Opcional" oninput="this.value=this.value.replace(/\D/g,'')">
            </label>
            <label>
                <span>Edad *</span>
                <select data-minor-field="rango_edad">
                    <option value="">Seleccione...</option>
                    <option value="8-12">8 - 12</option>
                    <option value="13-17">13 - 17</option>
                </select>
            </label>
            <label>
                <span>Sangre *</span>
                <select data-minor-field="grupo_sanguineo">
                    <option value="">Seleccione...</option>
                    <option value="O+">O+</option><option value="O-">O-</option>
                    <option value="A+">A+</option><option value="A-">A-</option>
                    <option value="AB+">AB+</option><option value="AB-">AB-</option>
                    <option value="B+">B+</option><option value="B-">B-</option>
                </select>
            </label>
            <label>
                <span>Alergico</span>
                <select data-minor-field="es_alergico">
                    <option value="0">No</option>
                    <option value="1">Si</option>
                </select>
            </label>
            <label>
                <span>Detalle alergia</span>
                <input type="text" data-minor-field="alergias_detalle" maxlength="255">
            </label>
            <label>
                <span>Experiencia *</span>
                <select data-minor-field="experiencia_senderismo">
                    <option value="">Seleccione...</option>
                    <option value="Primera vez">Primera vez</option>
                    <option value="Principiante">Principiante</option>
                    <option value="Intermedio">Intermedio</option>
                    <option value="Avanzado">Avanzado</option>
                </select>
            </label>
            <label class="span-2">
                <span>Enfermedad *</span>
                <input type="text" data-minor-field="enfermedad" maxlength="255" placeholder="Si no aplica, No">
            </label>
            <label class="span-2">
                <span>Seguro medico *</span>
                <input type="text" data-minor-field="seguro_medico" maxlength="255" placeholder="Si no aplica, No">
            </label>
            <label>
                <span>Emergencia *</span>
                <input type="text" data-minor-field="emergencia_nombre" maxlength="150" pattern="[^0-9]*" oninput="this.value=this.value.replace(/[0-9]/g,'')">
            </label>
            <label>
                <span>Parentesco *</span>
                <input type="text" data-minor-field="emergencia_parentesco" maxlength="80">
            </label>
            <label>
                <span>Tel. emergencia *</span>
                <input type="tel" data-minor-field="emergencia_telefono" maxlength="15" inputmode="numeric" pattern="[0-9]{10,15}" oninput="this.value=this.value.replace(/\D/g,'')">
            </label>
            <label>
                <span>Estado</span>
                <select data-minor-field="activo">
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                </select>
            </label>
        </div>
    </article>
</template>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
