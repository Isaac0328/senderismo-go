# Registro de cambios · Senderismo Go

Archivo compartido entre **Claude** y **Codex**. Aquí se anota cada cambio con su número de versión para que los dos trabajen a la par sin pisarse.

> **No subir esta carpeta (`docs/`), ni `AGENTS.md`, ni `CLAUDE.md` a producción.** Son notas de trabajo.

---

## 1. Cómo trabajar (obligatorio para Claude y Codex)

**Antes de empezar cualquier cambio**
1. Lee este archivo completo, sobre todo **"Estado actual"** y las últimas entradas del historial. Ahí está lo que el otro hizo y lo que sigue en curso.
2. Vuelve a leer del proyecto los archivos que vas a tocar (pueden haber cambiado desde la última vez que los viste). Si otra entrada reciente toca los mismos archivos, no sobrescribas: integra tu cambio sobre su versión.
3. Si vas a subir algo a producción, compara primero con lo que hay allí (ver punto 4). Producción puede tener cambios que no están en local.

**Mientras trabajas**
- Todo cambio se hace **solo en local** (`C:\xampp\htdocs\SG`, base `sgbd`, `http://localhost/SG/`).
- Prueba lo que hiciste. Si usas datos de prueba o un usuario temporal, bórralos o restaura la base al terminar.
- Cambios de base de datos = **migración nueva** en `migraciones/AAAAMMDD_NNN_nombre.php` (siguiente número libre). **Nunca edites una migración ya aplicada**: el sistema guarda su huella y muestra "fue modificada". Si ya la aplicaste en local y necesitas cambiarla, crea otra.

**Al terminar**
1. Sube de versión (ver sección 2) y agrega una entrada **al inicio del historial** (la más nueva arriba) con la plantilla de la sección 3.
2. Actualiza la tabla **"Estado actual"**.
3. Dile al usuario qué archivos cambiaste y espera su prueba.

**Producción (solo con el visto bueno explícito del usuario para esa lista de archivos)**
1. Baja la versión de producción de cada archivo que vas a reemplazar y guárdala como respaldo en `.private/respaldos_produccion/AAAA-MM-DD_nombre/`.
2. Compara el respaldo con la base sobre la que trabajaste. Si **no** es igual, alguien cambió producción: integra, no pises.
3. Sube, y comprueba que el archivo del servidor es idéntico al local, que `php -l` no da errores y que la pantalla responde.
4. Si hay migraciones, recuérdale al usuario aplicarlas en **Actualizaciones del sistema**.
5. Marca la entrada como **Subido a producción: sí (fecha)** y actualiza "Estado actual".
6. Nunca subas `.private/`, `herramientas/`, `docs/`, `AGENTS.md`, `CLAUDE.md`, ni notas o pruebas.


### 1b. Cómo subir a producción, paso a paso

Solo con el visto bueno explícito del usuario **para esa lista de archivos**. Servidor: alias SSH `senderismo` (ya configurado en `~/.ssh/config`; no uses ni escribas contraseñas, ni leas llaves). Sitio: `domains/senderismogo.com/public_html` (relativa al home; la cuenta tiene otros dominios que **no** se tocan).

1. **Lista de archivos.** Separa los nuevos de los modificados. Las migraciones se suben a `migraciones/` (nunca a `migraciones/CARGADAS/`).
2. **Respaldo de lo que vas a reemplazar** (solo los que ya existen en el servidor), en `.private/respaldos_produccion/AAAA-MM-DD_nombre/` conservando la ruta:
   ```
   scp -o BatchMode=yes senderismo:domains/senderismogo.com/public_html/RUTA/archivo.php .private/respaldos_produccion/AAAA-MM-DD_nombre/RUTA/archivo.php
   ```
3. **Comparar el respaldo con la versión de la que partiste** (compara el MD5 o el contenido). Si **no** es igual, alguien cambió producción (Codex o Claude subió directo): integra tu cambio sobre la versión de producción, no la pises.
4. **Subir** cada archivo:
   ```
   scp -o BatchMode=yes RUTA/archivo.php senderismo:domains/senderismogo.com/public_html/RUTA/archivo.php
   ```
5. **Verificar**:
   - Mismo contenido en el servidor: `ssh -o BatchMode=yes senderismo "cd domains/senderismogo.com/public_html && md5sum RUTA/archivo.php"` y comparar con el MD5 local (`md5sum` o `Get-FileHash -Algorithm MD5`).
   - Sintaxis de cada PHP: `ssh -o BatchMode=yes senderismo "cd domains/senderismogo.com/public_html && php -l RUTA/archivo.php"`.
   - La pantalla responde: `curl -s -o /dev/null -w "%{http_code}" https://senderismogo.com/RUTA`. Las pantallas de administración devuelven **302** hacia `/ingresar` sin sesión (es lo normal); los CSS y JS devuelven **200**.
6. **Migraciones**: avisa al usuario que debe aplicarlas en *Actualizaciones del sistema*. Se sabe que ya se aplicaron cuando el archivo pasa de `migraciones/` a `migraciones/CARGADAS/` en el servidor (`ssh ... "ls domains/senderismogo.com/public_html/migraciones/CARGADAS"`). El código nuevo debe seguir funcionando mientras la migración no esté aplicada (comprueba si la columna o tabla existe antes de usarla).
7. **Registrar**: en tu entrada del historial pon "Producción: sí (fecha)" y la carpeta del respaldo, y actualiza "Estado actual".

**Nunca subir**: `docs/`, `AGENTS.md`, `CLAUDE.md`, `.private/`, `herramientas/`, `vendor/` (ya está en el servidor), archivos `.sql` ni notas o pruebas. **Nunca** copiar a local los respaldos del servidor sobre archivos de trabajo sin revisar.

Si algo falla al verificar, **no sigas subiendo**: restaura desde el respaldo del paso 2 (`scp` en sentido contrario) y avisa al usuario.

---

## 2. Versionado

Formato `vMAYOR.MENOR.PARCHE`:
- **MAYOR**: módulo nuevo o cambio grande de estructura.
- **MENOR**: función nueva dentro de un módulo existente.
- **PARCHE**: corrección o ajuste de diseño/texto.

La versión es un solo contador del proyecto: quien cambie algo toma la siguiente. No reutilices números.

---

## 3. Plantilla de entrada

```
### vX.Y.Z · AAAA-MM-DD · Quién (Claude/Codex) · Título corto
- Qué cambió: ...
- Archivos: `ruta/archivo.php`, ...
- Migración: ninguna / `AAAAMMDD_NNN_nombre` (pendiente o aplicada en producción)
- Probado: cómo se probó
- Producción: no / sí (fecha) · respaldo en .private/respaldos_produccion/...
- Notas: pendientes, riesgos, decisiones del usuario
```

---

## 4. Estado actual

| | Versión | Nota |
|---|---|---|
| Local | v1.19.0 | Código igual al de producción; migraciones 019 a 023 aplicadas en local |
| Producción | v1.19.0 | Incluye v1.7.0 a v1.19.0 y v1.2.0; migraciones 019 a 023 subidas y **pendientes de aplicar** por el usuario, en orden (el código funciona igual sin ellas) |

