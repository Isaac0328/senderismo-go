# Cuentas, bancos y tipos

Acceso: Finanzas > Balances y cuentas > Mantenimientos.

- Bancos: agregar, renombrar, activar e inactivar bancos.
- Tipos de cuenta: agregar y editar nombres y estado; comportamiento Banco, Efectivo u Otro.
- Cuentas: nombre y tipo. Banco exige titular, banco y número de cuenta (texto, conserva ceros) y no permite repetir el número dentro del mismo banco. En listados se muestran solo los últimos cuatro dígitos.
- Efectivo y Otro exigen un responsable elegido de Personal y entidades (empleados o directivos). La ubicación o descripción es opcional.
- Referencia/nota es opcional. Apertura y saldo inicial solo se registran al crear; no se alteran al editar.
- Un tipo utilizado no puede cambiar de comportamiento. Los catalogos inactivos pueden conservarse en las cuentas existentes, pero no elegirse en nuevas cuentas.
- No se borran bancos ni tipos; se inactivan para preservar referencias.

## Produccion

1. Respaldar archivos y base de datos antes de actualizar.
2. Subir primero 20261021_011_cuentas_bancos_tipos.php y 20261021_012_cuentas_responsable_unicidad.php a la carpeta migraciones/ de producción. No se deben editar las copias que ya estén aplicadas.
3. Entrar en admin/actualizaciones y aplicar ambas actualizaciones antes de reemplazar los archivos de Balances. Así no se interrumpe la pantalla.
4. Subir componentes/balances_servicio.php, componentes/balances_vista.php, mantenimientos/mantenimiento_balances.php, js/balances.js y css/balances.css.
5. Registrar los bancos que se utilizan y completar los datos de las cuentas anteriores. No se inventan titulares, bancos, números ni responsables a partir de notas antiguas.

Las actualizaciones crean fin_bancos y fin_cuenta_tipos, relacionan los tipos anteriores, agregan los datos de cuenta, vinculan el responsable a fin_terceros y protegen banco+número contra duplicados. No cambian saldos iniciales, moneda, fechas, movimientos ni cuadres. La columna técnica tipo se conserva sincronizada para compatibilidad con el módulo existente.

Prueba local (transaccion revertida): C:\xampp\php\php.exe herramientas/verificar_cuentas_tesoreria.php
