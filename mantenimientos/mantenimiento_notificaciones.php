<?php
require_once __DIR__ . '/../configuracion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../componentes/recordar_sesion.php';
sg_restaurar_sesion_recordada();
$PERMISO_REQUERIDO = 'panel.notificaciones';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../componentes/notificaciones.php';
require_once __DIR__ . '/../componentes/permisos.php';

$pageTitle = 'Configuracion de avisos';
$cssFiles = ['css/global.css', 'css/barra_navegacion.css', 'css/notificaciones_admin.css'];
$jsFiles = ['js/barra_navegacion.js'];
$tipos = sg_notificaciones_tipos();
$config = sg_notificaciones_configuracion($conn);
$ready = sg_notificaciones_tabla($conn, 'notificacion_tipos');
$editable = sg_has_permission_action($conn, 'panel.notificaciones', 'editar');
$mensaje = $_SESSION['notificaciones_mensaje'] ?? null;
$error = $_SESSION['notificaciones_error'] ?? null;
unset($_SESSION['notificaciones_mensaje'], $_SESSION['notificaciones_error']);
$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>
<main class="notice-settings">
  <header class="notice-settings-head">
    <div><span class="notice-settings-kicker">CONFIGURACION</span><h1>Avisos y notificaciones</h1><p>Decide que avisos aparecen y cuales se destacan en la campana.</p></div>
    <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php">Volver al panel</a>
  </header>
  <?php if ($mensaje): ?><p class="notice-settings-message success" role="status"><?= $h($mensaje) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="notice-settings-message error" role="alert"><?= $h($error) ?></p><?php endif; ?>
  <?php if (!$ready): ?><p class="notice-settings-message error" role="alert">Falta instalar la actualizacion de base de datos para editar los avisos.</p><?php endif; ?>
  <form method="post" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_notificaciones.php">
    <?= csrf_field() ?>
    <?php foreach (['usuario' => 'Para usuarios', 'administracion' => 'Para administradores'] as $audiencia => $titulo): ?>
    <section class="notice-settings-group" aria-label="<?= $h($titulo) ?>">
      <h2><?= $h($titulo) ?></h2>
      <div class="notice-settings-table">
        <div class="notice-settings-labels" aria-hidden="true"><span>Aviso</span><span>Activo</span><span>Importante</span></div>
        <?php foreach ($tipos as $codigo => $tipo): if ($tipo[2] !== $audiencia) continue; $ajustes = $config[$codigo]; ?>
        <div class="notice-settings-row">
          <div class="notice-settings-description"><strong><?= $h($tipo[0]) ?></strong><small><?= $h($tipo[1]) ?></small></div>
          <label class="notice-settings-check"><input type="checkbox" name="activo[<?= $h($codigo) ?>]" value="1" <?= $ajustes['activo'] ? 'checked' : '' ?> <?= !$editable || !$ready ? 'disabled' : '' ?>><span>Activo</span></label>
          <label class="notice-settings-check"><input type="checkbox" name="importante[<?= $h($codigo) ?>]" value="1" <?= $ajustes['importante'] ? 'checked' : '' ?> <?= !$editable || !$ready ? 'disabled' : '' ?>><span>Importante</span></label>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endforeach; ?>
    <?php if ($editable && $ready): ?><div class="notice-settings-actions"><button type="submit"><i data-feather="save" aria-hidden="true"></i> Guardar cambios</button></div><?php endif; ?>
  </form>
</main>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