**En curso / pendiente** (quien lo termine lo mueve al historial)
- Idea sin hacer: convertir los cobros automáticos de senderos en cuentas por cobrar **con registro propio** (hoy se calculan al consultar, ver v1.9.0). Solo si el usuario lo pide.
- Por revisar: **v1.2.0, créditos para personas sin cuenta**. Probar en local que una ficha pueda generar y aplicar crédito, que la reconciliación conserve el saldo e historial al pasar a un usuario y que la columna Pago refleje el cobro. Llegó a local al sincronizar con producción el 27/10 (migraciones 015 y 016); no hay prueba integrada registrada.

---

## 5. Cosas que conviene saber del proyecto

- PHP 8 + mysqli con consultas preparadas. Producción: Hostinger (PHP 8.2, MariaDB 11.8). Local: XAMPP (MariaDB 10.4).
- Rutas: `admin/<nombre>` → `mantenimientos/mantenimiento_<nombre>.php`. Permisos en `componentes/permisos.php` (catálogo, mapa de rutas) y menú en `componentes/menu_panel.php`.
- En el contenido de las páginas **no uses `<nav>` sin neutralizarlo**: el CSS global lo deja fijo arriba. En Balances ya está neutralizado para `.bal-tabs` y `.bal-subtabs`.
- Balances: los movimientos **no se borran**. Cancelar = crear un reverso (`reversa_id`) y el original queda. Cada cambio queda en `fin_auditoria`.
- Probar con datos reales sin ensuciar: usar una transacción con `ROLLBACK`, o respaldar la base antes (`mysqldump`) y restaurarla después. Usuario temporal de pruebas: borrarlo al terminar.
- Respaldos de producción y de local viven en `.private/` (nunca se sube).

---

## 6. Historial (lo más nuevo arriba)

### v1.17.2 · 2026-11-02 · Codex · Aviso al salir con cambios sin guardar
- Qué cambió: al ir entre Ingresos por sendero y Asistencia por sendero, el sistema pide confirmar si hay cambios sin guardar. En Ingresos detecta un cobro abierto modificado o que se está guardando; en Asistencia compara las marcas y notas actuales con las cargadas. La advertencia del navegador también protege al recargar, cerrar la pestaña o usar otra navegación.
- Archivos: `mantenimientos/mantenimiento_ingresos_sendero.php`, `js/ingresos_sendero.js`, `mantenimientos/mantenimiento_asistencia_senderos.php`
- Migración: ninguna
- Probado: sintaxis PHP, JavaScript externo e inline; no se modificaron datos. El aviso de navegador requiere prueba interactiva con una sesión autenticada.
- Producción: sí (2026-11-02) · respaldo en `.private/respaldos_produccion/2026-11-02_aviso_cambios_asistencia_ingresos`
- Notas: después de guardar correctamente no se bloquea la salida. MD5, sintaxis PHP y respuestas HTTP verificados después de publicar.

### v1.17.1 · 2026-11-02 · Codex · Accesos directos entre Asistencia e Ingresos por sendero
- Qué cambió: con un sendero seleccionado, **Ingresos por sendero** muestra el botón **Asistencia**, que abre la asistencia de esa misma ruta. En **Asistencia por sendero**, el botón **Registrar ingresos** abre los cobros de la misma ruta. Ambos conservan el `sendero_id` y se ocultan si el rol no tiene permiso para el módulo de destino.
- Archivos: `mantenimientos/mantenimiento_ingresos_sendero.php`, `mantenimientos/mantenimiento_asistencia_senderos.php`, `css/asistencia_senderos.css`
- Migración: ninguna
- Probado: sintaxis PHP de las dos pantallas y rutas locales `admin/ingresos-sendero?sendero_id=12` y `admin/asistencia-senderos?sendero_id=12` (302 sin sesión, esperado).
- Producción: sí (2026-11-02) · respaldo en `.private/respaldos_produccion/2026-11-02_enlaces_asistencia_ingresos`
- Notas: el acceso de Asistencia se ubica sobre las métricas del sendero y se adapta a una columna en pantallas pequeñas. MD5, sintaxis PHP y respuestas HTTP verificados después de publicar.

