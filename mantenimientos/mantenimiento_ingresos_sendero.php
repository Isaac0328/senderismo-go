<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../componentes/helpers.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/actualizar_estado_senderos.php';
require_once __DIR__ . '/../componentes/filtro_senderos.php';
require_once __DIR__ . '/../componentes/senderos_admin_data.php';
require_once __DIR__ . '/../componentes/ingresos_cobro.php';
require_once __DIR__ . '/../componentes/cuadre_sendero.php';

sg_actualizar_senderos_vencidos($conn);

$pageTitle = "Ingresos por Sendero";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/contabilidad.css",
    "css/ingresos_sendero.css"
];
$jsFiles = [
    "js/barra_navegacion.js",
    "js/ingresos_sendero.js"
];

$senderoId = (int) ($_GET['sendero_id'] ?? 0);
$senderoFiltros = sgf_params();
$nivelesDificultad = sgf_niveles_dificultad($conn);
[$senderoWhere, $senderoTypes, $senderoValues] = sgf_where($senderoFiltros, 's');

$metodosPago = sg_admin_metodos_pago($conn);
// Usuarios a los que se puede cargar el cobro de una inscripcion (cuenta por cobrar).
$cargoListo = ic_cargado_listo($conn);
$usuariosCargo = [];
if ($cargoListo) {
    $resUsuariosCargo = mysqli_query($conn, "SELECT id, TRIM(CONCAT(nombre, ' ', apellido)) AS nombre FROM usuarios WHERE estado = 1 ORDER BY nombre");
    while ($resUsuariosCargo && $u = mysqli_fetch_assoc($resUsuariosCargo)) {
        $usuariosCargo[] = $u;
    }
}
$senderos = sg_admin_senderos_para_ingresos($conn, $senderoWhere, $senderoTypes, $senderoValues);
$senderoSeleccionado = sg_admin_find_row_by_id($senderos, $senderoId);
$esquemaListo = ic_esquema_listo($conn);
$catalogo = sg_financial_status_catalog($conn);

$filas = [];
$totales = null;
if ($senderoSeleccionado && $esquemaListo) {
    $filas = ic_cargar($conn, $senderoId);
    $totales = ic_totales($filas);
}

