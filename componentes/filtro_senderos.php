<?php

if (!function_exists('sgf_h')) {
    function sgf_h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sgf_params')) {
    function sgf_params(): array
    {
        $fechaDesde = trim((string) ($_GET['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($_GET['fecha_hasta'] ?? ''));
        $kmMin = trim((string) ($_GET['km_min'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) {
            $fechaDesde = '';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) {
            $fechaHasta = '';
        }

        return [
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'nivel_dificultad_id' => max(0, (int) ($_GET['nivel_dificultad_id'] ?? 0)),
            'km_min' => is_numeric($kmMin) ? max(0, (float) $kmMin) : '',
        ];
    }
}

if (!function_exists('sgf_where')) {
    function sgf_where(array $params, string $alias = 's'): array
    {
        $where = ["{$alias}.activo = 1"];
        $types = '';
        $values = [];

        if ($params['fecha_desde'] !== '') {
            $where[] = "{$alias}.fecha_sendero >= ?";
            $types .= 's';
            $values[] = $params['fecha_desde'];
        }

        if ($params['fecha_hasta'] !== '') {
            $where[] = "{$alias}.fecha_sendero <= ?";
            $types .= 's';
            $values[] = $params['fecha_hasta'];
        }

        if ((int) $params['nivel_dificultad_id'] > 0) {
            $where[] = "{$alias}.nivel_dificultad_id = ?";
            $types .= 'i';
            $values[] = (int) $params['nivel_dificultad_id'];
        }

        if ($params['km_min'] !== '') {
            $where[] = "{$alias}.distancia_km >= ?";
            $types .= 'd';
            $values[] = (float) $params['km_min'];
        }

        return ['WHERE ' . implode(' AND ', $where), $types, $values];
    }
}

if (!function_exists('sgf_execute_query')) {
    function sgf_execute_query(mysqli $conn, string $sql, string $types = '', array $values = [])
    {
        if ($types === '') {
            return mysqli_query($conn, $sql);
        }

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }

        $refs = [];
        foreach ($values as $key => $value) {
            $refs[$key] = &$values[$key];
        }
        mysqli_stmt_bind_param($stmt, $types, ...$refs);
        mysqli_stmt_execute($stmt);
        return mysqli_stmt_get_result($stmt);
    }
}

if (!function_exists('sgf_niveles_dificultad')) {
    function sgf_niveles_dificultad(mysqli $conn): array
    {
        $niveles = [];
        $res = mysqli_query($conn, "SELECT id, nombre FROM niveles_dificultad WHERE activo = 1 ORDER BY nivel_numero ASC, nombre ASC");
        while ($res && $row = mysqli_fetch_assoc($res)) {
            $niveles[] = $row;
        }

        return $niveles;
    }
}

if (!function_exists('sgf_has_search')) {
    function sgf_has_search(array $params, int $selectedId = 0): bool
    {
        return $selectedId > 0
            || isset($_GET['buscar_sendero'])
            || $params['fecha_desde'] !== ''
            || $params['fecha_hasta'] !== ''
            || (int) $params['nivel_dificultad_id'] > 0
            || $params['km_min'] !== '';
    }
}

if (!function_exists('sgf_fecha')) {
    function sgf_fecha(?string $fecha): string
    {
        $time = $fecha ? strtotime($fecha) : false;
        return $time ? date('d/m/Y', $time) : 'Sin fecha';
    }
}

if (!function_exists('sgf_sendero_metric')) {
    function sgf_sendero_metric(array $sendero): string
    {
        if (array_key_exists('asistieron', $sendero) && array_key_exists('registrados', $sendero)) {
            return (int) $sendero['asistieron'] . '/' . (int) $sendero['registrados'] . ' asistieron';
        }

        if (array_key_exists('activos', $sendero)) {
            return (int) $sendero['activos'] . ' activos';
        }

        if (array_key_exists('pagados', $sendero) && array_key_exists('inscritos', $sendero)) {
            return (int) $sendero['pagados'] . '/' . (int) $sendero['inscritos'] . ' pagados';
        }

        if (array_key_exists('total_gastos', $sendero)) {
            return 'RD$ ' . number_format((float) $sendero['total_gastos'], 2);
        }

        if (array_key_exists('ingresos', $sendero) && array_key_exists('gastos', $sendero)) {
            return 'Utilidad RD$ ' . number_format((float) $sendero['ingresos'] - (float) $sendero['gastos'], 2);
        }

        if (array_key_exists('total_registros', $sendero)) {
            return (int) $sendero['total_registros'] . ' registros';
        }

        return (string) ($sendero['estado'] ?? '');
    }
}

if (!function_exists('sgf_render')) {
    function sgf_render(array $config): void
    {
        $params = $config['params'] ?? sgf_params();
        $niveles = $config['niveles'] ?? [];
        $senderos = $config['senderos'] ?? [];
        $selectedId = (int) ($config['selected_id'] ?? 0);
        $clearUrl = (string) ($config['clear_url'] ?? '');
        $cardClass = (string) ($config['card_class'] ?? 'sg-filter-card');
        $headClass = (string) ($config['head_class'] ?? 'sg-filter-head');
        $formClass = (string) ($config['form_class'] ?? '');
        $icon = (string) ($config['icon'] ?? 'map');
        $hasSearch = sgf_has_search($params, $selectedId);
        $isCollapsed = $selectedId > 0 && !isset($_GET['buscar_sendero']);
        $selectedSendero = null;
        foreach ($senderos as $sendero) {
            if ((int) ($sendero['id'] ?? 0) === $selectedId) {
                $selectedSendero = $sendero;
                break;
            }
        }
        ?>
        <section class="<?= sgf_h($cardClass) ?> sg-filter-panel <?= $isCollapsed ? 'is-collapsed' : '' ?>" data-sg-filter-panel>
            <fieldset class="sg-filter-fieldset">
                <legend>
                    <button type="button" class="sg-filter-toggle" data-sg-filter-toggle aria-expanded="<?= $isCollapsed ? 'false' : 'true' ?>">
                        <span></span>
                        Criterios de busqueda
                    </button>
                </legend>

                <?php if ($selectedSendero): ?>
                    <div class="sg-filter-summary" data-sg-filter-summary>
                        <strong><?= sgf_h($selectedSendero['nombre'] ?? '') ?></strong>
                        <span><?= sgf_h(sgf_fecha($selectedSendero['fecha_sendero'] ?? null)) ?></span>
                        <span><?= sgf_h($selectedSendero['dificultad_nombre'] ?? 'Sin dificultad') ?></span>
                        <span><?= sgf_h($selectedSendero['distancia_km'] !== null ? number_format((float) $selectedSendero['distancia_km'], 1) . ' km' : 'Sin km') ?></span>
                    </div>
                <?php endif; ?>

                <div class="sg-filter-body" data-sg-filter-body>
                    <div class="<?= sgf_h($headClass) ?> sg-filter-heading">
                        <div>
                            <span>Filtro</span>
                            <h2>Buscar senderos</h2>
                        </div>
                        <i data-feather="<?= sgf_h($icon) ?>"></i>
                    </div>
                    <form method="GET" class="sg-sendero-filter <?= sgf_h($formClass) ?>">
                        <label>
                            <span>Fecha desde</span>
                            <input type="date" name="fecha_desde" value="<?= sgf_h($params['fecha_desde'] ?? '') ?>">
                        </label>
                        <label>
                            <span>Fecha hasta</span>
                            <input type="date" name="fecha_hasta" value="<?= sgf_h($params['fecha_hasta'] ?? '') ?>">
                        </label>
                        <label>
                            <span>Dificultad</span>
                            <select name="nivel_dificultad_id">
                                <option value="">Todas</option>
                                <?php foreach ($niveles as $nivel): ?>
                                    <option value="<?= (int) $nivel['id'] ?>" <?= (int) ($params['nivel_dificultad_id'] ?? 0) === (int) $nivel['id'] ? 'selected' : '' ?>>
                                        <?= sgf_h($nivel['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Kilometros mayor a</span>
                            <input type="number" name="km_min" min="0" step="0.1" value="<?= sgf_h($params['km_min'] ?? '') ?>" placeholder="0.0">
                        </label>
                        <div class="sg-filter-actions">
                            <button type="submit" name="buscar_sendero" value="1">
                                <i data-feather="search"></i>
                                Buscar sendero
                            </button>
                            <?php if ($clearUrl !== ''): ?>
                                <a href="<?= sgf_h($clearUrl) ?>">Limpiar</a>
                            <?php endif; ?>
                        </div>

                        <?php if ($hasSearch): ?>
                            <div class="sg-filter-results">
                                <div>
                                    <strong><?= count($senderos) ?></strong>
                                    <span>senderos encontrados</span>
                                </div>
                            </div>
                            <?php if (!empty($senderos)): ?>
                                <div class="sg-filter-sendero">
                                    <span>Selecciona un sendero</span>
                                    <div class="sg-sendero-results" role="radiogroup" aria-label="Senderos encontrados">
                                        <div class="sg-sendero-row sg-sendero-row-head" aria-hidden="true">
                                            <span></span>
                                            <strong>Sendero</strong>
                                            <strong>Fecha</strong>
                                            <strong>Dificultad</strong>
                                            <strong>Km</strong>
                                            <strong>Resumen</strong>
                                        </div>
                                        <?php foreach ($senderos as $sendero): ?>
                                            <?php
                                            $id = (int) $sendero['id'];
                                            $checked = $id === $selectedId;
                                            $dificultad = trim((string) ($sendero['dificultad_nombre'] ?? 'Sin dificultad'));
                                            $km = $sendero['distancia_km'] !== null ? number_format((float) $sendero['distancia_km'], 1) . ' km' : 'Sin km';
                                            ?>
                                            <label class="sg-sendero-row <?= $checked ? 'is-selected' : '' ?>">
                                                <input type="radio" name="sendero_id" value="<?= $id ?>" <?= $checked ? 'checked' : '' ?>>
                                                <span class="sg-sendero-radio"></span>
                                                <strong><?= sgf_h($sendero['nombre'] ?? '') ?></strong>
                                                <span><?= sgf_h(sgf_fecha($sendero['fecha_sendero'] ?? null)) ?></span>
                                                <span><?= sgf_h($dificultad) ?></span>
                                                <span><?= sgf_h($km) ?></span>
                                                <span><?= sgf_h(sgf_sendero_metric($sendero)) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="sg-filter-use">
                                    <button type="submit">
                                        <i data-feather="check-circle"></i>
                                        Trabajar con sendero
                                    </button>
                                </div>
                            <?php else: ?>
                                <p class="sg-filter-empty">No encontramos senderos con esos criterios.</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="sg-filter-help">Coloca los criterios y pulsa Buscar sendero para desplegar las rutas disponibles.</p>
                        <?php endif; ?>
                    </form>
                </div>
                <script>
                document.addEventListener('click', function (event) {
                    var toggle = event.target.closest('[data-sg-filter-toggle]');
                    if (!toggle) {
                        return;
                    }
                    var panel = toggle.closest('[data-sg-filter-panel]');
                    if (!panel) {
                        return;
                    }
                    var collapsed = panel.classList.toggle('is-collapsed');
                    toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                });
                document.addEventListener('change', function (event) {
                    if (!event.target.matches('.sg-sendero-row input[name="sendero_id"]')) {
                        return;
                    }
                    var group = event.target.closest('.sg-sendero-results');
                    if (!group) {
                        return;
                    }
                    group.querySelectorAll('.sg-sendero-row').forEach(function (row) {
                        row.classList.remove('is-selected');
                    });
                    event.target.closest('.sg-sendero-row')?.classList.add('is-selected');
                });
                document.addEventListener('dblclick', function (event) {
                    var row = event.target.closest('.sg-sendero-row:not(.sg-sendero-row-head)');
                    if (!row) {
                        return;
                    }
                    var radio = row.querySelector('input[name="sendero_id"]');
                    var form = row.closest('form');
                    if (!radio || !form) {
                        return;
                    }
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change', { bubbles: true }));
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                });
                </script>
            </fieldset>
        </section>
        <?php
    }
}