### v1.19.0 · 2026-11-03 · Claude · Ingresos por sendero: cuenta por cobrar a otro usuario
- Qué cambió: en el cobro de una inscripción (Ingresos por sendero) hay una casilla **"Cuenta por cobrar a otro usuario"**: se elige el usuario y el monto (botón "Lo que falta"). A la persona se le anota como **pagada** (queda "Al día / Pagado", sin saldo) y el monto se **carga como cuenta por cobrar** al otro usuario en Balances > Por cobrar (con el sendero referenciado y su ficha de Personal y entidades, como en v1.17.0). El monto cargado cuenta como cubierto en el cálculo (servidor y pantalla), se puede combinar con efectivo o crédito, y no puede pasar de lo que falta por pagar ni cargarse a la misma persona. Al **cambiar** el monto o el usuario se actualiza la cuenta por cobrar; al **quitarlo** se anula; si esa cuenta ya tiene cobros, no se puede cambiar ni quitar hasta revertirlos en Balances. La lista muestra "Cargado a NOMBRE: RD$ X", el detalle del sendero suma "Cargado a otros usuarios", y "Cobrar todo" omite esas filas.
- Archivos: `migraciones/20261103_023_cobro_cargado_a_usuario.php` (nuevo); `componentes/ingresos_cobro.php`, `procesos/proceso_ingresos_sendero.php`, `js/ingresos_sendero.js`, `mantenimientos/mantenimiento_ingresos_sendero.php`, `css/ingresos_sendero.css`
- Migración: `20261103_023_cobro_cargado_a_usuario` (columnas `cargado_usuario_id`, `monto_cargado`, `obligacion_id` en `contabilidad_registro_pagos`). Aplicada en local, pendiente en producción. Necesita también la 020 (sendero en cuentas). Sin la migración la casilla no aparece y todo sigue igual.
- Probado: local con admin temporal e inscrito temporal (RD$ 850): cargar todo a otro usuario deja Pagado/saldo 0 y crea la cuenta por cobrar con sendero (aparece en Por cobrar); la lista y el detalle lo muestran; al reabrir el cobro se precarga; al quitar el cargo la cuenta queda anulada y la persona vuelve a deber. Datos de prueba borrados.
- Producción: sí (2026-11-03) · respaldo en `.private/respaldos_produccion/2026-11-03_cobro_cargado` · MD5, `php -l` y HTTP verificados; migración 023 pendiente de aplicar (después de 020)
- Notas: **el monto cargado no cuenta aún como ingreso en los reportes de rentabilidad ni en "Ingreso del sendero"**: solo entra al dinero (Balances) cuando el otro usuario paga la cuenta por cobrar. Esos reportes salen de `contabilidad_registro_pagos` y no de los movimientos; si se quiere que cuenten desde el momento del cargo, es otro cambio. Pendiente de probar: cargar solo una parte combinada con efectivo, y cargar a una persona sin cuenta (ficha).
### v1.18.1 · 2026-11-03 · Claude · Directiva como casilla propia (aparte del rol y de los cargos)
- Qué cambió: **Directiva ya no es un cargo con permisos**: es la casilla **"Miembro de la directiva"** del usuario (`usuarios.es_directiva`), separada del Rol (acceso) y de los Cargos (Contable, Comunicaciones...). Solo marca pertenencia: activa lo de la directiva (exento en senderos, menores que pagan, cobro Exento, Por cobrar) y **no da permisos**. Un usuario puede ser de la directiva y además Contable, de Comunicaciones, etc. En Usuarios, la casilla va en su propio campo "Directiva" y la lista muestra la etiqueta Directiva. La migración marca como directiva a quien tenía ese cargo (o ese rol) y retira el cargo/rol Directiva si nadie lo usa como rol; **sus 4 permisos (panel.dashboard, reportes de rentabilidad, finanzas.panel) desaparecen con él**, como se decidió; hay que dárselos a los cargos que correspondan.
- Archivos: `migraciones/20261103_022_directiva_casilla.php` (nuevo); `componentes/cargos.php`, `componentes/directiva.php`, `mantenimientos/mantenimiento_usuarios.php`, `procesos/proceso_usuarios.php`, `js/usuarios.js`
- Migración: `20261103_022_directiva_casilla` (columna `usuarios.es_directiva`). Aplicada en local, pendiente en producción. Antes de ella el código sigue reconociendo la directiva por el cargo o el rol.
- Probado: local: la casilla y un cargo (Contable) se guardan juntos desde el formulario, se precargan al editar, la lista muestra ambas etiquetas, y el cobro del sendero lo trata como directiva (sin cobro propio). La migración se probó en una copia de la base con un usuario con cargo Directiva (queda marcado), uno con cargo Contable (no) y uno con el rol Directiva (marcado y el rol se conserva mientras alguien lo use). Datos de prueba borrados.
- Producción: sí (2026-11-03) · respaldo en `.private/respaldos_produccion/2026-11-03_directiva_casilla` · MD5, `php -l` y HTTP verificados; migración 022 pendiente de aplicar (después de la 021)
- Notas: orden de migraciones en producción: 021 y luego 022. Si en producción alguien aún tiene el rol Directiva como acceso, esa migración lo marca como directiva pero conserva el rol hasta que se le cambie.
### v1.18.0 · 2026-11-03 · Claude · Rol (acceso) separado de los cargos (Contable, Directiva...)
- Qué cambió: el **Rol** ahora solo dice el acceso (Administrador, Invitado, Normal) y los **cargos** (Contable, Directiva y los que se creen) son grupos aparte, **varios a la vez** por usuario. En Usuarios, el campo "Rol (acceso)" lista solo roles de acceso y hay un campo nuevo **"Cargos"** (casillas); la lista muestra los cargos como etiquetas. En Roles hay un campo **Tipo** (Acceso / Cargo) para crear más cargos y asignarles permisos como siempre. **Los permisos de un usuario son los de su rol más los de todos sus cargos** (`sg_has_permission*` y el menú/avisos suman ambos). **Directiva** pasa a leerse del cargo (exención en senderos, registro con menores, cobro, Por cobrar), así un Administrador también puede ser de la directiva. Se crea el rol de acceso **Normal** (copia de los permisos de Invitado, sin usuarios).
- Archivos: `migraciones/20261103_021_cargos_usuarios.php`, `componentes/cargos.php` (nuevos); `componentes/permisos.php`, `componentes/directiva.php`, `componentes/ingresos_cobro.php`, `componentes/balances_servicio.php`, `componentes/barra_navegacion.php`, `componentes/notificaciones.php`, `mantenimientos/mantenimiento_usuarios.php`, `procesos/proceso_usuarios.php`, `js/usuarios.js`, `css/usuarios.css`, `mantenimientos/mantenimiento_roles.php`, `procesos/proceso_roles.php`, `js/roles.js`, `css/roles.css`, `mantenimientos/mantenimiento_asistencia_senderos.php`, `mantenimientos/mantenimiento_usuarios_senderos.php`
- Migración: `20261103_021_cargos_usuarios` (columna `roles.tipo`, tabla `usuario_cargos`; marca Contable y Directiva como cargos; crea Normal; a quien hoy tiene como rol un cargo le asigna ese cargo y le deja el rol Invitado, sin perder permisos). Aplicada en local, pendiente en producción. Sin ella todo sigue como antes.
- Probado: local con admin temporal y un usuario Invitado con cargos Contable y Directiva: el formulario guarda los cargos (casillas, etiquetas en la lista, edición los precarga), el usuario Invitado+Contable abre el Centro Financiero (permiso del cargo) y no abre Usuarios, y como Invitado+Directiva el cobro del sendero lo muestra exento ("Directiva: sin cobro propio"). Pantalla de Roles con Tipo. Datos de prueba y usuarios temporales borrados.
- Producción: sí (2026-11-03) · respaldo en `.private/respaldos_produccion/2026-11-03_cargos` · MD5, `php -l` y HTTP verificados; migración 021 pendiente de aplicar
- Notas: en producción hay que confirmar que los roles se llamen exactamente "Contable" y "Directiva" (la migración los reconoce por nombre). Las páginas con `$ROLES_PERMITIDOS = [1]` siguen siendo solo del Administrador (rol 1), no se cambió. Quien ya tenía abierta la sesión conserva su rol anterior hasta volver a entrar, pero sus cargos y permisos se leen de la base. Pendiente: decidir si "Finanzas" u otros cargos se crean ya; el cargo Directiva es el único con efecto propio, los demás solo dan sus permisos.
### v1.17.3 · 2026-11-02 · Claude · Ingresos por sendero: Cortesía y Exento ocultan lo que no aplica
- Qué cambió: en el cobro de un inscrito, al elegir **Cortesía** o **Exento** se ocultan "Lo que le toca pagar" (descuento) y "Cómo pagó" (crédito, recibido, método, fecha), porque no se cobra nada; también se vacían descuento y crédito para que no queden valores escondidos. Si la persona **ya tenía dinero recibido**, el bloque de pago y el reparto (queda como crédito / se devuelve) siguen visibles, porque hay que decidir qué pasa con ese dinero antes de guardar (es la regla de siempre, ahora se ve por qué no deja guardar).
- También: un **miembro de la directiva** toma su modo solo. En el cobro, su propia participación ya no cuenta (aunque la inscripción tenga una inversión, p. ej. si se registró antes de la v1.15.0); solo cuentan sus menores. Sin menores queda **Exento** automáticamente, el panel dice "Directiva: sin cobro propio" y no muestra los campos de pago. Con menores, paga solo lo de los menores.
- Archivos: `js/ingresos_sendero.js`, `mantenimientos/mantenimiento_ingresos_sendero.php`, `componentes/ingresos_cobro.php`
- Migración: ninguna
- Probado: local con un usuario de rol Directiva inscrito con inversión: el panel lo muestra sin cobro propio, al guardar queda Exento con esperado 0 y sin saldo (datos de prueba borrados). Persona sin pago, Exento deja solo asistencia, tipo y nota, con "Guardar cobro"; persona con RD$ 850 recibidos, Exento muestra el pago y el reparto; al volver a Cobro normal reaparece todo. No se guardó ningún cobro.
- Producción: sí (2026-11-02) · respaldo en `.private/respaldos_produccion/2026-11-02_exento_directiva` · MD5, `php -l` y HTTP verificados
- Notas: el servidor (`ic_calcular`) no cambió: sigue exigiendo repartir lo recibido.
### v1.17.0 · 2026-11-02 · Claude · Cargar cuentas a un usuario y referenciar un sendero
- Qué cambió: en **Por cobrar / Por pagar (Crear)** y en **Registrar ingreso / gasto** hay una casilla **"Cargar a usuario"** (en gastos, "Pagar a un usuario registrado"): al marcarla, "Quien debe pagar" se cambia por la lista de usuarios registrados (incluida la directiva) y se guarda a su nombre. La primera vez se crea su ficha en Personal y entidades (Directivo si su rol es Directiva, Cliente en los demás casos) y se reutiliza después. Nuevo campo **"Referenciar a un sendero"** (opcional) en esos mismos formularios: se guarda en la cuenta y en los movimientos, los abonos heredan el sendero de la cuenta, aparece como "Sendero: ..." en Consultar, y se puede cambiar al editar un ingreso o gasto. Es solo una referencia: **no cambia** Ingresos por sendero, Por cobrar automático ni los reportes de rentabilidad. Caso de uso: la directiva que lleva 2 invitados adultos y paga por ellos (opción 2 acordada).
- Archivos: `migraciones/20261102_020_cuentas_usuario_sendero.php`, `js/balances_usuario_sendero.js`, `css/balances_usuario_sendero.css` (nuevos); `componentes/balances_servicio.php`, `componentes/balances_vista.php`, `componentes/balances_obligaciones.php`, `componentes/balances_registrar.php`, `mantenimientos/mantenimiento_balances.php`
- Migración: `20261102_020_cuentas_usuario_sendero` (agrega `fin_terceros.usuario_id` y `fin_obligaciones.sendero_id`; aplicada en local, pendiente en producción). Sin ella el código sigue igual que antes (sin casilla ni campo en cuentas; el sendero sí funciona en ingresos y gastos).
- Probado: local con administrador temporal: cuenta por cobrar a un usuario con sendero (ficha creada como Cliente, auditoría registrada), abono desde Registrar ingreso hereda el sendero, ingreso cobrado ahora a un usuario con sendero, Consultar muestra "Sendero: ...", editar precarga el sendero. Datos de prueba borrados; el usuario temporal también.
- Producción: sí (2026-11-02) · respaldo en `.private/respaldos_produccion/2026-11-02_cuentas_usuario_sendero` · MD5, `php -l` y HTTP verificados; migración 020 pendiente de aplicar
- Notas: `componentes/balances_registrar.php` parte de la versión de Codex v1.16.1 (ya en producción); comparar antes de subir. Pendiente por decidir: lista de usuarios larga (hoy es un desplegable normal, se podría hacer buscable); que la referencia al sendero sume en Ingresos por sendero / rentabilidad (hoy no).
### v1.16.1 · 2026-11-02 · Codex · Confirmación visible al registrar gastos
- Qué cambió: después de registrar un gasto o ingreso, la confirmación se muestra debajo del botón de guardar, dentro del mismo formulario, con estilo verde. Al recargar, la pantalla lleva el aviso a la vista para que no quede oculto en el encabezado de Balances.
- Archivos: `componentes/balances_registrar.php`, `js/balances.js`, `css/balances.css`
- Migración: ninguna
- Probado: sintaxis PHP (`php -l`) y JavaScript (`node --check`); se verificó que el formulario incluye el aviso accesible y que el mensaje retornado por el guardado se dirige a ese aviso.
- Producción: sí (2026-11-02) · respaldo en `.private/respaldos_produccion/2026-11-02_confirmacion_registro_balances`
- Notas: se conserva el aviso general para las acciones de las listas, como Cancelar o las operaciones masivas. MD5, sintaxis PHP y respuestas HTTP verificados después de publicar.