$filtros = [
    'todos' => 'Todos',
    'debe' => 'Deben',
    'pendiente' => 'Sin pagar aun',
    'al_dia' => 'Al dia',
    'no_asistio' => 'No asistieron',
];

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="fin-page fin-income-page ing-page" data-ingresos
      data-endpoint="<?= sg_h(BASE_URL . 'procesos/proceso_ingresos_sendero.php') ?>"
      data-sendero="<?= (int) $senderoId ?>"
      data-csrf="<?= sg_h(csrf_token()) ?>"
      data-hoy="<?= date('Y-m-d') ?>">
    <section class="fin-container">
        <header class="fin-header">
            <div>
                <span class="fin-kicker">Contabilidad</span>
                <h1>Ingresos por sendero</h1>
                <p>Marca quien asistio y registra el cobro de cada persona. Cada cambio se guarda al momento.</p>
            </div>
            <a class="fin-back" href="<?= BASE_URL ?>pantallas/panel_administrativo.php">
                <i data-feather="arrow-left"></i>
                Volver al panel
            </a>
        </header>

        <?php sgf_render([
            'conn' => $conn,
            'cuadre_area' => 'cobros',
            'params' => $senderoFiltros,
            'niveles' => $nivelesDificultad,
            'senderos' => $senderos,
            'selected_id' => $senderoId,
            'clear_url' => BASE_URL . 'mantenimientos/mantenimiento_ingresos_sendero.php',
            'card_class' => 'fin-card',
            'head_class' => 'fin-card-head',
            'form_class' => 'fin-filter-form',
            'icon' => 'users',
            'option_label' => static function (array $sendero): string {
                $km = $sendero['distancia_km'] !== null ? ' - ' . number_format((float) $sendero['distancia_km'], 1) . ' km' : '';
                $dificultad = !empty($sendero['dificultad_nombre']) ? ' - ' . $sendero['dificultad_nombre'] : '';
                return $sendero['nombre'] . ' - ' . sg_fecha($sendero['fecha_sendero']) . $dificultad . $km . ' (' . (int) $sendero['pagados'] . '/' . (int) $sendero['inscritos'] . ' pagados)';
            },
        ]); ?>

        <?php if (!$senderoSeleccionado): ?>
            <section class="fin-empty">
                <i data-feather="credit-card"></i>
                <h2>Selecciona un sendero</h2>
                <p>Veras las personas inscritas para registrar asistencia y cobros.</p>
            </section>
        <?php elseif (!$esquemaListo): ?>
            <div class="fin-alert error">
                Esta pantalla necesita una actualizacion de la base de datos.
                Aplicala en <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_actualizaciones.php">Actualizaciones del sistema</a> y vuelve aqui.
            </div>
        <?php else: ?>
            <section class="fin-card ing-cabecera">
                <div class="ing-sendero">
                    <div>
                        <span class="ing-sendero-estado"><?= sg_h(ucfirst((string) $senderoSeleccionado['estado'])) ?></span>
                        <h2><?= sg_h($senderoSeleccionado['nombre']) ?></h2>
                        <p><?= sg_h(sg_fecha($senderoSeleccionado['fecha_sendero'])) ?> · <?= count($filas) ?> inscripciones</p>
                    </div>
                    <div class="ing-sendero-lado">
                        <?php sg_cuadre_sendero_render($conn, (int) $senderoId, 'cobros'); ?>
                        <?php if (sg_has_permission($conn, 'operaciones.asistencia')): ?>
                            <a class="ing-btn" href="<?= sg_h(BASE_URL . 'admin/asistencia-senderos?sendero_id=' . (int) $senderoId) ?>" title="Ver y marcar asistencia de este sendero" data-confirmar-salida>
                                <i data-feather="check-square"></i>
                                Asistencia
                            </a>
                        <?php endif; ?>
                        <span class="ing-guardado" data-estado-guardado data-estado="listo" title="Cada cambio se guarda al momento; no hace falta un boton de guardar.">
                            <i data-feather="check-circle"></i><span data-estado-texto>Guardado automatico</span>
                        </span>
                        <a class="fin-export-link" href="<?= BASE_URL ?>procesos/proceso_exportar_ingresos_sendero.php?sendero_id=<?= (int) $senderoId ?>" title="Exportar ingresos a Excel">
                            <i data-feather="download"></i>
                            <span>Excel</span>
                        </a>
                    </div>
                </div>
                <div data-resumen><?= ic_render_resumen($totales) ?></div>
            </section>

            <section class="fin-card ing-lista">
                <?php if (empty($filas)): ?>
                    <div class="fin-empty compact">
                        <i data-feather="user-x"></i>
                        <h2>Sin inscritos activos</h2>
                        <p>Este sendero no tiene reservas activas.</p>
                    </div>
                <?php else: ?>
                    <div class="ing-barra">
                        <div class="fin-table-search">
                            <i data-feather="search" aria-hidden="true"></i>
                            <label class="fin-sr-only" for="ing-buscar">Buscar participante</label>
                            <input id="ing-buscar" type="search" data-buscar placeholder="Buscar por nombre, usuario, telefono o inversion" autocomplete="off">
                        </div>
                        <label class="ing-metodo-rapido">
                            <span>Pago rapido con</span>
                            <select data-metodo-rapido aria-label="Metodo de pago para el pago rapido">
                                <?php foreach ($metodosPago as $metodo): ?>
                                    <option value="<?= (int) $metodo['id'] ?>"><?= sg_h($metodo['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="ing-acciones">
                            <button type="button" class="ing-btn" data-masivo-asistencia><i data-feather="check-square"></i><span data-asistencia-texto>Marcar asistencia</span></button>
                            <button type="button" class="ing-btn" data-masivo-cobro><i data-feather="dollar-sign"></i><span data-cobro-texto>Cobro completo</span></button>
                        </div>
                    </div>
                    <div class="ing-filtros" role="group" aria-label="Filtrar inscritos">
                        <?php foreach ($filtros as $codigo => $titulo): ?>
                            <button type="button" class="ing-filtro<?= $codigo === 'todos' ? ' is-active' : '' ?>" data-filtro="<?= $codigo ?>" aria-pressed="<?= $codigo === 'todos' ? 'true' : 'false' ?>">
                                <?= sg_h($titulo) ?>
                                <span data-cuenta="<?= $codigo ?>"><?= $codigo === 'todos' ? count($filas) : (int) ($totales['grupos'][$codigo] ?? 0) ?></span>
                            </button>
                        <?php endforeach; ?>
                        <button type="button" class="ing-filtro is-warning" data-filtro="revisar" aria-pressed="false" <?= $totales['revisar'] > 0 ? '' : 'hidden' ?>>
                            Revisar <span data-cuenta="revisar"><?= (int) $totales['revisar'] ?></span>
                        </button>
                    </div>

                    <div class="ing-tabla-wrap">
                        <table class="ing-tabla">
                            <thead>
                                <tr>
                                    <th scope="col">Asistio</th>
                                    <th scope="col">Participante</th>
                                    <th scope="col">A pagar</th>
                                    <th scope="col">Como pago</th>
                                    <th scope="col">Balance</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col"><span class="fin-sr-only">Accion</span></th>
                                </tr>
                            </thead>
                            <tbody data-filas>
                                <?php foreach ($filas as $fila): ?>
                                    <?= ic_render_fila($fila, $catalogo) ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="ing-vacio" data-sin-resultados hidden>No hay inscritos con ese filtro o busqueda.</p>
                    </div>
                <?php endif; ?>
            </section>

            <dialog class="ing-panel" data-panel aria-labelledby="ing-panel-titulo">
                <form method="dialog" data-panel-form novalidate>
                    <header class="ing-panel-head">
                        <div>
                            <span class="fin-kicker">Cobro</span>
                            <h2 id="ing-panel-titulo" data-p-nombre></h2>
                            <p data-p-detalle></p>
                        </div>
                        <button type="button" class="ing-cerrar" data-cerrar aria-label="Cerrar"><i data-feather="x"></i></button>
                    </header>

                    <div class="ing-panel-cuerpo">
                        <section class="ing-paso">
                            <h3><span>1</span>Asistencia y tipo de cobro</h3>
                            <div class="ing-segmento" role="radiogroup" aria-label="Asistencia">
                                <label><input type="radio" name="asistio" value="1"><span>Asistio</span></label>
                                <label><input type="radio" name="asistio" value="0"><span data-p-no-asistio>No asistio</span></label>
                            </div>
                            <div class="ing-segmento" role="radiogroup" aria-label="Tipo de cobro">
                                <label><input type="radio" name="tipo" value="automatico"><span>Cobro normal</span></label>
                                <label><input type="radio" name="tipo" value="cortesia"><span>Cortesia</span></label>
                                <label><input type="radio" name="tipo" value="exento"><span>Exento</span></label>
                            </div>
                            <p class="ing-ayuda" data-p-ayuda-tipo hidden></p>
                        </section>

                        <section class="ing-paso" data-p-precio>
                            <h3><span>2</span>Lo que le toca pagar</h3>
                            <div class="ing-linea"><span>Valor de la inscripcion</span><strong data-p-valor></strong></div>
                            <div class="ing-campo">
                                <label for="ing-descuento">Descuento</label>
                                <div class="ing-grupo">
                                    <input id="ing-descuento" type="text" inputmode="decimal" name="descuento" placeholder="0.00" autocomplete="off" data-dinero>
                                    <button type="button" class="ing-mini" data-p-desc-modo aria-label="Cambiar entre pesos y porcentaje">RD$</button>
                                </div>
                                <small class="ing-nota" data-p-desc-ayuda></small>
                            </div>
                            <div class="ing-linea is-total"><span>A pagar</span><strong data-p-debe></strong></div>
                        </section>

                        <section class="ing-paso" data-p-pago>
                            <h3><span>3</span>Como pago</h3>
                            <div class="ing-campo" data-p-bloque-credito>
                                <label for="ing-credito">Usar credito a favor <em data-p-usable></em></label>
                                <div class="ing-grupo">
                                    <input id="ing-credito" type="text" inputmode="decimal" name="credito" placeholder="0.00" autocomplete="off" data-dinero>
                                    <button type="button" class="ing-mini" data-p-usar-credito>Usar lo necesario</button>
                                </div>
                            </div>
                            <p class="ing-nota" data-p-sin-cuenta hidden>Esta inscripcion no tiene cuenta ni ficha de persona sin cuenta: no puede usar ni recibir credito; el excedente se devuelve.</p>
                            <div class="ing-campo">
                                <label for="ing-recibido">Recibido (efectivo o transferencia)</label>
                                <div class="ing-grupo">
                                    <input id="ing-recibido" type="text" inputmode="decimal" name="recibido" placeholder="0.00" autocomplete="off" data-dinero>
                                    <button type="button" class="ing-mini" data-p-completar>Lo que falta</button>
                                </div>
                            </div>
                            <div class="ing-dos">
                                <div class="ing-campo">
                                    <label for="ing-metodo">Metodo de pago</label>
                                    <select id="ing-metodo" name="metodo_id">
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($metodosPago as $metodo): ?>
                                            <option value="<?= (int) $metodo['id'] ?>"><?= sg_h($metodo['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="ing-campo">
                                    <label for="ing-fecha">Fecha de pago</label>
                                    <input id="ing-fecha" type="date" name="fecha" max="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                            <?php if ($cargoListo): ?>
                            <div class="ing-campo ing-cargo" data-p-bloque-cargo>
                                <label class="ing-check"><input type="checkbox" data-p-cargar><span>Cuenta por cobrar a otro usuario</span></label>
                                <small class="ing-nota">Se anota como pagado a esta persona y el monto se le carga a otro usuario como cuenta por cobrar en Balances.</small>
                                <div class="ing-dos" data-p-cargo-campos hidden>
                                    <div class="ing-campo">
                                        <label for="ing-cargado-a">Usuario al que se le carga</label>
                                        <select id="ing-cargado-a" name="cargado_a">
                                            <option value="">Seleccione...</option>
                                            <?php foreach ($usuariosCargo as $usuarioCargo): ?>
                                                <option value="<?= (int) $usuarioCargo['id'] ?>"><?= sg_h($usuarioCargo['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="ing-campo">
                                        <label for="ing-cargado">Monto que se le carga</label>
                                        <div class="ing-grupo">
                                            <input id="ing-cargado" type="text" inputmode="decimal" name="cargado" placeholder="0.00" autocomplete="off" data-dinero>
                                            <button type="button" class="ing-mini" data-p-cargo-resto>Lo que falta</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </section>

                        <section class="ing-paso ing-reparto" data-p-reparto hidden>
                            <h3><span>4</span><b data-p-reparto-titulo>Que se hace con el dinero</b></h3>
                            <p class="ing-ayuda" data-p-reparto-ayuda></p>
                            <div class="ing-dos">
                                <div class="ing-campo">
                                    <label for="ing-a-credito">Queda como credito</label>
                                    <div class="ing-grupo">
                                        <input id="ing-a-credito" type="text" inputmode="decimal" name="a_credito" placeholder="0.00" autocomplete="off" data-dinero>
                                        <button type="button" class="ing-mini" data-p-modo="a_credito" aria-label="Cambiar entre pesos y porcentaje de lo recibido">RD$</button>
                                    </div>
                                    <small class="ing-nota" data-p-ayuda="a_credito"></small>
                                </div>
                                <div class="ing-campo">
                                    <label for="ing-devuelto">Se le devuelve</label>
                                    <div class="ing-grupo">
                                        <input id="ing-devuelto" type="text" inputmode="decimal" name="devuelto" placeholder="0.00" autocomplete="off" data-dinero>
                                        <button type="button" class="ing-mini" data-p-modo="devuelto" aria-label="Cambiar entre pesos y porcentaje de lo recibido">RD$</button>
                                    </div>
                                    <small class="ing-nota" data-p-ayuda="devuelto"></small>
                                </div>
                            </div>
                            <div class="ing-atajos">
                                <button type="button" class="ing-mini" data-p-atajo="retener">Retener todo</button>
                                <button type="button" class="ing-mini" data-p-atajo="credito">Todo a credito</button>
                                <button type="button" class="ing-mini" data-p-atajo="devolver">Devolver todo</button>
                                <button type="button" class="ing-mini is-sugerido" data-p-como-descuento hidden></button>
                            </div>
                            <div class="ing-linea"><span>Se queda para el sendero</span><strong data-p-retenido></strong></div>
                        </section>

                        <div class="ing-campo">
                            <label for="ing-nota">Nota (opcional)</label>
                            <input id="ing-nota" type="text" name="nota" maxlength="255" placeholder="Ej.: transferencia del 12/10, referencia 4455" autocomplete="off">
                        </div>

                        <div class="ing-resultado" data-p-resultado aria-live="polite"></div>
                        <ul class="ing-errores" data-p-errores aria-live="assertive"></ul>
                    </div>

                    <footer class="ing-panel-pie">
                        <button type="button" class="ing-btn is-peligro" data-p-deshacer hidden><i data-feather="rotate-ccw"></i>Deshacer cobro</button>
                        <button type="button" class="ing-btn" data-cerrar>Cancelar</button>
                        <button type="submit" class="ing-btn is-primary" data-p-guardar>Guardar cobro</button>
                    </footer>
                </form>
            </dialog>

            <dialog class="ing-panel ing-panel-chico" data-masivo aria-labelledby="ing-masivo-titulo">
                <form method="dialog" data-masivo-form>
                    <header class="ing-panel-head">
                        <div>
                            <span class="fin-kicker">Accion para varios</span>
                            <h2 id="ing-masivo-titulo" data-m-titulo></h2>
                        </div>
                        <button type="button" class="ing-cerrar" data-cerrar aria-label="Cerrar"><i data-feather="x"></i></button>
                    </header>
                    <div class="ing-panel-cuerpo">
                        <p class="ing-ayuda" data-m-texto></p>
                        <ul class="ing-lista-nombres" data-m-nombres></ul>
                        <div class="ing-dos" data-m-pago>
                            <div class="ing-campo">
                                <label for="ing-m-metodo">Metodo de pago</label>
                                <select id="ing-m-metodo" name="metodo_id">
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($metodosPago as $metodo): ?>
                                        <option value="<?= (int) $metodo['id'] ?>"><?= sg_h($metodo['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="ing-campo">
                                <label for="ing-m-fecha">Fecha de pago</label>
                                <input id="ing-m-fecha" type="date" name="fecha" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        <ul class="ing-errores" data-m-errores></ul>
                    </div>
                    <footer class="ing-panel-pie">
                        <button type="button" class="ing-btn" data-cerrar>Cancelar</button>
                        <button type="submit" class="ing-btn is-primary" data-m-confirmar>Confirmar</button>
                    </footer>
                </form>
            </dialog>

            <div class="ing-aviso" data-aviso role="status" aria-live="polite" hidden></div>
        <?php endif; ?>
    </section>
</main>

<?php
mysqli_close($conn);
include_once __DIR__ . '/../componentes/pie_pagina.php';
?>
