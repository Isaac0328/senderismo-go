# Estado de cuenta por usuario

Acceso: Finanzas > Estado de cuenta por usuario, ruta `admin/cuentas-usuarios`.
Permiso de consulta: `finanzas.cuentas_usuarios`. Las correcciones se realizan
en Ingresos por sendero, con su permiso independiente.

## Alcance de las cifras

- Pagos y descuentos: valores acumulados actuales de cada inscripcion.
- Fechas de importes: fecha del sendero o fecha de pago, seleccionable.
- Sin fechas: incluye todo el historial, incluso registros sin fecha.
- Con rango por fecha de pago: excluye registros sin fecha de pago.
- Balance pendiente y credito disponible actuales: todas las fechas.
- Creditos y aplicaciones vigentes: todas las fechas.
- Bitacora de movimientos: usa la fecha propia del movimiento y el rango.
- Asistentes temporales: una cuenta por inscripcion; nunca se unen por nombre.
- Menores: su inversion forma parte de la cuenta del participante responsable.
- Cancelaciones: conservan los pagos, pero no generan deuda ni valor esperado.

Los importes se suman en centavos. Valor esperado = monto esperado registrado
menos descuento; balance = valor esperado menos dinero pagado y credito aplicado.
Cortesia/exento/no asistencia sin pago siguen las excepciones del sistema.
Los balances negativos no compensan las deudas de otros senderos automaticamente.
Efectivo retenido = max(pago - credito generado, 0).
Total ingreso = efectivo retenido + credito aplicado.

La tabla de pagos no conserva cada abono ni versiones anteriores. La bitacora
de credito existente puede repetir aplicaciones al guardar y no registra todas
las modificaciones/anulaciones antiguas. No se utiliza su suma para determinar
credito consumido: las aplicaciones vigentes salen de los registros de pagos.

## Produccion

Subir estos archivos respetando sus rutas:

- `componentes/cuentas_usuarios.php`
- `componentes/cuentas_usuarios_excel.php`
- `componentes/permisos.php`
- `componentes/menu_panel.php`
- `mantenimientos/mantenimiento_cuentas_usuarios.php`
- `procesos/proceso_exportar_cuentas_usuarios.php`
- `css/cuentas_usuarios.css`
- `js/cuentas_usuarios.js`
- `migraciones/20261010_007_cuentas_usuarios.php`

Aplicar la actualizacion `20261010_007_cuentas_usuarios` desde Actualizaciones
del sistema. Solo registra el permiso y el acceso del panel. No requiere SQL
adicional ni cambia registros financieros. La exportacion usa el `vendor`
existente de PhpSpreadsheet y los mismos filtros/calculos de la pantalla.

## Verificacion local

Ejecutar `C:\xampp\php\php.exe -d extension=zip herramientas/verificar_cuentas_usuarios.php`.
Comprueba reglas financieras, filtros, conciliacion del credito actual,
identidades separadas y creacion/lectura de un XLSX. No modifica pagos.