### v1.16.0 · 2026-11-02 · Claude · Tarjetas del panel: elegir qué se muestra, su orden y para qué roles
- Qué cambió: nueva pantalla **Tarjetas del panel** (menú, junto a Configuración de avisos; ruta `admin/panel-tarjetas`, permiso `panel.tarjetas`). Cada pieza del panel administrativo (sendero destacado, atención, accesos rápidos y sus 8 botones, 4 indicadores, y las tarjetas Próximos senderos / Resumen financiero / Registros recientes / Lectura rápida; 19 en total) se puede mostrar u ocultar, reordenar con flechas y limitar a ciertos roles. Guardar y Restablecer piden confirmación. La configuración **solo puede ocultar, nunca dar acceso**: cada pieza sigue exigiendo su permiso (p. ej. el dinero pide Finanzas). Sin la migración, el panel se ve igual que antes (todo visible).
- Archivos: `componentes/panel_tarjetas.php`, `mantenimientos/mantenimiento_panel_tarjetas.php`, `procesos/proceso_panel_tarjetas.php`, `js/panel_tarjetas.js`, `css/panel_tarjetas.css`, `migraciones/20261102_019_panel_tarjetas.php` (todos nuevos); `pantallas/panel_administrativo.php`, `componentes/menu_panel.php`, `componentes/permisos.php`
- Migración: `20261102_019_panel_tarjetas` (crea `panel_tarjetas` y `panel_tarjeta_roles`; aplicada en local, pendiente en producción: el usuario la aplica en Actualizaciones del sistema)
- Probado: local con administrador: guardar/restablecer persisten; el panel respeta ocultas, orden y límite por rol (lógica de visibilidad comprobada por rol: Administrador, Contable, Directiva, Invitado; código desconocido se muestra). Datos de prueba restablecidos y usuarios temporales borrados.
- Producción: sí (2026-11-02) · respaldo en `.private/respaldos_produccion/2026-11-02_panel_tarjetas` · MD5 y `php -l` verificados; migración 019 pendiente de aplicar en Actualizaciones del sistema
- Notas: **hoy el panel administrativo solo lo abre el rol 1** (`$ROLES_PERMITIDOS = [1]` en `pantallas/panel_administrativo.php`), por eso limitar por rol otro que no sea Administrador no tiene efecto visible hasta que ese rol pueda abrir el panel. Abrirlo por permiso (`panel.dashboard`) sería una decisión aparte, no hecha.

