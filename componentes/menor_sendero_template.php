<template id="asisMinorTemplate">
    <section class="asis-minor-card" data-minor-card>
        <div class="asis-minor-card-head">
            <strong data-minor-title>Menor</strong>
            <button type="button" data-remove-minor aria-label="Quitar menor" title="Quitar menor"><i data-feather="trash-2"></i></button>
        </div>
        <div class="asis-minor-grid">
            <label class="asis-field"><span>Nombre *</span><input type="text" data-minor-name="nombre" maxlength="100" required></label>
            <label class="asis-field"><span>Apellido *</span><input type="text" data-minor-name="apellido" maxlength="100" required></label>
            <label class="asis-field"><span>Telefono</span><input type="text" data-minor-name="telefono" maxlength="30"></label>
            <label class="asis-field">
                <span>Inversion del menor *</span>
                <select data-minor-name="inversion_id" required>
                    <option value="">Selecciona una inversion</option>
                    <?php foreach ($inversionesMenoresSendero as $inversion): ?>
                        <?php $montoMenor = (int) ($senderoSeleccionado['menores_misma_inversion'] ?? 1) === 1
                            ? ($inversion['monto_menor'] ?? $inversion['monto'])
                            : $inversion['monto']; ?>
                        <option value="<?= (int) $inversion['id'] ?>">
                            <?= asis_h($inversion['nombre']) ?> - RD$ <?= number_format((float) $montoMenor, 2) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="asis-field"><span>Edad *</span><select data-minor-name="rango_edad" required>
                <option value="">Selecciona</option><option value="8-12">8-12</option><option value="13-17">13-17</option>
            </select></label>
            <label class="asis-field"><span>Grupo sanguineo *</span><select data-minor-name="grupo_sanguineo" required>
                <option value="">Selecciona</option><option value="O+">O+</option><option value="O-">O-</option>
                <option value="A+">A+</option><option value="A-">A-</option><option value="AB+">AB+</option>
                <option value="AB-">AB-</option><option value="B+">B+</option><option value="B-">B-</option>
            </select></label>
            <label class="asis-field"><span>Alergico</span><select data-minor-name="es_alergico" data-minor-allergy>
                <option value="0">No</option><option value="1">Si</option>
            </select></label>
            <label class="asis-field"><span>Detalle alergia</span><input type="text" data-minor-name="alergias_detalle" maxlength="255" data-minor-allergy-detail></label>
            <label class="asis-field"><span>Enfermedad *</span><input type="text" data-minor-name="enfermedad" maxlength="255" required placeholder="Ninguna / detalle"></label>
            <label class="asis-field"><span>Seguro medico *</span><input type="text" data-minor-name="seguro_medico" maxlength="255" required placeholder="Ninguno / nombre"></label>
            <label class="asis-field"><span>Experiencia *</span><select data-minor-name="experiencia_senderismo" required>
                <option value="">Selecciona</option><option value="Primera vez">Primera vez</option>
                <option value="Principiante">Principiante</option><option value="Intermedio">Intermedio</option>
                <option value="Avanzado">Avanzado</option>
            </select></label>
            <label class="asis-field"><span>Emergencia nombre *</span><input type="text" data-minor-name="emergencia_nombre" maxlength="150" required></label>
            <label class="asis-field"><span>Parentesco *</span><input type="text" data-minor-name="emergencia_parentesco" maxlength="80" required></label>
            <label class="asis-field"><span>Telefono emergencia *</span><input type="text" data-minor-name="emergencia_telefono" maxlength="30" required></label>
        </div>
    </section>
</template>