### v1.15.0 · 2026-11-02 · Claude · Registro a senderos para el rol Directiva (adulto exento, menores pagan)
- Qué cambió: cuando quien se registra tiene el rol **Directiva**, la pantalla de registro **no pide inversión ni comprobante de pago**: muestra "Sin cobro para ti" y renumera las secciones. Sus **menores acompañantes sí eligen inversión y pagan**. La inscripción se guarda con `inversion_id` vacío; el cálculo del cobro (`ic_calcular`) muestra **Exento** cuando la persona es de la directiva y no hay nada que cobrar, y si lleva menores solo se cobra lo de los menores (sale en Ingresos por sendero y, tras asistir a un sendero ya realizado, en Por cobrar). Cuando un administrador agrega a mano a un usuario de la directiva (Usuarios por sendero y Asistencia), el campo de inversión se oculta y no se exige; el servidor lo repite aunque se manipule la página. Si hace falta cortesía, se pone después en el registro de ingresos del sendero, como se acordó.
- Archivos: `componentes/directiva.php` (nuevo, `sg_usuario_es_directiva`), `componentes/ingresos_cobro.php`, `pantallas/registro_sendero.php`, `procesos/proceso_registro_sendero.php`, `js/registro_sendero.js`, `css/registro_sendero.css`, `procesos/proceso_usuarios_senderos.php`, `mantenimientos/mantenimiento_usuarios_senderos.php`, `mantenimientos/mantenimiento_asistencia_senderos.php`, `js/directiva_manual.js` (nuevo)
- Migración: ninguna (se reconoce a la directiva por el nombre del rol, "Directiva", sin importar su número)
- Probado: local con un usuario de rol Directiva: la pantalla sin inversión ni comprobante, el registro guardado con inversión vacía, cobro Exento sin menores, y con un menor se cobra solo el menor (RD$ 900) y aparece en Por cobrar; alta manual por administrador (campo oculto y guardado sin inversión) y un usuario normal sigue necesitando inversión. Datos de prueba restaurados.
- Producción: sí (2026-11-02) · respaldo en `.private/respaldos_produccion/2026-11-02_directiva_registro`
- Notas: no se pudo comprobar el nombre del rol directamente en la base de producción (consulta no permitida); en local, copia del export de producción, el rol se llama "Directiva". Hoy el rol Directiva no tiene usuarios; la regla se activa en cuanto se asigne ese rol a alguien. El estado "Exento" solo se muestra cuando no se debe nada; si lleva menores el estado es el normal del cobro de los menores. Las personas sin cuenta (fichas) no aplican, porque no tienen rol.
### v1.14.0 · 2026-10-30 · Codex · Alerta de cuenta coincidente en Personas sin cuenta
- Qué cambió: las fichas activas sin cuenta muestran un icono de alerta y un aviso cuando existe una cuenta activa que coincide exactamente por nombre completo, teléfono o correo. El aviso indica la cuenta y el dato coincidente; no reconcilia automáticamente ni modifica datos. La decisión sigue siendo manual mediante **Reconciliar**.
- Archivos: `componentes/personas_sin_cuenta.php`, `mantenimientos/mantenimiento_usuarios_sin_cuenta.php`, `css/usuarios_sin_cuenta.css`
- Migración: ninguna
- Probado: sintaxis PHP, consulta contra la base local (21 fichas activas/reconciliadas y 0 coincidencias actuales); no se crearon datos de prueba ni se modificaron registros.
- Producción: sí (2026-10-30) · respaldo en `.private/respaldos_produccion/2026-10-30_alerta_cuenta_coincidente`
- Notas: al crear un usuario nuevo, la alerta aparece al volver a cargar Usuarios sin cuenta. Solo se consideran cuentas activas para que la reconciliación siempre tenga un destino válido. MD5, sintaxis PHP y respuestas HTTP verificados después de publicar.

### v1.13.0 · 2026-10-30 · Claude · Senderos: reutilizar una imagen ya cargada como principal, flyer o catálogo
- Qué cambió: al **editar** un sendero (Material visual → Imágenes), debajo de Imagen principal, Imagen flyer e Imagen visitados/catálogo hay un botón **Elegir de las imágenes ya cargadas**. Abre una ventana con todas las imágenes que ese sendero ya tiene (las tres principales y la galería activa, sin repetir, con su etiqueta: Principal, Flyer, Catálogo, Galería N). Al elegir una se ve su miniatura, y al **guardar** el sendero ese espacio pasa a usar el mismo archivo: no se sube otra vez ni se borra de donde estaba (la galería se conserva). Sirve para intercambiar imágenes entre sus espacios. Si además se sube un archivo nuevo en ese espacio, el archivo nuevo manda. Hay "Quitar" para deshacer la elección antes de guardar.
- Archivos: `mantenimientos/mantenimiento_senderos.php`, `procesos/proceso_senderos.php` (`sendero_imagenes_reutilizables`), `js/senderos_admin.js`, `css/senderos_admin.css`
- Migración: ninguna
- Probado: local con el sendero 11 (14 imágenes disponibles): principal = flyer guardado correctamente, y catálogo = una imagen de la galería; una ruta falsa enviada a mano (`../configuracion.php`) fue ignorada por el servidor; la galería quedó intacta. Los datos del sendero se restauraron al terminar.
- Producción: sí (2026-10-30) · respaldo en `.private/respaldos_produccion/2026-10-30_senderos_reutilizar_imagenes`
- Notas: el servidor solo acepta rutas que ya pertenezcan a ese sendero (sus tres imágenes y su galería activa). Solo aparece al editar, no al crear un sendero nuevo (aún no tiene imágenes). "Quitar" de la galería solo la desactiva y no borra el archivo, por eso compartir un archivo entre espacios es seguro.
### v1.12.0 · 2026-10-29 · Claude · Por cobrar: crear factura (PDF) de un cobro de sendero
- Qué cambió: en Por cobrar → Consultar, las filas automáticas de senderos tienen el botón **Factura**. Abre una ventana "Crear factura" (fecha de emisión, vencimiento opcional, nota opcional) con **Previsualizar** (PDF en una pestaña nueva) y **Descargar PDF**. El PDF lleva: franja verde con el logo, "FACTURA", No. (`F-` + número de inscripción con 6 dígitos), fechas; bloques "Facturado a" (nombre, teléfono, correo) y "Sendero" (nombre, fecha, lugar); detalle (participación del adulto y de menores), subtotal, descuento, crédito y pagos recibidos si los hay, **TOTAL A PAGAR**; y un cuadro **Cómo pagar** con el mensaje de pago, los datos de la tarjeta de pago activa (`tarjeta_pago`: banco, tipo y número de cuenta, titular, cédula, teléfono para el comprobante) y la nota importante. Si ya está saldada dice SALDO RD$ 0.00 y "Pago recibido". Se quitan emojis de los nombres y los nombres en MAYÚSCULAS se muestran con formato normal.
- Archivos: `componentes/factura_sendero.php` (nuevo), `procesos/proceso_factura_sendero.php` (nuevo), `imagenes/logo/logo_factura.png` (nuevo; logo sobre fondo verde, sin transparencia), `componentes/balances_obligaciones.php`, `css/balances.css`
- Migración: ninguna
- Probado: local (ventana, PDF con y sin menores, saldada; el PDF se revisó convertido a imagen). **El PHP local de XAMPP no tiene GD activa** y Dompdf la exige: para probar facturas en local hay que quitar el `;` de `extension=gd` en `C:\xampp\php\php.ini` y reiniciar Apache (la pantalla lo avisa). En producción GD y Dompdf 3.1.6 están y se probó generar un PDF con datos de ejemplo en el servidor.
- Producción: sí (2026-10-29) · respaldo en `.private/respaldos_produccion/2026-10-29_factura_sendero`
- Notas: **no se guarda un registro de facturas**: el número sale de la inscripción y los montos son los de ese momento, por lo que reimprimir mañana puede mostrar otro saldo si hubo pagos. Si se quiere numeración consecutiva, historial y anular facturas, hace falta una tabla nueva (migración). El permiso requerido es el de crear en Balances. Solo para cobros de senderos; las cuentas por cobrar manuales no tienen factura.
### v1.11.1 · 2026-10-29 · Claude · Montos con dos decimales automáticos en Balances
- Qué cambió: al salir de cualquier campo de monto de Balances (los `input type=number` con paso 0.01: Monto, Abono inicial, saldo inicial, cobros y pagos, etc.), el valor se completa solo con dos decimales: `2000` pasa a `2000.00`, `1500.5` a `1500.50`. Un campo vacío se queda vacío. Mientras se escribe no se toca el valor.
- Archivos: `js/balances.js`
- Migración: ninguna
- Probado: local, escribiendo de verdad en el campo y saliendo con Tab; casos 1500.5, 0.1, 75, 1234.567 (queda 1234.57) y vacío.
- Producción: sí (2026-10-29) · respaldo en `.private/respaldos_produccion/2026-10-29_decimales_monto`
- Notas: otros módulos con montos (Ingresos/Gastos por sendero, inversiones) usan su propio JS y no se tocaron.
### v1.11.0 · 2026-10-29 · Claude · Gastos: campo opcional "Reembolsado a"
- Qué cambió: en Registrar gasto → Detalle del gasto hay un campo de texto libre **Reembolsado a** (opcional, junto a Concepto). Solo aparece en "Pagado ahora" (se oculta en "Queda pendiente" y en abonos). Se guarda con el gasto, se puede **editar** (Actualizar), se muestra bajo el concepto en **Consultar** y en **Movimientos**, se puede **buscar** en Consultar y sale como columna en la **exportación a CSV**. Si no se escribe nada queda vacío (NULL).
- Archivos: `migraciones/20261029_018_gastos_reembolsado_a.php` (nuevo), `componentes/balances_servicio.php`, `componentes/balances_registrar.php`, `mantenimientos/mantenimiento_balances.php`
- Migración: `20261029_018_gastos_reembolsado_a` (columna `fin_movimientos.reembolsado_a`). Aplicada en local; pendiente en producción. Mientras no se aplique, el campo no aparece y todo sigue como antes.
- Probado: local; creado con y sin el campo, editado (queda en `fin_auditoria` el antes y el después), buscado, exportado y verificado que se oculta en "Queda pendiente". Los datos de prueba se borraron.
- Producción: sí (2026-10-29, archivos) · respaldo en `.private/respaldos_produccion/2026-10-29_reembolsado_a`
- Notas: `bal_post` y la edición ahora arman las columnas según exista `reembolsado_a` (`bal_movimientos_reembolso_listo`). Un reverso conserva el valor del original.

### v1.10.0 · 2026-10-29 · Claude · Personal y entidades: Identificación y Contacto separados
- Qué cambió: el campo único "Identificación / contacto" pasa a dos campos: **Identificación** (cédula, RNC o pasaporte; sigue en `referencia`) y **Contacto** (teléfono o correo; columna nueva `contacto`). La lista y la edición muestran los dos. Los datos que ya existían quedan como Identificación; no se intentó separarlos.
- Archivos: `migraciones/20261029_017_terceros_contacto.php` (nuevo), `componentes/balances_servicio.php` (`bal_terceros_contacto_listo`), `mantenimientos/mantenimiento_balances.php`
- Migración: `20261029_017_terceros_contacto` (aplicada en local; **pendiente de aplicar en producción** por el usuario). Mientras no se aplique, el formulario muestra el campo único de antes.
- Probado: local (crear, listar y editar con los dos campos; los datos antiguos se conservan).
- Producción: sí (2026-10-29, archivos) · respaldo en `.private/respaldos_produccion/2026-10-29_terceros_contacto`
### v1.9.3 · 2026-10-29 · Claude · Por cobrar: nombre grande y sendero pequeño en los cobros automáticos
- Qué cambió: en las filas automáticas de senderos (Por cobrar → Consultar) la línea principal es ahora el **nombre de la persona** (15px, negrita) y debajo, en pequeño, el **sendero y el teléfono**. Se quitó el "Participación en" delante del sendero.
- Archivos: `componentes/balances_obligaciones.php`, `css/balances.css`
- Migración: ninguna
- Probado: local con datos reales (tamaño y peso de cada línea verificados).
- Producción: sí (2026-10-29) · respaldo en `.private/respaldos_produccion/2026-10-29_cobros_nombre_grande`
### v1.9.2 · 2026-10-29 · Claude · Botón legible y "gastos" en vez de "salidas" en Balances
- Qué cambió: (1) el texto de los botones **Registrar / Actualizar** en Registrar gasto/ingreso se veía gris y pequeño porque la regla `.bal-form-footer span` (pensada para "* Obligatorio") también le aplicaba al texto interno del botón; ahora es `.bal-form-footer>span`. (2) En Balances se dice **gastos** en lugar de **salidas**: el catálogo se llama **Tipos de ingresos y gastos**, "Gastos del periodo", "Ingresos y gastos por tipo", la clase de un tipo/movimiento se muestra como Gasto (no "salida"), el prefijo de las listas es "Gasto: " y la exportación a CSV escribe Ingreso/Gasto/Transferencia. Los valores guardados en la base (`salida`) no cambian.
- Archivos: `css/balances.css`, `mantenimientos/mantenimiento_balances.php`, `componentes/balances_vista.php` (nuevo ayudante `bal_naturaleza_label`), `componentes/balances_registrar.php`, `componentes/balances_obligaciones.php`
- Migración: ninguna
- Probado: botón verificado en local (texto blanco, 13px). El cambio de textos se verificó con `php -l`, comprobando que no queda "salida" visible en esos archivos y probando el ayudante; no se pudo recorrer la pantalla completa porque Codex reemplazó la base local en medio de la prueba.
- Producción: sí (2026-10-29) · respaldo en `.private/respaldos_produccion/2026-10-29_boton_y_gastos_texto`
- Notas: si algún texto con "salida" aparece en otro módulo (por ejemplo avisos o el panel), no se tocó; avisar para cambiarlo.
### v1.9.1 · 2026-10-29 · Codex · Base local sincronizada con producción
- Qué cambió: se reemplazó la base local `sgbd` con el export actual de `u591234109_senderismo`. Para que los procedimientos funcionen en XAMPP, únicamente durante la importación local sus propietarios técnicos (`DEFINER`) se adaptaron a `root@localhost`; los datos y la estructura del export se conservaron.
- Archivos: `u591234109_senderismo (1).sql` (fuente de importación local), `docs/REGISTRO_DE_CAMBIOS.md`
- Migración: ninguna; se importó el esquema y los datos ya aplicados en producción.
- Probado: 80 tablas, 9 procedimientos, 67 usuarios, 12 senderos y 128 inscripciones coinciden con la validación; conexión PHP local y `sp_iniciar_sesion` correctos.
- Producción: no
- Notas: respaldo previo en `.private/respaldos_bd/sgbd_antes_importar_produccion_correcta_20261029_232139.sql`. El export anterior de `sg_prueba` quedó respaldado también y no es la base activa local.

### v1.9.0 · 2026-10-29 · Claude · Por cobrar y Por pagar: Crear | Consultar + cobros automáticos de senderos
- Qué cambió: **Por cobrar** y **Por pagar** se dividen en **Crear** (formulario a pantalla completa) y **Consultar** (filtros: texto, estado, origen, persona/entidad, vencimiento desde/hasta; totales; lista paginada de 25). **Editar** abre Crear con los datos y el botón **Actualizar** pide confirmación; **Anular** pide confirmación y no elimina (queda como Anulada). En **Por cobrar**, Consultar incluye **automáticamente** a quienes **asistieron** a un sendero que **ya pasó** y **no han pagado**: no hay que crear nada, aparecen solos al marcar la asistencia y desaparecen al pagar. El botón **Cobrar** de esas filas lleva a Ingresos por sendero, donde se registra el pago.
- Archivos: `componentes/balances_obligaciones.php` (nuevo), `mantenimientos/mantenimiento_balances.php`, `css/balances.css`
- Migración: ninguna
- Probado: local con datos de prueba y datos reales (las 2 deudas de senderos coinciden con las de Ingresos por sendero; al marcar asistencia aparece una nueva y al pagar desaparece); crear, editar con confirmación y anular con confirmación.
- Producción: sí (2026-10-29) · respaldo en `.private/respaldos_produccion/2026-10-29_editar_cancelar_usuarios_obligaciones`
- Notas: **decisión de diseño**: los cobros de senderos NO son registros de `fin_obligaciones`; se calculan con la misma lógica de Ingresos por sendero (`ic_cargar`) para no duplicar el dinero ni desfasarse cuando alguien paga. Criterio: inscripción activa + asistió + fecha del sendero anterior a hoy + saldo mayor que cero. Si se quieren abonos, vencimientos o filtros por cuenta sobre estos cobros, habría que materializarlos (migración nueva con `registro_id` en `fin_obligaciones`).

### v1.8.0 · 2026-10-29 · Claude · Usuarios: Crear | Consultar, alertas y eliminar solo sin registros asociados
- Qué cambió: el módulo **Usuarios** se divide en **Crear** y **Consultar** (Editar abre Crear con los datos; hay "Cancelar edición"). Inactivar, activar, actualizar y eliminar piden confirmación. **Eliminar** solo aparece si el usuario está **inactivo** y **sin registros asociados**: inscripciones en senderos, créditos, encuestas respondidas, registros de tesorería o personas sin cuenta reconciliadas; si los tiene, se muestra el motivo ("No se elimina: tiene 1 inscripción en senderos"). El servidor repite la comprobación, aunque se manipule la pantalla. Tras guardar, activar, inactivar o eliminar se vuelve a Consultar.
- Archivos: `componentes/usuarios_vinculos.php` (nuevo), `mantenimientos/mantenimiento_usuarios.php`, `procesos/proceso_usuarios.php`, `js/usuarios.js`, `css/usuarios.css`
- Migración: ninguna (el procedimiento `sp_usuarios_eliminar` no se tocó; las comprobaciones nuevas están en PHP)
- Probado: local con usuarios de prueba (alertas, editar/cancelar edición, eliminar uno libre, eliminar bloqueado por registros incluso forzando la petición).
- Producción: sí (2026-10-29) · respaldo en `.private/respaldos_produccion/2026-10-29_editar_cancelar_usuarios_obligaciones`
- Notas: las invitaciones de encuesta sin responder no cuentan como registro asociado (se borran junto al usuario); las respondidas sí.
### v1.7.1 · 2026-10-29 · Codex · Historial de v1.2.0 completado
- Qué cambió: se reemplazó la descripción provisional de v1.2.0 por el alcance reconstruido desde sus migraciones y código: crédito para fichas de personas sin cuenta, reconciliación con usuarios, tipos de entidad y la columna Pago en Asistencia.
- Archivos: `docs/REGISTRO_DE_CAMBIOS.md`
- Migración: ninguna
- Probado: revisión estática de las migraciones 015 y 016 y del flujo de crédito, reconciliación y Asistencia; no se modificó ni se ejecutó la base de datos.
- Producción: no
- Notas: v1.2.0 conserva una prueba funcional integrada pendiente en local.

### v1.7.0 · 2026-10-29 · Claude · Editar y cancelar gastos e ingresos
- Qué cambió: en Registrar gasto / ingreso → **Consultar** ahora hay **Editar** y **Cancelar** por fila y un filtro de **Estado** (Válidos / Cancelados / Todos). Editar abre el formulario de **Crear** con los datos cargados; el botón dice **Actualizar** y pide confirmación antes de guardar. Cancelar pide confirmación, **no elimina**: crea un reverso con la fecha del movimiento (o la de hoy si ese periodo está cerrado), el original queda como **Cancelado** y deja de sumar y restar. Los abonos a cuentas por pagar/cobrar y los ajustes de saldo inicial solo se pueden cancelar/gestionar donde corresponde. Los cambios quedan en `fin_auditoria` con el antes y el después.
- Archivos: `componentes/balances_servicio.php`, `componentes/balances_registrar.php`, `procesos/proceso_balances.php`, `mantenimientos/mantenimiento_balances.php`, `js/balances.js`, `css/balances.css`
- Migración: ninguna
- Probado: en local con datos de prueba (editar, alerta de confirmación, cancelar, saldo de la cuenta, rechazo de abonos).
- Producción: sí (2026-10-29) · respaldo en `.private/respaldos_produccion/2026-10-29_editar_cancelar_usuarios_obligaciones`
- Notas: la opción de **reactivar** un movimiento cancelado no existe; se vuelve a registrar uno nuevo.

### v1.6.0 · 2026-10-29 · Claude · Crear | Consultar en Registrar gasto/ingreso
- Qué cambió: la pestaña se divide en **Crear** (formulario) y **Consultar** (filtros por fechas, persona/entidad, tipo, cuenta y texto; totales; lista paginada de 25). Se quitó el panel "Últimos gastos registrados".
- Archivos: `componentes/balances_registrar.php`, `mantenimientos/mantenimiento_balances.php`, `css/balances.css`
- Migración: ninguna
- Probado: local con datos de prueba (filtros, reversos fuera de la lista, paginación).
- Producción: sí (2026-10-29) · respaldo en `.private/respaldos_produccion/2026-10-29_balances_crear_consultar`

### v1.5.0 · 2026-10-27 · Claude · Sugerencias de concepto y referencia por proveedor
- Qué cambió: al elegir "Pagado a / Recibido de", salen los conceptos y referencias usados antes con esa persona o entidad (botones y sugerencias al escribir). "Pagado a" pasó antes de "Concepto".
- Archivos: `componentes/balances_registrar.php`, `js/balances.js`, `css/balances.css`
- Migración: ninguna
- Probado: local.
- Producción: sí (2026-10-27) · respaldo en `.private/respaldos_produccion/2026-10-27_balances_sugeridos`

### v1.4.1 · 2026-10-27 · Claude · Un solo selector "Tipo" en Movimientos
- Qué cambió: se quitó "Categoría"; queda un solo selector **Tipo** con Ingresos, Gastos (incluye proveedores y personal) y Transferencias, más los tipos nuevos que se agreguen.
- Archivos: `mantenimientos/mantenimiento_balances.php`
- Producción: sí (2026-10-27) · respaldo en `.private/respaldos_produccion/2026-10-27_balances_tipo_unico`

### v1.4.0 · 2026-10-27 · Claude · Filtros claros en Movimientos
- Qué cambió: filtros por tipo, persona o entidad y estado (vigentes sin reversos / con reversos / solo reversados); totales del filtro; origen en lenguaje claro; exportar respeta los filtros.
- Archivos: `mantenimientos/mantenimiento_balances.php`, `css/balances.css`
- Producción: sí (2026-10-27) · respaldo en `.private/respaldos_produccion/2026-10-27_balances_filtros`
- Notas: ese día se **sincronizó local con producción** (18 archivos y migraciones 015 y 016) porque Codex había cambiado producción directamente. Las versiones locales anteriores quedaron en `.private/respaldos_local/2026-10-27_antes_sync`.

### v1.3.0 · 2026-10-26 · Claude · Encuestas: "Enviar a N nuevos"
- Qué cambió: en encuestas enviadas, el botón dice **Enviar a N nuevos** (personas que cumplen el criterio y aún no tienen la encuesta, p. ej. marcadas como asistió después del envío) o **Todos al día**. El envío y el contador usan el mismo criterio. El destinatario por defecto sigue siendo "Asistentes del sendero" (solo marcados como asistió).
- Archivos: `componentes/encuestas_destinatarios.php` (nuevo), `procesos/proceso_encuestas.php`, `mantenimientos/mantenimiento_encuestas.php`, `css/encuestas_admin.css`
- Producción: sí (2026-10-26) · respaldo en `.private/respaldos_produccion/2026-10-26_encuestas_nuevos`
- Notas: las personas sin cuenta no reciben encuesta (se guarda por usuario con cuenta); al reconciliarse, entran como nuevos.

### v1.2.1 · 2026-10-26 · Claude · Asistencia: "Persona sin cuenta"
- Qué cambió: el modal de agregar asistente en Asistencia por sendero usa la misma lista de **Personas sin cuenta** que Usuarios por sendero.
- Archivos: `mantenimientos/mantenimiento_asistencia_senderos.php`, `css/asistencia_senderos.css`
- Producción: sí (2026-10-26) · respaldo en `.private/respaldos_produccion/2026-10-26_asistencia_sin_cuenta`
- Notas: producción ya tenía la columna "Pago" de Codex; se aplicó el cambio sobre esa versión.

### v1.2.0 · 2026-10-22 · Codex · Crédito para personas sin cuenta y tipos de entidad
- Qué cambió: Ingresos por sendero dejó de depender exclusivamente de una cuenta de usuario para manejar crédito. Una inscripción manual vinculada a una ficha de **Persona sin cuenta** puede generar, conservar y aplicar crédito a favor entre sus senderos. Si la ficha se reconcilia con un usuario registrado, sus inscripciones pasan a la cuenta y los registros de crédito conservan su historial, cambiando el titular a ese usuario. Las inscripciones manuales antiguas que no estén vinculadas a una ficha no pueden generar ni aplicar crédito.
- También se agregó el catálogo editable **Tipos de entidad** para Personal y entidades. Los tipos tienen una clase protegida: Empleado, Directivo, Proveedor, Cliente u Otro; las reglas de custodia de efectivo siguen permitiéndola solamente a Empleado y Directivo.
- Asistencia por sendero incorpora la columna **Pago**, que muestra el estado financiero del participante y, cuando corresponde, si está al día o el saldo que falta.
- Archivos: `migraciones/20261022_015_creditos_sin_cuenta.php`, `migraciones/20261022_016_tipos_entidad.php`, `componentes/personas_sin_cuenta.php`, `componentes/ingresos_cobro.php`, `mantenimientos/mantenimiento_ingresos_sendero.php`, `mantenimientos/mantenimiento_asistencia_senderos.php`, `componentes/balances_servicio.php`, `componentes/balances_registrar.php`, `componentes/balances_vista.php`, `css/balances.css`, `js/balances.js`, `js/ingresos_sendero.js`
- Migración: `20261022_015_creditos_sin_cuenta` y `20261022_016_tipos_entidad` (aplicadas en producción)
- Probado: no quedó una prueba funcional integrada registrada; al sincronizar local desde producción el 27/10 se identificó que falta validar el flujo completo junto con Usuarios sin cuenta.
- Producción: sí (2026-10-22, subida directa previa al protocolo de respaldos actual)
- Notas: v1.2.1 añadió después el selector de Personas sin cuenta al modal de Asistencia; ese alcance no corresponde a v1.2.0.

### v1.1.0 · 2026-10-22 · Claude · Usuarios sin cuenta
- Qué cambió: módulo nuevo **Usuarios sin cuenta** (fichas de personas inscritas a mano; crear, editar, inactivar si no tiene senderos, eliminar si está inactiva; **Reconciliar** con una cuenta y **Unir** fichas repetidas). La inscripción manual elige o crea una ficha; el Estado de cuenta agrupa a cada persona sin cuenta en una sola fila.
- Archivos: `componentes/personas_sin_cuenta.php`, `mantenimientos/mantenimiento_usuarios_sin_cuenta.php`, `procesos/proceso_usuarios_sin_cuenta.php`, `css/usuarios_sin_cuenta.css`, `js/usuarios_sin_cuenta.js`, `componentes/permisos.php`, `componentes/menu_panel.php`, `procesos/proceso_usuarios_senderos.php`, `mantenimientos/mantenimiento_usuarios_senderos.php`, `js/mantenimiento_usuarios_senderos.js`, `css/mantenimiento_usuarios_senderos.css`, `componentes/cuentas_usuarios.php`, `componentes/cuentas_usuarios_excel.php`, `mantenimientos/mantenimiento_cuentas_usuarios.php`
- Migración: `20261022_014_usuarios_sin_cuenta` (aplicada en producción)
- Producción: sí (2026-10-22)

### v1.0.0 · hasta 2026-10-21 · Claude y Codex · Punto de partida del registro
- Todo lo anterior a este archivo: Balances y cuentas (cuentas, asignación de cobros y gastos de senderos, cuadres, reversos masivos), cuadre de senderos con candado, filtros por cuadre en Ingresos/Gastos por sendero, Estado de cuenta por usuario, rediseño del panel y de varias pantallas, etc.
- Detalle fino: ver `git log` y los respaldos en `.private/`.
